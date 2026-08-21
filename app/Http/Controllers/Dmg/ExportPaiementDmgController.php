<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExportPaiementDmgController extends Controller
{
    public function __construct(private DmgService $service) {}

    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'type' => ['required', 'in:etat_paiement,attestation_demarrage,attestation_presence,fusion_tresor'],
            'mois' => ['required', 'date_format:Y-m', 'exists:periodes,code'],
            'ids' => ['nullable', 'array', 'max:500'],
            'ids.*' => ['integer', 'distinct', 'exists:paiements,id'],
        ]);
        $filters = $request->only(['agence_id', 'entreprise_id', 'source_financement_id', 'type_stage_id', 'type_structure_id', 'date_debut', 'date_fin', 'search', 'dossier_physique']);
        $nature = $data['type'] === 'attestation_presence' ? 'presence' : $request->string('nature', 'demarrage')->toString();
        $query = $nature === 'presence'
            ? $this->service->attentePaiementPresence($filters, $data['mois'])
            : $this->service->attentePaiementDemarrage($filters, $data['mois']);

        if ($ids = $data['ids'] ?? []) {
            $query->whereIn('paiements.id', $ids);
        }
        $paiements = $query->orderBy('paiements.id')->limit(500)->get();

        abort_if($paiements->isEmpty(), 404, 'Aucun paiement eligible pour cet export.');

        $libelles = [
            'etat_paiement' => 'Etat de paiement',
            'attestation_demarrage' => 'Attestations de demarrage',
            'attestation_presence' => 'Attestations de presence',
            'fusion_tresor' => 'Fusion Tresor Pay',
        ];
        $titre = $libelles[$data['type']];

        return Pdf::loadView('pdf.dmg-paiements', [
            'paiements' => $paiements,
            'titre' => $titre,
            'type' => $data['type'],
            'mois' => Carbon::createFromFormat('Y-m', $data['mois'])->locale('fr')->translatedFormat('F Y'),
        ])->setPaper('a4', $data['type'] === 'etat_paiement' ? 'landscape' : 'portrait')
            ->download(str($titre)->slug().'-'.$data['mois'].'.pdf');
    }
}
