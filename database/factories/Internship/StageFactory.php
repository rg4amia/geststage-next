<?php

namespace Database\Factories\Internship;

use App\Models\Internship\Stage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stage>
 */
class StageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'beneficiaire_id' => \App\Models\Beneficiary\Beneficiaire::factory(),
            'entreprise_id' => \App\Models\Company\Entreprise::factory(),
            'agence_id' => \App\Models\Reference\Agence::factory(),
            'type_stage_id' => \App\Models\Reference\TypeStage::factory(),
            'source_financement_id' => \App\Models\Reference\SourceFinancement::factory(),
            'intitule_poste' => $this->faker->jobTitle(),
            'date_debut' => $this->faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'date_fin_prevue' => clone $this->faker->dateTimeBetween('+2 months', '+6 months'),
        ];
    }
}
