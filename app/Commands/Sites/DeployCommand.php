<?php

declare(strict_types=1);

namespace App\Commands\Sites;

use App\Commands\BaseCommand;

class DeployCommand extends BaseCommand
{
    protected $signature = 'sites:deploy
        {site : Site ID.}
        {--wait : Block until the deployment finishes; exit non-zero if it fails.}
        {--timeout=900 : Seconds to wait before giving up (with --wait).}
        {--interval=5 : Seconds between status polls (with --wait).}';

    protected $description = 'Trigger a deploy on a server-hosted site.';

    public function handle(): int
    {
        $site = (string) $this->argument('site');
        $wait = (bool) $this->option('wait');

        // The deploy endpoint answers 202 without a deployment id, so snapshot
        // the existing ids first and look for a new API-triggered row.
        $seen = [];
        if ($wait) {
            $rows = $this->safely(fn () => $this->recentDeployments($site));
            if ($rows === null) {
                return self::FAILURE;
            }
            $seen = array_column($rows, 'id');
        }

        $data = $this->safely(fn () => $this->client()->post("/api/v1/sites/{$site}/deploy"));
        if ($data === null) {
            return self::FAILURE;
        }

        if (! $wait) {
            $deployment = is_array($data['data'] ?? null) ? $data['data'] : $data;
            $this->components->info(sprintf(
                'Deploy queued: %s (status: %s)',
                $deployment['id'] ?? '(unknown)',
                $deployment['status'] ?? 'queued',
            ));

            return self::SUCCESS;
        }

        $this->components->info('Deploy queued. Waiting for it to finish…');

        return $this->waitForDeployment($site, $seen);
    }

    /**
     * @param  list<string>  $seen  deployment ids that existed before the deploy
     */
    private function waitForDeployment(string $site, array $seen): int
    {
        $deadline = time() + max(1, (int) $this->option('timeout'));
        $interval = max(0, (int) $this->option('interval'));

        while (true) {
            $rows = $this->safely(fn () => $this->recentDeployments($site)) ?? [];
            $mine = array_values(array_filter(
                $rows,
                static fn (array $d): bool => ! in_array($d['id'] ?? null, $seen, true) && ($d['trigger'] ?? null) === 'api',
            ));
            // Rows come newest-first; the oldest new one is ours.
            $deployment = $mine === [] ? null : end($mine);
            $status = $deployment['status'] ?? null;

            if ($status === 'success') {
                $this->components->info(sprintf('Deployment %s finished: %s', $deployment['id'], $status));

                return self::SUCCESS;
            }

            // A skipped deploy (billing pause, deploy window, already running)
            // shipped nothing, so CI should not go green on it.
            if (in_array($status, ['failed', 'skipped'], true)) {
                $this->components->error(sprintf('Deployment %s %s (exit code %s).', $deployment['id'], $status, $deployment['exit_code'] ?? '-'));
                $log = trim((string) ($deployment['log_output'] ?? ''));
                if ($log !== '') {
                    $this->line($log);
                }

                return self::FAILURE;
            }

            if (time() >= $deadline) {
                $this->components->error('Timed out waiting for the deployment to finish.');

                return self::FAILURE;
            }

            sleep($interval);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentDeployments(string $site): array
    {
        $data = $this->client()->get("/api/v1/sites/{$site}/deployments", ['limit' => 10]);

        return array_values(array_filter((array) ($data['data'] ?? []), 'is_array'));
    }
}
