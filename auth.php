<?php
// Session, login guard, dan pengecekan level
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/helpers.php';

function sudah_login(): bool
{
    return isset($_SESSION['user']);
}

function user_level(): string
{
    return $_SESSION['user']['level'] ?? '';
}

function wajib_login(): void
{
    if (!sudah_login()) {
        redirect('login.php');
    }
}

// Halaman khusus admin (kasir diarahkan ke transaksi.php)
function wajib_admin(): void
{
    wajib_login();
    if (user_level() !== 'admin') {
        flash_set('error', 'Anda tidak punya akses ke halaman tersebut.');
        redirect('transaksi.php');
    }
}