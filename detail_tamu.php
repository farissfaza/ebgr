<?php
// detail_tamu.php
// Halaman untuk menampilkan rincian lengkap satu tamu

// 1. Mulai session
session_start();

// 2. Sertakan file koneksi database
include __DIR__ . '/config/koneksi.php';

// --- FUNGSI HELPER ---

require_once __DIR__ . '/services/s3_upload.php';

/**
 * Menampilkan file upload (S3 link)
 * @param string $path S3 Key
 * @return string HTML untuk ditampilkan
 */
function displayFile($path) {
    if (empty($path)) {
        return '<span class="text-muted">Tidak diupload</span>';
    }
    
    $s3Url = getS3Url($path);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
        return '<a href="' . $s3Url . '" target="_blank" title="Klik untuk perbesar">
                    <img src="' . $s3Url . '" class="img-thumbnail" style="max-width: 200px; max-height: 150px;" alt="Preview Dokumen">
                </a>';
    } elseif ($ext === 'pdf') {
        return '<a href="' . $s3Url . '" target="_blank" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-file-earmark-pdf"></i> Lihat PDF
                </a>';
    } else {
        return '<a href="' . $s3Url . '" target="_blank">Lihat File</a>';
    }
}

/**
 * Format angka menjadi format Rupiah
 * @param int $angka Angka yang akan diformat
 * @return string String dalam format "Rp 123.456"
 */
function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// 3. Validasi ID Tamu dari URL
$guest_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $guest_id = (int)$_GET['id'];
} else {
    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'ID Tamu tidak valid.'];
    header('Location: index.php');
    exit();
}

// 4. Ambil Data Tamu dari Database
$sql = "SELECT 
            t.nama as nama_tamu, t.nik, t.tempat_lahir, t.tanggal_lahir, t.jenis_kelamin, 
            t.no_handphone as no_hp, t.email, t.akun_google, t.agama, t.pekerjaan, 
            t.alamat, t.rt_rw, t.kelurahan_desa as kel_desa, t.kecamatan, t.kota_kabupaten, 
            t.tipe_tamu, t.nama_instansi, 
            i.nama_kamar, i.tanggal_check_in, i.tanggal_check_out, 
            i.detail_acara as acara_judul_diklat, i.nama_kelas, i.harga_kamar, i.harga_kelas, 
            cl.status, cl.input_method, cl.rfid_uid, cl.total_amount, 
            cl.ocr_path as upload_identitas, cl.pembayaran_path as upload_pembayaran, cl.suhu_rata_rata 
        FROM checkin_tamu_log cl 
        JOIN tamu_ms t ON cl.id_tamu = t.id_tamu 
        JOIN inapan_ms i ON cl.id_inapan = i.id_inapan 
        WHERE cl.checkin_tamu_id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $guest_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Data tamu dengan ID tersebut tidak ditemukan.'];
    header('Location: index.php');
    exit();
}

$tamu = $result->fetch_assoc();

$stmt->close();
$conn->close();

// 5. Sertakan header UI
include 'UI/header.php';

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Tamu - <?php echo htmlspecialchars($tamu['nama_tamu']); ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        .detail-list dt {
            font-weight: 600;
            color: #555;
        }
        .detail-list dd {
            color: #222;
        }
        .card-header-icon {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
        /* Modern Gradient Background */
        .glass-dashboard-bg {
            background: rgba(255, 255, 255, 0.9);
            min-height: calc(100vh - 60px);
            padding: 2rem 1.5rem;
            position: relative;
        }
        .glass-dashboard-bg::before,
        .glass-dashboard-bg::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            z-index: 0;
            filter: blur(60px);
            opacity: 0.6;
            pointer-events: none;
        }
        .glass-dashboard-bg::before { background: #ff9a9e; width: 300px; height: 300px; top: -50px; left: -50px; }
        .glass-dashboard-bg::after { background: #fecfef; width: 400px; height: 400px; bottom: -100px; right: -50px; }

        .content-container { position: relative; z-index: 1; }

        /* Liquid Glass Effect for Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.45);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .glass-card:hover { box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.15); transform: translateY(-2px); }

        .glass-header {
            background: rgba(255, 255, 255, 0.3);
            border-bottom: 1px solid rgba(255, 255, 255, 0.4);
            padding: 1.25rem 1.5rem;
        }
    </style>
</head>
<body>

<div id="wrapper">
    
    <?php include 'UI/sidebar.php'; // Sertakan sidebar ?>
    
    <div class="main-content">
        
        <?php include 'UI/navbar.php'; // Sertakan navbar ?>
        
        <main class="content glass-dashboard-bg">
            <div class="container-fluid p-0 content-container">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="h3"><strong>Detail Tamu</strong></h1>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Kembali ke Daftar Tamu
                    </a>
                </div>

                <div class="row">
                    <div class="col-lg-7">
                        
                        <div class="glass-card mb-4">
                            <div class="glass-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-person-badge card-header-icon"></i>
                                    Data Pribadi: <?php echo htmlspecialchars($tamu['nama_tamu']); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <dl class="row detail-list">
                                    <dt class="col-sm-4">Nama Lengkap</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($tamu['nama_tamu']); ?></dd>

                                    <dt class="col-sm-4">NIK</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($tamu['nik'] ?? '-'); ?></dd>
                                    
                                    <dt class="col-sm-4">Tempat, Tgl. Lahir</dt>
                                    <dd class="col-sm-8">
                                        <?php 
                                        echo htmlspecialchars($tamu['tempat_lahir'] ?? '-'); 
                                        if (!empty($tamu['tanggal_lahir'])) {
                                            echo ', ' . date('d M Y', strtotime($tamu['tanggal_lahir']));
                                        }
                                        ?>
                                    </dd>

                                    <dt class="col-sm-4">Jenis Kelamin</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($tamu['jenis_kelamin'] ?? '-'); ?></dd>
                                    
                                    <dt class="col-sm-4">No. Handphone</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($tamu['no_hp'] ?? '-'); ?></dd>
                                    
                                    <dt class="col-sm-4">Email</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($tamu['email'] ?? '-'); ?></dd>
                                    
                                    <dt class="col-sm-4">Akun Google</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($tamu['akun_google'] ?? '-'); ?></dd>

                                    <dt class="col-sm-4">Agama</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($tamu['agama'] ?? '-'); ?></dd>

                                    <dt class="col-sm-4">Pekerjaan</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($tamu['pekerjaan'] ?? '-'); ?></dd>
                                </dl>
                            </div>
                        </div>

                        <div class="glass-card mb-4">
                            <div class="glass-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-geo-alt card-header-icon"></i>
                                    Alamat
                                </h5>
                            </div>
                            <div class="card-body">
                                <dl class="row detail-list">
                                    <dt class="col-sm-4">Alamat Lengkap</dt>
                                    <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($tamu['alamat'] ?? '-')); ?></dd>

                                    <dt class="col-sm-4">RT/RW</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($tamu['rt_rw'] ?? '-'); ?></dd>

                                    <dt class="col-sm-4">Kelurahan/Desa</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($tamu['kel_desa'] ?? '-'); ?></dd>
                                    
                                    <dt class="col-sm-4">Kecamatan</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($tamu['kecamatan'] ?? '-'); ?></dd>

                                    <dt class="col-sm-4">Kota/Kabupaten</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($tamu['kota_kabupaten'] ?? '-'); ?></dd>
                                </dl>
                            </div>
                        </div>

                        <div class="glass-card mb-4">
                            <div class="glass-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-hdd-stack card-header-icon"></i>
                                    Data Sistem
                                </h5>
                            </div>
                            <div class="card-body">
                                <dl class="row detail-list">
                                    <dt class="col-sm-5">Status</dt>
                                    <dd class="col-sm-7">
                                        <?php
                                        // Logika Badge Status yang Diperbarui
                                        switch ($tamu['status']) {
                                            case 'active':
                                                echo '<span class="badge bg-success">Aktif</span>';
                                                break;
                                            case 'cancelled':
                                                echo '<span class="badge bg-danger">Dibatalkan</span>';
                                                break;
                                            case 'checkout':
                                            default:
                                                echo '<span class="badge bg-secondary">Selesai</span>';
                                                break;
                                        }
                                        ?>
                                        </dd>
                                    
                                    <dt class="col-sm-5">Metode Input</dt>
                                    <dd class="col-sm-7">
                                        <?php 
                                        $method = $tamu['input_method'] ?? 'manual';
                                        if ($method === 'ocr') {
                                            echo 'Scan KTP';
                                        } elseif ($method === 'rfid') {
                                            echo 'Scan e-KTP (RFID)';
                                        } else {
                                            echo 'Input Manual';
                                        }
                                        ?>
                                    </dd>
                                    
                                    <dt class="col-sm-5">RFID UID</dt>
                                    <dd class="col-sm-7"><?php echo htmlspecialchars($tamu['rfid_uid'] ?? '-'); ?></dd>
                                    
                                    <dt class="col-sm-5">Suhu Rata-rata</dt>
                                    <dd class="col-sm-7"><?php echo $tamu['suhu_rata_rata'] ? htmlspecialchars($tamu['suhu_rata_rata']) . ' &deg;C' : '-'; ?></dd>
                                </dl>

                                <?php if ($tamu['status'] === 'active'): ?>
                                    <hr class="my-3">
                                    <a href="actions/checkout_tamu.php?id=<?php echo $guest_id; ?>" 
                                       class="btn btn-warning w-100" 
                                       onclick="return confirm('Apakah Anda yakin ingin checkout tamu ini? Kamar akan dikosongkan.')">
                                        <i class="bi bi-box-arrow-right me-2"></i> Checkout Tamu Ini
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>

                    <div class="col-lg-5">

                        <div class="glass-card mb-4">
                            <div class="glass-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-calendar-check card-header-icon"></i>
                                    Detail Menginap
                                </h5>
                            </div>
                            <div class="card-body">
                                <dl class="row detail-list">
                                    <dt class="col-sm-5">Kamar</dt>
                                    <dd class="col-sm-7"><strong><?php echo htmlspecialchars($tamu['nama_kamar']); ?></strong></dd>

                                    <dt class="col-sm-5">Check-In</dt>
                                    <dd class="col-sm-7"><?php echo date('d M Y', strtotime($tamu['tanggal_check_in'])); ?></dd>

                                    <dt class="col-sm-5">Check-Out</dt>
                                    <dd class="col-sm-7"><?php echo date('d M Y', strtotime($tamu['tanggal_check_out'])); ?></dd>
                                    
                                    <dt class="col-sm-5">Tipe Tamu</dt>
                                    <dd class="col-sm-7"><?php echo htmlspecialchars($tamu['tipe_tamu']); ?></dd>

                                    <?php if (!empty($tamu['nama_instansi'])): ?>
                                    <dt class="col-sm-5">Nama Instansi</dt>
                                    <dd class="col-sm-7"><?php echo htmlspecialchars($tamu['nama_instansi']); ?></dd>
                                    <?php endif; ?>

                                    <dt class="col-sm-5">Acara/Diklat</dt>
                                    <dd class="col-sm-7"><?php echo htmlspecialchars($tamu['acara_judul_diklat'] ?? '-'); ?></dd>
                                    
                                    <dt class="col-sm-5">Kelas</dt>
                                    <dd class="col-sm-7"><?php echo htmlspecialchars($tamu['nama_kelas'] ?? '-'); ?></dd>
                                </dl>
                            </div>
                        </div>

                        <div class="glass-card mb-4">
                            <div class="glass-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-wallet2 card-header-icon"></i>
                                    Pembayaran
                                </h5>
                            </div>
                            <div class="card-body">
                                <dl class="row detail-list">
                                    <dt class="col-sm-5">Harga Kamar</dt>
                                    <dd class="col-sm-7"><?php echo formatRupiah($tamu['harga_kamar'] ?? 0); ?></dd>

                                    <dt class="col-sm-5">Harga Kelas</dt>
                                    <dd class="col-sm-7"><?php echo formatRupiah($tamu['harga_kelas'] ?? 0); ?></dd>

                                    <dt class="col-sm-5">Total Bayar</dt>
                                    <dd class="col-sm-7"><strong class="h5 text-primary"><?php echo formatRupiah($tamu['total_amount'] ?? 0); ?></strong></dd>
                                </dl>

                                <hr class="my-3">
                                <h6 class="text-muted mb-2">Cetak Kwitansi</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <a href="actions/generate_kwitansi_pdf.php?id=<?php echo $guest_id; ?>&preview=1" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye me-1"></i> Preview PDF
                                    </a>
                                    <a href="actions/generate_kwitansi_pdf.php?id=<?php echo $guest_id; ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="bi bi-file-earmark-pdf me-1"></i> Download PDF
                                    </a>
                                    <a href="actions/generate_kwitansi.php?id=<?php echo $guest_id; ?>" 
                                       class="btn btn-sm btn-success">
                                        <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download Excel
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="glass-card mb-4">
                            <div class="glass-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-file-earmark-text card-header-icon"></i>
                                    Dokumen
                                </h5>
                            </div>
                            <div class="card-body">
                                <dl class="detail-list">
                                    <dt>Foto Identitas (KTP/SIM)</dt>
                                    <dd class="mt-2"><?php echo displayFile($tamu['upload_identitas']); ?></dd>

                                    <dt class="mt-3">Bukti Pembayaran</dt>
                                    <dd class="mt-2"><?php echo displayFile($tamu['upload_pembayaran']); ?></dd>
                                </dl>
                            </div>
                        </div>

                    </div>
                </div> </div>
        </main>
        
    </div>
</div>

<?php include 'UI/settings.php'; // Sertakan settings ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<?php include 'UI/footer.php'; // Sertakan footer ?>

</body>
</html>