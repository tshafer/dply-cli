<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;

class DeployCommand extends BaseCommand
{
    protected $signature = 'edge:deploy
        {site : Edge site ID.}
        {--commit= : Specific commit SHA to deploy (defaults to the branch HEAD).}
        {--branch= : Override the source branch for this deploy.}';

    protected $description = 'Queue a new Edge deployment for a site (optionally pinned to a commit/branch).';

    public function handle(): int
    {
        $site = $this->argument('site');
        $payload = array_filter([
            'git_commit' => $this->option('commit'),
            'git_branch' => $this->option('branch'),
        ], static fn ($v) => $v !== null && $v !== '');

        $data = $this->safely(fn () => $this->client()->post("/api/v1/edge/sites/{$site}/deployments", $payload));
        if ($data === null) {
            return self::FAILURE;
        }

        $deployment = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $deployId = $deployment['id'] ?? '(unknown)';
        $status = $deployment['status'] ?? '(unknown)';

        $this->components->info(sprintf('Deploy queued: %s (status: %s)', $deployId, $status));
        $this->components->info("Track it: dply edge:deployments {$site}");

        return self::SUCCESS;
    }
}
