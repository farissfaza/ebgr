<?php
// poll_result.php — Website polls for task result
header('Content-Type: application/json');
include __DIR__ . '/../config/koneksi.php';

$task_token = $_GET['task_token'] ?? '';

if (empty($task_token)) {
    echo json_encode(['success' => false, 'message' => 'task_token required']);
    exit;
}

$stmt = $conn->prepare("SELECT status, result_data, s3_path, task_type FROM raspi_tasks WHERE task_token = ?");
$stmt->bind_param("s", $task_token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'status' => 'not_found', 'message' => 'Task not found']);
    $stmt->close();
    $conn->close();
    exit;
}

$task = $result->fetch_assoc();
$stmt->close();
$conn->close();

$response = [
    'success' => true,
    'status' => $task['status'],
    'task_type' => $task['task_type']
];

if ($task['status'] === 'completed') {
    $response['result_data'] = json_decode($task['result_data'], true) ?: [];
    $response['s3_path'] = $task['s3_path'];
    
    // If there's an S3 path, generate the viewable URL
    if (!empty($task['s3_path'])) {
        require_once __DIR__ . '/../services/s3_upload.php';
        $response['s3_url'] = getS3Url($task['s3_path']);
    }
}

echo json_encode($response);
?>
