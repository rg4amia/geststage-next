<?php

use App\Models\Payment\DroitPaiement;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$count = DroitPaiement::whereHas('stage.sourceFinancement', fn ($sf) => $sf->where('code', '!=', 'PEJEDEC'))->count();
echo "DroitPaiement not PEJEDEC: $count\n";

$countAll = DroitPaiement::count();
echo "DroitPaiement Total: $countAll\n";
