<?php
require_once __DIR__ . '/../includes/layout.php';
require_admin();
$db  = db_admin();
$pem = proc_rows($db, 'sp_daftar_pemilihan_admin');
$id  = (int)($_GET['id'] ?? ($pem[0]['id_pemilihan'] ?? 0));

$cur = null;
foreach ($pem as $p) {
    if ((int)$p['id_pemilihan'] === $id) {
        $cur = $p;
    }
}

$rekap = $stat = $integ = null;
if ($cur) {
    $rekap = proc_rows($db, 'sp_rekap_suara', [$id]);
    [$terdaftar, $memilih, $persen] = proc_out($db, 'sp_statistik_partisipasi', [$id], 3);
    $stat  = ['terdaftar' => (int)$terdaftar, 'memilih' => (int)$memilih, 'persen' => (float)$persen];
    $integ = proc_one($db, 'sp_integritas_pemilihan', [$id]);
}
$maks = 0;
foreach ($rekap ?? [] as $r) {
    $maks = max($maks, (int)$r['jumlah_suara']);
}

admin_start('Rekapitulasi', 'rekap');
?>
<?php if (!$pem): ?>
  <p class="empty">Belum ada pemilihan.</p>
<?php else: ?>
<form method="get" class="picker">
  <label for="pil">Pemilihan</label>
  <select id="pil" name="id" data-autosubmit>
    <?php foreach ($pem as $p): ?>
      <option value="<?= (int)$p['id_pemilihan'] ?>"<?= (int)$p['id_pemilihan'] === $id ? ' selected' : '' ?>><?= e($p['nama_pemilihan']) ?> (<?= e(strtolower($p['status'])) ?>)</option>
    <?php endforeach; ?>
  </select>
  <noscript><button class="btn sm" type="submit">Tampilkan</button></noscript>
</form>

<?php if ($cur && $cur['status'] !== 'SELESAI'): ?>
  <div class="notice warn" role="status">Pemilihan belum ditutup. Angka di bawah hanya untuk petugas dan belum dipublikasikan.</div>
<?php endif; ?>

<?php if ($stat): ?>
<dl class="kpis">
  <div><dt>Pemilih aktif</dt><dd><?= $stat['terdaftar'] ?></dd></div>
  <div><dt>Sudah memilih</dt><dd><?= $stat['memilih'] ?></dd></div>
  <div><dt>Partisipasi</dt><dd><?= number_format($stat['persen'], 2, ',', '.') ?>%</dd></div>
</dl>
<?php endif; ?>

<h2>Perolehan suara</h2>
<?php if (!$rekap): ?>
  <p class="empty">Belum ada kandidat.</p>
<?php else: ?>
<ol class="rows">
<?php foreach ($rekap as $h):
    $w = $maks > 0 ? round((int)$h['jumlah_suara'] * 100 / $maks, 1) : 0; ?>
  <li class="row">
    <span class="row-no"><?= (int)$h['no_urut'] ?></span>
    <div class="row-body">
      <div class="row-top"><strong><?= e($h['nama_kandidat']) ?></strong>
        <span><?= (int)$h['jumlah_suara'] ?> suara <span class="muted">(<?= number_format((float)$h['persentase'], 2, ',', '.') ?>%)</span></span></div>
      <div class="meter big"><span style="width:<?= $w ?>%"></span></div>
    </div>
  </li>
<?php endforeach; ?>
</ol>
<?php endif; ?>

<?php if ($integ): ?>
<p class="<?= !empty($integ['konsisten']) ? 'okline' : 'badline' ?>">
  <?= !empty($integ['konsisten'])
      ? 'Uji kecocokan lolos: ' . (int)$integ['jumlah_suara'] . ' suara sama dengan ' . (int)$integ['jumlah_partisipasi'] . ' partisipasi.'
      : 'Uji kecocokan GAGAL: ' . (int)$integ['jumlah_suara'] . ' suara berbanding ' . (int)$integ['jumlah_partisipasi'] . ' partisipasi.' ?>
</p>
<?php endif; ?>
<?php endif; ?>
<?php admin_end();
