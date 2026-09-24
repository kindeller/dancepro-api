# Customer competition access

This adapter uses existing web sessions and CSRF protection. Customer token issuance and native clients remain pending.
Web signup and customer sign-in are now available. Shared business rules support future web,
iPhone and Android adapters.

## Event selection and login

Customers select the specific event (name and date) from the dropdown, then enter
its **event login code**. Media codes are independent: several events can share a
competition/media code and have different customer login codes. Media codes do not
grant access. All noncancelled competition events appear in the selector, including events whose
login code has not been configured yet. A valid configured login code is still
required for registration; folder and photo readiness is checked separately.

| Method | Route | Behaviour |
| --- | --- | --- |
| GET | `/my/competitions` | Verified registrations and available event selector |
| POST | `/my/competitions` | `event_uuid` and `login_code`, 10 requests/minute |
| GET | `/my/competitions/{event UUID}` | Ready folders; optional `folder` UUID/page |
| GET | `/my/competitions/{event UUID}/photos/{photo UUID}` | Watermarked derivative; `size=thumbnail` or `preview` |

Requires an active customer or administrator account. Admins also verify the event
login code; crew-only accounts are denied. Repeated registration updates the same
user/event record. Stable membership survives date/media-code edits. Cancellation,
a noncompetition event type or inactive account prevents access on the next request.

## Administration and storage

Set or replace an event login code in Media → Competition Photos → Gallery settings.
Codes use 6–64 ASCII letters, numbers or hyphens, compared case-insensitively; customer
input trims outer whitespace. Laravel hashes normalized codes. Neither plaintext
nor hashes appear in audit history, rendered pages or model serialization. Login-code
fields are excluded from validation old-input flashing. Keep a separate copy when
sharing: the UI supports replacement, not recovery of the stored code.

Settings use the existing event lock and optimistic version. Registration holds that
same lock while verifying. Code replacement leaves already verified registrations
valid; it is not account revocation. New and previously unverified registrations
must verify the current code. No real event code was assigned during implementation.

Migration 000009 drops the erroneous `registered_code` column, adds nullable
`access_verified_at`, and adds nullable `login_code_hash` to gallery settings. Existing
media-code registrations are retained but cannot browse until login verification.
Rollback removes the new hash/verification fields and recreates a nullable legacy
column without recovering its old values; it must not be used as an access bypass.

The shared CompetitionCustomerPreview service enforces ready sections at every browse
and image request, including cached derivatives. Originals remain private. Event
registration conveys no purchase/download entitlement. List cards use event logos;
selected gallery covers require a ready section. Responses are private/no-store.

Next: customer onboarding and mobile authentication/API contracts. Both phone platforms
are required; mobile technology, guest access and customer-specific login branding
remain pending.


## Customer web accounts

- `GET /customer/register`: customer account form.
- `POST /customer/register`: name, email, password and password_confirmation;
  guest-only, CSRF protected, five attempts/minute/IP.
- `GET /customer/sign-in`: customer-facing sign-in form.
- `POST /customer/sign-in`: reuses WebAuthController and the existing login limiter,
  inactive-account checks, session rotation and configured two-factor handling.

CreateCustomerAccount atomically creates a user with fixed customer type,
`is_admin=false`, and a CustomerProfile with `registration_source=web`. Email is
trimmed and lowercased; uniqueness includes deleted users. Passwords use the existing
User hashed cast, require confirmation and at least 12 characters, and reject inputs
over 72 bytes. Password fields are never repopulated after validation errors.
Duplicate insert races return a validation error; callers cannot set privileges.

Signup signs in the new account and opens My Competitions. The existing 2FA middleware
still applies. Event selection/login-code verification is separate and mandatory.
There is no automatic event registration, purchase entitlement, guest claim or
verification email sent. Email verification and customer policy onboarding remain
pending; no terms/privacy/marketing timestamps are fabricated. No production launch
is implied by this local review slice.

Signed-out requests to `/my/*` go to customer sign-in; staff routes retain their
existing login. Public navigation provides customer sign-in and authenticated sign-out.
Password recovery links reuse the existing reset flow.


## My Dancers discovery

`GET /my/competitions/{event UUID}/my-dancers` requires the same verified event
registration as the gallery. It compares the account's dancer names and saved
programme-name aliases with current competitor/routine titles. Matching is
case-insensitive and observes whole name boundaries so a partial surname does not
suggest another competitor. The result includes only items within sections that pass
the existing automatic readiness check. Links open the normal customer album route,
which checks readiness again when opened. The page is private/no-store and noindex.

These are suggestions based on title text, not an identity or ownership claim. The
Whole Event view remains one click away, and not matching a name never removes
access to an authorized section. Preview images continue using the existing guarded
customer image route. Structured studio metadata is absent from current programme
nodes; My Studios’ Routines needs that information before it can match reliably.


## My Studios’ Routines

`GET /my/competitions/{event UUID}/my-studios` uses the same event-registration
request as the gallery. It considers only current programme items with an explicit
admin studio assignment. A suggestion appears only if its section is ready and the
customer has verified that active studio in My Profile using its gallery code. Direct album/image requests
recheck their existing gates. The page is private/no-store and noindex. Whole Event
remains accessible even when no studio is saved or assigned.

An administrator assigns a studio on a competitor album under Media → Competition
Photos using the `competition-gallery.routine-studio` POST route. This action is
admin-only, validates a current active item and studio, prevents stale overwrites,
and audits each actual change. Removing an assignment affects only discovery.
Programme uploads and folder identities are unchanged. See ADR-0030.

A verified profile studio records knowledge of its gallery code. It grants neither
competition event access nor photo purchases; the competition login code and readiness
checks remain independent.
