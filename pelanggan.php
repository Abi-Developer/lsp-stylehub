<?php
require_once __DIR__ . '/includes/auth.php';
wajib_admin(); // admin

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
        if (user_level() !== 'admin') {
            flash_set('error', 'Hanya admin yang dapat menghapus pelanggan.');
            redirect('pelanggan.php');
        }
        $id = int_valid(post_str('id'));
        try {
            $stmt = $db->prepare('DELETE FROM pelanggan WHERE id_pelanggan = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            flash_set('sukses', 'Pelanggan dihapus.');
        } catch (mysqli_sql_exception $ex) {
            flash_set('error', $ex->getCode() === 1451
                ? 'Pelanggan sudah punya transaksi, tidak bisa dihapus.'
                : 'Gagal menghapus pelanggan.');
        }
        redirect('pelanggan.php');
    }

    // simpan (tambah / ubah)
    $id      = int_valid(post_str('id'));
    $nama    = post_str('nama_pelanggan');
    $alamat  = post_str('alamat');
    $telepon = post_str('telepon');
    $email   = post_str('email');

    if ($nama === '' || mb_strlen($nama) > 100) {
        $errors[] = 'Nama pelanggan wajib diisi (maksimal 100 karakter).';
    }
    if (mb_strlen($alamat) > 200) {
        $errors[] = 'Alamat maksimal 200 karakter.';
    }
    if ($telepon !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $telepon)) {
        $errors[] = 'Nomor telepon tidak valid (6-20 digit).';
    }
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100)) {
        $errors[] = 'Format email tidak valid.';
    }

    if (!$errors) {
        $al = $alamat === '' ? null : $alamat;
        $tp = $telepon === '' ? null : $telepon;
        $em = $email === '' ? null : $email;
        if ($id) {
            $stmt = $db->prepare('UPDATE pelanggan SET nama_pelanggan = ?, alamat = ?, telepon = ?, email = ? WHERE id_pelanggan = ?');
            $stmt->bind_param('ssssi', $nama, $al, $tp, $em, $id);
            $pesan = 'Pelanggan diperbarui.';
        } else {
            $stmt = $db->prepare('INSERT INTO pelanggan (nama_pelanggan, alamat, telepon, email) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('ssss', $nama, $al, $tp, $em);
            $pesan = 'Pelanggan ditambahkan.';
        }
        $stmt->execute();
        $stmt->close();
        flash_set('sukses', $pesan);
        redirect('pelanggan.php');
    }

    $aksi = $id ? 'ubah' : 'tambah';
    $data = ['id_pelanggan' => $id, 'nama_pelanggan' => $nama, 'alamat' => $alamat, 'telepon' => $telepon, 'email' => $email];
}

// ---------- DATA UNTUK FORM ----------
if (in_array($aksi, ['tambah', 'ubah'], true) && !isset($data)) {
    $data = ['id_pelanggan' => null, 'nama_pelanggan' => '', 'alamat' => '', 'telepon' => '', 'email' => ''];
    if ($aksi === 'ubah') {
        $id = int_valid((string)($_GET['id'] ?? ''));
        $stmt = $db->prepare('SELECT * FROM pelanggan WHERE id_pelanggan = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            flash_set('error', 'Pelanggan tidak ditemukan.');
            redirect('pelanggan.php');
        }
        $data = array_map(fn($v) => (string)$v, $row);
        $data['id_pelanggan'] = (int)$row['id_pelanggan'];
    }
}

// ---------- DATA UNTUK LIST ----------
$daftar = [];
$q = trim((string)($_GET['q'] ?? ''));
if ($aksi === 'list') {
    $like = '%' . $q . '%';
    $stmt = $db->prepare(
        'SELECT id_pelanggan, nama_pelanggan, alamat, telepon, email
           FROM pelanggan
          WHERE nama_pelanggan LIKE ? OR telepon LIKE ?
          ORDER BY nama_pelanggan'
    );
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $daftar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$judul = 'Pelanggan';
require __DIR__ . '/includes/header.php';
?>

<?php if ($aksi === 'list'): ?>
    <div class="bar-atas">
        <form method="get">
            <input type="text" name="q" placeholder="Cari nama / telepon..." value="<?= e($q) ?>">
            <button class="btn btn-biru">Cari</button>
        </form>
        <a href="pelanggan.php?aksi=tambah" class="btn">+ Tambah Pelanggan</a>
    </div>
    <div class="tabel-bungkus">
        <table>
            <thead><tr><th>Nama</th><th>Alamat</th><th>Telepon</th><th>Email</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php if (!$daftar): ?><tr><td colspan="5" class="kosong">Belum ada pelanggan.</td></tr><?php endif; ?>
            <?php foreach ($daftar as $r): ?>
                <tr>
                    <td><?= e($r['nama_pelanggan']) ?></td>
                    <td><?= e($r['alamat']) ?></td>
                    <td><?= e($r['telepon']) ?></td>
                    <td><?= e($r['email']) ?></td>
                    <td class="aksi">
                        <a href="pelanggan.php?aksi=ubah&id=<?= (int)$r['id_pelanggan'] ?>" class="btn btn-kecil btn-biru">Ubah</a>
                        <?php if (user_level() === 'admin'): ?>
                        <form method="post" onsubmit="return confirm('Hapus pelanggan ini?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="op" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$r['id_pelanggan'] ?>">
                            <button class="btn btn-kecil btn-merah">Hapus</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <div class="kartu">
        <h2><?= $data['id_pelanggan'] ? 'Ubah' : 'Tambah' ?> Pelanggan</h2>
        <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="simpan">
            <input type="hidden" name="id" value="<?= (int)$data['id_pelanggan'] ?>">
            <div class="form-grid">
                <div class="form-grup penuh">
                    <label for="nama_pelanggan">Nama Pelanggan</label>
                    <input type="text" id="nama_pelanggan" name="nama_pelanggan" maxlength="100" value="<?= e($data['nama_pelanggan']) ?>" required>
                </div>
                <div class="form-grup penuh">
                    <label for="alamat">Alamat</label>
                    <input type="text" id="alamat" name="alamat" maxlength="200" value="<?= e($data['alamat']) ?>">
                </div>
                <div class="form-grup">
                    <label for="telepon">Telepon</label>
                    <input type="text" id="telepon" name="telepon" maxlength="20" value="<?= e($data['telepon']) ?>">
                </div>
                <div class="form-grup">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" maxlength="100" value="<?= e($data['email']) ?>">
                </div>
            </div>
            <p>
                <button type="submit" class="btn">Simpan</button>
                <a href="pelanggan.php" class="btn btn-abu">Batal</a>
            </p>
        </form>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
