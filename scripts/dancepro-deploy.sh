#!/usr/bin/env bash
# One manual/SSM entry point. Execute a private snapshot before changing Git.
set -Eeuo pipefail
set +x

if [[ "${1:-}" == --help || "${1:-}" == -h ]]; then
    printf 'Usage: bash scripts/dancepro-deploy.sh [--dry-run] <approved-full-master-sha>\n'
    exit 0
fi
if [[ "${DANCEPRO_SNAPSHOT_FILE:-}" != "${BASH_SOURCE[0]}" || "${DANCEPRO_SNAPSHOT_FILE:-}" != /tmp/dancepro-deploy.* ]]; then
    snapshot="$(mktemp /tmp/dancepro-deploy.XXXXXX)"
    cp -- "${BASH_SOURCE[0]}" "$snapshot"
    export DANCEPRO_SNAPSHOT_FILE="$snapshot"
    exec /bin/bash "$snapshot" "$@"
fi

readonly APP_DIR="${DANCEPRO_APP_DIR:-/var/www/dancepro-api}"
readonly DEPLOY_USER="${DANCEPRO_DEPLOY_USER:-ec2-user}"
readonly RUNTIME_USER="${DANCEPRO_RUNTIME_USER:-apache}"
readonly APP_GROUP="${DANCEPRO_APP_GROUP:-apache}"
readonly PHP_BIN="${DANCEPRO_PHP_BIN:-/usr/bin/php}"
readonly PHP_FPM_SERVICE="${DANCEPRO_PHP_FPM_SERVICE:-php-fpm.service}"
readonly ORIGIN_HOST="${DANCEPRO_ORIGIN_HOST:-dancepro.dotdeveloper.com.au}"
readonly ORIGIN_SCHEME="${DANCEPRO_ORIGIN_SCHEME:-http}"
readonly ORIGIN_PORT="${DANCEPRO_ORIGIN_PORT:-80}"
readonly EXPECTED_REMOTE_URL="${DANCEPRO_EXPECTED_REMOTE_URL:-https://github.com/kindeller/dancepro-api.git}"
readonly LOCK_FILE="${DANCEPRO_DEPLOY_LOCK_FILE:-/var/lock/dancepro-deploy.lock}"
readonly LOG_DIR="${DANCEPRO_DEPLOY_LOG_DIR:-/var/log/dancepro-deploy}"
readonly BACKUP_S3_BUCKET="dance-pro-db-backup"
readonly BACKUP_S3_PREFIX=deployments
readonly AWS_REGION=ap-southeast-2
readonly BRANCH=master
DRY_RUN=false
TARGET_COMMIT="" CURRENT_COMMIT="" ROLLBACK_TAG="" BACKUP_TEMP_DIR="" BACKUP_KEY=""
MAINTENANCE_REQUESTED=false
umask 077
RUN_DIR="$(mktemp -d /tmp/dancepro-checks.XXXXXX)"
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
export GIT_TERMINAL_PROMPT=0 COMPOSER_NO_INTERACTION=1

fail() { printf 'ERROR: %s\n' "$1" >&2; exit 1; }
log() { printf '\n=== %s ===\n' "$1"; }
artisan() { (umask 007; sudo -n -u "$RUNTIME_USER" -- "$PHP_BIN" "$APP_DIR/artisan" "$@"); }

# Apache creates Laravel files. Only deployment-owned generated leftovers need
# repair; never chmod arbitrary storage, credentials, uploads, or .git metadata.
permissions() {
    git ls-files -z > "$RUN_DIR/tracked"
    "$PHP_BIN" /dev/stdin "$APP_DIR" "$APP_GROUP" "$(id -u)" "$1" "$RUN_DIR/tracked" <<'PHP'
<?php
[$script, $root, $group, $owner, $scope, $manifest] = $argv;
chdir($root);
$device = stat('.')['dev'];
$nodes = [];
$add = function (string $path) use (&$nodes, $device, $owner, $scope): void {
    $parent = $path;
    while ($parent !== '.') {
        if (is_link($parent)) { throw new RuntimeException("Refusing symlink: $parent"); }
        $parent = dirname($parent);
    }
    $stat = lstat($path);
    if ($stat === false || $stat['dev'] !== $device || (!is_dir($path) && (!is_file($path) || $stat['nlink'] !== 1))) {
        throw new RuntimeException("Unsafe file or filesystem boundary: $path");
    }
    if ($stat['uid'] !== (int) $owner) {
        if ($scope === 'generated') { return; }
        throw new RuntimeException("Operator must review ownership: $path");
    }
    $public = $path === 'public' || str_starts_with($path, 'public/');
    $nodes[$path] = $scope === 'generated'
        ? (is_dir($path) ? 02770 : 0660)
        : (is_dir($path) ? ($public ? 02755 : 02750) : ($public ? 0644 : 0640) | (($stat['mode'] & 0111) ? ($public ? 0111 : 0110) : 0));
};
if ($scope === 'code') {
    foreach (explode("\0", file_get_contents($manifest)) as $path) {
        if ($path === '' || preg_match('~^(storage/|bootstrap/cache/|\.env(?:\.|$)|\.git/)~', $path)) { continue; }
        $add($path);
        for ($parent = dirname($path); $parent !== '.'; $parent = dirname($parent)) { $add($parent); }
    }
    $trees = ['vendor'];
} else {
    $trees = ['bootstrap/cache', 'storage/framework/views'];
    foreach (['storage/framework/down', 'storage/framework/maintenance.php'] as $path) {
        if (file_exists($path) || is_link($path)) { $add($path); }
    }
}
foreach ($trees as $tree) {
    $add($tree);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tree, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $entry) { $add($entry->getPathname()); }
}
// Validate the full scope before changing modes. No symlinks are followed.
foreach ($nodes as $path => $mode) {
    if (!chgrp($path, $group) || !chmod($path, $mode)) { throw new RuntimeException("Cannot prepare permissions: $path"); }
}
PHP
}

http_once() {
    local path="$1" expected="${2:-ready}" status type result accept=text/html
    [[ "$path" != /up && "$path" != /api/studios ]] || accept=application/json
    result="$(curl --silent --show-error --noproxy '*' \
        --resolve "$ORIGIN_HOST:$ORIGIN_PORT:127.0.0.1" --header "Accept: $accept" \
        --connect-timeout 3 --max-time 10 --output "$RUN_DIR/body" \
        --write-out '%{http_code}\n%{content_type}' "$ORIGIN_SCHEME://$ORIGIN_HOST:$ORIGIN_PORT$path")" || return 1
    status="${result%%$'\n'*}"; type="${result#*$'\n'}"
    if [[ "$expected" == closed ]]; then
        [[ "$status" == 503 ]] && grep -Eq 'temporarily unavailable|DancePro deployment maintenance' "$RUN_DIR/body"
        return
    fi
    [[ "$status" == 200 ]] || return 1
    case "$path" in
        /|/login)
            [[ "$type" == text/html* ]] || return 1
            if [[ "$path" == / ]]; then grep -Fq 'Find your dance studio.' "$RUN_DIR/body"
            else grep -Fq '<title>DancePro Login</title>' "$RUN_DIR/body"; fi ;;
        /up|/api/studios)
            [[ "$type" == application/json* ]] || return 1
            "$PHP_BIN" -r '
                $data = json_decode(file_get_contents($argv[2]), true);
                $ok = is_array($data) && ($argv[1] === "/up" ? ($data["status"] ?? null) === "up"
                    : ($data["success"] ?? null) === true && isset($data["data"]) && is_array($data["data"]) && array_is_list($data["data"]));
                exit($ok ? 0 : 1);
            ' "$path" "$RUN_DIR/body" ;;
        /js/admin-concert-uploader.js)
            [[ "$type" == *javascript* ]] && cmp -s public/js/admin-concert-uploader.js "$RUN_DIR/body" ;;
        *) return 1 ;;
    esac
}

http_check() {
    local attempt
    for attempt in 1 2 3; do
        if http_once "$@"; then return 0; fi
        ((attempt == 3)) || sleep 1
    done
    return 1
}

# If Laravel cannot boot after a failure, close the public entry point without
# loading dependencies. Only the two exact maintenance paths are replaced.
close_on_failure() (
    artisan down --render=errors.503 && http_check / closed && return 0
    [[ "$(readlink -f storage/framework)" == "$APP_DIR/storage/framework" ]] || return 1
    for path in storage/framework/down storage/framework/maintenance.php; do
        [[ ! -L "$path" && (! -f "$path" || "$(stat -c %h "$path")" == 1) ]] || return 1
    done
    printf '{"status":503,"template":"DancePro deployment maintenance."}\n' > "$RUN_DIR/down"
    printf '<?php http_response_code(503); header("Content-Type: text/plain"); echo "DancePro deployment maintenance."; exit;\n' > "$RUN_DIR/maintenance.php"
    chgrp "$APP_GROUP" "$RUN_DIR/down" "$RUN_DIR/maintenance.php" || return 1
    chmod 0640 "$RUN_DIR/down" "$RUN_DIR/maintenance.php" || return 1
    mv -T "$RUN_DIR/down" storage/framework/down || return 1
    mv -T "$RUN_DIR/maintenance.php" storage/framework/maintenance.php || return 1
    http_check / closed
)

on_exit() {
    local status=$?
    trap - EXIT
    if ((status != 0)); then
        if [[ "$MAINTENANCE_REQUESTED" == true ]]; then
            if close_on_failure; then printf 'Deployment failed; maintenance HTTP 503 verified.\n' >&2
            else printf 'CRITICAL: maintenance could not be verified. Block traffic at Nginx and inspect locally.\n' >&2; fi
        fi
        printf 'Review the deployment log and migration state. Rollback reference: %s. No database rollback/restore was attempted.\n' "${ROLLBACK_TAG:-not-created}" >&2
    fi
    if [[ -n "$BACKUP_TEMP_DIR" && -d "$BACKUP_TEMP_DIR" ]]; then
        rm -f -- "$BACKUP_TEMP_DIR/"{client.cnf,database-name,database.sql,database.sql.gz}
        rmdir "$BACKUP_TEMP_DIR" 2>/dev/null || true
    fi
    rm -f -- "$RUN_DIR/"{tracked,body,down,maintenance.php}
    rmdir "$RUN_DIR" 2>/dev/null || true
    rm -f -- "$DANCEPRO_SNAPSHOT_FILE"
    exit "$status"
}
trap on_exit EXIT

[[ "${1:-}" != --dry-run ]] || { DRY_RUN=true; shift; }
[[ $# == 1 && "$1" =~ ^[0-9a-f]{40}$ ]] || fail 'Supply one lowercase, 40-character approved commit SHA.'
TARGET_COMMIT="$1"
[[ "$(id -un)" == "$DEPLOY_USER" && "$DEPLOY_USER" != "$RUNTIME_USER" ]] || fail 'Run as the deployment user, not root or PHP-FPM.'
[[ "$ORIGIN_HOST" =~ ^[a-zA-Z0-9.-]+$ && "$ORIGIN_PORT" =~ ^[0-9]+$ && ("$ORIGIN_SCHEME" == http || "$ORIGIN_SCHEME" == https) ]] || fail 'Invalid origin settings.'
cd "$APP_DIR"
for command in git php composer curl sudo flock gzip stat mktemp; do command -v "$command" >/dev/null || fail "Missing command: $command"; done
id -nG | tr ' ' '\n' | grep -Fxq "$APP_GROUP" || fail "Deployment user needs membership of $APP_GROUP."
[[ "$(stat -c %G .)" == "$APP_GROUP" && -w . ]] || fail 'Provision the application root with the shared group and deployment ownership.'
for directory in storage/framework storage/framework/views bootstrap/cache; do
    [[ -d "$directory" && -w "$directory" && "$(readlink -f "$directory")" == "$APP_DIR/$directory" ]] || fail "Review runtime directory: $directory"
done
mkdir -p "$LOG_DIR"
readonly LOG_FILE="$LOG_DIR/deploy-$(date -u +%Y%m%d-%H%M%S)-${TARGET_COMMIT:0:12}.log"
exec > >(tee -a "$LOG_FILE") 2>&1
exec 9>"$LOCK_FILE"
flock -n 9 || fail 'Another DancePro deployment is already running.'
[[ -L public/storage && "$(readlink -f public/storage)" == "$APP_DIR/storage/app/public" ]] || fail 'Review the public/storage link before deploying.'
[[ -f .env && -f vendor/autoload.php ]] || fail 'Production configuration or dependencies are missing.'
[[ "$(git branch --show-current)" == master && "$(git remote get-url origin)" == "$EXPECTED_REMOTE_URL" ]] || fail 'Unexpected branch or origin.'
[[ -z "$(git status --porcelain)" ]] || fail 'Production working tree is not clean.'
CURRENT_COMMIT="$(git rev-parse HEAD)"
(umask 027; git fetch --no-tags origin +refs/heads/master:refs/remotes/origin/master)
git cat-file -e "$TARGET_COMMIT^{commit}"
git merge-base --is-ancestor "$TARGET_COMMIT" origin/master || fail 'Target is not on origin/master.'
git merge-base --is-ancestor "$CURRENT_COMMIT" "$TARGET_COMMIT" || fail 'Target is not a fast-forward.'
artisan list --raw > /dev/null
sudo -n -l -- /usr/bin/systemctl reload "$PHP_FPM_SERVICE" >/dev/null || fail 'The exact PHP-FPM reload command needs noninteractive permission.'
/usr/bin/systemctl is-active --quiet "$PHP_FPM_SERVICE" || fail 'PHP-FPM is not active.'
http_check /up || fail 'Current origin health failed; investigate before deployment.'
if [[ "$DRY_RUN" == true ]]; then
    git diff --stat "$CURRENT_COMMIT..$TARGET_COMMIT"
    printf 'Dry run: no checkout, backup, permission, migration, or maintenance changes.\n'
    exit 0
fi
command -v aws >/dev/null || fail 'AWS CLI is missing.'
if command -v mariadb-dump >/dev/null; then DUMP_COMMAND=mariadb-dump
elif command -v mysqldump >/dev/null; then DUMP_COMMAND=mysqldump
else fail 'A MySQL/MariaDB dump tool is required.'; fi

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

rm -f -- "$BACKUP_TEMP_DIR/"{client.cnf,database-name,database.sql,database.sql.gz}
rmdir "$BACKUP_TEMP_DIR"
BACKUP_TEMP_DIR=""


log 'Enable maintenance before updating code'
MAINTENANCE_REQUESTED=true
permissions code
permissions generated
artisan down --render=errors.503
http_check / closed || fail 'Maintenance response not verified; checkout was not changed.'
ROLLBACK_TAG="pre-deploy-$(date -u +%Y%m%d-%H%M%S)-${CURRENT_COMMIT:0:12}"
git tag "$ROLLBACK_TAG" "$CURRENT_COMMIT"
log 'Update approved release'
(umask 027; git merge --ff-only "$TARGET_COMMIT")
permissions code
log 'Install locked dependencies'
(umask 027; composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress)
permissions code
permissions generated
log 'Migrate and rebuild caches as Apache'
for command in config:clear event:clear route:clear view:clear; do artisan "$command"; done
artisan migrate --force || fail 'Migration failed and may be partially applied; review before recovery.'
for command in config:cache event:cache route:cache view:cache; do artisan "$command"; done
artisan list --raw > /dev/null
[[ -L public/storage && "$(readlink -f public/storage)" == "$APP_DIR/storage/app/public" ]] || fail 'Review public/storage; it must point to storage/app/public.'
artisan queue:restart
sudo -n /usr/bin/systemctl reload "$PHP_FPM_SERVICE"
http_check /up || fail 'PHP-FPM health failed while maintenance remained active.'
http_check /js/admin-concert-uploader.js || fail 'Static asset check failed.'
[[ "$(git rev-parse HEAD)" == "$TARGET_COMMIT" && -z "$(git status --porcelain)" ]] || fail 'Checkout changed during deployment.'
log 'Reopen and check application responses'
artisan up
# Cache compilation/bootstrap and FPM /up passed before reopening. Full route
# responses are checked immediately afterward; any failure re-enters maintenance.
for path in /up / /login /api/studios; do http_check "$path" || fail "Application response check failed: $path"; done
printf '{"commit":"%s","rollback_tag":"%s","backup_key":"%s"}\n' \
    "$TARGET_COMMIT" "$ROLLBACK_TAG" "$BACKUP_KEY" > "$LOG_DIR/current-deployment.json"
MAINTENANCE_REQUESTED=false
printf 'Deployment successful: %s\nRollback reference: %s\nLog: %s\n' "$TARGET_COMMIT" "$ROLLBACK_TAG" "$LOG_FILE"
