<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Models\Internship\Stage;
use App\Models\Workflow\InstanceParcours;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;

$legacyIds100 = DB::connection('legacy')->table('contrats_pae')
    ->where('etat_chef_agence', 100)
    ->pluck('id')->toArray();

$stages = Stage::whereIn('ancien_id', $legacyIds100)->pluck('id')->toArray();

$instances = InstanceParcours::whereIn('corbeille_actuelle', ['dmg_attente_paiement_presence', 'dmg_attente_paiement_demarrage'])
    ->whereIn('stage_id', $stages)
    ->get();

echo "Found " . $instances->count() . " Stage instances to fix.\n";

foreach ($instances as $instance) {
    echo "Fixing Stage ID " . $instance->stage_id . "\n";
    $instance->corbeille_actuelle = 'ca_attente_validation_demarrage';
    $instance->save();

    // Delete DroitsPaiement
    $droits = DroitPaiement::where('stage_id', $instance->stage_id)->get();
    foreach ($droits as $droit) {
        Paiement::where('droit_paiement_id', $droit->id)->delete();
        $droit->delete();
    }
}
echo "Done.\n";
