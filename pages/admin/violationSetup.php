<?php
include_once '../../server/api.php';
session_start();
/*if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/auth_login.php');
    exit();
}
*/
require_once '../../server/db.php';
$search = sanitizeString($_GET['search'] ?? '', 100);
$violations = [];
$students = [];
$dbError = null;

$conn = getDbConnection();

if ($conn) {
    $baseQuery = "SELECT i.infraction_id, i.student_id, s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name, vc.category_name, vc.severity, i.offense_committed, i.date_time, i.status, CASE WHEN EXISTS (SELECT 1 FROM clearance_hold ch WHERE ch.student_id = i.student_id AND LOWER(ch.status) IN ('active', 'on_hold')) THEN 'ON HOLD' ELSE 'CLEARED' END AS clearance_impact FROM infraction_logging i INNER JOIN students s ON s.student_id = i.student_id INNER JOIN violation_category vc ON vc.category_id = i.category_id";
    
    if ($search !== '') {
        $sql = $baseQuery . " WHERE s.student_number LIKE ? OR CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) LIKE ? OR vc.category_name LIKE ? OR i.offense_committed LIKE ? ORDER BY i.date_time DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $like = '%' . $search . '%';
            $stmt->bind_param('ssss', $like, $like, $like, $like);
            $stmt->execute();
            $violationsResult = $stmt->get_result();
            while ($row = $violationsResult->fetch_assoc()) {
                $violations[] = $row;
            }
            $stmt->close();
        }
    } else {
        $sql = $baseQuery . " ORDER BY i.date_time DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->execute();
            $violationsResult = $stmt->get_result();
            while ($row = $violationsResult->fetch_assoc()) {
                $violations[] = $row;
            }
            $stmt->close();
        }
    }

    $studentsStmt = $conn->prepare("SELECT student_id, student_number, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name FROM students WHERE status = 'Active' ORDER BY last_name, first_name");
    if ($studentsStmt) {
        $studentsStmt->execute();
        $studentsResult = $studentsStmt->get_result();
        while ($row = $studentsResult->fetch_assoc()) {
            $students[] = $row;
        }
        $studentsStmt->close();
    }
    mysqli_close($conn);
} else {
    $dbError = 'Database connection failed. Start the MySQL service and refresh the page.';
}

$todayLabel = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/logo.png">
    <link rel="stylesheet" href="../css/style.css?v=animations-v1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <title>Violation Setup | E-PDAMS</title>
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

                <a class="nav-link" href="dashboard.php">
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

                <a class="nav-link active" href="violationSetup.php" aria-current="page">
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
            <header class="topbar"><button class="menu-toggle" type="button" aria-controls="sidebar" aria-expanded="false"><i class="fa-solid fa-bars" aria-hidden="true"></i><span class="visually-hidden">Toggle navigation</span></button>
                <div class="breadcrumb"><span>Workspace</span><span aria-hidden="true">/</span><strong>Violation category</strong></div>
                <div class="topbar-date"><?= htmlspecialchars($todayLabel); ?></div>
            </header>
            <section class="page-heading">
                <div>
                    <p class="eyebrow">Discipline</p>
                    <h1>Manage violations.</h1>
                    <p class="heading-copy">Review student violations and record new incidents from one place.</p>
                </div>
                <button class="primary-button add-violation" type="button" data-student-id="" data-student-name=""><span aria-hidden="true">+</span>Add violation</button>
            </section>
            <section class="activity-panel" id="violations-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Violation records</p>
                        <h2>Student violations</h2>
                    </div>
                    <div class="records-toolbar">
                        <form method="get" action="violationSetup.php" class="records-search-form"><label class="search-box" aria-label="Search violations"><span aria-hidden="true">?</span><input type="search" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search Student" aria-label="Search violations"></label><button class="primary-button small-button" type="submit">Filter</button></form>
                    </div>
                </div>
                <?php if ($dbError): ?>
                    <div class="empty-state">
                        <div class="empty-icon" aria-hidden="true">!</div><strong>Unable to load violations</strong>
                        <p><?= htmlspecialchars($dbError); ?></p>
                    </div>
                <?php else: ?>
                    <div class="records-table-wrap">
                        <table class="records-table violations-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Category</th>
                                    <th>Severity</th>
                                    <th>Offense</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Clearance Impact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($violations)): ?>
                                    <?php foreach ($violations as $violation): ?>
                                        <?php $violationStatus = strtolower(trim((string) ($violation['status'] ?? '')));
                                        $violationBadge = $violationStatus === 'resolved' ? 'approved' : ($violationStatus === 'dismissed' ? 'archived' : 'pending'); ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars((string) ($violation['student_name'] ?? '')); ?></strong><small><?= htmlspecialchars((string) ($violation['student_number'] ?? '')); ?></small></td>
                                            <td><?= htmlspecialchars((string) ($violation['category_name'] ?? '')); ?></td>
                                            <td><span class="severity-label"><?= htmlspecialchars((string) ($violation['severity'] ?? '')); ?></span></td>
                                            <td><?= htmlspecialchars((string) ($violation['offense_committed'] ?? '')); ?></td>
                                            <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime((string) ($violation['date_time'] ?? 'now')))); ?></td>
                                            <td><span class="status-badge <?= $violationBadge; ?>"><?= htmlspecialchars(ucfirst((string) ($violation['status'] ?? 'Pending'))); ?></span></td>
                                            <?php $clearanceImpactClass = ($violation['clearance_impact'] ?? '') === 'ON HOLD' ? 'on_hold' : 'cleared'; ?>
                                            <td><span class="status-badge clearance-status <?= $clearanceImpactClass; ?>"><?= htmlspecialchars((string) ($violation['clearance_impact'] ?? 'CLEARED')); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state compact-empty-state">
                                                <div class="empty-icon" aria-hidden="true">!</div><strong>No violations found</strong>
                                                <p>Add a violation or adjust your search.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <div class="record-modal" id="violation-modal" hidden>
        <form class="record-form" id="violation-form">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Student violation</p>
                    <h2>Add violation</h2>
                    <p class="modal-student" id="violation-student-name"></p>
                </div><button class="modal-close" id="close-violation-modal" type="button" aria-label="Close">&times;</button>
            </div>
            <div class="form-grid">
                <label for="violation-student-id">
                    Student
                    <select id="violation-student-id" name="student_id" required>
                        <option value="" disabled selected>Select a student</option>
                        <?php foreach ($students as $student): ?>
                            <option
                                value="<?= (int) $student['student_id']; ?>"
                                data-name="<?= htmlspecialchars(
                                                (string) $student['full_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>">
                                <?= htmlspecialchars(
                                    (string) $student['student_number'] . ' - ' . (string) $student['full_name']
                                ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Category<input id="violation-category" name="category_name" required placeholder="e.g. Late attendance"></label>
                <label>Severity<select id="violation-severity" name="severity">
                        <option>Minor</option>
                        <option>Moderate</option>
                        <option>Major</option>
                    </select></label>
                <label>Offense<input id="violation-offense" name="offense_committed" required></label>
                <label>Date and time<input id="violation-date" name="date_time" type="datetime-local" required></label>
                <label class="full-field">Evidence<textarea id="violation-evidence" name="evidence" rows="3"></textarea></label>
            </div>
            <p class="form-error" id="violation-form-error" role="alert"></p>
            <div class="modal-actions"><button class="secondary-button" id="cancel-violation" type="button">Cancel</button><button class="primary-button" type="submit">Save violation</button></div>
        </form>
    </div>
    <script src="../../server/script.js?v=animations-v1"></script>
    <script src="../../server/records.js"></script>
</body>

</html>


