=== WP Portfolio Catalogue ===
Tags: portfolio, projects, gallery, shortcode, block
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publish a filterable project portfolio with a block or shortcode, image galleries, flexible project information, contacts, events and accordions.

== Description ==

WP Portfolio Catalogue adds a native Projects content type and a flexible portfolio catalogue. Display it on your homepage or any page using the Portfolio Catalogue block, or place [portfolio_catalogue] in a shortcode widget in Elementor or another page builder.

Create projects with a title, description, featured image and an optional ordered gallery. Each project can have its own start/end dates, status, client, location, contact information, social links, custom project facts, events and accordion sections. Empty optional fields are hidden.

= Features =

* Projects, project categories and project tags.
* Category filters, text search, pagination and responsive cards.
* A dynamic Portfolio Catalogue block with project selection and display controls.
* Shortcodes for WordPress, Elementor, Divi and other shortcode-capable builders.
* Ordered image galleries with touch scrolling, arrows and thumbnails.
* Repeatable project details, social/external links, events and accordions.
* Native WordPress editor panels and Media Library controls.
* Local CSS/JavaScript, with no external slider library, API key or subscription.
* Translation-ready interface and GPL-2.0-or-later licensing.
* Compatibility with supported Post Project Gallery project data, shortcodes and Selected Projects blocks.

= Quick examples =

[portfolio_catalogue]
[portfolio_catalogue columns="3" per_page="6"]
[portfolio_catalogue category="web-design" orderby="title" order="ASC"]
[portfolio_catalogue ids="12,34,56" filters="false" search="false"]
[portfolio_gallery id="123"]
[portfolio_project id="123"]
[portfolio_project title="false" content="false" gallery="false"]

Catalogue options include columns, per_page, category, tag, ids, filters, search, orderby, order and excerpt_words. Per-page counts are limited to 48. The gallery and project shortcodes use the current project when no ID is supplied. Project display switches title, content, gallery and details accept true/false.

= Privacy =

The plugin stores data in the site's WordPress database and uses the site's Media Library. It does not create tracking cookies, collect telemetry or connect to a third-party service. Links and legacy image URLs supplied by site editors may lead to external websites. Contact details entered for public projects are intended for public display.

Draft, private and password-protected projects are excluded from the public catalogue. Native WordPress permissions and nonces protect editing. The public filter endpoint is read-only and does not use an expiring visitor nonce.

= Compatibility =

The plugin retains the project content-type key and /projects/ permalink structure from Post Project Gallery. Deactivate the original plugin before activating this implementation. Existing project_gallery and project_images shortcodes and saved Selected Projects blocks are supported. Theme-specific layouts and old styling settings are not imported.

The theme controls the surrounding page layout. A builder must support normal WordPress shortcodes. See the included README.md for full configuration, migration notes and shortcode documentation.

= Source =

Readable source, documentation and issue tracking are available at https://github.com/raivis-kalnins/wp-portfolio-catalogue.

== Installation ==

1. Upload the wp-portfolio-catalogue directory to wp-content/plugins/, or install the release ZIP using Plugins > Add New Plugin > Upload Plugin.
2. Activate WP Portfolio Catalogue.
3. Create a project under Projects > Add New, add a featured image and assign categories.
4. Insert the Portfolio Catalogue block or [portfolio_catalogue] into a page.
5. Configure default layout and project-page options under Projects > Settings.

For an existing Post Project Gallery site, back up first and deactivate the original plugin. Projects and supported gallery metadata are retained; no data import is required.

== Frequently Asked Questions ==

= Does it work with Elementor Free? =

Use Elementor's Shortcode widget with [portfolio_catalogue]. No dedicated Elementor add-on is required. To edit project descriptions using Elementor, enable Projects in Elementor's supported post types. Custom project metadata is edited in the standard WordPress project screen.

= Can I show selected projects on the homepage? =

Yes. Select projects in the Portfolio Catalogue block or use [portfolio_catalogue ids="12,34,56"]. Category and tag restrictions can be combined with an ID selection.

= Do projects have to use every field? =

No. All extra fields are optional. Empty contact, date, event, link and accordion information is omitted from the public display.

= Can I build my own single-project template? =

Yes. Disable Automatic project layout in Projects > Settings, then place [portfolio_gallery] and [portfolio_project title="false" content="false" gallery="false"] where needed. The latter displays only structured project details.

= Does the catalogue work without JavaScript? =

The initial project grid, filter forms and pagination are server-rendered. JavaScript adds asynchronous filtering and gallery controls. Native gallery scrolling and HTML accordion sections remain usable without the enhancement.

= Why is the featured image shown twice on my project page? =

Some themes display a separate featured image before the project content. Disable that display in your theme or use your own template when the plugin gallery already contains the image.

= What happens when I uninstall? =

By default, all data is retained. The optional Uninstall clean-up setting removes plugin-specific settings, metadata and taxonomy data. Project posts, descriptions, featured images, media attachments and original Post Project Gallery data are always retained.

= Does the plugin add analytics or remote scripts? =

No. The plugin serves its own assets locally and does not include telemetry. Legacy image URLs and external links entered by site editors remain under the site's control.

== Changelog ==

= 1.0.0 =
* Initial release with project information, galleries, categories and tags.
* Added searchable catalogue, dynamic block and builder shortcodes.
* Added contact details, social links, custom facts, events and accordion sections.
* Added local touch gallery controls, responsive styles and legacy compatibility.

== Upgrade Notice ==

= 1.0.0 =
Initial release. Sites using Post Project Gallery should deactivate that plugin before enabling WP Portfolio Catalogue. Existing project content is retained.
