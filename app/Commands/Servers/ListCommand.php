<?php

declare(strict_types=1);

namespace App\Commands\Servers;

use App\Commands\BaseCommand;
use App\Support\Tables;

class ListCommand extends BaseCommand
{
    protected $signature = 'servers:list
        {--json : Print the raw API response as JSON.}';

    protected $description = 'List servers visible to the active token.';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get('/api/v1/servers'));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $rows = array_map(static fn (array $s): array => [
            'id' => $s['id'] ?? '',
            'name' => $s['name'] ?? '',
            'provider' => $s['provider'] ?? '—',
            'region' => $s['region'] ?? '—',
            'status' => $s['status'] ?? '',
            'ip' => $s['ip_address'] ?? $s['ip'] ?? '—',
            'updated' => Tables::relativeTime($s['updated_at'] ?? null),
        ], is_array($data['data'] ?? null) ? $data['data'] : $data);

        Tables::dump($this, $rows, ['ID', 'Name', 'Provider', 'Region', 'Status', 'IP', 'Updated']);

        return self::SUCCESS;
    }
}
