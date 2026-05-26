<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * Thin wrapper around Laravel's HTTP client, scoped to a single dply
 * host + token. Commands resolve one via {@see DplyClient::forActive()}
 * and just call get/post/put/etc — auth, base URL, JSON, and error
 * envelope decoding are handled here.
 *
 * Failures bubble as {@see DplyApiException} so commands can `try`
 * around a single call and present the user-facing error consistently.
 */
class DplyClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly ConfigStore $config,
        public readonly string $host,
        public readonly ?string $token,
    ) {}

    public static function forActive(?string $hostFlag = null): self
    {
        $config = app(ConfigStore::class);
        $host = $config->resolveHost($hostFlag);

        return new self(
            app(HttpFactory::class),
            $config,
            $host,
            $config->tokenFor($host),
        );
    }

    public function requireToken(): string
    {
        if ($this->token === null) {
            throw new DplyApiException(
                "Not logged in to {$this->host}. Run `dply login` (or `dply login --host={$this->host}`) first.",
                401,
            );
        }

        return $this->token;
    }

    public function isAuthenticated(): bool
    {
        return $this->token !== null;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function post(string $path, array $payload = []): array
    {
        return $this->request('POST', $path, ['json' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function put(string $path, array $payload = []): array
    {
        return $this->request('PUT', $path, ['json' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function patch(string $path, array $payload = []): array
    {
        return $this->request('PATCH', $path, ['json' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function delete(string $path, array $query = []): array
    {
        return $this->request('DELETE', $path, ['query' => $query]);
    }

    /**
     * Tail/stream a path — returns the raw response so the caller can
     * read the body line by line (used by `dply edge logs --tail`).
     */
    public function stream(string $path, array $query = []): Response
    {
        return $this->pending(authenticated: true)
            ->withOptions(['stream' => true])
            ->get($this->url($path, $query));
    }

    /**
     * @param  array{json?: array<string, mixed>, query?: array<string, mixed>}  $options
     */
    private function request(string $method, string $path, array $options = []): array
    {
        $pending = $this->pending(authenticated: true);
        if (isset($options['query'])) {
            $pending = $pending->withQueryParameters($options['query']);
        }

        $response = match (strtoupper($method)) {
            'GET' => $pending->get($this->url($path)),
            'DELETE' => $pending->delete($this->url($path)),
            'POST' => $pending->post($this->url($path), $options['json'] ?? []),
            'PUT' => $pending->put($this->url($path), $options['json'] ?? []),
            'PATCH' => $pending->patch($this->url($path), $options['json'] ?? []),
            default => throw new RuntimeException("Unsupported HTTP method: {$method}"),
        };

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        throw DplyApiException::fromResponse($response, $method, $path);
    }

    /**
     * Public-facing builder for callers that need to attach a one-off
     * header (e.g. the device-flow polling endpoint, which is unauthed).
     */
    public function pending(bool $authenticated): PendingRequest
    {
        $req = $this->http
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->connectTimeout(10)
            ->withUserAgent('dply-cli/'.app('git.version'));

        if ($authenticated) {
            $req = $req->withToken($this->requireToken());
        }

        return $req;
    }

    public function url(string $path, array $query = []): string
    {
        $url = $this->host.'/'.ltrim($path, '/');
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        return $url;
    }
}
