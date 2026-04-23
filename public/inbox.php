<?php
// public/inbox.php
declare(strict_types=1);

require_once __DIR__ . '/../src/session.php';
require_once __DIR__ . '/../src/db.php';

// ensure session helpers are available
if (session_status() === PHP_SESSION_NONE) session_start();

$viewer_id = 0;
$viewer_name = 'You';

// try canonical helper
if (function_exists('hr_get_viewer_id')) {
    $viewer_id = hr_get_viewer_id();
    $who = hr_is_logged_in();
    if ($who && !empty($who['data']['name'])) $viewer_name = $who['data']['name'];
} else {
    if (!empty($_SESSION['user'])) {
        $viewer_id = (int)($_SESSION['user']['id'] ?? 0);
        $viewer_name = $_SESSION['user']['name'] ?? $viewer_name;
    } elseif (!empty($_SESSION['seller'])) {
        $viewer_id = (int)($_SESSION['seller']['id'] ?? 0);
        $viewer_name = $_SESSION['seller']['name'] ?? $viewer_name;
    } elseif (!empty($_SESSION['user_id'])) {
        $viewer_id = (int)$_SESSION['user_id'];
    } elseif (!empty($_SESSION['seller_id'])) {
        $viewer_id = (int)$_SESSION['seller_id'];
    }
}

if (!$viewer_id) {
    // redirect to login; use explicit project-aware path to avoid accidental root /user/ resolution
    header('Location: /HouseRader/public/user/login.php');
    exit;
}

// ensure a CSRF token for XHR/FormData use
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// base URL for asset paths (if you mount the app at a different path, change this)
$APP_ROOT = '/HouseRader';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Inbox — HouseRadar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />

  <link rel="stylesheet" href="<?= $APP_ROOT ?>/public/assets/css/index.css" />
  <link rel="stylesheet" href="<?= $APP_ROOT ?>/public/assets/css/main_inbox.css" />

  <style>
    /* Small UI polish to make message times and bubbles tighter (non-invasive) */
    /* These override only a few sizes to address the screenshot issue you showed */
    .messages .msg {
      font-size: 13px;               /* slightly smaller body text */
      line-height: 1.35;
      padding: 8px 10px;
      border-radius: 10px;
      max-width: 86%;
      box-sizing: border-box;
    }
    .messages .msg time,
    .messages .msg time, .messages .msg time {
      display: block;
      margin-top: 6px;
      font-size: 11px;               /* smaller timestamp */
      opacity: 0.75;
      color: var(--muted, #9fb1bf);
    }
    .messages .msg.me {
      font-weight: 600;
    }
    /* Composer tweaks */
    .composer input[type="text"] {
      font-size: 14px;
      padding: 10px 12px;
    }
    .composer input[type="file"] {
      margin-right: 10px;
    }
    /* Conversation list item compactness */
    .conv-list .conv {
      padding: 8px 10px;
    }
    .thumb-avatar {
      width: 44px;
      height: 44px;
    }

    /* Responsive: ensure chat pane gets priority on narrow screens */
    @media (max-width:880px) {
      .conversations-pane { width: 100%; }
      .chat-pane { width: 100%; margin-top: 12px; }
    }
  </style>

  <script>
    // expose small runtime info for client scripts
    window.__HR = {
      csrf: <?= json_encode($_SESSION['csrf_token']) ?>,
      viewer_id: <?= json_encode((int)$viewer_id) ?>,
      viewer_name: <?= json_encode($viewer_name) ?>
    };
    // apply saved theme early so there's no flash
    (function(){ try{ if(localStorage.getItem('hr_theme') === 'dark') document.documentElement.classList.add('dark'); }catch(e){} })();
  </script>
</head>
<body>
  <header class="hr-navbar" role="navigation" aria-label="Main navigation">
    <div class="nav-left">
      <a class="brand" href="<?= $APP_ROOT ?>/public/index.php">🏠HouseRadar</a>
    </div>

    <div class="nav-center">
      <div class="muted">Messages</div>
    </div>

    <div class="nav-right">
      <button id="toggleTheme" class="icon-btn" title="Toggle theme">🌓</button>
      <a class="btn-link" href="<?= $APP_ROOT ?>/public/user/edit_profile.php">Profile</a>
      <a class="btn-link" href="<?= $APP_ROOT ?>/public/user/logout.php">Logout</a>
    </div>
  </header>

  <main class="inbox-wrap" id="inboxApp" role="application" aria-live="polite">
    <aside class="conversations-pane" id="conversationsPane" aria-label="Conversations">
      <div class="pane-header">
        <div class="title">Chats</div>
        <div class="sub small">Tap a conversation to open</div>
      </div>
      <div id="convList" class="conv-list" role="list">
        <div class="loading">Loading…</div>
      </div>
    </aside>

    <section class="chat-pane" id="chatPane" aria-label="Active conversation">
      <div class="chat-header" id="chatHeader">
        <div class="chat-meta">
          <div id="activePropertyTitle">Select a conversation</div>
          <div id="activeOtherSmall" class="small muted"></div>
        </div>
        <div class="chat-tools">
          <button id="backToList" class="pill" title="Back" aria-hidden="true">←</button>
          <button id="refreshMessagesBtn" class="pill">Refresh</button>
        </div>
      </div>

      <div id="messagesPane" class="messages" tabindex="0" aria-live="polite">
        <div class="empty">No conversation selected.</div>
      </div>

      <div class="composer" id="composer">
        <input id="fileInput" type="file" accept="image/*,.pdf" />
        <input id="msgInput" type="text" placeholder="Write a message…" aria-label="Message text"/>
        <button id="sendBtn" class="btn-primary" type="button">Send</button>
      </div>
    </section>
  </main>

  <div id="chatImageModal" class="chat-image-modal">
  <div class="chat-image-backdrop"></div>
  <div class="chat-image-content">
    <img id="chatImageModalImg" src="" alt="Preview">
    <button id="chatImageClose" aria-label="Close preview">×</button>
  </div>
</div>

  <script src="<?= $APP_ROOT ?>/public/assets/js/main_inbox.js"></script>
</body>
</html>
