# Deploying web01

How the website and the mailer get from a pull request to web01, how to go back, and how to
rebuild web01 from nothing (issue #21, RA8). The workflows are in [../.github/workflows](../.github/workflows).

```
pull request ──► CI: php -l · PHPStan · composer audit · tests · Semgrep · ShellCheck · actionlint
                     └─► both images built, Trivy fails on any CRITICAL CVE
merge to main ─► the same, then the images go to GHCR as :sha-<commit> and :main
tag vX.Y.Z ────► the same, then the images go to GHCR as :vX.Y.Z ─► Deploy
                     └─► runner in U1-IT ──SSH──► web01: guenaga-deploy deploy vX.Y.Z
                                                         git checkout vX.Y.Z, docker compose pull, up --wait
```

## What runs when

| Workflow | When | What |
|---|---|---|
| CI (`ci.yml`) | Every pull request and push to `main` | `composer validate` and `audit`, `php -l`, PHPStan level 8 (Larastan), PHPUnit; the mailer's tests and a Trivy scan of its libraries; Semgrep (PHP, Python, Dockerfiles, Compose, workflows, secrets); ShellCheck, the deploy script's tests and actionlint. Then both images are built and Trivy fails the run on any CRITICAL CVE |
| CI | Push to `main` | Also pushes `ghcr.io/igna091/guenaga-web-php` and `-mailer` as `:sha-<commit>` and `:main` |
| CI | Tag `vX.Y.Z` | Also pushes them as `:vX.Y.Z`, then deploys that release (when `DEPLOY_ENABLED` is `true`) |
| CI | Mondays 05:23 UTC | Everything again, so a CVE published since the last push still fails a build |
| Deploy (`deploy.yml`) | A release tag, or by hand with any tag | Deploys that release to web01 |
| Rollback (`rollback.yml`) | By hand | Goes back to the release before the current one |
| Dependabot (`dependabot.yml`) | Weekly | Pull requests bumping the base images (so a PHP security release arrives as one), Compose images, Composer and Python libraries and the actions |

A pull request that adds a dependency with a known vulnerability fails: `composer audit` fails on
any advisory in `composer.lock`, and Trivy on any vulnerability in `python/requirements.txt`.

## Releasing

Merge the pull request, then tag `main`:

```sh
git switch main && git pull
git tag -a v1.4.0 -m "Shop order emails"
git push origin v1.4.0
```

The tag's CI run builds, scans and pushes the images, then deploys them. Follow it under Actions;
the deploy job shows web01's output.

## Going back

- **One release back:** Actions → Rollback → Run workflow. Running it again goes back one more.
- **To a particular release:** Actions → Deploy → Run workflow, with its tag.
- **If the new release doesn't come up healthy,** the deploy script starts the previous release
  again on its own and the job fails.
- **From web01 itself,** for example while GitHub is down: `sudo guenaga-deploy rollback < /dev/null`.
  It works offline as long as the earlier release's images are still on web01, so don't prune them.
  `sudo guenaga-deploy status` shows the running release and the ones before it.

Going back changes the code, not the data: migrations only move forward. So a migration must not
break the release before it. Add columns and tables first, and remove old ones only in a later
release (expand, then contract). If data has to go back too, restore the database backup (#25).

## Set-up, once

### GitHub

1. **Settings → Actions → General:** set fork pull request workflows to *Require approval for all
   external contributors*. The repo is public, so this keeps strangers' workflow changes from
   running, above all on the runner in U1-IT. Keep *Workflow permissions* at read-only.
2. **Settings → Environments → New environment `production`:**
   - **Deployment branches and tags:** selected ones only: the tag pattern `v*` (CI's release
     deploys) and the branch `main` (Deploy and Rollback run by hand). Nothing from a pull request
     or another branch can deploy.
   - **Required reviewers (optional):** someone has to approve each deploy.
   - **Secret `WEB01_SSH_KEY`:** the private key below.
   - **Variables:** `WEB01_HOST` (10.20.10.10), `WEB01_USER` (`deploy`), and `WEB01_KNOWN_HOSTS`, web01's
     host key line. Read it from web01's console (`cat /etc/ssh/ssh_host_ed25519_key.pub`) and
     prefix it with the address: `10.20.10.10 ssh-ed25519 AAAA…`. Don't trust `ssh-keyscan` over the network.
3. **Settings → Rules (or Branches), the rule for `main`:** require the CI checks (PHP, Python
   mailer, Scripts and workflows, Semgrep, both Image jobs) to pass before merging.
4. **Settings → Code security:** turn on secret scanning, push protection and Dependabot alerts.
5. **Settings → Secrets and variables → Actions → Variables:** `DEPLOY_ENABLED` = `true`, once
   the runner and web01 are ready. Until then CI builds and pushes but doesn't deploy.

The images are published under your account's packages (Profile → Packages), linked to this repo.

### The runner in U1-IT

A small Debian VM in U1-IT, used for nothing else: it is the only machine allowed to reach
web01's SSH port (inside to DMZ).

1. Create an unprivileged user `runner`, without sudo and outside the `docker` group; the runner
   only needs `ssh`.
2. Settings → Actions → Runners → New self-hosted runner, Linux. Follow the commands as `runner`,
   and add the label `u1-it` when `config.sh` asks. Then install it as a service: `sudo ./svc.sh install runner`.
3. Firewall: outbound only HTTPS to GitHub (`github.com`, `*.actions.githubusercontent.com`,
   `ghcr.io`) and SSH to web01; nothing inbound.

Only `deploy.yml` uses this runner. Everything else runs on GitHub's own runners.

### web01

As root, after the Docker baseline ([../docker/README.md](../docker/README.md)):

```sh
# The repo and the stack's own settings and secrets, which git ignores
git clone https://github.com/Igna091/t3-erronka2.git /opt/guenaga
cd /opt/guenaga/web
printf 'APP_URL=https://www.guenaga.eus\n' > .env
# secrets/: the TLS pair from the Infra TLS CA (#10), then scripts/dev-init.sh for the rest (README.md)

# The deploy script, outside the checkout, so a deploy can't change the script it runs as root
install -o root -g root -m 0755 scripts/deploy.sh /usr/local/sbin/guenaga-deploy

# A user that can run that script and nothing else; the docker group stays empty
useradd --create-home --shell /bin/bash deploy
echo 'deploy ALL=(root) NOPASSWD: /usr/local/sbin/guenaga-deploy' > /etc/sudoers.d/guenaga-deploy
chmod 440 /etc/sudoers.d/guenaga-deploy
```

On an admin machine, create the key pair and put the private half in `WEB01_SSH_KEY`:

```sh
ssh-keygen -t ed25519 -N '' -C github-actions-deploy -f web01-deploy
```

On web01, `/home/deploy/.ssh/authorized_keys` (owned by `deploy`, mode 600) gets the public half
in one line. It is accepted only from the runner's address, can't open a shell or forwarding, and
always runs the deploy script:

```
from="<runner IP>",restrict,command="sudo -n /usr/local/sbin/guenaga-deploy $SSH_ORIGINAL_COMMAND" ssh-ed25519 AAAA… github-actions-deploy
```

The script accepts only `deploy vX.Y.Z`, `rollback` and `status`, one at a time (`flock`), and
logs to syslog for Wazuh (`guenaga-deploy`). It reads the job's `GITHUB_TOKEN` on stdin to pull
the images. That token can only read packages, expires when the job ends, and goes into a
throwaway Docker config, never root's.

Then the first release: Actions → Deploy → Run workflow, or push a tag.

Whenever `scripts/deploy.sh` changes in the repo, install it again on web01 (the `install` line).

## When Trivy fails

Trivy fails the build on a CRITICAL CVE in either image:

1. **There is a fixed package:** rebuild. The PHP image runs `apk upgrade` on every build, so a
   new CI run often fixes it; if it's in the base image, bump the base image (or merge
   Dependabot's pull request).
2. **There is no fix yet:** decide whether the vulnerable code is reachable. If you accept the
   risk for now, add it to a `.trivyignore.yaml` next to that image's Dockerfile, with the reason
   and an expiry date. On that date CI fails again until someone looks:

   ```yaml
   vulnerabilities:
     - id: CVE-2026-12345
       purls:
         - pkg:apk/alpine/libexample
       statement: Why it doesn't affect us, who decided, when.
       expired_at: 2026-12-31
   ```

For example, CVE-2026-6653 in Debian's libxml2 was fixed by moving the PHP image to Alpine, whose
libxml2 isn't affected, rather than by accepting it.

## Disaster recovery

| What happened | What to do |
|---|---|
| A release is broken | Rollback (above). |
| web01 is lost or must be rebuilt (disk, ransomware, compromise) | 1. Rebuild the host: hypervisor template, Docker baseline. 2. Set up web01 as above, with a **new** deploy key if the old one may be compromised (replace `WEB01_SSH_KEY`, delete the old public key). 3. Restore `web/secrets/` from the password manager and backups (#25). Never generate a new `app_key`: it encrypts the customers' health answers, which can't be read with a new one. 4. Run Deploy with the last good release; its migrations create an empty schema. 5. Restore the latest database backup (#25): `docker compose exec -T db pg_restore -U postgres -d guenaga --clean < guenaga.dump`. 6. Go through the checks in [README.md](README.md). |
| web01 was compromised | As above, from a clean template, after forensics has its copy (`forensics/`). Also rotate every secret (database passwords, mail password, TLS key) except `app_key`, revoke the deploy key and check GitHub's audit log and the packages for anything pushed that CI didn't build. |
| The runner was compromised | Remove it under Settings → Actions → Runners, replace `WEB01_SSH_KEY` and the public key on web01, rebuild the VM. |
| GitHub or GHCR is down | web01 keeps running. A rollback from web01 itself works offline with the images already there. |
| A secret leaked into the repo | Rotate it first, then remove it from the history. Push protection should stop this before it happens. |

Test a restore and a rollback at least once before the presentation, and keep the evidence in `evidence/`.
