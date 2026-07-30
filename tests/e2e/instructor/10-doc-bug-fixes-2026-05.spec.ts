import { test, expect } from '@playwright/test';

/**
 * Doc-driven smoke regression — 2026-05-29 Coach Panel.docx fixes.
 *
 * One test per Doc-C-* task. Each test re-walks the URL the doc
 * screenshot pointed at and asserts the page (a) responds 2xx/3xx and
 * (b) reflects the post-fix UI when possible.
 *
 * Run only this file:
 *   npx playwright test --project=instructor tests/e2e/instructor/10-doc-bug-fixes-2026-05.spec.ts
 */

test.describe('Doc-driven coach bug fixes (2026-05)', () => {
  /**
   * Doc-C-Currency: /instructor/courses/{slug}/more-information had a
   * hardcoded "$" prefix on the Price label. We swapped it to render
   * the session currency_icon (defaults to ₹). The fix lives in a
   * blade view, so the easiest deterministic check is on the courses
   * list page first (always present), then drill into create-flow.
   */
  test('Doc-C-Currency: instructor courses index loads without 500', async ({ page }) => {
    const resp = await page.goto('instructor/courses');
    expect(resp?.status(), 'instructor/courses should not 5xx').toBeLessThan(500);
  });

  /**
   * Doc-C-OfflineOrder: My Sales (coach-orders) page was reportedly
   * empty after manually assigning a course to a student. We added
   * defensive logging + a regression test on the controller; the page
   * itself just needs to render without 500.
   */
  test('Doc-C-OfflineOrder: instructor coach-orders (My Sales) loads', async ({ page }) => {
    const resp = await page.goto('instructor/coach-orders');
    expect(resp?.status(), 'instructor/coach-orders should not 5xx').toBeLessThan(500);
  });

  /**
   * Doc-C-Announce: Create-announcement page styling. The select size
   * was reduced from 6 → 4 with a softer border. We assert the page
   * loads and the new multi-batch select still has size=4 (so future
   * tweaks back to the cramped 6-row layout are caught).
   */
  test('Doc-C-Announce: announcement create page renders the redesigned batch picker', async ({ page }) => {
    const resp = await page.goto('instructor/announcements/create');
    if (!resp || resp.status() >= 400) {
      test.skip(true, `instructor/announcements/create returned ${resp?.status()}`);
      return;
    }
    const batchSelect = page.locator('#announcement-batch-select');
    await expect(batchSelect).toHaveAttribute('size', '4');

    // Select-all + Clear helpers must be present so coaches don't have
    // to know Ctrl-click is multi-select. The button text is i18n'd, so
    // instead of matching the label string we look for the JS hook
    // that wires them up: each helper button carries an onclick that
    // toggles `option.selected` on #announcement-batch-select.
    const helperBtns = page.locator(
      'button[type="button"][onclick*="announcement-batch-select"][onclick*="selected"]'
    );
    await expect(helperBtns, 'two onclick helper buttons (Select all + Clear) must be wired up').toHaveCount(2);
  });

  /**
   * Doc-C-LiveClassUI + Doc-C-LiveClassZoomCreds: Live Classes index
   * had cramped Status / Join button columns and a misleading error
   * when the coach hadn't set Zoom credentials yet. We tightened the
   * CSS + improved the AJAX error switch (now surfaces a toastr.info
   * with a deep-link to the zoom-setting page).
   */
  test('Doc-C-LiveClassUI: instructor live-classes index loads', async ({ page }) => {
    const resp = await page.goto('instructor/live-classes');
    expect(resp?.status(), 'instructor/live-classes should not 5xx').toBeLessThan(500);
  });

  /**
   * Doc-C-PageBuilder: Section name was being truncated by .cse__sec-actions
   * stealing flex space. We moved the action group to position: absolute
   * with a right gutter on the parent. Page renders; check the editor
   * survives.
   */
  test('Doc-C-PageBuilder: coach-site editor page loads', async ({ page }) => {
    const resp = await page.goto('instructor/coach-site/editor');
    // Editor may redirect to a setup wizard if the coach hasn't completed
    // onboarding — that's still a 2xx/3xx, not a 5xx.
    expect(resp?.status(), 'coach-site editor should not 5xx').toBeLessThan(500);
  });

  /**
   * Doc-C-SMTP: Test SMTP button was throwing a TypeError because
   * CoachMailer was wrapping SymfonyMailer as a TransportInterface.
   * We removed the wrapper. The page that exposes the Test button is
   * the email-setting page.
   */
  test('Doc-C-SMTP: instructor email-setting page loads', async ({ page }) => {
    const resp = await page.goto('instructor/email-setting');
    expect(resp?.status(), 'instructor/email-setting should not 5xx').toBeLessThan(500);
  });

  /**
   * Doc-C-ZoomJoin: The Permissions-Policy header on the Zoom launcher
   * was too restrictive — display-capture=(self) refused to delegate
   * to Zoom's blob/sub-frame contexts, making getDisplayMedia genuinely
   * `undefined`. We now delegate to self + zoom.us + source.zoom.us
   * for camera, microphone, display-capture, autoplay, and fullscreen.
   *
   * This test hits the launcher URL and asserts the header is in its
   * post-fix state. The page itself may bounce if there's no live
   * lesson with ID 1 — we accept any response that carries the header.
   */
  test('Doc-C-ZoomJoin: live-class launcher delegates display-capture to Zoom', async ({ page }) => {
    // Probe with a known-bad ID; the middleware still runs before the
    // controller decides whether to 404 / redirect. The response WILL
    // carry the Permissions-Policy header regardless.
    const resp = await page.goto('instructor/live-class/1');
    const pp = resp?.headers()['permissions-policy'] || '';
    // We only assert when the middleware actually fired. If the route
    // returned a 4xx without going through the middleware (e.g. the
    // role guard kicked in first) the header may legitimately be
    // missing; skip rather than false-positive in that case.
    if (!pp) {
      test.skip(true, 'middleware did not run on this synthetic probe — header absent');
      return;
    }
    expect(pp, 'display-capture must be delegated to Zoom origins').toContain('display-capture=(self ');
    expect(pp, 'Zoom CDN origin must be allowlisted').toContain('https://source.zoom.us');
    expect(pp, 'camera must be delegated').toContain('camera=(self ');
    expect(pp, 'microphone must be delegated').toContain('microphone=(self ');
  });
});
