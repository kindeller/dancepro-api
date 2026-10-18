#!/usr/bin/env bash

# Stable production deployment entry point.
#
# Install this file as /usr/local/bin/dancepro-deploy. The installed copy is
# deliberately independent of the Git checkout that it updates.

set -Eeuo pipefail

readonly APP_DIR="${DANCEPRO_APP_DIR:-/var/www/dancepro-api}"
readonly BRANCH="master"
readonly REMOTE="origin"
readonly EXPECTED_REMOTE_URL="${DANCEPRO_EXPECTED_REMOTE_URL:-https://github.com/kindeller/dancepro-api.git}"
readonly DEPLOY_SCRIPT="scripts/dancepro-deploy.sh"
readonly LOCK_FILE="${DANCEPRO_DEPLOY_LOCK_FILE:-/var/lock/dancepro-deploy.lock}"
readonly LOG_DIR="${DANCEPRO_DEPLOY_LOG_DIR:-/var/log/dancepro-deploy}"

DRY_RUN=false
TARGET_COMMIT=""
CURRENT_COMMIT=""
ROLLBACK_TAG=""

usage() {
    cat <<'EOF'
Usage:
  dancepro-deploy <40-character-commit-sha>
  dancepro-deploy --dry-run <40-character-commit-sha>

The commit must be reachable from origin/master, and the production checkout
must be able to fast-forward to it.
EOF
}

fail() {
    printf 'ERROR: %s\n' "$1" >&2
    exit 1
}

log() {
    printf '\n=== %s ===\n' "$1"
}

while (($# > 0)); do
    case "$1" in
        --dry-run)
            DRY_RUN=true
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            [[ -z "$TARGET_COMMIT" ]] || fail "Only one target commit may be supplied."
            TARGET_COMMIT="$1"
            ;;
    esac
    shift
done

[[ "$TARGET_COMMIT" =~ ^[0-9a-f]{40}$ ]] || {
    usage >&2
    fail "A lowercase, 40-character Git commit SHA is required."
}

umask 027
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
export GIT_TERMINAL_PROMPT=0
export COMPOSER_NO_INTERACTION=1

mkdir -p "$LOG_DIR"
readonly LOG_FILE="$LOG_DIR/launcher-$(date -u +'%Y-%m-%d_%H-%M-%S')-${TARGET_COMMIT:0:12}.log"
exec > >(tee -a "$LOG_FILE") 2>&1

printf 'DancePro production deployment launcher\n'
printf 'Target commit: %s\n' "$TARGET_COMMIT"
printf 'Mode:          %s\n' "$([[ "$DRY_RUN" == true ]] && printf 'dry run' || printf 'deployment')"
printf 'Launcher log:  %s\n' "$LOG_FILE"

command -v flock >/dev/null 2>&1 || fail "flock is not installed."
exec 9>"$LOCK_FILE" || fail "Cannot open deployment lock: $LOCK_FILE"
flock -n 9 || fail "Another DancePro deployment is already running."

log "Validate production checkout"
[[ -d "$APP_DIR" ]] || fail "Application directory does not exist: $APP_DIR"
cd "$APP_DIR"

command -v git >/dev/null 2>&1 || fail "Git is not installed."
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "$APP_DIR is not a Git working tree."

[[ "$(git branch --show-current)" == "$BRANCH" ]] || fail "The production checkout is not on $BRANCH."
[[ "$(git remote get-url "$REMOTE")" == "$EXPECTED_REMOTE_URL" ]] || fail "The origin remote is not authorised."

if [[ -n "$(git status --porcelain)" ]]; then
    git status --short
    fail "The production working tree is not clean."
fi

CURRENT_COMMIT="$(git rev-parse HEAD)"
printf 'Current commit: %s\n' "$CURRENT_COMMIT"

log "Fetch approved branch"
git fetch --no-tags "$REMOTE" "+refs/heads/$BRANCH:refs/remotes/$REMOTE/$BRANCH"

git cat-file -e "$TARGET_COMMIT^{commit}" 2>/dev/null || fail "The target commit was not fetched from $REMOTE/$BRANCH."
git merge-base --is-ancestor "$TARGET_COMMIT" "$REMOTE/$BRANCH" || \
    fail "The target commit is not reachable from $REMOTE/$BRANCH."
git merge-base --is-ancestor "$CURRENT_COMMIT" "$TARGET_COMMIT" || \
    fail "The target commit cannot fast-forward the current production commit."

printf 'Approved branch tip: %s\n' "$(git rev-parse "$REMOTE/$BRANCH")"
printf 'Target:              %s\n' "$(git log -1 --format='%H %s' "$TARGET_COMMIT")"

if [[ "$DRY_RUN" == true ]]; then
    log "Dry run"
    git diff --stat "$CURRENT_COMMIT..$TARGET_COMMIT"
    printf 'No repository or application changes were made.\n'
    exit 0
fi

log "Create rollback reference"
ROLLBACK_TAG="pre-deploy-$(date -u +'%Y%m%d-%H%M%S')-${CURRENT_COMMIT:0:12}"
git tag "$ROLLBACK_TAG" "$CURRENT_COMMIT"
printf 'Rollback tag: %s\n' "$ROLLBACK_TAG"

log "Fast-forward production checkout"
git merge --ff-only "$TARGET_COMMIT"
[[ "$(git rev-parse HEAD)" == "$TARGET_COMMIT" ]] || fail "The checkout did not reach the requested commit."
[[ -f "$DEPLOY_SCRIPT" ]] || fail "The deployed application script is missing: $DEPLOY_SCRIPT"

log "Execute version-controlled deployment"
set +e
/bin/bash "$APP_DIR/$DEPLOY_SCRIPT" \
    --from-commit "$CURRENT_COMMIT" \
    --target-commit "$TARGET_COMMIT" \
    --rollback-tag "$ROLLBACK_TAG"
DEPLOY_EXIT_CODE=$?
set -e

if ((DEPLOY_EXIT_CODE != 0)); then
    printf 'ERROR: Application deployment failed with exit code %d.\n' "$DEPLOY_EXIT_CODE" >&2
    printf 'The checkout remains at %s for diagnosis. See %s.\n' "$TARGET_COMMIT" "$LOG_FILE" >&2
    exit "$DEPLOY_EXIT_CODE"
fi

printf '\nDeployment launcher completed successfully at %s.\n' "$TARGET_COMMIT"
