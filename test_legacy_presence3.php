<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$legacyCount = DB::connection('legacy')->table('contrats_pae')
    ->where('etapetraitement_id', '!=', 5)
    ->where('etat_chef_agence', 2)
    ->where('avis_contrat', 1)
    ->where('source_financement', '!=', 5)
    ->whereNotIn('originestagiaire_id', [4, 3, 19])
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
    ->where(function ($query) {
        $query->where(function ($query) {
            $query->where('etatrenouvellement_id', 1)
                ->where(function ($query) {
                    $query->where(function ($query) {
                        $query->whereYear('date_debut', '2026')
                            ->whereMonth('date_debut', '08');
                    })->orWhere(function ($query) {
                        $query->whereYear('date_debut', '!=', '2026')
                            ->orWhereMonth('date_debut', '!=', '08');
                    });
                });
        })->orWhere(function ($query) {
            $query->where('etatrenouvellement_id', 0)
                ->where(function ($query) {
                    $query->whereYear('date_debut', '!=', '2026')
                        ->orWhereMonth('date_debut', '!=', '08');
                });
        });
    })
    ->where('date_debut', '<=', '2026-08-31')
    ->where('date_fin', '>=', '2026-08-01')
    ->count();

echo "Exact legacy count for Presence: $legacyCount\n";
