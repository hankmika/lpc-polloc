<?php
// Remove execution time limits for processing a large file.
set_time_limit(0);

require_once __DIR__ . '/config.php';

// If form was submitted and file uploaded without error...
if (
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& isset($_FILES['csv_file'])
	&& $_FILES['csv_file']['error'] === UPLOAD_ERR_OK
) {
	// 1) Connect
	$conn = mysqli_connect($servername, $username, $password, $dbname);
	if (!$conn) {
		die("<p>DB connect error: " . mysqli_connect_error() . "</p>");
	}
	echo "<p>✔ Connected to DB</p>";

	// 2) Drop & re-create table
	mysqli_query($conn, "DROP TABLE IF EXISTS election_sites")
	  or die("<p>Drop error: " . mysqli_error($conn) . "</p>");
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
	  or die("<p>Create table error: " . mysqli_error($conn) . "</p>");
	echo "<p>✔ Table election_sites is ready</p>";

	// 3) Move upload into /uploads
	$uploadDir  = __DIR__ . '/uploads';
	if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
	$targetFile = $uploadDir . '/' . basename($_FILES['csv_file']['name']);
	if (!move_uploaded_file($_FILES['csv_file']['tmp_name'], $targetFile)) {
		die("<p>Failed to move uploaded file.</p>");
	}

	// 4) Bulk‐load via LOAD DATA LOCAL INFILE
	mysqli_options($conn, MYSQLI_OPT_LOCAL_INFILE, true);
	$infile = mysqli_real_escape_string($conn, $targetFile);
	$loadSQL = <<<SQL
LOAD DATA LOCAL INFILE '$infile'
INTO TABLE election_sites
FIELDS TERMINATED BY ',' 
ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 3 LINES
(electoral_district, pd_sv, type, adv_ant,
 site_name_en, site_name_fr, address_en, address_fr,
 municipality_en, municipality_fr, province, postal_code)
SQL;
	mysqli_query($conn, $loadSQL)
	  or die("<p>LOAD DATA error: " . mysqli_error($conn) . "</p>");
	echo "<p>✔ Bulk import complete</p>";

	// 5) Add index to speed up merge‐lookup
	$idxSQL = "ALTER TABLE election_sites ADD INDEX idx_merge (electoral_district, pd_sv)";
	mysqli_query($conn, $idxSQL)
	  or die("<p>Index error: " . mysqli_error($conn) . "</p>");
	echo "<p>✔ Composite index added</p>";

	// 6) One‐shot UPDATE to overwrite merged rows (both EN/FR names + all address fields)
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
	  or die("<p>Merge‐rows UPDATE error: " . mysqli_error($conn) . "</p>");
	echo "<p>✔ Merged‐with rows updated</p>";

	// 7) Strip “-A” or “A” suffix off pd_sv
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
	  or die("<p>Strip suffix error: " . mysqli_error($conn) . "</p>");
	echo "<p>✔ Stripped both '-A' and 'A' suffixes from pd_sv codes</p>";

	// 8) Delete any pd_sv that end with a letter suffix,
	//    whether it's '-B' or '0C' etc.
	$deleteSQL = "
	  DELETE FROM election_sites
	  WHERE pd_sv RLIKE '^[0-9]+-[0-9]+(-?[A-Za-z])$'
	";
	mysqli_query($conn, $deleteSQL)
	  or die("<p>Delete suffixed duplicates error: " . mysqli_error($conn) . "</p>");
	echo "<p>✔ Deleted all rows whose pd_sv still had a letter suffix</p>";

	// 9) Remove any remaining duplicates, keeping only the lowest‑id row
	$deleteDuplicatesSQL = <<<SQL
	DELETE t1
	FROM election_sites AS t1
	JOIN election_sites AS t2
	  ON t1.electoral_district = t2.electoral_district
	 AND t1.pd_sv = t2.pd_sv
	 AND t1.id      > t2.id
	SQL;
	mysqli_query($conn, $deleteDuplicatesSQL)
		or die("<p>Duplicate‑cleanup error: " . mysqli_error($conn) . "</p>");
	echo "<p>✔ Removed duplicate pd_sv rows, kept lowest‑id</p>";

	// 10) Cleanup & finish
	unlink($targetFile);
	mysqli_close($conn);
	echo "<p><strong>Done!</strong></p><p>You can now export the <a href=\"ploc_adv.php\"><strong>advanced polling locations</strong></a> file or the <a href=\"ploc_reg.php\"><strong>regular polling locations</strong></a>.</p>";

} else {
	// Show upload form
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		echo "<p><em>Error uploading file.</em></p>";
	}
	?>
	<!DOCTYPE html>
	<html>
	<head><meta charset="UTF-8"><title>Import Election CSV</title></head>
	<body>
	  <h2>Upload Your CSV</h2>
	  <form action="" method="post" enctype="multipart/form-data">
		<input type="file" name="csv_file" accept=".csv" required>
		<button type="submit">Upload &amp; Import</button>
	  </form>
	</body>
	</html>
	<?php
}
?>