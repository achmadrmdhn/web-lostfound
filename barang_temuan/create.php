<?php
require_once '../config/database.php';
require_once '../config/auth.php';

requirePetugas();

$user = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_barang = trim($_POST['nama_barang'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $lokasi_ditemukan = trim($_POST['lokasi_ditemukan'] ?? '');
    $tanggal_ditemukan = trim($_POST['tanggal_ditemukan'] ?? '');
    $status = 'disimpan';
    $foto = '';
    
    if (empty($nama_barang) || empty($kategori) || empty($lokasi_ditemukan) || empty($tanggal_ditemukan)) {
        $error = 'Semua field wajib diisi';
    } else {
        // Upload foto bersifat opsional, tetapi tetap divalidasi.
        if (!empty($_FILES['foto']['name'])) {
            $file = $_FILES['foto'];
            $upload_dir = dirname(__DIR__) . '/uploads/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array(strtolower($file_ext), $allowed_ext)) {
                $error = 'Format file tidak didukung. Gunakan JPG, PNG, atau GIF';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'Ukuran file terlalu besar (max 5MB)';
            } else {
                $foto_name = 'barang_' . time() . '.' . $file_ext;
                $foto_path = $upload_dir . $foto_name;
                
                if (move_uploaded_file($file['tmp_name'], $foto_path)) {
                    $foto = $foto_name;
                } else {
                    $error = 'Gagal upload file';
                }
            }
        }
        
        if (empty($error)) {
            $stmt = safePrepare($conn, '
                INSERT INTO barang_temuan 
                (nama_barang, kategori, deskripsi, lokasi_ditemukan, tanggal_ditemukan, foto, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->bind_param('sssssssi', $nama_barang, $kategori, $deskripsi, $lokasi_ditemukan, $tanggal_ditemukan, $foto, $status, $user['id']);
            
            if ($stmt->execute()) {
                header('Location: index.php?crud_status=success&crud_action=create');
                exit;
            } else {
                $error = 'Gagal menambahkan data. Silakan coba lagi.';
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Barang Temuan - TemuBalik</title>
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

        .required-indicator {
            color: #ef4444;
        }

        .upload-area {
            border: 2px dashed #c4b5fd;
            border-radius: 16px;
            background: #faf7ff;
            padding: 28px 18px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .upload-area:hover,
        .upload-area.dragover {
            background: #f3ecff;
            border-color: var(--primary-purple);
        }

        .upload-area input {
            display: none;
        }

        .form-row-spacing {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
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
        <section class="page-header">
            <h1 class="page-title">➕ Tambah Barang Temuan</h1>
            <p class="page-subtitle">Catat dan simpan data barang yang telah ditemukan</p>
        </section>

        <?php if (!empty($error)): ?>
            <div class="alert-custom alert-danger-custom">
                <i data-lucide="alert-circle" width="20"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Nama Barang <span class="required-indicator">*</span></label>
                    <input type="text" class="form-control" name="nama_barang" placeholder="Masukkan nama barang" required>
                </div>

                <div class="form-row-spacing">
                    <div class="form-group">
                        <label class="form-label">Kategori <span class="required-indicator">*</span></label>
                        <select class="form-select" name="kategori" required>
                            <option value="">Pilih Kategori</option>
                            <option value="Perhiasan">Perhiasan</option>
                            <option value="Elektronik">Elektronik</option>
                            <option value="Kendaraan & Aksesori">Kendaraan & Aksesori</option>
                            <option value="Dokumen">Dokumen</option>
                            <option value="Tas & Dompet">Tas & Dompet</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tanggal Ditemukan <span class="required-indicator">*</span></label>
                        <input type="datetime-local" class="form-control" name="tanggal_ditemukan" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Lokasi Ditemukan <span class="required-indicator">*</span></label>
                    <input type="text" class="form-control" name="lokasi_ditemukan" placeholder="Contoh: Stasiun Pusat, Taman Kota, Pusat Perbelanjaan" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea class="form-control" name="deskripsi" rows="5" placeholder="Deskripsi detail barang, ciri-ciri khusus, kondisi barang, warna, brand, dll"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Foto Barang</label>
                    <div class="upload-area" id="fileDropzone">
                        <input type="file" id="file-input" class="form-control" name="foto" accept="image/*">
                        <i data-lucide="image-plus" width="48" height="48" class="text-slate mb-3"></i>
                        <h6 class="fw-700 mb-1">Klik atau Drag Foto ke Sini</h6>
                        <p class="text-slate small mb-0" id="uploadHint">Format JPG, PNG (Maks 5MB)</p>
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn-submit">
                        <i data-lucide="save" width="18"></i> Simpan Barang
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
        
        const fileDropzone = document.getElementById('fileDropzone');
        const fileInput = document.getElementById('file-input');
        const uploadHint = document.getElementById('uploadHint');

        fileDropzone.addEventListener('click', () => fileInput.click());

        fileDropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileDropzone.classList.add('dragover');
        });

        fileDropzone.addEventListener('dragleave', () => {
            fileDropzone.classList.remove('dragover');
        });

        fileDropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            fileDropzone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateUploadHint();
            }
        });

        fileInput.addEventListener('change', updateUploadHint);

        function updateUploadHint() {
            if (fileInput.files.length > 0) {
                uploadHint.textContent = 'File terpilih: ' + fileInput.files[0].name;
            }
        }
    </script>
</body>
</html>
