<?php
require_once __DIR__ . '/includes/auth.php';
wajib_login();

$id = int_valid((string)($_GET['id'] ?? ''));

$stmt = $db->prepare(
    'SELECT j.*, u.nama_user, pl.nama_pelanggan
     FROM penjualan j
     JOIN users u ON u.id_user = j.id_user
     LEFT JOIN pelanggan pl ON pl.id_pelanggan = j.id_pelanggan
     WHERE j.id_penjualan = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$trx) {
    flash_set('error', 'Nota tidak ditemukan.');
    redirect(user_level() === 'admin' ? 'laporan_penjualan.php' : 'transaksi.php');
}

$stmt = $db->prepare(
    'SELECT d.harga_jual, d.jumlah, d.subtotal, p.kode_produk, p.nama_produk, p.keterangan
       FROM detail_penjualan d
       JOIN produk p ON p.id_produk = d.id_produk
      WHERE d.id_penjualan = ?'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$detail = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$judul = 'Nota ' . $trx['no_nota'];
require __DIR__ . '/includes/header.php';
?>
<style>
.kop-cetak {
    display: none;
}
@media print {
    aside, nav, header, .sidebar, .no-print, .bar-atas, .alert, button, .btn {
        display: none !important;
    }
    .kop-cetak {
        display: block !important;
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid #e5e7eb;
    }
    .kop-cetak span {
        color: #ea580c;
    }
    body, main, .konten {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        background: #fff !important;
    }
    .kartu {
        box-shadow: none !important;
        border: 1px solid #e5e7eb !important;
    }
}
</style>

<div class="kop-cetak">Style<span>Hub</span></div>

<div class="kartu">
    <p>
        No. Nota: <strong><?= e($trx['no_nota']) ?></strong><br>
        Tanggal: <?= e(date('d/m/Y H:i', strtotime($trx['tgl_penjualan']))) ?><br>
        Kasir: <?= e($trx['nama_user']) ?><br>
        Pelanggan: <?= e($trx['nama_pelanggan'] ?? 'Umum') ?>
    </p>

    <table>
        <thead><tr><th>Kode</th><th>Produk (ukuran/warna)</th><th class="kanan">Qty</th><th class="kanan">Harga</th><th class="kanan">Subtotal</th></tr></thead>
        <tbody>
        <?php foreach ($detail as $d): ?>
            <tr>
                <td><?= e($d['kode_produk']) ?></td>
                <td><?= e($d['nama_produk']) ?> (<?= e($d['keterangan']) ?>)</td>
                <td class="kanan"><?= (int)$d['jumlah'] ?></td>
                <td class="kanan"><?= rupiah($d['harga_jual']) ?></td>
                <td class="kanan"><?= rupiah($d['subtotal']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p style="text-align:right">
        Diskon: <?= rupiah($trx['diskon']) ?><br>
        <strong>Total: <?= rupiah($trx['total']) ?></strong><br>
        Dibayar: <?= rupiah($trx['pembayaran']) ?><br>
        Kembalian: <?= rupiah($trx['kembalian']) ?>
    </p>

   <p class="no-print">
        <button class="btn" onclick="window.print()">Cetak Nota</button>
        <?php if (user_level() === 'kasir'): ?>
            <a href="transaksi.php" class="btn btn-biru">Transaksi Baru</a>
        <?php else: ?>
            <a href="laporan_penjualan.php" class="btn btn-abu">Kembali ke Laporan</a>
        <?php endif; ?>
    </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>