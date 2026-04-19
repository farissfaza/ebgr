<?php
header('Content-Type: application/json');
include __DIR__ . '/../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
if (empty($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Token is missing']);
    exit;
}

// Ensure database connection
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("SELECT status, ocr_data, image_path FROM mobile_ktp_sessions WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
    exit;
}

$session = $result->fetch_assoc();
$status = $session['status'];

if ($status === 'completed') {
    $ocrTextData = json_decode($session['ocr_data'], true);
    
    // Return the successful payload
    echo json_encode([
        'status' => 'completed',
        'image_url' => $session['image_path'],
        'data' => $ocrTextData
    ]);
    
    // Optional: We can delete or invalidate the token here so it can't be reused,
    // but leaving it might be safer for a few minutes in case the frontend misses a poll.
    // For extreme security, uncomment below:
    // $del = $conn->prepare("DELETE FROM mobile_ktp_sessions WHERE token = ?");
    // $del->bind_param("s", $token);
    // $del->execute();
    
} else if ($status === 'failed') {
    echo json_encode(['status' => 'failed', 'message' => 'Gagal membaca KTP pada HP']);
} else {
    // Still pending/scanned
    echo json_encode(['status' => 'pending']);
}

$stmt->close();
$conn->close();
?>
