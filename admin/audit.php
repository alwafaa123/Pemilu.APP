<?php
require_once __DIR__ . '/../includes/layout.php';
require_admin();
$db = db_admin();
const PER = 30;

$aksi  = trim((string)($_GET['aksi'] ?? ''));
$hasil = trim((string)($_GET['hasil'] ?? ''));
$hal   = max(1, (int)($_GET['hal'] ?? 1));
$rows  = proc_rows($db, 'sp_daftar_log', [$aksi, $hasil, PER, ($hal - 1) * PER]);
$total = (int)($rows[0]['total'] ?? 0);
$jml   = max(1, (int)ceil($total / PER));
$qs    = 'aksi=' . urlencode($aksi) . '&hasil=' . urlencode($hasil);

admin_start('Log audit', 'audit');
?>
<p class="lead">Catatan ini hanya bisa ditambah, tidak bisa diubah atau dihapus. Pilihan kandidat tidak pernah dicatat di sini.</p>
<form method="get" class="picker">
  <label for="aksi">Aksi</label>
  <select id="aksi" name="aksi">
    <option value="">Semua</option>
    <?php foreach (['CATAT_SUARA' => 'Catat suara', 'AKTIFKAN_PEMILIH' => 'Aktifkan pemilih', 'NONAKTIFKAN_PEMILIH' => 'Nonaktifkan pemilih'] as $v => $l): ?>
      <option value="<?= e($v) ?>"<?= $aksi === $v ? ' selected' : '' ?>><?= e($l) ?></option>
    <?php endforeach; ?>
  </select>
  <label for="hasil">Hasil</label>
  <select id="hasil" name="hasil">
    <option value="">Semua</option>
    <option value="BERHASIL"<?= $hasil === 'BERHASIL' ? ' selected' : '' ?>>Berhasil</option>
    <option value="GAGAL"<?= $hasil === 'GAGAL' ? ' selected' : '' ?>>Gagal</option>
  </select>
  <button class="btn sm" type="submit">Terapkan</button>
</form>

<?php if (!$rows): ?>
  <p class="empty">Tidak ada catatan yang cocok dengan filter.</p>
<?php else: ?>
<p class="muted"><?= $total ?> catatan.</p>
<div class="scroll">
<table class="tbl">
  <thead><tr><th scope="col">Waktu</th><th scope="col">Aksi</th><th scope="col">Hasil</th><th scope="col">Pemilih</th><th scope="col">Keterangan</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e(date('j M Y H:i:s', strtotime($r['waktu_log']))) ?></td>
      <td><?= e($r['aksi']) ?></td>
      <td><?= $r['hasil'] === 'BERHASIL' ? '<span class="badge live">Berhasil</span>' : '<span class="badge bad">Gagal</span>' ?></td>
      <td><?= $r['nik_samar'] ? e($r['nama_lengkap']) . '<br><code>' . e($r['nik_samar']) . '</code>' : '<span class="muted">-</span>' ?></td>
      <td><?= e((string)$r['keterangan']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<nav class="pager" aria-label="Halaman">
  <?php if ($hal > 1): ?><a class="btn ghost sm" href="<?= e(url('admin/audit.php?' . $qs . '&hal=' . ($hal - 1))) ?>">Sebelumnya</a><?php endif; ?>
  <span class="muted">Halaman <?= $hal ?> dari <?= $jml ?></span>
  <?php if ($hal < $jml): ?><a class="btn ghost sm" href="<?= e(url('admin/audit.php?' . $qs . '&hal=' . ($hal + 1))) ?>">Berikutnya</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php admin_end();
