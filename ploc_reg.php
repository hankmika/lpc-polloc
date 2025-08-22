<?php
// Allow this script to run as long as it needs
set_time_limit(0);

// --- Database configuration ---
$servername = '';
$username   = 'db_dom40916';
$password   = '';
$dbname     = 'db_dom40916';

// --- 1) Connect to MySQL via mysqli ---
$conn = mysqli_connect($servername, $username, $password, $dbname);
if (!$conn) {
	die("DB connect error: " . mysqli_connect_error());
}

// --- 2) Query non‑advance rows and join on adv_ant → pd_sv within the same district ---
// We match SUBSTRING_INDEX(t.pd_sv,'-',1)=m.adv_ant to find the true polling‑location record.
$sql = "
  SELECT
	electoral_district,
	pd_sv,
	adv_ant,
	province,
	site_name_en,
	site_name_fr,
	address_en,
	address_fr,
	municipality_en,
	municipality_fr,
	postal_code
  FROM election_sites
  WHERE `type` NOT LIKE 'Advance%'
";
$result = mysqli_query($conn, $sql);
if (!$result) {
	die("Query error: " . mysqli_error($conn));
}

// --- 3) Open output file for writing (ANSI / Windows‑1252) ---
$outputFile = __DIR__ . '/export_reg.txt';
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
echo "<p>Export complete. <a href=\"ploc_adv_download.php?reg=1\">Download the file</a></p>";
?>