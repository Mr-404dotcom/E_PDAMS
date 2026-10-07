<?php
// E-PDAMS Reformation Program Module
// Tracks student rehabilitation programs, community service hours, and restorative justice progress.

require_once '../../server/db.php';
session_start();

$conn = getDbConnection();
$totalPrograms = 0;
$totalEnrolled = 0;
$inProgressCount = 0;
$completedCount = 0;
$programsList = [];
$enrollmentsList = [];
$studentsList = [];
$dbError = null;

if ($conn) {
    // metrics
    $progRes = $conn->query("SELECT COUNT(*) AS total FROM reformation_programs WHERE LOWER(status) = 'active'");
    if ($progRes && $pr = $progRes->fetch_assoc()) {
        $totalPrograms = (int) ($pr['total'] ?? 0);
    }

    $enrStatRes = $conn->query("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed
        FROM student_reformations");
    if ($enrStatRes && $es = $enrStatRes->fetch_assoc()) {
        $totalEnrolled = (int) ($es['total'] ?? 0);
        $inProgressCount = (int) ($es['in_progress'] ?? 0);
        $completedCount = (int) ($es['completed'] ?? 0);
    }

    // programs list
    $pListRes = $conn->query("SELECT * FROM reformation_programs ORDER BY program_name ASC");
    if ($pListRes) {
        while ($p = $pListRes->fetch_assoc()) {
            $programsList[] = $p;
        }
    }

    // student enrollments
    $eQuery = "SELECT sr.*, rp.program_name, rp.duration_hours, s.student_number,
                      CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name,
                      s.grade_level, s.section
               FROM student_reformations sr
               INNER JOIN reformation_programs rp ON rp.program_id = sr.program_id
               INNER JOIN students s ON s.student_id = sr.student_id
               ORDER BY sr.created_at DESC";
    $eRes = $conn->query($eQuery);
    if ($eRes) {
        while ($er = $eRes->fetch_assoc()) {
            $enrollmentsList[] = $er;
        }
    }

    // active students
    $stRes = $conn->query("SELECT student_id, student_number, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS student_name, grade_level, section FROM students WHERE LOWER(status) = 'active' ORDER BY last_name, first_name");
    if ($stRes) {
        while ($st = $stRes->fetch_assoc()) {
            $studentsList[] = $st;
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
    <title>Reformation Program | E-PDAMS</title>
    <link rel="icon" type="image/png" href="../../assets/logo.png">
    <link rel="stylesheet" href="../css/style.css?v=animations-v1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 99px;
            overflow: hidden;
            margin-top: 4px;
        }
        .progress-bar-fill {
            height: 100%;
            background: var(--teal);
            border-radius: 99px;
            transition: width 0.3s ease;
        }
        .programs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 18px;
        }
        .program-card {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 20px;
        }
        .program-card h3 {
            margin: 0 0 8px;
            font-size: 16px;
            color: var(--teal-dark);
        }
        .program-card p {
            margin: 0 0 12px;
            font-size: 13px;
            color: var(--muted);
            line-height: 1.5;
        }
        .program-card-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--ink);
            font-weight: 600;
            border-top: 1px solid var(--line);
            padding-top: 12px;
        }
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
                <a class="nav-link" href="behaviorTracking.php"><i class="fa-solid fa-user-check nav-icon"></i><span>Behavior Tracking</span></a>
                <a class="nav-link" href="disciplinarySchedule.php"><i class="fa-solid fa-calendar-days nav-icon"></i><span>Disciplinary Schedule</span></a>
                <a class="nav-link active" href="reformationProgram.php" aria-current="page"><i class="fa-solid fa-rotate nav-icon"></i><span>Reformation Program</span></a>
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
                <div class="breadcrumb"><span>Management</span><span aria-hidden="true">/</span><strong>Reformation Program</strong></div>
                <div class="topbar-date"><?= htmlspecialchars($todayLabel); ?></div>
            </header>

            <section class="page-heading">
                <div>
                    <p class="eyebrow">Student Rehabilitation &amp; Values</p>
                    <h1>Reformation Programs</h1>
                    <p class="heading-copy">Manage positive behavioral intervention programs, student service assignments, and monitor completion progress.</p>
                </div>
                <button class="primary-button" id="enroll-btn" type="button">
                    <i class="fa-solid fa-user-plus"></i> Enroll Student
                </button>
            </section>

            <?php if ($dbError): ?>
                <div class="clearance-flash error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><strong>Database Connection Error</strong><span><?= htmlspecialchars($dbError); ?></span></div>
                </div>
            <?php endif; ?>

            <!-- Metrics -->
            <section class="metrics" aria-label="Reformation summary">
                <article class="metric-card accent-teal">
                    <span>Active Programs</span>
                    <strong><?= number_format($totalPrograms); ?></strong>
                    <small>Rehabilitation tracks offered</small>
                </article>
                <article class="metric-card accent-coral">
                    <span>Students In Progress</span>
                    <strong><?= number_format($inProgressCount); ?></strong>
                    <small>Currently attending sessions</small>
                </article>
                <article class="metric-card accent-green">
                    <span>Completed Reformations</span>
                    <strong><?= number_format($completedCount); ?></strong>
                    <small>Successfully graduated</small>
                </article>
                <article class="metric-card accent-yellow">
                    <span>Total Enrollments</span>
                    <strong><?= number_format($totalEnrolled); ?></strong>
                    <small>Cumulative student count</small>
                </article>
            </section>

            <!-- Student Enrollments Table -->
            <section class="activity-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Student Progress</p>
                        <h2>Enrolled Students &amp; Milestones</h2>
                    </div>
                </div>

                <div class="records-table-wrap">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Assigned Program</th>
                                <th>Start Date</th>
                                <th>Progress</th>
                                <th>Hours Done</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($enrollmentsList)): ?>
                                <?php foreach ($enrollmentsList as $enr): ?>
                                    <?php
                                    $st = strtolower($enr['status']);
                                    $badge = 'pending';
                                    if ($st === 'completed') $badge = 'approved';
                                    elseif ($st === 'failed') $badge = 'archived';
                                    elseif ($st === 'in progress') $badge = 'review';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($enr['student_name']); ?></strong>
                                            <small><?= htmlspecialchars($enr['student_number'] . ' - ' . $enr['grade_level'] . '-' . $enr['section']); ?></small>
                                        </td>
                                        <td><strong><?= htmlspecialchars($enr['program_name']); ?></strong></td>
                                        <td><?= htmlspecialchars(date('M j, Y', strtotime($enr['start_date']))); ?></td>
                                        <td style="min-width: 140px;">
                                            <div style="display:flex; justify-content:space-between; font-size:11px; font-weight:700;">
                                                <span><?= (int) $enr['progress_percent']; ?>%</span>
                                                <small><?= (int) $enr['hours_completed']; ?>/<?= (int) $enr['duration_hours']; ?> hrs</small>
                                            </div>
                                            <div class="progress-bar-container">
                                                <div class="progress-bar-fill" style="width: <?= min(100, max(0, (int) $enr['progress_percent'])); ?>%;"></div>
                                            </div>
                                        </td>
                                        <td><?= (int) $enr['hours_completed']; ?> hrs</td>
                                        <td>
                                            <span class="status-badge <?= $badge; ?>"><?= htmlspecialchars(ucfirst($enr['status'])); ?></span>
                                        </td>
                                        <td class="record-actions">
                                            <button class="table-action update-progress-btn" 
                                                    data-id="<?= (int) $enr['enrollment_id']; ?>"
                                                    data-hours="<?= (int) $enr['hours_completed']; ?>"
                                                    data-percent="<?= (int) $enr['progress_percent']; ?>"
                                                    data-status="<?= htmlspecialchars($enr['status']); ?>"
                                                    data-notes="<?= htmlspecialchars($enr['notes'] ?? ''); ?>"
                                                    type="button">
                                                Update Progress
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state compact-empty-state">
                                            <div class="empty-icon"><i class="fa-solid fa-rotate"></i></div>
                                            <strong>No students enrolled in reformation</strong>
                                            <p>Use "Enroll Student" above to assign a student to a rehabilitation program.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Programs Catalogue -->
            <section class="activity-panel" style="margin-top: 30px;">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Curriculum</p>
                        <h2>Available Reformation Programs</h2>
                    </div>
                    <button class="secondary-button" id="new-program-btn" type="button">
                        <i class="fa-solid fa-plus"></i> New Program
                    </button>
                </div>

                <div class="programs-grid">
                    <?php foreach ($programsList as $prog): ?>
                        <article class="program-card" style="position: relative;">
                            <button class="table-action edit-program-btn" style="position: absolute; top: 20px; right: 20px; background: none; border: none; cursor: pointer;"
                                data-id="<?= (int) $prog['program_id']; ?>"
                                data-name="<?= htmlspecialchars($prog['program_name']); ?>"
                                data-desc="<?= htmlspecialchars($prog['description']); ?>"
                                data-hours="<?= (int) $prog['duration_hours']; ?>"
                                data-coord="<?= htmlspecialchars($prog['coordinator']); ?>"
                                aria-label="Edit Program">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <h3 style="padding-right: 24px;"><?= htmlspecialchars($prog['program_name']); ?></h3>
                            <p><?= htmlspecialchars($prog['description']); ?></p>
                            <div class="program-card-meta">
                                <span><i class="fa-regular fa-clock"></i> <?= (int) $prog['duration_hours']; ?> Hours</span>
                                <span><?= htmlspecialchars($prog['coordinator']); ?></span>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal to Enroll Student -->
    <div class="record-modal" id="enroll-modal" hidden>
        <form class="record-form" id="enroll-form">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Rehabilitation Assignment</p>
                    <h2>Enroll Student in Reformation</h2>
                </div>
                <button class="modal-close" id="close-enroll-modal" type="button" aria-label="Close">&times;</button>
            </div>

            <div class="form-grid">
                <label class="full-field">Student
                    <select id="enroll-student-id" required>
                        <option value="">-- Choose student --</option>
                        <?php foreach ($studentsList as $st): ?>
                            <option value="<?= (int) $st['student_id']; ?>">
                                <?= htmlspecialchars($st['student_name'] . ' (' . $st['student_number'] . ') - ' . $st['grade_level']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="full-field">Reformation Program
                    <select id="enroll-program-id" required>
                        <option value="">-- Choose program --</option>
                        <?php foreach ($programsList as $pr): ?>
                            <option value="<?= (int) $pr['program_id']; ?>">
                                <?= htmlspecialchars($pr['program_name'] . ' (' . $pr['duration_hours'] . ' Hours)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Start Date
                    <input type="date" id="enroll-start-date" value="<?= date('Y-m-d'); ?>" required>
                </label>

                <label>Target Completion Date
                    <input type="date" id="enroll-end-date">
                </label>

                <label class="full-field">Notes &amp; Objectives
                    <textarea id="enroll-notes" rows="3" placeholder="Specific behavioral targets, adviser coordination..."></textarea>
                </label>
            </div>

            <p class="form-error" id="enroll-error" role="alert"></p>
            <div class="modal-actions">
                <button class="secondary-button" id="cancel-enroll" type="button">Cancel</button>
                <button class="primary-button" type="submit">Enroll Student</button>
            </div>
        </form>
    </div>

    <!-- Modal to Update Progress -->
    <div class="record-modal" id="progress-modal" hidden>
        <form class="record-form" id="progress-form" style="width: min(100%, 460px);">
            <div class="modal-heading">
                <h3>Update Student Progress</h3>
                <button class="modal-close" id="close-progress-modal" type="button" aria-label="Close">&times;</button>
            </div>

            <input type="hidden" id="prog-enrollment-id">

            <div class="form-grid" style="grid-template-columns: 1fr;">
                <label>Hours Completed
                    <input type="number" id="prog-hours" min="0" required>
                </label>

                <label>Progress Percentage (0 - 100%)
                    <input type="number" id="prog-percent" min="0" max="100" required>
                </label>

                <label>Program Status
                    <select id="prog-status" required>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="Failed">Failed</option>
                    </select>
                </label>

                <label>Counselor / Coordinator Remarks
                    <textarea id="prog-notes" rows="3" placeholder="Student attendance and attitude remarks..."></textarea>
                </label>
            </div>

            <p class="form-error" id="progress-error" role="alert"></p>
            <div class="modal-actions">
                <button class="secondary-button" id="cancel-progress" type="button">Cancel</button>
                <button class="primary-button" type="submit">Save Progress</button>
            </div>
        </form>
    </div>

    <!-- Modal to Manage Program -->
    <div class="record-modal" id="program-modal" hidden>
        <form class="record-form" id="program-form" style="width: min(100%, 500px);">
            <div class="modal-heading">
                <h3 id="program-form-title">New Program</h3>
                <button class="modal-close" id="close-program-modal" type="button" aria-label="Close">&times;</button>
            </div>

            <input type="hidden" id="program-id">

            <div class="form-grid" style="grid-template-columns: 1fr;">
                <label>Program Name
                    <input type="text" id="program-name" required>
                </label>

                <label>Description
                    <textarea id="program-desc" rows="3" required placeholder="Describe the program activities and goals..."></textarea>
                </label>

                <label>Duration (Hours)
                    <input type="number" id="program-hours" min="1" required>
                </label>

                <label>Coordinator
                    <input type="text" id="program-coord" required placeholder="Name of person in charge">
                </label>
            </div>

            <p class="form-error" id="program-error" role="alert"></p>
            <div class="modal-actions">
                <button class="secondary-button" id="cancel-program" type="button">Cancel</button>
                <button class="primary-button" type="submit">Save Program</button>
            </div>
        </form>
    </div>

    <script>
        const enrollModal = document.getElementById('enroll-modal');
        const enrollForm = document.getElementById('enroll-form');
        const progressModal = document.getElementById('progress-modal');
        const progressForm = document.getElementById('progress-form');
        const programModal = document.getElementById('program-modal');
        const programForm = document.getElementById('program-form');

        // Program Management
        document.getElementById('new-program-btn')?.addEventListener('click', () => {
            programForm.reset();
            document.getElementById('program-id').value = '';
            document.getElementById('program-form-title').textContent = 'New Program';
            document.getElementById('program-error').textContent = '';
            programModal.hidden = false;
        });

        document.querySelectorAll('.edit-program-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('program-id').value = btn.dataset.id;
                document.getElementById('program-name').value = btn.dataset.name;
                document.getElementById('program-desc').value = btn.dataset.desc;
                document.getElementById('program-hours').value = btn.dataset.hours;
                document.getElementById('program-coord').value = btn.dataset.coord;
                document.getElementById('program-form-title').textContent = 'Edit Program';
                document.getElementById('program-error').textContent = '';
                programModal.hidden = false;
            });
        });

        document.getElementById('close-program-modal')?.addEventListener('click', () => programModal.hidden = true);
        document.getElementById('cancel-program')?.addEventListener('click', () => programModal.hidden = true);

        programForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const err = document.getElementById('program-error');
            err.textContent = '';
            
            const pid = document.getElementById('program-id').value;
            const payload = {
                program_name: document.getElementById('program-name').value,
                description: document.getElementById('program-desc').value,
                duration_hours: document.getElementById('program-hours').value,
                coordinator: document.getElementById('program-coord').value,
                status: 'Active'
            };

            const method = pid ? 'PUT' : 'POST';
            const url = pid ? `../../server/api.php?resource=reformation_programs&id=${pid}` : `../../server/api.php?resource=reformation_programs`;

            const res = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            
            if (!res.ok || data.status !== 'success') {
                err.textContent = data.message || 'Failed to save program.';
                return;
            }
            window.location.reload();
        });

        document.getElementById('enroll-btn')?.addEventListener('click', () => {
            enrollForm.reset();
            document.getElementById('enroll-error').textContent = '';
            document.getElementById('enroll-start-date').value = new Date().toISOString().split('T')[0];
            enrollModal.hidden = false;
        });

        document.getElementById('close-enroll-modal')?.addEventListener('click', () => enrollModal.hidden = true);
        document.getElementById('cancel-enroll')?.addEventListener('click', () => enrollModal.hidden = true);

        // Submit new enrollment
        enrollForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const err = document.getElementById('enroll-error');
            err.textContent = '';

            const payload = {
                student_id: document.getElementById('enroll-student-id').value,
                program_id: document.getElementById('enroll-program-id').value,
                start_date: document.getElementById('enroll-start-date').value,
                expected_completion: document.getElementById('enroll-end-date').value,
                notes: document.getElementById('enroll-notes').value
            };

            const res = await fetch('../../server/api.php?resource=reformations', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') {
                err.textContent = data.message || 'Failed to enroll student.';
                return;
            }

            window.location.reload();
        });

        // Open progress modal
        document.querySelectorAll('.update-progress-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('prog-enrollment-id').value = btn.dataset.id;
                document.getElementById('prog-hours').value = btn.dataset.hours;
                document.getElementById('prog-percent').value = btn.dataset.percent;
                document.getElementById('prog-status').value = btn.dataset.status;
                document.getElementById('prog-notes').value = btn.dataset.notes;
                document.getElementById('progress-error').textContent = '';
                progressModal.hidden = false;
            });
        });

        document.getElementById('close-progress-modal')?.addEventListener('click', () => progressModal.hidden = true);
        document.getElementById('cancel-progress')?.addEventListener('click', () => progressModal.hidden = true);

        // Submit progress update
        progressForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const err = document.getElementById('progress-error');
            err.textContent = '';
            const id = document.getElementById('prog-enrollment-id').value;

            const payload = {
                hours_completed: parseInt(document.getElementById('prog-hours').value, 10),
                progress_percent: parseInt(document.getElementById('prog-percent').value, 10),
                status: document.getElementById('prog-status').value,
                notes: document.getElementById('prog-notes').value
            };

            const res = await fetch(`../../server/api.php?resource=reformations&id=${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') {
                err.textContent = data.message || 'Failed to update progress.';
                return;
            }

            window.location.reload();
        });
    </script>
    <script src="../../server/script.js?v=animations-v1"></script>
</body>

</html>


