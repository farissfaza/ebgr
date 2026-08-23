<?php
// proses_checkin.php — Process check-in form submission (Raspi Integration Version)
session_start();
include __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../services/s3_upload.php';

// ========== HELPER FUNCTIONS ==========
function sanitizeInput($input) {
    if ($input === null) return null;
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

function validateNIK($nik) {
    if (!preg_match('/^\d{16}$/', $nik)) return false;
    $province = substr($nik, 0, 2);
    if ($province < 11 || $province > 94) return false;
    $day = (int)substr($nik, 6, 2);
    $month = (int)substr($nik, 8, 2);
    if ($day > 40) $day -= 40;
    if ($day < 1 || $day > 31) return false;
    if ($month < 1 || $month > 12) return false;
    return true;
}

function validatePhoneNumber($phone) {
    $phone = preg_replace('/\D/', '', $phone);
    if (preg_match('/^0\d{9,12}$/', $phone)) return $phone;
    if (preg_match('/^62\d{9,12}$/', $phone)) return '0' . substr($phone, 2);
    return false;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ========== MAIN PROCESSING ==========
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $response = [
        'success' => false,
        'message' => '',
        'errors' => [],
        'data' => []
    ];
    
    try {
        $conn->autocommit(FALSE);
        
        // ========== COLLECT DATA ==========
        $input_method = sanitizeInput($_POST['input_method'] ?? 'ocr');
        $rfid_uid = sanitizeInput($_POST['rfid_uid'] ?? '');
        $checkin_session = sanitizeInput($_POST['checkin_session'] ?? '');
        
        // Guest data
        $nik = sanitizeInput($_POST['nik'] ?? null);
        $nama = sanitizeInput($_POST['nama'] ?? null);
        $tempat_lahir = sanitizeInput($_POST['tempat_lahir'] ?? null);
        $tanggal_lahir = $_POST['tanggal_lahir'] ?? null;
        $jenis_kelamin = sanitizeInput($_POST['jenis_kelamin'] ?? null);
        $no_handphone = sanitizeInput($_POST['no_handphone'] ?? null);
        $email = sanitizeInput($_POST['email'] ?? null);
        $alamat = sanitizeInput($_POST['alamat'] ?? null);
        $rt_rw = sanitizeInput($_POST['rt_rw'] ?? null);
        $kelurahan_desa = sanitizeInput($_POST['kelurahan_desa'] ?? null);
        $kecamatan = sanitizeInput($_POST['kecamatan'] ?? null);
        $kota_kabupaten = sanitizeInput($_POST['kota_kabupaten'] ?? null);
        $agama = sanitizeInput($_POST['agama'] ?? null);
        $pekerjaan = sanitizeInput($_POST['pekerjaan'] ?? null);
        $akun_google = sanitizeInput($_POST['akun_google'] ?? null);
        $tipe_tamu = sanitizeInput($_POST['tipe_tamu'] ?? null);
        $nama_instansi = sanitizeInput($_POST['nama_instansi'] ?? null);
        
        // Stay details
        $tanggal_check_in = $_POST['tanggal_check_in'] ?? null;
        $tanggal_check_out = $_POST['tanggal_check_out'] ?? null;
        $nama_kamar = sanitizeInput($_POST['nama_kamar'] ?? null);
        $harga_kamar = isset($_POST['harga_kamar']) ? (int)$_POST['harga_kamar'] : 0;
        $detail_acara = sanitizeInput($_POST['detail_acara'] ?? null);
        $nama_kelas = sanitizeInput($_POST['nama_kelas'] ?? null);
        $harga_kelas = isset($_POST['harga_kelas']) ? (int)$_POST['harga_kelas'] : 0;
        
        // S3 paths (from Raspi camera capture)
        $ocr_path = sanitizeInput($_POST['ocr_s3_path'] ?? null);
        $pembayaran_path = sanitizeInput($_POST['pembayaran_s3_path'] ?? null);
        
        // ========== VALIDATION ==========
        $errors = [];
        
        if (empty($nama)) $errors[] = 'Nama tamu harus diisi';
        
        if (empty($no_handphone)) {
            $errors[] = 'Nomor HP harus diisi';
        } else {
            $validatedPhone = validatePhoneNumber($no_handphone);
            if (!$validatedPhone) {
                $errors[] = 'Format nomor HP tidak valid';
            } else {
                $no_handphone = $validatedPhone;
            }
        }
        
        if (empty($email)) {
            $errors[] = 'Email harus diisi';
        } else if (!validateEmail($email)) {
            $errors[] = 'Format email tidak valid';
        }
        
        if (!empty($nik) && !validateNIK($nik)) {
            $errors[] = 'Format NIK tidak valid (harus 16 digit)';
        }
        
        if (empty($tanggal_check_in)) $errors[] = 'Tanggal check-in harus diisi';
        if (empty($tanggal_check_out)) $errors[] = 'Tanggal check-out harus diisi';
        
        if (!empty($tanggal_check_in) && !empty($tanggal_check_out)) {
            $checkIn = strtotime($tanggal_check_in);
            $checkOut = strtotime($tanggal_check_out);
            if ($checkOut <= $checkIn) $errors[] = 'Tanggal check-out harus setelah tanggal check-in';
            $nights = ceil(($checkOut - $checkIn) / (60 * 60 * 24));
            $total_harga_kamar = $harga_kamar * $nights;
        } else {
            $nights = 0;
            $total_harga_kamar = 0;
        }
        
        if (empty($nama_kamar)) $errors[] = 'Nama kamar harus dipilih';
        if (empty($tipe_tamu)) $errors[] = 'Tipe tamu harus dipilih';
        
        if (($tipe_tamu === 'Instansi' || $tipe_tamu === 'Perusahaan') && empty($nama_instansi)) {
            $errors[] = 'Nama instansi/perusahaan harus diisi untuk tipe tamu ' . $tipe_tamu;
        }
        
        if (empty($pembayaran_path)) $errors[] = 'Bukti pembayaran harus diunggah atau difoto terlebih dahulu';
        
        if (!empty($errors)) {
            throw new Exception('Validasi gagal: ' . implode(', ', $errors));
        }
        
        $total_amount = $total_harga_kamar + $harga_kelas;
        
        // ========== 1. INSERT/UPDATE tamu_ms ==========
        if (!empty($nik)) {
            // Check if guest exists by NIK
            $checkTamu = $conn->prepare("SELECT id_tamu FROM tamu_ms WHERE nik = ?");
            $checkTamu->bind_param("s", $nik);
            $checkTamu->execute();
            $tamuResult = $checkTamu->get_result();
            
            if ($tamuResult->num_rows > 0) {
                // Update existing guest
                $id_tamu = $tamuResult->fetch_assoc()['id_tamu'];
                $updateTamu = $conn->prepare("
                    UPDATE tamu_ms SET 
                        nama = ?, tempat_lahir = ?, tanggal_lahir = ?, jenis_kelamin = ?,
                        no_handphone = ?, email = ?, alamat = ?, rt_rw = ?, kelurahan_desa = ?,
                        kecamatan = ?, kota_kabupaten = ?, agama = ?, pekerjaan = ?,
                        akun_google = ?, tipe_tamu = ?, nama_instansi = ?, updated_at = NOW()
                    WHERE id_tamu = ?
                ");
                $updateTamu->bind_param("ssssssssssssssssi",
                    $nama, $tempat_lahir, $tanggal_lahir, $jenis_kelamin,
                    $no_handphone, $email, $alamat, $rt_rw, $kelurahan_desa,
                    $kecamatan, $kota_kabupaten, $agama, $pekerjaan,
                    $akun_google, $tipe_tamu, $nama_instansi, $id_tamu
                );
                $updateTamu->execute();
                $updateTamu->close();
            } else {
                // Insert new guest
                $insertTamu = $conn->prepare("
                    INSERT INTO tamu_ms (nik, nama, tempat_lahir, tanggal_lahir, jenis_kelamin,
                        no_handphone, email, alamat, rt_rw, kelurahan_desa, kecamatan,
                        kota_kabupaten, agama, pekerjaan, akun_google, tipe_tamu, nama_instansi)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insertTamu->bind_param("sssssssssssssssss",
                    $nik, $nama, $tempat_lahir, $tanggal_lahir, $jenis_kelamin,
                    $no_handphone, $email, $alamat, $rt_rw, $kelurahan_desa,
                    $kecamatan, $kota_kabupaten, $agama, $pekerjaan,
                    $akun_google, $tipe_tamu, $nama_instansi
                );
                $insertTamu->execute();
                $id_tamu = $conn->insert_id;
                $insertTamu->close();
            }
            $checkTamu->close();
        } else {
            // No NIK provided — always insert as new
            $insertTamu = $conn->prepare("
                INSERT INTO tamu_ms (nama, tempat_lahir, tanggal_lahir, jenis_kelamin,
                    no_handphone, email, alamat, rt_rw, kelurahan_desa, kecamatan,
                    kota_kabupaten, agama, pekerjaan, akun_google, tipe_tamu, nama_instansi)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insertTamu->bind_param("ssssssssssssssss",
                $nama, $tempat_lahir, $tanggal_lahir, $jenis_kelamin,
                $no_handphone, $email, $alamat, $rt_rw, $kelurahan_desa,
                $kecamatan, $kota_kabupaten, $agama, $pekerjaan,
                $akun_google, $tipe_tamu, $nama_instansi
            );
            $insertTamu->execute();
            $id_tamu = $conn->insert_id;
            $insertTamu->close();
        }
        
        // ========== 2. INSERT inapan_ms ==========
        $insertInapan = $conn->prepare("
            INSERT INTO inapan_ms (tanggal_check_in, tanggal_check_out, nama_kamar, harga_kamar,
                detail_acara, nama_kelas, harga_kelas)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insertInapan->bind_param("sssissi",
            $tanggal_check_in, $tanggal_check_out, $nama_kamar, $harga_kamar,
            $detail_acara, $nama_kelas, $harga_kelas
        );
        $insertInapan->execute();
        $id_inapan = $conn->insert_id;
        $insertInapan->close();
        
        // ========== 3. CALCULATE AVERAGE TEMPERATURE ==========
        $suhu_rata_rata = null;
        if (!empty($checkin_session)) {
            $tempStmt = $conn->prepare("SELECT AVG(temperature) as avg_temp FROM temperature_sessions WHERE checkin_session = ?");
            $tempStmt->bind_param("s", $checkin_session);
            $tempStmt->execute();
            $tempResult = $tempStmt->get_result();
            if ($tempRow = $tempResult->fetch_assoc()) {
                $suhu_rata_rata = $tempRow['avg_temp'] ? round($tempRow['avg_temp'], 2) : null;
            }
            $tempStmt->close();
        }
        
        // ========== 4. CALCULATE AVERAGE LATENCY ==========
        $latency_ms = null;
        if (!empty($checkin_session)) {
            $latencyStmt = $conn->prepare("SELECT AVG(latency_ms) as avg_latency FROM raspi_tasks WHERE checkin_session = ? AND status = 'completed' AND latency_ms IS NOT NULL");
            $latencyStmt->bind_param("s", $checkin_session);
            $latencyStmt->execute();
            $latencyResult = $latencyStmt->get_result();
            if ($latencyRow = $latencyResult->fetch_assoc()) {
                $latency_ms = $latencyRow['avg_latency'] ? round($latencyRow['avg_latency']) : null;
            }
            $latencyStmt->close();
        }
        
        // ========== 4b. CALCULATE TOTAL DURATION ==========
        $total_duration_sec = null;
        if (!empty($checkin_session)) {
            $durationStmt = $conn->prepare("SELECT TIMESTAMPDIFF(SECOND, MIN(created_at), NOW()) as total_sec FROM raspi_tasks WHERE checkin_session = ?");
            $durationStmt->bind_param("s", $checkin_session);
            $durationStmt->execute();
            if ($row = $durationStmt->get_result()->fetch_assoc()) {
                $total_duration_sec = $row['total_sec'];
            }
            $durationStmt->close();
        }
        
        // ========== 5. INSERT checkin_tamu_log ==========
        $insertLog = $conn->prepare("
            INSERT INTO checkin_tamu_log (id_tamu, nik, rfid_uid, id_inapan, ocr_path,
                pembayaran_path, suhu_rata_rata, input_method, total_amount, latency_ms, total_duration_sec, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $insertLog->bind_param("ississdsiii",
            $id_tamu, $nik, $rfid_uid, $id_inapan, $ocr_path,
            $pembayaran_path, $suhu_rata_rata, $input_method, $total_amount, $latency_ms, $total_duration_sec
        );
        
        if (!$insertLog->execute()) {
            throw new Exception("Gagal menyimpan log check-in: " . $insertLog->error);
        }
        $checkin_tamu_id = $conn->insert_id;
        $insertLog->close();
        
        // ========== 6. SAVE RFID MAPPING (if RFID was used) ==========
        if (!empty($rfid_uid)) {
            $checkMapping = $conn->prepare("SELECT id FROM rfid_mapping WHERE uid = ?");
            $checkMapping->bind_param("s", $rfid_uid);
            $checkMapping->execute();
            
            if ($checkMapping->get_result()->num_rows > 0) {
                $updateMapping = $conn->prepare("
                    UPDATE rfid_mapping SET nik = ?, nama = ?, tempat_lahir = ?, tanggal_lahir = ?,
                        jenis_kelamin = ?, alamat = ?, rt_rw = ?, kel_desa = ?, kecamatan = ?,
                        kota_kabupaten = ?, agama = ?, pekerjaan = ?, no_hp = ?, email = ?,
                        last_used = NOW(), usage_count = usage_count + 1
                    WHERE uid = ?
                ");
                $updateMapping->bind_param("sssssssssssssss",
                    $nik, $nama, $tempat_lahir, $tanggal_lahir, $jenis_kelamin,
                    $alamat, $rt_rw, $kelurahan_desa, $kecamatan, $kota_kabupaten,
                    $agama, $pekerjaan, $no_handphone, $email, $rfid_uid
                );
                $updateMapping->execute();
                $updateMapping->close();
            } else {
                $insertMapping = $conn->prepare("
                    INSERT INTO rfid_mapping (uid, nik, nama, tempat_lahir, tanggal_lahir,
                        jenis_kelamin, alamat, rt_rw, kel_desa, kecamatan, kota_kabupaten,
                        agama, pekerjaan, no_hp, email, last_used)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $insertMapping->bind_param("sssssssssssssss",
                    $rfid_uid, $nik, $nama, $tempat_lahir, $tanggal_lahir,
                    $jenis_kelamin, $alamat, $rt_rw, $kelurahan_desa, $kecamatan,
                    $kota_kabupaten, $agama, $pekerjaan, $no_handphone, $email
                );
                $insertMapping->execute();
                $insertMapping->close();
            }
            $checkMapping->close();
        }
        
        // ========== 7. UPDATE ROOM STATUS ==========
        $tableCheck = $conn->query("SHOW TABLES LIKE 'data_asset'");
        if ($tableCheck->num_rows > 0) {
            $updateRoom = $conn->prepare("UPDATE data_asset SET status = 'occupied' WHERE id_name = ? AND category = 'GEDUNG MESS'");
            if ($updateRoom) {
                $updateRoom->bind_param("s", $nama_kamar);
                $updateRoom->execute();
                $updateRoom->close();
            }
        }
        
        // Commit
        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = 'Check-in berhasil!';
        $response['data'] = [
            'checkin_tamu_id' => $checkin_tamu_id,
            'id_tamu' => $id_tamu,
            'id_inapan' => $id_inapan,
            'nama' => $nama,
            'nama_kamar' => $nama_kamar,
            'total_amount' => $total_amount,
            'nights' => $nights,
            'suhu_rata_rata' => $suhu_rata_rata,
            'input_method' => $input_method
        ];
        
        error_log("Check-in success: ID=$checkin_tamu_id, Method=$input_method, RFID=$rfid_uid");
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log('Check-in error: ' . $e->getMessage());
        $response['success'] = false;
        $response['message'] = $e->getMessage();
        $response['errors'] = $errors ?? [];
    }
    
    $conn->close();
    
    // Store flash message
    if ($response['success']) {
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => $response['message']];
        unset($_SESSION['form_old']);
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Gagal check-in: ' . $response['message']];
        $_SESSION['form_old'] = $_POST;
    }
    
    // AJAX or regular redirect
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        header('Location: ' . ($response['success'] ? '../index.php' : '../form_checkin.php'));
        exit();
    }
    
} else {
    header('Location: ../form_checkin.php');
    exit();
}
?>