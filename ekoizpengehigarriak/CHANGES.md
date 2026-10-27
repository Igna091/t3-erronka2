# ekoizpengehigarriak: what was changed, and why

The shop came as a PHP + MySQL app for XAMPP (it is based on Peruggia, an app made vulnerable on
purpose for training). This document lists every weakness found in it, how each one was fixed,
and how the fix is checked, then every file that was added or changed. The same list, in the
course's template, is in [docs/checklist.xlsx](docs/checklist.xlsx).

The original code is the first commit of this folder (`f354e1a`), so every change can be seen
with:

```sh
git diff f354e1a -- ekoizpengehigarriak/
```

Line numbers below ("orig.") refer to that original version.

## Weaknesses found and fixed

| # | Weakness | Where it was (orig.) | Risk | Fix |
|---|---|---|---|---|
| 1 | **SQL injection** in every query: user input glued into the SQL text | `login.php:11`, `register.php:32-43`, `main.php:10`, `description.php:15,26,40`, `updel.php:7,10,18`, `account.php:14,25,36` | Read, change or delete any data in the database, including password hashes | PDO with real prepared statements (`ATTR_EMULATE_PREPARES` off) in every query; ids are checked to be whole numbers first; the search word's `%` and `_` are escaped (`like_pattern()`) |
| 2 | **Authentication bypass**: the login query itself was injectable | `login.php:11` | Log in as anyone, the admin included, without a password | Prepared statement, then `password_verify()` in PHP (`lib/auth.php`) |
| 3 | **Weak password storage**: unsalted MD5, and both accounts shipped with the password `password` | `bdweb.sql:73-74`, `login.php:11`, `register.php:34`, `account.php:13,23` | Any leaked hash is cracked in seconds; anyone can log in as the admin | Argon2id (`password_hash()`); the two shipped accounts have no password that works until one is set with `bin/set-password.php`, which makes a random one |
| 4 | **Stored XSS**: product names, descriptions and usernames printed as HTML | `main.php:48,54`, `updel.php:20,57`, `description.php:43,52`, `account.php:82,84` | A product description or a username can run a script in the admin's browser | Everything printed goes through `e()` (`htmlspecialchars`); the Content Security Policy also allows no script at all |
| 5 | **Reflected XSS**: the search word, the register form's values and `$_SERVER['PHP_SELF']` printed back | `main.php:19`, `register.php:64-110`, `index.php:39-51` and every form | A link can make the page run a script | Escaped output; links and forms point at `index.php`, never `PHP_SELF`; Apache refuses extra paths after `index.php` (`AcceptPathInfo Off`) |
| 6 | **Local file inclusion**: any unknown `action` was included as a file | `index.php:97` | Run or show files the app was never meant to load | An allowlist of pages (`lib/routes.php`); anything else is "Ez da orria aurkitu" (404) |
| 7 | **Unrestricted file upload**: any file kept under its own name in a folder the server runs | `register.php:28-30`, `updel.php:15-17` | Upload a script and run it on the server | Only real pictures (`getimagesize`), at most 2 MB and 4000 × 4000 px, **redrawn** with GD (which also drops metadata such as GPS), saved under a random name with the extension of their real type; PHP is switched off in `images/`; profile photos are kept outside the web root |
| 8 | **Arbitrary file deletion**: the file name from the database went straight into `unlink()`; deleting a product also removed a picture other products used | `updel.php:9` | Delete files outside `images/`; break other products | Only safe names in `images/` are deleted (`delete_image()`), and only when no other product uses the picture |
| 9 | **Missing access control** | `description.php` (no check at all); `includes/*.php` reachable by URL; `updel.php:2-4` and `account.php:3-5` redirect without stopping; admin decided by comparing usernames, and `account.php:20,30,59` compared with `'admin'`, which never matched | Anyone could change prices; opening `includes/updel.php` directly ran its code | Each page has an access level (public, user, admin) checked in `index.php` before the page runs; a real `is_admin` column; only `src/public` is on the web, so `includes/`, `lib/` and `conf.php` can't be opened |
| 10 | **CSRF**: every change was a plain link or a form without a token | `updel.php:59` (delete product), `account.php:51,91` (password, delete user), `index.php:39-42` (logout) | Another site can make a logged-in admin's browser delete users or products | Every change is a POST with a per-session token (`lib/csrf.php`), and the session cookie is `SameSite=Strict` |
| 11 | **Session weaknesses**: no new session id at login, the password hash stored in the session, default cookie settings, no timeout | `login.php:15-17`, `index.php:3` | Session fixation; a stolen cookie works forever | New id at login and logout; the session holds only the user's id; cookie `__Host-bdweb` with `Secure`, `HttpOnly`, `SameSite=Strict`; `use_strict_mode`; 30 minutes idle and the session ends |
| 12 | **Broken password change**: it updated *every* account with the same hash, needed no current password and had no rules | `account.php:9-16` | Changing your password changed other users' passwords too | By user id; asks for the current password; 12 to 128 characters; every other session of the account is logged out (`session_version`) |
| 13 | **No brute-force protection** | `login.php` | Unlimited password guessing | 5 wrong passwords per account or 20 per IP in 15 minutes lock logging in (`login_failures` table) |
| 14 | **Information disclosure**: database errors and PHP warnings shown to visitors; `X-Powered-By` and server versions sent | `register.php:45`, `mysql.php` | The errors show SQL, table names and paths | `display_errors` off; one generic error page, details only in the container log; `expose_php` off; `ServerTokens Prod` |
| 15 | **Database as root with an empty password** | `conf.php:4-7` | Any injection controls the whole database server | The app logs in as `bdweb_app`, which may only read and write rows in `bdweb`, only from the backend network, with a password from a Docker secret; root logs in only from inside the MySQL container |
| 16 | **No HTTPS and no security headers** | (XAMPP setup) | Passwords and cookies travel in clear text; clickjacking, MIME sniffing | HTTPS only (HTTP redirects), TLS 1.2+, HSTS, CSP, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy` |
| 17 | **No input validation**: passwords never compared, postcodes and phones stored as numbers (leading zeros lost), prices as `float`, no lengths | `register.php`, `updel.php`, `bdweb.sql` | Wrong data, and errors that leak information | Every field checked with a message for the form (`lib/validation.php`); `CHAR(5)` postcodes, `VARCHAR` phones, `DECIMAL(8,2)` prices; the password field is `type=password` and never sent back to the page |
| 18 | **Personal data exposed**: profile photos public in `perfiles/` under their original names | `register.php:28` | Anyone can open everyone's photos | Photos in `storage/perfiles`, outside the web root; only their owner sees them, through `index.php?action=photo` |
| 19 | **Dead vulnerable code** | `includes/main_orig.php` | Reachable by URL, with its own SQL injection | Deleted |
| 20 | **Directory listing** | Apache default | Lists every uploaded file | `Options -Indexes` |

## Containers

The brief asks for the site in two containers, the web server and the database:

| Container | Image | Runs as | What it can do |
|---|---|---|---|
| `web` | `php:8.5.12-apache-trixie`, built by [web/Dockerfile](web/Dockerfile) | www-data (33), on ports 8080/8443 | Serves only `src/public`; writes only to the two upload volumes and tmpfs |
| `mysql` | `mysql:8.4.13` | mysql (999) | Only on the internal `backend` network; no route out |

Both have a read-only root filesystem, no Linux capabilities, `no-new-privileges`, memory, CPU
and process limits, and images pinned by digest. As the brief says, inside Docker the database
host is the service name, `mysql`, not `localhost` (`src/conf.php`).

## CI

[../.github/workflows/ci.yml](../.github/workflows/ci.yml) runs, on every push and pull request:

- `php -l` on every file, PHPStan at level 8, and the 54 unit tests in `tests/` (without a network)
- `composer audit` on the test tools, and a build of the runtime image
- ShellCheck on the scripts, and Semgrep (PHP, Dockerfile, Compose and secrets rules) on the whole repo

## How each fix is checked

The unit tests (`tests/`, run by CI) check the rules themselves:

| Test | What it checks |
|---|---|
| `HtmlTest` | Markup and quotes are printed as text (#4, #5) |
| `CsrfTest` | Only the session's own token is accepted (#10) |
| `RoutesTest` | Only listed pages open, `../conf` and other names are not pages, and who may open each page (#6, #9) |
| `ImagesTest` | Real pictures are redrawn under random names; a script, a fake picture header, an empty file, a picture too big and a file that wasn't uploaded are refused; deleting never leaves the folder (#7, #8) |
| `ValidationTest` | Sign-up fields, passwords, prices, products and the search word's wildcards (#12, #17, #1) |
| `AuthTest` | Argon2id, salted hashes, the shipped accounts' unusable password, and the login limits (#3, #13) |

On the running app (`docker compose up`), these are the checks to take screenshots of; the
expected result is the fixed behaviour:

| Check | How | Expected |
|---|---|---|
| Search with a quote or `%` | Search for `50% 'x` | "Ez dago produkturik katalogoan…", no database error (#1) |
| Markup in the search | Search for `<b>bold</b>` | The text `<b>bold</b>` shown, not bold (#5) |
| Wrong login | Log in with a wrong password | "Erabiltzailea edo pasahitza ez da zuzena." (#2) |
| Login limit | Six wrong passwords for one account | "Saiakera oker gehiegi. Itxaron 15 minutu…" (#13) |
| New session id | Compare the `__Host-bdweb` cookie before and after logging in (browser dev tools) | It changes (#11) |
| Cookie flags | Dev tools → Application → Cookies | `Secure`, `HttpOnly`, `SameSite=Strict` (#11) |
| Fake picture | As admin, upload a text file named `x.jpg` as a product | "Irudiak JPEG, PNG, GIF edo WebP izan behar du…" (#7) |
| Real picture | Upload a real picture | It's stored as `images/<32 random hex>.png` (#7) |
| Forged CSRF token | Change the hidden `csrf` field in dev tools, then send the form | "Eskaera ez da baliozkoa. Saiatu berriro." (#10) |
| Admin page as a user | As a normal user, open `index.php?action=updel` | "Ez da orria aurkitu." (#9) |
| Files outside the web root | Open `/includes/login.php`, `/conf.php`, `/storage/perfiles/usuario.jpg` | 404 or 403 (#9, #18) |
| Unknown page | Open `index.php?action=../conf` | "Ez da orria aurkitu." (#6) |
| Password change | Log in on two browsers, change the password on one | The other is logged out (#12) |
| Error page | Stop the database (`docker compose stop mysql`), open the shop | "Errore bat gertatu da…", and the details only in `docker compose logs web` (#14) |
| Headers | `curl -skI https://localhost:8443/` | HSTS, CSP, `nosniff`, `X-Frame-Options`, `Server: Apache` with no version, no `X-Powered-By` (#14, #16) |
| HTTP | `curl -sI http://localhost:8080/` | `301` to `https://localhost:8443/` (#16) |
| Database user | `docker compose exec mysql mysql -uroot -p -e "SHOW GRANTS FOR bdweb_app@'172.29.40.%'"` | Only `SELECT, INSERT, UPDATE, DELETE ON bdweb.*` (#15) |

## Files

Following the checklist: **new** files, and **changed** ones (moved files count as changed).

| File | New? | Why | Notes |
|---|---|---|---|
| `src/public/index.php` (was `index.php`) | Changed | Protect: #5, #6, #9, #10, #14 | Front controller: page allowlist, access check before the page runs, error handler, escaped header, logout as a POST |
| `src/conf.php` | Changed | Requirement (Docker) and protect: #15 | Settings from environment variables and a Docker secret; database host `mysql` |
| `src/lib/db.php` (was `includes/mysql.php`) | Changed | Protect: #1, #14, #15 | PDO with exceptions and real prepared statements, as `bdweb_app` |
| `src/lib/auth.php` | New | Requirement (secure authentication) and protect: #2, #3, #11, #12, #13 | Argon2id, login limits, new session id, hardened cookie, idle timeout, `session_version` |
| `src/lib/csrf.php` | New | Protect: #10 | Per-session token, compared with `hash_equals` |
| `src/lib/html.php` | New | Protect: #4, #5 | `e()` for every printed value |
| `src/lib/validation.php` | New | Protect: #1, #12, #17 | Checks for every form, with messages in Basque |
| `src/lib/images.php` | New | Protect: #7, #8, #18 | Picture checks, redrawing, random names, safe deletion |
| `src/lib/routes.php` | New | Protect: #6, #9 | The page allowlist and its access levels |
| `src/lib/http.php` | New | Protect: #10, #14 | Redirects that stop, POST helpers, one-time messages |
| `src/lib/app.php` | New | Requirement (tests) | Loads the libraries for the site, the CLI and the tests |
| `src/includes/login.php` | Changed | Protect: #2, #10, #11, #13 | One message for any wrong login |
| `src/includes/register.php` | Changed | Protect: #1, #5, #7, #10, #17, #18 | Field errors shown, password fields never refilled, optional photo kept private |
| `src/includes/account.php` | Changed | Protect: #1, #9, #10, #12 | Current password asked; admin features work (they never did) and need `is_admin` |
| `src/includes/main.php` | Changed | Protect: #1, #4, #5 | Prepared search; fixed the broken cart link markup |
| `src/includes/description.php` | Changed | Protect: #1, #4, #9, #10 | Admin only; validated price |
| `src/includes/updel.php` | Changed | Protect: #1, #4, #7, #8, #9, #10 | Deletes as POST; shared pictures kept |
| `src/includes/about.php` (was `about.html`) | Changed | Protect: #16 (CSP) | Now reachable from the footer; inline style moved to the CSS |
| `src/includes/photo.php` | New | Protect: #18 | Shows the user's own photo only |
| `src/includes/logout.php` | New | Protect: #10, #11 | POST with token; empties the session |
| `src/includes/main_orig.php` | Deleted | Protect: #19 | Unused |
| `src/public/style.css` (was `style.css`) | Changed | Protect: #16 (CSP) | Inline styles became classes; font sizes got units |
| `src/storage/perfiles/` (was `perfiles/`) | Changed | Protect: #18 | Outside the web root |
| `src/bin/set-password.php` | New | Requirement (secure authentication) and protect: #3 | Sets a random password for an account, for the first admin login |
| `db/init/01-bdweb.sql` (was `bdweb.sql`) | Changed | Protect: #3, #9, #12, #13, #17 | Argon2id-sized passwords, `is_admin`, unique usernames, `session_version`, text postcodes and phones, `DECIMAL` prices, `login_failures` |
| `db/init/02-app-user.sh` | New | Protect: #15 | The least-privilege `bdweb_app` MySQL user |
| `compose.yaml` | New | Requirement (two containers) and protect: #15, #16 | The `web` and `mysql` services, hardened, with secrets and an internal network |
| `web/Dockerfile` | New | Requirement (Docker, tests) | `base` → `dev` (PHPUnit, PHPStan) → `runtime` |
| `web/apache/bdweb.conf`, `ports.conf`, `zz-hardening.conf` | New | Protect: #5, #7, #9, #14, #16, #20 | HTTPS, headers, only `index.php` runs, PHP off in `images/` |
| `web/php/zz-hardening.ini` | New | Protect: #7, #11, #14 | No errors shown, no shell functions, `open_basedir`, upload limits, session settings |
| `scripts/init-secrets.sh` | New | Requirement (Docker) and protect: #15, #16 | Database passwords and the TLS certificate, outside git |
| `tests/*.php`, `phpunit.xml`, `phpstan.neon`, `composer.json`, `composer.lock` | New | Requirement (CI: static analysis and unit tests) | 54 tests; PHPStan level 8 |
| `../.github/workflows/ci.yml` | Changed | Requirement (CI) | The `ekoizpengehigarriak` job, and ShellCheck on its scripts |
| `README.md`, `CHANGES.md`, `docs/checklist.xlsx` | New | Documentation | How to run it; this list; the course's template, filled in |
