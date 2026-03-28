<?php
require_once '../config/database.php';
require_once '../config/auth.php';

requireLogin();

$user = getCurrentUser();
if (($user['role'] ?? '') !== 'pelapor') {
    header('Location: ../dashboard/index.php');
    exit;
}

$stmt = safePrepare($conn, 'SELECT COUNT(*) as total FROM matching m JOIN laporan_kehilangan lk ON m.laporan_id = lk.id WHERE lk.created_by = ? AND m.status IN ("pending", "dikonfirmasi")');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$pelapor_match_count = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();

$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$stmt = safePrepare($conn, 'SELECT COUNT(*) as total FROM laporan_kehilangan WHERE created_by = ?');
$stmt->bind_param('i', $user['id']);
$stmt->execute();
$total_items = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
$stmt->close();
$total_pages = ceil($total_items / $items_per_page);

$stmt = safePrepare($conn, '
    SELECT lk.*, 
           MAX(m.cocok_score) AS cocok_score,
           MAX(m.status) AS matching_status,
           MAX(pb.status) AS penyerahan_status,
           MAX(pb.tanggal_serah) AS tanggal_serah
    FROM laporan_kehilangan lk
    LEFT JOIN matching m ON lk.id = m.laporan_id
    LEFT JOIN penyerahan_barang pb ON m.id = pb.matching_id
    WHERE lk.created_by = ?
    GROUP BY lk.id
    ORDER BY lk.created_at DESC
    LIMIT ? OFFSET ?
');
$stmt->bind_param('iii', $user['id'], $items_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

function getTrackingLabel(array $row): string {
    if (($row['penyerahan_status'] ?? '') === 'diserahkan' || ($row['status'] ?? '') === 'diserahkan') {
        return 'Sudah Diserahkan';
    }
    if (($row['status'] ?? '') === 'ditemukan') {
        return 'Sedang Verifikasi';
    }
    if (($row['matching_status'] ?? '') === 'cocok') {
        return 'Calon Cocok Ditemukan';
    }
    return 'Masih Pencarian';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Tracking - TemuBalik</title>
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
        .avatar { width: 44px; height: 44px; border-radius: 12px; object-fit: cover; }

        .content-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            box-shadow: var(--card-shadow);
        }

        .track-row {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            border: 1px solid #f1f5f9;
            border-radius: 16px;
            margin-bottom: 16px;
            transition: all 0.2s ease;
        }

        .item-img {
            width: 50px;
            height: 50px;
            background: #f1f5f9;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
        }
        .item-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .track-row:last-child { margin-bottom: 0; }
        .track-row:hover {
            border-color: var(--primary-purple);
            box-shadow: var(--card-shadow);
        }

        .track-info { flex: 1; }
        .track-meta { min-width: 130px; text-align: right; }

        .status-pill {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .pill-success { background: #dcfce7; color: #166534; }
        .pill-warning { background: #fef9c3; color: #854d0e; }
        .pill-info { background: #e0f2fe; color: #0369a1; }
        .pill-danger { background: #fee2e2; color: #991b1b; }

        .btn-primary-custom {
            background: var(--primary-gradient);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(139, 92, 246, 0.3);
            color: white;
            text-decoration: none;
        }

        .btn-action-sm {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.2s ease;
            border: 1px solid var(--primary-purple);
            color: var(--primary-purple);
            background: transparent;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-action-sm:hover {
            background: var(--primary-purple);
            color: white;
        }

        .page-header { margin-bottom: 40px; }
        .page-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .page-subtitle {
            color: var(--text-slate);
            font-size: 1rem;
        }

        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
            .mobile-toggle { display: block !important; }
            .track-row { flex-direction: column; align-items: flex-start; }
            .track-meta { text-align: left; min-width: auto; }
        }
        .mobile-toggle {
            display: none;
            background: white;
            border: none;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            cursor: pointer;
        }

        .empty-state-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 300px;
            text-align: center;
            padding: 40px;
        }

        .empty-state-icon {
            display: inline-block;
            margin-bottom: 24px;
        }

        .empty-state-icon i {
            color: var(--primary-purple);
            width: 60px;
            height: 60px;
        }

        .empty-state-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .empty-state-description {
            font-size: 0.9rem;
            color: var(--text-slate);
            margin: 0;
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
                <a href="index.php" class="nav-link">
                    <i data-lucide="file-text" width="20"></i> Laporan Saya
                </a>
            </li>
            <li class="nav-item">
                <a href="status.php" class="nav-link active">
                    <i data-lucide="activity" width="20"></i> Status Tracking
                </a>
            </li>
            <li class="nav-item">
                <a href="../matching/index.php" class="nav-link">
                    <i data-lucide="zap" width="20"></i> Kecocokan
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
        <header class="top-header">
            <div class="d-flex align-items-center gap-3">
                <button class="mobile-toggle" onclick="toggleSidebar()">
                    <i data-lucide="menu"></i>
                </button>
            </div>

            <div class="user-profile">
                <div class="notif-badge">
                    <i data-lucide="bell" width="20" class="text-slate"></i>
                </div>
                <div class="d-none d-sm-block text-end">
                    <h6 class="mb-0 fw-700" style="font-size: 0.9rem;"><?= htmlspecialchars($user['name']) ?></h6>
                    <span class="text-slate" style="font-size: 0.75rem;">Pelapor</span>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=8b5cf6&color=fff" class="avatar" alt="User">
            </div>
        </header>

        <section class="page-header">
            <h1 class="page-title">🧭 Status Tracking</h1>
            <p class="page-subtitle">Pantau progres setiap laporan kehilangan Anda.</p>
        </section>

        <div class="filter-container" style="background: white; border-radius: 16px; padding: 20px; margin-bottom: 24px; box-shadow: var(--card-shadow);">
            <div class="search-bar" style="max-width: 320px;">
                <i data-lucide="search" width="18" class="text-slate"></i>
                <input type="text" id="filterSearch" placeholder="Cari status laporan...">
            </div>
        </div>

        <div class="content-card">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                        $icon = getCategoryIcon($row['kategori'] ?? '');
                        $has_photo = !empty($row['foto']) && file_exists(dirname(__DIR__) . '/uploads/' . $row['foto']);
                        $status_class = 'pill-info';
                        if (($row['status'] ?? '') === 'dilaporkan' || ($row['status'] ?? '') === 'hilang') {
                            $status_class = 'pill-danger';
                        } elseif (($row['status'] ?? '') === 'ditemukan' || ($row['matching_status'] ?? '') === 'cocok') {
                            $status_class = 'pill-warning';
                        } elseif (($row['status'] ?? '') === 'diserahkan' || ($row['penyerahan_status'] ?? '') === 'diserahkan' || ($row['status'] ?? '') === 'ditutup') {
                            $status_class = 'pill-success';
                        }
                    ?>
                    <div class="track-row">
                        <div class="item-img">
                            <?php if ($has_photo): ?>
                                <img src="../uploads/<?= htmlspecialchars($row['foto']) ?>" alt="<?= htmlspecialchars($row['nama_barang']) ?>" class="item-photo">
                            <?php else: ?>
                                <i data-lucide="<?= $icon ?>" class="text-slate"></i>
                            <?php endif; ?>
                        </div>
                        <div class="track-info">
                            <h6 class="mb-1 fw-700"><?= htmlspecialchars($row['nama_barang']) ?></h6>
                            <p class="text-slate small mb-1">Lokasi hilang: <?= htmlspecialchars($row['lokasi_hilang']) ?></p>
                            <p class="text-slate small mb-0">
                                Tracking: <strong><?= htmlspecialchars(getTrackingLabel($row)) ?></strong>
                                <?php if ($row['cocok_score'] !== null): ?>
                                    · Score: <strong><?= intval($row['cocok_score']) ?>%</strong>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="track-meta">
                            <span class="status-pill <?= $status_class ?>"><?= ucfirst($row['status']) ?></span>
                            <p class="text-slate small mt-2 mb-0">Update: <?= formatDateID($row['updated_at']) ?></p>
                        </div>

                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state-box">
                    <div class="empty-state-icon">
                        <i data-lucide="inbox"></i>
                    </div>
                    <h5 class="empty-state-title">Belum Ada Laporan</h5>
                    <p class="empty-state-description">Belum ada laporan kehilangan yang bisa dilacak.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="mt-4" aria-label="Pagination">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= max(1, $page - 1) ?>">Sebelumnya</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= min($total_pages, $page + 1) ?>">Berikutnya</a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();

        document.getElementById('filterSearch')?.addEventListener('input', function() {
            const keyword = this.value.toLowerCase();
            const rows = document.querySelectorAll('.track-row');
            if (rows.length === 0) return;
            let visibleCount = 0;
            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                const isVisible = !keyword || rowText.includes(keyword);
                row.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            let emptyStateBox = document.getElementById('emptyFilterResult');
            if (!emptyStateBox && visibleCount === 0 && keyword) {
                const contentCard = document.querySelector('.content-card');
                emptyStateBox = document.createElement('div');
                emptyStateBox.id = 'emptyFilterResult';
                emptyStateBox.className = 'empty-state-box';
                emptyStateBox.innerHTML = `
                    <div class="empty-state-icon">
                        <i data-lucide="search-x"></i>
                    </div>
                    <h5 class="empty-state-title">Tidak Ada Data</h5>
                    <p class="empty-state-description">Tidak ada status laporan yang cocok dengan kata kunci pencarian Anda.</p>
                `;
                contentCard.appendChild(emptyStateBox);
                lucide.createIcons();
            } else if (emptyStateBox && visibleCount > 0) {
                emptyStateBox.remove();
            }
        });

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
