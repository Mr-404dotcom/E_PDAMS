<?php
// E-PDAMS Database Connection & Security Helpers
// Simple procedural style functions for safe database access, input cleaning, and validation.

function getDbConnection()
{
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'prefect_db';

    $conn = mysqli_connect($host, $username, $password, $database);

    if (!$conn) {
        return null;
    }

    // Set utf8mb4 charset so database handles all text safely
    mysqli_set_charset($conn, 'utf8mb4');

    return $conn;
}

// Clean normal single-line string input to prevent XSS and broken text
function sanitizeString($value, $maxLength = 255)
{
    if ($value === null) {
        return '';
    }
    // Remove null bytes
    $clean = str_replace(chr(0), '', (string) $value);
    // Strip script and style blocks entirely including inner contents
    $clean = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $clean);
    // Strip remaining HTML tags
    $clean = strip_tags($clean);
    // Trim spaces
    $clean = trim($clean);
    // Limit length so database column does not overflow
    if ($maxLength > 0 && mb_strlen($clean, 'UTF-8') > $maxLength) {
        $clean = mb_substr($clean, 0, $maxLength, 'UTF-8');
    }
    return $clean;
}

// Clean multiline text like descriptions, recommendations, evidence notes
function sanitizeText($value, $maxLength = 5000)
{
    if ($value === null) {
        return '';
    }
    $clean = str_replace(chr(0), '', (string) $value);
    // Strip script and style blocks entirely including inner contents
    $clean = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $clean);
    // Strip remaining tags but keep plain line breaks
    $clean = strip_tags($clean);
    $clean = trim($clean);
    if ($maxLength > 0 && mb_strlen($clean, 'UTF-8') > $maxLength) {
        $clean = mb_substr($clean, 0, $maxLength, 'UTF-8');
    }
    return $clean;
}

// Check and clean email address
function sanitizeEmail($email)
{
    $clean = trim((string) $email);
    $clean = filter_var($clean, FILTER_SANITIZE_EMAIL);
    if (filter_var($clean, FILTER_VALIDATE_EMAIL)) {
        return $clean;
    }
    return null;
}

// Check and clean phone or contact number
function sanitizePhone($phone)
{
    // Only allow digits, spaces, plus, minus, and parentheses
    $clean = preg_replace('/[^0-9+\-() ]/', '', (string) $phone);
    return trim($clean);
}

// Validate student number (supports formats with or without hyphens, e.g. 2024-0001, 240100999, STU12345)
function sanitizeStudentNumber($number)
{
    $clean = trim((string) $number);
    // Allow letters, numbers, dashes, underscores, and spaces (up to 50 characters)
    if (preg_match('/^[A-Za-z0-9\-_\s]{1,50}$/', $clean)) {
        return $clean;
    }
    return '';
}

// Normalize student number by stripping hyphens, spaces, and underscores for flexible search matching
function normalizeStudentNumber($number)
{
    return preg_replace('/[^A-Za-z0-9]/', '', (string) $number);
}

// Check if value is inside an allowed list (whitelist check)
function sanitizeEnum($value, array $allowedList, $default = null)
{
    $val = trim((string) $value);
    foreach ($allowedList as $allowed) {
        if (strcasecmp($val, $allowed) === 0) {
            return $allowed; // Return the canonical casing from whitelist
        }
    }
    return $default;
}

// Generate CSRF token for forms
function getCsrfToken($key = 'app_csrf_token')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION[$key];
}

// Verify CSRF token from POST
function verifyCsrfToken($token, $key = 'app_csrf_token')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $stored = $_SESSION[$key] ?? '';
    if (!is_string($token) || $stored === '' || !is_string($stored)) {
        return false;
    }
    return hash_equals($stored, $token);
}

// Safe HTML output helper to prevent Cross-Site Scripting (XSS)
function safeHtml($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
