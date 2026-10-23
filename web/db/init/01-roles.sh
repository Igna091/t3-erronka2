#!/usr/bin/env bash
# Least-privilege roles for the stack (issue #18). Runs once, on the first start with an
# empty volume; passwords come from Compose secrets, never from the repo.
#   migrator - runs Laravel's migrations (the migrate service): owns the tables, the only role with DDL
#   app      - the Laravel site: reads and writes rows in the migrator's tables, no DDL
#   mailer   - the Python task (#22): granted SELECT on four user columns by a Laravel migration
set -e

psql -v ON_ERROR_STOP=1 --username "${POSTGRES_USER:-postgres}" --dbname "$POSTGRES_DB" \
    -v migrator_pw="$(< /run/secrets/db_migrator_password)" \
    -v app_pw="$(< /run/secrets/db_app_password)" \
    -v mailer_pw="$(< /run/secrets/db_mailer_password)" <<'SQL'
REVOKE ALL ON DATABASE guenaga FROM PUBLIC;

CREATE ROLE migrator LOGIN PASSWORD :'migrator_pw';
CREATE ROLE app LOGIN PASSWORD :'app_pw';
CREATE ROLE mailer LOGIN PASSWORD :'mailer_pw';

GRANT CONNECT ON DATABASE guenaga TO migrator, app, mailer;
GRANT USAGE, CREATE ON SCHEMA public TO migrator;
GRANT USAGE ON SCHEMA public TO app, mailer;

-- Every table and sequence the migrator creates is usable by app, but only for rows
ALTER DEFAULT PRIVILEGES FOR ROLE migrator IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app;
ALTER DEFAULT PRIVILEGES FOR ROLE migrator IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO app;
SQL
