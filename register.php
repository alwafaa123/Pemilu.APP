<?php
require_once __DIR__ . '/includes/layout.php';

if (pemilih()) {
    redirect('dashboard.php');
}

$val = ['nik' => '', 'nama' => '', 'tps' => ''];

if (is_post()) {
    csrf_verify();
    $val = ['nik' => post('nik'), 'nama' => post('nama'), 'tps' => post('tps')];
    $pass  = (string)($_POST['password'] ?? '');
    $pass2 = (string)($_POST['password2'] ?? '');

    if ($pass !== $pass2) {
        flash('bad', 'Konfirmasi kata sandi tidak sama.');
    } else {
        $st = proc_status(db_app(), 'sp_registrasi_pemilih',
            [$val['nik'], $val['nama'], $pass, $val['tps'] !== '' ? $val['tps'] : null]);
        if (flash_status($st)) {
            flash('ok', 'Silakan masuk dengan NIK dan kata sandi Anda.');
            redirect('login.php');
        }
    }
}

page_start('Daftar pemilih');
?>
<section class="narrow">
  <h1>Daftar sebagai pemilih</h1>
  <p class="lead">Satu NIK hanya dapat didaftarkan satu kali. Kata sandi disimpan dalam bentuk hash.</p>

  <form method="post" class="form" autocomplete="off" novalidate>
    <?= csrf_field() ?>
    <label for="nik">NIK (16 digit)</label>
    <input id="nik" name="nik" inputmode="numeric" pattern="[0-9]{16}" maxlength="16" required
           value="<?= e($val['nik']) ?>" data-digits>
    <small class="hint">Hanya angka, tanpa spasi.</small>

    <label for="nama">Nama lengkap</label>
    <input id="nama" name="nama" maxlength="100" required value="<?= e($val['nama']) ?>">

    <label for="tps">TPS <span class="opt">(opsional)</span></label>
    <input id="tps" name="tps" maxlength="20" value="<?= e($val['tps']) ?>" placeholder="Contoh: TPS 03">

    <label for="password">Kata sandi</label>
    <input id="password" name="password" type="password" minlength="6" required autocomplete="new-password">
    <small class="hint">Minimal 6 karakter.</small>

    <label for="password2">Ulangi kata sandi</label>
    <input id="password2" name="password2" type="password" minlength="6" required autocomplete="new-password">

    <button class="btn" type="submit">Daftar</button>
    <p class="muted">Sudah punya akun? <a class="textlink" href="<?= e(url('login.php')) ?>">Masuk</a></p>
  </form>
</section>
<?php page_end();
