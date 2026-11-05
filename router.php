<?php
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$publicDir = __DIR__ . '/public';
$targetFile = $publicDir . $path;

// If request is for an existing static file in public, let PHP built-in server serve it directly
if ($path !== '/' && file_exists($targetFile) && !is_dir($targetFile)) {
    return false;
}

// API Heartbeat routing
if ($path === '/api/heartbeat' || $path === '/api/heartbeat.php') {
    require $publicDir . '/api/heartbeat.php';
    return true;
}

// Sitemap XML routing
if ($path === '/sitemap.xml') {
    require $publicDir . '/sitemap.php';
    return true;
}

// Admin panel routing
if ($path === '/admin' || $path === '/admin/' || str_starts_with($path, '/admin/')) {
    require $publicDir . '/admin/index.php';
    return true;
}

// All other routes go to public/index.php (Pretty URLs)
require $publicDir . '/index.php';
