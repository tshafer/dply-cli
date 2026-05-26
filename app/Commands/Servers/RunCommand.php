<?php

declare(strict_types=1);

namespace App\Commands\Servers;

use App\Commands\BaseCommand;

class RunCommand extends BaseCommand
{
    protected $signature = 'servers:run
        {server : Server ID.}
        {command* : Command to run remotely (everything after the server id).}
        {--user=root : SSH user to invoke as.}';

    protected $description = 'Run an ad-hoc shell command on a server via the dply remote-exec API.';

    public function handle(): int
    {
        $cmd = implode(' ', (array) $this->argument('command'));
        if (trim($cmd) === '') {
            $this->components->error('Provide a command to execute, e.g. dply servers:run SERVER_ID -- uptime');

            return self::FAILURE;
        }

        $data = $this->safely(fn () => $this->client()->post(
            "/api/v1/servers/{$this->argument('server')}/run-command",
            ['command' => $cmd, 'user' => $this->option('user')],
        ));
        if ($data === null) {
            return self::FAILURE;
        }

        $stdout = (string) ($data['stdout'] ?? $data['output'] ?? '');
        $stderr = (string) ($data['stderr'] ?? '');
        $exit = (int) ($data['exit_code'] ?? 0);

        if ($stdout !== '') {
            $this->line($stdout);
        }
        if ($stderr !== '') {
            $this->getOutput()->getErrorOutput()->writeln($stderr);
        }

        return $exit === 0 ? self::SUCCESS : self::FAILURE;
    }
}
