#!/bin/bash
# The web app's own MySQL user. It may read and write rows in bdweb and nothing else: no
# CREATE/DROP/ALTER, no FILE, no other database, and only from the backend network. The
# original connected as root with no password. The password comes from a Docker secret.
# MySQL's entrypoint runs this once, after 01-bdweb.sql, on the first start with an empty volume.
set -euo pipefail

app_password="$(< /run/secrets/db_app_password)"
root_password="$(< /run/secrets/db_root_password)"

# root's password reaches the client in a file descriptor, never on its command line. The
# entrypoint's setup server listens only on its socket.
mysql --defaults-extra-file=<(printf '[client]\nuser=root\npassword="%s"\n' "$root_password") \
    --protocol=socket --database=mysql <<SQL
CREATE USER 'bdweb_app'@'172.29.40.%' IDENTIFIED BY '${app_password//\'/\'\'}';
GRANT SELECT, INSERT, UPDATE, DELETE ON bdweb.* TO 'bdweb_app'@'172.29.40.%';
SQL
