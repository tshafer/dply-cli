<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;

/**
 * Combined access settings:
 *   dply edge:access SITE                                  → show current
 *   dply edge:access SITE --mode=none|password|dply-account|email
 *   dply edge:access SITE --mode=password --password=hunter2
 *   dply edge:access SITE --mode=email   --allowed-email=a@b --allowed-email=c@d
 *
 * If --mode (or any other --set-style flag) is present, the command
 * issues a PATCH and then re-renders the resulting state.
 */
class AccessCommand extends BaseCommand
{
    protected $signature = 'edge:access
        {site : Edge site ID.}
        {--mode= : Access mode (none, password, dply-account, email).}
        {--password= : Password (only meaningful with --mode=password).}
        {--allowed-email=* : Allowed email (repeatable, only meaningful with --mode=email).}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'Show or update preview-protection access settings for an Edge site.';

    public function handle(): int
    {
        $site = $this->argument('site');
        $base = "/api/v1/edge/sites/{$site}/access";

        $mode = $this->option('mode');
        $password = $this->option('password');
        $emails = (array) $this->option('allowed-email');

        if ($mode !== null || $password !== null || $emails !== []) {
            $payload = array_filter([
                'mode' => $mode,
                'password' => $password,
                'allowed_emails' => $emails !== [] ? array_values(array_unique(array_filter(array_map('trim', $emails)))) : null,
            ], static fn ($v) => $v !== null && $v !== []);

            $update = $this->safely(fn () => $this->client()->patch($base, $payload));
            if ($update === null) {
                return self::FAILURE;
            }
            $this->components->info('Access settings updated.');
        }

        $data = $this->safely(fn () => $this->client()->get($base));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;

        $this->components->twoColumnDetail('Mode', (string) ($payload['mode'] ?? 'none'));
        if (isset($payload['password_set']) || isset($payload['has_password'])) {
            $this->components->twoColumnDetail(
                'Password',
                ($payload['password_set'] ?? $payload['has_password'] ?? false) ? '(set)' : '(not set)',
            );
        }
        $allowed = (array) ($payload['allowed_emails'] ?? []);
        if ($allowed !== []) {
            $this->components->twoColumnDetail('Allowed emails', implode(', ', array_map('strval', $allowed)));
        }
        if (isset($payload['updated_at'])) {
            $this->components->twoColumnDetail('Updated', (string) $payload['updated_at']);
        }

        return self::SUCCESS;
    }
}
