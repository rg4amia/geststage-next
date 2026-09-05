<?php

use App\Models\Payment\Paiement;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$count = Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement', function ($query) {
        $query->where('nature', 'PRESENCE')
            ->whereHas('periode', fn ($q) => $q->where('code', '2026-08'));
    })
    ->whereHas('droitPaiement.stage', function ($q) {
        $q->where('source_financement_id', 4);
    })
    ->count();

echo "Records with source_financement = PEJEDEC (4): $count\n";
