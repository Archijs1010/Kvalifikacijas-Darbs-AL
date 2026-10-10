<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TrackedSkinFactory extends Factory
{
    public function definition(): array
    {
        $skin = fake()->randomElement([
            'AK-47 | Redline',
            'AWP | Asiimov',
            'M4A1-S | Printstream',
            'Desert Eagle | Blaze',
            'USP-S | Kill Confirmed',
            'Glock-18 | Fade',
        ]);

        $wear = fake()->randomElement([
            'Factory New',
            'Minimal Wear',
            'Field-Tested',
            'Well-Worn',
            'Battle-Scarred',
        ]);

        return [
            'market_hash_name' => "{$skin} ({$wear})",
            'min_float' => fake()->randomFloat(4, 0, 0.5),
            'max_float' => fake()->randomFloat(4, 0.5, 1),
            'enabled' => true,
        ];
    }
}
