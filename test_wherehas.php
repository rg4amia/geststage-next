<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = App\Models\Payment\DroitPaiement::whereHas('stage.sourceFinancement', fn ($sf) => $sf->where('code', '!=', 'PEJEDEC'))->count();
echo "DroitPaiement not PEJEDEC: $count\n";

$countAll = App\Models\Payment\DroitPaiement::count();
echo "DroitPaiement Total: $countAll\n";
