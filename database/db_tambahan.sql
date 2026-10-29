
USE db_pemilu;
DELIMITER $$

-- ---------------------------------------------------------------------
-- A. PROSEDUR PUBLIK / PEMILIH  (untuk app_pemilu)
-- ---------------------------------------------------------------------

-- Ringkasan angka untuk beranda
DROP PROCEDURE IF EXISTS sp_ringkasan_publik$$
CREATE PROCEDURE sp_ringkasan_publik ()
BEGIN
  SELECT (SELECT COUNT(*) FROM pemilih WHERE status_aktif = 1)               AS pemilih_aktif,
         (SELECT COUNT(*) FROM pemilihan WHERE status <> 'DRAFT')            AS jumlah_pemilihan,
         (SELECT COUNT(*) FROM pemilihan WHERE status = 'BERLANGSUNG')       AS sedang_berlangsung,
         (SELECT COUNT(*) FROM suara)                                        AS total_suara;
END$$

-- Daftar pemilihan yang boleh dilihat publik (DRAFT disembunyikan)
DROP PROCEDURE IF EXISTS sp_daftar_pemilihan_publik$$
CREATE PROCEDURE sp_daftar_pemilihan_publik ()
BEGIN
  SELECT p.id_pemilihan, p.nama_pemilihan, p.waktu_mulai, p.waktu_selesai, p.status,
         (NOW() BETWEEN p.waktu_mulai AND p.waktu_selesai) AS dalam_jadwal,
         (SELECT COUNT(*) FROM kandidat k   WHERE k.id_pemilihan = p.id_pemilihan) AS jumlah_kandidat,
         (SELECT COUNT(*) FROM partisipasi t WHERE t.id_pemilihan = p.id_pemilihan) AS jumlah_memilih
    FROM pemilihan p
   WHERE p.status <> 'DRAFT'
   ORDER BY FIELD(p.status, 'BERLANGSUNG', 'SELESAI'), p.waktu_mulai DESC;
END$$

DROP PROCEDURE IF EXISTS sp_detail_pemilihan_publik$$
CREATE PROCEDURE sp_detail_pemilihan_publik (IN p_id INT)
BEGIN
  SELECT p.id_pemilihan, p.nama_pemilihan, p.waktu_mulai, p.waktu_selesai, p.status,
         (NOW() BETWEEN p.waktu_mulai AND p.waktu_selesai) AS dalam_jadwal,
         (SELECT COUNT(*) FROM kandidat k   WHERE k.id_pemilihan = p.id_pemilihan) AS jumlah_kandidat,
         (SELECT COUNT(*) FROM partisipasi t WHERE t.id_pemilihan = p.id_pemilihan) AS jumlah_memilih
    FROM pemilihan p
   WHERE p.id_pemilihan = p_id AND p.status <> 'DRAFT';
END$$

DROP PROCEDURE IF EXISTS sp_daftar_kandidat_publik$$
CREATE PROCEDURE sp_daftar_kandidat_publik (IN p_id INT)
BEGIN
  SELECT k.id_kandidat, k.no_urut, k.nama_kandidat, k.visi_misi
    FROM kandidat k
    JOIN pemilihan p ON p.id_pemilihan = k.id_pemilihan
   WHERE k.id_pemilihan = p_id AND p.status <> 'DRAFT'
   ORDER BY k.no_urut;
END$$

-- Profil pemilih yang sedang login (tanpa hash kata sandi)
DROP PROCEDURE IF EXISTS sp_info_pemilih$$
CREATE PROCEDURE sp_info_pemilih (IN p_id_pemilih INT)
BEGIN
  SELECT id_pemilih, nik, nama_lengkap, tps, status_aktif
    FROM pemilih
   WHERE id_pemilih = p_id_pemilih;
END$$

-- Apakah pemilih sudah memakai hak pilih? (tanpa membuka pilihannya)
DROP PROCEDURE IF EXISTS sp_status_memilih$$
CREATE PROCEDURE sp_status_memilih (IN p_nik CHAR(16), IN p_id_pemilihan INT)
BEGIN
  SELECT COUNT(*) > 0 AS sudah_memilih,
         MAX(t.waktu_memilih) AS waktu_memilih
    FROM partisipasi t
    JOIN pemilih m ON m.id_pemilih = t.id_pemilih
   WHERE m.nik = p_nik AND t.id_pemilihan = p_id_pemilihan;
END$$

-- Hasil akhir: hanya terbuka setelah pemilihan SELESAI
DROP PROCEDURE IF EXISTS sp_hasil_publik$$
CREATE PROCEDURE sp_hasil_publik (IN p_id INT)
BEGIN
  DECLARE v_status VARCHAR(15) DEFAULT NULL;
  SELECT status INTO v_status FROM pemilihan WHERE id_pemilihan = p_id;
  IF v_status = 'SELESAI' THEN
    CALL sp_rekap_suara(p_id);
  END IF;
END$$

DROP PROCEDURE IF EXISTS sp_buku_suara$$
CREATE PROCEDURE sp_buku_suara (IN p_id INT, IN p_limit INT, IN p_offset INT)
BEGIN
  DECLARE v_status VARCHAR(15) DEFAULT NULL;
  SELECT status INTO v_status FROM pemilihan WHERE id_pemilihan = p_id;
  IF v_status = 'SELESAI' THEN
    SELECT s.kode_verifikasi, k.no_urut, k.nama_kandidat
      FROM suara s
      JOIN kandidat k ON k.id_kandidat = s.id_kandidat AND k.id_pemilihan = s.id_pemilihan
     WHERE s.id_pemilihan = p_id
     ORDER BY s.kode_verifikasi
     LIMIT p_limit OFFSET p_offset;
  END IF;
END$$

-- Uji kecocokan: jumlah suara harus sama dengan jumlah partisipasi
DROP PROCEDURE IF EXISTS sp_integritas_pemilihan$$
CREATE PROCEDURE sp_integritas_pemilihan (IN p_id INT)
BEGIN
  DECLARE v_suara INT DEFAULT 0;
  DECLARE v_part  INT DEFAULT 0;
  DECLARE v_unik  INT DEFAULT 0;
  SELECT COUNT(*), COUNT(DISTINCT kode_verifikasi) INTO v_suara, v_unik
    FROM suara WHERE id_pemilihan = p_id;
  SELECT COUNT(*) INTO v_part FROM partisipasi WHERE id_pemilihan = p_id;
  SELECT v_suara AS jumlah_suara,
         v_part  AS jumlah_partisipasi,
         v_unik  AS kode_unik,
         (v_suara = v_part AND v_suara = v_unik) AS konsisten;
END$$

-- ---------------------------------------------------------------------
-- B. PROSEDUR ADMIN  (untuk admin_pemilu)
-- ---------------------------------------------------------------------

DROP PROCEDURE IF EXISTS sp_ringkasan_admin$$
CREATE PROCEDURE sp_ringkasan_admin ()
BEGIN
  SELECT (SELECT COUNT(*) FROM pemilih)                                         AS total_pemilih,
         (SELECT COUNT(*) FROM pemilih WHERE status_aktif = 1)                  AS pemilih_aktif,
         (SELECT COUNT(*) FROM pemilihan)                                       AS total_pemilihan,
         (SELECT COUNT(*) FROM pemilihan WHERE status = 'BERLANGSUNG')          AS sedang_berlangsung,
         (SELECT COUNT(*) FROM suara)                                           AS total_suara,
         (SELECT COUNT(*) FROM log_audit WHERE hasil = 'GAGAL')                 AS percobaan_gagal;
END$$

DROP PROCEDURE IF EXISTS sp_daftar_pemilihan_admin$$
CREATE PROCEDURE sp_daftar_pemilihan_admin ()
BEGIN
  SELECT p.id_pemilihan, p.nama_pemilihan, p.waktu_mulai, p.waktu_selesai, p.status,
         (NOW() BETWEEN p.waktu_mulai AND p.waktu_selesai) AS dalam_jadwal,
         (SELECT COUNT(*) FROM kandidat k   WHERE k.id_pemilihan = p.id_pemilihan) AS jumlah_kandidat,
         (SELECT COUNT(*) FROM partisipasi t WHERE t.id_pemilihan = p.id_pemilihan) AS jumlah_memilih
    FROM pemilihan p
   ORDER BY FIELD(p.status, 'BERLANGSUNG', 'DRAFT', 'SELESAI'), p.waktu_mulai DESC;
END$$

DROP PROCEDURE IF EXISTS sp_daftar_kandidat_admin$$
CREATE PROCEDURE sp_daftar_kandidat_admin (IN p_id INT)
BEGIN
  SELECT id_kandidat, no_urut, nama_kandidat, visi_misi
    FROM kandidat
   WHERE id_pemilihan = p_id
   ORDER BY no_urut;
END$$

-- Daftar pemilih (tanpa hash kata sandi), dengan pencarian dan halaman
DROP PROCEDURE IF EXISTS sp_daftar_pemilih$$
CREATE PROCEDURE sp_daftar_pemilih (IN p_cari VARCHAR(100), IN p_limit INT, IN p_offset INT)
BEGIN
  SELECT id_pemilih, nik, nama_lengkap, tps, status_aktif,
         COUNT(*) OVER () AS total
    FROM pemilih
   WHERE p_cari IS NULL OR p_cari = ''
      OR nik LIKE CONCAT('%', p_cari, '%')
      OR nama_lengkap LIKE CONCAT('%', p_cari, '%')
      OR tps LIKE CONCAT('%', p_cari, '%')
   ORDER BY nama_lengkap
   LIMIT p_limit OFFSET p_offset;
END$$

-- Jejak audit; NIK disamarkan, pilihan kandidat memang tidak pernah dicatat
DROP PROCEDURE IF EXISTS sp_daftar_log$$
CREATE PROCEDURE sp_daftar_log (IN p_aksi VARCHAR(50), IN p_hasil VARCHAR(20),
                                IN p_limit INT, IN p_offset INT)
BEGIN
  SELECT l.id_log, l.aksi, l.hasil, l.keterangan, l.waktu_log,
         IF(m.nik IS NULL, NULL, CONCAT(LEFT(m.nik, 4), '********', RIGHT(m.nik, 4))) AS nik_samar,
         m.nama_lengkap,
         COUNT(*) OVER () AS total
    FROM log_audit l
    LEFT JOIN pemilih m ON m.id_pemilih = l.id_pemilih
   WHERE (p_aksi  IS NULL OR p_aksi  = '' OR l.aksi  = p_aksi)
     AND (p_hasil IS NULL OR p_hasil = '' OR l.hasil = p_hasil)
   ORDER BY l.id_log DESC
   LIMIT p_limit OFFSET p_offset;
END$$

-- Hapus hanya pemilihan DRAFT yang belum memiliki partisipasi atau suara.
-- Kandidat pada pemilihan tersebut ikut dihapus dalam transaksi yang sama.
DROP PROCEDURE IF EXISTS sp_hapus_pemilihan_draft$$
CREATE PROCEDURE sp_hapus_pemilihan_draft (
  IN  p_id_pemilihan INT,
  OUT p_status       VARCHAR(150)
)
BEGIN
  DECLARE v_status VARCHAR(15) DEFAULT NULL;
  DECLARE v_partisipasi INT DEFAULT 0;
  DECLARE v_suara INT DEFAULT 0;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET p_status = 'GAGAL: pemilihan tidak dapat dihapus';
  END;

  START TRANSACTION;

  SELECT status INTO v_status
    FROM pemilihan
   WHERE id_pemilihan = p_id_pemilihan
   FOR UPDATE;

  IF v_status IS NULL THEN
    SET p_status = 'GAGAL: pemilihan tidak ditemukan';
  ELSEIF v_status <> 'DRAFT' THEN
    SET p_status = 'GAGAL: hanya pemilihan DRAFT yang dapat dihapus';
  ELSE
    SELECT COUNT(*) INTO v_partisipasi
      FROM partisipasi
     WHERE id_pemilihan = p_id_pemilihan;

    SELECT COUNT(*) INTO v_suara
      FROM suara
     WHERE id_pemilihan = p_id_pemilihan;

    IF v_partisipasi > 0 OR v_suara > 0 THEN
      SET p_status = 'GAGAL: pemilihan yang memiliki partisipasi atau suara tidak dapat dihapus';
    ELSE
      DELETE FROM kandidat WHERE id_pemilihan = p_id_pemilihan;
      DELETE FROM pemilihan WHERE id_pemilihan = p_id_pemilihan;
      SET p_status = 'BERHASIL: pemilihan DRAFT dan kandidatnya telah dihapus';
    END IF;
  END IF;

  COMMIT;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- C. HAK AKSES — tetap hanya EXECUTE, tanpa INSERT/UPDATE/DELETE
-- ---------------------------------------------------------------------
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_ringkasan_publik          TO 'app_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_daftar_pemilihan_publik   TO 'app_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_detail_pemilihan_publik   TO 'app_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_daftar_kandidat_publik    TO 'app_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_info_pemilih              TO 'app_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_status_memilih            TO 'app_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_hasil_publik              TO 'app_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_buku_suara                TO 'app_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_integritas_pemilihan      TO 'app_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_statistik_partisipasi     TO 'app_pemilu'@'localhost';

GRANT EXECUTE ON PROCEDURE db_pemilu.sp_ringkasan_admin           TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_daftar_pemilihan_admin    TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_daftar_kandidat_admin     TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_daftar_pemilih            TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_daftar_log                TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_integritas_pemilihan      TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_buku_suara                TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_hapus_pemilihan_draft     TO 'admin_pemilu'@'localhost';

FLUSH PRIVILEGES;
