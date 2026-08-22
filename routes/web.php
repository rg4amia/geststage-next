<?php

use App\Http\Controllers\AgentCompt\PaiementAcController;
use App\Http\Controllers\Cb\PaiementCbController;
use App\Http\Controllers\ChefAgence\HistoriqueGenerationController;
use App\Http\Controllers\ChefAgence\IndexChefAgenceController;
use App\Http\Controllers\ChefAgence\PointageChefAgenceController;
use App\Http\Controllers\Cip\MesStagiairesCipController;
use App\Http\Controllers\Cip\PointageCipController;
use App\Http\Controllers\Company\EntrepriseController;
use App\Http\Controllers\Company\OffreEmploiController;
use App\Http\Controllers\Daicg\StagiaireDaicgController;
use App\Http\Controllers\Desse\StagiaireDesseController;
use App\Http\Controllers\Dmg\OperationDmgController;
use App\Http\Controllers\Dmg\AttentePaiementDmgController;
use App\Http\Controllers\Dmg\DossierPaiementDmgController;
use App\Http\Controllers\Dmg\ExportPaiementDmgController;
use App\Http\Controllers\Dmg\OperationPaiementDmgController;
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
    Route::get('/api/stagiaires/demandeur/{matricule}', [\App\Http\Controllers\Registration\InscriptionController::class, 'demandeur'])->name('inscriptions.demandeur');

    // Phase CIP : Mes Stagiaires et Ajournements
    Route::get('/cip/mes-stagiaires', [MesStagiairesCipController::class, 'index'])->name('cip.mes_stagiaires');
    Route::get('/cip/mes-stagiaires/ajournes-ca', [MesStagiairesCipController::class, 'ajournesChefAgence'])->name('cip.mes_stagiaires.ajournes_ca');
    Route::get('/cip/pointage/ajourne-dmg', [MesStagiairesCipController::class, 'pointageAjourneDmg'])->name('cip.pointages.ajourne_dmg');
    Route::get('/cip/suivi', [MesStagiairesCipController::class, 'suivi'])->name('cip.suivi.index');

    // Nouveaux endpoints pour les boutons d'action (Mes Stagiaires)
    Route::get('/cip/mes-stagiaires/{id}/generer-contrat', [MesStagiairesCipController::class, 'genererContrat'])->name('cip.mes-stagiaires.generer-contrat');
    Route::get('/cip/mes-stagiaires/{id}/generer-contrat/json', [MesStagiairesCipController::class, 'genererContratJson'])->name('cip.mes-stagiaires.generer-contrat-json');
    Route::post('/cip/mes-stagiaires/{id}/transferer-contrat', [MesStagiairesCipController::class, 'transfererContrat'])->name('cip.mes-stagiaires.transferer-contrat');
    Route::get('/cip/mes-stagiaires/{id}/generer-tresor-money', [MesStagiairesCipController::class, 'genererTresorMoney'])->name('cip.mes-stagiaires.generer-tresor-money');
    Route::get('/cip/mes-stagiaires/{id}/generer-tresor-money/json', [MesStagiairesCipController::class, 'genererTresorMoneyJson'])->name('cip.mes-stagiaires.generer-tresor-money-json');
    Route::post('/cip/mes-stagiaires/{id}/upload-tresor-money', [MesStagiairesCipController::class, 'uploadTresorMoney'])->name('cip.mes-stagiaires.upload-tresor-money');
    Route::post('/cip/mes-stagiaires/{id}/transmettre-chef-agence', [MesStagiairesCipController::class, 'transmettreChefAgence'])->name('cip.mes-stagiaires.transmettre-chef-agence');
    Route::delete('/cip/mes-stagiaires/{id}', [MesStagiairesCipController::class, 'destroy'])->name('cip.mes-stagiaires.destroy');

    // Phase 5 : Chef d'Agence (Démarrage & Omis)
    Route::get('/chefagence/validations/mois-omis', [IndexChefAgenceController::class, 'moisOmis'])->name('chefagence.validations.moisOmis');
    Route::get('/chefagence/validations', [IndexChefAgenceController::class, 'listeStagiaireAttenteValidation'])->name('chefagence.validations');
    Route::post('/chefagence/demarrage/{id}/valider', [IndexChefAgenceController::class, 'validerDemarrage'])->name('chefagence.demarrage.valider');
    Route::post('/chefagence/demarrage-omis/{id}/valider', [IndexChefAgenceController::class, 'validerDemarrageOmis'])->name('chefagence.demarrage_omis.valider');
    Route::post('/chefagence/validations/valider-group', [IndexChefAgenceController::class, 'validerGroup'])->name('chefagence.validations.validerGroup');
    Route::post('/chefagence/validations/ajourner-group', [IndexChefAgenceController::class, 'ajournerGroup'])->name('chefagence.validations.ajournerGroup');
    Route::post('/chefagence/validations/generer-add-group', [IndexChefAgenceController::class, 'genererAddGroup'])->name('chefagence.validations.genererAddGroup');
    Route::get('/chefagence/validations/{id}/generer-contrat', [IndexChefAgenceController::class, 'genererContrat'])->name('chefagence.validations.genererContrat');
    Route::post('/chefagence/validations/generer-tresor-money-group', [IndexChefAgenceController::class, 'genererTresorMoneyGroup'])->name('chefagence.validations.genererTresorMoneyGroup');

    // Historique des générations de documents
    Route::get('/chefagence/historique', [HistoriqueGenerationController::class, 'page'])->name('chefagence.historique.page');
    Route::get('/chefagence/historique-generations', [HistoriqueGenerationController::class, 'index'])->name('chefagence.historique.index');
    Route::get('/chefagence/historique-generations/{uuid}', [HistoriqueGenerationController::class, 'show'])->name('chefagence.historique.show');
    Route::get('/chefagence/historique-generations/statistiques/global', [HistoriqueGenerationController::class, 'statistiques'])->name('chefagence.historique.statistiques');
    Route::get('/chefagence/historique-generations/recherche/documents', [HistoriqueGenerationController::class, 'search'])->name('chefagence.historique.search');

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
    Route::get('/chefagence/pointages/mois', [PointageChefAgenceController::class, 'moisDisponibles'])->name('chefagence.pointages.mois');
    Route::post('/chefagence/pointages/valider/{id}', [PointageChefAgenceController::class, 'valider'])->name('chefagence.pointages.valider');
    Route::post('/chefagence/pointages/valider-groupe', [PointageChefAgenceController::class, 'validerGroupe'])->name('chefagence.pointages.validerGroupe');
    Route::post('/chefagence/pointages/ajourner/{id}', [PointageChefAgenceController::class, 'ajourner'])->name('chefagence.pointages.ajourner');
    Route::post('/chefagence/pointages/ajourner-groupe', [PointageChefAgenceController::class, 'ajournerGroupe'])->name('chefagence.pointages.ajournerGroupe');
    Route::post('/chefagence/pointages-adp/{id}/valider', [PointageChefAgenceController::class, 'validerAjournementAdp'])->name('chefagence.pointages.adp.valider');
    Route::post('/chefagence/pointages-adp/{id}/rejeter', [PointageChefAgenceController::class, 'rejeterAjournementAdp'])->name('chefagence.pointages.adp.rejeter');
    Route::post('/chefagence/pointages/generer-attestation', [PointageChefAgenceController::class, 'genererAttestation'])->name('chefagence.pointages.genererAttestation');

    // Phase 6 : DMG (Chaîne Financière)
    Route::get('/dmg/validation', [ValidationDmgController::class, 'index'])->middleware('can:valider_dmg')->name('dmg.validation.index');
    Route::get('/dmg/paiements', [PaiementDmgController::class, 'index'])->middleware('can:voir_paiements_dmg')->name('dmg.paiements.index');
    Route::get('/dmg/paiements/json', [AttentePaiementDmgController::class, 'index'])->middleware('can:voir_paiements_dmg')->name('dmg.paiements.json');
    Route::get('/dmg/paiements/generer-pdf', ExportPaiementDmgController::class)->middleware('can:generer_etat_financier')->name('dmg.paiements.generer_pdf');
    Route::post('/dmg/paiements/ajourner', [AttentePaiementDmgController::class, 'ajourner'])->middleware('can:ajourner_paiement_dmg')->name('dmg.paiements.ajourner');
    Route::post('/dmg/paiements/marquer-dossier-physique', [AttentePaiementDmgController::class, 'marquerDossierPhysique'])->middleware('can:marquer_dossier_physique')->name('dmg.paiements.marquer_dossier_physique');
    Route::post('/dmg/paiements/generer', [DossierPaiementDmgController::class, 'generer'])->middleware('can:generer_dossier_paiement')->name('dmg.paiements.generer');
    Route::post('/dmg/paiements/transmettre/{dossier}', [DossierPaiementDmgController::class, 'transmettre'])->middleware('can:transmettre_cb')->name('dmg.paiements.transmettre');
    Route::post('/dmg/paiements/groupes', [DossierPaiementDmgController::class, 'grouper'])->middleware('can:generer_dossier_paiement')->name('dmg.paiements.groupes.store');
    Route::post('/dmg/paiements/groupes/{groupe}/transmettre', [DossierPaiementDmgController::class, 'transmettreGroupe'])->middleware('can:transmettre_cb')->name('dmg.paiements.groupes.transmettre');
    Route::post('/dmg/paiements/groupes/{groupe}/retirer-dossier', [DossierPaiementDmgController::class, 'retirerDuGroupe'])->middleware('can:retirer_paiement_dossier')->name('dmg.paiements.groupes.retirer_dossier');
    Route::post('/dmg/paiements/groupes/{groupe}/generer-pdfs', [DossierPaiementDmgController::class, 'genererPdfs'])->middleware('can:generer_dossier_paiement')->name('dmg.paiements.groupes.generer_pdfs');
    Route::get('/dmg/paiements/groupes/{groupe}/download-attestation', [DossierPaiementDmgController::class, 'downloadAttestation'])->middleware('can:generer_dossier_paiement')->name('dmg.paiements.groupes.download_attestation');
    Route::get('/dmg/paiements/groupes/{groupe}/download-etat-financier', [DossierPaiementDmgController::class, 'downloadEtatFinancier'])->middleware('can:generer_dossier_paiement')->name('dmg.paiements.groupes.download_etat_financier');
    Route::post('/dmg/paiements/retirer-paiement', [DossierPaiementDmgController::class, 'retirer'])->middleware('can:retirer_paiement_dossier')->name('dmg.paiements.retirer_paiement');
    Route::post('/dmg/paiements/elaborer-op', [OperationPaiementDmgController::class, 'elaborer'])->middleware('can:elaborer_op')->name('dmg.paiements.elaborer_op');
    Route::post('/dmg/paiements/creer-bordereau', [OperationPaiementDmgController::class, 'creerBordereau'])->middleware('can:creer_bordereau')->name('dmg.paiements.creer_bordereau');
    Route::post('/dmg/paiements/transmettre-bordereau/{bordereau}', [OperationPaiementDmgController::class, 'transmettreBordereau'])->middleware('can:transmettre_bordereau_ac')->name('dmg.paiements.transmettre_bordereau');
    Route::get('/dmg/paiements/dossiers-cb', [PaiementDmgController::class, 'dossiersCbByMois'])->middleware('can:voir_paiements_dmg')->name('dmg.paiements.dossiers_cb');
    Route::post('/dmg/paiements/stagiaires', [PaiementDmgController::class, 'stagiairesByDossier'])->middleware('can:voir_paiements_dmg')->name('dmg.paiements.stagiaires');
    Route::get('/dmg/multi-dossier', [\App\Http\Controllers\Dmg\MultiDossierController::class, 'index'])->middleware('can:generer_dossier_paiement')->name('dmg.multi-dossier.index');
    Route::get('/dmg/multi-dossier/dossiers', [\App\Http\Controllers\Dmg\MultiDossierController::class, 'getDossiers'])->middleware('can:generer_dossier_paiement')->name('dmg.multi-dossier.get-dossiers');
    Route::post('/dmg/multi-dossier/stagiaires', [\App\Http\Controllers\Dmg\MultiDossierController::class, 'getStagiaires'])->middleware('can:generer_dossier_paiement')->name('dmg.multi-dossier.get-stagiaires');
    Route::post('/dmg/multi-dossier/validate', [\App\Http\Controllers\Dmg\MultiDossierController::class, 'validateSelection'])->middleware('can:generer_dossier_paiement')->name('dmg.multi-dossier.validate');
    Route::post('/dmg/multi-dossier/ajourner-dossier', [\App\Http\Controllers\Dmg\MultiDossierController::class, 'ajournerDossier'])->middleware('can:ajourner_paiement_dmg')->name('dmg.multi-dossier.ajourner-dossier');
    Route::post('/dmg/multi-dossier/ajourner-stagiaire', [\App\Http\Controllers\Dmg\MultiDossierController::class, 'ajournerStagiaire'])->middleware('can:ajourner_paiement_dmg')->name('dmg.multi-dossier.ajourner-stagiaire');
    Route::post('/dmg/multi-dossier/generer-pdf-paiement', [\App\Http\Controllers\Dmg\MultiDossierController::class, 'generatePdfPaiement'])->middleware('can:generer_etat_financier')->name('dmg.multi-dossier.generate-pdf-paiement');
    Route::post('/dmg/multi-dossier/generer-pdf-attestations', [\App\Http\Controllers\Dmg\MultiDossierController::class, 'generatePdfAttestations'])->middleware('can:generer_etat_financier')->name('dmg.multi-dossier.generate-pdf-attestations');
    Route::get('/dmg/multi-dossier/download-attestation/{groupe}', [\App\Http\Controllers\Dmg\MultiDossierController::class, 'downloadAttestation'])->middleware('can:generer_etat_financier')->name('dmg.multi-dossier.download_attestation');
    Route::get('/dmg/multi-dossier/download-etat-financier/{groupe}', [\App\Http\Controllers\Dmg\MultiDossierController::class, 'downloadEtatFinancier'])->middleware('can:generer_etat_financier')->name('dmg.multi-dossier.download_etat_financier');
    Route::get('/dmg/operations', [OperationDmgController::class, 'index'])->middleware('can:valider_dmg')->name('dmg.operations.index');
    Route::get('/dmg/rejets', [RejetDmgController::class, 'index'])->middleware('can:valider_dmg')->name('dmg.rejets.index');

    // Phase 7 : Agent Comptable
    Route::get('/agent-comptable/paiements', [PaiementAcController::class, 'index'])->name('ac.paiements.index');
    Route::post('/agent-comptable/paiements/viser/{id}', [PaiementAcController::class, 'viser'])->name('ac.paiements.viser');
    Route::post('/agent-comptable/paiements/ajourner/{id}', [PaiementAcController::class, 'ajourner'])->name('ac.paiements.ajourner');
    Route::post('/agent-comptable/paiements/rejeter/{id}', [PaiementAcController::class, 'rejeter'])->name('ac.paiements.rejeter');
    Route::post('/ac/paiements/valider/{id}', [PaiementAcController::class, 'viser'])->name('ac.paiements.valider');
    Route::post('/ac/paiements/ajourner/{id}', [PaiementAcController::class, 'ajourner'])->name('ac.paiements.ajourner.alias');

    // Phase 8 : Chef de Bureau (CB)
    Route::get('/cb/paiements', [PaiementCbController::class, 'index'])->name('cb.paiements.index');
    Route::get('/cb/paiements/dossiers', [PaiementCbController::class, 'dossiersByMois'])->name('cb.paiements.dossiers');
    Route::post('/cb/paiements/stagiaires', [PaiementCbController::class, 'stagiairesByDossier'])->name('cb.paiements.stagiaires');
    Route::get('/cb/paiements/documents', [PaiementCbController::class, 'documentsByStage'])->name('cb.paiements.documents');
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

require __DIR__ . '/settings.php';
