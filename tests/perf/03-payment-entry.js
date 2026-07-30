import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate, Trend } from 'k6/metrics';

/**
 * Authenticated student → cart → coupon → checkout entry.
 *
 * THIS is the revenue path. The most likely places for hidden lock
 * contention + commission compute + Order::create round-trip cost.
 *
 * Auth: each VU logs in once using the E2E student credentials, holds
 * the session cookie for the run.
 *
 * Caveat: this script does NOT submit a real payment — it stops at
 * checkout entry. Going past that would charge real cards.
 */

const BASE_URL = __ENV.BASE_URL || 'http://localhost/mbsguru1/public';
const EMAIL    = __ENV.E2E_EMAIL    || 'e2e-student@mbsguru.test';
const PASSWORD = __ENV.E2E_PASSWORD || 'e2e!Test#2026';

const fivexxRate     = new Rate('fivexx_rate');
const checkoutLatency = new Trend('checkout_entry_ms', true);

export const options = {
  stages: [
    { duration: '30s', target: 10 },   // slow ramp — auth + cart heavier per VU
    { duration: '2m',  target: 30 },   // 30 concurrent shoppers
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration:  ['p(95)<1500', 'p(99)<3500'],
    http_req_failed:    ['rate<0.02'],
    fivexx_rate:        ['rate<0.005'],
    checkout_entry_ms:  ['p(95)<2000'],   // checkout entry should be < 2s p95
  },
};

// One-time setup per VU.
export function setup() {
  return { baseUrl: BASE_URL };
}

export default function (data) {
  // ─── login ─────────────────────────────────────────────────
  let csrf;
  let cookies = {};

  group('login', () => {
    const loginPage = http.get(`${data.baseUrl}/login`, { tags: { name: 'login-page' } });
    check(loginPage, { 'login page 2xx': (r) => r.status >= 200 && r.status < 300 });
    fivexxRate.add(loginPage.status >= 500);

    // Pull CSRF token from the login form.
    const match = loginPage.body.match(/name="_token"\s+value="([^"]+)"/);
    csrf = match ? match[1] : null;

    if (!csrf) {
      console.error('csrf token not found on login page');
      return;
    }

    const loginPost = http.post(
      `${data.baseUrl}/user-login`,
      { email: EMAIL, password: PASSWORD, _token: csrf },
      { tags: { name: 'login-submit' }, redirects: 0 }
    );
    check(loginPost, { 'login redirects': (r) => r.status === 302 });
    fivexxRate.add(loginPost.status >= 500);
  });
  sleep(1);

  // ─── browse + cart ─────────────────────────────────────────
  group('cart browse', () => {
    const r = http.get(`${data.baseUrl}/cart`, { tags: { name: 'cart' } });
    check(r, { 'cart 2xx': (res) => res.status >= 200 && res.status < 300 });
    fivexxRate.add(r.status >= 500);
  });
  sleep(1);

  // ─── apply coupon (FT-VAL-5 + FT-COUP-1 surface under load) ─
  group('apply-coupon', () => {
    if (!csrf) return;
    const r = http.post(
      `${data.baseUrl}/apply-coupon`,
      { coupon_code: 'PROBE_LOAD_BOGUS', _token: csrf },
      {
        tags: { name: 'apply-coupon' },
        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        redirects: 0,
      }
    );
    // Bogus coupon — should be 4xx, NOT 5xx.
    check(r, { 'apply-coupon not 5xx': (res) => res.status < 500 });
    fivexxRate.add(r.status >= 500);
  });
  sleep(1);

  // ─── checkout entry — the load-bearing measurement ─────────
  group('checkout entry', () => {
    const start = Date.now();
    const r = http.get(`${data.baseUrl}/checkout`, { tags: { name: 'checkout' } });
    checkoutLatency.add(Date.now() - start);
    check(r, { 'checkout not 5xx': (res) => res.status < 500 });
    fivexxRate.add(r.status >= 500);
  });
  sleep(1);
}
