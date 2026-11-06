// K&M Urban Properties — admin behaviour (1.1.0)

/* ---------- Menu: ☰ collapses the icon sidebar (kept per browser, per layout); on phones it opens the menu ---------- */
function navToggle() {
  var b = document.body, l = b.dataset.layout;
  if (window.matchMedia('(max-width:860px)').matches || !b.classList.contains('navl')) { b.classList.toggle('navo'); return; }
  var c = !b.classList.contains('navc');
  b.classList.toggle('navc', c);
  try { localStorage.setItem('kmup_nav_' + l, c ? '1' : '0'); } catch (e) {}
}

/* ---------- Light / dark mode: follows the theme setting; the header button overrides it for this browser ---------- */
function applyMode(m) {
  var d = m === 'dark' || (m === 'auto' && window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.setAttribute('data-bs-theme', d ? 'dark' : 'light');
  var btn = document.getElementById('modeBtn');
  if (btn) {
    btn.innerHTML = '<i class="bi ' + (m === 'dark' ? 'bi-moon-stars' : m === 'light' ? 'bi-sun' : 'bi-circle-half') + '"></i>';
    btn.title = 'Appearance: ' + (m === 'auto' ? 'follow device' : m) + ' (click to change)';
  }
}
function currentMode() { var m = null; try { m = localStorage.getItem('kmup_mode'); } catch (e) {} return m || 'auto'; }
function cycleMode() {
  var order = ['auto', 'light', 'dark'], m = order[(order.indexOf(currentMode()) + 1) % 3];
  try { if (m === 'auto') localStorage.removeItem('kmup_mode'); else localStorage.setItem('kmup_mode', m); } catch (e) {}
  applyMode(m);
}

/* ---------- Sign out inactive users ----------
   Activity = mouse, keyboard, touch or scrolling in any admin tab (shared through localStorage).
   Every minute the page tells the server how long it has been idle; one minute before the limit a
   warning offers "Stay signed in". Background refreshes do not count as activity. */
(function () {
  var b = document.body;
  if (!b || !b.dataset.ping) return;
  var limit = (parseInt(b.dataset.idleMin, 10) || 45) * 60, lastLocal = Date.now(), lastWrite = 0, warned = false, modal = null, timer = null;
  function lastAct() { var s = 0; try { s = parseInt(localStorage.getItem('kmup_act') || '0', 10); } catch (e) {} return Math.max(lastLocal, s || 0); }
  function mark() {
    lastLocal = Date.now();
    if (lastLocal - lastWrite > 5000) { lastWrite = lastLocal; try { localStorage.setItem('kmup_act', String(lastLocal)); } catch (e) {} }
    if (warned) hideWarn();
  }
  ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll', 'wheel'].forEach(function (ev) { window.addEventListener(ev, mark, { passive: true }); });
  mark();
  function idleSecs() { return Math.max(0, Math.floor((Date.now() - lastAct()) / 1000)); }
  function ping(idle) {
    var fd = new FormData(); fd.append('csrf', b.dataset.csrf); fd.append('idle', String(idle));
    return fetch(b.dataset.ping, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!j.ok) { location.reload(); return; } if (j.idle_min) limit = j.idle_min * 60; return j; })
      .catch(function () {});
  }
  function showWarn() {
    warned = true;
    if (!modal && window.bootstrap) modal = new bootstrap.Modal(document.getElementById('idleModal'));
    if (modal) modal.show();
    clearInterval(timer);
    timer = setInterval(function () {
      var left = limit - idleSecs();
      var el = document.getElementById('idleLeft'); if (el) el.textContent = String(Math.max(0, left));
      if (left <= 0) { clearInterval(timer); ping(limit + 60); }
    }, 1000);
  }
  function hideWarn() { warned = false; clearInterval(timer); if (modal) modal.hide(); }
  var stay = document.getElementById('idleStay');
  if (stay) stay.addEventListener('click', function () { mark(); try { localStorage.setItem('kmup_act', String(Date.now())); } catch (e) {} hideWarn(); ping(0); });
  setInterval(function () {
    var idle = idleSecs();
    if (idle >= limit - 60 && !warned) showWarn();
    if (idle < limit - 60 && warned) hideWarn();
  }, 5000);
  setInterval(function () { ping(idleSecs()); }, 60000);
})();

document.addEventListener('DOMContentLoaded', function () {
  applyMode(currentMode());

  // Clock in the header
  var clk = document.getElementById('clk');
  if (clk) {
    var tick = function () { clk.textContent = new Date().toLocaleString([], { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }); };
    tick(); setInterval(tick, 30000);
  }

  // Preview photos chosen for upload
  var input = document.getElementById('photoInput');
  var preview = document.getElementById('photoPreview');
  if (input && preview) {
    input.addEventListener('change', function () {
      preview.innerHTML = '';
      Array.prototype.slice.call(input.files, 0, 40).forEach(function (f) {
        if (!f.type.match(/^image\//)) return;
        var img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.title = f.name;
        preview.appendChild(img);
      });
    });
  }

  // Icon picker in landing section items
  var iconInput = document.getElementById('iconInput');
  document.querySelectorAll('.icon-opt').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.icon-opt').forEach(function (x) { x.classList.remove('active'); });
      btn.classList.add('active');
      if (iconInput) iconInput.value = btn.dataset.icon;
    });
  });

  // Property form: hide "per month" when the listing is for sale
  var rentOnly = document.querySelectorAll('.rent-only');
  function syncListingType() {
    var sale = document.getElementById('ltSale');
    rentOnly.forEach(function (el) { el.style.display = sale && sale.checked ? 'none' : ''; });
  }
  document.querySelectorAll('input[name="listing_type"]').forEach(function (r) { r.addEventListener('change', syncListingType); });
  syncListingType();

  // ID photos / document uploads: shrink large photos in the browser (max 2000 px JPEG), preview at once,
  // and keep only one of the paired "Take photo" / "Upload" inputs filled for the same ID side.
  function shrink(file) {
    return new Promise(function (resolve) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { resolve(file); return; }
      var img = new Image(), u = URL.createObjectURL(file);
      img.onload = function () {
        var s = Math.min(1, 2000 / Math.max(img.width, img.height));
        if (s === 1 && file.size < 1200 * 1024) { URL.revokeObjectURL(u); resolve(file); return; }
        var c = document.createElement('canvas');
        c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (b) { URL.revokeObjectURL(u); resolve(b ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file); }, 'image/jpeg', 0.86);
      };
      img.onerror = function () { URL.revokeObjectURL(u); resolve(file); };
      img.src = u;
    });
  }
  document.querySelectorAll('input[type=file][data-resize]').forEach(function (fi) {
    fi.addEventListener('change', function () {
      var files = Array.prototype.slice.call(fi.files);
      if (!files.length) return;
      if (fi.dataset.pair) { var other = fi.form && fi.form.querySelector('input[name="' + fi.dataset.pair + '"]'); if (other) other.value = ''; }
      var box = fi.dataset.preview ? document.querySelector(fi.dataset.preview) : null;
      Promise.all(files.map(shrink)).then(function (done) {
        if (typeof DataTransfer !== 'undefined') { var dt = new DataTransfer(); done.forEach(function (f) { dt.items.add(f); }); fi.files = dt.files; }
        if (box) {
          var f = done[0];
          box.innerHTML = /^image\//.test(f.type)
            ? '<div class="id-thumb"><img alt="" src="' + URL.createObjectURL(f) + '"></div><div class="hint mt-1 text-success"><i class="bi bi-check2"></i> New photo — saved when you click Save tenant</div>'
            : '<div class="id-thumb is-file"><i class="bi bi-file-earmark-pdf"></i><span></span></div><div class="hint mt-1 text-success"><i class="bi bi-check2"></i> Saved when you click Save tenant</div>';
          var sp = box.querySelector('span'); if (sp) sp.textContent = f.name;
        }
      });
    });
  });

  // Warn before uploads larger than the server allows
  document.querySelectorAll('input[type="file"]').forEach(function (fi) {
    fi.addEventListener('change', function () {
      var big = Array.prototype.filter.call(fi.files, function (f) { return f.size > 10 * 1024 * 1024 && !(fi.hasAttribute('data-resize') && /^image\//.test(f.type)); });
      if (big.length) alert(big.length + ' file(s) are over 10 MB and will probably be rejected by the server.');
    });
  });
});
