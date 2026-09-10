<?php

namespace Tests\Feature\Domain\Payment;

use App\Domain\Payment\Services\ApplicationPrelevementsService;
use App\Models\Internship\Stage;
use App\Models\Payment\DroitPaiement;
use App\Models\Payment\Paiement;
use App\Models\Payment\ReglePrelevement;
use App\Models\Reference\Periode;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Portage de `PaymentDeductionServiceTest` (legacy) : la prime calculée par le
 * barème est un montant brut, dont une part datée peut être prélevée (cotisation
 * CMU) selon la source de financement, le type de stage et le type de paiement.
 */
class ApplicationPrelevementsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_regle_applicable_ignores_rule_outside_effective_range(): void
    {
        $source = SourceFinancement::factory()->create();
        $periode = Periode::create(['code' => '2026-09', 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-30']);

        ReglePrelevement::query()->create([
            'nom' => 'CMU expirée',
            'type_prelevement' => ReglePrelevement::TYPE_CMU,
            'source_financement_id' => $source->id,
            'type_paiement' => ReglePrelevement::PAIEMENT_DEMARRAGE,
            'montant' => 6000,
            'effet_du' => '2025-01-01',
            'effet_au' => '2026-08-31',
            'actif' => true,
        ]);

        $regle = app(ApplicationPrelevementsService::class)->regleApplicable($source->id, null, $periode);

        $this->assertNull($regle);
    }

    public function test_regle_applicable_prefers_type_stage_specific_rule_over_generic_rule(): void
    {
        $source = SourceFinancement::factory()->create();
        $typeStage = TypeStage::factory()->create();
        $periode = Periode::create(['code' => '2026-09', 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-30']);

        ReglePrelevement::query()->create([
            'nom' => 'CMU générique',
            'type_prelevement' => ReglePrelevement::TYPE_CMU,
            'source_financement_id' => $source->id,
            'type_paiement' => ReglePrelevement::PAIEMENT_DEMARRAGE,
            'montant' => 6000,
            'effet_du' => '2026-01-01',
            'effet_au' => null,
            'actif' => true,
        ]);

        $specifique = ReglePrelevement::query()->create([
            'nom' => 'CMU stage école',
            'type_prelevement' => ReglePrelevement::TYPE_CMU,
            'source_financement_id' => $source->id,
            'type_stage_id' => $typeStage->id,
            'type_paiement' => ReglePrelevement::PAIEMENT_DEMARRAGE,
            'montant' => 3000,
            'effet_du' => '2026-01-01',
            'effet_au' => null,
            'actif' => true,
        ]);

        $regle = app(ApplicationPrelevementsService::class)->regleApplicable($source->id, $typeStage->id, $periode);

        $this->assertSame($specifique->id, $regle->id);
    }

    public function test_decomposer_caps_deduction_to_gross_amount(): void
    {
        $source = SourceFinancement::factory()->create();
        $periode = Periode::create(['code' => '2026-09', 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-30']);

        ReglePrelevement::query()->create([
            'nom' => 'CMU',
            'type_prelevement' => ReglePrelevement::TYPE_CMU,
            'source_financement_id' => $source->id,
            'type_paiement' => ReglePrelevement::PAIEMENT_DEMARRAGE,
            'montant' => 6000,
            'effet_du' => '2026-01-01',
            'effet_au' => null,
            'actif' => true,
        ]);

        $decomposition = app(ApplicationPrelevementsService::class)->decomposer(4000, $source->id, null, $periode);

        $this->assertSame(4000.0, $decomposition['brut']);
        $this->assertSame(4000.0, $decomposition['prelevement']);
        $this->assertSame(0.0, $decomposition['net']);
        $this->assertSame(ReglePrelevement::TYPE_CMU, $decomposition['type']);
    }

    public function test_decomposer_returns_full_net_when_no_rule_applies(): void
    {
        $source = SourceFinancement::factory()->create();
        $periode = Periode::create(['code' => '2026-09', 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-30']);

        $decomposition = app(ApplicationPrelevementsService::class)->decomposer(45000, $source->id, null, $periode);

        $this->assertSame(45000.0, $decomposition['brut']);
        $this->assertSame(0.0, $decomposition['prelevement']);
        $this->assertSame(45000.0, $decomposition['net']);
        $this->assertNull($decomposition['type']);
        $this->assertNull($decomposition['regle']);
    }

    public function test_appliquer_writes_snapshot_and_derives_net_montant(): void
    {
        $stage = Stage::factory()->create();
        $periode = Periode::create(['code' => '2026-09', 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-30']);
        $droit = DroitPaiement::create([
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'source_financement_id' => $stage->source_financement_id,
            'nature' => 'DEMARRAGE',
            'montant' => 60000,
            'statut' => 'OUVERT',
        ]);
        $paiement = Paiement::create([
            'droit_paiement_id' => $droit->id,
            'montant' => 60000,
            'statut' => 'A_TRAITER',
        ]);

        $regle = ReglePrelevement::query()->create([
            'nom' => 'CMU',
            'type_prelevement' => ReglePrelevement::TYPE_CMU,
            'source_financement_id' => $stage->source_financement_id,
            'type_paiement' => ReglePrelevement::PAIEMENT_DEMARRAGE,
            'montant' => 6000,
            'effet_du' => '2026-01-01',
            'effet_au' => null,
            'actif' => true,
        ]);

        app(ApplicationPrelevementsService::class)->appliquer($paiement, 60000, $regle);

        $paiement->refresh();

        $this->assertSame('60000.00', $paiement->montant_brut);
        $this->assertSame('6000.00', $paiement->montant_prelevement);
        $this->assertSame('54000.00', $paiement->montant);
        $this->assertSame(ReglePrelevement::TYPE_CMU, $paiement->type_prelevement);
        $this->assertSame($regle->id, $paiement->regle_prelevement_id);
        $this->assertTrue($paiement->a_prelevement);
    }

    public function test_appliquer_aux_paiements_en_attente_applies_matching_rule_only_to_open_payments(): void
    {
        $stage = Stage::factory()->create();
        $periode = Periode::create(['code' => '2026-09', 'date_debut' => '2026-09-01', 'date_fin' => '2026-09-30']);

        ReglePrelevement::query()->create([
            'nom' => 'CMU démarrage',
            'type_prelevement' => ReglePrelevement::TYPE_CMU,
            'source_financement_id' => $stage->source_financement_id,
            'type_paiement' => ReglePrelevement::PAIEMENT_DEMARRAGE,
            'montant' => 6000,
            'effet_du' => '2026-01-01',
            'effet_au' => null,
            'actif' => true,
        ]);

        $droitDemarrage = DroitPaiement::create([
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'source_financement_id' => $stage->source_financement_id,
            'nature' => 'DEMARRAGE',
            'montant' => 60000,
            'statut' => 'OUVERT',
        ]);
        $paiementDemarrage = Paiement::create([
            'droit_paiement_id' => $droitDemarrage->id,
            'montant' => 60000,
            'statut' => 'A_TRAITER',
        ]);

        $droitPresence = DroitPaiement::create([
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'source_financement_id' => $stage->source_financement_id,
            'nature' => 'PRESENCE',
            'montant' => 45000,
            'statut' => 'OUVERT',
        ]);
        $paiementPresence = Paiement::create([
            'droit_paiement_id' => $droitPresence->id,
            'montant' => 45000,
            'statut' => 'A_TRAITER',
        ]);

        $traites = app(ApplicationPrelevementsService::class)->appliquerAuxPaiementsEnAttente('2026-09', 'DEMARRAGE');

        $this->assertSame(1, $traites);

        $paiementDemarrage->refresh();
        $paiementPresence->refresh();

        $this->assertSame('6000.00', $paiementDemarrage->montant_prelevement);
        $this->assertSame('0.00', $paiementPresence->montant_prelevement);
    }

    public function test_type_paiement_de_nature_maps_demarrage_and_defaults_to_presence(): void
    {
        $service = app(ApplicationPrelevementsService::class);

        $this->assertSame(ReglePrelevement::PAIEMENT_DEMARRAGE, $service->typePaiementDeNature('DEMARRAGE'));
        $this->assertSame(ReglePrelevement::PAIEMENT_PRESENCE, $service->typePaiementDeNature('PRESENCE'));
        $this->assertSame(ReglePrelevement::PAIEMENT_PRESENCE, $service->typePaiementDeNature('AUTRE'));
    }
}
