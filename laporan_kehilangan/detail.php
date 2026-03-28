<?php
require_once '../config/database.php';
require_once '../config/auth.php';

requireLogin();

$user = getCurrentUser();
$pelapor_match_count = 0;
$id = intval($_GET['id'] ?? 0);

if ($id === 0) {
    header('Location: index.php');
    exit;
}

$stmt = safePrepare($conn, 'SELECT * FROM laporan_kehilangan WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$laporan = $result->fetch_assoc();
$stmt->close();

if (!$laporan) {
    header('Location: index.php');
    exit;
}

if ($user['role'] === 'pelapor' && $laporan['created_by'] !== $user['id']) {
    header('Location: index.php');
    exit;
}

if ($user['role'] === 'pelapor') {
    $stmt = safePrepare($conn, '
        SELECT COUNT(*) as total FROM matching m
        JOIN laporan_kehilangan lk ON m.laporan_id = lk.id
        WHERE lk.created_by = ? AND m.status IN ("pending", "dikonfirmasi")
    ');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $pelapor_match_count = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
}

$matching_query = 'SELECT m.*, bt.nama_barang, bt.kategori, bt.lokasi_ditemukan 
                   FROM matching m 
                   JOIN barang_temuan bt ON m.barang_id = bt.id 
                   WHERE m.laporan_id = ?';
$stmt = safePrepare($conn, $matching_query);
$stmt->bind_param('i', $id);
$stmt->execute();
$matching_result = $stmt->get_result();
$stmt->close();

$penyerahan_query = 'SELECT pb.* FROM penyerahan_barang pb
                     JOIN matching m ON pb.matching_id = m.id
                     WHERE m.laporan_id = ?';
$stmt = safePrepare($conn, $penyerahan_query);
$stmt->bind_param('i', $id);
$stmt->execute();
$penyerahan_result = $stmt->get_result();
$stmt->close();

$icon = getCategoryIcon($laporan['kategori'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Laporan Kehilangan - TemuBalik</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
        }

        .content-card {
            background: white;
            border-radius: 24px;
            padding: 32px;
            box-shadow: var(--card-shadow);
            margin-bottom: 24px;
        }

        .detail-section {
            margin-bottom: 28px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-slate);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .detail-value {
            font-size: 1.05rem;
            color: var(--text-main);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 28px;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .status-dilaporkan { background: #fee2e2; color: #991b1b; }
        .status-ditemukan { background: #fef9c3; color: #854d0e; }
        .status-diserahkan { background: #dcfce7; color: #166534; }
        .status-ditutup { background: #f3f4f6; color: #374151; }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .btn-action-primary {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-action-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(139, 92, 246, 0.3);
            color: white;
            text-decoration: none;
        }

        .btn-action-secondary {
            background: #f1f5f9;
            color: var(--text-slate);
            border: 1px solid #e2e8f0;
        }

        .btn-action-secondary:hover {
            background: #e2e8f0;
            color: var(--text-main);
            text-decoration: none;
        }

        .btn-action-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .btn-action-danger:hover {
            background: #fecaca;
            text-decoration: none;
        }

        .matching-item {
            background: var(--soft-purple);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 12px;
            border: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .matching-item:hover {
            border-color: var(--primary-purple);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.15);
        }

        .matching-item h6 {
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        .photo-container {
            margin: 24px 0;
        }

        .photo-container img {
            max-width: 100%;
            height: auto;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
        }

        .photo-frame {
            width: 100%;
            max-width: 420px;
            min-height: 240px;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .photo-frame-empty {
            color: var(--text-slate);
            text-align: center;
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .sidebar { width: 0; transform: translateX(-100%); }
            .main-content { margin-left: 0; padding: 20px; }
            .content-card { padding: 20px; }
            .page-title { font-size: 1.5rem; }
            .detail-grid { grid-template-columns: 1fr; }
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
            <?php if($user['role'] === 'petugas'): ?>
                <li class="nav-item">
                    <a href="../barang_temuan/index.php" class="nav-link">
                        <i data-lucide="package" width="20"></i> Barang Temuan
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../laporan_kehilangan/index.php" class="nav-link active">
                        <i data-lucide="file-text" width="20"></i> Laporan Masuk
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../matching/index.php" class="nav-link">
                        <i data-lucide="zap" width="20"></i> Pencocokan
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../verifikasi/index.php" class="nav-link">
                        <i data-lucide="check-square" width="20"></i> Verifikasi
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a href="../laporan_kehilangan/index.php" class="nav-link active">
                        <i data-lucide="file-text" width="20"></i> Laporan Saya
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../laporan_kehilangan/status.php" class="nav-link">
                        <i data-lucide="activity" width="20"></i> Status Tracking
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../matching/index.php" class="nav-link">
                        <i data-lucide="zap" width="20"></i> Kecocokan
                    </a>
                </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a href="../auth/logout.php" class="nav-link text-danger">
                    <i data-lucide="log-out" width="20"></i> Keluar
                </a>
            </li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">📋 Detail Laporan Kehilangan</h1>
            <a href="index.php" class="btn-action btn-action-secondary">
                <i data-lucide="arrow-left" width="18"></i> Kembali
            </a>
        </div>

        <div class="content-card">
            <div class="detail-grid">
                <div class="detail-section">
                    <div class="detail-label">Nama Barang</div>
                    <div class="detail-value"><?= htmlspecialchars($laporan['nama_barang']) ?></div>
                </div>

                <div class="detail-section">
                    <div class="detail-label">Kategori</div>
                    <div class="detail-value">
                        <span style="display: inline-flex; align-items: center; gap: 6px;">
                            <i data-lucide="<?= $icon ?>" width="18" style="color: var(--primary-purple);"></i>
                            <?= htmlspecialchars($laporan['kategori']) ?>
                        </span>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-label">Status</div>
                    <div class="detail-value">
                        <span class="status-badge status-<?= str_replace(' ', '', $laporan['status']) ?>">
                            <?= ucfirst($laporan['status']) ?>
                        </span>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="detail-label">Tanggal Hilang</div>
                    <div class="detail-value"><?= formatDateID($laporan['tanggal_hilang']) ?></div>
                </div>

                <div class="detail-section">
                    <div class="detail-label">Lokasi Hilang</div>
                    <div class="detail-value"><?= htmlspecialchars($laporan['lokasi_hilang']) ?></div>
                </div>

                <div class="detail-section">
                    <div class="detail-label">Tanggal Laporan</div>
                    <div class="detail-value"><?= formatDateID($laporan['created_at']) ?></div>
                </div>
            </div>

            <div class="detail-section" style="margin-top: 32px;">
                <div class="detail-label" style="margin-bottom: 12px;">Deskripsi Lengkap</div>
                <div class="detail-value" style="line-height: 1.6; color: var(--text-slate);">
                    <?= nl2br(htmlspecialchars($laporan['deskripsi'])) ?>
                </div>
            </div>

            <div class="photo-container">
                <div class="detail-label" style="margin-bottom: 12px;">Foto Barang</div>
                <div class="photo-frame">
                    <?php if (!empty($laporan['foto'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($laporan['foto']) ?>" alt="Foto Barang">
                    <?php else: ?>
                        <div class="photo-frame-empty">
                            <i data-lucide="image-off" width="24"></i>
                            <div style="margin-top: 8px;">Belum ada foto barang</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($user['role'] === 'pelapor' && $laporan['status'] === 'dilaporkan'): ?>
                <div class="action-buttons" style="margin-top: 32px; padding-top: 32px; border-top: 1px solid #e2e8f0;">
                    <a href="edit.php?id=<?= $id ?>" class="btn-action btn-action-primary">
                        <i data-lucide="edit" width="18"></i> Edit Laporan
                    </a>
                    <a href="delete.php?id=<?= $id ?>" class="btn-action btn-action-danger" onclick="return confirm('Yakin ingin menghapus laporan ini?')">
                        <i data-lucide="trash-2" width="18"></i> Hapus Laporan
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($matching_result->num_rows > 0): ?>
            <div class="content-card">
                <h3 style="font-weight: 800; margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="check-circle" width="24" style="color: #16a34a;"></i>
                    Barang yang Cocok
                </h3>
                <?php while ($match = $matching_result->fetch_assoc()): ?>
                    <div class="matching-item">
                        <h6 style="display: flex; align-items: center; gap: 8px;">
                            <i data-lucide="<?= getCategoryIcon($match['kategori'] ?? '') ?>" width="18" style="color: var(--primary-purple);"></i>
                            <?= htmlspecialchars($match['nama_barang']) ?>
                        </h6>
                        <p style="margin: 8px 0; color: var(--text-slate); font-size: 0.9rem;">
                            <strong>Kategori:</strong> <?= htmlspecialchars($match['kategori']) ?>
                        </p>
                        <p style="margin: 8px 0; color: var(--text-slate); font-size: 0.9rem;">
                            <strong>Ditemukan di:</strong> <?= htmlspecialchars($match['lokasi_ditemukan']) ?>
                        </p>
                        <div style="margin-top: 12px; display: inline-block; padding: 6px 12px; background: #ecfdf5; color: #065f46; border-radius: 8px; font-size: 0.85rem; font-weight: 700;">
                            🎯 Cocok Score: <?= $match['cocok_score'] ?>%
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <?php if ($penyerahan_result->num_rows > 0): ?>
            <div class="content-card">
                <h3 style="font-weight: 800; margin-bottom: 24px; display: flex; align-items: center; gap: 10px;">
                    <i data-lucide="package-check" width="24" style="color: #0891b2;"></i>
                    Data Penyerahan
                </h3>
                <?php while ($serah = $penyerahan_result->fetch_assoc()): ?>
                    <div style="background: #ecf8ff; border-radius: 16px; padding: 20px; margin-bottom: 12px; border: 1px solid #bae6fd;">
                        <div class="detail-grid" style="margin-bottom: 16px;">
                            <div class="detail-section">
                                <div class="detail-label">Tanggal Penyerahan</div>
                                <div class="detail-value"><?= formatDateID($serah['tanggal_serah']) ?></div>
                            </div>
                            <div class="detail-section">
                                <div class="detail-label">Nama Penerima</div>
                                <div class="detail-value"><?= htmlspecialchars($serah['penerima_nama']) ?></div>
                            </div>
                            <div class="detail-section">
                                <div class="detail-label">Telepon Penerima</div>
                                <div class="detail-value"><?= htmlspecialchars($serah['penerima_phone']) ?></div>
                            </div>
                            <div class="detail-section">
                                <div class="detail-label">Status Penyerahan</div>
                                <div class="detail-value">
                                    <span class="status-badge" style="<?php
                                        if ($serah['status'] === 'diserahkan') echo 'background: #dcfce7; color: #166534;';
                                        elseif ($serah['status'] === 'menunggu') echo 'background: #fef9c3; color: #854d0e;';
                                        elseif ($serah['status'] === 'ditolak') echo 'background: #fee2e2; color: #991b1b;';
                                    ?>">
                                        <?= ucfirst($serah['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($serah['catatan'])): ?>
                            <div style="margin: 12px 0; padding: 12px; background: white; border-radius: 12px;">
                                <strong style="color: var(--text-slate); font-size: 0.85rem; text-transform: uppercase;">Catatan:</strong>
                                <p style="margin: 6px 0 0 0; color: var(--text-main);"><?= nl2br(htmlspecialchars($serah['catatan'])) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($serah['foto_serah'])): ?>
                            <div style="margin-top: 12px;">
                                <strong style="color: var(--text-slate); font-size: 0.85rem; text-transform: uppercase;">Bukti Penyerahan:</strong>
                                <img src="../uploads/<?= htmlspecialchars($serah['foto_serah']) ?>" alt="Bukti Penyerahan" style="max-width: 250px; border-radius: 12px; margin-top: 8px; box-shadow: var(--card-shadow);">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
