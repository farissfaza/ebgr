<?php
// health_check.php — Raspi sends I2C/SPI/Camera connection status every 5 minutes
header('Content-Type: application/json');
include __DIR__ . '/../../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

// Expect JSON body with array of component statuses
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input) || !is_array($input)) {
    // Fallback: try POST params
    $input = [];
    if (!empty($_POST['component']) && !empty($_POST['status'])) {
        $input[] = ['component' => $_POST['component'], 'status' => $_POST['status'], 'details' => $_POST['details'] ?? null];
    }
}

if (empty($input)) {
    echo json_encode(['success' => false, 'message' => 'No health data provided']);
    exit;
}

$validComponents = ['pn532', 'mlx90614', 'camera'];
$validStatuses = ['connected', 'disconnected', 'error'];
$inserted = 0;

$stmt = $conn->prepare("INSERT INTO raspi_health (component, status, details, checked_at) VALUES (?, ?, ?, NOW())");

foreach ($input as $item) {
    $component = $item['component'] ?? '';
    $status = $item['status'] ?? '';
    $details = $item['details'] ?? null;
    
    if (!in_array($component, $validComponents) || !in_array($status, $validStatuses)) {
        continue;
    }
    
    $stmt->bind_param("sss", $component, $status, $details);
    if ($stmt->execute()) {
        $inserted++;
    }
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => $inserted > 0,
    'message' => "Inserted $inserted health check records"
]);
?>
