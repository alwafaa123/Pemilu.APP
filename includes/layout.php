<?php
require_once __DIR__ . '/bootstrap.php';

function logo_svg(): string
{
    return '<svg class="logo" viewBox="0 0 32 32" width="30" height="30" aria-hidden="true">'
         . '<rect x="3" y="2" width="22" height="28" rx="2" fill="#fff" stroke="currentColor" stroke-width="2"/>'
         . '<path d="M8 9h12M8 14h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>'
         . '<circle cx="22" cy="22" r="7" fill="#2A1B7B"/><circle cx="22" cy="22" r="2.6" fill="#F5F6F9"/></svg>';
}

function head_html(string $title): void
{
    ?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> — <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<?php
}

function flash_html(): void
{
    foreach (take_flash() as [$type, $msg]) {
        $role = $type === 'bad' ? 'alert' : 'status';
        echo '<div class="notice ' . e($type) . '" role="' . $role . '">' . e($msg) . '</div>';
    }
}

/* ------------------------------------------------------------------
 * Layout publik / pemilih
 * ------------------------------------------------------------------ */
function page_start(string $title, string $active = ''): void
{
    head_html($title);
    $p = pemilih();
    $nav = [
        'beranda'    => ['index.php', 'Beranda'],
        'pemilihan'  => ['dashboard.php', 'Surat suara'],
        'hasil'      => ['hasil.php', 'Hasil'],
        'transparan' => ['transparansi.php', 'Transparansi'],
    ];
    ?>
<body class="site">
<a class="skip" href="#isi">Lewati ke konten</a>
<header class="topbar">
  <div class="wrap bar">
    <a class="brand" href="<?= e(url('index.php')) ?>"><?= logo_svg() ?><span><?= e(APP_NAME) ?></span></a>
    <button class="burger" type="button" aria-expanded="false" aria-controls="menu" data-burger>Menu</button>
    <nav id="menu" class="menu" aria-label="Navigasi utama">
      <?php foreach ($nav as $k => [$href, $label]): ?>
        <a href="<?= e(url($href)) ?>"<?= $active === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
      <span class="menu-end">
      <?php if ($p): ?>
        <span class="who"><?= e($p['nama']) ?></span>
        <form method="post" action="<?= e(url('logout.php')) ?>" class="inline">
          <?= csrf_field() ?><button class="btn ghost sm" type="submit">Keluar</button>
        </form>
      <?php else: ?>
        <a class="btn ghost sm" href="<?= e(url('login.php')) ?>">Masuk</a>
        <a class="btn sm" href="<?= e(url('register.php')) ?>">Daftar pemilih</a>
      <?php endif; ?>
      </span>
    </nav>
  </div>
</header>
<main id="isi" class="wrap main">
<?php
    flash_html();
}

function page_end(): void
{
    ?>
</main>
<footer class="foot">
  <div class="wrap foot-in">
    <div>
      <strong><?= e(APP_NAME) ?></strong>
      <p>Prototipe akademik, Sistem Pemungutan Suara Pemilu. Aturan validasi dijalankan oleh stored procedure MySQL, bukan oleh halaman web.</p>
    </div>
    <div>
      <a href="<?= e(url('transparansi.php')) ?>">Periksa integritas suara</a><br>
      <a href="<?= e(url('admin/login.php')) ?>">Masuk petugas</a>
    </div>
  </div>
</footer>
<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
<?php
}

/* ------------------------------------------------------------------
 * Layout admin
 * ------------------------------------------------------------------ */
function admin_start(string $title, string $active = ''): void
{
    head_html($title);
    $a = require_admin();
    $nav = [
        'ringkasan' => ['admin/index.php', 'Ringkasan'],
        'pemilihan' => ['admin/pemilihan.php', 'Pemilihan'],
        'kandidat'  => ['admin/kandidat.php', 'Kandidat'],
        'pemilih'   => ['admin/pemilih.php', 'Pemilih'],
        'rekap'     => ['admin/rekap.php', 'Rekapitulasi'],
        'audit'     => ['admin/audit.php', 'Log audit'],
    ];
    ?>
<body class="adm">
<a class="skip" href="#isi">Lewati ke konten</a>
<div class="adm-grid">
  <aside class="side">
    <a class="brand" href="<?= e(url('admin/index.php')) ?>"><?= logo_svg() ?><span><?= e(APP_NAME) ?></span></a>
    <p class="side-sub">Panel petugas</p>
    <nav aria-label="Menu admin">
      <?php foreach ($nav as $k => [$href, $label]): ?>
        <a href="<?= e(url($href)) ?>"<?= $active === $k ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <span class="who"><?= e($a['username']) ?></span>
      <a href="<?= e(url('index.php')) ?>">Lihat situs publik</a>
      <form method="post" action="<?= e(url('admin/logout.php')) ?>">
        <?= csrf_field() ?><button class="btn ghost sm" type="submit">Keluar</button>
      </form>
    </div>
  </aside>
  <main id="isi" class="adm-main">
    <h1 class="adm-title"><?= e($title) ?></h1>
<?php
    flash_html();
}

function admin_end(): void
{
    ?>
  </main>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>" defer></script>
</body>
</html>
<?php
}

/** Bilah partisipasi sederhana */
function bar(int $part, int $total): string
{
    $pct = $total > 0 ? min(100, round($part * 100 / $total, 1)) : 0;
    return '<div class="meter" role="img" aria-label="Partisipasi ' . $pct . ' persen">'
         . '<span style="width:' . $pct . '%"></span></div>';
}
