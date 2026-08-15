<?php

namespace App\Domain\Payment\Services;

use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PejedecAafService
{
    public function genererPaiement(DroitPaiement $droitPaiement): Paiement
    {
        return DB::transaction(function () use ($droitPaiement) {
            return Paiement::firstOrCreate(
                ['droit_paiement_id' => $droitPaiement->id],
                [
                    'uuid_public' => (string) Str::uuid(),
                    'ancien_id' => null,
                    'montant' => $droitPaiement->montant,
                    'statut' => 'A_TRAITER',
                    'version_verrouillage' => 0,
                ],
            );
        });
    }
}
