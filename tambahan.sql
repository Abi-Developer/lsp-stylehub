-- =====================================================================
-- StyleHub - Script tambahan (jalankan SETELAH db_stylehub.sql di-import)
-- Isi: constraint, data awal, function, stored procedure, trigger
-- Aman dijalankan ulang.
-- Cara: phpMyAdmin > pilih db_stylehub > tab Import > pilih file ini.
-- =====================================================================

USE `db_stylehub`;

-- ---------------------------------------------------------------------
-- 1. CONSTRAINT (aturan bisnis: harga/stok tidak boleh negatif)
-- ---------------------------------------------------------------------
ALTER TABLE `produk` DROP CONSTRAINT IF EXISTS `chk_produk_nilai`;
ALTER TABLE `produk`
  ADD CONSTRAINT `chk_produk_nilai`
  CHECK (`harga_beli` >= 0 AND `harga_jual` >= 0 AND `stok` >= 0);

ALTER TABLE `detail_penjualan` DROP CONSTRAINT IF EXISTS `chk_detail_nilai`;
ALTER TABLE `detail_penjualan`
  ADD CONSTRAINT `chk_detail_nilai`
  CHECK (`harga_jual` >= 0 AND `jumlah` > 0 AND `diskon_item` >= 0 AND `subtotal` >= 0);

-- ---------------------------------------------------------------------
-- 2. DATA AWAL
-- Login:  admin / admin123   |   kasir / kasir123
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `users` (`nama_user`, `username`, `password`, `level`, `status`) VALUES
('Administrator', 'admin', '$2y$10$7U.445ky7SmTulCmg9VlU.LWhB3Ga64X8rkkazq2rAPwWDmtdlEae', 'admin', 'aktif'),
('Kasir StyleHub', 'kasir', '$2y$10$LqlAeasb6Hi1lUdTZC3S4.gvswhm7ZalYiwJWu6d5Epyo3sCY85Au', 'kasir', 'aktif');

INSERT IGNORE INTO `kategori` (`nama_kategori`, `keterangan`) VALUES
('Kaos', 'Kaos pria dan wanita'),
('Kemeja', 'Kemeja lengan panjang dan pendek'),
('Celana', 'Celana panjang, jeans, dan pendek'),
('Jaket', 'Jaket dan hoodie'),
('Aksesori', 'Topi, ikat pinggang, kaus kaki, dan lainnya');

INSERT IGNORE INTO `produk`
  (`id_kategori`, `kode_produk`, `nama_produk`, `deskripsi`, `harga_beli`, `harga_jual`, `stok`, `satuan`, `keterangan`)
SELECT k.id_kategori, d.kode, d.nama, d.deskripsi, d.beli, d.jual, d.stok, d.satuan, d.ket
FROM (
  SELECT 'Kaos'     AS kat, 'KS-001' AS kode, 'Kaos Polos Hitam M'      AS nama, 'Katun combed 30s'      AS deskripsi,  35000 AS beli,  65000 AS jual, 40 AS stok, 'pcs'  AS satuan, 'Ukuran M, warna hitam'  AS ket UNION ALL
  SELECT 'Kaos',     'KS-002', 'Kaos Polos Putih L',      'Katun combed 30s',      35000,  65000, 35, 'pcs',  'Ukuran L, warna putih'  UNION ALL
  SELECT 'Kemeja',   'KM-001', 'Kemeja Flanel Merah L',   'Flanel lengan panjang', 85000, 150000, 20, 'pcs',  'Ukuran L, warna merah'  UNION ALL
  SELECT 'Kemeja',   'KM-002', 'Kemeja Putih Slimfit M',  'Katun oxford',          80000, 140000,  4, 'pcs',  'Ukuran M, warna putih'  UNION ALL
  SELECT 'Celana',   'CL-001', 'Jeans Slim Biru 32',      'Denim stretch',        120000, 220000, 15, 'pcs',  'Ukuran 32, warna biru'  UNION ALL
  SELECT 'Celana',   'CL-002', 'Chino Krem 30',           'Katun twill',          100000, 185000,  3, 'pcs',  'Ukuran 30, warna krem'  UNION ALL
  SELECT 'Jaket',    'JK-001', 'Hoodie Abu-abu XL',       'Fleece tebal',         110000, 199000, 12, 'pcs',  'Ukuran XL, warna abu'   UNION ALL
  SELECT 'Aksesori', 'AK-001', 'Topi Baseball Hitam',     'Adjustable',            20000,  45000, 50, 'pcs',  'All size'               UNION ALL
  SELECT 'Aksesori', 'AK-002', 'Kaus Kaki Katun',         'Isi 3 pasang',          15000,  30000, 60, 'paket','Free size'
) AS d
JOIN `kategori` k ON k.nama_kategori = d.kat;

INSERT INTO `pelanggan` (`nama_pelanggan`, `alamat`, `telepon`, `email`)
SELECT 'Budi Santoso', 'Jl. Merdeka No. 10, Wonosobo', '081234567890', 'budi@example.com'
WHERE NOT EXISTS (SELECT 1 FROM `pelanggan` WHERE `nama_pelanggan` = 'Budi Santoso');

INSERT INTO `pelanggan` (`nama_pelanggan`, `alamat`, `telepon`, `email`)
SELECT 'Siti Aminah', 'Jl. Veteran No. 5, Wonosobo', '082198765432', 'siti@example.com'
WHERE NOT EXISTS (SELECT 1 FROM `pelanggan` WHERE `nama_pelanggan` = 'Siti Aminah');

-- ---------------------------------------------------------------------
-- 3. FUNCTION & STORED PROCEDURE & TRIGGER
-- (phpMyAdmin mendukung DELIMITER pada tab Import)
-- ---------------------------------------------------------------------
DROP FUNCTION IF EXISTS `fn_status_stok`;
DROP FUNCTION IF EXISTS `fn_buat_nota`;
DROP PROCEDURE IF EXISTS `sp_laporan_penjualan`;
DROP TRIGGER IF EXISTS `trg_detail_before_insert`;
DROP TRIGGER IF EXISTS `trg_detail_after_insert`;

DELIMITER $$

-- Status stok: 'HABIS' (0), 'RESTOK' (<= 5), atau 'AMAN'
CREATE FUNCTION `fn_status_stok`(p_stok INT)
RETURNS VARCHAR(10)
DETERMINISTIC
BEGIN
  IF p_stok <= 0 THEN
    RETURN 'HABIS';
  ELSEIF p_stok <= 5 THEN
    RETURN 'RESTOK';
  END IF;
  RETURN 'AMAN';
END$$

-- Nomor nota otomatis: NOTA-YYYYMMDD-0001, 0002, ...
CREATE FUNCTION `fn_buat_nota`()
RETURNS VARCHAR(30)
NOT DETERMINISTIC
READS SQL DATA
BEGIN
  DECLARE v_prefix VARCHAR(20);
  DECLARE v_urut INT;
  SET v_prefix = CONCAT('NOTA-', DATE_FORMAT(NOW(), '%Y%m%d'), '-');
  SELECT IFNULL(MAX(CAST(SUBSTRING(`no_nota`, LENGTH(v_prefix) + 1) AS UNSIGNED)), 0) + 1
    INTO v_urut
    FROM `penjualan`
   WHERE `no_nota` LIKE CONCAT(v_prefix, '%');
  RETURN CONCAT(v_prefix, LPAD(v_urut, 4, '0'));
END$$

-- Laporan penjualan per periode: rekap + produk terjual
CREATE PROCEDURE `sp_laporan_penjualan`(IN p_awal DATE, IN p_akhir DATE)
BEGIN
  -- Rekap
  SELECT COUNT(*)                  AS jumlah_transaksi,
         IFNULL(SUM(`total`), 0)   AS omzet,
         IFNULL(SUM(`diskon`), 0)  AS total_diskon
    FROM `penjualan`
   WHERE DATE(`tgl_penjualan`) BETWEEN p_awal AND p_akhir;

  -- Produk terjual
  SELECT p.`kode_produk`, p.`nama_produk`, k.`nama_kategori`,
         SUM(d.`jumlah`)   AS qty_terjual,
         SUM(d.`subtotal`) AS omzet_produk
    FROM `detail_penjualan` d
    JOIN `penjualan` j ON j.`id_penjualan` = d.`id_penjualan`
    JOIN `produk`    p ON p.`id_produk`    = d.`id_produk`
    JOIN `kategori`  k ON k.`id_kategori`  = p.`id_kategori`
   WHERE DATE(j.`tgl_penjualan`) BETWEEN p_awal AND p_akhir
   GROUP BY p.`id_produk`, p.`kode_produk`, p.`nama_produk`, k.`nama_kategori`
   ORDER BY qty_terjual DESC;
END$$

-- Trigger 1: tolak detail jika qty melebihi stok produk
CREATE TRIGGER `trg_detail_before_insert`
BEFORE INSERT ON `detail_penjualan`
FOR EACH ROW
BEGIN
  DECLARE v_stok INT;
  SELECT `stok` INTO v_stok FROM `produk` WHERE `id_produk` = NEW.`id_produk`;
  IF v_stok IS NULL OR NEW.`jumlah` > v_stok THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Stok produk tidak mencukupi';
  END IF;
END$$

-- Trigger 2: kurangi stok otomatis setelah detail tersimpan
-- (ikut ter-rollback bila transaksi dibatalkan)
CREATE TRIGGER `trg_detail_after_insert`
AFTER INSERT ON `detail_penjualan`
FOR EACH ROW
BEGIN
  UPDATE `produk`
     SET `stok` = `stok` - NEW.`jumlah`
   WHERE `id_produk` = NEW.`id_produk`;
END$$

DELIMITER ;
