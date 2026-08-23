<?php
// s3_upload.php — Helper functions for MinIO S3 operations

require_once __DIR__ . '/../config/s3.php';

use Aws\Exception\AwsException;

/**
 * Upload a local file to MinIO S3
 * @param string $localPath  Absolute path to the local file
 * @param string $s3Key      The key (path) inside the bucket, e.g. "ocr-ktp/photo.jpg"
 * @return string|false      The S3 key on success, false on failure
 */
function uploadToS3($localPath, $s3Key) {
    global $s3, $S3_BUCKET;
    
    try {
        $result = $s3->putObject([
            'Bucket'     => $S3_BUCKET,
            'Key'        => $s3Key,
            'SourceFile' => $localPath,
            'ContentType' => mime_content_type($localPath),
        ]);
        
        return $s3Key;
    } catch (AwsException $e) {
        error_log("S3 upload error: " . $e->getMessage());
        return false;
    }
}

/**
 * Upload file data (from $_FILES) to MinIO S3
 * @param array  $fileData  The $_FILES['fieldname'] array
 * @param string $folder    The S3 folder, e.g. "ocr-ktp" or "bukti-pembayaran"
 * @return array|false      ['s3_key' => ..., 'url' => ...] on success, false on failure
 */
function uploadFileToS3($fileData, $folder) {
    global $s3, $S3_BUCKET, $S3_ENDPOINT;
    
    if ($fileData['error'] !== UPLOAD_ERR_OK) {
        error_log("S3 upload: file upload error code " . $fileData['error']);
        return false;
    }
    
    // Generate unique filename
    $ext = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
    $uniqueName = time() . '_' . uniqid() . '.' . $ext;
    $s3Key = $folder . '/' . $uniqueName;
    
    try {
        $result = $s3->putObject([
            'Bucket'     => $S3_BUCKET,
            'Key'        => $s3Key,
            'SourceFile' => $fileData['tmp_name'],
            'ContentType' => $fileData['type'] ?: mime_content_type($fileData['tmp_name']),
        ]);
        
        return [
            's3_key' => $s3Key,
            'url'    => getS3Url($s3Key)
        ];
    } catch (AwsException $e) {
        error_log("S3 file upload error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get the public URL for an S3 object
 * @param string $s3Key  The key inside the bucket
 * @return string        The full URL
 */
function getS3Url($s3Key) {
    global $S3_ENDPOINT, $S3_BUCKET;
    return rtrim($S3_ENDPOINT, '/') . '/' . $S3_BUCKET . '/' . ltrim($s3Key, '/');
}

/**
 * Get a presigned URL for an S3 object (for private buckets)
 * @param string $s3Key     The key inside the bucket
 * @param int    $expiry    Expiry time in seconds (default 1 hour)
 * @return string           The presigned URL
 */
function getPresignedS3Url($s3Key, $expiry = 3600) {
    global $s3, $S3_BUCKET;
    
    try {
        $cmd = $s3->getCommand('GetObject', [
            'Bucket' => $S3_BUCKET,
            'Key'    => $s3Key
        ]);
        $request = $s3->createPresignedRequest($cmd, "+{$expiry} seconds");
        return (string) $request->getUri();
    } catch (AwsException $e) {
        error_log("S3 presigned URL error: " . $e->getMessage());
        return getS3Url($s3Key); // fallback to direct URL
    }
}

/**
 * Download an S3 object to a local temp file
 * @param string $s3Key  The key inside the bucket
 * @return string|false  Path to temp file on success, false on failure
 */
function downloadFromS3($s3Key) {
    global $s3, $S3_BUCKET;
    
    try {
        $tmpFile = tempnam(sys_get_temp_dir(), 'ebgr_s3_');
        $s3->getObject([
            'Bucket' => $S3_BUCKET,
            'Key'    => $s3Key,
            'SaveAs' => $tmpFile
        ]);
        return $tmpFile;
    } catch (AwsException $e) {
        error_log("S3 download error: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete an object from S3
 * @param string $s3Key  The key to delete
 * @return bool          Success
 */
function deleteFromS3($s3Key) {
    global $s3, $S3_BUCKET;
    
    try {
        $s3->deleteObject([
            'Bucket' => $S3_BUCKET,
            'Key'    => $s3Key
        ]);
        return true;
    } catch (AwsException $e) {
        error_log("S3 delete error: " . $e->getMessage());
        return false;
    }
}
?>
