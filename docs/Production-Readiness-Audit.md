# Production Readiness Audit

This checklist tracks the 22 findings from the pre-publication audit. Update the status, verification, and commit reference as each item is resolved.

| # | Priority | Finding | Status | Verification / commit |
|---:|---|---|---|---|
| 1 | Critical | Application could not be deployed from Git because required work was uncommitted | Resolved | `d05b005` |
| 2 | Critical | Imported studio/competition data and logos lacked a production transfer process | Resolved | `8900cb8` |
| 3 | Critical | Active non-admin users could access competition objects and download-link management | Resolved | 193 tests / 1,613 assertions; `4575f6f` |
| 4 | Critical | Crew documents and operational files are publicly accessible | Resolved | 44 files moved and verified; 200 tests / 1,638 assertions; `0adab3e` |
| 5 | Critical | Frontend production build is missing from deployment | Resolved | Locked install, offline-safe build, version preflight and manifest verification added; `0adab3e` |
| 6 | Critical | CloudFront download signature appears malformed | Resolved | AWS SDK canned-policy signer with cryptographic regression coverage; concert media signing unchanged; 201 tests / 1,650 assertions |
| 7 | High | Login and password-reset endpoints have no rate limiting | Resolved | Separate hashed email/IP and IP-wide limits cover browser/API login, reset-link requests and reset submissions; 206 tests / 1,694 assertions |
| 8 | High | API tokens receive unrestricted abilities | Resolved | Explicit account, competition-object and download-link abilities replace wildcard tokens and are enforced alongside database permissions; 209 tests / 1,699 assertions; commit pending approval |
| 9 | High | Production environment defaults and validation are unsafe | Resolved - pending commit approval | Added a deployment-blocking `production:validate` command, secure environment documentation, explicit cookie and download signer variables, and deployment-script enforcement. Existing media configuration remains unchanged; signer completeness is checked only when configured. |
| 10 | High | Storage failures can be silent | Pending | |
| 11 | High | Deployments have no automated database backup | Pending | |
| 12 | High | Deployment health check is too permissive | Pending | |
| 13 | High | Automated test suite was red due to copy drift | Pending recheck | Current suite passed while resolving item 3 |
| 14 | High | Public forms lack sufficient abuse protection | Pending | |
| 15 | High | Uploaded files rely on local server storage | Pending | |
| 16 | Usability | Mobile admin navigation is cumbersome | Pending | |
| 17 | Usability | Crew navigation is cramped on small phones | Pending | |
| 18 | Maintainability | Concert logos are matched using event names | Pending | |
| 19 | Usability | Email copying has no browser fallback | Pending | |
| 20 | Maintainability | Visual styles are duplicated across Blade layouts | Pending | |
| 21 | Operations | Production monitoring strategy is incomplete | Pending | |
| 22 | Privacy | Sensitive-data retention and key-recovery lifecycle is undocumented | Pending | |

## Working rule

Resolve items in numerical order unless the owner explicitly reprioritises them. A finding is only marked resolved after applicable migrations, formatting, tests, and route checks pass. Commits require explicit owner approval.
