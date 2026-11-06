// K&M Urban Properties — public site behaviour
document.addEventListener('DOMContentLoaded', function () {
  // Property gallery: thumbnails swap the main image; clicking opens the carousel at that photo
  var mainImg = document.getElementById('galleryMainImg');
  var main = document.querySelector('.gallery-main[data-index]');
  document.querySelectorAll('.gallery-thumb').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (mainImg) mainImg.src = btn.dataset.src;
      if (main) main.dataset.index = btn.dataset.index;
      document.querySelectorAll('.gallery-thumb').forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
    });
  });
  var modal = document.getElementById('galleryModal');
  var carouselEl = document.getElementById('galleryCarousel');
  if (modal && carouselEl && window.bootstrap) {
    modal.addEventListener('show.bs.modal', function () {
      var idx = parseInt((main && main.dataset.index) || '0', 10);
      bootstrap.Carousel.getOrCreateInstance(carouselEl).to(idx);
    });
  }
  // Mobile: tapping a dropdown toggle should open it rather than navigate
  document.querySelectorAll('.site-header .dropdown-toggle').forEach(function (t) {
    t.addEventListener('click', function (e) { e.preventDefault(); });
  });
});
