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
        $q->whereIn('origine_stagiaire_id', [3, 4]);
    })
    ->count();

echo "Records with origine_stagiaire_id IN (3, 4) [Sans incidence & Pré-financé]: $count\n";
