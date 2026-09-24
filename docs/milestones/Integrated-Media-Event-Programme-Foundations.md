# Integrated media: event and programme foundations

## Status

Implemented: manual programme draft/review/confirmation foundation. Initial automatic
extraction is now implemented in the [next milestone](Programme-Automatic-Extraction.md);
the Mac uploader remains subsequent work. See
[API contract](../api/Programme-Preparation.md) and
[ADR-0011](../decisions/ADR-0011-Programme-Drafts-and-Confirmed-Snapshots.md).
Sample findings remain in [Programme preparation](../specifications/Programme-Preparation.md).

## Outcome

An authorised administrator can prepare a programme linked to existing event
records. Each section and routine keeps its identity when its display name or
position changes. A later uploader can fetch a confirmed, versioned structure
without interpreting a filename as ownership.

## Scope and sequence

1. Reuse the existing scheduling event and its dated shifts for a multi-day
   competition. Confirm any additional media profile relationship is actually
   needed before adding it; record architectural decisions where applicable. Preserve booking-item-to-event/concert links and shared directories.
2. Reuse the existing event code without changing its calculation in this slice.
   Owner clarification: dates are unlikely to change and changes would normally
   stay within the month. Stored-code changes are not a prerequisite. Continue
   to use permanent internal identities for media relationships; revisit explicit
   code overrides only when an actual exception needs them.
3. Add programme draft/confirmed revisions with stable public identities for
   days, sections and routines; retain authoritative competition numbers and gaps.
   Separate display order from identity. Define how stale edits are rejected.
4. Add a small staff-only manual programme preparation flow and API contract
   using existing feature/policy/Form Request conventions. Reuse protected PDF
   attachments as references. Confirmation records actor/time; customer-visible
   data excludes internal notes.
5. Expose a read-only confirmed preparation snapshot for an authorised uploader,
   with event UUID/code, revision and structure. Preparing folders must not require
   a crew role selection. Preserve existing mobile and concert API contracts.
6. Test the identities, access boundaries, revision behaviour and regressions;
   document the API, migration/rollback behaviour and next uploader dependency.

The implemented endpoint and payload contract is documented in
[Programme Preparation API](../api/Programme-Preparation.md).

## Acceptance criteria

- [x] Existing event, studio, contact and crew information is reused.
- [x] Existing event-code calculation and API/display behaviour are preserved.
- [x] Multi-day competition selection is unambiguous and events cannot mix.
- [x] Competition withdrawals retain numbering gaps; an added competitor may
      have a generic label without changing existing routine identities.
- [x] Renaming/reordering preserves node UUIDs and prior snapshots. Media ownership
      integration is a later slice; existing media is not reassigned here.
- [x] Draft changes do not silently replace an already confirmed preparation
      snapshot; stale edits have an explicit conflict/retry outcome.
- [x] Unauthorised users cannot edit or fetch private preparation data.
- [x] Confirmation actor/time and revision history are retained.
- [x] Snapshot preparation works without choosing P1/P2 in advance.
- [x] Existing concert approval/access, programme copying and ingest behaviour
      retain regression coverage.
- [x] Migrations run and rollback behaviour is reviewed through Sail.
- [x] `sail artisan test`, `sail artisan route:list` and `sail pint` pass for the
      implementation, or exact environmental blockers are recorded.

## Deferred to subsequent slices

Programme PDF extraction/AI, uploader UI/local persistence, full gallery hierarchy,
contributor completion and waivers, split/combine semantics for uploaded concert
items, HOLD rules, group access codes, customer accounts, entitlements/payments,
and Tracker. These remain in the roadmap; they are not prerequisites for this
small preparation contract. No automatic release follows programme confirmation.

## Verification and next action

- New workflow feature tests cover drafts, stale writes, immutable snapshots,
  numbering, identities, private sources, token scope and cancellation.
- Browser verification on a fictional local concert covered bulk paste, save,
  reload, edit after save and confirmation. The extension blocked local file
  selection; PDF upload/source retention is covered by HTTP feature tests.
- Migration up/down/up succeeded on the new empty local tables in Colima.
- Final verification: `sail artisan test` passed 452 tests (3,835 assertions),
  including 17 programme feature tests. `sail artisan route:list`, `sail pint
  --test` for changed PHP files, `sail npm run build` and `git diff --check` passed.
- Existing image attachments remain untouched and do not become PDF sources.
- The fictional browser-check event and its preparation records were removed.

Next: benchmark layout-aware PDF extraction into this draft structure, beginning
with competition layouts and title/class concert lists. Ordinary crew uploader
permissions, source reconciliation, ballet scene interpretation and actual offline
folder preparation remain pending. Event-code behaviour remains unchanged.
