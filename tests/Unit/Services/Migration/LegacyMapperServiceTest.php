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

    public function test_map_chef_agence_corbeille_returns_retour_ajournement_when_validation_date_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService();
        $legacyContrat = (object) [
            'etat_chef_agence' => 0,
            'date_chef_agence' => '2026-08-05',
            'date_debut' => '2026-08-10',
        ];

        $this->assertSame(
            CorbeilleEnum::CA_RETOUR_AJOURNEMENT,
            $mapper->mapChefAgenceCorbeille($legacyContrat)
        );
    }

    public function test_normalize_legacy_date_returns_null_for_zero_dates(): void
    {
        $mapper = new LegacyMapperService();

        $this->assertNull($mapper->normalizeLegacyDate('0000-00-00 00:00:00'));
        $this->assertNull($mapper->normalizeLegacyDate('0000-00-00'));
    }

    public function test_normalize_legacy_date_range_clamps_end_before_start(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService();

        [$start, $end] = $mapper->normalizeLegacyDateRange('2024-03-01', '2024-02-29');

        $this->assertSame('2024-03-01', $start->format('Y-m-d'));
        $this->assertSame('2024-03-01', $end->format('Y-m-d'));
    }

    public function test_normalize_legacy_date_range_uses_default_duration_when_end_is_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService();

        [$start, $end] = $mapper->normalizeLegacyDateRange('2024-03-01', null);

        $this->assertSame('2024-03-01', $start->format('Y-m-d'));
        $this->assertSame('2024-09-01', $end->format('Y-m-d'));
    }

    public function test_map_type_user_to_role_uses_app_role_slugs(): void
    {
        $mapper = new LegacyMapperService();

        $this->assertSame('administrateur', $mapper->mapTypeUserToRole(1));
        $this->assertSame('agent_comptable', $mapper->mapTypeUserToRole(2));
        $this->assertSame('chef_agence', $mapper->mapTypeUserToRole(3));
        $this->assertSame('cip', $mapper->mapTypeUserToRole(4));
    }

    public function test_map_chef_agence_corbeille_ignores_zero_validation_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService();
        $legacyContrat = (object) [
            'etat_chef_agence' => 0,
            'date_chef_agence' => '0000-00-00 00:00:00',
            'date_debut' => '2026-08-10',
        ];

        $this->assertSame(
            CorbeilleEnum::CA_ATTENTE_VALIDATION_DEMARRAGE,
            $mapper->mapChefAgenceCorbeille($legacyContrat)
        );
    }

    public function test_map_chef_agence_corbeille_returns_demarrage_or_omis_based_on_stage_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-16'));

        $mapper = new LegacyMapperService();
        $demarrage = (object) [
            'etat_chef_agence' => 0,
            'date_chef_agence' => null,
            'date_debut' => '2026-08-10',
        ];
        $omis = (object) [
            'etat_chef_agence' => 0,
            'date_chef_agence' => null,
            'date_debut' => '2026-07-10',
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
}
