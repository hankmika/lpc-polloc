<?php
session_start();
require_once __DIR__ . '/export_utils.php';
require_once __DIR__ . '/template.php';

generate_export(false);

$token = bin2hex(random_bytes(32));
$_SESSION['download_token'] = $token;

$body = '<p>Export complete. <a class="button" href="ploc_adv_download.php?token=' . urlencode($token) . '">Download the file</a></p>';
render_template('Advanced Export', $body);
?>
