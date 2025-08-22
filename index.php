<?php
require_once __DIR__ . '/template.php';
$body = '<h2>Polling Location Tools</h2>';
$body .= '<a class="button" href="ploc_web.php">Upload CSV</a>';
$body .= '<a class="button" href="ploc_adv.php">Advanced Export</a>';
$body .= '<a class="button" href="ploc_reg.php">Regular Export</a>';
render_template('Polling Location Tools', $body);

