# ADR-0023: Audited photo contribution exceptions

Status: accepted and implemented, 22 September 2026.

Photo requirements belong to the event role and programme item. Administrators may
waive missing work for a whole role, day, section, group or item, with a required
reason. The scope resolves to explicit active item identities in the current
working programme. Completed contributions remain complete. Other roles are not
changed. Crew uploader permissions do not grant exception authority.

Waived contributions retain a separate status, reason, actor and timestamp. Every
change records the affected item identities and reason in the upload audit. Repeating
the same waiver is a no-op. Reinstatement clears the current waiver and returns the
item to awaiting files or receiving; it does not restore completion. Audit history
survives reinstatement. The portal displays the latest 50 entries.

A newly reserved photo or the first verified receipt of an older reservation reopens
the affected waiver and records an audit entry. Replaying an already verified receipt
does not reopen it. No photo is removed or modified by granting a waiver.

Upload Complete verifies non-waived requirements and leaves waived items separate.
Its acknowledgement includes waived_item_count; role_completed audits record a
user declaration, not proof that waived items were delivered. Server progress uses
waived or complete_with_exceptions and continues to show pending file counts.
Neither a waiver nor completion authorises customer publication. Release policy,
programme reconciliation, cloud storage and video transport remain separate work.

The migration adds nullable waiver metadata only. Its down method returns waived
items to receiving before dropping that metadata; it never manufactures completion
and preserves the independent audit table and photo files.
