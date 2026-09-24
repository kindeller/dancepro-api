# ADR-0027: Competition gallery presentation settings

Date: 2026-09-22
Status: Implemented for private management galleries

Store optional gallery title and cover selection against the existing scheduling
event in a separate competition_gallery_settings row. This permits Media settings
before a programme or upload plan exists and avoids changing the planning event's
name, programme snapshot, identifiers or logo. Default title and artwork continue
to come from the existing event. No second event is created.

A cover may reference only a canonical verified photo from this event's current
photo gallery/upload plan. Use its existing private watermarked thumbnail route;
never duplicate or expose its original. Cover eligibility is rechecked when rendering
settings/cards. An ineligible selection falls back to the event logo. Restoring the
logo clears only the selection, not the photo or event artwork.

Settings saves lock the existing event and require an optimistic integer version.
Stale windows fail with a reload instruction. A separate append-only change table
stores previous/new settings, actor and time in the same transaction; unchanged saves
produce no audit entry. Permissions remain administrator-only and event-scoped.

These are presentation choices only. They do not confirm programmes, affect role
completion, grant access or publish photos. Watermark overrides, publication and
customer access remain separate work. Migration rollback drops only settings and
history; underlying events, media and programmes remain intact.
