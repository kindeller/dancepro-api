# ADR-0053 — Publicity TV playback

Date: 26 September 2026
Status: Implemented for local pilot; physical Fire OS verification pending

## Context

The owner wants an internal TV app combining standard advertising, event
information and live Top Pics, including tagging changes after upload, without
interrupting playback on weak venue internet. The owner confirmed Fire OS.

## Decision

- Add a feature under `app/Features/Publicity`, using the existing verified,
  current-plan, non-duplicate Top Pics query in `AdminTopPics`. Selections and
  originals remain single-source; no parallel Top Pics albums or original copies.
- TV images use a dedicated unwatermarked 1920-pixel private display derivative,
  produced from the verified original. Customer previews keep their watermarks. Custom adverts are stored as
  private optimised JPEGs. No customer-original routes or account grants are
  exposed to screens. The existing local-only preview provider is retained;
  remote storage delivery requires later integration, not an original fallback.
- Store per-event enablement, durations and promotion frequency; global and
  event-specific information/image slides share one record shape. Slides can be
  edited or hidden. No destructive slide deletion is needed for this pilot.
- Native Fire OS app in `apps/fire-tv` uses Android views and app-private atomic
  files. A separate browser preview/player uses the same feed. The public web
  shell contains no event data; a service worker caches only that shell and
  player assets, while IndexedDB holds the paired screen's private slide cache.
- Screens pair using expiring one-use six-digit codes approved by an admin.
  The device receives a random scoped bearer credential; only its hash is stored
  server-side. Unpaired credentials expire after 10 minutes, paired credentials
  after 30 days without contact. Credentials never appear in URLs or server logs.
- An admin-paired crew screen can list and choose any non-cancelled competition explicitly enabled
  for TV. The catalogue is newest-date first and includes events without photos.
  Selection updates its single active event; each media request checks assignment,
  enablement, revocation and current slide eligibility. A paired screen does not
  acquire customer access or general administration privileges.
- Feed refresh runs every 60 seconds (5 seconds while waiting for pairing).
  Clients download changed identities/versions in the background and play only
  fully downloaded, decodable images. Removals apply after a valid feed refresh;
  the existing cached playlist remains usable on transport/server errors.
- Cache is limited to 500 MB (expanded for video advertising). Offline permission lasts 24 hours after successful
  refresh. Clients stop after expiry and clear private cache on rejected access.
  Offline devices cannot receive immediate removals or revocation.
- HTTPS for production. Debug Android package permits HTTP solely to reach a
  local Sail server over LAN. Admin preview uses its existing web session and
  memory-only image cache; it is not the paired-device offline shell.

## Consequences

The initial pilot can be built and previewed without the stick. Browser offline
behaviour is testable automatically. Physical remote navigation, sleep/power
behaviour and event-day endurance must still be tested on the actual hardware.
No auto-start, production deployment, public customer page or Appstore release
is implied. The owner subsequently requested the native homepage event picker;
TV enablement is the explicit event allowlist for paired crew screens. No
customer galleries or unrelated scheduling data become public. General and
event advertising plays when Top Pics are empty. The supplied DancePro TV
artwork is bundled unchanged for the homepage and launcher assets.

## Event advertising and scan-to-add access (26 September 2026)

Generate one default event advert on demand, cached by its template version,
name/date/logo and signed admission URL. Use the installed Bacon QR renderer;
no customer or event data goes to a third-party QR service. Generic information
is shown when no gallery code exists. Global and manually supplied event adverts
remain supported separately. Defaults: photos 2s, general adverts 5s, event adverts 8s.

A relative signed admission route validates the current gallery-code fingerprint
before retaining the event or studio in the browser session. Normal sign-in,
email verification, onboarding and two-factor rules apply before completion. The
completion revalidates eligibility and performs an idempotent, audited gallery
registration. It grants the same competition/studio photo scope as the existing
code-based flow, never video access or purchase entitlements. Competition dancers
are optional for QR admission; they remain useful for routine recommendations.
Changing the relevant access code invalidates the QR; existing
account access follows normal customer administration. No plaintext access code
is embedded in the advert. The signed link itself is an access capability intended
for attendees reached through the display, organiser or studio, equivalent to sharing the gallery access code.

Small PNG logo data is carried in the private cached manifest for consistent
native/browser corner overlays and offline use. Original photos remain unchanged.
Native Back/Escape now returns to event selection, while Menu/M controls the
focusable playback toolbar. Physical Fire OS verification remains outstanding.

## Independent sharing links (26 September 2026)

Publicity has a separate QR codes & links page, with competition and studio lists,
search, copy-link and downloadable PNG controls. Competition sharing uses exactly
the same signed URL as the automatic TV advert. Studio sharing uses a studio-level
signed route and does not depend on an individual concert or a slideshow. Gallery
codes must be configured first; inactive studios and cancelled competitions cannot
issue or accept links. Changing a code invalidates previous links, including ones
pending completion after sign-in. Links do not expire while the code stays current.

TV enablement no longer gates admission. Concerts are excluded from the crew event
catalogue; existing concert assignments return disabled playback at the next feed
refresh. Offline caches retain the existing 24-hour expiry boundary. There is no
schema migration or duplication of media. Signup retains the pending admission
through email verification and policy acceptance, then adds gallery access once.


Standard adverts now carry a competition/concert audience. The admin switch,
playlist query and image authorisation all use that audience. Event-specific
slides retain their event scope. Existing standard slides default to competition;
preparing a concert advert does not enable concert TV playback.


## Supplied competition QR templates (26 September 2026)

Six approved PNG designs now replace the generic generated advert for competitions
with configured gallery access. Normalize artwork to 1920 × 1080 once, and keep
explicit QR rectangles with the template catalogue. Render only the event’s
existing signed admission URL into that rectangle, with a four-module quiet zone;
no logos or additional text are baked in. Runtime corner branding remains separate.

Cache lossless rendered PNGs by source hash, placement, renderer version and URL.
The existing authorised image routes serve the detected image MIME type, and both
players already decode PNG. Stable event/template IDs plus content versions cause
connected screens to fetch replacements automatically. No database copies of six
slides per event, additional routes, or app release are needed. Events without a
configured code retain the generic message instead of displaying blank QR artwork.


## Video advertising extension — 26 September 2026

Publicity slides now optionally reference a private validated MP4. The server
checks H.264/yuv420p, AAC where present, dimensions, frame rate, duration, size and
a full decode with ffprobe/ffmpeg. No background conversion service is introduced.
Dedicated authorised video routes retain the existing event/screen scope. Clients
verify complete downloads against server SHA-256 and byte count before playback.
Native downloads stream into temporary files and atomically publish into private
cache; the browser uses bounded blobs in IndexedDB. Completion advances playback;
pause, skip, removal and leaving playback release the media player. Video controls
remain hidden, sound defaults off, and the existing offline lease still applies.


## Shared general advert storage (0.4.1)

General photo and video adverts are retained in private app storage across event
changes and app/device restarts. Switching events clears event-specific assets
but preserves the current general library. Pausing an event's playback retains
that library too. Media content hashes provide stable cache identities: edits to
titles, timing or sound settings do not download the media again. Replaced files
are downloaded on the next update, and hidden/removed adverts are pruned.

The shared library counts towards the 500 MB storage limit and is never evicted
to make room for event photos. Full storage prevents new downloads until content
is reduced. Unpairing, revoked access, clearing app data or uninstalling removes
the library. Existing 24-hour offline playback authorization still applies;
expiry stops playback without deleting downloaded files. The browser player uses
the same retention rules, subject to browser storage availability/eviction.


### Video placement refinement

Video adverts are distributed through equal photo groups, independent of the
image/QR advert interval. Their positions are recomputed from the current photo
count at each manifest update; each eligible video appears once per loop. When
possible a photo separates a video from the next regular image advert. Galleries
with no photos retain an advertising-only loop; when videos outnumber available
photo gaps, some videos necessarily share a gap. This is a server playlist change
and applies to both native and browser players without downloading videos again.
