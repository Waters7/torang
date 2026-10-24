<?php

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'website' => 'https://'.str($name)->slug().'.example.com',
            'panel_url' => 'https://panel.'.str($name)->slug().'.example.com',
            'support_contact' => 'support@example.com',
        ];
    }
}
