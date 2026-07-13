/*
 * NexusDesk charts — a small dependency-free canvas renderer (line, bar,
 * doughnut). Chart.js is CDN-blocked in locked-down environments, and this
 * keeps the "vanilla JavaScript" stack with zero external requests.
 *
 * Usage:  <canvas data-chart='{"type":"line","labels":[...],"series":[{"name":"..","data":[..]}]}'></canvas>
 * Colours are read from CSS custom properties so charts match the active theme.
 */
(function () {
  'use strict';

  function cssVar(name, fallback) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
  }

  function palette() {
    return [
      cssVar('--brand', '#4f46e5'),
      cssVar('--accent', '#0891b2'),
      cssVar('--success', '#16a34a'),
      cssVar('--warning', '#d97706'),
      cssVar('--danger', '#dc2626'),
      cssVar('--info', '#2563eb')
    ];
  }

  function setupCanvas(canvas, height) {
    var ratio = window.devicePixelRatio || 1;
    var width = canvas.clientWidth || canvas.parentNode.clientWidth || 600;
    canvas.width = width * ratio;
    canvas.height = height * ratio;
    canvas.style.height = height + 'px';
    var ctx = canvas.getContext('2d');
    ctx.scale(ratio, ratio);
    return { ctx: ctx, w: width, h: height };
  }

  function niceMax(v) {
    if (v <= 0) { return 1; }
    var mag = Math.pow(10, Math.floor(Math.log10(v)));
    return Math.ceil(v / mag) * mag;
  }

  function drawGrid(ctx, w, h, pad, max, steps) {
    var grid = cssVar('--border', '#e6e9f2');
    var muted = cssVar('--muted', '#64748b');
    ctx.strokeStyle = grid; ctx.fillStyle = muted;
    ctx.lineWidth = 1; ctx.font = '11px system-ui, sans-serif'; ctx.textAlign = 'right';
    for (var i = 0; i <= steps; i++) {
      var y = pad.t + (h - pad.t - pad.b) * (i / steps);
      ctx.beginPath(); ctx.moveTo(pad.l, y); ctx.lineTo(w - pad.r, y); ctx.stroke();
      var val = Math.round(max * (1 - i / steps));
      ctx.fillText(String(val), pad.l - 8, y + 4);
    }
  }

  function drawLine(ctx, w, h, cfg) {
    var pad = { t: 16, r: 16, b: 28, l: 40 };
    var colors = palette();
    var allVals = cfg.series.reduce(function (a, s) { return a.concat(s.data); }, []);
    var max = niceMax(Math.max.apply(null, allVals.concat([1])));
    drawGrid(ctx, w, h, pad, max, 4);

    var muted = cssVar('--muted', '#64748b');
    var plotW = w - pad.l - pad.r, plotH = h - pad.t - pad.b;
    var n = cfg.labels.length;
    var x = function (i) { return pad.l + (n <= 1 ? plotW / 2 : plotW * i / (n - 1)); };
    var y = function (v) { return pad.t + plotH * (1 - v / max); };

    // x labels (sparse)
    ctx.fillStyle = muted; ctx.font = '10px system-ui, sans-serif'; ctx.textAlign = 'center';
    var step = Math.ceil(n / 8);
    for (var i = 0; i < n; i++) {
      if (i % step === 0 || i === n - 1) { ctx.fillText(cfg.labels[i], x(i), h - 8); }
    }

    cfg.series.forEach(function (s, si) {
      var col = colors[si % colors.length];
      // area fill
      ctx.beginPath(); ctx.moveTo(x(0), y(s.data[0]));
      s.data.forEach(function (v, i) { ctx.lineTo(x(i), y(v)); });
      ctx.lineTo(x(n - 1), pad.t + plotH); ctx.lineTo(x(0), pad.t + plotH); ctx.closePath();
      ctx.fillStyle = col + '22'; ctx.fill();
      // line
      ctx.beginPath(); ctx.strokeStyle = col; ctx.lineWidth = 2;
      s.data.forEach(function (v, i) { i === 0 ? ctx.moveTo(x(i), y(v)) : ctx.lineTo(x(i), y(v)); });
      ctx.stroke();
      // endpoint dot
      ctx.beginPath(); ctx.fillStyle = col; ctx.arc(x(n - 1), y(s.data[n - 1]), 3, 0, 7); ctx.fill();
    });
  }

  function drawBar(ctx, w, h, cfg) {
    var pad = { t: 16, r: 16, b: 34, l: 40 };
    var colors = palette();
    var data = cfg.series[0].data;
    var max = niceMax(Math.max.apply(null, data.concat([1])));
    drawGrid(ctx, w, h, pad, max, 4);
    var plotW = w - pad.l - pad.r, plotH = h - pad.t - pad.b;
    var n = data.length;
    var bw = plotW / n * 0.6, gap = plotW / n;
    var muted = cssVar('--muted', '#64748b');
    ctx.fillStyle = muted; ctx.font = '10px system-ui, sans-serif'; ctx.textAlign = 'center';
    data.forEach(function (v, i) {
      var bh = plotH * v / max;
      var x = pad.l + gap * i + (gap - bw) / 2;
      ctx.fillStyle = colors[i % colors.length];
      var y = pad.t + plotH - bh;
      var r = Math.min(6, bw / 2);
      ctx.beginPath();
      ctx.moveTo(x, y + r); ctx.arcTo(x, y, x + r, y, r);
      ctx.lineTo(x + bw - r, y); ctx.arcTo(x + bw, y, x + bw, y + r, r);
      ctx.lineTo(x + bw, pad.t + plotH); ctx.lineTo(x, pad.t + plotH); ctx.closePath(); ctx.fill();
      ctx.fillStyle = muted; ctx.fillText(cfg.labels[i], x + bw / 2, h - 10);
    });
  }

  function drawDoughnut(ctx, w, h, cfg) {
    var colors = palette();
    var data = cfg.series[0].data;
    var total = data.reduce(function (a, b) { return a + b; }, 0) || 1;
    var cx = w / 2, cy = h / 2, R = Math.min(w, h) / 2 - 10, r = R * 0.62;
    var start = -Math.PI / 2;
    data.forEach(function (v, i) {
      var ang = (v / total) * Math.PI * 2;
      ctx.beginPath(); ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, R, start, start + ang); ctx.closePath();
      ctx.fillStyle = colors[i % colors.length]; ctx.fill();
      start += ang;
    });
    // punch the hole
    ctx.globalCompositeOperation = 'destination-out';
    ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.fill();
    ctx.globalCompositeOperation = 'source-over';
    ctx.fillStyle = cssVar('--ink', '#0f172a');
    ctx.font = '600 20px system-ui, sans-serif'; ctx.textAlign = 'center';
    ctx.fillText(String(total), cx, cy + 6);
  }

  function render(canvas) {
    var cfg;
    try { cfg = JSON.parse(canvas.getAttribute('data-chart')); } catch (e) { return; }
    if (!cfg || !cfg.type) { return; }
    var height = parseInt(canvas.getAttribute('data-height') || '220', 10);
    var s = setupCanvas(canvas, height);
    s.ctx.clearRect(0, 0, s.w, s.h);
    if (cfg.type === 'line') { drawLine(s.ctx, s.w, s.h, cfg); }
    else if (cfg.type === 'bar') { drawBar(s.ctx, s.w, s.h, cfg); }
    else if (cfg.type === 'doughnut') { drawDoughnut(s.ctx, s.w, s.h, cfg); }
  }

  function renderAll() {
    document.querySelectorAll('canvas[data-chart]').forEach(render);
  }

  document.addEventListener('DOMContentLoaded', renderAll);
  window.addEventListener('resize', function () { clearTimeout(window.__chartRz); window.__chartRz = setTimeout(renderAll, 200); });
  // Re-render on theme toggle so colours follow the theme.
  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-theme-toggle]')) { setTimeout(renderAll, 50); }
  });
})();
