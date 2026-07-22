/* Paragon HostOps — finance charts (Chart.js, self-hosted, CSP-safe). */
(function () {
    'use strict';
    if (typeof Chart === 'undefined') return;
    var island = document.getElementById('pg-finance-data');
    if (!island) return;
    var data;
    try { data = JSON.parse(island.textContent || '{}'); } catch (e) { return; }

    var categorical = ['#1d4ed8', '#0ea5a4', '#d97706', '#0284c7', '#16a34a', '#dc2626', '#7c3aed'];
    Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    Chart.defaults.color = '#5b6879';
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;

    function ctx(id) { var el = document.getElementById(id); return el ? el.getContext('2d') : null; }

    (function () {
        var c = ctx('chartRevenue'); if (!c) return;
        var d = data.revenueByCategory || {};
        var labels = Object.keys(d);
        if (!labels.length) return;
        new Chart(c, {
            type: 'doughnut',
            data: { labels: labels.map(function (l) { return l.charAt(0).toUpperCase() + l.slice(1); }),
                datasets: [{ data: labels.map(function (l) { return d[l]; }), backgroundColor: categorical, borderWidth: 0 }] },
            options: { cutout: '60%', plugins: { legend: { position: 'right' } } }
        });
    })();

    (function () {
        var c = ctx('chartPayments'); if (!c) return;
        var d = data.paymentStatus || {};
        var labels = Object.keys(d);
        if (!labels.length) return;
        var colorMap = { paid: '#16a34a', due: '#0284c7', partial: '#d97706', overdue: '#dc2626', suspended: '#b45309', complimentary: '#7c3aed', cancelled: '#94a3b8' };
        new Chart(c, {
            type: 'doughnut',
            data: { labels: labels.map(function (l) { return l.charAt(0).toUpperCase() + l.slice(1); }),
                datasets: [{ data: labels.map(function (l) { return d[l]; }), backgroundColor: labels.map(function (l) { return colorMap[l] || '#cbd5e1'; }), borderWidth: 0 }] },
            options: { cutout: '60%', plugins: { legend: { position: 'right' } } }
        });
    })();
})();
