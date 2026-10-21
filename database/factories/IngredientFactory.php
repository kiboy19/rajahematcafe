<?php

namespace Database\Factories;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ingredient>
 */
class IngredientFactory extends Factory
{
    protected $model = Ingredient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'unit' => fake()->randomElement(['kg', 'liter', 'pcs', 'pack']),
            'stock_qty_milli' => fake()->numberBetween(1000, 20000),
            'min_stock_milli' => 2000,
            'is_active' => true,
        ];
    }
}
