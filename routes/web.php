<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Tables/GridJs/index')->name('dashboard');

    Route::resource('entreprises', \App\Http\Controllers\Company\EntrepriseController::class);
    Route::resource('offres', \App\Http\Controllers\Company\OffreEmploiController::class)->parameters([
        'offres' => 'offre_emploi'
    ]);
    Route::resource('inscriptions', \App\Http\Controllers\Registration\InscriptionController::class);

    // Phase CIP : Mes Stagiaires et Ajournements
    Route::get('/cip/mes-stagiaires', [\App\Http\Controllers\Cip\MesStagiairesCipController::class, 'index'])->name('cip.mes_stagiaires');
    Route::get('/cip/pointage/ajourne-dmg', [\App\Http\Controllers\Cip\MesStagiairesCipController::class, 'pointageAjourneDmg'])->name('cip.pointages.ajourne_dmg');

    // Phase 5 : Chef d'Agence (Démarrage & Omis)
    Route::get('/chefagence/validations', [\App\Http\Controllers\ChefAgence\IndexChefAgenceController::class, 'listeStagiaireAttenteValidation'])->name('chefagence.validations');
    Route::post('/chefagence/demarrage/{id}/valider', [\App\Http\Controllers\ChefAgence\IndexChefAgenceController::class, 'validerDemarrage'])->name('chefagence.demarrage.valider');
    Route::post('/chefagence/demarrage-omis/{id}/valider', [\App\Http\Controllers\ChefAgence\IndexChefAgenceController::class, 'validerDemarrageOmis'])->name('chefagence.demarrage_omis.valider');
    
    // Phase 5 : Pointages CIP
    Route::get('/cip/pointages', [\App\Http\Controllers\Cip\PointageCipController::class, 'stagiaireAttentePointage'])->name('cip.pointages.index');
    Route::post('/cip/pointages/soumettre/{stageId}', [\App\Http\Controllers\Cip\PointageCipController::class, 'soumettre'])->name('cip.pointages.soumettre');
    
    // Phase 5 : Pointages Chef d'Agence
    Route::get('/chefagence/pointages', [\App\Http\Controllers\ChefAgence\PointageChefAgenceController::class, 'pointageAttenteValidationByChefAgence'])->name('chefagence.pointages.index');
    Route::post('/chefagence/pointages/valider/{id}', [\App\Http\Controllers\ChefAgence\PointageChefAgenceController::class, 'valider'])->name('chefagence.pointages.valider');
    Route::post('/chefagence/pointages/ajourner/{id}', [\App\Http\Controllers\ChefAgence\PointageChefAgenceController::class, 'ajourner'])->name('chefagence.pointages.ajourner');
    Route::post('/chefagence/pointages-adp/{id}/valider', [\App\Http\Controllers\ChefAgence\PointageChefAgenceController::class, 'validerAjournementAdp'])->name('chefagence.pointages.adp.valider');
    Route::post('/chefagence/pointages-adp/{id}/rejeter', [\App\Http\Controllers\ChefAgence\PointageChefAgenceController::class, 'rejeterAjournementAdp'])->name('chefagence.pointages.adp.rejeter');

    // Phase 6 : DMG (Chaîne Financière)
    Route::get('/dmg/paiements', [\App\Http\Controllers\Dmg\PaiementDmgController::class, 'index'])->name('dmg.paiements.index');
    Route::post('/dmg/paiements/generer', [\App\Http\Controllers\Dmg\PaiementDmgController::class, 'generer'])->name('dmg.paiements.generer');
    Route::post('/dmg/paiements/transmettre/{id}', [\App\Http\Controllers\Dmg\PaiementDmgController::class, 'transmettre'])->name('dmg.paiements.transmettre');

    // Phase 7 : Agent Comptable
    Route::get('/agent-comptable/paiements', [\App\Http\Controllers\AgentCompt\PaiementAcController::class, 'index'])->name('ac.paiements.index');
    Route::post('/agent-comptable/paiements/viser/{id}', [\App\Http\Controllers\AgentCompt\PaiementAcController::class, 'viser'])->name('ac.paiements.viser');
    Route::post('/agent-comptable/paiements/ajourner/{id}', [\App\Http\Controllers\AgentCompt\PaiementAcController::class, 'ajourner'])->name('ac.paiements.ajourner');
});

require __DIR__.'/settings.php';
