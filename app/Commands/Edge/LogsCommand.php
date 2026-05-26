<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;

class LogsCommand extends BaseCommand
{
    protected $signature = 'edge:logs
        {site : Edge site ID.}
        {--limit=100 : Lines to fetch per request.}
        {--since= : ISO timestamp lower bound (e.g. 2026-05-25T12:00:00Z).}
        {--tail : Keep polling for new lines until interrupted.}
        {--interval=3 : Poll interval in seconds when --tail is set.}';

    protected $description = 'Fetch (or tail) Edge access/error logs for a site.';

    public function handle(): int
    {
        $site = $this->argument('site');
        $base = "/api/v1/edge/sites/{$site}/logs";
        $since = $this->option('since');

        $tail = (bool) $this->option('tail');
        $interval = max(1, (int) $this->option('interval'));

        do {
            $data = $this->safely(fn () => $this->client()->get($base, array_filter([
                'limit' => (int) $this->option('limit'),
                'since' => $since,
            ], static fn ($v) => $v !== null && $v !== '')));

            if ($data === null) {
                return self::FAILURE;
            }

            $entries = is_array($data['data'] ?? null) ? $data['data'] : (is_array($data) ? $data : []);
            foreach ($entries as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $when = (string) ($row['timestamp'] ?? $row['logged_at'] ?? '');
                $status = (string) ($row['status'] ?? '   ');
                $method = (string) ($row['method'] ?? 'GET');
                $path = (string) ($row['path'] ?? $row['url'] ?? '');
                $ms = isset($row['ms']) ? sprintf('%4dms', (int) $row['ms']) : '   ';
                $msg = (string) ($row['message'] ?? '');

                $this->line(trim(sprintf('%s  %s  %s  %-6s %s %s', $when, $status, $ms, $method, $path, $msg)));

                if ($when !== '') {
                    $since = $when;
                }
            }

            if ($tail) {
                sleep($interval);
            }
        } while ($tail);

        return self::SUCCESS;
    }
}
