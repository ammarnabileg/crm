/*
 * HalaOps — progressive-enhancement layer (docs/30 Design System).
 *
 * No framework, no build. Every page works without this file; it only adds
 * behaviour to the Design System components (modal, drawer, tabs, switch, toast,
 * dark mode) on top of fully-functional, server-rendered HTML. All wiring is
 * data-attribute driven and delegated, so dynamically-injected markup works too.
 */
(function () {
  'use strict';

  var doc = document;
  var root = doc.documentElement;

  // --- helpers -------------------------------------------------------------
  function closest(el, selector) {
    return el && el.closest ? el.closest(selector) : null;
  }
  var FOCUSABLE = 'a[href],button:not([disabled]),textarea:not([disabled]),input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])';
  var lastFocused = null;

  function lockScroll(on) {
    root.classList.toggle('overflow-hidden', on);
  }
  function anyOverlayOpen() {
    return doc.querySelector('[data-modal]:not(.hidden), [data-drawer]:not(.hidden)') !== null;
  }

  // --- Overlays: modal + drawer (shared) -----------------------------------
  function openOverlay(el) {
    if (!el) return;
    lastFocused = doc.activeElement;
    el.classList.remove('hidden');
    el.setAttribute('aria-hidden', 'false');
    lockScroll(true);
    var panel = el.querySelector('[role="dialog"]');
    var focusTarget = (panel && panel.querySelector(FOCUSABLE)) || panel;
    if (focusTarget && focusTarget.focus) focusTarget.focus();
  }
  function closeOverlay(el) {
    if (!el) return;
    el.classList.add('hidden');
    el.setAttribute('aria-hidden', 'true');
    if (!anyOverlayOpen()) lockScroll(false);
    if (lastFocused && lastFocused.focus) lastFocused.focus();
  }
  function topOverlay() {
    var open = doc.querySelectorAll('[data-modal]:not(.hidden), [data-drawer]:not(.hidden)');
    return open.length ? open[open.length - 1] : null;
  }

  // --- Tabs ----------------------------------------------------------------
  function activateTab(tab) {
    var group = closest(tab, '[data-tabs]');
    if (!group) return;
    var tabs = group.querySelectorAll('[role="tab"]');
    tabs.forEach(function (t) {
      var selected = t === tab;
      t.setAttribute('aria-selected', selected ? 'true' : 'false');
      t.setAttribute('tabindex', selected ? '0' : '-1');
    });
    var targetId = tab.getAttribute('data-tab-target');
    group.querySelectorAll('[data-tab-panel]').forEach(function (panel) {
      panel.hidden = panel.id !== targetId;
    });
  }

  // --- Switch (ARIA) -------------------------------------------------------
  function toggleSwitch(btn) {
    if (btn.hasAttribute('disabled')) return;
    var on = btn.getAttribute('aria-checked') !== 'true';
    btn.setAttribute('aria-checked', on ? 'true' : 'false');
    // Sync the sibling hidden input so the new state submits with the form.
    var wrap = closest(btn, 'label') || btn.parentNode;
    var input = wrap && wrap.querySelector('input[type="hidden"]');
    if (input) {
      input.value = on ? (btn.getAttribute('data-value-on') || '1') : (btn.getAttribute('data-value-off') || '0');
    }
  }

  // --- Toasts --------------------------------------------------------------
  function ensureToastRegion() {
    var region = doc.getElementById('toast-region');
    if (!region) {
      region = doc.createElement('div');
      region.id = 'toast-region';
      region.className = 'pointer-events-none fixed bottom-4 end-4 z-toast flex flex-col gap-3';
      region.setAttribute('aria-live', 'polite');
      region.setAttribute('aria-atomic', 'false');
      doc.body.appendChild(region);
    }
    return region;
  }
  var TOAST_ICONS = {
    success: 'text-success-600 dark:text-success-400',
    error: 'text-red-600 dark:text-red-400',
    warning: 'text-warning-600 dark:text-warning-400',
    info: 'text-info-600 dark:text-info-400'
  };
  function dismissToast(el) {
    if (!el) return;
    el.style.transition = 'opacity 150ms ease-out';
    el.style.opacity = '0';
    setTimeout(function () { el.remove(); }, 160);
  }
  // Public API: HalaToast('Saved', { variant: 'success', title: '', timeout: 4000 }).
  window.HalaToast = function (message, opts) {
    opts = opts || {};
    var variant = opts.variant || 'info';
    var region = ensureToastRegion();
    var el = doc.createElement('div');
    el.className = 'pointer-events-auto flex w-80 max-w-full items-start gap-3 rounded-xl bg-white p-4 shadow-lg ring-1 ring-slate-200 animate-slide-up dark:bg-slate-900 dark:ring-slate-800';
    el.setAttribute('role', 'status');
    el.setAttribute('data-toast', '');
    var accent = TOAST_ICONS[variant] || TOAST_ICONS.info;
    var titleHtml = opts.title ? '<p class="text-sm font-semibold text-slate-800 dark:text-slate-100"></p>' : '';
    el.innerHTML =
      '<span class="mt-0.5 shrink-0 ' + accent + '"><svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v4a1 1 0 102 0V7zm-1 7a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg></span>' +
      '<div class="min-w-0 flex-1">' + titleHtml + '<p class="text-sm text-slate-500 dark:text-slate-400"></p></div>' +
      '<button type="button" data-toast-dismiss aria-label="Dismiss" class="-m-1 shrink-0 rounded-md p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>';
    // Set text via textContent (never innerHTML) so messages can't inject markup.
    var ps = el.querySelectorAll('p');
    if (opts.title) { ps[0].textContent = opts.title; ps[1].textContent = message || ''; }
    else { ps[0].textContent = message || ''; }
    region.appendChild(el);
    var timeout = opts.timeout == null ? 4000 : opts.timeout;
    if (timeout > 0) setTimeout(function () { dismissToast(el); }, timeout);
    return el;
  };

  // --- Dark mode -----------------------------------------------------------
  function setTheme(theme) {
    var dark = theme === 'dark';
    root.classList.toggle('dark', dark);
    try { localStorage.setItem('halaops-theme', dark ? 'dark' : 'light'); } catch (e) {}
    doc.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
      b.setAttribute('aria-pressed', dark ? 'true' : 'false');
    });
  }

  // --- Delegated click -----------------------------------------------------
  doc.addEventListener('click', function (event) {
    var t = event.target;

    var open = closest(t, '[data-modal-open]');
    if (open) { event.preventDefault(); openOverlay(doc.getElementById(open.getAttribute('data-modal-open'))); return; }
    var dopen = closest(t, '[data-drawer-open]');
    if (dopen) { event.preventDefault(); openOverlay(doc.getElementById(dopen.getAttribute('data-drawer-open'))); return; }

    if (closest(t, '[data-modal-close],[data-modal-overlay]')) { closeOverlay(closest(t, '[data-modal]')); return; }
    if (closest(t, '[data-drawer-close],[data-drawer-overlay]')) { closeOverlay(closest(t, '[data-drawer]')); return; }

    var tab = closest(t, '[data-tab-target]');
    if (tab) { event.preventDefault(); activateTab(tab); return; }

    var sw = closest(t, '[data-switch]');
    if (sw) { event.preventDefault(); toggleSwitch(sw); return; }

    if (closest(t, '[data-theme-toggle]')) { event.preventDefault(); setTheme(root.classList.contains('dark') ? 'light' : 'dark'); return; }

    // Declarative toast trigger (no inline JS): data-toast-demo + data-toast-*.
    var toastDemo = closest(t, '[data-toast-demo]');
    if (toastDemo) {
      event.preventDefault();
      window.HalaToast(toastDemo.getAttribute('data-toast-message') || 'Notification', {
        variant: toastDemo.getAttribute('data-toast-variant') || 'info',
        title: toastDemo.getAttribute('data-toast-title') || ''
      });
      return;
    }

    var chip = closest(t, '[data-chip-remove]');
    if (chip) { var c = closest(chip, '.chip'); if (c) c.remove(); return; }

    var alertX = closest(t, '[data-alert-dismiss]');
    if (alertX) { var a = closest(alertX, '[role="alert"]'); if (a) a.remove(); return; }

    var toastX = closest(t, '[data-toast-dismiss]');
    if (toastX) { dismissToast(closest(toastX, '[data-toast]')); return; }

    // Close any open <details> disclosure (dropdowns/menus) on outside click.
    doc.querySelectorAll('details[open]').forEach(function (d) {
      if (!d.contains(t)) d.removeAttribute('open');
    });
  });

  // --- Keyboard ------------------------------------------------------------
  doc.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      var overlay = topOverlay();
      if (overlay) { closeOverlay(overlay); return; }
      doc.querySelectorAll('details[open]').forEach(function (d) { d.removeAttribute('open'); });
      return;
    }
    // Space/Enter toggles a focused switch.
    if ((event.key === ' ' || event.key === 'Enter') && doc.activeElement && doc.activeElement.matches('[data-switch]')) {
      event.preventDefault();
      toggleSwitch(doc.activeElement);
      return;
    }
    // Arrow-key navigation within a tablist (WAI-ARIA pattern).
    if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
      var current = doc.activeElement;
      if (current && current.matches('[role="tab"]')) {
        var group = closest(current, '[data-tabs]');
        if (!group) return;
        var tabs = Array.prototype.slice.call(group.querySelectorAll('[role="tab"]'));
        var idx = tabs.indexOf(current);
        var rtl = root.getAttribute('dir') === 'rtl';
        var forward = (event.key === 'ArrowRight') !== rtl;
        var next = tabs[(idx + (forward ? 1 : -1) + tabs.length) % tabs.length];
        if (next) { next.focus(); activateTab(next); }
      }
    }
  });

  // --- Confirm destructive actions (any element with data-confirm) ---------
  doc.addEventListener('submit', function (event) {
    var trigger = event.submitter;
    var message = trigger && trigger.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });

  // Reflect the persisted theme on the toggle once the DOM is ready (the
  // no-flash <head> script already applied the class before first paint).
  doc.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
    b.setAttribute('aria-pressed', root.classList.contains('dark') ? 'true' : 'false');
  });
})();
