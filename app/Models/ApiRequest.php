<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['market_hash_name', 'status', 'rate_limit', 'rate_remaining', 'requested_at'])]
class ApiRequest extends Model
{
    public $timestamps = false;

    public static function usage(): array
    {
        $latestQuota = static::query()
            ->whereNotNull('rate_remaining')
            ->orderByDesc('requested_at')
            ->first();

        return [
            'today' => static::query()->where('requested_at', '>=', today())->count(),
            'last_day' => static::query()->where('requested_at', '>=', now()->subDay())->count(),
            'total' => static::query()->count(),
            'remaining' => $latestQuota?->rate_remaining,
            'limit' => $latestQuota?->rate_limit,
            'last_at' => static::query()->orderByDesc('requested_at')->first()?->requested_at,
        ];
    }

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'rate_limit' => 'integer',
            'rate_remaining' => 'integer',
            'requested_at' => 'datetime',
        ];
    }
}
