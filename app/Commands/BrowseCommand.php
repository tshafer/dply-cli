<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\ConfigStore;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Interactive entry point. The user just runs `dply` (or `dply browse`)
 * and walks a tree: pick a surface (edge/servers/sites/insights), pick
 * a resource via fuzzy search, pick an action, get JSON-pretty output
 * or jump into a sub-flow. Stays inside the menu loop until the user
 * chooses Quit.
 *
 * Everything here is a thin wrapper over the same DplyClient the
 * top-level commands use, so the browse flow can't drift from the
 * non-interactive commands.
 */
class BrowseCommand extends BaseCommand
{
    protected $signature = 'browse';

    protected $description = 'Interactive TUI: log in once, then browse and act on Edge sites, servers, and more.';

    public function handle(ConfigStore $config): int
    {
        $host = $this->host();
        if (! $config->tokenFor($host)) {
            note("Not logged in to {$host}.");
            if (! confirm('Run `dply login` now?', default: true)) {
                return self::FAILURE;
            }
            $this->call('login', ['--host' => $host]);
        }

        info("Connected to {$host}.");

        while (true) {
            $surface = select(
                label: 'Browse',
                options: [
                    'edge' => 'Edge — static / SSG / SSR sites',
                    'servers' => 'Servers',
                    'sites' => 'Sites (server-hosted)',
                    'insights' => 'Insights summary',
                    'quit' => 'Quit',
                ],
                default: 'edge',
            );

            $continue = match ($surface) {
                'edge' => $this->browseEdge(),
                'servers' => $this->browseServers(),
                'sites' => $this->browseSites(),
                'insights' => (bool) $this->call('insights:summary'),
                'quit' => false,
            };

            if ($surface === 'quit' || $continue === false) {
                return self::SUCCESS;
            }
        }
    }

    private function browseEdge(): bool
    {
        $sites = $this->fetchList('/api/v1/edge/sites');
        if ($sites === null) {
            return true;
        }
        if ($sites === []) {
            note('No Edge sites yet — create one in the dashboard or `dply` web UI.');

            return true;
        }

        $selected = search(
            label: 'Pick an Edge site',
            options: fn (string $needle) => $this->filterById($sites, $needle, fn (array $s) => sprintf(
                '%s   %s   [%s]',
                str_pad((string) ($s['name'] ?? ''), 28),
                $s['live_url'] ?? '',
                $s['status'] ?? '',
            )),
            placeholder: 'Type to filter',
        );

        $action = select(
            label: 'Action',
            options: [
                'show' => 'Show details',
                'deploy' => 'Trigger a deploy',
                'deployments' => 'List recent deployments',
                'env' => 'List env vars',
                'logs' => 'Recent logs',
                'domains' => 'List custom domains',
                'previews' => 'List previews',
                'usage' => 'Usage stats',
                'purge' => 'Purge cache',
                'back' => '← Back',
            ],
            default: 'show',
        );

        if ($action === 'back') {
            return true;
        }

        $args = ['site' => $selected];
        if ($action === 'deploy') {
            $branch = text('Branch (blank to use the site default)', required: false);
            if ($branch !== '') {
                $args['--branch'] = $branch;
            }
            $commit = text('Commit SHA (blank for branch HEAD)', required: false);
            if ($commit !== '') {
                $args['--commit'] = $commit;
            }
        }

        $this->call("edge:{$action}", $args);

        return true;
    }

    private function browseServers(): bool
    {
        $servers = $this->fetchList('/api/v1/servers');
        if ($servers === null || $servers === []) {
            if ($servers === []) {
                note('No servers visible to this token.');
            }

            return true;
        }

        $selected = search(
            label: 'Pick a server',
            options: fn (string $needle) => $this->filterById($servers, $needle, fn (array $s) => sprintf(
                '%s   %s   %s',
                str_pad((string) ($s['name'] ?? ''), 28),
                $s['ip_address'] ?? $s['ip'] ?? '',
                $s['status'] ?? '',
            )),
            placeholder: 'Type to filter',
        );

        $action = select(
            label: 'Action',
            options: [
                'firewall' => 'Show firewall rules',
                'run' => 'Run a remote command',
                'back' => '← Back',
            ],
            default: 'firewall',
        );

        if ($action === 'back') {
            return true;
        }
        if ($action === 'run') {
            $cmd = text('Command', placeholder: 'uptime', required: true);
            $this->call('servers:run', ['server' => $selected, 'command' => preg_split('/\s+/', $cmd)]);

            return true;
        }

        $this->call("servers:{$action}", ['server' => $selected]);

        return true;
    }

    private function browseSites(): bool
    {
        $sites = $this->fetchList('/api/v1/sites');
        if ($sites === null || $sites === []) {
            if ($sites === []) {
                note('No server-hosted sites visible to this token.');
            }

            return true;
        }

        $selected = search(
            label: 'Pick a site',
            options: fn (string $needle) => $this->filterById($sites, $needle, fn (array $s) => sprintf(
                '%s   %s   [%s]',
                str_pad((string) ($s['name'] ?? ''), 28),
                $s['server']['name'] ?? $s['server_name'] ?? '',
                $s['status'] ?? '',
            )),
            placeholder: 'Type to filter',
        );

        $action = select(
            label: 'Action',
            options: [
                'deployments' => 'List deployments',
                'deploy' => 'Trigger deploy',
                'back' => '← Back',
            ],
            default: 'deployments',
        );

        if ($action === 'back') {
            return true;
        }

        $this->call("sites:{$action}", ['site' => $selected]);

        return true;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function fetchList(string $path): ?array
    {
        $data = $this->safely(fn () => $this->client()->get($path));
        if ($data === null) {
            return null;
        }
        $rows = is_array($data['data'] ?? null) ? $data['data'] : $data;

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /**
     * Filter rows on a fuzzy substring match against id+name+other text,
     * mapping the survivors back to {id: label} for Prompts to render.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): string  $label
     * @return array<string, string>
     */
    private function filterById(array $rows, string $needle, callable $label): array
    {
        $needle = strtolower(trim($needle));
        $out = [];
        foreach ($rows as $row) {
            $hay = strtolower(json_encode($row, JSON_UNESCAPED_SLASHES) ?: '');
            if ($needle !== '' && ! str_contains($hay, $needle)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $out[$id] = $label($row);
        }

        return $out;
    }
}
