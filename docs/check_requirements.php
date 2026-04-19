<?php
// check_requirements.php
echo "<h2>OCR System Requirements Check</h2>";

echo "<h3>1. PHP Extensions</h3>";
echo "<ul>";
echo "<li>GD: " . (extension_loaded('gd') ? '✅ Installed' : '❌ Not installed') . "</li>";
echo "<li>ImageMagick: " . (extension_loaded('imagick') ? '✅ Installed' : '⚠️ Not installed (optional)') . "</li>";
echo "<li>FileInfo: " . (extension_loaded('fileinfo') ? '✅ Installed' : '❌ Not installed') . "</li>";
echo "</ul>";

echo "<h3>2. Tesseract OCR</h3>";
$tesseractPaths = [
    "C:\\Program Files\\Tesseract-OCR\\tesseract.exe",
    "C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe",
];

$tesseractFound = false;
foreach ($tesseractPaths as $path) {
    if (file_exists($path)) {
        echo "<p>✅ Tesseract found at: <strong>$path</strong></p>";
        $tesseractFound = true;
        
        // Test tesseract version
        $version = shell_exec("\"$path\" --version 2>&1");
        echo "<pre>$version</pre>";
        break;
    }
}

if (!$tesseractFound) {
    echo "<p>❌ Tesseract not found. Please install from: ";
    echo "<a href='https://github.com/UB-Mannheim/tesseract/wiki' target='_blank'>Download Tesseract</a></p>";
}

echo "<h3>3. Folder Permissions</h3>";
$uploadDir = __DIR__ . '/uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
    echo "<p>✅ Created uploads directory</p>";
} else {
    echo "<p>✅ Uploads directory exists</p>";
}

if (is_writable($uploadDir)) {
    echo "<p>✅ Uploads directory is writable</p>";
} else {
    echo "<p>❌ Uploads directory is NOT writable. Please set permissions to 0777</p>";
}

echo "<h3>4. PHP Configuration</h3>";
echo "<ul>";
echo "<li>upload_max_filesize: " . ini_get('upload_max_filesize') . "</li>";
echo "<li>post_max_size: " . ini_get('post_max_size') . "</li>";
echo "<li>max_execution_time: " . ini_get('max_execution_time') . "s</li>";
echo "</ul>";

echo "<h3>5. Test Upload</h3>";
echo "<form method='post' enctype='multipart/form-data'>";
echo "<input type='file' name='test_file' accept='image/*'>";
echo "<button type='submit'>Test Upload</button>";
echo "</form>";

if (isset($_FILES['test_file']) && $_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
    echo "<p>✅ File upload test successful!</p>";
    echo "<p>File size: " . $_FILES['test_file']['size'] . " bytes</p>";
    echo "<p>File type: " . $_FILES['test_file']['type'] . "</p>";
}
?>