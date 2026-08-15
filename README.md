# Ghost Media Hunter

**Find the media files nobody's using — before they quietly eat your disk space forever.**

Ghost Media Hunter scans your WordPress site for attachments (images, PDFs, and anything else in your Media Library) that aren't referenced anywhere, and gives you a clear, reviewable list — never an automatic delete. You decide what actually goes.

---

## Why this exists

Media libraries only grow. Every theme change, every abandoned draft, every "let me just try this image" leaves orphaned files behind — and WordPress gives you no built-in way to find them. Ghost Media Hunter closes that gap: it looks everywhere WordPress actually stores media references, tells you what it found (and *where* it looked), and lets you keep, restore, or remove each file on your own terms.

## What it checks

An attachment is only marked "unused" if **none** of the following reference it:

| Checker | Where it looks |
|---|---|
| **Post content** | `wp-image-{id}` classes, relative file paths, and resized-filename variants inside post/page content |
| **Featured images** | The `_thumbnail_id` post meta on any published post/page |
| **Post meta** | Custom fields (plain values and serialized arrays — e.g. ACF galleries) referencing the attachment ID |
| **Options** | `wp_options` values (theme mods, Customizer settings, plugin settings) referencing the attachment ID |
| **Widgets** | Classic Image widgets, Text/Custom HTML widgets, and block-based widgets |
| **Menus** | Custom Link menu items pointing directly at the file's URL |

Every match is recorded, not just a yes/no — so you can see *why* a file was flagged as used or unused.

**Not currently checked:** page builders (Elementor, Divi, etc.) — deliberately out of scope for now, since it's a genuinely open-ended list of formats to support. Support for a specific builder can be added later as its own checker without touching anything else.

## Requirements

- WordPress 6.2 or later
- PHP 8.0 or later

## Installation

1. Upload the plugin to `/wp-content/plugins/ghost-media-hunter`, or install the zip through **Plugins → Add New → Upload Plugin**.
2. Activate it. This creates the results table, schedules a daily scan, and generates a REST trigger key.
3. Go to **Media → Ghost Media Hunter** and click **Scan now** to run your first scan.

## Using it

### Media → Ghost Media Hunter

Your results live here, split into two tabs:

- **Unused** — attachments the last scan couldn't find a reference to. Each row has:
  - **Keep** — whitelist the file. It moves to the Kept tab and won't show up as unused again, even if the scanner still can't find a reference to it.
  - **Delete** — actually remove the attachment.
- **Kept** — anything you've whitelisted. Each row has:
  - **Restore** — undo Keep, moving it back to the Unused list.
  - **Delete** — same as above.

> ⚠️ **About Delete:** WordPress only moves media to the trash if your site has the `MEDIA_TRASH` constant enabled — most sites don't have this on by default. Without it, **Delete is permanent**. Ghost Media Hunter won't silently turn `MEDIA_TRASH` on for you (that's a site-wide behavior change beyond this plugin's business), so you'll get a clear confirmation prompt every time, spelling this out, before anything is removed.

### Scanning on a schedule

A full scan runs automatically once a day via WP-Cron. Like all WP-Cron jobs, this is triggered by site visits rather than a real clock — reliable on active sites, potentially delayed on very low-traffic ones.

### Triggering a scan externally

For guaranteed, traffic-independent scanning, Ghost Media Hunter exposes a REST endpoint:

```
POST /wp-json/ghost-media-hunter/v1/scan
X-GMH-Key: <your scan key>
```

Point a real system cron, uptime service, or external scheduler at this endpoint on whatever interval you want, and it'll trigger a scan regardless of whether anyone's visited the site. Only one scan runs at a time — if one's already in progress (from the button, cron, or this endpoint), the request returns `409` instead of overlapping it.

## Settings

**Media → GMH Settings**

- **External Trigger** — view your REST scan key, or regenerate it (the old key stops working immediately once you save).
- **Checker Matching** — the keywords (`image`, `logo`, `photo`, etc.) that post meta keys and option names must contain before their value is checked against an attachment ID. This exists to avoid false positives — without it, an unrelated numeric setting that happens to equal an attachment's ID could get wrongly flagged as "using" that file. Comma-separated, editable, and can't be saved empty (an empty list would break matching entirely, so it falls back to the defaults instead).

## Uninstalling

Deactivating the plugin stops the scheduled scan but keeps your data. **Deleting** it (via the Plugins screen, after deactivating) removes everything: the results table and all of the plugin's settings. Nothing about your actual media files is touched by uninstalling — only the plugin's own tracking data.

## Design principles

- **Never auto-deletes.** Every removal is a manual, confirmed action by you.
- **Records why, not just what.** A result isn't just "used" or "unused" — it shows every location that referenced the file.
- **Scans on a schedule, not per pageview.** Scanning your whole media library is real work; it shouldn't happen on every visitor's page load.

## License

GPLv3 — see `license.txt`.