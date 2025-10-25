<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
/**
 * E-LODGE Configuration File
 * Ensure MongoDB extension and Composer dependencies are installed.
 */

// Prevent session warnings
if (session_status() === PHP_SESSION_NONE) {
    // Session ini settings (set only before session starts)
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
}

// Load Composer autoloader (important for MongoDB\Client)
require_once __DIR__ . '/vendor/autoload.php';

// MongoDB Connection URI
$mongoUri = "mongodb://localhost:27017";

// Database name
$dbName = "elodge_db";

// Initialize MongoDB client connection
try {
    $client = new MongoDB\Client($mongoUri);
    $db = $client->selectDatabase($dbName);
} catch (Exception $e) {
    die("⚠️ Failed to connect to MongoDB: " . $e->getMessage());
}

// Timezone
date_default_timezone_set('Asia/Manila');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Application constants
define('APP_NAME', 'E-LODGE');
define('APP_VERSION', '1.0.0');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// Create uploads directory if not existing
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
