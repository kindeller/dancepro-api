#!/usr/bin/env bash
set -Eeuo pipefail
readonly PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
for script in dancepro-deploy.sh; do
    bash -n "$PROJECT_ROOT/scripts/$script"
done
if [[ "${1:-}" != --integration ]]; then
    printf 'Bash syntax passed. Run the mixed-identity integration suite through Sail:\n'
    printf '  sail exec -u root laravel.test bash tests/Deployment/deployment-scripts.sh --integration\n'
    exit 0
fi
[[ "$(id -u)" == 0 && -n "${LARAVEL_SAIL:-}" ]] || { printf 'Integration tests require root inside the isolated Sail container.\n' >&2; exit 1; }
for command in setpriv git php python3; do command -v "$command" >/dev/null; done
# Sail intentionally has no sudo package. The isolated fixture uses root as its
# code owner, then genuinely drops to www-data for every runtime operation.
readonly DEPLOY_ID=0
readonly RUNTIME_ID="$(id -u www-data)"
readonly SHARED_GROUP="$(id -g www-data)"
TEST_ROOT="$(mktemp -d /tmp/dancepro-deployment-tests.XXXXXX)"
cleanup() {
    local status=$?
    if ((status != 0)); then
        for report in last-failure dry-run success; do
            [[ ! -f "$TEST_ROOT/$report" ]] || tail -n 20 "$TEST_ROOT/$report" >&2
        done
    fi
    rm -rf -- "$TEST_ROOT"
}
trap cleanup EXIT
chmod 0755 "$TEST_ROOT"
mkdir -p "$TEST_ROOT/bin" "$TEST_ROOT/source" "$TEST_ROOT/logs"
for area in app bootstrap config database resources routes public; do cp -a "$PROJECT_ROOT/$area" "$TEST_ROOT/source/"; done
# Production migrations deliberately use MySQL-only constraints. Exercise the
# real Laravel migration runner against a minimal isolated SQLite catalog; the
# regular Laravel suite covers the complete application schema on MySQL.
rm -f "$TEST_ROOT/source/database/migrations/"*.php
cat > "$TEST_ROOT/source/database/migrations/2026_01_01_000000_deployment_fixture.php" <<'PHP'
<?php
return new class extends Illuminate\Database\Migrations\Migration {
    public function up(): void {
        Illuminate\Support\Facades\Schema::create('studios', function ($table) {
            $table->id(); $table->string('name'); $table->string('status'); $table->softDeletes();
        });
        Illuminate\Support\Facades\Schema::create('concerts', function ($table) {
            $table->id(); $table->unsignedBigInteger('studio_id'); $table->string('status');
            $table->boolean('is_enabled'); $table->boolean('requires_approval');
            $table->timestamp('approved_at')->nullable(); $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable(); $table->softDeletes();
        });
    }
};
PHP
mkdir -p "$TEST_ROOT/source/scripts"
sed -e "s|/usr/bin/systemctl|$TEST_ROOT/bin/systemctl|g" \
    -e "s|export PATH=\"/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\"|export PATH=\"$TEST_ROOT/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\"|" \
    "$PROJECT_ROOT/scripts/dancepro-deploy.sh" > "$TEST_ROOT/source/scripts/dancepro-deploy.sh"
cp "$PROJECT_ROOT/"{artisan,composer.json,composer.lock,.gitignore} "$TEST_ROOT/source/"
# Every repository in this suite is temporary; no project Git state is changed.
git() { command git -c safe.directory='*' "$@"; }
git init --bare --quiet "$TEST_ROOT/origin.git"
git init --quiet --initial-branch=master "$TEST_ROOT/source"
git -C "$TEST_ROOT/source" config user.name 'Deployment regression tests'
git -C "$TEST_ROOT/source" config user.email 'deployment@example.invalid'
git -C "$TEST_ROOT/source" add .
git -C "$TEST_ROOT/source" commit --quiet -m baseline
git -C "$TEST_ROOT/source" remote add origin "$TEST_ROOT/origin.git"
git -C "$TEST_ROOT/source" push --quiet -u origin master
git clone --quiet "$TEST_ROOT/origin.git" "$TEST_ROOT/production"
cp -a "$PROJECT_ROOT/vendor" "$TEST_ROOT/production/"
rm -f "$TEST_ROOT/production/bootstrap/cache/"*.php
mkdir -p "$TEST_ROOT/production/storage/framework/"{views,cache/data,sessions} "$TEST_ROOT/production/storage/logs" "$TEST_ROOT/production/storage/app/public"
ln -s "$TEST_ROOT/production/storage/app/public" "$TEST_ROOT/production/public/storage"
touch "$TEST_ROOT/production/storage/test.sqlite"
cat > "$TEST_ROOT/production/.env" <<EOF
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
APP_URL=https://dancepro.dotdeveloper.com.au
APP_MAINTENANCE_DRIVER=file
DB_CONNECTION=sqlite
DB_DATABASE=$TEST_ROOT/production/storage/test.sqlite
CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
LOG_CHANNEL=single
EOF
printf 'private fixture\n' > "$TEST_ROOT/production/storage/app/private-fixture"
chown -R "$DEPLOY_ID:$SHARED_GROUP" "$TEST_ROOT/source" "$TEST_ROOT/origin.git" "$TEST_ROOT/production" "$TEST_ROOT/logs"
chmod 2751 "$TEST_ROOT/production"
find "$TEST_ROOT/production" -path '*/.git' -prune -o -type d -exec chmod g+s {} +
chown -R "$RUNTIME_ID:$SHARED_GROUP" "$TEST_ROOT/production/storage" "$TEST_ROOT/production/bootstrap/cache"
find "$TEST_ROOT/production/storage" "$TEST_ROOT/production/bootstrap/cache" -type d -exec chmod 2770 {} +
chmod 0660 "$TEST_ROOT/production/storage/test.sqlite"
chmod 0600 "$TEST_ROOT/production/storage/app/private-fixture" "$TEST_ROOT/production/.env"
chmod 0640 "$TEST_ROOT/production/.env"
touch "$TEST_ROOT/production/fixture-trace"
chown "$DEPLOY_ID:$SHARED_GROUP" "$TEST_ROOT/production/fixture-trace"
chmod 0660 "$TEST_ROOT/production/fixture-trace"
touch "$TEST_ROOT/lock"
chown "$DEPLOY_ID:$SHARED_GROUP" "$TEST_ROOT/lock"
printf '/fixture-*\n/storage/\n/app/deployment-existing.txt\n/app/deployment-link\n' >> "$TEST_ROOT/production/.git/info/exclude"

cat > "$TEST_ROOT/bin/php" <<EOF
#!/usr/bin/env bash
set -eu
root='$TEST_ROOT/production'
if [[ "\${1:-}" == -r && "\${3:-}" == "\$root" ]]; then
    printf '[client]\\n' > "\$4"
    printf fixture > "\$5"
    exit 0
fi
if [[ "\${1:-}" == "\$root/artisan" ]]; then
    printf 'artisan %s\\n' "\${*:2}" >> "\$root/fixture-trace"
    if [[ -f "\$root/fixture-failure" && "\$(cat "\$root/fixture-failure")" == "\${2:-}" ]]; then exit 42; fi
fi
exec /usr/bin/php "\$@"
EOF
cat > "$TEST_ROOT/bin/composer" <<EOF
#!/usr/bin/env bash
set -eu
cd '$TEST_ROOT/production'
printf 'composer\\n' >> fixture-trace
[[ ! -f fixture-failure || "\$(cat fixture-failure)" != composer ]] || exit 42
# Simulate restrictive archive/hook outputs even with a corrected caller mask.
printf '<?php return [];\\n' > vendor/composer/deployment-fixture.php
chmod 0600 vendor/composer/deployment-fixture.php
printf '<?php return [];\\n' > bootstrap/cache/packages.php
chmod 0600 bootstrap/cache/packages.php
EOF
cat > "$TEST_ROOT/bin/sudo" <<EOF
#!/usr/bin/env bash
set -eu
if [[ "\${2:-}" == -l ]]; then exit 0; fi
if [[ "\${2:-}" == '$TEST_ROOT/bin/systemctl' ]]; then
    shift
    exec "\$@"
fi
[[ "\${1:-}" == -n && "\${2:-}" == -u && "\${3:-}" == www-data && "\${4:-}" == -- ]]
shift 4
exec setpriv --reuid=$RUNTIME_ID --regid=$SHARED_GROUP --groups=$SHARED_GROUP -- "\$@"
EOF
cat > "$TEST_ROOT/bin/systemctl" <<EOF
#!/usr/bin/env bash
set -eu
printf 'systemctl %s\\n' "\$*" >> '$TEST_ROOT/production/fixture-trace'
if [[ "\${1:-}" == show ]]; then printf '0007\\n'; fi
if [[ "\${1:-}" == reload && -f '$TEST_ROOT/production/fixture-failure' && "\$(cat '$TEST_ROOT/production/fixture-failure')" == reload ]]; then exit 42; fi
exit 0
EOF
cat > "$TEST_ROOT/bin/mariadb-dump" <<EOF
#!/usr/bin/env bash
set -eu
printf 'backup\\n' >> '$TEST_ROOT/production/fixture-trace'
[[ ! -f '$TEST_ROOT/production/fixture-failure' || "\$(cat '$TEST_ROOT/production/fixture-failure')" != backup ]] || exit 42
for argument in "\$@"; do
    case "\$argument" in --result-file=*) printf 'fixture database dump\\n' > "\${argument#--result-file=}" ;; esac
done
EOF
cat > "$TEST_ROOT/bin/aws" <<EOF
#!/usr/bin/env bash
set -eu
if [[ "\$1 \$2" == 's3 cp' ]]; then stat -c %s "\$3" > '$TEST_ROOT/production/fixture-backup-size'
elif [[ "\$1 \$2" == 's3api head-object' ]]; then cat '$TEST_ROOT/production/fixture-backup-size'
else exit 1; fi
EOF
cat > "$TEST_ROOT/bin/curl" <<'EOF'
#!/usr/bin/env python3
import pathlib, sys
root = pathlib.Path(__file__).resolve().parents[1] / 'production'
args = sys.argv[1:]
assert '--resolve' in args and '--noproxy' in args
assert 'dancepro.dotdeveloper.com.au:80:127.0.0.1' in args
path = args[-1].split(':80', 1)[1]
failure = (root / 'fixture-failure').read_text().strip() if (root / 'fixture-failure').exists() else ''
closed = (root / 'storage/framework/down').exists()
status, mime = 200, 'text/html; charset=UTF-8'
body = {'/': 'Find your dance studio.', '/login': '<title>DancePro Login</title>', '/up': '{"status":"up"}', '/api/studios': '{"success":true,"data":[]}'}.get(path, '')
if path in ('/up', '/api/studios'): mime = 'application/json'
if closed and path != '/js/admin-concert-uploader.js' and (path != '/up' or 'deployment maintenance' in (root / 'storage/framework/maintenance.php').read_text()):
    status, mime, body = 503, 'text/html', 'DancePro deployment maintenance.'
elif path == '/js/admin-concert-uploader.js':
    mime, body = 'application/javascript', (root / 'public/js/admin-concert-uploader.js').read_text()
elif not closed and failure == 'http-login' and path == '/login':
    body = 'temporarily unavailable'  # Misleading HTTP 200 must fail.
elif not closed and failure == 'http-json' and path == '/api/studios':
    body = '{"success":false,"data":[]}'
pathlib.Path(args[args.index('--output') + 1]).write_text(body)
print(f'{status}\n{mime}', end='')
EOF
chmod 0755 "$TEST_ROOT/bin/"*

sed -e "s|/usr/bin/systemctl|$TEST_ROOT/bin/systemctl|g" \
    -e "s|export PATH=\"/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\"|export PATH=\"$TEST_ROOT/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\"|" \
    "$PROJECT_ROOT/scripts/dancepro-deploy.sh" > "$TEST_ROOT/runner"
run_launcher() {
    setpriv --reuid="$DEPLOY_ID" --regid="$SHARED_GROUP" --groups="$SHARED_GROUP" env \
        DANCEPRO_DEPLOY_USER=root DANCEPRO_RUNTIME_USER=www-data DANCEPRO_APP_GROUP=www-data \
        DANCEPRO_APP_DIR="$TEST_ROOT/production" DANCEPRO_EXPECTED_REMOTE_URL="$TEST_ROOT/origin.git" \
        DANCEPRO_PHP_BIN="$TEST_ROOT/bin/php" \
        DANCEPRO_DEPLOY_LOCK_FILE="$TEST_ROOT/lock" DANCEPRO_DEPLOY_LOG_DIR="$TEST_ROOT/logs" \
        /bin/bash "$TEST_ROOT/runner" "$@"
}
expect_failure() {
    local output status
    set +e
    output="$(run_launcher "$@" 2>&1)"; status=$?
    set -e
    [[ "$status" != 0 ]] || { printf 'Expected failure, got success.\n'; exit 1; }
    printf '%s' "$output" > "$TEST_ROOT/last-failure"
}
new_target() {
    printf '%s\n' "$1" > "$TEST_ROOT/source/app/deployment-fixture.txt"
    git -C "$TEST_ROOT/source" add .
    git -C "$TEST_ROOT/source" commit --quiet -m "$1"
    git -C "$TEST_ROOT/source" push --quiet
    chown -R "$DEPLOY_ID:$SHARED_GROUP" "$TEST_ROOT/source" "$TEST_ROOT/origin.git"
    TARGET="$(git -C "$TEST_ROOT/source" rev-parse HEAD)"
}
new_target corrected-deployment
printf 'Checking input and dry-run protection...\n'
expect_failure invalid
grep -q '40-character' "$TEST_ROOT/last-failure"
run_launcher --dry-run "$TARGET" > "$TEST_ROOT/dry-run"
[[ ! -e "$TEST_ROOT/production/storage/framework/down" ]]
[[ ! -e "$TEST_ROOT/production/fixture-backup-size" ]]

printf 'Checking real Unix identity separation and existing tracked code repair...\n'
existing="$TEST_ROOT/production/app/Features/Auth/Controllers/AuthController.php"
chown "$DEPLOY_ID:$DEPLOY_ID" "$existing"
chmod 0600 "$existing"
setpriv --reuid="$RUNTIME_ID" --regid="$SHARED_GROUP" --clear-groups test ! -r "$existing"

printf 'Checking full manual deployment with real Laravel and mixed owners...\n'
run_launcher "$TARGET" > "$TEST_ROOT/success"
[[ "$(git -C "$TEST_ROOT/production" rev-parse HEAD)" == "$TARGET" ]]
[[ ! -e "$TEST_ROOT/production/storage/framework/down" ]]
[[ "$(stat -c %a "$TEST_ROOT/production/vendor/composer/deployment-fixture.php")" == 640 ]]
[[ "$(stat -c %U "$TEST_ROOT/production/bootstrap/cache/config.php")" == www-data ]]
setpriv --reuid="$RUNTIME_ID" --regid="$SHARED_GROUP" --clear-groups test -r "$TEST_ROOT/production/bootstrap/cache/config.php"
[[ "$(stat -c %a "$existing")" == 640 ]]
[[ "$(stat -c %a "$TEST_ROOT/production/.env")" == 640 ]]
[[ "$(stat -c %a "$TEST_ROOT/production/storage/app/private-fixture")" == 600 ]]
setpriv --reuid="$RUNTIME_ID" --regid="$SHARED_GROUP" --clear-groups test ! -w "$TEST_ROOT/production/app"
grep -q 'Deployment successful:' "$TEST_ROOT/success"
[[ -s "$TEST_ROOT/logs/current-deployment.json" ]]
backup_line="$(grep -n '^backup$' "$TEST_ROOT/production/fixture-trace" | head -1 | cut -d: -f1)"
down_line="$(grep -n '^artisan down' "$TEST_ROOT/production/fixture-trace" | head -1 | cut -d: -f1)"
composer_line="$(grep -n '^composer$' "$TEST_ROOT/production/fixture-trace" | head -1 | cut -d: -f1)"
[[ "$backup_line" -lt "$down_line" && "$down_line" -lt "$composer_line" ]]
[[ "$(stat -c %a "$TEST_ROOT/production/app/deployment-fixture.txt")" == 640 ]]
[[ "$(stat -c %G "$TEST_ROOT/production/app/deployment-fixture.txt")" == www-data ]]
find "$TEST_ROOT/production/storage/framework/views" -name '*.php' -type f | while IFS= read -r path; do
    [[ "$(stat -c %U "$path")" == www-data ]]
    setpriv --reuid="$RUNTIME_ID" --regid="$SHARED_GROUP" --clear-groups test -r "$path"
done
[[ -n "$(find "$TEST_ROOT/production/storage/framework/views" -name '*.php' -print -quit)" ]]

printf 'Checking backup failure preserves checkout and serving state...\n'
new_target backup-failure
printf backup > "$TEST_ROOT/production/fixture-failure"
before="$(git -C "$TEST_ROOT/production" rev-parse HEAD)"
expect_failure "$TARGET"
[[ "$(git -C "$TEST_ROOT/production" rev-parse HEAD)" == "$before" ]]
[[ ! -e "$TEST_ROOT/production/storage/framework/down" ]]
rm "$TEST_ROOT/production/fixture-failure"

for failure in down composer migrate view:cache reload http-login http-json; do
    printf 'Checking failure closure: %s...\n' "$failure"
    printf '%s' "$failure" > "$TEST_ROOT/production/fixture-failure"
    expect_failure "$TARGET"
    grep -q 'maintenance HTTP 503 verified\|Maintenance HTTP 503 verified' "$TEST_ROOT/last-failure"
    [[ -f "$TEST_ROOT/production/storage/framework/down" && -f "$TEST_ROOT/production/storage/framework/maintenance.php" ]]
    # Deliberate recovery of the isolated fixture, not a production procedure.
    rm "$TEST_ROOT/production/fixture-failure" "$TEST_ROOT/production/storage/framework/down" "$TEST_ROOT/production/storage/framework/maintenance.php"
done

printf 'Checking symlink refusal and private-target preservation...\n'
ln -s ../storage/app/private-fixture "$TEST_ROOT/source/app/deployment-link"
new_target unsafe-link
expect_failure "$TARGET"
[[ "$(stat -c %a "$TEST_ROOT/production/storage/app/private-fixture")" == 600 ]]
rm "$TEST_ROOT/source/app/deployment-link"
new_target remove-link
# Recover only the intentionally damaged temporary fixture before later cases.
git -C "$TEST_ROOT/production" fetch --quiet origin
git -C "$TEST_ROOT/production" merge --ff-only "$TARGET" >/dev/null
rm "$TEST_ROOT/production/storage/framework/down" "$TEST_ROOT/production/storage/framework/maintenance.php"

printf 'Checking nonroot deployment owner can repair group access without touching private paths...\n'
nonroot_dir="$TEST_ROOT/nonroot"
mkdir -p "$nonroot_dir/"{app,public,bootstrap,vendor,storage/private}
printf '<?php\n' > "$nonroot_dir/app/replaced.php"
printf 'private fixture\n' > "$nonroot_dir/storage/private/key"
printf 'private fixture\n' > "$nonroot_dir/.env"
git init --quiet "$nonroot_dir"
git -C "$nonroot_dir" add app
git -C "$nonroot_dir" -c user.name=test -c user.email=test@example.invalid commit --quiet -m fixture
chown -R "$(id -u sail):$(id -g sail)" "$nonroot_dir"
chmod 0600 "$nonroot_dir/app/replaced.php" "$nonroot_dir/storage/private/key" "$nonroot_dir/.env" "$nonroot_dir/.git/config"
chmod 2751 "$nonroot_dir"
chgrp "$SHARED_GROUP" "$nonroot_dir"
python3 - "$PROJECT_ROOT/scripts/dancepro-deploy.sh" "$TEST_ROOT/permission-function" <<'PYFIXTURE'
from pathlib import Path
import sys
text = Path(sys.argv[1]).read_text()
Path(sys.argv[2]).write_text(text[text.index('permissions() {'):text.index('\nhttp_once() {')])
PYFIXTURE
mkdir "$nonroot_dir/run"
chown "$(id -u sail):$(id -g sail)" "$nonroot_dir/run"
run_nonroot_permissions() {
    setpriv --reuid="$(id -u sail)" --regid="$(id -g sail)" --groups="$SHARED_GROUP" env \
        APP_DIR="$nonroot_dir" APP_GROUP=www-data PHP_BIN=/usr/bin/php RUN_DIR="$nonroot_dir/run" \
        /bin/bash -c 'cd "$APP_DIR"; source "$1"; permissions code' -- "$TEST_ROOT/permission-function"
}
run_nonroot_permissions
[[ "$(stat -c %a "$nonroot_dir/app/replaced.php")" == 640 && "$(stat -c %G "$nonroot_dir/app/replaced.php")" == www-data ]]
setpriv --reuid="$RUNTIME_ID" --regid="$SHARED_GROUP" --clear-groups test -r "$nonroot_dir/app/replaced.php"
for path in storage/private/key .env .git/config; do [[ "$(stat -c %a "$nonroot_dir/$path")" == 600 ]]; done

printf 'Checking hard-linked bootstrap refusal before code normalization...\n'
ln "$nonroot_dir/storage/private/key" "$nonroot_dir/bootstrap/app.php"
set +e
git -C "$nonroot_dir" add bootstrap/app.php
output="$(run_nonroot_permissions 2>&1)"
status=$?
set -e
[[ "$status" != 0 && "$output" == *'Unsafe file'* ]]
[[ "$(stat -c %a "$nonroot_dir/storage/private/key")" == 600 ]]

printf 'Checking checkout cannot replace the running deployment snapshot...\n'
printf '#!/usr/bin/env bash\nexit 99\n' > "$TEST_ROOT/source/scripts/dancepro-deploy.sh"
new_target replaces-running-script
run_launcher "$TARGET" > "$TEST_ROOT/snapshot-success"
grep -q 'Deployment successful:' "$TEST_ROOT/snapshot-success"
[[ "$(cat "$TEST_ROOT/production/scripts/dancepro-deploy.sh")" == *'exit 99'* ]]

printf 'Checking dirty-tree and lock refusal...\n'
printf dirty >> "$TEST_ROOT/production/artisan"
expect_failure "$TARGET"
grep -q 'working tree is not clean' "$TEST_ROOT/last-failure"
git -C "$TEST_ROOT/production" restore artisan
exec 8>"$TEST_ROOT/lock"
flock -n 8
expect_failure "$TARGET"
grep -q 'Another DancePro deployment' "$TEST_ROOT/last-failure"
flock -u 8
exec 8>&-
printf 'Deployment integration tests passed (real Laravel, separate Unix identities, no network/AWS operations).\n'
