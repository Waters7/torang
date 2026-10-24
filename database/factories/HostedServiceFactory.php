<?php

namespace Database\Factories;

use App\Enums\BillingCycle;
use App\Enums\HostedServiceStatus;
use App\Enums\HostedServiceType;
use App\Models\HostedService;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HostedService>
 */
class HostedServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = fake()->unique()->domainWord().'.example.ac.id';

        return [
            'provider_id' => Provider::factory(),
            'name' => "Domain {$domain}",
            'type' => HostedServiceType::Domain,
            'identifier' => $domain,
            'account_email' => 'tik@example.ac.id',
            'used_by' => 'Website kampus',
            'last_paid_at' => today()->subYear(),
            'expires_at' => today()->addDays(200),
            'billing_cycle' => BillingCycle::Annual,
            'cost' => 250000,
            'auto_renew' => false,
            'status' => HostedServiceStatus::Active,
        ];
    }

    public function expiresInDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => today()->addDays($days),
        ]);
    }

    public function stopped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => HostedServiceStatus::Stopped,
        ]);
    }
}
