<?php

declare(strict_types=1);

namespace App\Commands\Sites;

use App\Commands\BaseCommand;
use App\Support\Tables;

class ListCommand extends BaseCommand
{
    protected $signature = 'sites:list
        {--json : Print the raw API response as JSON.}';

    protected $description = 'List server-hosted sites (non-Edge).';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get('/api/v1/sites'));
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
            'server' => $s['server']['name'] ?? $s['server_name'] ?? '—',
            'runtime' => $s['runtime'] ?? $s['runtime_profile'] ?? '—',
            'status' => $s['status'] ?? '',
            'updated' => Tables::relativeTime($s['updated_at'] ?? null),
        ], is_array($data['data'] ?? null) ? $data['data'] : $data);

        Tables::dump($this, $rows, ['ID', 'Name', 'Server', 'Runtime', 'Status', 'Updated']);

        return self::SUCCESS;
    }
}
