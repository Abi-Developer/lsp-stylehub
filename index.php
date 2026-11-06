<?php
require_once __DIR__ . '/includes/auth.php';
wajib_login();
if (user_level() !== 'admin') {
    redirect('transaksi.php');
}

$judul = 'Dashboard';

// Statistik ringkas (produk, kategori, pelanggan, stok rendah, dan penjualan)
$stat = $db->query(
    'SELECT
        (SELECT COUNT(*) FROM produk)    AS jml_produk,
        (SELECT COUNT(*) FROM kategori)  AS jml_kategori,
        (SELECT COUNT(*) FROM pelanggan) AS jml_pelanggan,
        (SELECT COUNT(*) FROM produk WHERE stok <= ' . STOK_RENDAH . ') AS jml_stok_rendah,
        (SELECT COUNT(*) FROM penjualan WHERE DATE(tgl_penjualan) = CURDATE()) AS trx_hari_ini,
        (SELECT COALESCE(SUM(total), 0) FROM penjualan WHERE DATE(tgl_penjualan) = CURDATE()) AS omzet_hari_ini,
        (SELECT COUNT(*) FROM penjualan) AS total_trx,
        (SELECT COALESCE(SUM(total), 0) FROM penjualan) AS total_omzet'
)->fetch_assoc();

// Produk dengan stok rendah (JOIN ke kategori + keterangan & satuan)
$batas = STOK_RENDAH;
$stmt = $db->prepare(
    'SELECT p.kode_produk, p.nama_produk, p.keterangan, k.nama_kategori, p.stok, p.satuan,
            fn_status_stok(p.stok) AS status_stok
       FROM produk p
       JOIN kategori k ON k.id_kategori = p.id_kategori
      WHERE p.stok <= ?
      ORDER BY p.stok ASC, p.nama_produk ASC
      LIMIT 10'
);
$stmt->bind_param('i', $batas);
$stmt->execute();
$stokRendah = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Transaksi penjualan terbaru (JOIN ke users dan LEFT JOIN ke pelanggan)
$transaksiTerbaru = $db->query(
    'SELECT j.id_penjualan, j.no_nota, j.tgl_penjualan, u.nama_user, pl.nama_pelanggan, j.total
       FROM penjualan j
       JOIN users u ON u.id_user = j.id_user
       LEFT JOIN pelanggan pl ON pl.id_pelanggan = j.id_pelanggan
      ORDER BY j.tgl_penjualan DESC
      LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);

require __DIR__ . '/includes/header.php';
?>
<div class="bar-atas">
    <p>Selamat datang, <strong><?= e($_SESSION['user']['nama_user']) ?></strong>.</p>
    <div>
        <a href="pelanggan.php" class="btn btn-abu">Data Pelanggan</a>
        <a href="laporan_penjualan.php" class="btn btn-abu">Laporan Penjualan</a>
        <a href="laporan_stok.php" class="btn btn-abu">Laporan Stok</a>
    </div>
</div>

<div class="grid-statistik">
    <div class="stat"><div class="angka"><?= (int)$stat['jml_produk'] ?></div><div class="label">Produk</div></div>
    <div class="stat"><div class="angka"><?= (int)$stat['jml_kategori'] ?></div><div class="label">Kategori</div></div>
    <div class="stat"><div class="angka"><?= (int)$stat['jml_pelanggan'] ?></div><div class="label">Pelanggan</div></div>
    <div class="stat peringatan"><div class="angka"><?= (int)$stat['jml_stok_rendah'] ?></div><div class="label">Produk stok rendah (≤ <?= STOK_RENDAH ?>)</div></div>
    <div class="stat"><div class="angka"><?= (int)$stat['trx_hari_ini'] ?></div><div class="label">Transaksi Hari Ini</div></div>
    <div class="stat"><div class="angka"><?= rupiah($stat['omzet_hari_ini']) ?></div><div class="label">Omzet Hari Ini</div></div>
    <div class="stat"><div class="angka"><?= (int)$stat['total_trx'] ?></div><div class="label">Total Transaksi</div></div>
    <div class="stat"><div class="angka"><?= rupiah($stat['total_omzet']) ?></div><div class="label">Total Omzet</div></div>
</div>

<div class="kartu">
    <div class="bar-atas">
        <h2>Transaksi Terbaru</h2>
        <a href="laporan_penjualan.php" class="btn btn-kecil btn-biru">Lihat Semua</a>
    </div>
    <div class="tabel-bungkus">
        <table>
            <thead>
                <tr><th>No. Nota</th><th>Tanggal</th><th>Pelanggan</th><th>Kasir</th><th class="kanan">Total</th><th>Nota</th></tr>
            </thead>
            <tbody>
            <?php if (!$transaksiTerbaru): ?>
                <tr><td colspan="6" class="kosong">Belum ada transaksi.</td></tr>
            <?php endif; ?>
            <?php foreach ($transaksiTerbaru as $t): ?>
                <tr>
                    <td><?= e($t['no_nota']) ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($t['tgl_penjualan']))) ?></td>
                    <td><?= e($t['nama_pelanggan'] ?? 'Umum') ?></td>
                    <td><?= e($t['nama_user']) ?></td>
                    <td class="kanan"><?= rupiah($t['total']) ?></td>
                    <td><a href="nota.php?id=<?= (int)$t['id_penjualan'] ?>" class="btn btn-kecil btn-biru">Lihat</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="kartu">
    <div class="bar-atas">
        <h2>Perlu restok</h2>
        <a href="laporan_stok.php" class="btn btn-kecil btn-abu">Laporan Stok</a>
    </div>
    <div class="tabel-bungkus">
        <table>
            <thead>
                <tr><th>Kode</th><th>Produk</th><th>Ukuran / Warna</th><th>Kategori</th><th class="kanan">Stok</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php if (!$stokRendah): ?>
                <tr><td colspan="6" class="kosong">Semua stok aman.</td></tr>
            <?php endif; ?>
            <?php foreach ($stokRendah as $r): ?>
                <tr>
                    <td><?= e($r['kode_produk']) ?></td>
                    <td><?= e($r['nama_produk']) ?></td>
                    <td><?= e($r['keterangan']) ?></td>
                    <td><?= e($r['nama_kategori']) ?></td>
                    <td class="kanan"><?= (int)$r['stok'] ?> <?= e($r['satuan']) ?></td>
                    <td><span class="badge badge-<?= e(strtolower($r['status_stok'])) ?>"><?= e($r['status_stok']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>