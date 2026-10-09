<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable(['market_hash_name', 'status', 'rate_limit', 'rate_remaining', 'requested_at'])]
class ApiRequest extends Model
{
    public $timestamps = false;

    /**
     * Summarise outbound API traffic. The remaining quota comes from the
     * headers of the most recent call, so it never costs a request to read.
     *
     * @return array{today: int, last_day: int, total: int, remaining: int|null, limit: int|null, last_at: Carbon|null}
     */
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

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
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
