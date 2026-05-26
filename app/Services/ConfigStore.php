<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Reads / writes ~/.dply/config.json. The file holds a per-host token map
 * plus a `default_host` pointer — so a single user can be logged into
 * multiple dply instances (e.g. https://dplyi.test for local + the prod
 * host) and switch with --host=… without re-logging-in.
 *
 * Shape on disk:
 *   {
 *     "default_host": "https://dply.app",
 *     "hosts": {
 *       "https://dply.app":   { "token": "...", "user": {...}, "updated_at": "..." },
 *       "https://dplyi.test": { "token": "...", "user": {...}, "updated_at": "..." }
 *     }
 *   }
 *
 * File is created mode 0600 so an unprivileged process on the box can't
 * grab the token. The directory is mode 0700 for the same reason.
 */
class ConfigStore
{
    private const FILE_NAME = 'config.json';

    private const DIRECTORY_NAME = '.dply';

    public function __construct(private readonly ?string $homeOverride = null) {}

    public function path(): string
    {
        $home = $this->homeOverride ?? (getenv('HOME') ?: posix_getpwuid(posix_geteuid())['dir'] ?? '');
        if ($home === '') {
            throw new RuntimeException('Could not resolve $HOME — set HOME or pass --config-dir.');
        }

        return rtrim($home, '/').'/'.self::DIRECTORY_NAME.'/'.self::FILE_NAME;
    }

    public function directory(): string
    {
        return dirname($this->path());
    }

    /**
     * @return array{default_host: ?string, hosts: array<string, array<string, mixed>>}
     */
    public function load(): array
    {
        $path = $this->path();
        if (! is_file($path)) {
            return ['default_host' => null, 'hosts' => []];
        }

        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return ['default_host' => null, 'hosts' => []];
        }

        try {
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException("Config file at {$path} is not valid JSON. Delete it and run `dply login` again.");
        }

        return [
            'default_host' => is_string($decoded['default_host'] ?? null) ? $decoded['default_host'] : null,
            'hosts' => is_array($decoded['hosts'] ?? null) ? $decoded['hosts'] : [],
        ];
    }

    /**
     * @param  array{default_host: ?string, hosts: array<string, array<string, mixed>>}  $data
     */
    public function save(array $data): void
    {
        $dir = $this->directory();
        if (! is_dir($dir)) {
            // 0700 — readable/writable only by the owner. Stops other
            // local users on a shared box from harvesting tokens.
            if (! @mkdir($dir, 0o700, true) && ! is_dir($dir)) {
                throw new RuntimeException("Could not create config directory: {$dir}");
            }
        }

        $payload = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $path = $this->path();
        $tmp = $path.'.tmp';
        if (file_put_contents($tmp, $payload."\n", LOCK_EX) === false) {
            throw new RuntimeException("Could not write config to {$path}");
        }
        @chmod($tmp, 0o600);
        if (! @rename($tmp, $path)) {
            throw new RuntimeException("Could not finalize config at {$path}");
        }
    }

    public function defaultHost(): ?string
    {
        return $this->load()['default_host'];
    }

    /**
     * Resolve the host for this invocation. Order of precedence:
     *   1. explicit --host=… flag (passed in)
     *   2. DPLY_HOST environment variable
     *   3. stored default_host
     *   4. https://dply.app (final fallback)
     */
    public function resolveHost(?string $flagValue): string
    {
        $host = $flagValue
            ?: (getenv('DPLY_HOST') ?: null)
            ?: $this->defaultHost()
            ?: 'https://dply.app';

        return rtrim($host, '/');
    }

    /**
     * @return array{token: string, user: array<string, mixed>, updated_at: string}|null
     */
    public function hostEntry(string $host): ?array
    {
        $entry = $this->load()['hosts'][$this->normalizeHost($host)] ?? null;

        return is_array($entry) ? $entry : null;
    }

    public function tokenFor(string $host): ?string
    {
        $entry = $this->hostEntry($host);
        $token = $entry['token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function setHost(string $host, string $token, array $user = [], bool $makeDefault = true): void
    {
        $host = $this->normalizeHost($host);
        $data = $this->load();
        $data['hosts'][$host] = [
            'token' => $token,
            'user' => $user,
            'updated_at' => date(DATE_ATOM),
        ];
        if ($makeDefault || $data['default_host'] === null) {
            $data['default_host'] = $host;
        }
        $this->save($data);
    }

    public function forgetHost(string $host): void
    {
        $host = $this->normalizeHost($host);
        $data = $this->load();
        if (! isset($data['hosts'][$host])) {
            return;
        }
        unset($data['hosts'][$host]);
        if ($data['default_host'] === $host) {
            $data['default_host'] = array_key_first($data['hosts']);
        }
        $this->save($data);
    }

    public function normalizeHost(string $host): string
    {
        return rtrim(strtolower(trim($host)), '/');
    }
}
