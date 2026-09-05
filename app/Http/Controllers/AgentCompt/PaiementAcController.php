<?php

namespace App\Http\Controllers\AgentCompt;

use App\Domain\Payment\Services\AgentComptableService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PaiementAcController extends Controller
{
    /** Onglets stagiaires d'une OP, calqués sur ceux de l'écran legacy. */
    private const ONGLETS_STAGIAIRES = ['attente', 'valide', 'paye', 'non_paye', 'rejete', 'differe'];

    /** Plafond d'extraction des onglets globaux, aligné sur les exports DMG. */
    private const LIMITE_EXPORT = 5000;

    public function __construct(private AgentComptableService $acService) {}

    public function index(Request $request): Response
    {
        $mois = (string) $request->query('mois', Carbon::now()->format('Y-m'));
        $periode = Periode::query()->where('code', $mois)->first();
        $periodesDisponibles = $this->periodesDisponibles();

        if (! $periode) {
            return Inertia::render('AgentComptable/Paiements/Index', [
                'bordereauxAttente' => [],
                'bordereauxRejetes' => [],
                'bordereauxVises' => [],
                'operationsRejetees' => [],
                'ordresRejetes' => [],
                'statutPaiements' => [],
                'statutCompteurs' => ['payes' => 0, 'nonPayes' => 0],
                'moisActuel' => $mois,
                'periode' => null,
                'periodesDisponibles' => $periodesDisponibles,
                'vueActuelle' => $this->vueActuelle($request),
                'sousOngletActuel' => $this->sousOngletActuel($request),
            ]);
        }

        $baseQuery = BordereauPaiement::query()
            ->where('periode_id', $periode->id)
            ->with([
                'sourceFinancement',
                'ordresPaiement.dossiersPaiement.agence',
                'ordresPaiement.dossiersPaiement.paiementsActifs',
                'ordresPaiement.dossiersPaiement.paiementsActifs.decisions',
            ])
            ->orderByDesc('created_at');

        // Équivalent canonique du legacy : bordereau pending contenant au moins
        // un paiement DMG/CB qui n'a pas encore été validé par l'AC.
        $attente = (clone $baseQuery)
            ->where('statut', 'TRANSMIS_AC')
            ->whereHas('ordresPaiement.dossiersPaiement.paiementsActifs', function (Builder $query): void {
                $query->whereNotIn('paiements.statut', ['VALIDE_AC', 'REJETE_DEFINITIF']);
            })
            ->get()
            ->map(fn (BordereauPaiement $bordereau): array => $this->toRow($bordereau));

        $rejetes = (clone $baseQuery)
            ->whereIn('statut', ['REJETE_AC', 'REJETE_AC_DEFINITIF'])
            ->get()
            ->map(fn (BordereauPaiement $bordereau): array => $this->toRow($bordereau));

        // Legacy `status-validation` : OP déjà visées AC, avec paiements à confirmer,
        // payés ou non payés. Le bordereau parent peut encore être TRANSMIS_AC si
        // toutes ses OP ne sont pas déjà clôturées.
        $statutPaiements = $this->bordereauxAvecOrdres(
            $periode,
            ['VISE_AC'],
            ['VALIDE_AC', 'PAYE', 'NON_PAYE'],
        );

        // Legacy `operation-rejete` : OP rejetées par l'AC, même quand le
        // bordereau parent contient encore d'autres OP en traitement.
        $operationsRejetees = $this->bordereauxAvecOrdres(
            $periode,
            ['REJETE_AC', 'REJETE_AC_DEFINITIF'],
        );

        // Compteurs globaux des paiements déjà tranchés (onglets « Payés » et
        // « Non payés » de la rubrique Status paiement). Les lignes elles-mêmes
        // sont chargées à la demande par `paiementsStatuts`.
        $statutCompteurs = [
            'payes' => $this->comptePaiementsSituation($periode, 'PAYE'),
            'nonPayes' => $this->comptePaiementsSituation($periode, 'NON_PAYE'),
        ];

        return Inertia::render('AgentComptable/Paiements/Index', [
            'bordereauxAttente' => $attente,
            'bordereauxRejetes' => $rejetes,
            'bordereauxVises' => $statutPaiements,
            'operationsRejetees' => $operationsRejetees,
            // Alias conservés pour les consommateurs Inertia existants.
            'ordresRejetes' => $operationsRejetees,
            'statutPaiements' => $statutPaiements,
            'statutCompteurs' => $statutCompteurs,
            'moisActuel' => $mois,
            'periode' => $periode,
            'periodesDisponibles' => $periodesDisponibles,
            'vueActuelle' => $this->vueActuelle($request),
            'sousOngletActuel' => $this->sousOngletActuel($request),
        ]);
    }

    public function statusValidation(Request $request): RedirectResponse
    {
        return $this->redirigerVersIndex($request, 'statuts');
    }

    public function operationRejete(Request $request): RedirectResponse
    {
        return $this->redirigerVersIndex($request, 'operations_rejetees');
    }

    public function viser(int $id): RedirectResponse
    {
        $this->acService->viserBordereau($this->bordereauATraiter($id));

        return back()->with('success', 'Bordereau visé avec succès.');
    }

    public function ajourner(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $this->acService->ajournerBordereau($this->bordereauATraiter($id), $data['motif']);

        return back()->with('success', 'Bordereau différé et retourné à la DMG.');
    }

    public function rejeter(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $this->acService->rejeterBordereau($this->bordereauATraiter($id), $data['motif']);

        return back()->with('success', 'Bordereau rejeté définitivement.');
    }

    /**
     * Détail d'une OP : ses dossiers et ses stagiaires, répartis dans les quatre onglets de
     * l'écran legacy `wait-to-generate-bordereau` (OP en attente / validé / rejeté / différé)
     * et filtrables comme sa barre de recherche.
     */
    public function ordreDetails(Request $request, OrdrePaiement $ordre): JsonResponse
    {
        $ordre->load([
            'bordereau:id,numero,statut',
            'dossiersPaiement.agence',
            'dossiersPaiement.sourceFinancement',
            'dossiersPaiement.groupes',
            'dossiersPaiement.paiementsActifs.droitPaiement.stage.beneficiaire',
            'dossiersPaiement.paiementsActifs.droitPaiement.stage.entreprise',
            'dossiersPaiement.paiementsActifs.droitPaiement.stage.typeStage',
        ]);

        $onglet = (string) $request->query('onglet', self::ONGLETS_STAGIAIRES[0]);
        if (! in_array($onglet, self::ONGLETS_STAGIAIRES, true)) {
            $onglet = self::ONGLETS_STAGIAIRES[0];
        }

        $filtres = [
            'agence_id' => $request->query('agence_id'),
            'entreprise_id' => $request->query('entreprise_id'),
            'source_financement_id' => $request->query('source_financement_id'),
            'type_stage_id' => $request->query('type_stage_id'),
            'dossier_id' => $request->query('dossier_id'),
            'multi_dossier_id' => $request->query('multi_dossier_id'),
            'date_debut' => $request->query('date_debut'),
            'date_fin' => $request->query('date_fin'),
            'recherche' => trim((string) $request->query('recherche', '')),
        ];

        $compteurs = array_fill_keys(self::ONGLETS_STAGIAIRES, 0);
        $referentiels = ['agences' => [], 'entreprises' => [], 'sources_financement' => [], 'types_stage' => []];
        $dossiersOptions = [];
        $multiDossiersOptions = [];
        $dossiers = [];
        foreach ($ordre->dossiersPaiement as $dossier) {
            $lignes = [];

            foreach ($dossier->paiementsActifs as $paiement) {
                $stage = $paiement->droitPaiement?->stage;

                $compteurs[$this->ongletDuPaiement($paiement)]++;

                if ($dossier->agence) {
                    $referentiels['agences'][$dossier->agence->id] = $dossier->agence->nom;
                }
                if ($dossier->sourceFinancement) {
                    $referentiels['sources_financement'][$dossier->sourceFinancement->id] = $this->libelleSource($dossier->sourceFinancement);
                }
                if ($stage?->entreprise) {
                    $referentiels['entreprises'][$stage->entreprise->id] = $stage->entreprise->raison_sociale;
                }
                if ($stage?->typeStage) {
                    $referentiels['types_stage'][$stage->typeStage->id] = $stage->typeStage->nom;
                }

                $dossiersOptions[$dossier->id] = [
                    'id' => (int) $dossier->id,
                    'libelle' => $dossier->numero,
                    'nombre_paiements' => $dossier->paiementsActifs->count(),
                    'montant_total' => $dossier->montant_total,
                ];

                foreach ($dossier->groupes as $groupe) {
                    $multiDossiersOptions[$groupe->id] = [
                        'id' => (int) $groupe->id,
                        'libelle' => $groupe->numero,
                        'nombre_dossiers' => $groupe->dossiers_count ?? null,
                    ];
                }

                if ($this->ongletDuPaiement($paiement) !== $onglet || ! $this->correspondAuxFiltres($dossier, $stage, $filtres)) {
                    continue;
                }

                $lignes[] = $this->ligneStagiaire($paiement, $stage);
            }

            if ($lignes === []) {
                continue;
            }

            $dossiers[] = [
                'id' => $dossier->id,
                'numero' => $dossier->numero,
                'statut' => $dossier->statut,
                'montant_total' => $dossier->montant_total,
                'date_creation' => $dossier->created_at?->format('d/m/Y'),
                'agence' => $dossier->agence?->nom,
                'source_financement' => $this->libelleSource($dossier->sourceFinancement),
                'stagiaires' => $lignes,
            ];
        }

        return response()->json([
            'id' => $ordre->id,
            'numero' => $ordre->numero,
            'statut' => $ordre->statut,
            'montant_total' => $ordre->montant_total,
            'bordereau' => $ordre->bordereau?->only(['id', 'numero', 'statut']),
            'onglet' => $onglet,
            'compteurs' => $compteurs,
            'actions' => $this->actionsPossibles($ordre, $compteurs),
            'referentiels' => array_map(
                static fn (array $options): array => collect($options)
                    ->map(static fn (?string $libelle, int|string $id): array => ['id' => (int) $id, 'libelle' => $libelle ?? '—'])
                    ->sortBy('libelle')
                    ->values()
                    ->all(),
                $referentiels,
            ),
            'dossiers_options' => collect($dossiersOptions)->sortBy('libelle')->values()->all(),
            'multi_dossiers_options' => collect($multiDossiersOptions)->sortBy('libelle')->values()->all(),
            'dossiers' => $dossiers,
        ]);
    }

    /**
     * URLs de prévisualisation des pièces jointes légacy d'un stage (CNI, Trésor Money,
     * contrat, attestation). Les onglets absents reviennent à null.
     */
    public function piecesStage(Request $request): JsonResponse
    {
        $request->validate(['stage_id' => ['required', 'integer']]);
        $stageId = (int) $request->input('stage_id');
        $types = ['cni', 'tresor', 'contrat', 'attestation'];

        return response()->json([
            'stage_id' => $stageId,
            'pieces' => collect($types)->mapWithKeys(function (string $type) use ($stageId): array {
                $fichier = $this->cheminPieceAbsolu($stageId, $type);

                return [$type => $fichier !== null
                    ? route('ac.paiements.piece', ['stage_id' => $stageId, 'type' => $type])
                    : null];
            })->all(),
        ]);
    }

    /**
     * Streame la pièce jointe légacy demandée, en restreignant la lecture au disque
     * `piecejusticatif` configuré (chemin relatif issu du snapshot de migration).
     */
    public function streamPiece(Request $request): BinaryFileResponse
    {
        $request->validate([
            'stage_id' => ['required', 'integer'],
            'type' => ['required', 'in:cni,tresor,contrat,attestation'],
        ]);

        $fichier = $this->cheminPieceAbsolu((int) $request->input('stage_id'), (string) $request->input('type'));

        if ($fichier === null) {
            abort(404, 'Pièce jointe introuvable.');
        }

        return response()->file($fichier);
    }

    /**
     * Liste paginée des paiements déjà payés ou marqués non payés pour un mois,
     * transposition des deux onglets globaux de l'écran legacy `status-validation`
     * (« Paiement valider » / « Paiement refuser »).
     */
    public function paiementsStatuts(Request $request): JsonResponse
    {
        $mois = (string) $request->query('mois', Carbon::now()->format('Y-m'));
        $situation = (string) $request->query('situation', 'paye');
        abort_unless(in_array($situation, ['paye', 'non_paye'], true), 422, 'Situation invalide.');

        $periode = Periode::query()->where('code', $mois)->first();

        if (! $periode) {
            return response()->json(['rows' => [], 'total' => 0, 'montant_total' => 0, 'page' => 1, 'per_page' => 100]);
        }

        $statut = $situation === 'paye' ? 'PAYE' : 'NON_PAYE';
        $page = max(1, (int) $request->query('page', 1));
        $parPage = 100;

        $requete = $this->requetePaiementsSituation($periode, $statut);

        $total = (clone $requete)->count();
        $montantTotal = (clone $requete)->sum('montant');
        $rows = $requete
            ->orderByDesc('paye_le')
            ->orderByDesc('updated_at')
            ->forPage($page, $parPage)
            ->get()
            ->map(fn (Paiement $paiement): ?array => $this->lignePaiementSituation($paiement))
            ->filter()
            ->values();

        return response()->json([
            'rows' => $rows,
            'total' => $total,
            'montant_total' => $montantTotal,
            'page' => $page,
            'per_page' => $parPage,
        ]);
    }

    /**
     * Extraction Excel ou PDF des paiements déjà payés / non payés d'un mois,
     * transposition des boutons d'extraction de l'écran legacy `status-validation`.
     */
    public function exportPaiementsStatuts(Request $request): HttpResponse
    {
        $data = $request->validate([
            'mois' => ['required', 'date_format:Y-m', 'exists:periodes,code'],
            'situation' => ['required', 'in:paye,non_paye'],
            'format' => ['required', 'in:excel,pdf'],
        ]);

        $periode = Periode::query()->where('code', $data['mois'])->firstOrFail();
        $statut = $data['situation'] === 'paye' ? 'PAYE' : 'NON_PAYE';
        $libelle = $data['situation'] === 'paye' ? 'payés' : 'non payés';
        $moisLibelle = Carbon::createFromFormat('Y-m', $data['mois'])->locale('fr')->translatedFormat('F Y');

        $lignes = $this->requetePaiementsSituation($periode, $statut)
            ->orderByDesc('paye_le')
            ->orderByDesc('updated_at')
            ->limit(self::LIMITE_EXPORT)
            ->get()
            ->map(fn (Paiement $paiement): ?array => $this->lignePaiementSituation($paiement))
            ->filter()
            ->values();

        abort_if($lignes->isEmpty(), 404, 'Aucun paiement à exporter pour cette période.');

        return $data['format'] === 'excel'
            ? $this->exporterExcelStatuts($lignes, $data['situation'], $libelle, $moisLibelle, $data['mois'])
            : $this->exporterPdfStatuts($lignes, $libelle, $moisLibelle, $data['mois']);
    }

    /**
     * Onglet d'appartenance d'un paiement, transposition du `status_ac` legacy : `processed`
     * en attente, `validated`, `rejected`, et le différé porté côté legacy par `user_differed`
     * et ici par la corbeille de retour DMG.
     */
    private function ongletDuPaiement(Paiement $paiement): string
    {
        $corbeille = $paiement->corbeille_actuelle instanceof CorbeilleEnum
            ? $paiement->corbeille_actuelle->value
            : $paiement->corbeille_actuelle;

        return match (true) {
            $paiement->statut === 'VALIDE_AC' => 'valide',
            $paiement->statut === 'PAYE' => 'paye',
            $paiement->statut === 'NON_PAYE' => 'non_paye',
            in_array($paiement->statut, ['REJETE_AC', 'REJETE_AC_DEFINITIF', 'REJETE_DEFINITIF'], true) => 'rejete',
            $corbeille === CorbeilleEnum::DMG_OP_DIFFERE_AC->value => 'differe',
            default => 'attente',
        };
    }

    /** @param array{agence_id: mixed, entreprise_id: mixed, source_financement_id: mixed, type_stage_id: mixed, dossier_id: mixed, multi_dossier_id: mixed, date_debut: mixed, date_fin: mixed, recherche: string} $filtres */
    private function correspondAuxFiltres(mixed $dossier, mixed $stage, array $filtres): bool
    {
        if ($filtres['agence_id'] && (int) $filtres['agence_id'] !== (int) $dossier->agence_id) {
            return false;
        }

        if ($filtres['source_financement_id'] && (int) $filtres['source_financement_id'] !== (int) $dossier->source_financement_id) {
            return false;
        }

        if ($filtres['entreprise_id'] && (int) $filtres['entreprise_id'] !== (int) $stage?->entreprise_id) {
            return false;
        }

        if ($filtres['type_stage_id'] && (int) $filtres['type_stage_id'] !== (int) $stage?->type_stage_id) {
            return false;
        }

        if ($filtres['dossier_id'] && (int) $filtres['dossier_id'] !== (int) $dossier->id) {
            return false;
        }

        if ($filtres['multi_dossier_id']
            && ! $dossier->groupes->contains(fn ($groupe): bool => (int) $groupe->id === (int) $filtres['multi_dossier_id'])) {
            return false;
        }

        if ($filtres['date_debut'] && (! $stage?->created_at || $stage->created_at->lt(Carbon::parse($filtres['date_debut'])->startOfDay()))) {
            return false;
        }

        if ($filtres['date_fin'] && (! $stage?->created_at || $stage->created_at->gt(Carbon::parse($filtres['date_fin'])->endOfDay()))) {
            return false;
        }

        if ($filtres['recherche'] === '') {
            return true;
        }

        $beneficiaire = $stage?->beneficiaire;
        $terme = mb_strtolower($filtres['recherche']);
        $champs = [$beneficiaire?->nom, $beneficiaire?->prenoms, $beneficiaire?->numero_aej, $dossier->numero];

        foreach ($champs as $champ) {
            if ($champ !== null && str_contains(mb_strtolower((string) $champ), $terme)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function ligneStagiaire(Paiement $paiement, mixed $stage): array
    {
        $beneficiaire = $stage?->beneficiaire;
        $stageId = $stage?->id;

        return [
            'paiement_id' => $paiement->id,
            'statut_paiement' => $paiement->statut,
            'onglet' => $this->ongletDuPaiement($paiement),
            'montant' => $paiement->montant,
            'paye_le' => $paiement->paye_le?->format('d/m/Y H:i'),
            'beneficiaire_id' => $beneficiaire?->id,
            'stage_id' => $stageId !== null ? (int) $stageId : null,
            'pieces' => null,
            'numero_aej' => $beneficiaire?->numero_aej,
            'nom' => $beneficiaire?->nom,
            'prenoms' => $beneficiaire?->prenoms,
            'date_naissance' => $beneficiaire?->date_naissance?->format('d/m/Y'),
            'numero_tresor_money' => $beneficiaire?->numero_tresor_money,
            'entreprise' => $stage?->entreprise?->raison_sociale,
            'type_stage' => $stage?->typeStage?->nom,
            'date_debut' => $stage?->date_debut?->format('d/m/Y'),
            'date_fin' => ($stage?->date_fin_effective ?? $stage?->date_fin_prevue)?->format('d/m/Y'),
        ];
    }

    /**
     * Requête de base des paiements déjà tranchés (PAYE ou NON_PAYE) d'une
     * période, chargés avec leur dossier actif, leur OP et leur stagiaire.
     */
    private function requetePaiementsSituation(Periode $periode, string $statut): Builder
    {
        return Paiement::query()
            ->where('statut', $statut)
            ->whereHas('dossiersPaiement', function (Builder $query) use ($periode): void {
                $query->whereNull('lignes_dossiers_paiement.retire_le')
                    ->whereHas('ordrePaiement', fn (Builder $ordre): Builder => $ordre->where('periode_id', $periode->id));
            })
            ->with([
                'dossiersPaiement.agence',
                'dossiersPaiement.sourceFinancement',
                'dossiersPaiement.ordrePaiement',
                'droitPaiement.stage.beneficiaire',
                'droitPaiement.stage.entreprise',
                'droitPaiement.stage.typeStage',
                'decisions',
            ]);
    }

    /** Nombre de paiements déjà tranchés (PAYE ou NON_PAYE) sur la période. */
    private function comptePaiementsSituation(Periode $periode, string $statut): int
    {
        return (int) $this->requetePaiementsSituation($periode, $statut)->count();
    }

    /**
     * Classeur Excel des paiements déjà payés / non payés, avec les colonnes
     * de l'écran : dossier, OP, bénéficiaire, affectation, paiement et situation.
     *
     * @param  Collection<int, array<string, mixed>>  $lignes
     */
    private function exporterExcelStatuts(Collection $lignes, string $situation, string $libelle, string $moisLibelle, string $moisCode): HttpResponse
    {
        $classeur = new Spreadsheet;
        $feuille = $classeur->getActiveSheet();
        $feuille->setTitle($situation === 'paye' ? 'Paiements payes' : 'Paiements non payes');

        $entetes = [
            'N° dossier', 'OP', 'Date création', 'Agence', 'Entreprise',
            'Source de financement', 'Type de stagiaire', 'N° AEJ',
            'Nom et prénoms', 'Date de naissance', 'Début', 'Fin',
            'N° Trésor Money', 'Montant (FCFA)', 'Situation', 'Confirmé le',
        ];
        $feuille->fromArray($entetes, null, 'A1');

        $donnees = $lignes->map(fn (array $ligne): array => [
            $ligne['dossier']['numero'],
            $ligne['ordre']['numero'],
            $ligne['dossier']['date_creation'] ?? '',
            $ligne['dossier']['agence'] ?? '',
            $ligne['stagiaire']['entreprise'] ?? '',
            $ligne['dossier']['source_financement'] ?? '',
            $ligne['stagiaire']['type_stage'] ?? '',
            $ligne['stagiaire']['numero_aej'] ?? '',
            trim(($ligne['stagiaire']['nom'] ?? '').' '.($ligne['stagiaire']['prenoms'] ?? '')),
            $ligne['stagiaire']['date_naissance'] ?? '',
            $ligne['stagiaire']['date_debut'] ?? '',
            $ligne['stagiaire']['date_fin'] ?? '',
            $ligne['stagiaire']['numero_tresor_money'] ?? '',
            (float) $ligne['stagiaire']['montant'],
            $ligne['stagiaire']['statut_paiement'] === 'PAYE' ? 'Payé' : 'Non payé',
            $ligne['stagiaire']['decide_le'] ?? '',
        ])->values()->all();
        $feuille->fromArray($donnees, null, 'A2');

        $derniereColonne = chr(ord('A') + count($entetes) - 1);
        $derniereLigne = $lignes->count() + 1;
        $plageEntetes = "A1:{$derniereColonne}1";
        $plageMontants = "N2:N{$derniereLigne}";

        $feuille->getStyle($plageEntetes)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $feuille->getStyle($plageEntetes)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0AB39C');
        $feuille->getStyle($plageMontants)->getNumberFormat()->setFormatCode('#,##0');
        $feuille->setAutoFilter($plageEntetes);
        foreach (range('A', $derniereColonne) as $colonne) {
            $feuille->getColumnDimension($colonne)->setAutoSize(true);
        }

        $suffixe = $situation === 'paye' ? 'payes' : 'non-payes';
        $nomFichier = "paiements-{$suffixe}-{$moisCode}.xlsx";
        $redacteur = new Xlsx($classeur);

        return response()->streamDownload(function () use ($redacteur): void {
            $redacteur->save('php://output');
        }, $nomFichier, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Liste PDF des paiements déjà payés / non payés, au format paysage.
     *
     * @param  Collection<int, array<string, mixed>>  $lignes
     */
    private function exporterPdfStatuts(Collection $lignes, string $libelle, string $moisLibelle, string $moisCode): HttpResponse
    {
        $suffixe = str_contains($libelle, 'payés') ? 'payes' : 'non-payes';

        return Pdf::loadView('pdf.ac-paiements-statuts', [
            'lignes' => $lignes,
            'libelle' => $libelle,
            'mois' => $moisLibelle,
        ])->setPaper('a4', 'landscape')
            ->download("paiements-{$suffixe}-{$moisCode}.pdf");
    }

    /**
     * Ligne plate d'un paiement déjà tranché, avec son dossier et son OP, pour les
     * onglets globaux « Payés » / « Non payés ». Retourne null si le paiement n'a
     * plus de dossier actif (données incohérentes).
     *
     * @return array<string, mixed>|null
     */
    private function lignePaiementSituation(Paiement $paiement): ?array
    {
        $dossier = $paiement->dossiersPaiement->first();

        if ($dossier === null) {
            return null;
        }

        $stage = $paiement->droitPaiement?->stage;
        $stagiaire = $this->ligneStagiaire($paiement, $stage);
        $derniereDecision = $paiement->decisions->sortByDesc('decide_le')->first();
        $stagiaire['decide_le'] = $paiement->paye_le?->format('d/m/Y H:i')
            ?? $derniereDecision?->decide_le?->format('d/m/Y H:i');

        return [
            'stagiaire' => $stagiaire,
            'dossier' => [
                'id' => (int) $dossier->id,
                'numero' => $dossier->numero,
                'date_creation' => $dossier->created_at?->format('d/m/Y'),
                'agence' => $dossier->agence?->nom,
                'source_financement' => $this->libelleSource($dossier->sourceFinancement),
            ],
            'ordre' => [
                'id' => (int) $dossier->ordrePaiement->id,
                'numero' => $dossier->ordrePaiement->numero,
                'statut' => $dossier->ordrePaiement->statut,
            ],
        ];
    }

    /**
     * Chemin absolu et vérifié (dans les limites du disque `piecejusticatif` configuré)
     * de la pièce demandée, ou null si le snapshot de migration n'a pas de fichier pour
     * ce type ou que le fichier n'existe pas physiquement.
     */
    private function cheminPieceAbsolu(int $stageId, string $type): ?string
    {
        $chemin = $this->cheminPiece($this->donneesConservees($stageId), $type);
        $racine = realpath((string) config('filesystems.disks.legacy_pieces.root', ''));

        if ($chemin === null || $racine === false) {
            return null;
        }

        $fichier = realpath($racine.DIRECTORY_SEPARATOR.$chemin);

        if ($fichier === false
            || ! str_starts_with($fichier, $racine.DIRECTORY_SEPARATOR)
            || ! is_file($fichier)) {
            return null;
        }

        return $fichier;
    }

    /** @return array<string, mixed> */
    private function donneesConservees(int $stageId): array
    {
        $conserve = DB::table('conservations_contrats_pae')
            ->where('stage_id', $stageId)
            ->orderByDesc('id')
            ->first();

        return $conserve ? (json_decode((string) $conserve->donnees_originales, true) ?: []) : [];
    }

    /**
     * Chemin (relatif au disque `piecejusticatif` legacy) de la pièce demandée,
     * ou null si le stage n'a pas de fichier pour ce type.
     *
     * @param  array<string, mixed>  $json
     */
    private function cheminPiece(array $json, string $type): ?string
    {
        $valeur = static function (string $cle) use ($json): ?string {
            $valeurColonne = $json[$cle] ?? null;
            $valeurPropre = is_string($valeurColonne) ? trim($valeurColonne) : '';

            return $valeurPropre !== '' && $valeurPropre !== '0' ? $valeurPropre : null;
        };

        return match ($type) {
            'cni' => $valeur('file_cni'),
            'tresor' => $valeur('file_fiche_yup'),
            'contrat' => $this->cheminContrat($json, $valeur('file_contrat'), $valeur('filecontratavenant')),
            'attestation' => $valeur('file_attestation') ?? $valeur('attest_presence'),
            default => null,
        };
    }

    /**
     * Transposition du choix legacy entre contrat initial et avenant : le contrat est
     * retenu sauf quand le stagiaire est en renouvellement sans dates complètes.
     *
     * @param  array<string, mixed>  $json
     */
    private function cheminContrat(array $json, ?string $contrat, ?string $avenant): ?string
    {
        $renouvellement = (int) ($json['etatrenouvellement_id'] ?? 0);
        $renouvellementComplet = $renouvellement === 1
            && ! empty($json['date_debut_renouv']) && ! empty($json['date_fin_renouv']);

        if ($renouvellement === 0 || $renouvellementComplet) {
            return $contrat;
        }

        return $avenant ?? $contrat;
    }

    /**
     * Portage de `BordereauTraitement::checkerAcAction` : le legacy masque les actions
     * devenues incohérentes avec les décisions déjà prises sur l'OP.
     *
     * @param  array<string, int>  $compteurs
     * @return array<string, bool>
     */
    private function actionsPossibles(OrdrePaiement $ordre, array $compteurs): array
    {
        $ouvert = $ordre->statut === 'EN_BORDEREAU' && $ordre->bordereau?->statut === 'TRANSMIS_AC';
        $attente = $compteurs['attente'] > 0;
        $aucuneDecision = $compteurs['valide'] === 0 && $compteurs['rejete'] === 0 && $compteurs['differe'] === 0;

        return [
            'valider' => $ouvert && $attente && $compteurs['rejete'] === 0,
            'differer' => $ouvert && $attente && $compteurs['valide'] === 0 && $compteurs['rejete'] === 0,
            'differer_stagiaires' => $ouvert && $attente && $compteurs['rejete'] === 0,
            'rejeter' => $ouvert && $attente && $compteurs['valide'] === 0 && $compteurs['differe'] === 0,
            'retirer' => $ouvert && $aucuneDecision,
            'confirmer_paiements' => $ordre->statut === 'VISE_AC'
                && in_array($ordre->bordereau?->statut, ['TRANSMIS_AC', 'VISE_AC'], true)
                && $compteurs['valide'] > 0,
        ];
    }

    private function libelleSource(mixed $source): ?string
    {
        return $source?->libelle ?? $source?->nom ?? $source?->code;
    }

    private function vueActuelle(Request $request): string
    {
        $vue = (string) $request->query('vue', 'attente');

        return in_array($vue, ['attente', 'statuts', 'operations_rejetees', 'rejetes'], true)
            ? $vue
            : 'attente';
    }

    private function sousOngletActuel(Request $request): string
    {
        $sousOnglet = (string) $request->query('sous_onglet', 'par_op');

        return in_array($sousOnglet, ['par_op', 'payes', 'non_payes'], true)
            ? $sousOnglet
            : 'par_op';
    }

    private function redirigerVersIndex(Request $request, string $vue): RedirectResponse
    {
        return redirect()->route('ac.paiements.index', array_filter([
            'mois' => $request->query('mois'),
            'vue' => $vue,
        ]));
    }

    public function validerOrdre(Request $request, OrdrePaiement $ordre): RedirectResponse
    {
        $this->acService->validerOrdre($ordre, $request->user());

        return back()->with('success', "L’OP {$ordre->numero} a été validée.");
    }

    public function differerOrdre(Request $request, OrdrePaiement $ordre): RedirectResponse
    {
        $data = $this->validerMotifOrdre($request);
        $this->acService->differerOrdre($ordre, $request->user(), $data['motif']);

        return back()->with('success', "L’OP {$ordre->numero} a été différée vers la DMG.");
    }

    public function differerStagiaires(Request $request, OrdrePaiement $ordre): RedirectResponse
    {
        $data = $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:2000'],
            'paiement_ids' => ['required', 'array', 'min:1'],
            'paiement_ids.*' => ['integer'],
        ]);

        $differes = $this->acService->differerStagiaires($ordre, $request->user(), $data['paiement_ids'], $data['motif']);

        return back()->with('success', $differes > 1
            ? "{$differes} stagiaires de l’OP {$ordre->numero} ont été différés vers la DMG."
            : "1 stagiaire de l’OP {$ordre->numero} a été différé vers la DMG.");
    }

    public function confirmerSituationPaiements(Request $request, OrdrePaiement $ordre): RedirectResponse
    {
        $data = $request->validate([
            'paiement_ids' => ['required', 'array', 'min:1'],
            'paiement_ids.*' => ['integer'],
            'situation' => ['required', 'in:PAYE,NON_PAYE'],
            'motif' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $confirmes = $this->acService->confirmerSituationPaiements(
            $ordre,
            $request->user(),
            $data['paiement_ids'],
            $data['situation'],
            $data['motif'],
        );

        $libelle = $data['situation'] === 'PAYE' ? 'payé(s)' : 'marqué(s) non payé(s)';

        return back()->with('success', "{$confirmes} paiement(s) {$libelle}.");
    }

    public function rejeterOrdre(Request $request, OrdrePaiement $ordre): RedirectResponse
    {
        $data = $this->validerMotifOrdre($request);
        $this->acService->rejeterOrdre($ordre, $request->user(), $data['motif']);

        return back()->with('success', "L’OP {$ordre->numero} a été rejetée.");
    }

    public function retirerOrdre(Request $request, OrdrePaiement $ordre): RedirectResponse
    {
        $data = $this->validerMotifOrdre($request);
        $this->acService->retirerOrdre($ordre, $request->user(), $data['motif']);

        return back()->with('success', "L’OP {$ordre->numero} a été retirée du bordereau.");
    }

    private function bordereauATraiter(int $id): BordereauPaiement
    {
        return BordereauPaiement::query()
            ->with('ordresPaiement.dossiersPaiement.paiementsActifs')
            ->findOrFail($id);
    }

    /** @return array{motif: string} */
    private function validerMotifOrdre(Request $request): array
    {
        return $request->validate([
            'motif' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
    }

    /** @return Collection<int, array{code: string, count: int}> */
    private function periodesDisponibles(): Collection
    {
        $comptes = BordereauPaiement::query()
            ->select('periode_id')
            ->selectRaw('count(*) as total')
            ->where('statut', 'TRANSMIS_AC')
            ->whereHas('ordresPaiement.dossiersPaiement.paiementsActifs', function (Builder $query): void {
                $query->whereNotIn('paiements.statut', ['VALIDE_AC', 'REJETE_DEFINITIF']);
            })
            ->groupBy('periode_id')
            ->pluck('total', 'periode_id');

        $periodeIds = BordereauPaiement::query()
            ->whereIn('statut', ['TRANSMIS_AC', 'VISE_AC', 'REJETE_AC', 'REJETE_AC_DEFINITIF'])
            ->distinct()
            ->pluck('periode_id');

        $periodeIds = $periodeIds
            ->merge(OrdrePaiement::query()
                ->whereIn('statut', ['VISE_AC', 'REJETE_AC', 'REJETE_AC_DEFINITIF'])
                ->distinct()
                ->pluck('periode_id'))
            ->filter()
            ->unique()
            ->values();

        return Periode::query()
            ->whereIn('id', $periodeIds)
            ->orderByDesc('code')
            ->get(['id', 'code'])
            ->map(fn (Periode $periode): array => [
                'code' => $periode->code,
                'count' => (int) $comptes->get($periode->id, 0),
            ]);
    }

    /**
     * Regroupe une liste d'OP par bordereau pour garder le parcours compact de
     * l'écran unique, tout en reproduisant les vues legacy qui listaient les OP
     * directement (`status-validation` et `operation-rejete`).
     *
     * @param  array<int, string>  $statutsOrdres
     * @param  array<int, string>|null  $statutsPaiements
     * @return Collection<int, array<string, mixed>>
     */
    private function bordereauxAvecOrdres(Periode $periode, array $statutsOrdres, ?array $statutsPaiements = null): Collection
    {
        $ordres = OrdrePaiement::query()
            ->where('periode_id', $periode->id)
            ->whereIn('statut', $statutsOrdres)
            ->when($statutsPaiements !== null, function (Builder $query) use ($statutsPaiements): void {
                $query->whereHas('dossiersPaiement.paiementsActifs', function (Builder $paiement) use ($statutsPaiements): void {
                    $paiement->whereIn('paiements.statut', $statutsPaiements);
                });
            })
            ->with([
                'bordereau.sourceFinancement',
                'sourceFinancement',
                'dossiersPaiement.agence',
                'dossiersPaiement.sourceFinancement',
                'dossiersPaiement.paiementsActifs.decisions',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $avecBordereau = $ordres
            ->filter(fn (OrdrePaiement $ordre): bool => $ordre->bordereau !== null)
            ->groupBy('bordereau_paiement_id')
            ->map(function (Collection $ordresBordereau): array {
                /** @var OrdrePaiement $premierOrdre */
                $premierOrdre = $ordresBordereau->first();
                /** @var BordereauPaiement $bordereau */
                $bordereau = $premierOrdre->bordereau;
                $bordereau->setRelation('ordresPaiement', $ordresBordereau->values());
                $bordereau->montant_total = $ordresBordereau->sum('montant_total');

                return $this->toRow($bordereau);
            });

        $sansBordereau = $ordres
            ->filter(fn (OrdrePaiement $ordre): bool => $ordre->bordereau === null)
            ->map(fn (OrdrePaiement $ordre): array => $this->toRowOrdreSansBordereau($ordre));

        return $avecBordereau
            ->values()
            ->merge($sansBordereau->values());
    }

    /**
     * Répartition des paiements actifs d'une OP (total, payés, non payés et
     * encore à confirmer), affichée sur chaque OP de la liste « Par OP ».
     *
     * @return array{total: int, payes: int, nonPayes: int, valides: int}
     */
    private function compteursOrdre(OrdrePaiement $ordre): array
    {
        $paiements = $ordre->dossiersPaiement->flatMap(fn ($dossier) => $dossier->paiementsActifs);

        return [
            'total' => $paiements->count(),
            'payes' => $paiements->where('statut', 'PAYE')->count(),
            'nonPayes' => $paiements->where('statut', 'NON_PAYE')->count(),
            'valides' => $paiements->where('statut', 'VALIDE_AC')->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function toRow(BordereauPaiement $bordereau): array
    {
        $ordres = $bordereau->ordresPaiement->map(function ($ordre): array {
            $agences = $ordre->dossiersPaiement->pluck('agence.nom')->filter()->unique()->values();

            return [
                'id' => $ordre->id,
                'numero' => $ordre->numero,
                'statut' => $ordre->statut,
                'montant_total' => $ordre->montant_total,
                'nombre_dossiers' => $ordre->dossiersPaiement->count(),
                'nombre_paiements' => $ordre->dossiersPaiement->sum(fn ($dossier): int => $dossier->paiementsActifs->count()),
                'compteurs' => $this->compteursOrdre($ordre),
                'agences' => $agences->implode(', '),
            ];
        })->values();

        $motif = $bordereau->ordresPaiement
            ->flatMap(fn ($ordre) => $ordre->dossiersPaiement)
            ->flatMap(fn ($dossier) => $dossier->paiementsActifs)
            ->pluck('pivot.motif_retrait')
            ->filter()
            ->first()
            ?? $bordereau->ordresPaiement
                ->flatMap(fn ($ordre) => $ordre->dossiersPaiement)
                ->flatMap(fn ($dossier) => $dossier->paiementsActifs)
                ->flatMap(fn ($paiement) => $paiement->decisions)
                ->pluck('motif')
                ->filter()
                ->first();

        $source = $bordereau->sourceFinancement;

        return [
            'id' => $bordereau->id,
            'numero' => $bordereau->numero,
            'statut' => $bordereau->statut,
            'montant_total' => $bordereau->montant_total,
            'date_transmission' => $bordereau->created_at?->format('d/m/Y H:i'),
            'date_traitement' => $bordereau->updated_at?->format('d/m/Y H:i'),
            'motif' => $motif,
            'source_financement' => $source ? [
                'code' => $source->code,
                'libelle' => $source->libelle ?? $source->nom ?? $source->code,
            ] : null,
            'nombre_ordres' => $ordres->count(),
            'nombre_dossiers' => $ordres->sum('nombre_dossiers'),
            'nombre_paiements' => $ordres->sum('nombre_paiements'),
            'agences' => $ordres->pluck('agences')->filter()->unique()->implode(', '),
            'ordres' => $ordres,
        ];
    }

    /** @return array<string, mixed> */
    private function toRowOrdreSansBordereau(OrdrePaiement $ordre): array
    {
        $agences = $ordre->dossiersPaiement->pluck('agence.nom')->filter()->unique()->values();
        $motif = $ordre->dossiersPaiement
            ->flatMap(fn ($dossier) => $dossier->paiementsActifs)
            ->pluck('pivot.motif_retrait')
            ->filter()
            ->first();
        $source = $ordre->sourceFinancement;

        return [
            'id' => -1 * (int) $ordre->id,
            'numero' => 'OP sans bordereau',
            'statut' => 'SANS_BORDEREAU',
            'montant_total' => $ordre->montant_total,
            'date_transmission' => null,
            'date_traitement' => $ordre->updated_at?->format('d/m/Y H:i'),
            'motif' => $motif,
            'source_financement' => $source ? [
                'code' => $source->code,
                'libelle' => $source->libelle ?? $source->nom ?? $source->code,
            ] : null,
            'nombre_ordres' => 1,
            'nombre_dossiers' => $ordre->dossiersPaiement->count(),
            'nombre_paiements' => $ordre->dossiersPaiement->sum(fn ($dossier): int => $dossier->paiementsActifs->count()),
            'agences' => $agences->implode(', '),
            'ordres' => [[
                'id' => $ordre->id,
                'numero' => $ordre->numero,
                'statut' => $ordre->statut,
                'montant_total' => $ordre->montant_total,
                'nombre_dossiers' => $ordre->dossiersPaiement->count(),
                'nombre_paiements' => $ordre->dossiersPaiement->sum(fn ($dossier): int => $dossier->paiementsActifs->count()),
                'compteurs' => $this->compteursOrdre($ordre),
                'agences' => $agences->implode(', '),
            ]],
        ];
    }
}
