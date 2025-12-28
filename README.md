# Static HTML Importer

Import static `.html`/`.htm` files into WordPress as published pages via a simple admin UI.

## Features
- Admin menu page “HTML Importer” for uploading a single HTML/HTM file
- Security: capability check (`manage_options`) and nonce validation
- Parses `<title>` and `<body>` (fallbacks: file name and full HTML)
- Creates a published `page` with sanitized title and kses-filtered content
- Success/error notices via WP admin alerts

## Installation
1) Copy `static-html-importer.php` into your WordPress install at `wp-content/plugins/static-html-importer/`.
2) In WP Admin → Plugins, activate **Static HTML Importer**.

## Usage
1) Go to WP Admin → **HTML Importer**.
2) Choose a `.html` or `.htm` file and submit.
3) On success, a new published page is created using the parsed title/body; notices show results or issues.

## Notes
- Only admins (or users with `manage_options`) can import.
- Unsupported/empty uploads or bad nonces return clear error notices.
- Content is stored as-is; consider adding templates or link/media rewriting as future enhancements.
