<?php

declare(strict_types=1);

namespace App\Commands\Auth;

use App\Commands\BaseCommand;
use App\Services\ConfigStore;

class WhoamiCommand extends BaseCommand
{
    protected $signature = 'whoami';

    protected $description = 'Show the host, token preview, and cached profile for the current login.';

    public function handle(ConfigStore $config): int
    {
        $host = $this->host();
        $entry = $config->hostEntry($host);

        if ($entry === null) {
            $this->components->warn("Not logged in to {$host}. Run `dply login`.");

            return self::FAILURE;
        }

        $token = (string) ($entry['token'] ?? '');
        $masked = strlen($token) > 8
            ? substr($token, 0, 4).'…'.substr($token, -4)
            : '(set)';

        $user = is_array($entry['user'] ?? null) ? $entry['user'] : [];

        $this->components->twoColumnDetail('Host', $host);
        $this->components->twoColumnDetail('Token', $masked);
        if ($name = ($user['name'] ?? null)) {
            $this->components->twoColumnDetail('User', (string) $name);
        }
        if ($email = ($user['email'] ?? null)) {
            $this->components->twoColumnDetail('Email', (string) $email);
        }
        if ($org = ($user['organization'] ?? null)) {
            $this->components->twoColumnDetail('Org', (string) $org);
        }
        if ($updated = ($entry['updated_at'] ?? null)) {
            $this->components->twoColumnDetail('Logged in', (string) $updated);
        }

        return self::SUCCESS;
    }
}
