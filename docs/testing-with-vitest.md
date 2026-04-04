---
title: Server-Side Testing with Vitest and WordPress Playground
slug: /guides/testing-with-vitest
description: Set up server-side and API integration tests for WordPress plugins using Vitest and the Playground CLI.
sidebar_class_name: navbar-build-item
---

Server-side testing verifies that your WordPress plugin's REST API, custom endpoints, and HTTP behavior work correctly — without opening a browser. This guide shows how to combine [Vitest](https://vitest.dev/) with the [WordPress Playground CLI](/developers/local-development/wp-playground-cli) to write fast, reliable API tests with zero infrastructure.

How does this differ from browser testing? The [E2E Testing with Playwright](/guides/e2e-testing-with-playwright) guide covers user-facing behavior — clicking buttons, filling forms, checking rendered pages. This guide covers everything that happens on the server: REST API responses, custom endpoint logic, HTTP headers, and Blueprint state validation.

:::info
This guide assumes familiarity with WordPress plugin development. For an introduction to using Playground in your development workflow, see [WordPress Playground for Plugin Developers](/guides/for-plugin-developers). For Blueprint configuration details, see [Blueprints Getting Started](/blueprints/getting-started).
:::

## Prerequisites

- **Node.js 20+** and npm
- Basic familiarity with JavaScript or TypeScript
- A WordPress plugin or theme to test

No browser installation needed — Vitest tests run entirely over HTTP.

## Project setup

### Install dependencies

From your plugin or theme root directory:

```bash
npm init -y
npm install --save-dev vitest @wp-playground/cli
```

Unlike Playwright, there is no browser to download. The Playground CLI provides the WordPress server, and Vitest handles test execution.

### Configure Vitest

Create a `vitest.config.ts` file in your project root:

```typescript
import { defineConfig } from "vitest/config";

export default defineConfig({
  test: {
    testTimeout: 120_000,
    hookTimeout: 120_000,
    pool: "forks",
    poolOptions: {
      forks: {
        singleFork: true,
      },
    },
    include: ["tests/api/**/*.test.ts"],
  },
});
```

WordPress Playground needs time to boot. The 120-second timeouts account for WordPress initialization. The `singleFork: true` setting runs tests sequentially in a single process, preventing port conflicts when multiple tests share a Playground server.

:::tip
If your CI environment is slower, increase `testTimeout` and `hookTimeout` to 180,000 or higher. Start with 120 seconds and adjust based on your pipeline.
:::

### First test file

Create `tests/api/rest-api.test.ts`:

```typescript
import { describe, it, expect, beforeAll, afterAll } from "vitest";
import { runCLI } from "@wp-playground/cli";

let server: Awaited<ReturnType<typeof runCLI>>;

beforeAll(async () => {
  server = await runCLI({
    command: "server",
    blueprint: {
      preferredVersions: { php: "8.3", wp: "latest" },
      login: true,
    },
  });
});

afterAll(async () => {
  await server?.stop();
});

describe("WordPress REST API", () => {
  it("returns posts from the REST API", async () => {
    const response = await fetch(`${server.url}/wp-json/wp/v2/posts`);

    expect(response.status).toBe(200);

    const posts = await response.json();
    expect(Array.isArray(posts)).toBe(true);
  });
});
```

Run the test:

```bash
npx vitest run
```

## Writing tests

### Starting a Playground server

The `runCLI` function starts a local Playground server and returns a handle with the server URL. This is the same function used in the [Playwright guide](/guides/e2e-testing-with-playwright) — the only difference is what you do with the URL. Instead of navigating a browser, you call `fetch` directly.

```typescript
const server = await runCLI({
  command: "server",
  blueprint: {
    preferredVersions: { php: "8.3", wp: "latest" },
    login: true,
    steps: [
      {
        step: "installPlugin",
        pluginData: {
          resource: "wordpress.org/plugins",
          slug: "woocommerce",
        },
      },
    ],
  },
});
```

### Server lifecycle: shared vs. per-test

**Shared server (`beforeAll`/`afterAll`)** — one Playground instance serves all tests in a describe block. Use this for read-only API calls where tests do not modify state:

```typescript
describe("Plugin REST endpoints", () => {
  beforeAll(async () => {
    server = await runCLI({ command: "server", blueprint });
  });
  afterAll(async () => {
    await server?.stop();
  });
  // All tests share the same WordPress instance
});
```

**Per-test server (`beforeEach`/`afterEach`)** — each test gets a fresh instance. Use this when tests create, update, or delete data:

```typescript
beforeEach(async () => {
  server = await runCLI({ command: "server", blueprint });
});
afterEach(async () => {
  await server?.stop();
});
```

Shared servers run faster because WordPress boots once. Per-test servers provide full isolation at the cost of boot time per test.

### Authentication for REST API

WordPress REST API endpoints that modify data require authentication. Use a Blueprint to create an application password, then include it in your fetch requests.

```typescript
let server: Awaited<ReturnType<typeof runCLI>>;
let authHeader: string;

beforeAll(async () => {
  server = await runCLI({
    command: "server",
    blueprint: {
      preferredVersions: { php: "8.3", wp: "latest" },
      login: true,
      steps: [
        {
          step: "runPHP",
          code: `<?php
            require '/wordpress/wp-load.php';
            $user = get_user_by('login', 'admin');
            $app_password = WP_Application_Passwords::create_new_application_password(
              $user->ID,
              ['name' => 'vitest']
            );
            file_put_contents('/tmp/app-password.txt', $app_password[0]);
          `,
        },
      ],
    },
  });

  // Read the generated application password
  const passwordResponse = await fetch(
    `${server.url}/wp-content/mu-plugins/../../tmp/app-password.txt`
  );

  // Alternative: use runPHP to read the file and return it
  // For simplicity, use Basic auth with admin:password in Playground
  authHeader = "Basic " + btoa("admin:password");
});
```

A simpler approach works for Playground instances because the default admin password is known:

```typescript
const authHeader = "Basic " + btoa("admin:password");

it("creates a post via REST API", async () => {
  const response = await fetch(`${server.url}/wp-json/wp/v2/posts`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: authHeader,
    },
    body: JSON.stringify({
      title: "Test Post",
      content: "Hello from Vitest",
      status: "publish",
    }),
  });

  expect(response.status).toBe(201);

  const post = await response.json();
  expect(post.title.rendered).toBe("Test Post");
});
```

### Blueprints as test fixtures

Blueprints define the WordPress state each test needs. For full Blueprint patterns (installing from GitHub, creating content, setting options), see the [E2E Testing with Playwright guide](/guides/e2e-testing-with-playwright#using-blueprints-as-test-fixtures) — the same patterns apply here.

Here is a Blueprint that installs a local plugin with a custom REST endpoint:

```typescript
const server = await runCLI({
  command: "server",
  mount: {
    "./": "/wordpress/wp-content/plugins/my-plugin",
  },
  blueprint: {
    preferredVersions: { php: "8.3", wp: "latest" },
    login: true,
    steps: [
      {
        step: "activatePlugin",
        pluginPath: "my-plugin/my-plugin.php",
      },
    ],
  },
});
```

## Testing patterns

### REST API response validation

Test the structure and content of WordPress REST API responses:

```typescript
describe("REST API responses", () => {
  it("returns posts with expected fields", async () => {
    const response = await fetch(`${server.url}/wp-json/wp/v2/posts`);
    const posts = await response.json();

    expect(response.status).toBe(200);
    expect(response.headers.get("content-type")).toContain("application/json");

    if (posts.length > 0) {
      expect(posts[0]).toHaveProperty("id");
      expect(posts[0]).toHaveProperty("title");
      expect(posts[0]).toHaveProperty("content");
      expect(posts[0]).toHaveProperty("status");
    }
  });

  it("respects pagination headers", async () => {
    const response = await fetch(
      `${server.url}/wp-json/wp/v2/posts?per_page=1`
    );

    expect(response.headers.get("X-WP-Total")).toBeDefined();
    expect(response.headers.get("X-WP-TotalPages")).toBeDefined();
  });
});
```

### Custom plugin endpoint testing

Mount your plugin and test its custom REST routes:

```typescript
describe("Custom plugin endpoints", () => {
  let pluginServer: Awaited<ReturnType<typeof runCLI>>;

  beforeAll(async () => {
    pluginServer = await runCLI({
      command: "server",
      mount: {
        "./": "/wordpress/wp-content/plugins/my-plugin",
      },
      blueprint: {
        preferredVersions: { php: "8.3", wp: "latest" },
        login: true,
        steps: [
          {
            step: "activatePlugin",
            pluginPath: "my-plugin/my-plugin.php",
          },
        ],
      },
    });
  });

  afterAll(async () => {
    await pluginServer?.stop();
  });

  it("registers the custom endpoint", async () => {
    const response = await fetch(
      `${pluginServer.url}/wp-json/my-plugin/v1/items`
    );

    expect(response.status).toBe(200);

    const data = await response.json();
    expect(Array.isArray(data)).toBe(true);
  });

  it("returns 401 for unauthenticated write requests", async () => {
    const response = await fetch(
      `${pluginServer.url}/wp-json/my-plugin/v1/items`,
      {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: "Test Item" }),
      }
    );

    expect(response.status).toBe(401);
  });
});
```

### Blueprint state validation

Use Blueprints to set WordPress state, then verify it through the REST API:

```typescript
describe("Blueprint state validation", () => {
  let stateServer: Awaited<ReturnType<typeof runCLI>>;

  beforeAll(async () => {
    stateServer = await runCLI({
      command: "server",
      blueprint: {
        preferredVersions: { php: "8.3", wp: "latest" },
        login: true,
        steps: [
          {
            step: "setSiteOptions",
            options: {
              blogname: "Vitest Blog",
              blogdescription: "Testing with Vitest",
            },
          },
          {
            step: "runPHP",
            code: `<?php
              require '/wordpress/wp-load.php';
              wp_insert_post([
                'post_title' => 'Blueprint Post',
                'post_content' => 'Created by Blueprint',
                'post_status' => 'publish',
              ]);
            `,
          },
        ],
      },
    });
  });

  afterAll(async () => {
    await stateServer?.stop();
  });

  it("applies site options from Blueprint", async () => {
    const response = await fetch(`${stateServer.url}/wp-json/`);
    const data = await response.json();

    expect(data.name).toBe("Vitest Blog");
    expect(data.description).toBe("Testing with Vitest");
  });

  it("creates content from Blueprint", async () => {
    const response = await fetch(
      `${stateServer.url}/wp-json/wp/v2/posts?search=Blueprint+Post`
    );
    const posts = await response.json();

    expect(posts.length).toBeGreaterThan(0);
    expect(posts[0].title.rendered).toBe("Blueprint Post");
  });
});
```

### HTTP behavior testing

Test redirects, status codes, and HTTP headers:

```typescript
describe("HTTP behavior", () => {
  it("redirects non-trailing-slash URLs", async () => {
    const response = await fetch(`${server.url}/wp-admin`, {
      redirect: "manual",
    });

    expect(response.status).toBe(301);
    expect(response.headers.get("location")).toContain("/wp-admin/");
  });

  it("returns 404 for missing pages", async () => {
    const response = await fetch(`${server.url}/wp-json/wp/v2/posts/99999`);

    expect(response.status).toBe(404);
  });

  it("includes security headers", async () => {
    const response = await fetch(`${server.url}/wp-admin/`, {
      redirect: "manual",
    });

    expect(response.headers.get("x-frame-options")).toBeDefined();
  });
});
```

## Testing across PHP and WordPress versions

Parameterized tests cover multiple version combinations without duplicating test code:

```typescript
const versionMatrix = [
  { php: "8.1", wp: "6.5" },
  { php: "8.2", wp: "6.7" },
  { php: "8.3", wp: "latest" },
];

for (const { php, wp } of versionMatrix) {
  describe(`PHP ${php} + WP ${wp}`, () => {
    let versionServer: Awaited<ReturnType<typeof runCLI>>;

    beforeAll(async () => {
      versionServer = await runCLI({
        command: "server",
        mount: {
          "./": "/wordpress/wp-content/plugins/my-plugin",
        },
        blueprint: {
          preferredVersions: { php, wp },
          login: true,
          steps: [
            {
              step: "activatePlugin",
              pluginPath: "my-plugin/my-plugin.php",
            },
          ],
        },
      });
    });

    afterAll(async () => {
      await versionServer?.stop();
    });

    it("REST API responds without errors", async () => {
      const response = await fetch(
        `${versionServer.url}/wp-json/my-plugin/v1/items`
      );

      expect(response.status).toBe(200);
    });

    it("site root returns 200", async () => {
      const response = await fetch(versionServer.url);

      expect(response.status).toBe(200);
    });
  });
}
```

The `preferredVersions` property controls which PHP and WordPress versions the Playground instance uses. Supported ranges: PHP 7.0–8.4, WordPress 6.3–6.8+, plus `latest`, `nightly`, and `beta`.

## Running tests in CI/CD

### GitHub Actions

Create `.github/workflows/api-tests.yml`:

```yaml
name: API Tests

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  api-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: actions/setup-node@v4
        with:
          node-version: 20
          cache: "npm"

      - name: Install dependencies
        run: npm ci

      - name: Run API tests
        run: npx vitest run
```

This workflow is simpler than the Playwright equivalent — no browser installation, no `--with-deps` flag, no test report artifacts. Vitest runs entirely in Node.js.

## Combining with Playwright

Use both tools together for complete test coverage. Add scripts to your `package.json`:

```json
{
  "scripts": {
    "test:api": "vitest run",
    "test:e2e": "playwright test",
    "test": "vitest run && playwright test"
  }
}
```

Clear division of responsibilities:
- **Vitest**: REST API responses, HTTP headers, status codes, custom endpoints, Blueprint state
- **Playwright**: page rendering, form submissions, admin UI, visual behavior

Both tools share the same `runCLI` setup from `@wp-playground/cli` and the same Blueprint patterns.

## Troubleshooting

**Timeout errors** — Increase `testTimeout` and `hookTimeout` in `vitest.config.ts`. WordPress boot time varies by environment. CI runners often need 120–180 seconds.

**Port conflicts** — Ensure `singleFork: true` in your Vitest config. This prevents multiple test processes from starting Playground servers simultaneously.

**Authentication failures** — Verify your Blueprint includes `login: true`. For write operations, use Basic auth with the default admin credentials or create an application password via a `runPHP` Blueprint step.

**Fetch errors** — Make sure the server has fully started before making requests. Place `runCLI` in `beforeAll` and await it — Vitest waits for the promise to resolve before running tests.

**WordPress not loading** — Check your Blueprint syntax against the [Blueprint schema](https://playground.wordpress.net/blueprint-schema.json). Invalid steps can fail silently.

## Next steps

- [E2E Testing with Playwright](/guides/e2e-testing-with-playwright) — browser-based testing with the same Playground setup
- [WordPress Playground CLI documentation](/developers/local-development/wp-playground-cli) — full CLI reference
- [Blueprints reference](/blueprints/steps) — all available Blueprint steps
- [WordPress Playground for Plugin Developers](/guides/for-plugin-developers) — development workflow guide
