**DancePro production recovery and serving-confidence walkthrough**

Prepared from the repository on 18 October 2026. This is a handoff for a separate ChatGPT conversation while the owner operates the live server. Creating this document has not changed production or the deployment scripts.

**Paste this document into ChatGPT and start with this instruction:**

> Walk me through this investigation one stage at a time. Start by confirming whether the application is currently serving normally, showing maintenance, or failing. Give me one small command group, explain what it establishes, wait for my results, and choose the next step from the evidence. My priority is confidence that normal pages and endpoints work, including routes that the home page does not exercise. Start with diagnostic checks only. Do not suggest a redeployment, permission change, cache clearing, service restart, database operation, or launcher update until we have identified the exact problem and I explicitly approve that action. Keep an evidence log and prepare the Codex handoff at the end. Never request credentials, cookies, signed URLs, private-key contents, full configuration dumps, or unredacted logs. If the deployed release differs from this document, verify its routes and behavior before using the examples.

**1. Starting context and boundaries.**

Reported environment, to verify rather than assume:

| Item | Reported value |
|---|---|
| Application | Laravel 13, PHP 8.5; verify installed versions |
| EC2 operating system | Amazon Linux |
| Checkout | `/var/www/dancepro-api` |
| Deployment identity | `ec2-user` |
| PHP-FPM worker identity | `apache` |
| Web server | Nginx; its worker user is not yet verified |
| Production hostname | `dancepro.dotdeveloper.com.au` |
| Installed launcher | `/usr/local/bin/dancepro-deploy`, separate from the checkout |
| Git integration branch | `master` |
| AWS region | `ap-southeast-2` |

The owner reports that the main page now returns, following live permission repairs. First establish whether that means the normal studio listing, the maintenance page, or another response. Do not turn maintenance on or off simply to begin this investigation. Do not run either deployment entry point, including a same-commit deployment. The launcher's dry run still fetches Git data and creates logs; it is not needed for this serving investigation.

The original incident reported unreadable application code, bootstrap caches, and compiled Blade views. Repository review found:

- `scripts/dancepro-deploy-launcher.sh` uses `umask 027` before Git checkout updates.
- `scripts/dancepro-deploy.sh` uses `umask 077` before Composer and Artisan operations.
- Neither script establishes shared ownership or validates PHP-FPM's filesystem access.
- Composer's `post-autoload-dump` hook invokes `artisan package:discover`.
- Maintenance creates `storage/framework/down` and `maintenance.php`; these must also be readable by PHP-FPM.
- The application script runs `artisan up` before checking only `http://127.0.0.1/up`.
- `/up` is excluded from Laravel maintenance mode. It does not cover all application templates or database operations. Its HTML response can render a framework view, which is still different from the application's compiled views.
- `umask` affects creation, not existing files. Group inheritance, ACLs, explicit modes, and existing ownership also matter. Some Laravel atomic replacements can produce `0700` files under `077`, not only `0600`.

No single successful request proves every route works. The goal is a bounded filesystem scan plus representative requests through the real web stack, with untested capabilities recorded honestly.

Initial checks do not intentionally change code, permissions, configuration, caches, or business data. Normal HTTP requests can still write access logs, sessions, or compiled views. Artisan bootstrapping can log failures or invoke application providers. If absolutely no writes are permitted, stop before HTTP/Artisan checks and limit the session to metadata inspection; that cannot prove serving behavior.

All commands below are examples for the EC2 operator, not instructions for Codex to execute against production. Local development commands use Sail. Direct PHP/Artisan below are specifically for the live host, which does not use the local Sail environment. Confirm `/usr/bin/php` matches the deployed PHP version before using it.

**2. Establish the current baseline.**

Begin with metadata only:

```bash
cd /var/www/dancepro-api
date -u
id
git rev-parse HEAD
git status --short --branch
readlink -f /usr/local/bin/dancepro-deploy
stat -c '%U:%G %a %n' /usr/local/bin/dancepro-deploy
sha256sum /usr/local/bin/dancepro-deploy scripts/dancepro-deploy-launcher.sh
/usr/bin/php -v
id ec2-user
id apache
ps -eo user,group,pid,comm | awk 'NR == 1 || /php-fpm|nginx/'
```

Record the UTC time, deployed SHA, whether the checkout is dirty, and actual PHP-FPM/Nginx worker identities. The PHP-FPM master may be root; inspect the worker rows. `id apache` shows account membership, which may differ from already-running processes after a group change. For a verified worker PID, `/proc/<pid>/status` fields `Uid`, `Gid`, `Groups`, and `Umask` can help; inspect those fields only.

Different launcher checksums are evidence of a difference, not permission to replace the installed launcher. Record both. The installed file should be an independent root-owned regular file; if it is a symlink, report that to Codex. Do not print launcher contents if live customization might contain confidential values.

Check maintenance metadata without displaying its payload:

```bash
for path in storage/framework/down storage/framework/maintenance.php; do
    if [ -e "$path" ]; then
        stat -c '%U:%G %a %n' "$path"
    else
        printf 'Absent: %s\n' "$path"
    fi
done
```

Presence alone is not a complete maintenance-state check: the configured maintenance driver may be `cache`. Do not print `.env` or cached configuration to find out. Establish actual HTTP behavior in stage 3 and, if needed, have an operator report only the maintenance driver name.

**Return to Codex now if:** the SHA differs from the reviewed release, the checkout has unexplained changes, PHP-FPM runs as another user, the launcher is a symlink, or its installed version is uncertain. Continue independent HTTP checks, but do not make repairs based on assumed identities or script versions.

**3. Test the real hostname and distinguish normal content from maintenance.**

Run one request at a time, with short timeouts and no automatic redirect following. Start against the public hostname:

```bash
curl --silent --show-error --connect-timeout 5 --max-time 15 \
    --output /dev/null \
    --write-out 'HTTP %{http_code}; type=%{content_type}; bytes=%{size_download}; seconds=%{time_total}\n' \
    https://dancepro.dotdeveloper.com.au/
```

Do not use `--fail` for this first diagnostic request: expected 401/403/503 statuses are useful evidence. Curl transport success is not application success; record both exit errors and HTTP status. Do not use `-I` as a substitute for GET or `-L`, which could hide an authentication redirect behind a final 200.

Check normal content locally in the browser. This checkout's home page contains **“Find your dance studio.”**; its maintenance page contains **“temporarily unavailable”**. A 200 with unexpected content is a failure to investigate. Record only the marker and status, not full HTML, cookies, personal data, or configuration.

If HTTPS terminates on this EC2 host, compare the same virtual host while connecting directly to loopback:

```bash
curl --silent --show-error --connect-timeout 5 --max-time 15 \
    --resolve dancepro.dotdeveloper.com.au:443:127.0.0.1 \
    --output /dev/null \
    --write-out 'HTTP %{http_code}; type=%{content_type}; bytes=%{size_download}; seconds=%{time_total}\n' \
    https://dancepro.dotdeveloper.com.au/
```

If TLS terminates upstream and local Nginx listens on HTTP, use the confirmed listener with the production Host header:

```bash
curl --silent --show-error --connect-timeout 5 --max-time 15 \
    --header 'Host: dancepro.dotdeveloper.com.au' \
    --output /dev/null \
    --write-out 'HTTP %{http_code}; type=%{content_type}; bytes=%{size_download}; seconds=%{time_total}\n' \
    http://127.0.0.1/
```

A local HTTP-to-HTTPS redirect can be correct. Do not add guessed forwarded headers or disable TLS verification to force a pass. Ask the operator to identify the server block, TLS termination, document root, and PHP-FPM socket/pool. Do not paste a full `nginx -T`, FPM configuration, or service environment into chat; extract only these nonsecret settings.

| Outcome | Next action |
|---|---|
| Normal home page works both ways | Continue the route matrix and permission scan. |
| Public works, direct origin fails | Resolve listener/Host/TLS differences before treating it as an app permission failure. A proxy/cache can mask an origin problem. |
| Direct origin works, public fails | Investigate DNS, TLS, proxy, routing, and caching. Do not change Unix modes based on this alone. |
| Ordinary pages return 503 | Validate maintenance access and permission metadata. Do not run `artisan up` just to test routes. |
| 500 or a permission-denied error | Stop repeated probes, note the UTC time, and correlate a single request with a redacted error. |
| `/up` is 200 but pages fail | Treat application readiness as failed and continue with the affected template/runtime path. |

If maintenance prevents normal-page testing, report those checks as **blocked by maintenance**, not failed or passed. Testing behind a controlled maintenance bypass or web-server gate is a separate approved step; do not create or share a bypass value in chat or deployment logs.

**4. Run the simple cross-route permission investigation.**

A page can work while another route includes an unreadable controller or compiled template. The following scan looks for unreadable regular files and untraversable/unlistable directories as the actual runtime user. It reads filesystem metadata, not file contents. Replace `apache` only after verifying the real pool identity.

Run as the deployment user with the necessary sudo permission. A sudo denial means **not tested**, not application failure. Do not broaden sudoers to run the check.

```bash
sudo -u apache -- /bin/bash <<'BASH'
set -o pipefail
cd /var/www/dancepro-api || exit 1
for area in app bootstrap config resources routes vendor public database/migrations; do
    printf '\nChecking %s\n' "$area"
    find -P "$area" -xdev \
        \( \( -type f ! -readable \) -o \
           \( -type d \( ! -readable -o ! -executable \) \) \) \
        -print 2>&1 | awk 'NR <= 30 { print } END { printf "Reported entries: %d\n", NR }'
    scan_status=${PIPESTATUS[0]}
    printf 'find exit status: %s\n' "$scan_status"
done
BASH
```

Interpret each area separately: zero reported entries **and** exit status 0 is the desired result. Permission errors, a missing expected directory, or a nonzero status mean the scan is incomplete. The 30-line display limit does not stop the scan; the reported total reveals additional findings.

The scan does not follow symlinks or descend across filesystem boundaries. Separately identify symlink/mount boundaries in these approved trees and review only their intended targets. In particular, `public/storage` is normally a symlink. No result here proves access to its target. Do not broaden the scan to `.git`, all of `storage`, private media, database files, backups, or home directories.

For each first failing path, inspect it without repairing it:

```bash
namei -l /var/www/dancepro-api/bootstrap/app.php
stat -c '%U:%G %a %n' bootstrap/app.php bootstrap/cache storage/framework/views
getfacl -p bootstrap/app.php bootstrap/cache storage/framework/views
```

Substitute the exact failing path when relevant. `namei` reveals missing execute/traversal permission on ancestors. If `getfacl` is unavailable, record that; do not install packages automatically. Inspect SELinux labels as well if Unix permissions look correct, but do not relabel yet.

Check the runtime directory metadata and effective access separately:

```bash
sudo -u apache -- /bin/bash <<'BASH'
cd /var/www/dancepro-api || exit 1
for path in bootstrap/cache storage/framework storage/framework/views \
    storage/framework/cache storage/framework/cache/data storage/framework/sessions storage/logs; do
    if [ ! -d "$path" ]; then
        printf 'MISSING_OR_INACCESSIBLE %s\n' "$path"
    elif [ -r "$path" ] && [ -w "$path" ] && [ -x "$path" ]; then
        printf 'ACCESS_OK %s\n' "$path"
    else
        printf 'ACCESS_FAIL %s\n' "$path"
    fi
done
for path in storage/framework/down storage/framework/maintenance.php storage/logs/laravel.log; do
    if [ -e "$path" ]; then
        [ -r "$path" ] && printf 'READ_OK %s\n' "$path" || printf 'READ_FAIL %s\n' "$path"
        if [ "$path" = storage/logs/laravel.log ]; then
            [ -w "$path" ] && printf 'WRITE_OK %s\n' "$path" || printf 'WRITE_FAIL %s\n' "$path"
        fi
    else
        printf 'ABSENT_OR_INACCESSIBLE %s\n' "$path"
    fi
done
BASH
```

Confirm missing/inaccessible paths with the deployment user's metadata check. File sessions and file cache locations are required only if configured or otherwise used; repository defaults are database-backed sessions, cache, and queues. Log paths can also differ. A missing unused directory alone does not justify creating it.

For `storage/framework/views` and `bootstrap/cache`, use the same bounded `find` check if not already covered. Do not list session filenames or cached payloads in chat. Check other log files only by approved exact paths.

`test -w` does not prove an actual write will succeed: read-only mounts, disk/inode exhaustion, quotas, or SELinux may still interfere. Metadata-only follow-ups include `df -h /var/www/dancepro-api` and `df -i /var/www/dancepro-api`. Actual create/remove probes are a separate explicitly approved step in stage 7.

Also inspect whether Apache can write the application root or source directories. That would allow replacement of code even if individual files appear read-only. Report unexpected source writability to Codex; do not silently fix it.

These sudo checks test Unix access for a newly started process. They do not reproduce PHP-FPM's existing supplementary groups, service sandbox, SELinux domain, OPcache, or Nginx access. HTTP checks remain necessary. Nginx needs access to public assets and the public-storage target under its own verified identity; it should not be added to a group with secret access just to serve assets.

**Return to Codex with:** the first few failing paths, owner/group/modes, relevant ancestor permissions, ACL effective permissions, and the runtime user. Do not paste entire directory listings or file contents. If all scans pass but HTTP fails, send the request time and sanitized exception; avoid assuming that another recursive chmod will help.

**5. Exercise representative routes from this project.**

Use the production hostname and the transport confirmed in stage 3. Repeat the short GET command with one path at a time. Validate body shape/content locally; status alone is insufficient. In a browser, use a private window for unauthenticated checks. Disable browser cache while inspecting the Network tab, and verify responses came from the server rather than browser cache. Do not export a HAR containing cookies or authorization headers.

| Request | Expected when serving normally | What it establishes / limits |
|---|---|---|
| `GET /up` | 200; JSON `status: up` if requesting JSON | Basic Laravel health response, not full app readiness. |
| `GET /` | 200 and normal studio-listing marker | Database query, public layout, studio listing. Empty available-studio data is valid. |
| `GET /login` | 200; page title `DancePro Login`, form visible | A separate Blade template and include; do not submit a login initially. |
| `GET /api/studios` with `Accept: application/json` | 200; `success: true`, `data` is a list | Public API serialization and database query. Empty list is valid. |
| `GET /admin` without authentication | 302 redirect to the intended login page | Authentication middleware and redirect; does not exercise the protected dashboard. Inspect the destination locally. |
| `GET /api/auth/me` with JSON accept and **no authentication** | 401; JSON error with `success: false` | Unauthenticated API handling only. Do not attach a bearer value. |
| `GET /api/competitions/objects` with JSON accept and **no authentication** | 401 | Competition route registration/auth gate only, not its S3-backed listing. |
| `GET /favicon.ico` | 200 if installed nonempty; correct image response | Nginx/static-file access. An empty file needs separate interpretation. |
| `GET /js/admin-concert-uploader.js` | 200 and JavaScript body/type | A tracked static asset in this checkout. |
| `GET /storage/1024.png` | 200 and PNG if the deployed branding file exists | Public-storage symlink and Nginx target access. The image is referenced by the public/admin layouts and maintenance page; it is server-managed. |
| `GET /api/dancepro-recovery-check-not-a-route` with JSON accept | 404 and the normal JSON error shape | Error handling; one intentional 404 is sufficient. |

For JSON checks, add `--header 'Accept: application/json'` to the request. A 200 HTML maintenance/login page does not satisfy a JSON check. A 401/403 is not automatically a permissions fault: it can be the correct authorization response. File-access failure usually appears as 500 or a relevant server error, not a normal Laravel authorization response.

Use existing public records for deeper checks. Get the **public UUIDs** from the public studio API or follow links in the normal UI; never guess internal numeric IDs or publish records for testing.

- For a publicly listed studio: `/studios/<studio-uuid>` and `/api/studios/<studio-uuid>` should return 200.
- For a currently available concert from that studio: `/concerts/<concert-uuid>` and `/api/concerts/<concert-uuid>` should return 200. The HTML page can correctly be an unlock form; that does not test the unlocked player template.
- Test a studio/concert cover only if the UI/API indicates one exists. A missing optional cover can legitimately return 404.
- Slug routes `/s/<slug>` and `/c/<slug>` can be checked with real public slugs; verify the redirect destination locally without blindly following every link.

Publication, approval, enabled flags, availability dates, and access grants affect these results. A 404 on an arbitrary/private/expired record proves little. If no eligible records exist, mark those branches **not tested: no public fixture**.

Do not crawl all routes or automatically request every GET route. In this project, authenticated `GET /api/auth/me` writes `last_seen_at`; successful web login updates timestamps/session state; Sanctum can update usage metadata; tracked `/download/<value>` requests record accesses and update state. Unlock, upload, finalization, deletion, and revocation flows are also outside the initial diagnostic phase. Do not test them with fake submissions.

If an existing signed-in session is available and the owner approves normal session/audit activity, manually inspect the admin dashboard, competition listing, concert media listing, and an authorized unlocked concert page without saving anything. Do not share session values or full personalized responses. Playback and tracked downloads require their own approved smoke test and can incur AWS requests or audit writes.

**Optional route-registration check on EC2, after the identity/path checks:**

```bash
sudo -u apache -- /usr/bin/php artisan route:list --except-vendor
```

This boots the CLI application as Apache and lists methods, URIs, and middleware. It does not run controllers or prove every endpoint serves. It can trigger provider bootstrap/logging. Do not clear route caches to make the output match expectations; report mismatches between the live route list, deployed SHA, and this document. Do not run production tests, migrations, seeders, Tinker, `config:show`, `phpinfo`, or full environment/config dumps.

**6. Correlate failures without exposing sensitive data.**

For each failing probe, record the UTC timestamp, method/path without query parameters, expected behavior, actual status/type, and whether it failed publicly, at origin, or both. Inspect only the matching short error excerpt locally from the configured Laravel/Nginx/PHP-FPM log. Share the exception class/message, relevant application filename and line, and request time after redaction. Logs can contain credentials, signed URLs, request data, and personal information; never paste a raw tail or complete stack trace without review.

| Evidence | Interpretation and next step |
|---|---|
| `Permission denied` including an app/vendor file | Inspect exact file and ancestors under Apache; return metadata to Codex. |
| Failure in `bootstrap/cache` or compiled views | Record creator ownership, mode, and current generation identity; do not clear/rebuild yet. |
| Static 403 while PHP pages work | Check the Nginx identity, asset path, symlink target, server rules, and labels. |
| Static 404 | Check deployment existence and expected URL before changing permissions. |
| SQL connection/table/column error | Treat as database/config/schema evidence; no automatic migration or restore. |
| Signing-key access error | Report only the error category and redacted path metadata; never display the key or cached config. |
| S3/CloudFront authorization failure | Separate cloud permissions/signing/config from local Unix access; return the service/error code without credentials or signed URLs. |
| Works intermittently | Consider different FPM workers/groups, multiple origin nodes, OPcache, and caching; correlate the origin before changing anything. |
| All checks pass | Record precisely which routes and permission areas passed; continue to the confidence criteria below. |

For SELinux, an operator may check `getenforce` and `ls -Zd` on the exact affected paths. Match audit timestamps to the failed request. Records marked permissive are not proof of enforcement blocking that request. Do not disable SELinux or apply broad relabeling. Persistent context changes require a separate reviewed scope.

**7. If a repair or stronger probe is needed, pause for approval.**

ChatGPT should first provide the exact evidence, proposed paths, operation, expected benefit, and verification/undo plan. Obtain explicit approval before any of these:

- Changing owner/group/mode/ACLs, group membership, or SELinux labels.
- Creating/removing a runtime write probe; restrict it to one uniquely named file created by the probe in each approved directory, never an existing file.
- Clearing/rebuilding a Laravel cache or regenerating compiled views. `optimize:clear` also clears the application cache store in this framework; avoid using it as a generic repair command.
- Restarting/reloading services, changing maintenance state, updating the launcher, or changing code.
- Running an authenticated flow that writes usage/audit data, issuing API access, uploading, finalizing, or requesting a tracked download.

A repair must enumerate its targets before executing. Preserve `.env`, AWS/Git credentials, signing keys, `.git`, private media, uploaded files, database files/dumps, backups, and other sensitive storage. Do not run recursive chmod/chown across the application or all storage. Do not follow symlinks or mounts during normalization without reviewing the exact target. Avoid `777` and do not make sensitive files world-readable.

The proposed long-term model is deployment-owned/readable code with a dedicated app group, setgid inheritance, and separately writable runtime directories. Git/Composer code operations can use `027` with the correct group; runtime-generating Artisan should use a deliberate runtime identity/mask, often `apache` with `007`; private backup/log staging keeps `077`. These are recommendations, not approved production settings. Actual modes and ACLs must fit the verified host. Changing only the umask does not repair existing files.

Prefer repairing the current release if evidence isolates filesystem access. A code rollback does not restore permissions and may be incompatible with migrations already applied. No automatic database restore, migration rollback, or Git reset. If normal serving is already restored, do not redeploy merely to test whether it stays restored; validate future deployment changes separately before use.

If maintenance is active, readiness testing before opening needs an approved private bypass or external web-server gate. Do not open public traffic first just to perform checks. If maintenance must be re-enabled because of a confirmed failure, that is an owner-approved operational action and must itself be verified over HTTP.

**8. Decide how much confidence the evidence supports.**

| Confidence area | Evidence needed | What remains unproven |
|---|---|---|
| Public web/API serving | Correct normal content for `/`, `/login`, public JSON, correct origin/hostname, expected error/auth behavior | Authenticated actions and data-dependent branches not visited |
| Existing code/cache access | Completed runtime-user scan, no unreadable code/generated templates, correct ancestor traversal and reviewed symlink targets | Future file creation, SELinux/FPM differences not exercised by HTTP |
| Runtime operation | Expected runtime access, no fresh permission errors during requests; approved write probe if needed | Untested writers, rotated logs, queue/scheduler identity differences |
| Authenticated application | Approved browser checks of actual admin/unlocked templates | Mutations, uploads, finalization, tracked downloads not performed |
| Media delivery | Separately approved authorized playback/range/download checks, private access still denied | Every object/device or future cloud change |
| Deployment will preserve access | Scripts fixed/reviewed, mixed-identity tests pass, installed launcher verified, representative deployment validation | Cannot be established by manual repairs or home-page success alone |

At the end, label each area **passed**, **failed**, **blocked**, or **not tested** with the evidence and time. If the home page, independent login view, public JSON route, existing compiled views, relevant code trees, and assets pass, that is substantially stronger evidence than `/up` alone. It still does not justify claiming all endpoints or future deployments are safe.

If probes create new compiled files through normal requests, inspect a sample's owner/group/mode without reading its contents. This provides evidence of the live runtime writer; it does not establish how Composer/Artisan will write files at the next deployment.

**9. Return this structured outcome to Codex.**

Paste only sanitized evidence. Do not put confidential output in this tracked document or Git.

```text
DancePro production investigation handoff

UTC time/window:
Current serving state: normal / maintenance / errors / mixed
Deployed full SHA:
Working tree: clean / changed (sanitized filenames only)
Installed launcher: regular file / symlink; owner/group/mode; SHA256
Checkout launcher SHA256:
PHP CLI version and executable:
PHP-FPM service/pool and worker identity:
Nginx worker identity:
Relevant running-process group/mask observations:
TLS termination/local listener and document root:
Other origin nodes/proxy caching, if known:

HTTP results (method/path, expected/actual status, content check, public/origin):
  /up:
  /:
  /login:
  /api/studios:
  /admin unauthenticated:
  /api/auth/me unauthenticated:
  /api/competitions/objects unauthenticated:
  favicon / JS / branding image:
  Real public studio/concert routes:
  Approved authenticated/media checks, if any:

Filesystem scan: areas checked, totals, exit statuses, skipped symlinks/mounts
First failing paths and sanitized owner/group/mode/ACL/ancestor evidence:
Runtime access findings and active driver names only:
Unexpected Apache write access to source:
SELinux mode and relevant redacted finding:
Fresh errors: UTC time, route, sanitized exception and relevant file/line:

Any live repairs already performed: exact commands/scope/identity/mask,
before/after metadata, service actions, and validation outcome.
Do not include secret values or signed URLs.

Still failed/blocked/not tested:
Does routine serving appear restored? Evidence:
Can deployment recurrence be ruled out? Normally no until scripts are fixed:

Requested Codex follow-up:
  - Interpret evidence only / propose patch / implement an approved patch.
  - No production operation, branch change, commit, push, or PR authorized
    unless separately requested.
```

Codex should use the outcomes to scope changes to `scripts/dancepro-deploy-launcher.sh`, `scripts/dancepro-deploy.sh`, `tests/Deployment/deployment-scripts.sh`, and `docs/handbook/Deployment.md`, plus the deployment ADR if an architectural decision changes. The workflow/SSM document should change only where evidence requires it. Account for Composer hooks, maintenance artifacts, cache generation, existing incorrect files, runtime identities, workers, log rotation, origin hostname, and checks before `artisan up`. Updating the installed launcher remains a separate operational action.

**10. Reference and verification notes.**

Repository basis: `routes/web.php`, `routes/api.php`, public studio/concert controllers, authentication controllers, branding controller, layouts, filesystem/cache/session/queue configuration, deployment scripts, SSM document, deployment handbook, and installed Laravel maintenance/cache/view implementations. The route matrix describes this checkout; the live SHA and live route list take precedence when they differ.

The preceding repository investigation passed `bash tests/Deployment/deployment-scripts.sh`. That suite stubs the application deploy script, so it does not prove real cache or web-user permissions. Its passing result is not production readiness evidence. The local Laravel suite could not run because Sail reported `Docker or Podman is not running`.

Primary background references:

- [Linux umask semantics](https://man7.org/linux/man-pages/man2/umask.2.html)
- [Linux ACL semantics](https://man7.org/linux/man-pages/man5/acl.5.html)
- [Laravel maintenance mode and bypass behavior](https://laravel.com/docs/13.x/configuration#maintenance-mode)
- [PHP-FPM pool and socket configuration](https://www.php.net/manual/en/install.fpm.configuration.php)

Keep recovery of current serving behavior separate from proving the next deployment is safe. Both need evidence; neither requires an immediate redeployment.
