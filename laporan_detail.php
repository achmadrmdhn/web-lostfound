<?php
require_once 'config/database.php';
require_once 'config/auth.php';

$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$allowedTypes = ['temuan', 'kehilangan'];
if (!in_array($type, $allowedTypes, true) || $id <= 0) {
    http_response_code(404);
    $notFound = true;
} else {
    $notFound = false;
}

$report = null;

if (!$notFound) {
    try {
        if ($type === 'temuan') {
            $stmt = $conn->prepare(
                "SELECT 
                    'temuan' as type,
                    bt.id,
                    bt.nama_barang,
                    bt.kategori,
                    bt.deskripsi,
                    bt.lokasi_ditemukan as lokasi_laporan,
                    bt.tanggal_ditemukan as tanggal_laporan,
                    bt.foto,
                    bt.status as status_laporan,
                    u.name as reporter_name,
                    u.email as reporter_email
                FROM barang_temuan bt
                JOIN users u ON bt.created_by = u.id
                WHERE bt.id = ? AND bt.status != 'diserahkan'"
            );
        } else {
            $stmt = $conn->prepare(
                "SELECT 
                    'kehilangan' as type,
                    lk.id,
                    lk.nama_barang,
                    lk.kategori,
                    lk.deskripsi,
                    lk.lokasi_hilang as lokasi_laporan,
                    lk.tanggal_hilang as tanggal_laporan,
                    lk.foto,
                    lk.status as status_laporan,
                    u.name as reporter_name,
                    u.email as reporter_email
                FROM laporan_kehilangan lk
                JOIN users u ON lk.created_by = u.id
                WHERE lk.id = ? AND lk.status NOT IN ('diserahkan', 'ditutup')"
            );
        }

        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $report = $result ? $result->fetch_assoc() : null;
        }
    } catch (Exception $e) {
        $report = null;
    }
}

if (!$report) {
    $notFound = true;
}

$pageTitle = $report ? $report['nama_barang'] . ' - Detail Laporan' : 'Detail Laporan';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - TemuBalik</title>
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
            max-width: 100vw;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            scroll-behavior: smooth;
            position: relative;
        }

        nav, .detail-container, footer {
            max-width: 100%;
            overflow-x: clip;
        }

        .detail-container.container {
            max-width: 1140px;
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
            padding: 12px 24px;
            border-radius: 14px;
            font-weight: 700;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-gradient-primary:hover {
            background: #ffffff;
            color: var(--primary-purple) !important;
            border-color: var(--primary-purple);
            transform: translateY(-2px);
        }

        .btn-outline-custom {
            border: 2px solid var(--primary-purple);
            color: var(--primary-purple);
            padding: 12px 30px;
            border-radius: 14px;
            font-weight: 700;
            background: #ffffff;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-outline-custom:hover {
            background: var(--primary-gradient);
            color: #ffffff !important;
            border-color: transparent;
            transform: translateY(-3px);
        }

        .detail-container {
            padding-top: 120px;
            padding-bottom: 80px;
        }

        .breadcrumb-nav {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding: 0;
            font-size: 0.95rem;
        }

        .breadcrumb-link {
            color: var(--text-slate);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .breadcrumb-link:hover {
            color: var(--primary-purple);
        }

        .breadcrumb-separator {
            color: #cbd5e1;
            font-weight: 400;
            margin: 0 4px;
        }

        .breadcrumb-active {
            color: var(--primary-purple);
            font-weight: 600;
        }

        .detail-card {
            background: #ffffff;
            border-radius: 32px;
            border: 1px solid rgba(139, 92, 246, 0.1);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .detail-image-box {
            background: linear-gradient(45deg, #f1f5f9, #f5f3ff);
            height: 100%;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .detail-image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            min-height: 400px;
        }

        .detail-image-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #94a3b8;
            text-align: center;
            padding: 0 20px;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .detail-content-box {
            padding: 40px;
        }

        .detail-title {
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 12px;
            color: var(--text-main);
        }

        .detail-label {
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--primary-purple);
            margin-bottom: 8px;
            display: block;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin: 24px 0;
            padding: 20px;
            background: var(--soft-purple);
            border-radius: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-val {
            font-weight: 700;
            color: var(--text-main);
        }

        .info-key {
            font-size: 0.8rem;
            color: var(--text-slate);
        }

        .reporter-card {
            border: 1px solid rgba(0,0,0,0.05);
            padding: 20px;
            border-radius: 24px;
            margin-top: 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .reporter-avatar {
            width: 48px;
            height: 48px;
            background: var(--soft-purple);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-purple);
        }

        .reporter-name {
            font-size: 1.2rem;
            font-weight: 700;
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

        .badge-category {
            position: absolute;
            top: 16px;
            left: 16px;
            background: rgba(15, 23, 42, 0.82);
            color: #fff;
            padding: 7px 12px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            z-index: 2;
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

        @media (max-width: 991px) {
            .navbar { padding: 0.8rem 0; }
            .detail-image-box { min-height: 300px; }
            .detail-content-box { padding: 30px; }
            .detail-title { font-size: 1.8rem; }
            .info-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            footer { text-align: left; padding: 50px 0 30px; }
            .footer-brand { justify-content: flex-start; }
            .newsletter-group-light { flex-direction: column; gap: 8px; padding: 12px; }
            .newsletter-group-light button { padding: 12px; width: 100%; }
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

    <div class="container detail-container">
        <?php if ($notFound): ?>
            <nav class="breadcrumb-nav">
                <a class="breadcrumb-link" href="index.php">Beranda</a>
                <span class="breadcrumb-separator">></span>
                <a class="breadcrumb-link" href="laporan.php">Laporan</a>
                <span class="breadcrumb-separator">></span>
                <span class="breadcrumb-active">Laporan tidak ditemukan</span>
            </nav>
            <div class="detail-card p-4 p-lg-5 text-center">
                <i data-lucide="file-x" style="width: 52px; height: 52px; color: var(--primary-purple);"></i>
                <h2 class="h4 mt-3 mb-2">Laporan tidak ditemukan</h2>
                <p class="text-muted mb-4">Data laporan yang Anda cari tidak tersedia atau sudah dihapus.</p>
                <a href="laporan.php" class="btn btn-outline-custom">Kembali</a>
            </div>
        <?php else: ?>
            <?php
                $badgeClass = $report['type'] === 'kehilangan' ? 'badge-lost' : 'badge-found';
                $badgeText = $report['type'] === 'kehilangan' ? 'Hilang' : 'Ditemukan';
                $statusText = ucfirst(str_replace('_', ' ', (string) $report['status_laporan']));
                $parts = explode(' ', trim((string) $report['reporter_name']));
                $initials = '';
                foreach (array_slice($parts, 0, 2) as $part) {
                    $initials .= strtoupper(substr($part, 0, 1));
                }
                $reporterInitials = $initials !== '' ? $initials : 'U';
            ?>
            <nav class="breadcrumb-nav">
                <a class="breadcrumb-link" href="index.php">Beranda</a>
                <span class="breadcrumb-separator">></span>
                <a class="breadcrumb-link" href="laporan.php">Laporan</a>
                <span class="breadcrumb-separator">></span>
                <span class="breadcrumb-active"><?php echo htmlspecialchars($report['nama_barang']); ?></span>
            </nav>

            <div class="detail-card">
                <div class="row g-0">
                    <div class="col-lg-5">
                        <div class="detail-image-box">
                            <span class="badge-category"><?php echo htmlspecialchars($report['kategori']); ?></span>
                            <?php if (!empty($report['foto']) && file_exists('uploads/' . $report['foto'])): ?>
                                <img src="uploads/<?php echo htmlspecialchars($report['foto']); ?>" alt="<?php echo htmlspecialchars($report['nama_barang']); ?>">
                            <?php else: ?>
                                <div class="detail-image-placeholder">
                                    <i data-lucide="image-off" size="44"></i>
                                    <span>Belum ada foto barang</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="detail-content-box">
                            <div class="mb-3">
                                <span class="status-badge <?php echo $badgeClass; ?>"><?php echo $badgeText; ?></span>
                            </div>
                            <span class="detail-label">Informasi Laporan</span>
                            <h1 class="detail-title"><?php echo htmlspecialchars($report['nama_barang']); ?></h1>

                            <p class="text-slate mb-4"><?php echo nl2br(htmlspecialchars($report['deskripsi'])); ?></p>

                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-key">Lokasi</span>
                                    <span class="info-val d-flex align-items-center">
                                        <i data-lucide="map-pin" size="14" class="me-2 text-primary"></i>
                                        <span><?php echo htmlspecialchars($report['lokasi_laporan']); ?></span>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-key">Tanggal Dilaporkan</span>
                                    <span class="info-val d-flex align-items-center">
                                        <i data-lucide="calendar" size="14" class="me-2 text-primary"></i>
                                        <span><?php echo date('d M Y H:i', strtotime($report['tanggal_laporan'])); ?></span>
                                    </span>
                                </div>
                            </div>

                            <div class="reporter-card">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="reporter-avatar"><?php echo htmlspecialchars($reporterInitials); ?></div>
                                    <div>
                                        <span class="info-key d-block">Dilaporkan oleh</span>
                                        <span class="reporter-name"><?php echo htmlspecialchars($report['reporter_name']); ?></span>
                                        <span class="d-block text-slate small"><?php echo htmlspecialchars($report['reporter_email']); ?></span>
                                    </div>
                                </div>
                                <a href="mailto:<?php echo rawurlencode((string) $report['reporter_email']); ?>" class="btn btn-outline-custom">
                                    <i data-lucide="message-circle" size="18" class="me-2"></i> Hubungi
                                </a>
                            </div>

                            <div class="mt-4 pt-4 border-top">
                                <div class="d-flex gap-3">
                                    <a href="auth/login.php" class="btn btn-gradient-primary flex-grow-1 py-3">
                                        <i data-lucide="shield-check" size="18" class="me-2"></i> Klaim Barang Ini
                                    </a>
                                    <button id="btnShare" class="btn btn-outline-custom p-3" title="Bagikan" type="button">
                                        <i data-lucide="share-2" size="20"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

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
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }
        });
    </script>
</body>
</html>
