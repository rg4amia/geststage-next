<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(App\Domain\Payment\Services\DmgService::class);
$query = $service->attentePaiementPresence([], '2026-08');
echo $query->toSql() . "\n";
print_r($query->getBindings());
