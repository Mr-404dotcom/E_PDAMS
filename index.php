<?php
// E-PDAMS Landing & Gateway Router


require_once __DIR__ . '/server/db.php';
session_start();

// If already logged in as staff/admin, go directly to admin dashboard
if (!empty($_SESSION['edams_user'])) {
    header('Location: pages/admin/dashboard.php');
    exit;
}

// If system has 0 registered users (fresh installation), route to initial admin setup
$conn = getDbConnection();
if ($conn) {
    $userCountRes = $conn->query("SELECT COUNT(*) AS total FROM users");
    $totalUsers = (int) ($userCountRes->fetch_assoc()['total'] ?? 0);
    $conn->close();

    if ($totalUsers === 0) {
        header('Location: auth/auth_sign_in.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-PDAMS | Prefect Disciplinary Action & Clearance Management System</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <link rel="stylesheet" href="pages/css/style.css?v=animations-v1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Landing page custom backdrop and engaging layout */
        body.landing-body {
            margin: 0;
            font-family: 'DM Sans', sans-serif;
            color: var(--ink);
            background-color: #0c3355;
            background-image: linear-gradient(135deg, rgba(8, 30, 52, 0.72) 0%, rgba(14, 58, 94, 0.60) 100%), url('assets/bg.jpg');
            background-size: cover;
            background-position: center center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        .landing-shell {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        /* Top Navigation Bar */
        .landing-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px clamp(18px, 4.5vw, 56px);
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 4px 20px rgba(0, 18, 40, 0.12);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .landing-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .landing-brand img {
            width: 38px;
            height: 38px;
            object-fit: contain;
            border-radius: 8px;
        }

        .landing-brand-text strong {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #124d78;
            letter-spacing: -0.01em;
            display: block;
            line-height: 1.15;
        }

        .landing-brand-text span {
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .landing-nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-btn-clearance {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #124d78;
            background: #eef5f9;
            text-decoration: none;
            transition: background 0.18s ease, color 0.18s ease;
        }

        .nav-btn-clearance:hover {
            background: #dfeef8;
            color: #0b3758;
        }

        .nav-btn-login {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            color: #ffffff;
            background: var(--teal);
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(22, 114, 184, 0.3);
            transition: transform 0.18s ease, background 0.18s ease, box-shadow 0.18s ease;
        }

        .nav-btn-login:hover {
            background: var(--teal-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(18, 77, 120, 0.4);
        }

        /* Hero Section */
        .landing-hero {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: clamp(36px, 6vw, 64px) clamp(16px, 4vw, 48px) 44px;
        }

        .hero-intro {
            max-width: 920px;
            width: 100%;
            text-align: center;
            color: #ffffff;
            margin-bottom: 34px;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 99px;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.32);
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 18px;
        }

        .hero-badge i {
            color: #f2c76a;
        }

        .hero-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(28px, 4vw, 46px);
            font-weight: 700;
            line-height: 1.18;
            letter-spacing: -0.02em;
            margin: 0 auto 18px;
            max-width: 860px;
            width: 100%;
            text-align: center;
            color: #ffffff;
            text-shadow: 0 2px 16px rgba(0, 15, 35, 0.35);
        }

        .hero-copy {
            font-size: clamp(15px, 1.8vw, 17px);
            line-height: 1.62;
            color: #e4f1fa;
            max-width: 720px;
            margin: 0 auto 28px;
            text-shadow: 0 1px 6px rgba(0, 15, 35, 0.25);
        }

        /* Inline Instant Clearance Search Bar */
        .hero-search-container {
            max-width: 640px;
            margin: 0 auto 12px;
            width: 100%;
        }

        .hero-search-bar {
            display: flex;
            align-items: center;
            background: #ffffff;
            border-radius: 14px;
            padding: 6px 6px 6px 18px;
            box-shadow: 0 12px 35px rgba(4, 24, 45, 0.35);
            border: 2px solid rgba(255, 255, 255, 0.85);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .hero-search-bar:focus-within {
            transform: translateY(-2px);
            box-shadow: 0 18px 45px rgba(4, 24, 45, 0.45);
            border-color: #f2c76a;
        }

        .search-icon {
            font-size: 18px;
            color: var(--teal);
            margin-right: 12px;
            flex-shrink: 0;
        }

        .hero-search-bar input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 15px;
            font-family: 'DM Sans', sans-serif;
            color: var(--ink);
            background: transparent;
            min-width: 0;
        }

        .hero-search-bar input::placeholder {
            color: #8fa0af;
        }

        .hero-search-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 22px;
            border-radius: 10px;
            background: var(--teal);
            color: #ffffff;
            border: none;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            flex-shrink: 0;
            transition: background 0.15s ease, transform 0.15s ease;
        }

        .hero-search-btn:hover {
            background: var(--teal-dark);
        }

        .hero-search-hints {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
            font-size: 12.5px;
            color: #d1e7f7;
            margin-top: 10px;
        }

        .hero-search-hints span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .hero-search-hints i {
            color: #f2c76a;
            font-size: 11px;
        }

        /* Dual Portal Gateway Section */
        .portal-cards-wrapper {
            max-width: 960px;
            width: 100%;
            margin-bottom: 32px;
        }

        .portals-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 22px;
        }

        .portal-login-action {
            grid-column: 1 / -1;
            display: flex;
            justify-content: center;
        }

        .portal-login-action .nav-btn-login {
            width: min(100%, 420px);
            justify-content: center;
            padding: 12px 20px;
        }

        .portal-card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 16px;
            padding: 32px 28px;
            box-shadow: 0 16px 36px rgba(5, 25, 45, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.8);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .portal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 22px 50px rgba(5, 25, 45, 0.32);
        }

        .portal-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .portal-card.staff-card::before {
            background: linear-gradient(90deg, #124d78, #1672b8);
        }

        .portal-card.student-card::before {
            background: linear-gradient(90deg, #1672b8, #f2c76a);
        }

        .portal-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 16px;
        }

        .portal-badge.staff {
            background: #e6f0f8;
            color: #124d78;
        }

        .portal-badge.student {
            background: #fdf5e2;
            color: #9c6809;
        }

        .portal-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .portal-avatar-icon {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .portal-avatar-icon.staff {
            background: #e8f2f9;
            color: #124d78;
        }

        .portal-avatar-icon.student {
            background: #eef7f4;
            color: #1a7550;
        }

        .portal-title-area h3 {
            margin: 0;
            font-size: 20px;
            font-family: 'Space Grotesk', sans-serif;
            color: var(--ink);
        }

        .portal-title-area p {
            margin: 4px 0 0;
            font-size: 13px;
            color: var(--muted);
        }

        .portal-features-list {
            list-style: none;
            padding: 0;
            margin: 16px 0 24px;
        }

        .portal-features-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13.5px;
            color: #3b5062;
            margin-bottom: 9px;
            line-height: 1.45;
        }

        .portal-features-list li i {
            font-size: 12px;
            margin-top: 3px;
            flex-shrink: 0;
        }

        .portal-features-list li.staff-check i {
            color: #1672b8;
        }

        .portal-features-list li.student-check i {
            color: #21805a;
        }

        .portal-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 13px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            transition: transform 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
            box-sizing: border-box;
        }

        .portal-btn-primary {
            background: var(--teal);
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(22, 114, 184, 0.28);
        }

        .portal-btn-primary:hover {
            background: var(--teal-dark);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(18, 77, 120, 0.35);
        }

        .portal-btn-secondary {
            background: #f1f6fa;
            color: #124d78;
            border: 1px solid #c9dbe7;
        }

        .portal-btn-secondary:hover {
            background: #e2eef7;
            color: #0b3758;
            transform: translateY(-1px);
        }

        /* System Capabilities / Feature Cards Grid */
        .features-grid {
            max-width: 960px;
            width: 100%;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.94);
            border-radius: 12px;
            padding: 20px 18px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 8px 22px rgba(5, 25, 45, 0.14);
            text-align: left;
            transition: transform 0.2s ease;
        }

        .feature-card:hover {
            transform: translateY(-3px);
        }

        .feature-card .f-icon {
            font-size: 20px;
            color: var(--teal);
            margin-bottom: 12px;
            display: block;
        }

        .feature-card h4 {
            margin: 0 0 6px;
            font-size: 14px;
            font-family: 'Space Grotesk', sans-serif;
            color: var(--ink);
            font-weight: 700;
        }

        .feature-card p {
            margin: 0;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.5;
        }

        /* Trust / Institutional Highlights Strip */
        .trust-strip {
            max-width: 960px;
            width: 100%;
            display: flex;
            justify-content: space-around;
            align-items: center;
            background: rgba(8, 28, 48, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 14px;
            padding: 14px 20px;
            color: #ffffff;
            flex-wrap: wrap;
            gap: 16px;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 13px;
            font-weight: 600;
            color: #e4f1fa;
        }

        .trust-item i {
            color: #f2c76a;
            font-size: 15px;
        }

        /* Footer */
        .landing-footer {
            text-align: center;
            padding: 22px 20px;
            color: #b5cfe4;
            font-size: 12px;
            background: rgba(7, 24, 42, 0.92);
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }

        .landing-footer a {
            color: #ffffff;
            text-decoration: underline;
        }

        /* Mobile & Tablet Responsiveness */
        @media (max-width: 860px) {
            .portals-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 14px;
            }
        }

        @media (max-width: 600px) {
            .landing-nav {
                padding: 10px 14px;
            }

            .landing-brand img {
                width: 32px;
                height: 32px;
            }

            .landing-brand-text strong {
                font-size: 16px;
            }

            .landing-brand-text span {
                display: none;
            }

            .nav-btn-clearance {
                display: none;
            }

            .nav-btn-login {
                padding: 7px 12px;
                font-size: 12px;
                white-space: nowrap;
            }

            .landing-hero {
                padding: 24px 14px 36px;
            }

            .hero-badge {
                font-size: 10px;
                padding: 4px 10px;
            }

            .hero-title {
                font-size: clamp(20px, 5.2vw, 23px);
                line-height: 1.25;
            }

            .hero-copy {
                font-size: 13.5px;
                margin-bottom: 22px;
            }

            .hero-search-bar {
                flex-direction: column;
                padding: 8px;
                gap: 8px;
            }

            .hero-search-bar .search-icon {
                display: none;
            }

            .hero-search-bar input {
                width: 100%;
                padding: 8px 10px;
                text-align: center;
                font-size: 13.5px;
                box-sizing: border-box;
            }

            .hero-search-btn {
                width: 100%;
                padding: 10px 16px;
                justify-content: center;
                font-size: 13.5px;
                box-sizing: border-box;
            }

            .hero-search-hints {
                gap: 8px;
                font-size: 11px;
            }

            .portal-card {
                padding: 22px 18px;
            }

            .portal-title-area h3 {
                font-size: 18px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .trust-strip {
                flex-direction: column;
                align-items: flex-start;
                padding: 14px 16px;
                gap: 8px;
            }
        }
    </style>
</head>

<body class="landing-body">
    <!-- Global process loading state -->
    <div class="page-loader is-ready" id="page-loader" role="status" aria-live="polite">
        <div class="page-loader-content">
            <span class="page-loader-spinner" aria-hidden="true"></span>
            <span>Loading workspace</span>
        </div>
    </div>

    <div class="landing-shell">
        <!-- Top Navigation -->
        <header class="landing-nav">
            <a href="index.php" class="landing-brand">
                <img src="assets/logo.png" alt="E-PDAMS Logo">
                <div class="landing-brand-text">
                    <strong>E-PDAMS</strong>
                    <span>Prefect Disciplinary Action Management</span>
                </div>
            </a>

        </header>

        <!-- Main Engaging Hero -->
        <main class="landing-hero">
            <div class="hero-intro">
                <div class="hero-badge">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>Official Institutional Portal</span>
                </div>
                <h1 class="hero-title">Centralized Student Discipline &amp; Clearance Management</h1>
                <p class="hero-copy">
                    A modern institutional system uniting student conduct recording, corrective action scheduling,
                    proactive guardian notifications, and automated clearance validation in one secure platform.
                </p>

                <!-- Fast Clearance Lookup Widget -->
                <div class="hero-search-container">
                    <form action="pages/user/dashboard.php" method="GET" class="hero-search-bar" role="search" aria-label="Student clearance search">
                        <i class="fa-solid fa-magnifying-glass search-icon" aria-hidden="true"></i>
                        <input type="text" name="student_number" placeholder="Enter Student ID Number" required autocomplete="off">
                        <button type="submit" class="hero-search-btn">
                            <span>Check Clearance</span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>
                    <div class="hero-search-hints">
                        <span><i class="fa-solid fa-circle-check"></i> Instant Clearance Status</span>
                        <span><i class="fa-solid fa-bolt"></i> No Account Required</span>
                        <span><i class="fa-solid fa-shield-check"></i> Live School Database</span>
                    </div>
                </div>
            </div>

            <!-- Dual Portal Gateway Section -->
            <section class="portal-cards-wrapper" aria-label="System access portals">
                <div class="portals-grid">
                    <!-- Staff / Administrative Workspace -->
                    <article class="portal-card staff-card">
                        <div>
                            <span class="portal-badge staff">Staff &amp; Administration</span>
                            <div class="portal-header">
                                <div class="portal-avatar-icon staff">
                                    <i class="fa-solid fa-user-shield"></i>
                                </div>
                                <div class="portal-title-area">
                                    <h3>Prefect &amp; Admin Office</h3>
                                    <p>Authorized school personnel &amp; coordinators</p>
                                </div>
                            </div>
                            <ul class="portal-features-list">
                                <li class="staff-check">
                                    <i class="fa-solid fa-check"></i>
                                    <span>Log and categorize student policy infractions with severity ratings</span>
                                </li>
                                <li class="staff-check">
                                    <i class="fa-solid fa-check"></i>
                                    <span>Assign restorative sanctions and track disciplinary schedules</span>
                                </li>
                                <li class="staff-check">
                                    <i class="fa-solid fa-check"></i>
                                    <span>Automated SMS and email communication bridge for parents</span>
                                </li>
                                <li class="staff-check">
                                    <i class="fa-solid fa-check"></i>
                                    <span>Grant, hold, or revoke student disciplinary clearances</span>
                                </li>
                            </ul>
                        </div>
                      
                    </article>

                    <!-- Student & Parent Clearance Portal -->
                    <article class="portal-card student-card">
                        <div>
                            <span class="portal-badge student">Public Verification</span>
                            <div class="portal-header">
                                <div class="portal-avatar-icon student">
                                    <i class="fa-solid fa-graduation-cap"></i>
                                </div>
                                <div class="portal-title-area">
                                    <h3>Student &amp; Parent Verification</h3>
                                    <p>Clearance lookup and standing check</p>
                                </div>
                            </div>
                            <ul class="portal-features-list">
                                <li class="student-check">
                                    <i class="fa-solid fa-check"></i>
                                    <span>Immediate clearance standing check for graduation and enrollment</span>
                                </li>
                                <li class="student-check">
                                    <i class="fa-solid fa-check"></i>
                                    <span>Review recorded offenses, dates, and severity categories</span>
                                </li>
                                <li class="student-check">
                                    <i class="fa-solid fa-check"></i>
                                    <span>Monitor reformation progress and community service completion</span>
                                </li>
                                <li class="student-check">
                                    <i class="fa-solid fa-check"></i>
                                    <span>Transparent, fair accountability tracking with zero barriers</span>
                                </li>
                            </ul>
                        </div>
                        
                    </article>
                    <div class="portal-login-action">
                        <a href="auth/auth_login.php" class="nav-btn-login">
                            <i class="fa-solid fa-right-to-bracket"></i>
                            <span>Go to Login</span>
                        </a>
                    </div>
                </div>
            </section>

            <!-- 4 System Feature Cards -->
            <section class="features-grid" aria-label="System capabilities">
                <div class="feature-card">
                    <i class="fa-solid fa-clipboard-list f-icon"></i>
                    <h4>Infraction Logging</h4>
                    <p>Accurate incident tracking with standardized severity levels and official student records.</p>
                </div>
                <div class="feature-card">
                    <i class="fa-solid fa-scale-balanced f-icon"></i>
                    <h4>Restorative Justice</h4>
                    <p>Structured disciplinary hearings and rehabilitation programs fostering positive growth.</p>
                </div>
                <div class="feature-card">
                    <i class="fa-solid fa-comments f-icon"></i>
                    <h4>Parent Engagement</h4>
                    <p>Instant parent notifications via automated messaging to strengthen school-home collaboration.</p>
                </div>
                <div class="feature-card">
                    <i class="fa-solid fa-certificate f-icon"></i>
                    <h4>Clearance Engine</h4>
                    <p>Automatic verification engine preventing graduation or transfer delays for cleared students.</p>
                </div>
            </section>

            <!-- Trust / Institutional Highlights -->
            <div class="trust-strip">
                <div class="trust-item">
                    <i class="fa-solid fa-shield-check"></i>
                    <span>Enterprise Role-Based Security</span>
                </div>
                <div class="trust-item">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>Real-Time Audit Trail</span>
                </div>
                <div class="trust-item">
                    <i class="fa-solid fa-mobile-screen"></i>
                    <span>Mobile Responsive Access</span>
                </div>
                <div class="trust-item">
                    <i class="fa-solid fa-school"></i>
                    <span>Standardized Student Clearances</span>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="landing-footer">
            <div>
                &copy; <?= date('Y'); ?> Electronic Prefect Disciplinary Action Management System (E-PDAMS). All rights reserved.
            </div>
        </footer>
    </div>

    <script src="server/script.js?v=animations-v1"></script>
</body>

</html>