<?php
require_once __DIR__ . '/includes/auth.php';
wajib_admin();

if (user_level() !== 'admin') {
    flash_set('error', 'Akses ditolak. Kasir hanya dapat mengakses halaman transaksi dan nota.');
    redirect('transaksi.php');
}

$awal  = (string)($_GET['awal'] ?? date('Y-m-01'));
$akhir = (string)($_GET['akhir'] ?? date('Y-m-d'));
if (!strtotime($awal) || !strtotime($akhir) || $awal > $akhir) {
    $awal  = date('Y-m-01');
    $akhir = date('Y-m-d');
}

// Stored procedure: menghasilkan 2 result set (rekap, produk terjual)
$stmt = $db->prepare('CALL sp_laporan_penjualan(?, ?)');
$stmt->bind_param('ss', $awal, $akhir);
$stmt->execute();
$rekap = $stmt->get_result()->fetch_assoc();
$stmt->next_result();
$terjual = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
while ($stmt->more_results()) {
    $stmt->next_result();
}
$stmt->close();

// Daftar transaksi
$stmt = $db->prepare(
    'SELECT j.id_penjualan, j.no_nota, j.tgl_penjualan, u.nama_user, j.total
       FROM penjualan j
       JOIN users u ON u.id_user = j.id_user
      WHERE DATE(j.tgl_penjualan) BETWEEN ? AND ?
      ORDER BY j.tgl_penjualan DESC'
);
$stmt->bind_param('ss', $awal, $akhir);
$stmt->execute();
$transaksi = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$judul = 'Laporan Penjualan';
require __DIR__ . '/includes/header.php';
?>
<style>
.kop-cetak {
    display: none;
}
@media print {
    aside, nav, header, .sidebar, .no-print, .bar-atas, .alert, button, .btn, form {
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
        margin-bottom: 16px !important;
    }
}
</style>

<div class="kop-cetak">Style<span>Hub</span></div>

<div class="bar-atas no-print">
    <form method="get">
        <input type="date" name="awal" value="<?= e($awal) ?>">
        <input type="date" name="akhir" value="<?= e($akhir) ?>">
        <button class="btn btn-biru">Tampilkan</button>
    </form>
    <button class="btn btn-abu" onclick="window.print()">Cetak</button>
</div>

<p>
    Periode: <strong><?= e($awal) ?></strong> s/d <strong><?= e($akhir) ?></strong><br>
    Jumlah transaksi: <strong><?= (int)$rekap['jumlah_transaksi'] ?></strong> |
    Omzet: <strong><?= rupiah($rekap['omzet']) ?></strong>
</p>

<div class="kartu">
    <h2>Produk Terjual</h2>
    <table>
        <thead><tr><th>Kode</th><th>Produk</th><th>Kategori</th><th class="kanan">Qty</th><th class="kanan">Omzet</th></tr></thead>
        <tbody>
        <?php if (!$terjual): ?><tr><td colspan="5" class="kosong">Tidak ada penjualan.</td></tr><?php endif; ?>
        <?php foreach ($terjual as $r): ?>
            <tr>
                <td><?= e($r['kode_produk']) ?></td>
                <td><?= e($r['nama_produk']) ?></td>
                <td><?= e($r['nama_kategori']) ?></td>
                <td class="kanan"><?= (int)$r['qty_terjual'] ?></td>
                <td class="kanan"><?= rupiah($r['omzet_produk']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="kartu">
    <h2>Daftar Transaksi</h2>
    <table>
        <thead><tr><th>No. Nota</th><th>Tanggal</th><th>Kasir</th><th class="kanan">Total</th><th class="no-print">Nota</th></tr></thead>
        <tbody>
        <?php if (!$transaksi): ?><tr><td colspan="5" class="kosong">Belum ada transaksi.</td></tr><?php endif; ?>
        <?php foreach ($transaksi as $r): ?>
            <tr>
                <td><?= e($r['no_nota']) ?></td>
                <td><?= e(date('d/m/Y H:i', strtotime($r['tgl_penjualan']))) ?></td>
                <td><?= e($r['nama_user']) ?></td>
                <td class="kanan"><?= rupiah($r['total']) ?></td>
                <td class="no-print"><a href="nota.php?id=<?= (int)$r['id_penjualan'] ?>">Lihat</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>