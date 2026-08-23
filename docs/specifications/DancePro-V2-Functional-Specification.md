# DancePro V2 Functional Specification

## Purpose

This document defines the functional behaviour of DancePro V2. It describes the expected customer, studio and administrator experience without prescribing implementation details, database schema, Laravel architecture or cloud infrastructure.

The objective is to define **what the system should do**, allowing the underlying implementation to evolve while preserving the customer experience.

## Implementation Status

The following MVP customer behavior is implemented:

- Public studio and released-concert browsing.
- Approval, enabled/disabled and availability controls.
- Password and student-name access with session unlocks and attempt logging.
- Ordered concert video playback with automatic next-item playback.
- Optional program and external-gallery links with unavailable states.
- Individual downloads and a browser-driven bulk-download manager.
- Staff creation and editing of studios and concerts.
- Local demonstration media for workflow testing.

Automatic next-item playback has been manually validated on desktop and mobile
across multiple browsers and retains the active player during the transition.

The current production-readiness work is limited to finishing existing MVP
operations:

- Move concert playback and originals to authorised, short-lived S3 or
  CloudFront delivery.
- Use database-backed Downloads tracking for concert originals.
- Add staff media collection, asset, program and cover management.
- Complete playback error states, thumbnails, authorization, production
  configuration and verification.

Customer accounts, ordering and payments remain future capabilities and are not
required to make the current anonymous Concert experience operational.

---

# Vision

DancePro V2 provides a modern platform for managing studios, concerts and protected media.

The platform is designed to:

- Deliver a simple customer experience.
- Scale to very large media libraries.
- Support streaming and protected downloads.
- Separate business data from media storage.
- Support future expansion into competitions, ordering and customer accounts.

---

# Design Principles

- The database manages business entities.
- Media is storage-derived by default.
- Individual media files do not require database records simply because they exist.
- Concerts reference storage locations that contain their media.
- Streaming and original media are implementation details.
- Customers interact with concerts and media, never storage.
- Security should be transparent wherever possible.
- Features should remain provider-agnostic.

---

# Personas

- Public visitor
- Concert customer
- Studio reviewer
- DancePro administrator
- API/application client

---

# Functional Areas

## Studios

Requirements

- Browse available studios.
- Display cover image, branding and description.
- Show only studios with publicly available concerts.
- Alphabetical ordering.
- Empty states when no content exists.

---

## Concerts

Requirements

- Belong to one studio.
- Support cover image, description and branding.
- Can be enabled/disabled.
- Can require approval before release.
- Can have availability windows.
- Can optionally require a password.
- Reference a storage location containing their media.

---

## Customer Access

Requirements

- Password protected concerts require a password and student name.
- Session-based access after successful verification.
- Passwords stored securely.
- Access attempts logged.
- Signed temporary links may bypass password flow where appropriate.

---

## Media Playback

Requirements

- Customers browse media for a concert.
- Playable media appears in a predictable order.
- Embedded streaming player.
- Next-item playback.
- Playlist with thumbnails and display names.
- Streaming media is used where available.
- Original media remains available for protected downloads.

---

## Downloads

Requirements

- Individual protected downloads.
- Bulk download manager.
- Temporary signed download links.
- Browser guidance for multiple downloads.
- Pause, resume and reset download state.
- Desktop-first experience for large downloads.

---

## Programs and Galleries

Requirements

- Optional concert program.
- Optional external gallery.
- Clear unavailable states.
- Protected access where appropriate.

---

## Customer Accounts

MVP

- Anonymous concert access supported.

Future

- Saved concerts.
- Order history.
- Favourite media.
- Permanent customer library.

---

## Staff Administration

Staff can manage:

- Studios
- Concerts
- Users
- Roles
- Customer access
- Programs
- Cover media
- Availability
- Approval workflow

---

## Staff Media Management

Requirements

- Select a concert.
- Browse concert media.
- Upload media.
- Replace media.
- Rename display names.
- Delete media.
- Upload programs.
- Manage cover images.

The implementation may initially use local or seeded media before object storage integration.

---

## Orders

Future capability.

The platform will support:

- Media ordering.
- Order history.
- Protected purchased downloads.
- Order fulfilment.

---

## API

The API provides secure access to:

- Studios
- Concerts
- Customer authentication
- Media discovery
- Protected download links
- Administrative functions

The API should expose business entities rather than storage implementation.

---

# Security

- Password-protected concerts.
- Temporary signed links.
- Secure authentication.
- Role-based authorisation.
- No exposure of storage keys or internal identifiers.

---

# MVP Scope

The initial implementation should provide:

- Studio browsing
- Concert browsing
- Password access
- Media playback
- Programs
- Individual downloads
- Bulk download page
- Staff administration
- Seeded/local media support

Object storage, streaming generation and CDN integration may be introduced after the functional workflows are complete.

The functional workflows are now sufficiently established to begin the object
storage and CDN alignment. This work is tracked by
[Milestone 03 - Concert Production Readiness](../milestones/Milestone-03-Concert-Production-Readiness.md).

---

# Future Enhancements

- Competition media
- Customer accounts
- Media purchasing
- Download analytics
- Streaming optimisation
- Automatic transcoding
- Archive storage
- Customer favourites

---

# Out of Scope

This specification intentionally excludes:

- Database schema
- Laravel implementation
- Storage provider
- Queue architecture
- Controller design
- Infrastructure
- CSS/UI implementation

---

# Acceptance Philosophy

Every feature should be testable from the perspective of a customer or administrator.

Acceptance criteria should validate behaviour rather than implementation.
