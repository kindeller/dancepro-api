# Customer access and mobile API foundation

## Completed locally

- Concert video code groups can be created for one studio/year, assigned to
  concerts, released once, extended with later concerts and have their code
  replaced. Hashes remain server-side and changes are audited without code values.
  Release starts six-month streaming and
  twelve-month download periods; later additions do not restart them. Existing
  per-concert passwords and grants continue to work.
- Customer signup records an explicit acknowledgement of the published Terms
  and Privacy Policy and sends a signed verification link. Existing web customers
  without current acknowledgement are prompted. Media pages require verification
  and current acknowledgement for customer accounts.
- `/api/v1/customer` provides customer-only mobile tokens, onboarding state,
  profile, My Media, event codes, ready competition galleries and previews, and
  concert playback/download entry points for one iPhone/Android contract.

## Validation and remaining work

The feature and regression suite covers group release/expiry, later concerts,
code rotation and audit history, account verification, policy acknowledgement,
token scope and revocation, and web/mobile media access. The full Sail suite
passes (593 tests, 5,113 assertions); `sail artisan route:list` reports 406
routes; Pint and `git diff --check` pass. The three new migrations ran locally.
Their down methods remove only the new group, audit and policy-version schema;
no existing concert passwords, grants or accounts are deleted by the forward
migrations.

Before production, review the current policy content/version and email delivery
configuration, create real code groups, and test their release dates with actual
concert availability. Next build both phone clients against the shared API and
add guest access, reactivation checkout, reminders and device-local saving in the
later plan stages.
