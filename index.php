<?php
require_once 'config/database.php';
require_once 'config/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard/');
    exit;
}

// Ambil 6 laporan terbaru (stabil) dari database: barang temuan + laporan kehilangan
$recentItems = [];
try {
    $stmt = $conn->prepare("
     SELECT * FROM (
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
    ORDER BY tanggal_laporan DESC, id DESC
     LIMIT 6
    ");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recentItems[] = $row;
        }
    }
} catch (Exception $e) {
    // Jika query gagal, gunakan item kosong
    $recentItems = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TemuBalik - Platform Lost & Found Digital Terpercaya</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Lucide Icons -->
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

        nav, header, section, footer {
            max-width: 100%;
            overflow-x: clip;
        }

        /* --- Branding --- */
        .brand-logo {
            font-size: 1.4rem;
            color: var(--text-main);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        .brand-logo .temu { font-weight: 600; } 
        .brand-logo .balik { font-weight: 800; color: var(--primary-purple); }

        /* --- Background Radial Blobs --- */
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

        /* --- Navbar --- */
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

        /* --- Hero Section --- */
        .hero-section {
            padding: 160px 0 80px;
            position: relative;
        }

        .hero-section h1 {
            font-weight: 800;
            letter-spacing: -0.02em;
            font-size: clamp(2.2rem, 5vw, 3.5rem);
        }

        .gradient-text {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 800;
        }

        .hero-desc {
            font-weight: 500;
            color: var(--text-slate);
            max-width: 720px;
            margin: 0 auto 2.5rem;
            font-size: clamp(1rem, 2vw, 1.15rem);
            line-height: 1.6;
        }

        /* --- Buttons --- */
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

        .btn-outline-custom {
            border: 2px solid var(--primary-purple);
            color: var(--primary-purple);
            padding: 12px 30px;
            border-radius: 14px;
            font-weight: 700;
            background: #ffffff;
            transition: all 0.3s ease;
        }

        .btn-outline-custom:hover {
            background: var(--primary-gradient);
            color: #ffffff !important;
            border-color: transparent;
            transform: translateY(-3px);
        }

        /* --- Perbaikan Button "Lihat Semua Laporan" --- */
        .btn-section-link {
            background: rgba(139, 92, 246, 0.05);
            color: var(--deep-purple);
            border: 2px solid var(--primary-purple);
            padding: 12px 32px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            white-space: normal;
            text-align: center;
        }

        .btn-section-link:hover {
            background: var(--primary-purple);
            color: white !important;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px -5px rgba(139, 92, 246, 0.4);
        }

        .btn-section-link i { transition: transform 0.3s ease; }
        .btn-section-link:hover i { transform: translateX(4px); }

        /* --- Section Titles --- */
        .section-title {
            font-weight: 700;
            font-size: clamp(1.75rem, 4vw, 2.25rem);
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        /* --- Cards --- */
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

        /* Reporter Row Styling */
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

        /* --- Icons & Workflow --- */
        .icon-box {
            width: 56px;
            height: 56px;
            background: var(--soft-purple);
            color: var(--primary-purple);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }

        .step-circle {
            width: 52px;
            height: 52px;
            background: #ffffff;
            color: var(--primary-purple);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.25rem;
            margin-right: 1.5rem;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(139, 92, 246, 0.1);
        }

        /* --- Badges --- */
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

        /* --- CTA Full Background Section --- */
        .cta-full-section {
            padding: 80px 0;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
            position: relative;
            overflow: hidden;
            width: 100%;
        }

        .cta-blob-full {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.15) 0%, rgba(139, 92, 246, 0) 70%);
            filter: blur(80px);
            border-radius: 50%;
            pointer-events: none;
        }
        .cta-blob-1 { top: -200px; right: -100px; }
        .cta-blob-2 { bottom: -200px; left: -100px; background: radial-gradient(circle, rgba(129, 140, 248, 0.1) 0%, rgba(129, 140, 248, 0) 70%); }

        .cta-title-full {
            font-weight: 700;
            font-size: clamp(1.75rem, 4vw, 2.25rem);
            color: white;
            margin-bottom: 12px;
            letter-spacing: -0.01em;
        }

        .cta-text-full {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.05rem;
            max-width: 680px;
            margin: 0;
            line-height: 1.6;
        }

        /* --- Footer --- */
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
            cursor: pointer;
        }

        .copyright-text {
            color: var(--text-slate); 
            font-size: 0.85rem;
            text-align: center;
            font-weight: 500;
            margin-top: 3rem;
            opacity: 0.8;
        }

        /* --- Custom Responsive Fixes --- */
        @media (max-width: 991px) {
            .navbar { padding: 0.8rem 0; }
            .cta-full-section { text-align: center; padding: 60px 0; }
            .cta-text-full { margin: 0 auto 30px; }
            .cta-full-section .btn-gradient-primary { width: 100%; max-width: 320px; }
            .section-header-flex { flex-direction: column; text-align: center; align-items: center !important; }
            .section-header-flex .text-start { text-align: center !important; margin-bottom: 1.5rem; }
            .btn-section-link { width: 100%; justify-content: center; }
        }

        @media (max-width: 768px) {
            .hero-section { padding: 130px 0 60px; }
            .hero-section .d-flex { flex-direction: column; width: 100%; max-width: 320px; margin-left: auto; margin-right: auto; }
            .newsletter-group-light { flex-direction: column; gap: 8px; padding: 12px; }
            .newsletter-group-light button { padding: 12px; width: 100%; }
            footer { text-align: left; padding: 50px 0 30px; }
            .footer-brand { justify-content: flex-start; }
            .cta-full-section { padding: 60px 20px; }
            .blob { width: 300px; height: 300px; }
            .section-title { font-size: 1.85rem; }
        }
    </style>
</head>
<body>

    <!-- Radial Background Blobs -->
    <div class="blob blob-left"></div>
    <div class="blob blob-right"></div>
    <div class="blob blob-bottom-left"></div>

    <!-- Navigasi (DIURUTKAN SESUAI SECTION) -->
    <nav class="navbar navbar-expand-lg fixed-top" id="mainNav">
        <div class="container">
            <a class="brand-logo" href="#">
                <i data-lucide="search" class="me-2" style="color: var(--primary-purple); stroke-width: 3;"></i>
                <span class="temu">Temu</span><span class="balik">Balik</span>
            </a>

            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i data-lucide="menu" style="color: var(--text-main);"></i>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto align-items-center mb-3 mb-lg-0">
                    <li class="nav-item"><a class="nav-link px-3" href="#features">Fitur</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="#workflow">Cara Kerja</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="#recent">Laporan</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="#faq">Bantuan</a></li>
                </ul>
                <div class="text-center text-lg-start">
                    <a href="auth/login.php" class="btn btn-gradient-primary px-4 w-100 w-lg-auto">Mulai Lapor</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero-section text-center">
        <div class="container px-4">
            <h1>Kehilangan Sesuatu? <br class="d-none d-md-block"><span class="gradient-text">Kami Bantu Menemukannya.</span></h1>
            <p class="hero-desc">
                Platform Lost & Found digital modern dengan sistem keamanan tinggi untuk mempercepat penemuan kembali barang berharga Anda.
            </p>
            <div class="d-flex justify-content-center gap-3 flex-wrap">
                <a href="auth/register.php" class="btn btn-gradient-primary btn-lg d-flex align-items-center shadow">
                    <i data-lucide="plus-circle" class="me-2"></i> Lapor Kehilangan
                </a>
                <a href="auth/login.php" class="btn btn-outline-custom btn-lg d-flex align-items-center">
                    <i data-lucide="package" class="me-2"></i> Temukan Barang
                </a>
            </div>
        </div>
    </header>

    <!-- Fitur Section -->
    <section id="features" class="py-5">
        <div class="container py-lg-5 text-center">
            <h2 class="section-title">Layanan Digital Kami</h2>
            <p class="text-slate mx-auto mb-5 px-3" style="max-width: 600px; font-weight: 400;">Proses pengembalian yang aman dan transparan.</p>
            
            <div class="row g-4 text-start px-3 px-md-0">
                <div class="col-sm-6 col-lg-3">
                    <div class="card-custom">
                        <div class="icon-box"><i data-lucide="edit-3"></i></div>
                        <h5 class="card-title">Pencatatan Presisi</h5>
                        <p class="card-text-small mb-0">Input temuan dengan detail lokasi GPS dan dokumentasi foto berkualitas tinggi.</p>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card-custom">
                        <div class="icon-box"><i data-lucide="zap"></i></div>
                        <h5 class="card-title">Auto-Matching</h5>
                        <p class="card-text-small mb-0">Sistem cerdas kami mendeteksi kecocokan laporan secara real-time 24/7.</p>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card-custom">
                        <div class="icon-box"><i data-lucide="shield-check"></i></div>
                        <h5 class="card-title">Verifikasi Aman</h5>
                        <p class="card-text-small mb-0">Validasi kepemilikan ganda untuk menjamin barang ke tangan yang benar.</p>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card-custom">
                        <div class="icon-box"><i data-lucide="qr-code"></i></div>
                        <h5 class="card-title">Handover Digital</h5>
                        <p class="card-text-small mb-0">Serah terima terdokumentasi dengan tanda tangan digital & QR Code resmi.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Workflow Section -->
    <section id="workflow" class="py-5" style="background: linear-gradient(180deg, rgba(250,250,255,0) 0%, rgba(243,240,255,1) 100%);">
        <div class="container py-lg-5 px-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <h2 class="section-title text-center text-lg-start mb-5">Bagaimana Kami Membantu?</h2>
                    <div class="d-flex mb-4">
                        <div class="step-circle">1</div>
                        <div>
                            <h5 class="card-title">Pelaporan Cepat</h5>
                            <p class="card-text-small mb-0">Isi formulir singkat dengan deskripsi barang dan lampirkan foto untuk mempercepat pencocokan.</p>
                        </div>
                    </div>
                    <div class="d-flex mb-4">
                        <div class="step-circle">2</div>
                        <div>
                            <h5 class="card-title">Validasi Pemilik</h5>
                            <p class="card-text-small mb-0">Jika ditemukan kecocokan, Anda akan melalui proses verifikasi bukti kepemilikan yang aman.</p>
                        </div>
                    </div>
                    <div class="d-flex mb-0">
                        <div class="step-circle">3</div>
                        <div>
                            <h5 class="card-title">Serah Terima Sah</h5>
                            <p class="card-text-small mb-0">Barang dikembalikan dengan dokumentasi digital sebagai bukti proses telah selesai dengan benar.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="p-5 rounded-4 text-white shadow-lg text-center" style="background: var(--primary-gradient);">
                        <i data-lucide="shield-check" style="width: 80px; height: 80px;" class="mb-4"></i>
                        <h3 class="fw-700 mb-3">Keamanan Prioritas Utama</h3>
                        <p class="opacity-90 lead mb-0" style="font-weight: 500;">Kami menjamin data Anda terlindungi dan setiap proses penemuan barang diawasi secara digital.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Recent Items Section -->
    <section id="recent" class="py-5">
        <div class="container py-4 px-4">
            <!-- Header Flex Layout -->
            <div class="d-flex justify-content-between align-items-end mb-5 section-header-flex">
                <div class="text-start">
                    <h2 class="section-title">Laporan Terbaru</h2>
                    <p class="text-slate mb-0" style="font-weight: 400;">Pantau barang-barang yang baru saja dilaporkan.</p>
                </div>
                <div>
                    <a href="laporan.php" class="btn-section-link shadow-sm">
                        Lihat Semua Laporan <i data-lucide="arrow-right" class="ms-2" size="20"></i>
                    </a>
                </div>
            </div>

            <div class="row g-4 text-start">
                <?php 
                if (!empty($recentItems)) {
                    foreach ($recentItems as $item): 
                        $getInitials = function($name) {
                            $parts = explode(' ', trim($name));
                            $initials = '';
                            foreach (array_slice($parts, 0, 2) as $part) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                            return $initials ?: 'U';
                        };
                        $initials = $getInitials($item['reporter_name']);
                        $badgeClass = $item['type'] === 'kehilangan' ? 'badge-lost' : 'badge-found';
                        $badgeText = $item['type'] === 'kehilangan' ? 'Hilang' : 'Ditemukan';
                ?>
                <!-- Item Card -->
                <div class="col-md-6 col-lg-4">
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
                <?php 
                    endforeach;
                } else { 
                ?>
                <!-- No Data Message -->
                <div class="col-12">
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 300px; text-align: center;">
                        <div style="display: inline-block; margin-bottom: 24px;">
                            <i data-lucide="inbox" style="color: var(--primary-purple); width: 60px; height: 60px;"></i>
                        </div>
                        <h4 style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-bottom: 12px;">Belum Ada Laporan</h4>
                        <p style="font-size: 0.95rem; color: var(--text-slate); margin: 0; line-height: 1.6;">Mulai dengan membuat laporan kehilangan atau temuan Anda.</p>
                        <a href="auth/register.php" class="btn btn-gradient-primary mt-4">Buat Laporan Sekarang</a>
                    </div>
                </div>
                <?php 
                } 
                ?>
            </div>
        </div>
    </section>

    <!-- Professional Left-Right CTA Section -->
    <section class="cta-full-section">
        <div class="cta-blob-full cta-blob-1"></div>
        <div class="cta-blob-full cta-blob-2"></div>
        
        <div class="container position-relative px-4">
            <div class="row align-items-center">
                <div class="col-lg-8 mb-4 mb-lg-0">
                    <h2 class="cta-title-full">Siap Menemukan Kembali Barang Anda?</h2>
                    <p class="cta-text-full">
                        Gunakan platform pelaporan yang aman dan transparan untuk mempercepat proses penemuan kembali. Bergabunglah sekarang tanpa biaya.
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="auth/register.php" class="btn btn-gradient-primary px-5 py-3 shadow-lg" style="text-decoration: none;">Daftar Sekarang</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Light Mode Footer -->
    <footer id="faq">
        <div class="container position-relative px-4">
            <div class="row g-4 text-start">
                <div class="col-lg-5">
                    <a class="footer-brand" href="#">
                        <i data-lucide="search" class="me-2" style="color: var(--primary-purple); stroke-width: 3;"></i>
                        <span class="temu">Temu</span><span class="balik">Balik</span>
                    </a>
                    <p class="small pe-lg-5" style="max-width: 400px; line-height: 1.7;">Platform digital Lost & Found yang aman, transparan, dan terpercaya untuk mempermudah masyarakat Indonesia mengklaim barang temuan.</p>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="footer-heading">Platform</h6>
                    <ul class="list-unstyled">
                        <li><a href="#features" class="footer-link">Fitur Utama</a></li>
                        <li><a href="#workflow" class="footer-link">Alur Kerja</a></li>
                        <li><a href="#recent" class="footer-link">Laporan Terkini</a></li>
                    </ul>
                </div>

                <div class="col-6 col-md-3 col-lg-2">
                    <h6 class="footer-heading">Dukungan</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="footer-link">Bantuan</a></li>
                        <li><a href="#" class="footer-link">Kontak Kami</a></li>
                        <li><a href="#" class="footer-link">Kebijakan Privasi</a></li>
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

    <!-- Scripts -->
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
