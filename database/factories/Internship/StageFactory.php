<?php

namespace Database\Factories\Internship;

use App\Models\Beneficiary\Beneficiaire;
use App\Models\Company\Entreprise;
use App\Models\Internship\Stage;
use App\Models\Reference\Agence;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
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
            'beneficiaire_id' => Beneficiaire::factory(),
            'entreprise_id' => Entreprise::factory(),
            'agence_id' => Agence::factory(),
            'type_stage_id' => TypeStage::factory(),
            'source_financement_id' => SourceFinancement::factory(),
            'intitule_poste' => $this->faker->jobTitle(),
            'date_debut' => $this->faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'date_fin_prevue' => clone $this->faker->dateTimeBetween('+2 months', '+6 months'),
        ];
    }
}
