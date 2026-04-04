import { test, expect } from "@playwright/test";
import { runCLI } from "@wp-playground/cli";

let cli: Awaited<ReturnType<typeof runCLI>>;

test.beforeAll(async () => {
  cli = await runCLI({
    command: "server",
    mount: [
      {
        hostPath: "./plugin",
        vfsPath: "/wordpress/wp-content/plugins/wc-asia-demo",
      },
    ],
    blueprint: {
      preferredVersions: { php: "8.3", wp: "latest" },
      login: true,
      steps: [
        {
          step: "activatePlugin",
          pluginPath: "wc-asia-demo/wc-asia-demo.php",
        },
        {
          step: "setSiteOptions",
          options: {
            wc_asia_demo_greeting: "Hello, WC Asia!",
          },
        },
        {
          step: "runPHP",
          code: `<?php
            require '/wordpress/wp-load.php';
            wp_insert_post([
              'post_title'   => 'Greeting Page',
              'post_name'    => 'greeting',
              'post_content' => '[wc_asia_greeting]',
              'post_status'  => 'publish',
              'post_type'    => 'page',
            ]);
          `,
        },
      ],
    },
  });
});

test.afterAll(async () => {
  cli?.server?.close();
});

test("settings page loads", async ({ page }) => {
  await page.goto(
    `${cli.serverUrl}/wp-admin/options-general.php?page=wc-asia-demo`
  );

  await expect(
    page.getByRole("heading", { name: "WC Asia Demo Settings" })
  ).toBeVisible();
  await expect(page.getByLabel("API Key")).toBeVisible();
  await expect(page.getByLabel("Greeting Message")).toBeVisible();
});

test("save settings persists values", async ({ page }) => {
  await page.goto(
    `${cli.serverUrl}/wp-admin/options-general.php?page=wc-asia-demo`
  );

  await page.getByLabel("API Key").fill("test-key-123");
  await page.getByLabel("Greeting Message").fill("Ola, WC Asia!");
  await page.getByRole("button", { name: "Save Changes" }).click();

  await expect(page.getByText("Settings saved.").first()).toBeVisible();
  await expect(page.getByLabel("API Key")).toHaveValue("test-key-123");
  await expect(page.getByLabel("Greeting Message")).toHaveValue(
    "Ola, WC Asia!"
  );
});

test("shortcode renders greeting on front-end", async ({ page }) => {
  await page.goto(`${cli.serverUrl}/?pagename=greeting`);

  const greeting = page.getByTestId("wc-asia-greeting");
  await expect(greeting).toBeVisible();
  await expect(greeting).toContainText("WC Asia");
});

test("greeting updates on front-end after settings change", async ({
  page,
}) => {
  // Update the greeting via settings page
  await page.goto(
    `${cli.serverUrl}/wp-admin/options-general.php?page=wc-asia-demo`
  );
  await page.getByLabel("Greeting Message").fill("Updated Greeting!");
  await page.getByRole("button", { name: "Save Changes" }).click();
  await expect(page.getByText("Settings saved.").first()).toBeVisible();

  // Verify on front-end
  await page.goto(`${cli.serverUrl}/?pagename=greeting`);
  const greeting = page.getByTestId("wc-asia-greeting");
  await expect(greeting).toContainText("Updated Greeting!");
});
