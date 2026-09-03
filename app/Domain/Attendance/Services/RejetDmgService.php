<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Workflow\Services\WorkflowTransitionService;
use App\Models\Attendance\DecisionPointage;
use App\Models\Attendance\Pointage;
use App\Models\Document\Document;
use App\Models\Document\VersionDocument;
use App\Models\Internship\Stage;
use App\Models\Payment\DecisionPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\TypeDocument;
use App\Models\Reference\TypePaiement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Traitement CIP d'un pointage rejeté par la DMG.
 *
 * Reprend le parcours legacy `ChefAgence\AttestationPresenceController@editStagiaire` /
 * `@updateStagiaire` (vue `stage.chefagence.pointage.update-form`) : le CIP corrige la fiche
 * du stagiaire puis la renvoie au Chef d'Agence pour re-validation.
 *
 * Le legacy fusionnait les deux gestes (l'enregistrement déclenchait toujours le
 * `TraitementEvent` de remontée). Ici ils sont dissociables : on peut corriger sans transmettre,
 * transmettre plus tard, ou faire les deux d'un coup.
 */
class RejetDmgService
{
    /**
     * Pièces justificatives redéposables depuis le formulaire de traitement.
     * Codes alignés sur les préfixes d'upload du legacy (`updateStagiaireAfterRejetDmg`).
     *
     * @var array<string, string>
     */
    public const TYPES_DOCUMENT = [
        'CONTRAT' => 'Contrat de stage',
        'FICHE_AEJ' => 'Fiche AEJ',
        'PIECE_IDENTITE' => "Pièce d'identité",
        'FICHIER_CMU' => 'Attestation CMU',
        'FICHIER_DIPLOME' => 'Diplôme',
        'FICHIER_ATTESTATION' => "Attestation d'admissibilité",
        'FICHIER_CERTIFICAT_FREQUENTATION' => 'Certificat de fréquentation',
        'FICHIER_RIB' => 'Fiche RIB',
        'TRESOR_MONEY' => 'Fiche Trésor Money',
        'FICHE_WAVE' => 'Fiche Wave',
    ];

    /**
     * Champs de `beneficiaires` que le CIP peut corriger.
     *
     * @var array<int, string>
     */
    public const CHAMPS_BENEFICIAIRE = [
        'nom', 'prenoms', 'sexe', 'date_naissance', 'lieu_naissance', 'sous_prefecture_naissance',
        'commune_residence_id', 'sous_prefecture_residence', 'nature_piece_identite',
        'numero_piece_identite', 'numero_cmu', 'telephone_principal', 'telephone_secondaire',
        'email', 'personne_urgence', 'lien_parente_id', 'contact_urgence_1', 'contact_urgence_2',
        'niveau_etude_id', 'diplome_id', 'autre_diplome', 'specialite', 'annee_diplome',
        'etablissement_frequente', 'type_enseignement_id', 'handicap_id', 'type_handicap_id',
        'autre_handicap', 'type_paiement_id', 'numero_tresor_money', 'numero_wave',
    ];

    /**
     * Champs de `stages` que le CIP peut corriger.
     *
     * @var array<int, string>
     */
    public const CHAMPS_STAGE = [
        // Structure du stage (aligné sur Inscriptions/Create)
        'agence_id', 'conseiller_id', 'origine_stagiaire_id', 'offre_emploi_id',
        'source_financement_id', 'type_structure_id', 'date_entree_portefeuille',
        // Entreprise & encadrement
        'entreprise_id', 'type_stage_id', 'service_affectation', 'intitule_poste', 'localite_stage',
        'commune_stage', 'sous_prefecture_stage', 'nom_encadreur', 'fonction_encadreur',
        'contact_encadreur',
        // Statut, situation, dates
        'statut_stage', 'situation_stage', 'date_debut', 'date_fin_prevue',
        'nbr_mois_capitaliser', 'date_demarrage_capitalisation',
        'date_demarrage_capitalisation_sans_financiere', 'observations',
    ];

    public function __construct(private WorkflowTransitionService $workflow) {}

    /**
     * Le paiement ajourné par la DMG rattaché à ce stage, s'il existe encore.
     */
    public function paiementAjourne(Stage $stage): ?Paiement
    {
        return Paiement::with('decisions.auteur')
            ->where('statut', 'AJOURNE_DMG')
            ->whereHas('droitPaiement', function ($query) use ($stage) {
                $query->where('stage_id', $stage->id)->whereNotNull('pointage_id');
            })
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Le pointage que la correction doit renvoyer au Chef d'Agence.
     *
     * L'onglet « Ajourné / DMG » ne liste que les paiements `AJOURNE_DMG` dont le pointage est
     * encore `VALIDE` : c'est ce pointage-là qui repasse en `CORRIGE_CIP` à la transmission.
     */
    public function pointageACorriger(Stage $stage): ?Pointage
    {
        $paiement = $this->paiementAjourne($stage);

        return $paiement?->droitPaiement?->pointage()->with('versionCourante')->first();
    }

    /**
     * Applique les corrections de la fiche stagiaire (bénéficiaire + stage + pièces jointes).
     *
     * @param  array<string, mixed>  $donnees  valeurs déjà validées
     * @param  array<string, UploadedFile>  $fichiers  indexés par code de type de document
     */
    public function appliquerCorrections(Stage $stage, array $donnees, array $fichiers, User $auteur): void
    {
        DB::transaction(function () use ($stage, $donnees, $fichiers, $auteur) {
            $beneficiaire = $stage->beneficiaire;

            if (! $beneficiaire) {
                throw new RuntimeException('Le stage ne référence aucun bénéficiaire.');
            }

            $valeursBeneficiaire = array_intersect_key($donnees, array_flip(self::CHAMPS_BENEFICIAIRE));

            // Un stagiaire n'a qu'un canal de paiement : on purge le numéro de l'autre canal pour
            // que la DMG ne retrouve pas l'ancienne coordonnée à l'origine du rejet.
            if (! empty($valeursBeneficiaire['type_paiement_id'])) {
                $typePaiement = TypePaiement::find($valeursBeneficiaire['type_paiement_id']);

                if ($typePaiement?->estTresorMoney()) {
                    $valeursBeneficiaire['numero_wave'] = null;
                } elseif ($typePaiement?->estWave()) {
                    $valeursBeneficiaire['numero_tresor_money'] = null;
                }
            }

            $beneficiaire->update($valeursBeneficiaire);
            $stage->update(array_intersect_key($donnees, array_flip(self::CHAMPS_STAGE)));

            foreach ($fichiers as $code => $fichier) {
                $this->deposerDocument($stage, $fichier, $code, $auteur);
            }
        });
    }

    /**
     * Renvoie le pointage corrigé au Chef d'Agence (legacy : `TraitementEvent` de remontée).
     *
     * Le pointage quitte l'état `VALIDE` pour `CORRIGE_CIP`, ce qui le fait sortir de l'onglet
     * « Ajourné / DMG » et apparaître dans la corbeille de re-validation du CA.
     */
    public function transmettreAuChefAgence(Stage $stage, User $auteur, ?string $motif = null): Pointage
    {
        $pointage = $this->pointageACorriger($stage);

        if (! $pointage) {
            throw new RuntimeException("Aucun pointage ajourné par la DMG n'est rattaché à ce stagiaire.");
        }

        return DB::transaction(function () use ($stage, $pointage, $auteur, $motif) {
            $this->workflow->cipCorrigeAjournementDmg($pointage);

            DecisionPointage::create([
                'pointage_id' => $pointage->id,
                'version_pointage_id' => $pointage->versionCourante?->id,
                'auteur_id' => $auteur->id,
                'decision' => 'CORRIGE_CIP',
                'motif' => $motif,
            ]);

            // Trace côté paiement : la DMG et le CA doivent voir que le rejet a été traité,
            // le statut du paiement lui-même ne bouge qu'à la re-validation du CA.
            $paiement = $this->paiementAjourne($stage);

            if ($paiement) {
                DecisionPaiement::create([
                    'paiement_id' => $paiement->id,
                    'auteur_id' => $auteur->id,
                    'decision' => 'CORRIGE_CIP',
                    'statut_avant' => $paiement->statut,
                    'statut_apres' => $paiement->statut,
                    'motif' => $motif,
                    'decide_le' => now(),
                ]);
            }

            return $pointage;
        });
    }

    /**
     * Dépose une nouvelle version d'une pièce justificative (sous-système GED
     * documents / versions_documents). Un même Document est réutilisé pour un couple
     * (stage, type) : chaque dépôt ajoute une version au lieu de recréer un document.
     */
    private function deposerDocument(Stage $stage, UploadedFile $fichier, string $code, User $auteur): Document
    {
        $typeDocument = TypeDocument::firstOrCreate(
            ['code' => $code],
            ['nom' => self::TYPES_DOCUMENT[$code] ?? $code, 'actif' => true]
        );

        $document = Document::firstOrNew([
            'stage_id' => $stage->id,
            'type_document_id' => $typeDocument->id,
        ]);
        $document->beneficiaire_id = $stage->beneficiaire_id;
        $document->contrat_id = $stage->contrats()->latest('id')->value('id');
        $document->cree_par_id = $document->cree_par_id ?? $auteur->id;
        $document->nom = $fichier->getClientOriginalName();
        $document->statut = 'VALIDE';
        $document->prive = true;
        $document->save();

        $chemin = $fichier->store('rejets_dmg/'.$stage->id, 'public');

        VersionDocument::create([
            'document_id' => $document->id,
            'depose_par_id' => $auteur->id,
            'numero_version' => $document->versions()->max('numero_version') + 1,
            'disque' => 'public',
            'chemin' => $chemin,
            'nom_original' => $fichier->getClientOriginalName(),
            'type_mime' => $fichier->getMimeType(),
            'taille_octets' => $fichier->getSize(),
            'empreinte_sha256' => hash_file('sha256', $fichier->getRealPath()),
            'depose_le' => now(),
        ]);

        return $document;
    }
}
