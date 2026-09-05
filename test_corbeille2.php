<?php

use App\Models\Attendance\Pointage;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$count = Pointage::whereHas('instanceParcours', fn ($q) => $q->where('corbeille_actuelle', 'dmg_attente_paiement_presence'))
    ->whereDoesntHave('droitPaiement')
    ->count();

echo "Pointages in DMG but missing DroitPaiement: $count\n";

$countMois = Pointage::whereHas('instanceParcours', fn ($q) => $q->where('corbeille_actuelle', 'dmg_attente_paiement_presence'))
    ->whereDoesntHave('droitPaiement')
    ->whereHas('periode', fn ($q) => $q->where('code', '2026-08'))
    ->count();

echo "Pointages in DMG but missing DroitPaiement for August 2026: $countMois\n";
