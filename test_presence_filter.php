<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mois = '2026-08';
$startDate = \Carbon\Carbon::parse($mois)->startOfMonth()->toDateString();
$endDate = \Carbon\Carbon::parse($mois)->endOfMonth()->toDateString();

$query = App\Models\Payment\Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement', function ($query) use ($mois) {
        $query->where('nature', 'PRESENCE')
            ->whereHas('periode', fn($q) => $q->where('code', $mois));
    })
    ->whereHas('droitPaiement.stage', function ($s) use ($startDate, $endDate, $mois) {
        $s->where('date_debut', '<=', $endDate)
          ->where('date_fin_prevue', '>=', $startDate)
          ->where(function ($q) use ($mois) {
              // Exclude if it started in the same month (it's a Demarrage, not a Presence)
              // Unless we can identify renewals.
              $date = \Carbon\Carbon::parse($mois);
              $q->whereRaw('EXTRACT(MONTH FROM date_debut) != ? OR EXTRACT(YEAR FROM date_debut) != ?', [$date->month, $date->year]);
          });
    });

echo "Filtered Presence count: " . $query->count() . "\n";
