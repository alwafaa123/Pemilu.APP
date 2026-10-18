<?php
require_once __DIR__ . '/includes/layout.php';

header('Cache-Control: no-store');

$me = require_pemilih();
$db = db_app();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$pem = $id > 0 ? proc_one($db, 'sp_detail_pemilihan_publik', [$id]) : null;
if (!$pem) {
    flash('bad', 'Pemilihan tidak ditemukan.');
    redirect('dashboard.php');
}

/* ---- Kirim suara: seluruh validasi dilakukan sp_catat_suara ---- */
if (is_post()) {
    csrf_verify();
    $idKandidat = (int)($_POST['kandidat'] ?? 0);

    if ($idKandidat <= 0) {
        flash('bad', 'Pilih salah satu kandidat sebelum mengirim suara.');
        redirect('vote.php?id=' . $id);
    }

    $status = proc_status($db, 'sp_catat_suara', [$me['nik'], $id, $idKandidat]);
    flash_status($status);
    redirect('dashboard.php#p' . $id);
}

$sm       = proc_one($db, 'sp_status_memilih', [$me['nik'], $id]) ?? [];
$sudah    = !empty($sm['sudah_memilih']);
$aktif    = $pem['status'] === 'BERLANGSUNG' && (bool)$pem['dalam_jadwal'];
$kandidat = proc_rows($db, 'sp_daftar_kandidat_publik', [$id]);

page_start($pem['nama_pemilihan'], 'pemilihan');
?>
<nav class="crumb" aria-label="Jejak"><a href="<?= e(url('dashboard.php')) ?>">Surat suara</a></nav>
<h1><?= e($pem['nama_pemilihan']) ?></h1>
<p class="muted">Ditutup <?= e(tgl($pem['waktu_selesai'])) ?></p>

<?php if ($sudah): ?>
  <div class="notice ok" role="status">Suara Anda untuk pemilihan ini sudah tercatat pada <?= e(tgl($sm['waktu_memilih'] ?? null)) ?>. Pilihan Anda dirahasiakan dan tidak dapat diubah.</div>
<?php elseif (!$aktif): ?>
  <div class="notice warn" role="status">Pemilihan ini sedang tidak menerima suara.</div>
<?php elseif (!$kandidat): ?>
  <div class="notice warn" role="status">Belum ada kandidat terdaftar.</div>
<?php else: ?>
  <p class="lead">Baca visi dan misi, lalu pilih satu kandidat. Anda akan diminta memeriksa pilihan sebelum suara dikirim.</p>

  <form method="post" id="ballot" class="ballot" data-ballot>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <fieldset>
      <legend class="sr">Surat suara</legend>
      <?php foreach ($kandidat as $k): ?>
        <label class="cand">
          <input type="radio" name="kandidat" value="<?= (int)$k['id_kandidat'] ?>"
                 data-nama="<?= e($k['nama_kandidat']) ?>" data-no="<?= (int)$k['no_urut'] ?>" required>
          <span class="cand-no"><?= (int)$k['no_urut'] ?></span>
          <span class="cand-body">
            <strong class="cand-name"><?= e($k['nama_kandidat']) ?></strong>
            <?php if (trim((string)$k['visi_misi']) !== ''): ?>
              <span class="cand-vm"><?= nl2br(e($k['visi_misi'])) ?></span>
            <?php endif; ?>
          </span>
          <span class="punch" aria-hidden="true"></span>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <div class="ballot-act">
      <button class="btn" type="button" data-review>Periksa pilihan</button>
    </div>

    <dialog class="confirm" data-dialog aria-labelledby="cf-t">
      <h2 id="cf-t">Kirim suara ini?</h2>
      <p>Anda memilih <strong data-pick></strong>.</p>
      <p class="muted">Setelah dikirim, suara tidak dapat diubah atau ditarik kembali.</p>
      <div class="actions">
        <button class="btn" type="submit">Kirim suara</button>
        <button class="btn ghost" type="button" data-cancel>Kembali</button>
      </div>
    </dialog>
  </form>
<?php endif; ?>
<?php page_end();
