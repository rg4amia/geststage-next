<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\Eloquent\Builder;

$c1Closure = function (Builder $d) {
    // join isn't needed if we use whereHas('stage') but we need to compare them
    // actually inside whereHas('droitPaiement'), joining stages is perfectly fine and performant!
    $d->join('stages as s', 's.id', '=', 'droits_paiement.stage_id')
      ->whereRaw('EXTRACT(DAY FROM s.date_debut) BETWEEN 1 AND 5')
      ->where(function (Builder $q) {
          $q->whereRaw('EXTRACT(MONTH FROM droits_paiement.created_at) = EXTRACT(MONTH FROM s.date_debut) AND EXTRACT(DAY FROM droits_paiement.created_at) >= 11')
            ->orWhereRaw('EXTRACT(MONTH FROM droits_paiement.created_at) > EXTRACT(MONTH FROM s.date_debut)');
      });
};

$c2Closure = function (Builder $d) {
    $d->join('stages as s', 's.id', '=', 'droits_paiement.stage_id')
      ->whereRaw('EXTRACT(DAY FROM s.date_debut) = 10')
      ->where(function (Builder $q) {
          $q->whereRaw('EXTRACT(MONTH FROM droits_paiement.created_at) = EXTRACT(MONTH FROM s.date_debut) AND EXTRACT(DAY FROM droits_paiement.created_at) >= 21')
            ->orWhereRaw('EXTRACT(MONTH FROM droits_paiement.created_at) > EXTRACT(MONTH FROM s.date_debut)');
      });
};

$c3Closure = function (Builder $d) {
    $d->join('stages as s', 's.id', '=', 'droits_paiement.stage_id')
      ->whereRaw('EXTRACT(DAY FROM s.date_debut) = 20')
      ->whereRaw('EXTRACT(MONTH FROM droits_paiement.created_at) > EXTRACT(MONTH FROM s.date_debut)');
};

$c1 = App\Models\Payment\Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement.stage', fn($s) => $s->whereYear('date_debut', 2026)->whereMonth('date_debut', 8))
    ->whereHas('droitPaiement.stage.instanceParcours', fn($i) => $i->where('corbeille_actuelle', 'dmg_attente_paiement_demarrage'))
    ->whereHas('droitPaiement', $c1Closure)->count();

$c2 = App\Models\Payment\Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement.stage', fn($s) => $s->whereYear('date_debut', 2026)->whereMonth('date_debut', 8))
    ->whereHas('droitPaiement.stage.instanceParcours', fn($i) => $i->where('corbeille_actuelle', 'dmg_attente_paiement_demarrage'))
    ->whereHas('droitPaiement', $c2Closure)->count();

$c3 = App\Models\Payment\Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement.stage', fn($s) => $s->whereYear('date_debut', 2026)->whereMonth('date_debut', 8))
    ->whereHas('droitPaiement.stage.instanceParcours', fn($i) => $i->where('corbeille_actuelle', 'dmg_attente_paiement_demarrage'))
    ->whereHas('droitPaiement', $c3Closure)->count();

$global = App\Models\Payment\Paiement::where('statut', 'A_TRAITER')
    ->whereHas('droitPaiement.stage', fn($s) => $s->whereYear('date_debut', 2026)->whereMonth('date_debut', 8))
    ->whereHas('droitPaiement.stage.instanceParcours', fn($i) => $i->where('corbeille_actuelle', 'dmg_attente_paiement_demarrage'))
    ->whereDoesntHave('droitPaiement', $c1Closure)
    ->whereDoesntHave('droitPaiement', $c2Closure)
    ->whereDoesntHave('droitPaiement', $c3Closure)
    ->count();

echo "C1: $c1, C2: $c2, C3: $c3, Global: $global\n";
