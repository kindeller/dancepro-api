#!/usr/bin/env bash

# DancePro V2 production deployment script - V3
#
# Normal deployment:
#   dancepro-deploy
#
# Dry run:
#   dancepro-deploy --dry-run
#
# Optional health check override:
#   HEALTHCHECK_URL="http://127.0.0.1/health" dancepro-deploy
#
# This script:
#   - validates the repository, branch, origin remote and production environment
#   - validates its own tracked state and the complete Git working tree
#   - verifies database connectivity before deployment
#   - fetches and previews origin/master
#   - supports a non-destructive --dry-run mode
#   - creates a timestamped rollback tag
#   - logs the complete deployment
#   - enters Laravel maintenance mode using a pre-rendered maintenance page
#   - performs a fast-forward-only update
#   - installs locked production dependencies when Composer files changed
#   - reviews and optionally runs pending migrations
#   - rebuilds Laravel caches
#   - verifies the public storage symlink
#   - reloads PHP-FPM when available
#   - returns the application to service
#   - performs an HTTP health check
#   - prints rollback commands, timing and a deployment summary
#
# Database backups remain a separate prerequisite until the backup process
# is automated and integrated.

set -Eeuo pipefail

APP_DIR="/var/www/dancepro-api"
BRANCH="master"
REMOTE="origin"
EXPECTED_REMOTE_URL="https://github.com/kindeller/dancepro-api.git"
SCRIPT_RELATIVE_PATH="scripts/dancepro-deploy.sh"
HEALTHCHECK_URL="${HEALTHCHECK_URL:-http://127.0.0.1}"
DEPLOY_LOG_DIR="$APP_DIR/storage/logs/deployments"

DRY_RUN=false
DEPLOY_STARTED=false
DEPLOY_SUCCEEDED=false
ROLLBACK_TAG=""
CURRENT_COMMIT=""
REMOTE_COMMIT=""
MIGRATION_CHANGES=""
COMPOSER_CHANGED=false

START_EPOCH="$(date +%s)"
DEPLOY_TIMESTAMP_UTC="$(date -u +'%Y-%m-%d_%H-%M-%S')"
DEPLOY_LOG_FILE="$DEPLOY_LOG_DIR/deploy-$DEPLOY_TIMESTAMP_UTC.log"

usage() {
    cat <<EOF
Usage:
  $(basename "$0")
  $(basename "$0") --dry-run
  $(basename "$0") --help

Options:
  --dry-run   Fetch and show exactly what would be deployed without changing
              the working tree, application state, database, caches or services.
  --help      Show this help text.

Environment:
  HEALTHCHECK_URL   Override the default health check URL.
                    Default: $HEALTHCHECK_URL
EOF
}

for arg in "$@"; do
    case "$arg" in
        --dry-run)
            DRY_RUN=true
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            printf 'Unknown option: %s\n\n' "$arg" >&2
            usage >&2
            exit 1
            ;;
    esac
done

mkdir -p "$DEPLOY_LOG_DIR"
exec > >(tee -a "$DEPLOY_LOG_FILE") 2>&1

log() {
    printf '\n\033[1;34m=== %s ===\033[0m\n' "$1"
}

warn() {
    printf '\n\033[1;33mWARNING:\033[0m %s\n' "$1"
}

fail() {
    printf '\n\033[1;31mERROR:\033[0m %s\n' "$1" >&2
    exit 1
}

confirm() {
    local prompt="${1:-Continue?}"
    local answer
    read -r -p "$prompt [y/N]: " answer
    [[ "$answer" =~ ^[Yy]$ ]]
}

format_duration() {
    local total_seconds="$1"
    local minutes=$((total_seconds / 60))
    local seconds=$((total_seconds % 60))

    if (( minutes > 0 )); then
        printf '%dm %ds' "$minutes" "$seconds"
    else
        printf '%ds' "$seconds"
    fi
}

print_rollback_instructions() {
    [[ -n "$ROLLBACK_TAG" ]] || return 0

    cat <<EOF

Rollback commands:

  cd "$APP_DIR"
  php artisan down --render="errors.503"
  git reset --hard "$ROLLBACK_TAG"
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
  php artisan optimize:clear
  php artisan config:cache
  php artisan event:cache
  php artisan route:cache
  php artisan view:cache
  php artisan up

Important:
  A code rollback does not reverse database migrations automatically.
  Review database changes before rolling back a deployment that ran migrations.
EOF
}

on_exit() {
    local exit_code=$?
    local end_epoch
    local duration

    end_epoch="$(date +%s)"
    duration="$(format_duration $((end_epoch - START_EPOCH)))"

    if [[ "$DEPLOY_STARTED" == true && "$DEPLOY_SUCCEEDED" != true ]]; then
        printf '\n\033[1;31mDeployment did not complete.\033[0m\n'
        printf 'The application has intentionally been left in maintenance mode.\n'
        printf 'Elapsed time: %s\n' "$duration"
        printf 'Deployment log: %s\n' "$DEPLOY_LOG_FILE"
        print_rollback_instructions
    fi

    exit "$exit_code"
}
trap on_exit EXIT

log "DancePro deployment starting"
printf 'Mode:          %s\n' "$([[ "$DRY_RUN" == true ]] && echo "DRY RUN" || echo "LIVE DEPLOYMENT")"
printf 'Started:       %s UTC\n' "$(date -u +'%Y-%m-%d %H:%M:%S')"
printf 'Application:   %s\n' "$APP_DIR"
printf 'Branch:        %s/%s\n' "$REMOTE" "$BRANCH"
printf 'Health check:  %s\n' "$HEALTHCHECK_URL"
printf 'Log file:      %s\n' "$DEPLOY_LOG_FILE"

log "Preflight validation"

[[ -d "$APP_DIR" ]] || fail "Application directory does not exist: $APP_DIR"
cd "$APP_DIR"

command -v git >/dev/null 2>&1 || fail "Git is not installed."
command -v php >/dev/null 2>&1 || fail "PHP is not installed."
command -v composer >/dev/null 2>&1 || fail "Composer is not installed."
command -v curl >/dev/null 2>&1 || fail "curl is not installed."
command -v systemctl >/dev/null 2>&1 || fail "systemctl is not available."

[[ -f artisan ]] || fail "Laravel artisan file was not found."
[[ -f .env ]] || fail "The production .env file is missing."
[[ -f "$SCRIPT_RELATIVE_PATH" ]] || fail "Tracked deployment script is missing: $SCRIPT_RELATIVE_PATH"

git rev-parse --is-inside-work-tree >/dev/null 2>&1 || \
    fail "$APP_DIR is not a Git working tree."

CURRENT_BRANCH="$(git branch --show-current)"
[[ "$CURRENT_BRANCH" == "$BRANCH" ]] || \
    fail "Expected branch '$BRANCH', but '$CURRENT_BRANCH' is checked out."

ACTUAL_REMOTE_URL="$(git remote get-url "$REMOTE")"
[[ "$ACTUAL_REMOTE_URL" == "$EXPECTED_REMOTE_URL" ]] || \
    fail "Unexpected origin remote: $ACTUAL_REMOTE_URL"

# Explicit self-protection before the broader dirty-tree check.
if ! git diff --quiet -- "$SCRIPT_RELATIVE_PATH" || \
   ! git diff --cached --quiet -- "$SCRIPT_RELATIVE_PATH"; then
    git status --short -- "$SCRIPT_RELATIVE_PATH"
    fail "The deployment script has local changes. Commit or restore it before deploying."
fi

if [[ -n "$(git status --porcelain)" ]]; then
    git status --short
    fail "The working tree is not clean. Resolve the changes before deploying."
fi

APP_ENV_VALUE="$(php artisan env --no-ansi 2>/dev/null | sed -n 's/^Current application environment: //p' | tr -d '[:space:]')"

if [[ "$APP_ENV_VALUE" != "production" ]]; then
    php artisan about --only=environment || true
    fail "This application is not reporting APP_ENV=production."
fi

printf 'Repository:    verified\n'
printf 'Origin remote: verified\n'
printf 'Branch:        verified\n'
printf 'Working tree:  clean\n'
printf 'Environment:   production\n'

log "Database connectivity check"
php artisan migrate:status >/dev/null
printf 'Database connection: OK\n'

log "Fetch latest code"
git fetch "$REMOTE" "$BRANCH"

CURRENT_COMMIT="$(git rev-parse HEAD)"
REMOTE_COMMIT="$(git rev-parse "$REMOTE/$BRANCH")"

printf 'Current live commit:  %s\n' "$(git log -1 --oneline "$CURRENT_COMMIT")"
printf 'Remote master commit: %s\n' "$(git log -1 --oneline "$REMOTE_COMMIT")"

if [[ "$CURRENT_COMMIT" == "$REMOTE_COMMIT" ]]; then
    printf '\nThe live server is already up to date.\n'
    printf 'No deployment is required.\n'
    exit 0
fi

git merge-base --is-ancestor "$CURRENT_COMMIT" "$REMOTE_COMMIT" || \
    fail "origin/$BRANCH cannot fast-forward the current live commit."

log "Commits to deploy"
git log --oneline --decorate "$CURRENT_COMMIT..$REMOTE_COMMIT"

log "Changed files"
git diff --stat "$CURRENT_COMMIT..$REMOTE_COMMIT"

log "Change classification"

if git diff --quiet "$CURRENT_COMMIT" "$REMOTE_COMMIT" -- composer.json composer.lock; then
    printf 'Composer files: unchanged\n'
else
    COMPOSER_CHANGED=true
    printf 'Composer files: changed\n'
fi

if git diff --quiet "$CURRENT_COMMIT" "$REMOTE_COMMIT" -- routes config; then
    printf 'Routes/config: unchanged\n'
else
    printf 'Routes/config: changed\n'
fi

if git diff --quiet "$CURRENT_COMMIT" "$REMOTE_COMMIT" -- resources/views; then
    printf 'Blade views:   unchanged\n'
else
    printf 'Blade views:   changed\n'
fi

MIGRATION_CHANGES="$(git diff --name-status "$CURRENT_COMMIT" "$REMOTE_COMMIT" -- database/migrations || true)"

if [[ -n "$MIGRATION_CHANGES" ]]; then
    printf 'Migrations:    changed\n'
else
    printf 'Migrations:    unchanged\n'
fi

log "Migration review"

if [[ -n "$MIGRATION_CHANGES" ]]; then
    printf '%s\n' "$MIGRATION_CHANGES"
    printf '\nMigration diff:\n'
    git diff "$CURRENT_COMMIT" "$REMOTE_COMMIT" -- database/migrations || true
else
    printf 'No migration files changed.\n'
fi

if [[ "$DRY_RUN" == true ]]; then
    END_EPOCH="$(date +%s)"
    DURATION="$(format_duration $((END_EPOCH - START_EPOCH)))"

    log "Dry run complete"

    cat <<EOF

────────────────────────────────────────────────────────
DancePro Deployment Dry Run
────────────────────────────────────────────────────────
Current commit:  $(git log -1 --oneline "$CURRENT_COMMIT")
Target commit:   $(git log -1 --oneline "$REMOTE_COMMIT")
Composer files:  $([[ "$COMPOSER_CHANGED" == true ]] && echo "changed" || echo "unchanged")
Migration files: $([[ -n "$MIGRATION_CHANGES" ]] && echo "changed" || echo "unchanged")
Elapsed time:    $DURATION
Log file:        $DEPLOY_LOG_FILE
Changes made:    none
────────────────────────────────────────────────────────
EOF

    exit 0
fi

printf '\nA verified database backup should exist before continuing.\n'
confirm "Deploy these changes to production?" || fail "Deployment cancelled."

log "Create rollback tag"
ROLLBACK_TAG="pre-deploy-$(date -u +'%Y%m%d-%H%M%S')"
git tag "$ROLLBACK_TAG" "$CURRENT_COMMIT"
printf 'Created local rollback tag: %s -> %s\n' "$ROLLBACK_TAG" "$CURRENT_COMMIT"

log "Enable maintenance mode"
php artisan down --render="errors.503"
DEPLOY_STARTED=true

log "Update production branch"
git pull --ff-only "$REMOTE" "$BRANCH"

log "Production dependencies"

if [[ "$COMPOSER_CHANGED" == true ]]; then
    composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --prefer-dist
else
    printf 'composer.json and composer.lock are unchanged.\n'
    printf 'Skipping composer install.\n'
fi

log "Clear old Laravel caches"
php artisan optimize:clear

log "Review database migration status"
php artisan migrate:status

if [[ -n "$MIGRATION_CHANGES" ]]; then
    printf '\nThis deployment contains migration-file changes.\n'
    if confirm "Have you reviewed them and want to run pending migrations now?"; then
        php artisan migrate --force
    else
        fail "Migration changes were not approved. Deployment stopped safely."
    fi
else
    printf '\nNo migration files changed in this deployment.\n'
    if confirm "Run pending migrations anyway?"; then
        php artisan migrate --force
    else
        printf 'Skipping migrations.\n'
    fi
fi

log "Rebuild production caches"
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

log "Verify public storage link"
EXPECTED_STORAGE_TARGET="$APP_DIR/storage/app/public"

if [[ -L public/storage ]]; then
    ACTUAL_STORAGE_TARGET="$(readlink -f public/storage || true)"
    [[ "$ACTUAL_STORAGE_TARGET" == "$EXPECTED_STORAGE_TARGET" ]] || \
        fail "public/storage points to '$ACTUAL_STORAGE_TARGET', expected '$EXPECTED_STORAGE_TARGET'."
    printf 'Storage link already correct: public/storage -> %s\n' "$ACTUAL_STORAGE_TARGET"
elif [[ -e public/storage ]]; then
    fail "public/storage exists but is not a symbolic link. Inspect it manually."
else
    php artisan storage:link
    printf 'Storage link created.\n'
fi

log "Reload PHP-FPM"
PHP_FPM_SERVICE=""

for candidate in php-fpm php8.5-fpm php85-php-fpm; do
    if systemctl list-unit-files "$candidate.service" 2>/dev/null | grep -q "^$candidate.service"; then
        PHP_FPM_SERVICE="$candidate"
        break
    fi
done

if [[ -n "$PHP_FPM_SERVICE" ]]; then
    if sudo systemctl reload "$PHP_FPM_SERVICE"; then
        printf 'Reloaded service: %s\n' "$PHP_FPM_SERVICE"
    else
        fail "Could not reload $PHP_FPM_SERVICE."
    fi
else
    warn "No recognised PHP-FPM service was found. Skipping reload."
fi

log "Final checks before reopening"

if [[ -n "$(git status --porcelain)" ]]; then
    git status --short
    fail "Deployment changed tracked or untracked repository files."
fi

php artisan about --only=environment

log "Return application to service"
php artisan up

log "HTTP health check"
HEALTHCHECK_ATTEMPTS=5
HEALTHCHECK_DELAY_SECONDS=2
HEALTHCHECK_OK=false

for ((attempt=1; attempt<=HEALTHCHECK_ATTEMPTS; attempt++)); do
    HTTP_STATUS="$(curl \
        --silent \
        --show-error \
        --output /dev/null \
        --write-out '%{http_code}' \
        --max-time 10 \
        "$HEALTHCHECK_URL" || true)"

    printf 'Attempt %d/%d: HTTP %s\n' \
        "$attempt" "$HEALTHCHECK_ATTEMPTS" "${HTTP_STATUS:-000}"

    if [[ "$HTTP_STATUS" =~ ^(200|204|301|302)$ ]]; then
        HEALTHCHECK_OK=true
        break
    fi

    sleep "$HEALTHCHECK_DELAY_SECONDS"
done

if [[ "$HEALTHCHECK_OK" != true ]]; then
    php artisan down --render="errors.503"
    fail "Health check failed. The application was returned to maintenance mode."
fi

DEPLOY_SUCCEEDED=true

END_EPOCH="$(date +%s)"
DURATION="$(format_duration $((END_EPOCH - START_EPOCH)))"
DEPLOYED_COMMIT="$(git rev-parse HEAD)"
LARAVEL_VERSION="$(php artisan --version | sed 's/^Laravel Framework //')"
PHP_VERSION="$(php -r 'echo PHP_VERSION;')"

log "Deployment successful"

cat <<EOF

────────────────────────────────────────────────────────
DancePro Deployment Successful
────────────────────────────────────────────────────────
Application:    DancePro V2
Laravel:        $LARAVEL_VERSION
PHP:            $PHP_VERSION
Branch:         $BRANCH
Commit:         $(git log -1 --oneline "$DEPLOYED_COMMIT")
Completed:      $(date -u +'%Y-%m-%d %H:%M:%S') UTC
Duration:       $DURATION
Rollback tag:   $ROLLBACK_TAG
Deployment log: $DEPLOY_LOG_FILE
Health check:   $HEALTHCHECK_URL
Working tree:   clean
────────────────────────────────────────────────────────
EOF

print_rollback_instructions

printf '\nRecommended manual smoke tests:\n'
printf '  - Sign in with an authorised account\n'
printf '  - Open the competition media page\n'
printf '  - Confirm the logo loads\n'
printf '  - Create and open a download link\n'
printf '  - Check recent logs: tail -50 storage/logs/laravel.log\n'