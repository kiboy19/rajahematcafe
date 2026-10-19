<?php

namespace Database\Factories;

use App\Models\CafeTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CafeTable>
 */
class CafeTableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'label' => fake()->unique()->numerify('##'),
            'seats' => 4,
            'is_active' => true,
        ];
    }
}
