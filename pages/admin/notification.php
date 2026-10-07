<?php
// E-PDAMS Parent Notification Tool
// Sends email alerts to parents using PHPMailer and tracks all communication logs.

require_once '../../server/db.php';
require_once '../../server/mail.php';
session_start();

$conn = getDbConnection();
$totalSent = 0;
$totalFailed = 0;
$totalPending = 0;
$notificationsList = [];
$studentsList = [];
$smtpSettings = [];
$dbError = null;

if ($conn) {
    // metrics
    $mRes = $conn->query("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'Sent' THEN 1 ELSE 0 END) AS sent,
        SUM(CASE WHEN status = 'Failed' THEN 1 ELSE 0 END) AS failed,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending
        FROM parent_notification");
    if ($mRes && $m = $mRes->fetch_assoc()) {
        $totalSent = (int) ($m['sent'] ?? 0);
        $totalFailed = (int) ($m['failed'] ?? 0);
        $totalPending = (int) ($m['pending'] ?? 0);
    }

    // notifications log
    $nQuery = "SELECT pn.*, s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name,
                      s.parent_name, s.grade_level, s.section
               FROM parent_notification pn
               INNER JOIN students s ON s.student_id = pn.student_id
               ORDER BY pn.created_at DESC LIMIT 100";
    $nRes = $conn->query($nQuery);
    if ($nRes) {
        while ($r = $nRes->fetch_assoc()) {
            $notificationsList[] = $r;
        }
    }

    // fetch students with parent info
    $sRes = $conn->query("SELECT student_id, student_number, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS student_name,
                                 parent_name, parent_email, parent_contact, grade_level, section 
                          FROM students WHERE LOWER(status) = 'active' ORDER BY last_name, first_name");
    if ($sRes) {
        while ($sr = $sRes->fetch_assoc()) {
            $studentsList[] = $sr;
        }
    }

    // fetch SMTP config
    $smtpSettings = getSmtpSettings($conn);
    unset($smtpSettings['smtp_pass']); // hide pass

    $conn->close();
} else {
    $dbError = 'Database connection failed. Please ensure MySQL is running.';
}

$todayLabel = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Notifications | E-PDAMS</title>
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
                <a class="nav-link active" href="notification.php" aria-current="page"><i class="fa-solid fa-bell nav-icon"></i><span>Notifications</span></a>

                <p class="nav-label">Discipline</p>
                <a class="nav-link" href="violationSetup.php"><i class="fa-solid fa-triangle-exclamation nav-icon"></i><span>Violation Records</span></a>
                <a class="nav-link" href="sanction.php"><i class="fa-solid fa-gavel nav-icon"></i><span>Sanctions</span></a>
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
                <div class="breadcrumb"><span>Main</span><span aria-hidden="true">/</span><strong>Notifications</strong></div>
                <div class="topbar-date"><?= htmlspecialchars($todayLabel); ?></div>
            </header>

            <section class="page-heading">
                <div>
                    <p class="eyebrow">Parent Communication Bridge</p>
                    <h1>Parent Notification Tool</h1>
                    <p class="heading-copy">Send direct email notifications to parents regarding student infractions, sanctions, and clearance holds.</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <button class="secondary-button" id="smtp-settings-btn" type="button">
                        <i class="fa-solid fa-gear"></i> SMTP Settings
                    </button>
                    <button class="primary-button" id="compose-btn" type="button">
                        <i class="fa-solid fa-paper-plane"></i> Send Notification
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
            <section class="metrics" aria-label="Notifications overview">
                <article class="metric-card accent-green">
                    <span>Sent Successfully</span>
                    <strong><?= number_format($totalSent); ?></strong>
                    <small>Dispatched to parent inboxes</small>
                </article>
                <article class="metric-card accent-yellow">
                    <span>Pending / Queued</span>
                    <strong><?= number_format($totalPending); ?></strong>
                    <small>Scheduled for delivery</small>
                </article>
                <article class="metric-card accent-coral">
                    <span>Failed Deliveries</span>
                    <strong><?= number_format($totalFailed); ?></strong>
                    <small>Incorrect email or network errors</small>
                </article>
                <article class="metric-card accent-teal">
                    <span>Total Logs</span>
                    <strong><?= number_format(count($notificationsList)); ?></strong>
                    <small>Communication records on file</small>
                </article>
            </section>

            <!-- Notifications Log Table -->
            <section class="activity-panel" id="notifications-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Communication Trail</p>
                        <h2>Sent Parent Notifications</h2>
                    </div>
                </div>

                <div class="records-table-wrap">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Recipient &amp; Student</th>
                                <th>Notification Type</th>
                                <th>Subject</th>
                                <th>Channel</th>
                                <th>Sent Date</th>
                                <th>Status</th>
                                <th>View Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($notificationsList)): ?>
                                <?php foreach ($notificationsList as $notif): ?>
                                    <?php
                                    $st = strtolower($notif['status']);
                                    $badge = 'approved';
                                    if ($st === 'failed') $badge = 'archived';
                                    elseif ($st === 'pending') $badge = 'pending';
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($notif['recipient']); ?></strong>
                                            <small><?= htmlspecialchars($notif['student_name'] . ' (' . $notif['student_number'] . ')'); ?></small>
                                        </td>
                                        <td><span class="severity-label"><?= htmlspecialchars($notif['notification_type']); ?></span></td>
                                        <td><?= htmlspecialchars($notif['subject']); ?></td>
                                        <td><i class="fa-solid fa-envelope" style="color:var(--teal)"></i> <?= htmlspecialchars($notif['channel']); ?></td>
                                        <td><?= $notif['sent_at'] ? htmlspecialchars(date('M j, Y g:i A', strtotime($notif['sent_at']))) : '—'; ?></td>
                                        <td>
                                            <span class="status-badge <?= $badge; ?>"><?= htmlspecialchars(ucfirst($notif['status'])); ?></span>
                                            <?php if ($notif['error_message']): ?>
                                                <small class="table-subtext" style="color:#b63e38;" title="<?= htmlspecialchars($notif['error_message']); ?>">
                                                    <?= htmlspecialchars(substr($notif['error_message'], 0, 30) . '...'); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="table-action view-msg-btn" 
                                                    data-subject="<?= htmlspecialchars($notif['subject']); ?>"
                                                    data-recipient="<?= htmlspecialchars($notif['recipient']); ?>"
                                                    data-message="<?= htmlspecialchars($notif['message']); ?>"
                                                    type="button">
                                                Read
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state compact-empty-state">
                                            <div class="empty-icon"><i class="fa-regular fa-paper-plane"></i></div>
                                            <strong>No notifications sent yet</strong>
                                            <p>Click "Send Notification" to notify a parent about a violation or clearance hold.</p>
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

    <!-- Modal to Compose Notification -->
    <div class="record-modal" id="compose-modal" hidden>
        <form class="record-form" id="compose-form" style="width: min(100%, 650px);">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Parent Communication</p>
                    <h2>Compose Notification</h2>
                </div>
                <button class="modal-close" id="close-compose-modal" type="button" aria-label="Close">×</button>
            </div>

            <div class="form-grid">
                <label class="full-field">Select Student
                    <select id="compose-student-id" required>
                        <option value="">-- Choose student --</option>
                        <?php foreach ($studentsList as $st): ?>
                            <option value="<?= (int) $st['student_id']; ?>"
                                    data-name="<?= htmlspecialchars($st['student_name']); ?>"
                                    data-email="<?= htmlspecialchars($st['parent_email']); ?>"
                                    data-parent="<?= htmlspecialchars($st['parent_name'] ?: 'Parent/Guardian'); ?>">
                                <?= htmlspecialchars($st['student_name'] . ' (' . $st['student_number'] . ') - ' . $st['grade_level']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Parent Name
                    <input id="compose-parent-name" placeholder="Parent or guardian name" readonly style="background:#f1f5f9;">
                </label>

                <label>Recipient Email
                    <input id="compose-email" name="recipient" type="email" placeholder="parent@example.com" required>
                </label>

                <label class="full-field">Quick Template Preset
                    <select id="template-picker">
                        <option value="">-- Choose message template --</option>
                        <option value="violation">Violation Notification</option>
                        <option value="clearance">Clearance Hold Warning</option>
                        <option value="sanction">Sanction Requirement Notice</option>
                        <option value="conference">Disciplinary Conference Request</option>
                    </select>
                </label>

                <label class="full-field">Notification Type
                    <select id="compose-type" name="notification_type">
                        <option value="Violation Notice">Violation Notice</option>
                        <option value="Clearance Update">Clearance Update</option>
                        <option value="Sanction Notice">Sanction Notice</option>
                        <option value="Disciplinary Conference">Disciplinary Conference</option>
                        <option value="General Advisory">General Advisory</option>
                    </select>
                </label>

                <label class="full-field">Subject Line
                    <input id="compose-subject" name="subject" placeholder="Important Notice from Prefect Office" required>
                </label>

                <label class="full-field">Email Message Body
                    <textarea id="compose-message" name="message" rows="6" placeholder="Enter message text..." required></textarea>
                </label>
            </div>

            <p class="form-error" id="compose-error" role="alert"></p>
            <div class="modal-actions">
                <button class="secondary-button" id="cancel-compose" type="button">Cancel</button>
                <button class="primary-button" id="send-submit-btn" type="submit">
                    <i class="fa-solid fa-paper-plane"></i> Send Email
                </button>
            </div>
        </form>
    </div>

    <!-- Modal to View Sent Message Details -->
    <div class="record-modal" id="message-view-modal" hidden>
        <div class="record-form message-view-card">
            <div class="modal-heading">
                <div class="modal-heading-text">
                    <p class="eyebrow">Sent Message</p>
                    <h3 id="view-msg-subject"></h3>
                    <small id="view-msg-recipient"></small>
                </div>
                <button class="modal-close" id="close-view-msg-modal" type="button" aria-label="Close message modal">×</button>
            </div>
            <div class="modal-scroll-body">
                <p id="view-msg-body"></p>
            </div>
            <div class="modal-actions">
                <button class="secondary-button" id="close-msg-btn" type="button">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal for SMTP Settings -->
    <div class="record-modal" id="smtp-modal" hidden>
        <form class="record-form" id="smtp-form" style="width: min(100%, 560px);">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Email Configuration</p>
                    <h2>SMTP Mail Settings</h2>
                </div>
                <button class="modal-close" id="close-smtp-modal" type="button">×</button>
            </div>
            <p style="font-size: 12px; color: var(--muted); margin: 10px 0 0;">
                Configure real SMTP credentials (such as Gmail App Password) for live inbox delivery via PHPMailer.
            </p>

            <div class="form-grid">
                <label>SMTP Host
                    <input id="smtp-host" value="<?= htmlspecialchars($smtpSettings['smtp_host'] ?? 'smtp.gmail.com'); ?>" required>
                </label>

                <label>SMTP Port
                    <input id="smtp-port" type="number" value="<?= htmlspecialchars((string) ($smtpSettings['smtp_port'] ?? 587)); ?>" required>
                </label>

                <label>Username / Email
                    <input id="smtp-user" value="<?= htmlspecialchars($smtpSettings['smtp_user'] ?? ''); ?>" placeholder="your-email@gmail.com">
                </label>

                <label>Password / App Password
                    <input id="smtp-pass" type="password" placeholder="Leave blank to keep existing password">
                </label>

                <label>Encryption
                    <select id="smtp-enc">
                        <option value="tls" <?= ($smtpSettings['smtp_encryption'] ?? '') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                        <option value="ssl" <?= ($smtpSettings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    </select>
                </label>

                <label>Sender Display Name
                    <input id="sender-name" value="<?= htmlspecialchars($smtpSettings['sender_name'] ?? 'E-PDAMS Prefect Office'); ?>" required>
                </label>

                <label class="full-field">Sender Email Address
                    <input id="sender-email" type="email" value="<?= htmlspecialchars($smtpSettings['sender_email'] ?? 'prefect@school.edu'); ?>" required>
                </label>
            </div>

            <p class="form-error" id="smtp-error" role="alert"></p>
            <div class="modal-actions">
                <button class="secondary-button" id="cancel-smtp" type="button">Cancel</button>
                <button class="primary-button" type="submit">Save Settings</button>
            </div>
        </form>
    </div>

    <script>
        const composeModal = document.getElementById('compose-modal');
        const composeForm = document.getElementById('compose-form');
        const composeError = document.getElementById('compose-error');
        const studentSelect = document.getElementById('compose-student-id');
        const parentNameInput = document.getElementById('compose-parent-name');
        const emailInput = document.getElementById('compose-email');
        const templatePicker = document.getElementById('template-picker');
        const subjectInput = document.getElementById('compose-subject');
        const messageInput = document.getElementById('compose-message');
        const typeSelect = document.getElementById('compose-type');

        const smtpModal = document.getElementById('smtp-modal');
        const msgViewModal = document.getElementById('message-view-modal');

        document.getElementById('compose-btn')?.addEventListener('click', () => {
            composeForm.reset();
            composeError.textContent = '';
            composeModal.hidden = false;
        });

        document.getElementById('close-compose-modal')?.addEventListener('click', () => composeModal.hidden = true);
        document.getElementById('cancel-compose')?.addEventListener('click', () => composeModal.hidden = true);

        // When a student is selected, auto-fill parent info
        studentSelect?.addEventListener('change', () => {
            const opt = studentSelect.options[studentSelect.selectedIndex];
            parentNameInput.value = opt.dataset.parent || '';
            emailInput.value = opt.dataset.email || '';
            applyTemplate();
        });

        // Template selector
        templatePicker?.addEventListener('change', applyTemplate);

        function applyTemplate() {
            const tmpl = templatePicker.value;
            const opt = studentSelect.options[studentSelect.selectedIndex];
            const studentName = opt?.dataset?.name || '[Student Name]';
            const parentName = opt?.dataset?.parent || 'Parent/Guardian';

            if (tmpl === 'violation') {
                typeSelect.value = 'Violation Notice';
                subjectInput.value = `Official Notice: Disciplinary Infraction Recorded for ${studentName}`;
                messageInput.value = `Dear ${parentName},\n\nThis is an official communication from the Office of the Prefect of Discipline.\n\nPlease be informed that your child, ${studentName}, was recorded with a school disciplinary infraction.\n\nWe request your cooperation in discussing this matter at home to maintain a safe and productive learning environment. You may contact the Prefect Office for further details.\n\nSincerely,\nOffice of the Prefect of Discipline\nE-PDAMS`;
            } else if (tmpl === 'clearance') {
                typeSelect.value = 'Clearance Update';
                subjectInput.value = `Urgent Advisory: Clearance Hold Placed for ${studentName}`;
                messageInput.value = `Dear ${parentName},\n\nPlease be advised that a temporary Disciplinary Clearance Hold has been placed on the academic records of ${studentName}.\n\nTo clear this hold, the student must fulfill their pending corrective obligations or schedule a conference with the Prefect Office.\n\nSincerely,\nOffice of the Prefect of Discipline`;
            } else if (tmpl === 'sanction') {
                typeSelect.value = 'Sanction Notice';
                subjectInput.value = `Notice of Corrective Measure / Sanction for ${studentName}`;
                messageInput.value = `Dear ${parentName},\n\nFollowing our review of recent behavior logs, ${studentName} has been assigned a corrective disciplinary measure.\n\nPlease ensure your child completes the assigned obligations on schedule so their disciplinary status remains in good standing.\n\nRespectfully,\nOffice of the Prefect of Discipline`;
            } else if (tmpl === 'conference') {
                typeSelect.value = 'Disciplinary Conference';
                subjectInput.value = `Request for Parent Conference: Disciplinary Matters - ${studentName}`;
                messageInput.value = `Dear ${parentName},\n\nYou are cordially requested to attend an in-person conference with the Prefect of Discipline regarding the conduct of ${studentName}.\n\nPlease coordinate with our office at your earliest convenience to confirm an appointment date and time.\n\nThank you for your prompt cooperation.\n\nPrefect Office`;
            }
        }

        // Send Email
        composeForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            composeError.textContent = '';
            const sendBtn = document.getElementById('send-submit-btn');
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

            const payload = {
                student_id: studentSelect.value,
                recipient: emailInput.value,
                subject: subjectInput.value,
                message: messageInput.value,
                notification_type: typeSelect.value
            };

            try {
                const res = await fetch('../../server/api.php?resource=notifications', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!res.ok || data.status !== 'success') {
                    composeError.textContent = data.message || 'Failed to dispatch notification.';
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Email';
                    return;
                }
                window.location.reload();
            } catch (err) {
                composeError.textContent = 'Connection error: ' + err.message;
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Email';
            }
        });

        // View sent message modal
        document.querySelectorAll('.view-msg-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('view-msg-subject').textContent = btn.dataset.subject;
                document.getElementById('view-msg-recipient').textContent = 'To: ' + btn.dataset.recipient;
                document.getElementById('view-msg-body').textContent = btn.dataset.message;
                msgViewModal.hidden = false;
            });
        });

        document.getElementById('close-view-msg-modal')?.addEventListener('click', () => msgViewModal.hidden = true);
        document.getElementById('close-msg-btn')?.addEventListener('click', () => msgViewModal.hidden = true);

        // SMTP Settings modal
        document.getElementById('smtp-settings-btn')?.addEventListener('click', () => {
            document.getElementById('smtp-error').textContent = '';
            smtpModal.hidden = false;
        });

        document.getElementById('close-smtp-modal')?.addEventListener('click', () => smtpModal.hidden = true);
        document.getElementById('cancel-smtp')?.addEventListener('click', () => smtpModal.hidden = true);

        document.getElementById('smtp-form')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const err = document.getElementById('smtp-error');
            err.textContent = '';

            const payload = {
                smtp_host: document.getElementById('smtp-host').value,
                smtp_port: document.getElementById('smtp-port').value,
                smtp_user: document.getElementById('smtp-user').value,
                smtp_pass: document.getElementById('smtp-pass').value,
                smtp_encryption: document.getElementById('smtp-enc').value,
                sender_name: document.getElementById('sender-name').value,
                sender_email: document.getElementById('sender-email').value
            };

            const res = await fetch('../../server/api.php?resource=smtp', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') {
                err.textContent = data.message || 'Unable to update SMTP settings.';
                return;
            }
            alert('SMTP Settings saved successfully!');
            smtpModal.hidden = true;
        });
    </script>
    <script src="../../server/script.js?v=animations-v1"></script>
</body>

</html>


