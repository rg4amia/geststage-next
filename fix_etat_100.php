<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use App\Models\Workflow\InstanceParcours;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Find all stages that have etat_chef_agence = 100 in legacy
$legacyIds100 = DB::connection('legacy')->table('contrats_pae')
    ->where('etat_chef_agence', 100)
    ->pluck('id')->toArray();

// Find their corresponding workflow instances currently in dmg_attente_paiement_presence
$instances = InstanceParcours::where('corbeille_actuelle', 'dmg_attente_paiement_presence')
    ->whereHas('stage', function ($q) use ($legacyIds100) {
        $q->whereIn('ancien_id', $legacyIds100);
    })->get();

echo 'Found '.$instances->count()." instances that should NOT be in DMG (etat_chef_agence=100).\n";
