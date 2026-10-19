#!/usr/bin/env bash
# Entry point for the dply GitHub Action (see action.yml). Inputs arrive
# only through env vars and are validated before they reach the CLI.
set -euo pipefail

fail() { echo "::error::$*"; exit 1; }

[ -n "${DPLY_TOKEN:-}" ] || fail "The 'token' input is required."
echo "::add-mask::${DPLY_TOKEN}"

SITE="${INPUT_SITE:-}"
HOST="${INPUT_HOST:-}"
WAIT="${INPUT_WAIT:-true}"
TIMEOUT="${INPUT_TIMEOUT:-900}"

# No leading '-' (would parse as a CLI option) and no '/' (lands in the URL path).
[[ "$SITE" =~ ^[A-Za-z0-9][A-Za-z0-9._-]*$ ]] || fail "Invalid 'site' input: use the site ID or slug."
[[ "$HOST" =~ ^https?://[A-Za-z0-9.-]+(:[0-9]+)?/?$ ]] || fail "Invalid 'host' input: expected e.g. https://dply.io."
[[ "$WAIT" == "true" || "$WAIT" == "false" ]] || fail "Invalid 'wait' input: expected true or false."
[[ "$TIMEOUT" =~ ^[0-9]+$ ]] || fail "Invalid 'timeout' input: expected seconds."

export DPLY_HOST="$HOST"
args=(sites:deploy "$SITE" --no-interaction)
if [ "$WAIT" = "true" ]; then
  args+=(--wait "--timeout=$TIMEOUT")
fi

php "$GITHUB_ACTION_PATH/dply" "${args[@]}"
