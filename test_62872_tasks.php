<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$stage = App\Models\Internship\Stage::where('ancien_id', 62872)->first();
$instance = $stage->instanceParcours;
$taches = $instance->taches()->whereIn('statut', ['OUVERTE', 'REVENDIQUEE'])->get();
foreach ($taches as $tache) {
    echo "Tache ouverte: " . $tache->code_corbeille . "\n";
}
