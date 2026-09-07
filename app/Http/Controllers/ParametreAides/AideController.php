<?php

namespace App\Http\Controllers\ParametreAides;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Centre d'aide utilisateur.
 *
 * Les contenus ci-dessous sont derives des routes, policies, seeders, services
 * metier et pages Inertia actuellement presents dans l'application. Ils restent
 * volontairement separes du code d'action : la page d'aide consomme une base
 * structuree, mais ne modifie aucun workflow.
 */
class AideController extends Controller
{
    public function index(): Response
    {
        $rubriques = $this->rubriques();
        $roles = $this->roles();
        $workflows = $this->workflows();
        $erreurs = $this->erreurs();
        $glossaire = $this->glossaire();
        $baseConnaissances = $this->baseConnaissances($rubriques);
        $aideContextuelle = $this->aideContextuelle($rubriques);
        $intents = $this->intents($rubriques);

        return Inertia::render('ParametreAides/Aide/Index', [
            'synthese' => [
                'titre' => 'Centre d aide Geststage',
                'description' => 'Aide utilisateur structuree par module, role, workflow, page, erreur et source technique verifiee dans le code.',
                'modules' => count($rubriques),
                'roles' => count($roles),
                'workflows' => count($workflows),
                'articles' => array_sum(array_map(fn (array $rubrique): int => count($rubrique['details']), $rubriques)),
                'regles' => array_sum(array_map(fn (array $rubrique): int => count($rubrique['contraintes']), $rubriques)),
                'sources' => count($this->sourcesAnalysees()),
            ],
            'architecture' => $this->architecture(),
            'rubriques' => $rubriques,
            'roles' => $roles,
            'workflows' => $workflows,
            'matriceFonctionnelle' => $this->matriceFonctionnelle(),
            'matricePermissions' => $this->matricePermissions(),
            'matriceWorkflows' => $this->matriceWorkflows(),
            'faq' => $this->faq(),
            'erreurs' => $erreurs,
            'reglesMetier' => $this->reglesMetier(),
            'statuts' => $this->statuts(),
            'glossaire' => $glossaire,
            'aideContextuelle' => $aideContextuelle,
            'baseConnaissances' => $baseConnaissances,
            'intents' => $intents,
            'indexRecherche' => $this->indexRecherche($rubriques),
            'audit' => [
                'incoherences' => $this->incoherences(),
                'nonDocumentables' => $this->nonDocumentables(),
                'tracabilite' => $this->tracabilite(),
                'sourcesAnalysees' => $this->sourcesAnalysees(),
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rubriques(): array
    {
        return [
            [
                'id' => 'cip',
                'titre' => 'Espace CIP',
                'icone' => 'ri-team-line',
                'resume' => 'Inscrire les demandeurs, suivre les stagiaires, generer les pieces, saisir les pointages et corriger les retours.',
                'utilisateurs' => ['cip', 'administrateur'],
                'routes' => ['/cip/mes-stagiaires', '/cip/pointages', '/cip/pointage/ajourne-dmg', '/cip/renouvellements', '/cip/suivi', '/cip/situation-stagiaire'],
                'ecrans' => ['Mes Stagiaires', 'Presence - Pointages', 'Pointage Ajourne (DMG)', 'Renouvellements', 'Suivi et anomalies', 'Situation des stagiaires'],
                'details' => [
                    ['titre' => 'Dossier stagiaire', 'description' => 'Retrouver un beneficiaire, suivre son stage, generer le contrat et transmettre le dossier au chef d agence.'],
                    ['titre' => 'Documents de paiement', 'description' => 'Generer les pieces Tresor Money, deposer les justificatifs et consulter le suivi des pointages.'],
                    ['titre' => 'Pointage mensuel', 'description' => 'Saisir un pointage uniquement pour les stages actifs et deja valides par le chef d agence.'],
                    ['titre' => 'Ajournements et corrections', 'description' => 'Reprendre les dossiers ajournes par le chef d agence ou par la DMG, puis les retransmettre.'],
                    ['titre' => 'Renouvellements', 'description' => 'Proposer un avenant lorsque le stage arrive a terme ou dans la fenetre d anticipation.'],
                ],
                'actions' => [
                    'Generer un contrat',
                    'Transferer un contrat',
                    'Generer Tresor Money',
                    'Transmettre au chef d agence',
                    'Soumettre un pointage individuel ou en lot',
                    'Corriger un ajournement DMG',
                    'Proposer ou renvoyer un renouvellement',
                    'Reactiver une situation de stage',
                ],
                'workflow' => [
                    'Le CIP prepare le dossier puis le transmet au chef d agence.',
                    'Apres validation du demarrage, le stage entre dans le cycle mensuel.',
                    'Chaque pointage soumis passe chez le chef d agence.',
                    'Une correction DMG repasse par le chef d agence avant de revenir dans le flux normal.',
                ],
                'statuts' => ['SOUMIS', 'AJOURNE_CA', 'AJOURNE_DMG', 'CORRIGE_CIP', 'REJETE_DEFINITIF', 'ATTENTE_CA', 'AJOURNE', 'VALIDE'],
                'contraintes' => [
                    'Le pointage n est propose que pour une periode et un stage eligibles.',
                    'Un stage encore dans une corbeille non validee par le chef d agence ne peut pas etre pointe.',
                    'Un renouvellement ne peut pas etre cree si un avenant est deja en attente ou ajourne.',
                    'Les corrections et transmissions doivent conserver un motif lorsque l action l exige.',
                ],
                'erreurs' => [
                    'Aucune ligne n apparait si le mois, l agence, l entreprise, la source de financement ou le type de stage filtrent tout.',
                    'Le bouton de pointage n apparait pas si le dossier n est pas encore valide par le chef d agence.',
                    'La correction DMG echoue si le paiement ou le pointage ne correspond plus au statut attendu.',
                ],
                'faq' => [
                    ['question' => 'Pourquoi je ne vois pas un stagiaire dans le pointage ?', 'reponse' => 'Le stage doit etre en cours, couvrir la periode choisie et ne plus etre dans une corbeille de validation initiale du chef d agence.'],
                    ['question' => 'Pourquoi un renouvellement est refuse ?', 'reponse' => 'Le code bloque les contrats sans contrat actif ou ceux qui ont deja un avenant en attente ou ajourne.'],
                ],
                'liens' => [
                    ['libelle' => 'Mes stagiaires', 'href' => '/cip/mes-stagiaires'],
                    ['libelle' => 'Presence - Pointages', 'href' => '/cip/pointages'],
                    ['libelle' => 'Pointage Ajourne (DMG)', 'href' => '/cip/pointage/ajourne-dmg'],
                    ['libelle' => 'Renouvellements', 'href' => '/cip/renouvellements'],
                ],
                'sources' => [
                    'routes/web.php',
                    'app/Http/Controllers/Cip/MesStagiairesCipController.php',
                    'app/Http/Controllers/Cip/PointageCipController.php',
                    'app/Domain/Attendance/Services/PointageService.php',
                    'app/Domain/Contract/Services/RenouvellementService.php',
                    'resources/js/velzone/pages/Cip',
                ],
                'confiance' => 'elevee',
            ],
            [
                'id' => 'chef-agence',
                'titre' => 'Espace Chef d agence',
                'icone' => 'ri-checkbox-circle-line',
                'resume' => 'Valider les demarrages, ajourner les dossiers, traiter les pointages et produire les documents de controle.',
                'utilisateurs' => ['chef_agence', 'administrateur'],
                'routes' => ['/chefagence/validations', '/chefagence/validations/mois-omis', '/chefagence/pointages', '/chefagence/historique'],
                'ecrans' => ['Attente de validation', 'Validation des Pointages', 'Historique des generations'],
                'details' => [
                    ['titre' => 'Validation du demarrage', 'description' => 'Controler les dossiers transmis par les CIP, valider individuellement ou en groupe, ou ajourner avec motif.'],
                    ['titre' => 'Demarrages omis', 'description' => 'Traiter les dossiers dont le mois de demarrage ne correspond pas au mois courant.'],
                    ['titre' => 'Validation des pointages', 'description' => 'Traiter les pointages SOUMIS et les corrections CORRIGE_CIP.'],
                    ['titre' => 'Attestations', 'description' => 'Generer les attestations apres controle des pointages et suivre l historique des generations.'],
                ],
                'actions' => [
                    'Valider un demarrage',
                    'Valider en groupe',
                    'Ajourner en groupe avec motif',
                    'Valider ou ajourner un pointage',
                    'Valider ou rejeter une correction ADP',
                    'Generer une attestation',
                ],
                'workflow' => [
                    'Un dossier transmis par le CIP arrive en attente chef d agence.',
                    'Une validation de demarrage cree un droit de paiement de demarrage et envoie le paiement a la DMG.',
                    'Un demarrage omis valide place le stage en cycle mensuel.',
                    'Un ajournement renvoie le dossier vers le CIP.',
                ],
                'statuts' => ['ca_attente_validation_demarrage', 'ca_attente_validation_omis', 'SOUMIS', 'VALIDE', 'AJOURNE_CA', 'CORRIGE_CIP'],
                'contraintes' => [
                    'La liste des pointages est restreinte au perimetre d agence du chef d agence connecte.',
                    'Les pointages traitables doivent etre SOUMIS ou CORRIGE_CIP selon l onglet.',
                    'Un pointage doit appartenir a une instance non terminee et a un stage ayant un contrat.',
                ],
                'erreurs' => [
                    'Une action peut disparaitre si le pointage a deja ete traite par un autre utilisateur.',
                    'Un motif est attendu pour expliquer un ajournement ou un rejet de correction.',
                ],
                'faq' => [
                    ['question' => 'Que se passe-t-il apres la validation du demarrage ?', 'reponse' => 'Un droit de paiement DEMARRAGE et un paiement A_TRAITER sont crees, puis la DMG prend le relais.'],
                    ['question' => 'Pourquoi je ne vois pas tous les pointages ?', 'reponse' => 'Le service applique le perimetre agence du compte connecte et les filtres de periode.'],
                ],
                'liens' => [
                    ['libelle' => 'Attente de validation', 'href' => '/chefagence/validations'],
                    ['libelle' => 'Validation des pointages', 'href' => '/chefagence/pointages'],
                    ['libelle' => 'Historique', 'href' => '/chefagence/historique'],
                ],
                'sources' => [
                    'routes/web.php',
                    'app/Domain/Validation/Services/ValidationChefAgenceService.php',
                    'app/Domain/Attendance/Services/PointageChefAgenceService.php',
                    'resources/js/velzone/pages/ChefAgence',
                ],
                'confiance' => 'elevee',
            ],
            [
                'id' => 'dmg',
                'titre' => 'DMG - Paiements et OP',
                'icone' => 'ri-money-dollar-circle-line',
                'resume' => 'Controler les paiements, generer les dossiers, elaborer les ordres de paiement, creer et transmettre les bordereaux.',
                'utilisateurs' => ['dmg', 'administrateur'],
                'routes' => ['/dmg/paiements', '/dmg/paiements/ajournes', '/dmg/paiements/ops', '/dmg/multi-dossier', '/dmg/operations', '/dmg/rejets'],
                'ecrans' => ['Paiements / Elaboration OP', 'Ajournes', 'Ordres de paiement', 'Bordereaux', 'Multi-dossier'],
                'details' => [
                    ['titre' => 'Files de paiement', 'description' => 'Distinguer le demarrage et la presence selon les dates du stage et la nature du droit de paiement.'],
                    ['titre' => 'Controle DMG', 'description' => 'Marquer le dossier physique, ajourner ou generer des dossiers de paiement pour les paiements A_TRAITER.'],
                    ['titre' => 'Dossiers et multi-dossiers', 'description' => 'Regrouper des paiements par periode, nature et source de financement.'],
                    ['titre' => 'Ordres de paiement', 'description' => 'Elaborer une OP uniquement a partir de dossiers VALIDE_CB partageant le meme financement.'],
                    ['titre' => 'Bordereaux', 'description' => 'Creer un bordereau avec des OP BROUILLON puis le transmettre a l agent comptable.'],
                ],
                'actions' => [
                    'Generer un dossier de paiement',
                    'Ajourner un paiement',
                    'Marquer le dossier physique',
                    'Grouper des dossiers',
                    'Transmettre un dossier au CB',
                    'Elaborer une OP',
                    'Creer un bordereau',
                    'Transmettre un bordereau a l AC',
                    'Exporter PDF, Excel ou archive asynchrone',
                ],
                'workflow' => [
                    'A_TRAITER -> EN_DOSSIER apres generation du dossier.',
                    'BROUILLON -> TRANSMIS_CB lors de l envoi au CB.',
                    'VALIDE_CB -> EN_OP lors de l elaboration de l OP.',
                    'BROUILLON -> EN_BORDEREAU lors de l ajout a un bordereau.',
                    'TRANSMIS_AC transfere le traitement a l agent comptable.',
                ],
                'statuts' => ['A_TRAITER', 'AJOURNE_DMG', 'EN_DOSSIER', 'BROUILLON', 'TRANSMIS_CB', 'VALIDE_CB', 'EN_OP', 'EN_BORDEREAU', 'TRANSMIS_AC'],
                'contraintes' => [
                    'Les paiements PEJEDEC et les origines sans droit de paiement sont exclus des files DMG.',
                    'Les doublons DESSE non traites sont exclus des files DMG.',
                    'Un dossier de paiement doit etre BROUILLON pour etre transmis ou groupe.',
                    'Une OP doit etre BROUILLON pour etre ajoutee a un bordereau.',
                    'Un bordereau doit contenir des OP disponibles partageant la meme source de financement.',
                ],
                'erreurs' => [
                    'Selection invalide si un paiement est absent, annule, deja traite ou sorti de la corbeille DMG.',
                    'Un multi-dossier est refuse si les dossiers n ont pas la meme nature ou source de financement.',
                    'Un OP vide devient ANNULE apres retrait de ses dossiers.',
                ],
                'faq' => [
                    ['question' => 'Pourquoi un paiement n est pas dans la file DMG ?', 'reponse' => 'Il peut etre PEJEDEC, issu d une origine sans droit de paiement, bloque par un doublon DESSE ou deja traite.'],
                    ['question' => 'Pourquoi je ne peux pas creer un bordereau ?', 'reponse' => 'Les OP selectionnees doivent etre en BROUILLON, disponibles et partager le meme financement.'],
                ],
                'liens' => [
                    ['libelle' => 'Paiements DMG', 'href' => '/dmg/paiements'],
                    ['libelle' => 'Multi-dossier', 'href' => '/dmg/multi-dossier'],
                    ['libelle' => 'Operations DMG', 'href' => '/dmg/operations'],
                ],
                'sources' => [
                    'routes/web.php',
                    'app/Domain/Payment/Services/DmgService.php',
                    'app/Http/Controllers/Dmg',
                    'resources/js/velzone/pages/Dmg/Paiements/Index.tsx',
                ],
                'confiance' => 'elevee',
            ],
            [
                'id' => 'cb',
                'titre' => 'CB - Controle des paiements',
                'icone' => 'ri-shield-check-line',
                'resume' => 'Verifier les dossiers transmis par la DMG, les valider ou les ajourner avant elaboration des OP.',
                'utilisateurs' => ['cb', 'administrateur'],
                'routes' => ['/cb/paiements'],
                'ecrans' => ['Controle des paiements'],
                'details' => [
                    ['titre' => 'Dossiers transmis', 'description' => 'Afficher les dossiers TRANSMIS_CB par mois et consulter les stagiaires associes.'],
                    ['titre' => 'Validation CB', 'description' => 'Passer le dossier a VALIDE_CB si les paiements actifs EN_DOSSIER sont conformes.'],
                    ['titre' => 'Ajournement CB', 'description' => 'Renvoyer le dossier avec motif, conserve sur les lignes de dossier.'],
                ],
                'actions' => ['Valider un dossier', 'Ajourner un dossier', 'Consulter les stagiaires', 'Consulter les documents'],
                'workflow' => [
                    'TRANSMIS_CB -> VALIDE_CB lorsque le CB valide.',
                    'TRANSMIS_CB -> AJOURNE_CB lorsque le CB ajourne.',
                    'Les paiements restent EN_DOSSIER pendant la decision CB.',
                ],
                'statuts' => ['TRANSMIS_CB', 'VALIDE_CB', 'AJOURNE_CB', 'EN_DOSSIER'],
                'contraintes' => [
                    'Le dossier doit etre TRANSMIS_CB et ne pas etre rattache a une OP.',
                    'Le dossier doit contenir au moins un paiement actif EN_DOSSIER.',
                ],
                'erreurs' => [
                    'Ce dossier n est plus en attente de traitement CB.',
                    'Ce dossier ne contient aucun paiement actif a traiter ou ajourner.',
                ],
                'faq' => [
                    ['question' => 'Pourquoi le bouton Valider ne fonctionne pas ?', 'reponse' => 'Le dossier doit encore etre TRANSMIS_CB, sans OP rattachee, avec des paiements actifs.'],
                ],
                'liens' => [
                    ['libelle' => 'Controle des paiements', 'href' => '/cb/paiements'],
                ],
                'sources' => [
                    'routes/web.php',
                    'app/Domain/Payment/Services/CbPaiementService.php',
                    'resources/js/velzone/pages/Cb/Paiements/Index.tsx',
                ],
                'confiance' => 'elevee',
            ],
            [
                'id' => 'agent-comptable',
                'titre' => 'Agent comptable',
                'icone' => 'ri-file-shield-2-line',
                'resume' => 'Viser, differer, rejeter et confirmer la situation de paiement des OP et bordereaux transmis.',
                'utilisateurs' => ['agent_comptable', 'administrateur'],
                'routes' => ['/agent-comptable/paiements', '/agent-comptable/status-validation', '/agent-comptable/operation-rejete'],
                'ecrans' => ['Visas des Bordereaux', 'Statut validation', 'Operations rejetees'],
                'details' => [
                    ['titre' => 'Visa du bordereau', 'description' => 'Traiter un bordereau TRANSMIS_AC et faire passer ses OP et dossiers au visa AC.'],
                    ['titre' => 'Traitement par OP', 'description' => 'Valider, differer, differer partiellement, rejeter ou retirer une OP en attente.'],
                    ['titre' => 'Situation effective', 'description' => 'Apres visa, confirmer les paiements PAYE ou NON_PAYE avec motif.'],
                ],
                'actions' => ['Viser un bordereau', 'Valider une OP', 'Differer une OP', 'Differer des stagiaires', 'Rejeter une OP', 'Retirer une OP', 'Confirmer paye ou non paye'],
                'workflow' => [
                    'TRANSMIS_AC -> VISE_AC lorsque tout est vise.',
                    'EN_BORDEREAU -> VISE_AC, DIFFERE_AC, REJETE_AC ou BROUILLON selon la decision sur l OP.',
                    'EN_OP -> VALIDE_AC apres visa, puis PAYE ou NON_PAYE apres confirmation.',
                    'Un bordereau se ferme seulement quand aucune OP ne reste EN_BORDEREAU.',
                ],
                'statuts' => ['TRANSMIS_AC', 'EN_BORDEREAU', 'VISE_AC', 'DIFFERE_AC', 'REJETE_AC', 'REJETE_AC_DEFINITIF', 'VALIDE_AC', 'PAYE', 'NON_PAYE'],
                'contraintes' => [
                    'Les decisions AC portent sur des paiements EN_OP.',
                    'Un bordereau doit etre TRANSMIS_AC pour etre traite.',
                    'Une OP doit etre EN_BORDEREAU dans un bordereau TRANSMIS_AC pour recevoir une decision.',
                    'La confirmation de paiement est possible uniquement apres visa de l OP.',
                ],
                'erreurs' => [
                    'Cette OP ne depend plus d un bordereau en cours de traitement AC.',
                    'Cette OP ne contient plus aucun stagiaire en attente du visa de l Agent Comptable.',
                    'Aucun des paiements selectionnes n est encore a confirmer.',
                ],
                'faq' => [
                    ['question' => 'Pourquoi le bordereau reste ouvert ?', 'reponse' => 'Il reste au moins une OP au statut EN_BORDEREAU. Le bordereau n est finalise qu apres traitement de toutes ses OP.'],
                    ['question' => 'Quand peut-on marquer un paiement PAYE ?', 'reponse' => 'Apres visa de l OP, lorsque le paiement est VALIDE_AC.'],
                ],
                'liens' => [
                    ['libelle' => 'Visas des Bordereaux', 'href' => '/agent-comptable/paiements'],
                    ['libelle' => 'Statut validation', 'href' => '/agent-comptable/status-validation'],
                ],
                'sources' => [
                    'routes/web.php',
                    'app/Domain/Payment/Services/AgentComptableService.php',
                    'resources/js/velzone/pages/AgentComptable/Paiements/Index.tsx',
                ],
                'confiance' => 'elevee',
            ],
            [
                'id' => 'supervision',
                'titre' => 'Supervision DESSE, regionale et DAICG',
                'icone' => 'ri-shield-check-line',
                'resume' => 'Traiter les doublons, viser les dossiers regionaux et consulter les vues globales sans confondre les pouvoirs de decision.',
                'utilisateurs' => ['desse', 'daicg', 'cip', 'chef_agence', 'administrateur'],
                'routes' => ['/desse/stagiaires', '/agence-regionale/visas', '/daicg/stagiaires'],
                'ecrans' => ['Validation et doublons', 'Supervision regionale', 'Vue globale stagiaires'],
                'details' => [
                    ['titre' => 'Doublons DESSE', 'description' => 'Identifier les doublons et trancher le retour agence ou la validation du processus.'],
                    ['titre' => 'Visa regional', 'description' => 'Consulter les corbeilles, les pieces, les statistiques et viser ou rejeter selon habilitation.'],
                    ['titre' => 'DAICG', 'description' => 'Consulter les stagiaires et l avancement global, sans droit de visa.'],
                ],
                'actions' => ['Valider DESSE', 'Ajourner DESSE', 'Traiter un doublon', 'Viser un dossier regional', 'Rejeter un visa regional', 'Exporter les visas', 'Consulter les pieces'],
                'workflow' => [
                    'Un doublon avere renvoie vers l agence.',
                    'Un doublon non avere libere le dossier vers le suivi valide DESSE.',
                    'Le retour agence valide par la DESSE repart vers la DMG, demarrage ou presence selon le cycle.',
                    'Le visa regional inscrit la decision sur le dossier du perimetre regional.',
                ],
                'statuts' => ['desse_doublons_a_traiter', 'desse_retour_agence', 'daicg_valides_desse', 'dmg_attente_paiement_demarrage', 'dmg_attente_paiement_presence'],
                'contraintes' => [
                    'La DAICG dispose d une consultation et d exports, pas du pouvoir de viser.',
                    'Le role DESSE peut voir et viser la supervision regionale.',
                    'La DMG exclut les doublons DESSE non traites.',
                ],
                'erreurs' => [
                    'Une action de visa peut etre absente si le role a seulement la permission de consultation.',
                    'Un dossier bloque par doublon ne rejoint pas la file DMG avant decision.',
                ],
                'faq' => [
                    ['question' => 'Pourquoi un dossier valide par le CA n arrive pas a la DMG ?', 'reponse' => 'Un doublon DESSE non traite peut bloquer le passage dans les files de paiement.'],
                    ['question' => 'Pourquoi la DAICG ne peut pas viser ?', 'reponse' => 'Le role DAICG a voir_visas_ar mais pas viser_visas_ar.'],
                ],
                'liens' => [
                    ['libelle' => 'Validation et doublons', 'href' => '/desse/stagiaires'],
                    ['libelle' => 'Supervision regionale', 'href' => '/agence-regionale/visas'],
                    ['libelle' => 'Vue globale stagiaires', 'href' => '/daicg/stagiaires'],
                ],
                'sources' => [
                    'routes/web.php',
                    'database/seeders/RolePermissionSeeder.php',
                    'app/Domain/Workflow/Services/WorkflowTransitionService.php',
                    'app/Domain/Payment/Services/DmgService.php',
                    'resources/js/velzone/pages/AgenceRegionale/Visas/Index.tsx',
                ],
                'confiance' => 'elevee',
            ],
            [
                'id' => 'pejedec-aaf',
                'titre' => 'PEJEDEC / AAF',
                'icone' => 'ri-stack-line',
                'resume' => 'Suivre le circuit specifique des dossiers PEJEDEC et AAF, separe de la file de paiement DMG.',
                'utilisateurs' => ['pejedec', 'aaf', 'administrateur'],
                'routes' => ['/pejedec/af', '/pejedec/af/attente-validation', '/pejedec/af/paiements-ajournes', '/pejedec/af/corrections-a-valider', '/pejedec/af/attente-paiement'],
                'ecrans' => ['Tableau PEJEDEC / AAF', 'Validation PEJEDEC', 'Paiements ajournes', 'Corrections a valider', 'Attente paiement'],
                'details' => [
                    ['titre' => 'Validation PEJEDEC', 'description' => 'Traiter les pointages ou corrections en attente du circuit PEJEDEC.'],
                    ['titre' => 'AAF', 'description' => 'Generer les paiements pour les droits A_TRAITER de ce circuit.'],
                    ['titre' => 'Separation DMG', 'description' => 'Les financements PEJEDEC sont explicitement exclus de la file DMG.'],
                ],
                'actions' => ['Valider un pointage', 'Valider une correction', 'Generer un paiement', 'Consulter les paiements ajournes'],
                'workflow' => [
                    'Le circuit PEJEDEC / AAF traite ses droits hors DMG.',
                    'Les droits A_TRAITER peuvent etre envoyes vers le paiement AAF.',
                ],
                'statuts' => ['A_TRAITER'],
                'contraintes' => [
                    'Le financement PEJEDEC est exclu du traitement DMG.',
                    'Les roles PEJEDEC et AAF ont des permissions distinctes.',
                ],
                'erreurs' => [
                    'Un dossier PEJEDEC ne doit pas etre cherche dans la file DMG de demarrage ou presence.',
                ],
                'faq' => [
                    ['question' => 'Pourquoi mon dossier PEJEDEC n apparait pas chez la DMG ?', 'reponse' => 'Le service DMG exclut ce financement car il dispose d un circuit PEJEDEC / AAF separe.'],
                ],
                'liens' => [
                    ['libelle' => 'Tableau PEJEDEC / AAF', 'href' => '/pejedec/af'],
                    ['libelle' => 'Validation PEJEDEC', 'href' => '/pejedec/af/attente-validation'],
                    ['libelle' => 'Attente paiement', 'href' => '/pejedec/af/attente-paiement'],
                ],
                'sources' => [
                    'routes/web.php',
                    'app/Http/Controllers/Pejedec/AafController.php',
                    'app/Domain/Payment/Services/PejedecAafService.php',
                    'app/Domain/Payment/Services/DmgService.php',
                ],
                'confiance' => 'moyenne',
            ],
            [
                'id' => 'administration',
                'titre' => 'Parametre & Aides',
                'icone' => 'ri-settings-4-line',
                'resume' => 'Gerer les comptes, les referentiels, les parametres systeme, les journaux et le present centre d aide.',
                'utilisateurs' => ['administrateur', 'roles avec permissions ciblees'],
                'routes' => ['/parametre-aides', '/parametre-aides/comptes', '/parametre-aides/conseillers', '/parametre-aides/agences', '/parametre-aides/parametres-systeme', '/parametre-aides/journaux', '/entreprises', '/offres'],
                'ecrans' => ['Vue d ensemble', 'Comptes utilisateurs', 'Conseillers', 'Agences', 'Entreprises', 'Offres', 'Parametres systeme', 'Journaux d activite', 'Aide / Guide utilisateur'],
                'details' => [
                    ['titre' => 'Comptes utilisateurs', 'description' => 'Creer, modifier, activer ou desactiver un compte, affecter des roles et perimetres d agences.'],
                    ['titre' => 'Usurpation', 'description' => 'Un utilisateur autorise peut basculer temporairement dans un compte cible, puis revenir a son compte initial.'],
                    ['titre' => 'Conseillers et agences', 'description' => 'Gerer les conseillers et les agences selon les permissions de referentiel.'],
                    ['titre' => 'Parametres systeme', 'description' => 'Configurer les parametres generaux et les regles de prelevement CMU sans chevauchement actif.'],
                    ['titre' => 'Journaux', 'description' => 'Filtrer, consulter et exporter les actions historisees.'],
                ],
                'actions' => ['Creer un compte', 'Modifier un compte', 'Activer ou desactiver', 'Usurper une identite', 'Rattacher un compte a un conseiller', 'Creer une regle CMU', 'Exporter les journaux'],
                'workflow' => [
                    'Les menus du hub sont filtres par permission.',
                    'L administrateur contourne les permissions par Gate::before.',
                    'Les formulaires verifient aussi l autorisation via FormRequest ou Policy.',
                ],
                'statuts' => ['actif/inactif', 'regle active', 'journal exporte'],
                'contraintes' => [
                    'La gestion des agences est reservee a l administrateur car aucun role metier ne recoit gerer_agences.',
                    'La creation et la modification des comptes passent par les policies User et les permissions gerer_utilisateurs.',
                    'Les entreprises et offres conservent leurs routes historiques, mais sont visibles dans Parametre & Aides.',
                ],
                'erreurs' => [
                    'Un menu n apparait pas si le compte ne possede pas la permission correspondante.',
                    'Une action d administration peut etre interdite meme si la page est visible, car le controle est repete dans le controleur ou la requete.',
                ],
                'faq' => [
                    ['question' => 'Pourquoi je ne vois pas les journaux ?', 'reponse' => 'Il faut la permission voir_journaux_audit ou le role administrateur.'],
                    ['question' => 'Qui peut modifier une agence ?', 'reponse' => 'Le code ne donne gerer_agences a aucun role metier. L acces effectif revient a l administrateur.'],
                ],
                'liens' => [
                    ['libelle' => 'Comptes utilisateurs', 'href' => '/parametre-aides/comptes'],
                    ['libelle' => 'Conseillers', 'href' => '/parametre-aides/conseillers'],
                    ['libelle' => 'Agences', 'href' => '/parametre-aides/agences'],
                    ['libelle' => 'Parametres systeme', 'href' => '/parametre-aides/parametres-systeme'],
                    ['libelle' => 'Journaux', 'href' => '/parametre-aides/journaux'],
                ],
                'sources' => [
                    'routes/parametre-aides.php',
                    'database/seeders/RolePermissionSeeder.php',
                    'app/Policies/UserPolicy.php',
                    'app/Http/Requests/ParametreAides',
                    'resources/js/velzone/pages/ParametreAides',
                ],
                'confiance' => 'elevee',
            ],
            [
                'id' => 'reporting',
                'titre' => 'Reporting',
                'icone' => 'ri-bar-chart-2-line',
                'resume' => 'Consulter le tableau de bord, les indicateurs et exporter les KPI.',
                'utilisateurs' => ['cip', 'chef_agence', 'desse', 'daicg', 'dmg', 'cb', 'agent_comptable', 'pejedec', 'aaf', 'administrateur'],
                'routes' => ['/dashboard', '/reporting', '/reporting/export/kpi.csv'],
                'ecrans' => ['Tableau de bord', 'Reporting'],
                'details' => [
                    ['titre' => 'Indicateurs globaux', 'description' => 'Afficher les volumes de dossiers, pointages et paiements selon les donnees consolidees.'],
                    ['titre' => 'Export KPI', 'description' => 'Exporter les indicateurs au format CSV lorsque la permission reporting est disponible.'],
                ],
                'actions' => ['Consulter le tableau de bord', 'Exporter les KPI CSV'],
                'workflow' => ['Tous les roles metier seedes recoivent voir_reporting.'],
                'statuts' => ['SOUMIS', 'VALIDE', 'AJOURNE_CA', 'AJOURNE_DMG', 'TRANSMIS_AC', 'VISE_AC'],
                'contraintes' => ['Acces soumis a la permission voir_reporting.'],
                'erreurs' => ['Un profil sans voir_reporting est redirige ou bloque sur les routes reporting.'],
                'faq' => [
                    ['question' => 'Pourquoi je ne vois pas le reporting ?', 'reponse' => 'La route est protegee par voir_reporting. Les roles metier seedes la recoivent, sauf compte mal configure.'],
                ],
                'liens' => [
                    ['libelle' => 'Tableau de bord', 'href' => '/dashboard'],
                    ['libelle' => 'Reporting', 'href' => '/reporting'],
                ],
                'sources' => [
                    'routes/web.php',
                    'app/Services/ReportingDashboardService.php',
                    'database/seeders/RolePermissionSeeder.php',
                ],
                'confiance' => 'moyenne',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roles(): array
    {
        return [
            ['nom' => 'administrateur', 'mission' => 'Administration globale et contournement des permissions via Gate::before.', 'peut' => ['Acceder a tous les modules', 'Gerer comptes, referentiels, agences, parametres et journaux', 'Usurper si necessaire'], 'nePeutPas' => ['Aucune restriction metier codee par permission, hors contraintes de statut des services'], 'modules' => ['Tous'], 'sources' => ['app/Providers/AppServiceProvider.php', 'database/seeders/RolePermissionSeeder.php']],
            ['nom' => 'cip', 'mission' => 'Gestion operationnelle des stagiaires et pointages.', 'peut' => ['Gerer entreprises et offres', 'Gerer beneficiaires, contrats et pointages', 'Consulter la supervision regionale'], 'nePeutPas' => ['Valider comme chef d agence', 'Traiter les paiements DMG, CB ou AC'], 'modules' => ['CIP', 'Entreprises', 'Offres', 'Reporting'], 'sources' => ['database/seeders/RolePermissionSeeder.php']],
            ['nom' => 'chef_agence', 'mission' => 'Validation agence des demarrages et pointages.', 'peut' => ['Voir entreprises, offres, beneficiaires, contrats et pointages', 'Valider chef d agence', 'Consulter supervision regionale'], 'nePeutPas' => ['Gerer entreprises/offres', 'Traiter DMG/CB/AC'], 'modules' => ['Chef d agence', 'Reporting'], 'sources' => ['database/seeders/RolePermissionSeeder.php', 'app/Domain/Attendance/Services/PointageChefAgenceService.php']],
            ['nom' => 'desse', 'mission' => 'Controle DESSE, doublons et visas regionaux.', 'peut' => ['Valider DESSE', 'Voir et viser les visas AR', 'Voir beneficiaires'], 'nePeutPas' => ['Traitement financier DMG/CB/AC'], 'modules' => ['DESSE', 'Supervision regionale', 'Reporting'], 'sources' => ['database/seeders/RolePermissionSeeder.php']],
            ['nom' => 'daicg', 'mission' => 'Consultation et supervision globale.', 'peut' => ['Voir beneficiaires et contrats', 'Consulter les visas AR', 'Voir le reporting'], 'nePeutPas' => ['Viser les visas AR', 'Modifier les decisions metier'], 'modules' => ['DAICG', 'Supervision regionale', 'Reporting'], 'sources' => ['database/seeders/RolePermissionSeeder.php']],
            ['nom' => 'dmg', 'mission' => 'Preparation et transmission des paiements.', 'peut' => ['Voir paiements DMG', 'Generer dossiers, etats et attestations', 'Ajourner, elaborer OP, creer bordereaux, transmettre CB et AC'], 'nePeutPas' => ['Valider CB', 'Viser AC'], 'modules' => ['DMG', 'Reporting'], 'sources' => ['database/seeders/RolePermissionSeeder.php', 'app/Domain/Payment/Services/DmgService.php']],
            ['nom' => 'cb', 'mission' => 'Controle des dossiers de paiement transmis.', 'peut' => ['Voir dossiers CB', 'Valider ou ajourner un dossier CB'], 'nePeutPas' => ['Creer OP ou viser bordereau AC'], 'modules' => ['CB', 'Reporting'], 'sources' => ['database/seeders/RolePermissionSeeder.php', 'app/Domain/Payment/Services/CbPaiementService.php']],
            ['nom' => 'agent_comptable', 'mission' => 'Visa comptable et situation de paiement.', 'peut' => ['Voir bordereaux AC', 'Viser, ajourner ou rejeter les bordereaux et OP'], 'nePeutPas' => ['Generer les dossiers DMG', 'Valider les dossiers CB'], 'modules' => ['Agent comptable', 'Reporting'], 'sources' => ['database/seeders/RolePermissionSeeder.php', 'app/Domain/Payment/Services/AgentComptableService.php']],
            ['nom' => 'pejedec', 'mission' => 'Validation du circuit PEJEDEC.', 'peut' => ['Voir beneficiaires et contrats', 'Valider PEJEDEC', 'Voir reporting'], 'nePeutPas' => ['Traiter AAF ou DMG'], 'modules' => ['PEJEDEC / AAF', 'Reporting'], 'sources' => ['database/seeders/RolePermissionSeeder.php']],
            ['nom' => 'aaf', 'mission' => 'Traitement AAF du circuit PEJEDEC.', 'peut' => ['Voir beneficiaires et contrats', 'Valider AAF', 'Voir reporting'], 'nePeutPas' => ['Traiter DMG/CB/AC'], 'modules' => ['PEJEDEC / AAF', 'Reporting'], 'sources' => ['database/seeders/RolePermissionSeeder.php']],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workflows(): array
    {
        return [
            [
                'nom' => 'Dossier de stage',
                'objectif' => 'Faire passer un dossier prepare par le CIP vers la validation agence puis vers le circuit de paiement ou le cycle mensuel.',
                'etapes' => [
                    ['acteur' => 'CIP', 'action' => 'Transmettre au chef d agence', 'avant' => 'cip_mes_stagiaires', 'apres' => 'ca_attente_validation_demarrage ou ca_attente_validation_omis', 'condition' => 'Selon le mois de date_debut du stage'],
                    ['acteur' => 'Chef d agence', 'action' => 'Valider le demarrage', 'avant' => 'ca_attente_validation_demarrage', 'apres' => 'dmg_attente_paiement_demarrage', 'condition' => 'Stage lie a une instance et contrat actif'],
                    ['acteur' => 'Chef d agence', 'action' => 'Valider un demarrage omis', 'avant' => 'ca_attente_validation_omis', 'apres' => 'en_stage', 'condition' => 'Dossier de demarrage omis'],
                    ['acteur' => 'Chef d agence', 'action' => 'Ajourner', 'avant' => 'ca_attente_validation_demarrage', 'apres' => 'cip_mes_stagiaires', 'condition' => 'Motif d ajournement fourni'],
                ],
                'sources' => ['app/Domain/Workflow/Services/WorkflowTransitionService.php', 'app/Domain/Validation/Services/ValidationChefAgenceService.php'],
            ],
            [
                'nom' => 'Pointage mensuel',
                'objectif' => 'Declarer la presence mensuelle et ouvrir un droit de paiement de presence apres validation.',
                'etapes' => [
                    ['acteur' => 'CIP', 'action' => 'Soumettre le pointage', 'avant' => 'Aucun pointage du mois', 'apres' => 'SOUMIS', 'condition' => 'Stage en cours, periode couverte, validation CA deja acquise'],
                    ['acteur' => 'Chef d agence', 'action' => 'Valider', 'avant' => 'SOUMIS', 'apres' => 'VALIDE', 'condition' => 'Pointage en attente du mois et du perimetre agence'],
                    ['acteur' => 'Chef d agence', 'action' => 'Ajourner', 'avant' => 'SOUMIS', 'apres' => 'AJOURNE_CA', 'condition' => 'Motif attendu'],
                    ['acteur' => 'DMG/CIP/CA', 'action' => 'Corriger un ajournement DMG', 'avant' => 'AJOURNE_DMG', 'apres' => 'CORRIGE_CIP puis SOUMIS', 'condition' => 'Correction acceptee par le chef d agence'],
                ],
                'sources' => ['app/Domain/Attendance/Services/PointageService.php', 'app/Domain/Attendance/Services/PointageChefAgenceService.php'],
            ],
            [
                'nom' => 'Paiement DMG -> CB -> AC',
                'objectif' => 'Transformer les droits de paiement en dossiers, OP, bordereaux puis paiements valides ou rejetes.',
                'etapes' => [
                    ['acteur' => 'DMG', 'action' => 'Generer dossier', 'avant' => 'A_TRAITER', 'apres' => 'EN_DOSSIER / BROUILLON', 'condition' => 'Paiements eligibles et non deja traites'],
                    ['acteur' => 'DMG', 'action' => 'Transmettre au CB', 'avant' => 'BROUILLON', 'apres' => 'TRANSMIS_CB', 'condition' => 'Dossier encore brouillon'],
                    ['acteur' => 'CB', 'action' => 'Valider dossier', 'avant' => 'TRANSMIS_CB', 'apres' => 'VALIDE_CB', 'condition' => 'Paiements actifs EN_DOSSIER'],
                    ['acteur' => 'DMG', 'action' => 'Elaborer OP', 'avant' => 'VALIDE_CB', 'apres' => 'EN_OP / OP BROUILLON', 'condition' => 'Meme periode et meme financement'],
                    ['acteur' => 'DMG', 'action' => 'Creer et transmettre bordereau', 'avant' => 'OP BROUILLON', 'apres' => 'EN_BORDEREAU puis TRANSMIS_AC', 'condition' => 'OP disponibles'],
                    ['acteur' => 'Agent comptable', 'action' => 'Viser ou rejeter', 'avant' => 'TRANSMIS_AC / EN_BORDEREAU', 'apres' => 'VISE_AC, PAYE, NON_PAYE, A_TRAITER ou REJETE_AC', 'condition' => 'Decision AC et motif si necessaire'],
                ],
                'sources' => ['app/Domain/Payment/Services/DmgService.php', 'app/Domain/Payment/Services/CbPaiementService.php', 'app/Domain/Payment/Services/AgentComptableService.php'],
            ],
            [
                'nom' => 'Renouvellement de contrat',
                'objectif' => 'Prolonger un stage eligible avec decision du chef d agence.',
                'etapes' => [
                    ['acteur' => 'CIP', 'action' => 'Proposer un renouvellement', 'avant' => 'Stage arrive a terme ou anticipable', 'apres' => 'ATTENTE_CA', 'condition' => 'Type de stage renouvelable, pas d avenant en cours'],
                    ['acteur' => 'Chef d agence', 'action' => 'Valider', 'avant' => 'ATTENTE_CA', 'apres' => 'VALIDE', 'condition' => 'Avenant en attente'],
                    ['acteur' => 'Chef d agence', 'action' => 'Ajourner', 'avant' => 'ATTENTE_CA', 'apres' => 'AJOURNE', 'condition' => 'Motif d ajournement'],
                    ['acteur' => 'CIP', 'action' => 'Renvoyer apres correction', 'avant' => 'AJOURNE', 'apres' => 'ATTENTE_CA', 'condition' => 'Correction effectuee'],
                ],
                'sources' => ['app/Domain/Contract/Services/RenouvellementService.php', 'app/Models/Contract/AvenantContrat.php'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function matriceFonctionnelle(): array
    {
        return [
            ['module' => 'CIP', 'fonctionnalite' => 'Transmettre un dossier', 'page' => '/cip/mes-stagiaires', 'role' => 'cip', 'action' => 'Transmission au chef d agence', 'statut' => 'cip_mes_stagiaires', 'resultat' => 'Attente validation CA'],
            ['module' => 'Chef agence', 'fonctionnalite' => 'Valider demarrage', 'page' => '/chefagence/validations', 'role' => 'chef_agence', 'action' => 'Validation', 'statut' => 'ca_attente_validation_demarrage', 'resultat' => 'Paiement A_TRAITER en DMG'],
            ['module' => 'Pointages', 'fonctionnalite' => 'Soumettre pointage', 'page' => '/cip/pointages', 'role' => 'cip', 'action' => 'Soumission', 'statut' => 'Aucun pointage valide du mois', 'resultat' => 'Pointage SOUMIS'],
            ['module' => 'Pointages', 'fonctionnalite' => 'Valider pointage', 'page' => '/chefagence/pointages', 'role' => 'chef_agence', 'action' => 'Validation', 'statut' => 'SOUMIS', 'resultat' => 'Pointage VALIDE et droit PRESENCE'],
            ['module' => 'DMG', 'fonctionnalite' => 'Generer dossier paiement', 'page' => '/dmg/paiements', 'role' => 'dmg', 'action' => 'Generation', 'statut' => 'A_TRAITER', 'resultat' => 'Dossier BROUILLON et paiements EN_DOSSIER'],
            ['module' => 'CB', 'fonctionnalite' => 'Valider dossier CB', 'page' => '/cb/paiements', 'role' => 'cb', 'action' => 'Validation', 'statut' => 'TRANSMIS_CB', 'resultat' => 'VALIDE_CB'],
            ['module' => 'DMG', 'fonctionnalite' => 'Elaborer OP', 'page' => '/dmg/paiements', 'role' => 'dmg', 'action' => 'Creation OP', 'statut' => 'VALIDE_CB', 'resultat' => 'OP BROUILLON et dossiers EN_OP'],
            ['module' => 'Agent comptable', 'fonctionnalite' => 'Valider OP', 'page' => '/agent-comptable/paiements', 'role' => 'agent_comptable', 'action' => 'Visa OP', 'statut' => 'EN_BORDEREAU', 'resultat' => 'VALIDE_AC puis PAYE/NON_PAYE'],
            ['module' => 'Parametre & Aides', 'fonctionnalite' => 'Gerer comptes', 'page' => '/parametre-aides/comptes', 'role' => 'administrateur', 'action' => 'Creation/modification/activation', 'statut' => 'Compte actif ou inactif', 'resultat' => 'Compte mis a jour'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function matricePermissions(): array
    {
        return [
            ['fonctionnalite' => 'Reporting', 'administrateur' => 'Lecture export', 'cip' => 'Lecture export', 'chef_agence' => 'Lecture export', 'dmg' => 'Lecture export', 'cb' => 'Lecture export', 'agent_comptable' => 'Lecture export', 'desse' => 'Lecture export', 'daicg' => 'Lecture export'],
            ['fonctionnalite' => 'Entreprises et offres', 'administrateur' => 'Lecture creation modification suppression', 'cip' => 'Lecture creation modification suppression', 'chef_agence' => 'Lecture', 'dmg' => '-', 'cb' => '-', 'agent_comptable' => '-', 'desse' => '-', 'daicg' => '-'],
            ['fonctionnalite' => 'Pointages', 'administrateur' => 'Lecture modification validation', 'cip' => 'Lecture modification', 'chef_agence' => 'Lecture validation rejet', 'dmg' => 'Lecture', 'cb' => 'Lecture', 'agent_comptable' => '-', 'desse' => '-', 'daicg' => '-'],
            ['fonctionnalite' => 'Paiements DMG', 'administrateur' => 'Lecture validation generation export', 'cip' => '-', 'chef_agence' => '-', 'dmg' => 'Lecture generation ajournement export OP bordereau', 'cb' => '-', 'agent_comptable' => '-', 'desse' => '-', 'daicg' => '-'],
            ['fonctionnalite' => 'Controle CB', 'administrateur' => 'Lecture validation ajournement', 'cip' => '-', 'chef_agence' => '-', 'dmg' => '-', 'cb' => 'Lecture validation ajournement', 'agent_comptable' => '-', 'desse' => '-', 'daicg' => '-'],
            ['fonctionnalite' => 'Visa AC', 'administrateur' => 'Lecture visa ajournement rejet', 'cip' => '-', 'chef_agence' => '-', 'dmg' => '-', 'cb' => '-', 'agent_comptable' => 'Lecture visa ajournement rejet', 'desse' => '-', 'daicg' => '-'],
            ['fonctionnalite' => 'Visas regionaux', 'administrateur' => 'Lecture visa export', 'cip' => 'Lecture', 'chef_agence' => 'Lecture', 'dmg' => '-', 'cb' => '-', 'agent_comptable' => '-', 'desse' => 'Lecture visa export', 'daicg' => 'Lecture export'],
            ['fonctionnalite' => 'Administration', 'administrateur' => 'Lecture creation modification export usurpation', 'cip' => '-', 'chef_agence' => '-', 'dmg' => '-', 'cb' => '-', 'agent_comptable' => '-', 'desse' => '-', 'daicg' => '-'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function matriceWorkflows(): array
    {
        return collect($this->workflows())
            ->flatMap(fn (array $workflow) => collect($workflow['etapes'])->map(fn (array $etape) => [
                'workflow' => $workflow['nom'],
                'acteur' => $etape['acteur'],
                'action' => $etape['action'],
                'statutAvant' => $etape['avant'],
                'statutApres' => $etape['apres'],
                'condition' => $etape['condition'],
            ]))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function faq(): array
    {
        return [
            ['module' => 'General', 'question' => 'Pourquoi un menu n apparait pas ?', 'reponse' => 'Les menus et routes sont proteges par les permissions du compte. L administrateur a un acces global, les autres roles voient seulement leur perimetre.'],
            ['module' => 'CIP', 'question' => 'Pourquoi le pointage n est pas disponible ?', 'reponse' => 'Le stage doit etre en cours, couvrir la periode selectionnee et ne plus etre en attente de validation initiale par le chef d agence.'],
            ['module' => 'Chef agence', 'question' => 'Que produit la validation d un demarrage ?', 'reponse' => 'Elle cree un droit de paiement DEMARRAGE, un paiement A_TRAITER, puis positionne le paiement dans la file DMG.'],
            ['module' => 'DMG', 'question' => 'Pourquoi certains dossiers PEJEDEC ne sont pas visibles ?', 'reponse' => 'Le service DMG exclut explicitement le financement PEJEDEC, gere par le circuit PEJEDEC / AAF.'],
            ['module' => 'CB', 'question' => 'Pourquoi un dossier ne peut plus etre valide ?', 'reponse' => 'Il doit encore etre TRANSMIS_CB, sans OP rattachee, et contenir des paiements actifs EN_DOSSIER.'],
            ['module' => 'Agent comptable', 'question' => 'Pourquoi je ne peux pas confirmer PAYE ?', 'reponse' => 'La confirmation PAYE ou NON_PAYE est reservee aux paiements deja VALIDE_AC apres visa de l OP.'],
            ['module' => 'Administration', 'question' => 'Qui peut gerer les agences ?', 'reponse' => 'La permission gerer_agences existe, mais aucun role metier ne la recoit dans le seeder. L administrateur y accede via son bypass global.'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function erreurs(): array
    {
        return [
            ['symptome' => 'Le menu ou la page est inaccessible', 'cause' => 'Permission absente ou compte hors role attendu', 'condition' => 'Route protegee par can:* ou policy', 'solution' => 'Verifier le role et le perimetre du compte aupres d un administrateur.'],
            ['symptome' => 'Selection de paiements invalide', 'cause' => 'Un paiement est deja traite, annule ou sorti de la corbeille attendue', 'condition' => 'Generation dossier ou reprise DMG', 'solution' => 'Actualiser la page et refaire la selection avec les paiements encore eligibles.'],
            ['symptome' => 'Ce dossier n est plus en attente de traitement CB', 'cause' => 'Le dossier n est plus TRANSMIS_CB ou il est deja rattache a une OP', 'condition' => 'Validation ou ajournement CB', 'solution' => 'Actualiser la liste et controler le statut du dossier.'],
            ['symptome' => 'Cette OP ne depend plus d un bordereau en cours de traitement AC', 'cause' => 'Le bordereau ou l OP a deja change de statut', 'condition' => 'Decision AC sur une OP', 'solution' => 'Actualiser le bordereau et traiter uniquement les OP encore EN_BORDEREAU.'],
            ['symptome' => 'Aucun paiement a confirmer', 'cause' => 'Les paiements ne sont pas encore VALIDE_AC ou sont deja PAYE/NON_PAYE', 'condition' => 'Confirmation de situation AC', 'solution' => 'Verifier que l OP est visee et selectionner des paiements VALIDE_AC.'],
            ['symptome' => 'Un renouvellement est deja en cours', 'cause' => 'Le contrat a deja un avenant ATTENTE_CA ou AJOURNE', 'condition' => 'Proposition CIP de renouvellement', 'solution' => 'Traiter ou corriger l avenant existant avant une nouvelle demande.'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function reglesMetier(): array
    {
        return [
            ['regle' => 'Un pointage mensuel valide par le chef d agence genere un droit de paiement PRESENCE et un paiement A_TRAITER.', 'confiance' => 'elevee', 'sources' => 'PointageService::validerMensuel'],
            ['regle' => 'Un demarrage valide par le chef d agence genere un droit de paiement DEMARRAGE.', 'confiance' => 'elevee', 'sources' => 'ValidationChefAgenceService::validerDemarrage'],
            ['regle' => 'Le financement PEJEDEC est exclu de la file DMG.', 'confiance' => 'elevee', 'sources' => 'DmgService::attentePaiement'],
            ['regle' => 'Les doublons DESSE non traites bloquent l entree dans la file DMG.', 'confiance' => 'elevee', 'sources' => 'DmgService + DesseDoublonService'],
            ['regle' => 'Un bordereau AC reste ouvert tant qu une OP reste EN_BORDEREAU.', 'confiance' => 'elevee', 'sources' => 'AgentComptableService::finaliserBordereau'],
            ['regle' => 'Un renouvellement ne peut pas etre cree lorsqu un avenant est deja en cours.', 'confiance' => 'elevee', 'sources' => 'RenouvellementService::renouveler'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function statuts(): array
    {
        return [
            ['domaine' => 'Pointage', 'statuts' => ['SOUMIS', 'VALIDE', 'AJOURNE_CA', 'AJOURNE_DMG', 'CORRIGE_CIP', 'REJETE_DEFINITIF']],
            ['domaine' => 'Paiement', 'statuts' => ['A_TRAITER', 'AJOURNE_DMG', 'EN_DOSSIER', 'EN_OP', 'VALIDE_AC', 'PAYE', 'NON_PAYE', 'REJETE_AC', 'REJETE_DEFINITIF']],
            ['domaine' => 'Dossier paiement', 'statuts' => ['BROUILLON', 'TRANSMIS_CB', 'VALIDE_CB', 'AJOURNE_CB', 'EN_OP', 'VISE_AC', 'REJETE_AC']],
            ['domaine' => 'OP et bordereau', 'statuts' => ['BROUILLON', 'EN_BORDEREAU', 'TRANSMIS_AC', 'VISE_AC', 'DIFFERE_AC', 'REJETE_AC', 'ANNULE']],
            ['domaine' => 'Renouvellement', 'statuts' => ['ATTENTE_CA', 'VALIDE', 'AJOURNE']],
            ['domaine' => 'Corbeilles', 'statuts' => ['cip_mes_stagiaires', 'ca_attente_validation_demarrage', 'en_stage', 'dmg_attente_paiement_presence', 'ac_bordereau_op_attente']],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function glossaire(): array
    {
        return [
            ['terme' => 'CIP', 'definition' => 'Conseiller charge des dossiers stagiaires, contrats, pointages et corrections.', 'module' => 'CIP'],
            ['terme' => 'CA', 'definition' => 'Chef d agence qui valide les demarrages et pointages de son perimetre.', 'module' => 'Chef agence'],
            ['terme' => 'DMG', 'definition' => 'Acteur qui prepare les dossiers de paiement, OP et bordereaux.', 'module' => 'Paiements'],
            ['terme' => 'CB', 'definition' => 'Chef de bureau qui controle les dossiers transmis par la DMG.', 'module' => 'Paiements'],
            ['terme' => 'AC', 'definition' => 'Agent comptable qui vise, differe ou rejette les OP et bordereaux.', 'module' => 'Paiements'],
            ['terme' => 'OP', 'definition' => 'Ordre de paiement elabore par la DMG a partir de dossiers valides CB.', 'module' => 'Paiements'],
            ['terme' => 'Bordereau', 'definition' => 'Regroupement d OP transmis a l agent comptable.', 'module' => 'Paiements'],
            ['terme' => 'Ajourner', 'definition' => 'Renvoyer un element pour correction avec un motif.', 'module' => 'Workflows'],
            ['terme' => 'Corbeille', 'definition' => 'Position fonctionnelle d un dossier, pointage ou paiement dans le workflow.', 'module' => 'Workflows'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function architecture(): array
    {
        return [
            'technologies' => ['Laravel', 'Inertia.js', 'React', 'TypeScript', 'Reactstrap', 'Spatie Permission', 'PostgreSQL/MySQL selon environnement'],
            'navigation' => ['Menu principal', 'Dossiers de stage', 'Presences et pointages', 'Paiements et OP', 'PEJEDEC / AAF', 'Parametre & Aides'],
            'dossiersAnalyses' => ['routes', 'app/Http/Controllers', 'app/Domain', 'app/Models', 'app/Policies', 'app/Http/Requests', 'database/migrations', 'database/seeders', 'resources/js/velzone/pages', 'resources/js/velzone/Layouts'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function baseConnaissances(array $rubriques): array
    {
        return array_map(fn (array $rubrique): array => [
            'module' => $rubrique['titre'],
            'feature' => implode(', ', array_column($rubrique['details'], 'titre')),
            'page' => implode(', ', $rubrique['ecrans']),
            'route' => implode(', ', $rubrique['routes']),
            'roles' => $rubrique['utilisateurs'],
            'objective' => $rubrique['resume'],
            'prerequisites' => $rubrique['contraintes'],
            'steps' => $rubrique['workflow'],
            'business_rules' => $rubrique['contraintes'],
            'statuses' => $rubrique['statuts'],
            'possible_errors' => $rubrique['erreurs'],
            'solutions' => array_column($rubrique['faq'], 'reponse'),
            'related_features' => array_column($rubrique['details'], 'titre'),
            'keywords' => array_values(array_unique(array_merge([$rubrique['titre']], $rubrique['routes'], $rubrique['statuts'], $rubrique['actions']))),
        ], $rubriques);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function aideContextuelle(array $rubriques): array
    {
        return array_map(fn (array $rubrique): array => [
            'page' => $rubrique['ecrans'][0],
            'title' => $rubrique['titre'],
            'summary' => $rubrique['resume'],
            'actions' => array_map(fn (string $action): array => [
                'name' => $action,
                'description' => 'Action disponible selon le role, le statut courant et les contraintes du module.',
                'conditions' => $rubrique['contraintes'],
            ], $rubrique['actions']),
            'faq' => $rubrique['faq'],
            'warnings' => $rubrique['erreurs'],
            'next_steps' => $rubrique['workflow'],
        ], $rubriques);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function intents(array $rubriques): array
    {
        return array_map(fn (array $rubrique): array => [
            'intent' => str_replace('-', '_', $rubrique['id']),
            'examples' => [
                'Comment utiliser '.$rubrique['titre'].' ?',
                'Pourquoi une action n apparait pas dans '.$rubrique['titre'].' ?',
                'Que faire si le dossier est bloque dans '.$rubrique['titre'].' ?',
            ],
            'answer_source' => implode(', ', $rubrique['sources']),
        ], $rubriques);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function indexRecherche(array $rubriques): array
    {
        return array_map(fn (array $rubrique): array => [
            'title' => $rubrique['titre'],
            'module' => $rubrique['id'],
            'roles' => $rubrique['utilisateurs'],
            'keywords' => array_values(array_unique(array_merge($rubrique['routes'], $rubrique['statuts'], $rubrique['actions']))),
            'questions' => array_column($rubrique['faq'], 'question'),
            'content' => $rubrique['resume'].' '.implode(' ', $rubrique['workflow']).' '.implode(' ', $rubrique['contraintes']),
        ], $rubriques);
    }

    /**
     * @return list<array<string, string>>
     */
    private function incoherences(): array
    {
        return [
            ['probleme' => 'Endpoint frontend sans route visible', 'impact' => 'Le composant ChefAgence/Pointages appelle /chefagence/pointages/valider-par-filtre, mais la route lue dans routes/web.php n expose pas cette URL.', 'fichiers' => 'resources/js/velzone/pages/ChefAgence/Pointages/Index.tsx; routes/web.php', 'confiance' => 'moyenne', 'recommandation' => 'Verifier si une route manque ou si le bouton doit appeler valider-groupe.'],
            ['probleme' => 'Routes CB sans middleware can explicite', 'impact' => 'La protection peut reposer sur un controle ailleurs; l aide le signale comme a confirmer pour la permission exacte.', 'fichiers' => 'routes/web.php; Cb/Paiements/Index.tsx', 'confiance' => 'faible', 'recommandation' => 'Confirmer le controle d acces dans le controleur CB avant documentation definitive.'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function nonDocumentables(): array
    {
        return [
            ['sujet' => 'Messages exacts de certains formulaires volumineux', 'ceQueLeCodeMontre' => 'Les pages Inertia appellent plusieurs endpoints et modales.', 'ambiguite' => 'Tous les libelles et validations fines n ont pas ete recroises ligne par ligne dans cette integration initiale.', 'informationNecessaire' => 'Audit ecran par ecran si le centre d aide doit devenir contractuel.'],
            ['sujet' => 'Notifications mail/SMS', 'ceQueLeCodeMontre' => 'Aucun dossier app/Notifications, app/Events ou app/Listeners n est present dans l arborescence lue.', 'ambiguite' => 'Des notifications peuvent exister via flash messages ou librairies frontend.', 'informationNecessaire' => 'Verification exhaustive des messages flash et toasts par page.'],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function tracabilite(): array
    {
        return [
            ['regle' => 'Roles et permissions', 'sources' => 'database/seeders/RolePermissionSeeder.php; app/Providers/AppServiceProvider.php'],
            ['regle' => 'Menus et navigation', 'sources' => 'resources/js/velzone/Layouts/LayoutMenuData.tsx'],
            ['regle' => 'Routes metier', 'sources' => 'routes/web.php; routes/parametre-aides.php'],
            ['regle' => 'Cycle pointage', 'sources' => 'app/Domain/Attendance/Services/PointageService.php; app/Domain/Attendance/Services/PointageChefAgenceService.php'],
            ['regle' => 'Cycle paiement', 'sources' => 'app/Domain/Payment/Services/DmgService.php; CbPaiementService.php; AgentComptableService.php'],
            ['regle' => 'Renouvellements', 'sources' => 'app/Domain/Contract/Services/RenouvellementService.php; app/Models/Contract/AvenantContrat.php'],
        ];
    }

    /**
     * @return list<string>
     */
    private function sourcesAnalysees(): array
    {
        return [
            'routes/web.php',
            'routes/parametre-aides.php',
            'database/seeders/RolePermissionSeeder.php',
            'app/Providers/AppServiceProvider.php',
            'app/Policies/UserPolicy.php',
            'app/Policies/EntreprisePolicy.php',
            'app/Policies/OffreEmploiPolicy.php',
            'app/Enums/CorbeilleEnum.php',
            'app/Domain/Workflow/Services/WorkflowTransitionService.php',
            'app/Domain/Validation/Services/ValidationChefAgenceService.php',
            'app/Domain/Attendance/Services/PointageService.php',
            'app/Domain/Attendance/Services/PointageChefAgenceService.php',
            'app/Domain/Payment/Services/DmgService.php',
            'app/Domain/Payment/Services/CbPaiementService.php',
            'app/Domain/Payment/Services/AgentComptableService.php',
            'app/Domain/Contract/Services/RenouvellementService.php',
            'resources/js/velzone/Layouts/LayoutMenuData.tsx',
            'resources/js/velzone/pages',
        ];
    }
}
