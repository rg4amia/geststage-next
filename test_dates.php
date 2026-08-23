<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ps = App\Models\Payment\Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement.stage', fn($s) => $s->whereYear('date_debut', 2026)->whereMonth('date_debut', 8)->whereDay('date_debut', '<=', 5))
    ->whereHas('droitPaiement.stage.instanceParcours', fn($i) => $i->where('corbeille_actuelle', 'dmg_attente_paiement_demarrage'))
    ->with('droitPaiement')
    ->get();

$days = [];
foreach($ps as $p) {
    $days[] = $p->droitPaiement->created_at->format('d');
}
print_r(array_count_values($days));
