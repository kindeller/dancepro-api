#!/usr/bin/env bash

# DancePro V2 manual production deployment
# Run as ec2-user on the live EC2 server.
#
# This script:
#   - requires a clean Git working tree
#   - fetches and previews origin/master
#   - creates a local rollback tag
#   - enters Laravel maintenance mode
#   - performs a fast-forward-only update
#   - installs locked production dependencies
#   - rebuilds Laravel caches
#   - checks/creates the public storage symlink
#   - shows migration status and asks before running migrations
#   - returns the application to service only after success
#
# Database backups remain a separate step until the backup process is automated.

set -Eeuo pipefail

APP_DIR="/var/www/dancepro-api"
BRANCH="master"
REMOTE="origin"
DEPLOY_STARTED=false
DEPLOY_SUCCEEDED=false

log() {
    printf '\n\033[1;34m=== %s ===\033[0m\n' "$1"
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

on_exit() {
    local exit_code=$?

    if [[ "$DEPLOY_STARTED" == true && "$DEPLOY_SUCCEEDED" != true ]]; then
        printf '\n\033[1;31mDeployment did not complete.\033[0m\n'
        printf 'The application has intentionally been left in maintenance mode.\n'
        printf 'Review the error, then either fix and rerun the script or manually run:\n'
        printf '  cd %q && php artisan up\n' "$APP_DIR"
    fi

    exit "$exit_code"
}
trap on_exit EXIT

log "Preflight checks"

[[ -d "$APP_DIR" ]] || fail "Application directory does not exist: $APP_DIR"
cd "$APP_DIR"

command -v git >/dev/null 2>&1 || fail "Git is not installed."
command -v php >/dev/null 2>&1 || fail "PHP is not installed."
command -v composer >/dev/null 2>&1 || fail "Composer is not installed."
[[ -f artisan ]] || fail "Laravel artisan file was not found."

CURRENT_BRANCH="$(git branch --show-current)"
[[ "$CURRENT_BRANCH" == "$BRANCH" ]] || \
    fail "Expected branch '$BRANCH', but '$CURRENT_BRANCH' is checked out."

if [[ -n "$(git status --porcelain)" ]]; then
    git status --short
    fail "The working tree is not clean. Resolve the changes before deploying."
fi

log "Fetch latest code"
git fetch "$REMOTE" "$BRANCH"

CURRENT_COMMIT="$(git rev-parse HEAD)"
REMOTE_COMMIT="$(git rev-parse "$REMOTE/$BRANCH")"

printf 'Current live commit:  %s\n' "$(git log -1 --oneline "$CURRENT_COMMIT")"
printf 'Remote master commit: %s\n' "$(git log -1 --oneline "$REMOTE_COMMIT")"

if [[ "$CURRENT_COMMIT" == "$REMOTE_COMMIT" ]]; then
    printf '\nThe live server is already up to date.\n'
    exit 0
fi

# Refuse unexpected history changes. Production should only fast-forward.
git merge-base --is-ancestor "$CURRENT_COMMIT" "$REMOTE_COMMIT" || \
    fail "origin/$BRANCH cannot fast-forward the current live commit."

log "Commits to deploy"
git log --oneline --decorate "$CURRENT_COMMIT..$REMOTE_COMMIT"

log "Changed files"
git diff --stat "$CURRENT_COMMIT..$REMOTE_COMMIT"

log "Migration files changed by this deployment"
MIGRATION_CHANGES="$(git diff --name-status "$CURRENT_COMMIT..$REMOTE_COMMIT" -- database/migrations || true)"
if [[ -n "$MIGRATION_CHANGES" ]]; then
    printf '%s\n' "$MIGRATION_CHANGES"
    printf '\nReview these migration files before continuing:\n'
    git diff -- "$CURRENT_COMMIT" "$REMOTE_COMMIT" -- database/migrations || true
else
    printf 'No migration files changed.\n'
fi

printf '\nA verified database backup should exist before continuing.\n'
confirm "Deploy these changes to production?" || fail "Deployment cancelled."

log "Create rollback tag"
ROLLBACK_TAG="pre-deploy-$(date -u +'%Y%m%d-%H%M%S')"
git tag "$ROLLBACK_TAG" "$CURRENT_COMMIT"
printf 'Created local rollback tag: %s -> %s\n' "$ROLLBACK_TAG" "$CURRENT_COMMIT"

log "Enable maintenance mode"
php artisan down --retry=60
DEPLOY_STARTED=true

log "Update production branch"
git pull --ff-only "$REMOTE" "$BRANCH"

log "Install production dependencies"
composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist

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
    if confirm "Run 'php artisan migrate --force' anyway to apply any previously pending migrations?"; then
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

log "Final checks before reopening"
[[ -z "$(git status --porcelain)" ]] || {
    git status --short
    fail "Deployment changed tracked or untracked repository files."
}

php artisan about --only=environment

log "Return application to service"
php artisan up
DEPLOY_SUCCEEDED=true

log "Deployment complete"
printf 'Deployed commit: %s\n' "$(git log -1 --oneline)"
printf 'Rollback tag:    %s\n' "$ROLLBACK_TAG"
printf 'Working tree:    clean\n'

printf '\nRecommended manual smoke tests:\n'
printf '  - Sign in with an authorised account\n'
printf '  - Open the competition media page\n'
printf '  - Confirm the logo loads\n'
printf '  - Create and open a download link\n'
printf '  - Check recent logs: tail -50 storage/logs/laravel.log\n'
