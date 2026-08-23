<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$ids = DB::connection('legacy')->table('contrats_pae')
    ->whereYear('date_debut', 2026)
    ->whereMonth('date_debut', 8)
    ->whereNull('deleted_at')
    ->whereIn('etapetraitement_id', [2, 6, 13])
    ->where('etat_chef_agence', 2)
    ->pluck('id');

$withPayment = DB::connection('legacy')->table('paiement_models')
    ->whereIn('stagiaire_id', $ids)
    ->pluck('stagiaire_id')
    ->unique();

$withoutPayment = $ids->diff($withPayment);

echo "Total: " . $ids->count() . "\n";
echo "With Payment: " . $withPayment->count() . "\n";
echo "Without Payment: " . $withoutPayment->count() . "\n";
