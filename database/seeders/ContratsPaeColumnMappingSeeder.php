<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContratsPaeColumnMappingSeeder extends Seeder
{
    public function run(): void
    {
        $colonnes = array_map(
            static fn (string $colonne): string => trim($colonne),
            explode(',', <<<'COLONNES'
id,id_entreprise,id_agence,id_user,id_user_dmg,id_user_chef_agence,id_user_desse,id_soupref_lieu_stage,id_commune_de_residence,id_sous_prefecture_de_residence,id_type_stage,id_soupref_lieu_naisse,custom_sous_prefecture_naissance,id_commune_lieu_stage,id_reg,id_statut_stage,id_situation_stage,id_user_arret,match_benef,source_financement,type_structure_id,etapetraitement_id,passphrase,numero_aej,nom_stagiaire,prenoms_stagiaire,date_de_naissance,lieu_de_naissance,nature_piece,num_piece,niveau_etude,diplome,annee_diplome,specialite,lieu_de_stage,sexe,contact1,contact2,fonction,date_debut,date_fin,date_fin_effectif,nb_mois_effect,nb_mois_paye,nb_mois_rest,nb_jour_rest,montant_du,phase1,phase2,etat_fin_stage,retse_a_payer,date_entree,date_enregistrement,date_modification,etat_dossier,date_depot_cont,date_depot_cni,date_depot_rib,direction,service_affectation,intitule_poste_stage,prime_entreprise,nbre_mois_prev,numero_offre,intitule_offre,nombre_de_place,etablissement_frequente,type_enseignement,motif_staut,etatrenouvellement_id,valid,file_contrat,file_fiche_aej,file_cni,file_diplome,file_fiche_yup,numero_yup,file_rib,num_compte_banc,file_attestation_fin,observation,active_chef_agence,active_desse,date_chef_agence,date_desse,structure_id,agent_id,identite,motif_chef_agence,motif_desse,etat_chef_agence,etat_desse,active_dmg,motif_dmg,etat_dmg,date_dmg,handicap,type_handicap,nom_encadreur,conseiller_id,created_at,updated_at,attest_demarrage,attest_presence,file_attestation,file_certificat_frequentation,deleted_at,deleted_by,doubloncheck,avis_contrat,capitalisationfinance,autre_handicap,originestagiaire_id,personne_urgence,lien_parente_prsurgent,prsurgent_tel1,prsurgent_tel2,autre_diplome,date_demarrage_capitalisation,date_demarrage_capitalisation_sans_financiere,nbr_mois_captaliser,depot_dmg_status,numero_wave,lienparente_id,numerooffre,typeenseignement_id,handicap_id,typehandicap_id,fonction_encadreur,tel_encadreur,date_transmission_dmg,trans_attestation_grp,filecontratavenant,trans_cniattest,type_paiement_id,fiche_wave,file_wave,motifdesse_dmg,dessedmg_status,doublon_full,contact_stage_full,doublon_yup,doublon_wave,date_debut_renouv,date_fin_renouv,status_aaf,date_aaf,obs_aaf,filejustif_aaf,date_pejedec,status_pejedec,motif_pejedec,id_user_pejedec,numero_cmu
COLONNES
            ),
        );

        $normalisations = [
            'id' => ['conservations_contrats_pae', 'contrat_pae_ancien_id'],
            'id_entreprise' => ['entreprises', 'ancien_id'],
            'id_agence' => ['agences', 'ancien_id'],
            'id_type_stage' => ['types_stage', 'ancien_id'],
            'source_financement' => ['sources_financement', 'ancien_id'],
            'type_structure_id' => ['types_structure', 'ancien_id'],
            'etapetraitement_id' => ['etapes_parcours', 'ancien_id'],
            'numero_aej' => ['beneficiaires', 'numero_aej'],
            'nom_stagiaire' => ['beneficiaires', 'nom'],
            'prenoms_stagiaire' => ['beneficiaires', 'prenoms'],
            'date_de_naissance' => ['beneficiaires', 'date_naissance'],
            'lieu_de_naissance' => ['beneficiaires', 'lieu_naissance'],
            'sexe' => ['beneficiaires', 'sexe'],
            'contact1' => ['beneficiaires', 'telephone_principal'],
            'contact2' => ['beneficiaires', 'telephone_secondaire'],
            'date_debut' => ['stages', 'date_debut'],
            'date_fin' => ['stages', 'date_fin_prevue'],
            'date_fin_effectif' => ['stages', 'date_fin_effective'],
            'montant_du' => ['droits_paiement', 'montant'],
            'numero_offre' => ['offres_emploi', 'numero'],
            'intitule_offre' => ['offres_emploi', 'intitule'],
            'nombre_de_place' => ['offres_emploi', 'nombre_places'],
            'intitule_poste_stage' => ['stages', 'intitule_poste'],
            'numero_yup' => ['comptes_paiement_beneficiaires', 'numero_compte'],
            'numero_wave' => ['comptes_paiement_beneficiaires', 'numero_compte'],
            'num_compte_banc' => ['comptes_paiement_beneficiaires', 'numero_compte'],
            'type_paiement_id' => ['types_paiement', 'ancien_id'],
            'conseiller_id' => ['conseillers', 'ancien_id'],
        ];

        foreach ($colonnes as $colonne) {
            [$tableCible, $colonneCible] = $normalisations[$colonne] ?? [null, null];

            DB::table('correspondances_colonnes_contrats_pae')->updateOrInsert(
                ['nom_colonne_source' => $colonne],
                [
                    'table_cible' => $tableCible,
                    'colonne_cible' => $colonneCible,
                    'strategie_conservation' => $tableCible === null ? 'A_RECONCILIER' : 'NORMALISEE_ET_ARCHIVEE',
                    'obligatoire' => true,
                    'commentaire' => $tableCible === null
                        ? 'Conservée intégralement ; normalisation métier à valider avant bascule.'
                        : 'Normalisée dans le modèle cible et conservée dans l’archive source.',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
