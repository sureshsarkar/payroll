/**
 * Shared E2E fixtures: dedicated test accounts created by tinker. Pass-
 * words known + stable across runs because we explicitly hashed them
 * in setup.
 *
 * If a future change rotates these, re-run the tinker block in
 * docs/E2E_PLAYWRIGHT_2026-05-29.md → "Reset test accounts".
 */
export const ACCOUNTS = {
  admin: {
    email: 'e2e-admin@mbsguru.test',
    password: 'e2e!Test#2026',
    loginUrl: 'admin/login',
    dashUrl: '/admin/dashboard',
  },
  instructor: {
    email: 'e2e-instructor@mbsguru.test',
    password: 'e2e!Test#2026',
    loginUrl: 'login',
    dashUrl: '/instructor/dashboard',
  },
  student: {
    email: 'e2e-student@mbsguru.test',
    password: 'e2e!Test#2026',
    loginUrl: 'login',
    dashUrl: '/student/dashboard',
  },
} as const;
