<?php
declare(strict_types=1);

/**
 * OmniPOS Pro - Unified API Front Controller
 * 100% POST JSON Protocol Engine
 * Compatible with Shared Hosting (Apache / cPanel / LiteSpeed / PHP 8.2+)
 */

// Autoload Core Classes & Controllers (Case-tolerant for Linux / Windows shared hosting)
spl_autoload_register(function (string $class): void {
    $prefix = 'OmniPOS\\';
    $baseDir = __DIR__ . '/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $parts = explode('\\', $relativeClass);
    $className = array_pop($parts);
    $dir = strtolower(implode('/', $parts));
    $file = $baseDir . ($dir ? $dir . '/' : '') . $className . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\LicenseGuard;
use OmniPOS\Core\Response;
use OmniPOS\Core\Router;

// Handle CORS Preflight & Global Headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origin}");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Terminal-Id');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// STRICT CONSTRAINT: Reject GET, PUT, DELETE, PATCH
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST, OPTIONS');
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success'   => false,
        'message'   => 'Method Not Allowed. OmniPOS Pro API strictly enforces 100% HTTP POST protocol.',
        'error'     => ['code' => 'METHOD_NOT_ALLOWED', 'allowed' => ['POST']],
        'code'      => 405,
        'timestamp' => time(),
    ]);
    exit;
}

// Initialize Application Config
$config = require __DIR__ . '/config.php';
Database::init($config);
LicenseGuard::init($config);
Auth::init($config);

// Parse Request Body (JSON Envelope or Multipart Form Data)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$envelope = [];

if (stripos($contentType, 'application/json') !== false || empty($_POST)) {
    $rawInput = file_get_contents('php://input');
    if (!empty($rawInput)) {
        $envelope = json_decode($rawInput, true);
        if ($envelope === null && json_last_error() !== JSON_ERROR_NONE) {
            Response::error('Invalid JSON envelope payload: ' . json_last_error_msg(), 400);
        }
    }
} else {
    // Multipart / Form-Data support
    $envelope = $_POST;
    if (isset($envelope['payload']) && is_string($envelope['payload'])) {
        $envelope['payload'] = json_decode($envelope['payload'], true) ?: [];
    }
}

if (!is_array($envelope) || empty($envelope)) {
    Response::error('Empty request. Expecting JSON envelope { action, terminal_id, client_timestamp, auth_token, payload }', 400);
}

// Dispatch Action
try {
    Router::dispatch($envelope);
} catch (\Throwable $e) {
    Response::error('Unhandled server exception: ' . $e->getMessage(), 500, [
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ]);
}
