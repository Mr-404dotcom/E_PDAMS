<?php
// E-PDAMS Incident Reports Module
// Generates, manages, and prints formal incident reports for student disciplinary infractions.

require_once '../../server/db.php';
session_start();

$conn = getDbConnection();
$totalReports = 0;
$investigationCount = 0;
$resolvedCount = 0;
$filedCount = 0;
$reportsList = [];
$studentsList = [];
$infractionsList = [];
$dbError = null;

if ($conn) {
    // metrics
    $mRes = $conn->query("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN LOWER(status) = 'under investigation' THEN 1 ELSE 0 END) AS under_investigation,
        SUM(CASE WHEN LOWER(status) = 'resolved' THEN 1 ELSE 0 END) AS resolved,
        SUM(CASE WHEN LOWER(status) = 'filed' THEN 1 ELSE 0 END) AS filed
        FROM incident_report");
    if ($mRes && $m = $mRes->fetch_assoc()) {
        $totalReports = (int) ($m['total'] ?? 0);
        $investigationCount = (int) ($m['under_investigation'] ?? 0);
        $resolvedCount = (int) ($m['resolved'] ?? 0);
        $filedCount = (int) ($m['filed'] ?? 0);
    }

    // fetch all reports
    $rQuery = "SELECT ir.*, s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name,
                      s.grade_level, s.section, s.parent_name, s.parent_contact, s.parent_email,
                      u.full_name AS reported_by_name
               FROM incident_report ir
               INNER JOIN students s ON s.student_id = ir.student_id
               LEFT JOIN users u ON u.user_id = ir.reported_by
               ORDER BY ir.incident_date DESC, ir.incident_id DESC";
    $rRes = $conn->query($rQuery);
    if ($rRes) {
        while ($row = $rRes->fetch_assoc()) {
            $reportsList[] = $row;
        }
    }

    // fetch active students
    $sRes = $conn->query("SELECT student_id, student_number, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS student_name, grade_level, section FROM students WHERE LOWER(status) = 'active' ORDER BY last_name, first_name");
    if ($sRes) {
        while ($sr = $sRes->fetch_assoc()) {
            $studentsList[] = $sr;
        }
    }

    // fetch violations to optionally link to report
    $iRes = $conn->query("SELECT i.infraction_id, i.student_id, vc.category_name, i.offense_committed, i.date_time 
                          FROM infraction_logging i 
                          INNER JOIN violation_category vc ON vc.category_id = i.category_id
                          ORDER BY i.date_time DESC LIMIT 100");
    if ($iRes) {
        while ($ir = $iRes->fetch_assoc()) {
            $infractionsList[] = $ir;
        }
    }

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
    <title>Incident Reports | E-PDAMS</title>
    <link rel="icon" type="image/png" href="../../assets/logo.png">
    <link rel="stylesheet" href="../css/style.css?v=animations-v1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        /* Printable document styling for official incident report */
        @media print {
            body * {
                visibility: hidden;
            }
            #print-area, #print-area * {
                visibility: visible;
            }
            #print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: #fff;
                color: #000;
                padding: 20px;
            }
            .no-print {
                display: none !important;
            }
        }
        .print-modal-sheet {
            background: #fff;
            padding: 36px 40px;
            border-radius: 8px;
            max-width: 780px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            color: #1a2a3a;
        }
        .report-header-banner {
            text-align: center;
            border-bottom: 2px solid #1672b8;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .report-header-banner h2 {
            margin: 4px 0;
            font-size: 22px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #124d78;
        }
        .report-header-banner p {
            margin: 0;
            font-size: 13px;
            color: #555;
        }
        .report-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
            background: #f8fafc;
            padding: 16px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .report-meta-grid div span {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
        }
        .report-meta-grid div strong {
            font-size: 14px;
            color: #1e293b;
        }
        .report-section {
            margin-bottom: 16px;
        }
        .report-section h4 {
            margin: 0 0 6px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #1672b8;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .report-section p {
            margin: 0;
            font-size: 13px;
            line-height: 1.6;
            color: #334155;
            white-space: pre-wrap;
        }
        .report-signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 40px;
            padding-top: 20px;
        }
        .signature-line {
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 6px;
            font-size: 12px;
            font-weight: 600;
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
                <a class="nav-link active" href="IncidentReports.php" aria-current="page"><i class="fa-solid fa-file-circle-exclamation nav-icon"></i><span>Incident Reports</span></a>
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
                <div class="breadcrumb"><span>Discipline</span><span aria-hidden="true">/</span><strong>Incident Reports</strong></div>
                <div class="topbar-date"><?= htmlspecialchars($todayLabel); ?></div>
            </header>

            <section class="page-heading">
                <div>
                    <p class="eyebrow">Documentation &amp; Compliance</p>
                    <h1>Incident Reports</h1>
                    <p class="heading-copy">Formally document school incidents, record investigations, and produce official printable incident sheets.</p>
                </div>
                <button class="primary-button" id="create-report-btn" type="button">
                    <i class="fa-solid fa-plus"></i> File Incident Report
                </button>
            </section>

            <?php if ($dbError): ?>
                <div class="clearance-flash error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><strong>Database Connection Error</strong><span><?= htmlspecialchars($dbError); ?></span></div>
                </div>
            <?php endif; ?>

            <!-- Metrics -->
            <section class="metrics" aria-label="Reports summary">
                <article class="metric-card accent-teal">
                    <span>Total Filed Reports</span>
                    <strong><?= number_format($totalReports); ?></strong>
                    <small>Official recorded incidents</small>
                </article>
                <article class="metric-card accent-coral">
                    <span>Under Investigation</span>
                    <strong><?= number_format($investigationCount); ?></strong>
                    <small>Active case reviews</small>
                </article>
                <article class="metric-card accent-green">
                    <span>Resolved Reports</span>
                    <strong><?= number_format($resolvedCount); ?></strong>
                    <small>Completed investigations</small>
                </article>
                <article class="metric-card accent-yellow">
                    <span>Initial Filings</span>
                    <strong><?= number_format($filedCount); ?></strong>
                    <small>Pending formal review</small>
                </article>
            </section>

            <!-- Incident Reports Table -->
            <section class="activity-panel" id="reports-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Records</p>
                        <h2>Filed Incident Reports</h2>
                    </div>
                </div>

                <div class="records-table-wrap">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>Report #</th>
                                <th>Title &amp; Location</th>
                                <th>Student</th>
                                <th>Incident Date</th>
                                <th>Violation</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($reportsList)): ?>
                                <?php foreach ($reportsList as $r): ?>
                                    <?php
                                    $st = strtolower($r['status']);
                                    $badge = 'pending';
                                    if ($st === 'resolved' || $st === 'closed') $badge = 'approved';
                                    elseif ($st === 'under investigation') $badge = 'review';
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($r['report_number']); ?></strong></td>
                                        <td>
                                            <strong><?= htmlspecialchars($r['report_title']); ?></strong>
                                            <?php if ($r['incident_location']): ?>
                                                <small class="table-subtext"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($r['incident_location']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($r['student_name']); ?></strong>
                                            <small><?= htmlspecialchars($r['student_number'] . ' - ' . $r['grade_level'] . '-' . $r['section']); ?></small>
                                        </td>
                                        <td><?= htmlspecialchars(date('M j, Y g:i A', strtotime($r['incident_date']))); ?></td>
                                        <td><span class="severity-label"><?= htmlspecialchars($r['violation'] ?: 'General Incident'); ?></span></td>
                                        <td>
                                            <span class="status-badge <?= $badge; ?>"><?= htmlspecialchars(ucwords($r['status'])); ?></span>
                                        </td>
                                        <td class="record-actions">
                                            <button class="table-action view-print-btn" data-report='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8'); ?>' type="button">
                                                <i class="fa-solid fa-print"></i> View / Print
                                            </button>
                                            <button class="table-action edit-status-btn" data-id="<?= (int) $r['incident_id']; ?>" data-status="<?= htmlspecialchars($r['status']); ?>" type="button">
                                                Status
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state compact-empty-state">
                                            <div class="empty-icon"><i class="fa-solid fa-file-circle-exclamation"></i></div>
                                            <strong>No incident reports filed yet</strong>
                                            <p>Use the "File Incident Report" button above to document a formal school incident.</p>
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

    <!-- Modal to File New Incident Report -->
    <div class="record-modal" id="report-modal" hidden>
        <form class="record-form" id="report-form" style="width: min(100%, 720px);">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Prefect Disciplinary System</p>
                    <h2>File Incident Report</h2>
                </div>
                <button class="modal-close" id="close-report-modal" type="button" aria-label="Close">&times;</button>
            </div>

            <div class="form-grid">
                <label>Student Involved
                    <select id="report-student-id" name="student_id" required>
                        <option value="">-- Choose student --</option>
                        <?php foreach ($studentsList as $st): ?>
                            <option value="<?= (int) $st['student_id']; ?>">
                                <?= htmlspecialchars($st['student_name'] . ' (' . $st['student_number'] . ') - ' . $st['grade_level']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Report Title
                    <input id="report-title" name="report_title" placeholder="e.g. Science Lab Disruption" required>
                </label>

                <label>Incident Date &amp; Time
                    <input type="datetime-local" id="report-date" name="incident_date" required>
                </label>

                <label>Location
                    <input id="report-location" name="incident_location" placeholder="e.g. 3rd Floor Hallway, Cafeteria" required>
                </label>

                <label class="full-field">Offense / Violation Type
                    <input id="report-violation" name="violation" placeholder="e.g. Fighting, Bullying, Property Damage" required>
                </label>

                <label class="full-field">Incident Description &amp; Details
                    <textarea id="report-description" name="description" rows="4" placeholder="Detailed factual description of what occurred..." required></textarea>
                </label>

                <label>Persons Involved / Companions
                    <input id="report-persons" name="persons_involved" placeholder="Names of other students or staff">
                </label>

                <label>Witnesses
                    <input id="report-witnesses" name="witnesses" placeholder="Teachers or students who observed">
                </label>

                <label class="full-field">Evidence Details
                    <textarea id="report-evidence" name="evidence_details" rows="2" placeholder="CCTV clips, damaged items, written statements..."></textarea>
                </label>

                <label class="full-field">Immediate Action Taken
                    <textarea id="report-action" name="action_taken" rows="2" placeholder="First aid, parents contacted, separated from peers..."></textarea>
                </label>

                <label class="full-field">Recommended Prefect Measures
                    <textarea id="report-recommendations" name="recommendations" rows="2" placeholder="Counseling, suspension, campus service, parent conference..."></textarea>
                </label>

                <label>Initial Status
                    <select id="report-status" name="status">
                        <option value="Filed">Filed</option>
                        <option value="Under Investigation">Under Investigation</option>
                        <option value="Resolved">Resolved</option>
                    </select>
                </label>
            </div>

            <p class="form-error" id="report-error" role="alert"></p>
            <div class="modal-actions">
                <button class="secondary-button" id="cancel-report" type="button">Cancel</button>
                <button class="primary-button" type="submit">Submit Incident Report</button>
            </div>
        </form>
    </div>

    <!-- Modal for Printable View -->
    <div class="record-modal" id="print-modal" hidden>
        <div class="print-modal-sheet" id="print-area">
            <div class="no-print" style="display:flex; justify-content:space-between; margin-bottom: 20px;">
                <button class="secondary-button" id="close-print-modal" type="button"><i class="fa-solid fa-arrow-left"></i> Back</button>
                <button class="primary-button" onclick="window.print()" type="button"><i class="fa-solid fa-print"></i> Print Document</button>
            </div>

            <div class="report-header-banner">
                <img src="../../assets/logo.png" alt="E-PDAMS Logo" style="height:55px; width:auto; object-fit:contain; margin-bottom:8px;">
                <p><strong>OFFICE OF THE PREFECT OF DISCIPLINE</strong></p>
                <h2>STUDENT INCIDENT REPORT</h2>
                <p>Electronic Prefect Disciplinary Action Management System (E-PDAMS)</p>
            </div>

            <div class="report-meta-grid">
                <div><span>Report Number</span><strong id="print-report-no"></strong></div>
                <div><span>Status</span><strong id="print-status"></strong></div>
                <div><span>Student Name</span><strong id="print-student-name"></strong></div>
                <div><span>Student Number</span><strong id="print-student-no"></strong></div>
                <div><span>Grade &amp; Section</span><strong id="print-grade-sec"></strong></div>
                <div><span>Incident Date</span><strong id="print-incident-date"></strong></div>
                <div><span>Location</span><strong id="print-location"></strong></div>
                <div><span>Violation Type</span><strong id="print-violation"></strong></div>
            </div>

            <div class="report-section">
                <h4>Incident Title</h4>
                <p><strong id="print-title"></strong></p>
            </div>

            <div class="report-section">
                <h4>Description of Occurrence</h4>
                <p id="print-desc"></p>
            </div>

            <div class="report-section">
                <h4>Persons Involved &amp; Witnesses</h4>
                <p><strong>Persons Involved:</strong> <span id="print-persons"></span></p>
                <p><strong>Witnesses:</strong> <span id="print-witnesses"></span></p>
            </div>

            <div class="report-section">
                <h4>Physical or Recorded Evidence</h4>
                <p id="print-evidence"></p>
            </div>

            <div class="report-section">
                <h4>Immediate Action Taken</h4>
                <p id="print-action"></p>
            </div>

            <div class="report-section">
                <h4>Recommendations &amp; Sanctions</h4>
                <p id="print-recommendations"></p>
            </div>

            <div class="report-signatures">
                <div>
                    <br><br>
                    <div class="signature-line">Reporting Officer / Prefect Signature</div>
                </div>
                <div>
                    <br><br>
                    <div class="signature-line">Discipline Coordinator / Principal Signature</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Status Update Modal -->
    <div class="record-modal" id="status-modal" hidden>
        <div class="record-form" style="width: min(100%, 420px);">
            <div class="modal-heading">
                <h3>Update Report Status</h3>
                <button class="modal-close" id="close-status-modal" type="button" aria-label="Close">&times;</button>
            </div>
            <input type="hidden" id="status-incident-id">
            <div style="padding: 20px 0;">
                <label>Select Status
                    <select id="status-select" style="width: 100%; padding: 10px; margin-top: 6px;">
                        <option value="Filed">Filed</option>
                        <option value="Under Investigation">Under Investigation</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Closed">Closed</option>
                    </select>
                </label>
            </div>
            <div class="modal-actions">
                <button class="secondary-button" id="cancel-status-btn" type="button">Cancel</button>
                <button class="primary-button" id="save-status-btn" type="button">Update</button>
            </div>
        </div>
    </div>

    <script>
        const reportModal = document.getElementById('report-modal');
        const reportForm = document.getElementById('report-form');
        const reportError = document.getElementById('report-error');
        const printModal = document.getElementById('print-modal');
        const statusModal = document.getElementById('status-modal');

        // Set default date-time to now
        function setDefaultDateTime() {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('report-date').value = now.toISOString().slice(0, 16);
        }

        document.getElementById('create-report-btn')?.addEventListener('click', () => {
            reportForm.reset();
            reportError.textContent = '';
            setDefaultDateTime();
            reportModal.hidden = false;
        });

        document.getElementById('close-report-modal')?.addEventListener('click', () => reportModal.hidden = true);
        document.getElementById('cancel-report')?.addEventListener('click', () => reportModal.hidden = true);

        // Submit new report
        reportForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            reportError.textContent = '';

            const payload = {
                student_id: document.getElementById('report-student-id').value,
                report_title: document.getElementById('report-title').value,
                incident_date: document.getElementById('report-date').value.replace('T', ' ') + ':00',
                incident_location: document.getElementById('report-location').value,
                violation: document.getElementById('report-violation').value,
                description: document.getElementById('report-description').value,
                persons_involved: document.getElementById('report-persons').value,
                witnesses: document.getElementById('report-witnesses').value,
                evidence_details: document.getElementById('report-evidence').value,
                action_taken: document.getElementById('report-action').value,
                recommendations: document.getElementById('report-recommendations').value,
                status: document.getElementById('report-status').value
            };

            const res = await fetch('../../server/api.php?resource=incident_reports', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') {
                reportError.textContent = data.message || 'Unable to file report.';
                return;
            }

            window.location.reload();
        });

        // View and print report
        document.querySelectorAll('.view-print-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const rep = JSON.parse(btn.dataset.report);
                document.getElementById('print-report-no').textContent = rep.report_number;
                document.getElementById('print-status').textContent = rep.status.toUpperCase();
                document.getElementById('print-student-name').textContent = rep.student_name;
                document.getElementById('print-student-no').textContent = rep.student_number;
                document.getElementById('print-grade-sec').textContent = rep.grade_level + ' - ' + rep.section;
                document.getElementById('print-incident-date').textContent = new Date(rep.incident_date).toLocaleString();
                document.getElementById('print-location').textContent = rep.incident_location || 'Campus Premises';
                document.getElementById('print-violation').textContent = rep.violation || 'General Incident';
                document.getElementById('print-title').textContent = rep.report_title;
                document.getElementById('print-desc').textContent = rep.description;
                document.getElementById('print-persons').textContent = rep.persons_involved || 'None recorded';
                document.getElementById('print-witnesses').textContent = rep.witnesses || 'None recorded';
                document.getElementById('print-evidence').textContent = rep.evidence_details || 'No physical evidence recorded.';
                document.getElementById('print-action').textContent = rep.action_taken || 'No immediate action recorded.';
                document.getElementById('print-recommendations').textContent = rep.recommendations || 'Case referred for standard prefect review.';

                printModal.hidden = false;
            });
        });

        document.getElementById('close-print-modal')?.addEventListener('click', () => printModal.hidden = true);

        // Edit status
        document.querySelectorAll('.edit-status-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('status-incident-id').value = btn.dataset.id;
                document.getElementById('status-select').value = btn.dataset.status;
                statusModal.hidden = false;
            });
        });

        document.getElementById('close-status-modal')?.addEventListener('click', () => statusModal.hidden = true);
        document.getElementById('cancel-status-btn')?.addEventListener('click', () => statusModal.hidden = true);

        document.getElementById('save-status-btn')?.addEventListener('click', async () => {
            const id = document.getElementById('status-incident-id').value;
            const newStatus = document.getElementById('status-select').value;

            const res = await fetch(`../../server/api.php?resource=incident_reports&id=${id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: newStatus })
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') {
                alert(data.message || 'Failed to update status.');
                return;
            }
            window.location.reload();
        });
    </script>
    <script src="../../server/script.js?v=animations-v1"></script>
</body>

</html>


