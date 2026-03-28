-- Lost & Found Database
CREATE DATABASE IF NOT EXISTS lost_found;
USE lost_found;

-- Tabel pengguna dan peran.
CREATE TABLE IF NOT EXISTS users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data barang temuan oleh petugas.
CREATE TABLE IF NOT EXISTS barang_temuan (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data laporan kehilangan oleh pelapor.
CREATE TABLE IF NOT EXISTS laporan_kehilangan (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relasi pencocokan barang dan laporan.
CREATE TABLE IF NOT EXISTS matching (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catatan verifikasi/penyerahan barang.
CREATE TABLE IF NOT EXISTS penyerahan_barang (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed akun awal (petugas123 / pelapor123).
INSERT INTO users (email, password, name, role, phone) VALUES
('petugas@gmail.com', '$2y$10$9STPQFH42mjiC8GBvCFOROXStJnYrt7bGHbpSNzXk1Ud5/otsGMEq', 'Petugas', 'petugas', '081234567890'),
('pelapor@gmail.com', '$2y$10$CkaGAu0g3PneWaAkivlNBOR492Cbu3DTobphVIQVaOS3p56zLjx7q', 'Pelapor', 'pelapor', '081234567891');

-- Seed contoh barang temuan.
INSERT INTO barang_temuan (nama_barang, kategori, deskripsi, lokasi_ditemukan, tanggal_ditemukan, status, created_by) VALUES
('Dompet Kulit Hitam', 'Tas & Dompet', 'Dompet kulit hitam dengan inisial AZ, berisi kartu member', 'Stasiun Manggarai - Peron 7', '2026-03-20 10:30:00', 'disimpan', 1),
('Kunci Motor Honda', 'Kendaraan & Aksesori', 'Kunci motor Honda dengan gantungan karakter warna kuning', 'Area parkir Stasiun Bekasi', '2026-03-21 14:15:00', 'disimpan', 1),
('Jam Tangan Casio Biru', 'Perhiasan', 'Jam tangan digital Casio warna biru tua, tali resin', 'Gerbong 5 KRL tujuan Cikarang', '2026-03-22 09:00:00', 'disimpan', 1),
('Power Bank 10000mAh', 'Elektronik', 'Power bank hitam merek Anker, ada stiker nama "Raka"', 'Ruang tunggu Stasiun Sudirman', '2026-03-23 18:20:00', 'disimpan', 1),
('Map Dokumen Transparan', 'Dokumen', 'Map berisi fotokopi KTP, NPWP, dan berkas lamaran kerja', 'Loket tiket Stasiun Tanah Abang', '2026-03-24 08:10:00', 'disimpan', 1);

-- Seed contoh laporan kehilangan.
INSERT INTO laporan_kehilangan (nama_barang, kategori, deskripsi, tanggal_hilang, lokasi_hilang, status, created_by) VALUES
('Dompet Kulit Hitam', 'Tas & Dompet', 'Dompet hitam berisi KTP atas nama Andi dan 2 kartu ATM', '2026-03-20 09:55:00', 'Peron 7 Stasiun Manggarai', 'dilaporkan', 2),
('Kunci Motor Honda', 'Kendaraan & Aksesori', 'Kunci Honda Beat dengan gantungan karakter warna kuning', '2026-03-21 13:45:00', 'Area parkir Stasiun Bekasi', 'dilaporkan', 2),
('Jam Tangan Casio', 'Perhiasan', 'Jam tangan digital Casio warna biru, diperkirakan lepas di kereta', '2026-03-22 08:40:00', 'KRL Commuter Line jurusan Cikarang', 'dilaporkan', 2),
('Power Bank Hitam', 'Elektronik', 'Power bank 10000mAh merek Anker dengan stiker nama "Raka"', '2026-03-23 17:55:00', 'Ruang tunggu Stasiun Sudirman', 'dilaporkan', 2),
('Map Dokumen', 'Dokumen', 'Map transparan berisi berkas kerja dan fotokopi identitas', '2026-03-24 07:50:00', 'Sekitar loket tiket Stasiun Tanah Abang', 'dilaporkan', 2);
