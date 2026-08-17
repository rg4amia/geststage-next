import re

with open('routes/web.php', 'r') as f:
    content = f.read()

new_routes = """    Route::get('/cip/suivi', [MesStagiairesCipController::class, 'suivi'])->name('cip.suivi.index');

    // Nouveaux endpoints pour les boutons d'action (Mes Stagiaires)
    Route::get('/cip/mes-stagiaires/{id}/generer-contrat', [MesStagiairesCipController::class, 'genererContrat'])->name('cip.mes-stagiaires.generer-contrat');
    Route::post('/cip/mes-stagiaires/{id}/transferer-contrat', [MesStagiairesCipController::class, 'transfererContrat'])->name('cip.mes-stagiaires.transferer-contrat');
    Route::get('/cip/mes-stagiaires/{id}/generer-tresor-money', [MesStagiairesCipController::class, 'genererTresorMoney'])->name('cip.mes-stagiaires.generer-tresor-money');
    Route::post('/cip/mes-stagiaires/{id}/upload-tresor-money', [MesStagiairesCipController::class, 'uploadTresorMoney'])->name('cip.mes-stagiaires.upload-tresor-money');
    Route::delete('/cip/mes-stagiaires/{id}', [MesStagiairesCipController::class, 'destroy'])->name('cip.mes-stagiaires.destroy');"""

content = content.replace("    Route::get('/cip/suivi', [MesStagiairesCipController::class, 'suivi'])->name('cip.suivi.index');", new_routes)

with open('routes/web.php', 'w') as f:
    f.write(content)
