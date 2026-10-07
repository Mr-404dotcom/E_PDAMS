<?php
require_once '../server/db.php';
session_start();

if (!empty($_SESSION['edams_user'])) {
    header('Location: ../pages/admin/dashboard.php');
    exit;
}

$setupError = null;
$setupAvailable = false;
$conn = getDbConnection();

if (!$conn) {
    $setupError = 'Database connection failed. Start MySQL and refresh the page.';
} else {
    $countStatement = $conn->prepare('SELECT COUNT(*) AS total FROM users');
    if ($countStatement) {
        $countStatement->execute();
        $setupAvailable = (int) ($countStatement->get_result()->fetch_assoc()['total'] ?? 0) === 0;
        $countStatement->close();
    } else {
        $setupError = 'Unable to check account setup. Please try again.';
    }
}

if (!$setupAvailable && !$setupError) {
    $conn?->close();
    header('Location: auth_login.php');
    exit;
}

if (empty($_SESSION['first_admin_csrf'])) {
    $_SESSION['first_admin_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['first_admin_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$setupError) {
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $fullName = sanitizeString($_POST['full_name'] ?? '', 100);
    $username = sanitizeString($_POST['username'] ?? '', 50);
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!hash_equals($csrfToken, $submittedToken)) {
        $setupError = 'This setup form has expired. Refresh the page and try again.';
    } elseif (strlen($fullName) < 2 || strlen($fullName) > 100) {
        $setupError = 'Enter a name between 2 and 100 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
        $setupError = 'Username must be 3 to 50 characters using letters, numbers, dots, underscores, or hyphens.';
    } elseif (strlen($password) < 12 || strlen($password) > 255) {
        $setupError = 'Choose a password between 12 and 255 characters.';
    } elseif (!hash_equals($password, $passwordConfirmation)) {
        $setupError = 'The passwords do not match.';
    } else {
        $lockName = 'epdams_first_admin_setup';
        $lockStatement = $conn->prepare('SELECT GET_LOCK(?, 5) AS acquired');
        $lockAcquired = false;

        if ($lockStatement) {
            $lockStatement->bind_param('s', $lockName);
            $lockStatement->execute();
            $lockAcquired = (int) ($lockStatement->get_result()->fetch_assoc()['acquired'] ?? 0) === 1;
            $lockStatement->close();
        }

        if (!$lockAcquired) {
            $setupError = 'Account setup is busy. Wait a moment and try again.';
        } else {
            $recheckStatement = $conn->prepare('SELECT COUNT(*) AS total FROM users');
            if (!$recheckStatement) {
                $setupError = 'Unable to verify account setup. Please try again.';
            } else {
                $recheckStatement->execute();
                $usersExist = (int) ($recheckStatement->get_result()->fetch_assoc()['total'] ?? 0) > 0;
                $recheckStatement->close();

                if ($usersExist) {
                    $setupError = 'An account has already been created. Sign in instead.';
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'admin';
                    $status = 'Active';
                    $insertStatement = $conn->prepare('INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, ?, ?)');

                    if (!$insertStatement) {
                        $setupError = 'Unable to create the administrator account. Please try again.';
                    } else {
                        $insertStatement->bind_param('sssss', $username, $passwordHash, $fullName, $role, $status);
                        if ($insertStatement->execute()) {
                            $newUserId = $insertStatement->insert_id;
                            $insertStatement->close();
                            $releaseStatement = $conn->prepare('SELECT RELEASE_LOCK(?)');
                            if ($releaseStatement) {
                                $releaseStatement->bind_param('s', $lockName);
                                $releaseStatement->execute();
                                $releaseStatement->close();
                            }
                            $conn->close();
                            session_regenerate_id(true);
                            $_SESSION['edams_user'] = [
                                'id' => $newUserId,
                                'username' => $username,
                                'full_name' => $fullName,
                                'role' => $role,
                                'status' => $status,
                            ];
                            unset($_SESSION['first_admin_csrf']);
                            header('Location: ../pages/admin/dashboard.php');
                            exit;
                        }
                        $setupError = $insertStatement->errno === 1062
                            ? 'That username is already in use. Choose another one.'
                            : 'Unable to create the administrator account. Please try again.';
                        $insertStatement->close();
                    }
                }
            }

            $releaseStatement = $conn->prepare('SELECT RELEASE_LOCK(?)');
            if ($releaseStatement) {
                $releaseStatement->bind_param('s', $lockName);
                $releaseStatement->execute();
                $releaseStatement->close();
            }
        }
    }
}

if ($conn) {
    $conn->close();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" href="../assets/logo.png">
    <link rel="stylesheet" href="../pages/css/style.css?v=modal-fix-v2">
    <title>Set Up Administrator | E-PDAMS</title>
</head>
<body class="auth-page">
    <div class="auth-shell">
        <aside class="auth-visual">
            <div class="auth-brand">
                <img src="../assets/logo.png" alt="E-PDAMS Logo" class="auth-logo-img">
                <div><strong>E-PDAMS</strong><small>Electronic Prefect Disciplinary Action Management System</small></div>
            </div>
            <div class="auth-visual-copy">
                <p class="eyebrow">First-time setup</p>
                <h2>Create your administrator account.</h2>
                <p>This one-time setup is available only while the system has no registered accounts.</p>
            </div>
            <ul class="auth-features">
                <li>Administrator access to E-PDAMS</li>
                <li>Password stored securely as a hash</li>
                <li>Account setup closes after registration</li>
            </ul>
        </aside>
        <main class="auth-card">
            <h1>Admin setup</h1>
            <p class="muted">Choose your own username and password.</p>
            <?php if ($setupError): ?>
                <p class="alert" role="alert"><?= htmlspecialchars($setupError, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <form method="post" action="auth_sign_in.php" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="field-group">
                    <label for="full_name">Full name</label>
                    <input id="full_name" name="full_name" type="text" required maxlength="100" autocomplete="name" value="<?= htmlspecialchars((string) ($_POST['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Your full name">
                </div>
                <div class="field-group">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" required minlength="3" maxlength="50" pattern="[A-Za-z0-9._\-]+" autocomplete="username" value="<?= htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="Choose a username">
                </div>
                <div class="field-group">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required minlength="12" maxlength="255" autocomplete="new-password" placeholder="At least 12 characters">
                </div>
                <div class="field-group">
                    <label for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" maxlength="255" autocomplete="new-password" placeholder="Enter the same password">
                </div>
                <button type="submit">Create administrator account</button>
                <p class="muted auth-signup-row"><a class="signup-link" href="auth_login.php">Back to log in</a></p>
            </form>
        </main>
    </div>
    <script src="../server/script.js?v=modal-fix-v2"></script>
</body>
</html>

