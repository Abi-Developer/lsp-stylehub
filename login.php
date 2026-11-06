<?php
require_once __DIR__ . '/includes/auth.php';

if (sudah_login()) {
    redirect('index.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post_str('username');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $db->prepare('SELECT id_user, nama_user, username, password, level, status FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Username atau password salah.';
        } elseif ($user['status'] !== 'aktif') {
            $error = 'Akun Anda tidak aktif. Hubungi admin.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id_user'   => (int)$user['id_user'],
                'nama_user' => $user['nama_user'],
                'username'  => $user['username'],
                'level'     => $user['level'],
            ];
            redirect('index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - StyleHub</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-body">
    <form method="post" class="login-kotak" autocomplete="off">
        <h1>Style<span style="color:#e8590c">Hub</span></h1>
        <div class="sub">Aplikasi Kasir Toko Fashion</div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?= csrf_field() ?>
        <div class="form-grup">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e($username) ?>" maxlength="30" required autofocus>
        </div>
        <div class="form-grup">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn">Masuk</button>
    </form>
</body>
</html>
