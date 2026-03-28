<?php
require_once '../config/database.php';
require_once '../config/auth.php';

requirePetugas();

$id = intval($_GET['id'] ?? 0);

if ($id === 0) {
    header('Location: index.php?crud_status=error&crud_action=delete');
    exit;
}

$stmt = safePrepare($conn, 'SELECT foto FROM barang_temuan WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$barang = $result->fetch_assoc();
$stmt->close();

if (!$barang) {
    header('Location: index.php?crud_status=error&crud_action=delete');
    exit;
}

if ($barang && !empty($barang['foto'])) {
    $file_path = dirname(__DIR__) . '/uploads/' . $barang['foto'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

$stmt = safePrepare($conn, 'DELETE FROM barang_temuan WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();

if ($deleted) {
    header('Location: index.php?crud_status=success&crud_action=delete');
} else {
    header('Location: index.php?crud_status=error&crud_action=delete');
}
exit;
?>
