// public/assets/js/inbox_notifications.js
// Polls /api/notifications.php and shows desktop notifications + sound.
// Meant to be used with the provided api/notifications.php.
// Drop this file at: public/assets/js/inbox_notifications.js
// Include in pages (e.g. inbox.php): <script src="/assets/js/inbox_notifications.js" defer></script>

(function () {
  // CONFIG
  const POLL_MS = 3000;                     // poll interval
  const STORAGE_KEY = 'hr_notifications_last';
  const NOTIFY_TAG = 'hr-message';
  const MAX_BATCH = 200;

  // small helpers
  const nowIso = () => new Date().toISOString();
  const normalizeThumb = (path) => {
    if (!path) return '/assets/img/Nirvaana1.jpg';
    if (path.indexOf('/') !== -1) return path;
    return '/assets/img/' + path;
  };

  // short beep using WebAudio
  function playBeep() {
    try {
      const Ctx = window.AudioContext || window.webkitAudioContext;
      if (!Ctx) return;
      const ctx = new Ctx();
      const o = ctx.createOscillator();
      const g = ctx.createGain();
      o.type = 'sine';
      o.frequency.value = 1000;
      g.gain.value = 0.02;
      o.connect(g);
      g.connect(ctx.destination);
      o.start();
      o.stop(ctx.currentTime + 0.08);
      g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.08);
    } catch (e) {
      // silent fail
    }
  }

  // ask permission politely (non-blocking)
  async function ensurePermission() {
    if (!('Notification' in window)) return false;
    if (Notification.permission === 'granted') return true;
    if (Notification.permission === 'denied') return false;
    try {
      const p = await Notification.requestPermission();
      return p === 'granted';
    } catch (e) {
      return false;
    }
  }

  function showDesktopNotification(title, body, icon, clickUrl) {
    try {
      const n = new Notification(title, {
        body: body,
        icon: icon || '/assets/img/favicon.ico',
        tag: NOTIFY_TAG,
        renotify: false
      });
      if (clickUrl) {
        n.onclick = () => {
          try { window.focus(); } catch (e) {}
          location.href = clickUrl;
        };
      }
      setTimeout(() => n.close(), 8000);
    } catch (e) {
      // ignore
    }
  }

  // stored ISO timestamp string
  let last = localStorage.getItem(STORAGE_KEY);
  if (!last) {
    // initialize to a second ago so we don't immediately pull too many historic messages
    last = new Date(Date.now() - 1000).toISOString();
    localStorage.setItem(STORAGE_KEY, last);
  }

  let permissionGranted = (typeof Notification !== 'undefined' && Notification.permission === 'granted');
  ensurePermission().then(g => { permissionGranted = g; });

  // single poll
  async function pollOnce() {
    try {
      const url = '/api/notifications.php?since=' + encodeURIComponent(last);
      const res = await fetch(url, { credentials: 'include', cache: 'no-store' });
      if (!res.ok) return;
      const j = await res.json();
      if (!j.ok) {
        // if server responds with auth error, stop polling
        if (j.error && /auth/i.test(String(j.error))) {
          return;
        }
        // otherwise ignore
        if (j.server_time) {
          last = j.server_time;
          localStorage.setItem(STORAGE_KEY, last);
        }
        return;
      }

      const msgs = Array.isArray(j.messages) ? j.messages : [];
      if (msgs.length > 0) {
        // server_time is authoritative for advancing watermark
        const serverTime = j.server_time || new Date().toISOString();
        localStorage.setItem(STORAGE_KEY, serverTime);
        last = serverTime;

        // handle each message
        for (let m of msgs.slice(0, MAX_BATCH)) {
          // m: id, conversation_id, sender_type, sender_id, body, attachments, created_at, property_id, property_title, property_thumbnail
          const title = m.property_title || 'New message';
          const snippet = (m.body && m.body.length > 140) ? (m.body.slice(0, 140) + '…') : (m.body || 'Attachment');
          const thumb = normalizeThumb(m.property_thumbnail);
          const convUrl = '/public/inbox.php#conv-' + (m.conversation_id || '');

          // show desktop notif only if permission and tab not focused (optional)
          if (permissionGranted && (document.hidden || !document.hasFocus())) {
            showDesktopNotification(title, snippet, thumb, convUrl);
            playBeep();
          } else if (!permissionGranted) {
            // still play sound so user hears
            playBeep();
          }

          // in-page hook: if inbox implements window.handleIncomingMessage, call it
          if (typeof window.handleIncomingMessage === 'function') {
            try { window.handleIncomingMessage(m); } catch (e) { /* ignore */ }
          }
        }
      } else {
        // no messages -> sync watermark if provided
        if (j.server_time) {
          last = j.server_time;
          localStorage.setItem(STORAGE_KEY, last);
        }
      }
    } catch (e) {
      // network or parse error — silently continue
      console.warn('hr-notify poll error', e);
    }
  }

  // start polling
  let timer = null;
  function startPolling() {
    if (timer) clearInterval(timer);
    timer = setInterval(pollOnce, POLL_MS);
    // immediate run
    pollOnce();
  }

  // expose control
  window.hrNotifications = {
    start: startPolling,
    pollOnce
  };

  // start when DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', startPolling);
  } else {
    startPolling();
  }
})();
