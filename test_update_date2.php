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

$globalCount = 0;
foreach ($paiements as $p) {
    $legacyId = $p->droitPaiement->stage->ancien_id;
    if (isset($legacyDates[$legacyId])) {
        $date_chef_agence = $legacyDates[$legacyId];
        $date_debut = $p->droitPaiement->stage->date_debut;
        
        $day_debut = (int) substr($date_debut, 8, 2);
        $month_debut = (int) substr($date_debut, 5, 2);
        $day_ca = (int) substr($date_chef_agence, 8, 2);
        $month_ca = (int) substr($date_chef_agence, 5, 2);
        
        $isC1 = ($day_debut >= 1 && $day_debut <= 5) && ($month_ca == $month_debut && $day_ca >= 11 || $month_ca > $month_debut);
        $isC2 = ($day_debut == 10) && ($month_ca == $month_debut && $day_ca >= 21 || $month_ca > $month_debut);
        $isC3 = ($day_debut == 20) && ($month_ca == $month_debut + 1);
        
        if (!$isC1 && !$isC2 && !$isC3) {
            $globalCount++;
        }
    }
}
echo "Global logic using legacy date_chef_agence: $globalCount\n";
