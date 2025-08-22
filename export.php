<?php
function generate_export($isReg = false) {
    // Allow this script to run as long as it needs
    set_time_limit(0);

    // Load DB config
    require __DIR__ . '/config.php';

    // Connect to MySQL via mysqli
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    if (!$conn) {
        die("DB connect error: " . mysqli_connect_error());
    }

    if ($isReg) {
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
        $outputFile = __DIR__ . '/export_reg.txt';
    } else {
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
        $outputFile = __DIR__ . '/export.txt';
    }

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        die("Query error: " . mysqli_error($conn));
    }

    $fp = fopen($outputFile, 'w');
    if (!$fp) {
        die("Cannot open output file for writing.");
    }

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

    while ($row = mysqli_fetch_assoc($result)) {
        $district = $row['electoral_district'];
        $riding = substr($district, 0, 5);
        $pd = preg_replace('/\D+/', '', $row['pd_sv']);
        if ($row['province'] === 'QC') {
            $loc  = $row['site_name_fr'];
            $addr = $row['address_fr'];
            $city = $row['municipality_fr'];
        } else {
            $loc  = $row['site_name_en'];
            $addr = $row['address_en'];
            $city = $row['municipality_en'];
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
        if (!empty($district)) {
            fwrite($fp, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $line));
        }
    }

    fclose($fp);
    mysqli_free_result($result);
    mysqli_close($conn);

    return $outputFile;
}
?>
