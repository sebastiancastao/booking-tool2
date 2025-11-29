<?php

namespace Database\Factories;

use App\Models\Widget;
use App\Models\WidgetPricing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WidgetPricing>
 */
class WidgetPricingFactory extends Factory
{
    protected $model = WidgetPricing::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'widget_id' => Widget::factory(),
            'category' => 'moveSize',
            'pricing_rules' => [
                'studio' => [
                    'basePrice' => 350,
                    'hours' => 3,
                    'description' => 'Studio apartment',
                ],
                '1-bedroom' => [
                    'basePrice' => 450,
                    'hours' => 4,
                    'description' => '1 bedroom apartment',
                ],
                '2-bedroom' => [
                    'basePrice' => 650,
                    'hours' => 6,
                    'description' => '2 bedroom apartment',
                ],
            ],
        ];
    }

    /**
     * Set pricing category and rules.
     */
    public function category(string $category, array $rules): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
            'pricing_rules' => $rules,
        ]);
    }
}
