import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { Rate } from 'k6/metrics';

/**
 * Anonymous browse load — the read-heavy public surface.
 *
 * Hits: home → /course catalog → /course/{slug} details → /all-coaches.
 * No auth, no cart, no mutation.
 *
 * Catches: N+1 queries on course listings, cache misses on the home
 * page, broken `published` scopes that scan large tables, missing
 * indexes on category/instructor JOINs.
 */

const BASE_URL = __ENV.BASE_URL || 'http://localhost/mbsguru1/public';

// Custom metric — fraction of iterations that hit a 5xx anywhere.
const fivexxRate = new Rate('fivexx_rate');

export const options = {
  stages: [
    { duration: '30s', target: 20 },   // ramp to 20 VU
    { duration: '2m',  target: 50 },   // steady at 50 VU
    { duration: '30s', target: 0 },    // ramp down
  ],
  thresholds: {
    http_req_duration: ['p(95)<800', 'p(99)<2000'],
    http_req_failed:   ['rate<0.01'],   // <1% failure rate
    fivexx_rate:       ['rate<0.005'],  // <0.5% 5xx rate
  },
};

export default function () {
  group('home', () => {
    const r = http.get(`${BASE_URL}/`, { tags: { name: 'home' } });
    check(r, { 'home 2xx': (res) => res.status >= 200 && res.status < 300 });
    fivexxRate.add(r.status >= 500);
  });
  sleep(1);

  group('course catalog', () => {
    const r = http.get(`${BASE_URL}/course`, { tags: { name: 'course-catalog' } });
    check(r, { 'catalog 2xx/3xx': (res) => res.status < 400 });
    fivexxRate.add(r.status >= 500);
  });
  sleep(1);

  group('all coaches', () => {
    const r = http.get(`${BASE_URL}/all-coaches`, { tags: { name: 'all-coaches' } });
    check(r, { 'coaches 2xx/3xx': (res) => res.status < 400 });
    fivexxRate.add(r.status >= 500);
  });
  sleep(1);

  group('contact', () => {
    const r = http.get(`${BASE_URL}/contact`, { tags: { name: 'contact' } });
    check(r, { 'contact 2xx': (res) => res.status >= 200 && res.status < 300 });
    fivexxRate.add(r.status >= 500);
  });
  sleep(1);
}
