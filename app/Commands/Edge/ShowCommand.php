<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;

class ShowCommand extends BaseCommand
{
    protected $signature = 'edge:show
        {site : Edge site ID.}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'Show full detail for a single Edge site.';

    public function handle(): int
    {
        $site = $this->argument('site');
        $data = $this->safely(fn () => $this->client()->get('/api/v1/edge/sites/'.$site));
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
            'Name' => $payload['name'] ?? null,
            'Status' => $payload['status'] ?? null,
            'Backend' => $payload['edge_backend'] ?? $payload['backend'] ?? null,
            'Runtime mode' => $payload['runtime_mode'] ?? null,
            'Framework' => $payload['build']['framework'] ?? null,
            'Repo' => $payload['source']['repo'] ?? null,
            'Branch' => $payload['source']['branch'] ?? null,
            'Build command' => $payload['build']['command'] ?? null,
            'Output dir' => $payload['build']['output'] ?? null,
            'Live URL' => $payload['live_url'] ?? null,
            'Active deployment' => $payload['active_deployment_id'] ?? null,
            'Created' => $payload['created_at'] ?? null,
            'Updated' => $payload['updated_at'] ?? null,
        ] as $label => $value) {
            if ($value !== null && $value !== '') {
                $this->components->twoColumnDetail($label, (string) $value);
            }
        }

        return self::SUCCESS;
    }
}
