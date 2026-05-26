<?php

declare(strict_types=1);

namespace App\Commands\Edge;

use App\Commands\BaseCommand;

class PurgeCommand extends BaseCommand
{
    protected $signature = 'edge:purge
        {site : Edge site ID.}
        {paths?* : One or more paths to purge (default: full site purge).}';

    protected $description = 'Purge the edge cache for a site (or specific paths).';

    public function handle(): int
    {
        $site = $this->argument('site');
        $paths = (array) $this->argument('paths');

        $data = $this->safely(fn () => $this->client()->post(
            "/api/v1/edge/sites/{$site}/cache/purge",
            $paths === [] ? [] : ['paths' => $paths],
        ));
        if ($data === null) {
            return self::FAILURE;
        }

        $this->components->info($paths === []
            ? "Full purge queued for {$site}."
            : sprintf('Purged %d path(s) on %s.', count($paths), $site));

        return self::SUCCESS;
    }
}
