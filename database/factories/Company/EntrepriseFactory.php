<?php

namespace Database\Factories\Company;

use App\Models\Company\Entreprise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entreprise>
 */
class EntrepriseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agence_id' => \App\Models\Reference\Agence::factory() ?? 1,
            'commune_id' => null,
            'type_structure_id' => null,
            'raison_sociale' => $this->faker->company(),
            'sigle' => $this->faker->companySuffix(),
            'numero_contribuable' => $this->faker->unique()->numerify('NCC-#######'),
            'registre_commerce' => $this->faker->unique()->numerify('RC-#######'),
            'adresse' => $this->faker->address(),
            'telephone' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->companyEmail(),
            'actif' => true,
        ];
    }
}
