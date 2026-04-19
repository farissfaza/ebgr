<?php
$src = file_get_contents('ocr_vision.php');
$src = substr($src, 0, strpos($src, "try {"));
eval("?>$src");

$text = "PROVINSI JAWA BARAT\nKOTA BOGOR\nNIK\nE07960T06280402E :\nFARIS FAZA ASSYAMSI\nTempai Tgl Lahir : BANDUNG, 29-01-2004\nJenis kelaran\nAlamat\nRT/RW\n006/012\nKel/Desa\nBARANANGSIANG\nKecamatan\nBOGOR TIMUR\nAgama\nISLAM\nLAKI LAKE\nBABAKAN PEUNDEUY\nStatus Perkawinan: BELUM KAWIN\nPekerjaan\nKewarganegaraan: WNI\nBerlaku Hingga\nTU TA\nPELAJAR MAHASISWA\nARTISSEUMUR HIDUP\nGol. Darah B\nDA PENDUDUK KARTY\nSENDUDUK KAR\nKARTU TA\nNDA\nKOTA BOGOR\n26-02-2021\nNDUD\nPEND...";

print_r(extractKTPDataVision($text));
