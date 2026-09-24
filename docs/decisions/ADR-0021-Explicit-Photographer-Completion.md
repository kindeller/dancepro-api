# ADR-0021: Explicit photographer completion

Status: implemented local foundation, 2026-09-22. Crew-bound identity and
roster-personnel completion invalidation are superseded in agreed direction by
[ADR-0022](ADR-0022-Event-Role-Uploader-Identity.md); the local implementation migration is complete.
Verification, explicit completion and private-gallery safeguards remain applicable.

A verified file receipt is not a photographer completion declaration. Add a manual
Finished Uploading Event action for the selected crew member and photographer role.
The server derives active items from the immutable plan, restricted to their
published shift dates for competition programmes. It reuses current event,
programme revision and assignment validation. Empty items are included in the one
explicit declaration, without per-folder confirmation. A role with no matching
programme items cannot finish.

Serialize completion with reservation and receipt using the existing event then
programme locks. Every reservation for the selected contributions must have a
verified status, verification time and storage key. Unsent reservations block the
whole operation; there is no automatic abandonment or waiver. Store complete status,
completion time and declaring administrator on each contribution. Identical retries
preserve the original completion time. The declaration applies to the selected
crew/role only, including their registered work from other Macs. It cannot prove
that an offline Mac has no unreserved files: the confirmation explicitly asks the
operator to account for all that photographer's work.

A later unverified reservation reopens the affected contribution and clears its
completion metadata. Replaying an already verified reservation does not reopen it.
A later finish checks all eligible items again. Receipts and completion never
publish a gallery, create customer media, waive missing P2 work or modify video HOLD.

The Mac scans before finishing, blocks on unuploaded/queued/unstable local photos,
then syncs automatically and validates a scoped completion response. Save the
completion receipt in programme-sync.json. “Finish recorded” describes the last
successful declaration, not a live cross-Mac status or a release decision. New
local photos remove that indicator and require Ready, Upload and another finish.
An operator can repeat Finish to revalidate server state. Live aggregate progress,
expected-but-missing contributions and audited administrator exceptions remain the
next stage; they must use current server records, never the local receipt.

The migration adds nullable completion metadata only. Down drops those columns;
it does not remove photo files or contribution rows. Code and metadata rollback
must be coordinated because existing complete statuses remain on contribution rows.

Progress follow-up: the queue window now reads a current server snapshot, independent
of the local completion receipt. A shared scope maps programme items to published
assignment UUIDs. Changed assignment identities require completion again; registration
updates those identities and clears the earlier completion metadata. Read-only progress
never performs that mutation. Expected/unfilled role counts use current Crew Management
requirements; the response intentionally has no aggregate release-ready flag.
