<?php
require_once __DIR__ . '/includes/auth.php';
wajib_admin();

if (user_level() !== 'admin') {
    flash_set('error', 'Akses ditolak. Kasir hanya dapat mengakses halaman transaksi dan nota.');
    redirect('transaksi.php');
}

$daftar = $db->query(
    'SELECT p.kode_produk, p.nama_produk, p.keterangan, k.nama_kategori, p.stok, p.satuan,
            fn_status_stok(p.stok) AS status_stok
       FROM produk p
       JOIN kategori k ON k.id_kategori = p.id_kategori
      ORDER BY p.stok ASC, p.nama_produk ASC'
)->fetch_all(MYSQLI_ASSOC);

$judul = 'Laporan Stok';
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
    .kartu, .tabel-bungkus {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
    }
}
</style>

<div class="kop-cetak">Style<span>Hub</span></div>

<p class="no-print"><button class="btn btn-abu" onclick="window.print()">Cetak</button></p>

<div class="tabel-bungkus">
    <table>
        <thead><tr><th>SKU / Kode</th><th>Produk</th><th>Ukuran / Warna</th><th>Kategori</th><th class="kanan">Stok</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($daftar as $r): ?>
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
<?php require __DIR__ . '/includes/footer.php'; ?>