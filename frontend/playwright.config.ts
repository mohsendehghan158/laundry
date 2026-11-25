import { defineConfig, devices } from "@playwright/test";

/**
 * End-to-end tests run the real panel against the real API.
 *
 * Both servers are started here unless they are already running locally.
 * The API runs on its own database (E2E_DB_DATABASE, default laundry_e2e),
 * which is wiped and seeded with the demo data on every run.
 */
const CI = Boolean(process.env.CI);
const database = process.env.E2E_DB_DATABASE ?? "laundry_e2e";
const inherited = Object.fromEntries(Object.entries(process.env).filter((entry): entry is [string, string] => entry[1] !== undefined));

export default defineConfig({
  testDir: "./e2e",
  fullyParallel: false,
  workers: 1,
  forbidOnly: CI,
  retries: CI ? 1 : 0,
  reporter: CI ? [["github"], ["html", { open: "never" }]] : "list",
  use: {
    // Must be one of the backend's SANCTUM_STATEFUL_DOMAINS.
    baseURL: "http://localhost:3000",
    locale: "fa-IR",
    timezoneId: "Asia/Tehran",
    trace: "off",
  },
  projects: [
    { name: "desktop", use: { ...devices["Desktop Chrome"] }, grepInvert: /@mobile/ },
    { name: "mobile", use: { ...devices["Pixel 7"] }, grep: /@mobile/ },
  ],
  webServer: [
    {
      command: "php artisan migrate:fresh --seed --force && php artisan serve --host=127.0.0.1 --port=8000",
      cwd: "../backend",
      url: "http://127.0.0.1:8000/up",
      reuseExistingServer: !CI,
      timeout: 120_000,
      env: {
        ...inherited,
        DB_DATABASE: database,
        SESSION_DRIVER: "file",
        CACHE_STORE: "file",
        QUEUE_CONNECTION: "sync",
        APP_URL: "http://localhost:3000",
        // Every customer portal sign-in code is this (ignored in production).
        OTP_FIXED_CODE: "12345",
      },
    },
    {
      command: CI ? "npm run start" : "npm run dev",
      url: "http://localhost:3000/login",
      reuseExistingServer: !CI,
      timeout: 180_000,
      env: { ...inherited, BACKEND_URL: "http://127.0.0.1:8000" },
    },
  ],
});
