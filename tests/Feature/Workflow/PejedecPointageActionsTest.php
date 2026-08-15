<?php

namespace Tests\Feature\Workflow;

use App\Models\Attendance\Pointage;
use App\Models\Attendance\VersionPointage;
use App\Models\Internship\Stage;
use App\Models\Reference\Periode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PejedecPointageActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_correction_dmg_pejedec_passe_le_pointage_en_corrige_cip(): void
    {
        $user = User::factory()->create();
        $stage = Stage::factory()->create();
        $periode = Periode::create([
            'code' => '2026-08',
            'date_debut' => '2026-08-01',
            'date_fin' => '2026-08-31',
            'ouverte_pointage' => true,
            'ouverte_paiement' => false,
        ]);

        $pointage = Pointage::create([
            'uuid_public' => (string) Str::uuid(),
            'stage_id' => $stage->id,
            'periode_id' => $periode->id,
            'nature' => 'MENSUEL',
            'statut' => 'AJOURNE_DMG',
            'version_courante' => 1,
            'version_verrouillage' => 0,
        ]);

        VersionPointage::create([
            'pointage_id' => $pointage->id,
            'saisi_par_id' => $user->id,
            'numero_version' => 1,
            'presence' => 'PRESENT',
            'jours_presents' => 12,
            'jours_absents' => 8,
            'observation' => 'Correction requise',
        ]);

        $this->actingAs($user)
            ->post("/cip/pointages/corriger-ajournement-dmg/{$pointage->id}", [
                'motif' => 'Données corrigées',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('pointages', [
            'id' => $pointage->id,
            'statut' => 'CORRIGE_CIP',
        ]);

        $this->assertDatabaseHas('decisions_pointages', [
            'pointage_id' => $pointage->id,
            'decision' => 'CORRIGE_CIP',
        ]);
    }
}
