# Contributing

Thank you for helping improve WP Portfolio Catalogue.

## Report a problem

Open a GitHub issue with the WordPress version, PHP version, theme/builder, shortcode or block options, exact steps and expected/actual results. Include a minimal reproduction if possible. Do not include passwords, authentication tokens or private contact information.

For a suspected security vulnerability, use GitHub's private vulnerability reporting if enabled on the repository. If it is not available, contact the maintainer privately using a verified contact route before posting exploit details publicly.

## Development

1. Install the plugin on a disposable WordPress development site.
2. Keep changes scoped to the relevant class/assets; no production dependencies or build step are required.
3. Follow WordPress coding, security, escaping, translation and accessibility conventions.
4. Keep the public results query restricted to published, non-password-protected projects.
5. Preserve existing project data, gallery selections, shortcode contracts and stored block attributes.
6. Test the behaviour changed, including no-JavaScript rendering where relevant.
7. Run PHP syntax checks, JavaScript syntax checks and Plugin Check before submitting a release change.

See `docs/TESTING.md` for the tested release scope and further validation recommendations. Update `README.md`, `readme.txt`, the plugin header and block version consistently when preparing a release.

Contributions must be compatible with GPL-2.0-or-later. Do not add telemetry, remote executable code, undisclosed external services, frontend credit links or assets whose redistribution rights are unclear.
