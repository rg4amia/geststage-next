<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$legacyDates = DB::connection('legacy')->table('contrats_pae')
    ->whereYear('date_debut', 2026)
    ->whereMonth('date_debut', 8)
    ->pluck('date_chef_agence', 'id')
    ->toArray();

$paiements = App\Models\Payment\Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement.stage', fn($s) => $s->whereYear('date_debut', 2026)->whereMonth('date_debut', 8))
    ->whereHas('droitPaiement.stage.instanceParcours', fn($i) => $i->where('corbeille_actuelle', 'dmg_attente_paiement_demarrage'))
    ->with('droitPaiement.stage')
    ->get();

$updates = 0;
foreach ($paiements as $p) {
    $legacyId = $p->droitPaiement->stage->ancien_id;
    if (isset($legacyDates[$legacyId])) {
        $legacyDate = $legacyDates[$legacyId];
        // echo "Stage $legacyId: legacy date_chef_agence = $legacyDate\n";
        if (substr($legacyDate, 8, 2) < 11) {
            $updates++;
        }
    }
}
echo "Records with date_chef_agence < 11: $updates\n";
