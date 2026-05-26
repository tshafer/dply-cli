<?php

declare(strict_types=1);

namespace App\Commands\Sites;

use App\Commands\BaseCommand;
use App\Support\Tables;

class DeploymentsCommand extends BaseCommand
{
    protected $signature = 'sites:deployments
        {site : Site ID.}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'List deployments for a server-hosted site.';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get("/api/v1/sites/{$this->argument('site')}/deployments"));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $rows = array_map(static fn (array $d): array => [
            'id' => $d['id'] ?? '',
            'status' => $d['status'] ?? '',
            'commit' => substr((string) ($d['commit'] ?? $d['git_commit'] ?? ''), 0, 8) ?: '—',
            'started' => Tables::relativeTime($d['started_at'] ?? null),
            'finished' => Tables::relativeTime($d['finished_at'] ?? null),
        ], is_array($data['data'] ?? null) ? $data['data'] : $data);

        Tables::dump($this, $rows, ['ID', 'Status', 'Commit', 'Started', 'Finished']);

        return self::SUCCESS;
    }
}
