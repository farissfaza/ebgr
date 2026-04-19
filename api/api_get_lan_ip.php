<?php
header('Content-Type: application/json');

function getLocalIp() {
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (!$sock) {
        return '127.0.0.1';
    }
    socket_connect($sock, "8.8.8.8", 53);
    socket_getsockname($sock, $name, $port); // $name contains the local IP used to reach the internet
    socket_close($sock);
    return $name ? $name : '127.0.0.1';
}

$ip = getLocalIp();

echo json_encode(['ip' => $ip]);
?>
