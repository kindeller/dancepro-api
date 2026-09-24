# DancePro V2 Documentation

This folder contains the working documentation for the DancePro V2 Laravel API.
Documentation should be practical, concise, and maintained alongside the code.
It should explain why decisions were made and how the system is intended to work.

## Active integrated media plan

- [Roadmap and current next step](Roadmap.md)
- [Mac uploader preparation](milestones/Mac-Uploader-Preparation.md)
- [Mac uploader photo review](milestones/Mac-Uploader-Photo-Review.md)
- [Mac app build and usage](../apps/mac-uploader/README.md)
- [Agreed product handover](specifications/Integrated-Media-Product-Plan.md)
- [Programme samples and preparation workflow](specifications/Programme-Preparation.md)
- [Programme preparation API](api/Programme-Preparation.md)
- [ADR-0011: Programme drafts and confirmed snapshots](decisions/ADR-0011-Programme-Drafts-and-Confirmed-Snapshots.md)
- [Stage 1 implementation map](milestones/Integrated-Media-Stage-1-Inspection.md)
- [Next milestone: event and programme foundations](milestones/Integrated-Media-Event-Programme-Foundations.md)

The handover governs new integrated-media product direction where older proposals
differ. Existing milestone completion records remain historical evidence, not
proof that the expanded product is implemented.

## Structure

```text
docs/
├── api/
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
- [Remote Development](handbook/Remote-Development.md)
- [Remote Development Cheat Sheet](handbook/Remote-Development-Cheat-Sheet.md)
- [Git Workflow](handbook/Git-Workflow.md)
- [Architecture](handbook/Architecture.md)
- [API Guidelines](handbook/API-Guidelines.md)
- [DancePro V1 S3 Structure](handbook/V1-S3-Structure.md)
- [Concert Streaming AWS Setup Handoff](handbook/Concert-Streaming-AWS-Setup-Handoff.md)
- [Concert Streaming AWS ChatGPT Prompt](handbook/Concert-Streaming-AWS-ChatGPT-Prompt.md)
- [Crew Mobile API v1 contract](api/openapi-v1.yaml)
- [ADR-0007: Crew Mobile API Contract](decisions/ADR-0007-Crew-Mobile-API-Contract.md)
- [ADR-0010: Local Media Isolation](decisions/ADR-0010-Local-Media-Isolation.md)
- [Brand Style](handbook/Brand-Style.md)
- [Security](handbook/Security.md)
- [Production Launch Checklist](handbook/Production-Launch-Checklist.md)

## Epics

Epics describe major product capabilities and living product design.

Use them to explain why a capability exists, what problem it solves, current
scope, status, and future work. Epics should link to detailed specifications
instead of duplicating them.

Current epics:

- [Foundation](epics/Foundation.md)
- [Crew Scheduling](epics/Crew-Scheduling.md)
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

- [Crew Scheduling Project Specification](specifications/DancePro-Crew-Scheduling-Project-Specification.md)
- [Authentication](specifications/Authentication.md)
- [Download Links](specifications/Download-Links.md)
- [Competition Downloads](specifications/Competition-Downloads.md)
- [Media Sales and Loyalty Baseline](specifications/Media-Sales-and-Loyalty-Baseline.md)
- [DancePro V2 Functional Specification](specifications/DancePro-V2-Functional-Specification.md)
- [Concerts and Media Database Migration](specifications/DancePro-V2-Concerts-Media-Database-Migration-Spec.md)
- [Flutter Desktop Media Ingest API](specifications/Flutter-Desktop-Media-Ingest-API.md)

## Milestones

Milestones are implementation delivery records.

Use them to capture objectives, scope, status, notes, and follow-up work for a
delivery phase. They should be updated as delivery progresses, then treated as a
historical record once complete.

Current milestones:

- [Milestone 01 - Foundation](milestones/Milestone-01-Foundation.md)
- [Milestone 02 - Competition](milestones/Milestone-02-Competition.md)
- [Milestone 03 - Concert Production Readiness](milestones/Milestone-03-Concert-Production-Readiness.md)

## Decisions

Decisions contains architecture decision records and important planning records.

Use this folder when a technical or architectural choice needs durable context,
including the decision, alternatives considered, and consequences.

Current records:

- [DancePro V2 - Phase 0 Foundation Plan](decisions/DancePro-V2-Phase-0-Foundation-Plan.md)
- [Crew Scheduling - Phase 0 Integration Plan](decisions/DancePro-Crew-Scheduling-Phase-0-Integration-Plan.md)
- [ADR-0001 - Generic Downloads Bounded Context](decisions/ADR-0001-Generic-Downloads-Bounded-Context.md)
- [ADR-0002 - Concert Media Storage and Playback](decisions/ADR-0002-Concert-Media-Storage-and-Playback.md)
- [ADR-0003 - Desktop Media Ingest and Assignment](decisions/ADR-0003-Desktop-Media-Ingest-and-Assignment.md)
- [ADR-0004 - Media Ingest Compatibility and Verification](decisions/ADR-0004-Media-Ingest-Compatibility-and-Verification.md)
- [ADR-0005 - Retrievable Concert Access Codes](decisions/ADR-0005-Retrievable-Concert-Access-Codes.md)
- [ADR-0006 - Concert Branding Storage](decisions/ADR-0006-Concert-Branding-Storage.md)
- [ADR-0007 - Crew Mobile API Contract](decisions/ADR-0007-Crew-Mobile-API-Contract.md)
- [ADR-0008 - Communication Data Encryption](decisions/ADR-0008-Communication-Data-Encryption.md)
- [ADR-0009 - Booking Concert Link and Programme Copy](decisions/ADR-0009-Booking-Concert-Link-and-Programme-Copy.md)

## Documentation Principle

Documentation should answer why. The code should answer how. Delivery tooling
such as GitHub Projects or issues can mirror the next work; the active roadmap
and milestone keep the durable repository record of what is being built next.

- [Programme automatic extraction milestone](milestones/Programme-Automatic-Extraction.md)
- [ADR-0012: local programme extraction](decisions/ADR-0012-Local-Programme-Extraction.md)

- [Photo contribution exception decision](decisions/ADR-0023-Photo-Contribution-Exceptions.md)

- [Shared concert programme decision](decisions/ADR-0024-Shared-Concert-Programme.md)

- [Competition capture folder decision](decisions/ADR-0025-Competition-Capture-Folder-Additions.md)
- [Existing competition media videos decision](decisions/ADR-0041-Existing-Competition-Media-Videos.md)
