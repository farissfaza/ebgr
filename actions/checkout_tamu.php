<?php
// checkout_tamu.php — Process guest checkout (Raspi Integration Version)
session_start();
include __DIR__ . '/../config/koneksi.php';

// 1. Validate ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'ID Tamu tidak valid.'];
    header('Location: ../index.php');
    exit();
}

$checkin_id = (int)$_GET['id'];

// 2. Start transaction
$conn->autocommit(FALSE);

try {
    // 3. Get check-in data with room info
    $stmt_get = $conn->prepare("
        SELECT cl.status, i.nama_kamar 
        FROM checkin_tamu_log cl
        JOIN inapan_ms i ON cl.id_inapan = i.id_inapan
        WHERE cl.checkin_tamu_id = ?
    ");
    if (!$stmt_get) throw new Exception("Gagal mempersiapkan query: " . $conn->error);
    
    $stmt_get->bind_param("i", $checkin_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();

    if ($result->num_rows === 0) throw new Exception("Data tamu tidak ditemukan.");

    $data = $result->fetch_assoc();
    $nama_kamar = $data['nama_kamar'];
    $stmt_get->close();

    // 4. Check if already checked out
    if ($data['status'] !== 'active') {
        throw new Exception("Tamu ini sudah checkout atau statusnya tidak aktif.");
    }

    // 5. Update status in checkin_tamu_log
    $stmt_update = $conn->prepare("UPDATE checkin_tamu_log SET status = 'checked_out' WHERE checkin_tamu_id = ?");
    if (!$stmt_update) throw new Exception("Gagal mempersiapkan query update: " . $conn->error);
    
    $stmt_update->bind_param("i", $checkin_id);
    if (!$stmt_update->execute()) throw new Exception("Gagal update status: " . $stmt_update->error);
    $stmt_update->close();

    // 6. Free the room in data_asset
    $stmt_asset = $conn->prepare("UPDATE data_asset SET status = 'available' WHERE id_name = ? AND category = 'GEDUNG MESS'");
    if ($stmt_asset) {
        $stmt_asset->bind_param("s", $nama_kamar);
        $stmt_asset->execute();
        $stmt_asset->close();
    }

    // 7. Commit
    $conn->commit();
    $_SESSION['flash_message'] = [
        'type' => 'success', 
        'message' => 'Tamu (ID: ' . $checkin_id . ') berhasil di-checkout. Kamar ' . htmlspecialchars($nama_kamar) . ' sekarang tersedia.'
    ];

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Gagal checkout: ' . $e->getMessage()];

} finally {
    if (isset($conn)) $conn->close();
}

header('Location: ../index.php');
exit();
?>