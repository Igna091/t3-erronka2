#!/usr/bin/env bash
# Tests deploy.sh with stand-ins for git and docker, so it runs anywhere bash, flock and
# mktemp do (CI runs it on every pull request): deploys, rollback, a release that fails to start,
# and what the script refuses.
#   bash web/scripts/deploy-test.sh
set -euo pipefail

script=$(cd "$(dirname "$0")" && pwd)/deploy.sh
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

export GUENAGA_REPO_DIR=$work/repo GUENAGA_STATE_DIR=$work/state GUENAGA_IMAGES=ghcr.io/test/guenaga-web
export CALLS=$work/calls
mkdir -p "$work/bin" "$GUENAGA_REPO_DIR/web"
printf 'APP_URL=https://www.guenaga.eus\n' > "$GUENAGA_REPO_DIR/web/.env"

# git: records the release checked out; DIRTY makes the checkout look edited
cat > "$work/bin/git" <<'EOF'
#!/usr/bin/env bash
case " $* " in
    *" status "*) printf '%s' "${DIRTY:-}" ;;
    *" checkout "*) echo "checkout ${*: -1}" >> "$CALLS" ;;
esac
EOF
# docker: records what it is asked; "compose up" fails for the release named in FAIL
cat > "$work/bin/docker" <<'EOF'
#!/usr/bin/env bash
case "$1 $2" in
    "login ghcr.io") echo "login $* token=$(cat)" >> "$CALLS" ;;
    "compose up")
        release=$(sed -n 's/^RELEASE=//p' .env)
        echo "up $release" >> "$CALLS"
        [[ $release != "${FAIL:-}" ]] ;;
    *) echo "$*" >> "$CALLS" ;;
esac
EOF
printf '#!/bin/sh\n' > "$work/bin/logger"
chmod +x "$work/bin/"*
export PATH=$work/bin:$PATH

failures=0
check() {  # description, then a command that must succeed
    local what=$1
    shift
    if "$@"; then
        echo "ok   $what"
    else
        echo "FAIL $what"
        failures=$((failures + 1))
    fi
}
fails() { ! "$@"; }
run() { : > "$CALLS"; bash "$script" "$@" < /dev/null > "$work/out" 2>&1; }
run_with() { local setting=$1; shift; : > "$CALLS"; env "$setting" bash "$script" "$@" < /dev/null > "$work/out" 2>&1; }
history_is() { [[ "$(paste -sd ' ' "$GUENAGA_STATE_DIR/history")" == "$*" ]]; }
running() { grep -qx "PHP_IMAGE=ghcr.io/test/guenaga-web-php:$1" "$GUENAGA_REPO_DIR/web/.env" \
    && grep -qx "MAILER_IMAGE=ghcr.io/test/guenaga-web-mailer:$1" "$GUENAGA_REPO_DIR/web/.env"; }

check "deploy v1.0.0" run deploy v1.0.0
check "  checks out the tag and pulls its images" grep -qx "checkout refs/tags/v1.0.0" "$CALLS"
check "  starts v1.0.0" running v1.0.0
check "deploy v1.1.0" run deploy v1.1.0
check "  history is v1.0.0 v1.1.0" history_is v1.0.0 v1.1.0
check "  keeps web01's own settings in .env" grep -qx "APP_URL=https://www.guenaga.eus" "$GUENAGA_REPO_DIR/web/.env"

check "rollback" run rollback
check "  v1.0.0 runs again, from its own tag" running v1.0.0
check "  checks out v1.0.0" grep -qx "checkout refs/tags/v1.0.0" "$CALLS"
check "  history is v1.0.0" history_is v1.0.0
check "rollback with nothing earlier is refused" fails run rollback

run deploy v1.1.0
check "a release that doesn't start fails the deploy" fails run_with FAIL=v1.2.0 deploy v1.2.0
check "  and v1.1.0 is running again" running v1.1.0
check "  and the history is unchanged" history_is v1.0.0 v1.1.0

for bad in latest v1.2 "v1.2.0;id" "../v1.0.0" ""; do
    check "refuses the tag '$bad'" fails run deploy "$bad"
done
check "  without touching git or docker" test ! -s "$CALLS"
check "refuses extra arguments" fails run deploy v1.3.0 now
check "refuses unknown commands" fails run shell
check "refuses a checkout with local changes" fails run_with DIRTY=" M web/nginx/nginx.conf" deploy v1.3.0

: > "$CALLS"
printf 'gho_example' | bash "$script" deploy v1.3.0 > /dev/null 2>&1
check "logs in to GHCR with the token on stdin" grep -q "^login .*--password-stdin token=gho_example$" "$CALLS"
check "  and never puts the token in a command line" fails grep -q "gho_example.*token=" "$CALLS"

check "status shows the running release" run status
check "  which is v1.3.0" grep -qx "running: v1.3.0" "$work/out"

echo
if (( failures )); then
    echo "$failures check(s) failed"
    exit 1
fi
echo "all checks passed"
