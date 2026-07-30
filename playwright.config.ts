import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright E2E config for MBSGuru.
 *
 * Projects layered as:
 *   - setup           — logs each role in once, saves storageState
 *   - public          — anonymous Chromium flows
 *   - admin / instructor / student — role-aware Chromium flows (default)
 *   - firefox         — public flows on Firefox (cross-browser regression)
 *   - webkit          — public flows on WebKit / Safari (cross-browser regression)
 *   - mobile-chrome   — coach-site public flows on Pixel 5 (white-label is mobile-first)
 *   - mobile-safari   — coach-site public flows on iPhone 13
 *
 * Run a single project:
 *   npx playwright test --project=public
 *   npx playwright test --project=firefox
 *   npx playwright test --project=mobile-chrome
 *
 * Run everything (CI):
 *   npm run test:e2e
 *
 * Cross-browser + mobile projects target only the public surface — they
 * exist to catch CSS / browser-engine regressions on the visitor-facing
 * surface, not to re-run the entire auth'd suite on every engine. That
 * keeps CI cost bounded while still catching Safari-only / Firefox-only
 * issues that would otherwise reach prod.
 */
export default defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,             // XAMPP single-PHP-worker — serialize locally.
  workers: process.env.CI ? 2 : 1,  // CI runner can handle parallelism.
  retries: process.env.CI ? 1 : 0,  // One retry in CI to absorb flake.
  reporter: [
    ['list'],
    ['html', { outputFolder: 'tests/e2e/_report', open: 'never' }],
  ],
  timeout: 30_000,
  expect: { timeout: 5_000 },
  use: {
    // Trailing slash is REQUIRED — the app lives under a sub-path
    // (/mbsguru1/public/) on XAMPP. Without trailing slash + relative
    // paths in specs, `page.goto('/')` resolves to `http://localhost/`
    // and Apache returns 403 on the htdocs root.
    baseURL: process.env.E2E_BASE_URL || 'http://localhost/mbsguru1/public/',
    ignoreHTTPSErrors: true,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
    actionTimeout: 10_000,
    navigationTimeout: 20_000,
  },

  projects: [
    // 1. Auth setup — logs each role in once and saves storageState
    //    JSONs that the role-specific projects load up-front.
    { name: 'setup', testMatch: /.*\.setup\.ts/ },

    // ─── Chromium (default) — full role suite ─────────────────────
    // 2. Public / anonymous flows — no auth state.
    {
      name: 'public',
      testMatch: /public\/.*\.spec\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },

    // 3. Admin flows — uses storageState saved by auth.setup.ts.
    {
      name: 'admin',
      testMatch: /admin\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/e2e/.auth/admin.json',
      },
    },

    // 4. Instructor / coach flows.
    {
      name: 'instructor',
      testMatch: /instructor\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/e2e/.auth/instructor.json',
      },
    },

    // 5. Student flows.
    {
      name: 'student',
      testMatch: /student\/.*\.spec\.ts/,
      dependencies: ['setup'],
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'tests/e2e/.auth/student.json',
      },
    },

    // ─── Cross-browser — public surface only ──────────────────────
    // 6. Firefox — runs the same anonymous specs as `public`.
    {
      name: 'firefox',
      testMatch: /public\/.*\.spec\.ts/,
      use: { ...devices['Desktop Firefox'] },
    },

    // 7. WebKit / Safari — same public specs.
    {
      name: 'webkit',
      testMatch: /public\/.*\.spec\.ts/,
      use: { ...devices['Desktop Safari'] },
    },

    // ─── Mobile viewports — coach-site is mobile-first ────────────
    // 8. Pixel 5 — coach-site landing + checkout flow.
    {
      name: 'mobile-chrome',
      testMatch: /public\/(03-coach-site|01-home)\.spec\.ts/,
      use: { ...devices['Pixel 5'] },
    },

    // 9. iPhone 13 — coach-site landing on iOS Safari.
    {
      name: 'mobile-safari',
      testMatch: /public\/(03-coach-site|01-home)\.spec\.ts/,
      use: { ...devices['iPhone 13'] },
    },
  ],
});
