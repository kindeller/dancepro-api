# Production Deployment

DancePro API production deployments use GitHub Actions, AWS Systems Manager
(SSM), and a two-stage EC2 deployment process. The production `.env`, runtime
keys, AWS instance-role credentials, logs, and database backups remain outside
Git.

Automatic deployment on merge is deliberately **not enabled yet**. The checked-in
workflow supports manual dispatch only until the readiness checklist in this
document has been completed and approved.

## Current State

The following infrastructure was reported as established before this repository
change, but still requires production validation:

- EC2 instance `i-09a5f7c942155e96a` in `ap-southeast-2`.
- Application checkout `/var/www/dancepro-api` on branch `master`.
- An online SSM managed node and the `DancePro-Deploy-Production` Command
  document.
- GitHub's OIDC provider and IAM role `DancePro-GitHub-Deploy-Role`.
- S3 database-backup bucket `dance-pro-db-backup` and existing retention
  arrangements.

This repository now contains the stable launcher source, the application deploy
script, the proposed SSM document, a manual-only production workflow, and safe
isolated shell tests. No AWS resource, EC2 configuration, production file,
database, or GitHub repository setting is changed merely by merging these files.

## Architecture

```text
approved master commit (GitHub SHA)
              |
              v
GitHub Actions tests and OIDC role assumption
              |
              v
SSM DancePro-Deploy-Production(TargetCommit)
              |
              v
/usr/local/bin/dancepro-deploy <TargetCommit>
  - independent, root-owned launcher
  - lock, logs, repository and ancestry checks
  - verified database backup from the current release
  - maintenance mode using the current release
  - fast-forward to the exact approved SHA
              |
              v
scripts/dancepro-deploy.sh from that SHA
  - verifies the launcher-provided backup reference
  - dependencies, migrations and caches
  - services, health check and deployment record
```

The launcher is copied out of the repository so an update cannot replace the
process currently changing the checkout. The pre-checkout backup and maintenance
gate therefore lives in the installed launcher. Release-specific behaviour
remains in the version-controlled script and changes through the normal pull
request process.

The launcher accepts only a lowercase full SHA. It requires the requested commit
to be an ancestor of `origin/master` and the current production commit to be an
ancestor of the target. It never merges divergent history, rebases, resets, or
overwrites local changes. GitHub waits for the terminal SSM plugin status;
acceptance of `SendCommand` alone is not success. GitHub and EC2 both prevent
overlapping deployments.

## Database Backup Gate

Every live invocation performs and verifies a backup from the current production
release before maintenance mode, rollback-tag creation, or any checkout change:

1. Laravel's installed Dotenv library reads the server-only `.env` from the
   unchanged current release without booting Laravel. It requires
   `APP_ENV=production`, a MySQL/MariaDB connection, and discrete `DB_*` values
   rather than `DB_URL`.
2. PHP writes a mode-`0600` temporary client file. The password is never put in
   a command argument or printed. The successful dump is the connectivity test.
3. `mariadb-dump` is preferred, with `mysqldump` as fallback. The dump uses a
   single transaction and includes routines, triggers, and events.
4. The script requires non-empty SQL, compresses it, and runs `gzip -t`.
5. It uploads with the EC2 instance role to
   `s3://dance-pro-db-backup/deployments/dancepro-db-<UTC>-<commit>.sql.gz`.
6. `head-object` must report the same non-zero byte count before deployment.
7. Restricted temporary files are removed by the exit trap.
8. Only after verification succeeds does the launcher enable maintenance mode,
   create the rollback tag, and fast-forward to the approved commit. It passes
   the validated object key to the version-controlled deployment script.

A size match confirms transfer completeness, not restorability. Periodically
restore into an isolated nonproduction database and record the rehearsal. The
existing S3 retention, encryption, versioning, and lifecycle arrangements were
not changed here and require separate review. Never automatically restore after
a failed migration: migrations can be partially applied and a restore can lose
production writes made after the snapshot.

Each live invocation, including a same-commit SSM test, creates a new object and
therefore incurs the associated S3 request and storage charges.

## One-Time EC2 Migration

These are manual production changes that Codex has not executed. Perform them
during an approved maintenance window.

1. Confirm the checkout is clean, on `master`, and uses the authorised remote:

   ```bash
   cd /var/www/dancepro-api
   sudo -u ec2-user git status --short --branch
   sudo -u ec2-user git remote get-url origin
   ```

2. Verify Git authentication is noninteractive:

   ```bash
   sudo -u ec2-user env GIT_TERMINAL_PROMPT=0 git ls-remote origin refs/heads/master
   ```

   Keep any deploy key or Git credential outside the repository and read-only.
   GitHub Actions uses OIDC and needs no permanent AWS access key or SSH key.

3. Before the first deployment of this architecture, install the launcher from
   the exact approved target commit without changing the production checkout.
   This ordering is mandatory because the launcher now creates the backup and
   passes its verified key to the application script; the previous launcher is
   not compatible with that handoff.

   ```bash
   cd /var/www/dancepro-api
   TARGET_COMMIT=<approved-full-master-sha>
   sudo -u ec2-user git fetch --no-tags origin \
       +refs/heads/master:refs/remotes/origin/master
   sudo -u ec2-user git merge-base --is-ancestor "$TARGET_COMMIT" origin/master
   sudo -u ec2-user git show \
       "$TARGET_COMMIT:scripts/dancepro-deploy-launcher.sh" \
       | sudo tee /usr/local/bin/dancepro-deploy.new >/dev/null
   sudo chown root:root /usr/local/bin/dancepro-deploy.new
   sudo chmod 0755 /usr/local/bin/dancepro-deploy.new
   sudo mv /usr/local/bin/dancepro-deploy.new /usr/local/bin/dancepro-deploy
   sudo test -f /usr/local/bin/dancepro-deploy
   sudo test ! -L /usr/local/bin/dancepro-deploy
   ```

4. Create independent lock and log locations:

   ```bash
   sudo install -d -o ec2-user -g ec2-user -m 0750 /var/log/dancepro-deploy
   sudo touch /var/lock/dancepro-deploy.lock
   sudo chown ec2-user:ec2-user /var/lock/dancepro-deploy.lock
   sudo chmod 0640 /var/lock/dancepro-deploy.lock
   ```

5. Confirm `storage/` and `bootstrap/cache/` are writable by `ec2-user` and the
   web-service group. Never use mode `0777`.

6. Identify the installed PHP-FPM service. If it exists, grant only its exact
   reload command in `/etc/sudoers.d/dancepro-deploy`, checked with `visudo -cf`:

   ```text
   ec2-user ALL=(root) NOPASSWD: /usr/bin/systemctl reload php-fpm.service
   ```

   Confirm the binary path and service first; do not grant unrestricted `sudo`
   or guessed service names. If PHP-FPM is not used, no reload grant is needed.

7. Verify `git`, PHP 8.3+, Composer, `curl`, `gzip`, AWS CLI v2,
   `mariadb-dump` or `mysqldump`, `flock`, and `systemctl` are available.

8. Verify SSM Agent is at least `3.3.2746.0`. The proposed document uses SSM
   `ENV_VAR` interpolation so the SHA is data rather than shell source.

## Required AWS and GitHub Changes

These remain manual and must be reviewed before production testing.

### SSM document

Replace the current parameterless content with
`deployment/aws/ssm/DancePro-Deploy-Production.yaml`. This explicit document
change adds required `TargetCommit`, restricts it to `^[0-9a-f]{40}$`, invokes
the stable launcher as `ec2-user`, and preserves the 1,800-second timeout.

Review the new document version, make it default only for the controlled test,
and retain the previous version for recovery. Never add a free-form command
parameter.

### GitHub OIDC role

Verify the trust policy requires audience `sts.amazonaws.com` and subject
`repo:kindeller/dancepro-api:ref:refs/heads/master`. If the repository owner is
not `kindeller`, use the exact owner. The workflow does not use a GitHub
Environment, so do not use an environment-form subject without reviewing both
designs.

The role should allow `ssm:SendCommand` only for the production instance and
the `DancePro-Deploy-Production` document, plus minimum read actions for command
status and output. Some SSM result APIs may require `Resource: "*"`; keep their
action list narrow rather than broadening `SendCommand`. Validate the effective
policy with IAM Access Analyzer.

### EC2 instance role

In addition to managed-node permissions, it needs:

- `s3:PutObject` on `arn:aws:s3:::dance-pro-db-backup/deployments/*`;
- `s3:GetObject` on that prefix so `HeadObject` can verify uploads;
- required KMS actions if the bucket uses a customer-managed key.

It needs no S3 delete, lifecycle, bucket-policy, or protection-changing access.
Confirm the bucket policy permits the role and enforces established encryption.

### GitHub repository

Create repository variable `AWS_DEPLOY_ROLE_ARN` with the complete ARN of
`DancePro-GitHub-Deploy-Role`. This identifier is not a static credential.

The workflow currently has only `workflow_dispatch`. After all testing below,
enable merge deployment in a separate reviewed pull request by adding:

```yaml
push:
  branches:
    - master
```

Keep the concurrency group and `cancel-in-progress: false` so later merges queue
behind an active deployment.

## Failure Recovery

Launcher logs are under `/var/log/dancepro-deploy/`; application logs and the
successful `current-deployment.json` record are under
`storage/logs/deployments/`.

- Fetch, validation, dirty-tree, ancestry, lock, backup, or upload failure stops
  before maintenance mode and leaves the production checkout unchanged.
- After the backup is verified, the launcher enables maintenance mode before
  creating the rollback tag or changing the checkout.
- Dependency, migration, cache, reload, or integrity failure leaves maintenance
  mode enabled for diagnosis.
- Health-check failure returns the site to maintenance mode.
- Every failure exits non-zero through SSM and GitHub.

The launcher does not automatically reset Git and never automatically restores
the database. New code may already have run migrations, so either action needs
operator review. For an approved code-only rollback:

```bash
cd /var/www/dancepro-api
php artisan down --render=errors.503
git show --no-patch <pre-deploy-tag>
git reset --hard <pre-deploy-tag>
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
php artisan optimize:clear
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache
sudo -n systemctl reload <verified-php-fpm-service>
curl --fail --max-time 10 http://127.0.0.1/up
php artisan up
```

Review migrations, customer writes, the exact tag, and backup first. A Git reset
is destructive to the checkout and is not an automatic deployment action.

## Manual Emergency Deployment

Use the launcher so all normal safeguards remain active:

```bash
TARGET_COMMIT=<approved-full-master-sha>
/usr/local/bin/dancepro-deploy --dry-run "$TARGET_COMMIT"
/usr/local/bin/dancepro-deploy "$TARGET_COMMIT"
```

Do not invoke `scripts/dancepro-deploy.sh` directly. Updating application logic
uses a normal pull request. Updating the launcher is exceptional: review its
tests, schedule a window, install atomically, and record its checksum.

## Safe Activation Plan

1. **Static validation:** run `bash -n` on both scripts,
   `bash tests/Deployment/deployment-scripts.sh`, `sail artisan test`, and
   `sail pint --test`.
2. **Local dry-run:** use the isolated shell suite; it creates temporary local
   Git repositories and makes no AWS calls. Review workflow syntax separately.
3. **EC2 installation:** perform the one-time migration and run launcher
   `--dry-run` with the current approved SHA. Verify locks and logs.
4. **SSM test:** install a reviewed document version and invoke it with the
   already-deployed SHA. This live-script test still creates an S3 backup, so
   schedule and monitor it as a production deployment test.
5. **OIDC test:** confirm only the intended repository and `master` can assume
   the role, and that the role cannot target another instance or document.
6. **Manual workflow:** set `AWS_DEPLOY_ROLE_ARN`, dispatch from `master`, type
   `DEPLOY`, and verify tests, SHA, SSM output, backup, health, and smoke tests.
7. **Automatic activation:** after owner approval, add the `push` trigger in a
   reviewed pull request and monitor the first low-risk merge end to end.

Do not proceed after a failed stage.

## Production Smoke Tests

Verify `/up`, authentication, competition listings and downloads, public
concert discovery, authorised concert playback and seeking, original downloads,
branding assets, and an authorised staff media upload/finalisation. Confirm
unsigned private media remains inaccessible and inspect recent logs without
copying credentials or signed URLs into tickets.

## Known Limits

- Deployment uses an in-place fast-forward rather than immutable releases. The
  independent launcher prevents executing a partially updated deploy script;
  release-directory switching would be a separate project.
- Backup verification covers gzip integrity and exact object size. Only a
  restore rehearsal proves recoverability.
- Code rollback does not reverse migrations or S3 object changes.
- Retention, Git authentication, service names, IAM, SSM Agent version, and
  production health behaviour need manual validation before activation.

## Related Documentation

- [ADR-0007 - Commit-Pinned Two-Stage Production Deployment](../decisions/ADR-0007-Commit-Pinned-Two-Stage-Production-Deployment.md)
- [Git Workflow](Git-Workflow.md)
- [Security](Security.md)
- [Testing](Testing.md)
- [AWS](AWS.md)
