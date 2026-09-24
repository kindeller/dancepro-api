# Concert video title cards

## Current implementation

Concert programme preparation links to an event-specific video title card workspace. It provides a live 3840 x 2160 4K UHD preview driven by the confirmed programme, supports event or studio logos, preserves programme punctuation, omits a blank class name, and stores the event's colours and logo choice. Previous and next controls move through the running order. The selected card can be downloaded as a named high-quality JPEG; the complete running order downloads as one ZIP of named JPEGs. Cards can be downscaled for 1080p delivery.

The design uses Atlanta when it is available on the reviewing Mac and falls back to Arial for the preview. Exact server-side batch output requires a licensed Atlanta font file on the production renderer.

Each time the workspace opens or its logo selection changes, it samples the selected logo's opaque outer edge and matches the logo band to that background. Transparent logo edges retain the saved band colour. Gradient separator lines start at black for every template.

## Remaining production work

- Install the licensed Atlanta font on the production rendering service.
- Add batch generation for all confirmed programme items.
- Attach generated cards to the prepared event package used by the video workflow.
- Regenerate affected cards when a confirmed programme revision changes.
