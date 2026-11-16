<?php

namespace Database\Factories;

use App\Models\FamilyEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FamilyEvent>
 */
class FamilyEventFactory extends Factory
{
    protected $model = FamilyEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => '<p>' . fake()->paragraph() . '</p>',
            'city' => fake()->city(),
            'location' => 'https://maps.google.com/?q=24.7136,46.6753',
            'location_name' => fake()->company() . ' Hall',
            'event_date' => fake()->dateTimeBetween('+1 days', '+1 month'),
            'show_countdown' => fake()->boolean(60),
            'display_order' => fake()->numberBetween(1, 20),
            'is_active' => true,
        ];
    }

    /**
     * Mark event as upcoming.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_date' => now()->addDays(fake()->numberBetween(1, 30)),
            'is_active' => true,
        ]);
    }

    /**
     * Mark event as past.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_date' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    /**
     * Mark event as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Mark event with countdown enabled.
     */
    public function withCountdown(): static
    {
        return $this->state(fn (array $attributes) => [
            'show_countdown' => true,
        ]);
    }
}
