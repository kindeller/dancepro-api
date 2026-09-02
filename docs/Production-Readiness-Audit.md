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
| 16 | Usability | Mobile admin navigation is cumbersome | Resolved | At mobile widths the sidebar becomes a sticky compact header with an accessible menu toggle and scrollable overlay navigation; desktop navigation is unchanged; 228 tests / 1,821 assertions; `82ff3af`. |
| 17 | Usability | Crew navigation is cramped on small phones | Deferred | The compact menu attempt in `82ff3af` was unusable and has been removed. My Hub retains its original wrapping navigation because mobile use is expected primarily through the app; the website remains a fallback. |
| 18 | Maintainability | Concert logos are matched using event names | Resolved | Scheduling events now retain a nullable studio relationship, booking approval records the reviewed studio, existing unambiguous concert-name matches are backfilled, and My Hub resolves logos through the relationship with the event logo as fallback; 229 tests / 1,824 assertions; `3c54959`. |
| 19 | Usability | Email copying has no browser fallback | Resolved | Studio and competition email copying uses the modern Clipboard API when available and a shared hidden-field copy fallback when access is unavailable or rejected; 230 tests / 1,827 assertions; `17ec7d8`. |
| 20 | Maintainability | Visual styles are duplicated across Blade layouts | Resolved | The universal box-sizing and hidden-element rules are shared by the admin, My Hub and public media layouts; their intentionally distinct visual systems remain isolated to avoid cross-section regressions; 230 tests / 1,829 assertions; `875cb61`. |
| 21 | Operations | Production monitoring strategy is incomplete | Prepared - deployment activation required | Provider-neutral signals, thresholds, ownership, incident priorities and launch/quarterly alert tests are documented. External uptime, log, queue, scheduler, backup, capacity and TLS monitors must be connected and test alerts received after hosting is selected; `2962136`. |
| 22 | Privacy | Sensitive-data retention and key-recovery lifecycle is undocumented | Prepared - owner confirmation and deployment activation required | Data categories, baseline periods, offboarding, legal holds, secure disposal, key custody, quarterly recovery testing and safe rotation using `APP_PREVIOUS_KEYS` are documented. The owner/accountant must approve the final periods and the production secret manager/recovery vault must be connected before launch. |

## Working rule

Resolve items in numerical order unless the owner explicitly reprioritises them. A finding is only marked resolved after applicable migrations, formatting, tests, and route checks pass. Commits require explicit owner approval.

## Second audit

This follow-up checklist records the findings from the September 2026 full
application re-audit. It uses its own fixed numbering and is resolved in order.

| # | Priority | Finding | Status | Verification / commit |
|---:|---|---|---|---|
| 1 | Critical | API authentication bypasses enforced two-factor authentication | Resolved | API login now requires a valid TOTP or one-time recovery code for configured accounts and refuses enforced-but-unconfigured accounts before issuing a token; 234 tests / 1,842 assertions and Pint pass; `735da0c`. |
| 2 | High | API tokens do not expire by default | Resolved | New API tokens have an explicit seven-day expiry by default, the API reports `expires_at`, expired tokens are rejected, and production validation permits only 1-43,200 minutes; 235 tests / 1,846 assertions and Pint pass; `beb4f38`. |
| 3 | High | A stale Vite `public/hot` file can override a production build | Resolved | Production validation now fails while the Vite hot marker exists, the standard deploy already removes it, and the manual deployment procedure now does so explicitly; 236 tests / 1,849 assertions and Pint pass; `61e5ff6`. |
| 4 | High | Production dependency checks omit media storage and signing paths | Resolved | The dependency command now probes list/read access for competition, current concert and legacy concert storage without modifying media, validates concert signing and validates download signing when configured; 237 tests / 1,853 assertions and Pint pass; `f75a816`. |
| 5 | High | Database backups are not scheduled by the application | Resolved | The existing verified backup command now runs daily at a configurable local time when enabled, with pruning, overlap prevention and a single-server lock; schedule listing, 238 tests / 1,858 assertions and Pint pass; `167f677`. |
| 6 | Medium | Public concert media endpoints have no request throttling | Resolved - pending commit approval | Playback lookup, streaming and download routes now have separately configurable per-asset/IP and broader per-IP limits, with regression coverage for all three routes; route listing, 239 tests / 1,864 assertions and Pint pass. |
| 7 | Medium | Replacing private operational files leaves obsolete objects behind | Pending | |
| 8 | Medium | Standard HTTP security headers are not applied | Pending | |
| 9 | Medium | The concert-player production bundle exceeds the build warning threshold | Pending | |
| 10 | Medium | JavaScript-heavy workflows lack browser-level regression coverage | Pending | |
