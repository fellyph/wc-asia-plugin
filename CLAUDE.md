# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A WordPress plugin ("WC Asia Demo") built to demonstrate a testing pipeline using WordPress Playground. Created for a 30-minute presentation at WC Asia covering: WP Playground intro, Playwright/Vitest testing, and AI agent automation (Copilot + Gemini CLI fix loops).

## Commands

```bash
npm test              # Run all tests (API + E2E)
npm run test:api      # Run Vitest API tests only
npm run test:e2e      # Run Playwright E2E tests only
npx vitest run tests/api/rest-api.test.ts   # Run a single API test file
npx playwright test tests/e2e/plugin.spec.ts  # Run a single E2E test file
npx playwright test --debug   # Debug E2E tests with inspector
```

## Architecture

**Plugin** (`plugin/wc-asia-demo.php`): Single-file WordPress plugin with three testable surfaces:
- Settings page at `/wp-admin/options-general.php?page=wc-asia-demo` (API Key + Greeting Message fields)
- REST endpoint at `/wp-json/wc-asia-demo/v1/greeting` (public, returns greeting + plugin metadata)
- Shortcode `[wc_asia_greeting]` renders a `<div data-testid="wc-asia-greeting">` on the front-end

**API Tests** (`tests/api/`): Vitest tests using `@wp-playground/cli` `runCLI()` to spin up a WordPress instance and test REST API responses via `fetch`. Requires `Cookie: playground_auto_login_already_happened=1` header to bypass WP Playground's auto-login redirect middleware.

**E2E Tests** (`tests/e2e/`): Playwright tests using `runCLI()` with the same WP Playground setup. Tests interact with admin settings page and verify front-end shortcode output.

**CI Workflows** (`.github/workflows/`):
- `ai-fix-loop.yml` — Consolidated pipeline: runs API and E2E tests on push/PR to main. On PR test failure, uses `google-github-actions/run-gemini-cli@v0` to analyze failures and comments on PR tagging `@copilot` for fixes

## Key Patterns

- Both test suites mount the local `./plugin` directory into WP Playground using `mount: [{ hostPath, vfsPath }]` (array format, not object)
- `runCLI()` returns `{ playground, server, serverUrl }` — cleanup via `server.close()`, URL via `serverUrl`
- Blueprint `steps` handle plugin activation, site options, and content creation (e.g., inserting a page with the shortcode)
- Vitest config uses `singleFork: true` and 120s timeouts to handle WP boot time
- Playwright config uses `workers: 1` and 120s timeout for the same reason

## Docs

The `docs/` folder contains WordPress Playground documentation (from upstream) used as reference for building the tests. These are not project docs — they're reference material for the presentation.
