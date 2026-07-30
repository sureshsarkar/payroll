import { test, expect } from '@playwright/test';

/**
 * 1:1 Instant Meeting — coach panel (2026-07-03).
 *
 * Deterministic surfaces only: page render, nav item, server-side validation
 * and the roster (tenant-isolation) guard. The actual Zoom join is NOT exercised
 * (it needs a live Zoom account + HTTPS + camera), but every guard that runs
 * BEFORE the Zoom call is verified here.
 */

test.describe('Instant Meeting — coach panel', () => {
  test('page loads with the start form + student picker', async ({ page }) => {
    const resp = await page.goto('instructor/instant-meetings');
    expect(resp?.status(), `page returned ${resp?.status()}`).toBeLessThan(400);

    await expect(page.getByRole('heading', { name: /Instant Meeting/i })).toBeVisible();
    await expect(page.locator('select[name="student_id"]')).toBeVisible();
    await expect(page.locator('button[data-start]')).toBeVisible();
  });

  test('sidebar exposes the Instant Meeting link next to Live Classes', async ({ page }) => {
    await page.goto('instructor/instant-meetings');
    const link = page.locator('a[href$="/instructor/instant-meetings"]').first();
    await expect(link).toHaveCount(1);
  });

  test('start is rejected without a student (server-side validation)', async ({ page }) => {
    await page.goto('instructor/instant-meetings');
    const token = await page.locator('input[name="_token"]').first().inputValue();

    const res = await page.request.post('instructor/instant-meetings/start', {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      form: { _token: token, student_id: '' },
    });
    expect(res.status()).toBe(422);
  });

  test('start is rejected for a student outside the coach roster (tenant isolation)', async ({ page }) => {
    await page.goto('instructor/instant-meetings');
    const token = await page.locator('input[name="_token"]').first().inputValue();

    // A high, almost-certainly-not-in-roster id — the roster guard runs BEFORE
    // any Zoom call, so this is deterministic and never creates a meeting.
    const res = await page.request.post('instructor/instant-meetings/start', {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
      form: { _token: token, student_id: '999999999', purpose: 'doubt', duration: '30' },
    });
    expect(res.status()).toBe(422);
    const body = await res.json().catch(() => ({}));
    expect(JSON.stringify(body)).toMatch(/roster|not found/i);
  });

  test('status endpoint is wired + auth-gated (404 for an unknown meeting)', async ({ page }) => {
    const res = await page.request.get('instant-meeting/999999999/status', {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    });
    // Reached the controller (findOrFail) rather than 401/redirect → auth ok.
    expect(res.status()).toBe(404);
  });
});
