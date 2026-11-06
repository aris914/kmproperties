// K&M Urban Properties — admin behaviour
document.addEventListener('DOMContentLoaded', function () {
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
      document.querySelectorAll('.icon-opt').forEach(function (b) { b.classList.remove('active'); });
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

  // Warn before uploads larger than the configured limit
  document.querySelectorAll('input[type="file"]').forEach(function (fi) {
    fi.addEventListener('change', function () {
      var big = Array.prototype.filter.call(fi.files, function (f) { return f.size > 10 * 1024 * 1024; });
      if (big.length) alert(big.length + ' file(s) are over 10 MB and will probably be rejected by the server.');
    });
  });
});
