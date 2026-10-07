<?php

namespace App\Models;

use Database\Factories\TrackedSkinFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['market_hash_name', 'min_float', 'max_float', 'phase', 'enabled'])]
class TrackedSkin extends Model
{
    /** @use HasFactory<TrackedSkinFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_float' => 'decimal:4',
            'max_float' => 'decimal:4',
            'enabled' => 'boolean',
        ];
    }
}
