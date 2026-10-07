<?php
// E-PDAMS Sanctions Management
// Allows administrators to review infractions and assign multiple sanctions per violation.

require_once '../../server/db.php';
session_start();

$conn = getDbConnection();
$totalSanctions = 0;
$pendingSanctions = 0;
$inProgressSanctions = 0;
$completedSanctions = 0;
$violationsList = [];
$sanctionsList = [];
$dbError = null;

if ($conn) {
    // get metrics for sanctions
    $statRes = $conn->query("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed
        FROM sanctions");
    if ($statRes && $stats = $statRes->fetch_assoc()) {
        $totalSanctions = (int) ($stats['total'] ?? 0);
        $pendingSanctions = (int) ($stats['pending'] ?? 0);
        $inProgressSanctions = (int) ($stats['in_progress'] ?? 0);
        $completedSanctions = (int) ($stats['completed'] ?? 0);
    }

    // fetch all sanctions with student and violation details
    $sanctionQuery = "SELECT san.*, s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name,
                             vc.category_name, vc.severity, i.offense_committed
                      FROM sanctions san
                      INNER JOIN students s ON s.student_id = san.student_id
                      INNER JOIN infraction_logging i ON i.infraction_id = san.infraction_id
                      LEFT JOIN violation_category vc ON vc.category_id = i.category_id
                      ORDER BY san.created_at DESC";
    $sanRes = $conn->query($sanctionQuery);
    if ($sanRes) {
        while ($r = $sanRes->fetch_assoc()) {
            $sanctionsList[] = $r;
        }
    }

    // fetch violations for assigning new sanctions
    $violQuery = "SELECT i.infraction_id, i.student_id, s.student_number, 
                         CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name,
                         vc.category_name, vc.severity, i.offense_committed, i.status AS violation_status,
                         (SELECT COUNT(*) FROM sanctions san WHERE san.infraction_id = i.infraction_id) AS total_assigned_sanctions
                  FROM infraction_logging i
                  INNER JOIN students s ON s.student_id = i.student_id
                  INNER JOIN violation_category vc ON vc.category_id = i.category_id
                  ORDER BY i.date_time DESC";
    $vRes = $conn->query($violQuery);
    if ($vRes) {
        while ($vr = $vRes->fetch_assoc()) {
            $violationsList[] = $vr;
        }
    }

    $conn->close();
} else {
    $dbError = 'Database connection failed. Please check MySQL service.';
}

$todayLabel = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sanctions Management | E-PDAMS</title>
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
                <a class="nav-link active" href="sanction.php" aria-current="page"><i class="fa-solid fa-gavel nav-icon"></i><span>Sanctions</span></a>
                <a class="nav-link" href="IncidentReports.php"><i class="fa-solid fa-file-circle-exclamation nav-icon"></i><span>Incident Reports</span></a>
                <a class="nav-link" href="clearance.php"><i class="fa-solid fa-file-circle-check nav-icon"></i><span>Clearance</span></a>

                <p class="nav-label">Management</p>
                <a class="nav-link" href="behaviorTracking.php"><i class="fa-solid fa-user-check nav-icon"></i><span>Behavior Tracking</span></a>
                <a class="nav-link" href="disciplinarySchedule.php"><i class="fa-solid fa-calendar-days nav-icon"></i><span>Disciplinary Schedule</span></a>
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
                <div class="breadcrumb"><span>Discipline</span><span aria-hidden="true">/</span><strong>Sanctions</strong></div>
                <div class="topbar-date"><?= htmlspecialchars($todayLabel); ?></div>
            </header>

            <section class="page-heading">
                <div>
                    <p class="eyebrow">Disciplinary Action</p>
                    <h1>Sanction Management</h1>
                    <p class="heading-copy">Assign and track disciplinary actions and corrective measures. Multiple sanctions can be assigned per violation.</p>
                </div>
                <button class="primary-button" id="open-sanction-btn" type="button">
                    <i class="fa-solid fa-plus"></i> Assign Sanction
                </button>
            </section>

            <?php if ($dbError): ?>
                <div class="clearance-flash error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><strong>Database Connection Error</strong><span><?= htmlspecialchars($dbError); ?></span></div>
                </div>
            <?php endif; ?>

            <!-- Metrics -->
            <section class="metrics" aria-label="Sanctions overview">
                <article class="metric-card accent-teal">
                    <span>Total Sanctions</span>
                    <strong><?= number_format($totalSanctions); ?></strong>
                    <small>All assigned corrective actions</small>
                </article>
                <article class="metric-card accent-yellow">
                    <span>Pending</span>
                    <strong><?= number_format($pendingSanctions); ?></strong>
                    <small>Awaiting student completion</small>
                </article>
                <article class="metric-card accent-coral">
                    <span>In Progress</span>
                    <strong><?= number_format($inProgressSanctions); ?></strong>
                    <small>Currently being served</small>
                </article>
                <article class="metric-card accent-green">
                    <span>Completed</span>
                    <strong><?= number_format($completedSanctions); ?></strong>
                    <small>Successfully resolved</small>
                </article>
            </section>

            <!-- Active Sanctions Table -->
            <section class="activity-panel" id="sanctions-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Corrective Measures</p>
                        <h2>Assigned Sanctions List</h2>
                    </div>
                </div>

                <div class="records-table-wrap">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Sanction</th>
                                <th>Student</th>
                                <th>Type</th>
                                <th>Related Offense</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($sanctionsList)): ?>
                                <?php foreach ($sanctionsList as $sanction): ?>
                                    <?php
                                    $st = strtolower($sanction['status']);
                                    $badge = 'pending';
                                    if ($st === 'completed') $badge = 'approved';
                                    elseif ($st === 'in progress') $badge = 'review';
                                    elseif ($st === 'revoked') $badge = 'archived';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($sanction['sanction_name']); ?></strong>
                                            <?php if (!empty($sanction['description'])): ?>
                                                <small class="table-subtext"><?= htmlspecialchars($sanction['description']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($sanction['student_name']); ?></strong>
                                            <small><?= htmlspecialchars($sanction['student_number']); ?></small>
                                        </td>
                                        <td><span class="severity-label"><?= htmlspecialchars($sanction['sanction_type']); ?></span></td>
                                        <td>
                                            <span><?= htmlspecialchars($sanction['category_name'] ?: 'Violation'); ?></span>
                                            <small class="table-subtext"><?= htmlspecialchars(substr($sanction['offense_committed'], 0, 45) . '...'); ?></small>
                                        </td>
                                        <td>
                                            <?= $sanction['start_date'] ? htmlspecialchars(date('M j, Y', strtotime($sanction['start_date']))) : 'Immediate'; ?>
                                            <?= $sanction['end_date'] ? ' to ' . htmlspecialchars(date('M j, Y', strtotime($sanction['end_date']))) : ''; ?>
                                        </td>
                                        <td>
                                            <select class="sanction-status-select" data-id="<?= (int) $sanction['sanction_id']; ?>">
                                                <option value="Pending" <?= $sanction['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="In Progress" <?= $sanction['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="Completed" <?= $sanction['status'] === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="Revoked" <?= $sanction['status'] === 'Revoked' ? 'selected' : ''; ?>>Revoked</option>
                                            </select>
                                        </td>
                                        <td class="record-actions">
                                            <button class="table-action delete-record delete-sanction-btn" data-id="<?= (int) $sanction['sanction_id']; ?>" type="button">Remove</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state compact-empty-state">
                                            <div class="empty-icon"><i class="fa-solid fa-gavel"></i></div>
                                            <strong>No sanctions assigned yet</strong>
                                            <p>Select a violation below to assign one or multiple sanctions.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Violations Queue with Quick Assign -->
            <section class="activity-panel" style="margin-top: 30px;">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Violation Queue</p>
                        <h2>Logged Infractions</h2>
                    </div>
                </div>

                <div class="records-table-wrap">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Violation Category</th>
                                <th>Severity</th>
                                <th>Offense Detail</th>
                                <th>Sanctions Count</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($violationsList)): ?>
                                <?php foreach ($violationsList as $viol): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($viol['student_name']); ?></strong>
                                            <small><?= htmlspecialchars($viol['student_number']); ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($viol['category_name']); ?></td>
                                        <td><span class="severity-label"><?= htmlspecialchars($viol['severity']); ?></span></td>
                                        <td><?= htmlspecialchars($viol['offense_committed']); ?></td>
                                        <td>
                                            <span class="status-badge <?= (int) $viol['total_assigned_sanctions'] > 0 ? 'approved' : 'pending'; ?>">
                                                <?= (int) $viol['total_assigned_sanctions']; ?> assigned
                                            </span>
                                        </td>
                                        <td>
                                            <button class="primary-button small-button quick-assign-btn" 
                                                    data-infraction-id="<?= (int) $viol['infraction_id']; ?>"
                                                    data-student-id="<?= (int) $viol['student_id']; ?>"
                                                    data-student-name="<?= htmlspecialchars($viol['student_name']); ?>"
                                                    data-offense="<?= htmlspecialchars($viol['category_name']); ?>"
                                                    type="button">
                                                + Add Sanction
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal to Assign Sanction -->
    <div class="record-modal" id="sanction-modal" hidden>
        <form class="record-form" id="sanction-form">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Disciplinary Action</p>
                    <h2>Assign Sanction</h2>
                    <p class="modal-student" id="selected-student-text"></p>
                </div>
                <button class="modal-close" id="close-sanction-modal" type="button" aria-label="Close">&times;</button>
            </div>

            <input type="hidden" id="sanction-infraction-id" name="infraction_id">
            <input type="hidden" id="sanction-student-id" name="student_id">

            <div class="form-grid">
                <label class="full-field">Select Infraction
                    <select id="modal-infraction-select" required>
                        <option value="">-- Choose student infraction --</option>
                        <?php foreach ($violationsList as $v): ?>
                            <option value="<?= (int) $v['infraction_id']; ?>" 
                                    data-student-id="<?= (int) $v['student_id']; ?>"
                                    data-name="<?= htmlspecialchars($v['student_name']); ?>">
                                <?= htmlspecialchars($v['student_name'] . ' (' . $v['student_number'] . ') - ' . $v['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="full-field">Sanction Name / Title
                    <input id="sanction-name" name="sanction_name" placeholder="e.g. 5 Hours Campus Service" required>
                </label>

                <label>Sanction Type
                    <select id="sanction-type" name="sanction_type" required>
                        <option value="Community Service">Community Service</option>
                        <option value="Written Reprimand">Written Reprimand</option>
                        <option value="Verbal Warning">Verbal Warning</option>
                        <option value="Suspension">Suspension</option>
                        <option value="Parent Conference">Parent Conference</option>
                        <option value="Counseling Referral">Counseling Referral</option>
                        <option value="Reformation Program">Reformation Program</option>
                    </select>
                </label>

                <label>Initial Status
                    <select id="sanction-status" name="status">
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                    </select>
                </label>

                <label>Start Date
                    <input type="date" id="sanction-start-date" name="start_date" value="<?= date('Y-m-d'); ?>">
                </label>

                <label>Target End Date
                    <input type="date" id="sanction-end-date" name="end_date">
                </label>

                <label class="full-field">Instructions &amp; Description
                    <textarea id="sanction-desc" name="description" rows="3" placeholder="Specify tasks, supervisor, or specific obligations for the student..."></textarea>
                </label>
            </div>

            <p class="form-error" id="sanction-error" role="alert"></p>
            <div class="modal-actions">
                <button class="secondary-button" id="cancel-sanction" type="button">Cancel</button>
                <button class="primary-button" type="submit">Assign Sanction</button>
            </div>
        </form>
    </div>

    <script>
        const modal = document.getElementById('sanction-modal');
        const form = document.getElementById('sanction-form');
        const err = document.getElementById('sanction-error');
        const infractionSelect = document.getElementById('modal-infraction-select');
        const studentText = document.getElementById('selected-student-text');

        function openModal(infractionId = '', studentId = '', studentName = '') {
            form.reset();
            err.textContent = '';
            document.getElementById('sanction-start-date').value = new Date().toISOString().split('T')[0];
            
            if (infractionId) {
                infractionSelect.value = infractionId;
                document.getElementById('sanction-infraction-id').value = infractionId;
                document.getElementById('sanction-student-id').value = studentId;
                studentText.textContent = 'Student: ' + studentName;
            } else {
                studentText.textContent = '';
            }
            modal.hidden = false;
        }

        function closeModal() {
            modal.hidden = true;
        }

        document.getElementById('open-sanction-btn')?.addEventListener('click', () => openModal());
        document.getElementById('close-sanction-modal')?.addEventListener('click', closeModal);
        document.getElementById('cancel-sanction')?.addEventListener('click', closeModal);

        document.querySelectorAll('.quick-assign-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                openModal(btn.dataset.infractionId, btn.dataset.studentId, btn.dataset.studentName);
            });
        });

        infractionSelect?.addEventListener('change', () => {
            const opt = infractionSelect.options[infractionSelect.selectedIndex];
            document.getElementById('sanction-infraction-id').value = opt.value;
            document.getElementById('sanction-student-id').value = opt.dataset.studentId || '';
            studentText.textContent = opt.dataset.name ? 'Student: ' + opt.dataset.name : '';
        });

        // Submit new sanction
        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            err.textContent = '';

            const payload = {
                infraction_id: document.getElementById('sanction-infraction-id').value,
                student_id: document.getElementById('sanction-student-id').value,
                sanction_name: document.getElementById('sanction-name').value,
                sanction_type: document.getElementById('sanction-type').value,
                status: document.getElementById('sanction-status').value,
                start_date: document.getElementById('sanction-start-date').value,
                end_date: document.getElementById('sanction-end-date').value,
                description: document.getElementById('sanction-desc').value
            };

            if (!payload.infraction_id) {
                err.textContent = 'Please choose an infraction.';
                return;
            }

            const res = await fetch('../../server/api.php?resource=sanctions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') {
                err.textContent = data.message || 'Unable to save sanction.';
                return;
            }

            window.location.reload();
        });

        // Update status directly from table dropdown
        document.querySelectorAll('.sanction-status-select').forEach(sel => {
            sel.addEventListener('change', async () => {
                sel.disabled = true;
                const res = await fetch(`../../server/api.php?resource=sanctions&id=${sel.dataset.id}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ status: sel.value })
                });
                const data = await res.json();
                if (!res.ok || data.status !== 'success') {
                    alert(data.message || 'Failed to update sanction status.');
                }
                window.location.reload();
            });
        });

        // Delete sanction
        document.querySelectorAll('.delete-sanction-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Remove this sanction?')) return;
                const res = await fetch(`../../server/api.php?resource=sanctions&id=${btn.dataset.id}`, {
                    method: 'DELETE'
                });
                const data = await res.json();
                if (!res.ok || data.status !== 'success') {
                    alert(data.message || 'Failed to remove sanction.');
                    return;
                }
                window.location.reload();
            });
        });
    </script>
    <script src="../../server/script.js?v=animations-v1"></script>
</body>

</html>


