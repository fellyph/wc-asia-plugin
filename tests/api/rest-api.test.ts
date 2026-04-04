import { describe, it, expect, beforeAll, afterAll } from "vitest";
import { runCLI } from "@wp-playground/cli";

let server: Awaited<ReturnType<typeof runCLI>>;

// WP Playground auto-login middleware redirects requests without this cookie.
const headers = { Cookie: "playground_auto_login_already_happened=1" };

beforeAll(async () => {
  server = await runCLI({
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
            wc_asia_demo_greeting: "Hello from Blueprint!",
          },
        },
      ],
    },
  });
});

afterAll(async () => {
  server?.server?.close();
});

describe("WC Asia Demo REST API", () => {
  it("greeting endpoint returns 200", async () => {
    const response = await fetch(
      `${server.serverUrl}/wp-json/wc-asia-demo/v1/greeting`,
      { headers }
    );

    expect(response.status).toBe(200);
  });

  it("greeting endpoint returns correct data structure", async () => {
    const response = await fetch(
      `${server.serverUrl}/wp-json/wc-asia-demo/v1/greeting`,
      { headers }
    );
    const data = await response.json();

    expect(data).toHaveProperty("greeting");
    expect(data).toHaveProperty("plugin", "wc-asia-demo");
    expect(data).toHaveProperty("version", "1.0.0");
  });

  it("greeting reflects Blueprint site option", async () => {
    const response = await fetch(
      `${server.serverUrl}/wp-json/wc-asia-demo/v1/greeting`,
      { headers }
    );
    const data = await response.json();

    expect(data.greeting).toBe("Hello from Blueprint!");
  });

  it("WordPress REST API root is accessible", async () => {
    const response = await fetch(`${server.serverUrl}/wp-json/`, { headers });

    expect(response.status).toBe(200);

    const data = await response.json();
    expect(data).toHaveProperty("name");
    expect(data).toHaveProperty("namespaces");
    expect(data.namespaces).toContain("wc-asia-demo/v1");
  });
});
