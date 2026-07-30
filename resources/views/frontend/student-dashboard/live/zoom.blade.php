{{-- Zoom Meeting SDK — Component View launcher (replaces the legacy
     Client View on 2026-05-07). Component View renders the meeting
     into a sized div, which lets us wrap it in MBS Guru chrome
     (course breadcrumb header + Leave button) instead of letting
     Zoom take over the whole viewport.

     The signature endpoint at /zoom/sdk-signature/{id} returns a JWT
     compatible with both views — no backend change. The SDK secret
     never reaches the browser.
--}}
@php
    $appName     = $setting?->app_name ?? config('app.name');
    $logo        = $setting?->logo ? asset($setting->logo) : null;
    $favicon     = $setting?->favicon ? asset($setting->favicon) : null;
    $brandPrimary   = $setting?->primary_color   ?: '#10b981';
    $brandSecondary = $setting?->secondary_color ?: '#ffc224';
    $courseTitle = $lesson->course?->title ?? '';
    $lessonTitle = $lesson->title ?? '';
    $isHost      = (int) ($lesson->course?->instructor_id ?? 0) === (int) userAuth()?->id;
    $courseSlug  = $lesson->course?->slug;

    // 2026-07-10 (New Changes for UI #9) — the exit redirect + the coach
    // management action must key off the PANEL/ROLE the user operates in,
    // not host-identity. A STAFF member is never $isHost (course.instructor_id
    // is the parent COACH id, not the staff's), so they were wrongly sent to
    // /student/live-classes and never saw "Mark class completed". Anyone
    // operating in the coach panel — a real coach (role='instructor') or a
    // staff member (non-student role WITH a coach_id) — belongs on
    // /instructor/live-classes and may manage the class. The backend
    // markCompleted() still re-authorises by ownership + assigned batch, so
    // surfacing the button is safe (an unauthorised staff POST 403s).
    $__lcUser     = userAuth();
    $inCoachPanel = $__lcUser
        && $__lcUser->role !== 'student'
        && ($__lcUser->role === 'instructor' || ! empty($__lcUser->coach_id));

    // Audit 2026-05-18 Req 4 — route by role.
    //   - Host (coach) ending the meeting was previously sent to the
    //     student.learning.index page; the student middleware then
    //     bounced them and the user perceived this as "got logged out".
    //   - Students leaving were sent to the course "video list"
    //     (student.learning.index) which the user said is the wrong
    //     destination — they expect to go back to their live-classes
    //     overview.
    //
    // Resolution:
    //   - Host -> /instructor/live-classes
    //   - Student -> /student/live-classes
    //   - Fallback url('/') if either route is somehow missing (no
    //     throw — never strand the user in the SDK iframe).
    try {
        if ($inCoachPanel) {
            $leaveUrl = route('instructor.live-classes.index');
        } else {
            $leaveUrl = route('student.live-classes.index');
        }
    } catch (\Throwable $e) {
        $leaveUrl = $courseSlug ? route('student.learning.index', $courseSlug) : url('/');
    }

    // Long error strings live as variables here because Blade's @json
    // directive uses a naive comma-split parser — embedding a string
    // with commas directly inside @json(__("..., ...")) breaks compile.
    $timeoutErrorMsg     = __("Couldn't reach Zoom in 20 seconds. The most common causes: you're already in this meeting in another browser tab or the Zoom desktop app, the meeting was deleted on Zoom's side, or the instructor's Zoom account isn't connected. Try closing other tabs and the Zoom app, then click Retry.");
    $accessDeniedMsg     = __("You don't have access to this class.");
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <title>{{ $lessonTitle }} — Live Class | {{ $appName }}</title>
    <meta charset="utf-8">
    <meta name="format-detection" content="telephone=no">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Live class: {{ $lessonTitle }}{{ $courseTitle ? ' — '.$courseTitle : '' }}">
    @if ($favicon)
        <link rel="shortcut icon" type="image/x-icon" href="{{ $favicon }}">
    @endif
    {{-- Zoom Meeting SDK Component View bootstrap CSS. Their build
         expects this to be loaded before the script for layout. --}}
    <link rel="stylesheet" href="https://source.zoom.us/3.8.5/css/bootstrap.css">
    <link rel="stylesheet" href="https://source.zoom.us/3.8.5/css/react-select.css">
    <style>
        /* =============================================================
           LIVE CLASS LAYOUT — 2026-05-11 Option-B redesign.

           Strategy: don't fight Zoom Component View's internal rendering
           (it picks a narrow ~310px gallery and there's no client-side
           way to force it wider — verified empirically via DOM probes).
           Instead, frame Zoom's native rendering in a centered, bounded
           card that LOOKS designed. The stage around the card uses a
           gradient + radial brand glows so empty space reads as
           "stage backdrop", not "broken layout".

           Responsive breakpoints:
             ≤ 639px (phone)     → card fills viewport, header compact
             640–1023px (tablet) → card near-full with small breathing room
             ≥ 1024px (desktop)  → card centered with full breathing room
           ============================================================= */
        :root {
            --brand-primary:   {{ $brandPrimary }};
            --brand-secondary: {{ $brandSecondary }};
            --header-height:   60px;
            --stage-pad:       16px;
            --sdk-max-w:       1100px;
            --sdk-max-h:       720px;
            --sdk-radius:      14px;
            --stage-bg:
                radial-gradient(circle at 20% 0%,  rgba(16, 185, 129,.16) 0%, transparent 50%),
                radial-gradient(circle at 80% 100%, rgba(255,194,36,.08) 0%, transparent 50%),
                linear-gradient(180deg, #0f1117 0%, #161922 100%);
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            height: 100dvh;       /* dvh = dynamic viewport height; handles mobile URL bar */
            width: 100vw;
            overflow: hidden;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1c1a4a;
            background: #0b0d12;
        }

        /* Top-level shell: header row + stage row, full viewport. */
        .mbs-live-shell {
            display: flex;
            flex-direction: column;
            height: 100dvh;
            width: 100vw;
        }

        /* ============== HEADER (sticky top) ============== */
        .mbs-live-header {
            flex: 0 0 auto;
            min-height: var(--header-height);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 20px;
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
            z-index: 10;
        }
        .mbs-live-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;     /* allows lesson title to ellipsis */
            flex: 1 1 auto;
        }
        .mbs-live-brand img {
            height: 32px;
            max-width: 140px;
            object-fit: contain;
            flex-shrink: 0;
        }
        .mbs-live-brand-text {
            font-weight: 700;
            font-size: 16px;
            color: var(--brand-primary);
            flex-shrink: 0;
        }
        .mbs-live-context {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
        }
        .mbs-live-course {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .04em;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mbs-live-lesson {
            font-size: 14px;
            font-weight: 600;
            color: #1c1a4a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .mbs-live-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .mbs-live-role-badge {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 4px 10px;
            border-radius: 999px;
            background: rgba(16, 185, 129,.1);
            color: var(--brand-primary);
            white-space: nowrap;
        }
        .mbs-live-leave {
            padding: 8px 16px;
            background: #ef4444;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: filter .15s, transform .1s;
            white-space: nowrap;
        }
        .mbs-live-leave:hover  { filter: brightness(0.92); }
        .mbs-live-leave:active { transform: scale(.97); }

        /* ============== STAGE — wraps the SDK card ============== */
        .mbs-live-stage {
            flex: 1 1 auto;
            min-height: 0;     /* allows the child to shrink properly inside flex */
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--stage-pad);
            background: var(--stage-bg);
            position: relative;
            overflow: hidden;
        }
        /* Subtle "LIVE" watermark in the stage corner — designed look. */
        .mbs-live-stage::before {
            content: 'LIVE • ' attr(data-course);
            position: absolute;
            bottom: 14px;
            right: 18px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .12em;
            color: rgba(255,255,255,.18);
            text-transform: uppercase;
            pointer-events: none;
            max-width: 40vw;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ============== SDK CONTAINER ==============
           Bounded card. Zoom Component View renders its own UI inside —
           we don't control aspect / column count. The card just gives
           it a frame so the layout doesn't sprawl. */
        #meetingSDKElement {
            
            position: relative;
            width: 100%;
            max-width: var(--sdk-max-w);
            height: 100%;
            max-height: var(--sdk-max-h);
            background: linear-gradient(135deg, #1a1c4a 0%, #0f1230 100%);
            border-radius: var(--sdk-radius);
            overflow: hidden;
            box-shadow:
                0 16px 48px rgba(0,0,0,.45),
                0 0 0 1px rgba(255,255,255,.04);
        }

        /* === RESPONSIVE === */

        /* Phone: card fills the screen, header compresses. */
        @media (max-width: 639px) {
            :root {
                --stage-pad:  0;
                --sdk-radius: 0;
                --sdk-max-h:  100%;
                --header-height: 52px;
            }
            .mbs-live-header { padding: 8px 12px; gap: 8px; }
            .mbs-live-header img { height: 24px; max-width: 88px; }
            .mbs-live-course { display: none; }
            .mbs-live-lesson { font-size: 13px; }
            .mbs-live-role-badge { display: none; }
            .mbs-live-leave { padding: 6px 12px; font-size: 12px; }
            .mbs-live-stage::before { display: none; }
            #meetingSDKElement { box-shadow: none; }
        }

        /* Tablet: light padding, near-full card. */
        @media (min-width: 640px) and (max-width: 1023px) {
            :root {
                --stage-pad:  8px;
                --sdk-radius: 10px;
            }
            .mbs-live-stage::before { font-size: 10px; bottom: 10px; right: 12px; }
        }

        /* Reduce visual noise for users who prefer it. */
        @media (prefers-reduced-motion: reduce) {
            .mbs-live-leave { transition: none; }
        }

        /* Splash card while the SDK boots — sits above the SDK container,
           hidden once join completes. */
        #zoom-splash {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
            background: radial-gradient(ellipse at top, rgba(16, 185, 129,.18), transparent 60%),
                        linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            z-index: 5;
        }
        .zs-card {
            width: 100%; max-width: 480px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(28, 26, 74, 0.08);
            padding: 28px 24px;
            text-align: center;
        }
        .zs-spinner {
            width: 44px; height: 44px;
            border: 3px solid #e5e7eb;
            border-top-color: var(--brand-primary);
            border-radius: 50%;
            animation: zs-spin 0.9s linear infinite;
            margin: 0 auto 14px;
        }
        @keyframes zs-spin { to { transform: rotate(360deg); } }
        .zs-status { font-size: 14px; color: #4b5563; min-height: 20px; }

        /* Error state */
        #zoom-splash.error .zs-card { border-color: #fecaca; }
        #zoom-splash.error .zs-spinner { display: none; }
        #zoom-splash.error .zs-error-icon {
            width: 44px; height: 44px; margin: 0 auto 14px;
            display: flex; align-items: center; justify-content: center;
            background: #fef2f2; border-radius: 50%;
            color: #b91c1c; font-size: 24px; font-weight: 700;
        }
        #zoom-splash:not(.error) .zs-error-icon { display: none; }
        #zoom-splash.error .zs-status { color: #b91c1c; font-weight: 500; }
        .zs-actions { display: flex; gap: 8px; justify-content: center; margin-top: 18px; flex-wrap: wrap; }
        .zs-back, .zs-retry {
            display: inline-block;
            padding: 10px 20px; border-radius: 10px;
            font-weight: 600; font-size: 14px; text-decoration: none;
            border: none; cursor: pointer;
        }
        .zs-back { background: var(--brand-primary); color: #fff; }
        .zs-retry { background: #f3f4f6; color: #1c1a4a; }
        .zs-retry:hover { background: #e5e7eb; }
        #zoom-splash:not(.error) .zs-actions { display: none; }

        /* End-of-class wrap-up overlay — same card chrome, success colour. */
        #wrapup-overlay {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            padding: 24px;
            background: radial-gradient(ellipse at top, rgba(34,197,94,.12), transparent 60%),
                        linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            z-index: 6;
        }
        .wu-card {
            width: 100%; max-width: 520px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(28, 26, 74, 0.08);
            padding: 32px 28px;
            text-align: center;
        }
        .wu-icon {
            width: 56px; height: 56px; margin: 0 auto 16px;
            display: flex; align-items: center; justify-content: center;
            background: #dcfce7; color: #16a34a;
            border-radius: 50%;
            font-size: 28px;
        }
        .wu-title { font-size: 20px; font-weight: 700; color: #1c1a4a; margin: 0 0 6px; }
        .wu-course { font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; font-weight: 600; margin-bottom: 4px; }
        .wu-lesson { font-size: 15px; font-weight: 500; color: #1c1a4a; margin-bottom: 16px; }
        .wu-stats {
            display: flex; justify-content: center; gap: 32px;
            background: #f8fafc; border-radius: 12px;
            padding: 14px 18px; margin: 18px 0 22px;
        }
        .wu-stat { text-align: center; }
        .wu-stat-num { font-size: 22px; font-weight: 700; color: var(--brand-primary); line-height: 1; }
        .wu-stat-label { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; margin-top: 4px; }
        .wu-actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .wu-btn {
            padding: 10px 20px; border-radius: 10px;
            font-weight: 600; font-size: 14px; cursor: pointer;
            border: none; text-decoration: none;
            transition: filter .15s, opacity .15s;
        }
        .wu-btn-primary { background: var(--brand-primary); color: #fff; }
        .wu-btn-primary:hover { filter: brightness(0.92); color: #fff; }
        .wu-btn-secondary { background: #f3f4f6; color: #1c1a4a; }
        .wu-btn-secondary:hover { background: #e5e7eb; color: #1c1a4a; }
        .wu-btn:disabled { opacity: 0.6; cursor: default; }
        .wu-feedback { font-size: 13px; color: #16a34a; margin-top: 14px; min-height: 18px; }
        .wu-feedback.error { color: #b91c1c; }

        /* ============== NOTES DRAWER ==============
           Slides in from the right, overlays the meeting (doesn't resize
           the SDK so video quality isn't affected). Toggle button sits at
           the right edge of the meeting container always. */
        .mbs-notes-toggle {
            position: absolute;
            right: 0; top: 12px;
            z-index: 7;
            padding: 8px 12px 8px 14px;
            background: var(--brand-primary);
            color: #fff;
            border: none;
            border-radius: 8px 0 0 8px;
            font-weight: 600; font-size: 12px;
            letter-spacing: .04em; text-transform: uppercase;
            cursor: pointer;
            box-shadow: -2px 4px 12px rgba(0,0,0,.18);
            display: flex; align-items: center; gap: 8px;
        }
        .mbs-notes-toggle:hover { filter: brightness(0.94); }
        .mbs-notes-toggle .mbs-notes-toggle-dot {
            width: 7px; height: 7px;
            background: var(--brand-secondary);
            border-radius: 50%;
            display: none;
        }
        .mbs-notes-toggle[data-has-notes="1"] .mbs-notes-toggle-dot { display: inline-block; }

        .mbs-notes-drawer {
            position: absolute;
            right: 0; top: 0; bottom: 0;
            width: 340px;
            max-width: 90vw;
            background: #fff;
            box-shadow: -8px 0 28px rgba(0,0,0,.18);
            display: flex; flex-direction: column;
            transform: translateX(100%);
            transition: transform .25s ease;
            z-index: 8;
        }
        .mbs-notes-drawer.is-open { transform: translateX(0); }
        .mbs-notes-header {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 16px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
        }
        .mbs-notes-title { font-size: 14px; font-weight: 700; color: #1c1a4a; margin: 0; }
        .mbs-notes-close {
            background: transparent; border: none;
            font-size: 22px; line-height: 1; color: #6b7280;
            cursor: pointer; padding: 0 6px;
        }
        .mbs-notes-close:hover { color: #1c1a4a; }
        .mbs-notes-context {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            background: #fff;
        }
        .mbs-notes-context-course { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
        .mbs-notes-context-lesson { font-size: 13px; color: #1c1a4a; font-weight: 500; margin-top: 2px; }
        .mbs-notes-body {
            flex: 1 1 auto;
            display: flex; flex-direction: column;
            padding: 12px 16px;
            min-height: 0;
        }
        .mbs-notes-textarea {
            flex: 1 1 auto;
            width: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px; line-height: 1.5;
            color: #1c1a4a;
            font-family: inherit;
            resize: none;
        }
        .mbs-notes-textarea:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129,.1);
        }
        .mbs-notes-status {
            margin-top: 8px;
            font-size: 11px;
            color: #9ca3af;
            min-height: 16px;
        }
        .mbs-notes-status.saved { color: #16a34a; }
        .mbs-notes-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding: 10px 16px;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
        }
        .mbs-notes-clear {
            background: transparent; border: none;
            color: #9ca3af; font-size: 11px;
            cursor: pointer; text-decoration: underline;
        }
        .mbs-notes-clear:hover { color: #b91c1c; }
        .mbs-notes-hint { font-size: 10px; color: #9ca3af; }

        /* 2026-06-05 — Pre-join countdown / "waiting for your coach" gate.
           Rendered inside the existing splash card, so it inherits the same
           centered, responsive frame and never breaks the launcher layout. */
        .zs-gate { display:flex; flex-direction:column; align-items:center; gap:14px; padding:6px 4px; }
        .zs-gate-title { font-size:18px; font-weight:700; color:#111827; text-align:center; line-height:1.35; }
        .zs-gate-coach { font-size:13px; color:#6b7280; }
        .zs-gate-when { font-size:13px; color:#374151; font-weight:600; text-align:center; }
        .zs-gate-count { font-size:42px; font-weight:800; letter-spacing:1px; color:{{ $brandPrimary }}; font-variant-numeric:tabular-nums; line-height:1.1; }
        .zs-gate-msg { font-size:14px; color:#374151; text-align:center; max-width:430px; line-height:1.5; }
        .zs-gate-sub { font-size:12px; color:#9ca3af; text-align:center; max-width:430px; line-height:1.5; }
        .zs-gate-spin { width:48px; height:48px; border-radius:50%; border:4px solid #e5e7eb; border-top-color:{{ $brandPrimary }}; animation:zsgatespin 0.9s linear infinite; }
        @keyframes zsgatespin { to { transform: rotate(360deg); } }
        @media (max-width:480px){ .zs-gate-count{ font-size:34px; } .zs-gate-title{ font-size:16px; } }
    </style>
    <style>
        /* 2026-07-10 (New Changes for UI #4) — dark mode for this page's bespoke components. */
        html[data-theme="dark"] .mbs-live-header { background: #1e293b; border-bottom-color: #2a3a55; box-shadow: none; }
        html[data-theme="dark"] .mbs-live-course { color: #94a3b8; }
        html[data-theme="dark"] .mbs-live-lesson { color: #e2e8f0; }
        html[data-theme="dark"] .zs-card { background: #1e293b; border-color: #2a3a55; box-shadow: none; }
        html[data-theme="dark"] .zs-spinner { border-color: #2a3a55; border-top-color: var(--brand-primary); }
        html[data-theme="dark"] .zs-retry { background: #22304a; color: #e2e8f0; }
        html[data-theme="dark"] .zs-retry:hover { background: #2a3a55; }
        html[data-theme="dark"] .wu-card { background: #1e293b; border-color: #2a3a55; box-shadow: none; }
        html[data-theme="dark"] .wu-title { color: #e2e8f0; }
        html[data-theme="dark"] .wu-course { color: #94a3b8; }
        html[data-theme="dark"] .wu-lesson { color: #e2e8f0; }
        html[data-theme="dark"] .wu-stats { background: #17233a; }
        html[data-theme="dark"] .wu-stat-label { color: #94a3b8; }
        html[data-theme="dark"] .wu-btn-secondary { background: #22304a; color: #e2e8f0; }
        html[data-theme="dark"] .wu-btn-secondary:hover { background: #2a3a55; color: #e2e8f0; }
        html[data-theme="dark"] .mbs-notes-drawer { background: #1e293b; }
        html[data-theme="dark"] .mbs-notes-header { background: #17233a; border-bottom-color: #2a3a55; }
        html[data-theme="dark"] .mbs-notes-title { color: #e2e8f0; }
        html[data-theme="dark"] .mbs-notes-close { color: #94a3b8; }
        html[data-theme="dark"] .mbs-notes-close:hover { color: #e2e8f0; }
        html[data-theme="dark"] .mbs-notes-context { background: #1e293b; border-bottom-color: #2a3a55; }
        html[data-theme="dark"] .mbs-notes-context-course { color: #94a3b8; }
        html[data-theme="dark"] .mbs-notes-context-lesson { color: #e2e8f0; }
        html[data-theme="dark"] .mbs-notes-textarea { background: #17233a; border-color: #2a3a55; color: #e2e8f0; }
        html[data-theme="dark"] .mbs-notes-status { color: #94a3b8; }
        html[data-theme="dark"] .mbs-notes-footer { background: #17233a; border-top-color: #2a3a55; }
        html[data-theme="dark"] .mbs-notes-clear { color: #94a3b8; }
        html[data-theme="dark"] .mbs-notes-hint { color: #94a3b8; }
        html[data-theme="dark"] .zs-gate-title { color: #e2e8f0; }
        html[data-theme="dark"] .zs-gate-coach { color: #94a3b8; }
        html[data-theme="dark"] .zs-gate-sub { color: #94a3b8; }
        html[data-theme="dark"] .zs-gate-spin { border-color: #2a3a55; border-top-color: var(--brand-primary); }
    </style>
</head>
<body>
<div class="mbs-live-shell">
    {{-- ============== MBS GURU CHROME ============== --}}
    <header class="mbs-live-header">
        <div class="mbs-live-brand">
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $appName }}">
            @else
                <span class="mbs-live-brand-text">{{ $appName }}</span>
            @endif
            <div class="mbs-live-context">
                @if ($courseTitle)
                    <div class="mbs-live-course">{{ $courseTitle }}</div>
                @endif
                <div class="mbs-live-lesson">{{ $lessonTitle }}</div>
            </div>
        </div>
        <div class="mbs-live-meta">
            <span class="mbs-live-role-badge">{{ $isHost ? 'Host' : 'Attendee' }}</span>
            <button type="button" class="mbs-live-leave" id="mbsLeaveBtn">{{ __('Leave class') }}</button>
        </div>
    </header>

    {{-- ============== STAGE + ZOOM COMPONENT VIEW CONTAINER ==============
         The stage centers + frames the SDK card. data-course feeds the
         "LIVE • <course>" watermark CSS pseudo-element so the empty
         space around the card reads as branded backdrop rather than
         broken layout. --}}
    <section class="mbs-live-stage" data-course="{{ $courseTitle }}">
        <main id="meetingSDKElement">
            <div id="zoom-splash">
            <div class="zs-card">
                <div class="zs-spinner"></div>
                <div class="zs-error-icon">!</div>
                <div id="zs-status" class="zs-status">{{ __('Connecting to your live class…') }}</div>
                <div class="zs-actions">
                    <button type="button" class="zs-retry" onclick="window.location.reload()">{{ __('Retry') }}</button>
                    <a href="{{ $leaveUrl }}" class="zs-back">{{ __('Back to course') }}</a>
                </div>
            </div>
        </div>

        {{-- Notes drawer — slides in from the right. Overlay rather than
             pushing the SDK so video stays the same size. Notes persist
             to localStorage keyed by lesson id, never sent server-side. --}}
        <button type="button" class="mbs-notes-toggle" id="mbsNotesToggle" data-has-notes="0">
            <span class="mbs-notes-toggle-dot"></span>
            <span>{{ __('Notes') }}</span>
        </button>
        <aside class="mbs-notes-drawer" id="mbsNotesDrawer" aria-hidden="true">
            <div class="mbs-notes-header">
                <h3 class="mbs-notes-title">{{ __('My notes') }}</h3>
                <button type="button" class="mbs-notes-close" id="mbsNotesClose" aria-label="{{ __('Close') }}">×</button>
            </div>
            @if ($courseTitle || $lessonTitle)
                <div class="mbs-notes-context">
                    @if ($courseTitle)
                        <div class="mbs-notes-context-course">{{ $courseTitle }}</div>
                    @endif
                    <div class="mbs-notes-context-lesson">{{ $lessonTitle }}</div>
                </div>
            @endif
            <div class="mbs-notes-body">
                <textarea id="mbsNotesText" class="mbs-notes-textarea"
                          placeholder="{{ __('Take notes during the class. Saved to this browser only.') }}"></textarea>
                <div class="mbs-notes-status" id="mbsNotesStatus"></div>
            </div>
            <div class="mbs-notes-footer">
                <button type="button" class="mbs-notes-clear" id="mbsNotesClear">{{ __('Clear notes') }}</button>
                <span class="mbs-notes-hint">{{ __('Stored in this browser only') }}</span>
            </div>
        </aside>
        </main>
    </section>
</div>

{{-- Component View SDK bundle. The embedded build expects React /
     Redux / lodash as globals (it's not self-contained — Zoom's docs
     are explicit about loading these vendor scripts first). Without
     them ZoomMtgEmbedded fails to initialise. --}}
<script src="https://source.zoom.us/3.8.5/lib/vendor/react.min.js"></script>
<script src="https://source.zoom.us/3.8.5/lib/vendor/react-dom.min.js"></script>
<script src="https://source.zoom.us/3.8.5/lib/vendor/redux.min.js"></script>
<script src="https://source.zoom.us/3.8.5/lib/vendor/redux-thunk.min.js"></script>
<script src="https://source.zoom.us/3.8.5/lib/vendor/lodash.min.js"></script>
<script src="https://source.zoom.us/zoom-meeting-embedded-3.8.5.min.js"></script>

<script>
// ============== Notes drawer ==============
// Two-tier persistence: localStorage (instant, offline-tolerant) + server
// (cross-device sync). Server takes precedence on initial load if it has
// a more recent updated_at than localStorage's last-saved timestamp.
(function () {
    "use strict";
    var lessonId       = @json($lesson->id);
    var notesShowUrl   = @json(route('lesson-notes.show', ['lesson_id' => $lesson->id]));
    var notesStoreUrl  = @json(route('lesson-notes.store', ['lesson_id' => $lesson->id]));
    var STORAGE_KEY    = 'mbs-live-notes-v1-' + lessonId;
    var STORAGE_TS_KEY = 'mbs-live-notes-v1-ts-' + lessonId;
    var SAVE_DEBOUNCE_MS = 800;
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    var toggleBtn = document.getElementById('mbsNotesToggle');
    var drawer    = document.getElementById('mbsNotesDrawer');
    var closeBtn  = document.getElementById('mbsNotesClose');
    var textarea  = document.getElementById('mbsNotesText');
    var statusEl  = document.getElementById('mbsNotesStatus');
    var clearBtn  = document.getElementById('mbsNotesClear');
    if (!toggleBtn || !drawer || !textarea) return;

    function setHasNotesIndicator(hasContent) {
        toggleBtn.setAttribute('data-has-notes', hasContent ? '1' : '0');
    }
    function setStatus(text, kind) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.classList.toggle('saved', kind === 'saved');
    }

    // 1. Restore from localStorage immediately (instant render, no network).
    var localBody = '';
    var localTs   = 0;
    try {
        localBody = localStorage.getItem(STORAGE_KEY) || '';
        localTs   = parseInt(localStorage.getItem(STORAGE_TS_KEY) || '0', 10) || 0;
    } catch (e) {}
    textarea.value = localBody;
    setHasNotesIndicator(localBody.length > 0);

    // 2. Then ask the server. If the server's copy is newer (different
    //    device's edit), replace the textarea. If local is newer, push.
    fetch(notesShowUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data) return;
        var serverBody = data.body || '';
        var serverTs   = data.updated_at ? Date.parse(data.updated_at) : 0;
        if (serverTs && serverTs > localTs && serverBody !== textarea.value) {
            textarea.value = serverBody;
            try {
                localStorage.setItem(STORAGE_KEY, serverBody);
                localStorage.setItem(STORAGE_TS_KEY, String(serverTs));
            } catch (e) {}
            setHasNotesIndicator(serverBody.length > 0);
            setStatus('{{ __('Synced from another device.') }}', 'saved');
        }
      })
      .catch(function () { /* offline-tolerant — local copy is fine */ });

    function open()  { drawer.classList.add('is-open');    drawer.setAttribute('aria-hidden', 'false'); textarea.focus(); }
    function close() { drawer.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true'); }

    toggleBtn.addEventListener('click', function () {
        drawer.classList.contains('is-open') ? close() : open();
    });
    closeBtn.addEventListener('click', close);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && drawer.classList.contains('is-open')) close();
    });

    var saveTimer = null;
    function scheduleSave() {
        setStatus('{{ __('Saving…') }}', '');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            var body = textarea.value;
            var nowMs = Date.now();
            // localStorage write first — instant feedback even if network fails.
            try {
                localStorage.setItem(STORAGE_KEY, body);
                localStorage.setItem(STORAGE_TS_KEY, String(nowMs));
                setHasNotesIndicator(body.length > 0);
            } catch (e) {
                setStatus('{{ __('Could not save (browser storage blocked).') }}', '');
                return;
            }
            // Then mirror to server. Best-effort — local copy already saved.
            var fd = new FormData();
            fd.append('body', body);
            fd.append('_token', csrfToken);
            fetch(notesStoreUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            }).then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                var t = new Date();
                setStatus('{{ __('Saved everywhere at') }} ' + t.toLocaleTimeString(), 'saved');
            }).catch(function () {
                var t = new Date();
                setStatus('{{ __('Saved locally at') }} ' + t.toLocaleTimeString() + ' ({{ __('server sync failed') }})', 'saved');
            });
        }, SAVE_DEBOUNCE_MS);
    }
    textarea.addEventListener('input', scheduleSave);

    clearBtn.addEventListener('click', function () {
        if (!textarea.value || confirm('{{ __('Erase all notes for this class?') }}')) {
            textarea.value = '';
            try {
                localStorage.removeItem(STORAGE_KEY);
                localStorage.removeItem(STORAGE_TS_KEY);
            } catch (e) {}
            setHasNotesIndicator(false);
            // Also clear on the server.
            var fd = new FormData();
            fd.append('body', '');
            fd.append('_token', csrfToken);
            fetch(notesStoreUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            }).catch(function () {});
            setStatus('{{ __('Notes cleared.') }}', '');
            textarea.focus();
        }
    });

    // Final flush on tab close so an unsaved keystroke is preserved
    // locally (server save is best-effort and may not complete here).
    window.addEventListener('pagehide', function () {
        try {
            localStorage.setItem(STORAGE_KEY, textarea.value);
            localStorage.setItem(STORAGE_TS_KEY, String(Date.now()));
        } catch (e) {}
        if (navigator.sendBeacon) {
            var formBody = '_token=' + encodeURIComponent(csrfToken) +
                           '&body=' + encodeURIComponent(textarea.value);
            navigator.sendBeacon(
                notesStoreUrl,
                new Blob([formBody], { type: 'application/x-www-form-urlencoded' })
            );
        }
    });
})();

(function () {
    "use strict";

    var splashEl = document.getElementById("zoom-splash");
    var statusEl = document.getElementById("zs-status");
    var leaveBtn = document.getElementById("mbsLeaveBtn");
    var leaveUrl = "{{ $leaveUrl }}";
    var splashSnapshot = splashEl ? splashEl.outerHTML : '';

    // Attendance endpoint — populated when the launcher knows the
    // live-class row id. The lesson eager-loads `live` so we have it
    // server-side. If the row is missing the endpoint stays null and
    // we silently skip attendance logging instead of breaking the join.
    var attendanceUrl = @json($lesson->live?->id ? route('live-class.attendance', ['live_class_id' => $lesson->live->id]) : null);
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute("content");
    var attendanceLogged = false;

    // Wrap-up state — populated once the user actually joins so we can show
    // an end-of-class summary with their attendance duration when the
    // meeting closes.
    var joinedAt = null;
    var wrapupShown = false;
    var lessonId = @json($lesson->id);
    var lessonTitle = @json($lessonTitle);
    var courseTitle = @json($courseTitle);
    // 2026-06-05 — Completion is now a COACH action (see LiveClassController::
    // markCompleted). The host gets a "Mark class completed" button posting to
    // the coach-only live-class.complete route; it sets ended_at AND credits
    // attendance to each attendee's course progress. Students no longer self-
    // mark — their live lesson is credited automatically when the coach
    // completes the class. (The old student route logged a coach OUT — bug #5.)
    var completeClassUrl = @json(($lesson->live?->id && \Illuminate\Support\Facades\Route::has('instructor.live-class.complete')) ? route('instructor.live-class.complete', ['live_class_id' => $lesson->live->id]) : null);
    var isHost = @json((bool) ($isHost ?? false));
    // Coach-panel operators (coach OR authorised staff) may end the class.
    var canManage = @json((bool) ($inCoachPanel ?? false));

    function showWrapUp() {
        if (wrapupShown) return;
        wrapupShown = true;

        var sdkRoot = document.getElementById("meetingSDKElement");
        if (!sdkRoot) return;

        var minutes = 0;
        if (joinedAt) {
            minutes = Math.max(1, Math.round((Date.now() - joinedAt) / 60000));
        }

        var html = ''
            + '<div id="wrapup-overlay">'
            +   '<div class="wu-card">'
            +     '<div class="wu-icon">✓</div>'
            +     '<h2 class="wu-title">' + ' {!! __('Class ended') !!}' + '</h2>'
            +     (courseTitle ? '<div class="wu-course">' + escapeHtml(courseTitle) + '</div>' : '')
            +     '<div class="wu-lesson">' + escapeHtml(lessonTitle) + '</div>'
            +     '<div class="wu-stats">'
            +       '<div class="wu-stat">'
            +         '<div class="wu-stat-num">' + minutes + '</div>'
            +         '<div class="wu-stat-label">' + ' {!! __('minutes attended') !!}' + '</div>'
            +       '</div>'
            +     '</div>'
            +     '<div class="wu-actions">'
            +       (canManage && completeClassUrl ? '<button type="button" class="wu-btn wu-btn-primary" id="wuCompleteClass">' + ' {!! __('Mark class completed') !!}' + '</button>' : '')
            +       '<a href="' + escapeHtml(leaveUrl) + '" class="wu-btn wu-btn-secondary">' + ' {!! __('Back to live class') !!}' + '</a>'
            +     '</div>'
            +     '<div class="wu-feedback" id="wuFeedback"></div>'
            +   '</div>'
            + '</div>';

        sdkRoot.insertAdjacentHTML('beforeend', html);

        // Coach-only "Mark class completed" — posts to the instructor route
        // (sets ended_at + credits attendance to course progress). The button
        // is absent for students (and for a missing live-class id), so this
        // listener simply never attaches for them.
        if (document.getElementById('wuCompleteClass')) document.getElementById('wuCompleteClass').addEventListener('click', function () {
            var btn = this;
            var fb  = document.getElementById('wuFeedback');
            if (!completeClassUrl) return;
            btn.disabled = true;
            fb.classList.remove('error');
            fb.textContent = ' {!! __('Saving…') !!}';

            fetch(completeClassUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
            }).then(function (res) {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            }).then(function (d) {
                fb.textContent = (d && d.message) ? d.message : ' {!! __('Live class marked as completed.') !!}';
                btn.textContent = ' {!! __('Completed ✓') !!}';
            }).catch(function (err) {
                fb.classList.add('error');
                fb.textContent = ' {!! __('Could not save:') !!} ' + (err && err.message ? err.message : 'unknown');
                btn.disabled = false;
            });
        });
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // No passcode is ever passed to the SDK. Authentication lives in the
    // LMS — the signature endpoint already checks enrollment + role. New
    // meetings created via LiveClassController set `password: false` on
    // Zoom too, so server-side enforcement is also off. If a legacy
    // meeting still enforces a passcode on Zoom's side, the operator
    // needs to disable it on zoom.us; the launcher does not pretend to
    // recover that state by typing.
    function escapeHtmlAttr(s) { return String(s == null ? '' : s).replace(/"/g, '&quot;').replace(/&/g, '&amp;'); }
    function escapeHtmlText(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function postAttendance(event, useBeacon) {
        if (!attendanceUrl) return;
        if (useBeacon && navigator.sendBeacon) {
            // sendBeacon during pagehide can't set headers, so the CSRF
            // token has to ride in the body as a form field. Laravel's
            // VerifyCsrfToken middleware reads `_token` from form input.
            var formBody = '_token=' + encodeURIComponent(csrfToken) +
                           '&event=' + encodeURIComponent(event);
            navigator.sendBeacon(
                attendanceUrl,
                new Blob([formBody], { type: 'application/x-www-form-urlencoded' })
            );
            return;
        }
        fetch(attendanceUrl, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken,
                "X-Requested-With": "XMLHttpRequest"
            },
            body: JSON.stringify({ event: event }),
            keepalive: true
        }).catch(function () { /* attendance is best-effort */ });
    }

    function setStatus(msg) {
        if (!statusEl) statusEl = document.getElementById("zs-status");
        if (statusEl) statusEl.textContent = msg;
    }
    function showError(msg) {
        // Re-inject splash if it was already removed when the meeting started.
        if (!document.getElementById("zoom-splash") && splashSnapshot) {
            var sdkEl = document.getElementById("meetingSDKElement");
            if (sdkEl) sdkEl.insertAdjacentHTML('afterbegin', splashSnapshot);
        }
        splashEl = document.getElementById("zoom-splash");
        statusEl = document.getElementById("zs-status");
        if (splashEl) splashEl.classList.add("error");
        setStatus(msg);
    }
    function hideSplash() {
        var el = document.getElementById("zoom-splash");
        if (el && el.parentNode) el.parentNode.removeChild(el);
    }

    // Leave button.
    //  - If the user has already joined: ask the SDK to leave; the
    //    connection-change handler will fire Closed and then showWrapUp()
    //    renders the end-of-class summary. We do NOT navigate here so the
    //    user has a chance to click "Mark lesson complete" first.
    //  - If they haven't joined yet (still in the splash): navigate
    //    immediately, since there's no meeting to wrap up.
    leaveBtn.addEventListener('click', function () {
        try {
            if (window.__mbsZoomClient && typeof window.__mbsZoomClient.leaveMeeting === 'function') {
                window.__mbsZoomClient.leaveMeeting().catch(function () {
                    // SDK leave failed — fall through to a hard redirect
                    // so the user isn't stuck on a stale meeting view.
                    window.location.href = leaveUrl;
                });
                return;
            }
        } catch (e) { /* fall through to redirect */ }
        window.location.href = leaveUrl;
    });

    // ===================== PRE-JOIN GATE (2026-06-05) =====================
    // Students never enter before the scheduled start AND before the coach has
    // joined; the host may start within the early-join buffer. The launcher
    // polls the SERVER status endpoint, shows a countdown (driven by server
    // time, not the browser clock) then a "waiting for your coach" screen, and
    // only runs the signature/join flow once the server returns can_join.
    var statusUrl       = "{{ route('zoom.live-status', $lesson->id) }}";
    var POLL_MS         = 7000;
    var pollTimer       = null;
    var countdownTimer  = null;
    var serverSkewMs    = 0;      // serverNow - clientNow
    var scheduledStartMs = null;
    var joinFlowStarted = false;
    var gateMode        = null;   // 'count' | 'coach'
    var coachName       = @json($lesson->course?->instructor?->name ?? '');
    var splashCardOriginal = (function () {
        var c = document.querySelector('#zoom-splash .zs-card');
        return c ? c.innerHTML : null;
    })();

    var coachLabel    = @json(__('Coach'));
    var backLabel     = @json(__('Back to course'));
    var startingLabel = @json(__('Starting…'));
    var notStartedMsg = @json(__('Your live class has not started yet.'));
    var waitCoachMsg  = @json(__('Please wait. Your coach has not joined the live class yet.'));
    var waitCoachSub  = @json(__('You will be connected automatically as soon as the coach starts the class.'));

    function serverNow() { return Date.now() + serverSkewMs; }

    function clearGateTimers() {
        if (pollTimer)      { clearInterval(pollTimer);      pollTimer = null; }
        if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
    }

    function fmtCountdown(ms) {
        if (ms < 0) ms = 0;
        var s = Math.floor(ms / 1000);
        var d = Math.floor(s / 86400); s -= d * 86400;
        var h = Math.floor(s / 3600);  s -= h * 3600;
        var m = Math.floor(s / 60);    s -= m * 60;
        function p(n) { return (n < 10 ? '0' : '') + n; }
        return (d > 0 ? d + 'd ' : '') + p(h) + ':' + p(m) + ':' + p(s);
    }

    function startCountdownTicker() {
        if (countdownTimer) clearInterval(countdownTimer);
        function tick() {
            var el = document.getElementById('zsGateCount');
            if (!el || scheduledStartMs == null) return;
            var left = scheduledStartMs - serverNow();
            el.textContent = left <= 0 ? startingLabel : fmtCountdown(left);
        }
        tick();
        countdownTimer = setInterval(tick, 1000);
    }

    function renderGate(data) {
        var host = document.getElementById('zoom-splash');
        if (!host) return;
        var card = host.querySelector('.zs-card');
        if (!card) return;

        var status = data.status || '';
        var reason = data.reason || '';
        var waitingForCoach = (data.role === 'student' && status === 'ready_to_start') || reason === 'waiting_for_coach';
        var mode = waitingForCoach ? 'coach' : 'count';
        if (mode === gateMode) return; // only rebuild on mode change (no flicker)
        gateMode = mode;

        var coachLine = coachName
            ? ('<div class="zs-gate-coach">' + escapeHtmlText(coachLabel) + ': ' + escapeHtmlText(coachName) + '</div>')
            : '';
        var body;
        if (mode === 'coach') {
            body = '<div class="zs-gate-spin"></div>'
                 + '<div class="zs-gate-msg">' + escapeHtmlText(waitCoachMsg) + '</div>'
                 + '<div class="zs-gate-sub">' + escapeHtmlText(waitCoachSub) + '</div>';
        } else {
            body = (scheduledStartMs ? '<div class="zs-gate-when" id="zsGateWhen"></div>' : '')
                 + '<div class="zs-gate-count" id="zsGateCount">—</div>'
                 + '<div class="zs-gate-msg">' + escapeHtmlText(data.message || notStartedMsg) + '</div>';
        }

        card.innerHTML =
            '<div class="zs-gate">'
              + '<div class="zs-gate-title">' + escapeHtmlText(@json($lessonTitle)) + '</div>'
              + coachLine + body
              + '<div class="zs-actions"><a href="' + escapeHtmlAttr(leaveUrl) + '" class="zs-back">' + escapeHtmlText(backLabel) + '</a></div>'
            + '</div>';

        if (mode === 'count') {
            var whenEl = document.getElementById('zsGateWhen');
            if (whenEl && scheduledStartMs) { try { whenEl.textContent = new Date(scheduledStartMs).toLocaleString(); } catch (e) {} }
            startCountdownTicker();
        } else if (countdownTimer) {
            clearInterval(countdownTimer); countdownTimer = null;
        }
    }

    function pollStatusAndGate() {
        fetch(statusUrl, {
            method: "GET",
            credentials: "same-origin",
            headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" }
        })
        .then(function (res) {
            if (res.status === 401) { clearGateTimers(); showError("{{ __('Please log in to join this meeting.') }}"); return null; }
            if (res.status === 403) { clearGateTimers(); showError(@json($accessDeniedMsg)); return null; }
            if (!res.ok) return null; // transient (e.g. throttle) — next poll retries
            return res.json();
        })
        .then(function (data) {
            if (!data || !data.ok) return;
            if (typeof data.server_now === 'number')      serverSkewMs     = data.server_now - Date.now();
            if (typeof data.scheduled_start === 'number')  scheduledStartMs = data.scheduled_start;
            else if (data.scheduled_start === null)         scheduledStartMs = null;

            if (data.can_join) { startJoinFlow(); return; }
            renderGate(data);
        })
        .catch(function () { /* network blip — next poll retries */ });
    }

    function startJoinFlow() {
        if (joinFlowStarted) return;
        joinFlowStarted = true;
        clearGateTimers();
        // Restore the original splash (spinner + #zs-status) so the join flow's
        // status updates and error handling operate on the expected DOM.
        var c = document.querySelector('#zoom-splash .zs-card');
        if (c && splashCardOriginal !== null) c.innerHTML = splashCardOriginal;
        statusEl = document.getElementById('zs-status');
        runSignatureJoin();
    }

    // Kick off: the first status response decides countdown / waiting / join.
    setStatus("{{ __('Checking class status…') }}");
    pollStatusAndGate();
    pollTimer = setInterval(pollStatusAndGate, POLL_MS);

    function runSignatureJoin() {
    var endpoint = "{{ route('zoom.sdk-signature', $lesson->id) }}";
    var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute("content");

    setStatus("{{ __('Authenticating…') }}");

    fetch(endpoint, {
        method: "POST",
        credentials: "same-origin",
        headers: {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrf,
            "X-Requested-With": "XMLHttpRequest"
        }
    })
    .then(function (res) {
        if (res.status === 425) {
            // Race: the gate said joinable but the signature endpoint re-closed
            // it (e.g. the coach dropped in that instant). Fall back to the
            // waiting gate and resume polling instead of showing an error.
            joinFlowStarted = false;
            gateMode = null;
            if (!pollTimer) pollTimer = setInterval(pollStatusAndGate, POLL_MS);
            return null;
        }
        if (!res.ok) {
            return res.text().then(function () {
                var msg;
                if (res.status === 401) msg = "{{ __('Please log in to join this meeting.') }}";
                else if (res.status === 403) msg = @json($accessDeniedMsg);
                else if (res.status === 404) msg = "{{ __('This live session no longer exists.') }}";
                else if (res.status === 503) msg = "{{ __('Zoom is not configured for this course. Please contact the instructor.') }}";
                else msg = "{{ __('Could not start the meeting (HTTP') }} " + res.status + ").";
                throw new Error(msg);
            });
        }
        return res.json();
    })
    .then(function (cfg) {
        if (!cfg) return; // 425 race handled above — gate resumed polling.
        console.log("[ZoomMtgEmbedded] cfg received", {
            meetingNumber: cfg.meetingNumber,
            sdkKey:        cfg.sdkKey,
            role:          cfg.role,
            userName:      cfg.userName,
            signatureLen:  cfg.signature ? cfg.signature.length : 0,
        });

        setStatus("{{ __('Loading meeting…') }}");

        var client = ZoomMtgEmbedded.createClient();
        window.__mbsZoomClient = client;
        var sdkRoot = document.getElementById("meetingSDKElement");

        var joined = false;
        var joinDeadline = setTimeout(function () {
            if (!joined) {
                console.warn("[ZoomMtgEmbedded] 20s deadline elapsed");
                showError(@json($timeoutErrorMsg));
            }
        }, 20000);

        // Last-ditch leave logging + Zoom session cleanup when the user
        // closes the tab without hitting our Leave button. Without the
        // leaveMeeting() call here, Zoom keeps the participant connection
        // alive until its heartbeat timer prunes it (~30-60s), and a
        // student who rejoins in that window appears in the host's
        // gallery TWICE (once zombie, once live) — the duplicate-tile
        // bug we hit 2026-05-11. leaveMeeting() can throw if the SDK
        // already tore down; swallow silently. sendBeacon is the only
        // API that ships the attendance log during pagehide reliably.
        window.addEventListener('pagehide', function () {
            if (joined && attendanceLogged) postAttendance('leave', true);
            try {
                if (window.__mbsZoomClient && typeof window.__mbsZoomClient.leaveMeeting === 'function') {
                    window.__mbsZoomClient.leaveMeeting();
                }
            } catch (e) { /* SDK already torn down — nothing to leave */ }
        });

        // 2026-05-11 Option-B redesign: stop trying to coerce Zoom
        // Component View's internal gallery layout from the client side.
        // Prior iterations (force-fill walk, synthetic-resize nudges,
        // changeRenderMode probing, user-added re-fills) either had no
        // effect, or worse, clipped Zoom's video tiles entirely. The
        // CSS-side fix is what holds now — Zoom renders whatever it
        // renders inside our bounded, centered card.

        function attachConnectionListener() {
            // Connection-change listener — registered AFTER init() resolves
            // because Component View's event registry only exists post-init.
            // Calling client.on() pre-init crashes inside the SDK with
            // "Cannot read properties of undefined (reading 'includes')".
            try {
                client.on('connection-change', function (payload) {
                    console.log("[ZoomMtgEmbedded] connection-change", payload);
                    if (payload && payload.state === 'Connected') {
                        joined = true;
                        joinedAt = Date.now();
                        clearTimeout(joinDeadline);
                        hideSplash();
                        if (!attendanceLogged) {
                            attendanceLogged = true;
                            postAttendance('join');
                        }
                    }
                    if (payload && (payload.state === 'Fail' || payload.state === 'Closed')) {
                        if (joined) {
                            postAttendance('leave');
                            showWrapUp();
                        } else {
                            clearTimeout(joinDeadline);
                            showError("{{ __('Zoom connection failed.') }} " + (payload.reason || ''));
                        }
                    }
                });
            } catch (e) {
                console.warn("[ZoomMtgEmbedded] connection-change listener could not attach", e);
            }
        }

        // 3.8.5 Component View won't deep-merge a partial `customize`
        // block with its internal defaults — any sub-object you OMIT
        // becomes `undefined` and the SDK crashes the next time it
        // tries to read a property off it. We pass empty stubs for
        // every top-level customize key so the SDK uses its own
        // defaults without tripping the merge bug. No video.viewSizes
        // — Zoom ignores it for layout decisions anyway (empirically
        // verified 2026-05-11 via DOM probe), and the CSS-side bounded
        // card now frames whatever Zoom chooses to render.
        // 2026-06-02 — secure-context pre-flight.
        // Zoom's Web SDK (and every WebRTC camera/mic/screen-share API) needs
        // `navigator.mediaDevices`, which browsers expose ONLY on a SECURE
        // context: HTTPS or localhost. On a plain http:// origin it is
        // `undefined`, and the SDK then throws the cryptic
        //   "Cannot use 'in' operator to search for 'getDisplayMedia' in undefined".
        // That is exactly why live classes work on localhost (offline) but fail
        // on the live http:// site. There is NO JS workaround — the browser
        // hard-blocks media on insecure origins; the domain must serve HTTPS.
        // Here we just replace the cryptic SDK crash with a clear, actionable
        // message so the user/admin knows the real cause.
        if (!window.isSecureContext || !navigator.mediaDevices) {
            clearTimeout(joinDeadline);
            showError(@json(__('Live classes require a secure (HTTPS) connection. This page is open over an insecure http:// link, so the browser blocks the camera, microphone and screen-sharing the meeting needs. Please open the site using https:// — your administrator must enable an SSL certificate on the domain.')));
            return;
        }

        client.init({
            zoomAppRoot: sdkRoot,
            language: 'en-US',
            customize: {
                video:       {},
                toolbar:     {},
                chat:        {},
                meeting:     {},
                meetingInfo: [],
                video_share: {},
                settings:    {},
            },
        }).then(function () {
            console.log("[ZoomMtgEmbedded] init.success");
            attachConnectionListener();
            setStatus("{{ __('Joining…') }}");

            // Forward whatever Zoom is enforcing to client.join() so its
            // pre-join handshake clears without prompting the user:
            //
            //  - `password` arrives only when Zoom's account policy auto-
            //    assigned a passcode to the meeting (locked-on for Basic
            //    plans). Travels server → XHR → memory; never rendered
            //    into HTML source. LMS-side enrollment + role checks in
            //    the signature endpoint remain the real access gate.
            //  - `zak` arrives only when the server fetched a Zoom Access
            //    Key for the host (host role + S2S creds + zak scope).
            //    Required by Zoom's 2026-03-02 OBF/ZAK rule for cross-
            //    account host joins; harmless to omit otherwise.
            //
            // Conditional attach (rather than passing `null`/`""`) avoids
            // the SDK rejecting a falsy field that it expected to be
            // either present-and-valid or absent.
            var joinPayload = {
                signature:     cfg.signature,
                sdkKey:        cfg.sdkKey,
                meetingNumber: cfg.meetingNumber,
                userName:      cfg.userName,
                userEmail:     cfg.userEmail,
            };
            if (cfg.password) joinPayload.password = cfg.password;
            if (cfg.zak)      joinPayload.zak      = cfg.zak;
            return client.join(joinPayload);
        }).then(function () {
            console.log("[ZoomMtgEmbedded] join.success");
            joined = true;
            clearTimeout(joinDeadline);
            hideSplash();
            try { sessionStorage.removeItem('mbs_zoom_slot_retry_' + lessonId); } catch (e) {}
        }).catch(function (err) {
            console.error("[ZoomMtgEmbedded] init/join error", err);
            clearTimeout(joinDeadline);
            var msg = err && err.reason ? err.reason : (err && err.message ? err.message : 'unknown error');
            // Stored passcode out of sync with Zoom — usually because the
            // meeting was edited / recreated externally and `course_live_classes.password`
            // wasn't refreshed. Recovery is `php artisan zoom:recreate-meetings`,
            // not user-typed input (passcode is never user-entered in this LMS).
            if (/passcode|password/i.test(msg)) {
                showError(@json(__("This meeting's passcode is out of sync with Zoom. Ask the instructor to recreate the live class — the recreate command refreshes the passcode automatically.")));
            } else if (isHost && /in progress|already has other/i.test(msg)) {
                // 2026-06-04 — the server ends the host's lingering meeting before
                // issuing the signature, but Zoom needs a few seconds to actually
                // free the host's single concurrent-meeting slot. Auto-retry ONCE
                // instead of making the coach click Retry; a sessionStorage flag
                // (per lesson) prevents an infinite reload loop.
                var rk = 'mbs_zoom_slot_retry_' + lessonId;
                if (!sessionStorage.getItem(rk)) {
                    sessionStorage.setItem(rk, '1');
                    showError(@json(__('Ending the previous meeting… reconnecting in a moment.')));
                    setTimeout(function () { window.location.reload(); }, 3500);
                } else {
                    sessionStorage.removeItem(rk);
                    showError("{{ __('Failed to start meeting:') }} " + msg);
                }
            } else {
                showError("{{ __('Failed to start meeting:') }} " + msg);
            }
        });
    })
    .catch(function (err) {
        showError(err && err.message ? err.message : "{{ __('Could not start the meeting.') }}");
    });
    } // end runSignatureJoin — invoked by startJoinFlow() once the gate clears
})();
</script>
</body>
</html>
