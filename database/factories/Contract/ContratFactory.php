<?php

namespace Database\Factories\Contract;

use App\Models\Contract\Contrat;
use App\Models\Internship\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contrat>
 */
class ContratFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stage_id' => Stage::factory(),
            'numero' => $this->faker->unique()->numerify('CTR-######'),
            'date_debut' => clone $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'date_fin' => clone $this->faker->dateTimeBetween('+2 months', '+6 months'),
            'prime_mensuelle' => 45000,
            'statut' => 'BROUILLON',
            'version_verrouillage' => 0,
        ];
    }
}
