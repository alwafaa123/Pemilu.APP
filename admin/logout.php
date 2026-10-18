<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_post()) {
    csrf_verify();
    unset($_SESSION['admin']);
    session_regenerate_id(true);
    flash('ok', 'Anda sudah keluar dari panel petugas.');
}
redirect('admin/login.php');
