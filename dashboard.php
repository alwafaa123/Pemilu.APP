<?php
require_once __DIR__ . '/includes/layout.php';

$me = require_pemilih();
$db = db_app();
$daftar = proc_rows($db, 'sp_daftar_pemilihan_publik');

page_start('Surat suara', 'pemilihan');
?>
<h1>Halo, <?= e($me['nama']) ?></h1>
<p class="lead">Pilih pemilihan di bawah ini. Setiap pemilihan hanya dapat Anda coblos satu kali.</p>

<?php if (!$daftar): ?>
  <p class="empty">Belum ada pemilihan yang dibuka. Kembali lagi setelah petugas mengumumkan jadwalnya.</p>
<?php endif; ?>

<div class="elist">
<?php foreach ($daftar as $d):
    $id    = (int)$d['id_pemilihan'];
    $aktif = $d['status'] === 'BERLANGSUNG' && (bool)$d['dalam_jadwal'];
    $sm    = proc_one($db, 'sp_status_memilih', [$me['nik'], $id]) ?? [];
    $sudah = !empty($sm['sudah_memilih']);
?>
  <article class="eitem" id="p<?= $id ?>">
    <div class="eitem-main">
      <div class="plist-head">
        <h2><?= e($d['nama_pemilihan']) ?></h2>
        <?= status_badge($d['status'], (bool)$d['dalam_jadwal']) ?>
      </div>
      <p class="muted">Dibuka <?= e(tgl($d['waktu_mulai'])) ?>, ditutup <?= e(tgl($d['waktu_selesai'])) ?></p>
      <p class="muted"><?= (int)$d['jumlah_kandidat'] ?> kandidat. Partisipasi sejauh ini: <?= (int)$d['jumlah_memilih'] ?> pemilih.</p>
    </div>
    <div class="eitem-act">
      <?php if ($sudah): ?>
        <p class="done-mark">Suara Anda sudah tercatat<br><span class="muted"><?= e(tgl($sm['waktu_memilih'] ?? null)) ?></span></p>
      <?php elseif ($aktif): ?>
        <a class="btn" href="<?= e(url('vote.php?id=' . $id)) ?>">Buka surat suara</a>
      <?php elseif ($d['status'] === 'SELESAI'): ?>
        <p class="muted">Pemilihan ditutup.</p>
      <?php else: ?>
        <p class="muted">Belum dalam jadwal pemilihan.</p>
      <?php endif; ?>
      <?php if ($d['status'] === 'SELESAI'): ?>
        <a class="textlink" href="<?= e(url('hasil.php?id=' . $id)) ?>">Lihat hasil</a>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>
<?php page_end();
