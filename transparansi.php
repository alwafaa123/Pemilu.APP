<?php
require_once __DIR__ . '/includes/layout.php';

const PER_HAL = 50;
const BATAS_HITUNG_ULANG = 20000;

$db = db_app();
$daftar = proc_rows($db, 'sp_daftar_pemilihan_publik');
$id = (int)($_GET['id'] ?? 0);

$pem = null;
foreach ($daftar as $d) {
    if ($id === 0) {
        $id = (int)$d['id_pemilihan'];
    }
    if ((int)$d['id_pemilihan'] === $id) {
        $pem = $d;
    }
}

$integ = $pem ? proc_one($db, 'sp_integritas_pemilihan', [$id]) : null;
$selesai = $pem && $pem['status'] === 'SELESAI';

$hal    = max(1, (int)($_GET['hal'] ?? 1));
$buku   = [];
$jmlHal = 1;
$hitung = null;     // hasil hitung ulang independen
$cocok  = null;

if ($selesai && $integ) {
    $total  = (int)$integ['jumlah_suara'];
    $jmlHal = max(1, (int)ceil($total / PER_HAL));
    $hal    = min($hal, $jmlHal);
    $buku   = proc_rows($db, 'sp_buku_suara', [$id, PER_HAL, ($hal - 1) * PER_HAL]);

    // Hitung ulang sendiri dari buku suara, lalu bandingkan dengan rekap resmi
    if ($total > 0 && $total <= BATAS_HITUNG_ULANG) {
        $hitung = [];
        for ($off = 0; $off < $total; $off += 1000) {
            foreach (proc_rows($db, 'sp_buku_suara', [$id, 1000, $off]) as $r) {
                $k = (int)$r['no_urut'];
                $hitung[$k] = ($hitung[$k] ?? 0) + 1;
            }
        }
        ksort($hitung);
        $resmi = [];
        foreach (proc_rows($db, 'sp_hasil_publik', [$id]) as $r) {
            if ((int)$r['jumlah_suara'] > 0) {
                $resmi[(int)$r['no_urut']] = (int)$r['jumlah_suara'];
            }
        }
        ksort($resmi);
        $cocok = ($resmi === $hitung);
    }
}

page_start('Transparansi', 'transparan');
?>
<h1>Transparansi dan audit</h1>
<p class="lead">Siapa pun dapat memeriksa bahwa jumlah suara sama dengan jumlah pemilih yang berpartisipasi, dan menghitung ulang hasil dari buku suara. Identitas pemilih tidak pernah ada di sini.</p>

<?php if (!$daftar): ?>
  <p class="empty">Belum ada pemilihan yang dapat diperiksa.</p>
<?php else: ?>
  <form method="get" class="picker">
    <label for="pil">Pemilihan</label>
    <select id="pil" name="id" data-autosubmit>
      <?php foreach ($daftar as $d): ?>
        <option value="<?= (int)$d['id_pemilihan'] ?>"<?= (int)$d['id_pemilihan'] === $id ? ' selected' : '' ?>>
          <?= e($d['nama_pemilihan']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <noscript><button class="btn sm" type="submit">Tampilkan</button></noscript>
  </form>

  <?php if ($integ): ?>
  <section class="check" aria-labelledby="c1">
    <h2 id="c1">Uji kecocokan</h2>
    <table class="tbl">
      <tbody>
        <tr><th scope="row">Jumlah suara tersimpan</th><td><?= (int)$integ['jumlah_suara'] ?></td></tr>
        <tr><th scope="row">Jumlah pemilih yang berpartisipasi</th><td><?= (int)$integ['jumlah_partisipasi'] ?></td></tr>
        <tr><th scope="row">Kode verifikasi unik</th><td><?= (int)$integ['kode_unik'] ?></td></tr>
      </tbody>
    </table>
    <p class="<?= !empty($integ['konsisten']) ? 'okline' : 'badline' ?>">
      <?= !empty($integ['konsisten'])
          ? 'Lolos: tidak ada suara tanpa pemilih, dan tidak ada pemilih dengan suara ganda.'
          : 'Tidak cocok: ada selisih antara suara dan partisipasi.' ?>
    </p>
  </section>

  <?php if (!$selesai): ?>
    <div class="notice warn" role="status">Buku suara dan hitung ulang dibuka setelah pemilihan ditutup, agar hasil sementara tidak bocor.</div>
  <?php else: ?>

    <?php if ($hitung !== null): ?>
    <section class="check" aria-labelledby="c2">
      <h2 id="c2">Hitung ulang dari buku suara</h2>
      <table class="tbl">
        <thead><tr><th scope="col">No. urut</th><th scope="col">Suara (hitung ulang)</th></tr></thead>
        <tbody>
        <?php foreach ($hitung as $no => $n): ?>
          <tr><td><?= (int)$no ?></td><td><?= (int)$n ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="<?= $cocok ? 'okline' : 'badline' ?>">
        <?= $cocok ? 'Hasil hitung ulang sama dengan rekapitulasi resmi.' : 'Hasil hitung ulang berbeda dari rekapitulasi resmi.' ?>
      </p>
    </section>
    <?php endif; ?>

    <section class="check" aria-labelledby="c3">
      <h2 id="c3">Buku suara</h2>
      <p class="muted">Diurutkan menurut kode verifikasi, bukan urutan masuk, dan tanpa waktu, sehingga tidak dapat dicocokkan dengan siapa pun.</p>
      <?php if (!$buku): ?>
        <p class="empty">Tidak ada suara tercatat.</p>
      <?php else: ?>
      <div class="scroll">
        <table class="tbl">
          <thead><tr><th scope="col">Kode verifikasi</th><th scope="col">Pilihan</th></tr></thead>
          <tbody>
          <?php foreach ($buku as $b): ?>
            <tr>
              <td><code class="hash"><?= e($b['kode_verifikasi']) ?></code></td>
              <td><?= (int)$b['no_urut'] ?>. <?= e($b['nama_kandidat']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <nav class="pager" aria-label="Halaman buku suara">
        <?php if ($hal > 1): ?><a class="btn ghost sm" href="<?= e(url("transparansi.php?id={$id}&hal=" . ($hal - 1))) ?>">Sebelumnya</a><?php endif; ?>
        <span class="muted">Halaman <?= $hal ?> dari <?= $jmlHal ?></span>
        <?php if ($hal < $jmlHal): ?><a class="btn ghost sm" href="<?= e(url("transparansi.php?id={$id}&hal=" . ($hal + 1))) ?>">Berikutnya</a><?php endif; ?>
      </nav>
      <?php endif; ?>
    </section>
  <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>
<?php page_end();
