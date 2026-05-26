<?php

declare(strict_types=1);

namespace App\Commands\Imports;

use App\Commands\BaseCommand;

class MigrationCommand extends BaseCommand
{
    protected $signature = 'imports:migration
        {migration : Migration ID.}
        {--json : Print the raw API response as JSON.}';

    protected $description = 'Show full detail for a single import migration.';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get('/api/v1/imports/migrations/'.$this->argument('migration')));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;

        foreach ($payload as $key => $value) {
            // Items / log can be large structures — keep the summary
            // view scalar-only; --json is the escape hatch for full
            // payloads. Mirrors how the dashboard's detail page works.
            if (is_array($value)) {
                $this->components->twoColumnDetail(
                    ucfirst(str_replace('_', ' ', (string) $key)),
                    sprintf('(%d %s)', count($value), is_array($value[array_key_first($value)] ?? null) ? 'rows' : 'items'),
                );

                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $this->components->twoColumnDetail(
                ucfirst(str_replace('_', ' ', (string) $key)),
                is_scalar($value) ? (string) $value : json_encode($value),
            );
        }

        return self::SUCCESS;
    }
}
