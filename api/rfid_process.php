<?php
// rfid_process.php - RFID Data Processing
include __DIR__ . '/../config/koneksi.php';
header('Content-Type: application/json');

// Terima UID dari ESP32 atau form
$uid = $_POST['uid'] ?? '';
$action = $_POST['action'] ?? '';

if (empty($uid) && $action !== 'check_nik' && $action !== 'list') {
    echo json_encode(['success' => false, 'message' => 'No UID provided']);
    exit;
}

// Buat tabel mapping jika belum ada
$sql_create_table = "
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
    INDEX idx_uid (uid),
    INDEX idx_nik (nik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

$conn->query($sql_create_table);

if ($action == 'lookup') {
    // Cari data berdasarkan UID
    $stmt = $conn->prepare("
        SELECT rm.*, dt.* 
        FROM rfid_mapping rm
        LEFT JOIN tamu_ms dt ON rm.nik = dt.nik
        WHERE rm.uid = ?
        ORDER BY dt.id_tamu DESC
        LIMIT 1
    ");
    
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        
        // Update last used
        $updateStmt = $conn->prepare("UPDATE rfid_mapping SET last_used = NOW() WHERE uid = ?");
        $updateStmt->bind_param("s", $uid);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Kirim data
        echo json_encode([
            'success' => true,
            'found' => true,
            'data' => [
                'uid' => $uid,
                'nik' => $data['nik'],
                'nama' => $data['nama'],
                'tempat_lahir' => $data['tempat_lahir'],
                'tanggal_lahir' => $data['tanggal_lahir'],
                'jenis_kelamin' => $data['jenis_kelamin'],
                'alamat' => $data['alamat'],
                'rt_rw' => $data['rt_rw'],
                'kel_desa' => $data['kel_desa'],
                'kecamatan' => $data['kecamatan'],
                'kota_kabupaten' => $data['kota_kabupaten'],
                'agama' => $data['agama'],
                'pekerjaan' => $data['pekerjaan'],
                'no_hp' => $data['no_hp'],
                'email' => $data['email']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'found' => false,
            'message' => 'UID not registered. Please fill the form manually for first registration.',
            'uid' => $uid
        ]);
    }
    
    $stmt->close();
    
} elseif ($action == 'register') {
    // Registrasi UID baru dengan data tamu
    $nik = $_POST['nik'] ?? '';
    $nama = $_POST['nama'] ?? '';
    $tempat_lahir = $_POST['tempat_lahir'] ?? '';
    $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $alamat = $_POST['alamat'] ?? '';
    $rt_rw = $_POST['rt_rw'] ?? '';
    $kel_desa = $_POST['kel_desa'] ?? '';
    $kecamatan = $_POST['kecamatan'] ?? '';
    $kota_kabupaten = $_POST['kota_kabupaten'] ?? '';
    $agama = $_POST['agama'] ?? '';
    $pekerjaan = $_POST['pekerjaan'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Check if UID already exists
    $checkStmt = $conn->prepare("SELECT id FROM rfid_mapping WHERE uid = ?");
    $checkStmt->bind_param("s", $uid);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        // Update existing
        $updateStmt = $conn->prepare("
            UPDATE rfid_mapping SET 
                nik = ?, nama = ?, tempat_lahir = ?, tanggal_lahir = ?,
                jenis_kelamin = ?, alamat = ?, rt_rw = ?, kel_desa = ?,
                kecamatan = ?, kota_kabupaten = ?, agama = ?, pekerjaan = ?,
                no_hp = ?, email = ?, last_used = NOW()
            WHERE uid = ?
        ");
        
        $updateStmt->bind_param("sssssssssssssss", 
            $nik, $nama, $tempat_lahir, $tanggal_lahir,
            $jenis_kelamin, $alamat, $rt_rw, $kel_desa,
            $kecamatan, $kota_kabupaten, $agama, $pekerjaan,
            $no_hp, $email, $uid
        );
        
        if ($updateStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'RFID mapping updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update RFID mapping'
            ]);
        }
        
        $updateStmt->close();
        
    } else {
        // Insert new
        $insertStmt = $conn->prepare("
            INSERT INTO rfid_mapping 
            (uid, nik, nama, tempat_lahir, tanggal_lahir, jenis_kelamin, 
             alamat, rt_rw, kel_desa, kecamatan, kota_kabupaten, 
             agama, pekerjaan, no_hp, email) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $insertStmt->bind_param("sssssssssssssss", 
            $uid, $nik, $nama, $tempat_lahir, $tanggal_lahir,
            $jenis_kelamin, $alamat, $rt_rw, $kel_desa,
            $kecamatan, $kota_kabupaten, $agama, $pekerjaan,
            $no_hp, $email
        );
        
        if ($insertStmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'RFID registered successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to register RFID'
            ]);
        }
        
        $insertStmt->close();
    }
    
    $checkStmt->close();
    
} elseif ($action == 'check_nik') {
    // Periksa NIK
    $nik = $_POST['nik'] ?? '';
    
    if (empty($nik)) {
        echo json_encode(['success' => false, 'message' => 'No NIK provided']);
        exit;
    }
    
    // Prioritas 1: Cek di tabel rfid_mapping
    $stmt = $conn->prepare("SELECT uid, nama FROM rfid_mapping WHERE nik = ? LIMIT 1");
    $stmt->bind_param("s", $nik);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'found' => true,
            'data' => [
                'uid' => $data['uid'],
                'nama' => $data['nama']
            ]
        ]);
        $stmt->close();
        exit;
    }
    $stmt->close();
    
    // Prioritas 2 (Sesuai usulan User): Cek riwayat absen
    // Cari tamu dengan NIK tersebut yang sudah memiliki rfid_uid tertaut
    $stmt_history = $conn->prepare("
        SELECT cl.rfid_uid, t.nama as nama 
        FROM checkin_tamu_log cl 
        JOIN tamu_ms t ON cl.id_tamu = t.id_tamu
        WHERE t.nik = ? AND cl.rfid_uid IS NOT NULL AND cl.rfid_uid != '' 
        ORDER BY cl.checkin_tamu_id DESC LIMIT 1
    ");
    $stmt_history->bind_param("s", $nik);
    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    
    if ($result_history->num_rows > 0) {
        $data = $result_history->fetch_assoc();
        echo json_encode([
            'success' => true,
            'found' => true,
            'data' => [
                'uid' => $data['rfid_uid'],
                'nama' => $data['nama']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'found' => false
        ]);
    }
    $stmt_history->close();
    
} elseif ($action == 'list') {
    // List all registered RFIDs
    $stmt = $conn->prepare("
        SELECT uid, nik, nama, last_used, registered_at 
        FROM rfid_mapping 
        ORDER BY last_used DESC, registered_at DESC 
        LIMIT 100
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $cards = [];
    while ($row = $result->fetch_assoc()) {
        $cards[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'cards' => $cards
    ]);
    
    $stmt->close();
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
}

$conn->close();
?>