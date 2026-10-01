# Help screenshots

These captures use actual Blade views with fictional sample data. Public images
contain only the relevant controls, not browser chrome, test-account selectors,
customer identifiers or real media. No production account is used. Rendered HTML
stays in ignored `tmp/help-screenshots/`; only the cropped PNGs are public.

## Refresh after changing the customer interface

With the local Sail site running:

```sh
./vendor/bin/sail artisan tinker --execute="require base_path('scripts/help-screenshots/render.php');"
node scripts/help-screenshots/capture.mjs
```

The renderer refuses production environments, fakes mail/notifications and rolls
back its temporary customer. The capture script accepts only a localhost origin,
blocks non-GET and external requests, and never submits forms. Set `CHROME_PATH`
if using system Chrome rather than Playwright's installed Chromium. Optional
`HELP_BASE_URL` defaults to `http://localhost` (no trailing slash).

1. Inspect every resulting `public/images/help/*.png` for readability and privacy.
2. Update dimensions printed by the capture script in `config/help-screenshots.php`.
3. Check captions and article slugs against current interface labels. Each picture
   appears once, with an instructional caption and descriptive alternative text.
4. Run the feature suite through Sail. `HelpScreenshotsTest` checks asset dimensions,
   publication/search visibility and mapping to the public and signed-in Help pages.
5. Check Help at desktop and phone widths: linked answers open, images load, zoom
   and Fit to screen work, Escape/Close return focus, and full-size links work with
   JavaScript disabled. Do not submit the pictured example forms.

## Presentation

Screenshot mappings are curated in `config/help-screenshots.php`, while article
text remains editable in Website Management → Help & FAQ. Hiding an article also
hides its guide. The PNGs contain no private data and remain public static assets.
The viewer uses native dialog keyboard/focus behavior and a full-size image link
as the fallback. Expanded answers span the Help grid, avoiding tiny instructions
inside four narrow columns. Guides are lazy-loaded and add about 344 KB in total.

Inline screenshot cards use one-third of the guide width on desktop, two columns
on tablets and a single compact column on phones. A single screenshot stays the
same size as one card; it does not expand across the whole article. Full-size
viewing and zoom remain available.
