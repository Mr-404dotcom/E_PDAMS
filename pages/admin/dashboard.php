<?php
include '../../server/api.php';
session_start();

$totalStudents = 0;
$totalRecords = 0;
$activeRecords = 0;
$totalViolations = 0;
$pendingCases = 0;
$resolvedCases = 0;
$dismissedCases = 0;
$weeklyViolations = 0;
$pendingReview = 0;
$studentsOnClearanceHold = 0;
$recentRecords = [];
$dbError = null;
require_once '../../server/db.php';
$conn = getDbConnection();

if ($conn) {
    $summaryQuery = "SELECT COUNT(*) AS total_records, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_records FROM students";
    $summaryResult = mysqli_query($conn, $summaryQuery);
    if ($summaryResult && mysqli_num_rows($summaryResult) > 0) {
        $summaryRow = mysqli_fetch_assoc($summaryResult);
        $totalStudents = (int) ($summaryRow['total_records'] ?? 0);
        $totalRecords = (int) ($summaryRow['total_records'] ?? 0);
        $activeRecords = (int) ($summaryRow['active_records'] ?? 0);
    }

    $weeklyViolationsQuery = "SELECT COUNT(*) AS weekly_violations FROM infraction_logging WHERE date_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $weeklyViolationsResult = mysqli_query($conn, $weeklyViolationsQuery);
    if ($weeklyViolationsResult && mysqli_num_rows($weeklyViolationsResult) > 0) {
        $weeklyViolationsRow = mysqli_fetch_assoc($weeklyViolationsResult);
        $weeklyViolations = (int) ($weeklyViolationsRow['weekly_violations'] ?? 0);
    }

    $violationsQuery = "SELECT COUNT(*) AS total_violations FROM infraction_logging";
    $violationsResult = mysqli_query($conn, $violationsQuery);
    if ($violationsResult && mysqli_num_rows($violationsResult) > 0) {
        $violationsRow = mysqli_fetch_assoc($violationsResult);
        $totalViolations = (int) ($violationsRow['total_violations'] ?? 0);
    }

    $pendingQuery = "SELECT COUNT(*) AS pending_review FROM infraction_logging WHERE LOWER(status) = 'pending'";
    $pendingResult = mysqli_query($conn, $pendingQuery);
    if ($pendingResult && mysqli_num_rows($pendingResult) > 0) {
        $pendingRow = mysqli_fetch_assoc($pendingResult);
        $pendingCases = (int) ($pendingRow['pending_review'] ?? 0);
        $pendingReview = $pendingCases;
    }

    $resolvedQuery = "SELECT COUNT(*) AS resolved_cases FROM infraction_logging WHERE LOWER(status) = 'resolved'";
    $resolvedResult = mysqli_query($conn, $resolvedQuery);
    if ($resolvedResult && mysqli_num_rows($resolvedResult) > 0) {
        $resolvedRow = mysqli_fetch_assoc($resolvedResult);
        $resolvedCases = (int) ($resolvedRow['resolved_cases'] ?? 0);
    }

    $dismissedQuery = "SELECT COUNT(*) AS dismissed_cases FROM infraction_logging WHERE LOWER(status) = 'dismissed'";
    $dismissedResult = mysqli_query($conn, $dismissedQuery);
    if ($dismissedResult && mysqli_num_rows($dismissedResult) > 0) {
        $dismissedRow = mysqli_fetch_assoc($dismissedResult);
        $dismissedCases = (int) ($dismissedRow['dismissed_cases'] ?? 0);
    }

    $clearanceHoldsResult = mysqli_query($conn, "SELECT COUNT(DISTINCT student_id) AS students_on_hold FROM clearance_hold WHERE LOWER(status) IN ('active', 'on_hold')");
    if ($clearanceHoldsResult && mysqli_num_rows($clearanceHoldsResult) > 0) {
        $clearanceHoldsRow = mysqli_fetch_assoc($clearanceHoldsResult);
        $studentsOnClearanceHold = (int) ($clearanceHoldsRow['students_on_hold'] ?? 0);
    }

    $recentRecordsQuery = "
        SELECT
            s.student_id,
            s.student_number,
            CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS full_name,
            s.grade_level,
            s.section,
            s.created_at,
            GROUP_CONCAT(DISTINCT vc.category_name ORDER BY vc.category_name SEPARATOR ', ') AS violations,
            GROUP_CONCAT(DISTINCT i.status ORDER BY i.status SEPARATOR ', ') AS violation_status
        FROM students s
        LEFT JOIN infraction_logging i ON i.student_id = s.student_id
        LEFT JOIN violation_category vc ON vc.category_id = i.category_id
        GROUP BY s.student_id, s.student_number, s.first_name, s.middle_name, s.last_name, s.grade_level, s.section, s.created_at
        ORDER BY COUNT(i.infraction_id) DESC, s.created_at DESC
        LIMIT 5
    ";
    $recentRecordsResult = mysqli_query($conn, $recentRecordsQuery);
    if ($recentRecordsResult) {
        while ($row = mysqli_fetch_assoc($recentRecordsResult)) {
            $recentRecords[] = $row;
        }
    }

    mysqli_close($conn);
} else {
    $dbError = 'Database connection failed. Start MySQL to load recent records.';
}

$todayLabel = date('l, F j, Y');
$caseStatusTotal = $pendingCases + $resolvedCases + $dismissedCases;
$caseStatusPercentages = [
    'pending' => $caseStatusTotal > 0 ? ($pendingCases / $caseStatusTotal) * 100 : 0,
    'resolved' => $caseStatusTotal > 0 ? ($resolvedCases / $caseStatusTotal) * 100 : 0,
    'dismissed' => $caseStatusTotal > 0 ? ($dismissedCases / $caseStatusTotal) * 100 : 0,
];
$caseStatusChart = [
    ['label' => 'Pending', 'count' => $pendingCases, 'class' => 'pending'],
    ['label' => 'Resolved', 'count' => $resolvedCases, 'class' => 'resolved'],
    ['label' => 'Dismissed', 'count' => $dismissedCases, 'class' => 'dismissed'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | E-PDAMS</title>
    <link rel="icon" type="image/png" href="../../assets/logo.png">
    <link rel="stylesheet" href="../css/style.css?v=animations-v1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>
    <div class="page-loader is-ready" id="page-loader" role="status" aria-live="polite">
        <div class="page-loader-content"><span class="page-loader-spinner" aria-hidden="true"></span><span>Loading workspace</span></div>
    </div>
    <div class="dashboard-shell">
        <aside class="sidebar" id="sidebar" aria-label="Primary navigation">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <img src="../../assets/logo.png" alt="E-PDAMS Logo" class="brand-logo-img">
                    <div><strong>E-PDAMS</strong><span>Student discipline</span></div>
                </div>
            </div>
            
            <nav class="sidebar-nav">

                <!-- MAIN -->
                <p class="nav-label">Main</p>

                <a class="nav-link active" href="dashboard.php" aria-current="page">
                    <i class="fa-solid fa-chart-line nav-icon"></i>
                    <span>Dashboard</span>
                </a>

                <a class="nav-link" href="records.php">
                    <i class="fa-solid fa-file-lines nav-icon"></i>
                    <span>Records</span>
                </a>

                <a class="nav-link" href="ParentNotificationTool.php">
                    <i class="fa-solid fa-bell nav-icon"></i>
                    <span>Notifications</span>
                </a>


                <!-- DISCIPLINE -->
                <p class="nav-label">Discipline</p>

                <a class="nav-link" href="violationSetup.php">
                    <i class="fa-solid fa-triangle-exclamation nav-icon"></i>
                    <span>Violation Records</span>
                </a>

                <a class="nav-link" href="sanction.php">
                    <i class="fa-solid fa-gavel nav-icon"></i>
                    <span>Sanctions</span>
                </a>

                <a class="nav-link" href="IncidentReports.php">
                    <i class="fa-solid fa-file-circle-exclamation nav-icon"></i>
                    <span>Incident Reports</span>
                </a>

                <a class="nav-link" href="clearance.php">
                    <i class="fa-solid fa-file-circle-check nav-icon"></i>
                    <span>Clearance</span>
                </a>

                <!-- MANAGEMENT -->
                <p class="nav-label">Management</p>

                <a class="nav-link" href="behaviorTracking.php">
                    <i class="fa-solid fa-user-check nav-icon"></i>
                    <span>Behavior Tracking</span>
                </a>

                <a class="nav-link" href="disciplinarySchedule.php">
                    <i class="fa-solid fa-calendar-days nav-icon"></i>
                    <span>Disciplinary Schedule</span>
                </a>

                <a class="nav-link" href="reformationProgram.php">
                    <i class="fa-solid fa-rotate nav-icon"></i>
                    <span>Reformation Program</span>
                </a>

                <a class="nav-link" href="userManagement.php">
                    <i class="fa-solid fa-user-shield nav-icon"></i>
                    <span>User Accounts</span>
                </a>

            </nav>
            <div class="sidebar-footer">
                <div class="profile-chip">
                    <div class="profile-avatar">AD</div>
                    <div><strong>Administrator</strong><span>System admin</span></div>
                </div>
                <a class="logout-link" href="../../auth/auth_logout.php">
                    <i class="fa-solid fa-right-from-bracket quit"></i>
                    <span>Log out</span>
                </a>
            </div>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <button class="menu-toggle" type="button" aria-controls="sidebar" aria-expanded="false"><i class="fa-solid fa-bars" aria-hidden="true"></i><span class="visually-hidden">Toggle navigation</span></button>
                <div class="breadcrumb"><span>Workspace</span><span aria-hidden="true">/</span><strong>Dashboard</strong></div>
                <div class="topbar-date"><?= htmlspecialchars($todayLabel); ?></div>
            </header>
            <section class="page-heading">
                <div>
                    <p class="eyebrow">Overview</p>
                    <h1>Good morning, Administrator.</h1>
                    <p class="heading-copy">Overview of student disciplinary activities.
                    </p>
                </div>
            </section>
            <section class="metrics dashboard-metrics" aria-label="Dashboard summary">

                <!-- Total Students -->
                <article class="metric-card accent-teal">
                    <div class="metric-icon">
                        <i class="fa-solid fa-users"></i>
                    </div>

                    <div class="metric-content">
                        <span>Total Students</span>
                        <strong><?= number_format($totalStudents); ?></strong>
                        <small>
                            <i class="fa-solid fa-user-check"></i>
                            Currently enrolled
                        </small>
                    </div>
                </article>

                <article class="metric-card accent-green">
                    <div class="metric-icon">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div class="metric-content">
                        <span>Active Records</span>
                        <strong><?= number_format($activeRecords); ?></strong>
                        <small>Current student files</small>
                    </div>
                </article>

                <article class="metric-card accent-coral">
                    <div class="metric-icon">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>

                    <div class="metric-content">
                        <span>Violations This Week</span>
                        <strong><?= number_format($weeklyViolations); ?></strong>
                        <small>
                            <i class="fa-solid fa-file-circle-exclamation"></i>
                            Recorded violations
                        </small>
                    </div>
                </article>

                <article class="metric-card accent-yellow">
                    <div class="metric-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div class="metric-content">
                        <span>Pending Cases</span>
                        <strong><?= number_format($pendingCases); ?></strong>
                        <small>
                            <i class="fa-solid fa-hourglass-half"></i>
                            Awaiting resolution
                        </small>
                    </div>
                </article>

                <a class="metric-card metric-card-link accent-coral" href="clearance.php">
                    <div class="metric-icon"><i class="fa-solid fa-file-circle-exclamation" aria-hidden="true"></i></div>
                    <div class="metric-content">
                        <span>Students on Clearance Hold</span>
                        <strong><?= number_format($studentsOnClearanceHold); ?></strong>
                        <small>Students currently on disciplinary hold</small>
                    </div>
                </a>


            </section>

            <section class="quick-actions" aria-label="Quick actions">
                <a class="quick-action" href="records.php"><i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i><span>Manage student records</span></a>
                <a class="quick-action" href="violationSetup.php"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>Review violations</span></a>
                <a class="quick-action" href="IncidentReports.php"><i class="fa-solid fa-file-circle-exclamation" aria-hidden="true"></i><span>Open incident reports</span></a>
            </section>

            <?php if ($pendingCases > 0): ?>
                <aside class="dashboard-notice" role="status">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <p><strong><?= number_format($pendingCases); ?> pending <?= $pendingCases === 1 ? 'case' : 'cases'; ?> need review</strong><span>Review the current violation records.</span></p>
                    <a href="violationSetup.php">Review cases</a>
                </aside>
            <?php endif; ?>

            <div class="lower-dashboard-grid">
                <section class="activity-panel" id="records">
                    <div class="panel-heading">
                        <div>
                            <p class="eyebrow">Latest activity</p>
                            <h2>Recent Violations</h2>
                        </div><a href="records.php">View all <span aria-hidden="true">?</span></a>
                    </div>

                    <?php if (!empty($dbError)): ?>
                        <div class="empty-state">
                            <div class="empty-icon" aria-hidden="true">!</div>
                            <strong>Unable to load records</strong>
                            <p><?= htmlspecialchars($dbError); ?></p>
                        </div>
                    <?php elseif (!empty($recentRecords)): ?>
                        <div class="records-table-wrap">
                            <table class="records-table">
                                <thead>
                                    <tr>
                                        <th>Student name</th>
                                        <th>Status</th>
                                        <th>Violations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentRecords as $record): ?>
                                        <?php
                                        $statusLabel = trim((string) ($record['violation_status'] ?? ''));
                                        $status = strtolower($statusLabel);
                                        $badgeClass = 'approved';
                                        if ($status === 'inactive') {
                                            $badgeClass = 'archived';
                                        } elseif ($status === 'pending') {
                                            $badgeClass = 'pending';
                                        } elseif ($status === 'active') {
                                            $badgeClass = 'approved';
                                        } else {
                                            $badgeClass = 'review';
                                        }
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) ($record['full_name'] ?? '')); ?></td>
                                            <td><span class="status-badge <?= $badgeClass; ?>"><?= htmlspecialchars($statusLabel !== '' ? $statusLabel : 'No violation'); ?></span></td>
                                            <td><?= htmlspecialchars((string) ($record['violations'] ?? '0')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon" aria-hidden="true">?</div>
                            <strong>Your latest records will appear here</strong>
                            <p>Start by adding a new record to your workspace.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="case-status-panel">
                    <div class="case-status-card">
                        <h2>Case Status</h2>
                        <div class="case-status-chart" aria-label="Case status chart">
                            <div class="case-status-total <?= $caseStatusTotal > 0 ? '' : 'empty'; ?>" style="--pending-percent: <?= $caseStatusPercentages['pending']; ?>%; --resolved-percent: <?= $caseStatusPercentages['resolved']; ?>%; --dismissed-percent: <?= $caseStatusPercentages['dismissed']; ?>%;">
                                <strong><?= number_format($caseStatusTotal); ?></strong>
                                <span>Total cases</span>
                            </div>
                            <div class="case-status-bars" role="img" aria-label="Pending <?= number_format($pendingCases); ?>, Resolved <?= number_format($resolvedCases); ?>, Dismissed <?= number_format($dismissedCases); ?>">
                                <?php foreach ($caseStatusChart as $caseStatus): ?>
                                    <?php $percentage = $caseStatusPercentages[$caseStatus['class']]; ?>
                                    <div class="case-status-bar <?= $caseStatus['class']; ?>">
                                        <span><?= htmlspecialchars($caseStatus['label']); ?></span>
                                        <div class="case-status-track"><span style="width: <?= $percentage; ?>%"></span></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="case-status-legend">
                            <?php foreach ($caseStatusChart as $caseStatus): ?>
                                <div class="case-status-row">
                                    <span><i class="status-dot <?= $caseStatus['class']; ?>" aria-hidden="true"></i><?= htmlspecialchars($caseStatus['label']); ?></span>
                                    <strong><?= number_format($caseStatus['count']); ?></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <script src="../../server/script.js?v=animations-v1"></script>
</body>

</html>


