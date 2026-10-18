# ADR-0007: Commit-Pinned Two-Stage Production Deployment

- Status: Accepted for staged production validation
- Date: 2026-10-18

## Context

The production SSM command previously invoked a symlink into the Git checkout
that the deployment updated. The script fetched code itself, used interactive
confirmations, and relied on a manual database backup. It could not provide an
unattended, commit-consistent status to GitHub Actions.

## Decision

Use two stages:

1. An independently installed launcher validates the repository, serialises
   deployments, fetches `master`, fast-forwards to a validated full SHA, and
   executes the deployment script from that commit.
2. The version-controlled application script performs environment checks,
   verified backup, maintenance, dependencies, migrations, caches, service
   handling, and health validation without prompts.

GitHub passes its exact SHA through a restricted SSM parameter using environment
interpolation and a hexadecimal `allowedPattern`. Automation remains manual-only
until production validation finishes.

## Consequences

- Deployment logic evolves through pull requests without changing the SSM entry
  point.
- A run deploys its approved SHA, not an incidental later branch tip.
- Dirty or divergent checkouts fail closed, and concurrent runs are rejected.
- Every live attempt is gated by a verified S3 logical backup.
- The launcher and SSM document have a separate controlled update lifecycle.
- Deployments remain in-place; immutable releases remain a possible future ADR.
- Rollback stays operator-reviewed because code, migrations, and customer writes
  cannot safely be reversed as one automatic action.
