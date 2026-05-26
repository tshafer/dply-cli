<?php

declare(strict_types=1);

namespace App\Commands\Auth;

use App\Commands\BaseCommand;
use App\Services\ConfigStore;

class LogoutCommand extends BaseCommand
{
    protected $signature = 'logout';

    protected $description = 'Forget the stored token for the current dply host.';

    public function handle(ConfigStore $config): int
    {
        $host = $this->host();
        if ($config->tokenFor($host) === null) {
            $this->components->info("Not logged in to {$host}.");

            return self::SUCCESS;
        }

        $config->forgetHost($host);
        $this->components->info("Logged out of {$host}.");

        return self::SUCCESS;
    }
}
