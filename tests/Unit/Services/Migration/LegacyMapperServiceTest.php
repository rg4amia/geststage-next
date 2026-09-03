<?php

namespace Tests\Unit\Services\Migration;

use App\Enums\CorbeilleEnum;
use App\Services\Migration\LegacyMapperService;
use Carbon\Carbon;
use Tests\TestCase;

class LegacyMapperServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * L'étape 7 legacy (doublon avéré traité par le CA, en attente de la validation finale
     * de la DESSE — vue « Stagiaires doublon retourné / Agence ») doit alimenter la corbeille
     * CIP_AJOURNE_DESSE et non CIP_MES_STAGIAIRES : c'est elle qui peuple l'onglet DESSE
     * « Retour Chef d'Agence » et le suivi CIP « Doublon DESSE ». Sinon les dossiers du
     * legacy restent invisibles pour la DESSE après migration.
     */
    public function test_etape_seven_doublon_retour_chef_agence_goes_to_cip_ajourne_desse(): void
    {
        $mapper = new LegacyMapperService;

        $this->assertSame(
            CorbeilleEnum::CIP_AJOURNE_DESSE,
            $mapper->mapStatutStageToCorbeille(7)
        );

        // Via le contexte Chef d'Agence : que le CA ait statué ou non, l'étape 7
        // retombe sur le mapping ci-dessus (pas d'éligibilité CA hors étapes 1/4).
        foreach ([0, 1, 2] as $etatChefAgence) {
            $this->assertSame(
                CorbeilleEnum::CIP_AJOURNE_DESSE,
                $mapper->mapChefAgenceCorbeille((object) [
                    'etapetraitement_id' => 7,
                    'etat_chef_agence' => $etatChefAgence,
                    'date_debut' => '2026-08-10',
                    'agent_id' => 3,
                    'avis_contrat' => 1,
                    'file_contrat' => 'contrat.pdf',
                ])
            );
        }
    }

    /**
     * L'étape 8 legacy (doublon validé par la DESSE après retour du Chef d'Agence) est un
     * état « clos » — jamais un file actionnable : le dossier doit rejoindre la corbeille
     * DAICG_VALIDES_DESSE (là où aboutit l'action « Renvoyer / Valider » de l'onglet DESSE
     * « Retour Chef d'Agence »), et non DESSE_SUIVI_PROCESSUS qui n'a ni lecteur UI ni
     * transition de sortie. Ainsi l'onglet « Retour Chef d'Agence » ne liste que des
     * dossiers réellement en attente de validation.
     */
    public function test_etape_eight_doublon_valide_apres_retour_ca_goes_to_daicg_valides_desse(): void
    {
        $mapper = new LegacyMapperService;

        $this->assertSame(
            CorbeilleEnum::DAICG_VALIDES_DESSE,
            $mapper->mapStatutStageToCorbeille(8)
        );

        // Via le contexte Chef d'Agence : l'étape 8 n'est jamais éligible à la corbeille
        // de validation du CA (étapes 1/4 uniquement) et retombe sur le mapping ci-dessus.
        $this->assertSame(
            CorbeilleEnum::DAICG_VALIDES_DESSE,
            $mapper->mapChefAgenceCorbeille((object) [
                'etapetraitement_id' => 8,
                'etat_chef_agence' => 2,
                'date_debut' => '2026-08-10',
                'agent_id' => 3,
                'avis_contrat' => 1,
                'file_contrat' => 'contrat.pdf',
            ])
        );
    }

    /**
     * Un dossier ajourné puis re-soumis par le CIP garde la `date_chef_agence` de la passe
     * précédente alors que `etat_chef_agence` est remis à 0. WaitCheckedChefAgenceService ne
     * lisant jamais cette date, le dossier revient dans la corbeille de validation du CA.
     */
    public function test_chef_agence_queue_ignores_a_leftover_validation_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService;
        $legacyContrat = (object) [
            'etapetraitement_id' => 1,
            'etat_chef_agence' => 0,
            'date_chef_agence' => '2026-08-05',
            'date_debut' => '2026-08-10',
            'agent_id' => 3,
            'avis_contrat' => 1,
            'file_contrat' => 'contrat.pdf',
        ];

        $this->assertSame(
            CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE,
            $mapper->mapChefAgenceCorbeille($legacyContrat)
        );
    }

    /**
     * Hors éligibilité CA, la date de passage du Chef d'Agence ne doit pas déplacer le dossier :
     * la corbeille « ajournées » du CA est strictement `etapetraitement_id=2 AND etat_chef_agence=1`.
     */
    public function test_a_validation_date_alone_does_not_fill_the_adjournment_queue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService;

        $this->assertSame(
            CorbeilleEnum::CIP_MES_STAGIAIRES,
            $mapper->mapChefAgenceCorbeille((object) [
                'etapetraitement_id' => 1,
                'etat_chef_agence' => 0,
                'date_chef_agence' => '2026-08-05',
                'date_debut' => '2026-08-10',
                'agent_id' => 3,
                'avis_contrat' => 0,
                'file_contrat' => null,
            ])
        );

        $this->assertSame(
            CorbeilleEnum::CIP_MES_STAGIAIRES,
            $mapper->mapChefAgenceCorbeille((object) [
                'etapetraitement_id' => 2,
                'etat_chef_agence' => null,
                'date_chef_agence' => '2026-08-05',
                'date_debut' => '2026-08-10',
            ])
        );
    }

    /**
     * `status_dmg` ne concerne que l'étape de paiement : un pointage validé par le CIP puis
     * par le CA est VALIDE même si le DMG n'a pas encore payé. Sinon les 68 651 pointages
     * en attente de paiement retombent dans la corbeille de validation du Chef d'Agence,
     * qui ne liste que les pointages `SOUMIS`.
     */
    public function test_pointage_validated_by_cip_and_chef_agence_is_valide_before_dmg(): void
    {
        $mapper = new LegacyMapperService;

        $this->assertSame('VALIDE', $mapper->mapStatutPointage((object) [
            'status_cip' => 1, 'status_ca' => 1, 'status_dmg' => 0,
        ]));
        $this->assertSame('VALIDE', $mapper->mapStatutPointage((object) [
            'status_cip' => 1, 'status_ca' => 1, 'status_dmg' => 1,
        ]));
    }

    public function test_pointage_awaiting_chef_agence_stays_soumis(): void
    {
        $mapper = new LegacyMapperService;

        $this->assertSame('SOUMIS', $mapper->mapStatutPointage((object) [
            'status_cip' => 1, 'status_ca' => 0, 'status_dmg' => 0,
        ]));
    }

    public function test_pointage_adjournments_win_over_validation(): void
    {
        $mapper = new LegacyMapperService;

        $this->assertSame('AJOURNE_CA', $mapper->mapStatutPointage((object) [
            'status_cip' => 1, 'status_ca' => 2, 'status_dmg' => 0,
        ]));
        $this->assertSame('AJOURNE_DMG', $mapper->mapStatutPointage((object) [
            'status_cip' => 1, 'status_ca' => 0, 'status_dmg' => 2,
        ]));
    }

    public function test_normalize_legacy_date_returns_null_for_zero_dates(): void
    {
        $mapper = new LegacyMapperService;

        $this->assertNull($mapper->normalizeLegacyDate('0000-00-00 00:00:00'));
        $this->assertNull($mapper->normalizeLegacyDate('0000-00-00'));
    }

    public function test_normalize_legacy_date_range_clamps_end_before_start(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService;

        [$start, $end] = $mapper->normalizeLegacyDateRange('2024-03-01', '2024-02-29');

        $this->assertSame('2024-03-01', $start->format('Y-m-d'));
        $this->assertSame('2024-03-01', $end->format('Y-m-d'));
    }

    public function test_normalize_legacy_date_range_uses_default_duration_when_end_is_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService;

        [$start, $end] = $mapper->normalizeLegacyDateRange('2024-03-01', null);

        $this->assertSame('2024-03-01', $start->format('Y-m-d'));
        $this->assertSame('2024-09-01', $end->format('Y-m-d'));
    }

    public function test_map_type_user_to_role_uses_app_role_slugs(): void
    {
        $mapper = new LegacyMapperService;

        $this->assertSame('administrateur', $mapper->mapTypeUserToRole(1));
        $this->assertSame('agent_comptable', $mapper->mapTypeUserToRole(2));
        $this->assertSame('chef_agence', $mapper->mapTypeUserToRole(3));
        $this->assertSame('cip', $mapper->mapTypeUserToRole(4));
    }

    public function test_map_chef_agence_corbeille_ignores_zero_validation_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService;
        $legacyContrat = (object) [
            'etat_chef_agence' => 0,
            'date_chef_agence' => '0000-00-00 00:00:00',
            'date_debut' => '2026-08-10',
            'agent_id' => 3,
            'avis_contrat' => 1,
            'file_contrat' => 'contrat.pdf',
            'etapetraitement_id' => 1,
        ];

        $this->assertSame(
            CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE,
            $mapper->mapChefAgenceCorbeille($legacyContrat)
        );
    }

    public function test_map_chef_agence_corbeille_returns_demarrage_or_omis_based_on_stage_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService;
        $demarrage = (object) [
            'etat_chef_agence' => 0,
            'date_chef_agence' => null,
            'date_debut' => '2026-08-10',
            'agent_id' => 3,
            'avis_contrat' => 1,
            'file_contrat' => 'contrat.pdf',
            'etapetraitement_id' => 1,
        ];
        $omis = (object) [
            'etat_chef_agence' => 0,
            'date_chef_agence' => null,
            'date_debut' => '2026-07-10',
            'agent_id' => 3,
            'avis_contrat' => 1,
            'file_contrat' => 'contrat.pdf',
            'etapetraitement_id' => 1,
        ];

        $this->assertSame(
            CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE,
            $mapper->mapChefAgenceCorbeille($demarrage)
        );
        $this->assertSame(
            CorbeilleEnum::CA_ATTENTE_VALIDATION_OMIS,
            $mapper->mapChefAgenceCorbeille($omis)
        );
    }

    public function test_resolve_legacy_period_prefers_business_month_over_created_at(): void
    {
        $mapper = new LegacyMapperService;

        $date = $mapper->resolveLegacyPeriodDate((object) [
            'mois' => '2026-08',
            'created_at' => '2026-09-04 12:00:00',
        ]);

        $this->assertSame('2026-08-01', $date?->format('Y-m-d'));
    }

    public function test_nature_is_demarrage_only_for_the_stage_start_month(): void
    {
        $mapper = new LegacyMapperService;

        $this->assertSame('DEMARRAGE', $mapper->naturePaiementPourPeriode('2026-08-14', '2026-08'));
        $this->assertSame('PRESENCE', $mapper->naturePaiementPourPeriode('2026-08-14', '2026-09'));
        $this->assertSame('PRESENCE', $mapper->naturePaiementPourPeriode(null, '2026-08'));
    }

    public function test_validated_pointage_fallback_uses_its_nature_for_the_dmg_queue(): void
    {
        $mapper = new LegacyMapperService;

        $this->assertSame(
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
            $mapper->mapPointageToCorbeille(null, 'VALIDE', 'DEMARRAGE')
        );
        $this->assertSame(
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE,
            $mapper->mapPointageToCorbeille(null, 'VALIDE', 'PRESENCE')
        );
    }

    /**
     * Un paiement rejeté par l'AC repart au DMG (étape 29, `TraitementAjournementStagiaireRejetByAcJob`)
     * sans que l'étape du pointage ne soit touchée : `PaiementDmgService::attentePaiementValidation()`
     * ne teste que la signature du paiement, jamais l'étape. La contraindre à 20/21 laissait ces
     * dossiers hors file DMG côté cible alors que l'ancien Gestage les affichait toujours.
     */
    public function test_dmg_signature_reclassifies_regardless_of_the_pointage_stage(): void
    {
        $mapper = new LegacyMapperService;

        $this->assertSame(
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE,
            $mapper->mapPointageToCorbeille(29, 'VALIDE', 'PRESENCE', null, true)
        );
        $this->assertSame(
            CorbeilleEnum::DMG_ATTENTE_PAIEMENT_DEMARRAGE,
            $mapper->mapPointageToCorbeille(19, 'VALIDE', 'DEMARRAGE', null, true)
        );
    }

    /**
     * L'étape legacy 2 s'intitule « Chef Agence : validation des informations », mais
     * WaitCheckedChefAgenceService ne remonte que les étapes 1 et 4 dans la corbeille du
     * CA. Un dossier resté à l'étape 2 avec etat_chef_agence=2 a donc déjà été validé :
     * le laisser en CA_ATTENTE_VALIDATION_* gonflerait la corbeille du CA de milliers de
     * dossiers absents du legacy.
     */
    public function test_stage_validated_by_chef_agence_leaves_the_validation_queue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService;

        $this->assertSame(
            CorbeilleEnum::EN_STAGE,
            $mapper->mapChefAgenceCorbeille((object) [
                'etapetraitement_id' => 2,
                'etat_chef_agence' => 2,
                'date_chef_agence' => '2026-08-05',
                'date_debut' => '2026-08-10',
                'agent_id' => 3,
                'avis_contrat' => 1,
                'file_contrat' => 'contrat.pdf',
            ])
        );
    }

    public function test_stage_adjourned_by_chef_agence_goes_to_the_adjournment_queue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService;

        $this->assertSame(
            CorbeilleEnum::CA_RETOUR_AJOURNEMENT,
            $mapper->mapChefAgenceCorbeille((object) [
                'etapetraitement_id' => 2,
                'etat_chef_agence' => 1,
                'date_chef_agence' => '2026-08-05',
                'date_debut' => '2026-08-10',
            ])
        );
    }

    public function test_incomplete_stage_pending_chef_agence_stays_with_the_cip(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService;

        $this->assertSame(
            CorbeilleEnum::CIP_MES_STAGIAIRES,
            $mapper->mapChefAgenceCorbeille((object) [
                'etapetraitement_id' => 2,
                'etat_chef_agence' => 0,
                'date_chef_agence' => null,
                'date_debut' => '2026-08-10',
                'agent_id' => 3,
                'avis_contrat' => 0,
                'file_contrat' => null,
            ])
        );
    }
}
