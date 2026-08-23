<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$service = app(App\Domain\Payment\Services\DmgService::class);
$paiements = $service->attentePaiementPresence([], '2026-08')->with('droitPaiement.stage')->get();
$newIds = [];
foreach ($paiements as $p) {
    if ($p->droitPaiement && $p->droitPaiement->stage && $p->droitPaiement->stage->ancien_id) {
        $newIds[] = $p->droitPaiement->stage->ancien_id;
    }
}

$legacyIds = DB::connection('legacy')->table('contrats_pae')
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
    ->pluck('id')
    ->toArray();

$diff = array_diff($newIds, $legacyIds);
$diffReverse = array_diff($legacyIds, $newIds);

echo "New system has " . count($newIds) . " records.\n";
echo "Legacy system has " . count($legacyIds) . " records.\n";
echo "Records in New but NOT in Legacy: " . count($diff) . "\n";
echo "Records in Legacy but NOT in New: " . count($diffReverse) . "\n";
