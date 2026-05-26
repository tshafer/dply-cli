<?php

declare(strict_types=1);

namespace App\Commands\Operator;

use App\Commands\BaseCommand;

class SummaryCommand extends BaseCommand
{
    protected $signature = 'operator:summary
        {--json : Print the raw API response as JSON.}';

    protected $description = 'Show the operator identity + organization summary (fleet.operator scope).';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get('/api/v1/operator/summary'));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $operator = is_array($data['operator'] ?? null) ? $data['operator'] : [];
        $org = is_array($data['organization'] ?? null) ? $data['organization'] : [];

        foreach ([
            'Operator' => $operator['name'] ?? null,
            'Email' => $operator['email'] ?? null,
            'Role' => $operator['role'] ?? null,
            'Organization' => $org['name'] ?? null,
            'Org ID' => $org['id'] ?? null,
            'Plan' => $org['plan'] ?? null,
        ] as $label => $value) {
            if ($value !== null && $value !== '') {
                $this->components->twoColumnDetail($label, (string) $value);
            }
        }

        // Any other top-level keys not yet broken out get a generic dump
        // so we don't silently swallow fields when the API grows.
        foreach ($data as $key => $value) {
            if (in_array($key, ['operator', 'organization'], true) || ! is_scalar($value)) {
                continue;
            }
            $this->components->twoColumnDetail(ucfirst((string) $key), (string) $value);
        }

        return self::SUCCESS;
    }
}
