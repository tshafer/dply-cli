#!/usr/bin/env bash
#
# Installer for the dply CLI.
#
#   curl -fsSL https://raw.githubusercontent.com/tshafer/dply-cli/main/install.sh | bash
#
# Downloads the latest tagged dply.phar from the GitHub releases page
# and drops it at /usr/local/bin/dply (override with DPLY_INSTALL_DIR).
# Requires PHP 8.2+ on $PATH.
#
# Env knobs:
#   DPLY_INSTALL_DIR   destination directory (default: /usr/local/bin)
#   DPLY_VERSION       tag to install (default: latest)
#   DPLY_GH_REPO       owner/repo to pull from (default: tshafer/dply-cli)

set -euo pipefail

REPO="${DPLY_GH_REPO:-tshafer/dply-cli}"
INSTALL_DIR="${DPLY_INSTALL_DIR:-/usr/local/bin}"
VERSION="${DPLY_VERSION:-latest}"
BIN_NAME="dply"

red()    { printf '\033[31m%s\033[0m\n' "$*"; }
green()  { printf '\033[32m%s\033[0m\n' "$*"; }
yellow() { printf '\033[33m%s\033[0m\n' "$*"; }
note()   { printf '\033[2m%s\033[0m\n' "$*"; }

require() {
  command -v "$1" >/dev/null 2>&1 || { red "Required command not found: $1"; exit 1; }
}

require curl

# PHP version check — laravel-zero 12 requires PHP 8.2+.
if ! command -v php >/dev/null 2>&1; then
  red "PHP is not installed."
  echo "Install PHP 8.2+ first:"
  echo "  macOS:  brew install php"
  echo "  Ubuntu: sudo apt-get install -y php8.3-cli php8.3-mbstring php8.3-curl"
  exit 1
fi

PHP_VERSION_RAW="$(php -r 'echo PHP_VERSION;' 2>/dev/null || true)"
PHP_MAJOR="$(echo "$PHP_VERSION_RAW" | cut -d. -f1)"
PHP_MINOR="$(echo "$PHP_VERSION_RAW" | cut -d. -f2)"
if [ -z "$PHP_VERSION_RAW" ] || [ "$PHP_MAJOR" -lt 8 ] || { [ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 2 ]; }; then
  red "PHP 8.2+ required (found: ${PHP_VERSION_RAW:-none})."
  exit 1
fi

# Resolve the release tag → asset URL.
if [ "$VERSION" = "latest" ]; then
  note "Resolving latest release of ${REPO}…"
  TAG_LINE="$(curl -fsSL "https://api.github.com/repos/${REPO}/releases/latest" | grep '"tag_name"' || true)"
  if [ -z "$TAG_LINE" ]; then
    red "Could not find a 'latest' release for ${REPO}."
    echo "Set DPLY_VERSION=vX.Y.Z to install a specific tag."
    exit 1
  fi
  VERSION="$(echo "$TAG_LINE" | sed -E 's/.*"tag_name": "([^"]+)".*/\1/')"
fi

ASSET_URL="https://github.com/${REPO}/releases/download/${VERSION}/dply.phar"
note "Downloading ${ASSET_URL}"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT
TMP_PHAR="${TMP_DIR}/dply.phar"

if ! curl -fL --progress-bar -o "$TMP_PHAR" "$ASSET_URL"; then
  red "Download failed — check that ${VERSION} has a dply.phar asset."
  exit 1
fi

# Quick sanity: the PHAR must run at least its --version.
chmod +x "$TMP_PHAR"
if ! php "$TMP_PHAR" --version >/dev/null 2>&1; then
  red "Downloaded PHAR did not execute. Re-run with DPLY_VERSION=<specific-tag>?"
  exit 1
fi

DEST="${INSTALL_DIR%/}/${BIN_NAME}"

# Make sure the dest dir exists; prompt for sudo if we lack write perms.
if [ ! -d "$INSTALL_DIR" ]; then
  yellow "Install dir ${INSTALL_DIR} does not exist — creating it."
  if ! mkdir -p "$INSTALL_DIR" 2>/dev/null; then
    sudo mkdir -p "$INSTALL_DIR"
  fi
fi

if [ -w "$INSTALL_DIR" ]; then
  mv "$TMP_PHAR" "$DEST"
  chmod +x "$DEST"
else
  yellow "Need sudo to write to ${INSTALL_DIR}."
  sudo mv "$TMP_PHAR" "$DEST"
  sudo chmod +x "$DEST"
fi

green "Installed ${BIN_NAME} ${VERSION} → ${DEST}"
echo
echo "Try it:"
echo "  dply --version"
echo "  dply login --host=https://dply.app"
echo "  dply browse"
