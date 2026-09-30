<?php

namespace App\Models;

use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['sale_id', 'market_hash_name', 'price', 'float_value', 'sold_at', 'paint_index', 'raw_json'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'float_value' => 'decimal:8',
            'sold_at' => 'datetime',
            'paint_index' => 'integer',
            'raw_json' => 'array',
        ];
    }
}
