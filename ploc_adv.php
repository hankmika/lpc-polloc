<?php
// Allow this script to run as long as it needs
set_time_limit(0);

require_once __DIR__ . '/config.php';

// --- 1) Connect to MySQL via mysqli ---
$conn = mysqli_connect($servername, $username, $password, $dbname);
if (!$conn) {
	die("DB connect error: " . mysqli_connect_error());
}

// --- 2) Query non‑advance rows and join on adv_ant → pd_sv within the same district ---
// We match SUBSTRING_INDEX(t.pd_sv,'-',1)=m.adv_ant to find the true polling‑location record.
$sql = "
  SELECT
	m.electoral_district,
	m.pd_sv,
	m.adv_ant,
	t.province,
	t.site_name_en,
	t.site_name_fr,
	t.address_en,
	t.address_fr,
	t.municipality_en,
	t.municipality_fr,
	t.postal_code
  FROM election_sites AS m
  JOIN election_sites AS t
	ON m.electoral_district = t.electoral_district
   AND SUBSTRING_INDEX(t.pd_sv,'-',1) = m.adv_ant
  WHERE m.`type` NOT LIKE 'Advance%'
";
$result = mysqli_query($conn, $sql);
if (!$result) {
	die("Query error: " . mysqli_error($conn));
}

// --- 3) Open output file for writing (ANSI / Windows‑1252) ---
$outputFile = __DIR__ . '/export.txt';
if (!($fp = fopen($outputFile, 'w'))) {
	die("Cannot open output file for writing.");
}

// --- 4) Write header row ---
$headers = [
	'Electoral District',
	'Riding',
	'Polling Division',
	'Polling Location',
	'Polling Location Address',
	'Polling Location City',
	'Polling Location Province',
	'Polling Location PostalCode'
];
$line = implode("\t", $headers) . "\r\n";
fwrite($fp, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $line));

// --- 5) Loop through results and write each line ---
while ($row = mysqli_fetch_assoc($result)) {
	// Electoral District
	$district = $row['electoral_district'];
	// Riding = first 5 chars
	$riding = substr($district, 0, 5);
	// Polling Division = strip non‑digits
	$pd = preg_replace('/\D+/', '', $row['pd_sv']);
	// Choose FR vs EN based on province
	if ($row['province'] === 'QC') {
		$loc   = $row['site_name_fr'];
		$addr  = $row['address_fr'];
		$city  = $row['municipality_fr'];
	} else {
		$loc   = $row['site_name_en'];
		$addr  = $row['address_en'];
		$city  = $row['municipality_en'];
	}
	$prov   = $row['province'];
	$postal = $row['postal_code'];

	$fields = [
		$district, 
		$riding, 
		$pd, 
		$loc, 
		$addr, 
		$city, 
		$prov, 
		$postal
	];
	$line = implode("\t", $fields) . "\r\n";
	if(!empty($district))
		fwrite($fp, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $line));
}

fclose($fp);
mysqli_free_result($result);
mysqli_close($conn);

// --- 6) Provide download link ---
echo "<p>Export complete. <a href=\"ploc_adv_download.php\">Download the file</a></p>";
?>