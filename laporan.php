<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$allReports = [];
$search = trim($_GET['q'] ?? '');
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 6;
$totalReports = 0;
$totalPages = 1;

if ($currentPage < 1) {
    $currentPage = 1;
}

try {
    $baseSql = "
        FROM (
            SELECT 'temuan' as type, bt.id, bt.nama_barang, bt.kategori, bt.deskripsi,
                   bt.lokasi_ditemukan as lokasi_laporan,
                   bt.tanggal_ditemukan as tanggal_laporan,
                   bt.foto,
                   u.name as reporter_name
            FROM barang_temuan bt
            JOIN users u ON bt.created_by = u.id
                 WHERE bt.status != 'diserahkan'

            UNION ALL

            SELECT 'kehilangan' as type, lk.id, lk.nama_barang, lk.kategori, lk.deskripsi,
                   lk.lokasi_hilang as lokasi_laporan,
                   lk.tanggal_hilang as tanggal_laporan,
                   lk.foto,
                   u.name as reporter_name
            FROM laporan_kehilangan lk
            JOIN users u ON lk.created_by = u.id
                 WHERE lk.status NOT IN ('diserahkan', 'ditutup')
        ) all_laporan
    ";

    $whereSql = "";
    $searchLike = '%' . $search . '%';

    if ($search !== '') {
        $whereSql = "
            WHERE nama_barang LIKE ?
               OR kategori LIKE ?
               OR deskripsi LIKE ?
               OR lokasi_laporan LIKE ?
               OR reporter_name LIKE ?
        ";
    }

    $countSql = "SELECT COUNT(*) as total " . $baseSql . $whereSql;
    $countStmt = $conn->prepare($countSql);

    if ($countStmt) {
        if ($search !== '') {
            $countStmt->bind_param('sssss', $searchLike, $searchLike, $searchLike, $searchLike, $searchLike);
        }
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $countRow = $countResult ? $countResult->fetch_assoc() : null;
        $totalReports = (int) ($countRow['total'] ?? 0);
    }

    $totalPages = max(1, (int) ceil($totalReports / $perPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    $offset = ($currentPage - 1) * $perPage;

    $dataSql = "SELECT * " . $baseSql . $whereSql . " ORDER BY tanggal_laporan DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($dataSql);

    if ($stmt) {
        if ($search !== '') {
            $stmt->bind_param('sssssii', $searchLike, $searchLike, $searchLike, $searchLike, $searchLike, $perPage, $offset);
        } else {
            $stmt->bind_param('ii', $perPage, $offset);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $allReports[] = $row;
        }
    }
} catch (Exception $e) {
    $allReports = [];
    $totalReports = 0;
    $totalPages = 1;
}

$buildPageUrl = function (int $page) use ($search): string {
    $params = ['page' => $page];
    if ($search !== '') {
        $params['q'] = $search;
    }
    return 'laporan.php?' . http_build_query($params);
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Semua Laporan - TemuBalik</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        html, body {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        :root {
            --primary-purple: #8b5cf6;
            --deep-purple: #6d28d9;
            --soft-purple: #f5f3ff;
            --primary-gradient: linear-gradient(135deg, #818cf8 0%, #a855f7 100%);
            --bg-light: #fafaff;
            --text-main: #0f172a;
            --text-slate: #475569;
            --card-shadow: 0 10px 30px -5px rgba(139, 92, 246, 0.1);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-main);
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            scroll-behavior: smooth;
            position: relative;
        }

        nav, section, footer {
            max-width: 100%;
            overflow-x: clip;
        }

        .brand-logo {
            font-size: 1.4rem;
            color: var(--text-main);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        .brand-logo .temu { font-weight: 600; }
        .brand-logo .balik { font-weight: 800; color: var(--primary-purple); }

        .blob {
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.08) 0%, rgba(139, 92, 246, 0) 70%);
            filter: blur(60px);
            border-radius: 50%;
            z-index: -1;
            pointer-events: none;
        }
        .blob-left { top: 10%; left: -250px; }
        .blob-right { top: 40%; right: -250px; background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, rgba(99, 102, 241, 0) 70%); }
        .blob-bottom-left { bottom: 10%; left: -200px; }

        .navbar {
            backdrop-filter: blur(15px);
            background-color: rgba(255, 255, 255, 0.85);
            border-bottom: 1px solid rgba(139, 92, 246, 0.1);
            transition: all 0.3s ease;
            padding: 1.2rem 0;
        }

        .navbar .navbar-toggler {
            margin-left: auto;
        }

        .navbar .navbar-collapse {
            max-width: 100%;
        }

        .navbar.scrolled {
            padding: 0.8rem 0;
            background-color: rgba(255, 255, 255, 0.98);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
        }

        .nav-link {
            font-weight: 600;
            color: var(--text-slate) !important;
            transition: color 0.3s ease;
        }

        .nav-link:hover { color: var(--primary-purple) !important; }

        .btn-gradient-primary {
            background: var(--primary-gradient);
            border: 2px solid transparent;
            color: white;
            padding: 14px 32px;
            border-radius: 16px;
            font-weight: 700;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4);
        }

        .btn-gradient-primary:hover {
            background: #ffffff;
            color: var(--primary-purple) !important;
            border-color: var(--primary-purple);
            box-shadow: 0 15px 30px -10px rgba(139, 92, 246, 0.2);
            transform: translateY(-4px);
        }

        .btn-outline-brand {
            border: 2px solid var(--primary-purple);
            color: var(--primary-purple);
            background: #ffffff;
            border-radius: 16px;
            font-weight: 700;
            padding: 10px 14px;
            transition: all 0.3s ease;
        }

        .btn-outline-brand:hover {
            background: var(--primary-gradient);
            color: #ffffff !important;
            border-color: transparent;
            transform: translateY(-2px);
        }

        .section-title {
            font-weight: 700;
            font-size: clamp(1.75rem, 4vw, 2.25rem);
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        .card-custom {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(139, 92, 246, 0.1);
            border-radius: 24px;
            padding: 24px;
            transition: all 0.4s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: var(--card-shadow);
        }

        .card-custom:hover {
            transform: translateY(-10px);
            border-color: var(--primary-purple);
            background: #ffffff;
        }

        .card-body-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .card-title { font-weight: 700; margin-bottom: 0.5rem; font-size: 1.15rem; }

        .card-text-small {
            font-weight: 400;
            font-size: 0.9rem;
            color: var(--text-slate);
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }

        .item-image-box {
            position: relative;
            height: 200px;
            border-radius: 16px;
            margin-bottom: 20px;
            background: linear-gradient(45deg, #f1f5f9, #f5f3ff);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .item-image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-image-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: #94a3b8;
            text-align: center;
            padding: 0 16px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .category-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 2;
            background: rgba(15, 23, 42, 0.82);
            color: #ffffff;
            padding: 6px 10px;
            border-radius: 10px;
            font-size: 0.72rem;
            font-weight: 700;
            line-height: 1;
            backdrop-filter: blur(2px);
            max-width: calc(100% - 24px);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-meta {
            font-size: 0.8rem;
            color: var(--text-slate);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .reporter-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 16px;
        }

        .reporter-avatar {
            width: 32px;
            height: 32px;
            background: var(--soft-purple);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--primary-purple);
        }

        .reporter-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
        }

        .status-badge {
            font-size: 0.65rem;
            padding: 6px 14px;
            border-radius: 100px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-found { background: #ecfdf5; color: #059669; }
        .badge-lost { background: #fff1f2; color: #e11d48; }

        .reports-page {
            padding: 160px 0 80px;
            position: relative;
        }

        footer {
            background-color: #ffffff;
            background-image:
                radial-gradient(at 0% 100%, rgba(139, 92, 246, 0.05) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(129, 140, 248, 0.05) 0px, transparent 50%);
            color: var(--text-slate);
            padding: 80px 0 40px;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .footer-brand {
            font-size: 1.6rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            text-decoration: none;
        }
        .footer-brand .temu { color: var(--text-main); font-weight: 600; }
        .footer-brand .balik { color: var(--primary-purple); font-weight: 800; }

        .footer-heading {
            color: var(--text-main);
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-size: 1rem;
            letter-spacing: 0.01em;
        }

        .footer-link {
            color: var(--text-slate);
            text-decoration: none;
            transition: all 0.3s ease;
            display: block;
            margin-bottom: 0.65rem;
            font-size: 0.95rem;
            font-weight: 500;
        }
        .footer-link:hover { color: var(--primary-purple); transform: translateX(3px); }

        .newsletter-group-light {
            background: #f8fafc;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 12px;
            padding: 5px;
            display: flex;
            margin-top: 0.5rem;
        }

        .newsletter-group-light input {
            background: transparent;
            border: none;
            color: var(--text-main);
            padding: 8px 12px;
            width: 100%;
            outline: none;
            font-size: 0.9rem;
        }

        .newsletter-group-light button {
            background: var(--primary-gradient);
            border: none;
            color: white;
            border-radius: 8px;
            padding: 0 16px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .copyright-text {
            color: var(--text-slate);
            font-size: 0.85rem;
            text-align: center;
            font-weight: 500;
            margin-top: 3rem;
            opacity: 0.8;
        }

        .search-panel {
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(139, 92, 246, 0.14);
            border-radius: 18px;
            padding: 14px;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }

        .search-input {
            border-radius: 12px;
            border: 1px solid rgba(139, 92, 246, 0.2);
            padding: 11px 14px 11px 42px;
            font-weight: 500;
        }

        .search-input-wrap {
            position: relative;
        }

        .search-input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
        }

        .search-panel .btn {
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .search-panel .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .search-panel .btn-light {
            background: #f1f5f9;
            color: var(--text-slate);
            border: 1px solid rgba(0, 0, 0, 0.06);
        }

        .search-panel .btn-light:hover {
            background: #e2e8f0;
            color: var(--text-main);
        }

        .search-input:focus {
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 0.2rem rgba(139, 92, 246, 0.15);
        }

        .pagination .page-link {
            background: #ffffff;
            color: var(--deep-purple);
            border: 2px solid rgba(139, 92, 246, 0.25);
            border-radius: 10px;
            margin: 0 4px;
            font-weight: 700;
            min-width: 42px;
            height: auto;
            padding: 0.5rem 0.85rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: 0 6px 14px -10px rgba(139, 92, 246, 0.45);
        }

        .pagination .page-link.page-link-nav {
            min-width: auto;
            padding: 0.5rem 1rem;
            border-radius: 10px;
        }

        .pagination .page-item:first-child .page-link,
        .pagination .page-item:last-child .page-link {
            border-radius: 10px;
        }

        .pagination .page-link:hover {
            background: var(--primary-gradient);
            color: #ffffff;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 10px 18px -10px rgba(139, 92, 246, 0.6);
        }

        .pagination .page-item.active .page-link {
            background: var(--primary-gradient);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 12px 20px -10px rgba(139, 92, 246, 0.65);
        }

        .pagination .page-item.disabled .page-link {
            background: #f8fafc;
            color: #94a3b8;
            border-color: #e2e8f0;
            box-shadow: none;
            transform: none;
            pointer-events: none;
        }

        .empty-state-box {
            background: #ffffff;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: var(--card-shadow);
            border: 2px solid rgba(139, 92, 246, 0.08);
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
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 12px;
        }

        .empty-state-description {
            font-size: 0.95rem;
            color: var(--text-slate);
            margin: 0;
            line-height: 1.6;
        }

        .empty-state-no-data {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 300px;
            text-align: center;
        }

        .empty-state-no-data .empty-state-icon {
            margin-bottom: 24px;
        }

        .empty-state-no-data .empty-state-title {
            margin-bottom: 16px;
        }

        @media (max-width: 991px) {
            .navbar { padding: 0.8rem 0; }
        }

        @media (max-width: 768px) {
            .reports-page { padding: 130px 0 60px; }
            footer { text-align: left; padding: 50px 0 30px; }
            .footer-brand { justify-content: flex-start; }
            .blob { width: 300px; height: 300px; }
            .section-title { font-size: 1.85rem; }
            .newsletter-group-light { flex-direction: column; gap: 8px; padding: 12px; }
            .newsletter-group-light button { padding: 12px; width: 100%; }
            .search-panel .d-flex { flex-direction: column; }
            .search-panel .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="blob blob-left"></div>
    <div class="blob blob-right"></div>
    <div class="blob blob-bottom-left"></div>

    <nav class="navbar navbar-expand-lg fixed-top" id="mainNav">
        <div class="container">
            <a class="brand-logo" href="index.php">
                <i data-lucide="search" class="me-2" style="color: var(--primary-purple); stroke-width: 3;"></i>
                <span class="temu">Temu</span><span class="balik">Balik</span>
            </a>

            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i data-lucide="menu" style="color: var(--text-main);"></i>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto align-items-center mb-3 mb-lg-0">
                    <li class="nav-item"><a class="nav-link px-3" href="index.php#features">Fitur</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="index.php#workflow">Cara Kerja</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="laporan.php">Laporan</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="#faq">Bantuan</a></li>
                </ul>
                <div class="text-center text-lg-start">
                    <a href="auth/login.php" class="btn btn-gradient-primary px-4 w-100 w-lg-auto">Mulai Lapor</a>
                </div>
            </div>
        </div>
    </nav>

    <section class="reports-page">
        <div class="container py-4 px-4">
            <div class="text-center mb-5">
                <div>
                    <h2 class="section-title">Semua Laporan</h2>
                    <p class="text-slate mb-0" style="font-weight: 400;">Total <?php echo $totalReports; ?> laporan ditemukan dan kehilangan.</p>
                </div>
            </div>

            <div class="search-panel">
                <form method="GET" action="laporan.php" class="row g-2 align-items-center" id="searchForm">
                    <div class="col-12 col-md-auto flex-md-grow-1">
                        <div class="search-input-wrap">
                            <i data-lucide="search" width="18" class="search-input-icon"></i>
                            <input
                                type="text"
                                name="q"
                                id="filterSearch"
                                class="form-control search-input"
                                placeholder="Cari nama barang, kategori, lokasi, deskripsi, atau pelapor..."
                                value="<?php echo htmlspecialchars($search); ?>"
                            >
                        </div>
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-gradient-primary btn-sm px-4">
                            <i data-lucide="search" width="16" class="me-2" style="display: inline;"></i>Cari
                        </button>
                        <button type="reset" class="btn btn-light btn-sm px-4" id="resetBtn">
                            <i data-lucide="refresh-cw" width="16" class="me-2" style="display: inline;"></i>Reset
                        </button>
                    </div>
                </form>
            </div>

            <div class="row g-4 text-start" id="reportCardsList">
                <?php if (!empty($allReports)): ?>
                    <?php foreach ($allReports as $item):
                        $parts = explode(' ', trim($item['reporter_name']));
                        $initials = '';
                        foreach (array_slice($parts, 0, 2) as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                        }
                        if ($initials === '') {
                            $initials = 'U';
                        }
                        $badgeClass = $item['type'] === 'kehilangan' ? 'badge-lost' : 'badge-found';
                        $badgeText = $item['type'] === 'kehilangan' ? 'Hilang' : 'Ditemukan';
                        $searchText = strtolower(trim(
                            ($item['nama_barang'] ?? '') . ' ' .
                            ($item['kategori'] ?? '') . ' ' .
                            ($item['deskripsi'] ?? '') . ' ' .
                            ($item['lokasi_laporan'] ?? '') . ' ' .
                            ($item['reporter_name'] ?? '')
                        ));
                    ?>
                    <div class="col-md-6 col-lg-4 report-card-col" data-search="<?php echo htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="card-custom">
                            <div class="item-image-box">
                                <span class="category-badge"><?php echo htmlspecialchars($item['kategori']); ?></span>
                                <?php if (!empty($item['foto']) && file_exists('uploads/' . $item['foto'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($item['foto']); ?>" alt="<?php echo htmlspecialchars($item['nama_barang']); ?>">
                                <?php else: ?>
                                    <div class="item-image-placeholder">
                                        <i data-lucide="image-off" size="34"></i>
                                        <span>Belum ada foto barang</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body-content">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="status-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                                    <span class="item-meta mb-0"><i data-lucide="calendar" size="14"></i> <?php echo date('d M Y', strtotime($item['tanggal_laporan'])); ?></span>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($item['nama_barang']); ?></h5>
                                <p class="item-meta mb-3"><i data-lucide="map-pin" class="me-2 text-primary" size="14"></i> <?php echo htmlspecialchars($item['lokasi_laporan']); ?></p>
                                <p class="card-text-small"><?php echo htmlspecialchars(substr($item['deskripsi'], 0, 100)) . (strlen($item['deskripsi']) > 100 ? '...' : ''); ?></p>

                                <div class="reporter-row">
                                    <div class="reporter-avatar"><?php echo $initials; ?></div>
                                    <div class="reporter-name"><?php echo htmlspecialchars($item['reporter_name']); ?></div>
                                </div>

                                <a href="laporan_detail.php?type=<?php echo urlencode($item['type']); ?>&id=<?php echo (int) $item['id']; ?>" class="btn btn-gradient-primary w-100 btn-sm py-2 mt-auto" style="text-decoration: none;">Lihat Detail</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state-no-data">
                            <div class="empty-state-icon">
                                <i data-lucide="inbox"></i>
                            </div>
                            <h4 class="empty-state-title">Belum Ada Laporan</h4>
                            <p class="empty-state-description">Mulai dengan membuat laporan kehilangan atau temuan Anda.</p>
                            <a href="auth/register.php" class="btn btn-gradient-primary mt-4">Buat Laporan Sekarang</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-12 d-none" id="emptyFilterResult">
                <div class="empty-state-box mt-5 pt-5 pb-5">
                    <div class="empty-state-icon">
                        <i data-lucide="search-x"></i>
                    </div>
                    <h4 class="empty-state-title">Tidak Ada Data</h4>
                    <p class="empty-state-description">Tidak ada laporan yang cocok dengan kata kunci pencarian Anda.</p>
                </div>
            </div>

            <?php if ($totalPages > 1): ?>
                <nav class="mt-5" aria-label="Pagination laporan">
                    <ul class="pagination justify-content-center flex-wrap">
                        <li class="page-item <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link page-link-nav" href="<?php echo $currentPage > 1 ? htmlspecialchars($buildPageUrl($currentPage - 1)) : '#'; ?>">Sebelumnya</a>
                        </li>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i === $currentPage ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars($buildPageUrl($i)); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link page-link-nav" href="<?php echo $currentPage < $totalPages ? htmlspecialchars($buildPageUrl($currentPage + 1)) : '#'; ?>">Berikutnya</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </section>

    <footer id="faq">
        <div class="container position-relative px-4">
            <div class="row g-4 text-start">
                <div class="col-lg-5">
                    <a class="footer-brand" href="index.php">
                        <i data-lucide="search" class="me-2" style="color: var(--primary-purple); stroke-width: 3;"></i>
                        <span class="temu">Temu</span><span class="balik">Balik</span>
                    </a>
                    <p class="small pe-lg-5" style="max-width: 400px; line-height: 1.7;">Platform digital Lost & Found yang aman, transparan, dan terpercaya untuk mempermudah masyarakat Indonesia mengklaim barang temuan.</p>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="footer-heading">Platform</h6>
                    <ul class="list-unstyled">
                        <li><a href="index.php#features" class="footer-link">Fitur Utama</a></li>
                        <li><a href="index.php#workflow" class="footer-link">Alur Kerja</a></li>
                        <li><a href="laporan.php" class="footer-link">Laporan Terkini</a></li>
                    </ul>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="footer-heading">Dukungan</h6>
                    <ul class="list-unstyled">
                        <li><a href="#faq" class="footer-link">Bantuan</a></li>
                        <li><a href="#faq" class="footer-link">Kontak Kami</a></li>
                        <li><a href="#faq" class="footer-link">Kebijakan Privasi</a></li>
                    </ul>
                </div>

                <div class="col-lg-3">
                    <h6 class="footer-heading">Newsletter</h6>
                    <div class="newsletter-group-light">
                        <input type="email" placeholder="Email Anda">
                        <button type="button">Daftar</button>
                    </div>
                </div>
            </div>

            <hr class="mt-5 mb-4" style="border-color: rgba(0,0,0,0.06);">

            <div class="copyright-text">
                <p class="mb-0">© <?php echo date('Y'); ?> TemuBalik Team. Seluruh hak cipta dilindungi.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        lucide.createIcons();

        const nav = document.getElementById('mainNav');

        const searchInput = document.getElementById('filterSearch');
        const searchForm = document.getElementById('searchForm');
        const resetBtn = document.getElementById('resetBtn');
        const reportCards = Array.from(document.querySelectorAll('.report-card-col'));
        const emptyFilterResult = document.getElementById('emptyFilterResult');

        function filterReportsRealtime() {
            if (!searchInput || reportCards.length === 0) return;

            const keyword = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            reportCards.forEach((card) => {
                const haystack = (card.getAttribute('data-search') || '').toLowerCase();
                const isMatch = keyword === '' || haystack.includes(keyword);
                card.classList.toggle('d-none', !isMatch);
                if (isMatch) visibleCount += 1;
            });

            if (emptyFilterResult) {
                emptyFilterResult.classList.toggle('d-none', visibleCount > 0 || keyword === '');
            }
        }

        searchForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            filterReportsRealtime();
        });

        resetBtn?.addEventListener('click', (e) => {
            e.preventDefault();
            searchInput.value = '';
            filterReportsRealtime();
            if (emptyFilterResult) {
                emptyFilterResult.classList.add('d-none');
            }
        });

        searchInput?.addEventListener('input', filterReportsRealtime);
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        });

        const navLinks = document.querySelectorAll('.nav-link');
        const menuToggle = document.getElementById('navbarNav');
        const bsCollapse = new bootstrap.Collapse(menuToggle, {toggle: false});
        navLinks.forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    bsCollapse.hide();
                }
            });
        });
    </script>
</body>
</html>
