<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Redirect jika user sudah punya sesi aktif.
if (isLoggedIn()) {
    header('Location: ../dashboard/');
    exit;
}

$error = '';
$success = '';
$form_type = $_GET['type'] ?? 'login'; // Default ke login

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (empty($email) || empty($password)) {
        $error = 'Email dan password harus diisi';
    } else {
        $stmt = $conn->prepare('SELECT id, email, password, name, role FROM users WHERE email = ? AND is_active = 1');
        if (!$stmt) {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
            if ($tableCheck && $tableCheck->num_rows === 0) {
                $error = 'Tabel users belum tersedia. Silakan import ulang file database.sql.';
            } else {
                $error = 'Gagal memproses login. Detail: ' . $conn->error;
            }
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (verifyPassword($password, $user['password'])) {
                    // Simpan data inti user ke session.
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['logged_in'] = true;

                    header('Location: ../dashboard/');
                    exit;
                } else {
                    $error = 'Email atau password salah';
                }
            } else {
                $error = 'Email atau password salah';
            }
            $stmt->close();
        }
    }
}

// Handle Register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');
    
    if (empty($name) || empty($email) || empty($password) || empty($password_confirm)) {
        $error = 'Semua field harus diisi';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter';
    } elseif ($password !== $password_confirm) {
        $error = 'Kata sandi tidak cocok';
    } else {
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        if (!$stmt) {
            $tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
            if ($tableCheck && $tableCheck->num_rows === 0) {
                $error = 'Tabel users belum tersedia. Silakan import ulang file database.sql.';
            } else {
                $error = 'Gagal memproses registrasi. Detail: ' . $conn->error;
            }
        } else {
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error = 'Email sudah terdaftar';
            } else {
                // Password disimpan dalam bentuk hash (bcrypt).
                $hashed_password = hashPassword($password);

                $insertStmt = $conn->prepare('INSERT INTO users (email, password, name, role) VALUES (?, ?, ?, ?)');
                if (!$insertStmt) {
                    $error = 'Gagal memproses registrasi. Detail: ' . $conn->error;
                } else {
                    // Registrasi publik selalu menjadi pelapor.
                    $role = 'pelapor';
                    $insertStmt->bind_param('ssss', $email, $hashed_password, $name, $role);

                    if ($insertStmt->execute()) {
                        $success = 'Registrasi berhasil! Silakan login.';
                        $form_type = 'login';
                    } else {
                        $error = 'Terjadi kesalahan saat registrasi: ' . $conn->error;
                    }
                    $insertStmt->close();
                }
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
    <title>Masuk & Daftar - TemuBalik</title>
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
            --card-shadow: 0 20px 40px -10px rgba(139, 92, 246, 0.15);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-light);
            color: var(--text-main);
            min-height: 100vh;
            width: 100%;
            max-width: 100vw;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            padding: 20px;
            position: relative;
        }

        /* --- Background Blobs --- */
        .blob {
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.1) 0%, rgba(139, 92, 246, 0) 70%);
            filter: blur(80px);
            border-radius: 50%;
            z-index: -1;
        }
        .blob-1 { top: -200px; left: -200px; }
        .blob-2 { bottom: -200px; right: -200px; background: radial-gradient(circle, rgba(99, 102, 241, 0.1) 0%, rgba(99, 102, 241, 0) 70%); }

        /* --- Auth Card --- */
        .auth-container {
            width: 100%;
            max-width: 450px;
            perspective: 1000px;
            z-index: 10;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 32px;
            padding: 40px;
            box-shadow: var(--card-shadow);
            transition: all 0.5s ease;
        }

        .brand-logo {
            font-size: 1.5rem;
            color: var(--text-main);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
        }
        .brand-logo .temu { font-weight: 600; } 
        .brand-logo .balik { font-weight: 800; color: var(--primary-purple); }

        /* --- Form Elements --- */
        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-slate);
            margin-left: 4px;
            margin-bottom: 8px;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group-custom .main-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-slate);
            z-index: 5;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-slate);
            cursor: pointer;
            z-index: 5;
            background: none;
            border: none;
            padding: 0;
            display: flex;
            align-items: center;
            transition: color 0.2s ease;
        }

        .password-toggle:hover {
            color: var(--primary-purple);
        }

        .form-control-custom {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 14px 16px 14px 48px;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            width: 100%;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .form-control-custom:focus {
            outline: none;
            border-color: var(--primary-purple);
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.1);
        }

        .btn-auth {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 16px;
            padding: 14px;
            font-weight: 700;
            width: 100%;
            margin-top: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px -5px rgba(139, 92, 246, 0.4);
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px -10px rgba(139, 92, 246, 0.5);
            color: white;
        }

        .auth-footer {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-slate);
        }

        .auth-footer a {
            color: var(--primary-purple);
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }

        .back-home {
            position: fixed;
            top: 30px;
            left: 30px;
            color: var(--text-slate);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            z-index: 100;
            max-width: calc(100vw - 40px);
        }

        .back-home:hover {
            color: var(--primary-purple);
            transform: translateX(-5px);
        }

        /* --- Transitions --- */
        #register-form { display: none; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade {
            animation: fadeIn 0.4s ease forwards;
        }

        .alert {
            border-radius: 16px;
            border: none;
            margin-bottom: 20px;
            padding: 14px 16px;
        }

        .form-check {
            margin-bottom: 15px;
        }

        .form-check-input {
            border-color: #e2e8f0;
            border-radius: 4px;
            width: 1em;
            height: 1em;
            margin-top: 2px;
        }

        .form-check-input:checked {
            background-color: var(--primary-purple);
            border-color: var(--primary-purple);
        }

        .form-check-label {
            color: var(--text-slate);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-check-label a {
            color: var(--primary-purple);
            text-decoration: none;
        }

        .form-check-label a:hover {
            text-decoration: underline;
        }

        @media (max-width: 576px) {
            body {
                display: block;
                padding: 16px;
            }
            .auth-container {
                margin: 8px auto 0;
            }
            .auth-card { padding: 30px 20px; }
            .back-home {
                position: static;
                top: auto;
                left: auto;
                display: inline-flex;
                margin-bottom: 10px;
                font-size: 0.85rem;
                max-width: 100%;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }
    </style>
</head>
<body>

    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <a href="../index.php" class="back-home">
        <i data-lucide="arrow-left" width="18" height="18"></i> Kembali ke Beranda
    </a>

    <div class="auth-container">
        <!-- Login Form -->
        <div id="login-form" class="auth-card animate-fade" style="display: <?php echo $form_type === 'login' ? 'block' : 'none'; ?>;">
            <a class="brand-logo" href="../index.php">
                <i data-lucide="search" width="24" height="24" class="me-2" style="color: var(--primary-purple); stroke-width: 3;"></i>
                <span class="temu">Temu</span><span class="balik">Balik</span>
            </a>

            <div class="text-center mb-4">
                <h4 class="fw-800 mb-1">Selamat Datang!</h4>
                <p class="text-slate small">Silakan masuk untuk melanjutkan pencarian.</p>
            </div>

            <?php if (!empty($error) && $form_type === 'login'): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" width="18" height="18" class="me-2" style="display: inline-block;"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success) && $form_type === 'login'): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" width="18" height="18" class="me-2" style="display: inline-block;"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="login">

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-group-custom">
                        <i data-lucide="mail" width="18" height="18" class="main-icon"></i>
                        <input type="email" class="form-control form-control-custom" name="email" placeholder="contoh@email.com" required>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label">Kata Sandi</label>
                    <div class="input-group-custom">
                        <i data-lucide="lock" width="18" height="18" class="main-icon"></i>
                        <input type="password" id="loginPassword" class="form-control form-control-custom" name="password" placeholder="••••••••" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('loginPassword', this)">
                            <i data-lucide="eye" width="18" height="18"></i>
                        </button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">Ingat Saya</label>
                    </div>
                    <a href="#" class="small text-decoration-none fw-600" style="color: var(--primary-purple);">Lupa Sandi?</a>
                </div>

                <button type="submit" class="btn btn-auth">Masuk Sekarang</button>
            </form>

            <div class="auth-footer">
                Belum punya akun? <a onclick="switchForm('register')">Daftar Gratis</a>
            </div>
        </div>

        <!-- Register Form -->
        <div id="register-form" class="auth-card" style="display: <?php echo $form_type === 'register' ? 'block' : 'none'; ?>;">
            <a class="brand-logo" href="../index.php">
                <i data-lucide="search" width="24" height="24" class="me-2" style="color: var(--primary-purple); stroke-width: 3;"></i>
                <span class="temu">Temu</span><span class="balik">Balik</span>
            </a>

            <div class="text-center mb-4">
                <h4 class="fw-800 mb-1">Buat Akun Baru</h4>
                <p class="text-slate small">Gabung dengan komunitas penemu terbesar.</p>
            </div>

            <?php if (!empty($error) && $form_type === 'register'): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" width="18" height="18" class="me-2" style="display: inline-block;"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success) && $form_type === 'register'): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle" width="18" height="18" class="me-2" style="display: inline-block;"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="register">

                <div class="mb-3">
                    <label class="form-label">Nama Lengkap</label>
                    <div class="input-group-custom">
                        <i data-lucide="user" width="18" height="18" class="main-icon"></i>
                        <input type="text" class="form-control form-control-custom" name="name" placeholder="Nama lengkap Anda" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <div class="input-group-custom">
                        <i data-lucide="mail" width="18" height="18" class="main-icon"></i>
                        <input type="email" class="form-control form-control-custom" name="email" placeholder="contoh@email.com" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nomor Telepon <span style="font-size: 0.8rem; color: #cbd5e1;">(Opsional)</span></label>
                    <div class="input-group-custom">
                        <i data-lucide="phone" width="18" height="18" class="main-icon"></i>
                        <input type="tel" class="form-control form-control-custom" name="phone" placeholder="081234567890">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Kata Sandi</label>
                    <div class="input-group-custom">
                        <i data-lucide="lock" width="18" height="18" class="main-icon"></i>
                        <input type="password" id="registerPassword" class="form-control form-control-custom" name="password" placeholder="Minimal 8 karakter" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('registerPassword', this)">
                            <i data-lucide="eye" width="18" height="18"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Konfirmasi Kata Sandi</label>
                    <div class="input-group-custom">
                        <i data-lucide="lock" width="18" height="18" class="main-icon"></i>
                        <input type="password" id="registerPasswordConfirm" class="form-control form-control-custom" name="password_confirm" placeholder="Konfirmasi kata sandi" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('registerPasswordConfirm', this)">
                            <i data-lucide="eye" width="18" height="18"></i>
                        </button>
                    </div>
                </div>

                <div class="form-check mb-4">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        Saya setuju dengan <a href="#">Syarat & Ketentuan</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-auth">Daftar Akun</button>
            </form>

            <div class="auth-footer">
                Sudah punya akun? <a onclick="switchForm('login')">Masuk di sini</a>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Inisialisasi awal
        lucide.createIcons();

        function switchForm(type) {
            const loginForm = document.getElementById('login-form');
            const registerForm = document.getElementById('register-form');

            if (type === 'register') {
                loginForm.classList.remove('animate-fade');
                loginForm.style.display = 'none';
                registerForm.style.display = 'block';
                registerForm.classList.add('animate-fade');
            } else {
                registerForm.classList.remove('animate-fade');
                registerForm.style.display = 'none';
                loginForm.style.display = 'block';
                loginForm.classList.add('animate-fade');
            }
            // Re-render ikon untuk memastikan ikon di form yang baru muncul terproses
            lucide.createIcons();
        }

        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                input.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            // Re-render hanya ikon mata
            lucide.createIcons();
        }
    </script>
</body>
</html>
