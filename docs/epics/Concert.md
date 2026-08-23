# Concert Epic

## Purpose

Provide a secure customer experience for discovering studios, accessing released
concerts, playing concert media and downloading protected originals.

## Current Status

Customer discovery and playback foundation implemented. Staff studio and
concert creation, editing, approval, release, availability and access-password
management are available in the web admin. Staff media management remains the
next delivery slice.

## Scope

- Public studio browsing, limited to studios with an available concert.
- Published-concert browsing with approval and availability controls.
- Password and student-name access with session unlocks and attempt logging.
- Managed video playlist, next-item playback and protected original downloads.
- Optional program and external-gallery links with explicit empty states.
- Read-only public studio and concert API discovery.
- Local demonstration content for workflow testing.
- Staff studio list, creation and editing.
- Staff concert list, creation and editing.
- Staff approval, publication, availability and password controls.

## Links to Related Documentation

- [Architecture](../handbook/Architecture.md)
- [Security](../handbook/Security.md)
- [Functional specification](../specifications/DancePro-V2-Functional-Specification.md)
- [Concert/media database specification](../specifications/DancePro-V2-Concerts-Media-Database-Migration-Spec.md)

## Notes / Future Work

- Staff media upload, replacement, display-name editing and deletion.
- Program and cover-image upload workflows.
- Storage-derived collection listing alongside managed assets.
- Formal staff roles and granular authorization.
- CDN delivery and streaming optimisation.
