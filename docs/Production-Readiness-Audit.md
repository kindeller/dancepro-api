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
| 8 | High | API tokens receive unrestricted abilities | Resolved | Explicit account, competition-object and download-link abilities replace wildcard tokens and are enforced alongside database permissions; 209 tests / 1,699 assertions; `93dc93f` |
| 9 | High | Production environment defaults and validation are unsafe | Resolved | Deployment-blocking configuration validation and documentation; existing media remains unchanged; `93dc93f` |
| 10 | High | Storage failures can be silent | Resolved | All application disks throw and report failures; browser and API writes receive explicit retryable errors; `6b3c0aa` |
| 11 | High | Deployments have no automated database backup | Resolved | Verified private database backup, checksum manifest, bounded retention and restoration guidance; `210a0af` |
| 12 | High | Deployment health check is too permissive | Resolved | Strict production dependency checks and exact HTTPS `/up` response validation; `627070a` |
| 13 | High | Automated test suite was red due to copy drift | Resolved | “My Profile” and “Clocked out” interface copy matches its assertions; full suite green at 220 tests / 1,742 assertions. No application change was required; tracked in `edc4a2f`. |
| 14 | High | Public forms lack sufficient abuse protection | Resolved | Separate booking/download limits with hashed alert context; booking honeypot and 10-minute duplicate suppression; scheduled 180-day access-log retention; 225 tests / 1,797 assertions; `edc4a2f`. |
| 15 | High | Uploaded files rely on local server storage | Resolved | Public uploads and private operational files are configurable, production requires durable shared disks, existing public uploads have a non-destructive migration command, object-backup requirements are documented, and 227 tests / 1,814 assertions pass; `464afe4`. |
| 16 | Usability | Mobile admin navigation is cumbersome | Resolved - pending commit approval | At mobile widths the sidebar becomes a sticky compact header with an accessible menu toggle and scrollable overlay navigation; desktop navigation is unchanged; 228 tests / 1,821 assertions. |
| 17 | Usability | Crew navigation is cramped on small phones | Resolved - pending verification and commit approval | At phone widths My Hub now uses a compact, accessible menu toggle with a scrollable navigation panel; profile and admin access remain visible in the header, and desktop navigation is unchanged. |
| 18 | Maintainability | Concert logos are matched using event names | Pending | |
| 19 | Usability | Email copying has no browser fallback | Pending | |
| 20 | Maintainability | Visual styles are duplicated across Blade layouts | Pending | |
| 21 | Operations | Production monitoring strategy is incomplete | Pending | |
| 22 | Privacy | Sensitive-data retention and key-recovery lifecycle is undocumented | Pending | |

## Working rule

Resolve items in numerical order unless the owner explicitly reprioritises them. A finding is only marked resolved after applicable migrations, formatting, tests, and route checks pass. Commits require explicit owner approval.
