<?php

namespace App\Http\Controllers\AgentCompt;

use App\Domain\Payment\Services\AgentComptableService;
use App\Http\Controllers\Controller;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\OrdrePaiement;
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

    public function ordreDetails(OrdrePaiement $ordre): JsonResponse
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

        return response()->json([
            'id' => $ordre->id,
            'numero' => $ordre->numero,
            'statut' => $ordre->statut,
            'montant_total' => $ordre->montant_total,
            'bordereau' => $ordre->bordereau->only(['id', 'numero', 'statut']),
            'dossiers' => $ordre->dossiersPaiement->map(function ($dossier): array {
                return [
                    'id' => $dossier->id,
                    'numero' => $dossier->numero,
                    'statut' => $dossier->statut,
                    'montant_total' => $dossier->montant_total,
                    'agence' => $dossier->agence?->nom,
                    'source_financement' => $dossier->sourceFinancement?->libelle
                        ?? $dossier->sourceFinancement?->nom
                        ?? $dossier->sourceFinancement?->code,
                    'stagiaires' => $dossier->paiementsActifs->map(function ($paiement): array {
                        $stage = $paiement->droitPaiement?->stage;
                        $beneficiaire = $stage?->beneficiaire;

                        return [
                            'paiement_id' => $paiement->id,
                            'statut_paiement' => $paiement->statut,
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
                    })->values(),
                ];
            })->values(),
        ]);
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
