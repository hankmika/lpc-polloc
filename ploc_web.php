<?php
session_start();
set_time_limit(0);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/template.php';

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

function fail($msg) {
    render_template('Import Election CSV', "<p>{$msg}</p>");
    exit;
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
        render_form('Please upload a .csv file.');
        exit;
    }
    if ($file['size'] > $maxSize) {
        render_form('File is too large. Maximum size is 5MB.');
        exit;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowed = ['text/plain', 'text/csv', 'application/vnd.ms-excel'];
    if (!in_array($mime, $allowed, true)) {
        render_form('Invalid file type. Please upload a CSV file.');
        exit;
    }

    $msgs = [];
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    if (!$conn) {
        fail('DB connect error: ' . mysqli_connect_error());
    }
    $msgs[] = '<p>✔ Connected to DB</p>';

    mysqli_query($conn, 'DROP TABLE IF EXISTS election_sites')
        or fail('Drop error: ' . mysqli_error($conn));
    $createSQL = <<<SQL
CREATE TABLE election_sites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        electoral_district VARCHAR(255),
        pd_sv VARCHAR(50),
        type VARCHAR(50),
        adv_ant VARCHAR(50),
        site_name_en VARCHAR(255),
        site_name_fr VARCHAR(255),
        address_en VARCHAR(255),
        address_fr VARCHAR(255),
        municipality_en VARCHAR(255),
        municipality_fr VARCHAR(255),
        province VARCHAR(10),
        postal_code VARCHAR(20)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SQL;
    mysqli_query($conn, $createSQL)
        or fail('Create table error: ' . mysqli_error($conn));
    $msgs[] = '<p>✔ Table election_sites is ready</p>';

    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $targetFile = $uploadDir . '/' . basename($file['name']);
    if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
        fail('Failed to move uploaded file.');
    }

    mysqli_options($conn, MYSQLI_OPT_LOCAL_INFILE, true);
    $infile = mysqli_real_escape_string($conn, $targetFile);
    $loadSQL = <<<SQL
LOAD DATA LOCAL INFILE '$infile'
INTO TABLE election_sites
FIELDS TERMINATED BY ','
ENCLOSED BY '\"'
LINES TERMINATED BY '\n'
IGNORE 3 LINES
(electoral_district, pd_sv, type, adv_ant,
 site_name_en, site_name_fr, address_en, address_fr,
 municipality_en, municipality_fr, province, postal_code)
SQL;
    mysqli_query($conn, $loadSQL)
        or fail('LOAD DATA error: ' . mysqli_error($conn));
    $msgs[] = '<p>✔ Bulk import complete</p>';

    $idxSQL = 'ALTER TABLE election_sites ADD INDEX idx_merge (electoral_district, pd_sv)';
    mysqli_query($conn, $idxSQL)
        or fail('Index error: ' . mysqli_error($conn));
    $msgs[] = '<p>✔ Composite index added</p>';

    $updateSQL = <<<SQL
UPDATE election_sites AS m
JOIN election_sites AS t
  ON m.electoral_district = t.electoral_district
 AND t.pd_sv = TRIM(REPLACE(m.site_name_en, 'Merged with ', ''))
SET
        m.site_name_en    = t.site_name_en,
        m.site_name_fr    = t.site_name_fr,
        m.address_en      = t.address_en,
        m.address_fr      = t.address_fr,
        m.municipality_en = t.municipality_en,
        m.municipality_fr = t.municipality_fr,
        m.province        = t.province,
        m.postal_code     = t.postal_code
WHERE m.site_name_en LIKE 'Merged with %'
SQL;
    mysqli_query($conn, $updateSQL)
        or fail('Merge-rows UPDATE error: ' . mysqli_error($conn));
    $msgs[] = '<p>✔ Merged-with rows updated</p>';

    $stripA_SQL = <<<SQL
        UPDATE election_sites
        SET pd_sv = CASE
                WHEN pd_sv LIKE '%-A' THEN SUBSTRING_INDEX(pd_sv, '-', 2)
                WHEN pd_sv LIKE '%A'  THEN LEFT(pd_sv, CHAR_LENGTH(pd_sv) - 1)
        END
        WHERE pd_sv LIKE '%-A'
           OR pd_sv LIKE '%A'
SQL;
    mysqli_query($conn, $stripA_SQL)
        or fail('Strip suffix error: ' . mysqli_error($conn));
    $msgs[] = "<p>✔ Stripped both '-A' and 'A' suffixes from pd_sv codes</p>";

    $deleteSQL = "
          DELETE FROM election_sites
          WHERE pd_sv RLIKE '^[0-9]+-[0-9]+(-?[A-Za-z])$'
        ";
    mysqli_query($conn, $deleteSQL)
        or fail('Delete suffixed duplicates error: ' . mysqli_error($conn));
    $msgs[] = "<p>✔ Deleted all rows whose pd_sv still had a letter suffix</p>";

    $deleteDuplicatesSQL = <<<SQL
        DELETE t1
        FROM election_sites AS t1
        JOIN election_sites AS t2
          ON t1.electoral_district = t2.electoral_district
         AND t1.pd_sv = t2.pd_sv
         AND t1.id      > t2.id
SQL;
    mysqli_query($conn, $deleteDuplicatesSQL)
        or fail('Duplicate-cleanup error: ' . mysqli_error($conn));
    $msgs[] = '<p>✔ Removed duplicate pd_sv rows, kept lowest-id</p>';

    unlink($targetFile);
    mysqli_close($conn);

    render_success($msgs);
    } else {
        render_form('Invalid form submission.');
    }
} else {
    render_form();
}
?>
