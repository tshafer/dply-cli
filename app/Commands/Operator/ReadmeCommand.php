<?php

declare(strict_types=1);

namespace App\Commands\Operator;

use App\Commands\BaseCommand;

class ReadmeCommand extends BaseCommand
{
    protected $signature = 'operator:readme
        {--raw : Print the raw markdown without any pretty-printing.}';

    protected $description = 'Show the operator README (onboarding / runbook served by the dply instance).';

    public function handle(): int
    {
        $data = $this->safely(fn () => $this->client()->get('/api/v1/operator/readme'));
        if ($data === null) {
            return self::FAILURE;
        }

        $markdown = (string) ($data['markdown'] ?? $data['body'] ?? $data['content'] ?? '');
        if ($markdown === '') {
            // Some endpoints just return a flat array of sections — fall
            // through to a JSON dump so the operator at least sees what
            // the server is sending.
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($this->option('raw')) {
            $this->line($markdown);

            return self::SUCCESS;
        }

        // Tiny markdown rendering: bold headings, dim horizontal rules.
        // Anything richer (lists, links) would mean pulling another dep
        // — we keep it lean and let users pass --raw + pipe to glow / mdcat.
        foreach (preg_split('/\r?\n/', $markdown) ?: [] as $line) {
            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m) === 1) {
                $this->line('<fg=cyan;options=bold>'.$m[2].'</>');
            } elseif (preg_match('/^[-=]{3,}$/', $line) === 1) {
                $this->line('<fg=gray>'.str_repeat('─', 60).'</>');
            } else {
                $this->line($line);
            }
        }

        return self::SUCCESS;
    }
}
