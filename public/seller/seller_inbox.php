<?php
// public/seller/seller_inbox.php
declare(strict_types=1);

require_once __DIR__ . '/../../src/session.php';
require_once __DIR__ . '/../../src/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();


// canonical seller detection
$seller_id = 0;
$seller_name = 'Seller';

// Try helper if available (hr_get_viewer_id could return buyer/seller depending on implementation)
if (function_exists('hr_get_viewer_id') && function_exists('hr_is_logged_in')) {
    $who = hr_is_logged_in();
    // hr_is_logged_in may return array with roles; prefer seller if present
    if (!empty($_SESSION['seller']) && !empty($_SESSION['seller']['id'])) {
        $seller_id = (int)$_SESSION['seller']['id'];
        $seller_name = $_SESSION['seller']['name'] ?? $seller_name;
    } elseif (!empty($who) && !empty($who['role']) && $who['role'] === 'seller' && !empty($who['data']['id'])) {
        $seller_id = (int)$who['data']['id'];
        $seller_name = $who['data']['name'] ?? $seller_name;
    }
} else {
    // fallback to session keys used in your project
    if (!empty($_SESSION['seller']) && is_array($_SESSION['seller']) && !empty($_SESSION['seller']['id'])) {
        $seller_id = (int)$_SESSION['seller']['id'];
        $seller_name = $_SESSION['seller']['name'] ?? $seller_name;
    } elseif (!empty($_SESSION['seller_id'])) {
        $seller_id = (int)$_SESSION['seller_id'];
    }
}

// If no seller session, try guiding to seller login or role switch
if (!$seller_id) {
    // Prefer a seller-specific login; fall back to role switch page if exists
    $loginCandidate = '/HouseRader/public/user/login.php';
    $switchRole = '/HouseRader/public/user/switch_role.php';
    // redirect to seller login (you can change this path to seller/login.php if you have it)
    header('Location: ' . $loginCandidate);
    exit;
}

// ensure CSRF token for forms/XHR
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

$APP_ROOT = '/HouseRader';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Seller Inbox — HouseRadar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />

  <link rel="stylesheet" href="<?= $APP_ROOT ?>/public/assets/css/index.css" />
  <link rel="stylesheet" href="<?= $APP_ROOT ?>/public/assets/css/main_inbox.css" />

  <style>
    /* Small, non-invasive tweaks consistent with public/inbox.php */
    .messages .msg {
      font-size: 13px;
      line-height: 1.35;
      padding: 8px 10px;
      border-radius: 10px;
      max-width: 86%;
      box-sizing: border-box;
    }
    .messages .msg time {
      display:block;
      margin-top:6px;
      font-size:11px;
      opacity:0.75;
      color:var(--muted, #9fb1bf);
    }
    .composer input[type="text"] { font-size:14px; padding:10px 12px; }
    .composer input[type="file"] { margin-right:10px; }
    .conv-list .conv { padding:8px 10px; }
    .thumb-avatar { width:44px; height:44px; }
  </style>

  <script>
    // expose small runtime info for client scripts (seller context)
    window.__HR = {
      csrf: <?= json_encode($_SESSION['csrf_token']) ?>,
      viewer_id: <?= json_encode((int)$seller_id) ?>,
      viewer_name: <?= json_encode($seller_name) ?>
    };
    (function(){ try{ if(localStorage.getItem('hr_theme') === 'dark') document.documentElement.classList.add('dark'); }catch(e){} })();
  </script>
</head>
<body>
  <header class="hr-navbar" role="navigation" aria-label="Seller navigation">
    <div class="nav-left">
      <a class="brand" href="<?= $APP_ROOT ?>/public/seller/seller_index.php">🏠 HouseRadar (Seller)</a>
    </div>

    <div class="nav-center">
      <div class="muted">Seller Inbox</div>
    </div>

    <div class="nav-right">
      <button id="toggleTheme" class="icon-btn" title="Toggle theme">🌓</button>
      <a class="btn-link" href="<?= $APP_ROOT ?>/public/seller/seller_index.php">Dashboard</a>
      <a class="btn-link" href="<?= $APP_ROOT ?>/public/user/logout.php">Logout</a>
    </div>
  </header>

  <main class="inbox-wrap" id="inboxApp" role="application" aria-live="polite">
    <aside class="conversations-pane" id="conversationsPane" aria-label="Conversations">
      <div class="pane-header">
        <div class="title">Conversations</div>
        <div class="sub small">Property enquiries from buyers</div>
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
