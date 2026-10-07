<?php
include_once '../../server/api.php';
session_start();

require_once '../../server/db.php';
$search = sanitizeString($_GET['search'] ?? '', 100);
$totalRecords = 0;
$activeRecords = 0;
$inactiveRecords = 0;
$records = [];

$dbError = null;
$conn = getDbConnection();

if ($conn) {
    $baseQuery = "SELECT s.student_id, s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS full_name, s.grade_level, s.section, s.status, s.created_at, h.hold_id AS clearance_hold_id, h.status AS clearance_hold_status FROM students s LEFT JOIN clearance_hold h ON h.hold_id = (SELECT latest.hold_id FROM clearance_hold latest WHERE latest.student_id = s.student_id ORDER BY latest.hold_date DESC, latest.hold_id DESC LIMIT 1)";

    if ($search !== '') {
        $sql = $baseQuery . " WHERE s.student_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) LIKE ? ORDER BY s.created_at DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $like = '%' . $search . '%';
            $stmt->bind_param('ssss', $like, $like, $like, $like);
            $stmt->execute();
            $recordsResult = $stmt->get_result();
            while ($row = $recordsResult->fetch_assoc()) {
                $records[] = $row;
            }
            $stmt->close();
        }
    } else {
        $sql = $baseQuery . " ORDER BY s.created_at DESC LIMIT 50";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->execute();
            $recordsResult = $stmt->get_result();
            while ($row = $recordsResult->fetch_assoc()) {
                $records[] = $row;
            }
            $stmt->close();
        }
    }

    $summaryQuery = "SELECT COUNT(*) AS total_records, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_records, SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) AS inactive_records FROM students";
    $summaryStmt = $conn->prepare($summaryQuery);
    if ($summaryStmt) {
        $summaryStmt->execute();
        $summaryRow = $summaryStmt->get_result()->fetch_assoc();
        $totalRecords = (int) ($summaryRow['total_records'] ?? 0);
        $activeRecords = (int) ($summaryRow['active_records'] ?? 0);
        $inactiveRecords = (int) ($summaryRow['inactive_records'] ?? 0);
        $summaryStmt->close();
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
    <title>Records | E-PDAMS</title>
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

                <a class="nav-link active" href="records.php" aria-current="page">
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
                <div class="breadcrumb"><span>Workspace</span><span aria-hidden="true">/</span><strong>Records</strong></div>
                <div class="topbar-date"><?= htmlspecialchars($todayLabel); ?></div>
            </header>

            <section class="page-heading">
                <div>
                    <p class="eyebrow">Records</p>
                    <h1>Manage student records.</h1>
                    <p class="heading-copy">Review current enrollment records, monitor student status, and keep your files up to date.</p>
                </div>
                <button class="primary-button" id="new-record-button" type="button"><span aria-hidden="true">+</span>New record</button>
            </section>

            <section class="metrics" aria-label="Records summary">
                <article class="metric-card accent-teal"><span>Total records</span><strong><?= number_format($totalRecords); ?></strong><small>Across active enrollment files</small></article>
                <article class="metric-card accent-coral"><span>Active</span><strong><?= number_format($activeRecords); ?></strong><small><?= number_format($inactiveRecords); ?> inactive</small></article>
                <article class="metric-card accent-yellow"><span>Latest entries</span><strong><?= count($records); ?></strong><small>Viewable in current list</small></article>
            </section>


            <section class="activity-panel" id="records-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Overview</p>
                        <h2>Recent records</h2>
                    </div>
                    <div class="records-toolbar">
                        <form method="get" action="records.php" class="records-search-form">
                            <label class="search-box" aria-label="Search records">
                                <span aria-hidden="true">?</span>
                                <input type="search" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Search records" aria-label="Search records">
                            </label>
                            <button class="primary-button small-button" type="submit">Filter</button>
                        </form>
                    </div>
                </div>

                <div class="records-table-wrap">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Student ID</th>
                                <th>Student no.</th>
                                <th>Student name</th>
                                <th>Year level</th>
                                <th>Section</th>
                                <th>Status</th>
                                <th>Clearance</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($records)): ?>
                                <?php foreach ($records as $record): ?>
                                    <?php
                                    $status = strtolower(trim((string) ($record['status'] ?? '')));
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
                                        <td><?= htmlspecialchars((string) ($record['student_id'] ?? '')); ?></td>
                                        <td><?= htmlspecialchars((string) ($record['student_number'] ?? '')); ?></td>
                                        <td><?= htmlspecialchars((string) ($record['full_name'] ?? '')); ?></td>
                                        <td><?= htmlspecialchars((string) ($record['grade_level'] ?? '')); ?></td>
                                        <td><?= htmlspecialchars((string) ($record['section'] ?? '')); ?></td>
                                        <td><span class="status-badge <?= $badgeClass; ?>"><?= htmlspecialchars(ucfirst((string) ($record['status'] ?? 'Unknown'))); ?></span></td>
                                        <?php $clearanceHoldActive = in_array(strtolower((string) ($record['clearance_hold_status'] ?? '')), ['active', 'on_hold'], true); ?>
                                        <td><span class="status-badge clearance-status <?= $clearanceHoldActive ? 'on_hold' : 'cleared'; ?>"><?= $clearanceHoldActive ? 'ON HOLD' : 'CLEARED'; ?></span></td>
                                        <td><?= htmlspecialchars(date('M j, Y', strtotime((string) ($record['created_at'] ?? 'now')))); ?></td>
                                        <td class="record-actions">
                                            <button class="table-action edit-record" type="button" data-record='<?= htmlspecialchars(json_encode($record), ENT_QUOTES, 'UTF-8'); ?>'>Edit</button>
                                            <button class="table-action delete-record" type="button" data-id="<?= (int) $record['student_id']; ?>">Delete</button>
                                            <?php if ($clearanceHoldActive): ?><a class="table-action" href="clearance.php?student_id=<?= (int) $record['student_id']; ?>">View Clearance Hold</a><?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="empty-state compact-empty-state">
                                            <div class="empty-icon" aria-hidden="true">?</div>
                                            <strong>No matching records found</strong>
                                            <p>Try adjusting your search or add a new student record.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </main>
    </div>
    <div class="record-modal" id="record-modal" hidden>
        <form class="record-form" id="record-form">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Student record</p>
                    <h2 id="record-form-title">New record</h2>
                </div>
                <button class="modal-close" id="close-record-modal" type="button" aria-label="Close">&times;</button>
            </div>
            <input id="record-id" type="hidden">
            <div class="form-grid">
                <label>Student number<input id="student-number" name="student_number" required></label>
                <label>Status<select id="student-status" name="status">
                        <option>Active</option>
                        <option>Inactive</option>
                        <option>Pending</option>
                    </select></label>
                <label>First name<input id="first-name" name="first_name" required></label>
                <label>Middle name<input id="middle-name" name="middle_name"></label>
                <label>Last name<input id="last-name" name="last_name" required></label>
                <label>Gender<select id="gender" name="gender">
                        <option value="">Select</option>
                        <option>Male</option>
                        <option>Female</option>
                    </select></label>
                <label>Year level<input id="grade-level" name="grade_level"></label>
                <label>Section<input id="section" name="section"></label>
                <label>Parent/Guardian Name<input id="parent-name" name="parent_name"></label>
                <label>Parent Contact<input id="parent-contact" name="parent_contact" placeholder="e.g. 09123456789"></label>
                <label>Parent Email<input id="parent-email" name="parent_email" type="email"></label>
                <label class="full-field">Address<input id="address" name="address"></label>
            </div>
            <p class="form-error" id="record-form-error" role="alert"></p>
            <div class="modal-actions"><button class="secondary-button" id="cancel-record" type="button">Cancel</button><button class="primary-button" type="submit">Save record</button></div>
        </form>
    </div>
    <script src="../../server/script.js?v=animations-v1"></script>
    <script src="../../server/records.js"></script>
</body>

</html>


