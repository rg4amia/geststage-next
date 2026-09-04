<?php

namespace Tests\Feature\Cip;

use App\Domain\Attendance\Services\SituationStageService;
use App\Models\Attendance\Pointage;
use App\Models\Internship\Stage;
use App\Models\Reference\Periode;
use App\Models\Reference\SituationStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Corbeilles CIP « Situation du stagiaire » (abandon / suspension) et réactivation
 * d'une suspension, portage de `PointageSituationStageService` (legacy).
 */
class SituationStagiaireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            [SituationStage::CODE_EN_COURS, 'EN COURS'],
            [SituationStage::CODE_ABANDON, 'ABANDON'],
            [SituationStage::CODE_SUSPENSION, 'SUSPENSION'],
        ] as [$code, $nom]) {
            DB::table('situations_stage')->insert([
                'code' => $code,
                'nom' => $nom,
                'actif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_les_corbeilles_separent_abandons_et_suspensions(): void
    {
        $abandon = Stage::factory()->create(['situation_stage' => SituationStage::CODE_ABANDON]);
        $suspension = Stage::factory()->create(['situation_stage' => SituationStage::CODE_SUSPENSION]);
        Stage::factory()->create(['situation_stage' => SituationStage::CODE_EN_COURS]);

        $this->actingAs(User::factory()->create());

        $service = app(SituationStageService::class);

        $this->assertSame([$abandon->id], $service->abandonsQuery()->pluck('id')->all());
        $this->assertSame([$suspension->id], $service->suspensionsQuery()->pluck('id')->all());
        $this->assertSame(['abandon' => 1, 'suspension' => 1], $service->compteurs());
    }

    public function test_la_reactivation_repousse_la_date_de_fin_des_mois_suspendus(): void
    {
        $stage = Stage::factory()->create([
            'situation_stage' => SituationStage::CODE_SUSPENSION,
            'date_fin_prevue' => '2026-06-30',
        ]);

        $situationSuspension = SituationStage::where('code', SituationStage::CODE_SUSPENSION)->first();

        // Deux mois effectivement suspendus : la date de fin doit être repoussée d'autant.
        foreach (['2026-01', '2026-02'] as $mois) {
            $periode = Periode::create([
                'code' => "P-{$mois}",
                'date_debut' => "{$mois}-01",
                'date_fin' => "{$mois}-28",
            ]);

            Pointage::create([
                'uuid_public' => (string) Str::uuid(),
                'stage_id' => $stage->id,
                'periode_id' => $periode->id,
                'nature' => 'PRESENCE',
                'statut' => 'VALIDE',
                'situation_stage_id' => $situationSuspension->id,
            ]);
        }

        $user = User::factory()->create();
        $this->actingAs($user);

        $resultat = app(SituationStageService::class)->reactiverSuspension($stage->fresh(), $user->id);

        $this->assertSame(2, $resultat['mois_suspendus']);
        $this->assertSame('2026-08-29', $resultat['nouvelle_date_fin']);

        $stage->refresh();
        $this->assertSame(SituationStage::CODE_EN_COURS, $stage->situation_stage);
        $this->assertSame('2026-08-29', $stage->date_fin_prevue->format('Y-m-d'));

        $this->assertDatabaseHas('situations_stages', [
            'stage_id' => $stage->id,
            'auteur_id' => $user->id,
            'termine_le' => null,
        ]);
    }

    public function test_reactiver_un_stage_non_suspendu_est_refuse(): void
    {
        $stage = Stage::factory()->create(['situation_stage' => SituationStage::CODE_ABANDON]);

        $this->actingAs(User::factory()->create())
            ->post("/cip/situation-stagiaire/{$stage->id}/reactiver")
            ->assertSessionHas('error');

        $this->assertSame(SituationStage::CODE_ABANDON, $stage->fresh()->situation_stage);
    }

    public function test_le_cip_ne_voit_que_les_stages_de_son_perimetre_d_agence(): void
    {
        $mien = Stage::factory()->create(['situation_stage' => SituationStage::CODE_SUSPENSION]);
        Stage::factory()->create(['situation_stage' => SituationStage::CODE_SUSPENSION]);

        $user = User::factory()->create();
        $user->perimetresAgences()->attach($mien->agence_id);

        $this->actingAs($user);

        $this->assertSame(
            [$mien->id],
            app(SituationStageService::class)->suspensionsQuery()->pluck('id')->all()
        );
    }

    public function test_sans_perimetre_defini_toutes_les_agences_restent_visibles(): void
    {
        Stage::factory()->count(2)->create(['situation_stage' => SituationStage::CODE_SUSPENSION]);

        $this->actingAs(User::factory()->create());

        $this->assertCount(2, app(SituationStageService::class)->suspensionsQuery()->get());
    }
}
