<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Widget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Widget>
 */
class WidgetFactory extends Factory
{
    protected $model = Widget::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(3, true) . ' Widget',
            'service_category' => 'moving-services',
            'service_subcategory' => 'residential-moving',
            'domain' => fake()->domainName(),
            'company_name' => fake()->company(),
            'status' => 'draft',
            'widget_key' => Str::random(32),
            'embed_domain' => fake()->domainName(),
            'enabled_modules' => ['service-selection', 'project-scope', 'contact-info'],
            'module_configs' => [
                'service-selection' => [
                    'title' => 'Select Your Service',
                    'subtitle' => 'What can we help you with?',
                    'options' => [
                        [
                            'title' => 'Moving Service',
                            'description' => 'Full-service moving',
                            'icon' => 'Truck',
                        ],
                    ],
                ],
                'project-scope' => [
                    'title' => 'Project Size',
                    'options' => [
                        [
                            'title' => '1 Bedroom',
                            'base_price' => 350,
                            'estimated_hours' => 3,
                            'price_range_min' => 300,
                            'price_range_max' => 400,
                        ],
                    ],
                ],
            ],
            'branding' => [
                'primary_color' => '#F4C443',
                'secondary_color' => '#1A1A1A',
                'company_name' => fake()->company(),
                'logo_url' => null,
                'font_family' => 'Inter',
            ],
            'settings' => [
                'tax_rate' => 0.08,
                'service_area_miles' => 100,
                'minimum_job_price' => 0,
                'show_price_ranges' => true,
            ],
        ];
    }

    /**
     * Indicate that the widget is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
        ]);
    }

    /**
     * Indicate that the widget is paused.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paused',
        ]);
    }

    /**
     * Set the service category.
     */
    public function category(string $category, string $subcategory = null): static
    {
        return $this->state(fn (array $attributes) => [
            'service_category' => $category,
            'service_subcategory' => $subcategory ?? $category,
        ]);
    }

    /**
     * Set enabled modules.
     */
    public function withModules(array $modules): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled_modules' => $modules,
        ]);
    }
}
