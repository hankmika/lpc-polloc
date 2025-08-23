<?php
session_start();
require_once __DIR__ . '/template.php';

$token = filter_input(INPUT_GET, 'token');
$requested = basename((string) filter_input(INPUT_GET, 'file'));
$allowed = ['export.txt', 'export_reg.txt'];

if (
    !isset($_SESSION['download_token'], $token) ||
    !hash_equals($_SESSION['download_token'], $token) ||
    !in_array($requested, $allowed, true)
) {
    http_response_code(403);
    render_error('Invalid download token.');
}
unset($_SESSION['download_token']);

$filename = __DIR__ . '/' . $requested;

header('Content-Type: text/plain; charset=ISO-8859-1');
header('Content-Disposition: attachment; filename="' . $requested . '"');
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');

if (file_exists($filename)) {
    readfile($filename);
    if (!unlink($filename)) {
        error_log("Failed to delete file: $filename");
    }
    exit;
}

http_response_code(404);
render_error('File not found.');
?>
