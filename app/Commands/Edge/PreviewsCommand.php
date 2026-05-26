<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;
use App\Support\Tables;

class PreviewsCommand extends BaseCommand
{
    protected $signature = 'edge:previews
        {site : Edge site ID.}
        {--create= : Branch name to create a preview for.}
        {--delete= : Preview site ID to delete.}
        {--promote= : Preview site ID to promote to production.}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'List or manage preview deployments for an Edge site.';

    public function handle(): int
    {
        $site = $this->argument('site');
        $base = "/api/v1/edge/sites/{$site}/previews";

        if (($branch = $this->option('create')) !== null && $branch !== '') {
            if ($this->safely(fn () => $this->client()->post($base, ['branch' => trim($branch)])) === null) {
                return self::FAILURE;
            }
            $this->components->info("Preview build queued for branch {$branch}.");
        }

        if (($preview = $this->option('delete')) !== null && $preview !== '') {
            if ($this->safely(fn () => $this->client()->delete("{$base}/".trim($preview))) === null) {
                return self::FAILURE;
            }
            $this->components->info("Deleted preview {$preview}.");
        }

        if (($preview = $this->option('promote')) !== null && $preview !== '') {
            if ($this->safely(fn () => $this->client()->post("{$base}/".trim($preview).'/promote')) === null) {
                return self::FAILURE;
            }
            $this->components->info("Promoted preview {$preview} to production.");
        }

        $data = $this->safely(fn () => $this->client()->get($base));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $rows = array_map(static fn (array $p): array => [
            'id' => $p['id'] ?? '',
            'branch' => $p['preview_branch'] ?? $p['branch'] ?? '—',
            'pr' => $p['preview_pr_number'] ?? '—',
            'status' => $p['status'] ?? '',
            'url' => $p['live_url'] ?? '—',
            'updated' => Tables::relativeTime($p['updated_at'] ?? null),
        ], is_array($data['data'] ?? null) ? $data['data'] : $data);

        Tables::dump($this, $rows, ['ID', 'Branch', 'PR', 'Status', 'URL', 'Updated']);

        return self::SUCCESS;
    }
}
