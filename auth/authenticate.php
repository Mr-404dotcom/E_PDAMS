<?php
// E-PDAMS Authentication Processor
// Validates credentials, checks CSRF, sanitizes username, and protects session.

require_once '../server/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: auth_login.php');
    exit;
}

// 1. Verify CSRF Token to prevent cross-site request forgery
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken, 'login_csrf')) {
    $_SESSION['login_error'] = 'Security validation failed. Please try logging in again.';
    header('Location: auth_login.php');
    exit;
}

// 2. Sanitize and validate inputs
$username = sanitizeString($_POST['username'] ?? '', 50);
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'Please enter both username and password.';
    header('Location: auth_login.php');
    exit;
}

// Enforce username format (alphanumeric, dot, underscore, dash)
if (!preg_match('/^[A-Za-z0-9._\-]{3,50}$/', $username)) {
    $_SESSION['login_error'] = 'Invalid username or password.';
    header('Location: auth_login.php');
    exit;
}

// 3. Connect to database
$conn = getDbConnection();
if (!$conn) {
    $_SESSION['login_error'] = 'Database connection failed. Please ensure MySQL is running.';
    header('Location: auth_login.php');
    exit;
}

// 4. Secure prepared statement to fetch user
$stmt = $conn->prepare('SELECT user_id, username, password, full_name, role, status FROM users WHERE username = ? LIMIT 1');
if (!$stmt) {
    $conn->close();
    $_SESSION['login_error'] = 'An internal system error occurred. Please try again.';
    header('Location: auth_login.php');
    exit;
}

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();
$conn->close();

// 5. Verify user existence and password hash
if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION['login_error'] = 'Invalid username or password.';
    header('Location: auth_login.php');
    exit;
}

// 6. Check if account is active
if (strcasecmp((string) $user['status'], 'Active') !== 0) {
    $_SESSION['login_error'] = 'Your account has been deactivated. Please contact an administrator.';
    header('Location: auth_login.php');
    exit;
}

// 7. Regenerate session ID to prevent session fixation attacks
session_regenerate_id(true);

$_SESSION['edams_user'] = [
    'id' => (int) $user['user_id'],
    'username' => $user['username'],
    'full_name' => $user['full_name'],
    'role' => $user['role'],
    'status' => $user['status'],
];

// Clean up login CSRF token after successful login
unset($_SESSION['login_csrf']);

header('Location: ../pages/admin/dashboard.php');
exit;
