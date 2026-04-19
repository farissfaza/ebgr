<?php
header('Content-Type: application/json');
include __DIR__ . '/../config/koneksi.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['overlap' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Ensure database connection
if (!$conn) {
    echo json_encode(['overlap' => true, 'message' => 'Database connection failed.']);
    exit;
}

// Retrieve values from POST, set defaults to empty strings
$nik = isset($_POST['nik']) ? trim($_POST['nik']) : '';
$nama_tamu = isset($_POST['nama_tamu']) ? trim($_POST['nama_tamu']) : '';
$nama_kamar = isset($_POST['nama_kamar']) ? trim($_POST['nama_kamar']) : '';
$nama_kelas = isset($_POST['nama_kelas']) ? trim($_POST['nama_kelas']) : '';
$tanggal_check_in = isset($_POST['tanggal_check_in']) ? trim($_POST['tanggal_check_in']) : '';
$tanggal_check_out = isset($_POST['tanggal_check_out']) ? trim($_POST['tanggal_check_out']) : '';

// We need both date ranges to check overlaps accurately
if (empty($tanggal_check_in) || empty($tanggal_check_out)) {
    echo json_encode(['overlap' => false]); // Not enough info to determine overlap
    exit;
}

// Make sure check-out is strictly after check-in
if (strtotime($tanggal_check_out) <= strtotime($tanggal_check_in)) {
    echo json_encode(['overlap' => true, 'message' => "Tanggal check-out tidak valid."]);
    exit;
}

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
            $stmtUserCheck->close();
            echo json_encode(['overlap' => true, 'overlap_type' => 'user', 'message' => "Tamu ini sudah memiliki jadwal pada waktu yang sama."]);
            exit;
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
            $stmtRoom->close();
            echo json_encode(['overlap' => true, 'overlap_type' => 'room', 'message' => "Kamar sedang digunakan oleh {$occupant}."]);
            exit;
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
            $stmtClass->close();
            echo json_encode(['overlap' => true, 'overlap_type' => 'class', 'message' => "Kelas sedang digunakan oleh {$occupant}."]);
            exit;
        }
        $stmtClass->close();
    }
}

// If no overlaps triggered, return clear
echo json_encode(['overlap' => false, 'overlap_type' => 'none']);
exit;
?>
