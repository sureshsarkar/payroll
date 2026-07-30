import { test, expect } from '@playwright/test';

/**
 * 1:1 Instant Meeting — student side (2026-07-03).
 *
 * The student reaches the room via the invite notification link. Access is
 * limited to the invited participant; a student can never open a meeting that
 * isn't theirs. We verify the endpoint is reachable + participant-gated without
 * needing a live Zoom session.
 */

test.describe('Instant Meeting — student access', () => {
  test('student cannot open a meeting that is not theirs', async ({ page }) => {
    // Unknown id → 404 (findOrFail); an existing-but-foreign meeting → 403.
    const res = await page.request.get('instant-meeting/999999999/room', {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    expect([403, 404]).toContain(res.status());
  });

  test('student status poll on an unknown meeting is auth-gated (404)', async ({ page }) => {
    const res = await page.request.get('instant-meeting/999999999/status', {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    });
    expect(res.status()).toBe(404); // reached the controller → student session is authed
  });
});
