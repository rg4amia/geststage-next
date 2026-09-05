<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use App\Models\Payment\Paiement;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$legacyIds = DB::connection('legacy')->table('contrats_pae')
    ->where('etapetraitement_id', 5)
    ->pluck('id')->toArray();

$count = Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement', function ($query) {
        $query->where('nature', 'PRESENCE')
            ->whereHas('periode', fn ($q) => $q->where('code', '2026-08'));
    })
    ->whereHas('droitPaiement.stage', function ($q) use ($legacyIds) {
        $q->whereIn('ancien_id', $legacyIds);
    })
    ->count();

echo "Records that were in DESSE ajournement: $count\n";
