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

