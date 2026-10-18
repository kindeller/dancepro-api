#!/usr/bin/env bash

set -Eeuo pipefail

readonly PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
readonly LAUNCHER="$PROJECT_ROOT/scripts/dancepro-deploy-launcher.sh"
readonly DEPLOY_SCRIPT="$PROJECT_ROOT/scripts/dancepro-deploy.sh"

TEST_ROOT="$(mktemp -d /tmp/dancepro-deployment-tests.XXXXXX)"
trap 'rm -rf -- "$TEST_ROOT"' EXIT

assert_contains() {
    local haystack="$1"
    local needle="$2"
    [[ "$haystack" == *"$needle"* ]] || {
        printf 'Expected output to contain: %s\nActual output:\n%s\n' "$needle" "$haystack" >&2
        exit 1
    }
}

run_launcher() {
    DANCEPRO_APP_DIR="$TEST_ROOT/production" \
    DANCEPRO_EXPECTED_REMOTE_URL="$TEST_ROOT/origin.git" \
    DANCEPRO_DEPLOY_LOCK_FILE="$TEST_ROOT/deploy.lock" \
    DANCEPRO_DEPLOY_LOG_DIR="$TEST_ROOT/logs" \
    DEPLOY_TEST_TRACE="$TEST_ROOT/deploy-trace" \
        "$LAUNCHER" "$@"
}

printf 'Checking Bash syntax...\n'
bash -n "$LAUNCHER"
bash -n "$DEPLOY_SCRIPT"

if grep -nE '(^|[[:space:]])read[[:space:]]+-?[a-zA-Z]*[[:space:]]' "$DEPLOY_SCRIPT"; then
    printf 'The application deployment script must not prompt for input.\n' >&2
    exit 1
fi

backup_line="$(grep -n 'log "Create and verify database backup"' "$DEPLOY_SCRIPT" | cut -d: -f1)"
migration_line="$(grep -n 'php artisan migrate --force' "$DEPLOY_SCRIPT" | cut -d: -f1)"
[[ "$backup_line" -lt "$migration_line" ]] || {
    printf 'The verified backup must occur before migrations.\n' >&2
    exit 1
}

printf 'Creating isolated Git repositories...\n'
git init --bare --quiet "$TEST_ROOT/origin.git"
git init --quiet --initial-branch=master "$TEST_ROOT/source"
git -C "$TEST_ROOT/source" config user.name 'Deployment Test'
git -C "$TEST_ROOT/source" config user.email 'deployment-test@example.invalid'
mkdir -p "$TEST_ROOT/source/scripts"
printf 'initial\n' > "$TEST_ROOT/source/application.txt"
printf '#!/usr/bin/env bash\nprintf "%%s\\n" "$*" > "$DEPLOY_TEST_TRACE"\nexit 0\n' \
    > "$TEST_ROOT/source/scripts/dancepro-deploy.sh"
chmod +x "$TEST_ROOT/source/scripts/dancepro-deploy.sh"
git -C "$TEST_ROOT/source" add .
git -C "$TEST_ROOT/source" commit --quiet -m initial
git -C "$TEST_ROOT/source" remote add origin "$TEST_ROOT/origin.git"
git -C "$TEST_ROOT/source" push --quiet -u origin master
git clone --quiet "$TEST_ROOT/origin.git" "$TEST_ROOT/production"

initial_commit="$(git -C "$TEST_ROOT/production" rev-parse HEAD)"
printf 'second\n' > "$TEST_ROOT/source/application.txt"
git -C "$TEST_ROOT/source" add application.txt
git -C "$TEST_ROOT/source" commit --quiet -m second
git -C "$TEST_ROOT/source" push --quiet
target_commit="$(git -C "$TEST_ROOT/source" rev-parse HEAD)"

printf 'Checking target validation...\n'
set +e
invalid_output="$(run_launcher 'not-a-commit' 2>&1)"
invalid_status=$?
set -e
[[ "$invalid_status" -ne 0 ]]
assert_contains "$invalid_output" '40-character Git commit SHA is required'

printf 'Checking non-destructive dry run...\n'
run_launcher --dry-run "$target_commit" >/dev/null
[[ "$(git -C "$TEST_ROOT/production" rev-parse HEAD)" == "$initial_commit" ]]
[[ ! -e "$TEST_ROOT/deploy-trace" ]]

printf 'Checking commit-pinned deployment...\n'
run_launcher "$target_commit" >/dev/null
[[ "$(git -C "$TEST_ROOT/production" rev-parse HEAD)" == "$target_commit" ]]
trace="$(<"$TEST_ROOT/deploy-trace")"
assert_contains "$trace" "--from-commit $initial_commit"
assert_contains "$trace" "--target-commit $target_commit"
assert_contains "$trace" '--rollback-tag pre-deploy-'

printf 'Checking application exit-code propagation...\n'
printf '#!/usr/bin/env bash\nexit 42\n' > "$TEST_ROOT/source/scripts/dancepro-deploy.sh"
git -C "$TEST_ROOT/source" add scripts/dancepro-deploy.sh
git -C "$TEST_ROOT/source" commit --quiet -m failing-deploy
git -C "$TEST_ROOT/source" push --quiet
failing_commit="$(git -C "$TEST_ROOT/source" rev-parse HEAD)"
set +e
run_launcher "$failing_commit" >/dev/null 2>&1
launcher_status=$?
set -e
[[ "$launcher_status" -eq 42 ]] || {
    printf 'Expected launcher exit code 42, got %d.\n' "$launcher_status" >&2
    exit 1
}

printf 'Checking dirty-tree refusal...\n'
printf 'dirty\n' >> "$TEST_ROOT/production/application.txt"
set +e
dirty_output="$(run_launcher "$failing_commit" 2>&1)"
dirty_status=$?
set -e
[[ "$dirty_status" -ne 0 ]]
assert_contains "$dirty_output" 'working tree is not clean'
git -C "$TEST_ROOT/production" restore application.txt

printf 'Checking deployment lock...\n'
exec 8>"$TEST_ROOT/deploy.lock"
flock -n 8
set +e
lock_output="$(run_launcher "$failing_commit" 2>&1)"
lock_status=$?
set -e
flock -u 8
exec 8>&-
[[ "$lock_status" -ne 0 ]]
assert_contains "$lock_output" 'Another DancePro deployment is already running'

printf 'Checking backup failure blocks migrations...\n'
backup_app="$TEST_ROOT/backup-app"
fake_bin="$TEST_ROOT/fake-bin"
mkdir -p "$backup_app/scripts" "$backup_app/storage/logs/deployments" \
    "$backup_app/bootstrap/cache" "$fake_bin"
git init --quiet --initial-branch=master "$backup_app"
git -C "$backup_app" config user.name 'Deployment Test'
git -C "$backup_app" config user.email 'deployment-test@example.invalid'
printf '.env\nstorage/logs/\n' > "$backup_app/.gitignore"
printf '#!/usr/bin/env php\n' > "$backup_app/artisan"
chmod +x "$backup_app/artisan"
git -C "$backup_app" add .
git -C "$backup_app" commit --quiet -m fixture
git -C "$backup_app" remote add origin "$TEST_ROOT/origin.git"
backup_commit="$(git -C "$backup_app" rev-parse HEAD)"
backup_tag="pre-deploy-20261018-120000-${backup_commit:0:12}"
git -C "$backup_app" tag "$backup_tag" "$backup_commit"
touch "$backup_app/.env"

sed \
    -e "s|readonly APP_DIR=\"/var/www/dancepro-api\"|readonly APP_DIR=\"$backup_app\"|" \
    -e "s|readonly EXPECTED_REMOTE_URL=\"https://github.com/kindeller/dancepro-api.git\"|readonly EXPECTED_REMOTE_URL=\"$TEST_ROOT/origin.git\"|" \
    -e "s|export PATH=\"/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\"|export PATH=\"$fake_bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\"|" \
    "$DEPLOY_SCRIPT" > "$TEST_ROOT/rendered-deploy.sh"
chmod +x "$TEST_ROOT/rendered-deploy.sh"

cat > "$fake_bin/php" <<'EOF'
#!/usr/bin/env bash
set -eu
if [[ "${1:-}" == "artisan" && "${2:-}" == "env" ]]; then
    echo 'INFO  The application environment is [production].'
elif [[ "${1:-}" == "artisan" ]]; then
    printf 'php %s\n' "$*" >> "$DEPLOY_TEST_TRACE"
elif [[ "${1:-}" == "-r" ]]; then
    printf '[client]\npassword="not-logged"\n' > "$4"
    printf 'dancepro_test' > "$5"
else
    exit 1
fi
EOF

cat > "$fake_bin/mariadb-dump" <<'EOF'
#!/usr/bin/env bash
printf 'dump failed\n' >&2
exit 7
EOF

for command_name in composer curl aws; do
    cat > "$fake_bin/$command_name" <<'EOF'
#!/usr/bin/env bash
printf '%s %s\n' "$(basename "$0")" "$*" >> "$DEPLOY_TEST_TRACE"
exit 0
EOF
done
chmod +x "$fake_bin"/*

: > "$TEST_ROOT/backup-trace"
set +e
backup_output="$(
    cd "$backup_app"
    DEPLOY_TEST_TRACE="$TEST_ROOT/backup-trace" \
        "$TEST_ROOT/rendered-deploy.sh" \
        --from-commit "$backup_commit" \
        --target-commit "$backup_commit" \
        --rollback-tag "$backup_tag" 2>&1
)"
backup_status=$?
set -e
[[ "$backup_status" -eq 7 ]] || {
    printf 'Expected dump failure exit code 7, got %d.\n%s\n' "$backup_status" "$backup_output" >&2
    exit 1
}
assert_contains "$backup_output" 'dump failed'
assert_contains "$backup_output" 'running application was not placed in maintenance mode'
if grep -Eq 'composer|migrate --force|artisan down' "$TEST_ROOT/backup-trace"; then
    printf 'Deployment continued after a failed backup:\n' >&2
    cat "$TEST_ROOT/backup-trace" >&2
    exit 1
fi

printf 'Deployment script tests passed.\n'
