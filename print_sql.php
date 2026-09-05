<?php

use App\Domain\Payment\Services\DmgService;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$service = app(DmgService::class);
$query = $service->attentePaiementPresence([], '2026-08');
echo $query->toSql()."\n";
print_r($query->getBindings());
