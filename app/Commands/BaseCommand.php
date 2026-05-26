<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\ConfigStore;
use App\Services\DplyApiException;
use App\Services\DplyClient;
use LaravelZero\Framework\Commands\Command;

abstract class BaseCommand extends Command
{
    /**
     * Every command accepts --host so a user can target a non-default
     * dply instance for a single invocation (e.g. switch between
     * https://dply.app and https://dplyi.test) without re-logging-in.
     */
    public function configure(): void
    {
        parent::configure();
        $this->addOption(
            'host',
            null,
            \Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED,
            'Override the dply host for this command (e.g. https://dplyi.test). Defaults to your stored login.',
        );
    }

    protected function host(): string
    {
        $flag = $this->option('host');

        return app(ConfigStore::class)->resolveHost(is_string($flag) && $flag !== '' ? $flag : null);
    }

    protected function client(): DplyClient
    {
        return DplyClient::forActive($this->option('host') ?: null);
    }

    /**
     * Wrap an API-touching closure so commands don't have to duplicate
     * the try/catch + render boilerplate. Returns the closure's value
     * on success, or `null` on caught failure (after printing).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callable
     * @return TReturn|null
     */
    protected function safely(callable $callable): mixed
    {
        try {
            return $callable();
        } catch (DplyApiException $e) {
            $this->components->error($e->getMessage());
            if (is_array($e->body) && ! empty($e->body['errors'])) {
                foreach ((array) $e->body['errors'] as $field => $messages) {
                    foreach ((array) $messages as $message) {
                        $this->components->warn(sprintf('  %s: %s', $field, $message));
                    }
                }
            }

            return null;
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return null;
        }
    }
}
