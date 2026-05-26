<?php

declare(strict_types=1);

namespace App\Support;

use LaravelZero\Framework\Commands\Command;

/**
 * Shared formatting helpers so every command renders dates / statuses
 * the same way. Kept small on purpose — anything richer should live
 * in the calling command, not a util.
 */
class Tables
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $columns
     */
    public static function dump(Command $command, array $rows, array $columns): void
    {
        if ($rows === []) {
            $command->components->info('No results.');

            return;
        }

        $command->table(
            $columns,
            array_map(static function (array $row) use ($columns): array {
                $out = [];
                foreach ($columns as $col) {
                    $key = strtolower(str_replace([' ', '-'], '_', $col));
                    $value = $row[$key] ?? '';
                    $out[] = is_scalar($value) || $value === null ? (string) ($value ?? '—') : json_encode($value);
                }

                return $out;
            }, $rows),
        );
    }

    public static function relativeTime(?string $iso): string
    {
        if ($iso === null || $iso === '') {
            return '—';
        }

        try {
            $when = new \DateTimeImmutable($iso);
        } catch (\Throwable) {
            return $iso;
        }

        $diff = time() - $when->getTimestamp();
        $absoluteDiff = abs($diff);
        $suffix = $diff >= 0 ? 'ago' : 'from now';

        return match (true) {
            $absoluteDiff < 60 => $absoluteDiff.'s '.$suffix,
            $absoluteDiff < 3600 => floor($absoluteDiff / 60).'m '.$suffix,
            $absoluteDiff < 86400 => floor($absoluteDiff / 3600).'h '.$suffix,
            $absoluteDiff < 30 * 86400 => floor($absoluteDiff / 86400).'d '.$suffix,
            default => $when->format('Y-m-d'),
        };
    }
}
