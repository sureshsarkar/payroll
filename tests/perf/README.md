# k6 Performance Tests

Three load-test scenarios covering the highest-revenue / highest-traffic
paths of the application:

| Scenario | What it exercises | Why it matters |
|---|---|---|
| `01-anonymous-browse.js` | Anonymous home → course catalog → course details → instructor profile | Bulk of read traffic; hits cache + N+1 hotspots if any |
| `02-coach-site-burst.js` | Anonymous → coach-site landing → coach-cart → coach-checkout entry | White-label brand pages — coach campaigns drive sudden bursts |
| `03-payment-entry.js` | Authenticated student → cart → apply-coupon → checkout entry | Revenue path; the one where DB lock contention + commission compute live |

## Running locally

Install k6 (Windows: `choco install k6` / macOS: `brew install k6` /
Linux: package manager). Then:

```bash
# Single scenario, quick smoke
k6 run tests/perf/01-anonymous-browse.js

# Override base URL
k6 run -e BASE_URL=http://localhost/mbsguru1/public tests/perf/01-anonymous-browse.js

# All scenarios, full load profile
k6 run tests/perf/01-anonymous-browse.js && \
k6 run tests/perf/02-coach-site-burst.js && \
k6 run tests/perf/03-payment-entry.js
```

## Thresholds

Each script declares thresholds that **fail the run** if violated:

| Metric | Threshold |
|---|---|
| `http_req_duration{p(95)}` | < 800 ms |
| `http_req_failed` | < 1% |
| `iteration_duration{p(95)}` | scenario-specific |

CI does NOT block PRs on these — they emit metrics + a summary instead.
Operators review trends in Grafana / k6 Cloud / the JSON output.

## CI integration

Wired in `.github/workflows/perf.yml` (separate from the E2E workflow
so a perf regression doesn't block a security fix from merging).

Triggers:
- Nightly on `main` at 03:00 UTC (low-traffic window)
- Manual via `gh workflow run perf.yml`

Results are uploaded as a workflow artifact (`k6-results-<scenario>.json`).

## Load profile assumptions

The scenarios target a **single-node staging** at default config:

- 20 VUs ramp-up over 30 s
- 50 VUs steady-state for 2 min
- 0 VUs ramp-down over 30 s

That's a **smoke load** suitable for catching obvious regressions on
a small dev environment. **Real load testing** (1000+ VUs sustained,
distributed) requires k6 Cloud or distributed k6 — out of scope for
this baseline.
