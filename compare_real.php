<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$service = app(App\Domain\Payment\Services\DmgService::class);
$paiements = $service->attentePaiementPresence([], '2026-08')->get();
$newIds = [];
foreach ($paiements as $p) {
    if ($p->droitPaiement && $p->droitPaiement->stage && $p->droitPaiement->stage->ancien_id) {
        $newIds[] = $p->droitPaiement->stage->ancien_id;
    }
}

echo "New system has " . count($newIds) . " records.\n";
