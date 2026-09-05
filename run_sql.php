<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$bindings = [
    'A_TRAITER',
    '2026-08',
    'dmg_attente_paiement_presence',
    'OUVERTE',
    'REVENDIQUEE',
    'dmg_attente_paiement_presence',
    'OUVERTE',
    'REVENDIQUEE',
    '2026-08-31',
    '2026-08-01',
];
$sql = 'select count(*) from "paiements" where "statut" = ? and exists (select * from "droits_paiement" where "paiements"."droit_paiement_id" = "droits_paiement"."id" and "annule_le" is null and exists (select * from "periodes" where "droits_paiement"."periode_id" = "periodes"."id" and "code" = ?) and exists (select * from "stages" where "droits_paiement"."stage_id" = "stages"."id" and exists (select * from "contrats" where "stages"."id" = "contrats"."stage_id" and "contrats"."deleted_at" is null) and "stages"."deleted_at" is null) and exists (select * from "stages" where "droits_paiement"."stage_id" = "stages"."id" and exists (select * from "instances_parcours" where "stages"."id" = "instances_parcours"."stage_id" and "terminee_le" is null and (exists (select * from "taches_parcours" where "instances_parcours"."id" = "taches_parcours"."instance_parcours_id" and "code_corbeille" = ? and "statut" in (?, ?)) or ("corbeille_actuelle" = ? and (not exists (select * from "taches_parcours" where "instances_parcours"."id" = "taches_parcours"."instance_parcours_id") or (not exists (select * from "taches_parcours" where "instances_parcours"."id" = "taches_parcours"."instance_parcours_id" and "statut" in (?, ?))))))) and "stages"."deleted_at" is null)) and exists (select * from "droits_paiement" where "paiements"."droit_paiement_id" = "droits_paiement"."id" and exists (select * from "stages" where "droits_paiement"."stage_id" = "stages"."id" and "date_debut" <= ? and "date_fin_prevue" >= ? and "stages"."deleted_at" is null))';

$res = DB::select($sql, $bindings);
print_r($res);
