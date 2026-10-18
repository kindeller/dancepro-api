#!/usr/bin/env bash

# Version-controlled, unattended DancePro production deployment.
# The independently installed dancepro-deploy launcher updates the checkout and
# invokes this script with the exact approved commit and rollback reference.

set -Eeuo pipefail

readonly APP_DIR="/var/www/dancepro-api"
readonly BRANCH="master"
readonly EXPECTED_REMOTE_URL="https://github.com/kindeller/dancepro-api.git"
readonly BACKUP_S3_BUCKET="dance-pro-db-backup"
readonly HEALTHCHECK_URL="${HEALTHCHECK_URL:-http://127.0.0.1/up}"
readonly DEPLOY_LOG_DIR="$APP_DIR/storage/logs/deployments"

FROM_COMMIT=""
TARGET_COMMIT=""
ROLLBACK_TAG=""
BACKUP_KEY=""
MAINTENANCE_ENABLED=false
START_EPOCH="$(date +%s)"

usage() {
    cat <<'EOF'
Usage:
  scripts/dancepro-deploy.sh \
    --from-commit <40-character-sha> \
    --target-commit <40-character-sha> \
    --rollback-tag <validated-tag> \
    --verified-backup-key <validated-s3-key>

This application script is invoked by the stable production launcher. It does
not update Git and does not prompt for input.
EOF
}

fail() {
    printf 'ERROR: %s\n' "$1" >&2
    exit 1
}

log() {
    printf '\n=== %s ===\n' "$1"
}

format_duration() {
    local total_seconds="$1"
    printf '%dm %ds' "$((total_seconds / 60))" "$((total_seconds % 60))"
}

while (($# > 0)); do
    case "$1" in
        --from-commit|--target-commit|--rollback-tag|--verified-backup-key)
            (($# >= 2)) || fail "Missing value for $1."
            case "$1" in
                --from-commit) FROM_COMMIT="$2" ;;
                --target-commit) TARGET_COMMIT="$2" ;;
                --rollback-tag) ROLLBACK_TAG="$2" ;;
                --verified-backup-key) BACKUP_KEY="$2" ;;
            esac
            shift 2
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            usage >&2
            fail "Unknown option: $1"
            ;;
    esac
done

[[ "$FROM_COMMIT" =~ ^[0-9a-f]{40}$ ]] || fail "Invalid source commit SHA."
[[ "$TARGET_COMMIT" =~ ^[0-9a-f]{40}$ ]] || fail "Invalid target commit SHA."
[[ "$ROLLBACK_TAG" =~ ^pre-deploy-[0-9]{8}-[0-9]{6}-[0-9a-f]{12}$ ]] || fail "Invalid rollback tag."
[[ "$BACKUP_KEY" =~ ^deployments/dancepro-db-[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}-[0-9]{2}-[0-9a-f]{12}\.sql\.gz$ ]] || \
    fail "Invalid verified database backup key."

umask 077
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
export COMPOSER_NO_INTERACTION=1

mkdir -p "$DEPLOY_LOG_DIR"
readonly DEPLOY_LOG_FILE="$DEPLOY_LOG_DIR/deploy-$(date -u +'%Y-%m-%d_%H-%M-%S')-${TARGET_COMMIT:0:12}.log"
exec > >(tee -a "$DEPLOY_LOG_FILE") 2>&1

print_recovery_instructions() {
    cat <<EOF

Recovery requires operator review:
  1. Inspect $DEPLOY_LOG_FILE and storage/logs/laravel.log.
  2. Determine whether any migration was applied. Migrations are not
     automatically reversed and the database backup is not auto-restored.
  3. If a code rollback is safe, reset to tag $ROLLBACK_TAG, reinstall locked
     dependencies, rebuild caches, reload PHP-FPM, test, then run artisan up.
EOF
}

on_exit() {
    local exit_code=$?
    trap - EXIT

    if ((exit_code != 0)); then
        printf '\nDeployment failed with exit code %d.\n' "$exit_code" >&2
        if [[ "$MAINTENANCE_ENABLED" == true ]]; then
            printf 'The application remains in maintenance mode to avoid serving an unverified release.\n' >&2
            print_recovery_instructions >&2
        else
            printf 'The running application was not placed in maintenance mode.\n' >&2
        fi
    fi

    exit "$exit_code"
}
trap on_exit EXIT

log "Preflight validation"
[[ "$PWD" == "$APP_DIR" ]] || fail "This script must run from $APP_DIR."
[[ -f artisan && -f .env ]] || fail "The Laravel application or production .env file is missing."

for required_command in git php composer curl; do
    command -v "$required_command" >/dev/null 2>&1 || fail "$required_command is not installed."
done

[[ "$(git branch --show-current)" == "$BRANCH" ]] || fail "The production checkout is not on $BRANCH."
[[ "$(git remote get-url origin)" == "$EXPECTED_REMOTE_URL" ]] || fail "The origin remote is not authorised."
[[ "$(git rev-parse HEAD)" == "$TARGET_COMMIT" ]] || fail "The checkout does not match the approved target commit."
git rev-parse "$ROLLBACK_TAG^{commit}" >/dev/null 2>&1 || fail "The rollback tag does not exist."
[[ "$(git rev-parse "$ROLLBACK_TAG^{commit}")" == "$FROM_COMMIT" ]] || fail "The rollback tag does not match the source commit."

if [[ -n "$(git status --porcelain)" ]]; then
    git status --short
    fail "The production working tree is not clean."
fi

[[ -w storage && -w bootstrap/cache ]] || fail "storage and bootstrap/cache must be writable by the deployment user."

printf 'Commit:      %s\n' "$TARGET_COMMIT"
printf 'Verified backup: s3://%s/%s\n' "$BACKUP_S3_BUCKET" "$BACKUP_KEY"

MAINTENANCE_ENABLED=true
log "Refresh maintenance mode with approved release"
if [[ -f resources/views/errors/503.blade.php ]]; then
    php artisan down --render="errors.503"
else
    php artisan down
fi

log "Install locked production dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress

log "Clear old Laravel caches"
php artisan optimize:clear

log "Run database migrations"
if ! php artisan migrate --force; then
    printf 'Migration failure: one or more migrations may have been partially applied.\n' >&2
    fail "Database migration failed; automatic database restore is intentionally disabled."
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
    [[ "$ACTUAL_STORAGE_TARGET" == "$EXPECTED_STORAGE_TARGET" ]] || fail "public/storage points to an unexpected target: $ACTUAL_STORAGE_TARGET"
elif [[ -e public/storage ]]; then
    fail "public/storage exists but is not a symbolic link."
else
    php artisan storage:link
fi

log "Restart workers and reload PHP-FPM"
php artisan queue:restart

PHP_FPM_SERVICE=""
for candidate in php-fpm php8.5-fpm php85-php-fpm php8.4-fpm php8.3-fpm; do
    if systemctl list-unit-files "$candidate.service" 2>/dev/null | grep -q "^$candidate.service"; then
        PHP_FPM_SERVICE="$candidate"
        break
    fi
done

if [[ -n "$PHP_FPM_SERVICE" ]]; then
    sudo -n systemctl reload "$PHP_FPM_SERVICE" || fail "Could not reload $PHP_FPM_SERVICE without an interactive prompt."
    printf 'Reloaded service: %s\n' "$PHP_FPM_SERVICE"
else
    printf 'WARNING: no recognised PHP-FPM service was found; no service was reloaded.\n'
fi

log "Final integrity checks"
[[ "$(git rev-parse HEAD)" == "$TARGET_COMMIT" ]] || fail "The deployed commit changed during deployment."
if [[ -n "$(git status --porcelain)" ]]; then
    git status --short
    fail "Deployment changed files inside the Git working tree."
fi

log "Return application to service"
php artisan up
MAINTENANCE_ENABLED=false

log "HTTP health check"
HEALTHCHECK_OK=false
for attempt in 1 2 3 4 5; do
    HTTP_STATUS="$(curl --silent --show-error --output /dev/null --write-out '%{http_code}' --max-time 10 "$HEALTHCHECK_URL" || true)"
    printf 'Attempt %d/5: HTTP %s\n' "$attempt" "${HTTP_STATUS:-000}"
    if [[ "$HTTP_STATUS" == "200" ]]; then
        HEALTHCHECK_OK=true
        break
    fi
    sleep 2
done

if [[ "$HEALTHCHECK_OK" != true ]]; then
    php artisan down --render="errors.503"
    MAINTENANCE_ENABLED=true
    fail "Health check failed."
fi

COMPLETED_AT="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
printf '{"commit":"%s","rollback_tag":"%s","backup_key":"%s","completed_at":"%s"}\n' \
    "$TARGET_COMMIT" "$ROLLBACK_TAG" "$BACKUP_KEY" "$COMPLETED_AT" \
    > "$DEPLOY_LOG_DIR/current-deployment.json"

END_EPOCH="$(date +%s)"
log "Deployment successful"
printf 'Commit:         %s\n' "$TARGET_COMMIT"
printf 'Rollback tag:   %s\n' "$ROLLBACK_TAG"
printf 'Backup:         s3://%s/%s\n' "$BACKUP_S3_BUCKET" "$BACKUP_KEY"
printf 'Health check:   %s\n' "$HEALTHCHECK_URL"
printf 'Deployment log: %s\n' "$DEPLOY_LOG_FILE"
printf 'Duration:       %s\n' "$(format_duration $((END_EPOCH - START_EPOCH)))"
