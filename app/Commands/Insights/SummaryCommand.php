<?php

declare(strict_types=1);

namespace App\Commands\Insights;

use App\Commands\BaseCommand;

class SummaryCommand extends BaseCommand
{
    protected $signature = 'insights:summary
        {--json : Print the raw API response as JSON.}';

    protected $description = 'Show the organization-wide insights summary.';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get('/api/v1/insights/summary'));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($data as $key => $value) {
            $this->components->twoColumnDetail(
                ucfirst(str_replace('_', ' ', (string) $key)),
                is_scalar($value) ? (string) $value : json_encode($value),
            );
        }

        return self::SUCCESS;
    }
}
