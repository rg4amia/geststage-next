<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$bindings = [
    'A_TRAITER',
    '2026-08',
];
$sql = 'select count(*) from "paiements" where "statut" = ? and exists (select * from "droits_paiement" where "paiements"."droit_paiement_id" = "droits_paiement"."id" and "annule_le" is null and exists (select * from "periodes" where "droits_paiement"."periode_id" = "periodes"."id" and "code" = ?))';

$res = DB::select($sql, $bindings);
print_r($res);
