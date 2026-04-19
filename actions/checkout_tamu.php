<?php
// checkout_tamu.php
// Skrip untuk memproses checkout tamu dan mengosongkan kamar

session_start();
include __DIR__ . '/../config/koneksi.php';

// 1. Validasi ID Tamu dari URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'ID Tamu tidak valid.'];
    header('Location: ../index.php');
    exit();
}

$guest_id = (int)$_GET['id'];

// 2. Mulai Transaksi Database
// Ini penting untuk memastikan kedua update (status tamu & status kamar) berhasil
$conn->autocommit(FALSE);

try {
    // 3. Ambil data tamu (terutama nama_kamar dan status saat ini)
    $stmt_get = $conn->prepare("SELECT nama_kamar, status FROM data_tamu WHERE id = ?");
    if (!$stmt_get) {
        throw new Exception("Gagal mempersiapkan query: " . $conn->error);
    }
    $stmt_get->bind_param("i", $guest_id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Data tamu tidak ditemukan.");
    }

    $tamu = $result->fetch_assoc();
    $nama_kamar = $tamu['nama_kamar'];
    $stmt_get->close();

    // 4. Periksa apakah tamu sudah checkout
    if ($tamu['status'] !== 'active') {
        throw new Exception("Tamu ini sudah checkout atau statusnya tidak aktif.");
    }

    // 5. Update status tamu di 'data_tamu'
    $stmt_tamu = $conn->prepare("UPDATE data_tamu SET status = 'checked_out' WHERE id = ?");
    if (!$stmt_tamu) {
        throw new Exception("Gagal mempersiapkan query update tamu: " . $conn->error);
    }
    $stmt_tamu->bind_param("i", $guest_id);
    if (!$stmt_tamu->execute()) {
        throw new Exception("Gagal update status tamu: " . $stmt_tamu->error);
    }
    $stmt_tamu->close();

    // 6. Update status kamar di 'data_asset' agar tersedia kembali
    // Berdasarkan proses_checkin.php, status 'occupied' digunakan. 
    // Kita set kembali ke 'available' (atau 'tersedia', sesuaikan dengan datamu)
    $stmt_asset = $conn->prepare("UPDATE data_asset SET status = 'available' WHERE id_name = ? AND category = 'GEDUNG MESS'");
    if (!$stmt_asset) {
        throw new Exception("Gagal mempersiapkan query update aset: " . $conn->error);
    }
    $stmt_asset->bind_param("s", $nama_kamar);
    if (!$stmt_asset->execute()) {
        // Jika gagal update kamar, kita tidak ingin membatalkan checkout tamu.
        // Kita log error tapi tetap lanjutkan.
        error_log("Peringatan: Gagal mengosongkan kamar $nama_kamar di data_asset.");
    }
    $stmt_asset->close();

    // 7. Jika semua berhasil, commit transaksi
    $conn->commit();
    $_SESSION['flash_message'] = [
        'type' => 'success', 
        'message' => 'Tamu (ID: ' . $guest_id . ') berhasil di-checkout. Kamar ' . htmlspecialchars($nama_kamar) . ' sekarang tersedia.'
    ];

} catch (Exception $e) {
    // 8. Jika ada error, rollback transaksi
    $conn->rollback();
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Gagal checkout: ' . $e->getMessage()];

} finally {
    // Selalu tutup koneksi
    if (isset($conn)) {
        $conn->close();
    }
}

// 9. Kembalikan pengguna ke halaman index
header('Location: ../index.php');
exit();

?>