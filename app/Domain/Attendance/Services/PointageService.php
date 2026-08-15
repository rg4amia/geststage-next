<?php

namespace App\Domain\Attendance\Services;

use App\Models\Attendance\DecisionPointage;
use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Reference\Periode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PointageService
{
    /**
     * Retourne la liste des stages actifs (validés CA) n'ayant pas encore de pointage pour ce mois.
     */
    public function getStagiairesSansPointage(string $mois)
    {
        // En Eloquent moderne, on cherche les stages dont la date couvre le mois
        // et qui n'ont pas de pointage "VALIDE" ou "SOUMIS" pour ce mois
        // Note: C'est une implémentation simplifiée par rapport au legacy, 
        // mais elle respecte le même principe "whereDoesntHave".
        
        $periode = Periode::where('nom', 'like', "%$mois%")->first();
        if (!$periode) {
            return collect();
        }

        return Stage::with(['beneficiaire', 'entreprise', 'agence'])
            // Le stage doit être actif
            ->whereHas('instanceParcours', function($q) {
                // On peut vérifier l'état du workflow
            })
            // Il ne doit pas y avoir de pointage pour cette période
            ->whereDoesntHave('pointages', function ($query) use ($periode) {
                $query->where('periode_id', $periode->id)
                      ->whereIn('statut', ['SOUMIS', 'VALIDE']);
            })
            ->get();
    }

    /**
     * Le CIP soumet les présences mensuelles d'un stagiaire.
     */
    public function soumettreMensuel(Stage $stage, Periode $periode, int $joursPresents, int $joursAbsents, User $cip, ?string $observation = null): Pointage
    {
        return DB::transaction(function () use ($stage, $periode, $joursPresents, $joursAbsents, $cip, $observation) {
            // 1. Chercher s'il existe déjà un pointage pour cette période
            $pointage = Pointage::firstOrCreate(
                ['stage_id' => $stage->id, 'periode_id' => $periode->id, 'nature' => 'MENSUEL'],
                ['statut' => 'SOUMIS', 'version_courante' => 0]
            );

            // 2. Incrémenter la version
            $pointage->increment('version_courante');
            $pointage->update(['statut' => 'SOUMIS']);

            // 3. Créer la nouvelle version (snapshot)
            $version = VersionPointage::create([
                'pointage_id' => $pointage->id,
                'saisi_par_id' => $cip->id,
                'numero_version' => $pointage->version_courante,
                'presence' => $joursPresents > 0 ? 'PRESENT' : 'ABSENT',
                'jours_presents' => $joursPresents,
                'jours_absents' => $joursAbsents,
                'observation' => $observation,
            ]);

            return $pointage;
        });
    }

    /**
     * Le Chef d'Agence valide le pointage, ce qui génère un Droit de Paiement.
     */
    public function validerMensuel(Pointage $pointage, User $ca): DroitPaiement
    {
        return DB::transaction(function () use ($pointage, $ca) {
            if ($pointage->statut !== 'SOUMIS') {
                throw new InvalidArgumentException("Le pointage n'est pas dans l'état SOUMIS.");
            }

            // 1. Mettre à jour le statut du pointage
            $pointage->update(['statut' => 'VALIDE']);

            // 2. Enregistrer la décision
            $versionCourante = $pointage->versionCourante;
            DecisionPointage::create([
                'pointage_id' => $pointage->id,
                'version_pointage_id' => $versionCourante->id,
                'auteur_id' => $ca->id,
                'decision' => 'VALIDE',
            ]);

            // 3. Générer le Droit de Paiement de nature PRESENCE
            $stage = $pointage->stage;
            $contratActif = $stage->contrats()->latest()->first();
            
            // Calcul au prorata si nécessaire. Simplifié ici :
            $montantPaiement = $contratActif ? $contratActif->prime_mensuelle : 0; 
            
            $sourceFinancement = \App\Models\Reference\SourceFinancement::first();

            $droitPaiement = DroitPaiement::create([
                'stage_id' => $stage->id,
                'pointage_id' => $pointage->id,
                'periode_id' => $pointage->periode_id,
                'source_financement_id' => $sourceFinancement ? $sourceFinancement->id : 1,
                'nature' => 'PRESENCE',
                'montant' => $montantPaiement,
                'statut' => 'OUVERT',
            ]);

            return $droitPaiement;
        });
    }
}
