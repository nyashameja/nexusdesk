/* Paragon HostOps — dashboard charts (Chart.js, self-hosted, CSP-safe).
   All chart data is read from a JSON <script> island rendered server-side;
   no inline executable script is used. */
(function () {
    'use strict';

    if (typeof Chart === 'undefined') return;

    var island = document.getElementById('pg-dashboard-data');
    if (!island) return;

    var data;
    try { data = JSON.parse(island.textContent || '{}'); } catch (e) { return; }

    // Shared palette (matches the CSS design tokens).
    var C = {
        primary: '#1d4ed8', accent: '#0ea5a4', success: '#16a34a',
        warning: '#d97706', danger: '#dc2626', info: '#0284c7',
        grid: 'rgba(16,26,43,.07)', text: '#5b6879'
    };
    var categorical = [C.primary, C.accent, C.warning, C.info, C.success, C.danger, '#7c3aed', '#0891b2', '#b45309', '#4b5563'];

    Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = C.text;
    Chart.defaults.plugins.legend.labels.boxWidth = 12;
    Chart.defaults.plugins.legend.labels.boxHeight = 12;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.maintainAspectRatio = false;

    function ctx(id) {
        var el = document.getElementById(id);
        return el ? el.getContext('2d') : null;
    }

    function gb(mb) { return Math.round((mb / 1024) * 10) / 10; }

    var noGrid = { grid: { display: false }, ticks: { autoSkip: true, maxRotation: 0 } };
    var yGrid = { grid: { color: C.grid }, beginAtZero: true, ticks: { precision: 0 } };

    /* Disk usage by account (horizontal bar, GB) */
    (function () {
        var c = ctx('chartDisk'); if (!c) return;
        var d = data.diskByAccount || [];
        new Chart(c, {
            type: 'bar',
            data: {
                labels: d.map(function (r) { return r.domain; }),
                datasets: [{ label: 'Disk used (GB)', data: d.map(function (r) { return gb(r.used); }), backgroundColor: C.primary, borderRadius: 4, barThickness: 14 }]
            },
            options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: yGrid, y: noGrid } }
        });
    })();

    /* Bandwidth by account (horizontal bar, GB) */
    (function () {
        var c = ctx('chartBandwidth'); if (!c) return;
        var d = data.bandwidthByAccount || [];
        new Chart(c, {
            type: 'bar',
            data: {
                labels: d.map(function (r) { return r.domain; }),
                datasets: [{ label: 'Bandwidth (GB)', data: d.map(function (r) { return gb(r.used); }), backgroundColor: C.accent, borderRadius: 4, barThickness: 14 }]
            },
            options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: yGrid, y: noGrid } }
        });
    })();

    /* Accounts by package (doughnut) */
    (function () {
        var c = ctx('chartPackage'); if (!c) return;
        var d = data.byPackage || [];
        new Chart(c, {
            type: 'doughnut',
            data: {
                labels: d.map(function (r) { return r.package; }),
                datasets: [{ data: d.map(function (r) { return r.total; }), backgroundColor: categorical, borderWidth: 0 }]
            },
            options: { cutout: '62%', plugins: { legend: { position: 'right' } } }
        });
    })();

    /* Active vs suspended (doughnut) */
    (function () {
        var c = ctx('chartStatus'); if (!c) return;
        var d = data.activeSuspended || { active: 0, suspended: 0 };
        new Chart(c, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Suspended'],
                datasets: [{ data: [d.active, d.suspended], backgroundColor: [C.success, C.danger], borderWidth: 0 }]
            },
            options: { cutout: '62%', plugins: { legend: { position: 'right' } } }
        });
    })();

    /* SSL status distribution (doughnut) */
    (function () {
        var c = ctx('chartSsl'); if (!c) return;
        var d = data.sslDistribution || {};
        var labels = Object.keys(d);
        var colorMap = { valid: C.success, expiring: C.warning, expired: C.danger, invalid: C.danger, missing: '#94a3b8', unknown: '#cbd5e1' };
        new Chart(c, {
            type: 'doughnut',
            data: {
                labels: labels.map(function (l) { return l.charAt(0).toUpperCase() + l.slice(1); }),
                datasets: [{ data: labels.map(function (l) { return d[l]; }), backgroundColor: labels.map(function (l) { return colorMap[l] || '#cbd5e1'; }), borderWidth: 0 }]
            },
            options: { cutout: '62%', plugins: { legend: { position: 'right' } } }
        });
    })();

    /* Accounts created over time (line) */
    (function () {
        var c = ctx('chartCreated'); if (!c) return;
        var d = data.createdOverTime || [];
        new Chart(c, {
            type: 'line',
            data: {
                labels: d.map(function (r) { return r.month; }),
                datasets: [{
                    label: 'Accounts created', data: d.map(function (r) { return r.total; }),
                    borderColor: C.primary, backgroundColor: 'rgba(29,78,216,.12)',
                    fill: true, tension: .3, pointRadius: 3, pointBackgroundColor: C.primary
                }]
            },
            options: { plugins: { legend: { display: false } }, scales: { x: noGrid, y: yGrid } }
        });
    })();
})();
