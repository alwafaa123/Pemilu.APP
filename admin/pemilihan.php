<?php
require_once __DIR__ . '/../includes/layout.php';
require_admin();
$db = db_admin();

if (is_post()) {
    csrf_verify();
    $aksi = post('aksi');

    if ($aksi === 'buat') {
        $mulai   = dt_local_to_sql(post('mulai'));
        $selesai = dt_local_to_sql(post('selesai'));
        if (!$mulai || !$selesai) {
            flash('bad', 'Isi waktu mulai dan waktu selesai dengan benar.');
        } else {
            try {
                [$st] = proc_out($db, 'sp_buat_pemilihan', [post('nama'), $mulai, $selesai], 2);
            } catch (PDOException $ex) {
                error_log($ex->getMessage());
                $st = 'GAGAL: terjadi kesalahan sistem';
            }
            flash_status($st);
        }
    } elseif ($aksi === 'status') {
        $baru = post('baru');
        if (!in_array($baru, ['BERLANGSUNG', 'SELESAI'], true)) {
            flash('bad', 'Status tujuan tidak valid.');
        } else {
            flash_status(proc_status($db, 'sp_ubah_status_pemilihan', [(int)post('id'), $baru]));
        }
    } elseif ($aksi === 'hapus') {
        flash_status(proc_status($db, 'sp_hapus_pemilihan_draft', [(int)post('id')]));
    }
    redirect('admin/pemilihan.php');
}

$pem = proc_rows($db, 'sp_daftar_pemilihan_admin');
admin_start('Pemilihan', 'pemilihan');
?>
<section class="panel">
  <h2>Buat pemilihan baru</h2>
  <form method="post" class="form row-form">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="buat">
    <div class="f-wide">
      <label for="nama">Nama pemilihan</label>
      <input id="nama" name="nama" maxlength="150" required placeholder="Contoh: Pemilihan Ketua BEM 2026">
    </div>
    <div>
      <label for="mulai">Mulai</label>
      <input id="mulai" name="mulai" type="datetime-local" required>
    </div>
    <div>
      <label for="selesai">Selesai</label>
      <input id="selesai" name="selesai" type="datetime-local" required>
    </div>
    <div class="f-act"><button class="btn" type="submit">Buat pemilihan</button></div>
  </form>
  <p class="hint">Pemilihan baru berstatus Draf. Tambahkan kandidat, lalu mulai pemilihan.</p>
</section>

<h2>Daftar pemilihan</h2>
<?php if (!$pem): ?>
  <p class="empty">Belum ada pemilihan.</p>
<?php else: ?>
<div class="scroll">
<table class="tbl">
  <thead><tr><th scope="col">Nama</th><th scope="col">Status</th><th scope="col">Jadwal</th><th scope="col">Kandidat</th><th scope="col">Memilih</th><th scope="col">Tindakan</th></tr></thead>
  <tbody>
  <?php foreach ($pem as $p): $pid = (int)$p['id_pemilihan']; ?>
    <tr>
      <td><?= e($p['nama_pemilihan']) ?></td>
      <td><?= status_badge($p['status'], (bool)$p['dalam_jadwal']) ?></td>
      <td><?= e(tgl($p['waktu_mulai'])) ?><br><span class="muted">sampai <?= e(tgl($p['waktu_selesai'])) ?></span></td>
      <td><?= (int)$p['jumlah_kandidat'] ?></td>
      <td><?= (int)$p['jumlah_memilih'] ?></td>
      <td>
        <?php if ($p['status'] === 'DRAFT'): ?>
          <?php if ((int)$p['jumlah_kandidat'] < 2): ?>
            <span class="muted">Tambah minimal 2 kandidat</span><br>
            <a class="textlink" href="<?= e(url('admin/kandidat.php?id=' . $pid)) ?>">Kelola kandidat</a>
          <?php else: ?>
            <form method="post" class="inline" data-confirm="Mulai pemilihan ini? Kandidat tidak dapat ditambah lagi setelah dimulai.">
              <?= csrf_field() ?><input type="hidden" name="aksi" value="status">
              <input type="hidden" name="id" value="<?= $pid ?>"><input type="hidden" name="baru" value="BERLANGSUNG">
              <button class="btn sm" type="submit">Mulai pemilihan</button>
            </form>
            <a class="textlink" href="<?= e(url('admin/kandidat.php?id=' . $pid)) ?>">Kandidat</a>
          <?php endif; ?>
          <?php if ((int)$p['jumlah_memilih'] === 0): ?>
            <form method="post" class="inline" data-confirm="Hapus pemilihan DRAFT ini beserta semua kandidatnya? Tindakan ini tidak dapat dibatalkan.">
              <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id" value="<?= $pid ?>">
              <button class="btn warnbtn sm" type="submit">Hapus DRAFT</button>
            </form>
          <?php else: ?>
            <br><span class="muted">Tidak dapat dihapus: sudah ada partisipasi.</span>
          <?php endif; ?>
        <?php elseif ($p['status'] === 'BERLANGSUNG'): ?>
          <form method="post" class="inline" data-confirm="Tutup pemilihan ini? Setelah ditutup tidak ada suara baru yang dapat masuk dan hasil dibuka untuk umum.">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="status">
            <input type="hidden" name="id" value="<?= $pid ?>"><input type="hidden" name="baru" value="SELESAI">
            <button class="btn warnbtn sm" type="submit">Tutup pemilihan</button>
          </form>
        <?php else: ?>
          <a class="textlink" href="<?= e(url('admin/rekap.php?id=' . $pid)) ?>">Lihat rekap</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php admin_end();
