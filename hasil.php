<?php
require_once __DIR__ . '/includes/layout.php';

$db = db_app();
$daftar = proc_rows($db, 'sp_daftar_pemilihan_publik');
$id = (int)($_GET['id'] ?? 0);

$pem = null;
foreach ($daftar as $d) {
    if ($id === 0 && $d['status'] === 'SELESAI') {
        $id = (int)$d['id_pemilihan'];
    }
    if ((int)$d['id_pemilihan'] === $id) {
        $pem = $d;
    }
}

$hasil = [];
$stat  = null;
$integ = null;
if ($pem && $pem['status'] === 'SELESAI') {
    $hasil = proc_rows($db, 'sp_hasil_publik', [$id]);
    [$terdaftar, $memilih, $persen] = proc_out($db, 'sp_statistik_partisipasi', [$id], 3);
    $stat  = ['terdaftar' => (int)$terdaftar, 'memilih' => (int)$memilih, 'persen' => (float)$persen];
    $integ = proc_one($db, 'sp_integritas_pemilihan', [$id]);
}

$maks = 0;
foreach ($hasil as $h) {
    $maks = max($maks, (int)$h['jumlah_suara']);
}

page_start('Hasil pemilihan', 'hasil');
?>
<h1>Hasil pemilihan</h1>
<p class="lead">Hasil dibuka setelah pemilihan ditutup, supaya tidak ada yang bisa memengaruhi pilihan orang lain selama pemungutan berlangsung.</p>

<?php if (!$daftar): ?>
  <p class="empty">Belum ada pemilihan.</p>
<?php else: ?>
  <form method="get" class="picker">
    <label for="pil">Pemilihan</label>
    <select id="pil" name="id" data-autosubmit>
      <?php foreach ($daftar as $d): ?>
        <option value="<?= (int)$d['id_pemilihan'] ?>"<?= (int)$d['id_pemilihan'] === $id ? ' selected' : '' ?>>
          <?= e($d['nama_pemilihan']) ?> (<?= $d['status'] === 'SELESAI' ? 'selesai' : 'berlangsung' ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn sm" type="submit">Tampilkan</button></noscript>
  </form>

  <?php if ($pem && $pem['status'] !== 'SELESAI'): ?>
    <div class="notice warn" role="status">
      Pemilihan <strong><?= e($pem['nama_pemilihan']) ?></strong> masih berlangsung. Hasil akan tampil setelah ditutup pada <?= e(tgl($pem['waktu_selesai'])) ?>.
      Sejauh ini <?= (int)$pem['jumlah_memilih'] ?> pemilih sudah menggunakan hak pilihnya.
    </div>
  <?php elseif ($pem): ?>
    <section class="result" aria-labelledby="rt">
      <h2 id="rt"><?= e($pem['nama_pemilihan']) ?></h2>

      <?php if ($stat): ?>
      <p class="muted">Partisipasi: <?= $stat['memilih'] ?> dari <?= $stat['terdaftar'] ?> pemilih aktif (<?= number_format($stat['persen'], 2, ',', '.') ?>%).</p>
      <?php endif; ?>

      <ol class="rows">
      <?php foreach ($hasil as $h):
          $w = $maks > 0 ? round((int)$h['jumlah_suara'] * 100 / $maks, 1) : 0;
          $menang = $maks > 0 && (int)$h['jumlah_suara'] === $maks;
      ?>
        <li class="row<?= $menang ? ' lead-row' : '' ?>">
          <span class="row-no"><?= (int)$h['no_urut'] ?></span>
          <div class="row-body">
            <div class="row-top">
              <strong><?= e($h['nama_kandidat']) ?></strong>
              <span><?= number_format((int)$h['jumlah_suara'], 0, ',', '.') ?> suara
                <span class="muted">(<?= number_format((float)$h['persentase'], 2, ',', '.') ?>%)</span></span>
            </div>
            <div class="meter big"><span style="width:<?= $w ?>%"></span></div>
          </div>
        </li>
      <?php endforeach; ?>
      </ol>

      <?php if ($integ): ?>
        <p class="<?= !empty($integ['konsisten']) ? 'okline' : 'badline' ?>">
          <?= !empty($integ['konsisten'])
              ? 'Uji kecocokan lolos: ' . (int)$integ['jumlah_suara'] . ' suara = ' . (int)$integ['jumlah_partisipasi'] . ' partisipasi, semua kode verifikasi unik.'
              : 'Uji kecocokan GAGAL: jumlah suara dan partisipasi berbeda. Hubungi penyelenggara.' ?>
          <a class="textlink" href="<?= e(url('transparansi.php?id=' . $id)) ?>">Periksa buku suara</a>
        </p>
      <?php endif; ?>
    </section>
  <?php endif; ?>
<?php endif; ?>
<?php page_end();
