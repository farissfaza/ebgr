<?php
// ocr_processor.php - FIXED VERSION WITH BETTER ERROR HANDLING AND COMPATIBILITY

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Enable debugging
define('DEBUG_MODE', true);

function debugLog($message) {
    if (DEBUG_MODE) {
        $logFile = __DIR__ . '/ocr_debug_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}

// Simple but effective GD preprocessing
function preprocessImageGD($sourcePath, $destPath) {
    try {
        debugLog("Starting GD preprocessing: $sourcePath");
        
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            throw new Exception("Cannot read image info");
        }
        
        // Load image based on type
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($sourcePath);
                break;
            default:
                throw new Exception("Unsupported image type");
        }
        
        if (!$image) {
            throw new Exception("Failed to create image resource");
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Resize if too small (OCR needs good resolution)
        if ($width < 1500) {
            $scale = 1500 / $width;
            $newWidth = round($width * $scale);
            $newHeight = round($height * $scale);
            
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }
        
        // Create a clean white background image
        $processed = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($processed, 255, 255, 255);
        imagefill($processed, 0, 0, $white);
        
        // Copy and apply filters
        imagecopy($processed, $image, 0, 0, 0, 0, $width, $height);
        
        // Convert to grayscale
        imagefilter($processed, IMG_FILTER_GRAYSCALE);
        
        // Increase contrast multiple times for better text
        imagefilter($processed, IMG_FILTER_CONTRAST, -30);
        imagefilter($processed, IMG_FILTER_CONTRAST, -30);
        
        // Brightness adjustment
        imagefilter($processed, IMG_FILTER_BRIGHTNESS, 20);
        
        // Save as PNG
        $success = imagepng($processed, $destPath, 0);
        
        imagedestroy($image);
        imagedestroy($processed);
        
        debugLog("GD preprocessing completed: $destPath");
        return $success;
        
    } catch (Exception $e) {
        debugLog("GD preprocessing error: " . $e->getMessage());
        return false;
    }
}

// Simplified Tesseract execution
function runTesseract($imagePath) {
    debugLog("Starting Tesseract OCR for: $imagePath");
    
    // Find Tesseract executable
    $tesseractPaths = [
        "C:\\Program Files\\Tesseract-OCR\\tesseract.exe",
        "C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe",
        "/opt/homebrew/bin/tesseract", // Mac M1/M2/M3 (Apple Silicon)
        "/usr/local/bin/tesseract",    // Mac Intel
        "/usr/bin/tesseract",          // Linux
        "tesseract"
    ];
    
    $tesseractCmd = null;
    foreach ($tesseractPaths as $path) {
        if (file_exists($path) || ($path === 'tesseract' && !stristr(PHP_OS, 'WIN'))) {
            $tesseractCmd = $path;
            debugLog("Found Tesseract at: $path");
            break;
        }
    }
    
    if (!$tesseractCmd) {
        debugLog("ERROR: Tesseract not found!");
        return false;
    }
    
    // Prepare output file
    $outputBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_' . uniqid();
    $outputFile = $outputBase . '.txt';
    
    // Build command - use simpler approach for Windows
    if (stristr(PHP_OS, 'WIN')) {
        // Windows command
        $cmd = '"' . $tesseractCmd . '" "' . $imagePath . '" "' . $outputBase . '" -l ind+eng --psm 6 2>&1';
    } else {
        // Linux/Unix command
        $cmd = escapeshellcmd($tesseractCmd) . ' ' . escapeshellarg($imagePath) . ' ' . escapeshellarg($outputBase) . ' -l ind+eng --psm 6 2>&1';
    }
    
    debugLog("Executing command: $cmd");
    
    // Execute Tesseract
    $output = [];
    $returnVar = 0;
    exec($cmd, $output, $returnVar);
    
    debugLog("Command return code: $returnVar");
    debugLog("Command output: " . implode("\n", $output));
    
    // Read the output file
    if (file_exists($outputFile)) {
        $text = file_get_contents($outputFile);
        @unlink($outputFile);
        debugLog("OCR text length: " . strlen($text));
        return $text;
    } else {
        debugLog("ERROR: Output file not created: $outputFile");
        
        // Try to get output directly from command
        $directOutput = implode("\n", $output);
        if (strlen($directOutput) > 50) {
            return $directOutput;
        }
        
        return false;
    }
}

// Extract KTP data with fallback patterns
function extractKTPData($text) {
    debugLog("Extracting data from text length: " . strlen($text));
    
    // Clean text
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);
    
    // Also create uppercase version for searching
    $textUpper = strtoupper($text);
    
    $data = [
        'nik' => '',
        'nama' => '',
        'tempat_lahir' => '',
        'tanggal_lahir' => '',
        'alamat' => '',
        'rt_rw' => '',
        'kel_desa' => '',
        'kecamatan' => ''
    ];
    
    // Extract NIK - look for 16 digit number
    if (preg_match('/\b(\d{16})\b/', $text, $matches)) {
        $data['nik'] = $matches[1];
    } elseif (preg_match('/NIK\s*:?\s*([\d\s]{16,20})/', $textUpper, $matches)) {
        $data['nik'] = preg_replace('/\D/', '', $matches[1]);
        $data['nik'] = substr($data['nik'], 0, 16);
    }
    
    // Extract Name - look for line after "Nama" or capital letters pattern
    foreach ($lines as $i => $line) {
        $lineUpper = strtoupper(trim($line));
        
        // Check for NAMA label
        if (strpos($lineUpper, 'NAMA') !== false && strpos($lineUpper, 'NAMA') < 10) {
            // Get the name part from the same line or next line
            if (preg_match('/NAMA\s*:?\s*(.+)$/i', $line, $matches)) {
                $nama = trim($matches[1]);
                if (strlen($nama) > 2 && !preg_match('/^\d+$/', $nama)) {
                    $data['nama'] = $nama;
                    break;
                }
            } elseif (isset($lines[$i + 1])) {
                $nama = trim($lines[$i + 1]);
                if (strlen($nama) > 2 && !preg_match('/^\d+$/', $nama)) {
                    $data['nama'] = $nama;
                    break;
                }
            }
        }
        
        // Fallback: Look for name pattern (all caps, no numbers)
        if (!$data['nama'] && preg_match('/^[A-Z][A-Z\s\.]{3,50}$/', $lineUpper)) {
            // Skip common KTP labels
            if (!preg_match('/(PROVINSI|KOTA|KABUPATEN|NIK|NAMA|TEMPAT|ALAMAT|AGAMA|STATUS|BERLAKU)/', $lineUpper)) {
                $data['nama'] = trim($line);
            }
        }
    }
    
    // Extract Birth Date
    if (preg_match('/(\d{1,2})[\-\/](\d{1,2})[\-\/](\d{4})/', $text, $matches)) {
        $data['tanggal_lahir'] = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }
    
    // Extract Birth Place
    if (preg_match('/(?:TEMPAT|TTL)[^\n]*?([A-Z][A-Za-z\s]+?)\s*,?\s*\d{1,2}[\-\/]/i', $text, $matches)) {
        $data['tempat_lahir'] = trim($matches[1]);
    }
    
    // Extract Address
    foreach ($lines as $i => $line) {
        if (stripos($line, 'ALAMAT') !== false || stripos($line, 'Alamat') !== false) {
            // Get address from same line or next line
            if (preg_match('/ALAMAT\s*:?\s*(.+)$/i', $line, $matches)) {
                $data['alamat'] = trim($matches[1]);
            } elseif (isset($lines[$i + 1])) {
                $data['alamat'] = trim($lines[$i + 1]);
            }
            break;
        }
    }
    
    // Extract RT/RW
    if (preg_match('/RT\s*\/?\s*RW\s*:?\s*(\d{1,3})\s*\/\s*(\d{1,3})/i', $text, $matches)) {
        $data['rt_rw'] = str_pad($matches[1], 3, '0', STR_PAD_LEFT) . '/' . 
                         str_pad($matches[2], 3, '0', STR_PAD_LEFT);
    } elseif (preg_match('/(\d{3})\s*\/\s*(\d{3})/', $text, $matches)) {
        $data['rt_rw'] = $matches[1] . '/' . $matches[2];
    }
    
    // Extract Kelurahan/Desa
    if (preg_match('/(?:KEL|DESA|Kel\/Desa)\s*:?\s*([A-Za-z\s]+?)(?:\s+Kec|\s+KEC|\n|$)/i', $text, $matches)) {
        $data['kel_desa'] = trim($matches[1]);
    }
    
    // Extract Kecamatan
    if (preg_match('/(?:KECAMATAN|Kecamatan|KEC)\s*:?\s*([A-Za-z\s]+?)(?:\n|$)/i', $text, $matches)) {
        $data['kecamatan'] = trim($matches[1]);
    }
    
    // Clean up data
    foreach ($data as $key => $value) {
        if ($value) {
            $data[$key] = trim(preg_replace('/\s+/', ' ', $value));
        }
    }
    
    debugLog("Extracted data: " . json_encode($data));
    return $data;
}

// MAIN EXECUTION
try {
    debugLog("=== Starting OCR Process ===");
    
    // Check file upload
    if (!isset($_FILES['identitas'])) {
        throw new Exception('No file uploaded');
    }
    
    if ($_FILES['identitas']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error code: ' . $_FILES['identitas']['error']);
    }
    
    $uploadedFile = $_FILES['identitas']['tmp_name'];
    $fileName = $_FILES['identitas']['name'];
    
    debugLog("Processing file: $fileName");
    
    // Create uploads directory
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true)) {
            throw new Exception('Cannot create upload directory');
        }
    }
    
    // Process image with GD
    $processedImage = $uploadDir . 'processed_' . uniqid() . '.png';
    if (!preprocessImageGD($uploadedFile, $processedImage)) {
        // If preprocessing fails, use original
        debugLog("Preprocessing failed, using original image");
        $processedImage = $uploadedFile;
    }
    
    // Run OCR
    $ocrText = runTesseract($processedImage);
    
    // Clean up processed image if not original
    if ($processedImage !== $uploadedFile && file_exists($processedImage)) {
        @unlink($processedImage);
    }
    
    if (!$ocrText) {
        throw new Exception('OCR failed - no text extracted');
    }
    
    debugLog("OCR successful, text length: " . strlen($ocrText));
    
    // Extract data
    $extractedData = extractKTPData($ocrText);
    
    // Count extracted fields
    $fieldsFound = 0;
    foreach ($extractedData as $value) {
        if (!empty($value)) $fieldsFound++;
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'nik' => $extractedData['nik'],
        'nama' => $extractedData['nama'],
        'tempat_lahir' => $extractedData['tempat_lahir'],
        'tanggal_lahir' => $extractedData['tanggal_lahir'],
        'alamat_details' => [
            'alamat' => $extractedData['alamat'],
            'rt_rw' => $extractedData['rt_rw'],
            'kel_desa' => $extractedData['kel_desa'],
            'kecamatan' => $extractedData['kecamatan']
        ],
        'fields_found' => $fieldsFound,
        'message' => $fieldsFound > 0 ? 
            "Berhasil mengekstrak $fieldsFound field dari KTP" : 
            "OCR selesai tapi data tidak terdeteksi. Silakan isi manual."
    ];
    
    debugLog("Response: " . json_encode($response));
    echo json_encode($response);
    
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    debugLog("ERROR: $errorMsg");
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $errorMsg,
        'fields_found' => 0
    ]);
}

debugLog("=== OCR Process Complete ===\n");
?>