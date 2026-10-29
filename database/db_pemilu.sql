
CREATE DATABASE IF NOT EXISTS db_pemilu
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_pemilu;

CREATE TABLE IF NOT EXISTS admin (
  id_admin      INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  peran         VARCHAR(20)  NOT NULL DEFAULT 'ADMIN'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pemilih (
  id_pemilih    INT AUTO_INCREMENT PRIMARY KEY,
  nik           CHAR(16)     NOT NULL UNIQUE,
  nama_lengkap  VARCHAR(100) NOT NULL,
  password_hash CHAR(64)     NOT NULL,          -- hasil SHA2(...,256)
  tps           VARCHAR(20),
  status_aktif  TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pemilihan (
  id_pemilihan   INT AUTO_INCREMENT PRIMARY KEY,
  nama_pemilihan VARCHAR(150) NOT NULL,
  waktu_mulai    DATETIME     NOT NULL,
  waktu_selesai  DATETIME     NOT NULL,
  status         ENUM('DRAFT','BERLANGSUNG','SELESAI') NOT NULL DEFAULT 'DRAFT',
  CONSTRAINT chk_waktu CHECK (waktu_selesai > waktu_mulai)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kandidat (
  id_kandidat   INT AUTO_INCREMENT PRIMARY KEY,
  id_pemilihan  INT          NOT NULL,
  no_urut       INT          NOT NULL,
  nama_kandidat VARCHAR(100) NOT NULL,
  visi_misi     TEXT,
  UNIQUE KEY uq_no_urut (id_pemilihan, no_urut),
  UNIQUE KEY uq_kandidat_pemilihan (id_kandidat, id_pemilihan),
  CONSTRAINT fk_kandidat_pemilihan
    FOREIGN KEY (id_pemilihan) REFERENCES pemilihan(id_pemilihan)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 5. Partisipasi (siapa sudah memilih, TANPA pilihannya)
CREATE TABLE IF NOT EXISTS partisipasi (
  id_partisipasi INT AUTO_INCREMENT PRIMARY KEY,
  id_pemilih     INT      NOT NULL,
  id_pemilihan   INT      NOT NULL,
  waktu_memilih  DATETIME NOT NULL,
  UNIQUE KEY uq_satu_suara (id_pemilih, id_pemilihan),
  CONSTRAINT fk_partisipasi_pemilih
    FOREIGN KEY (id_pemilih) REFERENCES pemilih(id_pemilih)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_partisipasi_pemilihan
    FOREIGN KEY (id_pemilihan) REFERENCES pemilihan(id_pemilihan)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS suara (
  id_suara        INT AUTO_INCREMENT PRIMARY KEY,
  id_pemilihan    INT      NOT NULL,
  id_kandidat     INT      NOT NULL,
  waktu_suara     DATETIME NOT NULL,
  kode_verifikasi CHAR(64) NOT NULL UNIQUE,
  CONSTRAINT fk_suara_pemilihan
    FOREIGN KEY (id_pemilihan) REFERENCES pemilihan(id_pemilihan)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_suara_kandidat
    FOREIGN KEY (id_kandidat, id_pemilihan)
    REFERENCES kandidat(id_kandidat, id_pemilihan)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS log_audit (
  id_log      INT AUTO_INCREMENT PRIMARY KEY,
  id_pemilih  INT NULL,
  aksi        VARCHAR(50)  NOT NULL,
  hasil       VARCHAR(20),
  keterangan  VARCHAR(255),
  waktu_log   DATETIME(6)  NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_log_pemilih
    FOREIGN KEY (id_pemilih) REFERENCES pemilih(id_pemilih)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_registrasi_pemilih$$
CREATE PROCEDURE sp_registrasi_pemilih (
  IN  p_nik      CHAR(16),
  IN  p_nama     VARCHAR(100),
  IN  p_password VARCHAR(255),
  IN  p_tps      VARCHAR(20),
  OUT p_status   VARCHAR(100)
)
BEGIN
  DECLARE v_ada INT DEFAULT 0;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    SET p_status = 'GAGAL: terjadi kesalahan sistem';
  END;

  IF p_nik NOT REGEXP '^[0-9]{16}$' THEN
    SET p_status = 'GAGAL: format NIK harus 16 digit angka';
  ELSEIF p_nama IS NULL OR TRIM(p_nama) = '' THEN
    SET p_status = 'GAGAL: nama lengkap wajib diisi';
  ELSEIF CHAR_LENGTH(p_password) < 6 THEN
    SET p_status = 'GAGAL: kata sandi minimal 6 karakter';
  ELSE
    SELECT COUNT(*) INTO v_ada FROM pemilih WHERE nik = p_nik;
    IF v_ada > 0 THEN
      SET p_status = 'GAGAL: NIK sudah terdaftar';
    ELSE
      INSERT INTO pemilih (nik, nama_lengkap, password_hash, tps, status_aktif)
      VALUES (p_nik, p_nama, SHA2(p_password, 256), p_tps, 1);
      SET p_status = 'BERHASIL: pemilih berhasil didaftarkan';
    END IF;
  END IF;
END$$

DROP PROCEDURE IF EXISTS sp_login_pemilih$$
CREATE PROCEDURE sp_login_pemilih (
  IN  p_nik        CHAR(16),
  IN  p_password   VARCHAR(255),
  OUT p_status     VARCHAR(100),
  OUT p_id_pemilih INT
)
BEGIN
  DECLARE v_hash   CHAR(64);
  DECLARE v_aktif  TINYINT(1);
  DECLARE v_id     INT;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    SET p_status = 'GAGAL: terjadi kesalahan sistem';
    SET p_id_pemilih = NULL;
  END;

  SET p_id_pemilih = NULL;

  SELECT id_pemilih, password_hash, status_aktif
    INTO v_id, v_hash, v_aktif
    FROM pemilih
   WHERE nik = p_nik;

  IF v_id IS NULL THEN
    SET p_status = 'GAGAL: NIK tidak terdaftar';
  ELSEIF v_aktif = 0 THEN
    SET p_status = 'GAGAL: akun pemilih tidak aktif';
  ELSEIF v_hash <> SHA2(p_password, 256) THEN
    SET p_status = 'GAGAL: kata sandi salah';
  ELSE
    SET p_status = 'BERHASIL: login berhasil';
    SET p_id_pemilih = v_id;
  END IF;
END$$

DROP PROCEDURE IF EXISTS sp_buat_pemilihan$$
CREATE PROCEDURE sp_buat_pemilihan (
  IN  p_nama          VARCHAR(150),
  IN  p_waktu_mulai   DATETIME,
  IN  p_waktu_selesai DATETIME,
  OUT p_status        VARCHAR(100),
  OUT p_id_pemilihan  INT
)
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    SET p_status = 'GAGAL: terjadi kesalahan sistem';
    SET p_id_pemilihan = NULL;
  END;

  SET p_id_pemilihan = NULL;

  IF p_nama IS NULL OR TRIM(p_nama) = '' THEN
    SET p_status = 'GAGAL: nama pemilihan wajib diisi';
  ELSEIF p_waktu_selesai <= p_waktu_mulai THEN
    SET p_status = 'GAGAL: waktu selesai harus setelah waktu mulai';
  ELSE
    INSERT INTO pemilihan (nama_pemilihan, waktu_mulai, waktu_selesai, status)
    VALUES (p_nama, p_waktu_mulai, p_waktu_selesai, 'DRAFT');
    SET p_id_pemilihan = LAST_INSERT_ID();
    SET p_status = 'BERHASIL: pemilihan dibuat dengan status DRAFT';
  END IF;
END$$

DROP PROCEDURE IF EXISTS sp_tambah_kandidat$$
CREATE PROCEDURE sp_tambah_kandidat (
  IN  p_id_pemilihan INT,
  IN  p_no_urut      INT,
  IN  p_nama         VARCHAR(100),
  IN  p_visi_misi    TEXT,
  OUT p_status       VARCHAR(100)
)
BEGIN
  DECLARE v_status_pemilihan VARCHAR(15);
  DECLARE v_no_ada INT DEFAULT 0;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    SET p_status = 'GAGAL: terjadi kesalahan sistem (mungkin nomor urut sudah dipakai)';
  END;

  SELECT status INTO v_status_pemilihan
    FROM pemilihan
   WHERE id_pemilihan = p_id_pemilihan;

  IF v_status_pemilihan IS NULL THEN
    SET p_status = 'GAGAL: pemilihan tidak ditemukan';
  ELSEIF v_status_pemilihan <> 'DRAFT' THEN
    SET p_status = 'GAGAL: kandidat hanya dapat ditambahkan saat pemilihan berstatus DRAFT';
  ELSEIF p_no_urut IS NULL OR p_no_urut < 1 THEN
    SET p_status = 'GAGAL: nomor urut harus bilangan positif';
  ELSEIF p_nama IS NULL OR TRIM(p_nama) = '' THEN
    SET p_status = 'GAGAL: nama kandidat wajib diisi';
  ELSE
    SELECT COUNT(*) INTO v_no_ada
      FROM kandidat
     WHERE id_pemilihan = p_id_pemilihan AND no_urut = p_no_urut;
    IF v_no_ada > 0 THEN
      SET p_status = 'GAGAL: nomor urut sudah dipakai pada pemilihan ini';
    ELSE
      INSERT INTO kandidat (id_pemilihan, no_urut, nama_kandidat, visi_misi)
      VALUES (p_id_pemilihan, p_no_urut, p_nama, p_visi_misi);
      SET p_status = 'BERHASIL: kandidat berhasil ditambahkan';
    END IF;
  END IF;
END$$

DROP PROCEDURE IF EXISTS sp_catat_suara$$
CREATE PROCEDURE sp_catat_suara (
  IN  p_nik          CHAR(16),
  IN  p_id_pemilihan INT,
  IN  p_id_kandidat  INT,
  OUT p_status       VARCHAR(100)
)
BEGIN
  DECLARE v_id_pemilih INT DEFAULT NULL;
  DECLARE v_status     VARCHAR(15) DEFAULT NULL;
  DECLARE v_kandidat   INT DEFAULT 0;
  DECLARE v_sudah      INT DEFAULT 0;
  DECLARE v_ok         TINYINT(1) DEFAULT 0;
  DECLARE v_ket        VARCHAR(200) DEFAULT '';

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    INSERT INTO log_audit (id_pemilih, aksi, hasil, keterangan)
    VALUES (v_id_pemilih, 'CATAT_SUARA', 'GAGAL', 'kesalahan sistem');
    SET p_status = 'GAGAL: terjadi kesalahan sistem';
  END;

  START TRANSACTION;

  SELECT id_pemilih INTO v_id_pemilih
    FROM pemilih
   WHERE nik = p_nik AND status_aktif = 1
   FOR UPDATE;

  SELECT status INTO v_status
    FROM pemilihan
   WHERE id_pemilihan = p_id_pemilihan
     AND NOW() BETWEEN waktu_mulai AND waktu_selesai;

  SELECT COUNT(*) INTO v_kandidat
    FROM kandidat
   WHERE id_kandidat = p_id_kandidat
     AND id_pemilihan = p_id_pemilihan;

  SELECT COUNT(*) INTO v_sudah
    FROM partisipasi
   WHERE id_pemilih = v_id_pemilih
     AND id_pemilihan = p_id_pemilihan;

  IF v_id_pemilih IS NULL THEN
    SET v_ket = 'pemilih tidak terdaftar atau tidak aktif';
  ELSEIF v_status IS NULL OR v_status <> 'BERLANGSUNG' THEN
    SET v_ket = 'pemilihan tidak sedang berlangsung';
  ELSEIF v_kandidat = 0 THEN
    SET v_ket = 'kandidat tidak valid untuk pemilihan ini';
  ELSEIF v_sudah > 0 THEN
    SET v_ket = 'pemilih sudah menggunakan hak pilih';
  ELSE
    SET v_ok = 1;
  END IF;

  IF v_ok = 1 THEN
    INSERT INTO partisipasi (id_pemilih, id_pemilihan, waktu_memilih)
    VALUES (v_id_pemilih, p_id_pemilihan, NOW());

    INSERT INTO suara (id_pemilihan, id_kandidat, waktu_suara, kode_verifikasi)
    VALUES (p_id_pemilihan, p_id_kandidat, NOW(),
            SHA2(CONCAT(UUID(), p_id_pemilihan), 256));

    INSERT INTO log_audit (id_pemilih, aksi, hasil, keterangan)
    VALUES (v_id_pemilih, 'CATAT_SUARA', 'BERHASIL', CONCAT('pemilihan ', p_id_pemilihan));

    COMMIT;
    SET p_status = 'BERHASIL: suara Anda telah tercatat';
  ELSE
    ROLLBACK;
    INSERT INTO log_audit (id_pemilih, aksi, hasil, keterangan)
    VALUES (v_id_pemilih, 'CATAT_SUARA', 'GAGAL', v_ket);
    SET p_status = CONCAT('GAGAL: ', v_ket);
  END IF;
END$$

DROP PROCEDURE IF EXISTS sp_rekap_suara$$
CREATE PROCEDURE sp_rekap_suara (
  IN p_id_pemilihan INT
)
BEGIN
  DECLARE v_total INT DEFAULT 0;

  SELECT COUNT(*) INTO v_total
    FROM suara
   WHERE id_pemilihan = p_id_pemilihan;

  SELECT k.no_urut,
         k.nama_kandidat,
         COUNT(s.id_suara) AS jumlah_suara,
         IF(v_total = 0, 0, ROUND(COUNT(s.id_suara) * 100 / v_total, 2)) AS persentase
    FROM kandidat k
    LEFT JOIN suara s ON s.id_kandidat = k.id_kandidat
                     AND s.id_pemilihan = k.id_pemilihan
   WHERE k.id_pemilihan = p_id_pemilihan
   GROUP BY k.id_kandidat, k.no_urut, k.nama_kandidat
   ORDER BY k.no_urut;
END$$

DROP PROCEDURE IF EXISTS sp_statistik_partisipasi$$
CREATE PROCEDURE sp_statistik_partisipasi (
  IN  p_id_pemilihan INT,
  OUT p_terdaftar    INT,
  OUT p_memilih      INT,
  OUT p_persen       DECIMAL(5,2)
)
BEGIN
  SELECT COUNT(*) INTO p_terdaftar
    FROM pemilih
   WHERE status_aktif = 1;

  SELECT COUNT(*) INTO p_memilih
    FROM partisipasi
   WHERE id_pemilihan = p_id_pemilihan;

  SET p_persen = IF(p_terdaftar = 0, 0,
                    ROUND(p_memilih * 100 / p_terdaftar, 2));
END$$

DELIMITER ;

DROP PROCEDURE IF EXISTS sp_ubah_status_pemilihan$$
CREATE PROCEDURE sp_ubah_status_pemilihan (
  IN  p_id_pemilihan INT,
  IN  p_status_baru  VARCHAR(15),
  OUT p_status       VARCHAR(100)
)
BEGIN
  DECLARE v_status_lama VARCHAR(15);

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET p_status = 'GAGAL: terjadi kesalahan sistem';
  END;

  START TRANSACTION;

  SELECT status INTO v_status_lama
    FROM pemilihan
   WHERE id_pemilihan = p_id_pemilihan
   FOR UPDATE;

  IF v_status_lama IS NULL THEN
    SET p_status = 'GAGAL: pemilihan tidak ditemukan';
  ELSEIF NOT (
       (v_status_lama = 'DRAFT' AND p_status_baru = 'BERLANGSUNG')
    OR (v_status_lama = 'BERLANGSUNG' AND p_status_baru = 'SELESAI')
  ) THEN
    SET p_status = CONCAT('GAGAL: perpindahan status dari ', v_status_lama, ' ke ', p_status_baru, ' tidak diizinkan');
  ELSE
    UPDATE pemilihan SET status = p_status_baru WHERE id_pemilihan = p_id_pemilihan;
    SET p_status = CONCAT('BERHASIL: status pemilihan menjadi ', p_status_baru);
  END IF;

  COMMIT;
END$$

DELIMITER ;

DELIMITER $$

DROP PROCEDURE IF EXISTS sp_login_admin$$
CREATE PROCEDURE sp_login_admin (
  IN  p_username   VARCHAR(50),
  IN  p_password   VARCHAR(255),
  OUT p_status     VARCHAR(100),
  OUT p_id_admin   INT
)
BEGIN
  DECLARE v_id   INT DEFAULT NULL;
  DECLARE v_hash CHAR(64) DEFAULT NULL;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    SET p_status = 'GAGAL: terjadi kesalahan sistem';
    SET p_id_admin = NULL;
  END;

  SET p_id_admin = NULL;

  SELECT id_admin, password_hash INTO v_id, v_hash
    FROM admin
   WHERE username = p_username;

  IF v_id IS NULL OR v_hash <> SHA2(p_password, 256) THEN
    SET p_status = 'GAGAL: username atau kata sandi salah';
  ELSE
    SET p_status = 'BERHASIL: login admin berhasil';
    SET p_id_admin = v_id;
  END IF;
END$$

DROP PROCEDURE IF EXISTS sp_ubah_status_pemilih$$
CREATE PROCEDURE sp_ubah_status_pemilih (
  IN  p_id_admin INT,
  IN  p_nik      CHAR(16),
  IN  p_aktif    TINYINT(1),
  OUT p_status   VARCHAR(100)
)
BEGIN
  DECLARE v_admin       INT DEFAULT NULL;
  DECLARE v_id_pemilih  INT DEFAULT NULL;
  DECLARE v_aktif_lama  TINYINT(1) DEFAULT NULL;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    SET p_status = 'GAGAL: terjadi kesalahan sistem';
  END;

  SELECT id_admin INTO v_admin
    FROM admin
   WHERE id_admin = p_id_admin;

  SELECT id_pemilih, status_aktif INTO v_id_pemilih, v_aktif_lama
    FROM pemilih
   WHERE nik = p_nik;

  IF v_admin IS NULL THEN
    SET p_status = 'GAGAL: admin tidak valid';
  ELSEIF v_id_pemilih IS NULL THEN
    SET p_status = 'GAGAL: pemilih tidak ditemukan';
  ELSEIF p_aktif NOT IN (0, 1) THEN
    SET p_status = 'GAGAL: nilai status harus 0 (nonaktif) atau 1 (aktif)';
  ELSEIF v_aktif_lama = p_aktif THEN
    SET p_status = CONCAT('GAGAL: status pemilih sudah ',
                          IF(p_aktif = 1, 'aktif', 'nonaktif'));
  ELSE
    UPDATE pemilih
       SET status_aktif = p_aktif
     WHERE id_pemilih = v_id_pemilih;

    INSERT INTO log_audit (id_pemilih, aksi, hasil, keterangan)
    VALUES (v_id_pemilih,
            IF(p_aktif = 1, 'AKTIFKAN_PEMILIH', 'NONAKTIFKAN_PEMILIH'),
            'BERHASIL',
            CONCAT('dilakukan oleh admin ID ', p_id_admin));

    SET p_status = CONCAT('BERHASIL: status pemilih diubah menjadi ',
                          IF(p_aktif = 1, 'aktif', 'nonaktif'));
  END IF;
END$$

DELIMITER ;

CREATE USER IF NOT EXISTS 'app_pemilu'@'localhost'
  IDENTIFIED BY 'GantiDenganKataSandiKuat!2026';
CREATE USER IF NOT EXISTS 'admin_pemilu'@'localhost'
  IDENTIFIED BY 'GantiDenganKataSandiAdmin!2026';

-- Pengguna aplikasi pemilih: hanya boleh menjalankan prosedur pemilih
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_registrasi_pemilih TO 'app_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_login_pemilih      TO 'app_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_catat_suara        TO 'app_pemilu'@'localhost';

-- Pengguna admin: prosedur pengelolaan dan rekapitulasi, serta membaca log
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_buat_pemilihan        TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_tambah_kandidat       TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_ubah_status_pemilihan TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_rekap_suara           TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_statistik_partisipasi TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_registrasi_pemilih    TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_login_admin           TO 'admin_pemilu'@'localhost';
GRANT EXECUTE ON PROCEDURE db_pemilu.sp_ubah_status_pemilih   TO 'admin_pemilu'@'localhost';
GRANT SELECT ON db_pemilu.log_audit TO 'admin_pemilu'@'localhost';

FLUSH PRIVILEGES;

-- ---------------------------------------------------------------------
-- BAGIAN 3C. PROTEKSI APPEND-ONLY (data suara dan log tidak dapat diubah/dihapus)
-- Catatan: aksi ON DELETE SET NULL pada log_audit (saat pemilih dihapus)
-- tidak memicu trigger di MySQL, sehingga riwayat log tetap terjaga.
-- ---------------------------------------------------------------------
DELIMITER $$

DROP TRIGGER IF EXISTS trg_suara_no_update$$
CREATE TRIGGER trg_suara_no_update BEFORE UPDATE ON suara FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Data suara bersifat append-only dan tidak dapat diubah';
END$$

DROP TRIGGER IF EXISTS trg_suara_no_delete$$
CREATE TRIGGER trg_suara_no_delete BEFORE DELETE ON suara FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Data suara bersifat append-only dan tidak dapat dihapus';
END$$

DROP TRIGGER IF EXISTS trg_partisipasi_no_update$$
CREATE TRIGGER trg_partisipasi_no_update BEFORE UPDATE ON partisipasi FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Data partisipasi bersifat append-only dan tidak dapat diubah';
END$$

DROP TRIGGER IF EXISTS trg_partisipasi_no_delete$$
CREATE TRIGGER trg_partisipasi_no_delete BEFORE DELETE ON partisipasi FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Data partisipasi bersifat append-only dan tidak dapat dihapus';
END$$

DROP TRIGGER IF EXISTS trg_log_no_update$$
CREATE TRIGGER trg_log_no_update BEFORE UPDATE ON log_audit FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Log audit bersifat append-only dan tidak dapat diubah';
END$$

DROP TRIGGER IF EXISTS trg_log_no_delete$$
CREATE TRIGGER trg_log_no_delete BEFORE DELETE ON log_audit FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Log audit bersifat append-only dan tidak dapat dihapus';
END$$

DELIMITER ;


INSERT IGNORE INTO admin (username, password_hash, peran)
VALUES ('admin', SHA2('admin123', 256), 'ADMIN');
