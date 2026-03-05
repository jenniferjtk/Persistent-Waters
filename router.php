<?php
// router.php
// This makes PHP's built-in server respect clean URLs like Apache would

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve real files directly
if (file_exists(__DIR__ . $uri) && $uri !== '/') {
    return false;
}

// Everything else goes to index.php
require_once __DIR__ . '/index.php';