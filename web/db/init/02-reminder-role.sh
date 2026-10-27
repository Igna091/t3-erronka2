#!/usr/bin/env bash
# The photo reminder's role (#22): reminder may read five columns of users and nothing else,
# and its sessions are read-only. Runs after 01-roles.sh on the first start with an empty volume.
# On a database that already exists, run it once by hand; it does nothing twice:
#   docker compose exec db bash /docker-entrypoint-initdb.d/02-reminder-role.sh
set -e

psql -v ON_ERROR_STOP=1 --username "${POSTGRES_USER:-postgres}" --dbname "$POSTGRES_DB" \
    -v reminder_pw="$(< /run/secrets/db_reminder_password)" <<'SQL'
SELECT format('CREATE ROLE reminder LOGIN PASSWORD %L', :'reminder_pw')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'reminder') \gexec

-- Even a statement it had the grants for couldn't write
ALTER ROLE reminder SET default_transaction_read_only = on;

GRANT CONNECT ON DATABASE guenaga TO reminder;
GRANT USAGE ON SCHEMA public TO reminder;

-- A new database has no users table yet: a Laravel migration grants this once it exists
SELECT 'GRANT SELECT (id, name, email, profile_image, email_verified_at) ON users TO reminder'
WHERE to_regclass('public.users') IS NOT NULL \gexec
SQL
