<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use App\Models\Payment\Paiement;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// the 427 records we have in the new project
$q = Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement.stage', fn ($s) => $s->whereYear('date_debut', 2026)->whereMonth('date_debut', 8))
    ->whereHas('droitPaiement.stage.instanceParcours', fn ($i) => $i->where('corbeille_actuelle', 'dmg_attente_paiement_demarrage'));
$ancienIds = $q->get()->pluck('droitPaiement.stage.ancien_id')->toArray();

$rows = DB::connection('legacy')->table('contrats_pae')->whereIn('id', $ancienIds)->get();

$groups = [];
foreach ($rows as $row) {
    // group by common fields
    $keys = [
        'etapetraitement_id' => $row->etapetraitement_id,
        'etat_dossier' => $row->etat_dossier,
        'active_dmg' => $row->active_dmg,
        'trans_attestation_grp' => $row->trans_attestation_grp,
        'trans_cniattest' => $row->trans_cniattest,
        'etat_fin_stage' => $row->etat_fin_stage,
        'depot_dmg_status' => $row->depot_dmg_status,
        'doubloncheck' => $row->doubloncheck,
        'valid' => $row->valid,
    ];

    foreach ($keys as $k => $v) {
        $groups[$k][$v] = ($groups[$k][$v] ?? 0) + 1;
    }
}

print_r($groups);
$date_enregistrement_counts = [];
foreach ($rows as $row) {
    $ym = substr($row->date_enregistrement, 0, 7);
    $date_enregistrement_counts[$ym] = ($date_enregistrement_counts[$ym] ?? 0) + 1;
}
print_r($date_enregistrement_counts);
