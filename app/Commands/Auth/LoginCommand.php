<?php

declare(strict_types=1);

namespace App\Commands\Auth;

use App\Commands\BaseCommand;
use App\Services\ConfigStore;
use App\Services\DeviceFlow;
use App\Services\DplyClient;
use Illuminate\Http\Client\Factory as HttpFactory;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class LoginCommand extends BaseCommand
{
    protected $signature = 'login
        {--no-browser : Print the login URL instead of trying to open a browser.}';

    protected $description = 'Authenticate with a dply instance using the device-flow login.';

    public function handle(ConfigStore $config, DeviceFlow $deviceFlow, HttpFactory $http): int
    {
        $host = $this->option('host')
            ?: text(
                label: 'dply host',
                placeholder: 'https://dply.app',
                default: $config->defaultHost() ?? 'https://dply.app',
                required: true,
                validate: fn (string $v) => filter_var(rtrim(trim($v), '/'), FILTER_VALIDATE_URL) ? null : 'Enter a valid http(s) URL.',
            );
        $host = $config->normalizeHost($host);

        $started = $this->safely(fn () => $deviceFlow->start($host));
        if ($started === null) {
            return self::FAILURE;
        }

        // Lead with the user-facing one-time code so the operator can
        // verify it matches what they see on the web side — protects
        // against phishing-style "approve this device for me" attacks.
        info(sprintf('Your one-time code: %s', $started['user_code']));
        note('Approve the login on the dply web app:');
        note('  '.$started['verification_uri_complete']);
        $this->newLine();

        if (! $this->option('no-browser')) {
            $this->openInBrowser($started['verification_uri_complete']);
        }

        $token = spin(
            message: 'Waiting for browser approval…',
            callback: fn () => $this->pollUntilDone($deviceFlow, $host, $started),
        );

        if ($token === null) {
            return self::FAILURE;
        }

        // Fetch + cache the operator profile in the same call so
        // `dply whoami` doesn't need a round-trip on every invocation.
        $user = $this->fetchOperatorProfile($http, $host, $token);

        $config->setHost($host, $token, $user, makeDefault: true);

        $name = is_string($user['name'] ?? null) ? $user['name'] : ($user['email'] ?? 'unknown user');
        $this->components->info(sprintf('Logged in to %s as %s.', $host, $name));

        return self::SUCCESS;
    }

    /**
     * @param  array{device_code: string, expires_in: int, interval: int}  $started
     */
    private function pollUntilDone(DeviceFlow $deviceFlow, string $host, array $started): ?string
    {
        $deadline = time() + $started['expires_in'];
        $interval = max(2, $started['interval']);

        while (time() < $deadline) {
            sleep($interval);
            $result = $deviceFlow->poll($host, $started['device_code']);

            switch ($result['status']) {
                case DeviceFlow::STATUS_AUTHORIZED:
                    return $result['token'] ?? null;

                case DeviceFlow::STATUS_DENIED:
                    $this->components->error('Login was denied in the browser.');

                    return null;

                case DeviceFlow::STATUS_EXPIRED:
                    $this->components->error('Login code expired before approval — run `dply login` again.');

                    return null;

                case DeviceFlow::STATUS_PENDING:
                default:
                    continue 2;
            }
        }

        $this->components->error('Login timed out waiting for approval — run `dply login` again.');

        return null;
    }

    private function openInBrowser(string $url): void
    {
        $command = match (PHP_OS_FAMILY) {
            'Darwin' => ['open', $url],
            'Windows' => ['cmd', '/c', 'start', $url],
            default => ['xdg-open', $url],
        };

        // Suppress output — if no browser opener exists on the box,
        // the user already has the URL printed above.
        @exec(implode(' ', array_map('escapeshellarg', $command)).' > /dev/null 2>&1 &');
    }

    /**
     * Lightweight profile sniff — uses /api/v1/operator/summary because
     * it's the cheapest authed endpoint that returns identity info,
     * and the route is already part of the v1 API surface. Failure here
     * is non-fatal; we just save the token without a profile and let
     * `dply whoami` fill it in on demand.
     *
     * @return array<string, mixed>
     */
    private function fetchOperatorProfile(HttpFactory $http, string $host, string $token): array
    {
        try {
            $response = $http
                ->acceptJson()
                ->withToken($token)
                ->timeout(10)
                ->get(rtrim($host, '/').'/api/v1/operator/summary');

            if ($response->successful()) {
                $body = (array) $response->json();
                $user = is_array($body['operator'] ?? null) ? $body['operator'] : [];

                return [
                    'name' => $user['name'] ?? null,
                    'email' => $user['email'] ?? null,
                    'organization' => is_array($body['organization'] ?? null) ? ($body['organization']['name'] ?? null) : null,
                ];
            }
        } catch (\Throwable) {
            // Best-effort — operator/summary requires the fleet.operator
            // middleware which most CLI tokens won't have. That's fine.
        }

        return [];
    }
}
