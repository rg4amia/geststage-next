<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Http\Controllers\Controller;
use App\Models\Payment\BordereauPaiement;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Reference\Periode;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OperationPaiementDmgController extends Controller
{
    public function __construct(private DmgService $service) {}

    /**
     * Élaboration d'un ordre de paiement.
     *
     * `libelle` et `montant_etat_financement` reprennent les deux champs de la modale
     * « Créer une opération » de l'ancien Gestage : un intitulé libre et le montant de l'état
     * de financement mobilisé, qui peut différer du cumul des dossiers rattachés.
     */
    public function elaborer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'dossiers' => ['required', 'array', 'min:1', 'max:500'],
            'dossiers.*' => ['integer', 'distinct', 'exists:dossiers_paiement,id'],
            'periode_id' => ['required', 'exists:periodes,id'],
            'libelle' => ['nullable', 'string', 'max:255'],
            'montant_etat_financement' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->service->elaborerOp(
            $data['dossiers'],
            $data['periode_id'],
            $data['libelle'] ?? null,
            isset($data['montant_etat_financement']) ? (float) $data['montant_etat_financement'] : null,
        );

        return back()->with('success', 'Ordre de paiement elabore.');
    }

    public function creerBordereau(Request $request): RedirectResponse
    {
        $data = $request->validate(['ops' => ['required', 'array', 'min:1', 'max:10'], 'ops.*' => ['integer', 'distinct', 'exists:ordre_paiements,id'], 'periode_id' => ['required', 'exists:periodes,id']]);
        $this->service->creerBordereau($data['ops'], $data['periode_id']);

        return back()->with('success', 'Bordereau cree.');
    }

    public function transmettreBordereau(BordereauPaiement $bordereau): RedirectResponse
    {
        $this->service->transmettreBordereauAc($bordereau);

        return back()->with('success', 'Bordereau transmis a l agent comptable.');
    }

    /**
     * Ordres de paiement d'une période, avec de quoi remplir les colonnes de l'ancien tableau
     * « Liste des Ordres de paiement » : agences et financement d'origine, nombre de dossiers
     * et de stagiaires rattachés.
     */
    public function ops(Request $request): JsonResponse
    {
        $periode = Periode::where('code', $request->string('mois')->toString())->first();

        $ops = OrdrePaiement::query()
            ->with(['sourceFinancement:id,nom', 'bordereau:id,numero'])
            ->withCount('dossiersPaiement')
            ->when($periode, fn ($q) => $q->where('periode_id', $periode->id))
            ->when($request->string('statut')->toString(), fn ($q, $statut) => $q->where('statut', $statut))
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $agrege = $this->agregatsParOp($ops->modelKeys());

        return response()->json($ops->map(fn (OrdrePaiement $op) => [
            'id' => $op->id,
            'numero' => $op->numero,
            'libelle' => $op->libelle,
            'statut' => $op->statut,
            'montant_total' => (float) $op->montant_total,
            'montant_etat_financement' => $op->montant_etat_financement !== null ? (float) $op->montant_etat_financement : null,
            'source_financement' => $op->sourceFinancement?->nom,
            'bordereau' => $op->bordereau?->numero,
            'dossiers_count' => $op->dossiers_paiement_count,
            'stagiaires_count' => (int) ($agrege[$op->id]->stagiaires ?? 0),
            'agences' => $agrege[$op->id]->agences ?? null,
            'created_at' => $op->created_at?->format('d/m/Y H:i'),
        ]));
    }

    /**
     * Dossiers rattachés à un ordre de paiement (dépliement d'une ligne du tableau).
     */
    public function dossiersOp(OrdrePaiement $op): JsonResponse
    {
        $dossiers = $op->dossiersPaiement()
            ->with(['agence:id,nom', 'sourceFinancement:id,nom'])
            ->withCount(['paiements' => fn ($q) => $q->whereNull('lignes_dossiers_paiement.retire_le')])
            ->orderBy('numero')
            ->get();

        return response()->json($dossiers->map(fn (DossierPaiement $dossier) => [
            'id' => $dossier->id,
            'numero' => $dossier->numero,
            'nature' => $dossier->nature,
            'statut' => $dossier->statut,
            'agence' => $dossier->agence?->nom,
            'source_financement' => $dossier->sourceFinancement?->nom,
            'nombre_stagiaires' => $dossier->paiements_count,
            'montant_total' => (float) $dossier->montant_total,
        ]));
    }

    /**
     * Ordres de paiement d'un bordereau (dépliement d'une ligne du tableau des bordereaux).
     */
    public function opsBordereau(BordereauPaiement $bordereau): JsonResponse
    {
        $ops = $bordereau->ordresPaiement()->withCount('dossiersPaiement')->orderBy('numero')->get();

        return response()->json($ops->map(fn (OrdrePaiement $op) => [
            'id' => $op->id,
            'numero' => $op->numero,
            'libelle' => $op->libelle,
            'statut' => $op->statut,
            'dossiers_count' => $op->dossiers_paiement_count,
            'montant_total' => (float) $op->montant_total,
        ]));
    }

    public function retirerDossierOp(Request $request, OrdrePaiement $op): RedirectResponse
    {
        $data = $request->validate([
            'dossier_id' => ['required', 'integer', 'exists:dossiers_paiement,id'],
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $this->service->retirerDossierOp($op, DossierPaiement::findOrFail($data['dossier_id']), $data['motif'], $request->user());

        return back()->with('success', 'Dossier retire de l ordre de paiement.');
    }

    public function retirerOpBordereau(Request $request, BordereauPaiement $bordereau): RedirectResponse
    {
        $data = $request->validate([
            'op_id' => ['required', 'integer', 'exists:ordre_paiements,id'],
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $this->service->retirerOpBordereau($bordereau, OrdrePaiement::findOrFail($data['op_id']), $data['motif'], $request->user());

        return back()->with('success', 'Ordre de paiement retire du bordereau.');
    }

    /**
     * Agences distinctes et nombre de stagiaires par OP, en une passe : la boucle par OP
     * coûterait deux requêtes par ligne du tableau.
     *
     * @param  list<int>  $opIds
     * @return Collection<int, object>
     */
    private function agregatsParOp(array $opIds): Collection
    {
        if ($opIds === []) {
            return collect();
        }

        $driver = DB::getDriverName();
        $agences = $driver === 'pgsql'
            ? "string_agg(DISTINCT agences.nom, ', ')"
            : 'group_concat(DISTINCT agences.nom)';

        return DB::table('dossiers_paiement')
            ->leftJoin('agences', 'dossiers_paiement.agence_id', '=', 'agences.id')
            ->leftJoin('lignes_dossiers_paiement', fn (JoinClause $join) => $join
                ->on('lignes_dossiers_paiement.dossier_paiement_id', '=', 'dossiers_paiement.id')
                ->whereNull('lignes_dossiers_paiement.retire_le'))
            ->whereIn('dossiers_paiement.ordre_paiement_id', $opIds)
            ->groupBy('dossiers_paiement.ordre_paiement_id')
            ->selectRaw('dossiers_paiement.ordre_paiement_id as op_id, count(lignes_dossiers_paiement.id) as stagiaires, '.$agences.' as agences')
            ->get()
            ->keyBy('op_id');
    }
}
