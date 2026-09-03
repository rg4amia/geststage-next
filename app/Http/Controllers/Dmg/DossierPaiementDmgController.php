<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Domain\Payment\Services\MultiDossierPdfService;
use App\Http\Controllers\Controller;
use App\Models\Payment\DossierGroupe;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\Paiement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

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
}
