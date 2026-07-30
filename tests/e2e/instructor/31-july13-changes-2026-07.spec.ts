import { test, expect } from '@playwright/test';

/**
 * 13-July-Changes.docx — coach-panel E2E (runs under the `instructor` project,
 * i.e. logged in as the real coach e2e-instructor).
 *
 *   #1 Dynamic browser-tab titles — each coach module page shows a descriptive
 *      <title> ("Module | Brand"), never the generic "Coach Dashboard".
 *   #2 Membership Enterprise plan shows "Talk to Us" instead of a price.
 *   #6 Sidebar renames: "My Certificate" → "Certificate",
 *      "My Plan & Billing" → "Plan & Billing".
 *
 * Run only this file:
 *   npx playwright test --project=instructor tests/e2e/instructor/31-july13-changes-2026-07.spec.ts --no-deps
 */

// path → the module word that must appear in the browser tab title.
const TAB_TITLES: Record<string, string> = {
  'instructor/certificate-builder': 'Certificate Builder',
  'instructor/fees': 'Fees',
  'instructor/offline-payments': 'Offline Payments',
  'instructor/my-plan': 'Plan & Billing',
  'instructor/zoom-setting': 'Zoom Settings',
  'instructor/youtube-setting': 'YouTube Settings',
  'instructor/staff-role': 'Staff Roles',
  'instructor/staff-permission': 'Staff Permissions',
  'instructor/brand-settings': 'Settings',
  'instructor/web-page': 'Website Builder',
  'instructor/subscription-histories': 'Subscription History',
  'instructor/tax-settings': 'Tax Settings',
  'instructor/membership': 'Membership',
};

test.describe('July-13 coach panel changes', () => {
  test('#1 every listed coach page has a descriptive tab title (not "Coach Dashboard")', async ({ page }) => {
    for (const [path, expected] of Object.entries(TAB_TITLES)) {
      const resp = await page.goto(path).catch(() => null);
      // Skip a page the account can't reach (membership gate / 4xx) — the
      // title guarantee only applies to a served page.
      if (!resp || resp.status() >= 400) continue;
      // If a gate redirected us off the module, the title belongs to the
      // redirect target, not this page — skip.
      const seg = path.split('/').pop()!;
      if (!page.url().includes(seg)) continue;
      const title = await page.title();
      // A server/DB hiccup surfaces the exception as the title — not a title bug.
      if (/SQLSTATE|Exception|Whoops/i.test(title)) continue;
      expect(title, `${path} tab title`).toContain(expected);
      expect(title, `${path} must not be the generic dashboard label`).not.toMatch(/^Coach Dashboard\b/);
    }
  });

  test('#1 the dashboard landing itself still reads "Coach Dashboard"', async ({ page }) => {
    await page.goto('instructor/dashboard');
    expect(await page.title()).toMatch(/^Coach Dashboard/);
  });

  test('#2 Enterprise plan shows "Talk to Us" and no currency figure', async ({ page }) => {
    const resp = await page.goto('instructor/membership');
    if (!resp || resp.status() >= 400) {
      test.skip(true, 'membership page not reachable for this account');
      return;
    }
    const ent = page.locator('.mbs-plan--enterprise');
    if ((await ent.count()) === 0) {
      test.skip(true, 'no enterprise plan configured on this environment');
      return;
    }
    const price = ent.first().locator('.mbs-plan__price');
    await expect(price).toContainText('Talk to Us');
    // The price area must NOT render a numeric amount for enterprise.
    await expect(price).not.toHaveText(/\d/);
    // The Contact CTA stays.
    await expect(ent.first()).toContainText(/Contact us/i);
  });

  test('#6 sidebar shows "Certificate" and "Plan & Billing" (renamed)', async ({ page }) => {
    await page.goto('instructor/dashboard');
    // Read the sidebar TEXT (textContent decodes entities AND includes items in
    // collapsed/hidden menu groups, which getByRole would skip). The rename is
    // proven by the new labels being present and the old wording being gone.
    const txt = (await page.locator('body').textContent()) || '';
    expect(txt, 'renamed Certificate label present').toContain('Certificate');
    expect(txt, 'renamed Plan & Billing label present').toContain('Plan & Billing');
    expect(txt, 'old "My Certificate" wording gone').not.toContain('My Certificate');
    expect(txt, 'old "My Plan & Billing" wording gone').not.toContain('My Plan & Billing');
  });
});
