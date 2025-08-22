<?php
require_once __DIR__ . '/export.php';

generate_export(false);

echo '<p>Export complete. <a href="ploc_adv_download.php">Download the file</a></p>';
?>
