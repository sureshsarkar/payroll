#!/usr/bin/env bash
# UAT — Staff access-denied experience (2026-07-04)
#
#   bash tests/uat/uat_access_denied_2026-07.sh
#
# Verifies, over real HTTP, that:
#   • a staff member DENIED a module sees the professional access-denied card
#     rendered INSIDE the coach dashboard (sidebar present) — not the public page
#   • a staff member PERMITTED a module sees the real module (no card)
#   • the checkPermission fix lets an all-perms staff into the modules that used
#     to be wrongly blocked (coupons / coach-orders / staff-role / staff-permission)
#   • a real coach is never shown a denial
#
# Fixtures: run  php tests/e2e/seed-fixtures.php  and seed the all-perms staff
# (e2e-staff-all@mbsguru.test) first. Requires the local app served at BASE.

BASE="${1:-http://localhost/mbsguru1/public}"
JAR="/tmp/uat_ad_jar.txt"
PASS=0; FAIL=0
G="\033[32m"; R="\033[31m"; N="\033[0m"

login () {
  rm -f "$JAR"
  local email="$1"
  local token
  token=$(curl -s -c "$JAR" "$BASE/login" | grep -oE 'name="_token"[^>]*value="[^"]+"' | head -1 | sed -E 's/.*value="([^"]+)".*/\1/')
  curl -s -b "$JAR" -c "$JAR" -L -o /dev/null \
    --data-urlencode "_token=$token" --data-urlencode "email=$email" --data-urlencode "password=e2e!Test#2026" \
    "$BASE/user-login"
}

# check <label> <path> <expect: DENIED_INPANEL | ALLOWED>
check () {
  local label="$1" path="$2" expect="$3"
  curl -s -b "$JAR" -o /tmp/uat_body.html -w "" "$BASE/$path"
  local hasCard=no hasSidebar=no hasPublic=no
  grep -q "ad-card" /tmp/uat_body.html && hasCard=yes
  grep -qE "instructor-sidebar|sb-nav" /tmp/uat_body.html && hasSidebar=yes
  grep -q 'class="error-area' /tmp/uat_body.html && hasPublic=yes

  local ok=0
  if [ "$expect" = "DENIED_INPANEL" ]; then
    [ "$hasCard" = yes ] && [ "$hasSidebar" = yes ] && [ "$hasPublic" = no ] && ok=1
  else # ALLOWED
    [ "$hasCard" = no ] && ok=1
  fi
  if [ $ok -eq 1 ]; then printf "  ${G}PASS${N}  %-52s (card=%s sidebar=%s public=%s)\n" "$label" "$hasCard" "$hasSidebar" "$hasPublic"; PASS=$((PASS+1));
  else printf "  ${R}FAIL${N}  %-52s (card=%s sidebar=%s public=%s)\n" "$label" "$hasCard" "$hasSidebar" "$hasPublic"; FAIL=$((FAIL+1)); fi
}

echo "== Limited staff (courses, courses-create, coach-students) =="
login "e2e-staff@mbsguru.test"
check "DENIED coupons → in-panel card"        "instructor/coupons"      DENIED_INPANEL
check "DENIED coach-orders → in-panel card"   "instructor/coach-orders" DENIED_INPANEL
check "DENIED staff-role → in-panel card"     "instructor/staff-role"   DENIED_INPANEL
check "ALLOWED courses → real page"           "instructor/courses"      ALLOWED
check "ALLOWED coach-students → real page"    "instructor/coach-students" ALLOWED

echo "== All-perms staff (checkPermission fix — used to be wrongly blocked) =="
login "e2e-staff-all@mbsguru.test"
check "ALLOWED coupons"          "instructor/coupons"         ALLOWED
check "ALLOWED coach-orders"     "instructor/coach-orders"    ALLOWED
check "ALLOWED staff-role"       "instructor/staff-role"      ALLOWED
check "ALLOWED staff-permission" "instructor/staff-permission" ALLOWED

echo "== Real coach (never denied) =="
login "e2e-instructor@mbsguru.test"
check "ALLOWED coupons"      "instructor/coupons"      ALLOWED
check "ALLOWED staff-role"   "instructor/staff-role"   ALLOWED

echo ""
echo "  Total: $((PASS+FAIL))   Pass: $PASS   Fail: $FAIL"
exit $([ $FAIL -eq 0 ] && echo 0 || echo 1)
