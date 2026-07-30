import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate } from 'k6/metrics';

/**
 * Coach-site burst — white-label landing pages.
 *
 * Real-world driver: a coach runs a paid campaign and 5000 students
 * land on their site in 60 seconds. Need to survive without DB lock
 * contention on coach_site_settings / coach_landing_pages.
 *
 * The default slug `mbs` is a stable seeded page; override with the
 * COACH_SLUG env var to point at a fresh tenant.
 */

const BASE_URL    = __ENV.BASE_URL    || 'http://localhost/mbsguru1/public';
const COACH_SLUG  = __ENV.COACH_SLUG  || 'mbs';

const fivexxRate = new Rate('fivexx_rate');

export const options = {
  stages: [
    // Spikier than scenario 01 — burst pattern.
    { duration: '15s', target: 30 },
    { duration: '15s', target: 100 },  // sudden spike to simulate campaign click
    { duration: '1m',  target: 100 },  // sustained
    { duration: '30s', target: 0 },
  ],
  thresholds: {
    http_req_duration:  ['p(95)<1200', 'p(99)<3000'],  // first-byte LTE 1.2s p95
    http_req_failed:    ['rate<0.02'],                  // <2% — bursts cause more tail
    fivexx_rate:        ['rate<0.01'],
  },
};

export default function () {
  group('coach landing', () => {
    const r = http.get(`${BASE_URL}/coach/${COACH_SLUG}`, { tags: { name: 'coach-landing' } });
    check(r, { 'landing 2xx': (res) => res.status >= 200 && res.status < 300 });
    fivexxRate.add(r.status >= 500);
    // Validate CSP header is present under load (P1-4 contract — must
    // not get dropped when the response middleware stack is hot).
    check(r, {
      'CSP header present': (res) =>
        !!(res.headers['Content-Security-Policy-Report-Only'] || res.headers['Content-Security-Policy']),
    });
  });
  sleep(1);

  group('coach cart drawer', () => {
    const r = http.get(`${BASE_URL}/coach/${COACH_SLUG}/cart`, { tags: { name: 'coach-cart' } });
    check(r, { 'cart not 5xx': (res) => res.status < 500 });
    fivexxRate.add(r.status >= 500);
  });
  sleep(1);

  group('coach login page', () => {
    const r = http.get(`${BASE_URL}/coach/${COACH_SLUG}/login`, { tags: { name: 'coach-login' } });
    check(r, { 'login 2xx': (res) => res.status >= 200 && res.status < 300 });
    fivexxRate.add(r.status >= 500);
  });
  sleep(1);

  group('coach register page', () => {
    const r = http.get(`${BASE_URL}/coach/${COACH_SLUG}/register`, { tags: { name: 'coach-register' } });
    check(r, { 'register 2xx': (res) => res.status >= 200 && res.status < 300 });
    fivexxRate.add(r.status >= 500);
  });
  sleep(1);
}
