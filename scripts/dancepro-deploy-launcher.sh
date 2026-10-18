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
readonly BACKUP_S3_BUCKET="dance-pro-db-backup"
readonly BACKUP_S3_PREFIX="deployments"
readonly AWS_REGION="ap-southeast-2"

DRY_RUN=false
TARGET_COMMIT=""
CURRENT_COMMIT=""
ROLLBACK_TAG=""
BACKUP_TEMP_DIR=""
BACKUP_KEY=""
MAINTENANCE_ENABLED=false
MAINTENANCE_REQUESTED=false

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

cleanup() {
    if [[ -n "$BACKUP_TEMP_DIR" && -d "$BACKUP_TEMP_DIR" ]]; then
        rm -f -- "$BACKUP_TEMP_DIR/client.cnf" "$BACKUP_TEMP_DIR/database-name" \
            "$BACKUP_TEMP_DIR/database.sql" "$BACKUP_TEMP_DIR/database.sql.gz"
        rmdir "$BACKUP_TEMP_DIR" 2>/dev/null || true
    fi
}

on_exit() {
    local exit_code=$?
    trap - EXIT
    cleanup

    if ((exit_code != 0)); then
        printf '\nDeployment launcher failed with exit code %d.\n' "$exit_code" >&2
        if [[ "$MAINTENANCE_ENABLED" == true ]]; then
            printf 'The application remains in maintenance mode to avoid serving an unverified release.\n' >&2
        elif [[ "$MAINTENANCE_REQUESTED" == true ]]; then
            printf 'The checkout was not changed, but maintenance mode could not be confirmed; verify application state.\n' >&2
        else
            printf 'The production checkout and running application were not changed.\n' >&2
        fi
    fi

    exit "$exit_code"
}
trap on_exit EXIT

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

[[ -f artisan && -f .env && -f vendor/autoload.php ]] || \
    fail "The Laravel application, production .env, or Composer autoloader is missing."
[[ -w storage && -w bootstrap/cache ]] || \
    fail "storage and bootstrap/cache must be writable by the deployment user."

for required_command in php aws gzip stat mktemp; do
    command -v "$required_command" >/dev/null 2>&1 || fail "$required_command is not installed."
done

DUMP_COMMAND=""
if command -v mariadb-dump >/dev/null 2>&1; then
    DUMP_COMMAND="mariadb-dump"
elif command -v mysqldump >/dev/null 2>&1; then
    DUMP_COMMAND="mysqldump"
else
    fail "Neither mariadb-dump nor mysqldump is installed."
fi

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

log "Create and verify pre-deployment database backup"
BACKUP_TEMP_DIR="$(mktemp -d /tmp/dancepro-deploy-backup.XXXXXX)"
chmod 700 "$BACKUP_TEMP_DIR"

php -r '
require $argv[1]."/vendor/autoload.php";
$dotenv = Dotenv\Dotenv::createImmutable($argv[1]);
$dotenv->load();
$get = static fn (string $key, ?string $default = null): ?string =>
    array_key_exists($key, $_ENV) ? (string) $_ENV[$key] : $default;
if ($get("APP_ENV") !== "production") {
    fwrite(STDERR, "APP_ENV must be production.\n");
    exit(2);
}
if (! in_array($get("DB_CONNECTION"), ["mysql", "mariadb"], true)) {
    fwrite(STDERR, "Production database must use the mysql or mariadb driver.\n");
    exit(2);
}
if (($get("DB_URL", "") ?? "") !== "") {
    fwrite(STDERR, "DB_URL is not supported by the deployment backup; configure discrete DB_* values.\n");
    exit(2);
}
$values = [
    "host" => $get("DB_HOST", "127.0.0.1"),
    "port" => $get("DB_PORT", "3306"),
    "user" => $get("DB_USERNAME", ""),
    "password" => $get("DB_PASSWORD", ""),
];
$socket = $get("DB_SOCKET", "");
if ($socket !== "") {
    $values["socket"] = $socket;
}
foreach ($values as $value) {
    if (str_contains($value, "\n") || str_contains($value, "\r") || str_contains($value, "\0")) {
        fwrite(STDERR, "Database credentials contain unsupported control characters.\n");
        exit(2);
    }
}
$lines = ["[client]"];
foreach ($values as $key => $value) {
    $lines[] = $key."=\"".addcslashes($value, "\\\"")."\"";
}
file_put_contents($argv[2], implode("\n", $lines)."\n", LOCK_EX);
chmod($argv[2], 0600);
$name = $get("DB_DATABASE", "");
if ($name === "" || str_contains($name, "\n") || str_contains($name, "\r") || str_contains($name, "\0")) {
    fwrite(STDERR, "Database name is missing or invalid.\n");
    exit(2);
}
file_put_contents($argv[3], $name, LOCK_EX);
chmod($argv[3], 0600);
' "$APP_DIR" "$BACKUP_TEMP_DIR/client.cnf" "$BACKUP_TEMP_DIR/database-name"

DB_NAME="$(<"$BACKUP_TEMP_DIR/database-name")"
BACKUP_IDENTIFIER="$(date -u +'%Y-%m-%d_%H-%M-%S')-${TARGET_COMMIT:0:12}"
BACKUP_FILE="$BACKUP_TEMP_DIR/database.sql.gz"
BACKUP_KEY="$BACKUP_S3_PREFIX/dancepro-db-$BACKUP_IDENTIFIER.sql.gz"

"$DUMP_COMMAND" \
    --defaults-extra-file="$BACKUP_TEMP_DIR/client.cnf" \
    --single-transaction \
    --quick \
    --routines \
    --triggers \
    --events \
    --result-file="$BACKUP_TEMP_DIR/database.sql" \
    -- "$DB_NAME"

[[ -s "$BACKUP_TEMP_DIR/database.sql" ]] || fail "The database dump is empty."
gzip -9 "$BACKUP_TEMP_DIR/database.sql"
BACKUP_FILE="$BACKUP_TEMP_DIR/database.sql.gz"
[[ -s "$BACKUP_FILE" ]] || fail "The compressed database backup is empty."
gzip -t "$BACKUP_FILE"

LOCAL_BACKUP_BYTES="$(stat -c %s "$BACKUP_FILE")"
aws s3 cp "$BACKUP_FILE" "s3://$BACKUP_S3_BUCKET/$BACKUP_KEY" \
    --region "$AWS_REGION" \
    --no-progress \
    --only-show-errors \
    --metadata "deployment-commit=$TARGET_COMMIT"

S3_BACKUP_BYTES="$(aws s3api head-object \
    --bucket "$BACKUP_S3_BUCKET" \
    --key "$BACKUP_KEY" \
    --region "$AWS_REGION" \
    --query ContentLength \
    --output text)"

[[ "$S3_BACKUP_BYTES" =~ ^[0-9]+$ ]] || fail "S3 returned an invalid backup size."
[[ "$LOCAL_BACKUP_BYTES" == "$S3_BACKUP_BYTES" ]] || \
    fail "The uploaded backup size does not match the local backup."
printf 'Verified backup: s3://%s/%s (%s bytes)\n' \
    "$BACKUP_S3_BUCKET" "$BACKUP_KEY" "$S3_BACKUP_BYTES"

cleanup
BACKUP_TEMP_DIR=""

log "Enable maintenance mode before checkout"
MAINTENANCE_REQUESTED=true
if [[ -f resources/views/errors/503.blade.php ]]; then
    php artisan down --render="errors.503"
else
    php artisan down
fi
MAINTENANCE_ENABLED=true

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
    --rollback-tag "$ROLLBACK_TAG" \
    --verified-backup-key "$BACKUP_KEY"
DEPLOY_EXIT_CODE=$?
set -e

if ((DEPLOY_EXIT_CODE != 0)); then
    printf 'ERROR: Application deployment failed with exit code %d.\n' "$DEPLOY_EXIT_CODE" >&2
    printf 'The checkout remains at %s for diagnosis. See %s.\n' "$TARGET_COMMIT" "$LOG_FILE" >&2
    exit "$DEPLOY_EXIT_CODE"
fi

printf '\nDeployment launcher completed successfully at %s.\n' "$TARGET_COMMIT"
