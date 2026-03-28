<?php
require_once '../config/database.php';
require_once '../config/auth.php';

requirePetugas();

$user = getCurrentUser();
$error = '';
$success = '';
$matching = null;
$edit_mode = false;

$id = intval($_GET['id'] ?? 0);

if ($id > 0) {
    $edit_mode = true;
    $stmt = safePrepare($conn, 'SELECT * FROM matching WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $matching = $result->fetch_assoc();
    $stmt->close();
    
    if (!$matching) {
        header('Location: index.php?crud_status=error&crud_action=update');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    
    if ($action === 'delete' && $edit_mode && $matching) {
        $stmt = safePrepare($conn, 'DELETE FROM matching WHERE id = ?');
        $stmt->bind_param('i', $id);
        
        if ($stmt->execute()) {
            header('Location: index.php?crud_status=success&crud_action=delete');
            exit;
        } else {
            $error = 'Gagal menghapus data. Silakan coba lagi.';
        }
        $stmt->close();
    } else {
        $barang_id = intval($_POST['barang_id'] ?? 0);
        $laporan_id = intval($_POST['laporan_id'] ?? 0);
        $cocok_score = intval($_POST['cocok_score'] ?? 0);
        $catatan = trim($_POST['catatan'] ?? '');
        $status = trim($_POST['status'] ?? 'pending');
        
        if ($barang_id === 0 || $laporan_id === 0 || $cocok_score < 0 || $cocok_score > 100) {
            $error = 'Data tidak valid';
        } else {
            if ($edit_mode && $matching) {
                $stmt = safePrepare($conn, '
                    UPDATE matching 
                    SET cocok_score = ?, catatan = ?, status = ?
                    WHERE id = ?
                ');
                $stmt->bind_param('issi', $cocok_score, $catatan, $status, $id);
                
                if ($stmt->execute()) {
                    header('Location: index.php?crud_status=success&crud_action=update');
                    exit;
                } else {
                    $error = 'Gagal memperbarui data. Silakan coba lagi.';
                }
                $stmt->close();
            } else {
                // Cegah duplikasi pasangan barang-laporan.
                $stmt = safePrepare($conn, 'SELECT id FROM matching WHERE barang_id = ? AND laporan_id = ?');
                $stmt->bind_param('ii', $barang_id, $laporan_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = 'Pencocokan untuk barang dan laporan ini sudah ada';
                } else {
                    $stmt = safePrepare($conn, '
                        INSERT INTO matching (barang_id, laporan_id, cocok_score, catatan, status, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->bind_param('iiissi', $barang_id, $laporan_id, $cocok_score, $catatan, $status, $user['id']);
                    
                    if ($stmt->execute()) {
                        // Sinkronisasi status proses setelah matching dibuat.
                        $conn->query('UPDATE barang_temuan SET status = "dicocokkan" WHERE id = ' . $barang_id);
                        
                        $conn->query('UPDATE laporan_kehilangan SET status = "ditemukan" WHERE id = ' . $laporan_id);

                        header('Location: index.php?crud_status=success&crud_action=create');
                        exit;
                    } else {
                        $error = 'Gagal menambahkan data. Silakan coba lagi.';
                    }
                }
                $stmt->close();
            }
        }
    }
}

// Handle delete dari URL parameter (dari index.php)
$delete_action = trim($_GET['action'] ?? '');
if ($delete_action === 'delete' && $edit_mode && $matching) {
    $stmt = safePrepare($conn, 'DELETE FROM matching WHERE id = ?');
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        header('Location: index.php?crud_status=success&crud_action=delete');
        exit;
    } else {
        header('Location: index.php?crud_status=error&crud_action=delete');
        exit;
    }
    $stmt->close();
}

$current_barang_id = $edit_mode && !empty($matching['barang_id']) ? (int)$matching['barang_id'] : 0;
$current_laporan_id = $edit_mode && !empty($matching['laporan_id']) ? (int)$matching['laporan_id'] : 0;

$barang_query = 'SELECT * FROM barang_temuan WHERE (status IN ("disimpan", "dicocokkan")';
if ($current_barang_id > 0) {
    $barang_query .= ' OR id = ' . $current_barang_id;
}
$barang_query .= ') ORDER BY nama_barang';
$barang_result = $conn->query($barang_query);

$laporan_query = 'SELECT * FROM laporan_kehilangan WHERE (status IN ("dilaporkan", "ditemukan")';
if ($current_laporan_id > 0) {
    $laporan_query .= ' OR id = ' . $current_laporan_id;
}
$laporan_query .= ') ORDER BY nama_barang';
$laporan_result = $conn->query($laporan_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit_mode ? 'Edit' : 'Buat' ?> Pencocokan - TemuBalik</title>
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

        .preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-top: -8px;
            margin-bottom: 24px;
        }

        .preview-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 14px;
            background: #fafafa;
        }

        .preview-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-slate);
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 10px;
        }

        .preview-media {
            width: 100%;
            height: 160px;
            border-radius: 10px;
            background: #f1f5f9;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        .preview-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .preview-name {
            font-weight: 700;
            margin-bottom: 4px;
            font-size: 0.95rem;
        }

        .preview-category {
            margin: 0;
            color: var(--text-slate);
            font-size: 0.85rem;
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

        .score-display {
            background: var(--soft-purple);
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: 700;
            color: var(--primary-purple);
            text-align: center;
            font-size: 1.1rem;
        }

        .form-range {
            height: 8px;
            border-radius: 4px;
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
                <a href="index.php" class="nav-link active">
                    <i data-lucide="zap" width="20"></i> Pencocokan
                </a>
            </li>
            <li class="nav-item">
                <a href="../verifikasi/index.php" class="nav-link">
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
            <h1 class="page-title">⚡ <?= $edit_mode ? 'Edit' : 'Buat' ?> Pencocokan</h1>
            <p class="page-subtitle">Cocokkan barang temuan dengan laporan kehilangan</p>
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
            <form method="POST">
                <div class="form-row-spacing">
                    <div class="form-group">
                        <label class="form-label">Barang Temuan <span class="required-indicator">*</span></label>
                        <select class="form-select" id="barangSelect" name="barang_id" required>
                            <option value="">Pilih Barang Temuan</option>
                            <?php
                            while ($b = $barang_result->fetch_assoc()) {
                                $selected = ($edit_mode && (int)$matching['barang_id'] == (int)$b['id']) ? 'selected' : '';
                                $b_icon = getCategoryIcon($b['kategori'] ?? '');
                                $b_has_photo = !empty($b['foto']) && file_exists(dirname(__DIR__) . '/uploads/' . $b['foto']);
                                $b_photo_url = $b_has_photo ? '../uploads/' . $b['foto'] : '';
                                ?>
                                <option value="<?= (int)$b['id'] ?>"
                                        data-nama="<?= htmlspecialchars($b['nama_barang']) ?>"
                                        data-kategori="<?= htmlspecialchars($b['kategori']) ?>"
                                        data-icon="<?= htmlspecialchars($b_icon) ?>"
                                        data-foto="<?= htmlspecialchars($b_photo_url) ?>"
                                        <?= $selected ?>>
                                    <?= htmlspecialchars($b['nama_barang']) ?> (<?= htmlspecialchars($b['kategori']) ?>)
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Laporan Kehilangan <span class="required-indicator">*</span></label>
                        <select class="form-select" id="laporanSelect" name="laporan_id" required>
                            <option value="">Pilih Laporan Kehilangan</option>
                            <?php
                            while ($l = $laporan_result->fetch_assoc()) {
                                $selected = ($edit_mode && (int)$matching['laporan_id'] == (int)$l['id']) ? 'selected' : '';
                                $l_icon = getCategoryIcon($l['kategori'] ?? '');
                                $l_has_photo = !empty($l['foto']) && file_exists(dirname(__DIR__) . '/uploads/' . $l['foto']);
                                $l_photo_url = $l_has_photo ? '../uploads/' . $l['foto'] : '';
                                ?>
                                <option value="<?= (int)$l['id'] ?>"
                                        data-nama="<?= htmlspecialchars($l['nama_barang']) ?>"
                                        data-kategori="<?= htmlspecialchars($l['kategori']) ?>"
                                        data-icon="<?= htmlspecialchars($l_icon) ?>"
                                        data-foto="<?= htmlspecialchars($l_photo_url) ?>"
                                        <?= $selected ?>>
                                    <?= htmlspecialchars($l['nama_barang']) ?> (<?= htmlspecialchars($l['kategori']) ?>)
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="preview-grid">
                    <div class="preview-card">
                        <div class="preview-title">Preview</div>
                        <div class="preview-media">
                            <img id="previewBarangImg" class="preview-photo" alt="Barang Temuan">
                            <div id="previewBarangIconWrap"><i data-lucide="package" width="36" class="text-slate"></i></div>
                        </div>
                        <p class="preview-name" id="previewBarangName">Belum dipilih</p>
                        <p class="preview-category" id="previewBarangCategory">-</p>
                    </div>
                    <div class="preview-card">
                        <div class="preview-title">Preview</div>
                        <div class="preview-media">
                            <img id="previewLaporanImg" class="preview-photo" alt="Laporan Kehilangan">
                            <div id="previewLaporanIconWrap"><i data-lucide="file-text" width="36" class="text-slate"></i></div>
                        </div>
                        <p class="preview-name" id="previewLaporanName">Belum dipilih</p>
                        <p class="preview-category" id="previewLaporanCategory">-</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Cocok Score (%) <span class="required-indicator">*</span></label>
                    <div style="display: grid; grid-template-columns: 1fr 80px; gap: 12px;">
                        <input type="range" class="form-range" id="scoreRange" name="cocok_score" min="0" max="100" value="<?= $edit_mode ? $matching['cocok_score'] : '50' ?>" required>
                        <div class="score-display" id="scoreDisplay"><?= $edit_mode ? $matching['cocok_score'] : '50' ?>%</div>
                    </div>
                    <small class="text-muted" style="display: block; margin-top: 8px;">Tingkat kecocokan antara 0-100%</small>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="pending" <?= !$edit_mode || $matching['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="cocok" <?= $edit_mode && $matching['status'] === 'cocok' ? 'selected' : '' ?>>Cocok</option>
                        <option value="tidak_cocok" <?= $edit_mode && $matching['status'] === 'tidak_cocok' ? 'selected' : '' ?>>Tidak Cocok</option>
                        <option value="dikonfirmasi" <?= $edit_mode && $matching['status'] === 'dikonfirmasi' ? 'selected' : '' ?>>Dikonfirmasi</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan</label>
                    <textarea class="form-control" name="catatan" rows="5" placeholder="Masukkan catatan pencocokan..."><?= $edit_mode ? htmlspecialchars($matching['catatan']) : '' ?></textarea>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn-submit">
                        <i data-lucide="save" width="18"></i> <?= $edit_mode ? 'Simpan Perubahan' : 'Simpan' ?>
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

        const scoreRange = document.getElementById('scoreRange');
        const scoreDisplay = document.getElementById('scoreDisplay');

        const barangSelect = document.getElementById('barangSelect');
        const laporanSelect = document.getElementById('laporanSelect');

        function updatePreview(selectEl, imgId, iconWrapId, nameId, categoryId, defaultIcon) {
            if (!selectEl) return;

            const option = selectEl.options[selectEl.selectedIndex];
            const foto = option?.dataset?.foto || '';
            const icon = option?.dataset?.icon || defaultIcon;
            const nama = option?.dataset?.nama || 'Belum dipilih';
            const kategori = option?.dataset?.kategori || '-';

            const imgEl = document.getElementById(imgId);
            const iconWrap = document.getElementById(iconWrapId);
            const nameEl = document.getElementById(nameId);
            const categoryEl = document.getElementById(categoryId);

            nameEl.textContent = nama;
            categoryEl.textContent = kategori;

            if (foto) {
                imgEl.src = foto;
                imgEl.style.display = 'block';
                iconWrap.style.display = 'none';
                imgEl.onerror = function() {
                    this.style.display = 'none';
                    iconWrap.style.display = 'flex';
                };
            } else {
                imgEl.style.display = 'none';
                iconWrap.style.display = 'flex';
            }

            iconWrap.innerHTML = `<i data-lucide="${icon}" width="36" class="text-slate"></i>`;
            lucide.createIcons();
        }

        scoreRange.addEventListener('input', () => {
            scoreDisplay.textContent = scoreRange.value + '%';
        });

        barangSelect?.addEventListener('change', () => {
            updatePreview(barangSelect, 'previewBarangImg', 'previewBarangIconWrap', 'previewBarangName', 'previewBarangCategory', 'package');
        });

        laporanSelect?.addEventListener('change', () => {
            updatePreview(laporanSelect, 'previewLaporanImg', 'previewLaporanIconWrap', 'previewLaporanName', 'previewLaporanCategory', 'file-text');
        });

        updatePreview(barangSelect, 'previewBarangImg', 'previewBarangIconWrap', 'previewBarangName', 'previewBarangCategory', 'package');
        updatePreview(laporanSelect, 'previewLaporanImg', 'previewLaporanIconWrap', 'previewLaporanName', 'previewLaporanCategory', 'file-text');
    </script>
</body>
</html>
