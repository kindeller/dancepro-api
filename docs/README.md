# DancePro V2 Documentation

This folder contains the working documentation for the DancePro V2 Laravel API.
Documentation should be practical, concise, and maintained alongside the code.
It should explain why decisions were made and how the system is intended to work.

## Structure

```text
docs/
├── handbook/
├── epics/
├── specifications/
├── milestones/
└── decisions/
```

## Handbook

The handbook contains stable development and architecture guidance.

Use it for conventions that should apply across the project, including local
development, architecture, API responses, authentication approach, AWS,
deployment, security, testing, brand style, and git workflow.

Start with:

- [Development Environment](handbook/Development-Environment.md)
- [Git Workflow](handbook/Git-Workflow.md)
- [Architecture](handbook/Architecture.md)
- [API Guidelines](handbook/API-Guidelines.md)
- [Brand Style](handbook/Brand-Style.md)
- [Security](handbook/Security.md)

## Epics

Epics describe major product capabilities and living product design.

Use them to explain why a capability exists, what problem it solves, current
scope, status, and future work. Epics should link to detailed specifications
instead of duplicating them.

Current epics:

- [Foundation](epics/Foundation.md)
- [Downloads](epics/Downloads.md)
- [Competition](epics/Competition.md)
- [Concert](epics/Concert.md)
- [Customers](epics/Customers.md)
- [Payments](epics/Payments.md)
- [Reporting](epics/Reporting.md)

## Specifications

Specifications describe detailed technical behaviour for specific features.

Use them for workflows, endpoint behaviour, integration boundaries, security
rules, data expectations, and edge cases.

Current specifications:

- [Authentication](specifications/Authentication.md)
- [Download Links](specifications/Download-Links.md)
- [Competition Downloads](specifications/Competition-Downloads.md)

## Milestones

Milestones are implementation delivery records.

Use them to capture objectives, scope, status, notes, and follow-up work for a
delivery phase. They should be updated as delivery progresses, then treated as a
historical record once complete.

Current milestones:

- [Milestone 01 - Foundation](milestones/Milestone-01-Foundation.md)
- [Milestone 02 - Competition](milestones/Milestone-02-Competition.md)

## Decisions

Decisions contains architecture decision records and important planning records.

Use this folder when a technical or architectural choice needs durable context,
including the decision, alternatives considered, and consequences.

Current records:

- [DancePro V2 - Phase 0 Foundation Plan](decisions/DancePro-V2-Phase-0-Foundation-Plan.md)
- [ADR-0001 - Generic Downloads Bounded Context](decisions/ADR-0001-Generic-Downloads-Bounded-Context.md)

## Documentation Principle

Documentation should answer why. The code should answer how. Delivery tooling
such as GitHub Projects or issues should answer what is being built next.
