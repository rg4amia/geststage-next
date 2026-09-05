<?php

$file = 'routes/web.php';
$content = file_get_contents($file);
$search = "Route::post('/dmg/paiements/stagiaires', [PaiementDmgController::class, 'stagiairesByDossier'])->middleware('can:voir_paiements_dmg')->name('dmg.paiements.stagiaires');";
$replace = $search."\n    Route::get('/dmg/paiements/documents', [PaiementDmgController::class, 'documentsByStage'])->middleware('can:voir_paiements_dmg')->name('dmg.paiements.documents');";
$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
