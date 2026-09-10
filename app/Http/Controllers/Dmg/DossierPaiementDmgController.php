<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Domain\Payment\Services\MultiDossierPdfService;
use App\Http\Controllers\Controller;
use App\Jobs\ValiderPaiementsDmgJob;
use App\Models\Payment\DossierGroupe;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DossierPaiementDmgController extends Controller
{
    public function __construct(
        private DmgService $service,
        private MultiDossierPdfService $pdfService,
    ) {}

    public function generer(Request $request): RedirectResponse
    {
        // Valider « toute la liste » de présence porte sur le mois entier : plafond aligné sur
        // la liste et `exists` par élément laissé au contrôle groupé de genererDossiersPaiement().
        $data = $request->validate(['periode_id' => ['required', 'integer', 'exists:periodes,id'], 'paiement_ids' => ['required', 'array', 'min:1', 'max:'.DmgService::LIMITE_LISTE_ATTENTE], 'paiement_ids.*' => ['integer', 'distinct']]);
        $dossiers = $this->service->genererDossiersPaiement($data['periode_id'], $data['paiement_ids'], $request->user());

        return back()->with('success', $dossiers->count().' dossier(s) genere(s).');
    }

    public function validerWorkflow(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mois' => ['required', 'date_format:Y-m', 'exists:periodes,code'],
            'nature' => ['required', 'in:demarrage,presence'],
            'keyword' => ['nullable', 'in:valider,valider-select,annuler-selection,annuler-tous'],
            'datas' => ['nullable', 'array', 'max:'.DmgService::LIMITE_LISTE_ATTENTE],
            'datas.*' => ['integer', 'distinct'],
            'observation' => ['nullable', 'string', 'max:1000'],
            'agence_id' => ['nullable', 'integer'],
            'entreprise_id' => ['nullable', 'integer'],
            'source_financement_id' => ['nullable', 'integer'],
            'typesfinancement_id' => ['nullable', 'integer'],
            'type_stage_id' => ['nullable', 'integer'],
            'typestages_id' => ['nullable', 'integer'],
            'type_structure_id' => ['nullable', 'integer'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
            'date_debut_start' => ['nullable', 'date'],
            'date_debut_end' => ['nullable', 'date'],
            'date_validation_debut' => ['nullable', 'date'],
            'date_validation_fin' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'dossier_physique' => ['nullable', 'string', 'max:50'],
            'dossier_identifiant' => ['nullable', 'string', 'max:100'],
            'cohorte' => ['nullable', 'in:global,cohorte1,cohorte2,cohorte3'],
        ]);

        $keyword = $data['keyword'] ?? 'valider';
        $selection = in_array($keyword, ['valider-select', 'annuler-selection'], true);
        $action = in_array($keyword, ['annuler-selection', 'annuler-tous'], true) ? 'ajourner' : 'valider';
        $idsDemandes = array_values(array_unique(array_map('intval', $data['datas'] ?? [])));

        if ($selection && $idsDemandes === []) {
            throw ValidationException::withMessages([
                'datas' => 'Aucun paiement sélectionné.',
            ]);
        }

        $periode = Periode::query()->where('code', $data['mois'])->firstOrFail();
        $query = $this->requeteValidationPaiements($data);

        if ($selection) {
            $query->whereIn('paiements.id', $idsDemandes);
        }

        $paiementIds = $query
            ->orderBy('paiements.id')
            ->limit(DmgService::LIMITE_LISTE_ATTENTE)
            ->pluck('paiements.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        if ($paiementIds === [] || ($selection && count($paiementIds) !== count($idsDemandes))) {
            throw ValidationException::withMessages([
                'datas' => 'La sélection ne contient aucun paiement encore éligible.',
            ]);
        }

        $batch = Bus::batch([
            new ValiderPaiementsDmgJob(
                periodeId: (int) $periode->id,
                paiementIds: $paiementIds,
                action: $action,
                observation: $data['observation'] ?? null,
                auteurId: (int) $request->user()->id,
            ),
        ])->name("validation-paiements-dmg:{$action}:{$data['nature']}:{$data['mois']}")->dispatch();

        return response()->json([
            'batch_id' => $batch->id,
            'numero_dossier' => null,
            'paiements_count' => count($paiementIds),
            'action' => $action,
        ]);
    }

    public function progressionValidation(string $batchId): JsonResponse
    {
        $batch = Bus::findBatch($batchId);

        if (! $batch) {
            return response()->json(['message' => 'Batch introuvable.'], 404);
        }

        return response()->json([
            'id' => $batch->id,
            'name' => $batch->name,
            'totalJobs' => $batch->totalJobs,
            'pendingJobs' => $batch->pendingJobs,
            'failedJobs' => $batch->failedJobs,
            'processedJobs' => $batch->processedJobs(),
            'progress' => $batch->progress(),
            'finished' => $batch->finished(),
            'failureMessage' => $this->messageErreurBatch($batch->failedJobIds),
            'dossiers' => $this->dossiersDuBatch($batch->id),
        ]);
    }

    /**
     * @param  array<int, string>|null  $failedJobIds
     */
    private function messageErreurBatch(?array $failedJobIds): ?string
    {
        if ($failedJobIds === null || $failedJobIds === []) {
            return null;
        }

        $exception = DB::table('failed_jobs')
            ->where('uuid', $failedJobIds[0])
            ->value('exception');

        if (! is_string($exception) || $exception === '') {
            return null;
        }

        $premiereLigne = strtok($exception, "\n") ?: $exception;
        $message = preg_replace('/^.*Exception:\s*/', '', $premiereLigne) ?? $premiereLigne;
        $message = preg_replace('/\s+in\s+\/.*$/', '', $message) ?? $message;

        return trim($message) !== '' ? trim($message) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dossiersDuBatch(string $batchId): array
    {
        return DossierPaiement::query()
            ->where('validation_batch_id', $batchId)
            ->orderBy('id')
            ->get()
            ->map(fn (DossierPaiement $dossier) => [
                'id' => $dossier->id,
                'numero' => $dossier->numero,
                'nature' => $dossier->nature,
                'attestation_url' => $dossier->attestation_path
                    ? route('dmg.paiements.dossiers.download_attestation', $dossier)
                    : null,
                'etat_paiement_url' => $dossier->etat_financier_path
                    ? route('dmg.paiements.dossiers.download_etat_financier', $dossier)
                    : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requeteValidationPaiements(array $data): Builder
    {
        $filters = $this->filtresValidation($data);
        $query = $data['nature'] === 'presence'
            ? $this->service->attentePaiementPresence($filters, $data['mois'])
            : $this->service->attentePaiementDemarrage($filters, $data['mois']);

        if ($data['nature'] === 'demarrage') {
            $query = $this->service->applyCohorteFilter($query, $data['cohorte'] ?? 'global');
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filtresValidation(array $data): array
    {
        return [
            'agence_id' => $data['agence_id'] ?? null,
            'entreprise_id' => $data['entreprise_id'] ?? null,
            'source_financement_id' => $data['source_financement_id'] ?? $data['typesfinancement_id'] ?? null,
            'type_stage_id' => $data['type_stage_id'] ?? $data['typestages_id'] ?? null,
            'type_structure_id' => $data['type_structure_id'] ?? null,
            'date_debut' => $data['date_debut'] ?? $data['date_debut_start'] ?? null,
            'date_fin' => $data['date_fin'] ?? $data['date_debut_end'] ?? null,
            'date_validation_debut' => $data['date_validation_debut'] ?? null,
            'date_validation_fin' => $data['date_validation_fin'] ?? null,
            'search' => $data['search'] ?? null,
            'dossier_physique' => $data['dossier_physique'] ?? null,
        ];
    }

    public function transmettre(DossierPaiement $dossier): RedirectResponse
    {
        $this->service->transmettreDossierCb($dossier);

        return back()->with('success', 'Dossier transmis au CB.');
    }

    public function grouper(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'periode_id' => ['required', 'integer', 'exists:periodes,id'],
            'dossiers' => ['required', 'array', 'min:2', 'max:100'],
            'dossiers.*' => ['integer', 'distinct', 'exists:dossiers_paiement,id'],
            'observation' => ['nullable', 'string', 'max:1000'],
        ]);

        $groupe = $this->service->grouperDossiers(
            $data['periode_id'],
            $data['dossiers'],
            $data['observation'] ?? null,
            $request->user(),
        );

        return back()->with('success', "Multi-dossier {$groupe->numero} cree.");
    }

    public function transmettreGroupe(DossierGroupe $groupe): RedirectResponse
    {
        $this->service->transmettreGroupeCb($groupe);

        return back()->with('success', 'Multi-dossier transmis au CB.');
    }

    public function retirerDuGroupe(Request $request, DossierGroupe $groupe): RedirectResponse
    {
        $data = $request->validate([
            'dossier_id' => ['required', 'integer', 'exists:dossiers_paiement,id'],
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $this->service->retirerDossierGroupe(
            $groupe,
            DossierPaiement::findOrFail($data['dossier_id']),
            $data['motif'],
        );

        return back()->with('success', 'Dossier retire du multi-dossier.');
    }

    public function retirer(Request $request): RedirectResponse
    {
        $data = $request->validate(['dossier_id' => ['required', 'exists:dossiers_paiement,id'], 'paiement_id' => ['required', 'exists:paiements,id'], 'motif' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->service->retirerPaiementDossier(DossierPaiement::findOrFail($data['dossier_id']), Paiement::findOrFail($data['paiement_id']), $data['motif'], $request->user());

        return back()->with('success', 'Paiement retire du dossier.');
    }

    public function genererPdfs(DossierGroupe $groupe): RedirectResponse
    {
        $groupe = $this->pdfService->genererPdfs($groupe);

        return back()->with('success', 'PDFs generes pour le multi-dossier '.$groupe->numero.'.');
    }

    public function downloadAttestation(DossierGroupe $groupe): Response
    {
        if (! $groupe->attestation_path || ! Storage::disk('temp_files')->exists($groupe->attestation_path)) {
            abort(404, 'Fichier introuvable.');
        }

        $filePath = Storage::disk('temp_files')->path($groupe->attestation_path);

        return response()->download($filePath, basename($groupe->attestation_path));
    }

    public function downloadEtatFinancier(DossierGroupe $groupe): Response
    {
        if (! $groupe->etat_financier_path || ! Storage::disk('temp_files')->exists($groupe->etat_financier_path)) {
            abort(404, 'Fichier introuvable.');
        }

        $filePath = Storage::disk('temp_files')->path($groupe->etat_financier_path);

        return response()->download($filePath, basename($groupe->etat_financier_path));
    }

    /**
     * Régénération a posteriori pour un dossier simple : attestation de présence, générée à la
     * volée depuis les paiements actifs du dossier (équivalent legacy
     * generateAttestationPresenceFromDossier).
     */
    public function downloadAttestationDossier(DossierPaiement $dossier): Response
    {
        if ($dossier->attestation_path && Storage::disk('temp_files')->exists($dossier->attestation_path)) {
            return response()->download(
                Storage::disk('temp_files')->path($dossier->attestation_path),
                basename($dossier->attestation_path)
            );
        }

        $paiements = $this->paiementsActifsDuDossier($dossier);
        if ($paiements->isEmpty()) {
            throw new NotFoundHttpException('Aucun paiement actif dans ce dossier.');
        }

        $moisCode = $dossier->periode?->code ?? now()->format('Y-m');
        $pdf = $this->pdfService->construireAttestation(
            $paiements,
            $dossier->source_financement_id,
            $moisCode,
            $dossier->nature === 'DM' ? 'demarrage' : 'presence',
            $dossier->valideur_initiales,
            $dossier->numero,
        );

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, 'attestation-presence-'.$dossier->numero.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * Régénération a posteriori pour un dossier simple : état financier (état de paiement),
     * généré à la volée (équivalent legacy generateEtatFinancierFromDossier).
     */
    public function downloadEtatFinancierDossier(DossierPaiement $dossier): Response
    {
        if ($dossier->etat_financier_path && Storage::disk('temp_files')->exists($dossier->etat_financier_path)) {
            return response()->download(
                Storage::disk('temp_files')->path($dossier->etat_financier_path),
                basename($dossier->etat_financier_path)
            );
        }

        $paiements = $this->paiementsActifsDuDossier($dossier);
        if ($paiements->isEmpty()) {
            throw new NotFoundHttpException('Aucun paiement actif dans ce dossier.');
        }

        $moisCode = $dossier->periode?->code ?? now()->format('Y-m');
        $pdf = $this->pdfService->construireEtatFinancier(
            $paiements,
            $moisCode,
            $dossier->source_financement_id,
            $dossier->valideur_initiales,
            $dossier->numero,
        );

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, 'etat-paiement-'.$dossier->numero.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    /**
     * Paiements actifs (ligne non retirée) d'un dossier, avec les relations nécessaires aux vues PDF.
     */
    private function paiementsActifsDuDossier(DossierPaiement $dossier)
    {
        return Paiement::query()
            ->with([
                'droitPaiement.stage.beneficiaire',
                'droitPaiement.stage.entreprise.typeStructure',
                'droitPaiement.stage.agence',
                'droitPaiement.stage.sourceFinancement',
                'droitPaiement.stage.typeStage',
                'droitPaiement.stage.contrats',
            ])
            ->whereHas('dossiersPaiement', fn ($q) => $q
                ->where('dossiers_paiement.id', $dossier->id)
                ->whereNull('lignes_dossiers_paiement.retire_le'))
            ->orderBy('paiements.id')
            ->limit(DmgService::LIMITE_LISTE_ATTENTE)
            ->get();
    }
}
