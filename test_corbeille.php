<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$allCorbeilles = App\Models\Workflow\InstanceParcours::whereNotNull('pointage_id')->pluck('corbeille_actuelle')->countBy();
print_r($allCorbeilles->toArray());
