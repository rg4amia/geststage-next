<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Onglet « Ajournés » de la constitution des dossiers de paiement.
 *
 * Reprend l'écran legacy `dmg/ajournement-controller-budgetaire/ajournement-stagiaire` : la
 * liste nominative des stagiaires bloqués, quel que soit l'auteur de l'ajournement, et leur
 * remise en file d'attente une fois le dossier corrigé. L'ancien Gestage ne montrait que les
 * ajournements du CB ; on y ajoute ceux prononcés par la DMG elle-même, qui disparaissaient
 * sinon de tout écran DMG.
 */
class AjournementPaiementDmgController extends Controller
{
    public function __construct(private DmgService $service) {}

    /**
     * Liste paginée des stagiaires ajournés.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 10), 10), 200);
        $query = $this->baseQuery($request);
        $total = (clone $query)->count();

        $rows = $query
            ->orderByDesc('paiements.updated_at')
            ->forPage(max($request->integer('page', 1), 1), $perPage)
            ->get();

        return response()->json([
            'data' => $rows,
            'total' => $total,
            'page' => max($request->integer('page', 1), 1),
            'per_page' => $perPage,
            'last_page' => max((int) ceil($total / $perPage), 1),
        ]);
    }

    /**
     * Options du sélecteur « Dossier / Multi-dossier » : uniquement ceux qui portent encore un
     * stagiaire ajourné sur le mois demandé. Un dossier appartenant à un multi-dossier est
     * présenté sous ce multi-dossier, comme dans l'ancien Gestage (`dossier-rejected-by-month`).
     *
     */
    public function dossiers(Request $request): JsonResponse
    {
        $rows = $this->baseQuery($request)
            ->reorder()
            ->select([
                'dossiers_paiement.id as dossier_id',
                'dossiers_paiement.numero as dossier_numero',
                'dossiers_groupes.id as groupe_id',
                'dossiers_groupes.numero as groupe_numero',
            ])
            ->whereNotNull('dossiers_paiement.id')
            ->distinct()
            ->get();

        $options = [];
        foreach ($rows as $row) {
            if ($row->groupe_id !== null) {
                $options['multi_'.$row->groupe_id] = (string) $row->groupe_numero;
            } else {
                $options[(string) $row->dossier_id] = (string) $row->dossier_numero;
            }
        }
        asort($options);

        return response()->json(array_map(
            fn (string $value, string $label) => ['value' => $value, 'label' => $label],
            array_keys($options),
            array_values($options),
        ));
    }

    /**
     * Remet les paiements sélectionnés dans leur file d'attente DMG d'origine.
     */
    public function reprendre(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'paiement_ids' => ['required', 'array', 'min:1', 'max:'.DmgService::LIMITE_LISTE_ATTENTE],
            'paiement_ids.*' => ['integer', 'distinct'],
            'motif' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $nombre = $this->service->reprendrePaiementsAjournes($data['paiement_ids'], $data['motif'], $request->user());

        return back()->with('success', "{$nombre} paiement(s) remis en file d attente.");
    }

    /**
     * Socle commun à la liste et au sélecteur de dossiers : un paiement ajourné est soit marqué
     * `AJOURNE_DMG`, soit resté `EN_DOSSIER` dans un dossier que le CB a ajourné.
     */
    private function baseQuery(Request $request): QueryBuilder
    {
        $motif = DB::table('decisions_paiements')
            ->whereColumn('decisions_paiements.paiement_id', 'paiements.id')
            ->where('decisions_paiements.decision', 'like', 'AJOURNE%')
            ->orderByDesc('decisions_paiements.decide_le')
            ->limit(1);

        $query = DB::table('paiements')
            ->join('droits_paiement', 'paiements.droit_paiement_id', '=', 'droits_paiement.id')
            ->join('periodes', 'droits_paiement.periode_id', '=', 'periodes.id')
            ->join('stages', 'droits_paiement.stage_id', '=', 'stages.id')
            ->join('beneficiaires', 'stages.beneficiaire_id', '=', 'beneficiaires.id')
            ->leftJoin('entreprises', 'stages.entreprise_id', '=', 'entreprises.id')
            ->leftJoin('agences', 'stages.agence_id', '=', 'agences.id')
            ->leftJoin('sources_financement', 'droits_paiement.source_financement_id', '=', 'sources_financement.id')
            ->leftJoin('types_stage', 'stages.type_stage_id', '=', 'types_stage.id')
            ->leftJoin('lignes_dossiers_paiement', fn (JoinClause $join) => $join
                ->on('lignes_dossiers_paiement.paiement_id', '=', 'paiements.id')
                ->whereNull('lignes_dossiers_paiement.retire_le'))
            ->leftJoin('dossiers_paiement', 'lignes_dossiers_paiement.dossier_paiement_id', '=', 'dossiers_paiement.id')
            ->leftJoin('lignes_dossiers_groupes', fn (JoinClause $join) => $join
                ->on('lignes_dossiers_groupes.dossier_paiement_id', '=', 'dossiers_paiement.id')
                ->whereNull('lignes_dossiers_groupes.retire_le'))
            ->leftJoin('dossiers_groupes', 'lignes_dossiers_groupes.dossier_groupe_id', '=', 'dossiers_groupes.id')
            ->where(fn (QueryBuilder $q) => $q
                ->where('paiements.statut', 'AJOURNE_DMG')
                ->orWhere(fn (QueryBuilder $cb) => $cb
                    ->where('paiements.statut', 'EN_DOSSIER')
                    ->where('dossiers_paiement.statut', 'AJOURNE_CB')))
            ->select([
                'paiements.id as paiement_id',
                'paiements.statut',
                'paiements.montant',
                'paiements.updated_at as ajourne_le',
                'stages.id as stage_id',
                'stages.date_debut',
                'stages.date_fin_prevue as date_fin',
                'beneficiaires.nom',
                'beneficiaires.prenoms',
                'beneficiaires.numero_aej',
                'beneficiaires.date_naissance',
                'beneficiaires.numero_tresor_money as tresor_pay',
                'entreprises.raison_sociale as entreprise',
                'agences.nom as agence',
                'sources_financement.nom as source_financement',
                'types_stage.nom as type_stage',
                'periodes.code as periode',
                'dossiers_paiement.numero as dossier',
                'dossiers_groupes.numero as multi_dossier',
            ])
            ->selectSub($motif->select('decisions_paiements.motif'), 'motif');

        return $this->applyFilters($query, $request);
    }

    private function applyFilters(QueryBuilder $query, Request $request): QueryBuilder
    {
        $mois = $request->string('mois')->toString();
        if ($mois !== '') {
            $query->where('periodes.code', $mois);
        }

        foreach ([
            'agence_id' => 'stages.agence_id',
            'entreprise_id' => 'stages.entreprise_id',
            'type_stage_id' => 'stages.type_stage_id',
            'source_financement_id' => 'droits_paiement.source_financement_id',
        ] as $parametre => $colonne) {
            if ($id = $request->integer($parametre)) {
                $query->where($colonne, $id);
            }
        }

        // Le sélecteur mélange dossiers simples (identifiant numérique) et multi-dossiers
        // (préfixe `multi_`), comme le `dossierSelected` de l'ancien Gestage.
        $dossier = $request->string('dossier')->toString();
        if (str_starts_with($dossier, 'multi_')) {
            $query->where('dossiers_groupes.id', (int) substr($dossier, 6));
        } elseif ($dossier !== '') {
            $query->where('dossiers_paiement.id', (int) $dossier);
        }

        if ($search = $request->string('search')->toString()) {
            $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $term = '%'.addcslashes($search, '%_').'%';
            $query->where(fn (QueryBuilder $q) => $q
                ->where('beneficiaires.nom', $operator, $term)
                ->orWhere('beneficiaires.prenoms', $operator, $term)
                ->orWhere('beneficiaires.numero_aej', $operator, $term));
        }

        return $query;
    }
}
