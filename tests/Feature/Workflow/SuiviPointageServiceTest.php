<?php

namespace Tests\Feature\Workflow;

use App\Domain\Workflow\Services\SuiviPointageService;
use App\Enums\CorbeilleEnum;
use App\Enums\VisaDesseEnum;
use App\Models\Attendance\DecisionPointage;
use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Models\Internship\Stage;
use App\Models\Payment\DossierPaiement;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\OrdrePaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
use App\Models\User;
use App\Models\Workflow\DefinitionParcours;
use App\Models\Workflow\EtapeParcours;
use App\Models\Workflow\InstanceParcours;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Position d'un pointage dans le circuit complet : CIP → CA → visa DESSE → DMG →
 * CB → ordre de paiement → bordereau → AC → paiement, retours compris.
 */
class SuiviPointageServiceTest extends TestCase
{
    use RefreshDatabase;

    private SuiviPointageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Les étapes de parcours référencent un rôle responsable.
        $this->seed(RolePermissionSeeder::class);
        $this->service = app(SuiviPointageService::class);
    }

    private function periode(): Periode
    {
        return Periode::create(['code' => '2026-08', 'date_debut' => '2026-08-01', 'date_fin' => '2026-08-31']);
    }

    private function stageAvecPointage(string $statutPointage, string $corbeille): Pointage
    {
        $stage = Stage::factory()->create(['date_debut' => '2026-07-10', 'date_fin_prevue' => '2026-10-31']);
        $definition = DefinitionParcours::factory()->create();
        $etape = EtapeParcours::factory()->create(['definition_parcours_id' => $definition->id]);

        $pointage = Pointage::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stage->id,
            'periode_id' => $this->periode()->id,
            'nature' => 'PRESENCE',
            'statut' => $statutPointage,
        ]);

        VersionPointage::create([
            'pointage_id' => $pointage->id,
            'numero_version' => 1,
            'presence' => 'PRESENT',
            'jours_presents' => 30,
        ]);

        // La corbeille du mois est portée par l'instance du pointage (repli : celle du stage).
        InstanceParcours::create([
            'uuid_public' => (string) Str::uuid(),
            'definition_parcours_id' => $definition->id,
            'etape_courante_id' => $etape->id,
            'stage_id' => $stage->id,
            'pointage_id' => $pointage->id,
            'corbeille_actuelle' => $corbeille,
            'version_verrouillage' => 0,
        ]);

        return $pointage->fresh();
    }

    private function paiementPour(Pointage $pointage, string $statut = 'A_TRAITER'): Paiement
    {
        $droit = DroitPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $pointage->stage_id,
            'pointage_id' => $pointage->id,
            'periode_id' => $pointage->periode_id,
            'source_financement_id' => $pointage->stage->source_financement_id,
            'nature' => 'PRESENCE',
            'montant' => 45000,
            'statut' => 'OUVERT',
        ]);

        return Paiement::create([
            'uuid_public' => (string) Str::uuid(),
            'droit_paiement_id' => $droit->id,
            'montant' => 45000,
            'statut' => $statut,
        ]);
    }

    /**
     * @param  array<string, mixed>  $ligne
     */
    private function etat(array $ligne, string $code): string
    {
        return collect($ligne['etapes'])->firstWhere('code', $code)['etat'];
    }

    /**
     * @return array<string, mixed>
     */
    private function ligne(Pointage $pointage): array
    {
        $stage = Stage::with(SuiviPointageService::relationsStage(''))
            ->whereKey($pointage->stage_id)
            ->firstOrFail();

        return $this->service->pourStage($stage)[0];
    }

    public function test_un_pointage_soumis_attend_le_chef_dagence_et_na_pas_de_paiement(): void
    {
        $pointage = $this->stageAvecPointage('SOUMIS', CorbeilleEnum::CA_VALIDATION_POINTAGES->value);

        $ligne = $this->ligne($pointage);

        $this->assertSame('2026-08', $ligne['mois']);
        $this->assertSame(30, $ligne['position']);
        $this->assertSame("Chef d'Agence : Validation du pointage", $ligne['corbeille']['label']);
        $this->assertSame(SuiviPointageService::ETAT_TERMINEE, $this->etat($ligne, 'pointage_cip'));
        $this->assertSame(SuiviPointageService::ETAT_EN_COURS, $this->etat($ligne, 'validation_ca'));
        $this->assertSame(SuiviPointageService::ETAT_A_VENIR, $this->etat($ligne, 'attente_dmg'));
        $this->assertSame('SANS_PAIEMENT', $ligne['etat_paiement']['code']);
        $this->assertFalse($ligne['etat_paiement']['paye']);
    }

    public function test_le_visa_desse_est_sans_objet_tant_que_le_stage_nest_pas_soumis(): void
    {
        $pointage = $this->stageAvecPointage('SOUMIS', CorbeilleEnum::CA_VALIDATION_POINTAGES->value);

        $this->assertSame(SuiviPointageService::ETAT_SANS_OBJET, $this->etat($this->ligne($pointage), 'visa_desse'));

        $pointage->stage->update(['visa_desse' => VisaDesseEnum::REJETE->value, 'motif_visa_desse' => 'Pièce manquante']);

        $ligne = $this->ligne($pointage->fresh());
        $this->assertSame(SuiviPointageService::ETAT_AJOURNEE, $this->etat($ligne, 'visa_desse'));
        $this->assertSame('Pièce manquante', $ligne['visa_desse']['motif']);
    }

    public function test_un_paiement_en_dossier_transmis_positionne_le_dossier_chez_le_cb(): void
    {
        $pointage = $this->stageAvecPointage('VALIDE', CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE->value);
        $paiement = $this->paiementPour($pointage, 'EN_DOSSIER');

        $dossier = DossierPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'periode_id' => $pointage->periode_id,
            'source_financement_id' => $pointage->stage->source_financement_id,
            'numero' => 'PS082026-1',
            'nature' => 'PS',
            'statut' => 'TRANSMIS_CB',
            'montant_total' => 45000,
        ]);
        $dossier->paiements()->attach($paiement->id, ['montant' => 45000, 'ajoute_le' => now()]);

        $ligne = $this->ligne($pointage);

        $this->assertSame('PS082026-1', $ligne['dossier']['numero']);
        $this->assertSame(SuiviPointageService::ETAT_TERMINEE, $this->etat($ligne, 'attente_dmg'));
        $this->assertSame(SuiviPointageService::ETAT_TERMINEE, $this->etat($ligne, 'dossier_dmg'));
        $this->assertSame(SuiviPointageService::ETAT_EN_COURS, $this->etat($ligne, 'traitement_cb'));
        $this->assertSame(SuiviPointageService::ETAT_A_VENIR, $this->etat($ligne, 'ordre_paiement'));
        $this->assertSame('EN_COURS', $ligne['etat_paiement']['code']);
    }

    public function test_une_ligne_retiree_du_dossier_ramene_le_mois_en_attente_dmg(): void
    {
        // Ajournement CB : la ligne est retirée du dossier, le paiement retourne en A_TRAITER.
        $pointage = $this->stageAvecPointage('VALIDE', CorbeilleEnum::DMG_ATTENTE_PAIEMENT_PRESENCE->value);
        $paiement = $this->paiementPour($pointage, 'A_TRAITER');

        $dossier = DossierPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'periode_id' => $pointage->periode_id,
            'source_financement_id' => $pointage->stage->source_financement_id,
            'numero' => 'PS082026-2',
            'nature' => 'PS',
            'statut' => 'AJOURNE_CB',
            'montant_total' => 45000,
        ]);
        $dossier->paiements()->attach($paiement->id, [
            'montant' => 45000,
            'ajoute_le' => now(),
            'retire_le' => now(),
            'motif_retrait' => 'Dossier physique incomplet',
        ]);

        $ligne = $this->ligne($pointage);

        $this->assertNull($ligne['dossier']);
        $this->assertSame(SuiviPointageService::ETAT_EN_COURS, $this->etat($ligne, 'attente_dmg'));
        $this->assertSame(SuiviPointageService::ETAT_A_VENIR, $this->etat($ligne, 'traitement_cb'));
    }

    public function test_un_ajournement_dmg_est_restitue_avec_son_motif(): void
    {
        $pointage = $this->stageAvecPointage('AJOURNE_DMG', CorbeilleEnum::CIP_POINTAGE_AJOURNE_DMG->value);
        $paiement = $this->paiementPour($pointage, 'AJOURNE_DMG');

        $auteur = User::factory()->create(['nom' => 'Agent DMG']);
        $paiement->decisions()->create([
            'auteur_id' => $auteur->id,
            'decision' => 'AJOURNEMENT_DMG',
            'motif' => 'Attestation de présence non conforme',
            'decide_le' => now(),
        ]);

        $ligne = $this->ligne($pointage);

        $this->assertSame(SuiviPointageService::ETAT_AJOURNEE, $this->etat($ligne, 'pointage_cip'));
        $this->assertSame(SuiviPointageService::ETAT_AJOURNEE, $this->etat($ligne, 'attente_dmg'));
        $this->assertSame('AJOURNE', $ligne['etat_paiement']['code']);
        $this->assertSame('Attestation de présence non conforme', $ligne['dernier_retour']['motif']);
        $this->assertSame('Agent DMG', $ligne['dernier_retour']['auteur']);
    }

    public function test_le_retour_le_plus_recent_prime_entre_pointage_et_paiement(): void
    {
        $pointage = $this->stageAvecPointage('AJOURNE_CA', CorbeilleEnum::CIP_AJOURNE_CA->value);
        $version = VersionPointage::where('pointage_id', $pointage->id)->firstOrFail();
        $auteur = User::factory()->create(['nom' => "Chef d'Agence"]);

        DecisionPointage::create([
            'pointage_id' => $pointage->id,
            'version_pointage_id' => $version->id,
            'auteur_id' => $auteur->id,
            'decision' => 'AJOURNEMENT_CA',
            'motif' => 'Jours de présence incohérents',
            'decide_le' => now(),
        ]);

        $ligne = $this->ligne($pointage);

        $this->assertSame('POINTAGE', $ligne['dernier_retour']['origine']);
        $this->assertSame('Jours de présence incohérents', $ligne['dernier_retour']['motif']);
        $this->assertSame(SuiviPointageService::ETAT_AJOURNEE, $this->etat($ligne, 'validation_ca'));
    }

    public function test_un_paiement_paye_termine_le_circuit(): void
    {
        $pointage = $this->stageAvecPointage('VALIDE', CorbeilleEnum::AC_BORDEREAU_OP_ATTENTE->value);
        $paiement = $this->paiementPour($pointage, 'PAYE');

        $bordereau = \App\Models\Payment\BordereauPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'numero' => 'BRD-2026-08-1',
            'periode_id' => $pointage->periode_id,
            'source_financement_id' => $pointage->stage->source_financement_id,
            'montant_total' => 45000,
            'statut' => 'VISE_AC',
        ]);
        $ordre = OrdrePaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'numero' => 'OP-2026-08-1',
            'periode_id' => $pointage->periode_id,
            'source_financement_id' => $pointage->stage->source_financement_id,
            'bordereau_paiement_id' => $bordereau->id,
            'montant_total' => 45000,
            'statut' => 'VISE_AC',
        ]);
        $dossier = DossierPaiement::create([
            'uuid_public' => (string) Str::uuid(),
            'periode_id' => $pointage->periode_id,
            'source_financement_id' => $pointage->stage->source_financement_id,
            'ordre_paiement_id' => $ordre->id,
            'numero' => 'PS082026-3',
            'nature' => 'PS',
            'statut' => 'VISE_AC',
            'montant_total' => 45000,
        ]);
        $dossier->paiements()->attach($paiement->id, ['montant' => 45000, 'ajoute_le' => now()]);

        $ligne = $this->ligne($pointage);

        $this->assertSame('OP-2026-08-1', $ligne['ordre_paiement']['numero']);
        $this->assertSame('BRD-2026-08-1', $ligne['bordereau']['numero']);
        $this->assertSame(SuiviPointageService::ETAT_TERMINEE, $this->etat($ligne, 'visa_ac'));
        $this->assertSame(SuiviPointageService::ETAT_TERMINEE, $this->etat($ligne, 'paiement'));
        $this->assertTrue($ligne['etat_paiement']['paye']);
    }
}
