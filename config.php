<?php
/**
 * Konfigurasi aplikasi. Sesuaikan kata sandi dengan yang Anda pakai
 * saat membuat pengguna basis data di db_pemilu.sql (bagian 3B).
 */

const DB_HOST = 'localhost';
const DB_NAME = 'db_pemilu';

// Pengguna aplikasi pemilih: hanya EXECUTE pada prosedur pemilih/publik
const DB_APP_USER = 'app_pemilu';
const DB_APP_PASS = 'BiasmillahPastiMelakukanYangBaik2026';

// Pengguna admin: EXECUTE pada prosedur pengelolaan + rekap
const DB_ADMIN_USER = 'admin_pemilu';
const DB_ADMIN_PASS = 'BiasmillahPastiMelakukanYangBaik2026';

const APP_NAME      = 'SuaraKita';
const APP_TZ        = 'Asia/Jakarta';
const MYSQL_TZ      = '+07:00';  
const IDLE_TIMEOUT  = 1200;      
const LOGIN_MAX_TRY = 5;        
const LOGIN_LOCK_S  = 60;           

// Kosongkan agar dideteksi otomatis; isi mis. '/pemilu-web' jika deteksi salah.
const BASE_URL_OVERRIDE = '';
