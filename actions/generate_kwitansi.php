<?php
// generate_kwitansi.php - Fixed version without Excel corruption issues
session_start();
include __DIR__ . '/../config/koneksi.php';

// Check if Composer autoload exists
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require $composerAutoload;
} else {
    die('PHPSpreadsheet library not found. Please install via Composer: composer require phpoffice/phpspreadsheet');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

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

// Get reservation ID from parameter
$reservation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reservation_id == 0) {
    die('Invalid reservation ID');
}

// Fetch guest data
$sql = "SELECT 
            dt.*,
            DATEDIFF(tanggal_check_out, tanggal_check_in) as jumlah_malam
        FROM data_tamu dt
        WHERE dt.id = ?";

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

// Calculate amounts
$jumlah_malam = max(1, (int)$data['jumlah_malam']);
$harga_kamar = (int)$data['harga_kamar'];
$harga_kelas = (int)$data['harga_kelas'];
$total_kamar = $harga_kamar * $jumlah_malam;
$total_amount = $total_kamar + $harga_kelas;

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('KWITANSI');

// Remove any default styles that might cause issues
$spreadsheet->getDefaultStyle()->getFont()->setName('Arial');
$spreadsheet->getDefaultStyle()->getFont()->setSize(11);

// Set column widths
$sheet->getColumnDimension('A')->setWidth(3);
$sheet->getColumnDimension('B')->setWidth(18);
$sheet->getColumnDimension('C')->setWidth(3);
$sheet->getColumnDimension('D')->setWidth(35);
$sheet->getColumnDimension('E')->setWidth(25);
$sheet->getColumnDimension('F')->setWidth(15);
$sheet->getColumnDimension('G')->setWidth(20);

// ========== HEADER SECTION ==========
// Organization name - split into separate lines to avoid multi-line issues
$sheet->setCellValue('A1', 'PT PLN (Persero)');
$sheet->setCellValue('A2', 'PUSAT PENDIDIKAN DAN PELATIHAN');
$sheet->setCellValue('A3', 'UNIT PENDIDIKAN DAN PELATIHAN BOGOR');
$sheet->setCellValue('A4', 'Jalan Raya Puncak KM 72, Cibogo, Kabupaten Bogor');
$sheet->setCellValue('A5', 'email: pakupdlbogor@gmail.com');

// Merge cells for header
$sheet->mergeCells('A1:E1');
$sheet->mergeCells('A2:E2');
$sheet->mergeCells('A3:E3');
$sheet->mergeCells('A4:E4');
$sheet->mergeCells('A5:E5');

// Style header
$sheet->getStyle('A1:E3')->getFont()->setBold(true);
$sheet->getStyle('A1')->getFont()->setSize(12);
$sheet->getStyle('A2')->getFont()->setSize(12);
$sheet->getStyle('A3')->getFont()->setSize(11);
$sheet->getStyle('A4:A5')->getFont()->setSize(10);

// KWITANSI title
$sheet->mergeCells('F1:G3');
$sheet->setCellValue('F1', 'KWITANSI');
$sheet->getStyle('F1')->getFont()->setBold(true)->setSize(18);
$sheet->getStyle('F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('F1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

// Receipt number
$sheet->mergeCells('F4:G5');
$currentYear = date('Y');
$receiptNo = 'No. ' . str_pad($reservation_id, 3, '0', STR_PAD_LEFT) . '/IXC31010000/' . $currentYear;
$sheet->setCellValue('F4', $receiptNo);
$sheet->getStyle('F4')->getFont()->setBold(true);
$sheet->getStyle('F4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('F4')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

// Yellow background for receipt number
$sheet->getStyle('F4:G5')->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FFFFFF00');

// Add borders to header
$sheet->getStyle('A1:G5')->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_THIN);

// ========== MAIN CONTENT ==========
// Row 7: Telah terima dari
$sheet->setCellValue('B7', 'Telah terima dari');
$sheet->setCellValue('C7', ':');
$sheet->mergeCells('D7:G7');
$sheet->setCellValue('D7', strtoupper($data['nama_tamu']));
$sheet->getStyle('D7')->getFont()->setBold(true);

// Dotted underline
$sheet->getStyle('D7:G7')->getBorders()->getBottom()
    ->setBorderStyle(Border::BORDER_DOTTED);

// Row 9: Empty for spacing
$sheet->getRowDimension(9)->setRowHeight(5);

// Row 10: Uang Sejumlah
$sheet->setCellValue('B10', 'Uang Sejumlah');
$sheet->setCellValue('C10', ':');
$sheet->mergeCells('D10:G10');

// Avoid special characters that might cause issues
$terbilangText = ucwords(terbilang($total_amount)) . ' Rupiah';
$sheet->setCellValue('D10', $terbilangText);
$sheet->getStyle('D10')->getFont()->setItalic(true)->setBold(true);
$sheet->getStyle('D10:G10')->getBorders()->getBottom()
    ->setBorderStyle(Border::BORDER_DOTTED);

// Row 12: Empty for spacing
$sheet->getRowDimension(12)->setRowHeight(5);

// Row 13: Untuk pembayaran
$sheet->setCellValue('B13', 'Untuk pembayaran');
$sheet->setCellValue('C13', ':');
$sheet->mergeCells('D13:G13');
$sheet->setCellValue('D13', 'Penggunaan Akomodasi Mess');
$sheet->getStyle('D13')->getFont()->setBold(true);
$sheet->getStyle('D13:G13')->getBorders()->getBottom()
    ->setBorderStyle(Border::BORDER_DOTTED);

// Row 14: Detail pembayaran
$sheet->mergeCells('D14:G14');
$detailText = $data['nama_kamar'] . ' Pada ' . 
              formatTanggalIndonesia($data['tanggal_check_in']) . ' - ' . 
              formatTanggalIndonesia($data['tanggal_check_out']) . 
              ' (' . $jumlah_malam . ' Malam)';
$sheet->setCellValue('D14', $detailText);
$sheet->getStyle('D14:G14')->getBorders()->getBottom()
    ->setBorderStyle(Border::BORDER_DOTTED);

// Row 15: Additional class if exists
if ($harga_kelas > 0 && !empty($data['nama_kelas'])) {
    $sheet->mergeCells('D15:G15');
    $sheet->setCellValue('D15', 'Termasuk ' . $data['nama_kelas']);
    $sheet->getStyle('D15:G15')->getBorders()->getBottom()
        ->setBorderStyle(Border::BORDER_DOTTED);
}

// ========== AMOUNT BOX ==========
$amountRow = 18;

// Amount label
$sheet->setCellValue('B' . $amountRow, 'Rp.');
$sheet->getStyle('B' . $amountRow)->getFont()->setBold(true)->setSize(14);

// Amount value
$sheet->setCellValue('C' . $amountRow, number_format($total_amount, 0, ',', '.'));
$sheet->getStyle('C' . $amountRow)->getFont()->setBold(true)->setSize(14);

// Yellow background for amount
$sheet->getStyle('C' . $amountRow)->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FFFFFF00');

// Border around amount
$sheet->getStyle('B' . $amountRow . ':C' . $amountRow)->getBorders()->getAllBorders()
    ->setBorderStyle(Border::BORDER_MEDIUM);

// Date on the right
$sheet->mergeCells('E' . $amountRow . ':G' . $amountRow);
$sheet->setCellValue('E' . $amountRow, 'Bogor, ' . formatTanggalIndonesia(date('Y-m-d')));
$sheet->getStyle('E' . $amountRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// ========== BANK INFORMATION ==========
$bankRow = $amountRow + 3;
$sheet->mergeCells('B' . $bankRow . ':G' . $bankRow);
$sheet->setCellValue('B' . $bankRow, 'Mohon pembayaran ditransfer ke rekening bank berikut ini :');
$sheet->getStyle('B' . $bankRow)->getFont()->setBold(true);

$bankRow++;
$sheet->setCellValue('C' . $bankRow, 'BRI Cabang Pasar Minggu');
$sheet->getStyle('C' . $bankRow)->getFont()->setBold(true);

$bankRow++;
$sheet->setCellValue('C' . $bankRow, '033901000171301');
$sheet->getStyle('C' . $bankRow)->getFont()->setBold(true)->setSize(12);

$bankRow++;
$sheet->setCellValue('C' . $bankRow, 'Receipt PT PLN (Persero) Jasdik');

$bankRow++;
$sheet->mergeCells('C' . $bankRow . ':G' . $bankRow);
$sheet->setCellValue('C' . $bankRow, 'BUKTI TRANSFER AGAR DIKIRIM pakupdlbogor@gmail.com');
$sheet->getStyle('C' . $bankRow)->getFont()->setBold(true);

// ========== OUTER BORDER ==========
$lastRow = $bankRow + 1;
$sheet->getStyle('A1:G' . $lastRow)->getBorders()->getOutline()
    ->setBorderStyle(Border::BORDER_MEDIUM);

// ========== PAGE SETUP ==========
$sheet->getPageSetup()
    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A5)
    ->setFitToPage(true)
    ->setFitToWidth(1)
    ->setFitToHeight(1);

$sheet->getPageMargins()
    ->setTop(0.5)
    ->setRight(0.5)
    ->setLeft(0.5)
    ->setBottom(0.5);

// ========== SAVE FILE ==========
// Create filename - avoid special characters
$cleanName = preg_replace('/[^A-Za-z0-9]/', '', $data['nama_tamu']);
$filename = 'Kwitansi_PLN_' . str_pad($reservation_id, 6, '0', STR_PAD_LEFT) . '_' . 
            $cleanName . '_' . date('Ymd') . '.xlsx';

// Create writer
$writer = new Xlsx($spreadsheet);

// Configure writer settings to avoid corruption
$writer->setPreCalculateFormulas(false);
$writer->setOffice2003Compatibility(false);

// Set headers for download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

// Save to output
$writer->save('php://output');

// Clean up
$conn->close();
$spreadsheet->disconnectWorksheets();
unset($spreadsheet);

exit();
?>