<?php

namespace App\Http\Controllers\AgentCompt;

use App\Domain\Payment\Services\AgentComptableService;
use App\Enums\CorbeilleEnum;
use App\Http\Controllers\Controller;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PaiementAcController extends Controller
{
    /** Onglets stagiaires d'une OP, calqués sur ceux de l'écran legacy. */
    private const ONGLETS_STAGIAIRES = ['attente', 'valide', 'rejete', 'differe'];

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
                'ordresRejetes' => [],
                'statutPaiements' => [],
                'moisActuel' => $mois,
                'periode' => null,
                'periodesDisponibles' => $periodesDisponibles,
            ]);
        }

        $baseQuery = BordereauPaiement::query()
            ->where('periode_id', $periode->id)
            ->with([
                'sourceFinancement',
                'ordresPaiement.dossiersPaiement.agence',
                'ordresPaiement.dossiersPaiement.paiementsActifs',
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

        $vises = (clone $baseQuery)
            ->where('statut', 'VISE_AC')
            ->get()
            ->map(fn (BordereauPaiement $bordereau): array => $this->toRow($bordereau));

        return Inertia::render('AgentComptable/Paiements/Index', [
            'bordereauxAttente' => $attente,
            'bordereauxRejetes' => $rejetes,
            'bordereauxVises' => $vises,
            // Alias conservés pour les consommateurs Inertia existants.
            'ordresRejetes' => $rejetes,
            'statutPaiements' => $vises,
            'moisActuel' => $mois,
            'periode' => $periode,
            'periodesDisponibles' => $periodesDisponibles,
        ]);
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
            'dossiersPaiement.paiementsActifs.droitPaiement.stage.beneficiaire',
            'dossiersPaiement.paiementsActifs.droitPaiement.stage.entreprise',
            'dossiersPaiement.paiementsActifs.droitPaiement.stage.typeStage',
        ]);

        abort_unless($ordre->bordereau, 404);

        $onglet = (string) $request->query('onglet', self::ONGLETS_STAGIAIRES[0]);
        if (! in_array($onglet, self::ONGLETS_STAGIAIRES, true)) {
            $onglet = self::ONGLETS_STAGIAIRES[0];
        }

        $filtres = [
            'agence_id' => $request->query('agence_id'),
            'entreprise_id' => $request->query('entreprise_id'),
            'source_financement_id' => $request->query('source_financement_id'),
            'type_stage_id' => $request->query('type_stage_id'),
            'recherche' => trim((string) $request->query('recherche', '')),
        ];

        $compteurs = array_fill_keys(self::ONGLETS_STAGIAIRES, 0);
        $referentiels = ['agences' => [], 'entreprises' => [], 'sources_financement' => [], 'types_stage' => []];
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
            'bordereau' => $ordre->bordereau->only(['id', 'numero', 'statut']),
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
            'dossiers' => $dossiers,
        ]);
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
            in_array($paiement->statut, ['REJETE_AC', 'REJETE_AC_DEFINITIF', 'REJETE_DEFINITIF'], true) => 'rejete',
            $corbeille === CorbeilleEnum::DMG_OP_DIFFERE_AC->value => 'differe',
            default => 'attente',
        };
    }

    /** @param array{agence_id: mixed, entreprise_id: mixed, source_financement_id: mixed, type_stage_id: mixed, recherche: string} $filtres */
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

        return [
            'paiement_id' => $paiement->id,
            'statut_paiement' => $paiement->statut,
            'onglet' => $this->ongletDuPaiement($paiement),
            'montant' => $paiement->montant,
            'beneficiaire_id' => $beneficiaire?->id,
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
        ];
    }

    private function libelleSource(mixed $source): ?string
    {
        return $source?->libelle ?? $source?->nom ?? $source?->code;
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

        return Periode::query()
            ->whereIn('id', $periodeIds)
            ->orderByDesc('code')
            ->get(['id', 'code'])
            ->map(fn (Periode $periode): array => [
                'code' => $periode->code,
                'count' => (int) $comptes->get($periode->id, 0),
            ]);
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
                'agences' => $agences->implode(', '),
            ];
        })->values();

        $motif = $bordereau->ordresPaiement
            ->flatMap(fn ($ordre) => $ordre->dossiersPaiement)
            ->flatMap(fn ($dossier) => $dossier->paiementsActifs)
            ->pluck('pivot.motif_retrait')
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
}
