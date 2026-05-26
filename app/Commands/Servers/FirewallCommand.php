<?php

declare(strict_types=1);

namespace App\Commands\Servers;

use App\Commands\BaseCommand;

class FirewallCommand extends BaseCommand
{
    protected $signature = 'servers:firewall
        {server : Server ID.}
        {--apply : Apply the current firewall config to the server.}
        {--template= : Apply a named firewall template (e.g. web, ssh-only).}
        {--bundled= : Apply a bundled rules pack by key.}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'Show or apply firewall rules on a server.';

    public function handle(): int
    {
        $server = $this->argument('server');
        $base = "/api/v1/servers/{$server}/firewall";

        if (($tmpl = $this->option('template')) !== null && $tmpl !== '') {
            if ($this->safely(fn () => $this->client()->post("{$base}/templates/".trim($tmpl))) === null) {
                return self::FAILURE;
            }
            $this->components->info("Template {$tmpl} applied.");

            return self::SUCCESS;
        }

        if (($key = $this->option('bundled')) !== null && $key !== '') {
            if ($this->safely(fn () => $this->client()->post("{$base}/bundled/".trim($key))) === null) {
                return self::FAILURE;
            }
            $this->components->info("Bundled rules pack {$key} applied.");

            return self::SUCCESS;
        }

        if ($this->option('apply')) {
            if ($this->safely(fn () => $this->client()->post("{$base}/apply")) === null) {
                return self::FAILURE;
            }
            $this->components->info('Firewall apply queued.');

            return self::SUCCESS;
        }

        $data = $this->safely(fn () => $this->client()->get($base));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json') || ! is_array($data['rules'] ?? null)) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($data['rules'] as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $this->line(sprintf(
                '%-6s %-4s %-15s %-22s %s',
                strtoupper((string) ($rule['action'] ?? '?')),
                strtoupper((string) ($rule['protocol'] ?? '?')),
                $rule['port'] ?? $rule['port_range'] ?? '*',
                $rule['source'] ?? 'any',
                $rule['comment'] ?? '',
            ));
        }

        return self::SUCCESS;
    }
}
