<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use App\Models\Attendance\Pointage;
use App\Models\Internship\Stage;
use App\Models\Workflow\InstanceParcours;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$legacyIds = DB::connection('legacy')->table('contrats_pae')
    ->where('etat_chef_agence', 100)
    ->pluck('id')->toArray();
$stages = Stage::whereIn('ancien_id', $legacyIds)->pluck('id')->toArray();
$pointagesInStage = Pointage::whereIn('stage_id', $stages)->pluck('id')->toArray();
$instances = InstanceParcours::whereIn('pointage_id', $pointagesInStage)->get();

foreach ($instances as $instance) {
    echo 'Pointage ID '.$instance->pointage_id.' is in '.$instance->corbeille_actuelle."\n";
}
