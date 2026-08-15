<?php

namespace Database\Factories\Workflow;

use App\Models\Workflow\EtapeParcours;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EtapeParcours>
 */
class EtapeParcoursFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'definition_parcours_id' => \App\Models\Workflow\DefinitionParcours::factory(),
            'role_responsable_id' => 1, // Par défaut, on peut l'écraser
            'code' => $this->faker->unique()->word(),
            'nom' => $this->faker->sentence(3),
            'code_corbeille' => 'CORBEILLE_DEFAUT',
            'initiale' => false,
            'finale' => false,
            'ordre' => 1,
        ];
    }
}
