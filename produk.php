<?php
require_once __DIR__ . '/includes/auth.php';
wajib_admin();

if (user_level() !== 'admin') {
    flash_set('error', 'Akses ditolak. Kasir hanya dapat mengakses halaman transaksi dan nota.');
    redirect('transaksi.php');
}

$aksi = $_GET['aksi'] ?? 'list';
$errors = [];

// ---------- PROSES POST (simpan / hapus) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post_str('op');

    if ($op === 'hapus') {
        $id = int_valid(post_str('id'));
        try {
            $stmt = $db->prepare('DELETE FROM produk WHERE id_produk = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            flash_set('sukses', 'Produk dihapus.');
        } catch (mysqli_sql_exception $ex) {
            flash_set('error', $ex->getCode() === 1451
                ? 'Produk sudah pernah terjual, tidak bisa dihapus.'
                : 'Gagal menghapus produk.');
        }
        redirect('produk.php');
    }

    // simpan (tambah / ubah)
    $id          = int_valid(post_str('id'));
    $idKategori  = int_valid(post_str('id_kategori'));
    $kode        = strtoupper(post_str('kode_produk'));
    $nama        = post_str('nama_produk');
    $deskripsi   = post_str('deskripsi');
    $hargaBeli   = angka_valid(post_str('harga_beli'));
    $hargaJual   = angka_valid(post_str('harga_jual'));
    $stok        = int_valid(post_str('stok'));
    $satuan      = post_str('satuan');
    $keterangan  = post_str('keterangan');

    if (!$idKategori) {
        $errors[] = 'Kategori wajib dipilih.';
    } else {
        $c = $db->prepare('SELECT 1 FROM kategori WHERE id_kategori = ?');
        $c->bind_param('i', $idKategori);
        $c->execute();
        if ($c->get_result()->num_rows === 0) {
            $errors[] = 'Kategori tidak valid.';
        }
        $c->close();
    }
    if ($kode === '' || !preg_match('/^[A-Z0-9\-_.]{1,25}$/', $kode)) {
        $errors[] = 'Kode produk wajib diisi (huruf/angka/tanda -, maksimal 25 karakter).';
    }
    if ($nama === '' || mb_strlen($nama) > 100) {
        $errors[] = 'Nama produk wajib diisi (maksimal 100 karakter).';
    }
    if ($hargaBeli === null) {
        $errors[] = 'Harga beli harus angka dan tidak boleh negatif.';
    }
    if ($hargaJual === null) {
        $errors[] = 'Harga jual harus angka dan tidak boleh negatif.';
    }
    if ($stok === null) {
        $errors[] = 'Stok harus bilangan bulat dan tidak boleh negatif.';
    }
    if ($satuan === '' || mb_strlen($satuan) > 20) {
        $errors[] = 'Satuan wajib diisi (maksimal 20 karakter).';
    }
    if (mb_strlen($keterangan) > 150) {
        $errors[] = 'Keterangan maksimal 150 karakter.';
    }

    // kode produk harus unik
    if (!$errors) {
        $cek = $db->prepare('SELECT id_produk FROM produk WHERE kode_produk = ? AND id_produk <> ? LIMIT 1');
        $idCek = $id ?? 0;
        $cek->bind_param('si', $kode, $idCek);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $errors[] = 'Kode produk sudah dipakai produk lain.';
        }
        $cek->close();
    }

    if (!$errors) {
        $desk = $deskripsi === '' ? null : $deskripsi;
        $ket  = $keterangan === '' ? null : $keterangan;
        try {
            if ($id) {
                $stmt = $db->prepare(
                    'UPDATE produk SET id_kategori = ?, kode_produk = ?, nama_produk = ?, deskripsi = ?,
                            harga_beli = ?, harga_jual = ?, stok = ?, satuan = ?, keterangan = ?
                      WHERE id_produk = ?'
                );
                $stmt->bind_param('isssddissi', $idKategori, $kode, $nama, $desk, $hargaBeli, $hargaJual, $stok, $satuan, $ket, $id);
                $pesan = 'Produk diperbarui.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO produk (id_kategori, kode_produk, nama_produk, deskripsi, harga_beli, harga_jual, stok, satuan, keterangan)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('isssddiss', $idKategori, $kode, $nama, $desk, $hargaBeli, $hargaJual, $stok, $satuan, $ket);
                $pesan = 'Produk ditambahkan.';
            }
            $stmt->execute();
            $stmt->close();
            flash_set('sukses', $pesan);
            redirect('produk.php');
        } catch (mysqli_sql_exception $ex) {
            $errors[] = $ex->getCode() === 1062 ? 'Kode produk sudah dipakai produk lain.' : 'Gagal menyimpan produk.';
        }
    }

    $aksi = $id ? 'ubah' : 'tambah';
    $data = [
        'id_produk' => $id, 'id_kategori' => $idKategori, 'kode_produk' => $kode, 'nama_produk' => $nama,
        'deskripsi' => $deskripsi, 'harga_beli' => post_str('harga_beli'), 'harga_jual' => post_str('harga_jual'),
        'stok' => post_str('stok'), 'satuan' => $satuan, 'keterangan' => $keterangan,
    ];
}

// ---------- DATA UNTUK FORM ----------
$kategoriList = [];
if (in_array($aksi, ['tambah', 'ubah'], true)) {
    $kategoriList = $db->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori')->fetch_all(MYSQLI_ASSOC);

    if (!isset($data)) {
        $data = [
            'id_produk' => null, 'id_kategori' => null, 'kode_produk' => '', 'nama_produk' => '', 'deskripsi' => '',
            'harga_beli' => '0', 'harga_jual' => '0', 'stok' => '0', 'satuan' => 'pcs', 'keterangan' => '',
        ];
        if ($aksi === 'ubah') {
            $id = int_valid((string)($_GET['id'] ?? ''));
            $stmt = $db->prepare('SELECT * FROM produk WHERE id_produk = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                flash_set('error', 'Produk tidak ditemukan.');
                redirect('produk.php');
            }
            $data = $row;
            $data['deskripsi'] = (string)$data['deskripsi'];
            $data['keterangan'] = (string)$data['keterangan'];
            $data['harga_beli'] = (string)(float)$data['harga_beli'];
            $data['harga_jual'] = (string)(float)$data['harga_jual'];
        }
    }
}

// ---------- DATA UNTUK LIST ----------
$daftar = [];
$q = trim((string)($_GET['q'] ?? ''));
$fKategori = int_valid((string)($_GET['kategori'] ?? ''));
if ($aksi === 'list') {
    $sql = 'SELECT p.id_produk, p.kode_produk, p.nama_produk, k.nama_kategori, p.harga_jual, p.stok, p.satuan,
                   fn_status_stok(p.stok) AS status_stok
              FROM produk p
              JOIN kategori k ON k.id_kategori = p.id_kategori
             WHERE (p.nama_produk LIKE ? OR p.kode_produk LIKE ?)';
    $like = '%' . $q . '%';
    if ($fKategori) {
        $stmt = $db->prepare($sql . ' AND p.id_kategori = ? ORDER BY p.nama_produk');
        $stmt->bind_param('ssi', $like, $like, $fKategori);
    } else {
        $stmt = $db->prepare($sql . ' ORDER BY p.nama_produk');
        $stmt->bind_param('ss', $like, $like);
    }
    $stmt->execute();
    $daftar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $kategoriFilter = $db->query('SELECT id_kategori, nama_kategori FROM kategori ORDER BY nama_kategori')->fetch_all(MYSQLI_ASSOC);
}

$judul = 'Produk';
require __DIR__ . '/includes/header.php';
?>

<?php if ($aksi === 'list'): ?>
    <div class="bar-atas">
        <form method="get">
            <input type="text" name="q" placeholder="Cari kode / nama..." value="<?= e($q) ?>">
            <select name="kategori">
                <option value="">Semua kategori</option>
                <?php foreach ($kategoriFilter as $k): ?>
                    <option value="<?= (int)$k['id_kategori'] ?>" <?= $fKategori === (int)$k['id_kategori'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-biru">Cari</button>
        </form>
        <a href="produk.php?aksi=tambah" class="btn">+ Tambah Produk</a>
    </div>
    <div class="tabel-bungkus">
        <table>
            <thead>
                <tr><th>Kode</th><th>Nama Produk</th><th>Kategori</th><th class="kanan">Harga Jual</th><th class="kanan">Stok</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
            <?php if (!$daftar): ?><tr><td colspan="7" class="kosong">Tidak ada produk.</td></tr><?php endif; ?>
            <?php foreach ($daftar as $r): ?>
                <tr>
                    <td><?= e($r['kode_produk']) ?></td>
                    <td><?= e($r['nama_produk']) ?></td>
                    <td><?= e($r['nama_kategori']) ?></td>
                    <td class="kanan"><?= rupiah($r['harga_jual']) ?></td>
                    <td class="kanan"><?= (int)$r['stok'] ?> <?= e($r['satuan']) ?></td>
                    <td><span class="badge badge-<?= e(strtolower($r['status_stok'])) ?>"><?= e($r['status_stok']) ?></span></td>
                    <td class="aksi">
                        <a href="produk.php?aksi=ubah&id=<?= (int)$r['id_produk'] ?>" class="btn btn-kecil btn-biru">Ubah</a>
                        <form method="post" onsubmit="return confirm('Hapus produk ini?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="op" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$r['id_produk'] ?>">
                            <button class="btn btn-kecil btn-merah">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <div class="kartu">
        <h2><?= $data['id_produk'] ? 'Ubah' : 'Tambah' ?> Produk</h2>
        <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="simpan">
            <input type="hidden" name="id" value="<?= (int)$data['id_produk'] ?>">
            <div class="form-grid">
                <div class="form-grup">
                    <label for="id_kategori">Kategori</label>
                    <select id="id_kategori" name="id_kategori" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= (int)$k['id_kategori'] ?>" <?= (int)$data['id_kategori'] === (int)$k['id_kategori'] ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grup">
                    <label for="kode_produk">Kode Produk</label>
                    <input type="text" id="kode_produk" name="kode_produk" maxlength="25" value="<?= e($data['kode_produk']) ?>" required>
                    <span class="bantuan">Harus unik, contoh: KS-001</span>
                </div>
                <div class="form-grup penuh">
                    <label for="nama_produk">Nama Produk</label>
                    <input type="text" id="nama_produk" name="nama_produk" maxlength="100" value="<?= e($data['nama_produk']) ?>" required>
                </div>
                <div class="form-grup">
                    <label for="harga_beli">Harga Beli</label>
                    <input type="number" id="harga_beli" name="harga_beli" min="0" step="0.01" value="<?= e($data['harga_beli']) ?>" required>
                </div>
                <div class="form-grup">
                    <label for="harga_jual">Harga Jual</label>
                    <input type="number" id="harga_jual" name="harga_jual" min="0" step="0.01" value="<?= e($data['harga_jual']) ?>" required>
                </div>
                <div class="form-grup">
                    <label for="stok">Stok</label>
                    <input type="number" id="stok" name="stok" min="0" step="1" value="<?= e($data['stok']) ?>" required>
                </div>
                <div class="form-grup">
                    <label for="satuan">Satuan</label>
                    <input type="text" id="satuan" name="satuan" maxlength="20" value="<?= e($data['satuan']) ?>" required>
                </div>
                <div class="form-grup penuh">
                    <label for="deskripsi">Deskripsi</label>
                    <textarea id="deskripsi" name="deskripsi" rows="3"><?= e($data['deskripsi']) ?></textarea>
                </div>
                <div class="form-grup penuh">
                    <label for="keterangan">Keterangan (ukuran / warna)</label>
                    <input type="text" id="keterangan" name="keterangan" maxlength="150" value="<?= e($data['keterangan']) ?>">
                </div>
            </div>
            <p>
                <button type="submit" class="btn">Simpan</button>
                <a href="produk.php" class="btn btn-abu">Batal</a>
            </p>
        </form>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
