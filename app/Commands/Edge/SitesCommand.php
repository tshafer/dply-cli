<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;
use App\Support\Tables;

class SitesCommand extends BaseCommand
{
    protected $signature = 'edge:sites
        {--status= : Filter by status (active, provisioning, failed, ...).}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'List Edge sites in the active organization.';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get('/api/v1/edge/sites', array_filter([
            'status' => $this->option('status'),
        ], static fn ($v) => $v !== null && $v !== '')));

        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $sites = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $rows = array_map(static fn (array $site): array => [
            'id' => $site['id'] ?? '',
            'name' => $site['name'] ?? '',
            'status' => $site['status'] ?? '',
            'framework' => $site['build']['framework'] ?? $site['framework'] ?? '—',
            'hostname' => $site['live_url'] ?? $site['hostname'] ?? '—',
            'updated' => Tables::relativeTime($site['updated_at'] ?? null),
        ], $sites);

        Tables::dump($this, $rows, ['ID', 'Name', 'Status', 'Framework', 'Hostname', 'Updated']);

        return self::SUCCESS;
    }
}
