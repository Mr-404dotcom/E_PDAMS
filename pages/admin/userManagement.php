<?php
// E-PDAMS User Management
// Allows system administrators to manage prefect and staff accounts.

require_once '../../server/db.php';
session_start();

$conn = getDbConnection();
$usersList = [];
$totalUsers = 0;
$activeUsers = 0;
$adminUsers = 0;
$dbError = null;

if ($conn) {
    $statRes = $conn->query("SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active_count,
        SUM(CASE WHEN role IN ('admin', 'administrator') THEN 1 ELSE 0 END) AS admin_count
        FROM users");
    if ($statRes && $stats = $statRes->fetch_assoc()) {
        $totalUsers = (int) ($stats['total'] ?? 0);
        $activeUsers = (int) ($stats['active_count'] ?? 0);
        $adminUsers = (int) ($stats['admin_count'] ?? 0);
    }

    $uRes = $conn->query("SELECT user_id, username, full_name, role, status, created_at FROM users ORDER BY user_id DESC");
    if ($uRes) {
        while ($u = $uRes->fetch_assoc()) {
            $usersList[] = $u;
        }
    }
    $conn->close();
} else {
    $dbError = 'Database connection failed.';
}

$todayLabel = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | E-PDAMS</title>
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
                <a class="nav-link" href="disciplinarySchedule.php"><i class="fa-solid fa-calendar-days nav-icon"></i><span>Disciplinary Schedule</span></a>
                <a class="nav-link" href="reformationProgram.php"><i class="fa-solid fa-rotate nav-icon"></i><span>Reformation Program</span></a>
                <a class="nav-link active" href="userManagement.php" aria-current="page"><i class="fa-solid fa-user-shield nav-icon"></i><span>User Accounts</span></a>
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
                <div class="breadcrumb"><span>Management</span><span aria-hidden="true">/</span><strong>User Accounts</strong></div>
                <div class="topbar-date"><?= htmlspecialchars($todayLabel); ?></div>
            </header>

            <section class="page-heading">
                <div>
                    <p class="eyebrow">Access Control</p>
                    <h1>User Management</h1>
                    <p class="heading-copy">Manage administrative personnel, prefect officers, and staff authorization accounts.</p>
                </div>
                <button class="primary-button" id="new-user-btn" type="button">
                    <i class="fa-solid fa-user-plus"></i> New User Account
                </button>
            </section>

            <?php if ($dbError): ?>
                <div class="clearance-flash error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div><strong>Database Connection Error</strong><span><?= htmlspecialchars($dbError); ?></span></div>
                </div>
            <?php endif; ?>

            <!-- Metrics -->
            <section class="metrics" aria-label="Users overview">
                <article class="metric-card accent-teal">
                    <span>Total Accounts</span>
                    <strong><?= number_format($totalUsers); ?></strong>
                    <small>System users on record</small>
                </article>
                <article class="metric-card accent-green">
                    <span>Active Users</span>
                    <strong><?= number_format($activeUsers); ?></strong>
                    <small>Permitted to authenticate</small>
                </article>
                <article class="metric-card accent-coral">
                    <span>Administrators</span>
                    <strong><?= number_format($adminUsers); ?></strong>
                    <small>Full administrative privilege</small>
                </article>
            </section>

            <!-- Users Table -->
            <section class="activity-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Account List</p>
                        <h2>Registered System Users</h2>
                    </div>
                </div>

                <div class="records-table-wrap">
                    <table class="records-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Full Name</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($usersList)): ?>
                                <?php foreach ($usersList as $u): ?>
                                    <?php
                                    $st = strtolower($u['status']);
                                    $badge = $st === 'active' ? 'approved' : 'archived';
                                    ?>
                                    <tr>
                                        <td>#<?= (int) $u['user_id']; ?></td>
                                        <td><strong><?= htmlspecialchars($u['full_name']); ?></strong></td>
                                        <td><code><?= htmlspecialchars($u['username']); ?></code></td>
                                        <td><span class="severity-label"><?= htmlspecialchars(ucfirst($u['role'])); ?></span></td>
                                        <td><span class="status-badge <?= $badge; ?>"><?= htmlspecialchars(ucfirst($u['status'])); ?></span></td>
                                        <td><?= htmlspecialchars(date('M j, Y', strtotime($u['created_at']))); ?></td>
                                        <td class="record-actions">
                                            <button class="table-action edit-user-btn" data-user='<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8'); ?>' type="button">Edit</button>
                                            <button class="table-action delete-record delete-user-btn" data-id="<?= (int) $u['user_id']; ?>" type="button">Delete</button>
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

    <!-- Modal to Add/Edit User -->
    <div class="record-modal" id="user-modal" hidden>
        <form class="record-form" id="user-form">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">System User</p>
                    <h2 id="user-modal-title">New Account</h2>
                </div>
                <button class="modal-close" id="close-user-modal" type="button" aria-label="Close">&times;</button>
            </div>

            <input type="hidden" id="user-id">

            <div class="form-grid">
                <label class="full-field">Full Name
                    <input id="user-fullname" required placeholder="e.g. John Doe">
                </label>

                <label>Username
                    <input id="user-username" required placeholder="e.g. jdoe">
                </label>

                <label>Role
                    <select id="user-role" required>
                        <option value="admin">Administrator</option>
                        <option value="prefect">Prefect Officer</option>
                        <option value="staff">Staff / Counselor</option>
                    </select>
                </label>

                <label class="full-field">Password <small id="pass-hint" style="color:var(--muted); font-weight:normal;">(Leave blank to keep existing password when editing)</small>
                    <input id="user-password" type="password" placeholder="At least 6 characters">
                </label>

                <label class="full-field">Account Status
                    <select id="user-status" required>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>
            </div>

            <p class="form-error" id="user-error" role="alert"></p>
            <div class="modal-actions">
                <button class="secondary-button" id="cancel-user" type="button">Cancel</button>
                <button class="primary-button" type="submit">Save User</button>
            </div>
        </form>
    </div>

    <script>
        const userModal = document.getElementById('user-modal');
        const userForm = document.getElementById('user-form');
        const userError = document.getElementById('user-error');
        const userIdInput = document.getElementById('user-id');
        const title = document.getElementById('user-modal-title');
        const passHint = document.getElementById('pass-hint');

        function openUserModal(user = null) {
            userForm.reset();
            userError.textContent = '';
            if (user) {
                title.textContent = 'Edit Account';
                userIdInput.value = user.user_id;
                document.getElementById('user-fullname').value = user.full_name;
                document.getElementById('user-username').value = user.username;
                document.getElementById('user-role').value = user.role;
                document.getElementById('user-status').value = user.status;
                passHint.style.display = 'inline';
                document.getElementById('user-password').required = false;
            } else {
                title.textContent = 'New Account';
                userIdInput.value = '';
                passHint.style.display = 'none';
                document.getElementById('user-password').required = true;
            }
            userModal.hidden = false;
        }

        document.getElementById('new-user-btn')?.addEventListener('click', () => openUserModal());
        document.getElementById('close-user-modal')?.addEventListener('click', () => userModal.hidden = true);
        document.getElementById('cancel-user')?.addEventListener('click', () => userModal.hidden = true);

        document.querySelectorAll('.edit-user-btn').forEach(btn => {
            btn.addEventListener('click', () => openUserModal(JSON.parse(btn.dataset.user)));
        });

        // Submit User
        userForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            userError.textContent = '';
            const editing = Boolean(userIdInput.value);

            const payload = {
                full_name: document.getElementById('user-fullname').value,
                username: document.getElementById('user-username').value,
                role: document.getElementById('user-role').value,
                status: document.getElementById('user-status').value,
            };

            const password = document.getElementById('user-password').value;
            if (password) {
                payload.password = password;
            }

            const url = editing 
                ? `../../server/api.php?resource=users&id=${userIdInput.value}`
                : `../../server/api.php?resource=users`;

            const res = await fetch(url, {
                method: editing ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || data.status !== 'success') {
                userError.textContent = data.message || 'Failed to save account.';
                return;
            }

            window.location.reload();
        });

        // Delete User
        document.querySelectorAll('.delete-user-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Are you sure you want to delete this user account?')) return;
                const res = await fetch(`../../server/api.php?resource=users&id=${btn.dataset.id}`, {
                    method: 'DELETE'
                });
                const data = await res.json();
                if (!res.ok || data.status !== 'success') {
                    alert(data.message || 'Failed to delete account.');
                    return;
                }
                window.location.reload();
            });
        });
    </script>
    <script src="../../server/script.js?v=animations-v1"></script>
</body>

</html>


