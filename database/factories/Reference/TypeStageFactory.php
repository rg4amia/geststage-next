<?php

namespace Database\Factories\Reference;

use App\Models\Reference\TypeStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TypeStage>
 */
class TypeStageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('TS-???'),
            'nom' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'actif' => true,
        ];
    }
}
