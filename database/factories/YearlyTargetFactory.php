<?php

namespace Database\Factories;

use App\Models\YearlyTarget;
use App\Models\Puskesmas;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\YearlyTarget>
 */
class YearlyTargetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = YearlyTarget::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'puskesmas_id' => Puskesmas::factory(),
            'year' => $this->faker->numberBetween(2020, date('Y')),
            'disease_type' => $this->faker->randomElement(['ht', 'dm']),
            'target_count' => $this->faker->numberBetween(50, 500),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the target is for hypertension.
     */
    public function hypertension(): static
    {
        return $this->state(fn(array $attributes) => [
            'disease_type' => 'ht',
        ]);
    }

    /**
     * Indicate that the target is for diabetes mellitus.
     */
    public function diabetes(): static
    {
        return $this->state(fn(array $attributes) => [
            'disease_type' => 'dm',
        ]);
    }

    /**
     * Set a specific year for the target.
     */
    public function forYear(int $year): static
    {
        return $this->state(fn(array $attributes) => [
            'year' => $year,
        ]);
    }

    /**
     * Set a specific puskesmas for the target.
     */
    public function forPuskesmas(int $puskesmasId): static
    {
        return $this->state(fn(array $attributes) => [
            'puskesmas_id' => $puskesmasId,
        ]);
    }
}
