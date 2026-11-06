<?php
// Fungsi bantu umum

const STOK_RENDAH = 5; // sama dengan batas di fn_status_stok()

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function rupiah($angka): string
{
    return 'Rp ' . number_format((float)$angka, 0, ',', '.');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// ---- Pesan flash ----
function flash_set(string $tipe, string $pesan): void
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}

// ---- CSRF ----
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $kirim = $_POST['csrf'] ?? '';
    if (!is_string($kirim) || !hash_equals($_SESSION['csrf'] ?? '', $kirim)) {
        http_response_code(419);
        exit('Token keamanan tidak valid. Silakan kembali dan muat ulang halaman.');
    }
}

// ---- Ambil input ----
function post_str(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

// Angka desimal >= 0 (menerima "65000" atau "65000.50"); null jika tidak valid
function angka_valid(string $v): ?float
{
    if ($v === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $v)) {
        return null;
    }
    return (float)$v;
}

// Bilangan bulat >= 0; null jika tidak valid
function int_valid(string $v): ?int
{
    if ($v === '' || !ctype_digit($v) || strlen($v) > 9) {
        return null;
    }
    return (int)$v;
}
