<?php
require_once __DIR__ . '/export.php';

generate_export(true);

echo '<p>Export complete. <a href="ploc_adv_download.php?reg=1">Download the file</a></p>';
?>
