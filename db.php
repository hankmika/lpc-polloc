<?php

function db_connect() {
    require __DIR__ . '/config.php';
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    if (!$conn) {
        throw new Exception('DB connect error: ' . mysqli_connect_error());
    }
    return $conn;
}

function import_election_sites($csvFile) {
    $conn = db_connect();
    $msgs = ['<p>✔ Connected to DB</p>'];

    mysqli_query($conn, 'DROP TABLE IF EXISTS election_sites')
        or throw new Exception('Drop error: ' . mysqli_error($conn));

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
        or throw new Exception('Create table error: ' . mysqli_error($conn));
    $msgs[] = '<p>✔ Table election_sites is ready</p>';

    mysqli_options($conn, MYSQLI_OPT_LOCAL_INFILE, true);
    $infile = mysqli_real_escape_string($conn, $csvFile);
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
        or throw new Exception('LOAD DATA error: ' . mysqli_error($conn));
    $msgs[] = '<p>✔ Bulk import complete</p>';

    $idxSQL = 'ALTER TABLE election_sites ADD INDEX idx_merge (electoral_district, pd_sv)';
    mysqli_query($conn, $idxSQL)
        or throw new Exception('Index error: ' . mysqli_error($conn));
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
        or throw new Exception('Merge-rows UPDATE error: ' . mysqli_error($conn));
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
        or throw new Exception('Strip suffix error: ' . mysqli_error($conn));
    $msgs[] = "<p>✔ Stripped both '-A' and 'A' suffixes from pd_sv codes</p>";

    $deleteSQL = "
          DELETE FROM election_sites
          WHERE pd_sv RLIKE '^[0-9]+-[0-9]+(-?[A-Za-z])$'
        ";
    mysqli_query($conn, $deleteSQL)
        or throw new Exception('Delete suffixed duplicates error: ' . mysqli_error($conn));
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
        or throw new Exception('Duplicate-cleanup error: ' . mysqli_error($conn));
    $msgs[] = '<p>✔ Removed duplicate pd_sv rows, kept lowest-id</p>';

    mysqli_close($conn);
    unlink($csvFile);

    return $msgs;
}

?>
