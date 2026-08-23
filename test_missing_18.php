<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

// Query 1: The legacy IDs that should be in Global (256 records)
$legacyIds = DB::connection('legacy')->table('contrats_pae')
    ->whereYear('date_debut', 2026)
    ->whereMonth('date_debut', 8)
    ->where('etat_chef_agence', 2)
    ->where('avis_contrat', 1)
    ->where('source_financement', '!=', 5)
    ->where('etapetraitement_id', '!=', 5)
    ->where('etatrenouvellement_id', '!=', 1)
    ->whereNull('deleted_at')
    ->whereExists(function ($query) {
        $query->select(DB::raw(1))
            ->from('pointage_models')
            ->whereColumn('pointage_models.stagiaire_id', 'contrats_pae.id')
            ->where('mois', '2026-08')
            ->where('situationstage_id', 1)
            ->where('status_cip', 1)
            ->where('status_ca', 1)
            ->whereNotNull('date_ca');
    })
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
            ->from('paiement_models')
            ->whereColumn('paiement_models.stagiaire_id', 'contrats_pae.id');
    })
    ->where(function ($query) {
        $query->whereNot(function ($query) {
            $query->whereRaw('DAY(date_debut) >= 1 AND DAY(date_debut) <= 5')
                ->where(function ($query) {
                    $query->whereRaw('MONTH(date_chef_agence) = MONTH(date_debut) AND DAY(date_chef_agence) >= 11')
                        ->orWhereRaw('MONTH(date_chef_agence) > MONTH(date_debut)');
                });
        })->whereNot(function ($query) {
            $query->whereRaw('DAY(date_debut) = 10')
                ->where(function ($query) {
                    $query->whereRaw('MONTH(date_chef_agence) = MONTH(date_debut) AND DAY(date_chef_agence) >= 21')
                        ->orWhereRaw('MONTH(date_chef_agence) > MONTH(date_debut)');
                });
        })->whereNot(function ($query) {
            $query->whereRaw('DAY(date_debut) = 20')
                  ->whereRaw('MONTH(date_chef_agence) = MONTH(date_debut) + 1');
        });
    })->pluck('id')->toArray();

// Query 2: The legacy IDs currently in the new system's Global (238 records)
$c1Closure = function ($d) {
    $d->join('stages as s', 's.id', '=', 'droits_paiement.stage_id')
      ->whereRaw('EXTRACT(DAY FROM s.date_debut) BETWEEN 1 AND 5')
      ->where(function ($q) {
          $q->whereRaw('EXTRACT(MONTH FROM droits_paiement.created_at) = EXTRACT(MONTH FROM s.date_debut) AND EXTRACT(DAY FROM droits_paiement.created_at) >= 11')
            ->orWhereRaw('EXTRACT(MONTH FROM droits_paiement.created_at) > EXTRACT(MONTH FROM s.date_debut)');
      });
};
$c2Closure = function ($d) {
    $d->join('stages as s', 's.id', '=', 'droits_paiement.stage_id')
      ->whereRaw('EXTRACT(DAY FROM s.date_debut) = 10')
      ->where(function ($q) {
          $q->whereRaw('EXTRACT(MONTH FROM droits_paiement.created_at) = EXTRACT(MONTH FROM s.date_debut) AND EXTRACT(DAY FROM droits_paiement.created_at) >= 21')
            ->orWhereRaw('EXTRACT(MONTH FROM droits_paiement.created_at) > EXTRACT(MONTH FROM s.date_debut)');
      });
};
$c3Closure = function ($d) {
    $d->join('stages as s', 's.id', '=', 'droits_paiement.stage_id')
      ->whereRaw('EXTRACT(DAY FROM s.date_debut) = 20')
      ->whereRaw('EXTRACT(MONTH FROM droits_paiement.created_at) > EXTRACT(MONTH FROM s.date_debut)');
};

$newIds = App\Models\Payment\Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement.stage', fn($s) => $s->whereYear('date_debut', 2026)->whereMonth('date_debut', 8))
    ->whereHas('droitPaiement.stage.instanceParcours', fn($i) => $i->where('corbeille_actuelle', 'dmg_attente_paiement_demarrage'))
    ->whereDoesntHave('droitPaiement', $c1Closure)
    ->whereDoesntHave('droitPaiement', $c2Closure)
    ->whereDoesntHave('droitPaiement', $c3Closure)
    ->get()->pluck('droitPaiement.stage.ancien_id')->toArray();

$missing = array_diff($legacyIds, $newIds);

echo "Missing IDs count: " . count($missing) . "\n";
echo "First 5 missing IDs: " . implode(', ', array_slice($missing, 0, 5)) . "\n";

// Why are they missing? Let's check the first one in the new system
if (count($missing) > 0) {
    $firstMissing = reset($missing);
    $stage = App\Models\Stage\Stage::where('ancien_id', $firstMissing)->first();
    if (!$stage) {
        echo "Stage not migrated!\n";
    } else {
        echo "Statut stage: {$stage->statut_stage}\n";
        $corbeille = $stage->instanceParcours ? $stage->instanceParcours->corbeille_actuelle : 'No instance';
        echo "Corbeille actuelle: {$corbeille}\n";
        $hasDroit = App\Models\Payment\DroitPaiement::where('stage_id', $stage->id)->count();
        echo "Has DroitPaiement: $hasDroit\n";
    }
}
