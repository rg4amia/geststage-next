<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = App\Models\Payment\Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement', function ($query) {
        $query->where('nature', 'PRESENCE')
            ->whereHas('periode', fn($q) => $q->where('code', '2026-08'));
    })
    ->whereHas('droitPaiement.stage', function ($q) {
        $q->where('source_financement_id', 4);
    })
    ->count();

echo "Records with source_financement = PEJEDEC (4): $count\n";
