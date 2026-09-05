<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$countMois = DB::table('pointages')
    ->join('instances_parcours', 'instances_parcours.pointage_id', '=', 'pointages.id')
    ->join('periodes', 'periodes.id', '=', 'pointages.periode_id')
    ->where('instances_parcours.corbeille_actuelle', 'dmg_attente_paiement_presence')
    ->where('periodes.code', '2026-08')
    ->whereNotExists(function ($query) {
        $query->select(DB::raw(1))
            ->from('droits_paiement')
            ->whereColumn('droits_paiement.pointage_id', 'pointages.id');
    })
    ->count();

echo "Pointages in DMG but missing DroitPaiement for August 2026: $countMois\n";
