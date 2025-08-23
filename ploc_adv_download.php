<?php
session_start();
if (!isset($_SESSION['download_token'], $_GET['token']) ||
    !hash_equals($_SESSION['download_token'], $_GET['token'])) {
    http_response_code(403);
    exit('Invalid download token.');
}
unset($_SESSION['download_token']);

// --- Set headers to force download and specify ISO-8859-1 encoding ---
header('Content-Type: text/plain; charset=ISO-8859-1');
header('Content-Disposition: attachment; filename="export' . ( isset($_GET['reg']) ? '_reg' : '_adv' ) . '.txt"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');

// --- Output the file contents ---
$filename = __DIR__ . '/export' . ( isset($_GET['reg']) ? '_reg' : '' ) . '.txt';

if (file_exists($filename)) {
    readfile($filename);
    if (!unlink($filename)) {
        error_log("Failed to delete file: $filename");
    }
    exit;
}
http_response_code(404);
echo "File not found.";
?>
