import { test, expect } from '@playwright/test';

/**
 * 1:1 Instant Meeting — unauthenticated access control (2026-07-03).
 *
 * The room + its signature/status/attendance endpoints all sit behind auth.
 * An anonymous visitor must be bounced to login, never handed a meeting.
 */

test.describe('Instant Meeting — anonymous is blocked', () => {
  test('room page redirects an anonymous visitor to login', async ({ page }) => {
    await page.goto('instant-meeting/1/room');
    // auth middleware → login (never renders the Zoom launcher).
    await expect(page).toHaveURL(/login/i);
  });

  test('signature endpoint is not reachable anonymously', async ({ request, baseURL }) => {
    const res = await request.post(new URL('instant-meeting/1/signature', baseURL).toString(), {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      failOnStatusCode: false,
    });
    // 401/419 (unauthenticated / CSRF) or 302 redirect — anything but a 200 signature.
    expect(res.status()).not.toBe(200);
  });
});
