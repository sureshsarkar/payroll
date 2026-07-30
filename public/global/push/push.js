/**
 * Browser push subscription bootstrap for MBS.
 *
 * Loaded by the dashboard topbar partial. Behaviour:
 *   1. Check that Service Workers + Push API are supported
 *   2. Wait until the user clicks the bell to ask permission (less spammy than asking on page load)
 *   3. On grant: register the SW, subscribe with VAPID, POST the subscription
 *      JSON to /push/subscribe (CSRF-protected). The server saves it and pushes
 *      to it next time we send a webpush notification.
 *
 * Globals expected on window:
 *   window.MBS_VAPID_PUBLIC = "<base64url-encoded VAPID public key>"
 *   window.MBS_PUSH_ENABLED = true | false
 */
(function () {
    if (!window.MBS_PUSH_ENABLED) return;
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    const VAPID = window.MBS_VAPID_PUBLIC || '';
    if (!VAPID) return;

    function urlBase64ToUint8(b64) {
        const padding = '='.repeat((4 - (b64.length % 4)) % 4);
        const base64 = (b64 + padding).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(base64);
        const out = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
        return out;
    }

    async function ensureSubscribed() {
        const reg = await navigator.serviceWorker.register('/sw.js');
        const existing = await reg.pushManager.getSubscription();
        if (existing) return existing;

        const sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8(VAPID),
        });

        // Ship to the server.
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        await fetch('/push/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify(sub.toJSON()),
            credentials: 'same-origin',
        });

        return sub;
    }

    // Don't ask for notification permission on page load — wait for a user
    // gesture. Hook into the bell-icon hover/click (same trigger that loads the
    // notification dropdown).
    function attachTrigger() {
        const trigger = document.getElementById('mbs-bell-trigger');
        if (!trigger) return;
        let attempted = false;
        const handler = async () => {
            if (attempted) return;
            attempted = true;
            try {
                if (Notification.permission === 'granted') {
                    await ensureSubscribed();
                } else if (Notification.permission !== 'denied') {
                    const perm = await Notification.requestPermission();
                    if (perm === 'granted') await ensureSubscribed();
                }
            } catch (e) {
                console.warn('Push subscribe failed:', e);
            }
        };
        trigger.addEventListener('click', handler, { once: false });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachTrigger);
    } else {
        attachTrigger();
    }
})();
