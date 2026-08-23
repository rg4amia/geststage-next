<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$periodes = App\Models\Payment\DroitPaiement::where('nature', 'PRESENCE')
    ->whereHas('paiements', fn($q) => $q->where('statut', 'A_TRAITER'))
    ->with('periode')
    ->get()
    ->groupBy(fn($d) => $d->periode ? $d->periode->code : 'null')
    ->map->count();
print_r($periodes->toArray());
