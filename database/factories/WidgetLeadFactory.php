<?php

namespace Database\Factories;

use App\Models\Widget;
use App\Models\WidgetLead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WidgetLead>
 */
class WidgetLeadFactory extends Factory
{
    protected $model = WidgetLead::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'widget_id' => Widget::factory(),
            'lead_data' => [
                'serviceType' => 'full-service',
                'projectScope' => '2-bedroom',
                'moveDate' => fake()->date(),
            ],
            'contact_info' => [
                'name' => fake()->name(),
                'email' => fake()->email(),
                'phone' => fake()->phoneNumber(),
            ],
            'estimated_value' => fake()->randomFloat(2, 300, 2000),
            'status' => 'new',
            'source_url' => fake()->url(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Set the lead status.
     */
    public function status(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Mark as contacted.
     */
    public function contacted(): static
    {
        return $this->status('contacted');
    }

    /**
     * Mark as converted.
     */
    public function converted(): static
    {
        return $this->status('converted');
    }

    /**
     * Mark as lost.
     */
    public function lost(): static
    {
        return $this->status('lost');
    }
}
