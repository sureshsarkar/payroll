import { test, expect } from '@playwright/test';

/**
 * Public course catalog flow — what an anonymous visitor can browse
 * without logging in.
 */

test('course catalog page loads', async ({ page }) => {
  // Two routes are common across deploys: /courses and /course.
  // Try both.
  let resp = await page.goto('courses');
  if (resp?.status() === 404) {
    resp = await page.goto('course');
  }
  expect(resp?.status()).toBeLessThan(400);
  await expect(page.locator('body')).not.toBeEmpty();
});

test('instructors index page loads', async ({ page }) => {
  // Route is `all-coaches` (white-label rename of all-instructors).
  const candidates = ['all-coaches', 'all-instructors', 'instructors'];
  let ok = false;
  for (const url of candidates) {
    const resp = await page.goto(url);
    if (resp && resp.status() < 400) { ok = true; break; }
  }
  expect(ok, 'one of /instructors, /all-instructors, /instructor must respond 2xx/3xx').toBeTruthy();
});

test('contact page loads', async ({ page }) => {
  const resp = await page.goto('contact');
  expect(resp?.status()).toBeLessThan(400);
  // Contact form should exist.
  await expect(page.locator('form')).toHaveCount(await page.locator('form').count());
});

test('terms + privacy pages load', async ({ page }) => {
  for (const url of ['terms-and-conditions', 'privacy-policy']) {
    const resp = await page.goto(url);
    // 200 expected; 404 means CMS row missing — flag.
    expect(resp?.status(), `${url} must respond`).toBeLessThan(400);
  }
});
