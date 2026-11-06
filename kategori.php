<?php
require_once __DIR__ . '/includes/auth.php';
wajib_admin();

$aksi = $_GET['aksi'] ?? 'list';
$errors = [];

// ---------- PROSES POST (simpan / hapus) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post_str('op');

    if ($op === 'hapus') {
        $id = int_valid(post_str('id'));
        try {
            $stmt = $db->prepare('DELETE FROM kategori WHERE id_kategori = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            flash_set('sukses', 'Kategori dihapus.');
        } catch (mysqli_sql_exception $ex) {
            flash_set('error', $ex->getCode() === 1451
                ? 'Kategori masih dipakai produk, tidak bisa dihapus.'
                : 'Gagal menghapus kategori.');
        }
        redirect('kategori.php');
    }

    // simpan (tambah / ubah)
    $id         = int_valid(post_str('id'));
    $nama       = post_str('nama_kategori');
    $keterangan = post_str('keterangan');

    if ($nama === '') {
        $errors[] = 'Nama kategori wajib diisi.';
    } elseif (mb_strlen($nama) > 60) {
        $errors[] = 'Nama kategori maksimal 60 karakter.';
    }
    if (mb_strlen($keterangan) > 150) {
        $errors[] = 'Keterangan maksimal 150 karakter.';
    }

    if (!$errors) {
        // nama kategori harus unik
        $cek = $db->prepare('SELECT id_kategori FROM kategori WHERE nama_kategori = ? AND id_kategori <> ? LIMIT 1');
        $idCek = $id ?? 0;
        $cek->bind_param('si', $nama, $idCek);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $errors[] = 'Nama kategori sudah ada.';
        }
        $cek->close();
    }

    if (!$errors) {
        $ket = $keterangan === '' ? null : $keterangan;
        if ($id) {
            $stmt = $db->prepare('UPDATE kategori SET nama_kategori = ?, keterangan = ? WHERE id_kategori = ?');
            $stmt->bind_param('ssi', $nama, $ket, $id);
            $pesan = 'Kategori diperbarui.';
        } else {
            $stmt = $db->prepare('INSERT INTO kategori (nama_kategori, keterangan) VALUES (?, ?)');
            $stmt->bind_param('ss', $nama, $ket);
            $pesan = 'Kategori ditambahkan.';
        }
        $stmt->execute();
        $stmt->close();
        flash_set('sukses', $pesan);
        redirect('kategori.php');
    }

    $aksi = $id ? 'ubah' : 'tambah';
    $data = ['id_kategori' => $id, 'nama_kategori' => $nama, 'keterangan' => $keterangan];
}

// ---------- DATA UNTUK FORM ----------
if (in_array($aksi, ['tambah', 'ubah'], true) && !isset($data)) {
    $data = ['id_kategori' => null, 'nama_kategori' => '', 'keterangan' => ''];
    if ($aksi === 'ubah') {
        $id = int_valid((string)($_GET['id'] ?? ''));
        $stmt = $db->prepare('SELECT id_kategori, nama_kategori, keterangan FROM kategori WHERE id_kategori = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            flash_set('error', 'Kategori tidak ditemukan.');
            redirect('kategori.php');
        }
        $data = $row;
        $data['keterangan'] = (string)$data['keterangan'];
    }
}

// ---------- DATA UNTUK LIST ----------
$daftar = [];
if ($aksi === 'list') {
    $daftar = $db->query(
        'SELECT id_kategori, nama_kategori, keterangan
           FROM kategori
          ORDER BY nama_kategori'
    )->fetch_all(MYSQLI_ASSOC);
}

$judul = 'Kategori';
require __DIR__ . '/includes/header.php';
?>

<?php if ($aksi === 'list'): ?>
    <div class="bar-atas">
        <span></span>
        <a href="kategori.php?aksi=tambah" class="btn">+ Tambah Kategori</a>
    </div>
    <div class="tabel-bungkus">
        <table>
            <thead><tr><th>#</th><th>Nama</th><th>Keterangan</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (!$daftar): ?><tr><td colspan="4" class="kosong">Belum ada kategori.</td></tr><?php endif; ?>
            <?php foreach ($daftar as $i => $r): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($r['nama_kategori']) ?></td>
                    <td><?= e($r['keterangan']) ?></td>
                    <td class="aksi">
                        <a href="kategori.php?aksi=ubah&id=<?= (int)$r['id_kategori'] ?>" class="btn btn-kecil btn-biru">Ubah</a>
                        <form method="post" onsubmit="return confirm('Hapus kategori ini?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="op" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$r['id_kategori'] ?>">
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
        <h2><?= $data['id_kategori'] ? 'Ubah' : 'Tambah' ?> Kategori</h2>
        <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="simpan">
            <input type="hidden" name="id" value="<?= (int)$data['id_kategori'] ?>">
            <div class="form-grid">
                <div class="form-grup">
                    <label for="nama_kategori">Nama Kategori</label>
                    <input type="text" id="nama_kategori" name="nama_kategori" maxlength="60" value="<?= e($data['nama_kategori']) ?>" required>
                </div>
                <div class="form-grup">
                    <label for="keterangan">Keterangan</label>
                    <input type="text" id="keterangan" name="keterangan" maxlength="150" value="<?= e($data['keterangan']) ?>">
                </div>
            </div>
            <p>
                <button type="submit" class="btn">Simpan</button>
                <a href="kategori.php" class="btn btn-abu">Batal</a>
            </p>
        </form>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>