<?php

namespace Database\Factories\Beneficiary;

use App\Models\Beneficiary\Beneficiaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beneficiaire>
 */
class BeneficiaireFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_aej' => $this->faker->unique()->numerify('AEJ-######'),
            'nom' => $this->faker->lastName(),
            'prenoms' => $this->faker->firstName(),
            'date_naissance' => $this->faker->dateTimeBetween('-30 years', '-18 years')->format('Y-m-d'),
            'lieu_naissance' => $this->faker->city(),
            'sexe' => $this->faker->randomElement(['M', 'F']),
            'telephone_principal' => $this->faker->phoneNumber(),
            'email' => $this->faker->unique()->safeEmail(),
            'actif' => true,
        ];
    }
}
