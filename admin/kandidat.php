<?php
require_once __DIR__ . '/../includes/layout.php';
require_admin();
$db  = db_admin();
$pem = proc_rows($db, 'sp_daftar_pemilihan_admin');
$id  = (int)($_GET['id'] ?? $_POST['id'] ?? ($pem[0]['id_pemilihan'] ?? 0));

if (is_post()) {
    csrf_verify();
    flash_status(proc_status($db, 'sp_tambah_kandidat',
        [$id, (int)post('no_urut'), post('nama'), post('visi_misi')]));
    redirect('admin/kandidat.php?id=' . $id);
}

$cur = null;
foreach ($pem as $p) {
    if ((int)$p['id_pemilihan'] === $id) {
        $cur = $p;
    }
}
$kand = $cur ? proc_rows($db, 'sp_daftar_kandidat_admin', [$id]) : [];
$next = 1;
foreach ($kand as $k) {
    $next = max($next, (int)$k['no_urut'] + 1);
}

admin_start('Kandidat', 'kandidat');
?>
<?php if (!$pem): ?>
  <p class="empty">Buat pemilihan terlebih dahulu di menu <a class="textlink" href="<?= e(url('admin/pemilihan.php')) ?>">Pemilihan</a>.</p>
<?php else: ?>
<form method="get" class="picker">
  <label for="pil">Pemilihan</label>
  <select id="pil" name="id" data-autosubmit>
    <?php foreach ($pem as $p): ?>
      <option value="<?= (int)$p['id_pemilihan'] ?>"<?= (int)$p['id_pemilihan'] === $id ? ' selected' : '' ?>>
        <?= e($p['nama_pemilihan']) ?> (<?= e(strtolower($p['status'])) ?>)
      </option>
    <?php endforeach; ?>
  </select>
  <noscript><button class="btn sm" type="submit">Tampilkan</button></noscript>
</form>

<?php if ($cur): ?>
<?php if ($cur['status'] === 'DRAFT'): ?>
<section class="panel">
  <h2>Tambah kandidat</h2>
  <form method="post" class="form">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <div class="row-form">
      <div><label for="no_urut">Nomor urut</label>
        <input id="no_urut" name="no_urut" type="number" min="1" required value="<?= $next ?>"></div>
      <div class="f-wide"><label for="nama">Nama kandidat</label>
        <input id="nama" name="nama" maxlength="100" required></div>
    </div>
    <label for="visi">Visi dan misi</label>
    <textarea id="visi" name="visi_misi" rows="5"></textarea>
    <button class="btn" type="submit">Tambah kandidat</button>
  </form>
</section>
<?php else: ?>
  <div class="notice warn" role="status">Kandidat hanya dapat ditambahkan saat pemilihan berstatus Draf.</div>
<?php endif; ?>

<h2>Kandidat terdaftar</h2>
<?php if (!$kand): ?>
  <p class="empty">Belum ada kandidat untuk pemilihan ini.</p>
<?php else: ?>
<div class="scroll">
<table class="tbl">
  <thead><tr><th scope="col">No.</th><th scope="col">Nama</th><th scope="col">Visi dan misi</th></tr></thead>
  <tbody>
  <?php foreach ($kand as $k): ?>
    <tr><td><?= (int)$k['no_urut'] ?></td><td><?= e($k['nama_kandidat']) ?></td><td class="vm"><?= nl2br(e((string)$k['visi_misi'])) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php admin_end();
