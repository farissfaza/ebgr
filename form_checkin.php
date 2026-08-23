<?php
// form_checkin.php — Raspi Integration Version
// 4-Step Check-in Wizard: Input KTP → Data Tamu → Detail Menginap → Pembayaran
session_start();
include __DIR__ . '/config/koneksi.php';

// Fetch room options from data_asset
$kamar_options = [];
$sql_kamar = "SELECT id_name FROM data_asset WHERE category = 'GEDUNG MESS' AND aktif_tidak_aktif = 'AKTIF' ORDER BY id_name ASC";
$result_kamar = $conn->query($sql_kamar);
if ($result_kamar && $result_kamar->num_rows > 0) {
    while($row = $result_kamar->fetch_assoc()) {
        $kamar_options[] = $row['id_name'];
    }
}

// Fetch class options
$kelas_options = [];
$sql_kelas = "SELECT id_name FROM data_asset WHERE category = 'GEDUNG KELAS' AND aktif_tidak_aktif = 'AKTIF' ORDER BY id_name ASC";
$result_kelas = $conn->query($sql_kelas);
if ($result_kelas && $result_kelas->num_rows > 0) {
    while($row = $result_kelas->fetch_assoc()) {
        $kelas_options[] = $row['id_name'];
    }
}

// Restore old input values from failed submission
$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

// Generate a unique checkin session token for temperature tracking
$checkin_session = bin2hex(random_bytes(16));

include 'UI/header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Check-In Tamu — Raspi RFID & OCR</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        /* ========== GLASSMORPHISM BACKGROUND ========== */
        .glass-dashboard-bg {
            background: rgba(255, 255, 255, 0.9);
            min-height: calc(100vh - 60px);
            padding: 2rem 1.5rem;
            position: relative;
        }
        .glass-dashboard-bg::before, .glass-dashboard-bg::after {
            content: ''; position: fixed; border-radius: 50%; z-index: 0;
            filter: blur(60px); opacity: 0.6; pointer-events: none;
        }
        .glass-dashboard-bg::before { background: #ff9a9e; width: 300px; height: 300px; top: -50px; left: -50px; }
        .glass-dashboard-bg::after { background: #fecfef; width: 400px; height: 400px; bottom: -100px; right: -50px; }
        .content-container { position: relative; z-index: 1; }

        /* ========== GLASS CARD ========== */
        .glass-card {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .glass-card:hover { box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.15); }

        /* ========== PROGRESS STEPS ========== */
        .progress-steps { display: flex; justify-content: center; gap: 0; margin-bottom: 2rem; }
        .step-item { display: flex; align-items: center; }
        .step-circle {
            width: 40px; height: 40px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.9rem;
            background: rgba(200, 200, 200, 0.3); color: #999;
            border: 2px solid rgba(200, 200, 200, 0.5);
            transition: all 0.4s ease;
        }
        .step-circle.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border-color: transparent; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); }
        .step-circle.completed { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: #fff; border-color: transparent; }
        .step-line { width: 60px; height: 3px; background: rgba(200, 200, 200, 0.3); margin: 0 8px; transition: all 0.4s ease; }
        .step-line.active { background: linear-gradient(90deg, #11998e, #38ef7d); }
        .step-label { font-size: 0.7rem; text-align: center; margin-top: 6px; color: #999; font-weight: 600; }
        .step-label.active { color: #667eea; }

        /* ========== INPUT METHOD CARDS ========== */
        #input-method-choice { display: flex; gap: 1.5rem; justify-content: center; flex-wrap: wrap; }
        .method-card {
            width: 240px; padding: 2rem 1.25rem; border-radius: 20px; text-align: center;
            cursor: pointer; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            background: rgba(255, 255, 255, 0.6); border: 2px solid rgba(200, 200, 200, 0.3);
        }
        .method-card:hover { transform: translateY(-8px); box-shadow: 0 12px 30px rgba(31, 38, 135, 0.15); border-color: #667eea; }
        .method-card .method-icon { font-size: 3rem; margin-bottom: 1rem; display: block; }
        .method-card.rfid-card .method-icon { color: #11998e; }
        .method-card.ocr-card .method-icon { color: #667eea; }
        .method-card.upload-card .method-icon { color: #764ba2; }
        .method-card h5 { font-weight: 700; margin-bottom: 0.5rem; }
        .method-card p { color: #666; font-size: 0.85rem; margin: 0; }

        /* ========== TAB PILLS FOR SCAN/UPLOAD ========== */
        .mode-toggle-pills {
            display: inline-flex;
            background: rgba(200, 200, 200, 0.25);
            padding: 4px;
            border-radius: 30px;
            margin-bottom: 1.5rem;
        }
        .mode-pill-btn {
            border: none;
            background: transparent;
            padding: 8px 22px;
            border-radius: 25px;
            font-size: 0.88rem;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .mode-pill-btn.active {
            background: #fff;
            color: #667eea;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .mode-pill-btn.active-warning.active {
            background: #fff;
            color: #d97706;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        /* ========== UPLOAD DROPZONE ========== */
        .upload-dropzone {
            border: 2px dashed rgba(200, 200, 200, 0.5);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.6);
            padding: 2.5rem 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        .upload-dropzone:hover, .upload-dropzone.dragover {
            background: rgba(102, 126, 234, 0.1);
            border-color: #764ba2;
            transform: translateY(-2px);
        }
        .upload-dropzone.warning-border {
            border-color: #f59e0b;
            background: rgba(245, 158, 11, 0.04);
        }
        .upload-dropzone.warning-border:hover, .upload-dropzone.warning-border.dragover {
            background: rgba(245, 158, 11, 0.1);
            border-color: #d97706;
        }
        .upload-icon {
            font-size: 3.2rem;
            color: #667eea;
            margin-bottom: 0.8rem;
            display: inline-block;
            transition: transform 0.3s ease;
        }
        .upload-dropzone:hover .upload-icon {
            transform: scale(1.08);
        }
        .upload-icon.warning-icon {
            color: #f59e0b;
        }

        /* ========== SCAN SECTIONS ========== */
        .scan-section { display: none; animation: fadeIn 0.5s ease; }
        .scan-section.active { display: block; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
        @keyframes scanline {
            0% { top: 0; } 50% { top: 100%; } 100% { top: 0; }
        }

        .scan-waiting-box {
            text-align: center; padding: 3rem 2rem; border: 2px dashed #667eea;
            border-radius: 16px; background: rgba(102, 126, 234, 0.05);
            position: relative; overflow: hidden;
        }
        .scan-waiting-box.scanning::after {
            content: ''; position: absolute; left: 0; width: 100%; height: 3px;
            background: linear-gradient(90deg, transparent, #667eea, transparent);
            animation: scanline 2s infinite;
        }
        .scan-waiting-box .scan-icon { font-size: 4rem; color: #667eea; animation: pulse 2s infinite; }
        .scan-waiting-box h4 { margin-top: 1rem; font-weight: 700; }
        .scan-waiting-box p { color: #666; }

        /* ========== KTP PREVIEW ========== */
        .ktp-preview-box {
            text-align: center; margin-top: 1rem;
        }
        .ktp-preview-box img {
            max-width: 350px; max-height: 250px; border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 2px solid rgba(255,255,255,0.5);
        }

        /* ========== RASPI STATUS BAR ========== */
        .raspi-status-bar {
            display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.5rem;
        }
        .status-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 14px; border-radius: 20px; font-size: 0.78rem; font-weight: 600;
            background: rgba(200, 200, 200, 0.2); color: #999;
        }
        .status-chip .status-dot {
            width: 8px; height: 8px; border-radius: 50%; display: inline-block;
        }
        .status-chip.connected .status-dot { background: #38ef7d; box-shadow: 0 0 6px #38ef7d; }
        .status-chip.connected { background: rgba(56, 239, 125, 0.1); color: #11998e; }
        .status-chip.disconnected .status-dot { background: #ff6b6b; }
        .status-chip.disconnected { background: rgba(255, 107, 107, 0.1); color: #e74c3c; }
        .status-chip.unknown .status-dot { background: #999; }

        /* ========== FORM SECTIONS ========== */
        #main-form-content { display: none; animation: fadeIn 0.5s ease-in; }
        .form-section h5 { color: #667eea; }

        /* ========== RFID REGISTRATION BOX ========== */
        .rfid-registration-box {
            border-radius: 12px; padding: 1rem 1.25rem;
        }
    </style>
</head>
<body>

<div id="wrapper">
    <?php include 'UI/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'UI/navbar.php'; ?>
        
        <main class="content glass-dashboard-bg">
            <div class="container-fluid p-0 content-container">
                
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="h3"><strong>Check-In Tamu Baru</strong></h1>
                    <a href="index.php" class="btn btn-light rounded-pill px-3">
                        <i class="bi bi-arrow-left me-1"></i> Kembali
                    </a>
                </div>
                
                <!-- Raspi Hardware Status Bar -->
                <div class="raspi-status-bar" id="raspi-status-bar">
                    <div class="status-chip unknown" id="chip-pn532">
                        <span class="status-dot"></span>
                        <i class="bi bi-nfc"></i> PN532 RFID
                    </div>
                    <div class="status-chip unknown" id="chip-mlx90614">
                        <span class="status-dot"></span>
                        <i class="bi bi-thermometer-half"></i> MLX90614
                    </div>
                    <div class="status-chip unknown" id="chip-camera">
                        <span class="status-dot"></span>
                        <i class="bi bi-camera"></i> Camera
                    </div>
                    <div class="status-chip" id="chip-temperature" style="display:none;">
                        <i class="bi bi-thermometer-high text-warning"></i>
                        <span id="live-temp">--</span>°C
                    </div>
                </div>
                
                <!-- Progress Steps -->
                <div class="progress-steps" id="progress-steps">
                    <div class="step-item">
                        <div>
                            <div class="step-circle active" id="step-1">1</div>
                            <div class="step-label active">Input KTP</div>
                        </div>
                    </div>
                    <div class="step-line" id="line-1-2"></div>
                    <div class="step-item">
                        <div>
                            <div class="step-circle" id="step-2">2</div>
                            <div class="step-label">Data Tamu</div>
                        </div>
                    </div>
                    <div class="step-line" id="line-2-3"></div>
                    <div class="step-item">
                        <div>
                            <div class="step-circle" id="step-3">3</div>
                            <div class="step-label">Menginap</div>
                        </div>
                    </div>
                    <div class="step-line" id="line-3-4"></div>
                    <div class="step-item">
                        <div>
                            <div class="step-circle" id="step-4">4</div>
                            <div class="step-label">Bayar</div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Glass Card -->
                <div class="row justify-content-center">
                    <div class="col-lg-10 col-xl-9">
                        <div class="glass-card p-4 p-lg-5">
                            
                            <!-- ========== STEP 1: INPUT KTP ========== -->
                            <div class="form-section mb-4">
                                <h5 class="fw-bold pb-2 border-bottom mb-4">
                                    <i class="bi bi-credit-card-2-front me-2"></i>
                                    Langkah 1: Input KTP
                                </h5>
                                
                                <!-- Method Selection -->
                                <div id="input-method-choice">
                                    <div class="method-card rfid-card" onclick="selectInputMethod('rfid')">
                                        <i class="bi bi-broadcast method-icon"></i>
                                        <h5>Scan e-KTP (RFID)</h5>
                                        <p>Tempelkan e-KTP pada reader PN532 di Raspi</p>
                                    </div>
                                    <div class="method-card ocr-card" onclick="selectInputMethod('ocr', 'camera')">
                                        <i class="bi bi-camera method-icon"></i>
                                        <h5>Foto KTP (Kamera)</h5>
                                        <p>Arahkan KTP ke kamera Raspi untuk scan otomatis</p>
                                    </div>
                                    <div class="method-card upload-card" onclick="selectInputMethod('ocr', 'upload')">
                                        <i class="bi bi-cloud-arrow-up method-icon" style="color: #764ba2;"></i>
                                        <h5>Upload File KTP</h5>
                                        <p>Unggah file foto/scan KTP dari laptop/komputer</p>
                                    </div>
                                </div>
                                
                                <!-- RFID Scan Section -->
                                <div class="scan-section" id="rfid-scan-section">
                                    <div class="scan-waiting-box scanning" id="rfid-waiting-box">
                                        <i class="bi bi-nfc scan-icon"></i>
                                        <h4>Menunggu Scan e-KTP...</h4>
                                        <p>Tempelkan e-KTP pada reader RFID yang terhubung ke Raspberry Pi</p>
                                        <div class="spinner-border text-primary mt-3" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-3">
                                        <button class="btn btn-outline-secondary rounded-pill px-4" onclick="cancelScan()">
                                            <i class="bi bi-arrow-left me-1"></i> Kembali
                                        </button>
                                        <button class="btn btn-outline-primary rounded-pill px-4" onclick="skipToManual()">
                                            Lewati & Isi Manual <i class="bi bi-arrow-right ms-1"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Camera / Upload OCR Section -->
                                <div class="scan-section" id="ocr-scan-section">
                                    <!-- Mode Switcher Tabs -->
                                    <div class="text-center mb-3">
                                        <div class="mode-toggle-pills">
                                            <button type="button" class="mode-pill-btn active" id="ktp-tab-camera" onclick="switchKtpMode('camera')">
                                                <i class="bi bi-camera me-1"></i> Kamera Raspi
                                            </button>
                                            <button type="button" class="mode-pill-btn" id="ktp-tab-upload" onclick="switchKtpMode('upload')">
                                                <i class="bi bi-cloud-arrow-up me-1"></i> Upload File KTP
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Camera Mode -->
                                    <div id="ktp-camera-mode">
                                        <div class="scan-waiting-box scanning" id="ocr-waiting-box">
                                            <i class="bi bi-camera scan-icon"></i>
                                            <h4>Live Preview KTP</h4>
                                            <p>Arahkan KTP ke depan kamera Raspi lalu klik tombol Ambil Foto.</p>
                                            <div class="mt-3 mb-3 d-flex justify-content-center">
                                                <img src="http://10.59.201.134:5000/video_feed" class="img-fluid rounded border shadow-sm" style="max-height: 250px; width: auto;" alt="Live Camera Feed">
                                            </div>
                                            <button type="button" class="btn btn-primary rounded-pill px-4" onclick="startCameraKtp(); document.getElementById('ocr-waiting-box-overlay').classList.remove('d-none');">
                                                <i class="bi bi-camera me-1"></i> Ambil Foto
                                            </button>
                                            <div id="ocr-waiting-box-overlay" class="mt-3 d-none">
                                                <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                                                <span class="ms-2">Mengambil foto...</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Upload Mode -->
                                    <div id="ktp-upload-mode" style="display: none;">
                                        <div class="upload-dropzone" id="ktp-dropzone" onclick="document.getElementById('ktp-file-input').click()">
                                            <input type="file" id="ktp-file-input" accept="image/jpeg,image/png,image/jpg" style="display: none;" onchange="handleKtpFileSelect(this)">
                                            <i class="bi bi-cloud-arrow-up upload-icon"></i>
                                            <h4 class="fw-bold">Pilih atau Tarik File KTP ke Sini</h4>
                                            <p class="text-muted mb-3">Mendukung format JPG, PNG (Maks 10MB) — Data akan diekstrak otomatis melalui OCR</p>
                                            <button type="button" class="btn btn-primary rounded-pill px-4" onclick="event.stopPropagation(); document.getElementById('ktp-file-input').click();">
                                                <i class="bi bi-folder2-open me-1"></i> Telusuri File KTP
                                            </button>
                                            <div id="ktp-upload-spinner" class="mt-3 d-none">
                                                <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                                                <span class="ms-2 fw-semibold" id="ktp-upload-msg">Mengunggah & Mengekstrak Data KTP...</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="ktp-preview-box d-none" id="ktp-preview-container">
                                        <img id="ktp-preview-img" src="" alt="KTP Preview">
                                        <p class="text-success mt-2 fw-bold"><i class="bi bi-check-circle me-1"></i> Foto KTP berhasil dimuat!</p>
                                    </div>
                                    <div id="ocr-status" style="display:none;" class="mt-3 text-center">
                                        <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                                        <span id="ocr-message">Memproses OCR...</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-3">
                                        <button class="btn btn-outline-secondary rounded-pill px-4" onclick="cancelScan()">
                                            <i class="bi bi-arrow-left me-1"></i> Kembali
                                        </button>
                                        <button class="btn btn-outline-primary rounded-pill px-4" onclick="skipToManual()">
                                            Lewati & Isi Manual <i class="bi bi-arrow-right ms-1"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- ========== MAIN FORM (Steps 2-4) ========== -->
                            <form id="checkin-form" action="actions/proses_checkin.php" method="POST">
                                <input type="hidden" id="input_method" name="input_method" value="">
                                <input type="hidden" id="rfid_uid" name="rfid_uid" value="">
                                <input type="hidden" id="checkin_session" name="checkin_session" value="<?= $checkin_session ?>">
                                <input type="hidden" id="ocr_s3_path" name="ocr_s3_path" value="">
                                <input type="hidden" id="pembayaran_s3_path" name="pembayaran_s3_path" value="">
                                
                                <div id="main-form-content">
                                    
                                    <!-- RFID Registration Box (shows when RFID card can be linked) -->
                                    <div id="rfid-registration-box" class="alert alert-secondary d-flex align-items-center mb-4 rfid-registration-box" style="display:none !important;">
                                        <div>
                                            <h6 class="alert-heading mb-1">
                                                <i class="bi bi-nfc me-1"></i> e-KTP (Opsional)
                                            </h6>
                                            <span id="rfid-registration-text">Daftarkan e-KTP agar check-in berikutnya lebih cepat.</span>
                                        </div>
                                        <div id="rfid-registration-pulse" class="ms-auto d-none">
                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- KTP Preview (from OCR/Camera) -->
                                    <div id="form-ktp-preview-container" class="text-center mb-4 d-none">
                                        <img id="form-ktp-preview" src="" alt="Preview KTP" 
                                             class="img-thumbnail" style="max-width: 300px; border-radius: 12px;">
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <!-- ========== STEP 2: DATA TAMU ========== -->
                                    <div class="form-section mb-4">
                                        <h5 class="fw-bold pb-2 border-bottom mb-4">
                                            <i class="bi bi-person me-2"></i>
                                            Langkah 2: Data Tamu
                                        </h5>
                                        
                                        <div class="row">
                                            <!-- NIK -->
                                            <div class="col-md-6 mb-3">
                                                <label for="nik" class="form-label">NIK <small class="text-muted">(16 digit)</small></label>
                                                <input type="text" class="form-control" id="nik" name="nik" 
                                                       value="<?= htmlspecialchars($old['nik'] ?? '') ?>" 
                                                       maxlength="16" pattern="\d{16}" placeholder="3201234567890001">
                                            </div>
                                            
                                            <!-- Nama -->
                                            <div class="col-md-6 mb-3">
                                                <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="nama" name="nama" 
                                                       value="<?= htmlspecialchars($old['nama'] ?? '') ?>" required>
                                                <div class="invalid-feedback">Nama harus diisi</div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <!-- Tempat Lahir -->
                                            <div class="col-md-4 mb-3">
                                                <label for="tempat_lahir" class="form-label">Tempat Lahir</label>
                                                <input type="text" class="form-control" id="tempat_lahir" name="tempat_lahir" 
                                                       value="<?= htmlspecialchars($old['tempat_lahir'] ?? '') ?>">
                                            </div>
                                            
                                            <!-- Tanggal Lahir -->
                                            <div class="col-md-4 mb-3">
                                                <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                                                <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" 
                                                       value="<?= htmlspecialchars($old['tanggal_lahir'] ?? '') ?>">
                                            </div>
                                            
                                            <!-- Jenis Kelamin -->
                                            <div class="col-md-4 mb-3">
                                                <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                                                <select class="form-select" id="jenis_kelamin" name="jenis_kelamin">
                                                    <option value="">-- Pilih --</option>
                                                    <option value="LAKI-LAKI" <?= (isset($old['jenis_kelamin']) && $old['jenis_kelamin'] === 'LAKI-LAKI') ? 'selected' : '' ?>>Laki-Laki</option>
                                                    <option value="PEREMPUAN" <?= (isset($old['jenis_kelamin']) && $old['jenis_kelamin'] === 'PEREMPUAN') ? 'selected' : '' ?>>Perempuan</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <!-- No HP -->
                                            <div class="col-md-6 mb-3">
                                                <label for="no_handphone" class="form-label">No. Handphone <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="no_handphone" name="no_handphone" 
                                                       value="<?= htmlspecialchars($old['no_handphone'] ?? '') ?>" required placeholder="08xxxxxxxxxx">
                                                <div class="invalid-feedback">Nomor HP harus diisi</div>
                                            </div>
                                            
                                            <!-- Email -->
                                            <div class="col-md-6 mb-3">
                                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?= htmlspecialchars($old['email'] ?? '') ?>" required placeholder="nama@email.com">
                                                <div class="invalid-feedback">Email harus diisi</div>
                                            </div>
                                        </div>
                                        
                                        <!-- Alamat -->
                                        <div class="mb-3">
                                            <label for="alamat" class="form-label">Alamat</label>
                                            <textarea class="form-control" id="alamat" name="alamat" rows="2" 
                                                      placeholder="Alamat lengkap sesuai KTP"><?= htmlspecialchars($old['alamat'] ?? '') ?></textarea>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <label for="rt_rw" class="form-label">RT/RW</label>
                                                <input type="text" class="form-control" id="rt_rw" name="rt_rw" 
                                                       value="<?= htmlspecialchars($old['rt_rw'] ?? '') ?>" placeholder="000/000">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label for="kelurahan_desa" class="form-label">Kelurahan/Desa</label>
                                                <input type="text" class="form-control" id="kelurahan_desa" name="kelurahan_desa" 
                                                       value="<?= htmlspecialchars($old['kelurahan_desa'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label for="kecamatan" class="form-label">Kecamatan</label>
                                                <input type="text" class="form-control" id="kecamatan" name="kecamatan" 
                                                       value="<?= htmlspecialchars($old['kecamatan'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label for="kota_kabupaten" class="form-label">Kota/Kab.</label>
                                                <input type="text" class="form-control" id="kota_kabupaten" name="kota_kabupaten" 
                                                       value="<?= htmlspecialchars($old['kota_kabupaten'] ?? '') ?>">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="agama" class="form-label">Agama</label>
                                                <select class="form-select" id="agama" name="agama">
                                                    <option value="">-- Pilih --</option>
                                                    <?php foreach(['ISLAM','KRISTEN','KATOLIK','HINDU','BUDDHA','KONGHUCU'] as $ag): ?>
                                                    <option value="<?= $ag ?>" <?= (isset($old['agama']) && $old['agama'] === $ag) ? 'selected' : '' ?>><?= ucfirst(strtolower($ag)) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="pekerjaan" class="form-label">Pekerjaan</label>
                                                <input type="text" class="form-control" id="pekerjaan" name="pekerjaan" 
                                                       value="<?= htmlspecialchars($old['pekerjaan'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="akun_google" class="form-label">Akun Google <small class="text-muted">(Opsional)</small></label>
                                                <input type="text" class="form-control" id="akun_google" name="akun_google" 
                                                       value="<?= htmlspecialchars($old['akun_google'] ?? '') ?>" placeholder="username@gmail.com">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="tipe_tamu" class="form-label">Tipe Tamu <span class="text-danger">*</span></label>
                                                <select class="form-select" id="tipe_tamu" name="tipe_tamu" onchange="toggleInstansi(this.value)" required>
                                                    <option value="">-- Pilih Tipe --</option>
                                                    <option value="Pribadi" <?= (isset($old['tipe_tamu']) && $old['tipe_tamu'] === 'Pribadi') ? 'selected' : '' ?>>Pribadi</option>
                                                    <option value="Instansi" <?= (isset($old['tipe_tamu']) && $old['tipe_tamu'] === 'Instansi') ? 'selected' : '' ?>>Instansi</option>
                                                    <option value="Perusahaan" <?= (isset($old['tipe_tamu']) && $old['tipe_tamu'] === 'Perusahaan') ? 'selected' : '' ?>>Perusahaan</option>
                                                </select>
                                                <div class="invalid-feedback">Pilih tipe tamu</div>
                                            </div>
                                            <div class="col-md-6 mb-3" id="instansi-field" style="<?= (isset($old['tipe_tamu']) && in_array($old['tipe_tamu'], ['Instansi', 'Perusahaan'])) ? '' : 'display:none;' ?>">
                                                <label for="nama_instansi" class="form-label">Nama Instansi/Perusahaan <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="nama_instansi" name="nama_instansi" 
                                                       value="<?= htmlspecialchars($old['nama_instansi'] ?? '') ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <!-- ========== STEP 3: DETAIL MENGINAP ========== -->
                                    <div class="form-section mb-4 mt-5">
                                        <h5 class="fw-bold pb-2 border-bottom mb-4">
                                            <i class="bi bi-calendar me-2"></i>
                                            Langkah 3: Detail Menginap
                                        </h5>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="tanggal_check_in" class="form-label">Tanggal Check-In <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="tanggal_check_in" name="tanggal_check_in" 
                                                       value="<?= htmlspecialchars($old['tanggal_check_in'] ?? '') ?>" required>
                                                <div class="invalid-feedback">Tanggal check-in harus diisi</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="tanggal_check_out" class="form-label">Tanggal Check-Out <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="tanggal_check_out" name="tanggal_check_out" 
                                                       value="<?= htmlspecialchars($old['tanggal_check_out'] ?? '') ?>" required>
                                                <div class="invalid-feedback">Tanggal check-out harus diisi</div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="nama_kamar" class="form-label">Nama Kamar <span class="text-danger">*</span></label>
                                                <select class="form-select" id="nama_kamar" name="nama_kamar" required>
                                                    <option value="">-- Pilih Kamar --</option>
                                                    <?php foreach ($kamar_options as $kamar): ?>
                                                    <option value="<?= htmlspecialchars($kamar) ?>" <?= (isset($old['nama_kamar']) && $old['nama_kamar'] === $kamar) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($kamar) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="invalid-feedback">Pilih kamar</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="harga_kamar" class="form-label">Harga Kamar (per malam) <span class="text-danger">*</span></label>
                                                <input type="number" class="form-control" id="harga_kamar" name="harga_kamar" 
                                                       value="<?= htmlspecialchars($old['harga_kamar'] ?? '') ?>" min="0" placeholder="0" required>
                                                <div class="invalid-feedback">Harga kamar harus diisi</div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="detail_acara" class="form-label">Acara / Judul Diklat <small class="text-muted">(Jika ada)</small></label>
                                            <input type="text" class="form-control" id="detail_acara" name="detail_acara" 
                                                   value="<?= htmlspecialchars($old['detail_acara'] ?? '') ?>" placeholder="Nama acara atau pelatihan">
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="nama_kelas" class="form-label">Nama Kelas <small class="text-muted">(Jika ada)</small></label>
                                                <select class="form-select" id="nama_kelas" name="nama_kelas">
                                                    <option value="">-- Tidak Mengambil Kelas --</option>
                                                    <?php foreach ($kelas_options as $kelas): ?>
                                                    <option value="<?= htmlspecialchars($kelas) ?>" <?= (isset($old['nama_kelas']) && $old['nama_kelas'] === $kelas) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($kelas) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="harga_kelas" class="form-label">Harga Kelas</label>
                                                <input type="number" class="form-control" id="harga_kelas" name="harga_kelas" 
                                                       value="<?= htmlspecialchars($old['harga_kelas'] ?? '') ?>" min="0" placeholder="0">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4">
                                    
                                    <!-- ========== STEP 4: PEMBAYARAN ========== -->
                                    <div class="form-section mb-0 mt-5">
                                        <h5 class="fw-bold pb-2 border-bottom mb-4">
                                            <i class="bi bi-wallet2 me-2"></i>
                                            Langkah 4: Pembayaran
                                        </h5>
                                        
                                        <!-- Payment Mode Switcher Tabs -->
                                        <div class="text-center mb-3">
                                            <div class="mode-toggle-pills">
                                                <button type="button" class="mode-pill-btn active-warning active" id="pay-tab-camera" onclick="switchPaymentMode('camera')">
                                                    <i class="bi bi-camera me-1"></i> Kamera Raspi
                                                </button>
                                                <button type="button" class="mode-pill-btn" id="pay-tab-upload" onclick="switchPaymentMode('upload')">
                                                    <i class="bi bi-cloud-arrow-up me-1"></i> Upload File Bukti
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Camera / Upload Section for Payment -->
                                        <div id="payment-capture-section">
                                            <!-- Camera Mode -->
                                            <div id="payment-camera-mode">
                                                <div class="scan-waiting-box" id="payment-waiting-box" style="border-color: #f59e0b;">
                                                    <i class="bi bi-camera" style="font-size: 3rem; color: #f59e0b;"></i>
                                                    <h5 class="mt-2">Live Preview Pembayaran</h5>
                                                    <p class="text-muted">Arahkan bukti pembayaran ke kamera Raspi</p>
                                                    <div class="mt-3 mb-3 d-flex justify-content-center">
                                                        <img src="http://10.59.201.134:5000/video_feed" class="img-fluid rounded border shadow-sm" style="max-height: 250px; width: auto;" alt="Live Camera Feed">
                                                    </div>
                                                    <button type="button" class="btn btn-warning rounded-pill px-4 mt-2" onclick="capturePayment()">
                                                        <i class="bi bi-camera me-1"></i> Ambil Foto
                                                    </button>
                                                </div>
                                                <div id="payment-scanning" style="display:none;" class="text-center py-4">
                                                    <div class="spinner-border text-warning" role="status"></div>
                                                    <p class="mt-2 text-muted">Menunggu foto dari kamera Raspi...</p>
                                                </div>
                                            </div>

                                            <!-- Upload Mode -->
                                            <div id="payment-upload-mode" style="display: none;">
                                                <div class="upload-dropzone warning-border" id="payment-dropzone" onclick="document.getElementById('payment-file-input').click()">
                                                    <input type="file" id="payment-file-input" accept="image/jpeg,image/png,image/jpg,application/pdf" style="display: none;" onchange="handlePaymentFileSelect(this)">
                                                    <i class="bi bi-cloud-arrow-up upload-icon warning-icon"></i>
                                                    <h5 class="fw-bold mt-2">Pilih atau Tarik File Bukti Pembayaran ke Sini</h5>
                                                    <p class="text-muted mb-3">Mendukung format JPG, PNG, PDF (Maks 10MB)</p>
                                                    <button type="button" class="btn btn-warning rounded-pill px-4" onclick="event.stopPropagation(); document.getElementById('payment-file-input').click();">
                                                        <i class="bi bi-folder2-open me-1"></i> Telusuri Bukti Pembayaran
                                                    </button>
                                                    <div id="payment-upload-spinner" class="mt-3 d-none">
                                                        <div class="spinner-border text-warning spinner-border-sm" role="status"></div>
                                                        <span class="ms-2 fw-semibold">Mengunggah ke S3...</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Payment Preview -->
                                        <div id="payment-preview-container" class="text-center mt-3 d-none">
                                            <img id="payment-preview-img" src="" alt="Bukti Pembayaran" 
                                                 class="img-thumbnail" style="max-width: 350px; border-radius: 12px;">
                                            <p class="text-success mt-2 fw-bold">
                                                <i class="bi bi-check-circle me-1"></i> Bukti pembayaran berhasil disimpan!
                                            </p>
                                            <button type="button" class="btn btn-outline-warning btn-sm rounded-pill" onclick="retakePayment()">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i> Ganti File / Foto Ulang
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Total Summary -->
                                    <div class="card bg-light mb-4 mt-4" style="border-radius: 16px;">
                                        <div class="card-body">
                                            <h6 class="card-title">Ringkasan</h6>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Lama Menginap:</span>
                                                <strong id="summary-nights">0 malam</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Harga Kamar:</span>
                                                <strong id="summary-room-price">Rp 0</strong>
                                            </div>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Harga Kelas:</span>
                                                <strong id="summary-class-price">Rp 0</strong>
                                            </div>
                                            <hr>
                                            <div class="d-flex justify-content-between">
                                                <span class="h6">Total:</span>
                                                <strong class="h5 text-primary" id="summary-total">Rp 0</strong>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Submit -->
                                    <div class="d-flex justify-content-between mt-5 pt-4 border-top">
                                        <a href="index.php" class="btn btn-light px-4 py-2 rounded-pill fw-bold text-secondary">Batal</a>
                                        <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill fw-bold" id="submit-button" 
                                                style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none; box-shadow: 0 4px 15px rgba(56, 239, 125, 0.4);">
                                            <i class="bi bi-check-circle me-1"></i> Simpan Data Tamu
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include 'UI/settings.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if (isset($_SESSION['error'])): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Swal.fire({ icon: 'error', title: 'Oops...', text: '<?= htmlspecialchars($_SESSION['error']) ?>' });
});
</script>
<?php unset($_SESSION['error']); endif; ?>

<script>
// ========== GLOBAL STATE ==========
const CHECKIN_SESSION = '<?= $checkin_session ?>';
let selectedInputMethod = null;
let activeTaskToken = null;
let taskPollInterval = null;
let raspiStatusInterval = null;

// ========== RASPI STATUS POLLING ==========
function fetchRaspiStatus() {
    fetch('api/raspi_status.php')
    .then(r => r.json())
    .then(data => {
        ['pn532', 'mlx90614', 'camera'].forEach(comp => {
            const chip = document.getElementById('chip-' + comp);
            if (!chip) return;
            const info = data[comp];
            chip.className = 'status-chip ' + (info?.status || 'unknown');
        });
        // Show live temperature
        if (data.latest_temperature) {
            const tempChip = document.getElementById('chip-temperature');
            const tempVal = document.getElementById('live-temp');
            if (tempChip && tempVal) {
                tempChip.style.display = 'inline-flex';
                tempVal.textContent = data.latest_temperature.value.toFixed(1);
            }
        }
    })
    .catch(() => {});
}

// Poll raspi status every 30 seconds
raspiStatusInterval = setInterval(fetchRaspiStatus, 30000);
fetchRaspiStatus(); // initial

// ========== INPUT METHOD SELECTION & MODE SWITCHING ==========
function selectInputMethod(method, submode = 'camera') {
    selectedInputMethod = method;
    document.getElementById('input_method').value = method;
    document.getElementById('input-method-choice').style.display = 'none';
    
    if (method === 'rfid') {
        document.getElementById('rfid-scan-section').classList.add('active');
        startRfidScan();
    } else if (method === 'ocr') {
        document.getElementById('ocr-scan-section').classList.add('active');
        switchKtpMode(submode);
    }
    
    updateProgress(1);
}

function switchKtpMode(mode) {
    const camBtn = document.getElementById('ktp-tab-camera');
    const upBtn = document.getElementById('ktp-tab-upload');
    const camDiv = document.getElementById('ktp-camera-mode');
    const upDiv = document.getElementById('ktp-upload-mode');
    
    if (mode === 'camera') {
        camBtn.classList.add('active');
        upBtn.classList.remove('active');
        camDiv.style.display = 'block';
        upDiv.style.display = 'none';
    } else {
        upBtn.classList.add('active');
        camBtn.classList.remove('active');
        upDiv.style.display = 'block';
        camDiv.style.display = 'none';
    }
}

function switchPaymentMode(mode) {
    const camBtn = document.getElementById('pay-tab-camera');
    const upBtn = document.getElementById('pay-tab-upload');
    const camDiv = document.getElementById('payment-camera-mode');
    const upDiv = document.getElementById('payment-upload-mode');
    
    if (mode === 'camera') {
        camBtn.classList.add('active', 'active-warning');
        upBtn.classList.remove('active', 'active-warning');
        camDiv.style.display = 'block';
        upDiv.style.display = 'none';
    } else {
        upBtn.classList.add('active', 'active-warning');
        camBtn.classList.remove('active', 'active-warning');
        upDiv.style.display = 'block';
        camDiv.style.display = 'none';
    }
}

function cancelScan() {
    if (taskPollInterval) clearInterval(taskPollInterval);
    activeTaskToken = null;
    selectedInputMethod = null;
    
    document.querySelectorAll('.scan-section').forEach(s => s.classList.remove('active'));
    document.getElementById('input-method-choice').style.display = 'flex';
    
    // Reset waiting boxes
    document.getElementById('rfid-waiting-box').style.display = 'block';
    document.getElementById('ocr-waiting-box').style.display = 'block';
    document.getElementById('ocr-waiting-box-overlay')?.classList.add('d-none');
    document.getElementById('ktp-preview-container').classList.add('d-none');
    document.getElementById('ktp-upload-spinner')?.classList.add('d-none');
    const ktpFileInput = document.getElementById('ktp-file-input');
    if (ktpFileInput) ktpFileInput.value = '';
}

function skipToManual() {
    if (taskPollInterval) clearInterval(taskPollInterval);
    activeTaskToken = null;
    document.getElementById('input_method').value = 'ocr';
    showMainForm();
}

// ========== KTP FILE UPLOAD & OCR FLOW ==========
function handleKtpFileSelect(input) {
    if (input.files && input.files[0]) {
        uploadKtpFile(input.files[0]);
    }
}

function uploadKtpFile(file) {
    if (!file.type.match('image.*')) {
        showAlert('Format file tidak didukung. Harap unggah file gambar (JPG/PNG).', 'warning');
        return;
    }
    
    const spinner = document.getElementById('ktp-upload-spinner');
    const msg = document.getElementById('ktp-upload-msg');
    if (spinner) spinner.classList.remove('d-none');
    if (msg) msg.textContent = 'Mengunggah file KTP ke S3...';
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('folder', 'ocr-ktp');
    
    fetch('api/raspi/upload_to_s3.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(uploadRes => {
        if (!uploadRes.success) {
            if (spinner) spinner.classList.add('d-none');
            showAlert('Gagal mengunggah file KTP: ' + (uploadRes.message || 'Error server'), 'danger');
            return;
        }
        
        const s3Path = uploadRes.s3_key;
        const s3Url = uploadRes.url;
        document.getElementById('ocr_s3_path').value = s3Path;
        
        // Show preview in form area
        const formPreview = document.getElementById('form-ktp-preview');
        const formPreviewContainer = document.getElementById('form-ktp-preview-container');
        if (formPreview && formPreviewContainer) {
            formPreview.src = s3Url;
            formPreviewContainer.classList.remove('d-none');
        }
        
        // Run OCR
        if (msg) msg.textContent = 'Memproses OCR dengan Google Cloud Vision...';
        
        fetch('services/ocr_vision.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 's3_path=' + encodeURIComponent(s3Path)
        })
        .then(r => r.json())
        .then(ocrData => {
            if (spinner) spinner.classList.add('d-none');
            if (ocrData.success && ocrData.data) {
                fillFormFromOCR(ocrData.data);
                showAlert('Data KTP berhasil diekstrak dari file!', 'success');
            } else {
                showAlert(ocrData.message || 'OCR selesai. Silakan periksa dan lengkapi data jika diperlukan.', 'info');
            }
            showMainForm();
        })
        .catch(err => {
            if (spinner) spinner.classList.add('d-none');
            console.error('OCR Error:', err);
            showAlert('Terjadi kendala ekstraksi OCR. Silakan lengkapi form manual.', 'warning');
            showMainForm();
        });
    })
    .catch(err => {
        if (spinner) spinner.classList.add('d-none');
        console.error('Upload Error:', err);
        showAlert('Gagal mengunggah file KTP ke server.', 'danger');
    });
}

// ========== RASPI TASK CREATION + POLLING ==========
function createRaspiTask(taskType, onComplete) {
    fetch('api/create_task.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `task_type=${taskType}&checkin_session=${CHECKIN_SESSION}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            activeTaskToken = data.task_token;
            taskPollInterval = setInterval(() => pollTaskResult(onComplete), 2000);
        } else {
            showAlert('Gagal membuat task: ' + data.message, 'danger');
        }
    })
    .catch(err => {
        console.error('Task creation error:', err);
        showAlert('Gagal terhubung ke server.', 'danger');
    });
}

function pollTaskResult(onComplete) {
    if (!activeTaskToken) return;
    
    fetch('api/poll_result.php?task_token=' + activeTaskToken)
    .then(r => r.json())
    .then(data => {
        if (data.status === 'completed') {
            clearInterval(taskPollInterval);
            onComplete(data);
        } else if (data.status === 'failed' || data.status === 'expired') {
            clearInterval(taskPollInterval);
            showAlert('Task gagal atau expired. Silakan coba lagi.', 'danger');
        }
        // 'pending' or 'processing' = keep polling
    })
    .catch(err => console.error('Poll error:', err));
}

// ========== RFID SCAN FLOW ==========
function startRfidScan() {
    createRaspiTask('rfid_scan', handleRfidResult);
}

function handleRfidResult(data) {
    const resultData = data.result_data || {};
    const uid = resultData.uid;
    
    if (!uid) {
        showAlert('Tidak ada UID yang terbaca. Coba lagi.', 'warning');
        return;
    }
    
    // Store UID
    document.getElementById('rfid_uid').value = uid;
    
    // Lookup in rfid_mapping
    showAlert('e-KTP terdeteksi! UID: ' + uid, 'info');
    
    fetch('api/rfid_process.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'uid=' + uid + '&action=lookup'
    })
    .then(r => r.json())
    .then(lookupData => {
        if (lookupData.success && lookupData.found) {
            fillFormFromData(lookupData.data);
            showAlert('Data berhasil dimuat dari e-KTP!', 'success');
        } else {
            showAlert('e-KTP belum terdaftar. Silakan isi data manual.', 'warning');
        }
        showMainForm();
    })
    .catch(() => {
        showAlert('Gagal mencari data e-KTP. Isi manual.', 'warning');
        showMainForm();
    });
}

// ========== CAMERA KTP + OCR FLOW ==========
function startCameraKtp() {
    createRaspiTask('camera_ktp', handleCameraKtpResult);
}

function handleCameraKtpResult(data) {
    const s3Path = data.s3_path;
    const s3Url = data.s3_url;
    
    if (!s3Path) {
        showAlert('Gagal mengambil foto KTP.', 'danger');
        return;
    }
    
    // Store S3 path
    document.getElementById('ocr_s3_path').value = s3Path;
    
    // Show KTP preview
    document.getElementById('ocr-waiting-box').style.display = 'none';
    const previewContainer = document.getElementById('ktp-preview-container');
    const previewImg = document.getElementById('ktp-preview-img');
    previewImg.src = s3Url;
    previewContainer.classList.remove('d-none');
    
    // Also show in form area
    const formPreview = document.getElementById('form-ktp-preview');
    const formPreviewContainer = document.getElementById('form-ktp-preview-container');
    if (formPreview && formPreviewContainer) {
        formPreview.src = s3Url;
        formPreviewContainer.classList.remove('d-none');
    }
    
    // Run OCR on the S3 image
    const ocrStatus = document.getElementById('ocr-status');
    const ocrMessage = document.getElementById('ocr-message');
    if (ocrStatus) { ocrStatus.style.display = 'block'; ocrMessage.textContent = 'Memproses OCR dari foto KTP...'; }
    
    fetch('services/ocr_vision.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 's3_path=' + encodeURIComponent(s3Path)
    })
    .then(r => r.json())
    .then(ocrData => {
        if (ocrStatus) ocrStatus.style.display = 'none';
        
        if (ocrData.success && ocrData.data) {
            fillFormFromOCR(ocrData.data);
            showAlert('Data KTP berhasil diekstrak!', 'success');
        } else {
            showAlert(ocrData.message || 'OCR gagal membaca KTP. Isi manual.', 'warning');
        }
        showMainForm();
    })
    .catch(err => {
        if (ocrStatus) ocrStatus.style.display = 'none';
        console.error('OCR error:', err);
        showAlert('Terjadi kesalahan saat memproses OCR.', 'danger');
        showMainForm();
    });
}

// ========== PAYMENT CAPTURE & UPLOAD FLOW ==========
function capturePayment() {
    document.getElementById('payment-waiting-box').style.display = 'none';
    document.getElementById('payment-scanning').style.display = 'block';
    
    createRaspiTask('camera_payment', handlePaymentResult);
    updateProgress(4);
}

function handlePaymentFileSelect(input) {
    if (input.files && input.files[0]) {
        uploadPaymentFile(input.files[0]);
    }
}

function uploadPaymentFile(file) {
    const spinner = document.getElementById('payment-upload-spinner');
    if (spinner) spinner.classList.remove('d-none');
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('folder', 'bukti-pembayaran');
    
    fetch('api/raspi/upload_to_s3.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (spinner) spinner.classList.add('d-none');
        if (res.success) {
            document.getElementById('pembayaran_s3_path').value = res.s3_key;
            
            // Hide upload box & show preview
            document.getElementById('payment-capture-section').style.display = 'none';
            const previewContainer = document.getElementById('payment-preview-container');
            const previewImg = document.getElementById('payment-preview-img');
            previewImg.src = res.url;
            previewContainer.classList.remove('d-none');
            
            showAlert('Bukti pembayaran berhasil diunggah!', 'success');
        } else {
            showAlert('Gagal mengunggah bukti pembayaran: ' + (res.message || 'Error server'), 'danger');
        }
    })
    .catch(err => {
        if (spinner) spinner.classList.add('d-none');
        console.error('Payment upload error:', err);
        showAlert('Gagal mengunggah bukti pembayaran.', 'danger');
    });
}

function handlePaymentResult(data) {
    document.getElementById('payment-scanning').style.display = 'none';
    
    const s3Path = data.s3_path;
    const s3Url = data.s3_url;
    
    if (!s3Path) {
        showAlert('Gagal mengambil foto bukti pembayaran.', 'danger');
        document.getElementById('payment-waiting-box').style.display = 'block';
        return;
    }
    
    // Store S3 path
    document.getElementById('pembayaran_s3_path').value = s3Path;
    
    // Hide capture section & show preview
    document.getElementById('payment-capture-section').style.display = 'none';
    const previewContainer = document.getElementById('payment-preview-container');
    const previewImg = document.getElementById('payment-preview-img');
    previewImg.src = s3Url;
    previewContainer.classList.remove('d-none');
    
    showAlert('Bukti pembayaran berhasil disimpan!', 'success');
}

function retakePayment() {
    document.getElementById('payment-preview-container').classList.add('d-none');
    document.getElementById('pembayaran_s3_path').value = '';
    document.getElementById('payment-capture-section').style.display = 'block';
    document.getElementById('payment-waiting-box').style.display = 'block';
    document.getElementById('payment-scanning').style.display = 'none';
    const payFileInput = document.getElementById('payment-file-input');
    if (payFileInput) payFileInput.value = '';
}

// ========== FORM FILLING ==========
function fillFormFromData(data) {
    const mapping = {
        'nik': data.nik, 'nama': data.nama, 'tempat_lahir': data.tempat_lahir,
        'tanggal_lahir': data.tanggal_lahir, 'jenis_kelamin': data.jenis_kelamin,
        'no_handphone': data.no_hp || data.no_handphone, 'email': data.email,
        'alamat': data.alamat, 'rt_rw': data.rt_rw,
        'kelurahan_desa': data.kel_desa || data.kelurahan_desa,
        'kecamatan': data.kecamatan, 'kota_kabupaten': data.kota_kabupaten,
        'agama': data.agama, 'pekerjaan': data.pekerjaan
    };
    
    for (const [id, value] of Object.entries(mapping)) {
        const el = document.getElementById(id);
        if (el && value) {
            el.value = value;
            el.classList.add('is-valid');
        }
    }
}

function fillFormFromOCR(data) {
    const setVal = (id, val) => {
        const el = document.getElementById(id);
        if (el && val) { el.value = val; el.classList.add('is-valid'); }
    };
    
    setVal('nik', data.nik);
    setVal('nama', data.nama);
    setVal('tempat_lahir', data.tempat_lahir);
    setVal('tanggal_lahir', data.tanggal_lahir);
    setVal('jenis_kelamin', data.jenis_kelamin);
    setVal('agama', data.agama);
    setVal('pekerjaan', data.pekerjaan);
    setVal('kota_kabupaten', data.kota_kabupaten);
    
    // Address details
    if (data.alamat_details) {
        setVal('alamat', data.alamat_details.alamat);
        setVal('rt_rw', data.alamat_details.rt_rw);
        setVal('kelurahan_desa', data.alamat_details.kel_desa);
        setVal('kecamatan', data.alamat_details.kecamatan);
    }
}

// ========== SHOW MAIN FORM ==========
function showMainForm() {
    document.querySelectorAll('.scan-section').forEach(s => s.classList.remove('active'));
    document.getElementById('main-form-content').style.display = 'block';
    setDefaultDates();
    updateProgress(2);
    
    setTimeout(() => {
        const firstField = document.querySelector('#main-form-content input:not([type="hidden"])');
        if (firstField) firstField.focus();
    }, 300);
}

// ========== PROGRESS BAR ==========
function updateProgress(step) {
    for (let i = 1; i <= 4; i++) {
        const circle = document.getElementById('step-' + i);
        const label = circle?.parentElement?.querySelector('.step-label');
        
        if (i < step) {
            circle.className = 'step-circle completed';
            circle.innerHTML = '<i class="bi bi-check"></i>';
            if (label) label.className = 'step-label';
        } else if (i === step) {
            circle.className = 'step-circle active';
            circle.textContent = i;
            if (label) label.className = 'step-label active';
        } else {
            circle.className = 'step-circle';
            circle.textContent = i;
            if (label) label.className = 'step-label';
        }
    }
    // Lines
    for (let i = 1; i <= 3; i++) {
        const line = document.getElementById('line-' + i + '-' + (i + 1));
        if (line) line.className = 'step-line' + (i < step ? ' active' : '');
    }
}

// ========== UTILITIES ==========
function toggleInstansi(val) {
    document.getElementById('instansi-field').style.display = 
        (val === 'Instansi' || val === 'Perusahaan') ? '' : 'none';
}

function setDefaultDates() {
    const today = new Date().toISOString().split('T')[0];
    const cin = document.getElementById('tanggal_check_in');
    const cout = document.getElementById('tanggal_check_out');
    if (cin && !cin.value) cin.value = today;
    if (cout && !cout.value) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        cout.value = tomorrow.toISOString().split('T')[0];
    }
    updateSummary();
}

function showAlert(message, type) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: type === 'danger' ? 'error' : type,
            text: message,
            toast: true, position: 'top-end',
            showConfirmButton: false, timer: 4000, timerProgressBar: true
        });
    }
}

function formatRupiah(num) {
    return 'Rp ' + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function updateSummary() {
    const cin = document.getElementById('tanggal_check_in')?.value;
    const cout = document.getElementById('tanggal_check_out')?.value;
    const hargaKamar = parseInt(document.getElementById('harga_kamar')?.value) || 0;
    const hargaKelas = parseInt(document.getElementById('harga_kelas')?.value) || 0;
    
    let nights = 0;
    if (cin && cout) {
        const diff = new Date(cout) - new Date(cin);
        nights = Math.max(0, Math.ceil(diff / (1000 * 60 * 60 * 24)));
    }
    
    const totalKamar = hargaKamar * nights;
    const total = totalKamar + hargaKelas;
    
    document.getElementById('summary-nights').textContent = nights + ' malam';
    document.getElementById('summary-room-price').textContent = formatRupiah(totalKamar);
    document.getElementById('summary-class-price').textContent = formatRupiah(hargaKelas);
    document.getElementById('summary-total').textContent = formatRupiah(total);
}

// ========== OVERLAP CHECKING ==========
function checkBookingOverlap() {
    const nik = document.getElementById('nik')?.value.replace(/\D/g, '') || '';
    const nama_tamu = document.getElementById('nama')?.value || '';
    const tanggal_check_in = document.getElementById('tanggal_check_in')?.value || '';
    const tanggal_check_out = document.getElementById('tanggal_check_out')?.value || '';
    const nama_kamar = document.getElementById('nama_kamar')?.value || '';
    const nama_kelas = document.getElementById('nama_kelas')?.value || '';
    
    if (!tanggal_check_in || !tanggal_check_out) return;
    if (new Date(tanggal_check_out) <= new Date(tanggal_check_in)) return;

    fetch('api/api_check_overlap.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ nik, nama_tamu, tanggal_check_in, tanggal_check_out, nama_kamar, nama_kelas })
    })
    .then(r => r.json())
    .then(data => {
        const submitBtn = document.getElementById('submit-button');
        if (data.overlap) {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-x-circle me-2"></i> Jadwal Bertabrakan';
                submitBtn.style.background = 'linear-gradient(135deg, #e52d27 0%, #b31217 100%)';
            }
            showAlert(data.message, 'danger');
        } else {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Simpan Data Tamu';
                submitBtn.style.background = 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)';
            }
        }
    })
    .catch(err => console.error('Overlap check failed:', err));
}

// ========== EVENT LISTENERS ==========
document.addEventListener('DOMContentLoaded', function() {
    // Summary updates
    ['tanggal_check_in', 'tanggal_check_out', 'harga_kamar', 'harga_kelas'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', updateSummary);
    });
    
    // Overlap checking
    ['nik', 'nama', 'tanggal_check_in', 'tanggal_check_out', 'nama_kamar', 'nama_kelas'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', checkBookingOverlap);
            el.addEventListener('blur', checkBookingOverlap);
        }
    });
    
    // Setup Drag and Drop for Dropzones
    const setupZone = (zoneId, inputId, handleFn) => {
        const zone = document.getElementById(zoneId);
        const input = document.getElementById(inputId);
        if (!zone || !input) return;
        
        ['dragenter', 'dragover'].forEach(name => {
            zone.addEventListener(name, (e) => {
                e.preventDefault(); e.stopPropagation();
                zone.classList.add('dragover');
            });
        });
        
        ['dragleave', 'drop'].forEach(name => {
            zone.addEventListener(name, (e) => {
                e.preventDefault(); e.stopPropagation();
                zone.classList.remove('dragover');
            });
        });
        
        zone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                handleFn(e.dataTransfer.files[0]);
            }
        });
    };
    
    setupZone('ktp-dropzone', 'ktp-file-input', uploadKtpFile);
    setupZone('payment-dropzone', 'payment-file-input', uploadPaymentFile);

    // Form validation
    document.getElementById('checkin-form')?.addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            showAlert('Mohon lengkapi semua field yang wajib diisi.', 'warning');
        }
        this.classList.add('was-validated');
    });
});
</script>

<?php include 'UI/footer.php'; ?>

</body>
</html>