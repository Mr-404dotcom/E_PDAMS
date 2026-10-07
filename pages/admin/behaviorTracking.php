<?php

require_once '../../server/db.php';
require_once '../../server/api.php';
session_start();

$conn = getDbConnection();
$totalStudentsTracked = 0;
$positiveBehaviors = 0;
$behaviorConcerns = 0;
$activeInterventions = 0;
$studentsBehaviorList = [];
$interventionsList = [];
$studentsDropdown = [];
$dbError = null;

if ($conn) {
    // 1. Metric: Total active students
    $stCountRes = $conn->query("SELECT COUNT(*) AS total FROM students WHERE LOWER(status) <> 'inactive'");
    if ($stCountRes && $r = $stCountRes->fetch_assoc()) {
        $totalStudentsTracked = (int) ($r['total'] ?? 0);
    }

    // 2. Metric: Positive points recorded
    $posRes = $conn->query("SELECT COUNT(*) AS total FROM behavior_points WHERE points > 0");
    if ($posRes && $r = $posRes->fetch_assoc()) {
        $positiveBehaviors = (int) ($r['total'] ?? 0);
    }

    // 3. Metric: Behavioral concerns (Active/Pending infractions)
    $infRes = $conn->query("SELECT COUNT(*) AS total FROM infraction_logging WHERE LOWER(status) IN ('pending', 'under observation', 'intervention required')");
    if ($infRes && $r = $infRes->fetch_assoc()) {
        $behaviorConcerns = (int) ($r['total'] ?? 0);
    }

    // 4. Metric: Ongoing interventions
    $intRes = $conn->query("SELECT COUNT(*) AS total FROM behavior_interventions WHERE LOWER(status) IN ('ongoing', 'pending')");
    if ($intRes && $r = $intRes->fetch_assoc()) {
        $activeInterventions = (int) ($r['total'] ?? 0);
    }

    // 5. Fetch students with behavior statistics
    $studentsSql = "SELECT s.student_id, s.student_number, 
                           CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS full_name,
                           s.grade_level, s.section, s.status,
                           (SELECT COUNT(*) FROM infraction_logging i WHERE i.student_id = s.student_id) AS total_infractions,
                           (SELECT COUNT(*) FROM infraction_logging i INNER JOIN violation_category vc ON vc.category_id = i.category_id WHERE i.student_id = s.student_id AND vc.severity = 'Minor') AS minor_count,
                           (SELECT COUNT(*) FROM infraction_logging i INNER JOIN violation_category vc ON vc.category_id = i.category_id WHERE i.student_id = s.student_id AND vc.severity = 'Moderate') AS moderate_count,
                           (SELECT COUNT(*) FROM infraction_logging i INNER JOIN violation_category vc ON vc.category_id = i.category_id WHERE i.student_id = s.student_id AND vc.severity = 'Major') AS major_count,
                           (SELECT COALESCE(SUM(points), 0) FROM behavior_points bp WHERE bp.student_id = s.student_id) AS custom_points,
                           (SELECT COUNT(*) FROM behavior_interventions bi WHERE bi.student_id = s.student_id AND LOWER(bi.status) = 'ongoing') AS ongoing_interventions
                    FROM students s
                    WHERE LOWER(s.status) <> 'inactive'
                    ORDER BY s.last_name, s.first_name";

    $studentsResult = $conn->query($studentsSql);
    if ($studentsResult) {
        while ($row = $studentsResult->fetch_assoc()) {
            // Score formula: Base 100 - (Minor*2 + Mod*5 + Maj*15) + custom_points
            $base = 100;
            $deductions = ((int)$row['minor_count'] * 2) + ((int)$row['moderate_count'] * 5) + ((int)$row['major_count'] * 15);
            $rawScore = $base - $deductions + (int)$row['custom_points'];
            $score = max(0, min(100, $rawScore));

            if ($score >= 90) {
                $rating = 'Excellent';
                $badge = 'approved';
            } elseif ($score >= 80) {
                $rating = 'Good Standing';
                $badge = 'review';
            } elseif ($score >= 70) {
                $rating = 'Under Observation';
                $badge = 'pending';
            } else {
                $rating = 'Intervention Required';
                $badge = 'danger';
            }

            $row['calculated_score'] = $score;
            $row['rating_label'] = $rating;
            $row['badge_class'] = $badge;
            $studentsBehaviorList[] = $row;
            $studentsDropdown[] = [
                'student_id' => $row['student_id'],
                'student_number' => $row['student_number'],
                'full_name' => $row['full_name'],
                'grade_level' => $row['grade_level'],
                'section' => $row['section']
            ];
        }
    }

    // 6. Fetch ongoing interventions
    $intQuery = "SELECT bi.*, s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name,
                        s.grade_level, s.section
                 FROM behavior_interventions bi
                 INNER JOIN students s ON s.student_id = bi.student_id
                 ORDER BY bi.date_started DESC LIMIT 20";
    $intQueryRes = $conn->query($intQuery);
    if ($intQueryRes) {
        while ($ir = $intQueryRes->fetch_assoc()) {
            $interventionsList[] = $ir;
        }
    }

    $conn->close();
} else {
    $dbError = 'Database connection failed. Start MySQL and refresh the page.';
}

$todayLabel = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Behavior Tracking | E-PDAMS</title>
    <link rel="icon" type="image/png" href="../../assets/logo.png">
    <link rel="stylesheet" href="../css/style.css?v=animations-v1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .filter-select {
            padding: 10px 14px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #f7faf9;
            color: var(--ink);
            font: inherit;
            font-size: 13px;
        }
        .filter-select:focus {
            outline: none;
            border-color: var(--teal);
        }
        .score-pill {
            font-weight: 700;
            font-size: 14px;
            font-family: 'Space Grotesk', sans-serif;
            color: var(--ink);
        }
        .score-sub {
            display: block;
            font-size: 11px;
            color: var(--muted);
            margin-top: 2px;
        }
        .offense-tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-right: 4px;
        }
        .offense-tag.minor { background: #fef3c7; color: #92400e; }
        .offense-tag.moderate { background: #ffedd5; color: #9a3412; }
        .offense-tag.major { background: #fee2e2; color: #991b1b; }
    </style>
</head>

<body>
    <div class="dashboard-shell">
        <aside class="sidebar" id="sidebar" aria-label="Primary navigation">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <img src="../../assets/logo.png" alt="E-PDAMS Logo" class="brand-logo-img">
                    <div><strong>E-PDAMS</strong><span>Student discipline</span></div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <p class="nav-label">Main</p>
                <a class="nav-link" href="dashboard.php"><i class="fa-solid fa-chart-line nav-icon"></i><span>Dashboard</span></a>
                <a class="nav-link" href="records.php"><i class="fa-solid fa-file-lines nav-icon"></i><span>Records</span></a>
                <a class="nav-link" href="notification.php"><i class="fa-solid fa-bell nav-icon"></i><span>Notifications</span></a>

                <p class="nav-label">Discipline</p>
                <a class="nav-link" href="violationSetup.php"><i class="fa-solid fa-triangle-exclamation nav-icon"></i><span>Violation Records</span></a>
                <a class="nav-link" href="sanction.php"><i class="fa-solid fa-gavel nav-icon"></i><span>Sanctions</span></a>
                <a class="nav-link" href="IncidentReports.php"><i class="fa-solid fa-file-circle-exclamation nav-icon"></i><span>Incident Reports</span></a>
                <a class="nav-link" href="clearance.php"><i class="fa-solid fa-file-circle-check nav-icon"></i><span>Clearance</span></a>

                <p class="nav-label">Management</p>
                <a class="nav-link active" href="behaviorTracking.php" aria-current="page"><i class="fa-solid fa-user-check nav-icon"></i><span>Behavior Tracking</span></a>
                <a class="nav-link" href="disciplinarySchedule.php"><i class="fa-solid fa-calendar-days nav-icon"></i><span>Disciplinary Schedule</span></a>
                <a class="nav-link" href="reformationProgram.php"><i class="fa-solid fa-rotate nav-icon"></i><span>Reformation Program</span></a>
                <a class="nav-link" href="userManagement.php"><i class="fa-solid fa-user-shield nav-icon"></i><span>User Accounts</span></a>
            </nav>
            <div class="sidebar-footer">
                <div class="profile-chip">
                    <div class="profile-avatar">AD</div>
                    <div><strong>Administrator</strong><span>Prefect Office</span></div>
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
                <div class="breadcrumb"><span>Management</span><span aria-hidden="true">/</span><strong>Behavior Tracking</strong></div>
                <div class="topbar-date"><?= htmlspecialchars($todayLabel); ?></div>
            </header>

            <section class="page-heading">
                <div>
                    <p class="eyebrow">Behavioral Analytics &amp; Interventions</p>
                    <h1>Behavior Tracking</h1>
                    <p class="heading-copy">Monitor student conduct scores, track commendations, and manage guidance counseling interventions.</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="secondary-button" id="open-point-btn" type="button">
                        <i class="fa-solid fa-star"></i> Record Commendation / Point
                    </button>
                    <button class="primary-button" id="open-interv-btn" type="button">
                        <i class="fa-solid fa-hand-holding-heart"></i> Add Intervention
                    </button>
                </div>
            </section>

            <?php if ($dbError): ?>
                <div class="clearance-flash error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><strong>Database Connection Error</strong><span><?= htmlspecialchars($dbError); ?></span></div>
                </div>
            <?php endif; ?>

            <!-- Metrics -->
            <section class="metrics" aria-label="Behavior summary">
                <article class="metric-card accent-teal">
                    <span>Students Monitored</span>
                    <strong><?= number_format($totalStudentsTracked); ?></strong>
                    <small>Active student records</small>
                </article>
                <article class="metric-card accent-green">
                    <span>Positive Commendations</span>
                    <strong><?= number_format($positiveBehaviors); ?></strong>
                    <small>Merits &amp; recognized acts</small>
                </article>
                <article class="metric-card accent-coral">
                    <span>Behavioral Concerns</span>
                    <strong><?= number_format($behaviorConcerns); ?></strong>
                    <small>Pending infractions on record</small>
                </article>
                <article class="metric-card accent-yellow">
                    <span>Active Interventions</span>
                    <strong><?= number_format($activeInterventions); ?></strong>
                    <small>Counseling &amp; behavior plans</small>
                </article>
            </section>

            <!-- Student Behavioral Standing Table -->
            <section class="activity-panel" id="students-behavior-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Conduct Records</p>
                        <h2>Student Conduct Standing</h2>
                    </div>
                    <div class="records-toolbar">
                        <div class="records-search-form">
                            <label class="search-box" aria-label="Search students">
                                <span aria-hidden="true"><i class="fa-solid fa-magnifying-glass"></i></span>
                                <input type="search" id="behaviorSearchInput" placeholder="Search by name or ID...">
                            </label>
                            <select id="ratingFilterSelect" class="filter-select">
                                <option value="">All Standings</option>
                                <option value="Excellent">Excellent</option>
                                <option value="Good Standing">Good Standing</option>
                                <option value="Under Observation">Under Observation</option>
                                <option value="Intervention Required">Intervention Required</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="records-table-wrap">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Student Number</th>
                                <th>Grade &amp; Section</th>
                                <th>Conduct Score</th>
                                <th>Standing</th>
                                <th>Offenses Breakdown</th>
                                <th>Merits</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="behaviorTableBody">
                            <?php if (!empty($studentsBehaviorList)): ?>
                                <?php foreach ($studentsBehaviorList as $st): ?>
                                    <tr class="behavior-row" 
                                        data-name="<?= htmlspecialchars(strtolower($st['full_name'])); ?>"
                                        data-id="<?= htmlspecialchars(strtolower($st['student_number'])); ?>"
                                        data-rating="<?= htmlspecialchars($st['rating_label']); ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($st['full_name']); ?></strong>
                                            <?php if ((int)$st['ongoing_interventions'] > 0): ?>
                                                <small class="table-subtext" style="color:var(--coral);"><i class="fa-solid fa-heart-pulse"></i> Has Active Intervention</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= htmlspecialchars($st['student_number']); ?></code></td>
                                        <td><?= htmlspecialchars($st['grade_level'] . ' - ' . $st['section']); ?></td>
                                        <td>
                                            <span class="score-pill"><?= $st['calculated_score']; ?>/100</span>
                                            <small class="score-sub"><?= $st['calculated_score'] >= 80 ? 'Good standing' : 'Needs attention'; ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $st['badge_class']; ?>">
                                                <?= htmlspecialchars($st['rating_label']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ((int)$st['total_infractions'] === 0): ?>
                                                <span class="table-subtext" style="color:var(--green);"><i class="fa-solid fa-circle-check"></i> Clean Record</span>
                                            <?php else: ?>
                                                <?php if ((int)$st['minor_count'] > 0): ?><span class="offense-tag minor"><?= (int)$st['minor_count']; ?> Minor</span><?php endif; ?>
                                                <?php if ((int)$st['moderate_count'] > 0): ?><span class="offense-tag moderate"><?= (int)$st['moderate_count']; ?> Mod</span><?php endif; ?>
                                                <?php if ((int)$st['major_count'] > 0): ?><span class="offense-tag major"><?= (int)$st['major_count']; ?> Major</span><?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ((int)$st['custom_points'] > 0): ?>
                                                <strong style="color:var(--green);">+<?= (int)$st['custom_points']; ?> pts</strong>
                                            <?php elseif ((int)$st['custom_points'] < 0): ?>
                                                <strong style="color:var(--coral);"><?= (int)$st['custom_points']; ?> pts</strong>
                                            <?php else: ?>
                                                <span class="table-subtext">0 pts</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="record-actions">
                                            <button class="table-action quick-point-btn" 
                                                    data-id="<?= (int)$st['student_id']; ?>" 
                                                    data-name="<?= htmlspecialchars($st['full_name']); ?>"
                                                    type="button">
                                                <i class="fa-solid fa-star"></i> Point
                                            </button>
                                            <button class="table-action quick-interv-btn" 
                                                    data-id="<?= (int)$st['student_id']; ?>" 
                                                    data-name="<?= htmlspecialchars($st['full_name']); ?>"
                                                    type="button">
                                                <i class="fa-solid fa-hand-holding-heart"></i> Intervene
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state compact-empty-state">
                                            <div class="empty-icon"><i class="fa-solid fa-user-check"></i></div>
                                            <strong>No active student behavior files found</strong>
                                            <p>Add student records in the Records module to begin tracking behavior.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Active Guidance Interventions Tracker -->
            <section class="activity-panel" style="margin-top: 30px;">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Guidance &amp; Support</p>
                        <h2>Active Behavioral Interventions</h2>
                    </div>
                </div>

                <div class="records-table-wrap">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Intervention Plan</th>
                                <th>Person Responsible</th>
                                <th>Started</th>
                                <th>Follow-up</th>
                                <th>Status</th>
                                <th>Plan Description &amp; Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($interventionsList)): ?>
                                <?php foreach ($interventionsList as $inv): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($inv['student_name']); ?></strong>
                                            <small><?= htmlspecialchars($inv['student_number'] . ' - ' . $inv['grade_level'] . '-' . $inv['section']); ?></small>
                                        </td>
                                        <td><span class="severity-label"><?= htmlspecialchars($inv['intervention_type']); ?></span></td>
                                        <td><?= htmlspecialchars($inv['person_responsible']); ?></td>
                                        <td><?= htmlspecialchars(date('M j, Y', strtotime($inv['date_started']))); ?></td>
                                        <td><?= $inv['follow_up_date'] ? htmlspecialchars(date('M j, Y', strtotime($inv['follow_up_date']))) : '&mdash;'; ?></td>
                                        <td>
                                            <span class="status-badge <?= strtolower($inv['status']) === 'completed' ? 'approved' : 'pending'; ?>">
                                                <?= htmlspecialchars($inv['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span><?= htmlspecialchars($inv['description']); ?></span>
                                            <?php if ($inv['outcome']): ?>
                                                <small class="table-subtext" style="color:var(--teal);">Outcome: <?= htmlspecialchars($inv['outcome']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state compact-empty-state">
                                            <div class="empty-icon"><i class="fa-solid fa-hand-holding-heart"></i></div>
                                            <strong>No active interventions on record</strong>
                                            <p>Click "Add Intervention" to assign a guidance plan or behavior contract to a student.</p>
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

    <!-- Modal 1: Add Behavior Point / Commendation -->
    <div class="record-modal" id="point-modal" hidden>
        <form class="record-form" id="point-form">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Behavior Record</p>
                    <h2>Record Commendation or Point</h2>
                </div>
                <button class="modal-close" id="close-point-modal" type="button" aria-label="Close">&times;</button>
            </div>

            <div class="form-grid">
                <label class="full-field">Student
                    <select id="point-student-id" required>
                        <option value="">-- Choose student --</option>
                        <?php foreach ($studentsDropdown as $st): ?>
                            <option value="<?= (int) $st['student_id']; ?>">
                                <?= htmlspecialchars($st['full_name'] . ' (' . $st['student_number'] . ') - ' . $st['grade_level']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Category
                    <select id="point-category" required>
                        <option value="Conduct">General Conduct</option>
                        <option value="Respectful Conduct">Respectful Conduct</option>
                        <option value="Academic Integrity">Academic Integrity</option>
                        <option value="Leadership">Leadership Recognition</option>
                        <option value="Attendance">Perfect Attendance</option>
                        <option value="Community Service">Campus Service</option>
                    </select>
                </label>

                <label>Points to Award / Deduct (+ / -)
                    <input type="number" id="point-value" value="5" required>
                </label>

                <label class="full-field">Date Recorded
                    <input type="date" id="point-date" value="<?= date('Y-m-d'); ?>" required>
                </label>

                <label class="full-field">Description &amp; Observed Behavior
                    <textarea id="point-desc" rows="3" placeholder="Describe the recognized positive act or conduct..." required></textarea>
                </label>
            </div>

            <p class="form-error" id="point-error" role="alert"></p>
            <div class="modal-actions">
                <button class="secondary-button" id="cancel-point" type="button">Cancel</button>
                <button class="primary-button" type="submit">Save Behavior Point</button>
            </div>
        </form>
    </div>

    <!-- Modal 2: Add Intervention Plan -->
    <div class="record-modal" id="interv-modal" hidden>
        <form class="record-form" id="interv-form">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Guidance &amp; Support</p>
                    <h2>Assign Behavioral Intervention</h2>
                </div>
                <button class="modal-close" id="close-interv-modal" type="button" aria-label="Close">&times;</button>
            </div>

            <div class="form-grid">
                <label class="full-field">Student
                    <select id="interv-student-id" required>
                        <option value="">-- Choose student --</option>
                        <?php foreach ($studentsDropdown as $st): ?>
                            <option value="<?= (int) $st['student_id']; ?>">
                                <?= htmlspecialchars($st['full_name'] . ' (' . $st['student_number'] . ') - ' . $st['grade_level']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Intervention Type
                    <select id="interv-type" required>
                        <option value="Behavior Contract">Behavior Contract</option>
                        <option value="Check-in Session">Weekly Check-in Session</option>
                        <option value="Parent Coordination">Parent Coordination</option>
                        <option value="Counseling Referral">Counseling Referral</option>
                    </select>
                </label>

                <label>Person Responsible
                    <input id="interv-officer" value="Guidance Counselor" required>
                </label>

                <label>Date Started
                    <input type="date" id="interv-date-started" value="<?= date('Y-m-d'); ?>" required>
                </label>

                <label>Follow-up Date
                    <input type="date" id="interv-followup">
                </label>

                <label class="full-field">Status
                    <select id="interv-status">
                        <option value="Ongoing" selected>Ongoing</option>
                        <option value="Pending">Pending</option>
                        <option value="Completed">Completed</option>
                        <option value="Requires Follow-up">Requires Follow-up</option>
                    </select>
                </label>

                <label class="full-field">Intervention Objectives &amp; Plan
                    <textarea id="interv-desc" rows="3" placeholder="Outline corrective targets and monitoring strategy..." required></textarea>
                </label>

                <label class="full-field">Expected Outcome / Notes
                    <input id="interv-outcome" placeholder="e.g. Improved punctuality, peer dispute resolution">
                </label>
            </div>

            <p class="form-error" id="interv-error" role="alert"></p>
            <div class="modal-actions">
                <button class="secondary-button" id="cancel-interv" type="button">Cancel</button>
                <button class="primary-button" type="submit">Save Intervention</button>
            </div>
        </form>
    </div>

    <script>
        const pointModal = document.getElementById('point-modal');
        const pointForm = document.getElementById('point-form');
        const intervModal = document.getElementById('interv-modal');
        const intervForm = document.getElementById('interv-form');

        // Open point modal
        document.getElementById('open-point-btn')?.addEventListener('click', () => {
            pointForm.reset();
            document.getElementById('point-error').textContent = '';
            document.getElementById('point-date').value = new Date().toISOString().split('T')[0];
            pointModal.hidden = false;
        });

        document.getElementById('close-point-modal')?.addEventListener('click', () => pointModal.hidden = true);
        document.getElementById('cancel-point')?.addEventListener('click', () => pointModal.hidden = true);

        // Open intervention modal
        document.getElementById('open-interv-btn')?.addEventListener('click', () => {
            intervForm.reset();
            document.getElementById('interv-error').textContent = '';
            document.getElementById('interv-date-started').value = new Date().toISOString().split('T')[0];
            intervModal.hidden = false;
        });

        document.getElementById('close-interv-modal')?.addEventListener('click', () => intervModal.hidden = true);
        document.getElementById('cancel-interv')?.addEventListener('click', () => intervModal.hidden = true);

        // Quick row buttons
        document.querySelectorAll('.quick-point-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                pointForm.reset();
                document.getElementById('point-error').textContent = '';
                document.getElementById('point-student-id').value = btn.dataset.id;
                document.getElementById('point-date').value = new Date().toISOString().split('T')[0];
                pointModal.hidden = false;
            });
        });

        document.querySelectorAll('.quick-interv-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                intervForm.reset();
                document.getElementById('interv-error').textContent = '';
                document.getElementById('interv-student-id').value = btn.dataset.id;
                document.getElementById('interv-date-started').value = new Date().toISOString().split('T')[0];
                intervModal.hidden = false;
            });
        });

        // Submit Point
        pointForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const err = document.getElementById('point-error');
            err.textContent = '';

            const payload = {
                type: 'point',
                student_id: document.getElementById('point-student-id').value,
                category: document.getElementById('point-category').value,
                points: parseInt(document.getElementById('point-value').value, 10),
                date_recorded: document.getElementById('point-date').value,
                description: document.getElementById('point-desc').value
            };

            const res = await fetch('../../server/api.php?resource=behavior', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') {
                err.textContent = data.message || 'Unable to record behavior point.';
                return;
            }

            window.location.reload();
        });

        // Submit Intervention
        intervForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const err = document.getElementById('interv-error');
            err.textContent = '';

            const payload = {
                type: 'intervention',
                student_id: document.getElementById('interv-student-id').value,
                intervention_type: document.getElementById('interv-type').value,
                person_responsible: document.getElementById('interv-officer').value,
                date_started: document.getElementById('interv-date-started').value,
                follow_up_date: document.getElementById('interv-followup').value,
                status: document.getElementById('interv-status').value,
                description: document.getElementById('interv-desc').value,
                outcome: document.getElementById('interv-outcome').value
            };

            const res = await fetch('../../server/api.php?resource=behavior', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') {
                err.textContent = data.message || 'Unable to save intervention.';
                return;
            }

            window.location.reload();
        });

        // Client-side search and status filter
        const searchInput = document.getElementById('behaviorSearchInput');
        const ratingFilter = document.getElementById('ratingFilterSelect');
        const rows = document.querySelectorAll('.behavior-row');

        function filterRows() {
            const query = (searchInput.value || '').trim().toLowerCase();
            const selectedRating = (ratingFilter.value || '').trim();

            rows.forEach(row => {
                const name = row.dataset.name || '';
                const id = row.dataset.id || '';
                const rating = row.dataset.rating || '';

                const matchesQuery = !query || name.includes(query) || id.includes(query);
                const matchesRating = !selectedRating || rating.toLowerCase() === selectedRating.toLowerCase();

                row.style.display = matchesQuery && matchesRating ? '' : 'none';
            });
        }

        searchInput?.addEventListener('input', filterRows);
        ratingFilter?.addEventListener('change', filterRows);
    </script>
    <script src="../../server/script.js?v=animations-v1"></script>
</body>

</html>


