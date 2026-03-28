<?php
require_once '../config/database.php';
require_once '../config/auth.php';

requireLogin();

$user = getCurrentUser();
$role = $user['role'];
$pelapor_match_count = 0;

$stats = [];
$recent_items = [];

if ($role === 'petugas') {
    $res1 = $conn->query('SELECT COUNT(*) as total FROM barang_temuan WHERE status = "disimpan"');
    $stats['barang_disimpan'] = (int) ($res1->fetch_assoc()['total'] ?? 0);
    
    $res2 = $conn->query('SELECT COUNT(*) as total FROM laporan_kehilangan WHERE status = "dilaporkan"');
    $stats['laporan_masuk'] = (int) ($res2->fetch_assoc()['total'] ?? 0);
    
    $res3 = $conn->query('SELECT COUNT(*) as total FROM matching');
    $stats['matching_total'] = (int) ($res3->fetch_assoc()['total'] ?? 0);
    
    $res4 = $conn->query('SELECT COUNT(*) as total FROM penyerahan_barang');
    $stats['verifikasi_total'] = (int) ($res4->fetch_assoc()['total'] ?? 0);

    $recent_query = 'SELECT id, nama_barang, kategori, status, created_at, foto FROM barang_temuan ORDER BY created_at DESC LIMIT 5';
    $recent_items = $conn->query($recent_query);
} else {
    $stmt = safePrepare($conn, 'SELECT COUNT(*) as total FROM laporan_kehilangan WHERE created_by = ?');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stats['laporan_saya'] = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    
    $stmt = safePrepare($conn, 'SELECT COUNT(*) as total FROM laporan_kehilangan WHERE created_by = ? AND status != "ditutup"');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stats['status_tracking'] = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    $stmt = safePrepare($conn, '
        SELECT COUNT(*) as total FROM matching m 
        JOIN laporan_kehilangan lk ON m.laporan_id = lk.id 
        WHERE lk.created_by = ? AND m.status IN ("pending", "cocok", "dikonfirmasi")
    ');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stats['kecocokan'] = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $pelapor_match_count = (int) $stats['kecocokan'];
    $stmt->close();

    $stmt = safePrepare($conn, 'SELECT id, nama_barang, kategori, status, created_at, foto FROM laporan_kehilangan WHERE created_by = ? ORDER BY created_at DESC LIMIT 5');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $recent_items = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - TemuBalik</title>
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
            overflow-x: hidden;
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
            transition: all 0.3s ease;
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

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .search-bar {
            background: white;
            border-radius: 14px;
            padding: 10px 18px;
            display: flex;
            align-items: center;
            width: 100%;
            max-width: 400px;
            box-shadow: var(--card-shadow);
        }
        .search-bar input { border: none; outline: none; margin-left: 10px; width: 100%; font-size: 0.9rem; }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .notif-badge {
            width: 44px; height: 44px;
            background: white; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            position: relative; cursor: pointer;
            box-shadow: var(--card-shadow);
        }
        .notif-dot {
            position: absolute; top: 10px; right: 10px;
            width: 8px; height: 8px;
            background: #ef4444; border-radius: 50%;
            border: 2px solid white;
        }
        .avatar { width: 44px; height: 44px; border-radius: 12px; object-fit: cover; }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid rgba(0,0,0,0.03);
            box-shadow: var(--card-shadow);
            height: 100%;
        }
        .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
        }

        .content-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            box-shadow: var(--card-shadow);
        }
        .item-row {
            display: flex; align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .item-row:last-child { border-bottom: none; }
        .item-img {
            width: 50px; height: 50px;
            background: #f1f5f9; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            margin-right: 16px;
            overflow: hidden;
            flex-shrink: 0;
        }

        .item-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .status-pill {
            padding: 6px 12px; border-radius: 8px;
            font-size: 0.7rem; font-weight: 700; text-transform: uppercase;
        }
        .pill-success { background: #dcfce7; color: #166534; }
        .pill-warning { background: #fef9c3; color: #854d0e; }
        .pill-info { background: #e0f2fe; color: #0369a1; }
        .pill-danger { background: #fee2e2; color: #991b1b; }

        .btn-primary-custom {
            background: var(--primary-gradient);
            border: none; color: white; padding: 10px 20px;
            border-radius: 12px; font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(139, 92, 246, 0.3); color: white; }

        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
            .mobile-toggle { display: block !important; }
        }
        .mobile-toggle { display: none; background: white; border: none; width: 44px; height: 44px; border-radius: 12px; box-shadow: var(--card-shadow); }
    </style>
</head>
<body>

    <aside class="sidebar" id="sidebar">
        <a class="brand-logo" href="#">
            <i data-lucide="search" class="me-2" style="color: var(--primary-purple); stroke-width: 3;"></i>
            <span class="temu">Temu</span><span class="balik">Balik</span>
        </a>

        <ul class="nav-menu">
            <li class="nav-item">
                <a href="index.php" class="nav-link active">
                    <i data-lucide="layout-grid" width="20"></i> Dashboard
                </a>
            </li>
            <?php if($role === 'petugas'): ?>
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
                    <a href="../verifikasi/index.php" class="nav-link">
                        <i data-lucide="check-square" width="20"></i> Verifikasi
                    </a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a href="../laporan_kehilangan/index.php" class="nav-link">
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
        <header class="top-header">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" onclick="toggleSidebar()">
                    <i data-lucide="menu"></i>
                </button>
            </div>

            <div class="user-profile">
                <div class="notif-badge">
                    <i data-lucide="bell" width="20" class="text-slate"></i>
                    <div class="notif-dot"></div>
                </div>
                <div class="d-none d-sm-block text-end">
                    <h6 class="mb-0 fw-700" style="font-size: 0.9rem;"><?= htmlspecialchars($user['name']) ?></h6>
                    <span class="text-slate" style="font-size: 0.75rem;"><?= ucfirst($role) ?></span>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=8b5cf6&color=fff" class="avatar" alt="User">
            </div>
        </header>

        <section class="mb-5">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="fw-800 mb-1">Halo, <?= explode(' ', trim($user['name']))[0] ?>! 👋</h2>
                    <p class="text-slate mb-0">Senang melihatmu kembali. Berikut adalah ikhtisar aktivitas Anda hari ini.</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <?php if($role === 'pelapor'): ?>
                    <a href="../laporan_kehilangan/create.php" class="btn btn-primary-custom d-inline-flex align-items-center gap-2 text-decoration-none">
                        <i data-lucide="plus" width="18"></i> Buat Laporan Baru
                    </a>
                    <?php endif; ?>
            </div>
        </section>

        <div class="row g-4 mb-5">
            <?php if ($role === 'petugas'): ?>
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;"><i data-lucide="box" width="24"></i></div>
                        <h4 class="fw-800 mb-1"><?= $stats['barang_disimpan'] ?></h4>
                        <p class="text-slate small mb-0">Barang Disimpan</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #ecfdf5; color: #10b981;"><i data-lucide="file-text" width="24"></i></div>
                        <h4 class="fw-800 mb-1"><?= $stats['laporan_masuk'] ?></h4>
                        <p class="text-slate small mb-0">Laporan Masuk</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #fefce8; color: #eab308;"><i data-lucide="shuffle" width="24"></i></div>
                        <h4 class="fw-800 mb-1"><?= $stats['matching_total'] ?></h4>
                        <p class="text-slate small mb-0">Pencocokan</p>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #f5f3ff; color: #8b5cf6;"><i data-lucide="check-circle" width="24"></i></div>
                        <h4 class="fw-800 mb-1"><?= $stats['verifikasi_total'] ?></h4>
                        <p class="text-slate small mb-0">Verifikasi</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="col-6 col-lg-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;"><i data-lucide="file-text" width="24"></i></div>
                        <h4 class="fw-800 mb-1"><?= $stats['laporan_saya'] ?></h4>
                        <p class="text-slate small mb-0">Laporan Saya</p>
                    </div>
                </div>
                <div class="col-6 col-lg-4">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: #f5f3ff; color: #8b5cf6;"><i data-lucide="clock" width="24"></i></div>
                        <h4 class="fw-800 mb-1"><?= $stats['status_tracking'] ?></h4>
                        <p class="text-slate small mb-0">Status Tracking</p>
                    </div>
                </div>
                <div class="col-lg-4 d-none d-lg-block">
                    <div class="stat-card">
                         <div class="stat-icon" style="background: #fff7ed; color: #ea580c;"><i data-lucide="zap" width="24"></i></div>
                         <h4 class="fw-800 mb-1"><?= $stats['kecocokan'] ?></h4>
                        <p class="text-slate small mb-0">Kecocokan</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-800 mb-0"><?= $role === 'petugas' ? 'Barang Temuan Terbaru' : 'Laporan Terbaru' ?></h5>
                        <a href="<?= $role === 'petugas' ? '../barang_temuan/index.php' : '../laporan_kehilangan/index.php' ?>" class="text-decoration-none small fw-700" style="color: var(--primary-purple);">Lihat Semua</a>
                    </div>
                    
                    <div class="item-list">
                        <?php if ($recent_items && $recent_items->num_rows > 0): ?>
                            <?php while ($row = $recent_items->fetch_assoc()): 
                                $icon = getCategoryIcon($row['kategori'] ?? '');
                                $has_photo = !empty($row['foto']) && file_exists(dirname(__DIR__) . '/uploads/' . $row['foto']);

                                $status_class = 'pill-info';
                                if($row['status'] == 'ditemukan' || $row['status'] == 'diambil') $status_class = 'pill-success';
                                if($row['status'] == 'dilaporkan' || $row['status'] == 'hilang') $status_class = 'pill-danger';
                                if($row['status'] == 'proses' || $row['status'] == 'disimpan' || $row['status'] == 'mencari') $status_class = 'pill-warning';
                            ?>
                            <div class="item-row">
                                <div class="item-img">
                                    <?php if ($has_photo): ?>
                                        <img src="../uploads/<?= htmlspecialchars($row['foto']) ?>" alt="Foto Barang" class="item-photo">
                                    <?php else: ?>
                                        <i data-lucide="<?= $icon ?>" class="text-slate"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-700"><?= htmlspecialchars($row['nama_barang']) ?></h6>
                                    <p class="text-slate small mb-0"><?= $role === 'petugas' ? 'Dicatat' : 'Dilaporkan' ?>: <?= formatDateID($row['created_at']) ?></p>
                                </div>
                                <div class="text-end me-4 d-none d-md-block">
                                    <span class="status-pill <?= $status_class ?>"><?= ucfirst($row['status']) ?></span>
                                </div>
                                <a href="<?php if($role === 'petugas'): echo '../barang_temuan/edit.php?id=' . $row['id']; else: echo '../laporan_kehilangan/detail.php?id=' . $row['id']; endif; ?>" class="btn btn-light btn-sm rounded-pill"><i data-lucide="chevron-right" width="16"></i></a>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <p class="text-slate">Tidak ada data terbaru.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="content-card">
                    <h5 class="fw-800 mb-4">Kecocokan Baru ✨</h5>
                    
                    <?php if($role === 'pelapor' && $stats['kecocokan'] > 0): ?>
                    <div class="match-item p-3 rounded-4 mb-3 border" style="background: #f5f3ff; border-color: rgba(139,92,246,0.1) !important;">
                        <div class="d-flex gap-3">
                            <div class="bg-white p-2 rounded-3 shadow-sm" style="height: fit-content;">
                                <i data-lucide="zap" width="20" class="text-warning"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-700">Ada kecocokan!</h6>
                                <p class="small text-slate mb-2">Seseorang menemukan barang yang mirip dengan milik Anda.</p>
                                <a href="../matching/index.php" class="btn btn-primary-custom btn-sm w-100 py-2 text-decoration-none">Verifikasi</a>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="p-3 bg-light rounded-4 text-center mb-3">
                        <i data-lucide="search" class="text-slate mb-2" width="24" style="opacity: 0.5;"></i>
                        <p class="small text-slate mb-0">Belum ada kecocokan ditemukan.</p>
                    </div>
                    <?php endif; ?>

                    <div class="border-top mt-4 pt-4">
                        <h6 class="fw-700 mb-3" style="font-size: 0.9rem;">Aktivitas Terkini</h6>
                        <div class="d-flex gap-3 mb-3">
                            <div class="bg-light p-2 rounded-circle" style="height: fit-content;"><i data-lucide="message-square" width="14"></i></div>
                            <div>
                                <p class="small mb-0">System: Welcome to TemuBalik!</p>
                                <span class="text-slate" style="font-size: 0.7rem;">Baru saja</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        window.addEventListener('click', (e) => {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.mobile-toggle');
            if (window.innerWidth < 992 && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    </script>
</body>
</html>