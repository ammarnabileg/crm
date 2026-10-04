/* HaHireAI — platform UX layer. Progressive enhancement only: wrapped so any
   failure never breaks the app. Command palette, keyboard shortcuts, nav
   progress bar, toasts, mobile sidebar. Survives pjax swaps via delegation. */
(function () {
  'use strict';
  try {
    if (window.__hhux) { return; }
    window.__hhux = true;

    function el(tag, cls) { var d = document.createElement(tag); if (cls) { d.className = cls; } return d; }
    function esc(s) { return (s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

    var css = `
.hh-bar{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,#6366f1,#8b5cf6);z-index:9999;opacity:0;transition:width .2s ease,opacity .3s;border-radius:0 3px 3px 0}
.hh-pal-overlay{position:fixed;inset:0;z-index:9998;display:none;align-items:flex-start;justify-content:center;background:rgba(15,23,42,.45);backdrop-filter:blur(2px);padding-top:12vh}
.hh-pal-overlay.on{display:flex}
.hh-pal{width:min(92vw,640px);background:#fff;border-radius:16px;box-shadow:0 24px 60px rgba(2,6,23,.3);overflow:hidden;animation:hh-pop .12s ease}
@keyframes hh-pop{from{transform:translateY(-8px) scale(.985);opacity:.5}to{transform:none;opacity:1}}
.hh-pal-input{width:100%;border:0;border-bottom:1px solid #eef2f7;padding:16px 18px;font-size:15px;outline:none;color:#0f172a}
.hh-pal-list{max-height:50vh;overflow:auto;margin:0;padding:6px;list-style:none}
.hh-pal-item{display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-radius:10px;cursor:pointer}
.hh-pal-item.on{background:#eef2ff}
.hh-pal-l{font-size:14px;color:#1e293b}
.hh-pal-k{font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em}
.hh-pal-empty{padding:22px 16px;text-align:center;color:#94a3b8;font-size:13px}
.hh-pal-foot{display:flex;gap:14px;padding:9px 16px;border-top:1px solid #f1f5f9;font-size:11px;color:#94a3b8}
.hh-toasts{position:fixed;bottom:18px;right:18px;z-index:9999;display:flex;flex-direction:column;gap:8px}
.hh-toast{background:#0f172a;color:#fff;padding:10px 14px;border-radius:10px;font-size:13px;box-shadow:0 10px 28px rgba(2,6,23,.25);transform:translateY(12px);opacity:0;transition:.3s}
.hh-toast.on{transform:none;opacity:1}
.hh-toast.success{background:#059669}.hh-toast.error{background:#e11d48}
.hh-fade{animation:hh-in .18s ease}
@keyframes hh-in{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}
:focus-visible{outline:2px solid #6366f1;outline-offset:2px}
a[data-pjax]{transition:background-color .15s ease,color .15s ease}
button{transition:transform .06s ease}button:active{transform:translateY(1px)}
.hh-fab{position:fixed;bottom:18px;left:18px;z-index:9997;display:none}
.hh-fab button{height:48px;width:48px;border-radius:14px;background:#4f46e5;color:#fff;font-size:18px;box-shadow:0 10px 28px rgba(79,70,229,.45)}
@media (max-width:767px){.hh-fab{display:block}body.hh-nav-open #app-shell aside{display:flex!important;position:fixed;inset:0 auto 0 0;z-index:9996;box-shadow:0 0 40px rgba(2,6,23,.25)}}
`;
    var style = el('style'); style.id = 'hh-ux-style'; style.textContent = css; document.head.appendChild(style);

    // ---- progress bar (driven by pjax swaps) ----
    var bar = el('div', 'hh-bar'); document.body.appendChild(bar);
    var prog = (function () {
      var t, w;
      function tick() { t = setTimeout(function () { w = Math.min(92, w + (92 - w) * 0.14 + 1); bar.style.width = w + '%'; tick(); }, 220); }
      return {
        start: function () { clearTimeout(t); w = 8; bar.style.opacity = '1'; bar.style.width = '8%'; tick(); },
        done: function () { clearTimeout(t); bar.style.width = '100%'; setTimeout(function () { bar.style.opacity = '0'; bar.style.width = '0%'; }, 250); }
      };
    })();
    function fadeMain() { var m = document.querySelector('#app-shell main'); if (m) { m.classList.remove('hh-fade'); void m.offsetWidth; m.classList.add('hh-fade'); } }

    var shell = document.getElementById('app-shell');
    if (shell && shell.parentNode && window.MutationObserver) {
      new MutationObserver(function (muts) {
        for (var i = 0; i < muts.length; i++) { if (muts[i].addedNodes && muts[i].addedNodes.length) { prog.done(); fadeMain(); buildIndex(); break; } }
      }).observe(shell.parentNode, { childList: true });
    }
    document.addEventListener('click', function (e) { var a = e.target.closest && e.target.closest('a[data-pjax]'); if (a && e.button === 0 && !e.metaKey && !e.ctrlKey && !e.shiftKey && !e.altKey) { prog.start(); } });
    document.addEventListener('submit', function (e) { if (e.target.closest && e.target.closest('form[data-pjax]')) { prog.start(); } });

    // ---- command palette ----
    var overlay = el('div', 'hh-pal-overlay'); overlay.setAttribute('role', 'dialog'); overlay.setAttribute('aria-modal', 'true');
    overlay.innerHTML = '<div class="hh-pal"><input class="hh-pal-input" type="text" placeholder="Search pages and actions…" aria-label="Command palette"><ul class="hh-pal-list" role="listbox"></ul><div class="hh-pal-foot"><span>↑↓ navigate</span><span>↵ open</span><span>esc close</span></div></div>';
    document.body.appendChild(overlay);
    var input = overlay.querySelector('.hh-pal-input'), list = overlay.querySelector('.hh-pal-list');
    var items = [], filtered = [], sel = 0;

    function buildIndex() {
      items = [];
      var seen = {};
      document.querySelectorAll('#app-shell aside nav a, #app-shell a[data-pjax]').forEach(function (a) {
        var label = (a.textContent || '').trim().replace(/\s+/g, ' ');
        var href = a.getAttribute('href');
        if (label && href && href.charAt(0) === '/' && !seen[href]) { seen[href] = 1; items.push({ label: label, href: href, kind: 'Page' }); }
      });
      [['Notifications', '/notifications'], ['Edit profile', '/account/profile'], ['New workspace', '/workspaces/create'], ['Billing', '/billing']].forEach(function (p) {
        if (!seen[p[1]]) { seen[p[1]] = 1; items.push({ label: p[0], href: p[1], kind: 'Action' }); }
      });
    }
    buildIndex();

    function render() {
      list.innerHTML = '';
      if (filtered.length === 0) { list.innerHTML = '<li class="hh-pal-empty">No matches</li>'; return; }
      filtered.forEach(function (it, i) {
        var li = el('li', 'hh-pal-item' + (i === sel ? ' on' : '')); li.setAttribute('role', 'option');
        li.innerHTML = '<span class="hh-pal-l">' + esc(it.label) + '</span><span class="hh-pal-k">' + esc(it.kind) + '</span>';
        li.addEventListener('mouseenter', function () { sel = i; paint(); });
        li.addEventListener('click', function () { go(it); });
        list.appendChild(li);
      });
    }
    function paint() { var lis = list.querySelectorAll('.hh-pal-item'); for (var i = 0; i < lis.length; i++) { lis[i].classList.toggle('on', i === sel); } var on = list.querySelector('.hh-pal-item.on'); if (on && on.scrollIntoView) { on.scrollIntoView({ block: 'nearest' }); } }
    function filter(q) { q = (q || '').toLowerCase().trim(); filtered = !q ? items.slice(0, 60) : items.filter(function (it) { return it.label.toLowerCase().indexOf(q) >= 0; }).slice(0, 60); sel = 0; render(); }
    function open() { buildIndex(); overlay.classList.add('on'); input.value = ''; filter(''); setTimeout(function () { input.focus(); }, 10); }
    function close() { overlay.classList.remove('on'); }
    function go(it) { close(); var a = document.querySelector('a[href="' + it.href + '"][data-pjax]'); if (a) { a.click(); } else { window.location.href = it.href; } }

    input.addEventListener('input', function () { filter(input.value); });
    overlay.addEventListener('click', function (e) { if (e.target === overlay) { close(); } });

    // ---- keyboard shortcuts ----
    var gMode = false, gT;
    document.addEventListener('keydown', function (e) {
      var k = e.key;
      if ((e.metaKey || e.ctrlKey) && (k === 'k' || k === 'K')) { e.preventDefault(); overlay.classList.contains('on') ? close() : open(); return; }
      if (overlay.classList.contains('on')) {
        if (k === 'Escape') { close(); }
        else if (k === 'ArrowDown') { e.preventDefault(); sel = Math.min(filtered.length - 1, sel + 1); paint(); }
        else if (k === 'ArrowUp') { e.preventDefault(); sel = Math.max(0, sel - 1); paint(); }
        else if (k === 'Enter') { e.preventDefault(); if (filtered[sel]) { go(filtered[sel]); } }
        return;
      }
      var tag = (e.target.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable) { return; }
      if (k === '/' || k === '?') { e.preventDefault(); open(); return; }
      if (k === 'g') { gMode = true; clearTimeout(gT); gT = setTimeout(function () { gMode = false; }, 800); return; }
      if (gMode) {
        gMode = false;
        var map = { d: '/dashboard', j: '/jobs', c: '/candidates', p: '/pipeline', s: '/settings', b: '/billing', i: '/interviews', r: '/reports' };
        var href = map[(k || '').toLowerCase()];
        if (href) { var a = document.querySelector('a[href="' + href + '"][data-pjax]'); if (a) { a.click(); } else { window.location.href = href; } }
      }
    });

    // ---- mobile sidebar drawer ----
    var fab = el('div', 'hh-fab'); fab.innerHTML = '<button aria-label="Open menu">☰</button>'; document.body.appendChild(fab);
    fab.querySelector('button').addEventListener('click', function (ev) { ev.stopPropagation(); document.body.classList.toggle('hh-nav-open'); });
    document.addEventListener('click', function (e) {
      if (document.body.classList.contains('hh-nav-open')) {
        var aside = document.querySelector('#app-shell aside');
        if (aside && !aside.contains(e.target) && !fab.contains(e.target)) { document.body.classList.remove('hh-nav-open'); }
      }
    });

    // ---- toast API ----
    var toasts = el('div', 'hh-toasts'); document.body.appendChild(toasts);
    window.hhToast = function (msg, type) {
      var t = el('div', 'hh-toast ' + (type || '')); t.textContent = String(msg || ''); toasts.appendChild(t);
      setTimeout(function () { t.classList.add('on'); }, 10);
      setTimeout(function () { t.classList.remove('on'); setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 320); }, 3600);
    };

    // ---- <details data-popover> popovers: close on outside click / Escape ----
    document.addEventListener('click', function (e) {
      var open = document.querySelectorAll('details[data-popover][open]');
      for (var i = 0; i < open.length; i++) {
        if (!open[i].contains(e.target)) { open[i].removeAttribute('open'); }
      }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        var open = document.querySelectorAll('details[data-popover][open]');
        for (var i = 0; i < open.length; i++) { open[i].removeAttribute('open'); }
      }
    });

    // ---- auto-dismiss server flash banners (status strips) so they don't linger ----
    function dismissFlash() {
      var nodes = document.querySelectorAll('main .rounded-lg.bg-emerald-50, main .rounded-lg.bg-rose-50, main .rounded-lg.bg-amber-50');
      for (var i = 0; i < nodes.length; i++) {
        (function (n) {
          if (n.__hhFlash) { return; } n.__hhFlash = true;
          n.style.transition = 'opacity .4s ease, transform .4s ease';
          setTimeout(function () { n.style.opacity = '0'; n.style.transform = 'translateY(-6px)'; }, 4200);
          setTimeout(function () { if (n.parentNode) { n.style.display = 'none'; } }, 4700);
        })(nodes[i]);
      }
    }
    dismissFlash();
    if (shell && shell.parentNode && window.MutationObserver) {
      new MutationObserver(function () { dismissFlash(); }).observe(shell.parentNode, { childList: true, subtree: true });
    }

    fadeMain();
  } catch (err) { /* never break the app */ }
})();
