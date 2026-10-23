#!/usr/bin/env bash
# Creates the machine-specific secrets the stack reads from web/secrets/ (gitignored):
# Laravel's APP_KEY, four database passwords and a self-signed TLS certificate for dev.
# On web01 the TLS pair comes from the Infra TLS CA instead (issue #10).
# Existing files are kept, so running it again is safe.
# Works on Linux and in Git Bash on Windows (Git for Windows ships openssl).
set -euo pipefail

# Git Bash rewrites arguments starting with "/" (like -subj) into Windows paths; stop it
export MSYS_NO_PATHCONV=1

cd "$(dirname "$0")/.."
mkdir -p secrets

# Git for Windows' openssl ends lines with \r\n, so every value strips both

if [[ ! -s secrets/app_key ]]; then
    # Same format as php artisan key:generate
    printf 'base64:%s' "$(openssl rand -base64 32 | tr -d '\r\n')" > secrets/app_key
    echo "created secrets/app_key"
fi

for name in db_admin_password db_migrator_password db_app_password db_mailer_password; do
    if [[ ! -s secrets/$name ]]; then
        openssl rand -hex 24 | tr -d '\r\n' > "secrets/$name"
        echo "created secrets/$name"
    fi
done

if [[ ! -s secrets/tls.key ]]; then
    openssl req -x509 -newkey rsa:2048 -sha256 -days 90 -nodes \
        -keyout secrets/tls.key -out secrets/tls.crt \
        -subj "/CN=localhost" \
        -addext "subjectAltName=DNS:localhost,DNS:www.guenaga.eus,IP:127.0.0.1" 2> /dev/null
    echo "created secrets/tls.crt and secrets/tls.key (self-signed, 90 days)"
fi

# The containers run as other users (nginx 101, www-data 33, postgres 999) and must read these.
# Fine for throwaway dev secrets; on web01 grant read access per file instead (see README.md).
chmod 644 secrets/*
echo "secrets ready in web/secrets/"
