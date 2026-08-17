<?php

namespace Database\Factories\Company;

use App\Models\Company\Entreprise;
use App\Models\Company\OffreEmploi;
use App\Models\Reference\Agence;
use App\Models\Reference\SourceFinancement;
use App\Models\Reference\TypeStage;
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
            'entreprise_id' => Entreprise::factory(),
            'agence_id' => Agence::factory() ?? 1,
            'type_stage_id' => TypeStage::factory() ?? 1,
            'source_financement_id' => SourceFinancement::factory() ?? 1,
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
