<?php

declare(strict_types=1);

namespace App\Commands\Sites;

use App\Commands\BaseCommand;

class DeployCommand extends BaseCommand
{
    protected $signature = 'sites:deploy
        {site : Site ID.}';

    protected $description = 'Trigger a deploy on a server-hosted site.';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->post(
            "/api/v1/sites/{$this->argument('site')}/deploy",
        ));
        if ($data === null) {
            return self::FAILURE;
        }

        $deployment = is_array($data['data'] ?? null) ? $data['data'] : $data;
        $this->components->info(sprintf(
            'Deploy queued: %s (status: %s)',
            $deployment['id'] ?? '(unknown)',
            $deployment['status'] ?? 'queued',
        ));

        return self::SUCCESS;
    }
}
