// main_inbox.js - inbox UI + API wiring
// Assumes window.__HR (csrf, viewer_id) is present

(function(){
  // --------- API base helper (adapts when app mounted at /HouseRader) ----------
  const HR_API_BASE = (function(){
    const parts = window.location.pathname.split('/');
    const maybeRoot = parts.length > 1 ? ('/' + parts[1]) : '';
    return maybeRoot + '/api';
  })();

  // App root used to build local asset URLs when avatars are stored as relative paths
  const APP_ROOT = (function(){
    const parts = window.location.pathname.split('/');
    return parts.length > 1 ? ('/' + parts[1]) : '';
  })();

  const API_LIST = HR_API_BASE + '/conversations.php?action=list';
  const API_GET = HR_API_BASE + '/conversations.php?action=get';
  const API_SEND = HR_API_BASE + '/messages_send.php';
  const API_UPLOAD = HR_API_BASE + '/upload_attachment.php';

  // --------- DOM references (may be missing in some layouts; guard usage) ----------
  const convListEl = document.getElementById('convList');
  const messagesPane = document.getElementById('messagesPane');
  const msgInput = document.getElementById('msgInput');
  const sendBtn = document.getElementById('sendBtn');
  const fileInput = document.getElementById('fileInput');
  const refreshBtn = document.getElementById('refreshMessagesBtn');
  const activePropertyTitle = document.getElementById('activePropertyTitle');
  const activeOtherSmall = document.getElementById('activeOtherSmall');
  const backToListBtn = document.getElementById('backToList');

  const viewerId = window.__HR && window.__HR.viewer_id ? window.__HR.viewer_id : 0;
  const csrf = window.__HR && window.__HR.csrf ? window.__HR.csrf : '';

  let conversations = [];
  let activeConv = null;
  let messagesCache = {};

  // --------- robust fetch wrapper (checks status and content-type) ----------
  async function hrApiFetch(path, opts = {}) {
    let url = path;
    if (!path.startsWith('http') && !path.startsWith('/')) {
      url = HR_API_BASE + '/' + path;
    } else if (!path.startsWith('http') && path.startsWith('/')) {
      if (path.indexOf('/api/') === 0) {
        url = HR_API_BASE + path.slice(4);
      } else {
        url = path;
      }
    }

    const finalOpts = Object.assign({ credentials: 'include' }, opts);

    const resp = await fetch(url, finalOpts);
    if (!resp.ok) {
      let txt = '';
      try { txt = await resp.text(); } catch(e){ /* ignore */ }
      throw new Error(`API request failed: ${resp.status} ${resp.statusText}` + (txt ? ` — ${txt.slice(0,200)}` : ''));
    }
    const ct = resp.headers.get('content-type') || '';
    if (ct.indexOf('application/json') !== -1) {
      return resp.json();
    }
    return resp.text();
  }

  function jsonFetch(path, opts = {}) { return hrApiFetch(path, opts); }

  // --------- small helpers ----------
  function el(q, ctx=document) { return ctx ? ctx.querySelector(q) : null; }
  function elAll(q, ctx=document) { return Array.from((ctx || document).querySelectorAll(q)); }

  function fmtTime(iso) {
    if (!iso) return '';
    const s = String(iso).replace(' ', 'T');
    const d = new Date(s);
    if (Number.isNaN(d.getTime())) return iso;
    const now = new Date();
    const sameDay = d.toDateString() === now.toDateString();
    if (sameDay) {
      return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    if (d.getFullYear() === now.getFullYear()) {
      return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }
    return d.toLocaleDateString();
  }

  function escapeHtml(s){
    if (!s) return '';
    return String(s)
      .replace(/&/g,'&amp;')
      .replace(/</g,'&lt;')
      .replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;')
      .replace(/'/g,'&#039;')
      .replace(/\n/g,'<br>');
  }

  // constructs a usable avatar URL from a value returned by server.
  // If value looks absolute (http/https) or root-relative (/path) we use as-is.
  // Otherwise we assume it's a relative path stored in DB (e.g. "assets/img/foo.jpg")
  // and prefix APP_ROOT + '/public/' so it resolves correctly from the browser.
  function resolveAvatarUrl(val) {
    if (!val) return null;
    val = String(val).trim();
    if (!val) return null;
    if (/^https?:\/\//i.test(val)) return val;
    if (val.startsWith('/')) return val;
    // assume relative path: prefix with app root + '/public/'
    // e.g. APP_ROOT = '/HouseRader' -> '/HouseRader/public/assets/img/avatar.png'
    return APP_ROOT + '/public/' + val.replace(/^\/+/, '');
  }

  // render avatar element (img or initial letter)
  function renderAvatar(container, avatarUrl, displayName) {
    container.innerHTML = '';
    container.style.width = '44px';
    container.style.height = '44px';
    container.style.flex = '0 0 44px';
    container.style.borderRadius = '8px';
    container.style.display = 'inline-flex';
    container.style.alignItems = 'center';
    container.style.justifyContent = 'center';
    container.style.overflow = 'hidden';
    container.style.background = 'rgba(0,0,0,0.06)';
    container.style.color = 'var(--muted, #9fb1bf)';
    container.style.fontWeight = '700';
    container.style.fontSize = '14px';
    container.style.marginRight = '10px';

    if (avatarUrl) {
      const img = document.createElement('img');
      img.src = resolveAvatarUrl(avatarUrl);
      img.alt = displayName ? (displayName + ' avatar') : 'avatar';
      img.style.width = '100%';
      img.style.height = '100%';
      img.style.objectFit = 'cover';
      img.style.borderRadius = '8px';
      img.onerror = function(){ // fallback to initials if image fails
        container.innerHTML = '';
        const letter = document.createElement('div');
        letter.textContent = (displayName && displayName[0]) ? displayName[0].toUpperCase() : 'U';
        container.appendChild(letter);
      };
      container.appendChild(img);
    } else {
      const letter = document.createElement('div');
      letter.textContent = (displayName && displayName[0]) ? displayName[0].toUpperCase() : 'U';
      container.appendChild(letter);
    }
  }

  // --------- render conversation list (with red unread badge) ----------
  function renderConversations() {
    if (!convListEl) return;
    convListEl.innerHTML = '';
    if (!conversations || !conversations.length) {
      convListEl.innerHTML = '<div class="small">No conversations yet.</div>';
      return;
    }

    conversations.forEach(conv => {
      const div = document.createElement('div');
      div.className = 'conv';
      div.id = 'conv-' + conv.id;
      if (activeConv && activeConv.id === conv.id) div.classList.add('active');

      // avatar (letter or image)
      const avatar = document.createElement('div');
      avatar.className = 'thumb-avatar';
      avatar.setAttribute('aria-hidden','true');

      // choose which display name + avatar to show as "other party"
      // If viewer is seller -> show buyer info; if viewer is buyer -> show seller info
      let otherDisplayName = '';
      let otherAvatar = null;
      if (viewerId && conv.seller_id && Number(conv.seller_id) === Number(viewerId)) {
        // viewer is seller: other = buyer
        otherDisplayName = conv.buyer_display_name || conv.buyer_name || '';
        otherAvatar = conv.buyer_avatar || null;
      } else {
        // viewer is buyer or fallback: other = seller
        otherDisplayName = conv.seller_display_name || conv.seller_name || '';
        otherAvatar = conv.seller_avatar || null;
      }

      renderAvatar(avatar, otherAvatar, otherDisplayName);

      const meta = document.createElement('div');
      meta.className = 'meta';
      meta.style.flex = '1 1 auto';
      meta.style.minWidth = '0';

      const h = document.createElement('h4');
      const title = conv.property_title || ('Property #' + (conv.property_id || ''));
      h.textContent = title;
      h.style.margin = '0';
      h.style.fontSize = '13px';
      h.style.fontWeight = '700';
      h.style.overflow = 'hidden';
      h.style.textOverflow = 'ellipsis';
      h.style.whiteSpace = 'nowrap';

      // Show buyer_display_name or seller_display_name when present (other party)
      const subName = document.createElement('div');
      if (otherDisplayName) {
        subName.textContent = otherDisplayName;
        subName.style.fontSize = '12px';
        subName.style.color = 'var(--muted, #6b7b84)';
        subName.style.marginTop = '2px';
        subName.style.overflow = 'hidden';
        subName.style.textOverflow = 'ellipsis';
        subName.style.whiteSpace = 'nowrap';
      } else {
        // If no display name, attempt to show a fallback: buyer/seller email or phone if available (from API raw)
        const raw = conv.raw || {};
        let fallback = '';
        if (raw.buyer_email && raw.buyer_email.trim()) fallback = raw.buyer_email;
        else if (raw.buyer_phone && raw.buyer_phone.trim()) fallback = raw.buyer_phone;
        else if (raw.seller_email && raw.seller_email.trim()) fallback = raw.seller_email;
        else if (raw.seller_phone && raw.seller_phone.trim()) fallback = raw.seller_phone;

        if (fallback) {
          subName.textContent = fallback;
          subName.style.fontSize = '12px';
          subName.style.color = 'var(--muted, #6b7b84)';
          subName.style.marginTop = '2px';
          subName.style.overflow = 'hidden';
          subName.style.textOverflow = 'ellipsis';
          subName.style.whiteSpace = 'nowrap';
        } else {
          subName.textContent = '';
          subName.style.display = 'block';
          subName.style.height = '0';
          subName.style.marginTop = '0';
        }
      }

      let previewText = '';
      if (conv.last_message && String(conv.last_message).trim() !== '') {
        previewText = String(conv.last_message);
      } else if (conv.raw && conv.raw.last_message && String(conv.raw.last_message).trim() !== '') {
        previewText = String(conv.raw.last_message);
      } else if (conv.messages_count && Number(conv.messages_count) > 0) {
        previewText = String(conv.messages_count) + (conv.messages_count == 1 ? ' message' : ' messages');
      } else {
        previewText = 'No messages yet';
      }

      const p = document.createElement('p');
      p.textContent = previewText.length > 70 ? previewText.substring(0,70) + '…' : previewText;
      p.style.margin = '6px 0 0 0';
      p.style.fontSize = '13px';
      p.style.color = 'var(--muted, #9fb1bf)';
      p.style.overflow = 'hidden';
      p.style.textOverflow = 'ellipsis';
      p.style.whiteSpace = 'nowrap';

      // optional small "n unread" text under title for clarity (visually subtle)
      const smallUnread = document.createElement('div');
      smallUnread.style.fontSize = '12px';
      smallUnread.style.color = 'var(--muted, #9fb1bf)';
      smallUnread.style.marginTop = '4px';
      smallUnread.style.display = 'none'; // shown only if we decide to show text + badge
      smallUnread.className = 'small-unread';

      meta.appendChild(h);
      meta.appendChild(subName);
      meta.appendChild(p);
      meta.appendChild(smallUnread);

      const right = document.createElement('div');
      right.style.display = 'flex';
      right.style.flexDirection = 'column';
      right.style.alignItems = 'flex-end';
      right.style.gap = '6px';
      right.style.flex = '0 0 auto';
      right.style.marginLeft = '10px';

      const timeEl = document.createElement('div');
      timeEl.className = 'conv-time';
      timeEl.style.fontSize = '12px';
      timeEl.style.color = 'var(--muted, #9fb1bf)';
      timeEl.textContent = fmtTime(conv.last_message_at || conv.updated_at || conv.created_at || '');

      // compute unread_for_viewer robustly:
      // prefer explicit unread_for_buyer / unread_for_seller if returned by API;
      // otherwise fall back to prepopulated conv.unread_for_viewer if present.
      let unreadCount = 0;
      if (typeof conv.unread_for_viewer !== 'undefined' && conv.unread_for_viewer !== null) {
        unreadCount = Number(conv.unread_for_viewer) || 0;
      } else {
        // try explicit fields
        if (viewerId && conv.buyer_id && Number(conv.buyer_id) === Number(viewerId)) {
          unreadCount = Number(conv.unread_for_buyer || 0);
        } else if (viewerId && conv.seller_id && Number(conv.seller_id) === Number(viewerId)) {
          unreadCount = Number(conv.unread_for_seller || 0);
        } else {
          // last-resort: try an 'unread' or 'unread_count' field
          unreadCount = Number(conv.unread || conv.unread_count || 0);
        }
      }

      if (unreadCount && unreadCount > 0) {
        // red circular badge
        const badge = document.createElement('div');
        badge.className = 'badge unread';
        badge.textContent = (unreadCount > 99) ? '99+' : String(unreadCount);
        badge.setAttribute('aria-label', unreadCount + ' unread messages');
        badge.style.background = '#e0245e'; // red
        badge.style.color = '#fff';
        badge.style.padding = '4px 8px';
        badge.style.borderRadius = '999px';
        badge.style.fontSize = '12px';
        badge.style.fontWeight = '700';
        badge.style.minWidth = '28px';
        badge.style.textAlign = 'center';
        right.appendChild(badge);

        // also optionally set the small 'n unread' text below preview for screen readers / clarity on narrow layouts
        smallUnread.textContent = (unreadCount === 1) ? '1 unread' : (unreadCount + ' unread');
        smallUnread.style.display = 'block';
      } else {
        const spacer = document.createElement('div');
        spacer.style.height = '0';
        right.appendChild(spacer);
        smallUnread.style.display = 'none';
      }

      right.appendChild(timeEl);

      div.style.display = 'flex';
      div.style.alignItems = 'center';
      div.style.gap = '10px';
      div.style.padding = '10px';
      div.style.cursor = 'pointer';
      div.style.borderBottom = '1px solid rgba(0,0,0,0.04)';
      div.style.background = 'transparent';

      div.appendChild(avatar);
      div.appendChild(meta);
      div.appendChild(right);

      div.addEventListener('click', () => openConversation(conv));
      div.addEventListener('keypress', (e) => { if (e.key === 'Enter') openConversation(conv); });

      convListEl.appendChild(div);
    });
  }

  // --------- load conversations ----------
  async function loadConversations() {
    if (!convListEl) return;
    convListEl.innerHTML = '<div class="loading small">Loading…</div>';
    try {
      const res = await jsonFetch(API_LIST);
      if (typeof res === 'string') {
        convListEl.innerHTML = '<div class="small">Failed to load conversations</div>';
        console.error('Non-JSON response from conversations API:', res);
        return;
      }
      if (!res || !(res.success === true || res.ok === true)) {
        convListEl.innerHTML = '<div class="small">Failed to load conversations</div>';
        console.error('API error response', res);
        return;
      }
      conversations = res.conversations || [];

      // normalize conversations: prefer server-provided unread fields if available
      conversations = conversations.map(c => {
        // if API provided explicit unread_for_buyer/unread_for_seller we leave them;
        // create an explicit unread_for_viewer property for easier client code
        if (typeof c.unread_for_viewer === 'undefined') {
          if (viewerId && c.buyer_id && Number(c.buyer_id) === Number(viewerId)) {
            c.unread_for_viewer = Number(c.unread_for_buyer || 0);
          } else if (viewerId && c.seller_id && Number(c.seller_id) === Number(viewerId)) {
            c.unread_for_viewer = Number(c.unread_for_seller || 0);
          } else {
            c.unread_for_viewer = Number(c.unread || c.unread_count || 0);
          }
        }
        return c;
      });
      renderConversations();
    } catch (err) {
      console.error('loadConversations error', err);
      if (convListEl) convListEl.innerHTML = '<div class="small">Error loading conversations</div>';
    }
  }

  // --------- open conversation & load messages ----------
  async function openConversation(conv) {
    activeConv = conv;
    elAll('.conv').forEach(n => n.classList.remove('active'));
    const elActive = document.getElementById('conv-' + conv.id);
    if (elActive) elActive.classList.add('active');

    if (activePropertyTitle) {
      // Primary: show other party display name (with fallback) followed by property title
      const prop = conv.property_title || ('Property #' + conv.property_id);
      let other = '';
      if (viewerId && conv.seller_id && Number(conv.seller_id) === Number(viewerId)) {
        other = conv.buyer_display_name || conv.buyer_name || '';
      } else {
        other = conv.seller_display_name || conv.seller_name || '';
      }
      activePropertyTitle.textContent = other ? (other + ' — ' + prop) : prop;
    }
    if (activeOtherSmall) activeOtherSmall.textContent = 'Conversation ID: ' + conv.id;

    if (window.matchMedia('(max-width:880px)').matches) {
      if (backToListBtn) backToListBtn.removeAttribute('aria-hidden');
      const pane = document.querySelector('.conversations-pane');
      if (pane) pane.style.display = 'none';
    }

    if (messagesPane) messagesPane.innerHTML = '<div class="small">Loading messages…</div>';
    await loadMessages(conv.id, true);

    // refresh conversations to update unread counts after opening (server should mark read on GET or via separate API)
    await loadConversations();

    // Ensure message input is focused (prevents emoji-as-URL bug)
if (msgInput) {
  setTimeout(() => msgInput.focus(), 50);
}

  }

  // --------- load messages for a conversation ----------
  async function loadMessages(conversationId, replace = false) {
    if (!messagesPane) return;
    try {
      const url = API_GET + '&conversation_id=' + encodeURIComponent(conversationId);
      const res = await jsonFetch(url);
      if (typeof res === 'string') {
        messagesPane.innerHTML = '<div class="small">Failed to load messages</div>';
        console.error('Non-JSON response for messages', res);
        return;
      }
      if (!res || !(res.success === true || res.ok === true)) {
        messagesPane.innerHTML = '<div class="small">Failed to load messages</div>';
        console.error('Messages API error', res);
        return;
      }
      messagesCache[conversationId] = res.messages || [];
      // merge conversation meta if we loaded a fresh conv (useful when opening directly)
      if (res.conversation) {
        // update activeConv fields with any buyer_name/seller_name/property_title returned
        if (!activeConv) activeConv = res.conversation;
        else Object.assign(activeConv, res.conversation);
      }
      renderMessages(conversationId);
    } catch (err) {
      console.error('loadMessages error', err);
      if (messagesPane) messagesPane.innerHTML = '<div class="small">Error loading messages</div>';
    }
  }

  // --------- render message bubbles ----------
  function renderMessages(conversationId) {
    if (!messagesPane) return;
    const arr = messagesCache[conversationId] || [];
    messagesPane.innerHTML = '';
    if (!arr.length) {
      messagesPane.innerHTML = '<div class="empty">No messages yet. Say hello!</div>';
      return;
    }
    arr.forEach(m => {
      const bubble = document.createElement('div');
      const amISender = ((m.sender_type === 'seller' && m.sender_id == viewerId) || (m.sender_type === 'buyer' && m.sender_id == viewerId));
      bubble.className = 'msg ' + (amISender ? 'me' : 'them');
      bubble.style.maxWidth = '86%';
      bubble.style.margin = amISender ? '8px 0 8px auto' : '8px 0 8px 0';
      bubble.style.padding = '8px 10px';
      bubble.style.borderRadius = '10px';
      bubble.style.fontSize = '13px';
      bubble.style.lineHeight = '1.3';
      bubble.style.background = amISender ? 'linear-gradient(90deg, rgba(30,161,255,0.10), rgba(30,161,255,0.03))' : 'rgba(0,0,0,0.03)';
      bubble.style.color = 'var(--text, #e6f3fb)';
      bubble.style.boxShadow = '0 4px 12px rgba(0,0,0,0.06)';

      const body = document.createElement('div');
      body.innerHTML = escapeHtml(m.body || '');
      bubble.appendChild(body);

      const t = document.createElement('time');
      t.textContent = fmtTime(m.created_at || '');
      t.style.display = 'block';
      t.style.fontSize = '11px';
      t.style.color = 'var(--muted, #9fb1bf)';
      t.style.marginTop = '6px';
      bubble.appendChild(t);

      if (m.attachments) {
  try {
    const at = (typeof m.attachments === 'string')
      ? JSON.parse(m.attachments)
      : m.attachments;

    if (Array.isArray(at) && at.length) {
      at.forEach(a => {

        const url = a.url || a;
        const name = a.name || 'attachment';
        const mime = a.mime || '';

        const wrap = document.createElement('div');
        wrap.className = 'attachment-wrap';

        // Image preview
        if (mime.startsWith('image/') || /\.(jpg|jpeg|png|webp)$/i.test(url)) {

          const img = document.createElement('img');
          img.src = url;
          img.alt = name;
          img.className = 'attachment-image';

          img.addEventListener('click', () => {
  const modal = document.getElementById('chatImageModal');
  const modalImg = document.getElementById('chatImageModalImg');

  if (modal && modalImg) {
    modalImg.src = url;
    modal.classList.add('active');
  }
});

          wrap.appendChild(img);
        }

        // PDF preview
        else if (mime === 'application/pdf' || /\.pdf$/i.test(url)) {

          const pdfCard = document.createElement('a');
          pdfCard.href = url;
          pdfCard.target = '_blank';
          pdfCard.rel = 'noopener noreferrer';
          pdfCard.className = 'attachment-pdf';
          pdfCard.textContent = '📄 ' + name;

          wrap.appendChild(pdfCard);
        }

        // Fallback link
        else {

          const link = document.createElement('a');
          link.href = url;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.className = 'attachment-link';
          link.textContent = name;

          wrap.appendChild(link);
        }

        bubble.appendChild(wrap);
      });
    }
  } catch (e) {
    console.warn('Invalid attachments JSON', e);
  }
}

      messagesPane.appendChild(bubble);
    });

    messagesPane.scrollTop = messagesPane.scrollHeight;
  }

  // --------- file upload helper (single file) ----------
  async function uploadFile(file){
    const fd = new FormData();
    fd.append('file', file);
    const res = await fetch(API_UPLOAD, { method: 'POST', credentials: 'include', body: fd });
    const text = await res.text();
    let json;
    try { json = JSON.parse(text); } catch(e) { throw new Error('Upload failed: non-json response'); }
    if (!(json.ok === true || json.success === true)) {
      throw new Error(json.error || 'Upload failed');
    }
    return json.file || json;
  }

  // --------- send message handler ----------
  if (sendBtn) {
    sendBtn.addEventListener('click', async () => {
      if (!activeConv) { alert('Select a conversation first'); return; }
      const body = (msgInput && msgInput.value ? msgInput.value.trim() : '');
      const hasFile = (fileInput && fileInput.files && fileInput.files.length > 0);
      if (!body && !hasFile) { alert('Type a message or attach a file'); return; }

      sendBtn.disabled = true;
      try {
        let attachments = null;
        if (hasFile) {
          const f = fileInput.files[0];
          const uploaded = await uploadFile(f);
          attachments = [uploaded];
        }

        const fd = new FormData();
        fd.append('conversation_id', String(activeConv.id));
        if (body) fd.append('body', body);
        if (attachments) fd.append('attachments', JSON.stringify(attachments.map(a => ({ url: a.url, name: a.name || a.filename || '', mime: a.mime || '', size: a.size || 0 }))));
        if (csrf) fd.append('csrf_token', csrf);

        const resp = await fetch(API_SEND, { method: 'POST', credentials: 'include', body: fd });
        const txt = await resp.text();
        let json;
        try { json = JSON.parse(txt); } catch(e) { json = null; }
        if (!json || !(json.success === true || json.ok === true)) {
          const msg = (json && (json.error || json.message)) ? (json.error || json.message) : 'Send failed';
          alert(msg);
        } else {
          if (msgInput) msgInput.value = '';
          if (fileInput) fileInput.value = '';
          await loadMessages(activeConv.id, true);
          await loadConversations();
        }
      } catch (err) {
        console.error('Send message error', err);
        alert(err && err.message ? err.message : 'Send error');
      }
      sendBtn.disabled = false;
    });
  }

  // --------- Enter key on message input triggers send ----------
  if (msgInput) {
    msgInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey) {
        e.preventDefault();
        if (sendBtn && !sendBtn.disabled) {
          sendBtn.click();
        }
      }
    });
  }

  // --------- refresh button ----------
  if (refreshBtn) {
    refreshBtn.addEventListener('click', async () => {
      if (activeConv) await loadMessages(activeConv.id, true);
      await loadConversations();
    });
  }

  // --------- back to list (mobile) ----------
  if (backToListBtn) {
    backToListBtn.addEventListener('click', () => {
      const pane = document.querySelector('.conversations-pane');
      if (pane) pane.style.display = '';
      backToListBtn.setAttribute('aria-hidden', 'true');
    });
  }

  // --------- polling (lightweight updates) ----------
  setInterval(async () => {
    try {
      await loadConversations();
      if (activeConv) await loadMessages(activeConv.id, true);
    } catch (e) { /* ignore polling errors */ }
  }, 6000);

  // theme toggle integration
  const toggleTheme = document.getElementById('toggleTheme');
  if (toggleTheme){
    function updateToggle(){
      const isDark = document.documentElement.classList.contains('dark');
      toggleTheme.textContent = isDark ? '🌙' : '🌓';
      toggleTheme.setAttribute('aria-pressed', isDark ? 'true' : 'false');
    }
    toggleTheme.addEventListener('click', () => {
      document.documentElement.classList.toggle('dark');
      if (document.documentElement.classList.contains('dark')) localStorage.setItem('hr_theme','dark');
      else localStorage.removeItem('hr_theme');
      updateToggle();
    });
    updateToggle();
  }

  // --------- Image Modal Logic ----------
const chatImageModal = document.getElementById('chatImageModal');
const chatImageClose = document.getElementById('chatImageClose');

if (chatImageModal) {

  chatImageModal.addEventListener('click', (e) => {
    if (
      e.target.classList.contains('chat-image-backdrop') ||
      e.target.id === 'chatImageClose'
    ) {
      chatImageModal.classList.remove('active');
    }
  });

  // ESC key close
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      chatImageModal.classList.remove('active');
    }
  });
}

  // initial load
  loadConversations();

})();


// Prevent emoji being treated as URL navigation on mobile
document.addEventListener('click', (e) => {
  if (e.target && e.target.tagName === 'A') {
    const href = e.target.getAttribute('href');
    if (href && /%F0%9F/i.test(href)) {
      e.preventDefault();
    }
  }
});
