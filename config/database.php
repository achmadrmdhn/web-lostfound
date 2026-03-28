<?php
// Konfigurasi koneksi database (mendukung environment variable untuk lokal).
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_NAME', getenv('DB_NAME') ?: 'lost_found');

$envPass = getenv('DB_PASS');
$passwordCandidates = [];

if ($envPass !== false) {
    $passwordCandidates[] = $envPass;
}

$passwordCandidates[] = '';
$passwordCandidates[] = 'root';
$passwordCandidates = array_values(array_unique($passwordCandidates));

mysqli_report(MYSQLI_REPORT_OFF);

$conn = null;
$lastError = 'Unknown error';

foreach ($passwordCandidates as $pass) {
    $try = @new mysqli(DB_HOST, DB_USER, $pass, DB_NAME);
    if (!$try->connect_errno) {
        $conn = $try;
        break;
    }

    if ((int)$try->connect_errno === 1049) {
        $serverConn = @new mysqli(DB_HOST, DB_USER, $pass);
        if (!$serverConn->connect_errno) {
            if (!$serverConn->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
                $lastError = $serverConn->error;
                $serverConn->close();
                continue;
            }
            $serverConn->close();

            $retry = @new mysqli(DB_HOST, DB_USER, $pass, DB_NAME);
            if (!$retry->connect_errno) {
                $conn = $retry;
                break;
            }
            $lastError = $retry->connect_error;
            continue;
        }
    }

    $lastError = $try->connect_error;
}

if (!$conn) {
    die('Koneksi database gagal. Periksa username/password MySQL di config/database.php atau set DB_HOST, DB_USER, DB_PASS, DB_NAME pada environment. Detail: ' . $lastError);
}

$conn->set_charset('utf8mb4');

bootstrapSchema($conn);

function safePrepare(mysqli $conn, string $sql): mysqli_stmt {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('Query prepare gagal: ' . $conn->error);
    }
    return $stmt;
}

function bootstrapSchema(mysqli $conn): void {
    $check = $conn->query("SHOW TABLES LIKE 'users'");
    if ($check && $check->num_rows > 0) {
        return;
    }

    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            role ENUM('petugas', 'pelapor') NOT NULL DEFAULT 'pelapor',
            phone VARCHAR(15),
            address TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS barang_temuan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_barang VARCHAR(100) NOT NULL,
            kategori VARCHAR(50) NOT NULL,
            deskripsi TEXT,
            lokasi_ditemukan VARCHAR(255) NOT NULL,
            tanggal_ditemukan DATETIME NOT NULL,
            foto VARCHAR(255),
            status ENUM('disimpan', 'dicocokkan', 'diklaim', 'diserahkan', 'tidak_klaim') DEFAULT 'disimpan',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_status (status),
            INDEX idx_kategori (kategori),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS laporan_kehilangan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_barang VARCHAR(100) NOT NULL,
            kategori VARCHAR(50) NOT NULL,
            deskripsi TEXT,
            tanggal_hilang DATETIME NOT NULL,
            lokasi_hilang VARCHAR(255) NOT NULL,
            foto VARCHAR(255),
            status ENUM('dilaporkan', 'ditemukan', 'diserahkan', 'ditutup') DEFAULT 'dilaporkan',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_status (status),
            INDEX idx_kategori (kategori),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS matching (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barang_id INT NOT NULL,
            laporan_id INT NOT NULL,
            cocok_score INT DEFAULT 0,
            catatan TEXT,
            status ENUM('pending', 'cocok', 'tidak_cocok', 'dikonfirmasi') DEFAULT 'pending',
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_id) REFERENCES barang_temuan(id) ON DELETE CASCADE,
            FOREIGN KEY (laporan_id) REFERENCES laporan_kehilangan(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_barang_id (barang_id),
            INDEX idx_laporan_id (laporan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS penyerahan_barang (
            id INT AUTO_INCREMENT PRIMARY KEY,
            matching_id INT NOT NULL,
            tanggal_serah DATETIME NOT NULL,
            penerima_nama VARCHAR(100),
            penerima_phone VARCHAR(15),
            catatan TEXT,
            foto_serah VARCHAR(255),
            status ENUM('menunggu', 'diserahkan', 'ditolak') DEFAULT 'menunggu',
            petugas_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (matching_id) REFERENCES matching(id) ON DELETE CASCADE,
            FOREIGN KEY (petugas_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_matching_id (matching_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($queries as $sql) {
        if (!$conn->query($sql)) {
            die('Gagal membuat tabel awal: ' . $conn->error);
        }
    }

    $seedUsers = "INSERT INTO users (email, password, name, role, phone)
        VALUES
        ('petugas@gmail.com', '$2y$10$9STPQFH42mjiC8GBvCFOROXStJnYrt7bGHbpSNzXk1Ud5/otsGMEq', 'Petugas', 'petugas', '081234567890'),
        ('pelapor@gmail.com', '$2y$10$CkaGAu0g3PneWaAkivlNBOR492Cbu3DTobphVIQVaOS3p56zLjx7q', 'Pelapor', 'pelapor', '081234567891')
        ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        role = VALUES(role),
        phone = VALUES(phone)";

    if (!$conn->query($seedUsers)) {
        die('Gagal membuat akun awal: ' . $conn->error);
    }
}
?>
