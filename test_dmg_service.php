<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(App\Domain\Payment\Services\DmgService::class);
$count = $service->attentePaiementPresence([], '2026-08')->count();
echo "DmgService count for 2026-08: $count\n";
