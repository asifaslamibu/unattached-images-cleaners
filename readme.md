# Media Tools – Cleaner & WebP Converter

A WordPress plugin with three screens under a single **Media Tools** menu:

1. **Dashboard** — at-a-glance stats, format breakdown, environment check.
2. **Unattached Cleaner** — find and delete images not linked to any post or page.
3. **WebP Converter** — bulk-convert JPEG/PNG/GIF/BMP to WebP.

Both tool screens share the same filter UI: **format checkboxes** (JPG/PNG/GIF/WebP/BMP) plus a **folder dropdown** (YYYY/MM uploads folders), with a "Select all matching" action button that works across pages — not just the visible page.

## Install

1. Upload `unattached-images-cleaner.zip` via **Plugins → Add New → Upload Plugin** (or unzip into `wp-content/plugins/`).
2. Activate **Media Tools – Cleaner & WebP Converter**.
3. Open the new **Media Tools** menu in the sidebar.

## Dashboard

- Total image count, unattached count, convertible count, already-WebP count.
- Per-format breakdown table with proportional bars.
- Quick-action tiles for each tool.
- Environment panel: WebP support, image editor (GD/Imagick), folder count, PHP/WP version.

## Filters (both tool screens)

- **Format checkboxes** — pick any subset of JPG, PNG, GIF, WebP, BMP (cleaner shows all; converter excludes WebP since it can't convert WebP to WebP).
- **Folder dropdown** — choose any detected upload folder (`YYYY/MM`) or "All folders". Folder list is whitelisted against actual directories to prevent path injection.
- **Apply filters** updates the URL with the selection; **Reset** clears them.
- Each card shows the image's format and size, so you can verify before acting.

## Selecting images

- **Select all on this page** — checkbox in the toolbar; checks every visible card.
- **Delete/Convert Selected** — acts on visible checked cards.
- **Delete All Matching / Convert All Matching** — runs the current filter against the entire library and processes up to the batch limit per submit, ignoring what's actually checked. Submit again to continue chipping through the rest.

## Batch limits

- Cleaner: **100 deletes per submit**
- Converter: **20 conversions per submit** (conversion is CPU-heavy)
- Both call `set_time_limit(300)` defensively for shared-host PHP timeouts.

## WebP Converter modes

- **Replace originals** — converts the full-size + all thumbnails to `.webp`, deletes old `.jpg/.png/.gif/.bmp` files, updates `_wp_attached_file`, mime type, GUID, regenerates intermediate sizes.
- **Keep originals** — generates a `.webp` next to the original; attachment is untouched. Pair with `.htaccess` rewrite rules or a delivery plugin to actually serve the `.webp`.

### Replace mode caveats

- Hard-coded URLs (`<img src=".../photo.jpg">`) inside post content, theme options, page-builder data, or custom meta will still point at the deleted original. Run a search-replace after:
  ```bash
  wp search-replace '.jpg' '.webp' --include-columns=post_content --dry-run
  ```
- Theme code using `the_post_thumbnail()` or `wp_get_attachment_image()` keeps working — those resolve through `_wp_attached_file`, which the converter updates.
- Always take a backup before running in replace mode.

## Security

- All screens gated by `manage_options` capability.
- All write actions use nonces (`uic_delete_action`, `uic_webp_convert_action`).
- Folder filter values are whitelisted against actual filesystem folders.
- Deletion handler re-verifies each ID is an attachment with `post_parent = 0` even on the server side, so a tampered form can't delete an in-use image.

## Requirements

- WordPress 5.8+
- PHP 7.4+
- GD or Imagick with WebP support (for the converter)

## File structure

```
unattached-images-cleaner/
├── unattached-images-cleaner.php       Bootstrap + constants
├── readme.md
└── includes/
    ├── class-uic-helpers.php           Format map, folder discovery, filter UI, query builder, stats
    ├── class-uic-assets.php            Shared admin CSS + JS
    ├── class-uic-dashboard.php         Dashboard page (top-level menu)
    ├── class-uic-cleaner.php           Unattached cleaner submenu
    └── class-uic-webp.php              WebP converter submenu
```
