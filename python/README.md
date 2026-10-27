# python

The mailer (issue #22): a Python service that sends the emails the website queues, over SMTP
with TLS:

| Kind | When |
|---|---|
| `order_confirmation` | A shop order is paid by card or is to be paid at the counter |
| `verify_email` | An account is opened, or its email changed: the link that confirms the address |
| `reset_password` | Someone asks for a new password: the link to choose one |
| `security_alert` | A login from a new browser, a password changed or reset, 2-step verification turned on or off, a recovery code used |
| `photo_reminder` | Every Monday, to each user with a confirmed email and no profile photo ([The photo reminder](#the-photo-reminder)) |

It runs as the `mailer` service of
[web/compose.yaml](../web/compose.yaml), next to Mailpit, which catches the emails for the demo.
The photo reminder is the same image run once a week as the `photo-reminder` job.

```
Laravel ── same transaction as the payment ──► email_outbox ◄── mailer ── STARTTLS + login ──► Mailpit
                                                (PostgreSQL)    (Python)                       http://localhost:8025
```

## How it works

1. When an order is settled, the site adds a row to `email_outbox` in the same database
   transaction, so there is an email exactly when there is a settled order, never one without the
   other. The row holds only what the email shows: name and address, medicines, total, payment
   and the pharmacy's details. The health answers are not in it. Account emails are queued the
   same way, holding the name, the address and the link, or for an alert, what happened, when, and
   from which browser and IP.
2. Every 5 seconds the mailer locks the oldest row that is due (`FOR UPDATE SKIP LOCKED`), builds
   the email from `mailer/templates/<kind>/`, sends it, records how it went and, once it's sent,
   empties the row's payload: the order still has the details, and a confirmation or reset link
   leaves the database with it.
3. If the mail server turns an email down for now (4xx), the mailer tries again after 1 minute,
   then 2, 4… up to an hour, 8 attempts in all. If it refuses it for good (5xx), or the row can't
   become an email, the mailer gives up on that one email (`failed_at`) and goes on with the rest.
4. If the database or the mail server is down, the mailer logs one line saying so, without a
   traceback, and tries again after 5 seconds, then 10, 20… up to a minute. That costs the
   email no attempts. After a minute without a successful pass the health check fails, and after
   three failed checks (30 seconds apart) Docker marks the container unhealthy.

Delivery is at least once: if the mailer dies between sending an email and recording it, the email
goes again. Every attempt carries the same `Message-ID`, so mail clients show it once.

## Security

| What | How |
|---|---|
| Database | The `mailer` role may `SELECT` the outbox's content columns and `UPDATE` only its delivery columns ([migration](../web/laravel/database/migrations/2026_10_26_100002_create_email_outbox_table.php)). It can't read `users` or `orders`, see which order or account a row belongs to, add or delete rows, or create tables. The photo reminder logs in as `reminder` instead, which may only read five columns of `users`, in read-only sessions |
| TLS | STARTTLS on every connection (`MAIL_SCHEME=smtps` for TLS from the first byte); there is no unencrypted setting. The certificate must chain to `MAIL_CA_FILE` and match `MAIL_HOST`, with Python's strict X.509 checks. A server that doesn't offer STARTTLS gets nothing |
| Mailpit | Requires STARTTLS and the mailer's login; refuses plain text, a login before TLS and a wrong password. Its inbox listens on 127.0.0.1 only and answers only to `localhost` Host headers, against DNS rebinding. Optionally it forwards a copy of every email to the team's own inbox (web/README.md); the mailer itself still has no route out |
| Injection | The HTML is autoescaped, so a name or a medicine can't add markup or links. A line break or other control character in a name or address fails that email instead of adding headers such as `Bcc`. The templates are files in the image; the payload is only data |
| Logs | The email's number, kind and order reference, or the reminded user's number; never a name or an address |
| Container | Unprivileged user (uid 10001), read-only root filesystem, no pip, all capabilities dropped, memory, CPU and PID limits. Its two networks are internal, so it has no route out |
| Supply chain | Base image pinned by digest; every library installed from a wheel whose hash is in `requirements.txt` |

## Files

| Path | What |
|---|---|
| `mailer/cli.py` | `python -m mailer`: the loop, one pass with `--once`, or the photo reminder with `--remind-photos` |
| `mailer/worker.py` | One pass over the outbox: send, retry or give up |
| `mailer/outbox.py` | The outbox's SQL |
| `mailer/reminders.py` | The photo reminder: the selection, the email and the run |
| `mailer/database.py` | The connection to PostgreSQL, and its errors turned into one clear message |
| `mailer/message.py`, `mailer/render.py` | The email: headers, then subject, text and HTML from the templates |
| `mailer/smtp.py` | `smtplib` with STARTTLS, certificate checks and the login |
| `mailer/config.py` | Settings from the environment |
| `mailer/heartbeat.py` | Docker's health check |
| `mailer/templates/order_confirmation/` | The order email: `subject.txt`, `body.txt`, `body.html` |
| `mailer/templates/verify_email/`, `reset_password/`, `security_alert/`, `photo_reminder/` | The account emails; the HTML extends `_layout/account.html`, and an alert's subject and first sentence depend on the event |
| `tests/` | Unit tests; the mail server is faked, and so is the database, except that the photo reminder's selection runs on SQLite |
| `Dockerfile` | `base` → `dev` (pytest) → `runtime` |

## Settings

| Variable | Example | |
|---|---|---|
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` | `db`, `5432`, `guenaga`, `mailer` | |
| `DB_PASSWORD_FILE` | `/run/secrets/db_mailer_password` | Or `DB_PASSWORD` |
| `MAIL_HOST`, `MAIL_PORT` | `mailpit`, `1025` | Port defaults to 587, or 465 with `smtps` |
| `MAIL_SCHEME` | `smtp` | `smtp` (STARTTLS) or `smtps` (TLS) |
| `MAIL_CA_FILE` | `/run/secrets/mail_ca_crt` | Unset: the system's CAs |
| `MAIL_USERNAME`, `MAIL_PASSWORD_FILE` | `mailer`, `/run/secrets/mail_password` | Unset username: no login |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `no-reply@guenaga.eus`, `Guenaga Farmazeutika` | |
| `MAILER_POLL_SECONDS` | `5` | Default 10 |
| `MAILER_MAX_ATTEMPTS` | `8` | Default 8 |
| `SITE_URL` | `https://www.guenaga.eus` | Only the photo reminder needs it, for its link to the profile page |
| `LOG_LEVEL` | `info` | |

## Running it

From `web/`; it starts with the rest of the stack.

```sh
docker compose logs -f mailer                      # what it sends
docker compose run --rm mailer --once              # one pass, then exit: status 1 if the database or the mail server is down
docker compose -f compose.yaml -f compose.dev.yaml run --rm mailer-tests   # the unit tests, without a network
```

With `compose.dev.yaml` the mailer runs the code from `python/mailer`, so
`docker compose -f compose.yaml -f compose.dev.yaml restart mailer` picks up an edit.

Emails it gave up on keep their payload and the server's last answer:

```sh
docker compose exec db psql -U postgres -d guenaga -c "SELECT id, kind, attempts, last_error FROM email_outbox WHERE failed_at IS NOT NULL"
docker compose exec db psql -U postgres -d guenaga -c "UPDATE email_outbox SET failed_at = NULL, attempts = 0, next_attempt_at = NULL WHERE id = 3"   # try it again
```

## Adding an email

1. Add `mailer/templates/<kind>/` with `subject.txt`, `body.txt` and `body.html` (inline styles, no
   images or web fonts, like the order email).
2. In Laravel, queue a row with that `kind` in the transaction that makes the change, as
   `Order::queueConfirmationEmail()` and `App\Support\AccountEmails` do.
3. Add tests for both sides.

## Updating the libraries

Change the version in `requirements.in` or `requirements-dev.in`, then regenerate the pinned files
with their hashes, from `python/` (in Git Bash, prefix it with `MSYS_NO_PATHCONV=1` and use
`$(pwd -W)` for `$PWD`):

```sh
docker run --rm -v "$PWD:/src" -w /src python:3.14.9-slim-trixie sh -c '
  pip install -q pip-tools &&
  pip-compile -q --generate-hashes --strip-extras --allow-unsafe --no-emit-index-url -o requirements.txt requirements.in &&
  pip-compile -q --generate-hashes --strip-extras --allow-unsafe --no-emit-index-url -o requirements-dev.txt requirements-dev.in'
```

## The photo reminder

Once a week, every user whose email is confirmed and whose account has no photo
(`profile_image` NULL, empty or only spaces) gets a personal email with a button to the profile
page. Unconfirmed addresses are left out, or anyone could have the pharmacy write every week to
an address they typed in.

- **Read-only:** it logs in as `reminder`, which may only read `id`, `name`, `email`,
  `profile_image` and `email_verified_at` of `users`, and whose sessions are read-only
  ([02-reminder-role.sh](../web/db/init/02-reminder-role.sh)), so it can't change anything. That
  also means it can't remember whom it wrote to: a user gets one every Monday until the account
  has a photo.
- **Sending:** the same code as the other emails, so smtplib over STARTTLS with the certificate
  checks. A user the mail server turns down is skipped until the next week; if the database or
  the mail server is down, the run logs one line and exits with status 1.
- **The selection** is one SQL query (`WITHOUT_PHOTO` in `mailer/reminders.py`). The unit tests
  run that same query on SQLite, with users with and without photos, confirmed or not.
- **Schedule:** on web01 a systemd timer runs it on Mondays at 10:00, Madrid time
  ([../web/systemd/](../web/systemd/), installed as [DEPLOY.md](../web/DEPLOY.md) shows).

From `web/`:

```sh
docker compose run --rm photo-reminder        # one run now, as the timer does it
```

On a database created before the role existed, create it once, then run the reminder:

```sh
docker compose up -d                          # the db container gets the new password secret
docker compose exec db bash /docker-entrypoint-initdb.d/02-reminder-role.sh
```

In Git Bash, prefix that second line with `MSYS_NO_PATHCONV=1`.
