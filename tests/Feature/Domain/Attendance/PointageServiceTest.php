<?php

namespace Tests\Feature\Domain\Attendance;

use App\Domain\Attendance\Services\PointageService;
use App\Models\Attendance\Pointage;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
use App\Models\Reference\SituationStage;
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
