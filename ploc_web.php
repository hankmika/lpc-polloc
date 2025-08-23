<?php
session_start();
set_time_limit(0);

require_once __DIR__ . '/template.php';
require_once __DIR__ . '/db.php';

function render_form($error = '') {
    if (empty($_SESSION['upload_token'])) {
        $_SESSION['upload_token'] = bin2hex(random_bytes(32));
    }
    $token = htmlspecialchars($_SESSION['upload_token'], ENT_QUOTES, 'UTF-8');
    ob_start();
    if ($error) {
        echo "<p><em>{$error}</em></p>";
    }
    ?>
    <h2>Upload Your CSV</h2>
    <form action="" method="post" enctype="multipart/form-data">
        <input type="hidden" name="token" value="<?php echo $token; ?>">
        <input type="file" name="csv_file" accept=".csv" required>
        <button type="submit">Upload &amp; Import</button>
    </form>
    <?php
    $body = ob_get_clean();
    render_template('Import Election CSV', $body);
}

function render_success($messages) {
    $body = implode("\n", $messages);
    $body .= '<p><strong>Done!</strong></p><p>You can now export the <a href="ploc_adv.php"><strong>advanced polling locations</strong></a> file or the <a href="ploc_reg.php"><strong>regular polling locations</strong></a>.</p>';
    render_template('Import Election CSV', $body);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        isset($_FILES['csv_file'], $_POST['token'], $_SESSION['upload_token']) &&
        hash_equals($_SESSION['upload_token'], $_POST['token']) &&
        $_FILES['csv_file']['error'] === UPLOAD_ERR_OK
    ) {
        unset($_SESSION['upload_token']);
        $file = $_FILES['csv_file'];
        $maxSize = 5 * 1024 * 1024;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            render_error('Please upload a .csv file.');
        }
        if ($file['size'] > $maxSize) {
            render_error('File is too large. Maximum size is 5MB.');
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed = ['text/plain', 'text/csv', 'application/vnd.ms-excel'];
        if (!in_array($mime, $allowed, true)) {
            render_error('Invalid file type. Please upload a CSV file.');
        }
        $uploadDir = __DIR__ . '/uploads';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $targetFile = $uploadDir . '/' . basename($file['name']);
        if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
            render_error('Failed to move uploaded file.');
        }
        try {
            $msgs = import_election_sites($targetFile);
            render_success($msgs);
        } catch (Exception $e) {
            render_error($e->getMessage());
        }
    } else {
        render_error('Invalid form submission.');
    }
} else {
    render_form();
}
?>
