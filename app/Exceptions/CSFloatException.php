<?php

namespace App\Exceptions;

use DateTimeImmutable;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class CSFloatException extends RuntimeException
{
    public const CATEGORY_AUTH = 'auth';

    public const CATEGORY_NOT_FOUND = 'not_found';

    public const CATEGORY_RATE_LIMITED = 'rate_limited';

    public const CATEGORY_SERVER_ERROR = 'server_error';

    public const CATEGORY_CLIENT_ERROR = 'client_error';

    public const CATEGORY_CONNECTION = 'connection';

    public const CATEGORY_UNKNOWN = 'unknown';

    public function __construct(
        public readonly string $category,
        string $message,
        public readonly ?int $status = null,
        public readonly ?int $retryAfter = null,
        public readonly ?int $rateLimit = null,
        public readonly ?int $rateRemaining = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(Response $response): ?self
    {
        if ($response->successful()) {
            return null;
        }

        return match (true) {
            in_array($response->status(), [401, 403], true) => new self(
                self::CATEGORY_AUTH,
                'CSFloat rejected the request as unauthorized. The API key may be missing or invalid.',
                $response->status(),
            ),
            $response->status() === 404 => new self(
                self::CATEGORY_NOT_FOUND,
                'CSFloat could not find sales for this item (404). Check the market hash name.',
                404,
            ),
            $response->status() === 429 => self::rateLimited($response),
            $response->serverError() => new self(
                self::CATEGORY_SERVER_ERROR,
                "CSFloat is temporarily unavailable (HTTP {$response->status()}). Try again later.",
                $response->status(),
            ),
            default => new self(
                self::CATEGORY_CLIENT_ERROR,
                "CSFloat rejected the request (HTTP {$response->status()}).",
                $response->status(),
            ),
        };
    }

    public static function rateLimited(Response $response): self
    {
        $retryAfter = self::retryAfterSeconds($response);

        $message = $retryAfter !== null
            ? 'CSFloat rate limit reached. Try again in '.self::humanDuration($retryAfter).'.'
            : 'CSFloat rate limit reached. Wait before importing again so the quota can reset.';

        return new self(
            self::CATEGORY_RATE_LIMITED,
            $message,
            429,
            $retryAfter,
            self::intHeader($response, 'x-ratelimit-limit'),
            self::intHeader($response, 'x-ratelimit-remaining'),
        );
    }

    public static function connection(string $message = 'Could not reach CSFloat (network error or timeout). Try again later.'): self
    {
        return new self(self::CATEGORY_CONNECTION, $message);
    }

    public static function unknown(Throwable $previous): self
    {
        return new self(self::CATEGORY_UNKNOWN, 'The import failed unexpectedly. Try again later.', previous: $previous);
    }

    public function isRateLimited(): bool
    {
        return $this->category === self::CATEGORY_RATE_LIMITED;
    }

    private static function retryAfterSeconds(Response $response): ?int
    {
        $header = $response->header('retry-after');

        if (is_numeric($header)) {
            return max(0, (int) $header);
        }

        if (is_string($header) && $header !== '') {
            try {
                $seconds = (new DateTimeImmutable($header))->getTimestamp() - now()->getTimestamp();

                return max(0, $seconds);
            } catch (Throwable) {
            }
        }

        $reset = self::intHeader($response, 'x-ratelimit-reset');

        if ($reset !== null) {
            return max(0, $reset - now()->getTimestamp());
        }

        return null;
    }

    private static function intHeader(Response $response, string $header): ?int
    {
        $value = $response->header($header);

        return is_numeric($value) ? (int) $value : null;
    }

    private static function humanDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.' '.(($seconds === 1) ? 'second' : 'seconds');
        }

        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        if ($minutes < 60) {
            return $minutes.' '.(($minutes === 1) ? 'minute' : 'minutes')
                .($remaining > 0 ? ' '.$remaining.'s' : '');
        }
        $hours = intdiv($minutes, 60);
        $minutes %= 60;

        return $hours.' '.(($hours === 1) ? 'hour' : 'hours')
            .($minutes > 0 ? ' '.$minutes.'m' : '');
    }
}
