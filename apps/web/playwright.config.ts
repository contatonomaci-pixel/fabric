import { defineConfig, devices } from "@playwright/test";
import { randomBytes } from "node:crypto";
import { tmpdir } from "node:os";
import { join } from "node:path";
process.env.QA_SCREENSHOT_DIR ||= join(tmpdir(), "factorycontrol-qa");
process.env.E2E_PASSWORD ||= randomBytes(24).toString("base64url");
export default defineConfig({
  testDir: "./e2e",
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: "list",
  use: { baseURL: "http://localhost:5173" },
  projects: [
    {
      name: "desktop",
      use: {
        ...devices["Desktop Chrome"],
        viewport: { width: 1487, height: 1058 },
      },
    },
    {
      name: "mobile",
      use: { ...devices["iPhone 13"], defaultBrowserType: "chromium" },
    },
  ],
  webServer: [
    {
      command: "node scripts/e2e-api.mjs",
      stderr: "ignore", // Evita logs de conexão do PHP ocultarem os resultados dos testes.
      url: "http://127.0.0.1:8000/up",
      reuseExistingServer: false,
      timeout: 60000,
    },
    {
      command: "npm run dev",
      url: "http://localhost:5173",
      reuseExistingServer: false,
      timeout: 60000,
    },
  ],
});
