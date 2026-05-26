<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;
use App\Support\Tables;

class AliasesCommand extends BaseCommand
{
    protected $signature = 'edge:aliases
        {site : Edge site ID.}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'List per-deployment alias hostnames for an Edge site.';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get("/api/v1/edge/sites/{$this->argument('site')}/aliases"));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $aliases = is_array($data['data'] ?? null) ? $data['data'] : $data;
        if (! is_array($aliases) || $aliases === []) {
            $this->components->info('No alias hostnames issued for this site yet.');

            return self::SUCCESS;
        }

        $rows = array_map(static function ($entry): array {
            // The API may return either a flat list of strings or full
            // {hostname, deployment_id, created_at} maps. Handle both
            // so the command works regardless of shape.
            if (is_string($entry)) {
                return ['hostname' => $entry, 'deployment' => '—', 'created' => '—'];
            }
            if (! is_array($entry)) {
                return ['hostname' => '—', 'deployment' => '—', 'created' => '—'];
            }

            return [
                'hostname' => $entry['hostname'] ?? '—',
                'deployment' => $entry['deployment_id'] ?? $entry['deployment']['id'] ?? '—',
                'created' => Tables::relativeTime($entry['created_at'] ?? null),
            ];
        }, $aliases);

        Tables::dump($this, $rows, ['Hostname', 'Deployment', 'Created']);

        return self::SUCCESS;
    }
}
