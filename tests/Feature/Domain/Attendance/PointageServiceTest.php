<?php

namespace Tests\Feature\Domain\Attendance;

use App\Domain\Attendance\Services\PointageService;
use App\Models\Attendance\Pointage;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Reference\Periode;
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
