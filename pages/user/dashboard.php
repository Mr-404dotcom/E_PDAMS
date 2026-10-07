<?php
// E-PDAMS Student & Parent Portal
// Allows students and parents to check their clearance status, recorded violations, assigned sanctions, and behavior standing.

require_once '../../server/db.php';
session_start();

$conn = getDbConnection();
$rawSearch = trim((string) ($_GET['student_number'] ?? ''));
$searchNumber = sanitizeStudentNumber($rawSearch);
$normalizedSearch = normalizeStudentNumber($searchNumber);
$studentData = null;
$violations = [];
$sanctions = [];
$clearanceHold = null;
$schedules = [];
$reformations = [];
$behaviorScore = 100;
$behaviorStatus = 'Excellent Conduct';
$badgeClass = 'approved';

if ($conn && $searchNumber !== '') {
    // Flexible student lookup: matches exact number or normalized format (without hyphens/spaces)
    $stmt = $conn->prepare("SELECT * FROM students 
                            WHERE student_number = ? 
                               OR REPLACE(REPLACE(REPLACE(student_number, '-', ''), ' ', ''), '_', '') = ? 
                            LIMIT 1");
    $stmt->bind_param('ss', $searchNumber, $normalizedSearch);
    $stmt->execute();
    $studentData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($studentData) {
        $sid = (int) $studentData['student_id'];

        // clearance hold
        $hStmt = $conn->prepare("SELECT * FROM clearance_hold WHERE student_id = ? AND LOWER(status) IN ('active', 'on_hold') ORDER BY hold_date DESC LIMIT 1");
        $hStmt->bind_param('i', $sid);
        $hStmt->execute();
        $clearanceHold = $hStmt->get_result()->fetch_assoc();
        $hStmt->close();

        // violations
        $vStmt = $conn->prepare("SELECT i.*, vc.category_name, vc.severity FROM infraction_logging i INNER JOIN violation_category vc ON vc.category_id = i.category_id WHERE i.student_id = ? ORDER BY i.date_time DESC");
        $vStmt->bind_param('i', $sid);
        $vStmt->execute();
        $vRes = $vStmt->get_result();
        while ($vr = $vRes->fetch_assoc()) $violations[] = $vr;
        $vStmt->close();

        // sanctions
        $sStmt = $conn->prepare("SELECT * FROM sanctions WHERE student_id = ? ORDER BY created_at DESC");
        $sStmt->bind_param('i', $sid);
        $sStmt->execute();
        $sRes = $sStmt->get_result();
        while ($sr = $sRes->fetch_assoc()) $sanctions[] = $sr;
        $sStmt->close();

        // schedules
        $scStmt = $conn->prepare("SELECT * FROM disciplinary_schedule WHERE student_id = ? ORDER BY hearing_date ASC");
        $scStmt->bind_param('i', $sid);
        $scStmt->execute();
        $scRes = $scStmt->get_result();
        while ($scr = $scRes->fetch_assoc()) $schedules[] = $scr;
        $scStmt->close();

        // reformations
        $rfStmt = $conn->prepare("SELECT sr.*, rp.program_name, rp.duration_hours FROM student_reformations sr INNER JOIN reformation_programs rp ON rp.program_id = sr.program_id WHERE sr.student_id = ?");
        $rfStmt->bind_param('i', $sid);
        $rfStmt->execute();
        $rfRes = $rfStmt->get_result();
        while ($rfr = $rfRes->fetch_assoc()) $reformations[] = $rfr;
        $rfStmt->close();

        // behavior score calculation
        $minorCount = 0;
        $moderateCount = 0;
        $majorCount = 0;
        foreach ($violations as $v) {
            $sev = $v['severity'];
            if ($sev === 'Minor') $minorCount++;
            elseif ($sev === 'Moderate') $moderateCount++;
            elseif ($sev === 'Major') $majorCount++;
        }

        $pStmt = $conn->prepare("SELECT COALESCE(SUM(points), 0) AS total_pts FROM behavior_points WHERE student_id = ?");
        $pStmt->bind_param('i', $sid);
        $pStmt->execute();
        $bonus = (int) ($pStmt->get_result()->fetch_assoc()['total_pts'] ?? 0);
        $pStmt->close();

        $deductions = ($minorCount * 2) + ($moderateCount * 5) + ($majorCount * 15);
        $raw = 100 - $deductions + $bonus;
        $behaviorScore = max(0, min(100, $raw));

        if ($behaviorScore >= 90) {
            $behaviorStatus = 'Excellent Conduct';
            $badgeClass = 'approved';
        } elseif ($behaviorScore >= 80) {
            $behaviorStatus = 'Good Conduct';
            $badgeClass = 'approved';
        } elseif ($behaviorScore >= 70) {
            $behaviorStatus = 'Under Observation';
            $badgeClass = 'review';
        } else {
            $behaviorStatus = 'Intervention Required';
            $badgeClass = 'pending';
        }
    }

    $conn->close();
}

$todayLabel = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Clearance &amp; Discipline Portal | E-PDAMS</title>
    <link rel="icon" type="image/png" href="../../assets/logo.png">
    <link rel="stylesheet" href="../css/style.css?v=animations-v1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Body with matching full-bleed background */
        body.portal-body {
            margin: 0;
            font-family: 'DM Sans', sans-serif;
            color: var(--ink);
            background-color: #0c3355;
            background-image: linear-gradient(135deg, rgba(8, 30, 52, 0.72) 0%, rgba(14, 58, 94, 0.60) 100%), url('../../assets/bg.jpg');
            background-size: cover;
            background-position: center center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        .portal-page-shell {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        /* Top Navigation Bar */
        .portal-nav {
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

        .portal-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .portal-brand img {
            width: 38px;
            height: 38px;
            object-fit: contain;
            border-radius: 8px;
        }

        .portal-brand-text strong {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: #124d78;
            letter-spacing: -0.01em;
            display: block;
            line-height: 1.15;
        }

        .portal-brand-text span {
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .portal-nav-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-btn-home {
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

        .nav-btn-home:hover {
            background: #dfeef8;
            color: #0b3758;
        }

        .nav-btn-login {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13px;
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

        /* Main Content Container */
        .portal-content-wrap {
            flex: 1;
            max-width: 1040px;
            width: 100%;
            margin: 0 auto;
            padding: clamp(28px, 4vw, 48px) clamp(16px, 3.5vw, 24px) 50px;
            box-sizing: border-box;
        }

        /* Search Hero Card */
        .search-hero {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: 16px;
            padding: 34px 28px 28px;
            text-align: center;
            box-shadow: 0 16px 36px rgba(5, 25, 45, 0.22);
            margin-bottom: 26px;
            position: relative;
            overflow: hidden;
        }

        .search-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #124d78, #1672b8, #f2c76a);
        }

        .search-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 5px 14px;
            border-radius: 99px;
            background: #eef5f9;
            color: #124d78;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .search-badge i {
            color: var(--teal);
        }

        .search-hero h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(22px, 3.2vw, 30px);
            font-weight: 700;
            color: var(--ink);
            margin: 0 0 10px;
            letter-spacing: -0.01em;
            max-width: 100%;
            text-align: center;
        }

        .search-hero p {
            color: var(--muted);
            font-size: 14px;
            max-width: 580px;
            margin: 0 auto 22px;
            line-height: 1.55;
        }

        .search-input-group {
            display: flex;
            align-items: center;
            background: #ffffff;
            border: 2px solid #cbd5e1;
            border-radius: 12px;
            padding: 5px 5px 5px 16px;
            max-width: 520px;
            margin: 0 auto;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
            transition: border-color 0.18s ease, box-shadow 0.18s ease;
        }

        .search-input-group:focus-within {
            border-color: var(--teal);
            box-shadow: 0 0 0 3px rgba(22, 114, 184, 0.15);
        }

        .search-input-group input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 15px;
            font-family: inherit;
            color: var(--ink);
            min-width: 0;
            background: transparent;
        }

        .search-input-group button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 9px;
            background: var(--teal);
            color: #fff;
            border: none;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            transition: background 0.15s ease, transform 0.15s ease;
            flex-shrink: 0;
        }

        .search-input-group button:hover {
            background: var(--teal-dark);
        }

        .search-privacy-hint {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
            font-size: 12.5px;
            color: #486581;
            line-height: 1.4;
            background: #f0f6fa;
            border: 1px solid #d4e5f2;
            padding: 7px 16px;
            border-radius: 99px;
            max-width: 580px;
        }

        .search-privacy-hint i {
            color: var(--teal);
            font-size: 13px;
            flex-shrink: 0;
        }

        /* Clearance Status Banner */
        .clearance-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 22px 28px;
            border-radius: 14px;
            margin-bottom: 24px;
            color: #ffffff;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.18);
        }

        .clearance-banner.cleared {
            background: linear-gradient(135deg, #187741, #0f5c31);
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .clearance-banner.on_hold {
            background: linear-gradient(135deg, #b93830, #8f221c);
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .banner-content {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .banner-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            display: grid;
            place-items: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .banner-text h2 {
            margin: 0 0 4px;
            font-size: 20px;
            font-family: 'Space Grotesk', sans-serif;
            letter-spacing: 0.02em;
        }

        .banner-text p {
            margin: 0;
            font-size: 13.5px;
            opacity: 0.95;
            line-height: 1.45;
        }

        .banner-badge {
            background: rgba(255, 255, 255, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.35);
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* Student Profile Summary */
        .student-profile-summary {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: 14px;
            padding: 22px 26px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px rgba(5, 25, 45, 0.14);
        }

        .profile-stat-box {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .profile-stat-box .stat-label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--muted);
            letter-spacing: 0.03em;
        }

        .profile-stat-box .stat-label i {
            color: var(--teal);
        }

        .profile-stat-box .stat-val {
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        /* Activity Panels */
        .activity-panel {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: 14px;
            box-shadow: 0 10px 25px rgba(5, 25, 45, 0.14);
            margin-bottom: 24px;
            overflow: hidden;
        }

        .activity-panel .panel-heading {
            padding: 18px 24px;
            border-bottom: 1px solid var(--line);
            background: #fafcfe;
        }

        .activity-panel .panel-heading h2 {
            margin: 2px 0 0;
            font-size: 18px;
            font-family: 'Space Grotesk', sans-serif;
            color: var(--ink);
        }

        /* Guidance Cards (Initial empty state) */
        .guidance-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 10px;
        }

        .guidance-card {
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(255, 255, 255, 0.75);
            border-radius: 14px;
            padding: 24px 22px;
            box-shadow: 0 10px 24px rgba(5, 25, 45, 0.14);
            text-align: left;
            transition: transform 0.2s ease;
        }

        .guidance-card:hover {
            transform: translateY(-3px);
        }

        .guidance-card .g-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            font-size: 20px;
            margin-bottom: 14px;
            background: #e8f2f9;
            color: #124d78;
        }

        .guidance-card h3 {
            margin: 0 0 8px;
            font-size: 16px;
            font-family: 'Space Grotesk', sans-serif;
            color: var(--ink);
        }

        .guidance-card p {
            margin: 0;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
        }

        /* Not found card */
        .not-found-card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(255, 255, 255, 0.85);
            border-radius: 14px;
            box-shadow: 0 10px 25px rgba(5, 25, 45, 0.14);
            text-align: center;
            padding: 48px 24px;
        }

        .not-found-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #fee2e2;
            color: #b91c1c;
            display: grid;
            place-items: center;
            font-size: 26px;
            margin: 0 auto 16px;
        }

        .not-found-card h2 {
            margin: 0 0 8px;
            font-size: 20px;
            font-family: 'Space Grotesk', sans-serif;
            color: var(--ink);
        }

        /* Footer */
        .portal-footer {
            text-align: center;
            padding: 22px 20px;
            color: #b5cfe4;
            font-size: 12px;
            background: rgba(7, 24, 42, 0.92);
            border-top: 1px solid rgba(255, 255, 255, 0.12);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .portal-nav {
                padding: 10px 14px;
            }

            .portal-brand-text span {
                display: none;
            }

            .nav-btn-home span {
                display: none;
            }

            .nav-btn-login {
                padding: 7px 12px;
                font-size: 12px;
                white-space: nowrap;
            }

            .portal-content-wrap {
                padding: 20px 14px 40px;
            }

            .search-hero {
                padding: 24px 16px 20px;
            }

            .clearance-banner {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
                padding: 18px 16px;
            }

            .banner-badge {
                width: 100%;
                text-align: center;
                box-sizing: border-box;
            }

            .student-profile-summary {
                grid-template-columns: repeat(2, 1fr);
                gap: 14px;
                padding: 18px 16px;
            }

            .guidance-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }
        }

        @media (max-width: 500px) {
            .search-input-group {
                flex-direction: column;
                padding: 8px;
                gap: 8px;
            }

            .search-input-group input {
                width: 100%;
                padding: 8px 10px;
                text-align: center;
                box-sizing: border-box;
            }

            .search-input-group button {
                width: 100%;
                justify-content: center;
                padding: 10px 16px;
                box-sizing: border-box;
            }

            .student-profile-summary {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="portal-body">
    <!-- Global process loading state -->
    <div class="page-loader is-ready" id="page-loader" role="status" aria-live="polite">
        <div class="page-loader-content">
            <span class="page-loader-spinner" aria-hidden="true"></span>
            <span>Loading workspace</span>
        </div>
    </div>

    <div class="portal-page-shell">
        <!-- Navigation Bar -->
        <header class="portal-nav">
            <a href="../../index.php" class="portal-brand">
                <img src="../../assets/logo.png" alt="E-PDAMS Logo">
                <div class="portal-brand-text">
                    <strong>E-PDAMS</strong>
                    <span>Student &amp; Parent Verification Portal</span>
                </div>
            </a>
            <div class="portal-nav-actions">
                <a href="../../index.php" class="nav-btn-home">
                    <i class="fa-solid fa-house"></i>
                    <span>Home</span>
                </a>
                <a href="../../auth/auth_login.php" class="nav-btn-login">
                    <i class="fa-solid fa-lock"></i>
                    <span>Staff Sign In</span>
                </a>
            </div>
        </header>

        <!-- Main Content -->
        <main class="portal-content-wrap">
            <!-- Search Hero Card -->
            <section class="search-hero" aria-label="Student search form">
                <div class="search-badge">
                    <i class="fa-solid fa-file-circle-check"></i>
                    <span>Official Clearance Verification</span>
                </div>
                <h1>Check Student Disciplinary Records</h1>
                <p>Enter the assigned Student ID Number to inspect disciplinary clearance standing, review sanctions, and verify records.</p>

                <form method="get" action="dashboard.php" class="search-input-group" role="search">
                    <input type="text" name="student_number" value="<?= htmlspecialchars($rawSearch); ?>" placeholder="Enter Student ID Number..." required autocomplete="off">
                    <button type="submit">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <span>Verify Standing</span>
                    </button>
                </form>

                <div class="search-privacy-hint">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <span>Confidential Verification &bull; Official Student ID Required</span>
                </div>
            </section>

            <?php if ($studentData): ?>
                <!-- Clearance Status Banner -->
                <?php if ($clearanceHold): ?>
                    <div class="clearance-banner on_hold" role="alert">
                        <div class="banner-content">
                            <div class="banner-icon">
                                <i class="fa-solid fa-circle-exclamation"></i>
                            </div>
                            <div class="banner-text">
                                <h2>CLEARANCE ON HOLD</h2>
                                <p>A disciplinary hold has been flagged by the Prefect Office. Hold Reason: <strong><?= htmlspecialchars($clearanceHold['reason']); ?></strong></p>
                            </div>
                        </div>
                        <div>
                            <span class="banner-badge">ACTION REQUIRED</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="clearance-banner cleared" role="status">
                        <div class="banner-content">
                            <div class="banner-icon">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div class="banner-text">
                                <h2>CLEARANCE CLEARED</h2>
                                <p>No active disciplinary holds on file. Student is in good standing with the Prefect Office.</p>
                            </div>
                        </div>
                        <div>
                            <span class="banner-badge">GOOD STANDING</span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Student Profile Summary -->
                <section class="student-profile-summary" aria-label="Student profile overview">
                    <div class="profile-stat-box">
                        <span class="stat-label"><i class="fa-solid fa-user"></i> Student Name</span>
                        <span class="stat-val"><?= htmlspecialchars($studentData['first_name'] . ' ' . $studentData['last_name']); ?></span>
                    </div>
                    <div class="profile-stat-box">
                        <span class="stat-label"><i class="fa-solid fa-id-card"></i> Student ID</span>
                        <span class="stat-val"><?= htmlspecialchars($studentData['student_number']); ?></span>
                    </div>
                    <div class="profile-stat-box">
                        <span class="stat-label"><i class="fa-solid fa-school"></i> Grade &amp; Section</span>
                        <span class="stat-val"><?= htmlspecialchars($studentData['grade_level'] . ' - ' . $studentData['section']); ?></span>
                    </div>
                    <div class="profile-stat-box">
                        <span class="stat-label"><i class="fa-solid fa-chart-line"></i> Behavior Standing</span>
                        <span class="stat-val">
                            <span><?= $behaviorScore; ?>/100</span>
                            <span class="status-badge <?= $badgeClass; ?>"><?= $behaviorStatus; ?></span>
                        </span>
                    </div>
                </section>

                <!-- Assigned Sanctions Section -->
                <section class="activity-panel" aria-label="Assigned sanctions">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Corrective Measures</p>
                            <h2>Assigned Sanctions &amp; Requirements</h2>
                        </div>
                    </div>
                    <div class="records-table-wrap">
                        <table class="records-table">
                            <thead>
                                <tr>
                                    <th>Sanction Title</th>
                                    <th>Type</th>
                                    <th>Schedule</th>
                                    <th>Status</th>
                                    <th>Instructions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($sanctions)): ?>
                                    <?php foreach ($sanctions as $san): ?>
                                        <tr>
                                            <td data-label="Sanction Title"><strong><?= htmlspecialchars($san['sanction_name']); ?></strong></td>
                                            <td data-label="Type"><span class="severity-label"><?= htmlspecialchars($san['sanction_type']); ?></span></td>
                                            <td data-label="Schedule"><?= $san['start_date'] ? htmlspecialchars(date('M j, Y', strtotime($san['start_date']))) : 'Immediate'; ?></td>
                                            <td data-label="Status"><span class="status-badge <?= strtolower($san['status']) === 'completed' ? 'approved' : 'pending'; ?>"><?= htmlspecialchars($san['status']); ?></span></td>
                                            <td data-label="Instructions"><?= htmlspecialchars($san['description'] ?: 'Complete as instructed by prefect.'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center; color:var(--muted); padding:24px;">No pending corrective measures on file.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- Logged Infractions History -->
                <section class="activity-panel" aria-label="Infractions history">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Disciplinary History</p>
                            <h2>Logged Infractions</h2>
                        </div>
                    </div>
                    <div class="records-table-wrap">
                        <table class="records-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Offense Description</th>
                                    <th>Date Recorded</th>
                                    <th>Severity</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($violations)): ?>
                                    <?php foreach ($violations as $v): ?>
                                        <tr>
                                            <td data-label="Category"><strong><?= htmlspecialchars($v['category_name']); ?></strong></td>
                                            <td data-label="Offense Description"><?= htmlspecialchars($v['offense_committed']); ?></td>
                                            <td data-label="Date Recorded"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($v['date_time']))); ?></td>
                                            <td data-label="Severity"><span class="severity-label"><?= htmlspecialchars($v['severity']); ?></span></td>
                                            <td data-label="Status"><span class="status-badge <?= strtolower($v['status']) === 'resolved' ? 'approved' : 'pending'; ?>"><?= htmlspecialchars($v['status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" style="text-align:center; color:var(--muted); padding:24px;">No disciplinary violations recorded.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php elseif ($searchNumber !== ''): ?>
                <!-- Not Found State -->
                <section class="not-found-card" role="alert">
                    <div class="not-found-icon">
                        <i class="fa-solid fa-user-slash"></i>
                    </div>
                    <h2>Student Record Not Found</h2>
                    <p style="color:var(--muted); max-width:440px; margin:0 auto 18px; font-size:14px;">
                        No student record found matching <code><?= htmlspecialchars($searchNumber); ?></code>. Please verify the ID number or contact your school prefect office.
                    </p>
                    <a href="dashboard.php" class="secondary-button" style="display:inline-flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-rotate-left"></i> Clear Search
                    </a>
                </section>
            <?php else: ?>
                <!-- Initial Guidance Grid -->
                <section class="guidance-grid" aria-label="Portal guidance">
                    <div class="guidance-card">
                        <div class="g-icon">
                            <i class="fa-solid fa-certificate"></i>
                        </div>
                        <h3>Instant Digital Clearance</h3>
                        <p>Verify your official clearance standing in real time for graduation, enrollment, credentials, and honors eligibility.</p>
                    </div>
                    <div class="guidance-card">
                        <div class="g-icon">
                            <i class="fa-solid fa-scale-balanced"></i>
                        </div>
                        <h3>Restorative Accountability</h3>
                        <p>Track corrective sanctions, community service hours, and scheduled conferences transparently.</p>
                    </div>
                    <div class="guidance-card">
                        <div class="g-icon">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <h3>Guardian Collaboration</h3>
                        <p>Enables parents and guardians to stay actively involved in student conduct and behavior improvement plans.</p>
                    </div>
                </section>
            <?php endif; ?>
        </main>

        <!-- Footer -->
        <footer class="portal-footer">
            <div>
                &copy; <?= date('Y'); ?> Electronic Prefect Disciplinary Action Management System (E-PDAMS). All rights reserved.
            </div>
        </footer>
    </div>

    <script src="../../server/script.js?v=animations-v1"></script>
</body>

</html>
