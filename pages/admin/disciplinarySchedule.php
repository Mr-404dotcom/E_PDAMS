<?php
// E-PDAMS Disciplinary Schedule Module
// Manages disciplinary hearings, conferences, and prefect appointments.

require_once '../../server/db.php';
session_start();

$conn = getDbConnection();
$totalSchedules = 0;
$upcomingCount = 0;
$completedCount = 0;
$schedulesList = [];
$studentsList = [];
$violationsList = [];
$dbError = null;

if ($conn) {
    // metrics
    $mRes = $conn->query("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN hearing_date >= CURDATE() AND status = 'Scheduled' THEN 1 ELSE 0 END) AS upcoming,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed
        FROM disciplinary_schedule");
    if ($mRes && $m = $mRes->fetch_assoc()) {
        $totalSchedules = (int) ($m['total'] ?? 0);
        $upcomingCount = (int) ($m['upcoming'] ?? 0);
        $completedCount = (int) ($m['completed'] ?? 0);
    }

    // fetch all schedules
    $sQuery = "SELECT ds.*, s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name,
                      s.grade_level, s.section, vc.category_name, i.offense_committed
               FROM disciplinary_schedule ds
               INNER JOIN students s ON s.student_id = ds.student_id
               LEFT JOIN infraction_logging i ON i.infraction_id = ds.infraction_id
               LEFT JOIN violation_category vc ON vc.category_id = i.category_id
               ORDER BY ds.hearing_date ASC, ds.hearing_time ASC";
    $sRes = $conn->query($sQuery);
    if ($sRes) {
        while ($r = $sRes->fetch_assoc()) {
            $schedulesList[] = $r;
        }
    }

    // fetch students
    $stRes = $conn->query("SELECT student_id, student_number, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS student_name, grade_level, section FROM students WHERE LOWER(status) = 'active' ORDER BY last_name, first_name");
    if ($stRes) {
        while ($sr = $stRes->fetch_assoc()) {
            $studentsList[] = $sr;
        }
    }

    // fetch infractions
    $iRes = $conn->query("SELECT i.infraction_id, i.student_id, vc.category_name, i.offense_committed FROM infraction_logging i INNER JOIN violation_category vc ON vc.category_id = i.category_id ORDER BY i.date_time DESC");
    if ($iRes) {
        while ($ir = $iRes->fetch_assoc()) {
            $violationsList[] = $ir;
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
    <title>Disciplinary Schedule | E-PDAMS</title>
    <link rel="icon" type="image/png" href="../../assets/logo.png">
    <link rel="stylesheet" href="../css/style.css?v=animations-v1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
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
                <a class="nav-link" href="behaviorTracking.php"><i class="fa-solid fa-user-check nav-icon"></i><span>Behavior Tracking</span></a>
                <a class="nav-link active" href="disciplinarySchedule.php" aria-current="page"><i class="fa-solid fa-calendar-days nav-icon"></i><span>Disciplinary Schedule</span></a>
                <a class="nav-link" href="reformationProgram.php"><i class="fa-solid fa-rotate nav-icon"></i><span>Reformation Program</span></a>
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
                <div class="breadcrumb"><span>Management</span><span aria-hidden="true">/</span><strong>Disciplinary Schedule</strong></div>
                <div class="topbar-date"><?= htmlspecialchars($todayLabel); ?></div>
            </header>

            <section class="page-heading">
                <div>
                    <p class="eyebrow">Hearings &amp; Conferences</p>
                    <h1>Disciplinary Schedule</h1>
                    <p class="heading-copy">Schedule student hearings, parent-prefect conferences, and track upcoming disciplinary proceedings.</p>
                </div>
                <button class="primary-button" id="open-sched-btn" type="button">
                    <i class="fa-solid fa-plus"></i> Schedule Hearing
                </button>
            </section>

            <?php if ($dbError): ?>
                <div class="clearance-flash error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><strong>Database Connection Error</strong><span><?= htmlspecialchars($dbError); ?></span></div>
                </div>
            <?php endif; ?>

            <!-- Metrics -->
            <section class="metrics" aria-label="Schedule overview">
                <article class="metric-card accent-teal">
                    <span>Total Hearings</span>
                    <strong><?= number_format($totalSchedules); ?></strong>
                    <small>All scheduled sessions</small>
                </article>
                <article class="metric-card accent-yellow">
                    <span>Upcoming Sessions</span>
                    <strong><?= number_format($upcomingCount); ?></strong>
                    <small>Pending on the calendar</small>
                </article>
                <article class="metric-card accent-green">
                    <span>Completed Sessions</span>
                    <strong><?= number_format($completedCount); ?></strong>
                    <small>Conducted and concluded</small>
                </article>
            </section>

            <!-- Schedules Table -->
            <section class="activity-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Calendar Queue</p>
                        <h2>Scheduled Hearings &amp; Conferences</h2>
                    </div>
                </div>

                <div class="records-table-wrap">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Session Title</th>
                                <th>Student</th>
                                <th>Hearing Date &amp; Time</th>
                                <th>Location</th>
                                <th>Assigned Officer</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($schedulesList)): ?>
                                <?php foreach ($schedulesList as $sch): ?>
                                    <?php
                                    $st = strtolower($sch['status']);
                                    $badge = 'pending';
                                    if ($st === 'completed') $badge = 'approved';
                                    elseif ($st === 'cancelled') $badge = 'archived';
                                    elseif ($st === 'in progress') $badge = 'review';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($sch['title']); ?></strong>
                                            <?php if ($sch['category_name']): ?>
                                                <small class="table-subtext">Re: <?= htmlspecialchars($sch['category_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($sch['student_name']); ?></strong>
                                            <small><?= htmlspecialchars($sch['student_number'] . ' - ' . $sch['grade_level']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars(date('M j, Y', strtotime($sch['hearing_date']))); ?></strong>
                                            <small><i class="fa-regular fa-clock"></i> <?= htmlspecialchars(date('g:i A', strtotime($sch['hearing_time']))); ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($sch['location']); ?></td>
                                        <td><?= htmlspecialchars($sch['assigned_officer']); ?></td>
                                        <td>
                                            <span class="status-badge <?= $badge; ?>"><?= htmlspecialchars(ucfirst($sch['status'])); ?></span>
                                        </td>
                                        <td class="record-actions">
                                            <select class="schedule-status-select" data-id="<?= (int) $sch['schedule_id']; ?>">
                                                <option value="Scheduled" <?= $sch['status'] === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                                <option value="In Progress" <?= $sch['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="Completed" <?= $sch['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="Cancelled" <?= $sch['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            </select>
                                            <button class="table-action delete-record delete-sched-btn" data-id="<?= (int) $sch['schedule_id']; ?>" type="button">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state compact-empty-state">
                                            <div class="empty-icon"><i class="fa-regular fa-calendar-days"></i></div>
                                            <strong>No scheduled hearings found</strong>
                                            <p>Click "Schedule Hearing" above to arrange a disciplinary session.</p>
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

    <!-- Modal to Schedule Hearing -->
    <div class="record-modal" id="sched-modal" hidden>
        <form class="record-form" id="sched-form">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Disciplinary Hearing</p>
                    <h2>New Hearing Schedule</h2>
                </div>
                <button class="modal-close" id="close-sched-modal" type="button" aria-label="Close">&times;</button>
            </div>

            <div class="form-grid">
                <label class="full-field">Student
                    <select id="sched-student-id" required>
                        <option value="">-- Choose student --</option>
                        <?php foreach ($studentsList as $st): ?>
                            <option value="<?= (int) $st['student_id']; ?>">
                                <?= htmlspecialchars($st['student_name'] . ' (' . $st['student_number'] . ') - ' . $st['grade_level']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="full-field">Session Title
                    <input id="sched-title" placeholder="e.g. Formal Prefect Inquiry Hearing" required>
                </label>

                <label>Date
                    <input type="date" id="sched-date" value="<?= date('Y-m-d'); ?>" required>
                </label>

                <label>Time
                    <input type="time" id="sched-time" value="09:00" required>
                </label>

                <label>Location / Room
                    <input id="sched-loc" value="Prefect Office" required>
                </label>

                <label>Assigned Hearing Officer
                    <input id="sched-officer" value="Prefect of Discipline" required>
                </label>

                <label class="full-field">Agenda / Remarks
                    <textarea id="sched-notes" rows="3" placeholder="Case briefing or required attendees (e.g. parents, adviser)..."></textarea>
                </label>
            </div>

            <p class="form-error" id="sched-error" role="alert"></p>
            <div class="modal-actions">
                <button class="secondary-button" id="cancel-sched" type="button">Cancel</button>
                <button class="primary-button" type="submit">Schedule Hearing</button>
            </div>
        </form>
    </div>

    <script>
        const schedModal = document.getElementById('sched-modal');
        const schedForm = document.getElementById('sched-form');
        const schedError = document.getElementById('sched-error');

        document.getElementById('open-sched-btn')?.addEventListener('click', () => {
            schedForm.reset();
            schedError.textContent = '';
            document.getElementById('sched-date').value = new Date().toISOString().split('T')[0];
            schedModal.hidden = false;
        });

        document.getElementById('close-sched-modal')?.addEventListener('click', () => schedModal.hidden = true);
        document.getElementById('cancel-sched')?.addEventListener('click', () => schedModal.hidden = true);

        schedForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            schedError.textContent = '';

            const payload = {
                student_id: document.getElementById('sched-student-id').value,
                title: document.getElementById('sched-title').value,
                hearing_date: document.getElementById('sched-date').value,
                hearing_time: document.getElementById('sched-time').value,
                location: document.getElementById('sched-loc').value,
                assigned_officer: document.getElementById('sched-officer').value,
                notes: document.getElementById('sched-notes').value,
                status: 'Scheduled'
            };

            const res = await fetch('../../server/api.php?resource=schedules', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') {
                schedError.textContent = data.message || 'Failed to create schedule.';
                return;
            }

            window.location.reload();
        });

        // Quick status update
        document.querySelectorAll('.schedule-status-select').forEach(sel => {
            sel.addEventListener('change', async () => {
                sel.disabled = true;
                const res = await fetch(`../../server/api.php?resource=schedules&id=${sel.dataset.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: sel.value })
                });
                const data = await res.json();
                if (!res.ok || data.status !== 'success') {
                    alert(data.message || 'Failed to update schedule status.');
                }
                window.location.reload();
            });
        });

        // Delete
        document.querySelectorAll('.delete-sched-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Cancel and delete this hearing schedule?')) return;
                const res = await fetch(`../../server/api.php?resource=schedules&id=${btn.dataset.id}`, {
                    method: 'DELETE'
                });
                const data = await res.json();
                if (!res.ok || data.status !== 'success') {
                    alert(data.message || 'Failed to delete schedule.');
                    return;
                }
                window.location.reload();
            });
        });
    </script>
    <script src="../../server/script.js?v=animations-v1"></script>
</body>

</html>


