<?php

namespace Database\Factories\Company;

use App\Models\Company\OffreEmploi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OffreEmploi>
 */
class OffreEmploiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entreprise_id' => \App\Models\Company\Entreprise::factory(),
            'agence_id' => \App\Models\Reference\Agence::factory() ?? 1,
            'type_stage_id' => \App\Models\Reference\TypeStage::factory() ?? 1,
            'source_financement_id' => \App\Models\Reference\SourceFinancement::factory() ?? 1,
            'programme_id' => null,
            'numero' => $this->faker->unique()->numerify('OFFRE-####-####'),
            'intitule' => $this->faker->jobTitle(),
            'description' => $this->faker->paragraph(),
            'nombre_places' => $this->faker->numberBetween(1, 10),
            'publiee_le' => null,
            'valide_du' => now(),
            'valide_au' => now()->addMonths(6),
            'statut' => 'BROUILLON',
        ];
    }
}
