<?php
// generate_kwitansi_pdf.php - Generate receipt in PDF format with preview option
session_start();
include __DIR__ . '/../config/koneksi.php';

// Check if mPDF is installed via Composer
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require $composerAutoload;
} else {
    // If mPDF is not installed, show installation instructions
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>mPDF Library Not Found</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .error { background: #f8d7da; padding: 20px; border-radius: 5px; border: 1px solid #f5c6cb; }
            code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
            pre { background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto; }
        </style>
    </head>
    <body>
        <div class="error">
            <h2>mPDF Library Not Found</h2>
            <p>The mPDF library is required to generate PDF receipts but is not installed.</p>
            <h3>Installation Instructions:</h3>
            <ol>
                <li>Open terminal/command prompt in your project directory</li>
                <li>Run the following command:</li>
            </ol>
            <pre>composer require mpdf/mpdf</pre>
            <p>If you don't have Composer installed, download it from <a href="https://getcomposer.org/" target="_blank">getcomposer.org</a></p>
            <p><a href="javascript:history.back()">← Go Back</a></p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

use Mpdf\Mpdf;

// Function to convert number to Indonesian words
function terbilang($x) {
    $bilangan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    
    if ($x < 12) {
        return $bilangan[$x];
    } else if ($x < 20) {
        return $bilangan[$x - 10] . ' belas';
    } else if ($x < 100) {
        $hasil_bagi = (int)($x / 10);
        $hasil_mod = $x % 10;
        return trim(sprintf('%s puluh %s', $bilangan[$hasil_bagi], $bilangan[$hasil_mod]));
    } else if ($x < 200) {
        return sprintf('seratus %s', terbilang($x - 100));
    } else if ($x < 1000) {
        $hasil_bagi = (int)($x / 100);
        $hasil_mod = $x % 100;
        return trim(sprintf('%s ratus %s', $bilangan[$hasil_bagi], terbilang($hasil_mod)));
    } else if ($x < 2000) {
        return trim(sprintf('seribu %s', terbilang($x - 1000)));
    } else if ($x < 1000000) {
        $hasil_bagi = (int)($x / 1000);
        $hasil_mod = $x % 1000;
        return trim(sprintf('%s ribu %s', terbilang($hasil_bagi), terbilang($hasil_mod)));
    } else if ($x < 1000000000) {
        $hasil_bagi = (int)($x / 1000000);
        $hasil_mod = $x % 1000000;
        return trim(sprintf('%s juta %s', terbilang($hasil_bagi), terbilang($hasil_mod)));
    } else {
        return 'Angka terlalu besar';
    }
}

// Format tanggal Indonesia
function formatTanggalIndonesia($date) {
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $tanggal = date('d', strtotime($date));
    $bulan_num = (int)date('m', strtotime($date));
    $tahun = date('Y', strtotime($date));
    
    return $tanggal . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

// Get parameters
$reservation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$preview = isset($_GET['preview']) ? true : false;

if ($reservation_id == 0) {
    die('Invalid reservation ID');
}

// Fetch guest data
$sql = "SELECT 
            t.nama as nama_tamu,
            i.tanggal_check_in, i.tanggal_check_out,
            i.nama_kamar, i.harga_kamar, i.nama_kelas, i.harga_kelas,
            i.detail_acara as acara_judul_diklat,
            DATEDIFF(i.tanggal_check_out, i.tanggal_check_in) as jumlah_malam
        FROM checkin_tamu_log cl
        JOIN tamu_ms t ON cl.id_tamu = t.id_tamu
        JOIN inapan_ms i ON cl.id_inapan = i.id_inapan
        WHERE cl.checkin_tamu_id = ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die('Database error: ' . $conn->error);
}

$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die('Reservation not found');
}

$data = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Calculate amounts
$jumlah_malam = max(1, (int)$data['jumlah_malam']);
$harga_kamar = (int)$data['harga_kamar'];
$harga_kelas = (int)$data['harga_kelas'];
$total_kamar = $harga_kamar * $jumlah_malam;
$total_amount = $total_kamar + $harga_kelas;

// Generate receipt number
$currentYear = date('Y');
$receiptNo = str_pad($reservation_id, 3, '0', STR_PAD_LEFT) . '/IXC31010000/' . $currentYear;

$baseTemp = __DIR__ . '/tmp';
$mpdfTempDir = $baseTemp . '/mpdf';

// Buat struktur dua tingkat dan paksa izin CHMOD 777 untuk XAMPP MacOS
if (!file_exists($baseTemp)) { mkdir($baseTemp, 0777, true); }
chmod($baseTemp, 0777);

if (!file_exists($mpdfTempDir)) { mkdir($mpdfTempDir, 0777, true); }
chmod($mpdfTempDir, 0777);

// Create PDF using mPDF with error handling
try {
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A5-L', // A5 Landscape
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'margin_header' => 5,
        'margin_footer' => 5,
        'tempDir' => $baseTemp, // mPDF akan secara otomatis menambahkan folder /mpdf di dalamnya
        'default_font' => 'sans-serif'
    ]);
} catch (Exception $e) {
    die('Error creating PDF: ' . $e->getMessage());
}

// CSS Styling
$stylesheet = '
<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 11pt;
        line-height: 1.5;
    }
    .header {
        margin-bottom: 20px;
    }
    .header-left {
        float: left;
        width: 60%;
    }
    .header-right {
        float: right;
        width: 35%;
        text-align: center;
    }
    .company-name {
        font-weight: bold;
        font-size: 13pt;
        margin-bottom: 3px;
    }
    .company-subtitle {
        font-weight: bold;
        font-size: 12pt;
        margin-bottom: 3px;
    }
    .company-address {
        font-size: 10pt;
        margin-bottom: 2px;
    }
    .kwitansi-box {
        border: 2px solid #000;
        padding: 10px;
        background-color: #FFFF99;
        margin-bottom: 10px;
    }
    .kwitansi-title {
        font-size: 20pt;
        font-weight: bold;
        margin-bottom: 5px;
    }
    .receipt-number {
        font-size: 11pt;
        font-weight: bold;
    }
    .clear {
        clear: both;
    }
    .content-table {
        width: 100%;
        margin-top: 20px;
    }
    .content-table td {
        padding: 5px 0;
        vertical-align: top;
    }
    .label {
        width: 20%;
        font-weight: normal;
    }
    .colon {
        width: 2%;
    }
    .value {
        width: 78%;
        border-bottom: 1px dotted #000;
    }
    .value-bold {
        font-weight: bold;
    }
    .value-italic {
        font-style: italic;
    }
    .amount-box {
        margin-top: 20px;
        margin-bottom: 20px;
    }
    .amount-container {
        display: inline-block;
        border: 2px solid #000;
        padding: 8px 15px;
    }
    .amount-label {
        font-weight: bold;
        font-size: 14pt;
        display: inline-block;
        margin-right: 10px;
    }
    .amount-value {
        font-weight: bold;
        font-size: 14pt;
        background-color: #FFFF99;
        padding: 5px 10px;
        display: inline-block;
        min-width: 150px;
        text-align: right;
    }
    .date-location {
        text-align: right;
        margin-top: -30px;
        margin-bottom: 50px;
    }
    .bank-info {
        margin-top: 20px;
        padding: 10px;
        background-color: #f0f0f0;
        border: 1px solid #d0d0d0;
        border-radius: 5px;
    }
    .bank-info-title {
        font-weight: bold;
        margin-bottom: 5px;
    }
    .bank-details {
        margin-left: 20px;
        margin-top: 5px;
    }
    .bank-name {
        font-weight: bold;
        font-size: 11pt;
    }
    .account-number {
        font-weight: bold;
        font-size: 13pt;
        margin: 5px 0;
        color: #0066cc;
    }
    .account-name {
        font-size: 10pt;
    }
    .email-notice {
        font-weight: bold;
        margin-top: 10px;
        font-size: 10pt;
        color: #cc0000;
    }
    .signature-area {
        margin-top: 30px;
        text-align: right;
        padding-right: 50px;
    }
    .signature-line {
        margin-top: 60px;
        border-top: 1px solid #000;
        width: 200px;
        margin-left: auto;
    }
    .signature-name {
        margin-top: 5px;
    }
    .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 60pt;
        color: rgba(200, 200, 200, 0.2);
        font-weight: bold;
        z-index: -1;
    }
</style>
';

// HTML Content
$html = '
<html>
<head>' . $stylesheet . '</head>
<body>
    <!-- Header Section -->
    <div class="header">
        <div class="header-left">
            <div class="company-name">PT PLN (Persero)</div>
            <div class="company-subtitle">PUSAT PENDIDIKAN DAN PELATIHAN</div>
            <div class="company-subtitle">UNIT PENDIDIKAN DAN PELATIHAN BOGOR</div>
            <div class="company-address">Jalan Raya Puncak KM 72, Cibogo, Kabupaten Bogor</div>
            <div class="company-address">email: pakupdlbogor@gmail.com</div>
        </div>
        <div class="header-right">
            <div class="kwitansi-box">
                <div class="kwitansi-title">KWITANSI</div>
                <div class="receipt-number">No. ' . htmlspecialchars($receiptNo) . '</div>
            </div>
        </div>
        <div class="clear"></div>
    </div>

    <!-- Main Content -->
    <table class="content-table">
        <tr>
            <td class="label">Telah terima dari</td>
            <td class="colon">:</td>
            <td class="value value-bold">' . strtoupper(htmlspecialchars($data['nama_tamu'])) . '</td>
        </tr>
        <tr>
            <td colspan="3" style="height: 10px;"></td>
        </tr>
        <tr>
            <td class="label">Uang Sejumlah</td>
            <td class="colon">:</td>
            <td class="value value-bold value-italic">*** ' . ucwords(terbilang($total_amount)) . ' Rupiah ***</td>
        </tr>
        <tr>
            <td colspan="3" style="height: 10px;"></td>
        </tr>
        <tr>
            <td class="label">Untuk pembayaran</td>
            <td class="colon">:</td>
            <td class="value value-bold">Penggunaan Akomodasi Mess</td>
        </tr>
        <tr>
            <td class="label"></td>
            <td class="colon"></td>
            <td class="value">' . htmlspecialchars($data['nama_kamar']) . ' Pada ' . 
            formatTanggalIndonesia($data['tanggal_check_in']) . ' - ' . 
            formatTanggalIndonesia($data['tanggal_check_out']) . 
            ' (' . $jumlah_malam . ' Malam)</td>
        </tr>';

// Add class info if exists
if ($harga_kelas > 0 && !empty($data['nama_kelas'])) {
    $html .= '
        <tr>
            <td class="label"></td>
            <td class="colon"></td>
            <td class="value">Termasuk ' . htmlspecialchars($data['nama_kelas']) . '</td>
        </tr>';
}

// Add event/training info if exists
if (!empty($data['acara_judul_diklat'])) {
    $html .= '
        <tr>
            <td class="label"></td>
            <td class="colon"></td>
            <td class="value">Acara: ' . htmlspecialchars($data['acara_judul_diklat']) . '</td>
        </tr>';
}

$html .= '
    </table>

    <!-- Amount Section -->
    <div class="amount-box">
        <div class="amount-container">
            <span class="amount-label">Rp.</span>
            <span class="amount-value">' . number_format($total_amount, 0, ',', '.') . ',-</span>
        </div>
    </div>

    <!-- Date and Location -->
    <div class="date-location">
        Bogor, ' . formatTanggalIndonesia(date('Y-m-d')) . '
    </div>

    <!-- Bank Information -->
    <div class="bank-info">
        <div class="bank-info-title">Mohon pembayaran ditransfer ke rekening bank berikut ini :</div>
        <div class="bank-details">
            <div class="bank-name">BRI Cabang Pasar Minggu</div>
            <div class="account-number">033901000171301</div>
            <div class="account-name">Receipt PT PLN (Persero) Jasdik</div>
            <div class="email-notice">BUKTI TRANSFER AGAR DIKIRIM ke: pakupdlbogor@gmail.com</div>
        </div>
    </div>

    <!-- Signature Area -->
    <div class="signature-area">
        <div>Yang Menerima,</div>
        <div class="signature-line"></div>
        <div class="signature-name">(.................................)</div>
    </div>
</body>
</html>
';

try {
    // Write HTML to PDF
    $mpdf->WriteHTML($html);
    
    // Add metadata
    $mpdf->SetTitle('Kwitansi PLN - ' . $data['nama_tamu']);
    $mpdf->SetAuthor('PT PLN (Persero) UPDL Bogor');
    $mpdf->SetCreator('Hotel Management System');
    $mpdf->SetSubject('Kwitansi Pembayaran');
    $mpdf->SetKeywords('kwitansi, PLN, ' . $data['nama_tamu'] . ', ' . $receiptNo);
    
    // Filename
    $filename = 'Kwitansi_PLN_' . str_pad($reservation_id, 6, '0', STR_PAD_LEFT) . '_' . 
                preg_replace('/[^A-Za-z0-9]/', '', $data['nama_tamu']) . '_' . 
                date('Ymd') . '.pdf';
    
    // Output based on preview parameter
    if ($preview) {
        // Display in browser (preview mode)
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        $mpdf->Output($filename, 'I'); // I = Inline (preview in browser)
    } else {
        // Force download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $mpdf->Output($filename, 'D'); // D = Download
    }
    
} catch (Exception $e) {
    die('Error generating PDF: ' . $e->getMessage());
}

exit();
?>