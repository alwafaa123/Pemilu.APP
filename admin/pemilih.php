<?php
require_once __DIR__ . '/../includes/layout.php';
$adm = require_admin();
$db  = db_admin();
const PER = 20;

if (is_post()) {
    csrf_verify();
    $aksi = post('aksi');
    if ($aksi === 'tambah') {
        $tps = post('tps');
        flash_status(proc_status($db, 'sp_registrasi_pemilih',
            [post('nik'), post('nama'), (string)($_POST['password'] ?? ''), $tps !== '' ? $tps : null]));
    } elseif ($aksi === 'aktif') {
        flash_status(proc_status($db, 'sp_ubah_status_pemilih',
            [$adm['id'], post('nik'), (int)post('aktif')]));
    }
    redirect('admin/pemilih.php' . (post('q') !== '' ? '?q=' . urlencode(post('q')) : ''));
}

$q    = trim((string)($_GET['q'] ?? ''));
$hal  = max(1, (int)($_GET['hal'] ?? 1));
$rows = proc_rows($db, 'sp_daftar_pemilih', [$q, PER, ($hal - 1) * PER]);
$total = (int)($rows[0]['total'] ?? 0);
$jml   = max(1, (int)ceil($total / PER));

admin_start('Pemilih', 'pemilih');
?>
<section class="panel">
  <h2>Daftarkan pemilih</h2>
  <form method="post" class="form" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="tambah">
    <div class="row-form">
      <div><label for="nik">NIK (16 digit)</label><input id="nik" name="nik" inputmode="numeric" maxlength="16" required data-digits></div>
      <div class="f-wide"><label for="nama">Nama lengkap</label><input id="nama" name="nama" maxlength="100" required></div>
      <div><label for="tps">TPS</label><input id="tps" name="tps" maxlength="20"></div>
      <div><label for="pw">Kata sandi awal</label><input id="pw" name="password" type="password" minlength="6" required autocomplete="new-password"></div>
    </div>
    <button class="btn" type="submit">Daftarkan</button>
  </form>
</section>

<form method="get" class="picker">
  <label for="q">Cari pemilih</label>
  <input id="q" name="q" value="<?= e($q) ?>" placeholder="NIK, nama, atau TPS">
  <button class="btn sm" type="submit">Cari</button>
</form>

<?php if (!$rows): ?>
  <p class="empty"><?= $q !== '' ? 'Tidak ada pemilih yang cocok dengan pencarian.' : 'Belum ada pemilih terdaftar.' ?></p>
<?php else: ?>
<p class="muted"><?= $total ?> pemilih ditemukan.</p>
<div class="scroll">
<table class="tbl">
  <thead><tr><th scope="col">NIK</th><th scope="col">Nama</th><th scope="col">TPS</th><th scope="col">Status</th><th scope="col">Tindakan</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $aktif = (int)$r['status_aktif'] === 1; ?>
    <tr>
      <td><code><?= e($r['nik']) ?></code></td>
      <td><?= e($r['nama_lengkap']) ?></td>
      <td><?= e((string)$r['tps']) ?></td>
      <td><?= $aktif ? '<span class="badge live">Aktif</span>' : '<span class="badge draft">Nonaktif</span>' ?></td>
      <td>
        <form method="post" class="inline" data-confirm="<?= $aktif ? 'Nonaktifkan pemilih ini? Ia tidak akan bisa masuk atau memilih.' : 'Aktifkan kembali pemilih ini?' ?>">
          <?= csrf_field() ?><input type="hidden" name="aksi" value="aktif">
          <input type="hidden" name="nik" value="<?= e($r['nik']) ?>"><input type="hidden" name="aktif" value="<?= $aktif ? 0 : 1 ?>">
          <input type="hidden" name="q" value="<?= e($q) ?>">
          <button class="btn ghost sm" type="submit"><?= $aktif ? 'Nonaktifkan' : 'Aktifkan' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<nav class="pager" aria-label="Halaman">
  <?php if ($hal > 1): ?><a class="btn ghost sm" href="<?= e(url('admin/pemilih.php?q=' . urlencode($q) . '&hal=' . ($hal - 1))) ?>">Sebelumnya</a><?php endif; ?>
  <span class="muted">Halaman <?= $hal ?> dari <?= $jml ?></span>
  <?php if ($hal < $jml): ?><a class="btn ghost sm" href="<?= e(url('admin/pemilih.php?q=' . urlencode($q) . '&hal=' . ($hal + 1))) ?>">Berikutnya</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php admin_end();
