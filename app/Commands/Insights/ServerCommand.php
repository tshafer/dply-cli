<?php

declare(strict_types=1);

namespace App\Commands\Insights;

use App\Commands\BaseCommand;
use App\Support\Tables;

class ServerCommand extends BaseCommand
{
    protected $signature = 'insights:server
        {server : Server ID.}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'Show insights findings (alerts, recommendations) for a single server.';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get("/api/v1/servers/{$this->argument('server')}/insights"));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $findings = is_array($data['findings'] ?? null) ? $data['findings'] : (is_array($data['data'] ?? null) ? $data['data'] : $data);
        if (! is_array($findings) || $findings === []) {
            $this->components->info('No findings — server is clean.');

            return self::SUCCESS;
        }

        $rows = array_map(static fn (array $f): array => [
            'severity' => strtoupper((string) ($f['severity'] ?? 'info')),
            'category' => $f['category'] ?? '—',
            'title' => $f['title'] ?? $f['message'] ?? '',
            'found' => Tables::relativeTime($f['detected_at'] ?? $f['created_at'] ?? null),
            'ack' => ! empty($f['acknowledged_at']) ? '✓' : '',
        ], array_values(array_filter($findings, 'is_array')));

        Tables::dump($this, $rows, ['Severity', 'Category', 'Title', 'Found', 'Ack']);

        return self::SUCCESS;
    }
}
