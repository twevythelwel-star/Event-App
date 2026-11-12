/*
 * My Tickets: tab switching without a reload, the decorative keepsake image, and small confirmations.
 * Everything works without this script (tabs are links; the ticket page is complete on its own).
 *
 * The keepsake image is drawn in the browser from the data-* attributes on the share button, which carry only
 * the event's public details (title, date, place, ticket type, theme colours, artwork URL). It never includes
 * the admission QR token, the ticket code, the order number, the holder's name or any payment detail.
 */
(function () {
  'use strict';

  // ---------- Collection tabs ----------
  function initTabs() {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-collection-tab]'));
    if (!tabs.length) return;

    function select(tab, focus) {
      var key = tab.getAttribute('data-collection-tab');
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.classList.toggle('is-active', on);
        t.tabIndex = on ? 0 : -1;
      });
      document.querySelectorAll('[data-collection-panel]').forEach(function (panel) {
        panel.hidden = panel.getAttribute('data-collection-panel') !== key;
      });
      try { history.replaceState(null, '', tab.href); } catch (e) { /* not important */ }
      if (focus) tab.focus();
    }

    tabs.forEach(function (tab, i) {
      tab.tabIndex = tab.getAttribute('aria-selected') === 'true' ? 0 : -1;
      tab.addEventListener('click', function (e) {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        select(tab, false);
      });
      tab.addEventListener('keydown', function (e) {
        var next = null;
        if (e.key === 'ArrowRight') next = tabs[(i + 1) % tabs.length];
        if (e.key === 'ArrowLeft') next = tabs[(i - 1 + tabs.length) % tabs.length];
        if (e.key === 'Home') next = tabs[0];
        if (e.key === 'End') next = tabs[tabs.length - 1];
        if (next) { e.preventDefault(); select(next, true); }
      });
    });
  }

  // ---------- Small confirmation (calendar, saved keepsake) ----------
  function confirm(el, text) {
    var label = el.querySelector('[data-confirm-label]') || el;
    label.textContent = text;
    if (!el.querySelector('.eb-confirm-tick')) {
      var tick = document.createElement('span');
      tick.className = 'eb-confirm-tick';
      tick.setAttribute('aria-hidden', 'true');
      tick.textContent = '✓';
      el.appendChild(tick);
    }
    var live = document.getElementById('ticket-live-status');
    if (live) live.textContent = text;
  }

  function initConfirmations() {
    document.querySelectorAll('[data-confirm-text]').forEach(function (el) {
      el.addEventListener('click', function () { confirm(el, el.getAttribute('data-confirm-text')); });
    });
  }

  // ---------- Keepsake image ----------
  function loadImage(src) {
    return new Promise(function (resolve) {
      if (!src) return resolve(null);
      var img = new Image();
      img.decoding = 'async';
      img.onload = function () { resolve(img); };
      img.onerror = function () { resolve(null); };
      img.src = src;
    });
  }

  function wrap(ctx, text, maxWidth, maxLines) {
    var words = String(text || '').split(/\s+/), lines = [], line = '';
    words.forEach(function (w) {
      var test = line ? line + ' ' + w : w;
      if (ctx.measureText(test).width > maxWidth && line) { lines.push(line); line = w; } else { line = test; }
    });
    if (line) lines.push(line);
    if (lines.length > maxLines) {
      lines = lines.slice(0, maxLines);
      lines[maxLines - 1] = lines[maxLines - 1].replace(/\s*\S*$/, '') + '…';
    }
    return lines;
  }

  function cover(ctx, img, x, y, w, h) {
    var s = Math.max(w / img.naturalWidth, h / img.naturalHeight);
    var sw = w / s, sh = h / s;
    ctx.drawImage(img, (img.naturalWidth - sw) / 2, (img.naturalHeight - sh) * 0.35, sw, sh, x, y, w, h);
  }

  function draw(d, art, logo) {
    var W = 1080, H = 1350, canvas = document.createElement('canvas');
    canvas.width = W; canvas.height = H;
    var ctx = canvas.getContext('2d');
    var accent = d.accent || '#C7FF45', accent2 = d.accent2 || '#8B5CF6';

    ctx.fillStyle = '#07111F'; ctx.fillRect(0, 0, W, H);
    var artH = 760;
    if (art) {
      ctx.save();
      if (d.keepsake === '1') ctx.filter = 'sepia(0.2) saturate(0.9)';
      cover(ctx, art, 0, 0, W, artH);
      ctx.restore();
    } else {
      var g0 = ctx.createLinearGradient(0, 0, W, artH);
      g0.addColorStop(0, '#152C45'); g0.addColorStop(1, '#0B192B');
      ctx.fillStyle = g0; ctx.fillRect(0, 0, W, artH);
    }
    var scrim = ctx.createLinearGradient(0, artH * 0.45, 0, artH);
    scrim.addColorStop(0, 'rgba(7,17,31,0)'); scrim.addColorStop(1, 'rgba(7,17,31,1)');
    ctx.fillStyle = scrim; ctx.fillRect(0, 0, W, artH);

    var band = ctx.createLinearGradient(0, 0, W, 0);
    band.addColorStop(0, accent); band.addColorStop(1, accent2);
    ctx.fillStyle = band; ctx.fillRect(0, 0, W, 14);

    // Perforation between the artwork and the stub.
    ctx.strokeStyle = 'rgba(168,182,200,0.35)'; ctx.lineWidth = 4; ctx.setLineDash([18, 14]);
    ctx.beginPath(); ctx.moveTo(60, artH + 20); ctx.lineTo(W - 60, artH + 20); ctx.stroke(); ctx.setLineDash([]);
    ctx.fillStyle = '#07111F';
    ctx.beginPath(); ctx.arc(0, artH + 20, 34, 0, Math.PI * 2); ctx.arc(W, artH + 20, 34, 0, Math.PI * 2); ctx.fill();

    if (d.illustrative) {
      ctx.font = '600 26px system-ui, sans-serif'; ctx.textAlign = 'right';
      ctx.fillStyle = 'rgba(7,17,31,0.7)'; var lw = ctx.measureText(d.illustrative).width + 32;
      ctx.fillRect(W - 40 - lw, 40, lw, 48);
      ctx.fillStyle = '#F8FAFC'; ctx.fillText(d.illustrative, W - 56, 74); ctx.textAlign = 'left';
    }

    // Stamp
    ctx.save();
    ctx.translate(80, artH - 70); ctx.rotate(-0.06);
    ctx.font = '800 34px system-ui, sans-serif';
    var stamp = (d.stamp || 'Keepsake').toUpperCase(), sw = ctx.measureText(stamp).width + 48;
    ctx.strokeStyle = '#E7C873'; ctx.lineWidth = 5; ctx.fillStyle = 'rgba(7,17,31,0.75)';
    ctx.fillRect(0, -44, sw, 64); ctx.strokeRect(0, -44, sw, 64);
    ctx.fillStyle = '#E7C873'; ctx.fillText(stamp, 24, 2);
    ctx.restore();

    var x = 80, y = artH + 100;
    ctx.fillStyle = accent; ctx.font = '700 30px system-ui, sans-serif';
    ctx.fillText(String(d.kicker || '').toUpperCase(), x, y);
    y += 70;
    ctx.fillStyle = '#F8FAFC'; ctx.font = '800 64px system-ui, sans-serif';
    wrap(ctx, d.title, W - 160, 2).forEach(function (l) { ctx.fillText(l, x, y); y += 74; });
    ctx.fillStyle = '#A8B6C8'; ctx.font = '500 34px system-ui, sans-serif';
    [d.when, d.where, d.category].filter(Boolean).forEach(function (t) {
      wrap(ctx, t, W - 160, 1).forEach(function (l) { y += 8; ctx.fillText(l, x, y); y += 40; });
    });

    if (logo) {
      var lh = 56, lwid = logo.naturalWidth * (lh / logo.naturalHeight);
      ctx.drawImage(logo, W - 80 - lwid, H - 60 - lh, lwid, lh);
    } else {
      ctx.fillStyle = '#C7FF45'; ctx.font = '800 40px system-ui, sans-serif'; ctx.textAlign = 'right';
      ctx.fillText('EventBite', W - 80, H - 70); ctx.textAlign = 'left';
    }
    return canvas;
  }

  function toBlob(canvas) {
    return new Promise(function (resolve, reject) {
      try { canvas.toBlob(function (b) { b ? resolve(b) : reject(new Error('empty')); }, 'image/png'); } catch (e) { reject(e); }
    });
  }

  function initShare() {
    document.querySelectorAll('[data-keepsake-share]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var d = btn.dataset;
        btn.disabled = true;
        Promise.all([loadImage(d.art), loadImage(d.logo)]).then(function (imgs) {
          // If the artwork cannot be drawn into an image (another site's picture), use the plain design instead.
          return toBlob(draw(d, imgs[0], imgs[1])).catch(function () { return toBlob(draw(d, null, imgs[1])); });
        }).then(function (blob) {
          var file = new File([blob], d.filename || 'eventbite-keepsake.png', { type: 'image/png' });
          if (navigator.canShare && navigator.canShare({ files: [file] })) {
            return navigator.share({ files: [file], title: d.title }).then(function () { confirm(btn, 'Shared'); }, function () { /* cancelled */ });
          }
          var a = document.createElement('a');
          a.href = URL.createObjectURL(blob); a.download = file.name;
          document.body.appendChild(a); a.click(); a.remove();
          setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
          confirm(btn, 'Image saved');
        }).catch(function () {
          confirm(btn, 'Could not make the image');
        }).then(function () { btn.disabled = false; });
      });
    });
  }

  function init() { initTabs(); initConfirmations(); initShare(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
