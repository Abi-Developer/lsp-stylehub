<?php
// Layout atas. Set $judul sebelum include.
$judul = $judul ?? 'StyleHub';
$halaman = basename($_SERVER['SCRIPT_NAME']);
$flash = flash_get();

$menu = [
    ['index.php',             'Dashboard',         ['admin']],
    ['transaksi.php',         'Kasir',             ['kasir']],
    ['kategori.php',          'Kategori',          ['admin']],
    ['produk.php',            'Produk',            ['admin']],
    ['pelanggan.php',         'Pelanggan',         ['admin']],
    ['laporan_penjualan.php', 'Laporan Penjualan', ['admin']],
    ['laporan_stok.php',      'Laporan Stok',      ['admin']],
    ['user.php',              'Pengguna',          ['admin']],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($judul) ?> - StyleHub</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="layout">
    <aside class="sidebar no-print">
        <div class="brand">Style<span>Hub</span></div>
        <nav>
            <?php foreach ($menu as [$file, $label, $level]): ?>
                <?php if (in_array(user_level(), $level, true)): ?>
                    <a href="<?= e($file) ?>" class="<?= $halaman === $file ? 'aktif' : '' ?>"><?= e($label) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="user-box">
            <div><strong><?= e($_SESSION['user']['nama_user']) ?></strong></div>
            <div class="kecil"><?= e(ucfirst($_SESSION['user']['level'])) ?></div>
            <a href="logout.php" class="btn btn-kecil btn-abu">Keluar</a>
        </div>
    </aside>
    <main class="konten">
        <h1 class="no-print"><?= e($judul) ?></h1>
        <?php if ($flash): ?>
            <div class="alert alert-<?= e($flash['tipe']) ?> no-print"><?= e($flash['pesan']) ?></div>
        <?php endif; ?>