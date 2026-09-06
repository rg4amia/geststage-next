<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Domain\Payment\Services\ExportPaiementDmgService;
use App\Http\Controllers\Controller;
use App\Jobs\GenererExportPaiementJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ExportPaiementDmgController extends Controller
{
    public function __construct(
        private DmgService $service,
        private ExportPaiementDmgService $exportService,
    ) {}

    /**
     * Téléchargement synchrone (petite sélection). Les mois entiers passent par `lancer()` :
     * la génération synchrone sort du temps de réponse HTTP au-delà de ~2 000 lignes.
     */
    public function __invoke(Request $request): Response
    {
        $data = $this->validerExport($request);
        $filters = $this->filtres($request);
        $nature = $this->nature($data['type'], $request);

        $paiements = $this->exportService->paiementsPour($nature, $data['mois'], $filters, $data['ids'] ?? null);

        abort_if($paiements->isEmpty(), 404, 'Aucun paiement eligible pour cet export.');

        $pdf = $this->exportService->construirePdf($data['type'], $paiements, $data['mois'], $filters);

        return $pdf->download($this->nomFichier($data['type'], $data['mois'], $nature));
    }

    /**
     * Export Excel « Canvas TrésorPay » synchrone : liste plate des bénéficiaires et de leur
     * numéro Trésor Money pour alimenter la saisie des dépenses Trésor Pay.
     *
     * Équivalent legacy : PaiementExport (nom_beneficiaire, telephone_beneficiaire [numéro TM
     * sans son premier caractère], montant_beneficiaire, moyen_paiement='tm'). Quatre colonnes
     * conservées telles quelles : le fichier est consommé en aval par la plateforme Trésor Pay.
     */
    public function excel(Request $request): Response
    {
        $data = $request->validate([
            'mois' => ['required', 'date_format:Y-m', 'exists:periodes,code'],
            'nature' => ['nullable', 'in:demarrage,presence'],
            'ids' => ['nullable', 'array', 'max:'.DmgService::LIMITE_LISTE_ATTENTE],
            'ids.*' => ['integer', 'distinct'],
        ]);
        $nature = $data['nature'] ?? 'demarrage';
        $paiements = $this->exportService->paiementsPour($nature, $data['mois'], $this->filtres($request), $data['ids'] ?? null);

        abort_if($paiements->isEmpty(), 404, 'Aucun paiement eligible pour cet export.');

        $classeur = $this->exportService->construireExcel($paiements, $nature, $data['mois']);
        $horodatage = now()->format('Y-m-d_H-i-s');
        $nomFichier = "canvas_beneficiaires_tresorpay_depenses_{$nature}_du_{$data['mois']}_{$horodatage}.xlsx";
        $redacteur = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($classeur);

        return response()->streamDownload(function () use ($redacteur): void {
            $redacteur->save('php://output');
        }, $nomFichier, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Lance la génération d'un export en arrière-plan, pour les mois dont le volume dépasse le
     * temps de réponse HTTP. Le batch qui porte le job sert de suivi (progression / disponibilité).
     */
    public function lancer(Request $request): JsonResponse
    {
        $data = $this->validerExport($request);

        $batch = Bus::batch([
            new GenererExportPaiementJob(
                type: $data['type'],
                mois: $data['mois'],
                nature: $this->nature($data['type'], $request),
                filtres: $this->filtres($request),
                ids: $data['ids'] ?? null,
                demandeParId: Auth::id(),
            ),
        ])->name('export-paiements:'.$data['type'].':'.$data['mois'].':'.$this->nature($data['type'], $request))->dispatch();

        return response()->json([
            'batch_id' => $batch->id,
            'message' => 'Export lancé en arrière-plan. Vous serez notifié quand le fichier sera prêt.',
        ]);
    }

    /**
     * Avancement d'un export lancé en arrière-plan.
     */
    public function progression(string $batchId): JsonResponse
    {
        $batch = Bus::findBatch($batchId);

        if (! $batch) {
            return response()->json(['message' => 'Export introuvable.'], 404);
        }

        $type = $this->typeDuBatch($batch->name);
        $extension = $type === 'excel' ? 'xlsx' : 'pdf';

        return response()->json([
            'id' => $batch->id,
            'type' => $type,
            'mois' => $this->moisDuBatch($batch->name),
            'progress' => $batch->progress(),
            'completed' => $batch->finished(),
            'failedJobs' => $batch->failedJobs,
            'disponible' => $batch->finished()
                && $batch->failedJobs === 0
                && Storage::disk('temp_files')->exists(ExportPaiementDmgService::chemin($batch->id, $extension)),
        ]);
    }

    /**
     * Télécharge le fichier produit par un export en arrière-plan.
     */
    public function telechargement(string $batchId): BinaryFileResponse
    {
        $batch = Bus::findBatch($batchId);

        abort_if($batch === null, 404, 'Export introuvable.');

        $type = $this->typeDuBatch($batch->name);
        $mois = $this->moisDuBatch($batch->name) ?? now()->format('Y-m');
        $extension = $type === 'excel' ? 'xlsx' : 'pdf';
        $chemin = ExportPaiementDmgService::chemin($batchId, $extension);

        abort_unless(Storage::disk('temp_files')->exists($chemin), 404, "L'export n'est pas encore disponible.");

        return response()->download(
            Storage::disk('temp_files')->path($chemin),
            $this->nomFichier($type, $mois, $extension === 'xlsx' ? $this->natureDuBatch($batch->name) : '')
        );
    }

    /** @return array{type: string, mois: string, ids?: array<int>} */
    private function validerExport(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:etat_paiement,attestation_demarrage,attestation_presence,fusion_tresor,excel'],
            'mois' => ['required', 'date_format:Y-m', 'exists:periodes,code'],
            'ids' => ['nullable', 'array', 'max:'.DmgService::LIMITE_LISTE_ATTENTE],
            // Un identifiant inconnu est de toute façon écarté par le `whereIn` ci-dessous :
            // inutile de payer une requête `exists` par ligne sur un export de tout un mois.
            'ids.*' => ['integer', 'distinct'],
        ]);
    }

    /** @return array<string, mixed> */
    private function filtres(Request $request): array
    {
        return $request->only(['agence_id', 'entreprise_id', 'source_financement_id', 'type_stage_id', 'type_structure_id', 'date_debut', 'date_fin', 'date_validation_debut', 'date_validation_fin', 'search', 'dossier_physique']);
    }

    /**
     * L'attestation de présence ne se génère que sur la file présence ; l'export Excel accepte
     * la nature explicite ; tout le reste suit la nature demandée (démarrage par défaut).
     */
    private function nature(string $type, Request $request): string
    {
        if ($type === 'attestation_presence') {
            return 'presence';
        }

        return $request->string('nature', 'demarrage')->toString();
    }

    private function typeDuBatch(?string $nom): string
    {
        $parties = explode(':', (string) $nom);

        return $parties[1] ?? '';
    }

    private function moisDuBatch(?string $nom): ?string
    {
        $parties = explode(':', (string) $nom);

        return $parties[2] ?? null;
    }

    private function natureDuBatch(?string $nom): string
    {
        $parties = explode(':', (string) $nom);

        return $parties[3] ?? 'demarrage';
    }

    private function nomFichier(string $type, string $mois, string $nature): string
    {
        return match ($type) {
            'etat_paiement' => "etat-paiement-{$mois}.pdf",
            'attestation_presence' => "attestation-presence-{$mois}.pdf",
            'attestation_demarrage' => "attestations-demarrage-{$mois}.pdf",
            'fusion_tresor' => "fusion-tresor-pay-{$mois}.pdf",
            default => "canvas_beneficiaires_tresorpay_depenses_{$nature}_du_{$mois}.xlsx",
        };
    }
}