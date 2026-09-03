# Production Launch Checklist

Use this checklist for the final production go/no-go review. It records external
deployment decisions that cannot be discovered or completed from the repository
alone. Do not write credentials, private keys, personal phone numbers or other
secrets in this file; keep those in the selected provider's secret store and the
restricted deployment record.

The application is not launch-ready until every required item below has an
owner, has been tested in the final production environment and is marked
complete in the private deployment record.

## Production ownership

- [ ] Production host and support responsibility are identified.
- [ ] Public domain, DNS owner and cutover window are recorded.
- [ ] Incident owner and monitored alert destination are recorded privately.
- [ ] A developer or managed-host escalation path is recorded, if applicable.
- [ ] The approved privacy retention periods and legal-hold owner are recorded.

## Hosting and processes

- [ ] The server meets the PHP, Composer, Node.js, npm, database and web-server
  requirements in [Deployment](Deployment.md).
- [ ] The Laravel scheduler runs every minute and is supervised.
- [ ] Queue workers use the configured production queue and are supervised.
- [ ] PHP-FPM and the web server restart automatically after a host reboot.
- [ ] `storage` and `bootstrap/cache` are writable only by the required runtime
  accounts.
- [ ] TLS is valid for the public hostname and HTTP redirects safely to HTTPS.

## Configuration and secrets

- [ ] The production `.env` exists only in the deployment environment and was
  built from the current `.env.example` keys.
- [ ] Database, mail, AWS and CloudFront credentials have least-privilege access.
- [ ] The production `APP_KEY` is stored in the secret manager and a separately
  controlled recovery copy exists.
- [ ] Two-factor authentication is enabled and enforced.
- [ ] Google Maps and other browser-facing keys are restricted to the production
  domains.
- [ ] No local-development credentials or fictional demo data are present.

## Data, media and recovery

- [ ] The V1/live media configuration has been reconciled with the final merged
  application without changing existing object keys or public media behavior.
- [ ] Competition, current concert and legacy concert storage pass read checks.
- [ ] Public uploads and private operational files use durable backed-up storage.
- [ ] Object-storage versioning and backup/replication are active.
- [ ] A verified off-server database backup is created at least daily.
- [ ] An isolated restore of database, object storage and the matching `APP_KEY`
  has succeeded using [Sensitive Data Retention and Key Recovery](Sensitive-Data-Retention-and-Key-Recovery.md).

## Mail, monitoring and alerts

- [ ] Transactional mail sends from the production domain and passes SPF, DKIM
  and DMARC checks appropriate to the selected provider.
- [ ] Uptime, application errors, queues, scheduler, backups, storage capacity
  and TLS expiry are monitored as specified in
  [Production Monitoring](Production-Monitoring.md).
- [ ] Test failure and recovery alerts have reached the recorded owner.
- [ ] Production logs have an approved retention period and do not contain
  credentials or sensitive form values.

## Final technical verification

Run these commands on the production host through the standard deployment
process, not against the existing live application from a development machine:

```bash
php artisan production:validate
php artisan production:check-dependencies
php artisan migrate:status
php artisan schedule:list
```

- [ ] The standard deployment dry run completes against the intended branch and
  remote.
- [ ] The complete automated test and browser test suites pass for the release.
- [ ] All four production commands above complete successfully.
- [ ] The deployment creates and verifies its pre-migration database backup.
- [ ] The HTTPS `/up` endpoint returns status 200 and exactly `{"status":"up"}`.
- [ ] Admin and non-admin crew access boundaries pass a production smoke test.
- [ ] Booking, studio/competition contacts, email delivery, uploads and internal
  documents pass a production smoke test.
- [ ] Existing competition downloads and concert playback pass desktop and
  mobile playback, seeking and expiry tests.
- [ ] A rollback owner, rollback tag and database-recovery decision are recorded.

## Go/no-go record

The private deployment record should contain only operational metadata:

- Release commit and deployment timestamp
- Approver and operator
- Provider/dashboard references
- Backup and restore-test identifiers
- Alert-test date
- Smoke-test result
- Known accepted risks and follow-up owner

Any unchecked required item is a no-go unless the owner and responsible
developer explicitly record the risk and a time-bounded remediation plan.
