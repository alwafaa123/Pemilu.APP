<?php

require_once __DIR__ . '/../config.php';

date_default_timezone_set(APP_TZ);

/* ------------------------------------------------------------------
 * Header keamanan
 * ------------------------------------------------------------------ */
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; "
     . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
     . "font-src https://fonts.gstatic.com; img-src 'self' data:; "
     . "script-src 'self'; form-action 'self'; frame-ancestors 'none'");

/* ------------------------------------------------------------------
 * Sesi
 * ------------------------------------------------------------------ */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('suarakita');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (isset($_SESSION['_last']) && time() - $_SESSION['_last'] > IDLE_TIMEOUT) {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['_flash'] = [['warn', 'Sesi berakhir karena tidak ada aktivitas. Silakan masuk kembali.']];
}
$_SESSION['_last'] = time();

/* ------------------------------------------------------------------
 * Helper umum
 * ------------------------------------------------------------------ */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(): string
{
    if (BASE_URL_OVERRIDE !== '') {
        return rtrim(BASE_URL_OVERRIDE, '/');
    }
    $root = str_replace('\\', '/', (string)realpath(__DIR__ . '/..'));
    $doc  = str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($doc !== '' && str_starts_with($root, $doc)) {
        return rtrim(substr($root, strlen($doc)), '/');
    }
    return '';
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['_flash'][] = [$type, $msg];
}

function take_flash(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

const BLN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function tgl(?string $dt, bool $jam = true): string
{
    if (!$dt) {
        return '-';
    }
    $t = strtotime($dt);
    if ($t === false) {
        return e($dt);
    }
    $s = date('j', $t) . ' ' . BLN[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
    return $jam ? $s . ', ' . date('H:i', $t) . ' WIB' : $s;
}

/** Pesan status dari prosedur: "BERHASIL: ..." / "GAGAL: ..." */
function status_ok(?string $s): bool
{
    return $s !== null && str_starts_with($s, 'BERHASIL');
}

function status_text(?string $s): string
{
    if ($s === null) {
        return 'Tidak ada respons dari basis data.';
    }
    $p = strpos($s, ': ');
    $t = $p === false ? $s : substr($s, $p + 2);
    return ucfirst($t) . '.';
}

/** Terapkan hasil prosedur sebagai pesan flash. */
function flash_status(?string $s): bool
{
    $ok = status_ok($s);
    flash($ok ? 'ok' : 'bad', status_text($s));
    return $ok;
}

function status_badge(string $status, bool $dalamJadwal = true): string
{
    return match ($status) {
        'BERLANGSUNG' => $dalamJadwal
            ? '<span class="badge live">Berlangsung</span>'
            : '<span class="badge warn">Di luar jadwal</span>',
        'SELESAI'     => '<span class="badge done">Selesai</span>',
        default       => '<span class="badge draft">Draf</span>',
    };
}

/* ------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------ */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $ok = isset($_POST['_csrf'], $_SESSION['_csrf'])
        && hash_equals($_SESSION['_csrf'], (string)$_POST['_csrf']);
    if (!$ok) {
        http_response_code(419);
        exit('Permintaan tidak valid atau kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function post(string $k): string
{
    return trim((string)($_POST[$k] ?? ''));
}

/* ------------------------------------------------------------------
 * Pembatas percobaan login (per sesi)
 * ------------------------------------------------------------------ */
function throttle_locked(string $key): int
{
    $t = $_SESSION['_thr'][$key] ?? null;
    if ($t && ($t['until'] ?? 0) > time()) {
        return $t['until'] - time();
    }
    return 0;
}

function throttle_fail(string $key): void
{
    $t = $_SESSION['_thr'][$key] ?? ['n' => 0, 'until' => 0];
    $t['n']++;
    if ($t['n'] >= LOGIN_MAX_TRY) {
        $t = ['n' => 0, 'until' => time() + LOGIN_LOCK_S];
    }
    $_SESSION['_thr'][$key] = $t;
}

function throttle_reset(string $key): void
{
    unset($_SESSION['_thr'][$key]);
}

/* ------------------------------------------------------------------
 * Basis data — setiap akses lewat STORED PROCEDURE
 * ------------------------------------------------------------------ */
function make_pdo(string $user, string $pass): PDO
{
    static $pool = [];
    if (isset($pool[$user])) {
        return $pool[$user];
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '" . MYSQL_TZ . "'",
            ]
        );
    } catch (PDOException $ex) {
        error_log('Koneksi DB gagal: ' . $ex->getMessage());
        http_response_code(503);
        exit('<!doctype html><meta charset="utf-8"><title>Layanan tidak tersedia</title>'
           . '<body style="font-family:system-ui;max-width:36rem;margin:12vh auto;padding:0 1rem">'
           . '<h1>Basis data belum dapat dihubungi</h1>'
           . '<p>Pastikan MySQL aktif, <code>db_pemilu.sql</code> dan <code>db_tambahan.sql</code> '
           . 'sudah diimpor, dan kata sandi di <code>config.php</code> sesuai.</p></body>');
    }
    return $pool[$user] = $pdo;
}

function db_app(): PDO
{
    return make_pdo(DB_APP_USER, DB_APP_PASS);
}

function db_admin(): PDO
{
    return make_pdo(DB_ADMIN_USER, DB_ADMIN_PASS);
}

/**
 * Panggil prosedur yang mengembalikan result set. Mengembalikan baris-barisnya.
 */
function proc_rows(PDO $pdo, string $name, array $params = []): array
{
    $ph = implode(',', array_fill(0, count($params), '?'));
    $st = $pdo->prepare("CALL {$name}({$ph})");
    $st->execute(array_values($params));
    $rows = $st->columnCount() > 0 ? $st->fetchAll() : [];
    while ($st->nextRowset()) {
        // kuras result set sisa (OK packet prosedur)
    }
    $st->closeCursor();
    return $rows;
}

function proc_one(PDO $pdo, string $name, array $params = []): ?array
{
    $rows = proc_rows($pdo, $name, $params);
    return $rows[0] ?? null;
}

/**
 * Panggil prosedur dengan parameter OUT. $outs = jumlah parameter OUT di akhir.
 * Mengembalikan array nilai OUT berindeks 0..n-1.
 */
function proc_out(PDO $pdo, string $name, array $params, int $outs): array
{
    $vars = [];
    for ($i = 0; $i < $outs; $i++) {
        $vars[] = '@o_' . $i;
    }
    $ph = array_fill(0, count($params), '?');
    $st = $pdo->prepare("CALL {$name}(" . implode(',', array_merge($ph, $vars)) . ')');
    $st->execute(array_values($params));
    while ($st->nextRowset()) {
    }
    $st->closeCursor();

    $sel = [];
    foreach ($vars as $i => $v) {
        $sel[] = "{$v} AS o{$i}";
    }
    $row = $pdo->query('SELECT ' . implode(',', $sel))->fetch();
    $out = [];
    for ($i = 0; $i < $outs; $i++) {
        $out[] = $row["o{$i}"] ?? null;
    }
    return $out;
}

/** Jalankan prosedur dengan satu OUT p_status; kembalikan string status. */
function proc_status(PDO $pdo, string $name, array $params): string
{
    try {
        return (string)(proc_out($pdo, $name, $params, 1)[0] ?? 'GAGAL: tidak ada respons');
    } catch (PDOException $ex) {
        error_log("{$name}: " . $ex->getMessage());
        return 'GAGAL: terjadi kesalahan sistem';
    }
}

/* ------------------------------------------------------------------
 * Autentikasi
 * ------------------------------------------------------------------ */
function pemilih(): ?array
{
    return $_SESSION['pemilih'] ?? null;
}

function require_pemilih(): array
{
    $p = pemilih();
    if (!$p) {
        flash('warn', 'Masuk terlebih dahulu untuk melanjutkan.');
        redirect('login.php');
    }
    return $p;
}

function admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function require_admin(): array
{
    $a = admin();
    if (!$a) {
        flash('warn', 'Masuk sebagai admin terlebih dahulu.');
        redirect('admin/login.php');
    }
    return $a;
}

/** Ubah datetime-local ("2026-10-20T08:00") menjadi format DATETIME MySQL. */
function dt_local_to_sql(string $v): ?string
{
    $v = str_replace('T', ' ', trim($v));
    $t = strtotime($v);
    return $t === false ? null : date('Y-m-d H:i:s', $t);
}

function nik_samar(string $nik): string
{
    return strlen($nik) === 16 ? substr($nik, 0, 4) . '********' . substr($nik, -4) : $nik;
}
