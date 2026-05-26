<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;

class DeploymentCommand extends BaseCommand
{
    protected $signature = 'edge:deployment
        {site : Edge site ID.}
        {deployment : Deployment ID.}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'Show full detail for a single Edge deployment (build log, commit, artifact info).';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get(sprintf(
            '/api/v1/edge/sites/%s/deployments/%s',
            $this->argument('site'),
            $this->argument('deployment'),
        )));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;

        foreach ([
            'ID' => $payload['id'] ?? null,
            'Status' => $payload['status'] ?? null,
            'Commit' => $payload['git_commit'] ?? null,
            'Branch' => $payload['git_branch'] ?? null,
            'Commit subject' => $payload['meta']['commit']['subject'] ?? null,
            'Commit author' => $payload['meta']['commit']['author'] ?? null,
            'Storage prefix' => $payload['storage_prefix'] ?? null,
            'KV version' => $payload['cf_kv_version'] ?? null,
            'Build log' => $payload['build_log_path'] ?? null,
            'Published' => $payload['published_at'] ?? null,
            'Failed' => $payload['failed_at'] ?? null,
            'Failure reason' => $payload['failure_reason'] ?? null,
            'Created' => $payload['created_at'] ?? null,
        ] as $label => $value) {
            if ($value !== null && $value !== '') {
                $this->components->twoColumnDetail($label, (string) $value);
            }
        }

        $aliases = (array) ($payload['aliases'] ?? []);
        if ($aliases !== []) {
            $this->newLine();
            $this->components->info('Aliases:');
            foreach ($aliases as $alias) {
                $this->line('  '.(string) $alias);
            }
        }

        return self::SUCCESS;
    }
}
