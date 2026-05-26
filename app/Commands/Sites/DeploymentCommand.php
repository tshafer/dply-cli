<?php

declare(strict_types=1);

namespace App\Commands\Sites;

use App\Commands\BaseCommand;

class DeploymentCommand extends BaseCommand
{
    protected $signature = 'sites:deployment
        {site : Site ID.}
        {deployment : Deployment ID.}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'Show full detail for a single deployment on a server-hosted site.';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get(sprintf(
            '/api/v1/sites/%s/deployments/%s',
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
            'Commit' => $payload['commit'] ?? $payload['git_commit'] ?? null,
            'Branch' => $payload['branch'] ?? $payload['git_branch'] ?? null,
            'Author' => $payload['commit_author'] ?? null,
            'Subject' => $payload['commit_subject'] ?? null,
            'Started' => $payload['started_at'] ?? null,
            'Finished' => $payload['finished_at'] ?? null,
            'Duration' => $payload['duration'] ?? null,
        ] as $label => $value) {
            if ($value !== null && $value !== '') {
                $this->components->twoColumnDetail($label, (string) $value);
            }
        }

        $log = (string) ($payload['log'] ?? $payload['output'] ?? '');
        if ($log !== '') {
            $this->newLine();
            $this->components->info('--- log ---');
            $this->line($log);
        }

        return self::SUCCESS;
    }
}
