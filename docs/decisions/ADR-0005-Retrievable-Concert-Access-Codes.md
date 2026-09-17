# ADR-0005: Retrievable concert access codes

## Status

Accepted

## Context

Concerts currently use a simple shared password, not an account credential.
Staff need to read and share that code from the concert edit page. A one-way
hash cannot provide that view, and existing hash-only codes cannot be recovered.

## Decision

Keep `access_password_hash` for checking customer submissions. For newly set
codes, also store an encrypted copy in `access_password_encrypted` using
Laravel's encrypted cast and the application's encryption key. Display it as
text only on the authorised admin concert form. Hide both stored fields from
model serialisation. Clearing a code clears both fields.

## Consequences

Existing hash-only codes remain usable but must be replaced before staff can
view them. Database dumps retain ciphertext rather than plaintext, but anyone
with both a dump and the application key could decrypt it. Protect that key and
restrict admin access. Do not rotate the application key without a plan to
re-encrypt stored codes. The public concert page continues to request a code;
it never displays the saved value.
