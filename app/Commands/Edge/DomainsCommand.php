<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;
use App\Support\Tables;

class DomainsCommand extends BaseCommand
{
    protected $signature = 'edge:domains
        {site : Edge site ID.}
        {--add= : Hostname to attach.}
        {--verify= : Hostname to (re)verify.}
        {--remove= : Hostname to detach.}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'List or manage custom domains on an Edge site.';

    public function handle(): int
    {
        $site = $this->argument('site');
        $base = "/api/v1/edge/sites/{$site}/domains";

        if (($host = $this->option('add')) !== null && $host !== '') {
            if ($this->safely(fn () => $this->client()->post($base, ['hostname' => trim($host)])) === null) {
                return self::FAILURE;
            }
            $this->components->info("Attached {$host}.");
        }

        if (($host = $this->option('verify')) !== null && $host !== '') {
            if ($this->safely(fn () => $this->client()->post("{$base}/".trim($host).'/verify')) === null) {
                return self::FAILURE;
            }
            $this->components->info("Verification triggered for {$host}.");
        }

        if (($host = $this->option('remove')) !== null && $host !== '') {
            if ($this->safely(fn () => $this->client()->delete("{$base}/".trim($host))) === null) {
                return self::FAILURE;
            }
            $this->components->info("Detached {$host}.");
        }

        // Always end with a fresh list so the user sees the post-mutation state.
        $data = $this->safely(fn () => $this->client()->get($base));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $rows = array_map(static fn (array $d): array => [
            'hostname' => $d['hostname'] ?? '',
            'status' => $d['status'] ?? '',
            'verified' => Tables::relativeTime($d['verified_at'] ?? null),
            'created' => Tables::relativeTime($d['created_at'] ?? null),
        ], is_array($data['data'] ?? null) ? $data['data'] : $data);

        Tables::dump($this, $rows, ['Hostname', 'Status', 'Verified', 'Created']);

        return self::SUCCESS;
    }
}
