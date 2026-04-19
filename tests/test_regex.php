<?php
$text = "NIK\nNama\nTempat Tgl Lahir\nJenis kelaran\nAlamat\nPROVINSI JAWA BARAT\nKOTA BOGOR\n3204082901046603\nFARIS FAZA ASSYAMSI\nBANDUNG, 29-01-2004\nLAKI LAKE\nBABAKAN PEUNDEUY\nGol. Darah B\nRT/RW\n006/012\nKel/Desa\nBARANANGSIANG\nKecamatan\nBOGOR TIMUR\nAgama\nISLAM\nStatus Perkawinan: BELUM KAWIN\nPekerjaan\nPELAJAR MAHASISWA\nKewarganegaraan: WNI\nBerlaku Hingga\nSEUMUR HIDUP";

$lines = array_map('trim', explode("\n", $text));
$potential = [];

foreach ($lines as $line) {
    $cleanLine = trim(preg_replace('/^[:\-\.]\s*/', '', trim($line))); 
    $upperLine = strtoupper($cleanLine);
    
    if (preg_match('/^(NIK|NAMA|TEMPAT|TGL|LAHIR|JENIS\s*KELAMIN|ALAMAT|RT\s*[\/\\]\s*RW|KEL\s*[\/\\]\s*DESA|KECAMATAN|AGAMA|STATUS|KAWIN|PEKERJAAN|KEWARGANEGARAAN|BERLAKU|HINGGA|GOL\s*\.?\s*DARAH|PROVINSI|KOTA|KABUPATEN|KARTU|TANDA|PENDUDUK)$/i', $upperLine)) {
        echo "SKIPPED: $upperLine\n";
        continue;
    }
    
    // Testing Tempat Tgl Lahir matching which swallowed Nama
    if (preg_match('/([A-Z\s]+)\s*,\s*(\d{2}[\-\/]\d{2}[\-\/]\d{4})/i', $text, $matches)) {
        // Exclude common false positives
        $tmp = trim(strtoupper($matches[1]));
        $tmp = preg_replace('/.*(LAHIR|TEMPAT)\s*:?\s*/i', '', $tmp);
        echo "TEMPAT LAHIR FOUND: $tmp\n";
    }

    if ($cleanLine === strtoupper($cleanLine)) {
         $potential[] = $upperLine;
    }
}

echo "POTENTIAL FIELDS:\n";
print_r($potential);
?>
