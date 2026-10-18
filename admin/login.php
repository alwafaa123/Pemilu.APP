<?php
require_once __DIR__ . '/../includes/layout.php';

if (admin()) {
    redirect('admin/index.php');
}

$user = '';

if (is_post()) {
    csrf_verify();
    $user = post('username');
    $pass = (string)($_POST['password'] ?? '');

    if ($sisa = throttle_locked('admin')) {
        flash('bad', "Terlalu banyak percobaan. Coba lagi dalam {$sisa} detik.");
    } else {
        try {
            [$status, $id] = proc_out(db_admin(), 'sp_login_admin', [$user, $pass], 2);
        } catch (PDOException $ex) {
            error_log('login admin: ' . $ex->getMessage());
            [$status, $id] = ['GAGAL: terjadi kesalahan sistem', null];
        }

        if (status_ok($status) && $id) {
            session_regenerate_id(true);
            throttle_reset('admin');
            $_SESSION['admin'] = ['id' => (int)$id, 'username' => $user];
            flash('ok', 'Masuk sebagai ' . $user . '.');
            redirect('admin/index.php');
        }
        throttle_fail('admin');
        flash_status($status);
    }
}

page_start('Masuk petugas');
?>
<section class="narrow">
  <h1>Masuk petugas</h1>
  <p class="lead">Khusus penyelenggara pemilihan.</p>
  <form method="post" class="form" novalidate>
    <?= csrf_field() ?>
    <label for="username">Nama pengguna</label>
    <input id="username" name="username" maxlength="50" required value="<?= e($user) ?>" autocomplete="username">
    <label for="password">Kata sandi</label>
    <input id="password" name="password" type="password" required autocomplete="current-password">
    <button class="btn" type="submit">Masuk</button>
  </form>
</section>
<?php page_end();
