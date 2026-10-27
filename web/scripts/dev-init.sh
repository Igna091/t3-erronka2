#!/usr/bin/env bash
# Creates the machine-specific secrets the stack reads from web/secrets/ (gitignored):
# Laravel's APP_KEY, five database passwords, a self-signed TLS certificate for dev, and the
# mailer's SMTP login and Mailpit's STARTTLS certificate (issue #22).
# On web01 the certificates come from the Infra TLS CA instead (issue #10).
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

for name in db_admin_password db_migrator_password db_app_password db_mailer_password db_reminder_password; do
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

# The mailer's SMTP password, and the same login in Mailpit's password file
if [[ ! -s secrets/mail_password || ! -s secrets/mailpit_auth ]]; then
    password="$(openssl rand -hex 24 | tr -d '\r\n')"
    printf '%s' "$password" > secrets/mail_password
    printf 'mailer:%s\n' "$password" > secrets/mailpit_auth
    echo "created secrets/mail_password and secrets/mailpit_auth"
fi

# Mailpit's STARTTLS certificate for the name "mailpit", signed by a throwaway CA. The mailer
# trusts only that CA (mail_ca.crt), and the CA's key is deleted as soon as it has signed.
# Relative paths throughout, because Git for Windows' openssl can't read /tmp paths.
if [[ ! -s secrets/mailpit.key || ! -s secrets/mailpit.crt || ! -s secrets/mail_ca.crt ]]; then
    work="$(mktemp -d secrets/.ca.XXXXXX)"
    trap 'rm -rf "$work"' EXIT
    cat > "$work/openssl.cnf" <<'EOF'
[req]
distinguished_name = dn
[dn]
[ca]
basicConstraints = critical, CA:TRUE, pathlen:0
keyUsage = critical, keyCertSign, cRLSign
subjectKeyIdentifier = hash
[mailpit]
basicConstraints = critical, CA:FALSE
keyUsage = critical, digitalSignature
extendedKeyUsage = serverAuth
subjectAltName = DNS:mailpit
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid:always
EOF
    openssl req -x509 -config "$work/openssl.cnf" -extensions ca \
        -newkey ec -pkeyopt ec_paramgen_curve:P-256 -sha256 -days 90 -nodes \
        -subj "/CN=Guenaga dev mail CA" -keyout "$work/ca.key" -out secrets/mail_ca.crt 2> /dev/null
    openssl req -new -config "$work/openssl.cnf" \
        -newkey ec -pkeyopt ec_paramgen_curve:P-256 -nodes \
        -subj "/CN=mailpit" -keyout secrets/mailpit.key -out "$work/mailpit.csr" 2> /dev/null
    openssl x509 -req -in "$work/mailpit.csr" -CA secrets/mail_ca.crt -CAkey "$work/ca.key" \
        -set_serial "0x$(openssl rand -hex 16 | tr -d '\r\n')" -sha256 -days 90 \
        -extfile "$work/openssl.cnf" -extensions mailpit -out secrets/mailpit.crt 2> /dev/null
    echo "created secrets/mailpit.crt and secrets/mailpit.key, signed by secrets/mail_ca.crt (90 days)"
fi

# Where Mailpit forwards a copy of every email: empty means nowhere. To see the emails in a real
# inbox, fill it in as README.md shows.
if [[ ! -e secrets/mailpit_forward.yaml ]]; then
    : > secrets/mailpit_forward.yaml
    echo "created secrets/mailpit_forward.yaml (empty: Mailpit forwards nothing)"
fi

# The containers run as other users (nginx 101, www-data 82, postgres 999, mailer 10001,
# Mailpit 65534) and must read these.
# Fine for throwaway dev secrets; on web01 grant read access per file instead (see README.md).
chmod 644 secrets/*
echo "secrets ready in web/secrets/"
