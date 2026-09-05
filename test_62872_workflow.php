<?php

use App\Models\Internship\Stage;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$stage = Stage::where('ancien_id', 62872)->first();
$instance = $stage->instanceParcours;
echo 'Corbeille actuelle: '.$instance->corbeille_actuelle."\n";
