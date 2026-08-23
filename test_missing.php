<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$q = App\Models\Payment\Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement.stage', fn($s) => $s->whereYear('date_debut', 2026)->whereMonth('date_debut', 8))
    ->whereHas('droitPaiement.stage.instanceParcours', fn($i) => $i->where('corbeille_actuelle', 'dmg_attente_paiement_demarrage'));

$ids = $q->get()->pluck('droitPaiement.stage.ancien_id');

$withPointage = DB::connection('legacy')->table('pointage_models')
    ->whereIn('stagiaire_id', $ids)
    ->where('mois', '2026-08')
    ->count();

echo "Total in new DMG: " . $ids->count() . "\n";
echo "Have pointage in legacy for Aug 2026: " . $withPointage . "\n";
