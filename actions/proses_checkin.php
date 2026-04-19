<?php
// proses_checkin.php - Enhanced version with RFID mapping support
session_start();
include __DIR__ . '/../config/koneksi.php';

// ========== HELPER FUNCTIONS ==========
function handleUpload($fileInputName, $uploadDir = '../uploads/') {
    try {
        // Create upload directory if not exists
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('Gagal membuat direktori upload');
            }
        }
        
        // For RFID method, file upload is optional
        if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
            // Check if this is RFID method
            if (isset($_POST['input_method']) && $_POST['input_method'] === 'rfid') {
                return 'rfid_scan'; // Special marker for RFID scan
            }
            return null;
        }
        
        // Check if file was uploaded with error
        if ($_FILES[$fileInputName]['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = 'File tidak ditemukan atau error saat upload';
            
            switch ($_FILES[$fileInputName]['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMessage = 'Ukuran file terlalu besar';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMessage = 'File hanya terupload sebagian';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMessage = 'Folder temporary tidak ditemukan';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMessage = 'Gagal menulis file ke disk';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errorMessage = 'Upload file dibatalkan oleh ekstensi PHP';
                    break;
            }
            
            throw new Exception($errorMessage);
        }
        
        $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
        $fileName = $_FILES[$fileInputName]['name'];
        $fileSize = $_FILES[$fileInputName]['size'];
        
        // Validate file size (max 5MB)
        if ($fileSize > 5 * 1024 * 1024) {
            throw new Exception('Ukuran file terlalu besar. Maksimal 5MB.');
        }
        
        // Get file extension
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Validate file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'heic', 'heif'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Format file tidak diizinkan. Hanya JPG, PNG, GIF, HEIC, atau PDF.');
        }
        
        // Validate MIME type
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileTmpPath);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimeTypes)) {
            throw new Exception('Tipe file tidak valid: ' . $mimeType);
        }
        
        // Generate unique filename
        $timestamp = time();
        $uniqueId = uniqid();
        $newFileName = $timestamp . '_' . $uniqueId . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '', $fileName);
        $destPath = $uploadDir . $newFileName;
        
        // Move uploaded file
        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            throw new Exception('Gagal memindahkan file upload.');
        }
        
        return $destPath;
        
    } catch (Exception $e) {
        error_log('Upload error for ' . $fileInputName . ': ' . $e->getMessage());
        return null;
    }
}

function validateNIK($nik) {
    // NIK harus 16 digit
    if (!preg_match('/^\d{16}$/', $nik)) {
        return false;
    }
    
    // Validate province code (2 digits)
    $province = substr($nik, 0, 2);
    if ($province < 11 || $province > 94) {
        return false;
    }
    
    // Validate date components
    $day = (int)substr($nik, 6, 2);
    $month = (int)substr($nik, 8, 2);
    $year = (int)substr($nik, 10, 2);
    
    // For females, day is +40
    if ($day > 40) {
        $day -= 40;
    }
    
    // Basic date validation
    if ($day < 1 || $day > 31) return false;
    if ($month < 1 || $month > 12) return false;
    
    return true;
}

function validatePhoneNumber($phone) {
    // Remove non-numeric characters
    $phone = preg_replace('/\D/', '', $phone);
    
    // Check if starts with 0 and has 10-13 digits
    if (preg_match('/^0\d{9,12}$/', $phone)) {
        return $phone;
    }
    
    // If starts with 62 (Indonesia country code), convert to 0
    if (preg_match('/^62\d{9,12}$/', $phone)) {
        return '0' . substr($phone, 2);
    }
    
    return false;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizeInput($input) {
    if ($input === null) return null;
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

function saveRFIDMapping($conn, $uid, $data) {
    // Create table if not exists
    $createTableSQL = "
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $conn->query($createTableSQL);
    
    // Check if UID exists
    $checkStmt = $conn->prepare("SELECT id, usage_count FROM rfid_mapping WHERE uid = ?");
    $checkStmt->bind_param("s", $uid);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing record
        $existing = $checkResult->fetch_assoc();
        $newCount = $existing['usage_count'] + 1;
        
        $updateStmt = $conn->prepare("
            UPDATE rfid_mapping SET 
                nik = ?, nama = ?, tempat_lahir = ?, tanggal_lahir = ?,
                jenis_kelamin = ?, alamat = ?, rt_rw = ?, kel_desa = ?,
                kecamatan = ?, kota_kabupaten = ?, agama = ?, pekerjaan = ?,
                no_hp = ?, email = ?, last_used = NOW(), usage_count = ?
            WHERE uid = ?
        ");
        
        $updateStmt->bind_param("sssssssssssssssi", 
            $data['nik'], $data['nama'], $data['tempat_lahir'], $data['tanggal_lahir'],
            $data['jenis_kelamin'], $data['alamat'], $data['rt_rw'], $data['kel_desa'],
            $data['kecamatan'], $data['kota_kabupaten'], $data['agama'], $data['pekerjaan'],
            $data['no_hp'], $data['email'], $newCount, $uid
        );
        
        $success = $updateStmt->execute();
        $updateStmt->close();
        
        if ($success) {
            error_log("RFID mapping updated for UID: $uid (usage #$newCount)");
        }
        
    } else {
        // Insert new record
        $insertStmt = $conn->prepare("
            INSERT INTO rfid_mapping 
            (uid, nik, nama, tempat_lahir, tanggal_lahir, jenis_kelamin, 
             alamat, rt_rw, kel_desa, kecamatan, kota_kabupaten, 
             agama, pekerjaan, no_hp, email, last_used) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $insertStmt->bind_param("sssssssssssssss", 
            $uid, $data['nik'], $data['nama'], $data['tempat_lahir'], $data['tanggal_lahir'],
            $data['jenis_kelamin'], $data['alamat'], $data['rt_rw'], $data['kel_desa'],
            $data['kecamatan'], $data['kota_kabupaten'], $data['agama'], $data['pekerjaan'],
            $data['no_hp'], $data['email']
        );
        
        $success = $insertStmt->execute();
        $insertStmt->close();
        
        if ($success) {
            error_log("New RFID mapping created for UID: $uid");
        }
    }
    
    $checkStmt->close();
    return $success;
}

// ========== MAIN PROCESSING ==========
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Initialize response
    $response = [
        'success' => false,
        'message' => '',
        'errors' => [],
        'data' => []
    ];
    
    try {
        // Start transaction
        $conn->autocommit(FALSE);
        
        // ========== COLLECT AND VALIDATE DATA ==========
        
        // Check input method
        $input_method = sanitizeInput($_POST['input_method'] ?? 'manual');
        $rfid_uid = sanitizeInput($_POST['rfid_uid'] ?? '');
        
        // Basic Information
        $nik = sanitizeInput($_POST['nik'] ?? null);
        $nama_tamu = sanitizeInput($_POST['nama_tamu'] ?? null);
        $tempat_lahir = sanitizeInput($_POST['tempat_lahir'] ?? null);
        $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;
        $jenis_kelamin = sanitizeInput($_POST['jenis_kelamin'] ?? null);
        $no_hp = sanitizeInput($_POST['no_hp'] ?? null);
        $email = sanitizeInput($_POST['email'] ?? null);
        $akun_google = sanitizeInput($_POST['akun_google'] ?? null);
        
        // Address Information
        $alamat = sanitizeInput($_POST['alamat'] ?? null);
        $rt_rw = sanitizeInput($_POST['rt_rw'] ?? null);
        $kel_desa = sanitizeInput($_POST['kel_desa'] ?? null);
        $kecamatan = sanitizeInput($_POST['kecamatan'] ?? null);
        $kota_kabupaten = sanitizeInput($_POST['kota_kabupaten'] ?? null);
        
        // Additional Information
        $agama = sanitizeInput($_POST['agama'] ?? null);
        $pekerjaan = sanitizeInput($_POST['pekerjaan'] ?? null);
        
        // Guest Type Information
        $tipe_tamu = sanitizeInput($_POST['tipe_tamu'] ?? null);
        $nama_instansi = sanitizeInput($_POST['nama_instansi'] ?? null);
        
        // Booking Information
        $booking_check_in = sanitizeInput($_POST['booking_check_in'] ?? null);
        $tanggal_check_in = $_POST['tanggal_check_in'] ?? null;
        $tanggal_check_out = $_POST['tanggal_check_out'] ?? null;
        $nama_kamar = sanitizeInput($_POST['nama_kamar'] ?? null);
        $harga_kamar = isset($_POST['harga_kamar']) ? (int)$_POST['harga_kamar'] : 0;
        
        // Event/Class Information
        $acara_judul_diklat = sanitizeInput($_POST['acara_judul_diklat'] ?? null);
        $nama_kelas = sanitizeInput($_POST['nama_kelas'] ?? null);
        $harga_kelas = isset($_POST['harga_kelas']) ? (int)$_POST['harga_kelas'] : 0;
        
        // ========== VALIDATION ==========
        $errors = [];
        
        // Validate required fields
        if (empty($nama_tamu)) {
            $errors[] = 'Nama tamu harus diisi';
        }
        
        if (empty($no_hp)) {
            $errors[] = 'Nomor HP harus diisi';
        } else {
            $validatedPhone = validatePhoneNumber($no_hp);
            if (!$validatedPhone) {
                $errors[] = 'Format nomor HP tidak valid';
            } else {
                $no_hp = $validatedPhone;
            }
        }
        
        if (empty($email)) {
            $errors[] = 'Email harus diisi';
        } else if (!validateEmail($email)) {
            $errors[] = 'Format email tidak valid';
        }
        
        // Validate NIK if provided
        if (!empty($nik) && !validateNIK($nik)) {
            $errors[] = 'Format NIK tidak valid (harus 16 digit)';
        }
        
        // Validate dates
        if (empty($tanggal_check_in)) {
            $errors[] = 'Tanggal check-in harus diisi';
        }
        
        if (empty($tanggal_check_out)) {
            $errors[] = 'Tanggal check-out harus diisi';
        }
        
        if (!empty($tanggal_check_in) && !empty($tanggal_check_out)) {
            $checkIn = strtotime($tanggal_check_in);
            $checkOut = strtotime($tanggal_check_out);
            
            if ($checkOut <= $checkIn) {
                $errors[] = 'Tanggal check-out harus setelah tanggal check-in';
            }
            
            // Calculate number of nights
            $nights = ceil(($checkOut - $checkIn) / (60 * 60 * 24));
            $total_harga_kamar = $harga_kamar * $nights;
        } else {
            $nights = 0;
            $total_harga_kamar = 0;
        }
        
        // Validate room selection
        if (empty($nama_kamar)) {
            $errors[] = 'Nama kamar harus dipilih';
        }
        
        // Validate guest type
        if (empty($tipe_tamu)) {
            $errors[] = 'Tipe tamu harus dipilih';
        }
        
        // If guest type is Instansi or Perusahaan, nama_instansi is required
        if (($tipe_tamu === 'Instansi' || $tipe_tamu === 'Perusahaan') && empty($nama_instansi)) {
            $errors[] = 'Nama instansi/perusahaan harus diisi untuk tipe tamu ' . $tipe_tamu;
        }
        
        // Validate birth date format if provided
        if (!empty($tanggal_lahir)) {
            $birthDate = DateTime::createFromFormat('Y-m-d', $tanggal_lahir);
            if (!$birthDate) {
                $errors[] = 'Format tanggal lahir tidak valid';
            } else {
                // Check if birth date is reasonable (not in future, not too old)
                $now = new DateTime();
                $age = $now->diff($birthDate)->y;
                
                if ($birthDate > $now) {
                    $errors[] = 'Tanggal lahir tidak boleh di masa depan';
                } else if ($age > 150) {
                    $errors[] = 'Tanggal lahir tidak valid';
                }
            }
        }
        
        // If there are validation errors, throw exception
        if (!empty($errors)) {
            throw new Exception('Validasi gagal: ' . implode(', ', $errors));
        }
        
        // ========== HANDLE FILE UPLOADS ==========
        $path_identitas = null;
        $path_pembayaran = null;
        
        // Handle identity upload - optional for RFID
        if ($input_method === 'rfid' && !empty($rfid_uid)) {
            // For RFID, identity upload is optional
            $path_identitas = 'rfid_scan';
        } else {
            // 1. Try Standard File Upload from Desktop
            $path_identitas = handleUpload('upload_identitas');
            
            // 2. If Standard Upload is empty, check if it's a Mobile KTP Scan payload
            if (!$path_identitas && !empty($_POST['mobile_ktp_image'])) {
                $tempPath = $_POST['mobile_ktp_image'];
                if (file_exists($tempPath)) {
                    // Mobile image already physically exists in uploads/ktp/temp/
                    // We can just link it directly or rename it. We'll link it directly.
                    $path_identitas = sanitizeInput($tempPath);
                }
            }
            
            if (!$path_identitas && $input_method !== 'rfid') {
                // Not throwing error because upload_identitas is non-mandatory in UI.
                
                // Mari perbaiki: Pembayaran *wajib*, identitas *tidak*.
                // Di form_checkin.php, 'upload_identitas' *tidak* 'required'.
                // Tapi 'upload_pembayaran' *adalah* 'required'.
                
                // Cek ulang form_checkin.php
                // 'upload_identitas' (line 424) -> tidak ada 'required'
                // 'upload_pembayaran' (line 1037) -> ada 'required'
                // Logika JS (line 1178) juga mengecek 'required'.
                
                // Jadi, kita hanya perlu throw error jika 'upload_pembayaran' gagal.
                // 'upload_identitas' tidak masalah jika null.
                
                // Kode handleUpload sudah benar, dia return null jika tidak ada file
                // atau jika ada error.
            }
        }
        
        // Handle payment upload
        $path_pembayaran = handleUpload('upload_pembayaran');
        if (!$path_pembayaran) {
            // File pembayaran wajib
            throw new Exception('Gagal upload bukti pembayaran. Pastikan file diupload, berformat JPG/PNG dan ukuran maksimal 5MB.');
        }
        
        // ========== CHECK FOR OVERLAPS & DUPLICATES ==========
        
        // 1. Check if user already has an overlapping booking
        if (!empty($nik) && !empty($nama_tamu)) {
            $stmtUserCheck = $conn->prepare("
                SELECT id FROM data_tamu 
                WHERE nik = ? AND nama_tamu = ? 
                AND status != 'cancelled'
                AND tanggal_check_in < ? 
                AND tanggal_check_out > ? 
                LIMIT 1
            ");
            if ($stmtUserCheck) {
                $stmtUserCheck->bind_param("ssss", $nik, $nama_tamu, $tanggal_check_out, $tanggal_check_in);
                $stmtUserCheck->execute();
                if ($stmtUserCheck->get_result()->num_rows > 0) {
                    throw new Exception("Tamu tersebut sudah memiliki jadwal di waktu yang sama.");
                }
                $stmtUserCheck->close();
            }
        }
        
        // 2. Check Room Overlap
        if (!empty($nama_kamar)) {
            $stmtRoom = $conn->prepare("
                SELECT nama_tamu FROM data_tamu 
                WHERE nama_kamar = ? 
                AND status != 'cancelled'
                AND tanggal_check_in < ? 
                AND tanggal_check_out > ? 
                LIMIT 1
            ");
            if ($stmtRoom) {
                $stmtRoom->bind_param("sss", $nama_kamar, $tanggal_check_out, $tanggal_check_in);
                $stmtRoom->execute();
                $resRoom = $stmtRoom->get_result();
                if ($resRoom->num_rows > 0) {
                    $row = $resRoom->fetch_assoc();
                    $occupant = htmlspecialchars($row['nama_tamu']);
                    throw new Exception("Kamar sedang digunakan oleh {$occupant} pada jadwal tersebut.");
                }
                $stmtRoom->close();
            }
        }
        
        // 3. Check Class Overlap
        if (!empty($nama_kelas)) {
            $stmtClass = $conn->prepare("
                SELECT nama_tamu FROM data_tamu 
                WHERE nama_kelas = ? 
                AND status != 'cancelled'
                AND tanggal_check_in < ? 
                AND tanggal_check_out > ? 
                LIMIT 1
            ");
            if ($stmtClass) {
                $stmtClass->bind_param("sss", $nama_kelas, $tanggal_check_out, $tanggal_check_in);
                $stmtClass->execute();
                $resClass = $stmtClass->get_result();
                if ($resClass->num_rows > 0) {
                    $row = $resClass->fetch_assoc();
                    $occupant = htmlspecialchars($row['nama_tamu']);
                    throw new Exception("Kelas sedang digunakan oleh {$occupant} pada jadwal tersebut.");
                }
                $stmtClass->close();
            }
        }
        
        // ========== CALCULATE TOTAL AMOUNT ==========
        $total_amount = $total_harga_kamar + $harga_kelas;
        
        // ========== INSERT DATA ==========
        // Check if all columns exist in table (for compatibility)
        $checkColumns = $conn->query("SHOW COLUMNS FROM data_tamu");
        $columns = [];
        while ($col = $checkColumns->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        
        // Build dynamic insert query based on available columns
        $insertColumns = [];
        $insertValues = [];
        $insertTypes = '';
        $bindParams = [];
        
        // Map of field names to values and types
        $fieldMap = [
            'timestamp' => ['value' => 'NOW()', 'type' => null, 'bind' => false],
            'nik' => ['value' => $nik, 'type' => 's'],
            'nama_tamu' => ['value' => $nama_tamu, 'type' => 's'],
            'tempat_lahir' => ['value' => $tempat_lahir, 'type' => 's'],
            'tanggal_lahir' => ['value' => $tanggal_lahir, 'type' => 's'],
            'jenis_kelamin' => ['value' => $jenis_kelamin, 'type' => 's'],
            'no_hp' => ['value' => $no_hp, 'type' => 's'],
            'email' => ['value' => $email, 'type' => 's'],
            'akun_google' => ['value' => $akun_google, 'type' => 's'],
            'alamat' => ['value' => $alamat, 'type' => 's'],
            'rt_rw' => ['value' => $rt_rw, 'type' => 's'],
            'kel_desa' => ['value' => $kel_desa, 'type' => 's'],
            'kecamatan' => ['value' => $kecamatan, 'type' => 's'],
            'kota_kabupaten' => ['value' => $kota_kabupaten, 'type' => 's'],
            'agama' => ['value' => $agama, 'type' => 's'],
            'pekerjaan' => ['value' => $pekerjaan, 'type' => 's'],
            'tipe_tamu' => ['value' => $tipe_tamu, 'type' => 's'],
            'nama_instansi' => ['value' => $nama_instansi, 'type' => 's'],
            'booking_check_in' => ['value' => $booking_check_in, 'type' => 's'],
            'tanggal_check_in' => ['value' => $tanggal_check_in, 'type' => 's'],
            'tanggal_check_out' => ['value' => $tanggal_check_out, 'type' => 's'],
            'nama_kamar' => ['value' => $nama_kamar, 'type' => 's'],
            'harga_kamar' => ['value' => $harga_kamar, 'type' => 'i'],
            'acara_judul_diklat' => ['value' => $acara_judul_diklat, 'type' => 's'],
            'nama_kelas' => ['value' => $nama_kelas, 'type' => 's'],
            'harga_kelas' => ['value' => $harga_kelas, 'type' => 'i'],
            'upload_identitas' => ['value' => $path_identitas, 'type' => 's'],
            'upload_pembayaran' => ['value' => $path_pembayaran, 'type' => 's'],
            'total_amount' => ['value' => $total_amount, 'type' => 'i'],
            'status' => ['value' => 'active', 'type' => 's'],
            'input_method' => ['value' => $input_method, 'type' => 's'],
            'rfid_uid' => ['value' => $rfid_uid, 'type' => 's']
        ];
        
        // Check if input_method and rfid_uid columns exist, if not create them
        if (!in_array('input_method', $columns)) {
            $conn->query("ALTER TABLE data_tamu ADD COLUMN input_method VARCHAR(20) DEFAULT 'manual'");
            $columns[] = 'input_method';
        }
        
        if (!in_array('rfid_uid', $columns)) {
            $conn->query("ALTER TABLE data_tamu ADD COLUMN rfid_uid VARCHAR(20) NULL");
            $conn->query("ALTER TABLE data_tamu ADD INDEX idx_rfid_uid (rfid_uid)");
            $columns[] = 'rfid_uid';
        }
        
        foreach ($fieldMap as $column => $data) {
            if (in_array($column, $columns)) {
                $insertColumns[] = $column;
                if (isset($data['bind']) && $data['bind'] === false) {
                    $insertValues[] = $data['value'];
                } else {
                    $insertValues[] = '?';
                    $insertTypes .= $data['type'];
                    $bindParams[] = &$fieldMap[$column]['value'];
                }
            }
        }
        
        $sql = "INSERT INTO data_tamu (" . implode(', ', $insertColumns) . ") 
                VALUES (" . implode(', ', $insertValues) . ")";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
        
        // Bind parameters dynamically
        if (!empty($bindParams)) {
            array_unshift($bindParams, $insertTypes);
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Error executing statement: " . $stmt->error);
        }
        
        // Get the inserted ID
        $insertedId = $conn->insert_id;
        
        // ========== SAVE RFID MAPPING ==========
        if (!empty($rfid_uid)) {
            $rfidData = [
                'nik' => $nik,
                'nama' => $nama_tamu,
                'tempat_lahir' => $tempat_lahir,
                'tanggal_lahir' => $tanggal_lahir,
                'jenis_kelamin' => $jenis_kelamin,
                'alamat' => $alamat,
                'rt_rw' => $rt_rw,
                'kel_desa' => $kel_desa,
                'kecamatan' => $kecamatan,
                'kota_kabupaten' => $kota_kabupaten,
                'agama' => $agama,
                'pekerjaan' => $pekerjaan,
                'no_hp' => $no_hp,
                'email' => $email
            ];
            
            saveRFIDMapping($conn, $rfid_uid, $rfidData);
        }
        
        // ========== UPDATE ROOM STATUS (if needed) ==========
        // Check if data_asset table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'data_asset'");
        if ($tableCheck->num_rows > 0) {
            $updateRoom = $conn->prepare("
                UPDATE data_asset 
                SET status = 'occupied'
                WHERE id_name = ? 
                AND category = 'GEDUNG MESS'
            ");
            
            if ($updateRoom) {
                $updateRoom->bind_param("s", $nama_kamar);
                $updateRoom->execute();
                $updateRoom->close();
            }
        }
        
        // ========== LOG ACTIVITY ==========
        $logTableCheck = $conn->query("SHOW TABLES LIKE 'activity_log'");
        if ($logTableCheck->num_rows > 0) {
            $logSql = "INSERT INTO activity_log (timestamp, user_action, details) VALUES (NOW(), 'CHECK_IN', ?)";
            $logDetails = "Check-in tamu: $nama_tamu, Kamar: $nama_kamar, Check-in: $tanggal_check_in";
            if (!empty($rfid_uid)) {
                $logDetails .= ", RFID: $rfid_uid";
            }
            $logDetails .= ", Method: $input_method";
            
            $logStmt = $conn->prepare($logSql);
            if ($logStmt) {
                $logStmt->bind_param("s", $logDetails);
                $logStmt->execute();
                $logStmt->close();
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Close statement
        $stmt->close();
        
        // Prepare success response
        $response['success'] = true;
        $response['message'] = 'Check-in berhasil!';
        $response['data'] = [
            'id' => $insertedId,
            'nama_tamu' => $nama_tamu,
            'nama_kamar' => $nama_kamar,
            'tanggal_check_in' => $tanggal_check_in,
            'tanggal_check_out' => $tanggal_check_out,
            'total_amount' => $total_amount,
            'nights' => $nights,
            'input_method' => $input_method,
            'rfid_uid' => $rfid_uid
        ];
        
        // Log success
        error_log("Check-in success: ID=$insertedId, Method=$input_method, RFID=$rfid_uid");
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        // Log error
        error_log('Check-in error: ' . $e->getMessage());
        
        $response['success'] = false;
        $response['message'] = $e->getMessage();
        $response['errors'] = $errors;
    }
    
    // Close connection
    $conn->close();
    
    // ========== OUTPUT RESPONSE ==========
    
    // **AWAL PERBAIKAN**
    // Simpan pesan respon ke dalam session
    if ($response['success']) {
        $_SESSION['flash_message'] = [
            'type' => 'success',
            'message' => $response['message']
        ];
        
        // Bersihkan session data form lama jika berhasil
        unset($_SESSION['form_old']);
    } else {
        $_SESSION['flash_message'] = [
            'type' => 'danger',
            'message' => 'Gagal check-in: ' . $response['message']
        ];
        
        // Simpan input user saat gagal agar tidak capek mengetik ulang
        $_SESSION['form_old'] = $_POST;
    }
    
    // Check if request is AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        // AJAX request - return JSON
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        // Regular form submission 
        if ($response['success']) {
            // Berhasil -> ke dashboard utama
            header('Location: ../index.php');
        } else {
            // Gagal -> kembali ke halaman form_checkin agar melihat err/Flash Message dan tidak hilang data
            header('Location: ../form_checkin.php');
        }
        exit(); // Selalu exit setelah redirect
    }
    // **AKHIR PERBAIKAN**
    
} else {
    // Not a POST request
    header('Location: ../form_checkin.php');
    exit();
}
?>