# API Contract Tests (Newman)

A Postman collection runnable via Newman in CI that asserts the **mobile
API contract** at `/api/*`. Catches breaking changes to:
- Response status codes
- `Content-Type` (must always be `application/json`)
- Response body shape (where applicable)
- Auth gating (401/403 for protected endpoints)

## Coverage — 24 tests across 7 folders

### Public taxonomy (5)
- `GET /api/countries` — 200 + `data` array + `id`/`name` shape
- `GET /api/course-main-categories` — 200 + `data` array
- `GET /api/currency-list` — 200
- `GET /api/course-languages` — 200
- `GET /api/course-levels` — 200

### Auth (4)
- `POST /api/login` (valid creds) — 200/401/422, captures token
- `POST /api/login` (invalid creds) — 4xx (not 5xx)
- `POST /api/login` (empty body) — 422
- `POST /api/register` (empty body) — 422

### Course public (2)
- `GET /api/course/{bogus-slug}` — 404 + JSON
- `GET /api/course/buy/{slug}/quote` (no-auth) — 401

### Public contact (2)
- `POST /api/contact-us` (valid) — no 5xx
- `POST /api/contact-us` (empty body) — 422

### Student auth gate (8)
- `GET /api/student/dashboard` (no-auth) — 401
- `GET /api/student/profile` (no-auth) — 401
- `GET /api/student/courses` (no-auth) — 401
- `GET /api/student/cart-list` (no-auth) — 401
- `GET /api/student/order-history` (no-auth) — 401
- `GET /api/student/wishlist` (no-auth) — 401
- `GET /api/student/live-classes` (no-auth) — 401
- `POST /api/student/add-to-cart/{slug}` (no-auth) — 401

### Coach auth gate (1)
- `GET /api/instructor/students` (no-auth) — 401

### 2FA gate (1)
- `POST /api/2fa/verify` (no-auth) — 401

**Total: 24 contract tests** covering public surface + auth-gate
enforcement on every protected family.

## Running locally

```bash
# Install Newman (once)
npm install -g newman newman-reporter-htmlextra

# Run against local XAMPP
newman run tests/api/mbsguru-api.postman_collection.json \
  -e tests/api/local.postman_environment.json

# Run against CI environment (artisan serve on :8000)
newman run tests/api/mbsguru-api.postman_collection.json \
  -e tests/api/ci.postman_environment.json
```

## CI integration

Wired into `.github/workflows/api.yml`. Runs on every push/PR after
PHPUnit, before E2E. Failures block the merge.

HTML report (`newman-results/report.html`) + JSON results uploaded
as 7-day workflow artifact.

## What this catches vs. what it does NOT

**This collection catches:**
- A protected endpoint losing its auth middleware (auth-gate tests
  return 200 instead of 401 → test fails)
- A controller starting to return HTML instead of JSON
- A controller starting to 5xx on previously-handled input
- A response shape break that drops a required field (where asserted)

**This collection does NOT catch:**
- Authenticated body-shape regressions (needs fixture data + a
  logged-in session — operator follow-up)
- Pagination breakage
- Sort order changes
- Rate-limiting regressions (would need k6, not Newman)
- WebSocket / broadcast contract changes
- Push notification payload shape (separate service)

## Operator follow-up — extending coverage

For each authenticated endpoint, add a paired body-shape test that:
1. Reuses the captured `{{auth_token}}` from the `/api/login` setup
2. Hits the endpoint with the bearer token
3. Asserts the JSON Schema of the success response

Pattern:

```json
{
  "name": "GET /api/student/profile (authenticated)",
  "request": {
    "method": "GET",
    "header": [
      { "key": "Accept", "value": "application/json" },
      { "key": "Authorization", "value": "Bearer {{auth_token}}" }
    ],
    "url": { "raw": "{{base_url}}/api/student/profile" }
  },
  "event": [{
    "listen": "test",
    "script": { "exec": [
      "pm.test('status is 200', () => pm.response.to.have.status(200));",
      "const body = pm.response.json();",
      "pm.test('has user object', () => pm.expect(body).to.have.property('user'));",
      "pm.test('user has expected fields', () => {",
      "  pm.expect(body.user).to.have.property('id');",
      "  pm.expect(body.user).to.have.property('email');",
      "  pm.expect(body.user).to.have.property('role');",
      "});"
    ]}
  }]
}
```

Effort: ~30 minutes per endpoint. The 8 student-gate tests plus the
1 coach-gate test could be expanded to ~18 body-shape tests in
roughly 4-5 hours.

## Why Postman/Newman (not Bruno or Insomnia)

- Team familiarity (Postman is the de-facto standard)
- Collections export from the Postman UI for non-engineering review
- Newman is the most mature CI runner for Postman collections
- Bruno is fine but switching costs nothing if you stay on it

## Why this is separate from Playwright

Playwright's `request` fixture is fine for E2E-adjacent API setup.
But systematic API CONTRACT enforcement belongs in a dedicated layer:

- API changes can break mobile clients that don't run Playwright
- Mobile teams want to mock against the contract, not against E2E
- Faster than full browser E2E (~30s vs ~5min)
- Portable as API consumer documentation
