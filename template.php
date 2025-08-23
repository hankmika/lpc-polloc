<?php
function render_template($title, $body) {
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$title}</title>
<link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="container">
{$body}
</div>
</body>
</html>
HTML;
}

function render_error($message) {
    $msg  = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $body = "<p><strong>{$msg}</strong></p>";
    $body .= '<p><a class="button" href="index.php">Main Menu</a> ';
    $body .= '<a class="button" href="ploc_web.php">Upload Form</a></p>';
    render_template('Error', $body);
    exit;
}

