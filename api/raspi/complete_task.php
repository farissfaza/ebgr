<?php
// complete_task.php — Raspi reports task completion
header('Content-Type: application/json');
include __DIR__ . '/../../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$task_token = $_POST['task_token'] ?? '';
$status = $_POST['status'] ?? 'completed'; // completed or failed
$result_data = $_POST['result_data'] ?? '{}';
$s3_path = $_POST['s3_path'] ?? null;

if (empty($task_token)) {
    echo json_encode(['success' => false, 'message' => 'task_token required']);
    exit;
}

// Validate status
if (!in_array($status, ['completed', 'failed'])) {
    $status = 'completed';
}

$stmt = $conn->prepare("UPDATE raspi_tasks SET status = ?, result_data = ?, s3_path = ?, updated_at = NOW(), latency_ms = TIMESTAMPDIFF(MICROSECOND, created_at, NOW()) / 1000 WHERE task_token = ? AND status = 'processing'");
$stmt->bind_param("ssss", $status, $result_data, $s3_path, $task_token);
$success = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
$conn->close();

echo json_encode([
    'success' => $success && $affected > 0,
    'message' => $affected > 0 ? 'Task updated' : 'Task not found or not in processing state'
]);
?>
