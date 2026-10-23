# WP Portfolio Catalogue

A free, open-source WordPress plugin for publishing project portfolios. Create projects once, then display a searchable, filterable catalogue on the homepage or any other page using the **Portfolio Catalogue** block or a shortcode.

**Version:** 1.0.0 · **WordPress:** 6.5+ · **PHP:** 7.4+ · **License:** GPL-2.0-or-later

[Source and issues](https://github.com/raivis-kalnins/wp-portfolio-catalogue) · [WordPress.org submission guide](docs/WORDPRESS-ORG.md) · [Validation record](docs/TESTING.md)

## Features

- A native **Projects** custom post type, with Gutenberg editing, revisions, featured images and excerpts.
- Hierarchical project categories, project tags, category filtering, text search and paginated results.
- A server-rendered **Portfolio Catalogue** block with layout, sorting, project selection and filtering controls.
- Shortcodes for Elementor, Divi, Beaver Builder, the Classic Editor and other tools that render WordPress shortcodes.
- Responsive cards with 1–4 desktop columns and layouts that adapt to smaller screens.
- Featured images and ordered project galleries, including touch scrolling, arrows and thumbnail navigation.
- Optional start/end dates, status, client, location, contact name, email, phone and website.
- Repeatable custom project details, social and external links, project events and accordion sections.
- Automatic single-project content using the active theme, or manual placement with shortcodes.
- Native Media Library selection, image deduplication, drag reordering and keyboard-accessible move controls.
- Locally served CSS and JavaScript. No API key, subscription, remote font, tracking service or third-party JavaScript CDN is required.
- Translation-ready PHP and editor strings with one consistent text domain.
- Compatibility with project posts, gallery metadata, gallery shortcodes and saved Selected Projects blocks from Post Project Gallery.

## Install

1. Download the release ZIP containing the `wp-portfolio-catalogue` folder.
2. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP, install it, and activate **WP Portfolio Catalogue**.
4. Open **Projects → Add New** and create your first project.
5. Add the **Portfolio Catalogue** block or `[portfolio_catalogue]` to a page.
6. Adjust defaults under **Projects → Settings**.

For manual installation, put the plugin directory at `wp-content/plugins/wp-portfolio-catalogue/`, then activate it. No Composer or Node build is required on the WordPress server.

The plugin does not create or publish projects/pages automatically. Use the WordPress Media Library for your own images.

## Create a project

Use the main editor for the project description, and choose its featured image in the editor sidebar. Assign one or more **Project Categories** to make category filters useful; tags can further organise the portfolio.

The extra panels are optional:

| Panel | Available information |
| --- | --- |
| Project information | Client/organisation, location, start date, end date and status |
| Project gallery | Ordered Media Library images, captions, alternative text and featured-image-only mode |
| Contact details and links | Contact name, email, phone, website, social profiles and other labelled links |
| Additional details | Repeated label/value pairs, such as service, sector, technologies, materials or size |
| Events and milestones | Title, date, description and optional event URL |
| Accordion sections | Headings and expandable content for FAQs, specifications, credits or notes |

Empty information is omitted from the public project layout. Dates use `YYYY-MM-DD`; an invalid date is discarded. The end date must not be before the start date. Website and social URLs must start with `https://` or `http://`. Event descriptions and accordion content accept safe basic HTML.

Contact fields are public when displayed on a published project. Use contact information that you intend to publish.

## Add a catalogue with the block editor

1. Edit the homepage or another page.
2. Insert **Portfolio Catalogue** from the block inserter.
3. In the block settings, set columns, projects per page, excerpt length, search and category/tag filters.
4. Optionally search for and select specific projects. Leave the selection empty to query all published projects.
5. Optionally restrict results by category/tag slugs and choose sorting.

The block renders from current project data. Editing a project does not require rebuilding the pages containing the catalogue. Each block stores its own layout options; global defaults mainly apply to shortcodes that omit those options.

## Elementor and other page builders

Add the builder's **Shortcode** widget/module and insert:

```text
[portfolio_catalogue]
```

This works with the free Elementor Shortcode widget; a dedicated Elementor add-on is not required. To edit the description of a project with Elementor, enable **Projects** in Elementor's supported post types. The plugin's custom fields remain in the normal WordPress project edit screen.

For a custom single-project builder template, turn off **Projects → Settings → Automatic project layout**, then position the gallery and details shortcodes where needed. The plugin does not replace theme files or install a theme-builder template.

Builders that filter or do not execute shortcodes must use their supported shortcode element. Compatibility with every third-party theme or builder version is not implied by the integration API.

## Shortcodes

### Portfolio catalogue

```text
[portfolio_catalogue]
[portfolio_catalogue columns="3" per_page="6"]
[portfolio_catalogue category="web-design,interiors" tag="featured"]
[portfolio_catalogue ids="12,34,56" filters="false" search="false"]
[portfolio_catalogue orderby="title" order="ASC" excerpt_words="30"]
```

| Attribute | Meaning | Default |
| --- | --- | --- |
| `columns` | Desktop columns, 1–4 | Site setting, initially 3 |
| `per_page` | Projects per results page, 1–48 | Site setting, initially 6 |
| `category` | Restrict to comma-separated project category slugs | All categories |
| `tag` | Restrict to comma-separated project tag slugs | All tags |
| `ids` | Restrict to comma-separated project IDs | All published projects |
| `filters` | Show category and tag controls: `true` or `false` | `true` |
| `search` | Show text search: `true` or `false` | `true` |
| `orderby` | `date`, `title` or `menu_order` | `date` |
| `order` | `ASC` or `DESC` | `DESC` |
| `excerpt_words` | Excerpt length, 0–80; 0 hides the excerpt | Site setting, initially 22 |

Project IDs, category restrictions and tag restrictions are combined. Multiple categories in one `category` attribute are alternatives; an additional tag restriction must also match. Search covers standard WordPress post title, excerpt and content, not custom contact/detail metadata. Project start dates do not determine `orderby="date"`; that option uses the WordPress publication date.

Multiple catalogues on the same page maintain independent controls. Initial results and fallback pagination are rendered on the server; JavaScript enhances filtering and loading. Draft, private and password-protected projects are excluded from catalogue queries and the plugin's public results endpoint.

### Project gallery

```text
[portfolio_gallery]
[portfolio_gallery id="123"]
```

Without `id`, use the current project. The featured image is shown first, followed by the selected gallery images, with duplicates skipped. The featured-image-only option hides the gallery without deleting saved images. Captions and alternative text come from the Media Library.

### Project display

```text
[portfolio_project id="123"]
[portfolio_project title="false"]
[portfolio_project title="false" content="false" gallery="false"]
```

`id` defaults to the current project. The `title`, `content`, `gallery` and `details` switches default to `true`. The third example displays the structured project details only. To build a manual layout, use the WordPress/editor content where desired, then place `[portfolio_gallery]` and the details-only shortcode separately.

### Existing shortcodes

- `[project_gallery]` aliases `[portfolio_catalogue]`.
- `[project_gallery posts_per_page="6"]` remains supported.
- `[project_images]` aliases `[portfolio_gallery]`.
- Existing `post-project-gallery/selected-projects` blocks keep showing their saved selection. An empty legacy selection shows no catalogue.

## Settings and appearance

**Projects → Settings** controls the shortcode defaults, accent colour, card radius, card gap, automatic project layout, return-link destination and uninstall clean-up preference. Choosing a catalogue page does not insert a shortcode into it automatically.

Styles are scoped to plugin components. The theme still controls the page header, title, surrounding width and footer. If your theme also renders a featured image on single projects, disable that theme's featured-image display or use a custom template to avoid showing the image twice.

## Upgrade from Post Project Gallery

1. Back up the site and test the change on staging.
2. Deactivate **Post Project Gallery**. Do not run both implementations together: WP Portfolio Catalogue pauses itself while the original plugin is active.
3. Install and activate WP Portfolio Catalogue.
4. Existing `project` posts, descriptions, URLs, featured images and supported gallery metadata are read in place. No conversion or database import is required.
5. Review **Projects → Settings**, then assign project categories to enable filtering.
6. Existing shortcodes and saved Selected Projects blocks remain registered. You can switch to the new block/shortcodes gradually.

The original plugin's gallery metadata is left intact. New gallery edits are stored separately; explicitly clearing a gallery prevents old images from returning. Bundled legacy placeholder images are retained so old demo galleries do not depend on the original plugin directory. Old theme-specific single-project templates, styling settings and page SEO intro panels are not copied; add page introduction text using normal page blocks.

The generic `project` content type is retained for compatibility. If another plugin registers a different Projects content type using that same key, resolve the overlap on staging before enabling both. This plugin does not overwrite an already registered content type.

## Privacy, security and performance

Project information is stored in the site's normal WordPress posts, post metadata and taxonomy tables. The plugin creates no custom database tables, trackers, third-party account connections or telemetry. External URLs are normal links supplied by site editors. Legacy image URLs may still point to the external hosts originally chosen by the site owner.

Editing uses WordPress capabilities and nonces. Output is escaped and rich text is filtered through WordPress's allowed HTML rules. The public REST endpoint reads only published, unprotected projects and has bounded pagination; it intentionally does not rely on expiring visitor nonces, so cached pages keep working.

The plugin uses WordPress query and object-cache APIs through normal post queries. It does not install an object-cache drop-in or provide a separate server cache. Images use WordPress attachment sizes where available, and frontend code has no slider framework dependency.

## Deactivation and deletion

Deactivation keeps all data. Deleting the plugin also keeps its data by default.

If **Uninstall clean-up** was explicitly enabled, uninstall removes this plugin's settings, structured project metadata, gallery selections and project taxonomy terms. It still preserves project posts, descriptions, featured images, Media Library attachments and original Post Project Gallery metadata. Reinstalling the original plugin can therefore reveal its original gallery selection again.

## Translation

Text domain: `wp-portfolio-catalogue`. A translation template is included under `languages/`. Use standard WordPress translation tools or WordPress.org community translations after directory approval. Built-in interface text is English; translated projects and multilingual query behaviour depend on the site's multilingual setup.

## Development and support

The plugin ships readable PHP, CSS and JavaScript source. Its frontend uses WordPress APIs and browser features; the block editor uses WordPress-provided packages. There is no runtime build step.

- Runtime classes: `includes/`
- Frontend/admin/editor assets: `assets/`
- Block metadata: `blocks/catalogue/block.json`
- WordPress.org readme: `readme.txt`
- Release checks and test scope: `docs/TESTING.md`
- Contribution guide: `CONTRIBUTING.md`
- Attribution: `NOTICE.txt`

Report reproducible problems using [GitHub Issues](https://github.com/raivis-kalnins/wp-portfolio-catalogue/issues), including WordPress/PHP versions, theme, builder, shortcode, expected behaviour and actual result. Do not post credentials or private project data.

## WordPress.org

The package includes the standard plugin header, readme, licence, translation source and submission notes. It has not been submitted to or approved by WordPress.org. The repository owner should verify their WordPress.org contributor account, perform the final checks against their target environments, and follow [the submission guide](docs/WORDPRESS-ORG.md).

## Changelog

### 1.0.0

Initial WP Portfolio Catalogue release: project fields, categories and tags, filterable catalogue, dynamic block, builder shortcodes, local touch galleries, events, accordion sections and legacy gallery compatibility.
