<?php

function getHeader($title = 'Lost & Found') {
    $user = getCurrentUser();
    $userName = $user['name'] ?? 'User';
    $userRole = $user['role'] ?? '';
    
    return <<<HTML
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>$title - Lost & Found</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <style>
            :root {
                --primary-color: #0d6efd;
            }

            .navbar-brand i {
                margin-right: 8px;
            }
            
            .sidebar {
                background: white;
                border-right: 1px solid #e9ecef;
                min-height: calc(100vh - 70px);
                padding: 20px 0;
                position: fixed;
                left: 0;
                top: 70px;
                width: 260px;
            }
            
            .main-content {
                margin-left: 260px;
                margin-top: 70px;
                padding: 30px;
                min-height: 100vh;
            }
            
            .sidebar .nav-link {
                color: #495057;
                padding: 12px 20px;
                border-left: 3px solid transparent;
                margin: 0 10px 5px 0;
                border-radius: 5px 0 0 5px;
                transition: all 0.3s;
            }
            
            .sidebar .nav-link:hover,
            .sidebar .nav-link.active {
                background-color: #f0f2f5;
                color: var(--primary-color);
                border-left-color: var(--primary-color);
            }
            
            .sidebar .nav-link i {
                margin-right: 10px;
                width: 20px;
            }
            
            .sidebar-title {
                padding: 12px 20px;
                font-size: 0.85rem;
                font-weight: 600;
                color: #888;
                text-transform: uppercase;
                margin-top: 20px;
                margin-bottom: 10px;
            }
            
            .card {
                border: 1px solid #e9ecef;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
                transition: all 0.3s;
            }
            
            .card:hover {
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            
            .card-header {
                background-color: #f8f9fa;
                border-bottom: 1px solid #e9ecef;
            }
            
            .badge {
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 0.85rem;
                font-weight: 500;
            }
            
            .btn {
                border-radius: 5px;
                font-weight: 500;
                padding: 8px 16px;
                font-size: 0.95rem;
            }
            
            .btn-primary {
                background-color: var(--primary-color);
                border-color: var(--primary-color);
                color: #fff !important;
            }
            
            .btn-primary:hover {
                background-color: #0b5ed7;
                border-color: #0b5ed7;
                color: #fff !important;
            }

            .btn-primary:focus,
            .btn-primary:active {
                background-color: #0b5ed7 !important;
                border-color: #0b5ed7 !important;
                color: #fff !important;
            }
            
            .btn-sm {
                padding: 6px 12px;
                font-size: 0.85rem;
            }
            
            .form-control, .form-select {
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 10px 12px;
                font-size: 0.95rem;
            }
            
            .form-control:focus, .form-select:focus {
                border-color: var(--primary-color);
                box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
            }
            
            .table {
                background: white;
                border-radius: 8px;
                overflow: hidden;
            }
            
            .table thead {
                background-color: #f8f9fa;
                border-bottom: 2px solid #e9ecef;
            }
            
            .table th {
                color: #495057;
                font-weight: 600;
                padding: 12px;
                border: none;
            }
            
            .table td {
                padding: 12px;
                vertical-align: middle;
                border-color: #e9ecef;
            }
            
            .table tbody tr:hover {
                background-color: #f8f9fa;
            }
            
            .status-disimpan {
                background-color: #e7f3ff;
                color: #0066cc;
            }
            
            .status-dicocokkan {
                background-color: #fff3cd;
                color: #856404;
            }
            
            .status-diklaim {
                background-color: #d4edda;
                color: #155724;
            }
            
            .status-diserahkan {
                background-color: #d1ecf1;
                color: #0c5460;
            }
            
            .status-dilaporkan {
                background-color: #f8d7da;
                color: #721c24;
            }
            
            .status-ditemukan {
                background-color: #d4edda;
                color: #155724;
            }
            
            .user-profile {
                display: flex;
                align-items: center;
                gap: 10px;
                color: #495057;
            }
            
            .avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: var(--primary-color);
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
            }
            
            .alert {
                border-radius: 5px;
                border: none;
            }
            
            .role-badge {
                display: inline-block;
                padding: 4px 8px;
                background: #f8f9fa;
                color: #495057;
                border-radius: 4px;
                font-size: 0.8rem;
                font-weight: 600;
                margin-left: 8px;
            }
            
            .role-petugas {
                background: #e7f3ff;
                color: #0066cc;
            }
            
            .role-pelapor {
                background: #d4edda;
                color: #155724;
            }
            
            @media (max-width: 768px) {
                .sidebar {
                    width: 100%;
                    position: relative;
                    top: 0;
                    min-height: auto;
                    border-right: none;
                    border-bottom: 1px solid #e9ecef;
                }
                
                .main-content {
                    margin-left: 0;
                    margin-top: 0;
                    padding: 20px;
                }
            }
        </style>
    </head>
    <body>
        <nav class="navbar navbar-expand-lg navbar-light fixed-top">
            <div class="container-fluid">
                <a class="navbar-brand" href="/web-lostfound/dashboard/">
                    <i class="bi bi-search"></i> Lost & Found
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle user-profile" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <div class="avatar">{$userName[0]}</div>
                                <div>
                                    <div style="font-size: 0.9rem;">{$userName}</div>
                                    <span class="role-badge role-{$userRole}">{$userRole}</span>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="#"><i class="bi bi-person"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/web-lostfound/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
HTML;
}

function getSidebar() {
    $user = getCurrentUser();
    $role = $user['role'] ?? '';
    
    $html = <<<HTML
    <aside class="sidebar">
        <div class="sidebar-title">Menu Utama</div>
        <nav class="nav flex-column">
            <a class="nav-link" href="/web-lostfound/dashboard/">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
HTML;
    
    if ($role === 'petugas') {
        $html .= <<<HTML
            <a class="nav-link" href="/web-lostfound/barang_temuan/">
                <i class="bi bi-box"></i> Barang Temuan
            </a>
            <a class="nav-link" href="/web-lostfound/matching/">
                <i class="bi bi-shuffle"></i> Pencocokan
            </a>
            <a class="nav-link" href="/web-lostfound/verifikasi/">
                <i class="bi bi-check-circle"></i> Verifikasi
            </a>
            <a class="nav-link" href="/web-lostfound/laporan_kehilangan/">
                <i class="bi bi-files"></i> Laporan
            </a>
HTML;
    } elseif ($role === 'pelapor') {
        $html .= <<<HTML
            <a class="nav-link" href="/web-lostfound/laporan_kehilangan/">
                <i class="bi bi-file-earmark-text"></i> Laporan Saya
            </a>
            <a class="nav-link" href="/web-lostfound/laporan_kehilangan/status.php">
                <i class="bi bi-clock-history"></i> Status Tracking
            </a>
HTML;
    }
    
    $html .= <<<HTML
        </nav>
    </aside>
HTML;
    
    return $html;
}

function getFooter() {
    return <<<HTML
    <footer class="py-4 border-top mt-5 bg-white">
        <div class="container-fluid text-center text-muted">
            <p class="mb-0">&copy; 2026 Lost & Found Management System. All rights reserved.</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/web-lostfound/assets/js/main.js"></script>
    </body>
    </html>
HTML;
}

?>
