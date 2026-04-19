<?php
// form_checkin.php - Enhanced Version with RFID Integration
include __DIR__ . '/config/koneksi.php';

// Fetch room options
$kamar_options = [];
$sql_kamar = "SELECT id_name FROM data_asset WHERE category = 'GEDUNG MESS' AND aktif_tidak_aktif = 'AKTIF' ORDER BY id_name ASC";
$result_kamar = $conn->query($sql_kamar);
if ($result_kamar->num_rows > 0) {
    while($row = $result_kamar->fetch_assoc()) {
        $kamar_options[] = $row['id_name'];
    }
}

// Fetch class options  
$kelas_options = [];
$sql_kelas = "SELECT id_name FROM data_asset WHERE category = 'GEDUNG KELAS' AND aktif_tidak_aktif = 'AKTIF' ORDER BY id_name ASC";
$result_kelas = $conn->query($sql_kelas);
if ($result_kelas->num_rows > 0) {
    while($row = $result_kelas->fetch_assoc()) {
        $kelas_options[] = $row['id_name'];
    }
}

// Restore old input values if coming from a failed submission
$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

include 'UI/header.php';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Check-In Tamu - RFID & OCR</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- QRCode.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <!-- mPDF is handled by Composer, not needed here -->
    <!-- Custom CSS -->
    <style>
        /* Modern Gradient Background for Dashboard Content */
        .glass-dashboard-bg {
            background: rgba(255, 255, 255, 0.9);
            min-height: calc(100vh - 60px);
            padding: 2rem 1.5rem;
            position: relative;
        }
        
        .glass-dashboard-bg::before,
        .glass-dashboard-bg::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            z-index: 0;
            filter: blur(60px);
            opacity: 0.6;
            pointer-events: none;
        }
        .glass-dashboard-bg::before {
            background: #ff9a9e;
            width: 300px;
            height: 300px;
            top: -50px;
            left: -50px;
        }
        .glass-dashboard-bg::after {
            background: #fecfef;
            width: 400px;
            height: 400px;
            bottom: -100px;
            right: -50px;
        }

        .content-container {
            position: relative;
            z-index: 1;
        }

        /* Main form initially hidden */
        #main-form-content {
            display: none;
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* RFID Scanner Status */
        .rfid-status-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
        }
        
        .rfid-status-card.connected {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .rfid-status-card.error {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }
        
        .rfid-scan-animation {
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: scanning 2.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }
        
        @keyframes scanning {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .rfid-icon {
            font-size: 54px;
            margin-bottom: 10px;
            animation: pulse 2.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.9; }
            50% { transform: scale(1.15); opacity: 1; }
        }
        
        /* Choice buttons */
        .input-method-choice {
            display: flex;
            gap: 25px;
            justify-content: center;
            margin: 40px 0;
            flex-wrap: wrap;
        }
        
        .method-card {
            flex: 1;
            min-width: 250px;
            max-width: 320px;
            padding: 35px 25px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        
        .method-card:hover {
            border-color: #0d6efd;
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 15px 35px rgba(13, 110, 253, 0.15);
            background: rgba(255, 255, 255, 0.9);
        }
        
        .method-card.selected {
            border-color: #0d6efd;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            box-shadow: 0 8px 25px rgba(13, 110, 253, 0.2);
        }
        
        .method-icon {
            font-size: 70px;
            margin-bottom: 20px;
            color: #adb5bd;
            transition: color 0.3s ease;
        }
        
        .method-card:hover .method-icon {
            color: #6c757d;
        }
        
        .method-card.selected .method-icon {
            color: #0d6efd;
            animation: bounce 0.5s ease;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        /* Input sections */
        .input-section {
            display: none;
            padding: 20px 0;
        }
        
        .input-section.active {
            display: block;
            animation: slideUpFade 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes slideUpFade {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* OCR Status styling */
        #ocr-status {
            display: none;
            padding: 15px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            margin: 20px 0;
            position: relative;
            overflow: hidden;
        }
        
        #ocr-status.processing::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        /* KTP Preview styling */
        #ktp-preview {
            display: none;
            margin: 20px auto;
            max-width: 500px;
            width: 100%;
            border: 3px solid #e0e0e0;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        }
        
        #ktp-preview:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }
        
        /* File upload area styling */
        .upload-area {
            border: 2px dashed #6c757d;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
        
        .upload-area.dragover {
            border-color: #0d6efd;
            background: #cfe2ff;
        }
        
        .upload-icon {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        /* RFID Card animation */
        .rfid-card-detected {
            animation: cardDetected 0.5s ease;
        }
        
        @keyframes cardDetected {
            0% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.2) rotate(5deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
        
        /* Progress indicator */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
            padding: 0 20px;
        }
        
        .progress-steps::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 50px;
            right: 50px;
            height: 3px;
            background: #e9ecef;
            z-index: 0;
            border-radius: 3px;
        }
        
        .step {
            background: transparent;
            padding: 10px;
            text-align: center;
            position: relative;
            z-index: 1;
            flex: 1;
            transition: all 0.4s ease;
        }
        
        .step small {
            font-weight: 600;
            color: #6c757d;
            display: block;
            margin-top: 8px;
            transition: color 0.3s ease;
            letter-spacing: 0.5px;
        }
        
        .step-circle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 0 0 5px white; /* creates the gap effect with the line */
        }
        
        .step.active .step-circle {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 0 0 5px white, 0 5px 15px rgba(118, 75, 162, 0.4);
        }
        
        .step.active small {
            color: #764ba2;
            font-weight: 700;
        }
        
        .step.completed .step-circle {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            box-shadow: 0 0 0 5px white, 0 4px 10px rgba(56, 239, 125, 0.2);
        }
        
        .step.completed small {
            color: #11998e;
        }
        
        /* Glass styling for cards taking form inputs */
        .glass-form-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.05);
            padding: 10px;
        }
        
        .form-section {
            background: #ffffff;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            border: 1px solid #f1f3f5;
            transition: all 0.3s ease;
        }
        
        .form-section:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.05);
            transform: translateY(-2px);
        }
        
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }
        
        .form-control:focus, .form-select:focus {
            background-color: #ffffff;
            border-color: #84a2fa;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }
        
        /* Form validation styling */
        .form-control.is-valid {
            border-color: #28a745;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        
        /* Alert animations */
        .alert {
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Loading spinner */
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.2em;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .input-method-choice {
                flex-direction: column;
            }
            
            .method-card {
                max-width: 100%;
            }
            
            .progress-steps {
                flex-direction: column;
            }
            
            .progress-steps::before {
                display: none;
            }
            
            #ktp-preview {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>

<div id="wrapper">
    <?php include 'UI/sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'UI/navbar.php'; ?>
        
        <main class="content glass-dashboard-bg p-3 p-md-4">
            <div class="container-fluid p-0 mx-auto content-container" style="max-width: 1400px; width: 96%;">
                <h1 class="h3 mb-4 mt-2 px-2 text-dark fw-bold" style="text-shadow: 2px 2px 4px rgba(255,255,255,0.7); letter-spacing: -0.5px;">Form Check-In <span class="fw-normal">Tamu</span></h1>
                
                <!-- RFID Scanner Status -->
                <div class="rfid-status-card mx-2 my-4 shadow-sm" id="rfid-status-card">
                    <div class="rfid-scan-animation"></div>
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="rfid-icon">
                                <i class="bi bi-wifi"></i>
                            </div>
                        </div>
                        <div class="col">
                            <h5 class="mb-1">RFID Scanner Status</h5>
                            <p class="mb-0" id="rfid-status-text">Menghubungkan ke scanner...</p>
                            <small id="rfid-last-scan">Belum ada kartu yang di-scan</small>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-light btn-sm" onclick="reconnectRFID()">
                                <i class="bi bi-arrow-clockwise"></i> Reconnect
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Progress Steps -->
                <div class="progress-steps mt-5 mx-2">
                    <div class="step active" id="step-1">
                        <div class="step-circle">1</div>
                        <small>Input KTP</small>
                    </div>
                    <div class="step" id="step-2">
                        <div class="step-circle">2</div>
                        <small>Data Tamu</small>
                    </div>
                    <div class="step" id="step-3">
                        <div class="step-circle">3</div>
                        <small>Detail Menginap</small>
                    </div>
                    <div class="step" id="step-4">
                        <div class="step-circle">4</div>
                        <small>Pembayaran</small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12 col-xl-10 mx-auto">
                        <div class="card glass-form-card border-0">
                            <div class="card-header bg-transparent border-0 pt-4 pb-0">
                                <h4 class="card-title fw-bold mb-0 text-center text-dark">Input Data Tamu Baru</h4>
                            </div>
                            <div class="card-body p-4 p-md-5">
                                <form action="actions/proses_checkin.php" method="post" enctype="multipart/form-data" id="checkin-form">
                                    <input type="hidden" name="input_method" id="input_method" value="manual">
                                    <!-- Hidden field to port the mobile-uploaded KTP image path to the backend -->
                                    <input type="hidden" name="mobile_ktp_image" id="mobile_ktp_image" value="">
                                    <!-- Step 1: Choose Input Method -->
                                    <div class="input-method-choice" id="input-method-choice">
                                        <div class="method-card" onclick="selectInputMethod('rfid')">
                                            <div class="method-icon">
                                                <i class="bi bi-credit-card-2-front"></i>
                                            </div>
                                            <h5>Scan e-KTP</h5>
                                            <p class="text-muted">Tempelkan e-KTP yang terdaftar pada RFID scanner</p>
                                            <small class="text-success">Recommended</small>
                                        </div>
                                        
                                        <div class="method-card" onclick="selectInputMethod('ocr')">
                                            <div class="method-icon">
                                                <i class="bi bi-camera"></i>
                                            </div>
                                            <h5>Foto KTP</h5>
                                            <p class="text-muted">Upload foto KTP untuk OCR scanning</p>
                                            <small class="text-info">Alternative</small>
                                        </div>
                                        
                                    </div>
                                    
                                    <!-- RFID Input Section -->
                                    <div class="input-section" id="rfid-input-section">
                                        <div class="text-center p-5">
                                            <div style="font-size: 100px; color: #0d6efd; animation: pulse 2s infinite;">
                                                <i class="bi bi-credit-card-2-front"></i>
                                            </div>
                                            <h4 class="mt-3">Silakan Tempelkan e-KTP pada Scanner</h4>
                                            <p class="text-muted">Pastikan e-KTP menempel dengan baik pada area scanner RFID</p>
                                            <div class="mt-4">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Waiting...</span>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-secondary mt-4" onclick="cancelInputMethod()">
                                                <i class="bi bi-arrow-left"></i> Kembali
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- OCR Upload Section -->
                                    <div class="input-section" id="ocr-input-section">
                                        <h6 class="text-primary mb-3">
                                            <i class="bi bi-camera"></i>
                                            Upload Foto KTP/SIM/Paspor
                                        </h6>
                                        
                                        <div class="upload-area" id="upload-area">
                                            <div class="upload-icon">
                                                📷
                                            </div>
                                            <h6>Klik untuk upload atau drag & drop foto identitas</h6>
                                            <p class="text-muted mb-0">Format: JPG, PNG, HEIC (Max. 5MB)</p>
                                            <p class="text-muted small">Pastikan foto jelas dan tidak buram untuk hasil OCR optimal</p>
                                            <input type="file" 
                                                   class="form-control d-none" 
                                                   id="upload_identitas" 
                                                   name="upload_identitas" 
                                                   accept="image/*,.heic,.heif">
                                        </div>
                                        
                                        <!-- Mobile Camera Trigger -->
                                        <div class="text-center mt-3" id="mobile-trigger-section">
                                            <p class="text-muted mb-2 small fw-medium">Atau scan langsung dari HP Anda:</p>
                                            <button type="button" class="btn btn-info text-white rounded-pill px-4 shadow-sm" id="btn-scan-hp">
                                                <i class="bi bi-phone-vibrate me-2"></i>Gunakan Kamera HP
                                            </button>
                                        </div>
                                        
                                        <!-- Container for QR Code Pairing -->
                                        <div id="qr-pairing-section" class="d-none text-center bg-light rounded-4 p-4 border border-info border-2 mt-4" style="background: rgba(13, 202, 240, 0.05) !important;">
                                            <h5 class="fw-bold text-info"><i class="bi bi-qr-code-scan me-2"></i>Scan dengan HP Anda</h5>
                                            <p class="small text-muted mb-3">Arahkan kamera ke QR Code ini untuk memfoto KTP langsung melalui HP Anda. Hasilnya akan otomatis diproses ke layar ini.</p>
                                            
                                            <div class="d-inline-flex justify-content-center align-items-center bg-white p-3 rounded-4 shadow-sm mb-3" style="min-width: 250px;">
                                                <div id="qrcode-display" class="d-flex justify-content-center w-100"></div>
                                            </div>
                                            
                                            <div class="d-flex justify-content-center align-items-center mb-2">
                                                <div class="spinner-grow spinner-grow-sm text-info me-2" role="status"></div>
                                                <span class="text-primary fw-medium" id="qr-status-text">Menunggu proses scan di HP...</span>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger mt-2 rounded-pill px-3" id="btn-cancel-qr">Batalkan Proses</button>
                                        </div>
                                        
                                        <!-- KTP Preview -->
                                        <div class="text-center mt-3">
                                            <img id="ktp-preview" src="#" alt="Preview KTP" class="d-none" style="width: 100%; height: 250px; object-fit: contain; border-radius: 8px; background-color: #f8f9fa; border: 1px solid #dee2e6;">
                                        </div>
                                        
                                        <!-- OCR Status -->
                                        <div id="ocr-status" class="text-center fw-bold processing mt-3" style="display: none; background: rgba(13, 110, 253, 0.1); padding: 10px; border-radius: 8px;">
                                            <div class="spinner-border spinner-border-sm me-2 text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <span id="ocr-message" class="text-primary" style="font-weight: 700;">Memproses gambar KTP...</span>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center mt-4">
                                            <button type="button" class="btn btn-secondary" onclick="cancelInputMethod()">
                                                <i class="bi bi-arrow-left"></i> Kembali
                                            </button>
                                            <span class="text-muted">Atau isi data dengan <a href="javascript:void(0)" onclick="selectInputMethod('manual')" class="text-primary fw-bold text-decoration-none text-hover-underline">Ketik Manual</a></span>
                                        </div>
                                    </div>
                                    
                                    <hr class="my-4" id="form-divider" style="display: none;">
                                    
                                    <!-- Main Form Content -->
                                    <div id="main-form-content">
                                        
                                        <!-- Hidden field for RFID UID -->
                                        <input type="hidden" id="rfid_uid" name="rfid_uid" value="">
                                        
                                        <!-- Step 2: Data Tamu -->
                                        <div class="form-section mb-4">
                                            <h5 class="text-primary mb-3 fw-bold pb-2 border-bottom">
                                                <i class="bi bi-person me-2"></i>
                                                Langkah 2: Data Tamu
                                            </h5>
                                            
                                            <!-- Prominent RFID Registration Box -->
                                            <div class="alert alert-info d-flex align-items-center mb-4 rfid-registration-box" id="rfid-registration-box" style="border: 2px dashed #0dcaf0; background-color: rgba(13, 202, 240, 0.05);">
                                                <div class="me-3" style="font-size: 2rem; color: #0dcaf0;">
                                                    <i class="bi bi-credit-card-2-front"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="alert-heading fw-bold mb-1">Daftarkan e-KTP (Opsional)</h6>
                                                    <p class="mb-0 small" id="rfid-registration-text">Daftarkan e-KTP ini supaya pada tahap selanjutnya tidak perlu repot input manual.</p>
                                                </div>
                                                <div id="rfid-registration-pulse" class="ms-3 d-none">
                                                    <div class="spinner-border spinner-border-sm text-info" role="status">
                                                        <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Additional KTP Preview inside the form -->
                                            <div class="text-center mb-4 d-none" id="form-ktp-preview-container">
                                                <p class="text-muted small mb-2">Refrensi Foto Identitas (Otomatis terunggah)</p>
                                                <img id="form-ktp-preview" src="#" alt="Preview KTP Form" class="shadow-sm" style="width: 100%; height: 200px; object-fit: contain; border-radius: 8px; background-color: #f8f9fa; border: 1px solid #dee2e6;">
                                            </div>
                                            
                                            <div class="row">
                                                <!-- NIK -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="nik" class="form-label">NIK</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="nik" 
                                                           name="nik" 
                                                           value="<?= htmlspecialchars($old['nik'] ?? '') ?>"
                                                           maxlength="16" 
                                                           pattern="[0-9]{16}"
                                                           placeholder="16 digit NIK">
                                                    <div class="invalid-feedback">
                                                        NIK harus 16 digit angka
                                                    </div>
                                                </div>
                                                
                                                <!-- Nama -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="nama_tamu" class="form-label">
                                                        Nama Lengkap <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="nama_tamu" 
                                                           name="nama_tamu" value="<?= htmlspecialchars($old['nama_tamu'] ?? '') ?>" 
                                                           required>
                                                    <div class="invalid-feedback">
                                                        Nama tamu harus diisi
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <!-- Tempat Lahir -->
                                                <div class="col-md-4 mb-3">
                                                    <label for="tempat_lahir" class="form-label">Tempat Lahir</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="tempat_lahir" 
                                                           name="tempat_lahir" value="<?= htmlspecialchars($old['tempat_lahir'] ?? '') ?>">
                                                </div>
                                                
                                                <!-- Tanggal Lahir -->
                                                <div class="col-md-4 mb-3">
                                                    <label for="tanggal_lahir" class="form-label">Tanggal Lahir</label>
                                                    <input type="date" 
                                                           class="form-control" 
                                                           id="tanggal_lahir" 
                                                           name="tanggal_lahir" value="<?= htmlspecialchars($old['tanggal_lahir'] ?? '') ?>">
                                                </div>
                                                
                                                <!-- Jenis Kelamin -->
                                                <div class="col-md-4 mb-3">
                                                    <label for="jenis_kelamin" class="form-label">Jenis Kelamin</label>
                                                    <select class="form-select" id="jenis_kelamin" name="jenis_kelamin">
                                                        <option value="">-- Pilih --</option>
                                                        <option value="LAKI-LAKI" <?= (isset($old['jenis_kelamin']) && $old['jenis_kelamin'] === 'LAKI-LAKI') ? 'selected' : '' ?>>Laki-laki</option>
                                                        <option value="PEREMPUAN" <?= (isset($old['jenis_kelamin']) && $old['jenis_kelamin'] === 'PEREMPUAN') ? 'selected' : '' ?>>Perempuan</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <!-- No HP -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="no_hp" class="form-label">
                                                        No. Handphone <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="tel" 
                                                           class="form-control" 
                                                           id="no_hp" 
                                                           name="no_hp" value="<?= htmlspecialchars($old['no_hp'] ?? '') ?>" 
                                                           placeholder="08xxxxxxxxxx"
                                                           required>
                                                    <div class="invalid-feedback">
                                                        No. HP harus diisi dengan format yang benar
                                                    </div>
                                                </div>
                                                
                                                <!-- Email -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="email" class="form-label">
                                                        Email <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="email" 
                                                           class="form-control" 
                                                           id="email" 
                                                           name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" 
                                                           placeholder="email@example.com"
                                                           required>
                                                    <div class="invalid-feedback">
                                                        Email harus diisi dengan format yang benar
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Alamat -->
                                            <div class="mb-3">
                                                <label for="alamat" class="form-label">Alamat</label>
                                                <textarea class="form-control" 
                                                          id="alamat" 
                                                          name="alamat" 
                                                          rows="2"
                                                          placeholder="Alamat lengkap sesuai KTP"><?= htmlspecialchars($old['alamat'] ?? '') ?></textarea>
                                            </div>
                                            
                                            <div class="row">
                                                <!-- RT/RW -->
                                                <div class="col-md-3 mb-3">
                                                    <label for="rt_rw" class="form-label">RT/RW</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="rt_rw" 
                                                           name="rt_rw" value="<?= htmlspecialchars($old['rt_rw'] ?? '') ?>" 
                                                           placeholder="000/000">
                                                </div>
                                                
                                                <!-- Kelurahan/Desa -->
                                                <div class="col-md-3 mb-3">
                                                    <label for="kel_desa" class="form-label">Kelurahan/Desa</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="kel_desa" 
                                                           name="kel_desa" value="<?= htmlspecialchars($old['kel_desa'] ?? '') ?>">
                                                </div>
                                                
                                                <!-- Kecamatan -->
                                                <div class="col-md-3 mb-3">
                                                    <label for="kecamatan" class="form-label">Kecamatan</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="kecamatan" 
                                                           name="kecamatan" value="<?= htmlspecialchars($old['kecamatan'] ?? '') ?>">
                                                </div>
                                                
                                                <!-- Kota/Kabupaten -->
                                                <div class="col-md-3 mb-3">
                                                    <label for="kota_kabupaten" class="form-label">Kota/Kab.</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="kota_kabupaten" 
                                                           name="kota_kabupaten" value="<?= htmlspecialchars($old['kota_kabupaten'] ?? '') ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <!-- Agama -->
                                                <div class="col-md-4 mb-3">
                                                    <label for="agama" class="form-label">Agama</label>
                                                    <select class="form-select" id="agama" name="agama">
                                                        <option value="">-- Pilih --</option>
                                                        <option value="ISLAM" <?= (isset($old['agama']) && $old['agama'] === 'ISLAM') ? 'selected' : '' ?>>Islam</option>
                                                        <option value="KRISTEN" <?= (isset($old['agama']) && $old['agama'] === 'KRISTEN') ? 'selected' : '' ?>>Kristen</option>
                                                        <option value="KATOLIK" <?= (isset($old['agama']) && $old['agama'] === 'KATOLIK') ? 'selected' : '' ?>>Katolik</option>
                                                        <option value="HINDU" <?= (isset($old['agama']) && $old['agama'] === 'HINDU') ? 'selected' : '' ?>>Hindu</option>
                                                        <option value="BUDDHA" <?= (isset($old['agama']) && $old['agama'] === 'BUDDHA') ? 'selected' : '' ?>>Buddha</option>
                                                        <option value="KONGHUCU" <?= (isset($old['agama']) && $old['agama'] === 'KONGHUCU') ? 'selected' : '' ?>>Konghucu</option>
                                                    </select>
                                                </div>
                                                
                                                <!-- Pekerjaan -->
                                                <div class="col-md-4 mb-3">
                                                    <label for="pekerjaan" class="form-label">Pekerjaan</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="pekerjaan" 
                                                           name="pekerjaan" value="<?= htmlspecialchars($old['pekerjaan'] ?? '') ?>">
                                                </div>
                                                
                                                <!-- Akun Google -->
                                                <div class="col-md-4 mb-3">
                                                    <label for="akun_google" class="form-label">
                                                        Akun Google <small class="text-muted">(Opsional)</small>
                                                    </label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="akun_google" 
                                                           name="akun_google" value="<?= htmlspecialchars($old['akun_google'] ?? '') ?>" 
                                                           placeholder="username@gmail.com">
                                                </div>
                                            </div>
                                            

                                            <div class="row">
                                                <!-- Tipe Tamu -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="tipe_tamu" class="form-label">
                                                        Tipe Tamu <span class="text-danger">*</span>
                                                    </label>
                                                    <select class="form-select" 
                                                            id="tipe_tamu" 
                                                            name="tipe_tamu" 
                                                            onchange="toggleInstansi(this.value)" 
                                                            required>
                                                        <option value="">-- Pilih Tipe --</option>
                                                        <option value="Pribadi" <?= (isset($old['tipe_tamu']) && $old['tipe_tamu'] === 'Pribadi') ? 'selected' : '' ?>>Pribadi</option>
                                                        <option value="Instansi" <?= (isset($old['tipe_tamu']) && $old['tipe_tamu'] === 'Instansi') ? 'selected' : '' ?>>Instansi</option>
                                                        <option value="Perusahaan" <?= (isset($old['tipe_tamu']) && $old['tipe_tamu'] === 'Perusahaan') ? 'selected' : '' ?>>Perusahaan</option>
                                                    </select>
                                                    <div class="invalid-feedback">
                                                        Pilih tipe tamu
                                                    </div>
                                                </div>
                                                
                                                <!-- Nama Instansi -->
                                                <div class="col-md-6 mb-3" id="instansi-field" style="<?= (isset($old['tipe_tamu']) && in_array($old['tipe_tamu'], ['Instansi', 'Perusahaan'])) ? '' : 'display:none;' ?>">
                                                    <label for="nama_instansi" class="form-label">
                                                        Nama Instansi/Perusahaan <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="nama_instansi" 
                                                           name="nama_instansi" value="<?= htmlspecialchars($old['nama_instansi'] ?? '') ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr class="my-4">
                                        
                                        <!-- Step 3: Detail Menginap -->
                                        <div class="form-section mb-4 mt-5">
                                            <h5 class="text-primary mb-4 fw-bold pb-2 border-bottom">
                                                <i class="bi bi-calendar me-2"></i>
                                                Langkah 3: Detail Menginap
                                            </h5>
                                            
                                            <div class="row">
                                                <!-- Check-in Date -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="tanggal_check_in" class="form-label">
                                                        Tanggal Check-In <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="date" 
                                                           class="form-control" 
                                                           id="tanggal_check_in" 
                                                           name="tanggal_check_in" value="<?= htmlspecialchars($old['tanggal_check_in'] ?? '') ?>" 
                                                           required>
                                                    <div class="invalid-feedback">
                                                        Tanggal check-in harus diisi
                                                    </div>
                                                </div>
                                                
                                                <!-- Check-out Date -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="tanggal_check_out" class="form-label">
                                                        Tanggal Check-Out <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="date" 
                                                           class="form-control" 
                                                           id="tanggal_check_out" 
                                                           name="tanggal_check_out" value="<?= htmlspecialchars($old['tanggal_check_out'] ?? '') ?>" 
                                                           required>
                                                    <div class="invalid-feedback">
                                                        Tanggal check-out harus diisi
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <!-- Room Selection -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="nama_kamar" class="form-label">
                                                        Nama Kamar <span class="text-danger">*</span>
                                                    </label>
                                                    <select class="form-select" 
                                                            id="nama_kamar" 
                                                            name="nama_kamar" 
                                                            required>
                                                        <option value="">-- Pilih Kamar --</option>
                                                        <?php foreach ($kamar_options as $kamar): ?>
                                                            <option value="<?= htmlspecialchars($kamar) ?>" <?= (isset($old['nama_kamar']) && $old['nama_kamar'] === $kamar) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($kamar) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <div class="invalid-feedback">
                                                        Pilih kamar
                                                    </div>
                                                </div>
                                                
                                                <!-- Room Price -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="harga_kamar" class="form-label">
                                                        Harga Kamar (per malam) <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="number" 
                                                           class="form-control" 
                                                           id="harga_kamar" 
                                                           name="harga_kamar" value="<?= htmlspecialchars($old['harga_kamar'] ?? '') ?>" 
                                                           min="0" 
                                                           placeholder="0"
                                                           required>
                                                    <div class="invalid-feedback">
                                                        Harga kamar harus diisi
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Event/Training -->
                                            <div class="mb-3">
                                                <label for="acara_judul_diklat" class="form-label">
                                                    Acara / Judul Diklat <small class="text-muted">(Jika ada)</small>
                                                </label>
                                                <input type="text" 
                                                       class="form-control" 
                                                       id="acara_judul_diklat" 
                                                       name="acara_judul_diklat" value="<?= htmlspecialchars($old['acara_judul_diklat'] ?? '') ?>" 
                                                       placeholder="Nama acara atau pelatihan">
                                            </div>
                                            
                                            <div class="row">
                                                <!-- Class Selection -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="nama_kelas" class="form-label">
                                                        Nama Kelas <small class="text-muted">(Jika ada)</small>
                                                    </label>
                                                    <select class="form-select" 
                                                            id="nama_kelas" 
                                                            name="nama_kelas">
                                                        <option value="">-- Tidak Mengambil Kelas --</option>
                                                        <?php foreach ($kelas_options as $kelas): ?>
                                                            <option value="<?= htmlspecialchars($kelas) ?>" <?= (isset($old['nama_kelas']) && $old['nama_kelas'] === $kelas) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($kelas) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                
                                                <!-- Class Price -->
                                                <div class="col-md-6 mb-3">
                                                    <label for="harga_kelas" class="form-label">
                                                        Harga Kelas
                                                    </label>
                                                    <input type="number" 
                                                           class="form-control" 
                                                           id="harga_kelas" 
                                                           name="harga_kelas" value="<?= htmlspecialchars($old['harga_kelas'] ?? '') ?>" 
                                                           min="0"
                                                           placeholder="0">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr class="my-4">
                                        
                                        <!-- Step 4: Pembayaran -->
                                        <div class="form-section mb-0 mt-5">
                                            <h5 class="text-primary mb-4 fw-bold pb-2 border-bottom">
                                                <i class="bi bi-wallet2 me-2"></i>
                                                Langkah 4: Pembayaran
                                            </h5>
                                            
                                            <div class="mb-3">
                                                <label for="upload_pembayaran" class="form-label">
                                                    Upload Bukti Pembayaran <span class="text-danger">*</span>
                                                </label>
                                                <input type="file" 
                                                       class="form-control" 
                                                       id="upload_pembayaran" 
                                                       name="upload_pembayaran" 
                                                       accept="image/*,.heic,.heif" 
                                                       required>
                                                <div class="form-text">
                                                    Upload bukti transfer atau screenshot pembayaran
                                                </div>
                                                <div class="invalid-feedback">
                                                    Bukti pembayaran harus diupload
                                                </div>
                                            </div>
                                            
                                            <!-- Payment Preview -->
                                            <img id="payment-preview" 
                                                 src="#" 
                                                 alt="Preview Pembayaran" 
                                                 class="img-thumbnail d-none" 
                                                 style="max-width: 300px;">
                                        </div>
                                        
                                        <!-- Total Summary -->
                                        <div class="card bg-light mb-4">
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
                                        
                                        <!-- Submit Button -->
                                        <div class="d-flex justify-content-between mt-5 pt-4 border-top">
                                            <a href="index.php" class="btn btn-light px-4 py-2 rounded-pill fw-bold text-secondary">Batal</a>
                                            <button type="submit" class="btn btn-primary px-5 py-2 rounded-pill fw-bold" id="submit-button" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none; box-shadow: 0 4px 15px rgba(56, 239, 125, 0.4);">
                                                <i class="bi bi-check-circle me-1"></i> Simpan Data Tamu
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
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
<!-- HEIC to JPEG converter -->
<script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>

<?php if (isset($_SESSION['error'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: '<?= htmlspecialchars($_SESSION['error']) ?>'
        });
    });
</script>
<?php unset($_SESSION['error']); endif; ?>

<!-- Enhanced JavaScript with RFID Integration -->
<script>
// ========== GLOBAL VARIABLES ==========
let selectedInputMethod = null;
let rfidSocket = null;
let rfidReconnectInterval = null;
let lastScannedUID = null;

// ========== RFID CONNECTION ==========
function connectRFID() {
    // Get ESP32 IP from environment or config
    const ESP32_IP = '<?= $_ENV["ESP32_IP"] ?? "192.168.1.100" ?>';
    const wsUrl = `ws://${ESP32_IP}:81/`;
    
    try {
        rfidSocket = new WebSocket(wsUrl);
        
        rfidSocket.onopen = function() {
            console.log('RFID Scanner connected');
            updateRFIDStatus('connected', 'Scanner RFID terhubung dan siap');
            clearInterval(rfidReconnectInterval);
        };
        
        rfidSocket.onmessage = function(event) {
            try {
                const data = JSON.parse(event.data);
                if (data.uid) {
                    if (selectedInputMethod === 'rfid') {
                        // Regular flow: auto-fill guest data
                        handleRFIDCard(data.uid);
                    } else if (selectedInputMethod === 'manual' || selectedInputMethod === 'ocr') {
                        // Registration mode: Bind this UID to the new form being filled out
                        handleRFIDRegistration(data.uid);
                    }
                }
            } catch (e) {
                console.error('Error parsing RFID data:', e);
            }
        };
        
        rfidSocket.onerror = function(error) {
            console.error('RFID connection error:', error);
            updateRFIDStatus('error', 'Koneksi ke scanner bermasalah');
        };
        
        rfidSocket.onclose = function() {
            console.log('RFID Scanner disconnected');
            updateRFIDStatus('disconnected', 'Scanner tidak terhubung');
            // Auto reconnect every 5 seconds
            rfidReconnectInterval = setInterval(connectRFID, 5000);
        };
        
    } catch (e) {
        console.error('Failed to connect RFID:', e);
        updateRFIDStatus('error', 'Gagal menghubungkan ke scanner');
    }
}

function reconnectRFID() {
    if (rfidSocket) {
        rfidSocket.close();
    }
    connectRFID();
}

function updateRFIDStatus(status, message) {
    const card = document.getElementById('rfid-status-card');
    const text = document.getElementById('rfid-status-text');
    
    if (card && text) {
        text.textContent = message;
        
        // Update card styling
        card.classList.remove('connected', 'error');
        if (status === 'connected') {
            card.classList.add('connected');
        } else if (status === 'error' || status === 'disconnected') {
            card.classList.add('error');
        }
    }
}

function handleRFIDCard(uid) {
    console.log('RFID Card detected:', uid);
    
    // Prevent duplicate processing
    if (lastScannedUID === uid && Date.now() - lastScannedTime < 3000) {
        return;
    }
    
    lastScannedUID = uid;
    lastScannedTime = Date.now();
    
    // Update UI
    const lastScan = document.getElementById('rfid-last-scan');
    if (lastScan) {
        lastScan.textContent = `UID: ${uid} - ${new Date().toLocaleTimeString()}`;
    }
    
    // Add animation effect
    const rfidSection = document.getElementById('rfid-input-section');
    if (rfidSection) {
        rfidSection.classList.add('rfid-card-detected');
        setTimeout(() => rfidSection.classList.remove('rfid-card-detected'), 500);
    }
    
    // Store UID
    document.getElementById('rfid_uid').value = uid;
    
    // Fetch data from server
    fetchDataByRFID(uid);
}

function fetchDataByRFID(uid) {
    showAlert('Membaca data dari e-KTP...', 'info');
    
    fetch('api/rfid_process.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'uid=' + uid + '&action=lookup'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.found) {
            fillFormFromRFID(data.data);
            showAlert('Data berhasil dimuat dari e-KTP', 'success');
            showMainForm();
        } else {
            showAlert('e-KTP belum terdaftar. Silakan isi data manual untuk registrasi.', 'warning');
            sessionStorage.setItem('current_uid', uid);
            showMainForm();
        }
    })
    .catch(error => {
        console.error('Error fetching RFID data:', error);
        showAlert('Gagal membaca data. Silakan coba lagi atau isi manual.', 'danger');
    });
}

function handleRFIDRegistration(uid) {
    console.log('RFID Registration scan detected:', uid);
    
    // Prevent duplicate processing
    if (lastScannedUID === uid && Date.now() - lastScannedTime < 3000) {
        return;
    }
    
    lastScannedUID = uid;
    lastScannedTime = Date.now();
    
    // Ensure the box is visible
    const box = document.getElementById('rfid-registration-box');
    const textPart = document.getElementById('rfid-registration-text');
    const pulsePart = document.getElementById('rfid-registration-pulse');
    const uidField = document.getElementById('rfid_uid');
    
    if (box) {
        box.className = 'alert alert-warning d-flex align-items-center mb-4 rfid-registration-box';
        box.style.border = '2px dashed #ffc107';
        box.style.backgroundColor = 'rgba(255, 193, 7, 0.05)';
        textPart.innerHTML = '<strong>Memeriksa status pendaftaran KTP...</strong>';
        pulsePart.classList.remove('d-none');
    }
    
    // Check if this UID is already bound
    fetch('api/rfid_process.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'uid=' + uid + '&action=lookup'
    })
    .then(response => response.json())
    .then(data => {
        if (box) {
            pulsePart.classList.add('d-none');
            // Provide visual feedback
            if (data.success && data.found) {
                box.className = 'alert alert-danger d-flex align-items-center mb-4 rfid-registration-box';
                box.style.border = '2px dashed #dc3545';
                box.style.backgroundColor = 'rgba(220, 53, 69, 0.05)';
                textPart.innerHTML = `<strong>Peringatan Terdaftar:</strong> KTP ini (Atas nama: ${data.data.nama}) sudah digunakan orang lain. Jika tetap dilanjutkan, data lama e-KTP akan tertimpa.`;
                
                // Do NOT aggressively bind it, just warn them. 
                uidField.value = '';
                
            } else {
                box.className = 'alert alert-success d-flex align-items-center mb-4 rfid-registration-box';
                box.style.border = '2px solid #198754';
                box.style.backgroundColor = 'rgba(25, 135, 84, 0.05)';
                textPart.innerHTML = `<strong>KTP Berhasil Terhubung!</strong> UID: ${uid} siap didaftarkan saat Anda menekan tombol Simpan di bawah.`;
                
                // Bind the UID string to the hidden form input
                uidField.value = uid;
            }
        }
    })
    .catch(error => {
        console.error('Error validating RFID registration:', error);
        if (box) {
            pulsePart.classList.add('d-none');
            box.className = 'alert alert-danger d-flex align-items-center mb-4 rfid-registration-box';
            box.style.border = '2px dashed #dc3545';
            box.style.backgroundColor = 'rgba(220, 53, 69, 0.05)';
            textPart.innerHTML = `<strong>Koneksi Gagal:</strong> Tidak dapat memeriksa status e-KTP.`;
        }
    });
}

function checkNIKRegistration(nikValue) {
    if (!nikValue) return;
    
    // Ensure we are working with just the digits
    const cleanNik = String(nikValue).replace(/\D/g, '');
    
    const box = document.getElementById('rfid-registration-box');
    const textPart = document.getElementById('rfid-registration-text');
    const titlePart = box ? box.querySelector('h6.alert-heading') : null;
    const pulsePart = document.getElementById('rfid-registration-pulse');
    const uidField = document.getElementById('rfid_uid');
    
    // If not valid length, don't check backend but silently reset if needed
    if (cleanNik.length < 16) return;
    
    // UI indicator that we're checking
    if (pulsePart) pulsePart.classList.remove('d-none');
    if (textPart) textPart.innerHTML = `Mengecek status database untuk NIK ini...`;
    
    fetch('api/rfid_process.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'nik=' + cleanNik + '&action=check_nik'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.found && box) {
            box.className = 'alert alert-info d-flex align-items-center mb-4 rfid-registration-box';
            box.style.border = '2px solid #0dcaf0';
            box.style.backgroundColor = 'rgba(13, 202, 240, 0.1)';
            if (titlePart) titlePart.innerHTML = 'KTP Sudah Terdaftar <i class="bi bi-check-circle-fill ms-1"></i>';
            textPart.innerHTML = `Tamu ini terdeteksi memiliki riwayat e-KTP. UID-nya <code>${data.data.uid}</code> otomatis terhubung.`;
            
            // Auto bind it since we know this NIK belongs to that UID
            if (uidField) uidField.value = data.data.uid;
            if (pulsePart) pulsePart.classList.add('d-none');
            
        } else if (data.success && !data.found && box) {
            if (!uidField || !uidField.value) {
                // Reset to default instruction only if there's no active card scan happening
                box.className = 'alert alert-secondary d-flex align-items-center mb-4 rfid-registration-box';
                box.style.border = '2px dashed #6c757d';
                box.style.backgroundColor = 'rgba(108, 117, 125, 0.05)';
                if (titlePart) titlePart.innerHTML = 'e-KTP Belum Terdaftar (Opsional)';
                textPart.innerHTML = `Daftarkan e-KTP ini supaya pada tahap selanjutnya tidak perlu repot input manual.`;
            } else {
                // They just scanned a physical card that wasn't found before entering this NIK
                if (titlePart) titlePart.innerHTML = 'Siap Didaftarkan';
                textPart.innerHTML = `UID ${uidField.value} siap diikat ke data ini.`;
            }
            if (pulsePart) pulsePart.classList.add('d-none');
        }
    })
    .catch(error => {
        console.error('Error checking NIK:', error);
        if (pulsePart) pulsePart.classList.add('d-none');
    });
}

function fillFormFromRFID(data) {
    // Fill all form fields
    const fields = {
        'nik': data.nik,
        'nama_tamu': data.nama,
        'tempat_lahir': data.tempat_lahir,
        'tanggal_lahir': data.tanggal_lahir,
        'jenis_kelamin': data.jenis_kelamin,
        'alamat': data.alamat,
        'rt_rw': data.rt_rw,
        'kel_desa': data.kel_desa,
        'kecamatan': data.kecamatan,
        'kota_kabupaten': data.kota_kabupaten,
        'agama': data.agama,
        'pekerjaan': data.pekerjaan,
        'no_hp': data.no_hp,
        'email': data.email
    };
    
    for (const [fieldId, value] of Object.entries(fields)) {
        const field = document.getElementById(fieldId);
        if (field && value) {
            field.value = value;
            field.classList.add('is-valid');
        }
    }
    
    updateProgress(2);
}

// ========== REAL-TIME OVERLAP VALIDATION ==========
function checkBookingOverlap() {
    const nik = document.getElementById('nik')?.value.replace(/\D/g, '') || '';
    const nama_tamu = document.getElementById('nama_tamu')?.value || '';
    const tanggal_check_in = document.getElementById('tanggal_check_in')?.value || '';
    const tanggal_check_out = document.getElementById('tanggal_check_out')?.value || '';
    const nama_kamar = document.getElementById('nama_kamar')?.value || '';
    const nama_kelas = document.getElementById('nama_kelas')?.value || '';
    
    // Only check if we have dates and at least one identifier or booking item
    if (!tanggal_check_in || !tanggal_check_out) return;
    if (!nik && !nama_tamu && !nama_kamar && !nama_kelas) return;
    
    // Ensure checkout is after checkin
    if (new Date(tanggal_check_out) <= new Date(tanggal_check_in)) return;

    fetch('api/api_check_overlap.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            nik, nama_tamu, tanggal_check_in, tanggal_check_out, nama_kamar, nama_kelas
        })
    })
    .then(response => response.json())
    .then(data => {
        const submitBtn = document.getElementById('submit-button');
        
        // Setup fields for dynamic inline validation
        const targetFields = {
            'user': ['nik', 'nama_tamu'],
            'room': ['nama_kamar'],
            'class': ['nama_kelas']
        };
        
        // Reset all overlap-triggered invalid states first
        ['nik', 'nama_tamu', 'nama_kamar', 'nama_kelas'].forEach(id => {
            const el = document.getElementById(id);
            if (el && el.dataset.overlapError === 'true') {
                el.classList.remove('is-invalid');
                el.dataset.overlapError = 'false';
                // Reset to default feedback if available
                const feedback = el.nextElementSibling;
                if (feedback && feedback.classList.contains('invalid-feedback')) {
                    feedback.innerHTML = el.dataset.originalFeedback || feedback.innerHTML;
                }
            }
        });
        
        if (data.overlap) {
            // Lock submit button
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-x-circle me-2"></i> Jadwal Bertabrakan';
                submitBtn.style.background = 'linear-gradient(135deg, #e52d27 0%, #b31217 100%)';
                submitBtn.style.boxShadow = '0 4px 15px rgba(229, 45, 39, 0.4)';
            }
            
            // Apply inline validation to the specific overlapping field(s)
            if (data.overlap_type && targetFields[data.overlap_type]) {
                targetFields[data.overlap_type].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.classList.add('is-invalid');
                        el.dataset.overlapError = 'true';
                        
                        const feedback = el.nextElementSibling;
                        if (feedback && feedback.classList.contains('invalid-feedback')) {
                            // Backup original message so we don't lose standard validation like "Pilih Kamar"
                            if (!el.dataset.originalFeedback) {
                                el.dataset.originalFeedback = feedback.innerHTML;
                            }
                            feedback.innerHTML = data.message;
                        }
                    }
                });
            }
            
            // Show alert
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Jadwal Bentrok!',
                    html: data.message,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true
                });
            }
        } else {
            // Unlock submit button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-save me-2"></i> Simpan Data Tamu';
                submitBtn.style.background = 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)';
                submitBtn.style.boxShadow = '0 4px 15px rgba(56, 239, 125, 0.4)';
            }
        }
    })
    .catch(error => console.error('Overlap check failed:', error));
}

// Attach listeners for dynamic overlap checking
document.addEventListener('DOMContentLoaded', function() {
    const triggerFields = ['nik', 'nama_tamu', 'tanggal_check_in', 'tanggal_check_out', 'nama_kamar', 'nama_kelas'];
    triggerFields.forEach(fieldId => {
        const el = document.getElementById(fieldId);
        if (el) {
            el.addEventListener('change', checkBookingOverlap);
            el.addEventListener('blur', checkBookingOverlap);
            if (fieldId === 'nik' || fieldId === 'nama_tamu') {
                el.addEventListener('input', () => { setTimeout(checkBookingOverlap, 800) }); // debounce text inputs slightly
            }
        }
    });
});

// ========== INPUT METHOD SELECTION ==========
function selectInputMethod(method) {
    selectedInputMethod = method;
    
    const inputMethodField = document.getElementById('input_method');
    if (inputMethodField) inputMethodField.value = method;
    
    // Hide method selection
    document.getElementById('input-method-choice').style.display = 'none';
    
    // Hide all input sections
    document.querySelectorAll('.input-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Show selected input method
    switch(method) {
        case 'rfid':
            document.getElementById('rfid-input-section').classList.add('active');
            break;
        case 'ocr':
            document.getElementById('ocr-input-section').classList.add('active');
            setupOCRHandlers();
            break;
        case 'manual':
            showMainForm();
            break;
    }
}

function cancelInputMethod() {
    selectedInputMethod = null;
    
    // Show method selection
    document.getElementById('input-method-choice').style.display = 'flex';
    
    // Hide all input sections
    document.querySelectorAll('.input-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Hide main form
    document.getElementById('main-form-content').style.display = 'none';
    document.getElementById('form-divider').style.display = 'none';
}

function showMainForm() {
    // Hide input sections
    document.querySelectorAll('.input-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Show form
    document.getElementById('form-divider').style.display = 'block';
    document.getElementById('main-form-content').style.display = 'block';
    
    // Set default dates
    setDefaultDates();
    
    // Update progress
    updateProgress(2);
    
    // Focus first field
    setTimeout(() => {
        const firstField = document.querySelector('#main-form-content input:not([type="hidden"]):not([type="file"])');
        if (firstField) firstField.focus();
    }, 300);
}

// ========== OCR HANDLERS ==========
function setupOCRHandlers() {
    const uploadArea = document.getElementById('upload-area');
    const identitasInput = document.getElementById('upload_identitas');
    
    if (uploadArea && identitasInput) {
        // Click to upload
        uploadArea.onclick = function() {
            identitasInput.click();
        };
        
        // Drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                identitasInput.files = files;
                handleOCRUpload(files[0]);
            }
        });
        
        // File input change
        identitasInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleOCRUpload(this.files[0]);
            }
        });
        
        // Scan via HP trigger
        const btnScanHp = document.getElementById('btn-scan-hp');
        const btnCancelQr = document.getElementById('btn-cancel-qr');
        
        if (btnScanHp) {
            btnScanHp.addEventListener('click', startMobileKtpScan);
        }
        if (btnCancelQr) {
            btnCancelQr.addEventListener('click', cancelMobileKtpScan);
        }
    }
}

let scanPollingInterval = null;
let currentScanToken = null;

function generateRandomToken() {
    return Math.random().toString(36).substring(2, 8).toUpperCase();
}

function startMobileKtpScan() {
    // Hide standard upload buttons, show QR
    const mobileTrigger = document.getElementById('mobile-trigger-section');
    if (mobileTrigger) mobileTrigger.style.display = 'none';
    
    const uploadArea = document.getElementById('upload-area');
    if (uploadArea) uploadArea.style.display = 'none';
    
    // Hide the return & manual buttons temporarily to prevent user from navigating away mid-scan
    const bottomNav = document.querySelector('#ocr-input-section .d-flex.justify-content-between');
    if (bottomNav) bottomNav.style.display = 'none';
    
    const qrSection = document.getElementById('qr-pairing-section');
    qrSection.classList.remove('d-none');
    
    // Generate Token
    currentScanToken = generateRandomToken();
    
    // Get actual LAN IP to ensure cross-device connectivity, then register token
    fetch('api/api_get_lan_ip.php')
      .then(res => res.json())
      .then(ipData => {
         const serverIp = ipData.ip || '192.168.100.125'; // Fallback
         
         const formData = new FormData();
         formData.append('token', currentScanToken);
         formData.append('action', 'create_token');
         
         return fetch('api/api_register_token.php', {
             method: 'POST',
             headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
             body: 'token=' + currentScanToken
         }).then(() => serverIp);
      })
      .then((serverIp) => {
          // Construct Mobile URL strictly using the Backend LAN IP
          const currentPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
          let protocol = window.location.protocol;
          let port = window.location.port ? ':' + window.location.port : '';
          
          let hostDomain = window.location.hostname;
          if (hostDomain === 'localhost' || hostDomain === '127.0.0.1') {
              hostDomain = serverIp;
          }

          const mobileUrl = protocol + '//' + hostDomain + port + currentPath + '/mobile_ktp.php?token=' + currentScanToken;
          console.log("Generating QR for Mobile URL: ", mobileUrl);
          
          // Render QR
          const qrContainer = document.getElementById('qrcode-display');
          qrContainer.innerHTML = ''; // clear old
          new QRCode(qrContainer, {
              text: mobileUrl,
              width: 200,
              height: 200,
              colorDark : "#0d6efd",
              colorLight : "#ffffff",
              correctLevel : QRCode.CorrectLevel.H
          });
          
          // Debugging: Show the actual URL so the user can see it
          const debugUrlContainer = document.getElementById('debug-mobile-url');
          if (!debugUrlContainer) {
              const debugDiv = document.createElement('div');
              debugDiv.id = 'debug-mobile-url';
              debugDiv.className = 'mt-3 mb-2 small text-muted text-break';
              debugDiv.innerHTML = `<strong>URL Scanner:</strong> <a href="${mobileUrl}" target="_blank" class="text-decoration-none">${mobileUrl}</a><br><small class="text-danger">Pastikan HP dan Mac terhubung di WiFi yang sama.</small>`;
              qrContainer.parentNode.appendChild(debugDiv);
          } else {
              debugUrlContainer.innerHTML = `<strong>URL Scanner:</strong> <a href="${mobileUrl}" target="_blank" class="text-decoration-none">${mobileUrl}</a><br><small class="text-danger">Pastikan HP dan Mac terhubung di WiFi yang sama.</small>`;
          }
          
          document.getElementById('qr-status-text').textContent = 'Menunggu proses scan di HP...';
          
          // Begin Polling
          scanPollingInterval = setInterval(pollMobileToken, 3000);
      })
      .catch(err => {
          console.error("Gagal menyiapkan QR Scanner:", err);
          cancelMobileKtpScan();
      });
}

function cancelMobileKtpScan() {
    if (scanPollingInterval) clearInterval(scanPollingInterval);
    currentScanToken = null;
    
    // Restore UI
    document.getElementById('qr-pairing-section').classList.add('d-none');
    
    const mobileTrigger = document.getElementById('mobile-trigger-section');
    if (mobileTrigger) mobileTrigger.style.display = 'block';
    
    const uploadArea = document.getElementById('upload-area');
    if (uploadArea) uploadArea.style.display = 'block';
    
    const bottomNav = document.querySelector('#ocr-input-section .d-flex.justify-content-between');
    if (bottomNav) bottomNav.style.display = 'flex';
}

function pollMobileToken() {
    if (!currentScanToken) return;
    
    fetch('api/api_mobile_poll.php?token=' + currentScanToken)
    .then(r => r.json())
    .then(data => {
        if (data.status === 'completed') {
            clearInterval(scanPollingInterval);
            document.getElementById('qr-status-text').textContent = 'Memproses data KTP...';
            document.getElementById('qr-status-text').className = 'text-success fw-bold';
            
            // Render the photo preview synced from mobile
            const preview = document.getElementById('ktp-preview');
            if (preview) {
                preview.src = data.image_url;
                preview.classList.remove('d-none');
                preview.style.display = 'block';
            }
            
            const formPreview = document.getElementById('form-ktp-preview');
            const formPreviewContainer = document.getElementById('form-ktp-preview-container');
            if (formPreview && formPreviewContainer) {
                formPreview.src = data.image_url;
                formPreviewContainer.classList.remove('d-none');
            }
            
            // Port the server-side temporary file path into the form POST payload
            const hiddenMobileKtpImage = document.getElementById('mobile_ktp_image');
            if (hiddenMobileKtpImage) {
                hiddenMobileKtpImage.value = data.image_url;
            }
            
            // Map the parsed JSON back into the form fields
            fillFormFields({
                nik: data.data.nik,
                nama: data.data.nama,
                tempat_lahir: data.data.tempat_lahir,
                tanggal_lahir: data.data.tanggal_lahir,
                jenis_kelamin: data.data.jenis_kelamin,
                agama: data.data.agama,
                pekerjaan: data.data.pekerjaan,
                kota_kabupaten: data.data.kota_kabupaten,
                alamat_details: data.data.alamat_details
            });
            
            if (data.data.nik) checkNIKRegistration(data.data.nik);
            
            showAlert('Data KTP berhasil ditangkap dari Kamera HP!', 'success');
            setTimeout(() => {
                cancelMobileKtpScan();
                showMainForm();
            }, 1000);
            
        } else if (data.status === 'failed') {
            clearInterval(scanPollingInterval);
            document.getElementById('qr-status-text').textContent = 'Gagal membaca KTP pada HP.';
            document.getElementById('qr-status-text').className = 'text-danger fw-bold';
            setTimeout(cancelMobileKtpScan, 2000);
            showAlert('Gagal membaca KTP. Silakan coba lagi atau foto manual.', 'danger');
        }
    })
    .catch(err => console.error(err));
}

function handleOCRUpload(file) {
    // Validate file
    if (file.size > 5 * 1024 * 1024) {
        showAlert('Ukuran file terlalu besar. Maksimal 5MB.', 'danger');
        return;
    }
    
    if (!file.type.match('image.*') && !file.name.toLowerCase().endsWith('.heic') && !file.name.toLowerCase().endsWith('.heif')) {
        showAlert('File harus berupa gambar (JPG/PNG/HEIC).', 'danger');
        return;
    }
    
    // Show preview area and loading state immediately
    const preview = document.getElementById('ktp-preview');
    if (preview) {
        preview.style.display = 'block';
        preview.src = 'https://media.giphy.com/media/3oEjI6SIIHBdRxXI40/giphy.gif'; // temporary loading image
    }
    document.getElementById('upload-area').style.display = 'none';

    const ocrStatus = document.getElementById('ocr-status');
    const ocrMessage = document.getElementById('ocr-message');
    if (ocrStatus && ocrMessage) {
        ocrStatus.style.display = 'block';
        ocrStatus.classList.add('processing');
        ocrMessage.textContent = 'Menyiapkan gambar...';
    }

        // Process HEIC specifically if detected
    if (file.name.toLowerCase().endsWith('.heic') || file.name.toLowerCase().endsWith('.heif')) {
        if (ocrMessage) ocrMessage.textContent = 'Mengonversi gambar HEIC iPhone...';
        
        heic2any({
            blob: file,
            toType: "image/jpeg",
            quality: 0.8
        }).then(function(conversionResult) {
            // result is a Blob
            let convertedBlob;
            if (Array.isArray(conversionResult)) {
                convertedBlob = conversionResult[0];
            } else {
                convertedBlob = conversionResult;
            }
            
            const newFile = new File([convertedBlob], file.name.split('.')[0] + ".jpg", {
                type: "image/jpeg",
                lastModified: new Date().getTime()
            });
            
            // Show real preview on both screens
            const reader = new FileReader();
            reader.onload = function(e) {
                if (preview) {
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                }
                const formPreview = document.getElementById('form-ktp-preview');
                const formPreviewContainer = document.getElementById('form-ktp-preview-container');
                if (formPreview && formPreviewContainer) {
                    formPreview.src = e.target.result;
                    formPreviewContainer.classList.remove('d-none');
                }
            };
            reader.readAsDataURL(newFile);
            
            processOCR(newFile);
        }).catch(function(e) {
            console.error(e);
            showAlert('Gagal mengonversi file HEIC.', 'danger');
            cancelInputMethod();
        });
    } else {
        // Show real preview on both screens for standard JPG/PNG
        const reader = new FileReader();
        reader.onload = function(e) {
            if (preview) {
                preview.src = e.target.result;
                preview.classList.remove('d-none');
            }
            const formPreview = document.getElementById('form-ktp-preview');
            const formPreviewContainer = document.getElementById('form-ktp-preview-container');
            if (formPreview && formPreviewContainer) {
                formPreview.src = e.target.result;
                formPreviewContainer.classList.remove('d-none');
            }
        };
        reader.readAsDataURL(file);
        
        processOCR(file);
    }
}

function processOCR(file) {
    const ocrStatus = document.getElementById('ocr-status');
    const ocrMessage = document.getElementById('ocr-message');
    
    if (ocrStatus && ocrMessage) {
        ocrStatus.style.display = 'block';
        ocrStatus.classList.add('processing');
        ocrMessage.textContent = 'Memproses gambar KTP...';
    }
    
    const formData = new FormData();
    formData.append('identitas', file);
    
    fetch('services/ocr_vision.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (ocrStatus) {
            ocrStatus.style.display = 'none';
            ocrStatus.classList.remove('processing');
        }
        
        if (data.success) {
            fillFormFields(data.data);
            if (data.data && data.data.nik) {
                checkNIKRegistration(data.data.nik);
            }
            showAlert('Data berhasil diekstrak dari KTP', 'success');
        } else {
            showAlert(data.message || 'Gagal membaca KTP. Silakan isi manual.', 'warning');
        }
        
        showMainForm();
    })
    .catch(error => {
        console.error('OCR Error:', error);
        
        if (ocrStatus) {
            ocrStatus.style.display = 'none';
            ocrStatus.classList.remove('processing');
        }
        
        showAlert('Terjadi kesalahan saat memproses KTP.', 'danger');
        showMainForm();
    });
}

function fillFormFields(data) {
    const setFieldValue = (id, value) => {
        const field = document.getElementById(id);
        if (field && value) {
            field.value = value;
            field.classList.add('is-valid');
        }
    };
    
    setFieldValue('nik', data.nik);
    setFieldValue('nama_tamu', data.nama);
    setFieldValue('tempat_lahir', data.tempat_lahir);
    setFieldValue('tanggal_lahir', data.tanggal_lahir);
    setFieldValue('jenis_kelamin', data.jenis_kelamin);
    setFieldValue('agama', data.agama);
    setFieldValue('pekerjaan', data.pekerjaan);
    setFieldValue('kota_kabupaten', data.kota_kabupaten);
    
    if (data.alamat_details) {
        setFieldValue('alamat', data.alamat_details.alamat);
        setFieldValue('rt_rw', data.alamat_details.rt_rw);
        setFieldValue('kel_desa', data.alamat_details.kel_desa);
        setFieldValue('kecamatan', data.alamat_details.kecamatan);
    }
}

// ========== HELPER FUNCTIONS ==========
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.card-body');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);
    }
    
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => alertDiv.remove(), 150);
    }, 5000);
}

function updateProgress(stepNumber) {
    const steps = document.querySelectorAll('.step');
    steps.forEach((step, index) => {
        if (index < stepNumber) {
            step.classList.add('completed');
            step.classList.remove('active');
        } else if (index === stepNumber - 1) {
            step.classList.add('active');
        } else {
            step.classList.remove('active', 'completed');
        }
    });
}

function setDefaultDates() {
    const checkInDate = document.getElementById('tanggal_check_in');
    const checkOutDate = document.getElementById('tanggal_check_out');
    
    if (checkInDate && checkOutDate) {
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        checkInDate.value = formatDate(today);
        checkInDate.min = formatDate(today);
        
        checkOutDate.value = formatDate(tomorrow);
        checkOutDate.min = formatDate(tomorrow);
    }
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const day = date.getDate().toString().padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

// ========== FORM HANDLERS ==========
function toggleInstansi(value) {
    const instansiField = document.getElementById('instansi-field');
    const namaInstansi = document.getElementById('nama_instansi');
    
    if (value === 'Instansi' || value === 'Perusahaan') {
        instansiField.style.display = 'block';
        namaInstansi.required = true;
    } else {
        instansiField.style.display = 'none';
        namaInstansi.required = false;
        namaInstansi.value = '';
    }
}

// ========== INITIALIZATION ==========
document.addEventListener('DOMContentLoaded', function() {
    console.log('Form Check-in with RFID initialized');
    
    // Try to connect RFID
    connectRFID();
    
    // Date validation
    const checkInDate = document.getElementById('tanggal_check_in');
    const checkOutDate = document.getElementById('tanggal_check_out');
    
    if (checkInDate) {
        checkInDate.addEventListener('change', function() {
            if (checkOutDate) {
                const checkIn = new Date(this.value);
                const minCheckOut = new Date(checkIn);
                minCheckOut.setDate(minCheckOut.getDate() + 1);
                checkOutDate.min = formatDate(minCheckOut);
                
                if (new Date(checkOutDate.value) <= checkIn) {
                    checkOutDate.value = formatDate(minCheckOut);
                }
                
                updateSummary();
                updateProgress(3);
            }
        });
    }
    
    if (checkOutDate) {
        checkOutDate.addEventListener('change', updateSummary);
    }
    
    // NIK Registration Check
    const nikInput = document.getElementById('nik');
    if (nikInput) {
        let nikTimeout = null;
        nikInput.addEventListener('input', function() {
            clearTimeout(nikTimeout);
            const val = this.value;
            nikTimeout = setTimeout(() => {
                if (val.replace(/\D/g, '').length === 16) {
                    checkNIKRegistration(val);
                }
            }, 800); // Debounce 800ms
        });
        nikInput.addEventListener('blur', function() {
            if (this.value.replace(/\D/g, '').length === 16) {
                checkNIKRegistration(this.value);
            }
        });
    }
    
    // Price calculation
    const hargaKamar = document.getElementById('harga_kamar');
    const hargaKelas = document.getElementById('harga_kelas');
    
    if (hargaKamar) hargaKamar.addEventListener('input', updateSummary);
    if (hargaKelas) hargaKelas.addEventListener('input', updateSummary);
    
    function updateSummary() {
        if (checkInDate && checkOutDate) {
            const checkIn = new Date(checkInDate.value);
            const checkOut = new Date(checkOutDate.value);
            
            if (checkIn && checkOut && checkOut > checkIn) {
                const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
                document.getElementById('summary-nights').textContent = `${nights} malam`;
                
                const roomPrice = parseInt(hargaKamar?.value) || 0;
                const classPrice = parseInt(hargaKelas?.value) || 0;
                const roomTotal = roomPrice * nights;
                const total = roomTotal + classPrice;
                
                document.getElementById('summary-room-price').textContent = formatCurrency(roomTotal);
                document.getElementById('summary-class-price').textContent = formatCurrency(classPrice);
                document.getElementById('summary-total').textContent = formatCurrency(total);
            }
        }
    }
    
    // Payment preview
    const uploadPembayaran = document.getElementById('upload_pembayaran');
    if (uploadPembayaran) {
        uploadPembayaran.addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                const preview = document.getElementById('payment-preview');
                
                // Cek apakah file adalah HEIC/HEIF
                if (file.name.toLowerCase().endsWith('.heic') || file.name.toLowerCase().endsWith('.heif')) {
                    // Tampilkan indikator loading pada gambar
                    if (preview) {
                        preview.src = 'https://media.giphy.com/media/3oEjI6SIIHBdRxXI40/giphy.gif';
                        preview.classList.remove('d-none');
                    }
                    
                    // Gunakan library heic2any yang sudah dimuat sebelumnya
                    heic2any({
                        blob: file,
                        toType: "image/jpeg",
                        quality: 0.8
                    }).then(function(conversionResult) {
                        let convertedBlob = Array.isArray(conversionResult) ? conversionResult[0] : conversionResult;
                        
                        // Buat objek File baru
                        const newFile = new File([convertedBlob], file.name.split('.')[0] + ".jpg", {
                            type: "image/jpeg",
                            lastModified: new Date().getTime()
                        });
                        
                        // Timpa input.files dengan DataTransfer
                        const dataTransfer = new DataTransfer();
                        dataTransfer.items.add(newFile);
                        uploadPembayaran.files = dataTransfer.files;
                        
                        // Tampilkan preview aslinya
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (preview) preview.src = e.target.result;
                        };
                        reader.readAsDataURL(newFile);
                        updateProgress(4);
                        
                    }).catch(function(e) {
                        console.error("Gagal convert HEIC Bukti Pembayaran:", e);
                        showAlert('Gagal mengonversi foto HEIC Bukti Pembayaran.', 'danger');
                        if (preview) preview.classList.add('d-none');
                    });
                } else {
                    // Logika preview standar untuk JPG/PNG
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (preview) {
                            preview.src = e.target.result;
                            preview.classList.remove('d-none');
                        }
                    };
                    reader.readAsDataURL(file);
                    updateProgress(4);
                }
            }
        });
    }
    
    // Form submission
    const checkinForm = document.getElementById('checkin-form');
    if (checkinForm) {
        checkinForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate required fields
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value || field.value.trim() === '') {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                showAlert('Harap lengkapi semua field yang wajib diisi.', 'danger');
                return;
            }
            
            // Hard block if AJAX overlap checker locked the button
            const submitBtn = document.getElementById('submit-button');
            if (submitBtn && submitBtn.disabled) {
                showAlert('Terdapat jadwal yang bertabrakan. Harap ganti jadwal, kamar, atau kelas sebelum menyimpan.', 'danger');
                return;
            }
            
            // Helper for final submission
            const processFormSubmission = (form) => {
                const uid = document.getElementById('rfid_uid').value;
                const nik = document.getElementById('nik').value;
                if (uid && nik) {
                    console.log('Saving RFID mapping:', uid, nik);
                }
                
                const submitButton = document.getElementById('submit-button');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';
                }
                form.submit();
            };

            // STRICT COMPANY POLICY: Manual input MUST have KTP Photo
            const inputMethodField = document.getElementById('input_method');
            const uploadIdentitas = document.getElementById('upload_identitas');
            
            if (inputMethodField && inputMethodField.value === 'manual') {
                if (!uploadIdentitas.files || uploadIdentitas.files.length === 0) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Peringatan: Wajib Upload KTP',
                            html: '<p>Sesuai kebijakan perusahaan, pengisian data tamu secara manual <b>wajib</b> menyertakan file foto identitas (KTP).</p> ' +
                                  '<p class="small text-muted mb-0">Silakan pilih file foto KTP tamu pada kotak di bawah ini untuk melanjutkan penyimpanan.</p>',
                            input: 'file',
                            inputAttributes: {
                                'accept': 'image/*,.heic,.heif',
                                'aria-label': 'Upload foto identitas'
                            },
                            showCancelButton: true,
                            confirmButtonText: '<i class="bi bi-cloud-upload me-2"></i> Upload & Simpan Data',
                            cancelButtonText: 'Kembali',
                            confirmButtonColor: '#0d6efd',
                            cancelButtonColor: '#6c757d',
                            allowOutsideClick: false
                        }).then((result) => {
                            if (result.value) {
                                // Dynamically assign uploaded file to hidden form input
                                const dataTransfer = new DataTransfer();
                                dataTransfer.items.add(result.value);
                                uploadIdentitas.files = dataTransfer.files;
                                
                                // Proceed with final form submit
                                processFormSubmission(this);
                            }
                        });
                    } else {
                        alert('Kebijakan perusahaan mewajibkan foto KTP dilampirkan. Mohon kembali ke menu KTP.');
                    }
                    return; // Halts the current submission
                }
            }
            
            // Standard submission if requirements are met
            processFormSubmission(this);
        });
    }
    
    // Input validations
    const nikValidationField = document.getElementById('nik');
    if (nikValidationField) {
        nikValidationField.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 16) value = value.substring(0, 16);
            e.target.value = value;
            
            if (value.length === 16) {
                e.target.classList.remove('is-invalid');
                e.target.classList.add('is-valid');
            } else if (value.length > 0) {
                e.target.classList.remove('is-valid');
                e.target.classList.add('is-invalid');
            }
        });
    }
    
    const noHpInput = document.getElementById('no_hp');
    if (noHpInput) {
        noHpInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0 && !value.startsWith('0')) {
                value = '0' + value;
            }
            if (value.length > 13) value = value.substring(0, 13);
            e.target.value = value;
            if (value.length >= 10 && value.length <= 13) {
                e.target.classList.remove('is-invalid');
                e.target.classList.add('is-valid');
            } else if (value.length > 0) {
                e.target.classList.remove('is-valid');
                e.target.classList.add('is-invalid');
            }
        });
    }
    
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailRegex.test(this.value)) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            } else if (this.value.trim() !== '') {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
            }
        });
    }
});

// ========== CLEANUP ON PAGE UNLOAD ==========
window.addEventListener('beforeunload', function() {
    if (rfidSocket) {
        rfidSocket.close();
    }
    if (rfidReconnectInterval) {
        clearInterval(rfidReconnectInterval);
    }
});
</script>

<?php include 'UI/footer.php'; ?>

</body>
</html>