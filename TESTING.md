# Testing and release validation

## Automated checks performed for version 1.0.0

The plugin was activated and exercised in isolated WordPress Playground installations using the real WordPress APIs and the SQLite integration. These tests did not modify a live site.

| Environment | Result |
| --- | --- |
| WordPress 7.1.3, PHP 8.3.33 | 47 integration assertions passed; no PHP error output |
| WordPress 6.5.13, PHP 7.4.33 | The same 47 integration assertions passed; no PHP error output |
| PHP 7.4.33 and PHP 8.3.33 parsers | All seven plugin PHP source files parsed successfully |
| Node.js syntax checks | Admin, front-end and block-editor JavaScript parsed successfully |
| Targeted scope/preview regression tests on WordPress 7.1.3 | 8 further assertions passed |
| Plugin Check 2.1.0 | 34 checks reviewed; code/readme errors corrected; final file-type check passed; 3 branding advisories retained |

The integration suite covers activation; project and taxonomy registration; the native and legacy dynamic blocks; the five shortcodes; bounded settings and repeaters; date validation; removal of unsafe URL schemes, script tags and event attributes; preservation of permitted rich text; missing and invalid save nonces; insufficient user permissions; literal backslashes and apostrophes; legacy gallery preservation; explicit gallery clearing; exclusion of draft, private and password-protected projects; read-only REST methods; search; pagination; category restrictions; selected project IDs; independent catalogue identifiers; and project galleries, events and accordion sections.

The translation template was generated with WP-CLI 2.12.0 from the PHP and JavaScript source. Block metadata was added using the contexts in WordPress's official `block-i18n.json` schema.

The additional regression assertions verify that malformed selected IDs/category/tag scopes cannot broaden results, the per-page limit of 48 is consistent, an administrator cannot embed a draft/private project into a public page, and an authorized preview can display only its own non-public project.

Plugin Check's remaining three advisories concern the requested `WP Portfolio Catalogue` name and `wp-portfolio-catalogue` slug. In Plugin Check 2.1.0, the `Trademarks_Check` source explicitly describes `wp` as allowed while still issuing a warning. The requested branding is retained for review. The earlier packaging warning for a development-only hidden file was resolved and the file-type check was repeated with zero findings. No hidden development files belong in the installable release ZIP.

WordPress Playground uses SQLite and a WebAssembly PHP runtime. It is useful for functional and security regression checks, but it is not a substitute for testing your actual theme, builder, MySQL/MariaDB database, image library, caching stack and hosting configuration.

## Browser checks performed for version 1.0.0

All 29 public-interface assertions passed in Chromium 133 on the WordPress 7.1.3 / PHP 8.3.33 test site, using the Twenty Twenty-Five theme. These checks covered initial rendering; repeated Load more requests and the final-page state; combined category/search filtering and reset; independent catalogues and their base restrictions; gallery arrows, thumbnails, lightbox keyboard navigation, Escape and focus restoration; event accordions; a 390-pixel touch viewport; and filtering and pagination with JavaScript disabled. No uncaught JavaScript errors were observed.

Visual inspection covered desktop and narrow layouts. A follow-up narrow-layout check confirmed that all three filter fields occupy separate full-width rows, the Apply button remains usable, and the page has no horizontal overflow. The card grid also adapts to the width supplied by the surrounding theme.

A final focused gallery test verified stable scroll positions after forward/back, thumbnail, touch and keyboard navigation, and after closing the lightbox. Gallery navigation scrolls only the gallery viewport, leaving surrounding theme and builder containers in place.

An authenticated WordPress editor check confirmed the plugin settings screen, native project field and gallery markup, client-side block registration, and a dynamic Portfolio Catalogue preview containing three project cards. No editor JavaScript errors or block-preview error notices were observed. The integration tests above exercise save validation; this browser check did not test a complete Media Library upload or every editor save workflow.

These checks do not constitute a complete browser/device matrix. Elementor itself and other proprietary builder interfaces were not exercised.

## Before each release

Run [Plugin Check](https://wordpress.org/plugins/plugin-check/) against the final installable ZIP and validate `readme.txt`. Review findings individually. Keep the tested versions and changelog accurate. The WordPress.org review team makes the final directory-approval decision.

Recommended staging checks:

1. Activate on a clean site. Confirm Projects, Categories, Tags and Settings are available and that activation does not publish sample content.
2. Create a project with a description, featured image, multiple gallery images and captions. Fill in dates, contact details, links, custom facts, events and accordion sections. Save and reopen the editor.
3. Verify that blank optional fields do not leave empty headings or boxes on the public page. Confirm the gallery handles a single image and an empty gallery.
4. Create at least seven projects across two categories. Add `[portfolio_catalogue per_page="3"]` to a page, then check category and tag filters, search, loading further results, clearing filters and empty results.
5. Place two catalogues on the same page. Filter and paginate one and confirm the other remains independent.
6. Repeat the catalogue checks with JavaScript disabled. Server-rendered results, forms and pagination links must remain usable.
7. Insert the Portfolio Catalogue block in Gutenberg. Verify the preview, selected-project picker, category/tag controls, ordering, columns and saved public rendering.
8. Add the catalogue shortcode using Elementor's Shortcode widget or the equivalent component in the builder you use. Check both builder preview and the public page. For a custom single-project template, disable automatic project details to avoid duplication and add the project/gallery shortcodes where needed.
9. Test a narrow phone viewport, a tablet and desktop. Check keyboard focus, gallery arrow/thumbnail controls, touch scrolling, the image viewer, Escape to close and return of focus to the opener.
10. Verify that draft, private and password-protected projects do not appear in public catalogue or filter responses. Confirm editors cannot overwrite projects they cannot edit.
11. If migrating from Post Project Gallery, back up first, deactivate the original plugin, activate this plugin and check existing project URLs, the original shortcodes and image galleries. Clearing the new gallery must not bring the old gallery back.
12. Deactivate and reactivate. Project content, uploaded images and settings should survive. On a disposable site, verify uninstall with cleanup disabled and enabled; uploaded media and project posts must remain.

## Reproducible commands

On a local WordPress development site with PHP and WP-CLI installed:

```bash
wp plugin activate wp-portfolio-catalogue
wp plugin install plugin-check --activate
wp plugin check wp-portfolio-catalogue
wp i18n make-pot wp-content/plugins/wp-portfolio-catalogue \
  wp-content/plugins/wp-portfolio-catalogue/languages/wp-portfolio-catalogue.pot \
  --domain=wp-portfolio-catalogue --exclude=docs
```

To include Plugin Check's runtime checks from WP-CLI, follow the official Plugin Check instructions for loading its `cli.php` file before WordPress. Review reports rather than treating a command's completion as proof that every check passed.

## Compatibility scope

The plugin provides WordPress blocks and standard shortcodes, so builders can render it without a proprietary integration. A separate native Elementor widget is not included. Builder/theme compatibility still depends on the surrounding template and how that builder invokes WordPress content filters. Test those combinations before production rollout.

Supported language infrastructure consists of a consistent text domain, translatable PHP/JavaScript strings and the included POT template. Translated PO/MO/JSON language packs can be generated separately; the package does not claim that all interface languages have already been translated.
