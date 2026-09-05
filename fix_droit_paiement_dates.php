<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use App\Models\Payment\DroitPaiement;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$legacyDates = DB::connection('legacy')->table('contrats_pae')
    ->whereNotNull('date_chef_agence')
    ->where('date_chef_agence', '!=', '0000-00-00 00:00:00')
    ->pluck('date_chef_agence', 'id')
    ->toArray();

$droits = DroitPaiement::with('stage')->get();
$updates = 0;
foreach ($droits as $droit) {
    if ($droit->stage && $droit->stage->ancien_id) {
        $legacyId = $droit->stage->ancien_id;
        if (isset($legacyDates[$legacyId])) {
            $legacyDate = $legacyDates[$legacyId];
            if ($droit->created_at->format('Y-m-d H:i:s') !== $legacyDate) {
                // Update timestamps without firing events
                DB::table('droits_paiement')
                    ->where('id', $droit->id)
                    ->update([
                        'created_at' => $legacyDate,
                        'updated_at' => $legacyDate,
                    ]);
                $updates++;
            }
        }
    }
}
echo "Updated $updates droits_paiement records.\n";
