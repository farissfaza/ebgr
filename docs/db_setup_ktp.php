<?php
include __DIR__ . '/../config/koneksi.php';

$sql = "CREATE TABLE IF NOT EXISTS mobile_ktp_sessions (
  token VARCHAR(10) PRIMARY KEY,
  status ENUM('pending', 'scanned', 'completed', 'failed') DEFAULT 'pending',
  ocr_data TEXT,
  image_path VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql) === TRUE) {
  echo "Table 'mobile_ktp_sessions' created successfully.";
} else {
  echo "Error creating table: " . $conn->error;
}
$conn->close();
?>
