<?php
header('Content-Type: application/json');
include __DIR__ . '/../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

$token = isset($_POST['token']) ? trim($_POST['token']) : '';
if (empty($token)) {
    echo json_encode(['status' => 'error', 'message' => 'Token is missing']);
    exit;
}

// Ensure database connection
if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO mobile_ktp_sessions (token, status) VALUES (?, 'pending') ON DUPLICATE KEY UPDATE status='pending'");
$stmt->bind_param("s", $token);

if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to register token']);
}

$stmt->close();
$conn->close();
?>
