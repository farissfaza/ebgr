-- ============================================================
-- EBGR Database Schema — Raspi Integration
-- Run this file to create all required tables
-- ============================================================

-- 1. Guest Master Table
CREATE TABLE IF NOT EXISTS tamu_ms (
    id_tamu INT AUTO_INCREMENT PRIMARY KEY,
    nik VARCHAR(16) UNIQUE,
    nama VARCHAR(255) NOT NULL,
    tempat_lahir VARCHAR(100),
    tanggal_lahir DATE,
    jenis_kelamin ENUM('LAKI-LAKI', 'PEREMPUAN'),
    no_handphone VARCHAR(25),
    email VARCHAR(255),
    alamat TEXT,
    rt_rw VARCHAR(10),
    kelurahan_desa VARCHAR(255),
    kecamatan VARCHAR(255),
    kota_kabupaten VARCHAR(255),
    agama VARCHAR(50),
    pekerjaan VARCHAR(255),
    akun_google VARCHAR(255),
    tipe_tamu VARCHAR(100),
    nama_instansi VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_nik (nik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Stay Details Master Table
CREATE TABLE IF NOT EXISTS inapan_ms (
    id_inapan INT AUTO_INCREMENT PRIMARY KEY,
    tanggal_check_in DATE NOT NULL,
    tanggal_check_out DATE NOT NULL,
    nama_kamar VARCHAR(255) NOT NULL,
    harga_kamar INT DEFAULT 0,
    detail_acara VARCHAR(255),
    nama_kelas VARCHAR(255),
    harga_kelas INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Master Check-in Log Table
CREATE TABLE IF NOT EXISTS checkin_tamu_log (
    checkin_tamu_id INT AUTO_INCREMENT PRIMARY KEY,
    id_tamu INT NOT NULL,
    nik VARCHAR(16),
    rfid_uid VARCHAR(20),
    id_inapan INT NOT NULL,
    ocr_path TEXT COMMENT 'S3 path: ebgr/ocr-ktp/...',
    pembayaran_path TEXT COMMENT 'S3 path: ebgr/bukti-pembayaran/...',
    suhu_rata_rata DECIMAL(5,2) COMMENT 'Average MLX90614 temperature during session',
    input_method ENUM('rfid', 'ocr') DEFAULT 'ocr',
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    latency_ms INT NULL COMMENT 'Average round-trip latency (ms) across all Raspi tasks in this session',
    total_duration_sec INT NULL COMMENT 'Total time (seconds) from first hardware scan until form submission',
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_tamu) REFERENCES tamu_ms(id_tamu) ON DELETE RESTRICT,
    FOREIGN KEY (id_inapan) REFERENCES inapan_ms(id_inapan) ON DELETE RESTRICT,
    INDEX idx_status (status),
    INDEX idx_nik (nik),
    INDEX idx_rfid (rfid_uid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Raspi Task Queue
CREATE TABLE IF NOT EXISTS raspi_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_token VARCHAR(64) UNIQUE NOT NULL,
    task_type ENUM('rfid_scan', 'camera_ktp', 'camera_payment') NOT NULL,
    checkin_session VARCHAR(64) COMMENT 'Links tasks to a browser check-in session for temperature grouping',
    status ENUM('pending', 'processing', 'completed', 'failed', 'expired') DEFAULT 'pending',
    result_data JSON COMMENT 'RFID UID or S3 path result',
    s3_path TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    latency_ms INT COMMENT 'Round-trip latency in milliseconds (updated_at - created_at)',
    INDEX idx_status (status),
    INDEX idx_token (task_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Raspi Hardware Health Status
CREATE TABLE IF NOT EXISTS raspi_health (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component ENUM('pn532', 'mlx90614', 'camera') NOT NULL,
    status ENUM('connected', 'disconnected', 'error') NOT NULL,
    details TEXT,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_component (component)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Temperature Readings During Check-in Session
CREATE TABLE IF NOT EXISTS temperature_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checkin_session VARCHAR(64) NOT NULL COMMENT 'Browser session token linking to the active check-in process',
    temperature DECIMAL(5,2) NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session (checkin_session)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. RFID Mapping (keep existing, create if not exists)
CREATE TABLE IF NOT EXISTS rfid_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uid VARCHAR(20) UNIQUE NOT NULL,
    nik VARCHAR(16),
    nama VARCHAR(100),
    tempat_lahir VARCHAR(100),
    tanggal_lahir DATE,
    jenis_kelamin VARCHAR(20),
    alamat TEXT,
    rt_rw VARCHAR(10),
    kel_desa VARCHAR(100),
    kecamatan VARCHAR(100),
    kota_kabupaten VARCHAR(100),
    agama VARCHAR(20),
    pekerjaan VARCHAR(100),
    no_hp VARCHAR(15),
    email VARCHAR(100),
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used TIMESTAMP NULL,
    usage_count INT DEFAULT 1,
    INDEX idx_uid (uid),
    INDEX idx_nik (nik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Expire stale tasks (older than 10 minutes)
-- Run this periodically or via a cron job
-- UPDATE raspi_tasks SET status = 'expired' WHERE status IN ('pending', 'processing') AND created_at < NOW() - INTERVAL 10 MINUTE;
