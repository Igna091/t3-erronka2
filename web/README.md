# web

The Guenaga website for web01 (10.20.10.10, U1-DMZIT): Laravel 13 on PHP 8.5.12 with PostgreSQL 18,
issues #18–#21.

```
WAN :80/:443 ──► nginx ──► php-fpm 8.5.12 (Laravel) ──► PostgreSQL 18.6
                 │  frontend network  │  backend network (internal: no route out)  │
```

| Service | Image | Runs as | Networks | Role |
|---|---|---|---|---|
| nginx | `nginx:1.30.6` | nginx (101) | frontend, backend | Only service publishing ports. TLS 1.2/1.3, security headers, POST rate limit, runs nothing but Laravel's `public/index.php` |
| php | built from `php/Dockerfile` (`runtime` stage) | www-data (33) | backend | Laravel with `vendor/` baked in; no Composer in the image |
| migrate | same image | www-data (33) | backend | One-shot `php artisan migrate` as the `migrator` role, then exits; php starts only after it succeeds |
| db | `postgres:18.6-trixie` | postgres (999) | backend | Accepts only the stack's roles, only from the backend subnet |

Every service runs with a read-only root filesystem, `cap_drop: ALL`, `no-new-privileges`,
memory, CPU and PID limits and rotated logs. All images are pinned by digest. Laravel can write
only to tmpfs (`storage/framework`, `storage/logs`) and to the uploads volume (`storage/app`, outside `public/`).

## Run it locally

You need Docker Desktop (or Docker Engine), plus Git Bash on Windows. In PowerShell, `bash` may
start WSL instead of Git Bash, so run the script from a Git Bash window.

```sh
cd web
bash scripts/dev-init.sh              # APP_KEY, passwords and a self-signed cert in web/secrets/ (gitignored)
docker compose up -d --build --wait
```

Open https://localhost and accept the self-signed certificate.

- **Reset the database:** `docker compose down -v` deletes the data and the uploads, so `db/init` runs again on the next start.
- **Ports 80/443 taken:** set `HTTP_PORT` and `HTTPS_PORT` (PowerShell: `$env:HTTPS_PORT=8443`), then browse to https://localhost:8443 directly, because the HTTP redirect always points at 443.

## Developing

Add the dev file to every command (or set `COMPOSE_FILE=compose.yaml:compose.dev.yaml`; on Windows separate them with `;`):

```sh
docker compose -f compose.yaml -f compose.dev.yaml up -d --build --wait   # code from ./laravel, live, APP_DEBUG on
docker compose -f compose.yaml -f compose.dev.yaml run --rm tools composer require laravel/fortify
docker compose -f compose.yaml -f compose.dev.yaml run --rm tools php artisan make:controller ProfileController
docker compose -f compose.yaml -f compose.dev.yaml run --rm tools php vendor/bin/phpunit
docker compose run --rm migrate                                   # apply new migrations
docker compose run --rm migrate php artisan migrate:fresh --force # dev only: drop and recreate every table
```

`tools` is the `dev` stage of the image: the same PHP plus Composer, with `./laravel` mounted
read-write, so nobody needs PHP installed locally. Run `composer install` through it after cloning, so `laravel/vendor` exists for the live mount.
Configuration comes from `compose.yaml`, not `.env`; secrets are read from `/run/secrets` with
`env_secret()` ([laravel/app/helpers.php](laravel/app/helpers.php)), so they never appear in the environment.

## Checks

| Check | Command | Expected |
|---|---|---|
| PHP2Shell fixed (#18) | `docker compose exec php php -v` | `PHP 8.5.12` |
| No version leak (#20) | `curl -skI https://localhost` | no `X-Powered-By`; HSTS, CSP, nosniff and frame headers present |
| Session cookie | same | `__Host-gfsid=…; secure; httponly; samesite=strict` |
| Only Laravel's front controller runs | `curl -sk -o /dev/null -w '%{http_code}' https://localhost/x.php` | `404` |
| HTTP redirects | `curl -sI http://localhost` | `301` to `https://` |
| Old TLS refused | `curl -sk --tls-max 1.1 https://localhost` | handshake fails |
| php has no internet | `docker compose exec php php -r 'var_dump(@fsockopen("1.1.1.1", 443));'` | `bool(false)` |
| Passwords are Argon2id | `docker compose exec db psql -U postgres -d guenaga -c 'SELECT left(password, 10) FROM users'` | `$argon2id$` |

In Git Bash, prefix commands that contain container paths (such as `/run/secrets`) with
`MSYS_NO_PATHCONV=1`, or Git Bash rewrites them into Windows paths.

`disable_functions` blocks `proc_open` and friends for the CLI too, so a few artisan commands that
shell out (`about`, `schedule:run`, `db`) fail inside the runtime container. Run them through `tools` instead.

## Layout

| Path | What |
|---|---|
| `compose.yaml` | The stack. `compose.dev.yaml` adds the live-code override and `tools` |
| `php/Dockerfile` | Stages `base` → `dev` (tools) → `vendor` (composer install) → `runtime` (web01) |
| `php/conf.d/zz-hardening.ini` | `expose_php`, `open_basedir`, `disable_functions`, upload limits |
| `php/fpm/zz-pool.conf` | Worker pool, `.php` only |
| `nginx/nginx.conf` | Unprivileged Nginx on 8080/8443, TLS, front-controller routing, rate limit |
| `nginx/snippets/security-headers.conf` | HSTS, CSP, frame, referrer and permissions headers |
| `db/pg_hba.conf` | Who may connect to PostgreSQL, and from where |
| `db/init/01-roles.sh` | Creates the roles, once, on an empty volume |
| `laravel/` | The Laravel application; the schema lives in `laravel/database/migrations` |
| `scripts/dev-init.sh` | Creates `secrets/` |

## Database roles

| Role | Can do | Used by |
|---|---|---|
| `postgres` | Everything, but only from inside the db container: `docker compose exec db psql -U postgres -d guenaga` | Admins |
| `migrator` | Owns the tables; the only role with DDL | The `migrate` service |
| `app` | Read and write rows in the migrator's tables; no DDL | The Laravel site |
| `mailer` | `SELECT` on `id`, `name`, `email`, `profile_image` of `users` only (granted by a migration) | Python task (#22) |

## Moving to web01

1. Apply the host baseline in [docker/README.md](../docker/README.md).
2. Put the Infra TLS CA certificate and key for `www.guenaga.eus` (#10) in `secrets/tls.crt` and `secrets/tls.key`, then run `scripts/dev-init.sh`. It keeps existing files and only creates the key and passwords.
3. Lock the folder: as root, `chmod 700 secrets` and leave the files `0644`. Host users can't open the folder, but Docker bind-mounts each file on its own, so the containers can still read them, including under `userns-remap`.
4. Set `APP_URL=https://www.guenaga.eus` and `server_name www.guenaga.eus;` in `nginx/nginx.conf`.
5. Once CI/CD lands (#21), web01 pulls the PHP image from GHCR instead of building it.

## Next

- **#19:** build the site and client portal in `laravel/`. Laravel Fortify (`composer require laravel/fortify`) provides login, registration, password reset, login throttling and TOTP two-factor without a JavaScript build. Store photos on the private `local` disk and serve them through an authorised route. Use a self-hosted CAPTCHA, since php has no route to the internet; `gd` with FreeType is in the image.
- **#20:** add ModSecurity with the OWASP CRS, for example by swapping Nginx for `owasp/modsecurity-crs:nginx`, which also runs unprivileged on 8080/8443. It is OWASP's image rather than a Docker Official Image, so record that exception. Save the `testssl.sh` and header results in `evidence/`.
- **#21:** if the site adds Vite assets, CI builds them and Nginx must get `public/build` from the image instead of the bind mount.
- **#22:** the mailer container joins the backend network as the `mailer` role, with Mailpit alongside it for the demo.
