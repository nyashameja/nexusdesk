/* Paragon HostOps — vanilla JS (self-hosted, CSP-friendly, no inline scripts) */
(function () {
    'use strict';

    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    csrfToken = csrfToken ? csrfToken.getAttribute('content') : '';

    var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';

    /* ---- Mobile navigation drawer ----
       Slide-in panel with a focus trap, Escape handling, scroll lock and
       outside-click dismissal. Falls back to a plain visible sidebar if JS
       never runs (CSS shows it from 901px up regardless). */
    function initSidebar() {
        var burger   = document.querySelector('[data-toggle="sidebar"]');
        var sidebar  = document.querySelector('.pg-sidebar');
        var backdrop = document.querySelector('.pg-backdrop');
        if (!burger || !sidebar) return;

        var lastFocused = null;

        function isOpen() { return sidebar.classList.contains('open'); }

        function open() {
            lastFocused = document.activeElement;
            sidebar.classList.add('open');
            if (backdrop) { backdrop.hidden = false; backdrop.classList.add('show'); }
            burger.setAttribute('aria-expanded', 'true');
            document.body.classList.add('pg-scroll-locked');
            var first = sidebar.querySelector(FOCUSABLE);
            if (first) first.focus();
        }

        function close() {
            if (!isOpen()) return;
            sidebar.classList.remove('open');
            if (backdrop) { backdrop.classList.remove('show'); backdrop.hidden = true; }
            burger.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('pg-scroll-locked');
            if (lastFocused && lastFocused.focus) lastFocused.focus();
        }

        burger.addEventListener('click', function () { isOpen() ? close() : open(); });
        if (backdrop) backdrop.addEventListener('click', close);

        document.addEventListener('keydown', function (e) {
            if (!isOpen()) return;

            if (e.key === 'Escape') { close(); return; }

            /* Keep Tab inside the drawer while it covers the page. */
            if (e.key !== 'Tab') return;
            var items = Array.prototype.filter.call(
                sidebar.querySelectorAll(FOCUSABLE),
                function (el) { return el.offsetParent !== null; }
            );
            if (!items.length) return;
            var first = items[0], last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        });

        /* Returning to desktop width must not leave the page scroll-locked. */
        window.addEventListener('resize', function () {
            if (window.innerWidth > 900 && isOpen()) close();
        });
    }

    /* ---- Desktop sidebar collapse (icons only), remembered per browser ---- */
    function initSidebarCollapse() {
        var btn   = document.querySelector('[data-toggle="sidebar-collapse"]');
        var shell = document.getElementById('pg-shell');
        if (!btn || !shell) return;

        var KEY = 'pg.sidebar.collapsed';

        function apply(collapsed) {
            shell.classList.toggle('sidebar-collapsed', collapsed);
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }

        var stored = null;
        try { stored = window.localStorage.getItem(KEY); } catch (err) { /* private mode */ }
        apply(stored === '1');

        btn.addEventListener('click', function () {
            var collapsed = !shell.classList.contains('sidebar-collapsed');
            apply(collapsed);
            try { window.localStorage.setItem(KEY, collapsed ? '1' : '0'); } catch (err) { /* ignore */ }
        });
    }

    /* ---- Submit buttons show progress instead of looking unresponsive ---- */
    function initFormLoading() {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-no-loading')) return;
            var btn = form.querySelector('button[type="submit"], button:not([type])');
            if (btn) setTimeout(function () { btn.setAttribute('data-loading', 'true'); }, 0);
        });
    }

    /* ---- Toast notifications ---- */
    function toast(message, type) {
        var wrap = document.querySelector('.pg-toasts');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.className = 'pg-toasts';
            document.body.appendChild(wrap);
        }
        var el = document.createElement('div');
        el.className = 'pg-toast ' + (type || '');
        el.textContent = message;
        wrap.appendChild(el);
        setTimeout(function () { el.style.opacity = '0'; setTimeout(function () { el.remove(); }, 300); }, 4200);
    }
    window.pgToast = toast;

    /* ---- CSRF-aware JSON POST ---- */
    function postJson(url) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-Token': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            credentials: 'same-origin'
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); });
    }
    window.pgPostJson = postJson;

    /* ---- WHM connection test (settings page) ---- */
    function initWhmTest() {
        var btn = document.querySelector('[data-action="whm-test"]');
        var out = document.querySelector('[data-whm-test-result]');
        if (!btn || !out) return;

        btn.addEventListener('click', function () {
            btn.disabled = true;
            var original = btn.textContent;
            btn.textContent = 'Testing…';
            out.innerHTML = '<div class="pg-soft">Running connection test…</div>';

            postJson(btn.getAttribute('data-endpoint')).then(function (res) {
                renderTest(out, res.data);
                toast(res.data.ok ? 'WHM connection successful' : 'WHM connection failed', res.data.ok ? 'ok' : 'danger');
            }).catch(function () {
                out.innerHTML = '<div class="pg-alert error">Unable to run the connection test.</div>';
            }).finally(function () {
                btn.disabled = false;
                btn.textContent = original;
            });
        });
    }

    function renderTest(out, data) {
        if (!data) { out.innerHTML = ''; return; }
        var html = '<div class="pg-alert ' + (data.ok ? 'success' : 'error') + '">' + escapeHtml(data.message) + '</div>';
        if (data.steps && data.steps.length) {
            html += '<table class="pg-table"><tbody>';
            data.steps.forEach(function (s) {
                var badge = s.ok ? '<span class="pg-badge ok">Pass</span>' : '<span class="pg-badge danger">Fail</span>';
                html += '<tr><td>' + escapeHtml(s.name) + '</td><td class="pg-soft">' + escapeHtml(s.detail) + '</td><td class="text-right">' + badge + '</td></tr>';
            });
            html += '</tbody></table>';
        }
        out.innerHTML = html;
    }

    /* ---- Capability checker ---- */
    function initCapabilityCheck() {
        var btn = document.querySelector('[data-action="whm-capabilities"]');
        var out = document.querySelector('[data-whm-capabilities-result]');
        if (!btn || !out) return;

        btn.addEventListener('click', function () {
            btn.disabled = true;
            var original = btn.textContent;
            btn.textContent = 'Checking…';
            out.innerHTML = '<div class="pg-soft">Probing token capabilities…</div>';

            postJson(btn.getAttribute('data-endpoint')).then(function (res) {
                var caps = (res.data && res.data.capabilities) || [];
                var html = '<table class="pg-table"><thead><tr><th>Function</th><th>Status</th><th>Detail</th></tr></thead><tbody>';
                caps.forEach(function (c) {
                    html += '<tr><td><code>' + escapeHtml(c.function) + '</code></td><td>' + statusBadge(c.status, c.label) + '</td><td class="pg-soft">' + escapeHtml(c.message) + '</td></tr>';
                });
                html += '</tbody></table>';
                out.innerHTML = html;
            }).catch(function () {
                out.innerHTML = '<div class="pg-alert error">Unable to probe capabilities.</div>';
            }).finally(function () {
                btn.disabled = false;
                btn.textContent = original;
            });
        });
    }

    function statusBadge(status, label) {
        var cls = 'neutral';
        if (status === 'available') cls = 'ok';
        else if (status === 'permission_denied' || status === 'auth_failed') cls = 'warn';
        else if (status === 'server_error') cls = 'danger';
        return '<span class="pg-badge ' + cls + '">' + escapeHtml(label) + '</span>';
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initSidebarCollapse();
        initFormLoading();
        initWhmTest();
        initCapabilityCheck();
    });
})();
