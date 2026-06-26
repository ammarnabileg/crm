/* HalaOps — small progressive-enhancement helpers. No framework, no build. */
(function () {
  'use strict';

  // Close any open <details> dropdown when clicking outside of it.
  document.addEventListener('click', function (event) {
    document.querySelectorAll('details[open]').forEach(function (details) {
      if (!details.contains(event.target)) {
        details.removeAttribute('open');
      }
    });
  });

  // Close dropdowns on Escape.
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      document.querySelectorAll('details[open]').forEach(function (d) { d.removeAttribute('open'); });
    }
  });

  // Confirm destructive actions (any element with data-confirm).
  document.addEventListener('submit', function (event) {
    var trigger = event.submitter;
    var message = trigger && trigger.getAttribute('data-confirm');
    if (message && !window.confirm(message)) {
      event.preventDefault();
    }
  });
})();
