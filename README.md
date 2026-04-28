# WC Asia Demo Plugin

A WordPress plugin demonstrating a testing pipeline using [WordPress Playground](https://wordpress.org/playground/), [Playwright](https://playwright.dev/), [Vitest](https://vitest.dev/), and GitHub Actions — including an AI agent fix loop with Gemini CLI and GitHub Copilot.

## Plugin Features

- **Settings page** — Configure an API key and a greeting message at `Settings > WC Asia Demo`
- **REST API endpoint** — `GET /wp-json/wc-asia-demo/v1/greeting` returns the greeting message as JSON
- **Shortcode** — `[wc_asia_greeting]` renders the greeting message on any post or page
- **Internationalization** — All strings are translatable via `wc-asia-demo` text domain
- **PR Preview** — Every pull request gets a "Preview in Playground" button automatically

## Requirements

- WordPress 6.3+
- PHP 8.0+
- Node.js 20+ (for running tests)

## Getting Started

### Download & Install (Recommended)

Download the latest `wc-asia-demo.zip` from the [GitHub Releases page](https://github.com/fellyph/wc-asia-plugin/releases/latest) and install it via **Plugins → Add New → Upload Plugin** in your WordPress admin.

### Installation from Source

```bash
git clone https://github.com/fellyph/wc-asia-plugin.git
cd wc-asia-plugin
npm install
npx playwright install chromium
```

### Local Development with WordPress Playground

Start a local WordPress instance with the plugin mounted and activated:

```bash
npx wp-playground server \
  --mount ./plugin:/wordpress/wp-content/plugins/wc-asia-demo \
  --blueprint blueprint.json
```

The site will be available at `http://127.0.0.1:9400`. The blueprint auto-logs you in, activates the plugin, and creates a "Greeting Page" with the shortcode.

### Using the Plugin in Your Own Site

1. Copy the `plugin/` folder into your WordPress `wp-content/plugins/` directory
2. Activate "WC Asia Demo" from the WordPress admin
3. Go to `Settings > WC Asia Demo` to configure your greeting message
4. Add `[wc_asia_greeting]` to any post or page to display the greeting
5. Access `GET /wp-json/wc-asia-demo/v1/greeting` for the JSON response

## Running Tests

```bash
# Run all tests (API + E2E)
npm test

# Run API tests only (Vitest + WordPress Playground)
npm run test:api

# Run E2E tests only (Playwright + WordPress Playground)
npm run test:e2e
```

### API Tests

Located in `tests/api/`. These use Vitest and `@wp-playground/cli` to spin up a WordPress instance and test the REST API endpoint with `fetch()`. No browser required.

### E2E Tests

Located in `tests/e2e/`. These use Playwright to open a real browser against a WordPress Playground instance and test:

- Settings page loads correctly
- Saving settings persists values
- Shortcode renders the greeting on the front-end
- Greeting updates after changing settings

### Debugging E2E Tests

```bash
# Step through tests with Playwright Inspector
npx playwright test --debug

# Run in UI mode
npx playwright test --ui

# View trace from a failed test
npx playwright show-trace test-results/<test-folder>/trace.zip
```

## CI/CD Workflows

### Release Pipeline (`.github/workflows/release.yml`)

Triggered when a `v*` tag is pushed (e.g., `v1.0.0`). The workflow:

1. Runs the full test suite (API + E2E)
2. Builds `wc-asia-demo.zip` from the `plugin/` directory
3. Creates a GitHub Release and attaches the zip as a downloadable artifact

### Testing & AI Fix Loop (`.github/workflows/ai-fix-loop.yml`)

Runs API and E2E tests on every push and pull request to `main`. On pull requests, if tests fail:

1. Captures the test output
2. Analyzes failures using the [`google-github-actions/run-gemini-cli`](https://github.com/google-github-actions/run-gemini-cli) GitHub Action
3. Posts a comment on the PR with the analysis and tags `@copilot` to fix the code
4. Copilot pushes a fix, tests run again — the loop continues until tests pass

**Required secret:** `GEMINI_API_KEY` — obtain from [Google AI Studio](https://aistudio.google.com/apikey) and add it in your repository settings under `Settings > Secrets and variables > Actions`.

### PR Preview (`.github/workflows/pr-preview.yml`)

Adds a "Preview in Playground" button to every pull request description using the [WordPress Playground PR Preview action](https://github.com/WordPress/action-wp-playground-pr-preview). Reviewers can test the plugin directly in the browser without any local setup.

## Plugin Architecture

The plugin follows WordPress best practices:

- **Single bootstrap entry point** — all hooks registered from `plugins_loaded`
- **Admin isolation** — admin-only hooks loaded behind `is_admin()` check
- **Settings API** — uses `register_setting()`, `add_settings_section()`, and `add_settings_field()` with `sanitize_callback`
- **Security** — capability checks (`manage_options`), input sanitization, output escaping
- **Lifecycle hooks** — `register_activation_hook` / `register_deactivation_hook` for setup and cleanup
- **Clean uninstall** — `uninstall.php` removes all plugin options from the database
- **i18n ready** — all strings wrapped with `__()` / `esc_html__()`, text domain loaded via `load_plugin_textdomain()`

## Project Structure

```
├── plugin/
│   ├── wc-asia-demo.php        # WordPress plugin
│   ├── uninstall.php           # Clean uninstall handler
│   └── languages/              # Translation files (.po/.mo)
├── tests/
│   ├── api/
│   │   └── rest-api.test.ts    # Vitest API tests
│   └── e2e/
│       └── plugin.spec.ts      # Playwright E2E tests
├── .github/workflows/
│   ├── release.yml             # Release pipeline (tag → build zip → GitHub Release)
│   ├── ai-fix-loop.yml         # Testing pipeline + AI agent fix loop
│   └── pr-preview.yml          # Playground preview button
├── blueprint.json              # WP Playground blueprint
├── vitest.config.ts
├── playwright.config.ts
└── package.json
```

## Internationalization

The plugin is translation-ready. To generate a `.pot` file:

```bash
wp i18n make-pot plugin/ plugin/languages/wc-asia-demo.pot
```

To create a translation (e.g., Portuguese):

```bash
wp i18n make-json plugin/languages/wc-asia-demo-pt_BR.po plugin/languages/
```

## Presentation Context

This plugin was built for a 30-minute talk at WC Asia about building testing pipelines with WordPress Playground. The `docs/` folder contains upstream WordPress Playground documentation used as reference material.

## License

GPL-2.0-or-later
