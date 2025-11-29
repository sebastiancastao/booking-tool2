<?php

namespace Database\Factories;

use App\Models\Widget;
use App\Models\WidgetStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WidgetStep>
 */
class WidgetStepFactory extends Factory
{
    protected $model = WidgetStep::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'widget_id' => Widget::factory(),
            'step_key' => 'service-selection',
            'title' => 'Select Your Service',
            'subtitle' => 'What can we help you with today?',
            'prompt' => [
                'message' => 'Please select the service you need',
                'type' => 'avatar',
            ],
            'options' => [
                [
                    'id' => 'service_1',
                    'value' => 'full-service',
                    'title' => 'Full Service',
                    'description' => 'Complete moving service',
                    'icon' => 'Truck',
                    'type' => 'service',
                ],
            ],
            'buttons' => [
                'primary' => [
                    'text' => 'Continue',
                    'action' => 'next',
                ],
            ],
            'layout' => [
                'type' => 'grid',
                'columns' => 1,
                'centered' => true,
            ],
            'validation' => [
                'required' => true,
                'field' => 'serviceType',
            ],
            'order_index' => 1,
            'is_enabled' => true,
        ];
    }

    /**
     * Set the step key and related fields.
     */
    public function stepKey(string $key): static
    {
        return $this->state(fn (array $attributes) => [
            'step_key' => $key,
            'validation' => [
                'required' => true,
                'field' => $key,
            ],
        ]);
    }

    /**
     * Set the order index.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_index' => $order,
        ]);
    }

    /**
     * Mark the step as disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
