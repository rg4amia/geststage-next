<?php

use App\Models\Internship\Stage;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$stage = Stage::where('ancien_id', 62872)->first();
$instance = $stage->instanceParcours;
$taches = $instance->taches()->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])->get();
foreach ($taches as $tache) {
    echo 'Tache ouverte: '.$tache->code_corbeille."\n";
}
