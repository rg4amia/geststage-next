<?php

namespace Database\Factories\Reference;

use App\Models\Reference\Agence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Agence>
 */
class AgenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'region_id' => null, // or a factory relation if needed
            'nom' => $this->faker->city().' Agence',
            'code' => $this->faker->unique()->lexify('AG-???'),
            'adresse' => $this->faker->address(),
            'actif' => true,
        ];
    }
}
