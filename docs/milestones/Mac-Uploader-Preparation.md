# Mac uploader: event preparation and persistent offline state

Date: 2026-09-21

## Owner direction

There is no existing Mac uploader to reuse. Build it as a standalone Mac app.
Prepare/download belongs in the app, without a separate browser login on each
computer. Existing API login and Keychain use were explicitly approved as an
exception to the unavailable AWS secrets skill; no AWS secrets are accessed.

## Implemented

- Native SwiftUI app with server selection, administrator login, optional 2FA,
  Keychain session persistence and logout. Existing token lifetimes retained.
- Approved-event discovery with search, dates, pagination, cancellation exclusion,
  readiness and latest-revision metadata; explicit programme ability required.
- Snapshot preview, native destination picker, pinned PDF download, staged folder
  creation and saved JSON manifest. Concert numbering/title rules preserved.
- Repeat preparation preserves media; different revisions stay separate. Unsafe
  paths, duplicate identities and symlinks are rejected.
- Prepared-event catalogue survives restart. Saved titles, PDF and folders can
  be opened offline, independently of sign-in.
- Local build script creates a double-clickable, ad-hoc signed .app.

See [app README](../../apps/mac-uploader/README.md) for operation and limitations;
[ADR-0013](../decisions/ADR-0013-Native-Mac-Uploader.md) records the platform choice.

## Verification

- Full Sail suite: 477 tests, 3,969 assertions passed.
- API routes listed; targeted Pint and `git diff --check` passed.
- 10 native preparation and API contract tests passed.
- Release app built, locally signed and launched. Native UI sign-in with the
  existing local demonstration account succeeded and the approved-event list
  loaded. Sign-in was retained after restarting. Its legacy approval correctly
  shows that reapproval is needed.
- No existing event was reapproved by the agent. No production deployment,
  branch, commit or push was performed.

## Next

First user check: reapprove the existing demo programme in the portal, refresh
in the Mac app, and use Prepare Event with a chosen destination. This deliberate
approval is needed because its older immutable revision lacks folder fields.
Stage 4.2 is now implemented in [Photo review](Mac-Uploader-Photo-Review.md). Before crew
rollout, define event/role access and prepare a signed/notarized distribution.
Actual photo/video upload is not implemented in this slice.

## Layout reference supplied after the first build

The owner supplied the [four-screen uploader draft](../specifications/Mac-Uploader-Layout-Reference.md).
Use its event screen, left folder tree, large media preview, Top Pics, compact
controls and bottom upload bar for the next native screens. The initial
preparation UI is not the final visual design.
