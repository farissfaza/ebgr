<?php
// upload_to_s3.php — Raspi uploads camera photos to MinIO S3
header('Content-Type: application/json');

require_once __DIR__ . '/../../services/s3_upload.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']);
    exit;
}

$folder = $_POST['folder'] ?? '';
if (!in_array($folder, ['ocr-ktp', 'bukti-pembayaran'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid folder. Use "ocr-ktp" or "bukti-pembayaran"']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

// Validate file size (max 10MB)
if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large (max 10MB)']);
    exit;
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $_FILES['file']['tmp_name']);
// finfo_close is deprecated in PHP 8.5+ and objects are freed automatically

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type: ' . $mimeType]);
    exit;
}

$result = uploadFileToS3($_FILES['file'], $folder);

if ($result) {
    echo json_encode([
        'success' => true,
        's3_key' => $result['s3_key'],
        'url' => $result['url']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload to S3']);
}
?>
