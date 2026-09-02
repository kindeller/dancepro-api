# Production Monitoring

## Status

The application exposes a strict `/up` health endpoint, writes structured
Laravel logs through the configured production channel, validates its critical
dependencies during deployment, and creates verified database backups.

The hosting provider and alerting service have not yet been selected. External
monitors must therefore be connected during deployment; they cannot truthfully
be marked active in this repository.

## Required monitors

Configure these checks before public launch:

| Signal | Check | Alert threshold |
| --- | --- | --- |
| Public availability | HTTPS `GET /up`; require status 200 and exact body `{"status":"up"}` | Two consecutive failures, checked every 1-5 minutes |
| Application errors | Production log events at `error` or higher | Any event; group repeats to prevent alert floods |
| Queue health | Failed jobs and oldest queued-job age | Any failed job or oldest job over 10 minutes |
| Scheduler health | Laravel scheduler heartbeat | No successful heartbeat for 10 minutes |
| Database backups | Successful verified off-server backup | No successful backup within 26 hours |
| Storage capacity | Database, logs, temporary storage and upload storage | Warn at 75%; urgent at 90% |
| TLS certificate | Public certificate expiry | Warn at 30 days; urgent at 7 days |

The uptime check must run outside the production host and must not follow
redirects. Monitoring credentials and webhook URLs belong in the deployment
platform's secret store, never in Git.

## Alert ownership

David Mueller is the initial service owner. Before launch, record a monitored
email address or phone destination in the provider and send a test alert. If a
developer or managed host will provide after-hours support, document that
escalation contact in the private operations records rather than this public
repository.

## Response priorities

- **Urgent:** site unavailable, suspected data exposure, database unavailable,
  or backups missing. Acknowledge immediately and place the site in maintenance
  mode if continued operation could lose or expose data.
- **High:** repeated application exceptions, failed queue work, mail failure or
  upload failure. Investigate the same business day.
- **Routine:** capacity warnings, expiring certificates and isolated recoverable
  errors. Schedule remediation before the warning becomes urgent.

For every incident, record the start time, customer impact, relevant log/event
identifiers, actions taken and resolution. Do not copy passwords, access tokens,
personal contact details or signing keys into tickets or alerts.

## Launch verification

Before DNS cutover:

1. Run `sail artisan production:validate` and
   `sail artisan production:check-dependencies` in the deployment environment.
2. Confirm production logs reach the selected provider without sensitive
   request fields.
3. Trigger and receive a test application-error alert.
4. Temporarily point the uptime monitor at a known failing endpoint and confirm
   an alert, then restore `/up` and confirm recovery.
5. Confirm the scheduler and queue workers are supervised and their monitors
   are healthy.
6. Confirm the newest off-server database backup passes
   `database:backup-verify`.
7. Record the provider, dashboard location, alert recipient and test date in the
   private deployment record.

Repeat alert-path testing quarterly and after changing the hosting, logging,
queue or backup provider.
