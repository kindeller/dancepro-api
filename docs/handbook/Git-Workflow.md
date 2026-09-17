# Git Workflow

## Purpose

Define how contributors and AI agents make, review, and integrate changes
without putting the protected branch or unrelated work at risk.

## Protected Branch

The canonical integration branch is `master`. Direct pushes to `master` are
reserved for the repository owner. Everyone else must use a working branch and
open a Pull Request (PR), including for documentation and maintenance changes.

The repository owner must review and approve every PR before it is merged.
Contributors must not approve or merge their own PRs, bypass branch protection,
or ask an AI agent to do so.

## Working Branches

Start all new work from the latest canonical branch. Do not develop major work
directly on `master`.

Branch names must follow:

```text
<developer-id>-<work-description>
```

Use a short, recognisable developer ID and a lowercase, hyphen-separated work
description. Keep the name readable and specific.

Examples:

```text
alex-competition-download-audit
sam-fix-login-validation
```

Before starting work:

```bash
git switch master
git pull --ff-only origin master
git switch -c <developer-id>-<work-description>
```

Never reuse another contributor's branch without coordinating with them.

## Safe Collaboration

- Keep each branch and PR focused on one coherent change.
- Commit only files that belong to the current task.
- Do not discard, overwrite, reformat, or commit another contributor's
  unrelated changes.
- Pull with `--ff-only` on the canonical branch so divergent history is not
  silently merged.
- Never force-push a shared branch. If rewriting the history of your own
  unreviewed branch is genuinely necessary, coordinate first and use
  `--force-with-lease`, never `--force`.
- Never commit secrets, credentials, `.env` files, private keys, production
  data, or generated dependency directories.
- Do not change branch protection, repository settings, CI/CD, or deployment
  configuration without the repository owner's explicit approval.

## Pull Requests

Push the working branch and open a PR into the canonical branch. The PR must:

- Explain what changed and why.
- Stay within the stated scope; unrelated work belongs in another branch/PR.
- Identify migrations, configuration changes, security implications, and
  deployment considerations.
- Include the commands run and their results, or the exact reason a required
  check could not be run.
- Link the relevant issue, specification, or epic when one exists.
- Be ready for review: resolve known failures and review the diff for secrets,
  debug code, accidental files, and unrelated changes.

Address review feedback on the same working branch. Only the repository owner
may approve the PR for merge. Delete the working branch after merge when it is
no longer needed.

## Definition of Done

A task is only complete when all applicable items are finished.

### Code

- Feature implemented.
- Existing architecture followed.
- Controllers remain thin.
- Business logic remains in Actions or Services.
- No unnecessary technical debt introduced.

### Documentation

- Relevant docs updated.
- Epic, specification, or handbook files updated where affected.
- ADR added only if an architectural decision was made.

### Database

If migrations were added:

- Migrations run successfully.
- Migration rollback/down behaviour reviewed where applicable.

### Testing

Run all applicable checks:

```bash
sail artisan test
```

If routes changed, also run:

```bash
sail artisan route:list
```

If formatting changed, also run:

```bash
sail pint
```

If a required check cannot be run because of the local environment, record the
exact command and reason in the work summary.

## AI-Agent Approval Gates

AI agents must follow all contributor rules above. They must not create or
switch branches, commit, push, open or edit PRs, merge, or change repository
settings unless the user explicitly authorises that action. Approval for one
action does not imply approval for later actions.

After the Definition of Done is satisfied, AI-assisted work must not be
committed until the user explicitly approves the exact commit.

Before asking for commit approval:

- Run all applicable verification commands.
- Review `git status`.
- Summarise changed files and verification results for the user.

Before committing:

- Stage only files changed for the current task.
- Do not stage or revert unrelated user changes.
- Use a concise, descriptive commit message.
- Do not create a commit if required verification failed, unless the user
  explicitly asks for a commit and the failure is documented.

Before requesting permission to push or open a PR, report the branch, commits,
verification results, and intended remote/target branch. Never merge a PR on
the user's behalf unless the repository owner explicitly requests that exact
merge after reviewing it.

Do not add CI/CD, GitHub Actions, or deployment automation unless explicitly
requested.

## Related Documentation

- [Development Environment](Development-Environment.md)
- [Testing](Testing.md)
- [Architecture](Architecture.md)
