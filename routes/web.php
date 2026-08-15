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

    // Phase 5 : Chef d'Agence (Démarrage)
    Route::get('/chefagence/validations', [\App\Http\Controllers\ChefAgence\IndexChefAgenceController::class, 'listeStagiaireAttenteValidation'])->name('chefagence.validations');
    Route::post('/validations/demarrage/{id}', [\App\Http\Controllers\Validation\ValidationController::class, 'validerDemarrage'])->name('validations.demarrage');
    Route::post('/validations/ajourner/{id}', [\App\Http\Controllers\Validation\ValidationController::class, 'ajourner'])->name('validations.ajourner');
    
    // Phase 5 : Pointages CIP
    Route::get('/cip/pointages', [\App\Http\Controllers\Cip\PointageCipController::class, 'stagiaireAttentePointage'])->name('cip.pointages.index');
    Route::post('/cip/pointages/soumettre/{stageId}', [\App\Http\Controllers\Cip\PointageCipController::class, 'soumettre'])->name('cip.pointages.soumettre');
    
    // Phase 5 : Pointages Chef d'Agence
    Route::get('/chefagence/pointages', [\App\Http\Controllers\ChefAgence\PointageChefAgenceController::class, 'pointageAttenteValidationByChefAgence'])->name('chefagence.pointages.index');
    Route::post('/chefagence/pointages/valider/{id}', [\App\Http\Controllers\ChefAgence\PointageChefAgenceController::class, 'valider'])->name('chefagence.pointages.valider');
    Route::post('/chefagence/pointages/ajourner/{id}', [\App\Http\Controllers\ChefAgence\PointageChefAgenceController::class, 'ajourner'])->name('chefagence.pointages.ajourner');
});

require __DIR__.'/settings.php';
