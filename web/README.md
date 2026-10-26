# web

The Guenaga website for web01 (10.20.10.10, U1-DMZIT): Laravel 13 on PHP 8.5.12 with PostgreSQL 18,
and the Python mailer that sends its emails, issues #18–#22.

```
WAN :80/:443 ──► nginx ──► php-fpm 8.5.12 (Laravel) ──► PostgreSQL 18.6 ◄── mailer (Python) ──STARTTLS──► Mailpit ──► 127.0.0.1:8025
                 │  frontend network  │  backend network (internal: no route out)          │  mail network (internal)  │
```

| Service | Image | Runs as | Networks | Role |
|---|---|---|---|---|
| nginx | `nginx:1.30.6` | nginx (101) | frontend, backend | Only service publishing ports. TLS 1.2/1.3, security headers, POST rate limit, runs nothing but Laravel's `public/index.php` |
| php | built from `php/Dockerfile` (`runtime` stage) | www-data (33) | backend | Laravel with `vendor/` baked in; no Composer in the image |
| migrate | same image | www-data (33) | backend | One-shot `php artisan migrate` as the `migrator` role, then exits; php starts only after it succeeds |
| db | `postgres:18.6-trixie` | postgres (999) | backend | Accepts only the stack's roles, only from the backend subnet |
| mailer | built from [../python/Dockerfile](../python/Dockerfile) (`runtime` stage) | mailer (10001) | backend, mail | Sends the emails the site queues, over STARTTLS ([../python/README.md](../python/README.md)) |
| mailpit | `axllent/mailpit:v1.32.0` | nobody (65534) | mail, mail_inbox | Demo only: catches every email and shows it at http://localhost:8025, published on 127.0.0.1 only. Third-party image, not a Docker Official Image |

Every service runs with a read-only root filesystem, `cap_drop: ALL`, `no-new-privileges`,
memory, CPU and PID limits and rotated logs. All images are pinned by digest. Laravel can write
only to tmpfs (`storage/framework`, `storage/logs`) and to the uploads volume (`storage/app`, outside `public/`).
`mail_inbox` exists only to publish Mailpit's inbox, since Docker can't publish ports from an
internal network. It gives Mailpit a route out, so Mailpit's version check and remote CSS and fonts are off.

## Run it locally

You need Docker Desktop (or Docker Engine), plus Git Bash on Windows. In PowerShell, `bash` may
start WSL instead of Git Bash, so run the script from a Git Bash window.

```sh
cd web
bash scripts/dev-init.sh              # APP_KEY, passwords and dev certificates in web/secrets/ (gitignored)
docker compose up -d --build --wait
```

Open https://localhost and accept the self-signed certificate. The emails the shop sends arrive
in Mailpit at http://localhost:8025. Mailpit keeps them in memory, so restarting it empties the inbox.

- **Reset the database:** `docker compose down -v` deletes the data and the uploads, so `db/init` runs again on the next start.
- **Ports 80/443 taken:** set `HTTP_PORT` and `HTTPS_PORT` (PowerShell: `$env:HTTPS_PORT=8443`), then browse to https://localhost:8443 directly, because the HTTP redirect always points at 443. Mailpit's port is `MAILPIT_PORT`.
- **After pulling a change that adds secrets:** run `scripts/dev-init.sh` again; it only creates what's missing.

## Developing

Add the dev file to every command (or set `COMPOSE_FILE=compose.yaml:compose.dev.yaml`; on Windows separate them with `;`):

```sh
docker compose -f compose.yaml -f compose.dev.yaml up -d --build --wait   # code from ./laravel, live, APP_DEBUG on
docker compose -f compose.yaml -f compose.dev.yaml run --rm tools composer require laravel/fortify
docker compose -f compose.yaml -f compose.dev.yaml run --rm tools php artisan make:controller ProfileController
docker compose -f compose.yaml -f compose.dev.yaml run --rm tools php vendor/bin/phpunit
docker compose -f compose.yaml -f compose.dev.yaml run --rm mailer-tests   # the mailer's tests (Python)
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
| Order emails go out (#22) | Place an order at https://localhost/shop, then open http://localhost:8025 | The confirmation within seconds; `docker compose logs mailer` shows `Sent email #…` |
| Mailpit refuses plain text | `docker compose exec mailer python -c "import smtplib; smtplib.SMTP('mailpit', 1025).sendmail('a@guenaga.eus', ['b@example.com'], 'x')"` | `530 … Must issue a STARTTLS command first` |
| The mailer can't read orders | `docker compose exec db psql -U postgres -d guenaga -c 'SET ROLE mailer' -c 'SELECT email FROM orders'` | `permission denied for table orders` |
| The mailer has no internet | `docker compose exec mailer python -c "import socket; socket.create_connection(('1.1.1.1', 443), 4)"` | `Network is unreachable` |
| A down mail server is logged, not a crash | `docker compose stop mailpit`, place an order, `docker compose logs mailer` | `Can't reach the mail server at mailpit:1025: …`, no traceback; `docker compose start mailpit` and it goes out |

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
| `../python/` | The mailer: its code, tests and Dockerfile |
| `scripts/dev-init.sh` | Creates `secrets/`, including Mailpit's certificate and the throwaway CA the mailer trusts |

## The public site

The landing page comes from the team's design and is plain Blade, CSS and JavaScript, with no build step:

| What | Where |
|---|---|
| Phone, address, opening hours, legal name, licence | `laravel/config/pharmacy.php`; the `[bracketed]` values are the design's placeholders |
| Page markup | `laravel/resources/views/home.blade.php`, inside `layouts/site.blade.php` |
| Header and footer of every page | `laravel/resources/views/partials/`; the footer carries the EU online-pharmacy logo and authorisation that Spain requires on every page of a site selling medicines (`[placeholders]` in `config/pharmacy.php`) |
| Capsule illustration | `laravel/resources/views/components/capsule.blade.php`, animated by `laravel/public/js/site.js` |
| Styles | `laravel/public/css/site.css` |
| Font | Archivo, self-hosted in `laravel/public/fonts/archivo/` (SIL Open Font License, copy beside it) |

"Open now" and "Open until" are worked out from `pharmacy.hours` in Madrid time
(`laravel/app/Support/OpeningHours.php`). The CSP blocks inline `style=""`, inline `<script>`,
`on…=` handlers and files from other sites, so nothing on the page uses them;
`laravel/tests/Feature/HomePageTest.php` fails if one creeps in.

## The shop

Medicines that don’t need a prescription, ordered online and collected at the counter (no delivery).
It is a demo: orders are real rows, payments are simulated.

| What | Where |
|---|---|
| Catalogue: 8 medicines, example prices | `laravel/database/seeders/MedicineSeeder.php`, applied on every deploy by the `migrate` service (`migrate --seed`) |
| Pack limit, demo cards, `[placeholders]` | `laravel/config/shop.php` |
| Basket: in the session, at most 2 packs of a medicine | `laravel/app/Support/Basket.php` |
| Pages | `laravel/resources/views/shop/`, styles in `public/css/shop.css`, the card animation in `public/js/shop.js` |
| Tests | `laravel/tests/Feature/ShopTest.php` |

Before anything goes live, check every medicine on CIMA (only non-prescription medicines may be
sold online) and copy each dose and warning from its package leaflet; the seeder's `[…]` texts
are placeholders on purpose.

What the pentest and the GRC work should know:

- **No accounts:** only the browser session that placed an order can see it or pay for it. Anyone
  else gets a 404, even with the order reference.
- **Health answers** (age, pregnancy, other medicines, allergies) are encrypted in the database with
  the APP_KEY (Laravel’s `encrypted` casts) and only stored with the customer’s consent.
- **Simulated card payment:** only the two demo card numbers in `config/shop.php` are accepted, so a
  real card is never used. Only the last 4 digits are stored, and card fields are never flashed back
  into the session after a form error. The bank’s check uses a random code per payment shown on
  the simulated phone; three wrong codes decline the payment. Card and code attempts are limited to
  10 a minute per session, and the card fields have `autocomplete="off"` so browsers don’t offer
  saved real cards.
- Nginx allows 30 POSTs a minute per IP, so a shopping session fits under the limit.
- **Confirmation email:** once an order is paid by card or is to be paid at the counter, the site
  queues an email in `email_outbox`, in the same transaction, holding only what the email shows
  (no health answers). The Python mailer sends it over STARTTLS and empties the row once it's
  sent; see [../python/README.md](../python/README.md).

## Database roles

| Role | Can do | Used by |
|---|---|---|
| `postgres` | Everything, but only from inside the db container: `docker compose exec db psql -U postgres -d guenaga` | Admins |
| `migrator` | Owns the tables; the only role with DDL | The `migrate` service |
| `app` | Read and write rows in the migrator's tables; no DDL | The Laravel site |
| `mailer` | `SELECT` on the content columns of `email_outbox` and `UPDATE` on its delivery columns; `SELECT` on `id`, `name`, `email`, `profile_image` of `users`. Nothing else, so not `orders` (granted by migrations) | The Python mailer (#22) |

## Moving to web01

1. Apply the host baseline in [docker/README.md](../docker/README.md).
2. Put the Infra TLS CA certificate and key for `www.guenaga.eus` (#10) in `secrets/tls.crt` and `secrets/tls.key`, then run `scripts/dev-init.sh`. It keeps existing files and only creates the key and passwords.
3. Lock the folder: as root, `chmod 700 secrets` and leave the files `0644`. Host users can't open the folder, but Docker bind-mounts each file on its own, so the containers can still read them, including under `userns-remap`.
4. Set `APP_URL=https://www.guenaga.eus` and `server_name www.guenaga.eus;` in `nginx/nginx.conf`.
5. Mail: for the demo, Mailpit stays, and its inbox is reached through an SSH tunnel
   (`ssh -L 8025:127.0.0.1:8025 web01`). Its certificate (`secrets/mailpit.crt` and `.key`, for the
   name `mailpit`) can come from the Infra TLS CA, with that CA's certificate in
   `secrets/mail_ca.crt`. To deliver real email, point the mailer's `MAIL_*` settings at the relay
   (with its CA, or none for a public one) and remove `mailpit`.
6. Once CI/CD lands (#21), web01 pulls the PHP and mailer images from GHCR instead of building them.

## Next

- **#19:** the landing page is done; next is the client portal in `laravel/`, which the "Send a prescription" buttons should point to (they jump to "How a refill works" for now). Laravel Fortify (`composer require laravel/fortify`) provides login, registration, password reset, login throttling and TOTP two-factor without a JavaScript build. Store photos on the private `local` disk and serve them through an authorised route. Use a self-hosted CAPTCHA, since php has no route to the internet; `gd` with FreeType is in the image.
- **#20:** add ModSecurity with the OWASP CRS, for example by swapping Nginx for `owasp/modsecurity-crs:nginx`, which also runs unprivileged on 8080/8443. It is OWASP's image rather than a Docker Official Image, so record that exception. Save the `testssl.sh` and header results in `evidence/`.
- **#21:** if the site adds Vite assets, CI builds them and Nginx must get `public/build` from the image instead of the bind mount.
- **#22:** the mailer sends the order emails. The brief's reminder to users without a profile image
  needs the client portal's accounts first; [../python/README.md](../python/README.md) has the plan.
