# Testing

## Purpose

Define testing expectations for DancePro V2 development.

## Current Status

Feature tests should cover API behaviour for new features where practical.
Authentication has the initial baseline test focus.

## Scope

After application code changes, run:

```bash
sail artisan test
```

If routes changed, also run:

```bash
sail artisan route:list
```

Initial baseline coverage should include:

- Successful login.
- Invalid login.
- Inactive users cannot log in.
- Authenticated users can call `/api/auth/me`.
- Unauthenticated users cannot access protected routes.

## Links to Related Documentation

- [Development Environment](Development-Environment.md)
- [API Guidelines](API-Guidelines.md)
- [Authentication Specification](../specifications/Authentication.md)

## Notes / Future Work

Add feature-specific test expectations to the relevant specification or epic
when behaviour becomes detailed enough to warrant it.
