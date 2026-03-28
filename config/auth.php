<?php
// Inisialisasi session untuk autentikasi pengguna.
session_start();

define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'email' => $_SESSION['user_email'],
        'name' => $_SESSION['user_name'],
        'role' => $_SESSION['user_role']
    ];
}

function hasRole($role) {
    return isLoggedIn() && $_SESSION['user_role'] === $role;
}

// Guard untuk halaman yang wajib login.
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /web-lostfound/auth/login.php');
        exit;
    }
}

// Guard khusus halaman internal petugas.
function requirePetugas() {
    requireLogin();
    if (!hasRole('petugas')) {
        header('Location: /web-lostfound/dashboard/');
        exit;
    }
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Formatter tanggal Indonesia untuk tampilan UI.
function formatDateID($date, $format = 'd M Y H:i') {
    $months = [
        '01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr',
        '05' => 'Mei', '06' => 'Jun', '07' => 'Jul', '08' => 'Agu',
        '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'
    ];
    
    if (empty($date)) return '-';
    
    $timestamp = strtotime($date);
    $dayName = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    $day = $dayName[date('w', $timestamp)];
    $formattedDate = date('d', $timestamp);
    $month = $months[date('m', $timestamp)];
    $year = date('Y', $timestamp);
    $time = date('H:i', $timestamp);
    
    if ($format === 'd M Y H:i') {
        return "$formattedDate $month $year $time";
    } elseif ($format === 'd M Y') {
        return "$formattedDate $month $year";
    }
    return date($format, $timestamp);
}

// Mapping ikon kategori untuk konsistensi UI laporan/barang.
function getCategoryIcon($category) {
    $normalized = strtolower(trim((string) $category));

    if (strpos($normalized, 'perhiasan') !== false) return 'gem';
    if (strpos($normalized, 'elektronik') !== false) return 'smartphone';
    if (strpos($normalized, 'kendaraan') !== false || strpos($normalized, 'aksesori') !== false) return 'car';
    if (strpos($normalized, 'dokumen') !== false) return 'file-text';
    if (strpos($normalized, 'tas') !== false || strpos($normalized, 'dompet') !== false) return 'briefcase';
    return 'package';
}

?>
