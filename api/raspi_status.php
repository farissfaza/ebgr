<?php
// raspi_status.php — Website gets latest Raspi hardware health
header('Content-Type: application/json');
include __DIR__ . '/../config/koneksi.php';

$components = ['pn532', 'mlx90614', 'camera'];
$response = [];

foreach ($components as $comp) {
    $stmt = $conn->prepare("SELECT status, details, checked_at FROM raspi_health WHERE component = ? ORDER BY checked_at DESC LIMIT 1");
    $stmt->bind_param("s", $comp);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $response[$comp] = [
            'status' => $row['status'],
            'details' => $row['details'],
            'checked_at' => $row['checked_at'],
            'age_seconds' => time() - strtotime($row['checked_at'])
        ];
    } else {
        $response[$comp] = [
            'status' => 'unknown',
            'details' => 'No health check received yet',
            'checked_at' => null,
            'age_seconds' => null
        ];
    }
    $stmt->close();
}

// Also get latest temperature reading
$tempStmt = $conn->query("SELECT temperature, recorded_at FROM temperature_sessions ORDER BY recorded_at DESC LIMIT 1");
if ($tempStmt && $tempRow = $tempStmt->fetch_assoc()) {
    $response['latest_temperature'] = [
        'value' => floatval($tempRow['temperature']),
        'recorded_at' => $tempRow['recorded_at']
    ];
} else {
    $response['latest_temperature'] = null;
}

$conn->close();
echo json_encode($response);
?>
