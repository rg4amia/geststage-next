<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Domain\Workflow\Services\CorbeilleParcoursQueryService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttentePaiementDmgController extends Controller
{
    public function __construct(private DmgService $service, private CorbeilleParcoursQueryService $rows) {}

    public function index(Request $request): JsonResponse
    {
        $mois = $request->string('mois', Carbon::now()->format('Y-m'))->toString();
        $filters = $request->only(['agence_id', 'entreprise_id', 'source_financement_id', 'type_stage_id', 'type_structure_id', 'date_debut', 'date_fin', 'search', 'dossier_physique']);
        $query = $request->string('type', 'demarrage')->toString() === 'presence'
            ? $this->service->attentePaiementPresence($filters, $mois)
            : $this->service->attentePaiementDemarrage($filters, $mois);
        $query = $this->service->applyCohorteFilter($query, $request->string('cohorte', 'global')->toString());
        $perPage = min(max($request->integer('per_page', 50), 10), 200);
        $page = $query->orderByDesc('paiements.created_at')->paginate($perPage);

        return response()->json([
            'data' => $this->rows->paiementRows(collect($page->items())),
            'total' => $page->total(), 'page' => $page->currentPage(),
            'per_page' => $page->perPage(), 'last_page' => $page->lastPage(),
        ]);
    }

    public function ajourner(Request $request): RedirectResponse
    {
        $data = $request->validate(['paiement_ids' => ['required', 'array', 'min:1', 'max:500'], 'paiement_ids.*' => ['integer', 'distinct', 'exists:paiements,id'], 'motif' => ['required', 'string', 'min:5', 'max:1000']]);
        $nombre = $this->service->ajournerPaiements($data['paiement_ids'], $data['motif'], $request->user());

        return back()->with('success', "{$nombre} paiement(s) ajourne(s).");
    }

    public function marquerDossierPhysique(Request $request): RedirectResponse
    {
        $data = $request->validate(['paiement_ids' => ['required', 'array', 'min:1', 'max:500'], 'paiement_ids.*' => ['integer', 'distinct', 'exists:paiements,id'], 'statut' => ['required', 'in:EN_ATTENTE,RECU,CONFORME']]);
        $nombre = $this->service->marquerDossiersPhysiques($data['paiement_ids'], $data['statut'], $request->user());

        return back()->with('success', "{$nombre} dossier(s) physique(s) marque(s).");
    }
}
