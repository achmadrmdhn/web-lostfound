<?php
require_once '../config/database.php';
require_once '../config/auth.php';

requireLogin();
if ($_SESSION['user_role'] !== 'pelapor') {
    header('Location: index.php?crud_status=error&crud_action=delete');
    exit;
}

$user = getCurrentUser();
$id = intval($_GET['id'] ?? 0);

if ($id === 0) {
    header('Location: index.php?crud_status=error&crud_action=delete');
    exit;
}

$stmt = safePrepare($conn, 'SELECT foto FROM laporan_kehilangan WHERE id = ? AND created_by = ?');
$stmt->bind_param('ii', $id, $user['id']);
$stmt->execute();
$result = $stmt->get_result();
$laporan = $result->fetch_assoc();
$stmt->close();

if (!$laporan) {
    header('Location: index.php?crud_status=error&crud_action=delete');
    exit;
}

if ($laporan && !empty($laporan['foto'])) {
    $file_path = dirname(__DIR__) . '/uploads/' . $laporan['foto'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

$stmt = safePrepare($conn, 'DELETE FROM laporan_kehilangan WHERE id = ? AND created_by = ?');
$stmt->bind_param('ii', $id, $user['id']);
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
