# ADR-0008: Single Snapshot Deployment

- Status: Accepted for local implementation; production validation pending
- Date: 2026-10-18
- Supersedes: ADR-0007

## Context

DancePro deploys manually to one EC2 instance. The permission incident came from
Git/Composer files unreadable by Apache and deployment-user Artisan outputs that
Apache could not read. A proposed installed support bundle, runtime helper,
protocol versions, and host-process validation added management overhead beyond
the immediate problem. That proposal was not installed on production.

## Decision

Use one version-controlled Bash script, `scripts/dancepro-deploy.sh`. It copies
itself to a private temporary file and executes that snapshot before Git changes.
There is no separate launcher, installed support bundle, or protocol handoff.

Keep approved full-SHA/fast-forward validation, locking, the verified S3 backup,
maintenance, migrations, and origin checks. Normalize deployment-owned tracked
code/dependencies and generated leftovers only. Use Apache's existing group for
code read access and run all deployment Artisan commands as Apache. Secrets,
private uploads, and Git metadata stay outside permission normalization.

Use only scoped sudo for Apache's Artisan command and the exact root FPM reload.
Do not mandate service-mask changes, scan processes, or add diagnostic endpoints.
Compile views/bootstrap and check FPM health during maintenance; check actual
home/login/API responses immediately after reopening, restoring maintenance on
failure. No automatic database restoration or migration rollback is introduced.

## Consequences

- One script and a small one-time ownership/group/sudo setup are maintained.
- Checkout cannot replace the running script. Deployment-script changes take
  effect on the next invocation, or immediately when an operator explicitly
  extracts/reviews the target script before running it.
- Full page checks after `up` leave a short exposure window for route failures;
  zero-exposure switching remains a separate project.
- SSH and future SSM callers use the same command. GitHub stays manual-only;
  SSM and GitHub delivery remain unverified until separate staged testing.
