<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    public function definition(): array
    {
        $saleId = fake()->unique()->uuid();

        return [
            'sale_id' => $saleId,
            'market_hash_name' => fake()->randomElement([
                'AK-47 | Redline (Field-Tested)',
                'AWP | Asiimov (Field-Tested)',
                'M4A1-S | Printstream (Minimal Wear)',
                'Desert Eagle | Blaze (Factory New)',
                'USP-S | Kill Confirmed (Field-Tested)',
                'Glock-18 | Fade (Factory New)',
            ]),
            'price' => fake()->randomFloat(2, 1, 2500),
            'float_value' => fake()->randomFloat(6, 0, 1),
            'sold_at' => fake()->dateTimeBetween('-1 year'),
            'paint_index' => fake()->optional()->numberBetween(0, 10000),
            'raw_json' => ['id' => $saleId],
        ];
    }
}
