<?php
require_once __DIR__ . '/includes/layout.php';

if (pemilih()) {
    redirect('dashboard.php');
}

$nik = '';

if (is_post()) {
    csrf_verify();
    $nik  = post('nik');
    $pass = (string)($_POST['password'] ?? '');

    if ($sisa = throttle_locked('pemilih')) {
        flash('bad', "Terlalu banyak percobaan. Coba lagi dalam {$sisa} detik.");
    } else {
        $db = db_app();
        try {
            [$status, $id] = proc_out($db, 'sp_login_pemilih', [$nik, $pass], 2);
        } catch (PDOException $ex) {
            error_log('login: ' . $ex->getMessage());
            [$status, $id] = ['GAGAL: terjadi kesalahan sistem', null];
        }

        if (status_ok($status) && $id) {
            $info = proc_one($db, 'sp_info_pemilih', [(int)$id]);
            session_regenerate_id(true);
            throttle_reset('pemilih');
            $_SESSION['pemilih'] = [
                'id'   => (int)$id,
                'nik'  => $nik,
                'nama' => $info['nama_lengkap'] ?? 'Pemilih',
                'tps'  => $info['tps'] ?? null,
            ];
            flash('ok', 'Selamat datang, ' . ($info['nama_lengkap'] ?? 'Pemilih') . '.');
            redirect('dashboard.php');
        }

        throttle_fail('pemilih');
        // Jangan membocorkan apakah NIK terdaftar
        if (str_contains((string)$status, 'tidak terdaftar') || str_contains((string)$status, 'sandi salah')) {
            flash('bad', 'NIK atau kata sandi salah.');
        } else {
            flash_status($status);
        }
    }
}

page_start('Masuk');
?>
<section class="narrow">
  <h1>Masuk untuk memilih</h1>
  <p class="lead">Gunakan NIK dan kata sandi yang Anda daftarkan.</p>

  <form method="post" class="form" novalidate>
    <?= csrf_field() ?>
    <label for="nik">NIK</label>
    <input id="nik" name="nik" inputmode="numeric" pattern="[0-9]{16}" maxlength="16" required
           value="<?= e($nik) ?>" autocomplete="username" data-digits>

    <label for="password">Kata sandi</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">

    <button class="btn" type="submit">Masuk</button>
    <p class="muted">Belum terdaftar? <a class="textlink" href="<?= e(url('register.php')) ?>">Daftar sebagai pemilih</a></p>
  </form>
</section>
<?php page_end();
