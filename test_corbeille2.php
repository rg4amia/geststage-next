<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = App\Models\Attendance\Pointage::whereHas('instanceParcours', fn($q) => $q->where('corbeille_actuelle', 'dmg_attente_paiement_presence'))
    ->whereDoesntHave('droitPaiement')
    ->count();
    
echo "Pointages in DMG but missing DroitPaiement: $count\n";

$countMois = App\Models\Attendance\Pointage::whereHas('instanceParcours', fn($q) => $q->where('corbeille_actuelle', 'dmg_attente_paiement_presence'))
    ->whereDoesntHave('droitPaiement')
    ->whereHas('periode', fn($q) => $q->where('code', '2026-08'))
    ->count();
    
echo "Pointages in DMG but missing DroitPaiement for August 2026: $countMois\n";
