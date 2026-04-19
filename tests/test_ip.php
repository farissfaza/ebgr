<?php
$actual_ip = trim(shell_exec("ipconfig getifaddr en0 2>/dev/null || ipconfig getifaddr en1 2>/dev/null || iproute get 1 | awk '{print $7}' 2>/dev/null"));
echo "IP from shell: " . $actual_ip . "\n";
echo "SERVER_ADDR: " . ($_SERVER['SERVER_ADDR'] ?? 'not set') . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'not set') . "\n";
?>
