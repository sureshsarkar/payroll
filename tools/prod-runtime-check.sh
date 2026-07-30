#!/usr/bin/env bash
#
# prod-runtime-check.sh — run on the PRODUCTION server (mbsguru.com)
# to verify the three operational items the audit can't check from dev:
#   T2.7a — .env matches production defaults (no debug, no LIVE bleed)
#   T2.7b — scheduler (cron) is firing
#   T2.7c — backup:run is succeeding
#
# Read-only. Exits 0 if all checks pass, non-zero otherwise.
# Usage:  bash tools/prod-runtime-check.sh
#

set -u
cd "$(dirname "$0")/.." || exit 1

PASS=0
FAIL=0
WARN=0

check() {
    local label="$1" status="$2" detail="$3"
    case "$status" in
        OK)   echo "  [PASS] $label — $detail"; PASS=$((PASS+1)) ;;
        WARN) echo "  [WARN] $label — $detail"; WARN=$((WARN+1)) ;;
        FAIL) echo "  [FAIL] $label — $detail"; FAIL=$((FAIL+1)) ;;
    esac
}

# ---------- T2.7a — .env sanity ----------
echo
echo "=== T2.7a — Production .env sanity ==="

APP_ENV=$(grep -E '^APP_ENV=' .env | cut -d= -f2- | tr -d '"' | awk '{print $1}')
[ "$APP_ENV" = "production" ] \
    && check "APP_ENV" OK "production" \
    || check "APP_ENV" FAIL "is '$APP_ENV', expected 'production'"

APP_DEBUG=$(grep -E '^APP_DEBUG=' .env | cut -d= -f2- | tr -d '"' | awk '{print $1}')
[ "$APP_DEBUG" = "false" ] \
    && check "APP_DEBUG" OK "false" \
    || check "APP_DEBUG" FAIL "is '$APP_DEBUG', expected 'false'"

LOG_LEVEL=$(grep -E '^LOG_LEVEL=' .env | cut -d= -f2- | tr -d '"' | awk '{print $1}')
case "$LOG_LEVEL" in
    warning|error|critical|alert|emergency)
        check "LOG_LEVEL" OK "$LOG_LEVEL (production-safe)" ;;
    *)
        check "LOG_LEVEL" FAIL "is '$LOG_LEVEL', expected warning or stricter" ;;
esac

LOG_CHANNEL=$(grep -E '^LOG_CHANNEL=' .env | cut -d= -f2- | tr -d '"' | awk '{print $1}')
[ "$LOG_CHANNEL" = "daily" ] || [ "$LOG_CHANNEL" = "stack" ] \
    && check "LOG_CHANNEL" OK "$LOG_CHANNEL" \
    || check "LOG_CHANNEL" WARN "is '$LOG_CHANNEL'; recommend daily"

# Audit 2026-05-18 — broadcast + web push readiness checks.
# Without these, NewBatchAnnouncement (and every other InAppNotification)
# falls back to database-only delivery — the bell badge still works but
# real-time updates + browser push are silently dropped.
BROADCAST_DRIVER=$(grep -E '^BROADCAST_DRIVER=' .env | cut -d= -f2- | tr -d '"' | awk '{print $1}')
case "$BROADCAST_DRIVER" in
    pusher|ably|reverb|redis)
        check "BROADCAST_DRIVER" OK "$BROADCAST_DRIVER (real-time enabled)" ;;
    log|null|"")
        check "BROADCAST_DRIVER" WARN "is '$BROADCAST_DRIVER' — real-time Pusher updates are DISABLED" ;;
    *)
        check "BROADCAST_DRIVER" WARN "unknown driver '$BROADCAST_DRIVER'" ;;
esac

# When BROADCAST_DRIVER=pusher, verify the keys are actually set.
if [ "$BROADCAST_DRIVER" = "pusher" ]; then
    PUSHER_KEY=$(grep -E '^PUSHER_APP_KEY=' .env | cut -d= -f2- | tr -d '"')
    PUSHER_SECRET=$(grep -E '^PUSHER_APP_SECRET=' .env | cut -d= -f2- | tr -d '"')
    if [ -n "$PUSHER_KEY" ] && [ -n "$PUSHER_SECRET" ]; then
        check "PUSHER keys" OK "set"
    else
        check "PUSHER keys" FAIL "BROADCAST_DRIVER=pusher but PUSHER_APP_KEY/SECRET empty"
    fi
fi

VAPID_PUBLIC=$(grep -E '^VAPID_PUBLIC_KEY=' .env | cut -d= -f2- | tr -d '"')
VAPID_PRIVATE=$(grep -E '^VAPID_PRIVATE_KEY=' .env | cut -d= -f2- | tr -d '"')
if [ -n "$VAPID_PUBLIC" ] && [ -n "$VAPID_PRIVATE" ]; then
    check "VAPID keys" OK "set — web push channel active"
else
    check "VAPID keys" WARN "VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY missing — web push silently skipped"
fi

# ---------- T2.7b — Scheduler / cron ----------
echo
echo "=== T2.7b — Scheduler / cron ==="

# Does crontab contain schedule:run?
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    check "crontab schedule:run" OK "registered"
else
    check "crontab schedule:run" FAIL "no 'schedule:run' line in crontab"
fi

# Is the queue draining? jobs table should not have ancient rows.
OLDEST_JOB_AGE_HOURS=$(php artisan tinker --execute='
    $row = DB::table("jobs")->orderBy("id","asc")->first();
    if (!$row) { echo "0"; exit; }
    echo (int) ((time() - $row->created_at) / 3600);
' 2>/dev/null | tail -1)

if [ -z "$OLDEST_JOB_AGE_HOURS" ] || [ "$OLDEST_JOB_AGE_HOURS" -eq 0 ]; then
    check "queue draining" OK "no pending jobs"
elif [ "$OLDEST_JOB_AGE_HOURS" -lt 1 ]; then
    check "queue draining" OK "oldest job ${OLDEST_JOB_AGE_HOURS}h ago"
elif [ "$OLDEST_JOB_AGE_HOURS" -lt 24 ]; then
    check "queue draining" WARN "oldest pending job is ${OLDEST_JOB_AGE_HOURS}h old"
else
    check "queue draining" FAIL "oldest pending job is ${OLDEST_JOB_AGE_HOURS}h old — queue:work not firing"
fi

# ---------- T2.7c — Backup health ----------
echo
echo "=== T2.7c — Backup health ==="

BACKUP_DIR=$(find storage/app -maxdepth 1 -type d -name "$(grep -E '^APP_NAME=' .env | cut -d= -f2- | tr -d '" ' | head -1)" | head -1)
[ -z "$BACKUP_DIR" ] && BACKUP_DIR=$(find storage/app -maxdepth 1 -type d ! -path 'storage/app' | head -1)

if [ -d "$BACKUP_DIR" ]; then
    LATEST=$(ls -t "$BACKUP_DIR"/*.zip 2>/dev/null | head -1)
    if [ -n "$LATEST" ]; then
        AGE_HOURS=$(( ($(date +%s) - $(stat -c %Y "$LATEST")) / 3600 ))
        if [ "$AGE_HOURS" -lt 30 ]; then
            check "latest backup" OK "$(basename "$LATEST") (${AGE_HOURS}h old)"
        elif [ "$AGE_HOURS" -lt 72 ]; then
            check "latest backup" WARN "$(basename "$LATEST") is ${AGE_HOURS}h old"
        else
            check "latest backup" FAIL "$(basename "$LATEST") is ${AGE_HOURS}h old — backup:run not firing"
        fi
    else
        check "latest backup" FAIL "no .zip files under $BACKUP_DIR"
    fi
else
    check "backup dir" FAIL "no backup directory under storage/app/"
fi

# ---------- T2.7d — Disk space ----------
# Audit 2026-05-19 — out-of-disk silently breaks backup:run, queue:work,
# and Telescope. Catch it before users see 500s.
echo
echo "=== T2.7d — Disk space ==="

USED_PCT=$(df -P . 2>/dev/null | tail -1 | awk '{ gsub("%","",$5); print $5+0 }')
if [ -z "$USED_PCT" ]; then
    check "disk usage" WARN "could not read df output"
elif [ "$USED_PCT" -ge 95 ]; then
    check "disk usage" FAIL "${USED_PCT}% used — critical, backup:run will fail"
elif [ "$USED_PCT" -ge 85 ]; then
    check "disk usage" WARN "${USED_PCT}% used — plan to free space"
else
    check "disk usage" OK "${USED_PCT}% used"
fi

# storage/logs/ can balloon if LOG_LEVEL=debug or daily rotation is off.
LOGS_MB=$(du -sm storage/logs 2>/dev/null | awk '{print $1}')
if [ -z "$LOGS_MB" ]; then
    check "storage/logs/" WARN "could not stat directory"
elif [ "$LOGS_MB" -ge 1024 ]; then
    check "storage/logs/" FAIL "${LOGS_MB} MB — daily rotation may be off or LOG_LEVEL=debug"
elif [ "$LOGS_MB" -ge 256 ]; then
    check "storage/logs/" WARN "${LOGS_MB} MB — check log rotation"
else
    check "storage/logs/" OK "${LOGS_MB} MB"
fi

# ---------- T2.7e — Laravel essentials ----------
echo
echo "=== T2.7e — Laravel essentials ==="

# APP_KEY must be non-empty + base64-prefixed (or 32 bytes).
APP_KEY=$(grep -E '^APP_KEY=' .env | cut -d= -f2- | tr -d '"')
if [ -z "$APP_KEY" ]; then
    check "APP_KEY" FAIL "empty — payment-secret decryption will fail"
elif [ "${APP_KEY#base64:}" != "$APP_KEY" ] || [ "${#APP_KEY}" -ge 32 ]; then
    check "APP_KEY" OK "set"
else
    check "APP_KEY" WARN "looks short — verify with php artisan key:generate --show"
fi

# storage/ -> public/storage symlink must exist for public uploads to render.
if [ -L public/storage ]; then
    check "storage:link" OK "symlink in place"
else
    check "storage:link" FAIL "public/storage symlink missing — run php artisan storage:link"
fi

# SESSION_DRIVER=file kills horizontal scaling; recommend database / redis.
SESSION_DRIVER=$(grep -E '^SESSION_DRIVER=' .env | cut -d= -f2- | tr -d '"' | awk '{print $1}')
case "$SESSION_DRIVER" in
    database|redis|memcached)
        check "SESSION_DRIVER" OK "$SESSION_DRIVER" ;;
    file)
        check "SESSION_DRIVER" WARN "file — fine on single-server, blocks horizontal scaling" ;;
    *)
        check "SESSION_DRIVER" WARN "unusual driver '$SESSION_DRIVER'" ;;
esac

# Encryption keypair sanity — payment-gateway secrets are encrypted with
# enc:v1:<APP_KEY>. If APP_KEY rotated without re-encrypting, decrypt fails.
# Probe one known-encrypted row.
DECRYPT_OK=$(php -d display_errors=0 artisan tinker --execute='
    try {
        $row = DB::table("payment_gateways")->where("key","razorpay_secret")->first();
        if (!$row) { echo "skip"; exit; }
        if (str_starts_with((string)$row->value, "enc:v1:")) {
            \App\Support\SecretSettings::decrypt($row->value);
            echo "ok";
        } else {
            echo "plaintext";
        }
    } catch (\Throwable $e) { echo "fail:" . $e->getMessage(); }
' 2>/dev/null | tail -1)

case "$DECRYPT_OK" in
    ok)        check "encrypted secrets" OK "razorpay_secret decrypts cleanly" ;;
    plaintext) check "encrypted secrets" WARN "razorpay_secret is plaintext — run php artisan gateway-secrets:encrypt-existing --commit" ;;
    skip)      check "encrypted secrets" WARN "no razorpay_secret row to probe" ;;
    fail:*)    check "encrypted secrets" FAIL "razorpay_secret won't decrypt — APP_KEY may have rotated. ${DECRYPT_OK#fail:}" ;;
    *)         check "encrypted secrets" WARN "probe returned: '$DECRYPT_OK'" ;;
esac

# ---------- T2.7f — Razorpay readiness (Fee Management) ----------
# Phase 5b 2026-05-19 — the Fee Management module depends on
# Razorpay being fully configured. Delegate to the dedicated artisan
# command so the rules stay in PHP (not duplicated in shell).
echo
echo "=== T2.7f — Razorpay readiness (Fee Management) ==="

RZP_OUTPUT=$(php artisan razorpay:test 2>&1)
RZP_EXIT=$?

# Parse the structured PASS/WARN/FAIL counts the command prints.
RZP_PASS=$(echo "$RZP_OUTPUT" | grep -oE 'PASS=[0-9]+' | head -1 | cut -d= -f2)
RZP_WARN=$(echo "$RZP_OUTPUT" | grep -oE 'WARN=[0-9]+' | head -1 | cut -d= -f2)
RZP_FAIL=$(echo "$RZP_OUTPUT" | grep -oE 'FAIL=[0-9]+' | head -1 | cut -d= -f2)

case "$RZP_EXIT" in
    0)
        check "Razorpay readiness" OK "all 5 sub-checks green (PASS=${RZP_PASS:-?})" ;;
    2)
        check "Razorpay readiness" WARN "PASS=${RZP_PASS} WARN=${RZP_WARN} FAIL=0 — usable, but address warnings before launch" ;;
    1)
        check "Razorpay readiness" FAIL "PASS=${RZP_PASS} WARN=${RZP_WARN} FAIL=${RZP_FAIL} — gateway NOT ready (run 'php artisan razorpay:test' for detail)" ;;
    *)
        check "Razorpay readiness" WARN "razorpay:test exited ${RZP_EXIT}" ;;
esac

# ---------- Summary ----------
echo
echo "============================================="
echo "PASS=$PASS  WARN=$WARN  FAIL=$FAIL"
echo "============================================="
[ "$FAIL" -gt 0 ] && exit 1
[ "$WARN" -gt 0 ] && exit 2
exit 0
