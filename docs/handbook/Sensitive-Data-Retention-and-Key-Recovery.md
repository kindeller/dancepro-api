# Sensitive Data Retention and Key Recovery

This document defines the production baseline for retaining personal data and
recovering Laravel-encrypted records. It is an operational policy, not legal
advice. The owner and accountant must confirm the final statutory periods and
any insurance or contractual requirements before automated deletion is enabled.

## Principles

- Collect and retain only information needed for operations or a legal duty.
- Restrict sensitive crew data to authorised administrators.
- Review retained data at least annually and when a crew relationship ends.
- A legal hold, active dispute, incident, investigation or other documented
  obligation pauses normal deletion for the affected records only.
- Soft deletion is not erasure. Final disposal must remove or irreversibly
  de-identify database records and stored files, then allow protected backups
  to expire under their normal retention policy.
- Never place personal data, encryption keys or credentials in application logs.

## Retention Schedule

The following is the production baseline pending owner/accountant confirmation:

| Data | Baseline retention | Disposal action |
|---|---|---|
| Payroll, timesheets, payment and employment records | 7 years from the record or relevant transaction | Permanently delete after the period and any legal hold |
| Superannuation contribution and choice records | 7 years, using the longer payroll baseline | Permanently delete after the period and any legal hold |
| Contracts, invoices, approvals and material audit history | 7 years after the relationship or transaction ends | Permanently delete after the period and any legal hold |
| Crew medical, dietary and emergency-contact details | While actively required, then no later than 90 days after the crew relationship ends unless a documented exception applies | Remove from the profile and all operational exports |
| Driver licence, Working With Children and first-aid details | While required for an active role, then no later than 90 days after the relationship ends unless a statutory, insurance or incident requirement applies | Remove values and associated files |
| Crew profile photograph and ordinary contact details | While the account or working relationship is active, then review at offboarding and remove within 90 days unless required for an outstanding record | Delete stored file and clear profile values |
| Studio and competition contacts and booking snapshots | While the relationship is active, then review after 2 years without a booking or contact | Delete or de-identify when no longer needed |
| Authentication sessions, API tokens and recovery credentials | Revoke immediately when access ends; retain no plaintext recovery codes | Revoke/delete and invalidate active sessions |
| Download-access records | 180 days by default | Daily scheduled pruning using `DOWNLOAD_ACCESS_RETENTION_DAYS` |
| Application logs | 90 days maximum unless needed for an active security investigation | Expire automatically; redact personal data at collection |
| Database and object-storage backups | 30 days unless the production backup policy records a different approved period | Expire automatically; never selectively alter immutable backups |

Before enabling a pruning job for a new category, record the approved period,
scope, owner and legal-hold behaviour. Test the job in report-only mode and
verify an authorised restore before its first destructive run.

## Offboarding Review

When crew access ends, an administrator must:

1. Deactivate the account and revoke sessions and API tokens immediately.
2. Identify outstanding shifts, payroll, invoices, incidents and legal holds.
3. Export only the records that must be retained and keep their access limited.
4. Schedule short-lived profile data and files for deletion within 90 days.
5. Record the review date, reviewer and any exception without copying sensitive
   values into the audit note.

Studio and competition contacts receive a similar annual inactivity review.

## Laravel Encryption Key Custody

`APP_KEY` encrypts sensitive model attributes and two-factor authentication
secrets. A database backup without the matching key cannot recover those
values. Treat the key as production infrastructure, not as application code.

- Keep the current `APP_KEY` in the production secret manager only.
- Keep a second encrypted recovery copy in a separate administrative account or
  offline vault. Do not store it beside the database backup or in Git.
- Limit access to the owner and one designated recovery role. Audit all reads
  and changes through the chosen secret-management system.
- Back up the key whenever it changes and label it with the deployment date and
  environment, without placing the key value in tickets or documentation.
- Never run `key:generate` against an existing production database.

## Recovery Test

At launch and quarterly thereafter, an authorised operator must perform a
non-production restore of the database and required object storage, supply the
matching recovery key at runtime, and verify that a designated encrypted test
record can be decrypted. Record only the date, operator, backup identifiers and
success/failure; do not record decrypted values.

If the key is unavailable or the restore cannot decrypt the test record, treat
the backup as failed and escalate under the production incident process.

## Key Rotation

Laravel supports old keys through the comma-separated `APP_PREVIOUS_KEYS`
setting. Rotation must occur in a maintenance window using this sequence:

1. Create and verify a fresh database and object-storage backup.
2. Confirm both the existing and replacement keys are secured in the recovery
   vault without displaying either in logs or command output.
3. Set the replacement as `APP_KEY` and retain the former key in
   `APP_PREVIOUS_KEYS` so existing ciphertext remains readable.
4. Clear and rebuild the production configuration cache.
5. Verify login, two-factor authentication and decryption of designated test
   records before reopening the application.
6. Re-encrypt existing encrypted attributes using a reviewed, tested migration
   command before retiring the former key. Do not use an ad-hoc database edit.
7. Remove the former key only after all applicable records have been rewritten,
   validation reports no dependency on it, and backups requiring it have aged
   out or the key remains available solely in the recovery vault.

An exposed or suspected-compromised key is a security incident. Preserve the
evidence needed for investigation, rotate promptly, review affected data and
complete any required notification assessment.

## Production Activation Checklist

- Owner/accountant has approved or amended every retention period.
- A named person owns annual retention and offboarding reviews.
- Legal-hold decisions and exceptions have a restricted audit location.
- Production secret manager and separate recovery vault are identified.
- `APP_KEY` recovery access is limited and audited.
- Launch restore/decryption test has succeeded.
- Scheduler pruning for download-access records is monitored.
- Any additional automated deletion is separately reviewed and tested.
