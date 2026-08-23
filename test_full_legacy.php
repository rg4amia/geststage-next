<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$q = DB::connection('legacy')->table('contrats_pae')
    ->whereYear('date_debut', 2026)
    ->whereMonth('date_debut', 8)
    ->where('etat_chef_agence', 2)
    ->where('avis_contrat', 1)
    ->where('source_financement', '!=', 5)
    ->where('etapetraitement_id', '!=', 5)
    ->where('etatrenouvellement_id', '!=', 1)
    ->whereNull('deleted_at')
    ->whereExists(function ($query) {
        $query->select(DB::raw(1))
            ->from('pointage_models')
            ->whereColumn('pointage_models.stagiaire_id', 'contrats_pae.id')
            ->where('mois', '2026-08')
            ->where('situationstage_id', 1)
            ->where('status_cip', 1)
            ->where('status_ca', 1)
            ->whereNotNull('date_ca');
    })
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
            ->from('paiement_models')
            ->whereColumn('paiement_models.stagiaire_id', 'contrats_pae.id');
    });

echo $q->count() . "\n";
