#!/usr/bin/env bash
# Creates the secrets compose.yaml reads from secrets/ (ignored by git): MySQL's root password,
# the app's MySQL password, and a self-signed TLS certificate for localhost. Existing files are
# kept, so running it again is safe. Works on Linux and in Git Bash on Windows.
set -euo pipefail

# Git Bash rewrites arguments starting with "/" (like -subj) into Windows paths; stop it
export MSYS_NO_PATHCONV=1

cd "$(dirname "$0")/.."
mkdir -p secrets

# Git for Windows' openssl ends lines with \r\n, so every value strips both
for name in db_root_password db_app_password; do
    if [[ ! -s secrets/$name ]]; then
        openssl rand -hex 24 | tr -d '\r\n' > "secrets/$name"
        echo "created secrets/$name"
    fi
done

if [[ ! -s secrets/tls.key ]]; then
    openssl req -x509 -newkey rsa:2048 -sha256 -days 90 -nodes \
        -keyout secrets/tls.key -out secrets/tls.crt \
        -subj "/CN=localhost" \
        -addext "subjectAltName=DNS:localhost,IP:127.0.0.1" 2> /dev/null
    echo "created secrets/tls.crt and secrets/tls.key (self-signed, 90 days)"
fi

# The containers run as www-data (33) and mysql (999) and must read these. Fine for a
# throwaway local setup; on a real server grant read access per file instead.
chmod 644 secrets/*
echo "secrets ready in secrets/"
