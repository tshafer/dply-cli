<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;
use App\Support\Tables;

/**
 * Combined env management:
 *   dply edge:env SITE                          → list keys
 *   dply edge:env SITE --set KEY=value          → upsert one var
 *   dply edge:env SITE --set K1=v1 --set K2=v2  → upsert several
 *   dply edge:env SITE --unset KEY              → delete
 *   dply edge:env SITE --from-file .env         → bulk replace
 *
 * Values are never returned by the API on GET — this is by design,
 * the dashboard shows the same masked view. Use --json on `dply
 * edge:show` if you need everything else about a site.
 */
class EnvCommand extends BaseCommand
{
    protected $signature = 'edge:env
        {site : Edge site ID.}
        {--set=* : KEY=value pair to upsert (repeatable).}
        {--unset=* : KEY to remove (repeatable).}
        {--from-file= : Path to a .env-style file. Bulk-replaces all production env on the site.}
        {--scope=production : Env scope (production | preview).}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'List or modify environment variables for an Edge site.';

    public function handle(): int
    {
        $site = $this->argument('site');
        $scope = (string) $this->option('scope');
        $base = "/api/v1/edge/sites/{$site}/env";

        // --from-file → PUT bulk replace
        if (($file = $this->option('from-file')) !== null && $file !== '') {
            return $this->bulkReplaceFromFile($base, $scope, (string) $file);
        }

        $setOps = (array) $this->option('set');
        $unsetOps = (array) $this->option('unset');

        if ($setOps !== []) {
            foreach ($setOps as $pair) {
                if (! is_string($pair) || ! str_contains($pair, '=')) {
                    $this->components->error("--set expects KEY=value (got: ".var_export($pair, true).')');

                    return self::FAILURE;
                }
                [$key, $value] = explode('=', $pair, 2);
                $key = trim($key);
                if ($this->safely(fn () => $this->client()->patch("{$base}/{$key}", [
                    'value' => $value,
                    'scope' => $scope,
                ])) === null) {
                    return self::FAILURE;
                }
                $this->components->info("set {$key}");
            }
        }

        if ($unsetOps !== []) {
            foreach ($unsetOps as $key) {
                if (! is_string($key) || trim($key) === '') {
                    continue;
                }
                if ($this->safely(fn () => $this->client()->delete("{$base}/".trim($key), ['scope' => $scope])) === null) {
                    return self::FAILURE;
                }
                $this->components->info("unset {$key}");
            }
        }

        // Default action: list.
        $data = $this->safely(fn () => $this->client()->get($base, ['scope' => $scope]));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $vars = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $rows = array_map(static fn (array $v): array => [
            'key' => $v['key'] ?? '',
            'scope' => $v['scope'] ?? $scope,
            'updated' => Tables::relativeTime($v['updated_at'] ?? null),
        ], $vars);

        Tables::dump($this, $rows, ['Key', 'Scope', 'Updated']);

        return self::SUCCESS;
    }

    private function bulkReplaceFromFile(string $base, string $scope, string $file): int
    {
        if (! is_file($file)) {
            $this->components->error("File not found: {$file}");

            return self::FAILURE;
        }

        $entries = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            // Strip matching surrounding quotes (.env convention).
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[strlen($v) - 1] === $v[0]) {
                $v = substr($v, 1, -1);
            }
            $entries[] = ['key' => $k, 'value' => $v, 'scope' => $scope];
        }

        if ($entries === []) {
            $this->components->warn('No KEY=value lines found in the file. Aborting.');

            return self::FAILURE;
        }

        $data = $this->safely(fn () => $this->client()->put($base, ['vars' => $entries, 'scope' => $scope]));
        if ($data === null) {
            return self::FAILURE;
        }

        $this->components->info(sprintf('Replaced %d env var(s) on scope %s.', count($entries), $scope));

        return self::SUCCESS;
    }
}
