<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;
use App\Support\Tables;

class DeploymentsCommand extends BaseCommand
{
    protected $signature = 'edge:deployments
        {site : Edge site ID.}
        {--limit=20 : Max rows to fetch.}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'List recent deployments for an Edge site.';

    public function handle(): int
    {
        $site = $this->argument('site');
        $data = $this->safely(fn () => $this->client()->get("/api/v1/edge/sites/{$site}/deployments", [
            'limit' => (int) $this->option('limit'),
        ]));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $deployments = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $rows = array_map(static fn (array $d): array => [
            'id' => $d['id'] ?? '',
            'status' => $d['status'] ?? '',
            'commit' => substr((string) ($d['git_commit'] ?? ''), 0, 8) ?: '—',
            'branch' => $d['git_branch'] ?? '—',
            'published' => Tables::relativeTime($d['published_at'] ?? null),
            'created' => Tables::relativeTime($d['created_at'] ?? null),
        ], $deployments);

        Tables::dump($this, $rows, ['ID', 'Status', 'Commit', 'Branch', 'Published', 'Created']);

        return self::SUCCESS;
    }
}
