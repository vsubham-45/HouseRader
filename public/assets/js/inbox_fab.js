// Floating Inbox Notification Dot + Header Dot

(function () {
  const FAB_DOT = document.getElementById("hrInboxDot");
  const NAV_DOT = document.getElementById("navInboxDot");
  const POLL_MS = 4000;

  function getApiBase() {
    const parts = window.location.pathname.split('/');
    const rootSegment = parts[1] || '';
    const maybeRoot = rootSegment ? ('/' + rootSegment) : '';
    return window.location.origin + maybeRoot + '/api';
  }

  async function checkUnread() {
    try {
      const url = getApiBase() + "/conversations.php?action=list&limit=200";

      const res = await fetch(url, {
        credentials: "include",
        cache: "no-store"
      });

      if (!res.ok) return;

      const data = await res.json();
      if (!data.ok || !Array.isArray(data.conversations)) return;

      let unread = 0;
      for (const c of data.conversations) {
        unread += parseInt(c.unread_for_viewer || 0);
      }

      // FAB dot
      if (FAB_DOT) {
        if (unread > 0) {
          FAB_DOT.hidden = false;
        } else {
          FAB_DOT.hidden = true;
        }
      }

      // HEADER dot
      if (NAV_DOT) {
        NAV_DOT.style.display = unread > 0 ? "block" : "none";
      }

    } catch (err) {
      console.warn("Inbox unread check failed:", err);
    }
  }

  function start() {
    checkUnread();
    setInterval(checkUnread, POLL_MS);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start);
  } else {
    start();
  }
})();
