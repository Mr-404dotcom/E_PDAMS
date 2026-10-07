<?php
require_once '../server/db.php';
session_start();
if (!empty($_SESSION['edams_user'])) {
    header('Location: ../pages/admin/dashboard.php');
    exit;
}
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
$showAdminSetup = false;
$conn = getDbConnection();
if ($conn) {
    $setupResult = $conn->query('SELECT COUNT(*) AS total FROM users');
    $showAdminSetup = $setupResult && (int) ($setupResult->fetch_assoc()['total'] ?? 0) === 0;
    $conn->close();
}
$loginCsrfToken = getCsrfToken('login_csrf');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in | E-PDAMS</title>
    <link rel="icon" type="image/png" href="../assets/logo.png">
    <link rel="stylesheet" href="../pages/css/style.css?v=modal-fix-v2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .password-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }
        .password-input-wrapper input {
            width: 100%;
            padding-right: 44px !important;
        }
        .password-toggle-btn {
            position: absolute;
            right: 8px;
            background: transparent;
            border: 0;
            padding: 8px 10px;
            cursor: pointer;
            color: var(--muted);
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }
        .password-toggle-btn:hover {
            color: var(--teal);
        }
        .back-gateway-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 18px;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .back-gateway-link:hover {
            color: var(--teal);
        }
    </style>
</head>

<body class="auth-page">
    <div class="auth-shell">
        <aside class="auth-visual">
            <div class="auth-brand">
                <img src="../assets/logo.png" alt="E-PDAMS Logo" class="auth-logo-img">
                <div>
                    <strong>E-PDAMS</strong>
                    <small>Electronic Prefect Disciplinary Action Management System</small>
                </div>
            </div>

            <div class="auth-visual-copy">
                <p class="eyebrow">Secure &amp; Efficient Management</p>
                <h2>Manage student disciplinary records with ease.</h2>
                <p>
                    Efficiently manage disciplinary records, monitor student violations,
                    and maintain organized administrative information in one centralized system.
                </p>
            </div>

            <ul class="auth-features">
                <li><i class="fa-solid fa-check" style="margin-right:8px; color:#f2c76a;"></i> Centralized disciplinary records</li>
                <li><i class="fa-solid fa-check" style="margin-right:8px; color:#f2c76a;"></i> Secure role-based access</li>
                <li><i class="fa-solid fa-check" style="margin-right:8px; color:#f2c76a;"></i> Efficient student record management</li>
            </ul>
        </aside>

        <main class="auth-card">
            <a href="../index.php" class="back-gateway-link">
                <i class="fa-solid fa-arrow-left"></i> Back to main portal
            </a>

            <h1>Log in</h1>
            <p class="muted">Access your prefect management workspace.</p>

            <?php if ($error): ?>
                <p class="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form method="post" action="authenticate.php">
                <input type="hidden" name="csrf_token" value="<?= safeHtml($loginCsrfToken); ?>">
                <div class="field-group">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" required autocomplete="username" placeholder="Enter your username">
                </div>

                <div class="field-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">
                        <button type="button" class="password-toggle-btn" id="togglePasswordBtn" aria-label="Toggle password visibility">
                            <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit">Log in</button>
                <?php if ($showAdminSetup): ?>
                    <p class="muted auth-signup-row">No administrator account exists. <a class="signup-link" href="auth_sign_in.php">Set up the first admin</a></p>
                <?php endif; ?>
            </form>
        </main>
    </div>

    <script>
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const passInput = document.getElementById('password');
        const toggleIcon = document.getElementById('togglePasswordIcon');

        toggleBtn?.addEventListener('click', () => {
            const isPassword = passInput.getAttribute('type') === 'password';
            passInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleIcon.classList.toggle('fa-eye', !isPassword);
            toggleIcon.classList.toggle('fa-eye-slash', isPassword);
        });
    </script>
    <script src="../server/script.js?v=modal-fix-v2"></script>
</body>

</html>

