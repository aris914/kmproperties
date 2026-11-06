/* Map pin picker and read-only map (Leaflet + OpenStreetMap). Used on List Your Property,
   the admin property form and the property page.
   <div data-map-picker data-lat="#lat" data-lng="#lng"></div>  — click / drag to set the pin
   <div data-map-view data-lat="14.55" data-lng="121.02"></div>  — shows a pin */
(function () {
  var MANILA = [14.5995, 120.9842];
  function tiles(map) {
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
  }
  function num(v) { var n = parseFloat(v); return isFinite(n) ? n : null; }

  function picker(el) {
    var latIn = document.querySelector(el.dataset.lat), lngIn = document.querySelector(el.dataset.lng);
    var lat = num(latIn && latIn.value), lng = num(lngIn && lngIn.value);
    var map = L.map(el, { scrollWheelZoom: false }).setView(lat !== null ? [lat, lng] : MANILA, lat !== null ? 17 : 11);
    tiles(map);
    var marker = null, status = el.parentElement.querySelector('[data-map-status]');
    function set(ll, zoom) {
      var a = Math.round(ll.lat * 1e7) / 1e7, b = Math.round(ll.lng * 1e7) / 1e7;
      if (latIn) latIn.value = a; if (lngIn) lngIn.value = b;
      if (!marker) {
        marker = L.marker(ll, { draggable: true }).addTo(map);
        marker.on('dragend', function () { set(marker.getLatLng()); });
      } else marker.setLatLng(ll);
      if (zoom) map.setView(ll, zoom);
      if (status) status.textContent = 'Pinned at ' + a.toFixed(5) + ', ' + b.toFixed(5) + ' — drag the pin to adjust.';
    }
    function clear() {
      if (marker) { map.removeLayer(marker); marker = null; }
      if (latIn) latIn.value = ''; if (lngIn) lngIn.value = '';
      if (status) status.textContent = 'No pin yet — click the map where the property is.';
    }
    if (lat !== null) set(L.latLng(lat, lng)); else clear();
    map.on('click', function (e) { set(e.latlng); });

    var wrap = el.parentElement;
    var q = wrap.querySelector('[data-map-search]'), go = wrap.querySelector('[data-map-go]');
    function search() {
      var text = (q && q.value || '').trim(); if (!text) return;
      if (status) status.textContent = 'Searching…';
      fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ph&q=' + encodeURIComponent(text), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j && j[0]) set(L.latLng(+j[0].lat, +j[0].lon), 17);
          else if (status) status.textContent = 'Not found — try the building name and city, or click the map.';
        })
        .catch(function () { if (status) status.textContent = 'Search is not available right now — click the map instead.'; });
    }
    if (go) go.addEventListener('click', search);
    if (q) q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); search(); } });
    var me = wrap.querySelector('[data-map-me]');
    if (me && navigator.geolocation) {
      me.addEventListener('click', function () {
        if (status) status.textContent = 'Finding your location…';
        navigator.geolocation.getCurrentPosition(function (p) { set(L.latLng(p.coords.latitude, p.coords.longitude), 18); },
          function () { if (status) status.textContent = 'Your location is not available — click the map instead.'; }, { enableHighAccuracy: true, timeout: 10000 });
      });
    } else if (me) me.style.display = 'none';
    var clr = wrap.querySelector('[data-map-clear]');
    if (clr) clr.addEventListener('click', clear);
    // maps inside hidden tabs / collapsed areas need a resize once shown
    setTimeout(function () { map.invalidateSize(); }, 300);
  }

  function view(el) {
    var lat = num(el.dataset.lat), lng = num(el.dataset.lng);
    if (lat === null || lng === null) return;
    var map = L.map(el, { scrollWheelZoom: false }).setView([lat, lng], 16);
    tiles(map);
    L.marker([lat, lng]).addTo(map);
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (!window.L) return;
    document.querySelectorAll('[data-map-picker]').forEach(picker);
    document.querySelectorAll('[data-map-view]').forEach(view);
  });
})();

/* Photo picker: previews, remove, up to N photos, resized in the browser (max 1600 px JPEG)
   so several photos fit within the server's upload limit.
   <input type="file" multiple data-photo-picker data-max="12" data-preview="#id"> */
(function () {
  function resize(file, max) {
    return new Promise(function (resolve) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { resolve(file); return; }
      var img = new Image(), url = URL.createObjectURL(file);
      img.onload = function () {
        var s = Math.min(1, max / Math.max(img.width, img.height));
        if (s === 1 && file.size < 900 * 1024) { URL.revokeObjectURL(url); resolve(file); return; }
        var c = document.createElement('canvas');
        c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (b) {
          URL.revokeObjectURL(url);
          resolve(b ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
        }, 'image/jpeg', 0.82);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-photo-picker]').forEach(function (input) {
      var max = parseInt(input.dataset.max || '12', 10), box = document.querySelector(input.dataset.preview), files = [];
      var canSet = typeof DataTransfer !== 'undefined';
      function sync() {
        if (canSet) { var dt = new DataTransfer(); files.forEach(function (f) { dt.items.add(f); }); input.files = dt.files; }
        if (!box) return;
        box.innerHTML = '';
        files.forEach(function (f, i) {
          var d = document.createElement('div'); d.className = 'pp-item';
          d.innerHTML = '<img alt=""><button type="button" aria-label="Remove photo">&times;</button>' + (i === 0 ? '<span>Cover</span>' : '');
          d.querySelector('img').src = URL.createObjectURL(f);
          d.querySelector('button').addEventListener('click', function () { files.splice(i, 1); sync(); });
          box.appendChild(d);
        });
        var note = document.querySelector(input.dataset.count);
        if (note) note.textContent = files.length ? files.length + ' of ' + max + ' photos' : '';
      }
      input.addEventListener('change', function () {
        if (!canSet) return; // old browsers: send the files as chosen
        var chosen = Array.prototype.slice.call(input.files);
        Promise.all(chosen.map(function (f) { return resize(f, 1600); })).then(function (done) {
          done.forEach(function (f) { if (files.length < max && /^image\//.test(f.type)) files.push(f); });
          if (chosen.length + 0 > 0 && files.length >= max) { /* silently capped */ }
          sync();
        });
      });
    });
  });
})();
