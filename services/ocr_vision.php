<?php
// ocr_vision.php - Google Cloud Vision API Integration
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

define('DEBUG_MODE', true);

function debugLog($message) {
    if (DEBUG_MODE) {
        $logFile = __DIR__ . '/../ocr_debug_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[$timestamp] [VISION API] $message\n", FILE_APPEND);
    }
}

// TODO: UBAH BAGIAN INI DENGAN API KEY GOOGLE CLOUD VISION ANDA
$googleVisionApiKey = 'AIzaSyAcnhL48wJ1r_iSiLX_uUfXmZ5o7dmhr38';

function extractKTPDataVision($text) {
    debugLog("Extracting from Vision text:\n" . substr($text, 0, 500) . "...");
    
    // Clean text and split by line
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
    
    // ============================================
    // FIXED REGEX PARSING FOR OUT-OF-ORDER COLUMNS
    // ============================================

    $raw_nik_match = '';
    
    // 1. EXTRACT NIK
    if (preg_match('/\b(\d{16})\b/', $text, $matches)) {
        $data['nik'] = $matches[1];
        $raw_nik_match = $matches[1];
    } else if (preg_match('/\b([0-9]{2}[A-Z0-9]{13,16})\b/i', $textUpper, $matches)) {
        // FUZZY NIK: If Google Vision read 16 chars but with some letters like 'O' instead of '0'
        $raw_nik_match = strtoupper($matches[1]);
        $digits = preg_replace('/[^0-9]/', '', $matches[1]);
        // Must have at least *some* digits to be considered a NIK
        if (strlen($digits) >= 12) { 
             // Transliterate common OCR mistakes
             $replacements = [
                 'O' => '0', 'D' => '0', 'Q' => '0',
                 'I' => '1', 'L' => '1',
                 'Z' => '2',
                 'E' => '3',
                 'A' => '4',
                 'S' => '5',
                 'G' => '6', 'b' => '6',
                 'T' => '7',
                 'B' => '8',
                 'P' => '9', 'g' => '9'
             ];
             $cleanedNik = strtr($raw_nik_match, $replacements);
             // Strip any remaining non-digits
             $cleanedNik = preg_replace('/[^0-9]/', '', $cleanedNik);
             
             // Must be 16 digits exactly after cleanup, or at least take first 16
             if (strlen($cleanedNik) >= 16) {
                 $data['nik'] = substr($cleanedNik, 0, 16);
             } else {
                 $data['nik'] = $cleanedNik;
             }
    }
    }

    // Heuristic: The last 4 digits of a NIK represent a sequential daily birth queue.  
    // Queues rarely exceed 0100. If Vision reads "6603" because of KTP glare/scratches on "0003",
    // we can safely convert the false "6"s or "8"s back to "0"s if the numeric sequence > 100.
    if (!empty($data['nik']) && strlen($data['nik']) === 16) {
        $sequence = substr($data['nik'], -4);
        if (intval($sequence) > 100) {
            // Replace common OCR circular misreads ONLY in the first 3 digits of the sequence
            // to avoid destroying a valid queue number like ending in 6 (e.g. 0006).
            $seq_head = substr($sequence, 0, 3);
            $seq_tail = substr($sequence, -1);
            $seq_head = strtr($seq_head, ['6'=>'0', '8'=>'0', '9'=>'0', 'G'=>'0', 'b'=>'0', 'B'=>'0']);
            $data['nik'] = substr($data['nik'], 0, 12) . $seq_head . $seq_tail;
        }
    }
    
    // 2. EXTRACT TTL (Tempat, Tgl Lahir)
    // Avoid \s across newlines. Use [A-Z ]+ to restrict to a single line.
    if (preg_match('/([A-Z ]+?)\s*,\s*(\d{2})[\-\/\.](\d{2})[\-\/\.](\d{4})/i', $text, $matches)) {
        $tmp = trim(strtoupper($matches[1]));
        $tmp = preg_replace('/.*(LAHIR|TEMPAT)\s*:?\s*/i', '', $tmp);
        $data['tempat_lahir'] = trim($tmp);
        // Format to YYYY-MM-DD specifically for HTML5 <input type="date">
        $data['tanggal_lahir'] = $matches[4] . '-' . $matches[3] . '-' . $matches[2];
    }
    
    // 3. EXTRACT JENIS KELAMIN (Fuzzy matching for OCR errors like LAKI LAKE)
    if (preg_match('/(LAKI[\s\-\.]*LAKI|LAKI[\s\-\.]*LAKE|PEREMPUAN)/i', $textUpper, $matches)) {
        $kelamin = strtoupper($matches[1]);
        if (strpos($kelamin, 'LAKI') !== false) {
            $data['jenis_kelamin'] = 'LAKI-LAKI';
        } else {
            $data['jenis_kelamin'] = 'PEREMPUAN';
        }
    }
    
    // 4. EXTRACT GOL DARAH
    if (preg_match('/(?:GOL[\.\s]*DARAH|DARAH)\s*:?\s*([ABO]|AB)\b/i', $textUpper, $matches)) {
        $data['golongan_darah'] = strtoupper($matches[1]);
    }
    
    // 5. EXTRACT RT/RW
    if (preg_match('/\b(\d{3})\s*[\\\\\/]\s*(\d{3})\b/', $textUpper, $matches)) {
        $data['rt_rw'] = $matches[1] . '/' . $matches[2];
    }
    
    // 6. EXTRACT AGAMA
    if (preg_match('/\b(ISLAM|KRISTEN|KATOLIK|HINDU|BUDDHA|KONGHUCU)\b/i', $textUpper, $matches)) {
        $data['agama'] = trim($matches[1]);
    }
    
    // 7. EXTRACT STATUS PERKAWINAN
    if (preg_match('/\b(BELUM\s*KAWIN|KAWIN|CERAI\s*HIDUP|CERAI\s*MATI)\b/i', $textUpper, $matches)) {
        $data['status_perkawinan'] = trim($matches[1]);
    }
    
    // 8. EXTRACT KEWARGANEGARAAN
    if (preg_match('/\b(WNI|WNA)\b/i', $textUpper, $matches)) {
        $data['kewarganegaraan'] = trim($matches[1]);
    }
    
    // 9. EXTRACT BERLAKU HINGGA
    if (preg_match('/\b(SEUMUR\s*HIDUP|\d{2}[\-\/\.]\d{2}[\-\/\.]\d{4})\b/i', $textUpper, $matches) && trim($matches[1]) !== $data['tanggal_lahir']) {
         $data['berlaku_hingga'] = trim($matches[1]);
    }

    // Extract Provinsi dan Kota (Use [A-Z ] instead of [A-Z\s] to avoid overflowing into next lines)
    if (preg_match('/PROVINSI\s+([A-Z ]+)/i', $textUpper, $matches)) {
        $data['provinsi'] = trim($matches[1]);
    }
    if (preg_match('/(?:KOTA|KABUPATEN)\s+([A-Z ]+)/i', $textUpper, $matches)) {
        $data['kota_kabupaten'] = trim($matches[1]);
    }

    // 10. HEURISTIC FOR NAMA, ALAMAT, KELURAHAN, KECAMATAN, PEKERJAAN
    $potential_fields = [];
    $last_seen_label = '';

    foreach ($lines as $line) {
        $cleanLine = trim(preg_replace('/^[:\-\.]\s*/', '', trim($line))); 
        $upperLine = strtoupper($cleanLine);
        
        if (empty($upperLine) || strlen($upperLine) < 3) continue;

        // Exactly a label standing alone? Skip it.
        if (preg_match('/^(NIK|NAMA|TEMPAT|TGL|LAHIR|JENIS\s*KELAMIN|ALAMAT|RT\s*[\\\\\/]\s*RW|KEL\s*[\\\\\/]\s*DESA|KECAMATAN|AGAMA|STATUS|KAWIN|PEKERJAAN|KEWARGANEGARAAN|BERLAKU|HINGGA|GOL\s*\.?\s*DARAH|PROVINSI|KOTA|KABUPATEN|KARTU|TANDA|PENDUDUK)$/i', $upperLine, $lbl)) {
            $last_seen_label = strtoupper($lbl[1]);
            continue;
        }

        // Label AND value on SAME line? Extract value and skip.
        if (preg_match('/^KECAMATAN\s*:?\s*([A-Z ]+)$/i', $upperLine, $m) && strlen(trim($m[1])) > 2) { $data['kecamatan'] = trim($m[1]); continue; }
        if (preg_match('/^(?:KEL|DESA)\s*:?\s*([A-Z ]+)$/i', $upperLine, $m) && !preg_match('/(AMIN|AMATAN)/', $upperLine) && strlen(trim($m[1])) > 2) { $data['kel_desa'] = trim($m[1]); continue; }
        if (preg_match('/^ALAMAT\s*:?\s*([A-Z\s0-9\.\-]+)$/i', $upperLine, $m) && strlen(trim($m[1])) > 3) { $data['alamat'] = trim($m[1]); continue; }
        if (preg_match('/^PEKERJAAN\s*:?\s*([A-Z\s\/]+)$/i', $upperLine, $m) && strlen(trim($m[1])) > 2) { $data['pekerjaan'] = trim($m[1]); continue; }
        if (preg_match('/^NAMA\s*:?\s*([A-Z\s\.\,]+)$/i', $upperLine, $m) && strlen(trim($m[1])) > 2) { $data['nama'] = trim($m[1]); continue; }

        // Mismatched known false-positive substrings from Google Vision
        if (preg_match('/(LAKI LAKE|LAKI.*?LAKI|PEREMPUAN)/i', $upperLine)) continue;
        
        // Filter out KTP watermarks and noise
        if (preg_match('/(KARTU|TANDA|PENDUDUK|KARTY|SENDUDUK|NDUD|PEND)/i', $upperLine)) continue;
        if (preg_match('/^(TU TA|NDA|KAR|DA)$/i', $upperLine)) continue;
        
        // Filter out ghost background serial numbers (e.g. E07960T06280402E) from becoming Alamat.
        // If it's a 14+ char string with NO spaces, it's neither an Address nor a Name.
        if (preg_match('/^[A-Z0-9]{14,20}\s*:?$/i', $upperLine)) {
            $last_seen_label = ''; // Reset label tracking just in case
            continue;
        }

        // Skip lines that contain values we ALREADY successfully extracted from full-text regex
        if (!empty($raw_nik_match) && strpos($upperLine, $raw_nik_match) !== false) continue;
        if (!empty($data['tanggal_lahir']) && strpos($upperLine, $data['tanggal_lahir']) !== false) continue;
        if (!empty($data['agama']) && strpos($upperLine, $data['agama']) !== false) continue;
        if (!empty($data['status_perkawinan']) && strpos($upperLine, $data['status_perkawinan']) !== false) continue;
        if (!empty($data['kewarganegaraan']) && strpos($upperLine, $data['kewarganegaraan']) !== false) continue;
        if (!empty($data['berlaku_hingga']) && strpos($upperLine, $data['berlaku_hingga']) !== false) continue;
        if (!empty($data['golongan_darah']) && preg_match('/(?:GOL|DARAH)\s*.*'.preg_quote($data['golongan_darah']).'/i', $upperLine)) continue;
        if (!empty($data['rt_rw']) && strpos($upperLine, $data['rt_rw']) !== false) continue;
        if (preg_match('/^(?:PROVINSI|KOTA|KABUPATEN)\b/i', $upperLine)) continue;

        // Special: if this line matches an RT/RW pattern but was missed, catch it now
        if (preg_match('/(\d{3})\s*[\\\\\/]\s*(\d{3})/', $upperLine, $m)) {
             $data['rt_rw'] = $m[1] . '/' . $m[2];
             continue;
        }

        // If it's pure CAPS (with some allowed punctuation) and survived all the above, it's a floating field!
        if ($cleanLine === strtoupper($cleanLine)) {
             if ($last_seen_label === 'ALAMAT' && empty($data['alamat'])) { $data['alamat'] = $upperLine; $last_seen_label = ''; continue; }
             if (preg_match('/(?:KEL|DESA)/', $last_seen_label) && empty($data['kel_desa'])) { $data['kel_desa'] = $upperLine; $last_seen_label = ''; continue; }
             if ($last_seen_label === 'KECAMATAN' && empty($data['kecamatan'])) { $data['kecamatan'] = $upperLine; $last_seen_label = ''; continue; }
             if ($last_seen_label === 'PEKERJAAN' && empty($data['pekerjaan'])) { $data['pekerjaan'] = $upperLine; $last_seen_label = ''; continue; }
             if ($last_seen_label === 'NAMA' && empty($data['nama'])) { $data['nama'] = $upperLine; $last_seen_label = ''; continue; }

             $potential_fields[] = $upperLine;
        }
        
        $last_seen_label = ''; // Reset when a non-label text block is passed
    }
    
    // Assignment Heuristic Strategy
    // Top-to-bottom array elements. Typically Name -> Alamat -> Kel -> Kec -> Pekerjaan
    foreach ($potential_fields as $f) {
        if (empty($data['nama']) && !preg_match('/[0-9]/', $f)) {
            $data['nama'] = $f;
            continue;
        }
        if (empty($data['alamat'])) {
            $data['alamat'] = $f;
            continue;
        }
        // In some Vision API reads, Kecamatan gets listed before Kelurahan if columns misalign
        if (empty($data['kel_desa']) && !preg_match('/[0-9]/', $f)) {
            $data['kel_desa'] = $f;
            continue;
        }
        if (empty($data['kecamatan']) && !preg_match('/[0-9]/', $f)) {
            $data['kecamatan'] = $f;
            continue;
        }
        if (empty($data['pekerjaan']) && !preg_match('/[0-9]/', $f)) {
            // Pekerjaan is often grouped at the bottom
            $data['pekerjaan'] = $f;
            continue;
        }
    }

    debugLog("Extracted Data: " . json_encode($data));
    return $data;
}

try {
    debugLog("=== Starting Google Vision OCR ===");
    
    if ($googleVisionApiKey === 'YOUR_API_KEY_HERE') {
        throw new Exception('API Key Google Vision belum diatur di server (ocr_vision.php). Silakan masukkan API Key Anda terlebih dahulu.');
    }
    
    $imageData = null;
    
    if (!empty($_POST['s3_path'])) {
        require_once __DIR__ . '/s3_upload.php';
        $s3Path = $_POST['s3_path'];
        debugLog("Using S3 Path: $s3Path");
        
        $tmpFile = downloadFromS3($s3Path);
        if (!$tmpFile) {
            throw new Exception("Gagal mengunduh gambar dari S3");
        }
        $imageData = base64_encode(file_get_contents($tmpFile));
        @unlink($tmpFile); // Clean up temp file
    } else if (isset($_FILES['identitas']) && $_FILES['identitas']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['identitas']['tmp_name'];
        debugLog("Received file size: " . filesize($tmpName) . " bytes.");
        $imageData = base64_encode(file_get_contents($tmpName));
    } else {
        throw new Exception('Gagal mendapatkan gambar KTP. Pastikan foto terpilih atau S3 path valid.');
    }
    
    // Construct the payload
    $payload = [
        "requests" => [
            [
                "image" => [
                    "content" => $imageData
                ],
                "features" => [
                    [
                        "type" => "DOCUMENT_TEXT_DETECTION"
                    ]
                ],
                "imageContext" => [
                    "languageHints" => ["id", "en"]
                ]
            ]
        ]
    ];
    
    $jsonPayload = json_encode($payload);
    
    // Init cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://vision.googleapis.com/v1/images:annotate?key=" . $googleVisionApiKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonPayload)
    ]);
    // Fix for Windows dev environments missing CA certificates
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    
    debugLog("Sending request to Google Vision API...");
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    curl_close($ch);
    
    debugLog("Google Vision Response HTTP Code: " . $httpCode);
    
    $result = json_decode($response, true);
    
    if ($httpCode !== 200) {
        $apiError = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown HTTP Error';
        throw new Exception('Google Vision API Error: ' . $apiError);
    }
    
    if (empty($result['responses'][0]['textAnnotations'])) {
        throw new Exception('Google Vision tidak mendeteksi teks apa pun pada gambar ini.');
    }
    
    // The very first textAnnotation contains the full parsed text block
    $fullText = $result['responses'][0]['textAnnotations'][0]['description'];
    debugLog("Total characters extracted by Google: " . strlen($fullText));
    
    $extractedData = extractKTPDataVision($fullText);
    
    $fieldsFound = 0;
    foreach ($extractedData as $value) {
        if (!empty($value)) $fieldsFound++;
    }
    
    if ($fieldsFound === 0) {
        throw new Exception('Teks terbaca oleh Google, tapi format KTP tidak dikenali.');
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'nik' => $extractedData['nik'],
            'nama' => $extractedData['nama'],
            'tempat_lahir' => $extractedData['tempat_lahir'],
            'tanggal_lahir' => $extractedData['tanggal_lahir'],
            'jenis_kelamin' => $extractedData['jenis_kelamin'],
            'golongan_darah' => $extractedData['golongan_darah'],
            'alamat_details' => [
                'alamat' => $extractedData['alamat'],
                'rt_rw' => $extractedData['rt_rw'],
                'kel_desa' => $extractedData['kel_desa'],
                'kecamatan' => $extractedData['kecamatan']
            ],
            'agama' => $extractedData['agama'],
            'status_perkawinan' => $extractedData['status_perkawinan'],
            'pekerjaan' => $extractedData['pekerjaan'],
            'kewarganegaraan' => $extractedData['kewarganegaraan'],
            'berlaku_hingga' => $extractedData['berlaku_hingga'],
            'provinsi' => $extractedData['provinsi'],
            'kota_kabupaten' => $extractedData['kota_kabupaten']
        ],
        'fields_found' => $fieldsFound,
        'message' => "Berhasil mengekstrak $fieldsFound data dari KTP",
        'raw_text' => $fullText
    ]);

} catch (Exception $e) {
    debugLog("ERROR: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
