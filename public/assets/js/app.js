/* NexusDesk — progressive enhancement (vanilla JS, no dependencies). */
(function () {
  'use strict';

  // --- Theme toggle (persisted) ---------------------------------------------
  var root = document.documentElement;
  var stored = null;
  try { stored = localStorage.getItem('nexusdesk-theme'); } catch (e) {}
  if (stored === 'light' || stored === 'dark') {
    root.setAttribute('data-theme', stored);
  }
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-theme-toggle]');
    if (toggle) {
      var cur = root.getAttribute('data-theme');
      if (!cur) {
        cur = window.matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'light';
      }
      var next = cur === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('nexusdesk-theme', next); } catch (e2) {}
    }

    // --- Mobile sidebar ---
    if (e.target.closest('[data-menu-toggle]')) {
      document.querySelector('.sidebar')?.classList.toggle('open');
      document.querySelector('.backdrop')?.classList.toggle('open');
    }
    if (e.target.classList.contains('backdrop')) {
      document.querySelector('.sidebar')?.classList.remove('open');
      e.target.classList.remove('open');
    }

    // --- Internal-note toggle styling on the reply box ---
    var noteToggle = e.target.closest('[data-note-toggle]');
    if (noteToggle) {
      var box = document.querySelector('[data-reply-box]');
      if (box) { box.classList.toggle('note-mode', noteToggle.checked); }
    }
  });

  // --- Confirm destructive actions ------------------------------------------
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.hasAttribute('data-confirm')) {
      if (!window.confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    }
  });
})();
