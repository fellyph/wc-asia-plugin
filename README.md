# WC Asia Demo Plugin

A WordPress plugin demonstrating a testing pipeline using [WordPress Playground](https://wordpress.org/playground/), [Playwright](https://playwright.dev/), [Vitest](https://vitest.dev/), and GitHub Actions — including an AI agent fix loop with Gemini CLI and GitHub Copilot.

## Plugin Features

- **Settings page** — Configure an API key and a greeting message at `Settings > WC Asia Demo`
- **REST API endpoint** — `GET /wp-json/wc-asia-demo/v1/greeting` returns the greeting message as JSON
- **Shortcode** — `[wc_asia_greeting]` renders the greeting message on any post or page

## Getting Started

### Prerequisites

- Node.js 20+
- npm

### Installation

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

### Standard Pipeline (`.github/workflows/e2e-tests.yml`)

Runs API and E2E tests on every push and pull request to `main`.

### AI Fix Loop (`.github/workflows/ai-fix-loop.yml`)

Demonstrates an automated AI agent workflow on pull requests:

1. Runs API and E2E tests
2. If tests fail, captures the test output
3. Gemini CLI analyzes the failures using `if: failure()` conditional
4. Posts a comment on the PR with the analysis and tags `@copilot` to fix the code
5. Copilot pushes a fix, tests run again — the loop continues until tests pass

**Required secret:** `GEMINI_API_KEY` — add it in your repository settings under `Settings > Secrets and variables > Actions`.

## Project Structure

```
├── plugin/
│   └── wc-asia-demo.php        # WordPress plugin
├── tests/
│   ├── api/
│   │   └── rest-api.test.ts    # Vitest API tests
│   └── e2e/
│       └── plugin.spec.ts      # Playwright E2E tests
├── .github/workflows/
│   ├── e2e-tests.yml           # Standard CI pipeline
│   └── ai-fix-loop.yml         # AI agent fix loop
├── blueprint.json              # WP Playground blueprint
├── vitest.config.ts
├── playwright.config.ts
└── package.json
```

## Using the Plugin in Your Own Site

1. Copy the `plugin/` folder into your WordPress `wp-content/plugins/` directory
2. Activate "WC Asia Demo" from the WordPress admin
3. Go to `Settings > WC Asia Demo` to configure your greeting message
4. Add `[wc_asia_greeting]` to any post or page to display the greeting
5. Access `GET /wp-json/wc-asia-demo/v1/greeting` for the JSON response

## Presentation Context

This plugin was built for a 30-minute talk at WC Asia about building testing pipelines with WordPress Playground. The `docs/` folder contains upstream WordPress Playground documentation used as reference material.
