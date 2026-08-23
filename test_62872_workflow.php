<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$stage = App\Models\Internship\Stage::where('ancien_id', 62872)->first();
$instance = $stage->instanceParcours;
echo "Corbeille actuelle: " . $instance->corbeille_actuelle . "\n";
