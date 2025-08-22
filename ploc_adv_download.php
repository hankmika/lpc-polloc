<?php
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
	exit;
} else {
	http_response_code(404);
	echo "File not found.";
}
?>