<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$ids = DB::connection('legacy')->table('contrats_pae')
    ->whereYear('date_debut', 2026)
    ->whereMonth('date_debut', 8)
    ->whereNull('deleted_at')
    ->where('etapetraitement_id', 2)
    ->where('etat_chef_agence', 2)
    ->pluck('id');

$withPayment = DB::connection('legacy')->table('paiement_models')
    ->whereIn('stagiaire_id', $ids)
    ->pluck('stagiaire_id')
    ->unique();

$withoutPayment = $ids->diff($withPayment);
echo "Without Payment (etape 2): " . $withoutPayment->count() . "\n";
