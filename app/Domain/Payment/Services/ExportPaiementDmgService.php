<?php

declare(strict_types=1);

namespace App\Domain\Payment\Services;

use App\Models\Internship\Stage;
use App\Services\TresorMoneyService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Génération des exports de la page DMG (état de paiement, attestations, fusion Trésor Pay,
 * canvas Excel), partagée entre le téléchargement synchrone et le job d'arrière-plan lancé
 * pour les mois volumineux.
 *
 * Le cœur de requête reste DmgService::attentePaiementDemarrage/Presence : ce service ne
 * fait que construire les fichiers à partir de la file déjà filtrée.
 */
class ExportPaiementDmgService
{
    public const DOSSIER = 'exports/paiements';

    public function __construct(
        private DmgService $service,
        private MultiDossierPdfService $multiDossierPdf,
        private TresorMoneyService $tresorMoney,
    ) {}

    /**
     * File DMG filtrée, prête à être exportée (identique pour le synchrone et l'asynchrone :
     * un seul cœur de requête pour démarrage et présence).
     *
     * @param  string  $nature  demarrage|presence
     * @param  array<string, mixed>  $filtres
     * @param  list<int>|null  $ids
     */
    public function paiementsPour(string $nature, string $mois, array $filtres, ?array $ids = null): Collection
    {
        $query = $nature === 'presence'
            ? $this->service->attentePaiementPresence($filtres, $mois)
            : $this->service->attentePaiementDemarrage($filtres, $mois);

        if ($ids) {
            $query->whereIn('paiements.id', $ids);
        }

        return $query->orderBy('paiements.id')->limit(DmgService::LIMITE_LISTE_ATTENTE)->get();
    }

    /**
     * Construit le PDF demandé (déjà configuré : contexte SSL, numérotation de page).
     *
     * @param  Collection<int, \App\Models\Payment\Paiement>  $paiements
     * @param  array<string, mixed>  $filtres
     */
    public function construirePdf(string $type, Collection $paiements, string $moisCode, array $filtres): mixed
    {
        $pdf = match ($type) {
            'etat_paiement' => $this->multiDossierPdf->construireEtatFinancier($paiements, $moisCode),
            'attestation_presence' => $this->multiDossierPdf->construireAttestation(
                $paiements,
                $this->sourceFinancementId($paiements, $filtres),
                $moisCode,
            ),
            'fusion_tresor' => $this->fusionTresor($paiements),
            default => $this->attestationDemarrage($paiements, $moisCode),
        };

        // L'état de paiement et l'attestation de présence sont déjà configurés (contexte SSL +
        // numérotation de page) par MultiDossierPdfService::construireEtatFinancier/Attestation :
        // reconfigurer ajouterait une seconde numérotation. Les deux autres types ne le sont pas.
        if (! in_array($type, ['etat_paiement', 'attestation_presence'], true)) {
            $this->configurerPdf($pdf);
        }

        return $pdf;
    }

    /**
     * Construit le classeur « Canvas TrésorPay » (export Excel).
     *
     * @param  Collection<int, \App\Models\Payment\Paiement>  $paiements
     */
    public function construireExcel(Collection $paiements, string $nature, string $moisCode): Spreadsheet
    {
        $classeur = new Spreadsheet;
        $feuille = $classeur->getActiveSheet();
        $feuille->setTitle('Canvas TrésorPay');

        $feuille->fromArray(['nom_beneficiaire', 'telephone_beneficiaire', 'montant_beneficiaire', 'moyen_paiement'], null, 'A1');
        $feuille->fromArray($this->lignesExcel($paiements), null, 'A2');

        $derniereLigne = $paiements->count() + 1;
        $plageEntetes = 'A1:D1';
        $feuille->getStyle($plageEntetes)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $feuille->getStyle($plageEntetes)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF087F5B');
        $feuille->setAutoFilter($plageEntetes);
        foreach (range('A', 'D') as $colonne) {
            $feuille->getColumnDimension($colonne)->setAutoSize(true);
        }

        return $classeur;
    }

    /**
     * Sauvegarde le PDF sur le disque temp et retourne le chemin relatif.
     */
    public function sauverPdf(mixed $pdf, string $batchId): string
    {
        $chemin = self::chemin($batchId, 'pdf');
        Storage::disk('temp_files')->makeDirectory(self::DOSSIER);
        $pdf->save(Storage::disk('temp_files')->path($chemin));

        return $chemin;
    }

    /**
     * Sauvegarde le classeur Excel sur le disque temp et retourne le chemin relatif.
     */
    public function sauverExcel(Spreadsheet $classeur, string $batchId): string
    {
        $chemin = self::chemin($batchId, 'xlsx');
        Storage::disk('temp_files')->makeDirectory(self::DOSSIER);
        (new Xlsx($classeur))->save(Storage::disk('temp_files')->path($chemin));

        return $chemin;
    }

    public static function chemin(string $batchId, string $extension): string
    {
        return self::DOSSIER.'/'.$batchId.'.'.$extension;
    }

    /** @param  Collection<int, \App\Models\Payment\Paiement>  $paiements */
    private function fusionTresor(Collection $paiements): mixed
    {
        $stages = Stage::query()
            ->whereIn('id', $paiements
                ->map(fn ($paiement) => $paiement->droitPaiement?->stage_id)
                ->filter()
                ->unique()
                ->values())
            ->get();

        return $this->tresorMoney->genererFichierTresorMoney($stages);
    }

    /**
     * Attestation de démarrage : la vue historique `pdf.dmg-paiements`, conservée telle quelle.
     *
     * @param  Collection<int, \App\Models\Payment\Paiement>  $paiements
     */
    private function attestationDemarrage(Collection $paiements, string $moisCode): mixed
    {
        $mois = \Carbon\Carbon::createFromFormat('Y-m', $moisCode)->locale('fr')->translatedFormat('F Y');

        return Pdf::loadView('pdf.dmg-paiements', [
            'paiements' => $paiements,
            'titre' => 'Attestations de demarrage',
            'type' => 'attestation_demarrage',
            'mois' => $mois,
        ])->setPaper('a4', 'portrait');
    }

    /**
     * Code de la source de financement qui pilote l'en-tête de l'attestation de présence : le
     * filtre quand il est posé (comme le legacy qui choisissait la vue depuis typesfinancement_id),
     * sinon la source dominante de la sélection (premier paiement trié par id).
     *
     * @param  Collection<int, \App\Models\Payment\Paiement>  $paiements
     * @param  array<string, mixed>  $filtres
     */
    private function sourceFinancementId(Collection $paiements, array $filtres): ?int
    {
        $financementId = $filtres['source_financement_id'] ?? null;

        if ($financementId !== null && $financementId !== '') {
            return (int) $financementId;
        }

        return $paiements->first()?->droitPaiement?->stage?->source_financement_id;
    }

    /** @param  Collection<int, \App\Models\Payment\Paiement>  $paiements */
    private function lignesExcel(Collection $paiements): array
    {
        return $paiements->map(function ($paiement): array {
            $stage = $paiement->droitPaiement?->stage;
            $beneficiaire = $stage?->beneficiaire;
            $numeroTm = $beneficiaire?->numero_tresor_money;

            return [
                trim(($beneficiaire?->nom ?? '').' '.($beneficiaire?->prenoms ?? '')),
                // Le legacy exporte le numéro YUP sans son premier caractère (substr(numero_yup, 1)).
                is_string($numeroTm) && $numeroTm !== '' ? substr($numeroTm, 1) : ($numeroTm ?? ''),
                (int) round((float) $paiement->montant),
                'tm',
            ];
        })->values()->all();
    }

    private function configurerPdf($pdf): void
    {
        $pdf->getDomPDF()->setHttpContext(
            stream_context_create([
                'ssl' => [
                    'allow_self_signed' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ])
        );
        $pdf->output();
        $canvas = $pdf->get_canvas();
        $canvas->page_text(10, $canvas->get_height() - 20, 'P. {PAGE_NUM} / {PAGE_COUNT}', null, 10, [0, 0, 0]);
    }
}