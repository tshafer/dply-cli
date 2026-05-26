<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;

class UsageCommand extends BaseCommand
{
    protected $signature = 'edge:usage
        {site : Edge site ID.}
        {--period= : Period to report on (e.g. day, week, month).}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'Show bandwidth / request usage for an Edge site.';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get(
            "/api/v1/edge/sites/{$this->argument('site')}/usage",
            array_filter(['period' => $this->option('period')], static fn ($v) => $v !== null && $v !== ''),
        ));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;
        foreach ($payload as $key => $value) {
            $this->components->twoColumnDetail(
                ucfirst(str_replace('_', ' ', (string) $key)),
                is_scalar($value) ? (string) $value : json_encode($value),
            );
        }

        return self::SUCCESS;
    }
}
