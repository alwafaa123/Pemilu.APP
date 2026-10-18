<?php
require_once __DIR__ . '/includes/layout.php';

$db = db_app();
$ring = proc_one($db, 'sp_ringkasan_publik') ?? [];
$daftar = proc_rows($db, 'sp_daftar_pemilihan_publik');

page_start('Beranda', 'beranda');
?>
<section class="hero">
  <div class="hero-text">
    <h1>Satu suara, tercatat sekali, dan bisa diperiksa siapa saja.</h1>
    <p class="lead">Pemungutan suara yang rahasia bagi pemilih dan terbuka bagi pengawas. Setiap aturan dijaga langsung oleh basis data, jadi tidak ada halaman web yang dapat melewatinya.</p>
    <div class="actions">
      <?php if (pemilih()): ?>
        <a class="btn" href="<?= e(url('dashboard.php')) ?>">Buka surat suara</a>
      <?php else: ?>
        <a class="btn" href="<?= e(url('login.php')) ?>">Masuk untuk memilih</a>
        <a class="btn ghost" href="<?= e(url('register.php')) ?>">Daftar sebagai pemilih</a>
      <?php endif; ?>
    </div>
    <dl class="facts">
      <div><dt>Pemilih aktif</dt><dd><?= number_format((int)($ring['pemilih_aktif'] ?? 0), 0, ',', '.') ?></dd></div>
      <div><dt>Suara tercatat</dt><dd><?= number_format((int)($ring['total_suara'] ?? 0), 0, ',', '.') ?></dd></div>
      <div><dt>Sedang berlangsung</dt><dd><?= (int)($ring['sedang_berlangsung'] ?? 0) ?> pemilihan</dd></div>
    </dl>
  </div>

  <aside class="hero-panel" aria-label="Daftar pemilihan">
    <h2>Pemilihan saat ini</h2>
    <?php if (!$daftar): ?>
      <p class="empty">Belum ada pemilihan yang dibuka. Petugas akan mengumumkan jadwalnya di sini.</p>
    <?php else: ?>
      <ul class="plist">
      <?php foreach (array_slice($daftar, 0, 4) as $d): ?>
        <li>
          <div class="plist-head">
            <strong><?= e($d['nama_pemilihan']) ?></strong>
            <?= status_badge($d['status'], (bool)$d['dalam_jadwal']) ?>
          </div>
          <p class="muted"><?= e(tgl($d['waktu_mulai'])) ?> sampai <?= e(tgl($d['waktu_selesai'])) ?></p>
          <p class="muted"><?= (int)$d['jumlah_kandidat'] ?> kandidat, <?= (int)$d['jumlah_memilih'] ?> suara masuk</p>
          <a class="textlink" href="<?= e(url($d['status'] === 'SELESAI' ? 'hasil.php?id=' : 'dashboard.php#p') . $d['id_pemilihan']) ?>">
            <?= $d['status'] === 'SELESAI' ? 'Lihat hasil' : 'Lihat pemilihan' ?>
          </a>
        </li>
      <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </aside>
</section>

<section class="guarantees">
  <h2>Bagaimana suara Anda dijaga</h2>
  <div class="gl">
    <div class="g">
      <h3>Rahasia</h3>
      <p>Siapa yang sudah memilih disimpan di satu tabel, pilihannya di tabel lain. Tidak ada kolom yang menghubungkan keduanya.</p>
    </div>
    <div class="g">
      <h3>Sekali saja</h3>
      <p>Baris pemilih dikunci selama transaksi berjalan, dan batasan UNIQUE menutup celah klik ganda atau permintaan serentak.</p>
    </div>
    <div class="g">
      <h3>Tidak bisa diubah</h3>
      <p>Tabel suara, partisipasi, dan log audit bersifat append-only. Upaya mengubah atau menghapus ditolak oleh trigger.</p>
    </div>
    <div class="g">
      <h3>Bisa diaudit</h3>
      <p>Setiap percobaan memilih, berhasil atau gagal, tercatat. Setelah pemilihan selesai, buku suara dan uji kecocokan terbuka untuk umum.</p>
    </div>
  </div>
</section>
<?php page_end();
