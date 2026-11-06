<?php
require_once __DIR__ . '/includes/auth.php';
wajib_admin();

if (user_level() !== 'admin') {
    flash_set('error', 'Akses ditolak. Kasir hanya dapat mengakses halaman transaksi dan nota.');
    redirect('transaksi.php');
}

$aksi = $_GET['aksi'] ?? 'list';
$errors = [];
$levelValid = ['admin', 'kasir'];
$statusValid = ['aktif', 'nonaktif'];

// ---------- PROSES POST (simpan / hapus) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post_str('op');

    if ($op === 'hapus') {
        $id = int_valid(post_str('id'));
        if ($id === (int)$_SESSION['user']['id_user']) {
            flash_set('error', 'Anda tidak bisa menghapus akun yang sedang dipakai.');
            redirect('user.php');
        }
        try {
            $stmt = $db->prepare('DELETE FROM users WHERE id_user = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            flash_set('sukses', 'Pengguna dihapus.');
        } catch (mysqli_sql_exception $ex) {
            flash_set('error', $ex->getCode() === 1451
                ? 'Pengguna sudah punya transaksi. Nonaktifkan saja akunnya.'
                : 'Gagal menghapus pengguna.');
        }
        redirect('user.php');
    }

    // simpan (tambah / ubah)
    $id       = int_valid(post_str('id'));
    $nama     = post_str('nama_user');
    $username = post_str('username');
    $password = (string)($_POST['password'] ?? '');
    $level    = post_str('level');
    $status   = post_str('status');

    if ($nama === '' || mb_strlen($nama) > 100) {
        $errors[] = 'Nama wajib diisi (maksimal 100 karakter).';
    }
    if (!preg_match('/^[A-Za-z0-9_.]{3,30}$/', $username)) {
        $errors[] = 'Username 3-30 karakter (huruf, angka, titik, garis bawah).';
    }
    if (!$id && strlen($password) < 6) {
        $errors[] = 'Password wajib diisi minimal 6 karakter.';
    }
    if ($id && $password !== '' && strlen($password) < 6) {
        $errors[] = 'Password baru minimal 6 karakter.';
    }
    if (!in_array($level, $levelValid, true)) {
        $errors[] = 'Level tidak valid.';
    }
    if (!in_array($status, $statusValid, true)) {
        $errors[] = 'Status tidak valid.';
    }
    // admin tidak boleh menonaktifkan / menurunkan akunnya sendiri
    if ($id && $id === (int)$_SESSION['user']['id_user'] && ($level !== 'admin' || $status !== 'aktif')) {
        $errors[] = 'Anda tidak bisa menonaktifkan atau menurunkan level akun sendiri.';
    }

    if (!$errors) {
        $cek = $db->prepare('SELECT id_user FROM users WHERE username = ? AND id_user <> ? LIMIT 1');
        $idCek = $id ?? 0;
        $cek->bind_param('si', $username, $idCek);
        $cek->execute();
        if ($cek->get_result()->num_rows > 0) {
            $errors[] = 'Username sudah dipakai.';
        }
        $cek->close();
    }

    if (!$errors) {
        if ($id) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE users SET nama_user = ?, username = ?, password = ?, level = ?, status = ? WHERE id_user = ?');
                $stmt->bind_param('sssssi', $nama, $username, $hash, $level, $status, $id);
            } else {
                $stmt = $db->prepare('UPDATE users SET nama_user = ?, username = ?, level = ?, status = ? WHERE id_user = ?');
                $stmt->bind_param('ssssi', $nama, $username, $level, $status, $id);
            }
            $pesan = 'Pengguna diperbarui.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (nama_user, username, password, level, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sssss', $nama, $username, $hash, $level, $status);
            $pesan = 'Pengguna ditambahkan.';
        }
        $stmt->execute();
        $stmt->close();
        flash_set('sukses', $pesan);
        redirect('user.php');
    }

    $aksi = $id ? 'ubah' : 'tambah';
    $data = ['id_user' => $id, 'nama_user' => $nama, 'username' => $username, 'level' => $level, 'status' => $status];
}

// ---------- DATA UNTUK FORM ----------
if (in_array($aksi, ['tambah', 'ubah'], true) && !isset($data)) {
    $data = ['id_user' => null, 'nama_user' => '', 'username' => '', 'level' => 'kasir', 'status' => 'aktif'];
    if ($aksi === 'ubah') {
        $id = int_valid((string)($_GET['id'] ?? ''));
        $stmt = $db->prepare('SELECT id_user, nama_user, username, level, status FROM users WHERE id_user = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            flash_set('error', 'Pengguna tidak ditemukan.');
            redirect('user.php');
        }
        $data = $row;
    }
}

// ---------- DATA UNTUK LIST ----------
$daftar = [];
if ($aksi === 'list') {
    $daftar = $db->query('SELECT id_user, nama_user, username, level, status FROM users ORDER BY nama_user')->fetch_all(MYSQLI_ASSOC);
}

$judul = 'Pengguna';
require __DIR__ . '/includes/header.php';
?>

<?php if ($aksi === 'list'): ?>
    <div class="bar-atas">
        <span></span>
        <a href="user.php?aksi=tambah" class="btn">+ Tambah Pengguna</a>
    </div>
    <div class="tabel-bungkus">
        <table>
            <thead><tr><th>Nama</th><th>Username</th><th>Level</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($daftar as $r): ?>
                <tr>
                    <td><?= e($r['nama_user']) ?></td>
                    <td><?= e($r['username']) ?></td>
                    <td><span class="badge badge-abu"><?= e(ucfirst($r['level'])) ?></span></td>
                    <td><span class="badge <?= $r['status'] === 'aktif' ? 'badge-aman' : 'badge-habis' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                    <td class="aksi">
                        <a href="user.php?aksi=ubah&id=<?= (int)$r['id_user'] ?>" class="btn btn-kecil btn-biru">Ubah</a>
                        <?php if ((int)$r['id_user'] !== (int)$_SESSION['user']['id_user']): ?>
                        <form method="post" onsubmit="return confirm('Hapus pengguna ini?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="op" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$r['id_user'] ?>">
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
        <h2><?= $data['id_user'] ? 'Ubah' : 'Tambah' ?> Pengguna</h2>
        <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="op" value="simpan">
            <input type="hidden" name="id" value="<?= (int)$data['id_user'] ?>">
            <div class="form-grid">
                <div class="form-grup">
                    <label for="nama_user">Nama Lengkap</label>
                    <input type="text" id="nama_user" name="nama_user" maxlength="100" value="<?= e($data['nama_user']) ?>" required>
                </div>
                <div class="form-grup">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" maxlength="30" value="<?= e($data['username']) ?>" required>
                </div>
                <div class="form-grup">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" <?= $data['id_user'] ? '' : 'required' ?>>
                    <?php if ($data['id_user']): ?><span class="bantuan">Kosongkan jika tidak ingin mengganti password.</span><?php endif; ?>
                </div>
                <div class="form-grup">
                    <label for="level">Level</label>
                    <select id="level" name="level">
                        <?php foreach ($levelValid as $lv): ?>
                            <option value="<?= e($lv) ?>" <?= $data['level'] === $lv ? 'selected' : '' ?>><?= e(ucfirst($lv)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grup">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <?php foreach ($statusValid as $st): ?>
                            <option value="<?= e($st) ?>" <?= $data['status'] === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <p>
                <button type="submit" class="btn">Simpan</button>
                <a href="user.php" class="btn btn-abu">Batal</a>
            </p>
        </form>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
