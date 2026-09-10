<?php

namespace Tests\Feature\Domain\Attendance;

use App\Domain\Attendance\Services\PointageService;
use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Payment\PrimeConfigurationOverride;
use App\Models\Payment\ReglePrelevement;
use App\Models\Reference\Periode;
use App\Models\Reference\SituationStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dmg_deferred_count_excludes_payments_only_waiting_for_dmg(): void
    {
        $periode = Periode::create([
            'code' => '2026-08',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-31',
        ]);

        $this->createPaymentForPeriod($periode, 'AJOURNE_DMG');
        $this->createPaymentForPeriod($periode, 'A_TRAITER');

        $counts = app(PointageService::class)->getCountsByTab($periode->id, [
            'mois' => '2026-08',
        ]);

        $this->assertSame(1, $counts['ajourne_dmg']);
    }

    public function test_ca_deferred_count_excludes_trainees_who_left_the_program(): void
    {
        $periode = Periode::create([
            'code' => '2026-08',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-31',
        ]);

        // Legacy `getPointageAjournerByChefAgence()` filtre `id_situation_stage = 1` : seul le
        // stagiaire encore dans le dispositif a un pointage que le CIP peut corriger.
        $this->createDeferredByCaPointage($periode, SituationStage::CODE_EN_COURS);
        $this->createDeferredByCaPointage($periode, 'SS-002');

        $counts = app(PointageService::class)->getCountsByTab($periode->id, [
            'mois' => '2026-08',
        ]);

        $this->assertSame(1, $counts['ajourne_ca']);
    }

    /**
     * Portage de la trajectoire brute / prélèvement / net legacy : la validation
     * mensuelle du pointage doit poser l'instantané CMU sur le paiement de
     * présence généré, à l'identique du démarrage.
     */
    public function test_validating_mensuel_pointage_applies_the_cmu_deduction_rule_to_the_generated_payment(): void
    {
        PrimeConfigurationOverride::query()->create([
            'id' => 1,
            'configuration' => ['smig' => ['default' => 45000]],
        ]);

        $stage = Stage::factory()->create([
            'date_debut' => '2026-04-01',
            'date_fin_prevue' => '2026-09-30',
        ]);

        ReglePrelevement::query()->create([
            'nom' => 'CMU présence',
            'type_prelevement' => ReglePrelevement::TYPE_CMU,
            'source_financement_id' => $stage->source_financement_id,
            'type_paiement' => ReglePrelevement::PAIEMENT_PRESENCE,
            'montant' => 6000,
            'effet_du' => '2026-01-01',
            'effet_au' => null,
            'actif' => true,
        ]);

        $periode = Periode::create([
            'code' => '2026-08',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-31',
        ]);

        $pointage = Pointage::create([
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'nature' => 'MENSUEL',
            'statut' => 'SOUMIS',
        ]);
        VersionPointage::create([
            'pointage_id' => $pointage->id,
            'numero_version' => 1,
            'presence' => 'COMPLETE',
            'jours_presents' => 22,
        ]);

        $ca = User::factory()->create();

        $droitPaiement = app(PointageService::class)->validerMensuel($pointage->fresh(), $ca);

        $paiement = Paiement::where('droit_paiement_id', $droitPaiement->id)->firstOrFail();

        $this->assertSame('45000.00', $paiement->montant_brut);
        $this->assertSame('6000.00', $paiement->montant_prelevement);
        $this->assertSame('39000.00', $paiement->montant);
        $this->assertSame(ReglePrelevement::TYPE_CMU, $paiement->type_prelevement);
    }

    private function createDeferredByCaPointage(Periode $periode, string $situationStage): Pointage
    {
        $stage = Stage::factory()->create([
            'date_debut' => '2026-04-01',
            'date_fin_prevue' => '2026-09-30',
            'situation_stage' => $situationStage,
        ]);

        return Pointage::create([
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'nature' => 'MENSUEL',
            'statut' => 'AJOURNE_CA',
        ]);
    }

    private function createPaymentForPeriod(Periode $periode, string $statut): Paiement
    {
        $stage = Stage::factory()->create([
            'date_debut' => '2026-04-01',
            'date_fin_prevue' => '2026-09-30',
        ]);
        $pointage = Pointage::create([
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'nature' => 'MENSUEL',
            'statut' => 'VALIDE',
        ]);
        $droit = DroitPaiement::create([
            'stage_id' => $stage->id,
            'pointage_id' => $pointage->id,
            'periode_id' => $periode->id,
            'source_financement_id' => $stage->source_financement_id,
            'nature' => 'PRESENCE',
            'montant' => 45000,
            'statut' => 'OUVERT',
        ]);

        return Paiement::create([
            'droit_paiement_id' => $droit->id,
            'montant' => 45000,
            'statut' => $statut,
        ]);
    }
}
