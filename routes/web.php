<?php

use App\Http\Controllers\AgentCompt\PaiementAcController;
use App\Http\Controllers\Cb\PaiementCbController;
use App\Http\Controllers\ChefAgence\IndexChefAgenceController;
use App\Http\Controllers\ChefAgence\PointageChefAgenceController;
use App\Http\Controllers\Cip\MesStagiairesCipController;
use App\Http\Controllers\Cip\PointageCipController;
use App\Http\Controllers\Company\EntrepriseController;
use App\Http\Controllers\Company\OffreEmploiController;
use App\Http\Controllers\Daicg\StagiaireDaicgController;
use App\Http\Controllers\Desse\StagiaireDesseController;
use App\Http\Controllers\Dmg\OperationDmgController;
use App\Http\Controllers\Dmg\PaiementDmgController;
use App\Http\Controllers\Dmg\RejetDmgController;
use App\Http\Controllers\Dmg\ValidationDmgController;
use App\Http\Controllers\Registration\InscriptionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Tables/GridJs/index')->name('dashboard');

    Route::resource('entreprises', EntrepriseController::class);
    Route::resource('offres', OffreEmploiController::class)->parameters([
        'offres' => 'offre_emploi',
    ]);
    Route::resource('inscriptions', InscriptionController::class);

    // Phase CIP : Mes Stagiaires et Ajournements
    Route::get('/cip/mes-stagiaires', [MesStagiairesCipController::class, 'index'])->name('cip.mes_stagiaires');
    Route::get('/cip/pointage/ajourne-dmg', [MesStagiairesCipController::class, 'pointageAjourneDmg'])->name('cip.pointages.ajourne_dmg');
    Route::get('/cip/suivi', [MesStagiairesCipController::class, 'suivi'])->name('cip.suivi.index');

    // Phase 5 : Chef d'Agence (Démarrage & Omis)
    Route::get('/chefagence/validations', [IndexChefAgenceController::class, 'listeStagiaireAttenteValidation'])->name('chefagence.validations');
    Route::post('/chefagence/demarrage/{id}/valider', [IndexChefAgenceController::class, 'validerDemarrage'])->name('chefagence.demarrage.valider');
    Route::post('/chefagence/demarrage-omis/{id}/valider', [IndexChefAgenceController::class, 'validerDemarrageOmis'])->name('chefagence.demarrage_omis.valider');

    // Phase 5 : Pointages CIP
    Route::get('/cip/pointages', [PointageCipController::class, 'stagiaireAttentePointage'])->name('cip.pointages.index');
    Route::get('/cip/pointages/pejedec', [PointageCipController::class, 'stagiaireAttentePointagePejedec'])->name('cip.pointages.pejedec');
    Route::post('/cip/pointages/soumettre/{stageId}', [PointageCipController::class, 'soumettre'])->name('cip.pointages.soumettre');

    // Phase 5 : Pointages Chef d'Agence
    Route::get('/chefagence/pointages', [PointageChefAgenceController::class, 'pointageAttenteValidationByChefAgence'])->name('chefagence.pointages.index');
    Route::post('/chefagence/pointages/valider/{id}', [PointageChefAgenceController::class, 'valider'])->name('chefagence.pointages.valider');
    Route::post('/chefagence/pointages/ajourner/{id}', [PointageChefAgenceController::class, 'ajourner'])->name('chefagence.pointages.ajourner');
    Route::post('/chefagence/pointages-adp/{id}/valider', [PointageChefAgenceController::class, 'validerAjournementAdp'])->name('chefagence.pointages.adp.valider');
    Route::post('/chefagence/pointages-adp/{id}/rejeter', [PointageChefAgenceController::class, 'rejeterAjournementAdp'])->name('chefagence.pointages.adp.rejeter');

    // Phase 6 : DMG (Chaîne Financière)
    Route::get('/dmg/validation', [ValidationDmgController::class, 'index'])->name('dmg.validation.index');
    Route::get('/dmg/paiements', [PaiementDmgController::class, 'index'])->name('dmg.paiements.index');
    Route::post('/dmg/paiements/generer', [PaiementDmgController::class, 'generer'])->name('dmg.paiements.generer');
    Route::post('/dmg/paiements/transmettre/{id}', [PaiementDmgController::class, 'transmettre'])->name('dmg.paiements.transmettre');
    Route::get('/dmg/operations', [OperationDmgController::class, 'index'])->name('dmg.operations.index');
    Route::get('/dmg/rejets', [RejetDmgController::class, 'index'])->name('dmg.rejets.index');

    // Phase 7 : Agent Comptable
    Route::get('/agent-comptable/paiements', [PaiementAcController::class, 'index'])->name('ac.paiements.index');
    Route::post('/agent-comptable/paiements/viser/{id}', [PaiementAcController::class, 'viser'])->name('ac.paiements.viser');
    Route::post('/agent-comptable/paiements/ajourner/{id}', [PaiementAcController::class, 'ajourner'])->name('ac.paiements.ajourner');
    Route::post('/ac/paiements/valider/{id}', [PaiementAcController::class, 'viser'])->name('ac.paiements.valider');
    Route::post('/ac/paiements/ajourner/{id}', [PaiementAcController::class, 'ajourner'])->name('ac.paiements.ajourner.alias');

    // Phase 8 : Chef de Bureau (CB)
    Route::get('/cb/paiements', [PaiementCbController::class, 'index'])->name('cb.paiements.index');
    Route::post('/cb/paiements/valider/{id}', [PaiementCbController::class, 'valider'])->name('cb.paiements.valider');
    Route::post('/cb/paiements/ajourner/{id}', [PaiementCbController::class, 'ajourner'])->name('cb.paiements.ajourner');

    Route::get('/desse/stagiaires', [StagiaireDesseController::class, 'index'])->name('desse.stagiaires.index');
    Route::get('/daicg/stagiaires', [StagiaireDaicgController::class, 'index'])->name('daicg.stagiaires.index');
});

require __DIR__.'/settings.php';
