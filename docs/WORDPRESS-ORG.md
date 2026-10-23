# Publishing on WordPress.org

WP Portfolio Catalogue is distributed as free, GPL-compatible source code. The installable package is intended for submission to the WordPress.org Plugin Directory. Preparing the package does not mean the directory team has approved it.

## Before submitting

1. Install the release ZIP on a separate WordPress test site. Follow `README.md` and run the checks in `docs/TESTING.md`.
2. Use the official [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin. Review every error and warning; do not assume a clean result guarantees approval.
3. Check `readme.txt` with the official [readme validator](https://wordpress.org/plugins/developers/readme-validator/). Keep the version in the plugin header, `Stable tag`, changelog and release tag consistent.
4. Ensure `Requires at least` and `Requires PHP` appear in the main plugin PHP header. Set `Tested up to` only to a WordPress version on which the release has actually been tested.
5. Verify that any `Contributors` entries are real WordPress.org usernames belonging to the project. A GitHub username is not automatically the same account.
6. Review the included licence and asset attribution. Keep third-party copyright and licence notices with redistributed code or assets. The submitted ZIP must contain the complete, readable source needed to run the plugin.
7. Confirm that the plugin name and requested slug are available and acceptable to the review team. Keep branding clear and avoid suggesting endorsement by WordPress or Elementor.
8. Add real screenshots of the release if desired. WordPress.org marketing banners and icons belong in the repository's separate `assets` directory after approval; they are not required for the plugin to run.

## Submit the first release

Sign in to the WordPress.org account that will own the plugin and use [Add your plugin](https://wordpress.org/plugins/developers/add/). Upload the installable ZIP and provide the requested details. Respond to the review team's email and make any requested changes. Submission and approval are separate actions.

After approval, WordPress.org provides an SVN repository. Keep development history on GitHub, and put the approved release files in the assigned SVN repository. Use a versioned `tags/1.0.0/` directory and a matching `Stable tag: 1.0.0` in `trunk/readme.txt`. Follow the [SVN guide](https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/) when publishing or updating the directory release.

### Name advisory in automated checks

Plugin Check 2.1.0 produces advisories for the `WP` prefix in this project's requested name and slug. Its own `Trademarks_Check` implementation marks `wp` as allowed but warning-producing. The supplied package therefore keeps the requested branding. Confirm the final name and slug with the directory review team; an automated advisory is not a directory approval or rejection.

## Project commitments

- The plugin's executable front-end assets are local. It does not need a remote CDN, paid service or API key.
- Core features remain free and do not expire.
- Project contact details and links are content deliberately published by the site owner. Add only information that should appear publicly.
- The plugin adds no analytics tracking, advertising, hidden network service or automatic public credit link.
- Administrator actions use WordPress permissions and request verification. Public catalogue requests are read-only and restricted to published, non-password-protected projects.
- Deactivation preserves project content. The optional uninstall cleanup affects this plugin's settings, metadata and taxonomy data, while retaining the project posts and uploaded media.

## Official references

- [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Plugin readme format](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [Plugin Check](https://wordpress.org/plugins/plugin-check/)
- [Plugin security](https://developer.wordpress.org/plugins/security/)
- [Block metadata](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/)

The directory's requirements and review process can change. Review these official pages again before submitting a new release.
