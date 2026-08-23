<?php
// temperature.php — Raspi sends MLX90614 temperature readings during a check-in session
header('Content-Type: application/json');
include __DIR__ . '/../../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$checkin_session = $_POST['checkin_session'] ?? '';
$temperature = isset($_POST['temperature']) ? floatval($_POST['temperature']) : null;

if (empty($checkin_session) || $temperature === null) {
    echo json_encode(['success' => false, 'message' => 'checkin_session and temperature required']);
    exit;
}

// Validate temperature range (human body temp range + some margin)
if ($temperature < 20.0 || $temperature > 50.0) {
    echo json_encode(['success' => false, 'message' => 'Temperature out of range (20-50°C)']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO temperature_sessions (checkin_session, temperature, recorded_at) VALUES (?, ?, NOW())");
$stmt->bind_param("sd", $checkin_session, $temperature);
$success = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode([
    'success' => $success,
    'recorded' => $temperature
]);
?>
