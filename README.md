# dply CLI

Command-line interface for the [dply](https://dply.app) deployment platform.
Manage Edge sites, deployments, env vars, custom domains, servers and more
from your terminal.

Written in PHP on top of [Laravel Zero](https://laravel-zero.com) +
[Laravel Prompts](https://laravel.com/docs/prompts). Ships as a single
self-contained PHAR.

## Install

```bash
curl -fsSL https://raw.githubusercontent.com/tshafer/dply-cli/main/install.sh | bash
```

The installer downloads the latest tagged `dply.phar` from the GitHub
releases page and drops it at `/usr/local/bin/dply`. Requires PHP 8.2+ on
your `$PATH`.

Pin a specific version:

```bash
DPLY_VERSION=v0.1.0 \
  curl -fsSL https://raw.githubusercontent.com/tshafer/dply-cli/main/install.sh | bash
```

Install to a custom location (no sudo needed):

```bash
DPLY_INSTALL_DIR="$HOME/.local/bin" \
  curl -fsSL https://raw.githubusercontent.com/tshafer/dply-cli/main/install.sh | bash
```

## Quickstart

```bash
# 1. Log in. The CLI opens dply.app in your browser; you approve, the
#    token lands in ~/.dply/config.json.
dply login

# 2. Drop into the interactive browse menu — pick a surface, fuzzy-search
#    a resource, pick an action.
dply browse

# 3. Or use the explicit commands directly.
dply edge:sites
dply edge:deploy 01ksh9zqsp9pje0fx7h6h2386a
dply edge:env 01ksh9zqsp9pje0fx7h6h2386a --set DATABASE_URL=postgres://…
dply edge:logs 01ksh9zqsp9pje0fx7h6h2386a --tail
dply edge:lint ./dply.yaml
```

## Command reference

```
dply login                       # device-flow login
dply logout                      # forget the current host's token
dply whoami                      # show host + token preview + profile
dply browse                      # interactive TUI

dply edge:sites                  # list Edge sites
dply edge:show SITE              # detailed view of one site
dply edge:deploy SITE [--branch ..] [--commit ..]
dply edge:deployments SITE
dply edge:deployment SITE DEPLOYMENT
dply edge:rollback SITE DEPLOYMENT
dply edge:env SITE [--set K=V] [--unset K] [--from-file .env]
dply edge:logs SITE [--tail] [--since ISO]
dply edge:lint [path]            # POST contents to /api/v1/edge/lint
dply edge:domains SITE [--add host] [--verify host] [--remove host]
dply edge:aliases SITE
dply edge:access SITE [--mode none|password|dply-account|email] [--password …] [--allowed-email …]
dply edge:previews SITE [--create branch] [--delete id] [--promote id]
dply edge:purge SITE [paths…]
dply edge:usage SITE

dply servers:list
dply servers:run SERVER -- command…
dply servers:firewall SERVER [--apply] [--template name] [--bundled key]

dply sites:list
dply sites:deploy SITE [--wait] [--timeout=900]
dply sites:deployments SITE
dply sites:deployment SITE DEPLOYMENT

dply insights:summary
dply insights:server SERVER

dply imports:migrations
dply imports:migration MIGRATION

dply operator:summary
dply operator:readme [--raw]
```

Every command takes `--host=https://your.dply.example` to target an
instance other than your stored default — useful for switching between
local (`https://dplyi.test`) and prod (`https://dply.app`).

## Authentication

`dply login` uses the OAuth-style device authorization flow against
`POST /api/v1/auth/device/start` + `/poll`. The token is stored in
`~/.dply/config.json` (mode `0600`, directory `0700`) keyed by host, so
you can be logged into multiple instances simultaneously.

`whoami` shows host, masked token, and cached profile.

## GitHub Action

Deploy a server-hosted site from CI:

```yaml
name: Deploy
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: tshafer/dply-cli@v1
        with:
          token: ${{ secrets.DPLY_TOKEN }}
          site: 01ksh9zqsp9pje0fx7h6h2386a
```

| Input | Default | |
|---|---|---|
| `token` | (required) | API token with the `sites.deploy` ability. Store it as a secret. |
| `site` | (required) | Site ID (ULID). A slug works only if no other server has a site with the same slug. |
| `host` | `https://dply.io` | dply instance URL. |
| `wait` | `true` | Wait for the deployment to finish. The step fails if it fails, is skipped, or times out. |
| `timeout` | `900` | Seconds to wait. |
| `php-version` | `8.3` | PHP used to run the CLI (8.2+). |

The action installs PHP, runs the CLI from the action's own checkout and calls
`dply sites:deploy SITE --wait`. The site deploys its configured branch, so
`ref`/`sha` inputs are not supported. If push-to-deploy is also enabled on the
site, every push deploys twice, so turn one of them off.

Outside the action, set `DPLY_TOKEN` (and optionally `DPLY_HOST`) to use the CLI
without `dply login`. The env token takes precedence over the stored login.

## Local development

```bash
git clone https://github.com/tshafer/dply-cli
cd dply-cli
composer install

# Run directly from source:
./dply --version
./dply edge:sites --host=https://dplyi.test

# Build a PHAR locally:
php dply app:build dply --build-version=local --no-interaction
ls -lh builds/
```

PHAR releases are produced by `.github/workflows/release.yml` on tag
push (`vX.Y.Z`) — builds + uploads `dply.phar` and `install.sh` to the
matching GitHub release.

## License

MIT.
