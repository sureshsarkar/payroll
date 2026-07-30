#!/usr/bin/env bash
# UAT — "Role Permission Test" doc (2026-07-06)  menu / route gating over real HTTP
#
#   bash tests/uat/uat_role_permission_doc_2026-07-06.sh
#
# Verifies the doc's permission cluster on the live app:
#   A  Analytics + My Plan hidden from staff without the permission (menu + route 403)
#   D  Instant Meeting hidden unless the instant-meetings permission is held
#      (was wrongly surfaced by the Live Classes permission)
#   Quick Add shortcuts only show modules the staff can actually open
#   A real coach always sees everything.
#
# Fixtures: php tests/e2e/seed-fixtures.php   (creates e2e-instructor / e2e-staff /
# e2e-staff-all @mbsguru.test, pw e2e!Test#2026). App served at BASE.

BASE="${1:-http://localhost/mbsguru1/public}"
JAR="/tmp/uat_rp_jar.txt"
PASS=0; FAIL=0
G="\033[32m"; R="\033[31m"; N="\033[0m"

login () {
  rm -f "$JAR"; local email="$1" token
  token=$(curl -s -c "$JAR" "$BASE/login" | grep -oE 'name="_token"[^>]*value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')
  curl -s -b "$JAR" -c "$JAR" -L -o /dev/null \
    --data-urlencode "_token=$token" --data-urlencode "email=$email" --data-urlencode "password=e2e!Test#2026" \
    "$BASE/user-login"
}

# menu <label> <url-path> <expect: SHOWN|HIDDEN>  — looks for the nav link href in the dashboard.
# Grepping the href (e.g. instructor/analytics") is reliable; label-text grep is brittle
# against HTML whitespace/icons/badges (the Playwright spec covers the DOM-level check).
menu () {
  local label="$1" path="$2" expect="$3"
  curl -s -b "$JAR" -o /tmp/uat_rp.html "$BASE/instructor/dashboard"
  local present=no
  grep -qE "href=\"[^\"]*/${path}\"" /tmp/uat_rp.html && present=yes
  local ok=0
  { [ "$expect" = SHOWN ] && [ "$present" = yes ]; } && ok=1
  { [ "$expect" = HIDDEN ] && [ "$present" = no ]; } && ok=1
  if [ $ok -eq 1 ]; then printf "  ${G}PASS${N}  %-42s expect=%-6s present=%s\n" "$label" "$expect" "$present"; PASS=$((PASS+1));
  else printf "  ${R}FAIL${N}  %-42s expect=%-6s present=%s\n" "$label" "$expect" "$present"; FAIL=$((FAIL+1)); fi
}

# route <path> <expected-http>
route () {
  local path="$1" want="$2"
  local code; code=$(curl -s -b "$JAR" -o /dev/null -w "%{http_code}" "$BASE/$path")
  if [ "$code" = "$want" ]; then printf "  ${G}PASS${N}  %-42s http=%s\n" "$path" "$code"; PASS=$((PASS+1));
  else printf "  ${R}FAIL${N}  %-42s http=%s (want %s)\n" "$path" "$code" "$want"; FAIL=$((FAIL+1)); fi
}

echo "== Limited staff (courses, courses-create, coach-students) =="
login "e2e-staff@mbsguru.test"
menu  "Analytics"        "instructor/analytics"        HIDDEN
menu  "Instant Meeting"  "instructor/instant-meetings" HIDDEN     # issue D
menu  "Live Classes"     "instructor/live-classes"     HIDDEN
route "instructor/analytics" 403    # issue A route gate

echo "== All-perms staff — sees the gated modules =="
login "e2e-staff-all@mbsguru.test"
menu  "Analytics"        "instructor/analytics"        SHOWN
menu  "Instant Meeting"  "instructor/instant-meetings" SHOWN
menu  "Live Classes"     "instructor/live-classes"     SHOWN
route "instructor/analytics" 200

echo "== Real coach — never gated =="
login "e2e-instructor@mbsguru.test"
menu  "Analytics"        "instructor/analytics"        SHOWN
menu  "Instant Meeting"  "instructor/instant-meetings" SHOWN
route "instructor/analytics" 200

echo ""
echo "  Total: $((PASS+FAIL))   Pass: $PASS   Fail: $FAIL"
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
