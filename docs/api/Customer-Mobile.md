# Customer mobile API v1

The same JSON contract is intended for iPhone and Android. It is separate from
the crew app API. All protected routes require a Sanctum Bearer token with the
explicit `customer-mobile` ability; staff, crew and general API tokens cannot use
them. Responses use the shared `{success,message,data}` structure. The API does
not create a phone client or an offline media cache.

## Account and onboarding

- `POST /api/v1/customer/register`: name, email, confirmed password and explicit
  policy acknowledgement. Creates a customer-only account and sends a signed,
  expiring email verification link. No token is returned.
- `POST /api/v1/customer/auth/login`: email, password, device name, optional 2FA
  or recovery code. Issues a scoped expiring token. One token per device name is
  retained; a new login with the same name revokes the old token.
- `POST /api/v1/customer/auth/forgot-password`: sends the existing signed web
  password-reset email with a non-enumerating response.
- `GET /api/v1/customer/auth/me`: account verification and policy state, including
  links to the currently published Terms and Privacy Policy.
- `POST /api/v1/customer/auth/resend-verification`: throttled resend while needed.
- `POST /api/v1/customer/policy`: explicit acceptance of the configured current
  policy version after email verification.
- `POST /api/v1/customer/auth/logout`: revokes the calling token.

The verification email uses the signed web route. A phone user can open it in a
browser, then refresh `auth/me`; no web session or phone-specific link is needed
to mark the email verified. Media routes reject unverified accounts or accounts
without current policy acknowledgement. Existing customer web accounts created
before this flow are prompted when they next open My Media. Current Terms and
Privacy links both point to DancePro's public FAQ page; policy content and its
configured version need owner review before production launch.

## Media

- `GET /api/v1/customer/media`: owned dancers and verified studios; competition
  selector; concert years; recommended routines; paid-media event summaries; and
  My Events. The result contains public UUIDs and display fields, not internal IDs
  or media originals.
- `POST /api/v1/customer/profile`: reuses the web profile action contract.
- `POST /api/v1/customer/competitions`: event UUID plus separate event photo login
  code. Requires a saved dancer.
- `GET /api/v1/customer/competitions/{event}`: ready-only gallery hierarchy and
  paginated, watermarked photo URLs. A verified event registration is required.
- `GET /api/v1/customer/competitions/{event}/photos/{photo}`: private watermarked
  thumbnail or preview, subject to live ready-section checks.
- `GET /api/v1/customer/recommendations/{event}/{item}/{photo}/thumbnail`: a
  matching ready routine's watermarked thumbnail before event registration.
- `POST /api/v1/customer/concert-codes`: year plus video code; uses the same
  verified-studio and code-group checks as the website.
- `GET /api/v1/customer/concerts/{concert}`: authorized video list and current
  streaming/download capabilities. Playback and download URLs are API routes;
  streaming is withheld after six months while downloads can remain available.

These endpoints provide a shared server contract. Actual iPhone/Android clients,
device-local saving, push notifications, favourites, guest purchases and checkout
remain later stages.
