<?php
require_once '../config/database.php';
require_once '../config/auth.php';

requirePetugas();

$user = getCurrentUser();
$error = '';
$success = '';
$penyerahan = null;
$edit_mode = false;

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $edit_mode = true;
    $stmt = safePrepare($conn, 'SELECT * FROM penyerahan_barang WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $penyerahan = $result->fetch_assoc();
    $stmt->close();
    
    if (!$penyerahan) {
        header('Location: index.php?crud_status=error&crud_action=update');
        exit;
    }
}

$action = trim($_GET['action'] ?? '');
if ($action === 'delete' && $edit_mode && $penyerahan) {
    $stmt = safePrepare($conn, 'DELETE FROM penyerahan_barang WHERE id = ?');
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        header('Location: index.php?crud_status=success&crud_action=delete');
        exit;
    }

    header('Location: index.php?crud_status=error&crud_action=delete');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matching_id = intval($_POST['matching_id'] ?? 0);
    $tanggal_serah = trim($_POST['tanggal_serah'] ?? '');
    $penerima_nama = trim($_POST['penerima_nama'] ?? '');
    $penerima_phone = trim($_POST['penerima_phone'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');
    $status = trim($_POST['status'] ?? 'menunggu');
    $foto_serah = '';
    
    if ($matching_id === 0 || empty($tanggal_serah) || empty($penerima_nama) || empty($penerima_phone)) {
        $error = 'Semua field wajib diisi';
    } else {
        if (!empty($_FILES['foto_serah']['name'])) {
            $file = $_FILES['foto_serah'];
            $upload_dir = dirname(__DIR__) . '/uploads/';
            
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array(strtolower($file_ext), $allowed_ext)) {
                $error = 'Format file tidak didukung';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Ukuran file terlalu besar';
            } else {
                $foto_name = 'penyerahan_' . time() . '.' . $file_ext;
                $foto_path = $upload_dir . $foto_name;
                
                if (move_uploaded_file($file['tmp_name'], $foto_path)) {
                    $foto_serah = $foto_name;
                } else {
                    $error = 'Gagal upload file';
                }
            }
        } elseif ($edit_mode && $penyerahan) {
            $foto_serah = $penyerahan['foto_serah'];
        }
        
        if (empty($error)) {
            if ($edit_mode && $penyerahan) {
                $stmt = safePrepare($conn, '
                    UPDATE penyerahan_barang 
                    SET matching_id = ?, tanggal_serah = ?, penerima_nama = ?, 
                        penerima_phone = ?, catatan = ?, foto_serah = ?, status = ?, petugas_id = ?
                    WHERE id = ?
                ');
                $stmt->bind_param('isssssiii', $matching_id, $tanggal_serah, $penerima_nama, $penerima_phone, $catatan, $foto_serah, $status, $user['id'], $id);
                
                if ($stmt->execute()) {
                    if ($status === 'diserahkan') {
                        $conn->query('UPDATE matching SET status = "dikonfirmasi" WHERE id = ' . $matching_id);
                        $conn->query('UPDATE barang_temuan SET status = "diserahkan" WHERE id IN (SELECT barang_id FROM matching WHERE id = ' . $matching_id . ')');
                        $conn->query('UPDATE laporan_kehilangan SET status = "diserahkan" WHERE id IN (SELECT laporan_id FROM matching WHERE id = ' . $matching_id . ')');
                    }
                    header('Location: index.php?crud_status=success&crud_action=update');
                    exit;
                } else {
                    $error = 'Gagal memperbarui data. Silakan coba lagi.';
                }
                $stmt->close();
            } else {
                $stmt = safePrepare($conn, '
                    INSERT INTO penyerahan_barang 
                    (matching_id, tanggal_serah, penerima_nama, penerima_phone, catatan, foto_serah, status, petugas_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->bind_param('issssssi', $matching_id, $tanggal_serah, $penerima_nama, $penerima_phone, $catatan, $foto_serah, $status, $user['id']);
                
                if ($stmt->execute()) {
                    if ($status === 'diserahkan') {
                        $conn->query('UPDATE matching SET status = "dikonfirmasi" WHERE id = ' . $matching_id);
                        $conn->query('UPDATE barang_temuan SET status = "diserahkan" WHERE id IN (SELECT barang_id FROM matching WHERE id = ' . $matching_id . ')');
                        $conn->query('UPDATE laporan_kehilangan SET status = "diserahkan" WHERE id IN (SELECT laporan_id FROM matching WHERE id = ' . $matching_id . ')');
                    }
                    header('Location: index.php?crud_status=success&crud_action=create');
                    exit;
                } else {
                    $error = 'Gagal menambahkan data. Silakan coba lagi.';
                }
                $stmt->close();
            }
        }
    }
}

$current_matching_id = $edit_mode && $penyerahan ? (int) $penyerahan['matching_id'] : 0;

$matching_sql = '
    SELECT m.*, bt.nama_barang as barang_nama, lk.nama_barang as laporan_nama
    FROM matching m
    JOIN barang_temuan bt ON m.barang_id = bt.id
    JOIN laporan_kehilangan lk ON m.laporan_id = lk.id
    WHERE m.status IN ("cocok", "dikonfirmasi")';

if ($current_matching_id > 0) {
    $matching_sql .= ' OR m.id = ' . $current_matching_id;
}

$matching_sql .= ' ORDER BY m.created_at';
$matching_result = $conn->query($matching_sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_mode ? 'Edit' : 'Buat' ?> Penyerahan - TemuBalik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary-purple: #8b5cf6;
            --deep-purple: #6d28d9;
            --soft-purple: #f5f3ff;
            --bg-light: #f8fafc;
            --sidebar-width: 280px;
            --text-main: #0f172a;
            --text-slate: #64748b;
            --card-shadow: 0 10px 25px -5px rgba(139, 92, 246, 0.05);
            --primary-gradient: linear-gradient(135deg, #818cf8 0%, #a855f7 100%);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-main);
        }

        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0; top: 0;
            background: white;
            border-right: 1px solid rgba(0,0,0,0.05);
            padding: 24px;
            z-index: 1000;
        }

        .brand-logo {
            font-size: 1.4rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            margin-bottom: 40px;
            color: var(--text-main);
        }
        .brand-logo .temu { font-weight: 600; }
        .brand-logo .balik { font-weight: 800; color: var(--primary-purple); }

        .nav-menu { list-style: none; padding: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: var(--text-slate);
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
            gap: 12px;
        }
        .nav-link:hover, .nav-link.active {
            background: var(--soft-purple);
            color: var(--primary-purple);
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 40px;
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: var(--text-slate);
            font-size: 1rem;
        }

        .form-card {
            background: white;
            border-radius: 24px;
            padding: 32px;
            box-shadow: var(--card-shadow);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .form-control, .form-select {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-row-spacing {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
        }

        .upload-area {
            border: 2px dashed #c4b5fd;
            border-radius: 16px;
            background: #faf7ff;
            padding: 28px 18px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .upload-area:hover,
        .upload-area.dragover {
            background: #f3ecff;
            border-color: var(--primary-purple);
        }

        .upload-area input {
            display: none;
        }

        .btn-submit {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 12px 32px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(139, 92, 246, 0.3);
            color: white;
        }

        .btn-cancel {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: var(--text-slate);
            padding: 12px 32px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
            color: var(--text-main);
        }

        .alert-custom {
            border-radius: 12px;
            border: 1px solid;
            padding: 14px 18px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger-custom {
            background: #fee2e2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .alert-success-custom {
            background: #dcfce7;
            border-color: #bbf7d0;
            color: #166534;
        }

        .required-indicator {
            color: #ef4444;
        }

        @media (max-width: 768px) {
            .sidebar { width: 0; transform: translateX(-100%); }
            .main-content { margin-left: 0; padding: 20px; }
            .form-card { padding: 20px; }
            .page-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <a class="brand-logo" href="../dashboard/index.php">
            <i data-lucide="search" class="me-2" style="color: var(--primary-purple); stroke-width: 3;"></i>
            <span class="temu">Temu</span><span class="balik">Balik</span>
        </a>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="../dashboard/index.php" class="nav-link">
                    <i data-lucide="layout-grid" width="20"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="../barang_temuan/index.php" class="nav-link">
                    <i data-lucide="package" width="20"></i> Barang Temuan
                </a>
            </li>
            <li class="nav-item">
                <a href="../laporan_kehilangan/index.php" class="nav-link">
                    <i data-lucide="file-text" width="20"></i> Laporan Masuk
                </a>
            </li>
            <li class="nav-item">
                <a href="../matching/index.php" class="nav-link">
                    <i data-lucide="zap" width="20"></i> Pencocokan
                </a>
            </li>
            <li class="nav-item">
                <a href="index.php" class="nav-link active">
                    <i data-lucide="check-square" width="20"></i> Verifikasi
                </a>
            </li>
            <li class="nav-item">
                <a href="../auth/logout.php" class="nav-link text-danger">
                    <i data-lucide="log-out" width="20"></i> Keluar
                </a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <section class="page-header">
            <h1 class="page-title">✓ <?= $edit_mode ? 'Edit' : 'Buat' ?> Penyerahan</h1>
            <p class="page-subtitle">Verifikasi dan catat penyerahan barang kepada pelapor</p>
        </section>

        <?php if (!empty($error)): ?>
            <div class="alert-custom alert-danger-custom">
                <i data-lucide="alert-circle" width="20"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert-custom alert-success-custom">
                <i data-lucide="check-circle" width="20"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Pencocokan <span class="required-indicator">*</span></label>
                    <select class="form-select" name="matching_id" required>
                        <option value="">Pilih Pencocokan</option>
                        <?php
                        while ($m = $matching_result->fetch_assoc()) {
                            $selected = ($edit_mode && (int) $penyerahan['matching_id'] === (int) $m['id']) ? 'selected' : '';
                            echo '<option value="' . $m['id'] . '" ' . $selected . '>';
                            echo htmlspecialchars($m['barang_nama']) . ' ↔ ' . htmlspecialchars($m['laporan_nama']);
                            echo '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Tanggal Penyerahan <span class="required-indicator">*</span></label>
                    <input type="datetime-local" class="form-control" name="tanggal_serah" value="<?= $edit_mode ? date('Y-m-d\TH:i', strtotime($penyerahan['tanggal_serah'])) : '' ?>" required>
                </div>

                <div class="form-row-spacing">
                    <div class="form-group">
                        <label class="form-label">Nama Penerima <span class="required-indicator">*</span></label>
                        <input type="text" class="form-control" name="penerima_nama" value="<?= $edit_mode ? htmlspecialchars($penyerahan['penerima_nama']) : '' ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Telepon Penerima <span class="required-indicator">*</span></label>
                        <input type="tel" class="form-control" name="penerima_phone" value="<?= $edit_mode ? htmlspecialchars($penyerahan['penerima_phone']) : '' ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Status <span class="required-indicator">*</span></label>
                    <select class="form-select" name="status" required>
                        <option value="menunggu" <?= !$edit_mode || $penyerahan['status'] === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                        <option value="diserahkan" <?= $edit_mode && $penyerahan['status'] === 'diserahkan' ? 'selected' : '' ?>>Diserahkan</option>
                        <option value="ditolak" <?= $edit_mode && $penyerahan['status'] === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea class="form-control" name="catatan" rows="4" placeholder="Masukkan catatan penyerahan..."><?= $edit_mode ? htmlspecialchars($penyerahan['catatan']) : '' ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Foto Bukti Penyerahan</label>
                    <?php if ($edit_mode && !empty($penyerahan['foto_serah'])): ?>
                        <div style="margin-bottom: 12px;">
                            <img src="../uploads/<?= htmlspecialchars($penyerahan['foto_serah']) ?>" alt="Foto Penyerahan" class="preview-image" style="width: 120px; height: 120px; border-radius: 12px; object-fit: cover; border: 2px solid #e2e8f0;">
                        </div>
                    <?php endif; ?>
                    <div class="upload-area" id="fileDropzone">
                        <input type="file" name="foto_serah" accept="image/*" id="fotoInput">
                        <i data-lucide="image-plus" width="48" height="48" class="text-slate mb-3"></i>
                        <h6 class="fw-700 mb-1">Klik atau Drag Foto ke Sini</h6>
                        <p class="text-slate small mb-0" id="uploadHint">Format JPG, PNG (Maks 5MB)</p>
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn-submit">
                        <i data-lucide="save" width="18"></i> Simpan Penyerahan
                    </button>
                    <a href="index.php" class="btn-cancel">
                        <i data-lucide="x" width="18"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();

        const fileInput = document.getElementById('fotoInput');
        const fileLabel = document.getElementById('fileDropzone');
        const uploadHint = document.getElementById('uploadHint');

        fileLabel.addEventListener('click', () => fileInput.click());

        fileLabel.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileLabel.classList.add('dragover');
        });

        fileLabel.addEventListener('dragleave', () => {
            fileLabel.classList.remove('dragover');
        });

        fileLabel.addEventListener('drop', (e) => {
            e.preventDefault();
            fileLabel.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateUploadText();
            }
        });

        fileInput.addEventListener('change', updateUploadText);

        function updateUploadText() {
            if (fileInput.files.length > 0) {
                uploadHint.textContent = 'File terpilih: ' + fileInput.files[0].name;
            }
        }
    </script>
</body>
</html>
