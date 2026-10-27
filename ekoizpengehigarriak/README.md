# ekoizpengehigarriak

The shop web app from the "Ekoizpen gehigarriak" exercise (Erronka 2): put a vulnerable PHP +
MySQL site into secure production. It is fixed, it runs in two containers (web server and
database), and CI checks it. Every weakness found and how it was fixed is in
[CHANGES.md](CHANGES.md).

```
browser ──HTTPS :8443──► web (Apache + PHP 8.5, www-data) ──backend network (internal)──► mysql (MySQL 8.4)
        ──HTTP  :8080──► (redirect to HTTPS)
```

## Run it

You need Docker Desktop (or Docker Engine), plus Git Bash on Windows. From this folder:

```sh
bash scripts/init-secrets.sh             # database passwords and a TLS certificate, in secrets/ (ignored by git)
docker compose up -d --build --wait
docker compose exec web php bin/set-password.php admin@bdweb   # prints the admin's first password
```

Open https://localhost:8443, accept the self-signed certificate, and log in as `admin@bdweb` with
the password the last command printed. Change it under **Kontua**.

The database ships with two accounts, `admin@bdweb` (admin) and `izaskun@uni.eus`, and neither has
a password that works until one is set this way. Anyone can also sign up under **Erregistratu**.

- **Other ports:** put `HTTP_PORT` and `HTTPS_PORT` in `.env` (see [.env.example](.env.example)).
- **Start again from an empty database:** `docker compose down -v` deletes the data and the
  uploaded pictures; the next `up` loads `db/init/` again.
- **Logs:** `docker compose logs web` shows every request and error; visitors only ever see
  "Errore bat gertatu da".

## Layout

| Path | What |
|---|---|
| `src/public/` | The only folder on the web: `index.php`, the stylesheet, and the product pictures |
| `src/includes/` | The pages `index.php` shows, one per `action` |
| `src/lib/` | Database, login and session, CSRF, validation, pictures, page list |
| `src/storage/perfiles/` | Profile photos, outside the web root |
| `src/conf.php` | Settings, read from the environment |
| `src/bin/set-password.php` | Sets a random password for an account |
| `db/init/` | The schema with its data, and the app's MySQL user, loaded on the first start |
| `web/` | The image: Dockerfile, Apache and PHP settings |
| `tests/` | Unit tests (PHPUnit) |
| `docs/checklist.xlsx` | The course's checklist of changed files, filled in |

## Tests and static analysis

The tools run in the image's `dev` stage, so nothing needs installing:

```sh
docker build --target dev --tag ekoizpen-tools --file web/Dockerfile .
docker run --rm -v "$PWD:/work:ro" ekoizpen-tools phpunit                  # 54 unit tests
docker run --rm -v "$PWD:/work:ro" ekoizpen-tools phpstan analyse          # static analysis, level 8
```

In Git Bash, prefix them with `MSYS_NO_PATHCONV=1` and use `$(pwd -W)` for `$PWD`. CI runs the
same, plus `php -l`, `composer audit`, ShellCheck and Semgrep
([../.github/workflows/ci.yml](../.github/workflows/ci.yml)).

## Security in short

- Every query is a prepared statement, and the app's MySQL user may only read and write rows.
- Everything printed is escaped, and the Content Security Policy allows no script at all.
- `index.php` shows only the pages on its list, and checks who may see each one first.
- Passwords are Argon2id hashes, at least 12 characters; wrong passwords are limited per account
  and per IP.
- The session cookie is `__Host-`, `Secure`, `HttpOnly` and `SameSite=Strict`. It gets a new id
  at login and expires after 30 minutes idle, and a password change logs out the other sessions.
- Every change is a POST with a CSRF token.
- Uploads must be real pictures, which are redrawn and given random names, and PHP never runs in
  the pictures folder.
- Only HTTPS, with HSTS and the other security headers. Both containers have read-only
  filesystems, no capabilities and no root.
