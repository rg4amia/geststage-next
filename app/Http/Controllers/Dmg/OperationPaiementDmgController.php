<?php

namespace App\Http\Controllers\Dmg;

use App\Domain\Payment\Services\DmgService;
use App\Http\Controllers\Controller;
use App\Models\Payment\BordereauPaiement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OperationPaiementDmgController extends Controller
{
    public function __construct(private DmgService $service) {}

    public function elaborer(Request $request): RedirectResponse
    {
        $data = $request->validate(['dossiers' => ['required', 'array', 'min:1', 'max:500'], 'dossiers.*' => ['integer', 'distinct', 'exists:dossiers_paiement,id'], 'periode_id' => ['required', 'exists:periodes,id']]);
        $this->service->elaborerOp($data['dossiers'], $data['periode_id']);

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
}
