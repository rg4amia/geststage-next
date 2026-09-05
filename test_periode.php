<?php

use App\Models\Payment\DroitPaiement;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$periodes = DroitPaiement::where('nature', 'PRESENCE')
    ->whereHas('paiements', fn ($q) => $q->where('statut', 'A_TRAITER'))
    ->with('periode')
    ->get()
    ->groupBy(fn ($d) => $d->periode ? $d->periode->code : 'null')
    ->map->count();
print_r($periodes->toArray());
