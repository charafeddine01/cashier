<?php
declare(strict_types=1);

/**
 * OmniPOS Pro - Local Development Server Router
 * Simulates Apache / cPanel .htaccess routing for `php -S 0.0.0.0:8000 router.php`
 *
 * Requirements:
 * 1. Requests starting with /api route directly to api/index.php.
 * 2. Static files in dist/ (like /assets/*, /manifest.json, /sw.js) served with correct MIME types.
 * 3. Static files in public/ (like /uploads/*, /favicon.ico) served with correct MIME types.
 * 4. All other routes serve dist/index.html (SPA Fallback).
 */

$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = urldecode((string)parse_url($rawUri, PHP_URL_PATH));

// Helper: Serve a static file with explicit MIME type and exit
function serveStaticFile(string $filePath): void {
    if (!is_file($filePath)) {
        return;
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'js'    => 'application/javascript; charset=UTF-8',
        'mjs'   => 'application/javascript; charset=UTF-8',
        'css'   => 'text/css; charset=UTF-8',
        'json'  => 'application/json; charset=UTF-8',
        'svg'   => 'image/svg+xml',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'webp'  => 'image/webp',
        'ico'   => 'image/x-icon',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'html'  => 'text/html; charset=UTF-8',
        'txt'   => 'text/plain; charset=UTF-8',
    ];

    $mime = $mimeTypes[$ext] ?? (function_exists('mime_content_type') ? (mime_content_type($filePath) ?: 'application/octet-stream') : 'application/octet-stream');

    header("Content-Type: {$mime}");
    header("Content-Length: " . (string)filesize($filePath));
    header("Access-Control-Allow-Origin: *");
    header("Cache-Control: no-cache, must-revalidate");
    readfile($filePath);
    exit;
}

// 1. API Endpoint Routing (100% POST protocol)
if (str_starts_with($uri, '/api')) {
    require __DIR__ . '/api/index.php';
    exit;
}

// 1b. Central License Authority API Routing (/license-api or /portal/license-api)
if (str_starts_with($uri, '/license-api') || str_starts_with($uri, '/portal/license-api')) {
    require __DIR__ . '/portal/license-api/index.php';
    exit;
}

// 1c. Portal Installer (/portal/install)
if (str_starts_with($uri, '/portal/install')) {
    require __DIR__ . '/portal/install/index.php';
    exit;
}

// 1d. Portal Showcase Site & Assets (/portal)
if ($uri === '/portal' || $uri === '/portal/') {
    serveStaticFile(__DIR__ . '/portal/index.html');
}
if (str_starts_with($uri, '/portal/')) {
    $portalFilePath = __DIR__ . $uri;
    if (is_file($portalFilePath)) {
        serveStaticFile($portalFilePath);
    }
    // Fallback for portal subroutes
    serveStaticFile(__DIR__ . '/portal/index.html');
}

// 2. Vendor License Manager Tool
if (str_starts_with($uri, '/vendor-license-manager')) {
    $filePath = __DIR__ . $uri;
    if (is_file($filePath) && !str_ends_with($filePath, '.php')) {
        serveStaticFile($filePath);
    }
    require __DIR__ . '/vendor-license-manager/index.php';
    exit;
}

// 3. 1-Click Web Installer Tool
if (str_starts_with($uri, '/install')) {
    $filePath = __DIR__ . $uri;
    if (is_file($filePath) && !str_ends_with($filePath, '.php')) {
        serveStaticFile($filePath);
    }
    require __DIR__ . '/install/index.php';
    exit;
}

// 4. Static Assets in dist/ (Vite output: /assets/*, etc.)
if ($uri !== '/') {
    $distPath = __DIR__ . '/dist' . $uri;
    if (is_file($distPath)) {
        serveStaticFile($distPath);
    }
}

// 5. Static Assets in public/ (/sw.js, /manifest.json, /uploads/*, etc.)
if ($uri !== '/') {
    $publicPath = __DIR__ . '/public' . $uri;
    if (is_file($publicPath)) {
        serveStaticFile($publicPath);
    }
}

// 6. Root Direct Files (if any)
if ($uri !== '/') {
    $rootPath = __DIR__ . $uri;
    if (is_file($rootPath) && !str_ends_with($rootPath, '.php') && !str_starts_with($uri, '/database')) {
        serveStaticFile($rootPath);
    }
}

// 7. SPA Fallback: Serve dist/index.html
$indexHtml = __DIR__ . '/dist/index.html';
if (file_exists($indexHtml)) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-cache, must-revalidate');
    readfile($indexHtml);
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo "404 Not Found: dist/index.html not generated. Please run `npm run build`.";
