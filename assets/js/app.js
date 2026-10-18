(function () {
  'use strict';

  // Menu mobile
  var burger = document.querySelector('[data-burger]');
  var menu = document.getElementById('menu');
  if (burger && menu) {
    burger.addEventListener('click', function () {
      var open = menu.classList.toggle('open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Pilihan otomatis kirim
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });

  // NIK: hanya angka
  document.querySelectorAll('[data-digits]').forEach(function (el) {
    el.addEventListener('input', function () {
      el.value = el.value.replace(/\D+/g, '').slice(0, 16);
    });
  });

  // Konfirmasi tindakan admin
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.getAttribute('data-confirm'))) { ev.preventDefault(); }
    });
  });

  // Surat suara: periksa pilihan sebelum kirim
  var ballot = document.querySelector('[data-ballot]');
  if (ballot) {
    var dialog = ballot.querySelector('[data-dialog]');
    var pick = ballot.querySelector('[data-pick]');
    var review = ballot.querySelector('[data-review]');
    var cancel = ballot.querySelector('[data-cancel]');
    var supportsDialog = dialog && typeof dialog.showModal === 'function';

    review.addEventListener('click', function () {
      var sel = ballot.querySelector('input[name="kandidat"]:checked');
      if (!sel) {
        var first = ballot.querySelector('input[name="kandidat"]');
        if (first) { first.focus(); }
        window.alert('Pilih salah satu kandidat terlebih dahulu.');
        return;
      }
      var label = 'nomor ' + sel.getAttribute('data-no') + ', ' + sel.getAttribute('data-nama');
      if (supportsDialog) {
        pick.textContent = label;
        dialog.showModal();
      } else if (window.confirm('Kirim suara untuk ' + label + '? Tidak dapat diubah.')) {
        ballot.submit();
      }
    });

    if (cancel) { cancel.addEventListener('click', function () { dialog.close(); }); }

    // Cegah kirim ganda
    ballot.addEventListener('submit', function () {
      ballot.querySelectorAll('button[type="submit"]').forEach(function (b) { b.disabled = true; });
    });
  }
})();
