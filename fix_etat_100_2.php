<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$legacyIds100 = DB::connection('legacy')->table('contrats_pae')
    ->where('etat_chef_agence', 100)
    ->pluck('id')->toArray();

$pointageIds = DB::table('pointages')
    ->join('instances_parcours', 'instances_parcours.pointage_id', '=', 'pointages.id')
    ->join('stages', 'pointages.stage_id', '=', 'stages.id')
    ->where('instances_parcours.corbeille_actuelle', 'dmg_attente_paiement_presence')
    ->whereIn('stages.ancien_id', $legacyIds100)
    ->pluck('instances_parcours.id')
    ->toArray();

echo 'Found '.count($pointageIds)." instances that should NOT be in DMG (etat_chef_agence=100).\n";
