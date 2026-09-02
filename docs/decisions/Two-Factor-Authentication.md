# Two-Factor Authentication

## Status

Accepted for launch; implemented but disabled during local product development.

## Decision

DancePro web accounts use time-based one-time passwords compatible with Apple
Passwords, 1Password, Google Authenticator and other standard authenticator
apps. Secrets are encrypted using the application encryption key. Recovery
codes are encrypted as a collection and individually one-way hashed; each code
can be used only once.

Two independent configuration switches control rollout:

```text
TWO_FACTOR_ENABLED=false
TWO_FACTOR_ENFORCED=false
```

`TWO_FACTOR_ENABLED` exposes setup and applies login challenges to accounts that
have completed setup. `TWO_FACTOR_ENFORCED` additionally prevents unconfigured
web accounts from entering administrator or crew areas. Both remain false until
the launch security review and deployment configuration are complete.

The API/mobile authentication surface is not changed by this web decision and
requires a separate authentication review before production iPhone access.

## Security properties

- QR codes are rendered locally; the TOTP secret is not sent to an external QR
  service.
- Setup, disabling and recovery-code regeneration require the current password.
- Setup must be confirmed with a valid six-digit TOTP code.
- Login challenges are rate limited.
- Responses do not serialize TOTP secrets or recovery-code hashes.
- Recovery codes are displayed only when first generated or regenerated.

