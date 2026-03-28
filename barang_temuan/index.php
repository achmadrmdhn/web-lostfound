<?php
require_once '../config/database.php';
require_once '../config/auth.php';

requireLogin();

$user = getCurrentUser();
$role = $user['role'];

if ($role !== 'petugas') {
    header('Location: ../dashboard/index.php');
    exit;
}

$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

$query = 'SELECT * FROM barang_temuan ORDER BY created_at DESC LIMIT ' . $items_per_page . ' OFFSET ' . $offset;
$result = $conn->query($query);

$count_query = 'SELECT COUNT(*) as total FROM barang_temuan';
$count_result = $conn->query($count_query);
$total_items = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

$crud_status = $_GET['crud_status'] ?? '';
$crud_action = $_GET['crud_action'] ?? '';
$crud_message = '';

if (in_array($crud_status, ['success', 'error'], true) && in_array($crud_action, ['create', 'update', 'delete'], true)) {
    $crud_messages = [
        'success' => [
            'create' => 'Data berhasil ditambahkan.',
            'update' => 'Data berhasil diperbarui.',
            'delete' => 'Data berhasil dihapus.'
        ],
        'error' => [
            'create' => 'Gagal menambahkan data. Silakan coba lagi.',
            'update' => 'Gagal memperbarui data. Silakan coba lagi.',
            'delete' => 'Gagal menghapus data. Silakan coba lagi.'
        ]
    ];
    $crud_message = $crud_messages[$crud_status][$crud_action] ?? '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barang Temuan - TemuBalik</title>
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

        .content-card {
            background: white;
            border-radius: 24px;
            padding: 28px;
            box-shadow: var(--card-shadow);
        }

        .crud-alert {
            border-radius: 14px;
            border: 1px solid;
            padding: 14px 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .crud-alert.is-hiding {
            opacity: 0;
            transform: translateY(-6px);
        }

        .crud-alert-success {
            background: #dcfce7;
            border-color: #bbf7d0;
            color: #166534;
        }

        .crud-alert-error {
            background: #fee2e2;
            border-color: #fecaca;
            color: #991b1b;
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
        .btn-primary-custom:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(139, 92, 246, 0.3); color: white; text-decoration: none; }

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
        }
        .btn-action-sm:hover {
            background: var(--primary-purple);
            color: white;
        }

        .btn-action-danger {
            border-color: #ef4444;
            color: #ef4444;
        }
        .btn-action-danger:hover {
            background: #ef4444;
            color: white;
        }

        .filter-container {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: var(--card-shadow);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-container select {
            border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.1);
            padding: 8px 12px;
            font-size: 0.9rem;
        }
        .filter-container select:focus {
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
            outline: none;
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

        @media (max-width: 991px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
            .mobile-toggle { display: block !important; }
        }
        .mobile-toggle { display: none; background: white; border: none; width: 44px; height: 44px; border-radius: 12px; box-shadow: var(--card-shadow); cursor: pointer; }

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
                <a href="index.php" class="nav-link active">
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
                    <span class="text-slate" style="font-size: 0.75rem;"><?= ucfirst($role) ?></span>
                </div>
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=8b5cf6&color=fff" class="avatar" alt="User">
            </div>
        </header>

        <section class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">📦 Barang Temuan</h1>
                    <p class="page-subtitle">Kelola dan pantau semua barang yang telah ditemukan</p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <a href="create.php" class="btn btn-primary-custom d-inline-flex align-items-center gap-2 text-decoration-none">
                        <i data-lucide="plus" width="18"></i> Tambah Barang
                    </a>
                </div>
            </div>
        </section>

        <?php if (!empty($crud_message)): ?>
        <div class="crud-alert <?= $crud_status === 'success' ? 'crud-alert-success' : 'crud-alert-error' ?>">
            <i data-lucide="<?= $crud_status === 'success' ? 'check-circle-2' : 'alert-circle' ?>" width="18"></i>
            <span><?= htmlspecialchars($crud_message) ?></span>
        </div>
        <?php endif; ?>

        <div class="filter-container">
            <select id="filterStatus" style="max-width: 150px;">
                <option value="">Semua Status</option>
                <option value="disimpan">Disimpan</option>
                <option value="dicocokkan">Dicocokkan</option>
                <option value="diklaim">Diklaim</option>
                <option value="diserahkan">Diserahkan</option>
            </select>
            <select id="filterKategori" style="max-width: 220px;">
                <option value="">Semua Kategori</option>
                <option value="Perhiasan">Perhiasan</option>
                <option value="Elektronik">Elektronik</option>
                <option value="Kendaraan & Aksesori">Kendaraan & Aksesori</option>
                <option value="Dokumen">Dokumen</option>
                <option value="Tas & Dompet">Tas & Dompet</option>
                <option value="Lainnya">Lainnya</option>
            </select>
            <div class="search-bar ms-md-auto" style="max-width: 320px;">
                <i data-lucide="search" width="18" class="text-slate"></i>
                <input type="text" id="filterSearch" placeholder="Cari barang...">
            </div>
        </div>

        <div class="content-card">
            <div class="item-list">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): 
                        $icon = getCategoryIcon($row['kategori'] ?? '');
                        $status_class = 'pill-info';
                        if($row['status'] == 'disimpan') $status_class = 'pill-warning';
                        if($row['status'] == 'dicocokkan') $status_class = 'pill-info';
                        if($row['status'] == 'diklaim' || $row['status'] == 'diserahkan') $status_class = 'pill-success';
                    ?>
                    <div class="item-row">
                        <div class="item-img">
                            <?php if (!empty($row['foto'])): ?>
                                <img src="../uploads/<?= htmlspecialchars($row['foto']) ?>" alt="Foto Barang" class="item-photo">
                            <?php else: ?>
                                <i data-lucide="<?= $icon ?>" class="text-slate"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-700"><?= htmlspecialchars($row['nama_barang']) ?></h6>
                            <p class="text-slate small mb-0">
                                <span class="badge bg-light text-dark me-2"><?= htmlspecialchars($row['kategori']) ?></span>
                                <span><?= htmlspecialchars($row['lokasi_ditemukan']) ?></span>
                            </p>
                        </div>
                        <div class="text-end me-4 d-none d-md-block">
                            <span class="status-pill <?= $status_class ?>"><?= ucfirst($row['status']) ?></span>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="detail.php?id=<?= $row['id'] ?>" class="btn-action-sm" title="Lihat">
                                <i data-lucide="eye" width="14"></i>
                            </a>
                            <a href="edit.php?id=<?= $row['id'] ?>" class="btn-action-sm" title="Edit">
                                <i data-lucide="pencil" width="14"></i>
                            </a>
                            <a href="delete.php?id=<?= $row['id'] ?>" class="btn-action-sm btn-action-danger" title="Hapus" onclick="return confirm('Yakin ingin menghapus?')">
                                <i data-lucide="trash-2" width="14"></i>
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state-box">
                        <div class="empty-state-icon">
                            <i data-lucide="inbox"></i>
                        </div>
                        <h5 class="empty-state-title">Belum Ada Barang Temuan</h5>
                        <p class="empty-state-description">Tidak ada barang temuan yang terdaftar di database.</p>
                    </div>
                <?php endif; ?>
            </div>
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

        const crudAlert = document.querySelector('.crud-alert');
        if (crudAlert) {
            setTimeout(() => {
                crudAlert.classList.add('is-hiding');
                setTimeout(() => crudAlert.remove(), 300);
            }, 3500);
        }

        document.getElementById('filterStatus')?.addEventListener('change', function() {
            filterTable();
        });

        document.getElementById('filterKategori')?.addEventListener('change', function() {
            filterTable();
        });

        document.getElementById('filterSearch')?.addEventListener('input', function() {
            filterTable();
        });

        function filterTable() {
            const status = document.getElementById('filterStatus').value.toLowerCase();
            const kategori = document.getElementById('filterKategori').value.toLowerCase();
            const keyword = document.getElementById('filterSearch')?.value.toLowerCase() || '';
            const rows = document.querySelectorAll('.item-row');
            if (rows.length === 0) return;
            let visibleCount = 0;
            
            rows.forEach(row => {
                const rowKategori = row.querySelector('.badge')?.textContent.toLowerCase() || '';
                const rowStatus = row.querySelector('.status-pill')?.textContent.toLowerCase() || '';
                const rowText = row.textContent.toLowerCase();
                
                const statusMatch = !status || rowStatus.includes(status);
                const kategoriMatch = !kategori || rowKategori.includes(kategori);
                const keywordMatch = !keyword || rowText.includes(keyword);
                
                const isVisible = statusMatch && kategoriMatch && keywordMatch;
                row.style.display = isVisible ? '' : 'none';
                if (isVisible) visibleCount++;
            });

            let emptyStateBox = document.getElementById('emptyFilterResult');
            if (!emptyStateBox && visibleCount === 0 && (status || kategori || keyword)) {
                const itemList = document.querySelector('.item-list');
                emptyStateBox = document.createElement('div');
                emptyStateBox.id = 'emptyFilterResult';
                emptyStateBox.className = 'empty-state-box';
                emptyStateBox.innerHTML = `
                    <div class="empty-state-icon">
                        <i data-lucide="search-x"></i>
                    </div>
                    <h5 class="empty-state-title">Tidak Ada Data</h5>
                    <p class="empty-state-description">Tidak ada barang yang cocok dengan filter pencarian Anda.</p>
                `;
                itemList.appendChild(emptyStateBox);
                lucide.createIcons();
            } else if (emptyStateBox && visibleCount > 0) {
                emptyStateBox.remove();
            }
        }
    </script>
</body>
</html>
