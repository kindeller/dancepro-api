#!/usr/bin/env bash

set -Eeuo pipefail

readonly PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
readonly LAUNCHER="$PROJECT_ROOT/scripts/dancepro-deploy-launcher.sh"
readonly DEPLOY_SCRIPT="$PROJECT_ROOT/scripts/dancepro-deploy.sh"

TEST_ROOT="$(mktemp -d /tmp/dancepro-deployment-tests.XXXXXX)"
trap 'rm -rf -- "$TEST_ROOT"' EXIT
fake_bin="$TEST_ROOT/fake-bin"
mkdir -p "$fake_bin"
rendered_launcher="$TEST_ROOT/dancepro-deploy-launcher.sh"

sed \
    -e "s|export PATH=\"/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\"|export PATH=\"$fake_bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\"|" \
    "$LAUNCHER" > "$rendered_launcher"
chmod +x "$rendered_launcher"

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
    DEPLOY_TEST_BACKUP_SIZE="$TEST_ROOT/backup-size" \
    DEPLOY_TEST_BACKUP_FAIL="${DEPLOY_TEST_BACKUP_FAIL:-false}" \
        "$rendered_launcher" "$@"
}

printf 'Checking Bash syntax...\n'
bash -n "$LAUNCHER"
bash -n "$DEPLOY_SCRIPT"

if grep -nE '(^|[[:space:]])read[[:space:]]+-?[a-zA-Z]*[[:space:]]' "$DEPLOY_SCRIPT"; then
    printf 'The application deployment script must not prompt for input.\n' >&2
    exit 1
fi

backup_line="$(grep -n 'log "Create and verify pre-deployment database backup"' "$LAUNCHER" | cut -d: -f1)"
maintenance_line="$(grep -n 'log "Enable maintenance mode before checkout"' "$LAUNCHER" | cut -d: -f1)"
checkout_line="$(grep -n 'git merge --ff-only' "$LAUNCHER" | cut -d: -f1)"
[[ "$backup_line" -lt "$maintenance_line" && "$maintenance_line" -lt "$checkout_line" ]] || {
    printf 'The verified backup and maintenance mode must precede the production checkout.\n' >&2
    exit 1
}

if grep -qE 'mariadb-dump|mysqldump|aws s3 cp' "$DEPLOY_SCRIPT"; then
    printf 'Database backup operations must remain in the stable launcher.\n' >&2
    exit 1
fi

cat > "$fake_bin/php" <<'EOF'
#!/usr/bin/env bash
set -eu
if [[ "${1:-}" == "-r" ]]; then
    printf '[client]\npassword="not-logged"\n' > "$4"
    printf 'dancepro_test' > "$5"
elif [[ "${1:-}" == "artisan" ]]; then
    printf 'php %s\n' "$*" >> "$DEPLOY_TEST_TRACE"
else
    exit 1
fi
EOF

cat > "$fake_bin/mariadb-dump" <<'EOF'
#!/usr/bin/env bash
set -eu
printf 'backup-head %s\n' "$(git -C "$DANCEPRO_APP_DIR" rev-parse HEAD)" >> "$DEPLOY_TEST_TRACE"
if [[ "$DEPLOY_TEST_BACKUP_FAIL" == "true" ]]; then
    printf 'dump failed\n' >&2
    exit 7
fi
for argument in "$@"; do
    case "$argument" in
        --result-file=*) output_file="${argument#--result-file=}" ;;
    esac
done
printf '%s\n' 'database backup' > "$output_file"
EOF

cat > "$fake_bin/aws" <<'EOF'
#!/usr/bin/env bash
set -eu
if [[ "${1:-}" == "s3" && "${2:-}" == "cp" ]]; then
    stat -c %s "$3" > "$DEPLOY_TEST_BACKUP_SIZE"
    printf 's3-upload %s\n' "$4" >> "$DEPLOY_TEST_TRACE"
elif [[ "${1:-}" == "s3api" && "${2:-}" == "head-object" ]]; then
    cat "$DEPLOY_TEST_BACKUP_SIZE"
else
    exit 1
fi
EOF
chmod +x "$fake_bin"/*

printf 'Creating isolated Git repositories...\n'
git init --bare --quiet "$TEST_ROOT/origin.git"
git init --quiet --initial-branch=master "$TEST_ROOT/source"
git -C "$TEST_ROOT/source" config user.name 'Deployment Test'
git -C "$TEST_ROOT/source" config user.email 'deployment-test@example.invalid'
mkdir -p "$TEST_ROOT/source/scripts"
mkdir -p "$TEST_ROOT/source/vendor" "$TEST_ROOT/source/storage" \
    "$TEST_ROOT/source/bootstrap/cache"
printf '.env\n' > "$TEST_ROOT/source/.gitignore"
touch "$TEST_ROOT/source/storage/.gitkeep" "$TEST_ROOT/source/bootstrap/cache/.gitkeep"
printf '%s\n' '<?php' > "$TEST_ROOT/source/vendor/autoload.php"
printf '#!/usr/bin/env php\n' > "$TEST_ROOT/source/artisan"
chmod +x "$TEST_ROOT/source/artisan"
printf 'initial\n' > "$TEST_ROOT/source/application.txt"
printf '#!/usr/bin/env bash\nprintf "deploy-args %%s\\n" "$*" >> "$DEPLOY_TEST_TRACE"\nprintf "deploy-head %%s\\n" "$(git rev-parse HEAD)" >> "$DEPLOY_TEST_TRACE"\nexit 0\n' \
    > "$TEST_ROOT/source/scripts/dancepro-deploy.sh"
chmod +x "$TEST_ROOT/source/scripts/dancepro-deploy.sh"
git -C "$TEST_ROOT/source" add .
git -C "$TEST_ROOT/source" commit --quiet -m initial
git -C "$TEST_ROOT/source" remote add origin "$TEST_ROOT/origin.git"
git -C "$TEST_ROOT/source" push --quiet -u origin master
git clone --quiet "$TEST_ROOT/origin.git" "$TEST_ROOT/production"
touch "$TEST_ROOT/production/.env"

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
assert_contains "$trace" "backup-head $initial_commit"
assert_contains "$trace" "deploy-args --from-commit $initial_commit"
assert_contains "$trace" "--target-commit $target_commit"
assert_contains "$trace" '--rollback-tag pre-deploy-'
assert_contains "$trace" '--verified-backup-key deployments/dancepro-db-'
assert_contains "$trace" "deploy-head $target_commit"

backup_trace_line="$(grep -n '^backup-head ' "$TEST_ROOT/deploy-trace" | tail -1 | cut -d: -f1)"
checkout_trace_line="$(grep -n '^deploy-head ' "$TEST_ROOT/deploy-trace" | tail -1 | cut -d: -f1)"
[[ "$backup_trace_line" -lt "$checkout_trace_line" ]]

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

printf 'Checking backup failure preserves current code and service state...\n'
printf 'backup failure target\n' > "$TEST_ROOT/source/application.txt"
git -C "$TEST_ROOT/source" add application.txt
git -C "$TEST_ROOT/source" commit --quiet -m backup-failure-target
git -C "$TEST_ROOT/source" push --quiet
backup_failure_target="$(git -C "$TEST_ROOT/source" rev-parse HEAD)"
: > "$TEST_ROOT/deploy-trace"
set +e
backup_output="$(DEPLOY_TEST_BACKUP_FAIL=true run_launcher "$backup_failure_target" 2>&1)"
backup_status=$?
set -e
[[ "$backup_status" -eq 7 ]] || {
    printf 'Expected dump failure exit code 7, got %d.\n%s\n' "$backup_status" "$backup_output" >&2
    exit 1
}
assert_contains "$backup_output" 'dump failed'
assert_contains "$backup_output" 'production checkout and running application were not changed'
[[ "$(git -C "$TEST_ROOT/production" rev-parse HEAD)" == "$failing_commit" ]]
if grep -Eq 'artisan down|deploy-head|deploy-args' "$TEST_ROOT/deploy-trace"; then
    printf 'Deployment continued after a failed pre-checkout backup:\n' >&2
    cat "$TEST_ROOT/deploy-trace" >&2
    exit 1
fi

printf 'Deployment script tests passed.\n'
