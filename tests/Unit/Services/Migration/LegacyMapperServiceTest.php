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
