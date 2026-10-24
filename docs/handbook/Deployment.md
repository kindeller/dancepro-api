# Production Deployment

Production deployment is manual on one EC2 instance. Use one script as `ec2-user`:

```bash
cd /var/www/dancepro-api
bash scripts/dancepro-deploy.sh --dry-run 'approved-full-master-sha'
bash scripts/dancepro-deploy.sh 'approved-full-master-sha'
```

Replace the placeholder with the reviewed lowercase 40-character SHA. The script
copies itself to a private temporary file and runs that snapshot, so checkout
cannot replace the running deployment. No installed launcher, support bundle, protocol versions, process scans, or
service-mask changes are needed. Repository validation helpers run under the one
authoritative deployment script.

GitHub Actions still has **only `workflow_dispatch`**: merging to `master` does
not deploy. GitHub/SSM delivery remains unverified and deferred. Neither is needed
for the manual command above.

## Deployment sequence

1. Acquire the lock; validate user, clean `master`, origin, exact SHA and
   fast-forward ancestry, Apache's Artisan access, FPM, and current origin health.
2. Create and verify the existing private MySQL/MariaDB backup in S3.
3. Prepare code access and deployment-owned generated leftovers; enter maintenance
   and verify HTTP 503 before changing checkout.
4. Update the approved SHA and install locked production dependencies, repairing
   Git/Composer-created permissions at each boundary.
5. Run explicit cache clearing, migrations, all cache/view generation, and queue
   restart **as Apache**. Reload the exact configured PHP-FPM unit.
6. Verify bootstrap/view compilation, FPM `/up`, and a static asset during
   maintenance. Reopen and immediately check home, login, health, and public API
   status, response type, and content. Failure restores maintenance.
7. Write the private record `/var/log/dancepro-deploy/current-deployment.json`.

## One-time host setup

Recovery confirmed PHP-FPM runs as `apache` and Nginx as `nginx`. Reuse Apache's
existing group; add `ec2-user` to it if missing and start a new deployment login.
Apache already has its primary group, so no new group or FPM group restart is
required. Review existing ownership/ACLs before applying setup changes.

| Area | Intended access |
|---|---|
| Application root | `ec2-user:apache`, `2751`: deploy write, Apache read, Nginx traversal |
| Tracked code and dependencies | Deployment-owned, group `apache`; directories `2750`, files `0640`, required executables `0750` |
| Tracked public assets | Directories `2755`, files `0644`; executable bits preserved |
| `bootstrap/cache`, `storage/framework`, `storage/framework/views` | Group `apache`, directories `2770`, writable by both identities |
| Application logs | Apache writable; repair an existing incorrect log only by its exact reviewed path |
| Deployment logs | Deployment-only directory `0700`, files created under `077` |
| `.env`, keys, uploads, backups, Git metadata | Preserve separately; never recursively normalize them |

Separately approved setup examples, **not executed by Codex**:

```bash
id ec2-user
id apache
stat -c '%U:%G %a %n' /var/www/dancepro-api
# Only if membership is missing:
sudo usermod -aG apache ec2-user
sudo chgrp apache /var/www/dancepro-api
sudo chmod 2751 /var/www/dancepro-api
cd /var/www/dancepro-api
for path in bootstrap/cache storage/framework storage/framework/views; do
    sudo chgrp apache "$path"
    sudo chmod 2770 "$path"
done
sudo install -d -o ec2-user -g ec2-user -m 0700 /var/log/dancepro-deploy
sudo install -d -o ec2-user -g ec2-user -m 0700 /var/www/dancepro-staging
sudo touch /var/lock/dancepro-deploy.lock
sudo chown ec2-user:ec2-user /var/lock/dancepro-deploy.lock
sudo chmod 0600 /var/lock/dancepro-deploy.lock
```

Keep Nginx out of the Apache group. It needs public-file access and traversal of
public-storage ancestors, not `.env` or cached configuration access. Preserve the
recovered public-storage access; its link must resolve exactly to
`/var/www/dancepro-api/storage/app/public`. Review SELinux separately, without
disabling it. Do not recursively change storage permissions.

Validate these two scoped sudo grants with `visudo -cf` before deploying:

```sudoers
ec2-user ALL=(apache) NOPASSWD: /usr/bin/php /var/www/dancepro-api/artisan *
ec2-user ALL=(root) NOPASSWD: /usr/bin/systemctl reload php-fpm.service
```

Artisan runs as the application user, not root; arbitrary root PHP/shell/chmod is
not granted. No per-release sudoers or helper installation is needed. Keep the
existing PHP, Composer, Git, curl, sudo, coreutils, flock, gzip, AWS CLI and dump
tool available. Align queue/scheduler identity with Apache where practical;
`queue:restart` is a signal, not proof the supervisor restarted its workers.

## Concert media runtime permissions

**Required on live: `s3:ListBucket` on the concert bucket ARN
`arn:aws:s3:::dance-pro-concerts`.** This is a bucket permission; attaching it
only to `arn:aws:s3:::dance-pro-concerts/*` does not grant bucket access.
The Laravel runtime also needs the scoped object permissions documented in
[Required concert upload permissions](Concert-Streaming-AWS-Setup-Handoff.md#required-concert-upload-permissions).
Verify the identity used by PHP-FPM and queue workers, rather than relying on
the deployment user's AWS access or access to the database-backup bucket.

The 21 October 2026 live multipart upload incident was resolved by adding the
missing concert bucket `s3:ListBucket` permission. Parts uploaded successfully,
but the API completion step returned HTTP 500 and the upload remained pending.
The completion action checks for an existing assembled object with `HeadObject`
before completing the upload. Without `s3:ListBucket`, a missing object returns
403 instead of the 404 the application expects. See [AWS HeadObject documentation](https://docs.aws.amazon.com/AmazonS3/latest/API/API_HeadObject.html).

Include a real concert multipart upload through finalisation in release smoke
checks when changing media configuration or runtime IAM permissions. Confirm
the upload becomes `completed`; successful part transfers, `/up`, and deployment
backup checks do not verify this workflow. The deployment script does not
provision or validate concert IAM permissions. If finalisation fails, inspect
the production Laravel log for `HeadObject` / 403 at the request time before
assuming application code needs redeployment.

## Why this fixes the incident

Git/Composer use `027`, setgid code directories inherit the Apache group, and
explicit normalization also handles restrictive archive/file modes. Only tracked
code and dependencies are normalized. Storage, bootstrap cache contents, `.env`
variants and Git metadata are excluded from that code pass. Symlinks/hard links,
filesystem boundaries, and unexpected code ownership stop the operation.

All Artisan output is created by Apache, so Apache can read its caches, views,
maintenance files and logs even if sudo applies a restrictive mask. There is no
requirement to change the global FPM mask. Deployment-owned leftovers are repaired
only in bootstrap cache, compiled views, and the two exact maintenance files.
Other runtime/private contents are untouched. PHP-FPM cannot write source code.

## Adopt the new script safely

Earlier scripts remain in Git history. For the first corrected deployment,
extract and review the new script without updating production checkout first:

```bash
cd /var/www/dancepro-api
DEPLOY_SHA='replace-with-approved-40-character-master-sha'
[[ "$DEPLOY_SHA" =~ ^[0-9a-f]{40}$ ]]
git fetch --no-tags origin +refs/heads/master:refs/remotes/origin/master
git merge-base --is-ancestor "$DEPLOY_SHA" origin/master
umask 077
reviewed_script="$(mktemp /tmp/dancepro-reviewed.XXXXXX)"
git show "$DEPLOY_SHA:scripts/dancepro-deploy.sh" > "$reviewed_script"
bash -n "$reviewed_script"
sha256sum "$reviewed_script"
# Review it, verify host setup, then perform the nondeploying check:
bash "$reviewed_script" --dry-run "$DEPLOY_SHA"
# Separately approve this live invocation:
bash "$reviewed_script" "$DEPLOY_SHA"
```

A dry run fetches Git and checks access/health, but does not back up, change
permissions, enter maintenance, migrate, or update checkout. Ordinary runtime/log
activity can occur. Stop on failure; do not change the working application just
to force a pass. Remove only the exact reviewed temporary file after use.

After adoption, use the repository script directly. Deployment-script changes
normally take effect on the next invocation. Extract/review the target script as
above if a release needs its new deployment behavior immediately.

The existing `/usr/local/bin/dancepro-deploy` remains the **old** launcher until
separately updated; do not assume editing Git changes it. That installed command
is unnecessary for the direct path. Retire it from the deployment procedure and use the
repository script directly; the October transition checklist below applies.

## Validation, failure and recovery

Record the baseline SHA, clean tree, group/access metadata and public home/login/
API responses before the first corrected manual deployment. Check the private
record and public HTTPS pages/assets afterward; inspect fresh logs locally and
redact sensitive data before sharing. Approve authenticated/playback/download
smoke tests separately because they can create sessions/audits or AWS requests.

Checks default to local HTTP port 80 with the production hostname via `--resolve`.
If that listener redirects, set `DANCEPRO_ORIGIN_SCHEME=https` and
`DANCEPRO_ORIGIN_PORT=443`; TLS verification stays enabled. The exact unit/PHP path
can be set with `DANCEPRO_PHP_FPM_SERVICE` / `DANCEPRO_PHP_BIN`. Defaults are
`php-fpm.service` and `/usr/bin/php`. Keep any nonsecret overrides consistent for
SSH and later SSM. No new host configuration file is needed.

Compiling all views and bootstrapping as Apache before reopening catches the
permission failures seen here. Actual home/login/API requests run immediately
after `up`, leaving a short exposure window for route-specific failures. A
zero-exposure cutover requires a separate traffic-gate/release design.

Failure after maintenance is requested re-enters maintenance and verifies HTTP
503. If Laravel cannot boot, the script attempts a minimal standalone response
in the two exact maintenance files. Unverified closure is reported as **CRITICAL**:
an operator must block traffic at Nginx. Disk/host failure, inaccessible framework
directories or stale OPcache can still require intervention. Use file maintenance
when relying on this fallback. There is no automatic Git reset, migration rollback
or database restoration; review migrations and customer writes before recovery.

The existing backup gate is preserved: current-release Dotenv reading, private
client file, nonempty dump, gzip verification, S3 upload to
`dance-pro-db-backup/deployments/`, and equal nonzero local/S3 sizes. Backup failure
leaves checkout and maintenance unchanged. Only a restore rehearsal proves
recoverability. No S3 deletion or protection change is introduced.

## Local tests and future SSM

```bash
bash tests/Deployment/deployment-scripts.sh
sail exec -T -u root laravel.test bash tests/Deployment/deployment-scripts.sh --integration
sail artisan test
sail pint --test
```

The isolated fixture uses real Laravel cache/migration operations, a minimal
SQLite schema and separate Unix identities, with cloud/HTTP/service boundaries
stubbed. It tests permissions, backup ordering, failures, locks and replacement of
the repository script during checkout. The normal MySQL suite covers the full app
schema. Neither suite proves actual EC2 sudoers, FPM, Nginx, SELinux or S3.

The proposed SSM document invokes the same repository script as SSH. Later, test
SSM identity/environment/exit reporting with an approved nondeploying command or
dry run, then authorize one controlled deployment. Verify GitHub OIDC identity
before testing its manual workflow. Keep both integrations unverified until those
stages pass, and keep `workflow_dispatch` as the only trigger. Updating document
source does not update AWS or the working production application.

See [ADR-0008](../decisions/ADR-0008-Single-Snapshot-Deployment.md),
[Testing](Testing.md), and [Git Workflow](Git-Workflow.md).

### GitHub test-job bootstrap

The manual production workflow installs PHP 8.5 and Composer 2 using
`shivammathur/setup-php`, then installs `composer.lock` with development
packages so `vendor/bin/sail` is available. It checks platform requirements on
both the runner and Sail; no platform requirements are ignored. This bootstrap
is required before the vendor-provided Sail runtime can be built.

Integration and Laravel tests still use the PHP 8.5 runtime in `compose.yaml`.
Sail waits for services to become healthy before testing. MySQL publishes port
13306 on the disposable runner to avoid a host MySQL conflict; Laravel still
connects to `mysql:3306` inside Docker. Cleanup runs after a Sail startup attempt
(including a partial startup failure), but skips failed dependency installation
so a missing Sail executable does not obscure the installation failure.

## Vite deployment and launcher transition (October 2026)

The authoritative entry point is `scripts/dancepro-deploy.sh`, with one positional
approved full SHA. Do not use `/usr/local/bin/dancepro-deploy`: the installed old
launcher passed named options and caused the interrupted deployment. Neither a
Git update nor editing this document replaces an installed launcher or SSM document.
The reported online baseline is `83b37afa927f2033856e1fbd8a886cedbf4c50e8`;
verify it on EC2 rather than treating HEAD as evidence of a completed deployment.

### One-time prerequisites

- Install **Node.js 22.22.0** and its bundled npm for the EC2 architecture. Verify
  the official archive checksum before installation. Expose `node` and `npm` in
  `/usr/local/bin` or `/usr/bin`, not only an interactive NVM shell. The deployment
  script requires this exact Node version; Actions uses the same pin.
- Retain the existing PHP/Composer, dump tool, AWS CLI, Apache group, scoped sudo,
  lock/log directory and runtime permissions described above. Also provide `tar`.
- Provision `/var/www/dancepro-staging` once using the setup command above:
  `ec2-user:ec2-user`, mode `0700`. Keep `/var/www` owned by `root:root` with mode
  `0755`; the deployment user does not need write access to it. The script creates
  private `dancepro-release.*` directories inside the dedicated parent. It rejects
  missing, symlinked, incorrectly owned or non-0700 parents and requires the same
  filesystem as `public/build`. Allow enough disk for dependencies, a build and
  retained previous assets. `DANCEPRO_STAGING_PARENT` supports isolated tests or a
  deliberately provisioned alternate absolute path with the same checks.
- Permit outbound HTTPS to the npm registry and Bunny font endpoints used by the
  configured Laravel Vite font plugin. No production secrets enter build staging.
- `public/build`, if present, must be a deployment-owned regular directory, without
  symlinks. Nginx requires public directory traversal and files readable as 0644.
  Review and remove only an identified stale `public/hot` before deployment; the
  script refuses it rather than deleting it automatically.
- Keep file-based maintenance and the existing origin hostname/port overrides.
  The local Nginx listener must serve `/build/*` statically during maintenance.
- Verify SSM Agent supports `interpolationType: ENV_VAR`, the instance is managed,
  the existing OIDC role can send/read this command, and the instance retains its
  existing Git and S3 backup access. No new AWS resources are needed.

### Safe adoption before enabling the workflow

1. Stop use of the old launcher and do not start a workflow during transition.
   Inspect current HEAD, clean tree, maintenance state, migration status, deployment
   log and `current-deployment.json`. Confirm baseline HTTP responses. Reconcile the
   interrupted deployment before attempting another; do not reset code or rerun
   migrations blindly.
2. After the implementation is reviewed and separately committed/merged, fetch the
   approved SHA and extract `scripts/dancepro-deploy.sh` from that SHA to a private
   temporary file using the adoption commands above. Review/hash it and run its
   `--dry-run` with the positional SHA. This selects the new executor without
   changing the live checkout first. The first actual invocation still requires
   explicit deployment approval. Dry-run validates tools but does not build.
3. Use that reviewed executor for the first approved manual deployment. It stages
   the target's tracked inputs, runs `npm ci --include=dev` and `npm run build`, and
   validates the entire build **before backup, maintenance or Git advancement**.
   A failed build leaves the existing checkout and assets untouched. Tailwind's
   Laravel pagination sources come from the current installed framework; rerun
   the Actions build when changing the framework version as well.
4. Manually update the existing AWS Systems Manager **Command document**
   `DancePro-Deploy-Production` in `ap-southeast-2` from
   `deployment/aws/ssm/DancePro-Deploy-Production.yaml`. In the AWS console, create
   a new document version and explicitly make it the **default version**. Review
   its content: it must call `/bin/bash /var/www/dancepro-api/scripts/dancepro-deploy.sh`
   as `ec2-user`, passing only `"$SSM_TargetCommit"`; it must never call the installed
   launcher. Verify the installed default document content before running Actions.
   Updating the file in Git does not perform this AWS update.
5. Once the corrected repository script is present on EC2, SSH and SSM both invoke
   that same script directly. Retire the old launcher from operational instructions;
   it need not be replaced to use this process. Any removal/rename of its installed
   file is a separate reviewed host operation. Do not keep two deployment engines.
6. After approved SSM validation, trigger Actions only on reviewed `master`, enter
   `DEPLOY`, and verify both the test job and SSM execution complete successfully.
   Merging alone never deploys. A future change to deployment-script behaviour must
   be adopted from the reviewed target script before a deployment that needs it;
   the running executor snapshots itself and is not replaced mid-run.

### Publish and verification

The complete validated build directory is moved into place during maintenance,
with 0755 directories and 0644 files. The prior directory is retained at the exact
`Previous assets:` path in the deployment log. No build runs in the live public
folder. All staging paths are logged and retained for operator inspection; do not
let retained node_modules consume the disk indefinitely. After confirming success,
review and remove only the exact staging directories no longer needed for recovery.

Validation checks every manifest entry, referenced CSS/assets and imported chunks,
required entry points, regular files, and rejects links/path escapes. Referenced
assets must be nonempty except the intentionally empty placeholder app.js entry.
The configured font files and font manifest travel with the complete directory.
The player bundle must return HTTP 200, a JavaScript type and byte-identical content
through local Nginx. Laravel boots with its production caches and renders the actual
concert Blade view with in-memory fixture models (no customer data, playback URL
signing or writes). Home/login/public API route checks run through the CLI HTTP
kernel with maintenance middleware excluded **only in that process**; maintenance
remains HTTP 503 publicly. FPM `/up` and static asset checks run before reopening;
real HTTP route checks run immediately after reopening and failure closes again.
These checks do not prove CloudFront playback: verify a permitted concert in Chrome
and confirm `/playback` and the compiled bundle load after release approval.

### First-deployment failure recovery

- Before maintenance: inspect the logged staged build failure; the serving release
  and assets remain intact. Fix the cause, obtain approval and rerun the same
  reviewed script. Do not force past failed validation.
- After maintenance/Git changes: leave HTTP 503 in place. Read the exact failure,
  current SHA, rollback tag, previous-assets path, backup key and migration state.
  The script does not automatically reset Git, restore the database or reopen.
- If asset publishing failed after moving the old build, its complete directory is
  at the logged previous-assets path. Under the deployment lock and maintenance,
  preserve any new build separately and move that exact prior directory back to
  `public/build` only as part of recovery to its matching application release.
  The first deployment may have **no** previous build; do not invent a backup path.
- Prefer a corrected forward deployment after reviewing migrations. If reverting
  code is necessary, use the recorded rollback reference and review compatibility
  with applied migrations/customer writes before separately authorising recovery.
  Restore the matching assets, locked PHP dependencies and caches; reload FPM and
  repeat all checks before reopening. Never blindly run migration rollback or
  restore the backup. A database restore requires its own reviewed recovery plan.
- If closure cannot be verified, the script reports CRITICAL: keep traffic blocked
  at Nginx and investigate locally. Do not rerun the legacy launcher.
