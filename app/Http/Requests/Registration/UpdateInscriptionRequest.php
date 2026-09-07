<?php

namespace App\Http\Requests\Registration;

use App\Rules\AgeStageAutorise;
use App\Rules\JourAutoriseDebutStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Règles de validation du formulaire d'édition d'un dossier stagiaire, adaptées du
 * legacy PostUpdateStagiaire (voir plan glittery-exploring-pearl.md). L'autorisation
 * (rôle + périmètre agence) reste portée par InscriptionController::assertCanEdit(),
 * pas par cette classe.
 */
class UpdateInscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $typeStageId = $this->input('stage.type_stage_id');
        $sourceFinancementId = $this->input('stage.source_financement_id');
        $origineStagiaireId = $this->input('stage.origine_stagiaire_id');

        return [
            // Le sous-ensemble des champs bénéficiaire/stage/contrat ci-dessous doit
            // rester le sur-ensemble de InscriptionController::update()'s onlyKnown()
            // allow-lists : un champ absent d'ici est silencieusement ignoré par
            // FormRequest::validated() et donc jamais persisté.
            // 'present' (pas 'required') : une édition peut légitimement ne toucher
            // qu'une section (ex. dépôt de document seul), auquel cas le client envoie
            // un tableau vide pour les autres — 'required' échouerait car Laravel
            // considère un tableau vide comme "absent" pour cette règle.
            'beneficiaire' => ['present', 'array'],
            'beneficiaire.numero_aej' => ['nullable', 'string', 'between:10,14'],
            'beneficiaire.nom' => ['nullable', 'string', 'max:255'],
            'beneficiaire.prenoms' => ['nullable', 'string', 'max:255'],
            'beneficiaire.date_naissance' => ['nullable', 'date', new AgeStageAutorise($typeStageId ? (int) $typeStageId : null)],
            'beneficiaire.lieu_naissance' => ['nullable', 'string', 'max:255'],
            'beneficiaire.sous_prefecture_naissance' => ['nullable', 'string', 'max:255'],
            'beneficiaire.sexe' => ['nullable', 'string', 'in:M,F'],
            'beneficiaire.telephone_principal' => ['nullable', 'string', 'regex:/^0[0-9]{9}$/'],
            'beneficiaire.telephone_secondaire' => ['nullable', 'string', 'regex:/^0[0-9]{9}$/'],
            'beneficiaire.email' => ['nullable', 'email', 'max:255'],
            'beneficiaire.commune_residence_id' => ['nullable', 'exists:communes,id'],
            'beneficiaire.sous_prefecture_residence' => ['nullable', 'string', 'max:255'],
            'beneficiaire.nature_piece_identite' => ['nullable', 'string', 'max:255'],
            'beneficiaire.numero_piece_identite' => ['nullable', 'string', 'max:255'],
            'beneficiaire.numero_cmu' => ['nullable', 'string', 'max:50'],
            'beneficiaire.personne_urgence' => ['nullable', 'string', 'max:255'],
            'beneficiaire.lien_parente_id' => ['nullable', 'exists:liens_parente,id'],
            'beneficiaire.contact_urgence_1' => ['nullable', 'string', 'regex:/^0[0-9]{9}$/'],
            'beneficiaire.contact_urgence_2' => ['nullable', 'string', 'regex:/^0[0-9]{9}$/'],
            'beneficiaire.niveau_etude_id' => ['nullable', 'exists:niveaux_etude,id'],
            'beneficiaire.diplome_id' => ['nullable', 'exists:diplomes,id'],
            'beneficiaire.autre_diplome' => ['required_if:beneficiaire.diplome_id,42', 'nullable', 'string'],
            'beneficiaire.specialite' => ['nullable', 'string', 'max:255'],
            'beneficiaire.annee_diplome' => ['nullable', 'integer'],
            'beneficiaire.etablissement_frequente' => ['nullable', 'string', 'max:255'],
            'beneficiaire.type_enseignement_id' => ['nullable', 'exists:types_enseignement,id'],
            'beneficiaire.handicap_id' => ['nullable', 'exists:handicaps,id'],
            'beneficiaire.type_handicap_id' => ['nullable', 'exists:types_handicap,id'],
            'beneficiaire.autre_handicap' => ['nullable', 'string'],
            'beneficiaire.type_paiement_id' => ['nullable', 'exists:types_paiement,id'],
            'beneficiaire.numero_tresor_money' => ['nullable', 'string', 'between:10,10'],
            'beneficiaire.numero_wave' => ['nullable', 'string', 'between:10,10'],

            'stage' => ['present', 'array'],
            'stage.agence_id' => ['nullable', 'exists:agences,id'],
            'stage.conseiller_id' => ['nullable', 'exists:conseillers,id'],
            'stage.origine_stagiaire_id' => ['nullable', 'exists:origines_stagiaire,id'],
            'stage.date_entree_portefeuille' => ['nullable', 'date'],
            'stage.type_stage_id' => ['nullable', 'exists:types_stage,id'],
            'stage.source_financement_id' => ['nullable', 'exists:sources_financement,id'],
            'stage.programme_id' => ['nullable', 'exists:programmes,id'],
            'stage.service_affectation' => ['nullable', 'string', 'max:255'],
            'stage.intitule_poste' => ['nullable', 'string', 'max:255'],
            'stage.localite_stage' => ['nullable', 'string', 'max:255'],
            'stage.commune_stage' => ['nullable', 'string', 'max:255'],
            'stage.sous_prefecture_stage' => ['nullable', 'string', 'max:255'],
            'stage.nom_encadreur' => ['nullable', 'string', 'max:255'],
            'stage.fonction_encadreur' => ['nullable', 'string', 'max:255'],
            'stage.contact_encadreur' => ['nullable', 'string', 'regex:/^0[0-9]{9}$/'],
            'stage.statut_stage' => ['nullable', 'string'],
            'stage.situation_stage' => ['nullable', 'string'],
            'stage.nbr_mois_capitaliser' => ['nullable', 'integer', 'min:0'],
            'stage.date_demarrage_capitalisation' => ['nullable', 'date'],
            'stage.date_demarrage_capitalisation_sans_financiere' => ['nullable', 'date'],
            'stage.observations' => ['nullable', 'string'],
            'stage.offre_emploi_id' => [
                Rule::requiredIf(fn () => (string) $origineStagiaireId === '1'),
                'nullable', 'exists:offres_emploi,id',
            ],
            'stage.entreprise_id' => ['nullable', 'exists:entreprises,id'],
            'stage.date_debut' => ['nullable', 'date', new JourAutoriseDebutStage($sourceFinancementId ? (int) $sourceFinancementId : null)],
            'stage.date_fin_prevue' => ['nullable', 'date', 'after_or_equal:stage.date_debut'],
            'stage.duree_mois' => ['nullable', 'numeric', 'min:0.5', 'max:24'],

            'contrat' => ['nullable', 'array'],
            'contrat.numero' => ['nullable', 'string', 'max:255'],
            'contrat.prime_mensuelle' => ['nullable', 'numeric', 'min:0'],
            'contrat.date_debut' => ['nullable', 'date'],
            'contrat.date_fin' => ['nullable', 'date', 'after_or_equal:contrat.date_debut'],

            'documents' => ['nullable', 'array'],
            'documents.*' => ['nullable', 'file', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.regex' => 'Le numéro de téléphone doit être composé de 10 chiffres (format ivoirien).',
        ];
    }
}
