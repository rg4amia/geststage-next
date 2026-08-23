<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$q = DB::connection('legacy')->table('contrats_pae')->where('id', 62872);

echo "Exists: " . $q->count() . "\n";
echo "etapetraitement_id != 5: " . (clone $q)->where('etapetraitement_id', '!=', 5)->count() . "\n";
echo "etat_chef_agence == 2: " . (clone $q)->where('etat_chef_agence', 2)->count() . "\n";
echo "avis_contrat == 1: " . (clone $q)->where('avis_contrat', 1)->count() . "\n";
echo "source_financement != 5: " . (clone $q)->where('source_financement', '!=', 5)->count() . "\n";
echo "originestagiaire_id NOT IN (4, 3, 19): " . (clone $q)->whereNotIn('originestagiaire_id', [4, 3, 19])->count() . "\n";
echo "pointages condition: " . (clone $q)->whereExists(function ($query) {
        $query->select(DB::raw(1))
            ->from('pointage_models')
            ->whereColumn('pointage_models.stagiaire_id', 'contrats_pae.id')
            ->where('mois', '2026-08')
            ->where('situationstage_id', 1)
            ->where('status_cip', 1)
            ->where('status_ca', 1)
            ->whereNotNull('date_ca');
    })->count() . "\n";
echo "date bounds: " . (clone $q)->where('date_debut', '<=', '2026-08-31')->where('date_fin', '>=', '2026-08-01')->count() . "\n";
