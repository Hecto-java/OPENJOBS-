(function(){
  const body = document.body;
  const saved = localStorage.getItem('theme');
  if (saved === 'dark') body.classList.add('dark-mode');

  window.toggleTheme = function(){
    body.classList.toggle('dark-mode');
    localStorage.setItem('theme', body.classList.contains('dark-mode') ? 'dark' : 'light');
  };

  window.showToast = function(message, type='success'){
    const el = document.getElementById('appToast');
    if (!el || typeof bootstrap === 'undefined') return;
    const bodyEl = el.querySelector('.toast-body');
    bodyEl.textContent = message;
    bodyEl.className = 'toast-body text-white bg-' + type + ' rounded-4';
    bootstrap.Toast.getOrCreateInstance(el).show();
  };

  function animateCounter(el){
    const target = parseInt(el.dataset.target || '0', 10);
    const suffix = el.dataset.suffix || '';
    const prefix = el.dataset.prefix || '';
    const duration = 1200;
    const startTs = performance.now();
    const step = (ts) => {
      const progress = Math.min((ts - startTs) / duration, 1);
      const value = Math.floor(progress * target);
      el.textContent = prefix + value.toLocaleString('es-MX') + suffix;
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }

  function setupReveal(){
    const items = document.querySelectorAll('.reveal');
    if (!('IntersectionObserver' in window) || !items.length) {
      items.forEach(el => el.classList.add('visible'));
      return;
    }
    const io = new IntersectionObserver((entries)=>{
      entries.forEach(entry=>{
        if(entry.isIntersecting){
          entry.target.classList.add('visible');
          if(entry.target.classList.contains('counter-value') && !entry.target.dataset.done){
            entry.target.dataset.done = '1';
            animateCounter(entry.target);
          }
        }
      });
    }, {threshold:.18});
    items.forEach(el=>io.observe(el));
  }

  let seenNotificationIds = new Set();

  function renderNotifications(items){
    const list = document.getElementById('notificationList');
    if (!list) return;
    if (!items || !items.length) {
      list.innerHTML = '<div class="notification-empty">No hay notificaciones nuevas.</div>';
      return;
    }
    list.innerHTML = items.map(item => {
      const link = item.link ? item.link : 'javascript:void(0)';
      const readClass = parseInt(item.is_read, 10) ? 'is-read' : '';
      return `
        <a class="notification-item ${readClass}" href="${link}" ${item.link ? '' : 'aria-disabled="true"'}>
          <div class="notification-title">${escapeHtml(item.title || 'Notificación')}</div>
          <div class="notification-body">${escapeHtml(item.body || '')}</div>
          <div class="notification-time">${escapeHtml(item.created_at || '')}</div>
        </a>
      `;
    }).join('');
  }

  function updateNotificationBadge(count){
    const badge = document.getElementById('notificationBadge');
    if (!badge) return;
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.classList.remove('d-none');
    } else {
      badge.classList.add('d-none');
    }
  }

  function maybeToastNotification(items){
    if (!items || !items.length) return;
    const firstUnread = items.find(item => !parseInt(item.is_read || 0, 10));
    if (!firstUnread) return;
    const id = String(firstUnread.id || '');
    if (!id || seenNotificationIds.has(id)) return;
    seenNotificationIds.add(id);
    if (document.hidden) {
      showToast((firstUnread.title || 'Notificación') + ': ' + (firstUnread.body || ''), 'primary');
    }
  }

  async function pollNotifications(){
    if (!body.dataset.notificationPoll) return;
    try {
      const res = await fetch('api/notifications.php', {credentials: 'same-origin'});
      if (!res.ok) return;
      const data = await res.json();
      if (!data.ok) return;
      const items = data.items || [];
      maybeToastNotification(items);
      updateNotificationBadge(data.unread_count || 0);
      renderNotifications(items);
    } catch (e) {}
  }

  function appendChatMessages(messages){
    const win = document.getElementById('chatWindow');
    if (!win || !messages || !messages.length) return;
    const me = Number(window.OPENJOBS_ME || 0);
    let lastId = Number(win.dataset.chatLastId || 0);

    messages.forEach(m => {
      const id = Number(m.id || 0);
      if (id <= lastId) return;
      const row = document.createElement('div');
      row.className = 'message-row ' + (Number(m.sender_id) === me ? 'sent' : 'received');
      row.dataset.messageId = String(id);
      const bubble = document.createElement('div');
      bubble.className = 'bubble';
      const meta = document.createElement('div');
      meta.className = 'small opacity-75 mb-1';
      meta.textContent = `${m.sender_name || 'Usuario'} · ${m.sender_role || ''}`.trim();
      const body = document.createElement('div');
      body.textContent = m.body || '';
      bubble.appendChild(meta);
      bubble.appendChild(body);
      row.appendChild(bubble);
      win.appendChild(row);
      lastId = id;
    });

    win.dataset.chatLastId = String(lastId);
    win.scrollTop = win.scrollHeight;
  }

  async function pollChatMessages(){
    const win = document.getElementById('chatWindow');
    if (!win) return;
    const selected = Number(win.dataset.chatSelected || 0);
    const lastId = Number(win.dataset.chatLastId || 0);
    if (!selected) return;
    try {
      const res = await fetch(`api/chat_messages.php?to=${selected}&after_id=${lastId}`, {credentials: 'same-origin'});
      if (!res.ok) return;
      const data = await res.json();
      if (!data.ok || !data.messages) return;
      appendChatMessages(data.messages);
    } catch (e) {}
  }

  function getNotificationHost(){
    let host = document.getElementById('notificationFloatingHost');
    if (!host) {
      host = document.createElement('div');
      host.id = 'notificationFloatingHost';
      host.style.position = 'fixed';
      host.style.inset = '0';
      host.style.pointerEvents = 'none';
      host.style.zIndex = '30000';
      document.body.appendChild(host);
    }
    return host;
  }

  function resolveDropdownRoot(target){
    if (!target) return null;
    if (target.classList && target.classList.contains('dropdown')) return target;
    if (target.closest) return target.closest('.dropdown');
    return null;
  }

  function positionNotificationMenu(dropdownRoot){
    dropdownRoot = resolveDropdownRoot(dropdownRoot);
    if (!dropdownRoot) return;
    const toggle = dropdownRoot.querySelector('#notificationsDropdown, [data-bs-toggle="dropdown"]');
    const menu = dropdownRoot.querySelector('.notification-menu') || document.querySelector('.notification-menu.notification-floating');
    if (!toggle || !menu) return;

    if (!menu._originalParent) {
      menu._originalParent = dropdownRoot;
      menu._originalNextSibling = menu.nextSibling;
    }

    const host = getNotificationHost();
    if (menu.parentNode !== host) host.appendChild(menu);

    menu.classList.add('notification-floating');
    menu.classList.add('show');
    menu.style.setProperty('pointer-events', 'auto', 'important');
    menu.style.setProperty('display', 'block', 'important');

    const rect = toggle.getBoundingClientRect();
    const mobile = window.innerWidth <= 575;
    const width = mobile ? Math.max(280, window.innerWidth - 24) : 360;
    let left = rect.right - width;
    if (left < 12) left = 12;
    if (left + width > window.innerWidth - 12) left = Math.max(12, window.innerWidth - width - 12);
    const top = Math.max(12, rect.bottom + 12);

    menu.style.setProperty('position', 'fixed', 'important');
    menu.style.setProperty('top', top + 'px', 'important');
    menu.style.setProperty('left', left + 'px', 'important');
    menu.style.setProperty('right', 'auto', 'important');
    menu.style.setProperty('bottom', 'auto', 'important');
    menu.style.setProperty('width', width + 'px', 'important');
    menu.style.setProperty('max-width', mobile ? 'calc(100vw - 24px)' : '360px', 'important');
    menu.style.setProperty('z-index', '30001', 'important');
  }

  function restoreNotificationMenu(dropdownRoot){
    dropdownRoot = resolveDropdownRoot(dropdownRoot);
    const menu = dropdownRoot ? (dropdownRoot.querySelector('.notification-menu') || document.querySelector('.notification-menu.notification-floating')) : document.querySelector('.notification-menu.notification-floating');
    if (!menu) return;

    if (menu._originalParent) {
      if (menu._originalNextSibling && menu._originalNextSibling.parentNode === menu._originalParent) {
        menu._originalParent.insertBefore(menu, menu._originalNextSibling);
      } else {
        menu._originalParent.appendChild(menu);
      }
    }

    menu.classList.remove('notification-floating');
    menu.classList.remove('show');
    menu.style.setProperty('display', 'none', 'important');
    ['pointer-events','position','top','left','right','bottom','width','max-width','z-index'].forEach((prop)=>menu.style.removeProperty(prop));
  }

  function refreshOpenNotificationMenu(){
    const openMenu = document.querySelector('.notification-menu.show');
    if (!openMenu) return;
    const openDropdown = openMenu._originalParent || resolveDropdownRoot(openMenu);
    if (openDropdown) positionNotificationMenu(openDropdown);
  }

  async function markNotificationsRead(){
    try {
      const res = await fetch('api/mark_notifications_read.php', {method: 'POST', credentials: 'same-origin'});
      if (!res.ok) return;
      updateNotificationBadge(0);
      pollNotifications();
    } catch (e) {}
  }

  function escapeHtml(str){
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  window.addEventListener('load', () => {
    const loader = document.getElementById('loader');
    if (loader) loader.classList.add('hide');
    document.querySelectorAll('.chat-window, .chat-messages').forEach((chat) => {
      chat.scrollTop = chat.scrollHeight;
    });
    setupReveal();
    pollNotifications();
    if (document.getElementById('chatWindow')) {
      pollChatMessages();
      window.setInterval(pollChatMessages, 5000);
    }
    if (body.dataset.notificationPoll) {
      window.setInterval(pollNotifications, 10000);
    }
  });

  document.addEventListener('click', (e) => {
    const toastTarget = e.target.closest('[data-toast]');
    if (toastTarget) showToast(toastTarget.getAttribute('data-toast'));

    if (e.target.closest('#markNotificationsRead')) {
      e.preventDefault();
      markNotificationsRead();
    }
  });

  document.addEventListener('shown.bs.dropdown', (e) => {
    const dropdownRoot = resolveDropdownRoot(e.target);
    const toggle = dropdownRoot ? dropdownRoot.querySelector('#notificationsDropdown') : null;
    if (toggle) {
      pollNotifications();
      positionNotificationMenu(dropdownRoot);
    }
  });

  document.addEventListener('hide.bs.dropdown', (e) => {
    const dropdownRoot = resolveDropdownRoot(e.target);
    const menu = dropdownRoot ? dropdownRoot.querySelector('.notification-menu') : null;
    if (menu) {
      restoreNotificationMenu(dropdownRoot);
    }
  });

  window.addEventListener('resize', refreshOpenNotificationMenu);
  window.addEventListener('scroll', refreshOpenNotificationMenu, true);

  document.addEventListener('click', (e) => {
    const floatingMenu = document.querySelector('.notification-menu.notification-floating.show');
    if (!floatingMenu) return;
    const root = floatingMenu._originalParent || null;
    const toggle = root ? root.querySelector('#notificationsDropdown') : null;
    if (floatingMenu.contains(e.target) || (toggle && toggle.contains(e.target))) return;
    restoreNotificationMenu(root);
  });

  document.addEventListener('click', (e) => {
    const actionable = e.target.closest('a, button, .btn, .nav-link, .dropdown-item');
    if (!actionable) return;
    actionable.style.pointerEvents = 'auto';
  }, true);
})();