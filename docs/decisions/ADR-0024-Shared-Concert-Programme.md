# ADR-0024: Shared concert programme and crew notifications

Status: implemented for concerts, 22 September 2026.

The first successful concert sync establishes the shared working programme. A
second laptop must use that plan rather than silently create another one. The
existing Programme button remains; a nearby Confirmed tag means its local structure
matches the last successful online check. Update available means it needs to load
the shared version. Failed checks show Check needed, never a fresh confirmation.

Programme provides Check online, Load shared programme and Confirm Programme.
Confirming can happen before any photos are uploaded. Existing Upload also confirms
on its first sync. Subsequent explicit Share Changes requests must name the currently
shared plan as their base; concurrent or stale edits receive 409. An accepted change
updates the shared pointer atomically under the same event/programme locks as media
registration. Identical retries reuse the plan and do not repeat notifications.

Once contributions have been registered for the shared plan, structural changes
are blocked pending administrator reconciliation. This retains the existing media
ownership and completion guarantees. Superseded concert plans cannot register
contributions; server progress marks them as needing attention. Existing installations
prefer their gallery plan, otherwise their earliest synced plan, until an explicit
shared pointer is established. No existing photo or plan is migrated or deleted.

Downloading validates server, event, programme, revision and folder structure.
Applying uses the existing journalled concert-folder operation and combination
support, retaining photo identities and Top Pics. Additional local item identities
that are absent online block application with a review message. There is no silent
local-data discard or automatic merge of independently added items. Uploaded folders
retain their existing edit locks. Checks run every 30 seconds while the event view
is open, and on demand; downloading/applying requires an explicit click.

Each accepted batch creates one programme_update event-chat message through the
existing event-message/crew-notification service, attributed to its uploader. It
summarises changed names, additions, combinations and numbering (up to 20 details).
The browser event chat polls programme notifications every five seconds while
visible, retaining the draft composer and existing conversation. The existing crew
mobile chat API includes these normal event records; no mobile push transport is
introduced. No messages are sent for unsynced edits or rejected changes.

Competition folders are a separate next step: additions from capture apps must be
allowed; show Folder differences to check across P1/P2/V without automatic local
renames or blocking normal uploads. Do not reuse concert programme confirmation for
competition folder differences. This decision is recorded; competition ingestion
and comparison are not implemented by this change.

The nullable shared-plan foreign key can be rolled back without deleting plans,
messages, media or contributions. A rollback loses the explicit shared pointer;
the legacy fallback still prefers the gallery or first synced plan.

Follow-up: competition discovery and comparisons were implemented in ADR-0025.
