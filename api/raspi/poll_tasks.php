<?php
// poll_tasks.php — Raspi polls for pending tasks
// Called by Raspi every 1-2 seconds
header('Content-Type: application/json');
include __DIR__ . '/../../config/koneksi.php';

// Expire stale tasks (older than 10 minutes)
$conn->query("UPDATE raspi_tasks SET status = 'expired' WHERE status IN ('pending', 'processing') AND created_at < NOW() - INTERVAL 10 MINUTE");

// Get oldest pending task
$stmt = $conn->prepare("SELECT id, task_token, task_type, checkin_session FROM raspi_tasks WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $task = $result->fetch_assoc();
    
    // Mark as processing
    $updateStmt = $conn->prepare("UPDATE raspi_tasks SET status = 'processing', updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $task['id']);
    $updateStmt->execute();
    $updateStmt->close();
    
    echo json_encode([
        'has_task' => true,
        'task_token' => $task['task_token'],
        'task_type' => $task['task_type'],
        'checkin_session' => $task['checkin_session']
    ]);
} else {
    echo json_encode(['has_task' => false]);
}

$stmt->close();
$conn->close();
?>
