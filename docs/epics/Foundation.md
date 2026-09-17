# Foundation Epic

## Purpose

Establish the secure, maintainable Laravel 13 API foundation for DancePro V2
before rebuilding major product capabilities.

## Current Status

The initial foundation is substantially implemented. The application uses the
feature-based structure, shared API responses, Sanctum authentication, a user
foundation, security defaults, maintained documentation and feature tests.

Remaining foundation work is production hardening carried by the active feature
milestones rather than a new foundation build. This includes completing the
security review, expanding authorization where staff responsibilities require
it, and keeping verification and deployment guidance current.

## Scope

- Feature-based architecture.
- Shared API response conventions.
- Sanctum authentication baseline.
- User foundation.
- Security baseline.
- Documentation structure.
- Testing expectations.

## Links to Related Documentation

- [Phase 0 Foundation Plan](../decisions/DancePro-V2-Phase-0-Foundation-Plan.md)
- [Milestone 01 - Foundation](../milestones/Milestone-01-Foundation.md)
- [Architecture](../handbook/Architecture.md)
- [API Guidelines](../handbook/API-Guidelines.md)
- [Authentication](../specifications/Authentication.md)
- [Security](../handbook/Security.md)

## Notes / Future Work

This epic intentionally points to the existing Phase 0 plan rather than
duplicating it. Keep implementation records in milestones and durable technical
choices in decisions. The implemented foundation should now be improved as part
of each product delivery rather than treated as a separate unfinished phase.
