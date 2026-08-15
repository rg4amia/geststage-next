<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\DefinitionParcours;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DefinitionParcours>
 */
class DefinitionParcoursFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->word(),
            'nom' => $this->faker->sentence(3),
            'version' => 1,
            'active' => true,
        ];
    }
}
