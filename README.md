# SuaraKita — Web Sistem Pemungutan Suara Pemilu

PHP 8.1+ (PDO) + MySQL 8. Web memanggil stored procedure di database;
semua validasi suara tetap di basis data.

## Cara pasang (XAMPP / Laragon)
1. Jalankan MySQL, lalu impor berurutan:
   - `database/db_pemilu.sql`
   - `database/db_tambahan.sql` (prosedur tambahan untuk web)
   Jika database sudah pernah dipasang, impor ulang kedua file yang sudah diperbarui dalam urutan ini untuk memasang prosedur dan hak akses terbaru.
2. Salin folder ini ke `htdocs/pemilu-web`.
3. Buka `config.php`, samakan kata sandi `app_pemilu` dan `admin_pemilu` dengan yang Anda pakai di SQL.
4. Buka `http://localhost/pemilu-web/`.
   Admin awal: `admin` / `admin123` di `/admin/login.php` (ganti sebelum dipakai sungguhan).

Ekstensi PHP yang dibutuhkan: `pdo_mysql`.

## Alur uji cepat
1. Admin: Pemilihan → buat pemilihan → Kandidat → tambah minimal 2 → Mulai pemilihan.
   (Jadwal harus mencakup waktu sekarang; zona waktu WIB diatur di `config.php`.)
2. Pemilih: Daftar → Masuk → Buka surat suara → pilih → Kirim.
3. Admin: Tutup pemilihan. Hasil, buku suara, dan uji kecocokan terbuka untuk umum.

## Halaman
| Halaman | Prosedur yang dipanggil |
|---|---|
| `register.php` | `sp_registrasi_pemilih` |
| `login.php` | `sp_login_pemilih`, `sp_info_pemilih` |
| `dashboard.php`, `vote.php` | `sp_daftar_pemilihan_publik`, `sp_daftar_kandidat_publik`, `sp_status_memilih`, **`sp_catat_suara`** |
| `hasil.php` | `sp_hasil_publik` (→ `sp_rekap_suara`), `sp_statistik_partisipasi`, `sp_integritas_pemilihan` |
| `transparansi.php` | `sp_integritas_pemilihan`, `sp_buku_suara`, hitung ulang independen |
| `admin/login.php` | `sp_login_admin` |
| `admin/pemilihan.php` | `sp_buat_pemilihan`, `sp_ubah_status_pemilihan`, `sp_hapus_pemilihan_draft` |
| `admin/kandidat.php` | `sp_tambah_kandidat` |
| `admin/pemilih.php` | `sp_registrasi_pemilih`, `sp_ubah_status_pemilih`, `sp_daftar_pemilih` |
| `admin/rekap.php` | `sp_rekap_suara`, `sp_statistik_partisipasi` |
| `admin/audit.php` | `sp_daftar_log` |

## Keamanan yang diterapkan
- Dua koneksi terpisah (`app_pemilu` untuk pemilih, `admin_pemilu` untuk petugas), hanya hak EXECUTE.
- Token CSRF di semua form POST, ID sesi diperbarui saat login, cookie HttpOnly + SameSite.
- Batas percobaan login (5x, kunci 60 detik) dan sesi habis setelah 20 menit tidak aktif.
- Pesan login pemilih tidak membocorkan apakah NIK terdaftar.
- Hasil dan buku suara baru terbuka setelah pemilihan SELESAI; buku suara tanpa waktu dan diurutkan menurut kode acak.
- Admin hanya dapat menghapus pemilihan berstatus DRAFT yang belum memiliki partisipasi atau suara; kandidatnya ikut dihapus.
- Header CSP, X-Frame-Options, nosniff.
