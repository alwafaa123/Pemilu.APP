<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_post()) {
    csrf_verify();
    unset($_SESSION['pemilih']);
    session_regenerate_id(true);
    flash('ok', 'Anda sudah keluar.');
}
redirect('index.php');
