<?php
function render_template($title, $body) {
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>{$title}</title>
<style>
body{font-family:Arial,sans-serif;margin:2em;}
a.button{display:inline-block;margin:0.5em;padding:0.5em 1em;background:#007bff;color:#fff;text-decoration:none;border-radius:4px;}
a.button:hover{background:#0056b3;}
</style>
</head>
<body>
{$body}
</body>
</html>
HTML;
}
