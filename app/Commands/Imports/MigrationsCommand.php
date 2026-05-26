<?php

declare(strict_types=1);

namespace App\Commands\Imports;

use App\Commands\BaseCommand;
use App\Support\Tables;

class MigrationsCommand extends BaseCommand
{
    protected $signature = 'imports:migrations
        {--json : Print the raw API response as JSON.}';

    protected $description = 'List import migrations (Forge → dply transitions, etc.).';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get('/api/v1/imports/migrations'));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $rows = array_map(static fn (array $m): array => [
            'id' => $m['id'] ?? '',
            'source' => $m['source'] ?? '—',
            'status' => $m['status'] ?? '',
            'items' => $m['item_count'] ?? $m['items'] ?? '—',
            'updated' => Tables::relativeTime($m['updated_at'] ?? null),
        ], array_values(array_filter(is_array($data['data'] ?? null) ? $data['data'] : $data, 'is_array')));

        Tables::dump($this, $rows, ['ID', 'Source', 'Status', 'Items', 'Updated']);

        return self::SUCCESS;
    }
}
