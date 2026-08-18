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
use App\Http\Controllers\Pejedec\AafController;
use App\Http\Controllers\Registration\InscriptionController;
use App\Http\Controllers\Reporting\TableauDeBordController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [TableauDeBordController::class, 'index'])->middleware('can:voir_reporting')->name('dashboard');
    Route::get('/reporting', [TableauDeBordController::class, 'index'])->middleware('can:voir_reporting')->name('reporting.index');
    Route::get('/reporting/export/kpi.csv', [TableauDeBordController::class, 'exportCsv'])->middleware('can:voir_reporting')->name('reporting.export.kpi');

    Route::resource('entreprises', EntrepriseController::class);
    Route::resource('offres', OffreEmploiController::class)->parameters([
        'offres' => 'offre_emploi',
    ]);
    Route::resource('inscriptions', InscriptionController::class);

    // Phase CIP : Mes Stagiaires et Ajournements
    Route::get('/cip/mes-stagiaires', [MesStagiairesCipController::class, 'index'])->name('cip.mes_stagiaires');
    Route::get('/cip/pointage/ajourne-dmg', [MesStagiairesCipController::class, 'pointageAjourneDmg'])->name('cip.pointages.ajourne_dmg');
    Route::get('/cip/suivi', [MesStagiairesCipController::class, 'suivi'])->name('cip.suivi.index');

    // Nouveaux endpoints pour les boutons d'action (Mes Stagiaires)
    Route::get('/cip/mes-stagiaires/{id}/generer-contrat', [MesStagiairesCipController::class, 'genererContrat'])->name('cip.mes-stagiaires.generer-contrat');
    Route::post('/cip/mes-stagiaires/{id}/transferer-contrat', [MesStagiairesCipController::class, 'transfererContrat'])->name('cip.mes-stagiaires.transferer-contrat');
    Route::get('/cip/mes-stagiaires/{id}/generer-tresor-money', [MesStagiairesCipController::class, 'genererTresorMoney'])->name('cip.mes-stagiaires.generer-tresor-money');
    Route::post('/cip/mes-stagiaires/{id}/upload-tresor-money', [MesStagiairesCipController::class, 'uploadTresorMoney'])->name('cip.mes-stagiaires.upload-tresor-money');
    Route::delete('/cip/mes-stagiaires/{id}', [MesStagiairesCipController::class, 'destroy'])->name('cip.mes-stagiaires.destroy');

    // Phase 5 : Chef d'Agence (Démarrage & Omis)
    Route::get('/chefagence/validations', [IndexChefAgenceController::class, 'listeStagiaireAttenteValidation'])->name('chefagence.validations');
    Route::post('/chefagence/demarrage/{id}/valider', [IndexChefAgenceController::class, 'validerDemarrage'])->name('chefagence.demarrage.valider');
    Route::post('/chefagence/demarrage-omis/{id}/valider', [IndexChefAgenceController::class, 'validerDemarrageOmis'])->name('chefagence.demarrage_omis.valider');
    Route::post('/chefagence/validations/valider-group', [IndexChefAgenceController::class, 'validerGroup'])->name('chefagence.validations.validerGroup');
    Route::post('/chefagence/validations/ajourner-group', [IndexChefAgenceController::class, 'ajournerGroup'])->name('chefagence.validations.ajournerGroup');
    Route::post('/chefagence/validations/generer-add-group', [IndexChefAgenceController::class, 'genererAddGroup'])->name('chefagence.validations.genererAddGroup');

    // Phase 5 : Pointages CIP
    Route::get('/cip/pointages', [PointageCipController::class, 'stagiaireAttentePointage'])->name('cip.pointages.index');
    Route::post('/cip/pointages/soumettre-batch', [PointageCipController::class, 'soumettreBatch'])->name('cip.pointages.soumettre_batch');
    Route::get('/cip/pointages/edit-stagiaire/{id}', [PointageCipController::class, 'editStagiaire'])->name('cip.pointages.edit_stagiaire');
    Route::put('/cip/pointages/update-stagiaire/{id}', [PointageCipController::class, 'updateStagiaire'])->name('cip.pointages.update_stagiaire');

    Route::post('/cip/pointages/corriger-ajournement-dmg/{id}', [PointageCipController::class, 'corrigerAjournementDmg'])->name('cip.pointages.corriger_ajournement_dmg');
    Route::post('/cip/pointages/soumettre-individuel', [PointageCipController::class, 'soumettreIndividuel'])->name('cip.pointages.soumettre_individuel');
    Route::delete('/cip/pointages/{id}/annuler', [PointageCipController::class, 'annulerPointage'])->name('cip.pointages.annuler');

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
    Route::post('/dmg/paiements/elaborer-op', [PaiementDmgController::class, 'elaborerOp'])->name('dmg.paiements.elaborer_op');
    Route::post('/dmg/paiements/creer-bordereau', [PaiementDmgController::class, 'creerBordereau'])->name('dmg.paiements.creer_bordereau');
    Route::post('/dmg/paiements/transmettre-bordereau/{id}', [PaiementDmgController::class, 'transmettreBordereau'])->name('dmg.paiements.transmettre_bordereau');
    Route::get('/dmg/operations', [OperationDmgController::class, 'index'])->name('dmg.operations.index');
    Route::get('/dmg/rejets', [RejetDmgController::class, 'index'])->name('dmg.rejets.index');

    // Phase 7 : Agent Comptable
    Route::get('/agent-comptable/paiements', [PaiementAcController::class, 'index'])->name('ac.paiements.index');
    Route::post('/agent-comptable/paiements/viser/{id}', [PaiementAcController::class, 'viser'])->name('ac.paiements.viser');
    Route::post('/agent-comptable/paiements/ajourner/{id}', [PaiementAcController::class, 'ajourner'])->name('ac.paiements.ajourner');
    Route::post('/agent-comptable/paiements/rejeter/{id}', [PaiementAcController::class, 'rejeter'])->name('ac.paiements.rejeter');
    Route::post('/ac/paiements/valider/{id}', [PaiementAcController::class, 'viser'])->name('ac.paiements.valider');
    Route::post('/ac/paiements/ajourner/{id}', [PaiementAcController::class, 'ajourner'])->name('ac.paiements.ajourner.alias');

    // Phase 8 : Chef de Bureau (CB)
    Route::get('/cb/paiements', [PaiementCbController::class, 'index'])->name('cb.paiements.index');
    Route::post('/cb/paiements/valider/{id}', [PaiementCbController::class, 'valider'])->name('cb.paiements.valider');
    Route::post('/cb/paiements/ajourner/{id}', [PaiementCbController::class, 'ajourner'])->name('cb.paiements.ajourner');

    Route::get('/desse/stagiaires', [StagiaireDesseController::class, 'index'])->name('desse.stagiaires.index');
    Route::post('/desse/stagiaires/valider/{id}', [StagiaireDesseController::class, 'valider'])->name('desse.stagiaires.valider');
    Route::post('/desse/stagiaires/ajourner/{id}', [StagiaireDesseController::class, 'ajourner'])->name('desse.stagiaires.ajourner');
    Route::post('/desse/stagiaires/doublons/{id}/traiter', [StagiaireDesseController::class, 'traiterDoublon'])->name('desse.stagiaires.doublons.traiter');
    Route::get('/daicg/stagiaires', [StagiaireDaicgController::class, 'index'])->name('daicg.stagiaires.index');

    // Phase 9 : PEJEDEC / AAF
    Route::get('/pejedec/af', [AafController::class, 'index'])->name('pejedec.af.index');
    Route::get('/pejedec/af/attente-validation', [AafController::class, 'attenteValidation'])->name('pejedec.af.attente_validation');
    Route::post('/pejedec/af/pointages/{id}/valider', [AafController::class, 'validerPointage'])->name('pejedec.af.pointages.valider');
    Route::post('/pejedec/af/pointages/{id}/valider-correction', [AafController::class, 'validerCorrection'])->name('pejedec.af.pointages.valider_correction');
    Route::get('/pejedec/af/paiements-ajournes', [AafController::class, 'paiementsAjournes'])->name('pejedec.af.paiements_ajournes');
    Route::get('/pejedec/af/corrections-a-valider', [AafController::class, 'correctionsAValider'])->name('pejedec.af.corrections_a_valider');
    Route::get('/pejedec/af/attente-paiement', [AafController::class, 'attentePaiement'])->name('pejedec.af.attente_paiement');
    Route::post('/pejedec/af/droits-paiement/{id}/generer', [AafController::class, 'genererPaiement'])->name('pejedec.af.droits.generer_paiement');
});

require __DIR__.'/settings.php';
