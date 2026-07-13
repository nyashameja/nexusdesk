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

  // --- Live unread notification count ---------------------------------------
  var badge = document.querySelector('[data-notif-count]');
  if (badge && window.fetch) {
    var refresh = function () {
      fetch('/notifications/unread-count', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data) { return; }
          var n = data.count || 0;
          if (n > 0) { badge.textContent = n > 99 ? '99' : n; badge.removeAttribute('hidden'); }
          else { badge.setAttribute('hidden', ''); }
        })
        .catch(function () {});
    };
    setInterval(refresh, 60000); // poll once a minute
  }

  // --- AI assist (ticket workspace) -----------------------------------------
  function csrf() {
    var el = document.querySelector('input[name="_token"]');
    return el ? el.value : '';
  }
  document.addEventListener('click', function (e) {
    var chip = e.target.closest('[data-ai]');
    if (chip && window.fetch) {
      var task = chip.getAttribute('data-ai');
      var ticket = chip.getAttribute('data-ticket');
      var box = document.querySelector('[data-ai-result]');
      var out = document.querySelector('[data-ai-output]');
      var stub = document.querySelector('[data-ai-stub]');
      if (box && out) {
        box.removeAttribute('hidden');
        out.textContent = 'Thinking…';
        fetch('/desk/tickets/' + ticket + '/ai/' + task, {
          method: 'POST',
          headers: { 'X-CSRF-Token': csrf(), 'Accept': 'application/json' }
        })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            var data = (res && res.data) ? res.data : res;
            out.textContent = data.output || 'No suggestion.';
            if (stub) { stub.textContent = data.stubbed ? '(stub — connect a provider in Settings → AI)' : ''; }
          })
          .catch(function () { out.textContent = 'AI request failed.'; });
      }
    }
    var insert = e.target.closest('[data-ai-insert]');
    if (insert) {
      var text = document.querySelector('[data-ai-output]');
      var textarea = document.querySelector('[data-reply-box] textarea[name="body"]');
      if (text && textarea) {
        textarea.value = (textarea.value ? textarea.value + '\n\n' : '') + text.textContent;
        textarea.focus();
      }
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
