<?php
session_start();
require_once __DIR__ . '/export_utils.php';
require_once __DIR__ . '/template.php';

generate_export(true);

$token = bin2hex(random_bytes(32));
$_SESSION['download_token'] = $token;
$file = 'export_reg.txt';
$href = 'ploc_adv_download.php?file=' . urlencode($file) . '&token=' . urlencode($token);
$body = '<p>Export complete. <a class="button" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">Download the file</a></p>';
render_template('Regular Export', $body);
?>
