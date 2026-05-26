<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;

use function Laravel\Prompts\confirm;

class RollbackCommand extends BaseCommand
{
    protected $signature = 'edge:rollback
        {site : Edge site ID.}
        {deployment : Deployment ID to roll production back to.}
        {--yes : Skip the confirmation prompt.}';

    protected $description = 'Roll production back to a prior Edge deployment.';

    public function handle(): int
    {
        if (! $this->option('yes')) {
            $proceed = confirm(
                label: "Roll back site {$this->argument('site')} to deployment {$this->argument('deployment')}?",
                default: false,
            );
            if (! $proceed) {
                $this->components->warn('Cancelled.');

                return self::SUCCESS;
            }
        }

        $data = $this->safely(fn () => $this->client()->post(sprintf(
            '/api/v1/edge/sites/%s/deployments/%s/rollback',
            $this->argument('site'),
            $this->argument('deployment'),
        )));

        if ($data === null) {
            return self::FAILURE;
        }

        $this->components->info('Rollback queued.');

        return self::SUCCESS;
    }
}
