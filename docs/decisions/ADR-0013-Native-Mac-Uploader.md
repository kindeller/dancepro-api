# ADR-0013: Native Mac uploader with local programme preparation

Date: 2026-09-21
Status: Accepted for initial implementation

## Context

The owner clarified that no Mac uploader exists. Earlier references to reusing a
Flutter desktop client were assumptions, not inspected working software. Event
preparation/download must run inside the uploader so each event computer can be
prepared without a separate browser login. The product is Mac-only; Xcode is
available locally and no Flutter uploader toolchain/project exists.

## Decision

Build a native SwiftUI macOS app in `apps/mac-uploader`, using a Swift package
with a separately tested preparation core. Use the existing Laravel staff login
and programme contracts, adding only a paginated approved-event discovery route.
Keep controller logic thin and discovery queries within the Programme feature.

Use Keychain for this app's API token, retaining the existing server expiration
and ability rules. First delivery supports current administrator access; a crew
permission design must explicitly define event/role access before widening it.
No AWS credential handling is part of the app.

Persist approved JSON, pinned PDF and a server/event/revision/item manifest beside
media folders. Remember local roots through bookmarks. Create each revision in
its own directory using staging and a final move; never overwrite user media.
Root names are labels, not identities. Validate full identifiers when reusing a
root, and reject unsafe or colliding paths.

## Consequences

Native Finder, folder selection, offline state and Keychain integration are
available without a browser or a Flutter installation. Capture-app integration,
photo review, role selection, upload queueing and reconciliation remain later
stages. There is no automatic migration of an existing media tree.

The local app is ad-hoc signed, not a crew distribution release. Developer ID
signing/notarization and architecture coverage are separate release tasks.
The historical Flutter ingest API specification remains a backend reference,
not evidence of a desktop application or a requirement to build in Flutter.
