<?php
require_once __DIR__ . '/../includes/layout.php';
require_admin();

$db  = db_admin();
$r   = proc_one($db, 'sp_ringkasan_admin') ?? [];
$pem = proc_rows($db, 'sp_daftar_pemilihan_admin');

admin_start('Ringkasan', 'ringkasan');
?>
<dl class="kpis">
  <div><dt>Pemilih terdaftar</dt><dd><?= (int)($r['total_pemilih'] ?? 0) ?></dd><span class="muted"><?= (int)($r['pemilih_aktif'] ?? 0) ?> aktif</span></div>
  <div><dt>Pemilihan</dt><dd><?= (int)($r['total_pemilihan'] ?? 0) ?></dd><span class="muted"><?= (int)($r['sedang_berlangsung'] ?? 0) ?> berlangsung</span></div>
  <div><dt>Suara tercatat</dt><dd><?= (int)($r['total_suara'] ?? 0) ?></dd></div>
  <div><dt>Percobaan gagal</dt><dd><?= (int)($r['percobaan_gagal'] ?? 0) ?></dd><span class="muted">di log audit</span></div>
</dl>

<h2>Pemilihan</h2>
<?php if (!$pem): ?>
  <p class="empty">Belum ada pemilihan. <a class="textlink" href="<?= e(url('admin/pemilihan.php')) ?>">Buat pemilihan pertama</a>.</p>
<?php else: ?>
<div class="scroll">
<table class="tbl">
  <thead><tr><th scope="col">Nama</th><th scope="col">Status</th><th scope="col">Jadwal</th><th scope="col">Kandidat</th><th scope="col">Memilih</th></tr></thead>
  <tbody>
  <?php foreach ($pem as $p): ?>
    <tr>
      <td><?= e($p['nama_pemilihan']) ?></td>
      <td><?= status_badge($p['status'], (bool)$p['dalam_jadwal']) ?></td>
      <td><?= e(tgl($p['waktu_mulai'])) ?><br><span class="muted">sampai <?= e(tgl($p['waktu_selesai'])) ?></span></td>
      <td><?= (int)$p['jumlah_kandidat'] ?></td>
      <td><?= (int)$p['jumlah_memilih'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php admin_end();
