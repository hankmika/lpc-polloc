<?php
session_start();
require_once __DIR__ . '/export.php';

generate_export(true);

$token = bin2hex(random_bytes(32));
$_SESSION['download_token'] = $token;

echo '<p>Export complete. <a href="ploc_adv_download.php?reg=1&token=' . urlencode($token) . '">Download the file</a></p>';
?>
