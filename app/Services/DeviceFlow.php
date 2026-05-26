<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * OAuth-style device authorization flow against /api/v1/auth/device/*.
 * Two calls + a polling loop: start() mints the code pair, poll()
 * checks status until the user approves on the web side.
 *
 * Status values mirror the server: pending | authorized | denied | expired.
 */
class DeviceFlow
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_DENIED = 'denied';

    public const STATUS_EXPIRED = 'expired';

    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @return array{
     *   device_code: string,
     *   user_code: string,
     *   verification_uri: string,
     *   verification_uri_complete: string,
     *   expires_in: int,
     *   interval: int,
     * }
     */
    public function start(string $host): array
    {
        $response = $this->http
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->withUserAgent('dply-cli/'.app('git.version'))
            ->post(rtrim($host, '/').'/api/v1/auth/device/start');

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Could not start device-flow login on %s: %d %s',
                $host,
                $response->status(),
                $response->body() === '' ? '(empty body)' : substr($response->body(), 0, 200),
            ));
        }

        $body = (array) $response->json();
        foreach (['device_code', 'user_code', 'verification_uri', 'verification_uri_complete'] as $key) {
            if (! is_string($body[$key] ?? null) || $body[$key] === '') {
                throw new RuntimeException("Device-flow start response missing field: {$key}");
            }
        }

        return [
            'device_code' => $body['device_code'],
            'user_code' => $body['user_code'],
            'verification_uri' => $body['verification_uri'],
            'verification_uri_complete' => $body['verification_uri_complete'],
            'expires_in' => (int) ($body['expires_in'] ?? 600),
            'interval' => max(1, (int) ($body['interval'] ?? 5)),
        ];
    }

    /**
     * Hit /auth/device/poll once. Returns the parsed payload so the
     * caller can drive its own loop (and show a spinner).
     *
     * @return array{status: string, token?: string}
     */
    public function poll(string $host, string $deviceCode): array
    {
        $response = $this->http
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            ->withUserAgent('dply-cli/'.app('git.version'))
            ->post(rtrim($host, '/').'/api/v1/auth/device/poll', [
                'device_code' => $deviceCode,
            ]);

        if (! $response->successful()) {
            // 429 from the server's tight throttle just means "back off
            // and try again". Surface as pending so the loop sleeps.
            if ($response->status() === 429) {
                return ['status' => self::STATUS_PENDING];
            }
            throw new RuntimeException(sprintf(
                'Device-flow poll failed: %d %s',
                $response->status(),
                substr($response->body(), 0, 200),
            ));
        }

        $body = (array) $response->json();
        $status = is_string($body['status'] ?? null) ? $body['status'] : self::STATUS_EXPIRED;
        $payload = ['status' => $status];
        if ($status === self::STATUS_AUTHORIZED && is_string($body['token'] ?? null)) {
            $payload['token'] = (string) $body['token'];
        }

        return $payload;
    }
}
