<?php
require_once '../config/database.php';
require_once '../config/auth.php';

requireLogin();

$user = getCurrentUser();
if (($user['role'] ?? '') !== 'petugas') {
    header('Location: ../dashboard/index.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = safePrepare($conn, 'SELECT * FROM barang_temuan WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$barang = $result->fetch_assoc();
$stmt->close();

if (!$barang) {
    header('Location: index.php');
    exit;
}

$icon = getCategoryIcon($barang['kategori'] ?? '');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Barang Temuan - TemuBalik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary-purple: #8b5cf6;
            --soft-purple: #f5f3ff;
            --bg-light: #f8fafc;
            --sidebar-width: 280px;
            --text-main: #0f172a;
            --text-slate: #64748b;
            --card-shadow: 0 10px 25px -5px rgba(139, 92, 246, 0.05);
            --primary-gradient: linear-gradient(135deg, #818cf8 0%, #a855f7 100%);
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-light); color: var(--text-main); }
        .sidebar { width: var(--sidebar-width); height: 100vh; position: fixed; left: 0; top: 0; background: white; border-right: 1px solid rgba(0,0,0,0.05); padding: 24px; z-index: 1000; }
        .brand-logo { font-size: 1.4rem; text-decoration: none; display: flex; align-items: center; margin-bottom: 40px; color: var(--text-main); }
        .brand-logo .temu { font-weight: 600; }
        .brand-logo .balik { font-weight: 800; color: var(--primary-purple); }
        .nav-menu { list-style: none; padding: 0; }
        .nav-item { margin-bottom: 8px; }
        .nav-link { display: flex; align-items: center; padding: 12px 16px; color: var(--text-slate); text-decoration: none; border-radius: 12px; font-weight: 600; transition: all 0.2s ease; gap: 12px; }
        .nav-link:hover, .nav-link.active { background: var(--soft-purple); color: var(--primary-purple); }

        .main-content { margin-left: var(--sidebar-width); padding: 40px; min-height: 100vh; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-size: 2rem; font-weight: 800; }

        .content-card { background: white; border-radius: 24px; padding: 32px; box-shadow: var(--card-shadow); }
        .detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 24px; }
        .detail-label { font-weight: 600; color: var(--text-slate); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .detail-value { font-size: 1.02rem; }
        .status-badge { display: inline-block; padding: 8px 14px; border-radius: 8px; font-size: 0.82rem; font-weight: 700; text-transform: uppercase; }
        .status-disimpan { background: #fef9c3; color: #854d0e; }
        .status-dicocokkan { background: #e0f2fe; color: #0369a1; }
        .status-diklaim { background: #dcfce7; color: #166534; }
        .status-diserahkan { background: #dcfce7; color: #166534; }

        .photo-frame { margin-top: 24px; border: 1px solid #e2e8f0; border-radius: 16px; max-width: 420px; min-height: 240px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
        .photo-frame img { width: 100%; height: 100%; object-fit: cover; }

        .btn-action { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 12px; font-weight: 700; font-size: 0.9rem; text-decoration: none; border: none; }
        .btn-action-secondary { background: #f1f5f9; color: var(--text-slate); border: 1px solid #e2e8f0; }
        .btn-action-primary { background: var(--primary-gradient); color: white; }

        @media (max-width: 768px) {
            .sidebar { width: 0; transform: translateX(-100%); }
            .main-content { margin-left: 0; padding: 20px; }
            .content-card { padding: 20px; }
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
            <li class="nav-item"><a href="../dashboard/index.php" class="nav-link"><i data-lucide="layout-grid" width="20"></i> Dashboard</a></li>
            <li class="nav-item"><a href="index.php" class="nav-link active"><i data-lucide="package" width="20"></i> Barang Temuan</a></li>
            <li class="nav-item"><a href="../laporan_kehilangan/index.php" class="nav-link"><i data-lucide="file-text" width="20"></i> Laporan Masuk</a></li>
            <li class="nav-item"><a href="../matching/index.php" class="nav-link"><i data-lucide="zap" width="20"></i> Pencocokan</a></li>
            <li class="nav-item"><a href="../verifikasi/index.php" class="nav-link"><i data-lucide="check-square" width="20"></i> Verifikasi</a></li>
            <li class="nav-item"><a href="../auth/logout.php" class="nav-link text-danger"><i data-lucide="log-out" width="20"></i> Keluar</a></li>
        </ul>
    </aside>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">📦 Detail Barang Temuan</h1>
            <a href="index.php" class="btn-action btn-action-secondary"><i data-lucide="arrow-left" width="18"></i> Kembali</a>
        </div>

        <div class="content-card">
            <div class="detail-grid">
                <div>
                    <div class="detail-label">Nama Barang</div>
                    <div class="detail-value"><?= htmlspecialchars($barang['nama_barang']) ?></div>
                </div>
                <div>
                    <div class="detail-label">Kategori</div>
                    <div class="detail-value"><i data-lucide="<?= $icon ?>" width="16" style="color: var(--primary-purple);"></i> <?= htmlspecialchars($barang['kategori']) ?></div>
                </div>
                <div>
                    <div class="detail-label">Status</div>
                    <div class="detail-value"><span class="status-badge status-<?= htmlspecialchars($barang['status']) ?>"><?= ucfirst($barang['status']) ?></span></div>
                </div>
                <div>
                    <div class="detail-label">Tanggal Ditemukan</div>
                    <div class="detail-value"><?= formatDateID($barang['tanggal_ditemukan']) ?></div>
                </div>
                <div>
                    <div class="detail-label">Lokasi Ditemukan</div>
                    <div class="detail-value"><?= htmlspecialchars($barang['lokasi_ditemukan']) ?></div>
                </div>
                <div>
                    <div class="detail-label">Tanggal Input</div>
                    <div class="detail-value"><?= formatDateID($barang['created_at']) ?></div>
                </div>
            </div>

            <div style="margin-top: 24px;">
                <div class="detail-label" style="margin-bottom: 8px;">Deskripsi</div>
                <div class="detail-value" style="color: var(--text-slate); line-height: 1.6;"><?= nl2br(htmlspecialchars($barang['deskripsi'] ?? '-')) ?></div>
            </div>

            <div style="margin-top: 24px;">
                <div class="detail-label" style="margin-bottom: 8px;">Foto Barang</div>
                <div class="photo-frame">
                    <?php if (!empty($barang['foto'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($barang['foto']) ?>" alt="Foto Barang">
                    <?php else: ?>
                        <div style="text-align:center; color: var(--text-slate);"><i data-lucide="image-off" width="22"></i><div style="margin-top:8px;">Belum ada foto</div></div>
                    <?php endif; ?>
                </div>
            </div>

            <div style="margin-top: 24px; display:flex; gap:10px;">
                <a href="edit.php?id=<?= $barang['id'] ?>" class="btn-action btn-action-primary"><i data-lucide="pencil" width="16"></i> Edit</a>
                <a href="delete.php?id=<?= $barang['id'] ?>" class="btn-action btn-action-secondary" onclick="return confirm('Yakin ingin menghapus?')"><i data-lucide="trash-2" width="16"></i> Hapus</a>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>
