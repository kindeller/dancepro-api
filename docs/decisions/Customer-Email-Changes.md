# Confirm a replacement account email before changing sign-in

Date: 29 September 2026

## Decision

Customer profile email edits keep the current email active and store a proposed
address plus request time on the existing user. Requesting the change requires
the current password. A server-signed link to the proposed address expires after
60 minutes and must be opened while signed into the same account. Confirmation
rechecks the pending request and email uniqueness before changing anything.
No new user is created and purchases/media stay attached to their owner.

Confirmation updates that user's Studio Hub access and linked studio-contact
email addresses without changing roles. A conflicting separate studio invitation
blocks confirmation for manual resolution. API tokens, remembered logins and other
database sessions are invalidated; the confirming session remains. The old email
receives a change notice. Existing addresses stored in historical orders/messages
remain historical records, not rewritten communications.

Any other change to the account email clears pending requests. Anonymisation also
clears them. The pending address is excluded from generic user serialization and
included in the owner's privacy export. The migration adds nullable fields and
its rollback retains the active email address.

## Reason

Immediate replacement can lock a user out after a typo. Keeping the current
address until confirmation proves ownership of the replacement while preserving
the account's existing access and purchases. Signed links are single-use in
practice because confirmation clears the pending request. No mailbox credentials
or external email-provider changes are needed for this workflow.
