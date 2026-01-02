<?php
/**
 * MediAssist+ Configuration File
 */

// Start output buffering to prevent stray output
ob_start();

// Error reporting - log errors but don't display (prevents JSON corruption)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Application Settings
define('APP_NAME', 'MediAssist+');
define('APP_VERSION', '1.0.0');
define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// JWT Settings (for future implementation)
define('JWT_SECRET', 'your-secret-key-change-in-production');
define('JWT_EXPIRY', 86400); // 24 hours

// Create upload directories if not exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

/**
 * Send JSON response
 */
function sendResponse($data, $status = 200) {
    // Clear any buffered output to prevent JSON corruption
    ob_clean();
    http_response_code($status);
    echo json_encode($data);
    exit();
}

/**
 * Send error response
 */
function sendError($message, $status = 400) {
    sendResponse(['success' => false, 'error' => $message], $status);
}

/**
 * Get JSON input
 */
function getJsonInput() {
    $json = file_get_contents('php://input');
    return json_decode($json, true) ?? [];
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    return $missing;
}

/**
 * Sanitize input
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)));
}
