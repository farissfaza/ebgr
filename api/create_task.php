<?php
// create_task.php — Website creates a task for the Raspi to pick up
header('Content-Type: application/json');
include __DIR__ . '/../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$task_type = $_POST['task_type'] ?? '';
$checkin_session = $_POST['checkin_session'] ?? '';

$validTypes = ['rfid_scan', 'camera_ktp', 'camera_payment'];
if (!in_array($task_type, $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid task_type. Use: ' . implode(', ', $validTypes)]);
    exit;
}

// Generate unique task token
$task_token = bin2hex(random_bytes(16));

// Cancel any existing pending/processing tasks of the same type for this session
// This prevents task buildup if the user clicks multiple times
if (!empty($checkin_session)) {
    $cancelStmt = $conn->prepare("UPDATE raspi_tasks SET status = 'expired' WHERE checkin_session = ? AND task_type = ? AND status IN ('pending', 'processing')");
    $cancelStmt->bind_param("ss", $checkin_session, $task_type);
    $cancelStmt->execute();
    $cancelStmt->close();
}

$stmt = $conn->prepare("INSERT INTO raspi_tasks (task_token, task_type, checkin_session, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
$stmt->bind_param("sss", $task_token, $task_type, $checkin_session);
$success = $stmt->execute();
$stmt->close();
$conn->close();

if ($success) {
    echo json_encode([
        'success' => true,
        'task_token' => $task_token,
        'task_type' => $task_type
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create task']);
}
?>
