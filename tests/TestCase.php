<?php

namespace Tests;

use App\Domain\Payment\Services\Prime\ContexteCalculPrime;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La correspondance id Next -> `ancien_id` est mise en cache pour tout
        // le processus : sans purge, un test hériterait des référentiels du
        // test précédent, que `RefreshDatabase` vient de supprimer.
        ContexteCalculPrime::oublierReferentiels();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
