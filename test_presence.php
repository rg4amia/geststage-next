<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$legacyPointages = DB::connection('legacy')->table('pointage_models')
    ->where('mois', '2026-08')
    ->where('status_cip', 1)
    ->where('status_ca', 1)
    ->whereNotNull('date_ca')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
            ->from('paiement_models')
            ->whereColumn('paiement_models.stagiaire_id', 'pointage_models.stagiaire_id')
            ->where('mois', '2026-08');
    })
    ->count();
echo "Legacy valid pointages without payment for 2026-08: $legacyPointages\n";

$newPointages = App\Models\Payment\Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement', function ($query) {
        $query->where('nature', 'PRESENCE')
            ->whereHas('periode', fn($q) => $q->where('code', '2026-08'));
    })
    ->count();
echo "New system DroitPaiement PRESENCE for 2026-08: $newPointages\n";
