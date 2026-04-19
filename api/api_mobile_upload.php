<?php
include __DIR__ . '/../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Invalid request method");
}

$token = isset($_POST['token']) ? trim($_POST['token']) : '';
if (empty($token)) {
    die("Token is missing");
}

function redirectBack($status, $token, $msg = '') {
    $url = "mobile_ktp.php?token=" . urlencode($token) . "&status=" . urlencode($status);
    if (!empty($msg)) {
        $url .= "&message=" . urlencode($msg);
    }
    header("Location: " . $url);
    exit;
}

// Check if token exists in DB and is pending
$stmt = $conn->prepare("SELECT status FROM mobile_ktp_sessions WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirectBack('error', $token, 'Invalid or expired token');
}

$session = $result->fetch_assoc();
if ($session['status'] !== 'pending') {
    redirectBack('error', $token, 'Session is no longer pending or already used');
}
$stmt->close();

$fileKey = 'ktp_image';
if (!isset($_FILES['ktp_image'])) {
    if (isset($_FILES['ktp_image_gallery'])) {
        $fileKey = 'ktp_image_gallery';
    } else {
        // Log what POST looks like to debug if iOS is stripping the file
        error_log("Mobile Upload Failed: No file keys found. POST data: " . print_r($_POST, true) . " FILES data: " . print_r($_FILES, true));
        redirectBack('error', $token, 'Gagal mengunggah file. Pastikan foto terpilih. (Code: No Payload)');
    }
}

if ($_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES[$fileKey]['error'];
    $errMsg = "Gagal mengupload (Error Code: $errCode)";
    if ($errCode == UPLOAD_ERR_INI_SIZE) $errMsg = "File terlalu besar (melebihi batas server).";
    if ($errCode == UPLOAD_ERR_PARTIAL) $errMsg = "File terpotong saat diunggah.";
    if ($errCode == UPLOAD_ERR_NO_FILE) $errMsg = "File fisik tidak diterima server.";
    
    redirectBack('error', $token, $errMsg);
}

// 1. Save File Temporarily
$tempDir = __DIR__ . '/uploads/ktp/temp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// Sanitize filename and create unique name
$originalName = basename($_FILES[$fileKey]['name']);
// HEIC to JPG conversion from frontend comes through here
$fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (empty($fileExt)) $fileExt = 'jpg'; // fallback

$newFilename = 'mobile_' . $token . '_' . time() . '.' . $fileExt;
$destination = $tempDir . '/' . $newFilename;

if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $destination)) {
    redirectBack('error', $token, 'Failed to save uploaded file');
}

// Map path for frontend consumption later
$publicPath = 'uploads/ktp/temp/' . $newFilename;

// 2. Trigger OCR by simulating a cURL request to our existing ocr_vision.php endpoint
// We do this to reuse the exact Google Cloud Vision integration logic
$ocrApiUrl = 'http://localhost/iot/ocr_vision.php';

$cfile = new CURLFile($destination, mime_content_type($destination), $newFilename);
$postData = ['identitas' => $cfile];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ocrApiUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Short timeout because we want to block until it finishes so we can update DB
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$ocrResponseRaw = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$ocrResponseRaw) {
    // Flag as failed in DB
    $failStmt = $conn->prepare("UPDATE mobile_ktp_sessions SET status = 'failed' WHERE token = ?");
    $failStmt->bind_param("s", $token);
    $failStmt->execute();
    
    redirectBack('error', $token, 'OCR Engine failed to process the image. HTTP: ' . $httpCode);
}

// Verify output is valid JSON
$ocrResult = json_decode($ocrResponseRaw, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($ocrResult['success'])) {
    $failStmt = $conn->prepare("UPDATE mobile_ktp_sessions SET status = 'failed' WHERE token = ?");
    $failStmt->bind_param("s", $token);
    $failStmt->execute();
    
    redirectBack('error', $token, 'Invalid OCR response format');
}

if ($ocrResult['success'] === false) {
    // OCR failed to find text
    $failStmt = $conn->prepare("UPDATE mobile_ktp_sessions SET status = 'failed' WHERE token = ?");
    $failStmt->bind_param("s", $token);
    $failStmt->execute();
    
    redirectBack('error', $token, $ocrResult['message'] ?? 'Could not read KTP text');
}

// 3. Update Token Session as Completed
$ocrDataJson = json_encode($ocrResult['data']);

$updateStmt = $conn->prepare("UPDATE mobile_ktp_sessions SET status = 'completed', ocr_data = ?, image_path = ? WHERE token = ?");
$updateStmt->bind_param("sss", $ocrDataJson, $publicPath, $token);

if ($updateStmt->execute()) {
    redirectBack('success', $token);
} else {
    redirectBack('error', $token, 'Failed to save session data to database');
}
$updateStmt->close();
$conn->close();
?>
