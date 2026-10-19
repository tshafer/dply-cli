<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    putenv('DPLY_TOKEN=ci-token');
    putenv('DPLY_HOST=https://dply.test');
});

afterEach(function () {
    putenv('DPLY_TOKEN');
    putenv('DPLY_HOST');
});

function deploymentsPage(array ...$rows): array
{
    return ['data' => $rows];
}

it('waits for the new API-triggered deployment and succeeds', function () {
    $old = ['id' => '01OLD', 'trigger' => 'webhook', 'status' => 'success'];
    Http::fake([
        'dply.test/api/v1/sites/my-site/deploy' => Http::response(['message' => 'Deployment queued.'], 202),
        'dply.test/api/v1/sites/my-site/deployments*' => Http::sequence()
            ->push(deploymentsPage($old))
            ->push(deploymentsPage(['id' => '01HOOK', 'trigger' => 'webhook', 'status' => 'failed'], $old))
            ->push(deploymentsPage(['id' => '01NEW', 'trigger' => 'api', 'status' => 'running'], $old))
            ->push(deploymentsPage(['id' => '01NEW', 'trigger' => 'api', 'status' => 'success'], $old)),
    ]);

    $this->artisan('sites:deploy', ['site' => 'my-site', '--wait' => true, '--interval' => 0])
        ->assertExitCode(0);

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer ci-token'));
});

it('fails when the deployment fails', function () {
    Http::fake([
        'dply.test/api/v1/sites/my-site/deploy' => Http::response(['message' => 'Deployment queued.'], 202),
        'dply.test/api/v1/sites/my-site/deployments*' => Http::sequence()
            ->push(deploymentsPage())
            ->push(deploymentsPage(['id' => '01NEW', 'trigger' => 'api', 'status' => 'failed', 'exit_code' => 1, 'log_output' => 'boom'])),
    ]);

    $this->artisan('sites:deploy', ['site' => 'my-site', '--wait' => true, '--interval' => 0])
        ->expectsOutputToContain('boom')
        ->assertExitCode(1);
});

it('fails when the deployment is skipped', function () {
    Http::fake([
        'dply.test/api/v1/sites/my-site/deploy' => Http::response(['message' => 'Deployment queued.'], 202),
        'dply.test/api/v1/sites/my-site/deployments*' => Http::sequence()
            ->push(deploymentsPage())
            ->push(deploymentsPage(['id' => '01NEW', 'trigger' => 'api', 'status' => 'skipped'])),
    ]);

    $this->artisan('sites:deploy', ['site' => 'my-site', '--wait' => true, '--interval' => 0])
        ->assertExitCode(1);
});

it('times out when no deployment shows up', function () {
    Http::fake([
        'dply.test/api/v1/sites/my-site/deploy' => Http::response(['message' => 'Deployment queued.'], 202),
        'dply.test/api/v1/sites/my-site/deployments*' => Http::response(deploymentsPage()),
    ]);

    $this->artisan('sites:deploy', ['site' => 'my-site', '--wait' => true, '--interval' => 1, '--timeout' => 1])
        ->expectsOutputToContain('Timed out')
        ->assertExitCode(1);
});
