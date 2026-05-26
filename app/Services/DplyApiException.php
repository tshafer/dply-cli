<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use RuntimeException;

class DplyApiException extends RuntimeException
{
    /** @var array<string, mixed>|null */
    public ?array $body = null;

    public string $method = '';

    public string $path = '';

    public static function fromResponse(Response $response, string $method, string $path): self
    {
        $status = $response->status();
        $body = $response->json();
        $hint = self::hintFor($status, is_array($body) ? $body : []);

        $instance = new self(
            sprintf('%s %s → %d %s', strtoupper($method), $path, $status, $hint),
            $status,
        );
        $instance->body = is_array($body) ? $body : null;
        $instance->method = strtoupper($method);
        $instance->path = $path;

        return $instance;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function hintFor(int $status, array $body): string
    {
        // Laravel APIs default to {message: ...} for errors + {errors: {...}} for 422s.
        if (is_string($body['message'] ?? null) && $body['message'] !== '') {
            return $body['message'];
        }

        return match (true) {
            $status === 401 => 'Not authenticated. Run `dply login` to refresh your token.',
            $status === 403 => 'Forbidden — your token lacks the required ability for this endpoint.',
            $status === 404 => 'Not found.',
            $status === 422 => 'Validation failed.',
            $status === 429 => 'Rate limited — wait a moment and retry.',
            $status >= 500 => 'Server error.',
            default => 'Request failed.',
        };
    }
}
