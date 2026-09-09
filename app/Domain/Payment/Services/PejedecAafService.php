<?php

namespace App\Domain\Payment\Services;

use App\Models\Attendance\DecisionPointage;
use App\Models\Attendance\Pointage;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PejedecAafService
{
    public function validerPointage(Pointage $pointage, User $aaf, bool $depuisCorrection = false): DroitPaiement
    {
        return DB::transaction(function () use ($pointage, $aaf, $depuisCorrection) {
            $pointage->loadMissing(['stage.contrats', 'stage.sourceFinancement', 'versionCourante']);

            if (! $pointage->stage) {
                throw new InvalidArgumentException('Le stage du pointage est introuvable.');
            }

            if ($pointage->stage->sourceFinancement?->code !== 'PEJEDEC'
                && (int) $pointage->stage->sourceFinancement?->ancien_id !== 5) {
                throw new InvalidArgumentException("Ce pointage n'appartient pas au financement PEJEDEC.");
            }

            $statutAttendu = $depuisCorrection ? 'CORRIGE_CIP' : 'VALIDE';
            if ($pointage->statut !== $statutAttendu) {
                throw new InvalidArgumentException(
                    $depuisCorrection
                        ? 'La correction ne peut pas être validée dans cet état.'
                        : "Le pointage doit d'abord être validé par le Chef d'agence."
                );
            }

            if (! $pointage->versionCourante) {
                throw new InvalidArgumentException('La version courante du pointage est introuvable.');
            }

            if ($depuisCorrection) {
                $pointage->update(['statut' => 'VALIDE']);
            }

            DecisionPointage::firstOrCreate([
                'pointage_id' => $pointage->id,
                'version_pointage_id' => $pointage->versionCourante->id,
                'decision' => 'VALIDE_AAF',
            ], [
                'auteur_id' => $aaf->id,
            ]);

            $stage = $pointage->stage;
            $contratActif = $stage->contrats()->latest('date_debut')->latest('id')->first();
            $montantPaiement = (float) ($contratActif?->prime_mensuelle ?? 0);

            $droitPaiement = DroitPaiement::where('stage_id', $stage->id)
                ->where('periode_id', $pointage->periode_id)
                ->where('nature', 'PRESENCE')
                ->whereNull('annule_le')
                ->first();

            if ($droitPaiement) {
                return $droitPaiement;
            }

            return DroitPaiement::create([
                'stage_id' => $stage->id,
                'pointage_id' => $pointage->id,
                'periode_id' => $pointage->periode_id,
                'source_financement_id' => $stage->source_financement_id,
                'nature' => 'PRESENCE',
                'montant' => $montantPaiement,
                'statut' => 'OUVERT',
            ]);
        });
    }

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
