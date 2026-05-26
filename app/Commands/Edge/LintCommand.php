<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;

class LintCommand extends BaseCommand
{
    protected $signature = 'edge:lint
        {path? : Path to dply.yaml (defaults to ./dply.yaml).}';

    protected $description = 'Validate a local dply.yaml against the server-side linter.';

    public function handle(): int
    {
        $path = (string) ($this->argument('path') ?? './dply.yaml');
        if (! is_file($path)) {
            // Try the common variants before giving up so `dply edge:lint`
            // in a repo root that uses dply.yml / dply.json still works.
            foreach (['./dply.yaml', './dply.yml', './dply.json'] as $candidate) {
                if (is_file($candidate)) {
                    $path = $candidate;
                    break;
                }
            }
        }

        if (! is_file($path)) {
            $this->components->error("Config file not found: {$path}");

            return self::FAILURE;
        }

        $raw = (string) file_get_contents($path);
        $data = $this->safely(fn () => $this->client()->post('/api/v1/edge/lint', [
            'path' => basename($path),
            'content' => $raw,
        ]));

        if ($data === null) {
            return self::FAILURE;
        }

        $ok = (bool) ($data['ok'] ?? false);
        $errors = (array) ($data['errors'] ?? []);
        $warnings = (array) ($data['warnings'] ?? []);

        $this->components->twoColumnDetail('File', $path);
        $this->components->twoColumnDetail('Result', $ok ? '<fg=green>ok</>' : '<fg=red>failed</>');
        $this->components->twoColumnDetail('Errors', (string) count($errors));
        $this->components->twoColumnDetail('Warnings', (string) count($warnings));

        foreach ($errors as $err) {
            $this->components->error((string) $err);
        }
        foreach ($warnings as $warn) {
            $this->components->warn((string) $warn);
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
