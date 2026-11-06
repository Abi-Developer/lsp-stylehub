<?php
require_once __DIR__ . '/includes/auth.php';
wajib_login();

if (user_level() !== 'kasir') {
    flash_set('error', 'Halaman Kasir hanya untuk akun kasir.');
    redirect('index.php');
}

if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = []; // [id_produk => jumlah]
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post_str('op');

    // Tambah ke keranjang
    if ($op === 'tambah') {
        $id  = int_valid(post_str('id_produk'));
        $qty = int_valid(post_str('jumlah'));

        if (!$id || !$qty) {
            flash_set('error', 'Pilih produk dan isi jumlah minimal 1.');
        } else {
            $stmt = $db->prepare('SELECT stok FROM produk WHERE id_produk = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $p = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $baru = ($_SESSION['keranjang'][$id] ?? 0) + $qty;
            if (!$p) {
                flash_set('error', 'Produk tidak ditemukan.');
            } elseif ($baru > $p['stok']) {
                flash_set('error', 'Stok tidak cukup. Tersedia ' . $p['stok'] . '.');
            } else {
                $_SESSION['keranjang'][$id] = $baru;
                flash_set('sukses', 'Ditambahkan ke keranjang.');
            }
        }
        redirect('transaksi.php');
    }

    // Hapus item
    if ($op === 'hapus') {
        unset($_SESSION['keranjang'][int_valid(post_str('id_produk'))]);
        redirect('transaksi.php');
    }

    // Bayar
    if ($op === 'bayar') {
        $idPel  = int_valid(post_str('id_pelanggan'));   // kosong = NULL
        $diskon = post_str('diskon') === '' ? 0 : angka_valid(post_str('diskon'));
        $bayar  = angka_valid(post_str('pembayaran'));
        $uid    = $_SESSION['user']['id_user'];

        try {
            if (!$_SESSION['keranjang']) {
                throw new Exception('Keranjang masih kosong.');
            }
            if ($diskon === null || $bayar === null) {
                throw new Exception('Diskon dan pembayaran harus angka, tidak boleh negatif.');
            }

            $db->begin_transaction();

            // Hitung subtotal dan ambil stok dari database lewat PHP
            $items = [];
            $subtotal = 0;
            foreach ($_SESSION['keranjang'] as $id => $qty) {
                $stmt = $db->prepare('SELECT harga_jual, stok FROM produk WHERE id_produk = ? FOR UPDATE');
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $p = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $sub = $p['harga_jual'] * $qty;
                $items[] = [$id, $p['harga_jual'], $qty, $sub, (int)$p['stok']];
                $subtotal += $sub;
            }

            if ($diskon > $subtotal) {
                throw new Exception('Diskon melebihi total belanja.');
            }
            $total = $subtotal - $diskon;
            if ($bayar < $total) {
                throw new Exception('Pembayaran kurang. Total ' . rupiah($total) . '.');
            }
            $kembali = $bayar - $total;

            // Simpan transaksi
            $nota = $db->query('SELECT fn_buat_nota()')->fetch_row()[0];
            $stmt = $db->prepare(
                'INSERT INTO penjualan (id_pelanggan, id_user, no_nota, tgl_penjualan, total, diskon, pembayaran, kembalian)
                 VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)'
            );
            $stmt->bind_param('iisdddd', $idPel, $uid, $nota, $total, $diskon, $bayar, $kembali);
            $stmt->execute();
            $idJual = $db->insert_id;
            $stmt->close();

            // Simpan detail (tanpa subquery ke tabel produk agar tidak bentrok dengan trigger)
            foreach ($items as [$id, $harga, $qty, $sub, $stokSaatIni]) {
                $stmt = $db->prepare(
                    'INSERT INTO detail_penjualan (id_penjualan, id_produk, harga_jual, jumlah, diskon_item, subtotal, stok)
                     VALUES (?, ?, ?, ?, 0, ?, ?)'
                );
                $stmt->bind_param('iididi', $idJual, $id, $harga, $qty, $sub, $stokSaatIni);
                $stmt->execute();
                $stmt->close();
            }

            $db->commit();
            $_SESSION['keranjang'] = [];
            flash_set('sukses', 'Transaksi berhasil. Nota ' . $nota);
            redirect('nota.php?id=' . $idJual);

        } catch (Exception $e) {
            $db->rollback();
            flash_set('error', $e->getMessage());
            redirect('transaksi.php');
        }
    }
}

// ---- Data tampilan ----
$baris = [];
$subtotal = 0;
foreach ($_SESSION['keranjang'] as $id => $qty) {
    $stmt = $db->prepare('SELECT kode_produk, nama_produk, keterangan, harga_jual FROM produk WHERE id_produk = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$p) {
        continue;
    }
    $p['id']  = $id;
    $p['qty'] = $qty;
    $p['sub'] = $p['harga_jual'] * $qty;
    $baris[]  = $p;
    $subtotal += $p['sub'];
}

$produk    = $db->query('SELECT id_produk, kode_produk, nama_produk, keterangan, harga_jual, stok FROM produk WHERE stok > 0 ORDER BY nama_produk')->fetch_all(MYSQLI_ASSOC);
$pelanggan = $db->query('SELECT id_pelanggan, nama_pelanggan FROM pelanggan ORDER BY nama_pelanggan')->fetch_all(MYSQLI_ASSOC);

$judul = 'Kasir';
require __DIR__ . '/includes/header.php';
?>

<div class="kartu">
    <h2>Tambah ke Keranjang</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="tambah">
        <div class="form-grid">
            <div class="form-grup penuh">
                <label>Produk</label>
                <select name="id_produk" required>
                    <option value="">-- Pilih produk --</option>
                    <?php foreach ($produk as $p): ?>
                        <option value="<?= (int)$p['id_produk'] ?>">
                            <?= e($p['kode_produk'] . ' - ' . $p['nama_produk'] . ' (' . $p['keterangan'] . ') | stok ' . $p['stok'] . ' | ' . rupiah($p['harga_jual'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grup">
                <label>Jumlah</label>
                <input type="number" name="jumlah" min="1" value="1" required>
            </div>
        </div>
        <p><button class="btn">+ Tambah</button></p>
    </form>
</div>

<div class="kartu">
    <h2>Keranjang</h2>
    <div class="tabel-bungkus">
        <table>
            <thead><tr><th>Kode</th><th>Produk</th><th class="kanan">Harga</th><th class="kanan">Qty</th><th class="kanan">Subtotal</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (!$baris): ?><tr><td colspan="6" class="kosong">Keranjang kosong.</td></tr><?php endif; ?>
            <?php foreach ($baris as $b): ?>
                <tr>
                    <td><?= e($b['kode_produk']) ?></td>
                    <td><?= e($b['nama_produk']) ?> <span class="bantuan">(<?= e($b['keterangan']) ?>)</span></td>
                    <td class="kanan"><?= rupiah($b['harga_jual']) ?></td>
                    <td class="kanan"><?= (int)$b['qty'] ?></td>
                    <td class="kanan"><?= rupiah($b['sub']) ?></td>
                    <td>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="op" value="hapus">
                            <input type="hidden" name="id_produk" value="<?= (int)$b['id'] ?>">
                            <button class="btn btn-kecil btn-merah">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p><strong>Subtotal: <?= rupiah($subtotal) ?></strong></p>
</div>

<?php if ($baris): ?>
<div class="kartu">
    <h2>Pembayaran</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="op" value="bayar">
        <div class="form-grid">
            <div class="form-grup">
                <label>Pelanggan (opsional)</label>
                <select name="id_pelanggan">
                    <option value="">Umum</option>
                    <?php foreach ($pelanggan as $pl): ?>
                        <option value="<?= (int)$pl['id_pelanggan'] ?>"><?= e($pl['nama_pelanggan']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grup">
                <label>Diskon (Rp)</label>
                <input type="number" name="diskon" min="0" value="0">
            </div>
            <div class="form-grup">
                <label>Uang dibayar (Rp)</label>
                <input type="number" name="pembayaran" min="0" required>
            </div>
        </div>
        <p><button class="btn">Simpan Transaksi</button></p>
    </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>