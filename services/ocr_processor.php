<?php
// ocr_processor_optimized.php - OPTIMIZED VERSION WITH MULTIPLE STRATEGIES

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Enable debugging
define('DEBUG_MODE', true);

function debugLog($message) {
    if (DEBUG_MODE) {
        $logFile = '/Applications/XAMPP/xamppfiles/htdocs/iot/ocr_debug_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    }
}

// Strategy 1: Minimal preprocessing for clear images
function preprocessMinimal($sourcePath, $destPath) {
    try {
        debugLog("Strategy 1: Minimal preprocessing");
        
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) return false;
        
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$image) return false;
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Only resize if too small
        if ($width < 1200) {
            $scale = 1200 / $width;
            $newWidth = round($width * $scale);
            $newHeight = round($height * $scale);
            
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }
        
        // Save with minimal processing
        $success = imagepng($image, $destPath);
        imagedestroy($image);
        
        return $success;
    } catch (Exception $e) {
        debugLog("Minimal preprocessing error: " . $e->getMessage());
        return false;
    }
}

// Strategy 2: Standard preprocessing
function preprocessStandard($sourcePath, $destPath) {
    try {
        debugLog("Strategy 2: Standard preprocessing");
        
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) return false;
        
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$image) return false;
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Optimal size for OCR
        $targetWidth = 1500;
        if ($width !== $targetWidth) {
            $scale = $targetWidth / $width;
            $newWidth = $targetWidth;
            $newHeight = round($height * $scale);
            
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefill($resized, 0, 0, $white);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }
        
        // Apply standard filters
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_CONTRAST, -20);
        imagefilter($image, IMG_FILTER_BRIGHTNESS, 10);
        
        $success = imagepng($image, $destPath);
        imagedestroy($image);
        
        return $success;
    } catch (Exception $e) {
        debugLog("Standard preprocessing error: " . $e->getMessage());
        return false;
    }
}

// Strategy 3: Aggressive preprocessing for poor quality images
function preprocessAggressive($sourcePath, $destPath) {
    try {
        debugLog("Strategy 3: Aggressive preprocessing");
        
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) return false;
        
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$image) return false;
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Larger size for better OCR
        $targetWidth = 2000;
        $scale = $targetWidth / $width;
        $newWidth = $targetWidth;
        $newHeight = round($height * $scale);
        
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $white);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($image);
        $image = $resized;
        
        // Aggressive filtering
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
        imagefilter($image, IMG_FILTER_CONTRAST, -50);
        imagefilter($image, IMG_FILTER_BRIGHTNESS, 20);
        imagefilter($image, IMG_FILTER_CONTRAST, -30);
        
        $success = imagepng($image, $destPath);
        imagedestroy($image);
        
        return $success;
    } catch (Exception $e) {
        debugLog("Aggressive preprocessing error: " . $e->getMessage());
        return false;
    }
}

// Run Tesseract with different PSM modes
function runTesseractWithMode($imagePath, $psm = 3, $lang = 'ind+eng') {
    debugLog("Running Tesseract with PSM $psm");
    
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
            break;
        }
    }
    
    if (!$tesseractCmd) {
        debugLog("ERROR: Tesseract not found!");
        return false;
    }
    
    $outputBase = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr_' . uniqid();
    $outputFile = $outputBase . '.txt';
    
    // Build command
    if (stristr(PHP_OS, 'WIN')) {
        $cmd = '"' . $tesseractCmd . '" "' . $imagePath . '" "' . $outputBase . '" -l ' . $lang . ' --psm ' . $psm . ' 2>&1';
    } else {
        $cmd = escapeshellcmd($tesseractCmd) . ' ' . escapeshellarg($imagePath) . ' ' . escapeshellarg($outputBase) . ' -l ' . $lang . ' --psm ' . $psm . ' 2>&1';
    }
    
    debugLog("Command: $cmd");
    
    $output = [];
    $returnVar = 0;
    exec($cmd, $output, $returnVar);
    
    debugLog("Return code: $returnVar");
    
    if (file_exists($outputFile)) {
        $text = file_get_contents($outputFile);
        @unlink($outputFile);
        debugLog("OCR text length: " . strlen($text));
        return $text;
    }
    
    return false;
}

// Try multiple OCR strategies
function performOCR($imagePath) {
    $bestText = '';
    $bestLength = 0;
    
    // PSM modes to try:
    // 3 = Fully automatic page segmentation (default)
    // 6 = Uniform block of text
    // 11 = Sparse text
    // 4 = Single column of text
    $psmModes = [3, 6, 11, 4];
    
    foreach ($psmModes as $psm) {
        $text = runTesseractWithMode($imagePath, $psm);
        if ($text && strlen($text) > $bestLength) {
            $bestText = $text;
            $bestLength = strlen($text);
            debugLog("PSM $psm produced better results: " . strlen($text) . " chars");
        }
    }
    
    // Try with only Indonesian if mixed language didn't work well
    if ($bestLength < 100) {
        debugLog("Trying with Indonesian only");
        $text = runTesseractWithMode($imagePath, 6, 'ind');
        if ($text && strlen($text) > $bestLength) {
            $bestText = $text;
            $bestLength = strlen($text);
        }
    }
    
    return $bestText;
}

// Enhanced KTP data extraction
function extractKTPData($text) {
    debugLog("Extracting from text of length: " . strlen($text));
    debugLog("First 500 chars: " . substr($text, 0, 500));
    
    // Clean text
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = array_map('trim', explode("\n", $text));
    $textUpper = strtoupper($text);
    
    $data = [
        'nik' => '',
        'nama' => '',
        'tempat_lahir' => '',
        'tanggal_lahir' => '',
        'jenis_kelamin' => '',
        'golongan_darah' => '',
        'alamat' => '',
        'rt_rw' => '',
        'kel_desa' => '',
        'kecamatan' => '',
        'agama' => '',
        'status_perkawinan' => '',
        'pekerjaan' => '',
        'kewarganegaraan' => '',
        'berlaku_hingga' => '',
        'provinsi' => '',
        'kota_kabupaten' => ''
    ];
    
    // Extract NIK - Multiple patterns
    $nikPatterns = [
        '/\b(\d{16})\b/',
        '/(\d{4})\s*(\d{4})\s*(\d{4})\s*(\d{4})/',
        '/NIK\s*:?\s*([\d\s]{16,20})/',
        '/\b(\d{4}[\s\-]?\d{4}[\s\-]?\d{4}[\s\-]?\d{4})\b/'
    ];
    
    foreach ($nikPatterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $nik = preg_replace('/[\s\-]/', '', end($matches));
            if (strlen($nik) == 16 && is_numeric($nik)) {
                $data['nik'] = $nik;
                debugLog("Found NIK: " . $nik);
                break;
            }
        }
    }
    
    // Extract Name - Check each line
    foreach ($lines as $i => $line) {
        $lineClean = trim($line);
        $lineUpper = strtoupper($lineClean);
        
        // Skip empty lines
        if (empty($lineClean)) continue;
        
        // Check for name patterns
        if (preg_match('/^NAMA\s*:?\s*(.+)$/i', $lineClean, $matches)) {
            $data['nama'] = strtoupper(trim($matches[1]));
            debugLog("Found nama in line: " . $data['nama']);
        } elseif (strpos($lineUpper, 'NAMA') !== false && isset($lines[$i + 1])) {
            $nextLine = trim($lines[$i + 1]);
            if (preg_match('/^[A-Z\s\.]+$/i', $nextLine) && strlen($nextLine) > 2) {
                $data['nama'] = strtoupper($nextLine);
                debugLog("Found nama in next line: " . $data['nama']);
            }
        } elseif (!$data['nama'] && preg_match('/^[A-Z]{2,}[\sA-Z\.]*$/i', $lineClean) && strlen($lineClean) > 5) {
            // Potential name line (all caps, no numbers)
            if (!preg_match('/(PROVINSI|KOTA|KABUPATEN|KECAMATAN|DESA|NIK|ALAMAT|RT|RW)/', $lineUpper)) {
                $data['nama'] = strtoupper($lineClean);
                debugLog("Found potential nama: " . $data['nama']);
            }
        }
        
        // Extract dates
        if (preg_match('/(\d{1,2})[\-\/\s](\d{1,2})[\-\/\s](\d{4})/', $lineClean, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = $matches[3];
            
            if (!$data['tanggal_lahir']) {
                $data['tanggal_lahir'] = "$day-$month-$year";
                debugLog("Found date: " . $data['tanggal_lahir']);
            }
        }
        
        // Extract location before date (birth place)
        if (preg_match('/([A-Z][A-Z\s]+)\s*,?\s*\d{1,2}[\-\/\s]\d{1,2}[\-\/\s]\d{4}/i', $lineClean, $matches)) {
            $data['tempat_lahir'] = strtoupper(trim($matches[1]));
            debugLog("Found tempat lahir: " . $data['tempat_lahir']);
        }
    }
    
    // Extract Gender
    if (preg_match('/(LAKI[\-\s]*LAKI|PEREMPUAN)/i', $text, $matches)) {
        $data['jenis_kelamin'] = strtoupper(str_replace(' ', '-', trim($matches[1])));
        debugLog("Found gender: " . $data['jenis_kelamin']);
    }
    
    // Extract Blood Type
    if (preg_match('/(?:GOL|DARAH)\s*:?\s*([ABO]|AB)/i', $text, $matches)) {
        $data['golongan_darah'] = strtoupper(trim($matches[1]));
        debugLog("Found blood type: " . $data['golongan_darah']);
    }
    
    // Extract Address components
    if (preg_match('/ALAMAT\s*:?\s*(.+?)(?=RT|RW|\n|$)/si', $text, $matches)) {
        $data['alamat'] = strtoupper(trim($matches[1]));
        debugLog("Found alamat: " . $data['alamat']);
    }
    
    // RT/RW
    if (preg_match('/RT\s*\/?\s*RW\s*:?\s*(\d{1,3})\s*\/\s*(\d{1,3})/i', $text, $matches)) {
        $data['rt_rw'] = str_pad($matches[1], 3, '0', STR_PAD_LEFT) . '/' . 
                         str_pad($matches[2], 3, '0', STR_PAD_LEFT);
        debugLog("Found RT/RW: " . $data['rt_rw']);
    }
    
    // Kelurahan/Desa
    if (preg_match('/(?:KEL|DESA)\s*:?\s*([A-Z\s]+?)(?=KEC|\n|$)/i', $text, $matches)) {
        $data['kel_desa'] = strtoupper(trim($matches[1]));
        debugLog("Found kel/desa: " . $data['kel_desa']);
    }
    
    // Kecamatan
    if (preg_match('/KEC(?:AMATAN)?\s*:?\s*([A-Z\s]+?)(?=\n|$)/i', $text, $matches)) {
        $data['kecamatan'] = strtoupper(trim($matches[1]));
        debugLog("Found kecamatan: " . $data['kecamatan']);
    }
    
    // Extract Religion
    if (preg_match('/(ISLAM|KRISTEN|KATOLIK|HINDU|BUDDHA|KONGHUCU)/i', $text, $matches)) {
        $data['agama'] = strtoupper(trim($matches[1]));
        debugLog("Found agama: " . $data['agama']);
    }
    
    // Extract Marital Status
    if (preg_match('/(BELUM\s*KAWIN|KAWIN|CERAI)/i', $text, $matches)) {
        $data['status_perkawinan'] = strtoupper(trim($matches[0]));
        debugLog("Found status: " . $data['status_perkawinan']);
    }
    
    // Extract Province/City from header
    if (preg_match('/PROVINSI\s+([A-Z\s]+?)(?=\n|KOTA|KAB)/i', $text, $matches)) {
        $data['provinsi'] = strtoupper(trim($matches[1]));
        debugLog("Found provinsi: " . $data['provinsi']);
    }
    
    if (preg_match('/(KOTA|KABUPATEN)\s+([A-Z\s]+?)(?=\n|$)/i', $text, $matches)) {
        $data['kota_kabupaten'] = strtoupper(trim($matches[0]));
        debugLog("Found kota/kab: " . $data['kota_kabupaten']);
    }
    
    return $data;
}

// MAIN EXECUTION
try {
    debugLog("=== Starting Optimized OCR Process ===");
    
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
        mkdir($uploadDir, 0777, true);
    }
    
    $results = [];
    $strategies = [
        'minimal' => 'preprocessMinimal',
        'standard' => 'preprocessStandard', 
        'aggressive' => 'preprocessAggressive'
    ];
    
    // Try each preprocessing strategy
    foreach ($strategies as $name => $function) {
        $processedImage = $uploadDir . 'processed_' . $name . '_' . uniqid() . '.png';
        
        if ($function($uploadedFile, $processedImage)) {
            debugLog("Trying OCR with $name preprocessing");
            
            $ocrText = performOCR($processedImage);
            
            if ($ocrText && strlen($ocrText) > 50) {
                $extractedData = extractKTPData($ocrText);
                
                // Count fields found
                $fieldsFound = 0;
                foreach ($extractedData as $value) {
                    if (!empty($value)) $fieldsFound++;
                }
                
                $results[$name] = [
                    'text_length' => strlen($ocrText),
                    'fields_found' => $fieldsFound,
                    'data' => $extractedData,
                    'text' => $ocrText
                ];
                
                debugLog("Strategy $name: " . strlen($ocrText) . " chars, $fieldsFound fields");
            }
            
            // Clean up
            @unlink($processedImage);
        }
    }
    
    // Select best result
    $bestResult = null;
    $bestScore = 0;
    
    foreach ($results as $name => $result) {
        $score = $result['fields_found'] * 100 + $result['text_length'];
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestResult = $result;
            debugLog("Best strategy so far: $name with score $score");
        }
    }
    
    if (!$bestResult) {
        // Fallback: try original image without preprocessing
        debugLog("All strategies failed, trying original image");
        $ocrText = performOCR($uploadedFile);
        
        if ($ocrText) {
            $extractedData = extractKTPData($ocrText);
            $fieldsFound = 0;
            foreach ($extractedData as $value) {
                if (!empty($value)) $fieldsFound++;
            }
            
            $bestResult = [
                'text_length' => strlen($ocrText),
                'fields_found' => $fieldsFound,
                'data' => $extractedData,
                'text' => $ocrText
            ];
        }
    }
    
    if (!$bestResult) {
        throw new Exception('OCR failed - no text extracted from any strategy');
    }
    
    debugLog("Final result: " . $bestResult['fields_found'] . " fields extracted");
    
    // Prepare response
    $data = $bestResult['data'];
    $response = [
        'success' => true,
        'nik' => $data['nik'],
        'nama' => $data['nama'],
        'tempat_lahir' => $data['tempat_lahir'],
        'tanggal_lahir' => $data['tanggal_lahir'],
        'jenis_kelamin' => $data['jenis_kelamin'],
        'golongan_darah' => $data['golongan_darah'],
        'alamat_details' => [
            'alamat' => $data['alamat'],
            'rt_rw' => $data['rt_rw'],
            'kel_desa' => $data['kel_desa'],
            'kecamatan' => $data['kecamatan']
        ],
        'agama' => $data['agama'],
        'status_perkawinan' => $data['status_perkawinan'],
        'pekerjaan' => $data['pekerjaan'],
        'kewarganegaraan' => $data['kewarganegaraan'],
        'berlaku_hingga' => $data['berlaku_hingga'],
        'provinsi' => $data['provinsi'],
        'kota_kabupaten' => $data['kota_kabupaten'],
        'fields_found' => $bestResult['fields_found'],
        'message' => $bestResult['fields_found'] > 0 ? 
            "Berhasil mengekstrak " . $bestResult['fields_found'] . " field dari KTP" : 
            "OCR selesai tapi data tidak terdeteksi. Silakan isi manual.",
        'debug_info' => [
            'text_length' => $bestResult['text_length'],
            'text_sample' => substr($bestResult['text'], 0, 200)
        ]
    ];
    
    debugLog("Response prepared with " . $bestResult['fields_found'] . " fields");
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