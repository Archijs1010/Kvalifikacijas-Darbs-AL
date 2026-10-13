<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['market_hash_name', 'min_float', 'max_float', 'phase', 'enabled', 'is_favorite'])]
class TrackedSkin extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'min_float' => 'decimal:4',
            'max_float' => 'decimal:4',
            'enabled' => 'boolean',
            'is_favorite' => 'boolean',
        ];
    }
}
