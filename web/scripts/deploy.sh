#!/usr/bin/env bash
# Deploys a release of the website stack on web01, or goes back to the release before it
# (issue #21). Installed as /usr/local/sbin/guenaga-deploy, owned by root; the CI's SSH key can
# run nothing else, through sudo (web/DEPLOY.md).
#
#   guenaga-deploy deploy v1.4.0   check out that release, pull its images and start them. If the
#                                   stack doesn't come up healthy, the previous release comes back
#   guenaga-deploy rollback        go back to the release deployed before the current one
#   guenaga-deploy status          the running release and the ones before it
#
# deploy and rollback read a GitHub token on stdin to pull the images from GHCR: in CI, the job's
# own GITHUB_TOKEN, which can only read packages and expires when the job ends. With nothing on
# stdin they use root's Docker login, or none for public images.
set -euo pipefail

# Where things are on web01. Overridable only for deploy-test.sh: sudo resets the environment,
# so the CI can't change them.
REPO_DIR=${GUENAGA_REPO_DIR:-/opt/guenaga}               # clone of the repo; the stack is in web/
STATE_DIR=${GUENAGA_STATE_DIR:-/var/lib/guenaga-deploy}  # release history and lock
IMAGES=${GUENAGA_IMAGES:-ghcr.io/igna091/guenaga-web}    # then -php:<tag> and -mailer:<tag>

HISTORY=$STATE_DIR/history  # deployed releases, oldest first; the last one is running

log() {
    printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*"
    # Also to syslog, where Wazuh picks it up
    logger -t guenaga-deploy -- "$*" 2> /dev/null || true
}

die() {
    log "ERROR: $*"
    exit 1
}

is_release() {
    [[ $1 =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]
}

releases() {
    [[ -s $HISTORY ]] && cat "$HISTORY"
    return 0
}

# Logs in to GHCR with the token on stdin, using a Docker config of its own that is deleted on exit
login_from_stdin() {
    [[ -t 0 ]] && return 0
    local token
    token=$(head -c 4096)
    [[ -n $token ]] || return 0
    DOCKER_CONFIG=$(mktemp -d)
    export DOCKER_CONFIG
    trap 'rm -rf "$DOCKER_CONFIG"' EXIT
    printf '%s' "$token" | docker login ghcr.io --username github-actions --password-stdin > /dev/null
}

# Points web/.env at the release's images; the file's other settings (APP_URL…) stay as they are
use_images() {
    local tag=$1 env=$REPO_DIR/web/.env
    touch "$env" && chmod 600 "$env" || return 1
    { grep -v -E '^(RELEASE|PHP_IMAGE|MAILER_IMAGE)=' "$env" || true
      printf 'RELEASE=%s\nPHP_IMAGE=%s-php:%s\nMAILER_IMAGE=%s-mailer:%s\n' "$tag" "$IMAGES" "$tag" "$IMAGES" "$tag"
    } > "$env.new" && mv "$env.new" "$env"
}

# Checks out the release's compose files and configuration, then starts its images. Every step
# returns on failure itself: set -e doesn't apply inside a function that an if calls.
start() {
    local tag=$1
    log "starting $tag"
    # With GitHub unreachable, a release whose tag and images are already here still starts,
    # so a rollback works offline; anything missing makes the checkout or the start fail below
    git -C "$REPO_DIR" fetch --quiet --force --tags origin || log "can't fetch from GitHub; using the tags already here"
    git -C "$REPO_DIR" -c advice.detachedHead=false checkout --quiet --detach "refs/tags/$tag" || return 1
    use_images "$tag" || return 1
    # --wait fails unless every service is healthy (and migrate has finished) within 5 minutes
    ( cd "$REPO_DIR/web" \
        && docker compose pull --quiet --ignore-pull-failures php migrate mailer \
        && docker compose up --detach --no-build --remove-orphans --wait --wait-timeout 300 ) || return 1
    log "$tag is running"
}

deploy() {
    local tag=$1 last
    last=$(releases | tail -n 1)
    if [[ $tag == "$last" ]]; then
        log "$tag is already running; starting it again"
    fi
    if start "$tag"; then
        [[ $tag == "$last" ]] || echo "$tag" >> "$HISTORY"
        return 0
    fi
    [[ -n $last ]] || die "$tag didn't start, and there is no earlier release to go back to"
    log "$tag didn't start; going back to $last"
    start "$last" || die "$last didn't start either: check docker compose ps and logs on web01"
    die "$tag didn't start; $last is running again"
}

rollback() {
    local -a deployed
    mapfile -t deployed < <(releases)
    (( ${#deployed[@]} >= 2 )) || die "there is no earlier release to go back to"
    local current=${deployed[-1]} previous=${deployed[-2]}
    log "rolling back from $current to $previous"
    start "$previous"
    # The history is a stack: going back removes the current release from it, so rolling back
    # twice goes back two releases
    printf '%s\n' "${deployed[@]:0:${#deployed[@]}-1}" > "$HISTORY.new"
    mv "$HISTORY.new" "$HISTORY"
}

status() {
    local -a deployed
    mapfile -t deployed < <(releases)
    if (( ${#deployed[@]} == 0 )); then
        echo "running: nothing deployed yet"
        return
    fi
    echo "running: ${deployed[-1]}"
    (( ${#deployed[@]} < 2 )) || echo "before it, oldest first: ${deployed[*]:0:${#deployed[@]}-1}"
}

main() {
    local command=${1:-} tag=${2:-}
    (( $# <= 2 )) || die "too many arguments"
    mkdir -p "$STATE_DIR"
    chmod 700 "$STATE_DIR"

    case $command in
        status)
            status
            return
            ;;
        deploy)
            is_release "$tag" || die "not a release tag: '$tag' (expected vX.Y.Z)"
            ;;
        rollback)
            [[ -z $tag ]] || die "rollback takes no argument"
            ;;
        *)
            die "usage: guenaga-deploy deploy vX.Y.Z | rollback | status"
            ;;
    esac

    # One deploy at a time
    exec 9> "$STATE_DIR/lock"
    flock --nonblock 9 || die "another deploy or rollback is running"

    # web01's own settings live in web/.env and web/secrets/, which git ignores. A change to a
    # tracked file would be lost (or block) on the next checkout, so it belongs in the repo.
    if [[ -n $(git -C "$REPO_DIR" status --porcelain --untracked-files=no) ]]; then
        die "$REPO_DIR has local changes to tracked files; put them in the repo instead"
    fi

    login_from_stdin
    "$command" "$tag"
}

main "$@"
