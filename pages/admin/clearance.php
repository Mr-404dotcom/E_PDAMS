<?php
require_once '../../server/db.php';
session_start();
$user = $_SESSION['edams_user'] ?? null;
$userRole = strtolower(trim((string) ($user['role'] ?? '')));
$userStatus = strtolower(trim((string) ($user['status'] ?? '')));
if (!is_array($user) || !in_array($userRole, ['admin', 'administrator'], true) || $userStatus !== 'active') {
    header('Location: ../../auth/auth_login.php');
    exit;
}
$userId = filter_var($user['id'] ?? null, FILTER_VALIDATE_INT);
if (!$userId || $userId < 1) {
    http_response_code(403);
    exit('Administrator access is required.');
}
if (empty($_SESSION['clearance_csrf'])) {
    $_SESSION['clearance_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string) $_SESSION['clearance_csrf'];

function clearanceEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clearancePrepare(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $statement = $conn->prepare($sql);
    if (!$statement) {
        throw new RuntimeException('Unable to prepare clearance query.');
    }

    if ($types !== '') {
        $bindValues = [$types];
        foreach (array_keys($params) as $index) {
            $bindValues[] = &$params[$index];
        }
        if (!call_user_func_array([$statement, 'bind_param'], $bindValues)) {
            $statement->close();
            throw new RuntimeException('Unable to bind clearance query.');
        }
    }

    if (!$statement->execute()) {
        $statement->close();
        throw new RuntimeException('Unable to execute clearance query.');
    }

    return $statement;
}

function clearanceParseDate(string $value, bool $allowEmpty = false)
{
    if ($value === '' && $allowEmpty) {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : false;
}

function clearanceQueueParentNotification(mysqli $conn, int $studentId, string $recipient, string $message): void
{
    if (trim($recipient) === '') {
        return;
    }

    $notificationType = 'Clearance Update';
    $notificationStatus = 'Pending';
    $notification = clearancePrepare(
        $conn,
        'INSERT INTO parent_notification (student_id, notification_type, recipient, message, status) VALUES (?, ?, ?, ?, ?)',
        'issss',
        [$studentId, $notificationType, $recipient, $message, $notificationStatus]
    );
    $notification->close();
}

function clearanceRedirect(string $query = ''): void
{
    header('Location: clearance.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

$conn = getDbConnection();
$dbError = null;
$flash = $_SESSION['clearance_flash'] ?? null;
unset($_SESSION['clearance_flash']);
$today = date('Y-m-d');

if ($conn) {
    $conn->set_charset('utf8mb4');
} else {
    $dbError = 'Database connection failed. Start MySQL and refresh the page.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$conn) {
        $_SESSION['clearance_flash'] = ['type' => 'error', 'title' => 'Unable to update clearance', 'message' => $dbError];
        clearanceRedirect();
    }

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $submittedToken)) {
        $_SESSION['clearance_flash'] = ['type' => 'error', 'title' => 'Request expired', 'message' => 'Refresh the page and try again.'];
        clearanceRedirect();
    }

    $action = trim((string) ($_POST['action'] ?? ''));
    $returnStudentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
    $transactionStarted = false;

    try {
        if ($action === 'create' || $action === 'edit') {
            $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
            $holdId = filter_var($_POST['hold_id'] ?? null, FILTER_VALIDATE_INT);
            $reason = trim((string) ($_POST['reason'] ?? ''));
            $violationId = filter_var($_POST['violation_id'] ?? null, FILTER_VALIDATE_INT);
            $violationId = $violationId && $violationId > 0 ? (int) $violationId : null;
            $holdDate = clearanceParseDate(trim((string) ($_POST['hold_date'] ?? '')));
            $expectedDate = clearanceParseDate(trim((string) ($_POST['expected_resolution_date'] ?? '')), true);
            $notes = trim((string) ($_POST['notes'] ?? ''));

            if (!$studentId || $studentId < 1 || $reason === '' || strlen($reason) > 5000 || $holdDate === false || $holdDate > $today || $expectedDate === false || strlen($notes) > 5000) {
                throw new DomainException('Enter a valid student, reason, and hold date. The expected date cannot be invalid.');
            }
            if ($expectedDate !== null && $expectedDate < $holdDate) {
                throw new DomainException('Expected resolution date cannot be earlier than the hold date.');
            }

            $conn->begin_transaction();
            $transactionStarted = true;
            $studentStatement = clearancePrepare($conn, 'SELECT student_id, parent_email, CONCAT(first_name, \' \', COALESCE(middle_name, \'\'), \' \', last_name) AS full_name FROM students WHERE student_id = ? FOR UPDATE', 'i', [(int) $studentId]);
            $student = $studentStatement->get_result()->fetch_assoc();
            $studentStatement->close();
            if (!$student) {
                throw new DomainException('The selected student could not be found.');
            }

            if ($violationId !== null) {
                $violationStatement = clearancePrepare($conn, 'SELECT infraction_id FROM infraction_logging WHERE infraction_id = ? AND student_id = ? LIMIT 1', 'ii', [$violationId, (int) $studentId]);
                $violation = $violationStatement->get_result()->fetch_assoc();
                $violationStatement->close();
                if (!$violation) {
                    throw new DomainException('Select a violation belonging to this student.');
                }
            }

            if ($action === 'create') {
                $activeStatement = clearancePrepare($conn, "SELECT hold_id FROM clearance_hold WHERE student_id = ? AND LOWER(status) IN ('active', 'on_hold') LIMIT 1 FOR UPDATE", 'i', [(int) $studentId]);
                $activeHold = $activeStatement->get_result()->fetch_assoc();
                $activeStatement->close();
                if ($activeHold) {
                    throw new DomainException('This student already has an active clearance hold.');
                }

                $insert = clearancePrepare(
                    $conn,
                    "INSERT INTO clearance_hold (student_id, violation_id, reason, placed_by, hold_date, expected_resolution_date, status, notes) VALUES (?, ?, ?, ?, ?, ?, 'ON_HOLD', ?)",
                    'iisisss',
                    [(int) $studentId, $violationId, $reason, (int) $userId, $holdDate, $expectedDate, $notes]
                );
                $insert->close();
                clearanceQueueParentNotification($conn, (int) $studentId, (string) ($student['parent_email'] ?? ''), 'Clearance has been placed on hold for ' . trim((string) $student['full_name']) . '.');
                $conn->commit();
                $transactionStarted = false;
                $_SESSION['clearance_flash'] = [
                    'type' => 'success',
                    'title' => 'Clearance Hold Created',
                    'message' => 'Clearance has been placed on hold for ' . trim((string) $student['full_name']) . '.',
                ];
            } else {
                if (!$holdId || $holdId < 1) {
                    throw new DomainException('The hold to edit could not be found.');
                }
                $activeStatement = clearancePrepare($conn, "SELECT hold_id FROM clearance_hold WHERE hold_id = ? AND student_id = ? AND LOWER(status) IN ('active', 'on_hold') FOR UPDATE", 'ii', [(int) $holdId, (int) $studentId]);
                $activeHold = $activeStatement->get_result()->fetch_assoc();
                $activeStatement->close();
                if (!$activeHold) {
                    throw new DomainException('Only an active hold can be edited.');
                }
                $update = clearancePrepare(
                    $conn,
                    'UPDATE clearance_hold SET violation_id = ?, reason = ?, hold_date = ?, expected_resolution_date = ?, notes = ? WHERE hold_id = ? AND student_id = ?',
                    'issssii',
                    [$violationId, $reason, $holdDate, $expectedDate, $notes, (int) $holdId, (int) $studentId]
                );
                $update->close();
                $conn->commit();
                $transactionStarted = false;
                $_SESSION['clearance_flash'] = ['type' => 'success', 'title' => 'Clearance Hold Updated', 'message' => 'The active hold details have been updated.'];
            }

            clearanceRedirect('student_id=' . (int) $studentId);
        }

        if ($action === 'resolve') {
            $holdId = filter_var($_POST['hold_id'] ?? null, FILTER_VALIDATE_INT);
            $resolutionType = trim((string) ($_POST['resolution_type'] ?? ''));
            $resolutionNotes = trim((string) ($_POST['resolution_notes'] ?? ''));
            $allowedResolutionTypes = ['Violation Resolved', 'Sanction Completed', 'Administrative Approval', 'Hold Released', 'Other'];
            if (!$holdId || $holdId < 1 || !in_array($resolutionType, $allowedResolutionTypes, true) || strlen($resolutionNotes) > 5000) {
                throw new DomainException('Choose a valid resolution type and enter notes under 5,000 characters.');
            }

            $conn->begin_transaction();
            $transactionStarted = true;
            $holdStatement = clearancePrepare($conn, "SELECT h.student_id, s.parent_email, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS full_name FROM clearance_hold h INNER JOIN students s ON s.student_id = h.student_id WHERE h.hold_id = ? AND LOWER(h.status) IN ('active', 'on_hold') FOR UPDATE", 'i', [(int) $holdId]);
            $hold = $holdStatement->get_result()->fetch_assoc();
            $holdStatement->close();
            if (!$hold) {
                throw new DomainException('This hold is no longer active. Refresh the page and try again.');
            }
            $update = clearancePrepare($conn, "UPDATE clearance_hold SET status = 'RESOLVED', resolution_date = CURDATE(), resolution_type = ?, resolution_notes = ?, resolved_by = ? WHERE hold_id = ? AND LOWER(status) IN ('active', 'on_hold')", 'ssii', [$resolutionType, $resolutionNotes, (int) $userId, (int) $holdId]);
            $update->close();
            clearanceQueueParentNotification($conn, (int) $hold['student_id'], (string) ($hold['parent_email'] ?? ''), 'Clearance status for ' . trim((string) $hold['full_name']) . ' has been updated.');
            $conn->commit();
            $transactionStarted = false;
            $_SESSION['clearance_flash'] = [
                'type' => 'success',
                'title' => 'Clearance Hold Resolved',
                'message' => 'Clearance status for ' . trim((string) $hold['full_name']) . ' has been updated.',
            ];
            clearanceRedirect('student_id=' . (int) $hold['student_id']);
        }

        if ($action === 'release') {
            $holdId = filter_var($_POST['hold_id'] ?? null, FILTER_VALIDATE_INT);
            $releaseReason = trim((string) ($_POST['release_reason'] ?? ''));
            $releaseNotes = trim((string) ($_POST['release_notes'] ?? ''));
            if (!$holdId || $holdId < 1 || $releaseReason === '' || strlen($releaseReason) > 3000 || $releaseNotes === '' || strlen($releaseNotes) > 5000) {
                throw new DomainException('A release reason and notes are required.');
            }

            $conn->begin_transaction();
            $transactionStarted = true;
            $holdStatement = clearancePrepare($conn, "SELECT h.student_id, s.parent_email, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS full_name FROM clearance_hold h INNER JOIN students s ON s.student_id = h.student_id WHERE h.hold_id = ? AND LOWER(h.status) IN ('active', 'on_hold') FOR UPDATE", 'i', [(int) $holdId]);
            $hold = $holdStatement->get_result()->fetch_assoc();
            $holdStatement->close();
            if (!$hold) {
                throw new DomainException('This hold is no longer active. Refresh the page and try again.');
            }
            $update = clearancePrepare($conn, "UPDATE clearance_hold SET status = 'RELEASED', release_date = CURDATE(), released_date = CURDATE(), release_reason = ?, release_notes = ?, released_by = ? WHERE hold_id = ? AND LOWER(status) IN ('active', 'on_hold')", 'ssii', [$releaseReason, $releaseNotes, (int) $userId, (int) $holdId]);
            $update->close();
            clearanceQueueParentNotification($conn, (int) $hold['student_id'], (string) ($hold['parent_email'] ?? ''), 'Clearance hold for ' . trim((string) $hold['full_name']) . ' has been released.');
            $conn->commit();
            $transactionStarted = false;
            $_SESSION['clearance_flash'] = [
                'type' => 'success',
                'title' => 'Clearance Hold Released',
                'message' => 'The hold for ' . trim((string) $hold['full_name']) . ' has been released.',
            ];
            clearanceRedirect('student_id=' . (int) $hold['student_id']);
        }

        throw new DomainException('Unknown clearance action.');
    } catch (DomainException $exception) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        $_SESSION['clearance_flash'] = ['type' => 'error', 'title' => 'Unable to update clearance', 'message' => $exception->getMessage()];
        clearanceRedirect($returnStudentId ? 'student_id=' . (int) $returnStudentId : '');
    } catch (Throwable $exception) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        error_log('Clearance action failed: ' . $exception->getMessage());
        $_SESSION['clearance_flash'] = ['type' => 'error', 'title' => 'Unable to update clearance', 'message' => 'The request could not be completed. Please refresh and try again.'];
        clearanceRedirect($returnStudentId ? 'student_id=' . (int) $returnStudentId : '');
    }
}

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = strtoupper(trim((string) ($_GET['status'] ?? '')));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'hold_newest');
$requestedPage = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$page = $requestedPage && $requestedPage > 0 ? (int) $requestedPage : 1;
$perPage = 15;
$studentId = filter_var($_GET['student_id'] ?? null, FILTER_VALIDATE_INT);
$studentId = $studentId && $studentId > 0 ? (int) $studentId : null;
$sortOptions = [
    'hold_newest' => 'COALESCE(h.hold_date, s.created_at) DESC, s.student_id DESC',
    'hold_oldest' => 'COALESCE(h.hold_date, s.created_at) ASC, s.student_id ASC',
    'name_asc' => 's.last_name ASC, s.first_name ASC, s.student_id ASC',
    'name_desc' => 's.last_name DESC, s.first_name DESC, s.student_id DESC',
];
if (!isset($sortOptions[$sort])) {
    $sort = 'hold_newest';
}
$validStatuses = ['CLEARED', 'ON_HOLD', 'RELEASED'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}
$dateFromValid = $dateFrom === '' ? null : clearanceParseDate($dateFrom);
$dateToValid = $dateTo === '' ? null : clearanceParseDate($dateTo);
if ($dateFromValid === false || $dateToValid === false) {
    $flash = ['type' => 'error', 'title' => 'Invalid date filter', 'message' => 'Choose valid dates for the clearance list.'];
    $dateFrom = '';
    $dateTo = '';
    $dateFromValid = null;
    $dateToValid = null;
}

$totalStudents = 0;
$clearedStudents = 0;
$studentsOnHold = 0;
$resolvedHolds = 0;
$studentOptions = [];
$violationOptions = [];
$clearanceRows = [];
$totalRows = 0;
$totalPages = 1;
$selectedStudent = null;
$studentHistory = [];
$editHold = null;
$queryError = null;
$clearanceStatusExpression = "CASE WHEN h.hold_id IS NULL THEN 'CLEARED' WHEN LOWER(h.status) IN ('active', 'on_hold') THEN 'ON_HOLD' WHEN LOWER(h.status) = 'released' THEN 'RELEASED' ELSE 'CLEARED' END";
$fromSql = " FROM students s
    LEFT JOIN clearance_hold h ON h.hold_id = (
        SELECT latest.hold_id FROM clearance_hold latest
        WHERE latest.student_id = s.student_id
        ORDER BY latest.hold_date DESC, latest.hold_id DESC LIMIT 1
    )
    LEFT JOIN infraction_logging i ON i.infraction_id = h.violation_id
    LEFT JOIN violation_category vc ON vc.category_id = i.category_id
    LEFT JOIN sanctions san ON san.sanction_id = i.sanction_id
    LEFT JOIN incident_report ir ON ir.incident_id = h.incident_id
    LEFT JOIN infraction_logging current_i ON current_i.infraction_id = (
        SELECT recent.infraction_id FROM infraction_logging recent
        WHERE recent.student_id = s.student_id
            AND LOWER(COALESCE(recent.status, '')) NOT IN ('resolved', 'dismissed')
        ORDER BY recent.date_time DESC, recent.infraction_id DESC LIMIT 1
    )
    LEFT JOIN violation_category current_vc ON current_vc.category_id = current_i.category_id
    LEFT JOIN sanctions current_san ON current_san.sanction_id = current_i.sanction_id";

if ($conn) {
    try {
        $countStatement = clearancePrepare($conn, 'SELECT COUNT(*) AS total FROM students');
        $totalStudents = (int) ($countStatement->get_result()->fetch_assoc()['total'] ?? 0);
        $countStatement->close();

        $countStatement = clearancePrepare($conn, "SELECT COUNT(DISTINCT student_id) AS total FROM clearance_hold WHERE LOWER(status) IN ('active', 'on_hold')");
        $studentsOnHold = (int) ($countStatement->get_result()->fetch_assoc()['total'] ?? 0);
        $countStatement->close();

        $countStatement = clearancePrepare($conn, "SELECT COUNT(*) AS total FROM clearance_hold WHERE LOWER(status) = 'resolved'");
        $resolvedHolds = (int) ($countStatement->get_result()->fetch_assoc()['total'] ?? 0);
        $countStatement->close();
        $clearedStudents = max(0, $totalStudents - $studentsOnHold);

        $studentsStatement = clearancePrepare($conn, "SELECT student_id, student_number, CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name) AS full_name, grade_level, section FROM students ORDER BY last_name, first_name");
        $studentsResult = $studentsStatement->get_result();
        while ($studentRow = $studentsResult->fetch_assoc()) {
            $studentOptions[] = $studentRow;
        }
        $studentsStatement->close();

        $violationsStatement = clearancePrepare(
            $conn,
            "SELECT i.infraction_id, i.student_id, s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name, COALESCE(vc.category_name, i.offense_committed) AS violation_name, i.date_time, i.status, san.sanction_name
             FROM infraction_logging i
             INNER JOIN students s ON s.student_id = i.student_id
             LEFT JOIN violation_category vc ON vc.category_id = i.category_id
             LEFT JOIN sanctions san ON san.sanction_id = i.sanction_id
             ORDER BY i.date_time DESC LIMIT 1000"
        );
        $violationsResult = $violationsStatement->get_result();
        while ($violationRow = $violationsResult->fetch_assoc()) {
            $violationOptions[] = $violationRow;
        }
        $violationsStatement->close();

        $editHoldId = filter_var($_GET['edit_hold'] ?? null, FILTER_VALIDATE_INT);
        if ($editHoldId && $editHoldId > 0) {
            $editStatement = clearancePrepare(
                $conn,
                "SELECT h.hold_id, h.student_id, h.violation_id, h.reason, h.hold_date, h.expected_resolution_date, h.notes
                 FROM clearance_hold h WHERE h.hold_id = ? AND LOWER(h.status) IN ('active', 'on_hold') LIMIT 1",
                'i',
                [(int) $editHoldId]
            );
            $editHold = $editStatement->get_result()->fetch_assoc() ?: null;
            $editStatement->close();
        }

        if ($studentId !== null) {
            $profileStatement = clearancePrepare(
                $conn,
                "SELECT s.student_id, s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS full_name, s.grade_level, s.section,
                    h.hold_id, h.reason, h.hold_date, h.expected_resolution_date, h.status AS hold_status, h.resolution_date, h.resolution_type, h.resolution_notes,
                    h.release_date, h.released_date, h.release_reason, h.release_notes, h.notes,
                    COALESCE(vc.category_name, ir.violation) AS related_violation, i.date_time AS violation_date, i.status AS violation_status, san.sanction_name,
                    placer.full_name AS placed_by_name, resolver.full_name AS resolved_by_name, releaser.full_name AS released_by_name,
                    $clearanceStatusExpression AS clearance_status
                 FROM students s
                 LEFT JOIN clearance_hold h ON h.hold_id = (SELECT latest.hold_id FROM clearance_hold latest WHERE latest.student_id = s.student_id ORDER BY latest.hold_date DESC, latest.hold_id DESC LIMIT 1)
                 LEFT JOIN infraction_logging i ON i.infraction_id = h.violation_id
                 LEFT JOIN violation_category vc ON vc.category_id = i.category_id
                 LEFT JOIN sanctions san ON san.sanction_id = i.sanction_id
                 LEFT JOIN incident_report ir ON ir.incident_id = h.incident_id
                 LEFT JOIN users placer ON placer.user_id = h.placed_by
                 LEFT JOIN users resolver ON resolver.user_id = h.resolved_by
                 LEFT JOIN users releaser ON releaser.user_id = h.released_by
                 WHERE s.student_id = ? LIMIT 1",
                'i',
                [$studentId]
            );
            $selectedStudent = $profileStatement->get_result()->fetch_assoc() ?: null;
            $profileStatement->close();

            if ($selectedStudent) {
                $historyStatement = clearancePrepare(
                    $conn,
                    "SELECT h.hold_id, h.reason, h.hold_date, h.resolution_date, h.resolution_type, h.resolution_notes,
                        h.release_date, h.released_date, h.release_reason, h.release_notes, h.status,
                        COALESCE(vc.category_name, ir.violation) AS related_violation,
                        placer.full_name AS placed_by_name, resolver.full_name AS resolved_by_name, releaser.full_name AS released_by_name
                     FROM clearance_hold h
                     LEFT JOIN infraction_logging i ON i.infraction_id = h.violation_id
                     LEFT JOIN violation_category vc ON vc.category_id = i.category_id
                     LEFT JOIN incident_report ir ON ir.incident_id = h.incident_id
                     LEFT JOIN users placer ON placer.user_id = h.placed_by
                     LEFT JOIN users resolver ON resolver.user_id = h.resolved_by
                     LEFT JOIN users releaser ON releaser.user_id = h.released_by
                     WHERE h.student_id = ? ORDER BY h.hold_date DESC, h.hold_id DESC",
                    'i',
                    [$studentId]
                );
                $historyResult = $historyStatement->get_result();
                while ($historyRow = $historyResult->fetch_assoc()) {
                    $studentHistory[] = $historyRow;
                }
                $historyStatement->close();
            }
        }

        $whereParts = [];
        $filterTypes = '';
        $filterParams = [];
        if ($search !== '') {
            $whereParts[] = "(s.student_number LIKE ? OR CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) LIKE ? OR COALESCE(h.reason, '') LIKE ? OR COALESCE(vc.category_name, ir.violation, current_vc.category_name, current_i.offense_committed, '') LIKE ?)";
            $searchTerm = '%' . $search . '%';
            array_push($filterParams, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
            $filterTypes .= 'ssss';
        }
        if ($statusFilter !== '') {
            $whereParts[] = $clearanceStatusExpression . ' = ?';
            $filterParams[] = $statusFilter;
            $filterTypes .= 's';
        }
        if ($dateFromValid !== null) {
            $whereParts[] = 'h.hold_date >= ?';
            $filterParams[] = $dateFromValid;
            $filterTypes .= 's';
        }
        if ($dateToValid !== null) {
            $whereParts[] = 'h.hold_date <= ?';
            $filterParams[] = $dateToValid;
            $filterTypes .= 's';
        }
        $whereSql = $whereParts ? ' WHERE ' . implode(' AND ', $whereParts) : '';

        $countSql = 'SELECT COUNT(*) AS total' . $fromSql . $whereSql;
        $countStatement = clearancePrepare($conn, $countSql, $filterTypes, $filterParams);
        $totalRows = (int) ($countStatement->get_result()->fetch_assoc()['total'] ?? 0);
        $countStatement->close();
        $totalPages = max(1, (int) ceil($totalRows / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $rowsSql = "SELECT s.student_id, s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS full_name,
                s.grade_level, s.section, h.hold_id, h.reason AS hold_reason, h.hold_date, h.expected_resolution_date, h.status AS hold_status,
                COALESCE(vc.category_name, ir.violation, current_vc.category_name, current_i.offense_committed) AS active_violation,
                COALESCE(i.date_time, current_i.date_time) AS violation_date, COALESCE(i.status, current_i.status) AS violation_status,
                COALESCE(san.sanction_name, current_san.sanction_name) AS sanction_name,
                $clearanceStatusExpression AS clearance_status" . $fromSql . $whereSql . ' ORDER BY ' . $sortOptions[$sort] . ' LIMIT ? OFFSET ?';
        $rowTypes = $filterTypes . 'ii';
        $rowParams = $filterParams;
        $rowParams[] = $perPage;
        $rowParams[] = $offset;
        $rowsStatement = clearancePrepare($conn, $rowsSql, $rowTypes, $rowParams);
        $rowsResult = $rowsStatement->get_result();
        while ($clearanceRow = $rowsResult->fetch_assoc()) {
            $clearanceRows[] = $clearanceRow;
        }
        $rowsStatement->close();
    } catch (Throwable $exception) {
        error_log('Clearance page query failed: ' . $exception->getMessage());
        $queryError = 'Clearance data could not be loaded. Confirm the clearance database upgrade has been applied.';
    }
    $conn->close();
}

$todayLabel = date('l, F j, Y');
$pageQuery = $_GET;
unset($pageQuery['page']);
$pageUrl = static function (int $pageNumber) use ($pageQuery): string {
    return 'clearance.php?' . http_build_query(array_merge($pageQuery, ['page' => $pageNumber]));
};
$activeProfileHold = $selectedStudent && in_array(strtolower((string) ($selectedStudent['hold_status'] ?? '')), ['active', 'on_hold'], true);
$profileStatus = $selectedStudent['clearance_status'] ?? 'CLEARED';
$editStudentId = (int) ($editHold['student_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Clearance | E-PDAMS</title>
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
                <a class="nav-link" href="ParentNotificationTool.php"><i class="fa-solid fa-bell nav-icon"></i><span>Notifications</span></a>
                <p class="nav-label">Discipline</p>
                <a class="nav-link" href="violationSetup.php"><i class="fa-solid fa-triangle-exclamation nav-icon"></i><span>Violation Records</span></a>
                <a class="nav-link" href="sanction.php"><i class="fa-solid fa-gavel nav-icon"></i><span>Sanctions</span></a>
                <a class="nav-link" href="IncidentReports.php"><i class="fa-solid fa-file-circle-exclamation nav-icon"></i><span>Incident Reports</span></a>
                <a class="nav-link active" href="clearance.php" aria-current="page"><i class="fa-solid fa-file-circle-check nav-icon"></i><span>Clearance</span></a>
                <p class="nav-label">Management</p>
                <a class="nav-link" href="behaviorTracking.php"><i class="fa-solid fa-user-check nav-icon"></i><span>Behavior Tracking</span></a>
                <a class="nav-link" href="disciplinarySchedule.php"><i class="fa-solid fa-calendar-days nav-icon"></i><span>Disciplinary Schedule</span></a>
                <a class="nav-link" href="reformationProgram.php"><i class="fa-solid fa-rotate nav-icon"></i><span>Reformation Program</span></a>
            </nav>
            <div class="sidebar-footer">
                <div class="profile-chip">
                    <div class="profile-avatar"><?= clearanceEscape(strtoupper(substr((string) ($user['full_name'] ?? 'AD'), 0, 2))); ?></div>
                    <div><strong><?= clearanceEscape($user['full_name'] ?? 'Administrator'); ?></strong><span><?= clearanceEscape(ucfirst((string) ($user['role'] ?? 'Administrator'))); ?></span></div>
                </div>
                <a class="logout-link" href="../../auth/auth_logout.php"><i class="fa-solid fa-right-from-bracket quit"></i><span>Log out</span></a>
            </div>
        </aside>

        <main class="main-content">
            <header class="topbar">
                <button class="menu-toggle" type="button" aria-controls="sidebar" aria-expanded="false"><i class="fa-solid fa-bars" aria-hidden="true"></i><span class="visually-hidden">Toggle navigation</span></button>
                <div class="breadcrumb"><span>Workspace</span><span aria-hidden="true">/</span><strong>Clearance</strong></div>
                <div class="topbar-date"><?= clearanceEscape($todayLabel); ?></div>
            </header>

            <section class="page-heading clearance-heading">
                <div>
                    <p class="eyebrow">Student services</p>
                    <h1>Student clearance</h1>
                    <p class="heading-copy">Manage disciplinary holds, review resolutions, and preserve a complete clearance history.</p>
                </div>
                <button class="primary-button" type="button" data-open-modal="hold-modal"><i class="fa-solid fa-plus" aria-hidden="true"></i> Place Clearance Hold</button>
            </section>

            <?php if ($flash): ?>
                <div class="clearance-flash <?= clearanceEscape($flash['type'] ?? 'success'); ?>" role="status" aria-live="polite">
                    <i class="fa-solid <?= ($flash['type'] ?? '') === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'; ?>" aria-hidden="true"></i>
                    <div><strong><?= clearanceEscape($flash['title'] ?? 'Clearance updated'); ?></strong><span><?= clearanceEscape($flash['message'] ?? ''); ?></span></div>
                    <button type="button" class="flash-dismiss" aria-label="Dismiss message">&times;</button>
                </div>
            <?php endif; ?>

            <?php if ($dbError || $queryError): ?>
                <div class="clearance-flash error" role="alert"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div><strong>Unable to load clearance records</strong><span><?= clearanceEscape($dbError ?: $queryError); ?></span></div>
                </div>
            <?php else: ?>
                <section class="clearance-summary" aria-label="Clearance summary">
                    <article class="clearance-stat blue"><span class="clearance-stat-icon"><i class="fa-solid fa-user-group" aria-hidden="true"></i></span>
                        <div><span>Total Students</span><strong><?= number_format($totalStudents); ?></strong></div>
                    </article>
                    <article class="clearance-stat green"><span class="clearance-stat-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
                        <div><span>Cleared Students</span><strong><?= number_format($clearedStudents); ?></strong></div>
                    </article>
                    <article class="clearance-stat red"><span class="clearance-stat-icon"><i class="fa-solid fa-file-circle-exclamation" aria-hidden="true"></i></span>
                        <div><span>Students on Hold</span><strong><?= number_format($studentsOnHold); ?></strong></div>
                    </article>
                    <article class="clearance-stat violet"><span class="clearance-stat-icon"><i class="fa-solid fa-rotate" aria-hidden="true"></i></span>
                        <div><span>Resolved Holds</span><strong><?= number_format($resolvedHolds); ?></strong></div>
                    </article>
                </section>

                <?php if ($selectedStudent): ?>
                    <section class="clearance-profile activity-panel" aria-labelledby="student-profile-title">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Student information</p>
                                <h2 id="student-profile-title"><?= clearanceEscape($selectedStudent['full_name']); ?></h2>
                            </div>
                            <span class="status-badge clearance-status <?= clearanceEscape(strtolower($profileStatus)); ?>"><?= clearanceEscape(str_replace('_', ' ', $profileStatus)); ?></span>
                        </div>
                        <div class="student-details-grid">
                            <div><span>Student Number</span><strong><?= clearanceEscape($selectedStudent['student_number']); ?></strong></div>
                            <div><span>Year Level</span><strong><?= clearanceEscape($selectedStudent['grade_level'] ?: 'Not provided'); ?></strong></div>
                            <div><span>Section</span><strong><?= clearanceEscape($selectedStudent['section'] ?: 'Not provided'); ?></strong></div>
                            <div><span>Clearance Status</span><strong><?= clearanceEscape(str_replace('_', ' ', $profileStatus)); ?></strong></div>
                        </div>
                        <?php if ($selectedStudent['hold_id']): ?>
                            <div class="hold-detail-grid">
                                <div><span>Hold Reason</span>
                                    <p><?= nl2br(clearanceEscape($selectedStudent['reason'])); ?></p>
                                </div>
                                <div><span>Date Placed</span>
                                    <p><?= clearanceEscape(date('F j, Y', strtotime($selectedStudent['hold_date']))); ?><?php if ($selectedStudent['placed_by_name']): ?> &bull; <?= clearanceEscape($selectedStudent['placed_by_name']); ?><?php endif; ?></p>
                                </div>
                                <div><span>Related Violation</span>
                                    <p><?= clearanceEscape($selectedStudent['related_violation'] ?: 'Not linked'); ?><?php if ($selectedStudent['violation_date']): ?> &bull; <?= clearanceEscape(date('M j, Y', strtotime($selectedStudent['violation_date']))); ?><?php endif; ?></p>
                                </div>
                                <div><span>Sanction</span>
                                    <p><?= clearanceEscape($selectedStudent['sanction_name'] ?: 'Not recorded'); ?></p>
                                </div>
                                <?php if ($selectedStudent['expected_resolution_date']): ?><div><span>Expected Resolution</span>
                                        <p><?= clearanceEscape(date('F j, Y', strtotime($selectedStudent['expected_resolution_date']))); ?></p>
                                    </div><?php endif; ?>
                                <?php if ($selectedStudent['notes']): ?><div class="full-field"><span>Administrator Notes</span>
                                        <p><?= nl2br(clearanceEscape($selectedStudent['notes'])); ?></p>
                                    </div><?php endif; ?>
                            </div>
                        <?php elseif (strtolower((string) $selectedStudent['hold_status']) === 'resolved'): ?>
                            <p class="profile-resolution"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Hold resolved <?= $selectedStudent['resolution_date'] ? 'on ' . clearanceEscape(date('F j, Y', strtotime($selectedStudent['resolution_date']))) : ''; ?><?php if ($selectedStudent['resolution_type']): ?> &bull; <?= clearanceEscape($selectedStudent['resolution_type']); ?><?php endif; ?></p>
                        <?php elseif (strtolower((string) $selectedStudent['hold_status']) === 'released'): ?>
                            <p class="profile-resolution"><i class="fa-solid fa-unlock" aria-hidden="true"></i> Hold released <?= ($selectedStudent['release_date'] ?: $selectedStudent['released_date']) ? 'on ' . clearanceEscape(date('F j, Y', strtotime($selectedStudent['release_date'] ?: $selectedStudent['released_date']))) : ''; ?><?php if ($selectedStudent['release_reason']): ?> &bull; <?= clearanceEscape($selectedStudent['release_reason']); ?><?php endif; ?></p>
                        <?php else: ?>
                            <p class="profile-resolution"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> No active clearance hold.</p>
                        <?php endif; ?>
                        <?php if ($activeProfileHold): ?>
                            <div class="profile-actions">
                                <a class="secondary-button" href="clearance.php?edit_hold=<?= (int) $selectedStudent['hold_id']; ?>&amp;student_id=<?= (int) $selectedStudent['student_id']; ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit Hold</a>
                                <button class="secondary-button" type="button" data-open-modal="resolve-modal" data-hold-id="<?= (int) $selectedStudent['hold_id']; ?>" data-student-name="<?= clearanceEscape($selectedStudent['full_name']); ?>"><i class="fa-solid fa-check" aria-hidden="true"></i> Resolve Hold</button>
                                <button class="secondary-button danger-outline" type="button" data-open-modal="release-modal" data-hold-id="<?= (int) $selectedStudent['hold_id']; ?>" data-student-name="<?= clearanceEscape($selectedStudent['full_name']); ?>"><i class="fa-solid fa-unlock" aria-hidden="true"></i> Release Hold</button>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="activity-panel clearance-history" aria-labelledby="clearance-history-title">
                        <div class="panel-heading">
                            <div>
                                <p class="eyebrow">Audit trail</p>
                                <h2 id="clearance-history-title">Clearance History</h2>
                            </div><span class="panel-count"><?= count($studentHistory); ?> entries</span>
                        </div>
                        <div class="records-table-wrap">
                            <table class="records-table clearance-history-table">
                                <thead>
                                    <tr>
                                        <th>Hold ID</th>
                                        <th>Reason</th>
                                        <th>Related Violation</th>
                                        <th>Date Placed</th>
                                        <th>Date Resolved</th>
                                        <th>Resolution</th>
                                        <th>Released By</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($studentHistory): ?>
                                        <?php foreach ($studentHistory as $history): ?>
                                            <?php $historyStatus = strtoupper((string) $history['status']); ?>
                                            <tr>
                                                <td>#<?= (int) $history['hold_id']; ?></td>
                                                <td><?= clearanceEscape($history['reason']); ?><?php if ($history['placed_by_name']): ?><small class="table-subtext">Placed by <?= clearanceEscape($history['placed_by_name']); ?></small><?php endif; ?></td>
                                                <td><?= $history['related_violation'] ? clearanceEscape($history['related_violation']) : '&mdash;'; ?></td>
                                                <td><?= clearanceEscape(date('M j, Y', strtotime($history['hold_date']))); ?></td>
                                                <td><?= $history['resolution_date'] ? clearanceEscape(date('M j, Y', strtotime($history['resolution_date']))) : (($history['release_date'] ?: $history['released_date']) ? clearanceEscape(date('M j, Y', strtotime($history['release_date'] ?: $history['released_date']))) : '&mdash;'); ?></td>
                                                <td><?= ($history['resolution_type'] ?: $history['release_reason']) ? clearanceEscape($history['resolution_type'] ?: $history['release_reason']) : '&mdash;'; ?><?php if ($history['resolution_notes'] ?: $history['release_notes']): ?><small class="table-subtext"><?= clearanceEscape($history['resolution_notes'] ?: $history['release_notes']); ?></small><?php endif; ?><?php if ($history['resolved_by_name']): ?><small class="table-subtext">Resolved by <?= clearanceEscape($history['resolved_by_name']); ?></small><?php endif; ?></td>
                                                <td><?= $history['released_by_name'] ? clearanceEscape($history['released_by_name']) : '&mdash;'; ?></td>
                                                <td><span class="status-badge clearance-status <?= clearanceEscape(strtolower($historyStatus)); ?>"><?= clearanceEscape(str_replace('_', ' ', $historyStatus)); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8">
                                                <div class="empty-state compact-empty-state">
                                                    <div class="empty-icon"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></div><strong>No clearance history</strong>
                                                    <p>Previous holds and resolutions will appear here.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                <?php elseif ($studentId !== null): ?>
                    <div class="activity-panel">
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fa-solid fa-user-slash" aria-hidden="true"></i></div><strong>Student not found</strong>
                            <p>The selected student record is unavailable.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <section class="activity-panel clearance-list-panel" aria-labelledby="clearance-list-title">
                    <div class="panel-heading clearance-list-heading">
                        <div>
                            <p class="eyebrow">Clearance records</p>
                            <h2 id="clearance-list-title">Student Clearance Status</h2>
                        </div>
                        <span class="panel-count"><?= number_format($totalRows); ?> students</span>
                    </div>
                    <form class="clearance-filters" method="get" action="clearance.php">
                        <label class="search-box"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" name="search" value="<?= clearanceEscape($search); ?>" placeholder="Search students or hold reasons" aria-label="Search clearance records"></label>
                        <select name="status" aria-label="Filter by clearance status">
                            <option value="">All statuses</option>
                            <option value="CLEARED" <?= $statusFilter === 'CLEARED' ? 'selected' : ''; ?>>Cleared</option>
                            <option value="ON_HOLD" <?= $statusFilter === 'ON_HOLD' ? 'selected' : ''; ?>>On hold</option>
                            <option value="RELEASED" <?= $statusFilter === 'RELEASED' ? 'selected' : ''; ?>>Released</option>
                        </select>
                        <label class="date-filter"><span>From</span><input type="date" name="date_from" value="<?= clearanceEscape($dateFrom); ?>" aria-label="Filter from date"></label>
                        <label class="date-filter"><span>To</span><input type="date" name="date_to" value="<?= clearanceEscape($dateTo); ?>" aria-label="Filter to date"></label>
                        <select name="sort" aria-label="Sort clearance records">
                            <option value="hold_newest" <?= $sort === 'hold_newest' ? 'selected' : ''; ?>>Newest hold</option>
                            <option value="hold_oldest" <?= $sort === 'hold_oldest' ? 'selected' : ''; ?>>Oldest hold</option>
                            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : ''; ?>>Student name A-Z</option>
                            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : ''; ?>>Student name Z-A</option>
                        </select>
                        <button class="primary-button small-button" type="submit">Apply</button>
                        <a class="filter-reset" href="clearance.php">Reset</a>
                    </form>

                    <?php if ($queryError): ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div><strong>Clearance table unavailable</strong>
                            <p><?= clearanceEscape($queryError); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="records-table-wrap">
                            <table class="records-table clearance-table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Student Number</th>
                                        <th>Clearance Status</th>
                                        <th>Active Violation</th>
                                        <th>Hold Reason</th>
                                        <th>Hold Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($clearanceRows): ?>
                                        <?php foreach ($clearanceRows as $row): ?>
                                            <?php $rowStatus = strtoupper((string) $row['clearance_status']); ?>
                                            <tr>
                                                <td><a class="student-name-link" href="clearance.php?student_id=<?= (int) $row['student_id']; ?>"><?= clearanceEscape($row['full_name']); ?></a><small class="table-subtext"><?= clearanceEscape($row['grade_level'] ?: 'Year level not set'); ?></small></td>
                                                <td><?= clearanceEscape($row['student_number']); ?></td>
                                                <td><span class="status-badge clearance-status <?= clearanceEscape(strtolower($rowStatus)); ?>"><?= clearanceEscape(str_replace('_', ' ', $rowStatus)); ?></span></td>
                                                <td><?= $row['active_violation'] ? clearanceEscape($row['active_violation']) : '&mdash;'; ?><?php if ($row['violation_status']): ?><small class="table-subtext"><?= clearanceEscape($row['violation_status']); ?><?= $row['sanction_name'] ? ' &bull; ' . clearanceEscape($row['sanction_name']) : ''; ?></small><?php endif; ?></td>
                                                <td><?= $row['hold_reason'] ? clearanceEscape($row['hold_reason']) : '&mdash;'; ?></td>
                                                <td><?= $row['hold_date'] ? clearanceEscape(date('M j, Y', strtotime($row['hold_date']))) : '&mdash;'; ?></td>
                                                <td class="clearance-row-actions">
                                                    <a class="table-action" href="clearance.php?student_id=<?= (int) $row['student_id']; ?>">View</a>
                                                    <?php if (in_array(strtolower((string) $row['hold_status']), ['active', 'on_hold'], true)): ?>
                                                        <a class="table-action" href="clearance.php?edit_hold=<?= (int) $row['hold_id']; ?>&amp;student_id=<?= (int) $row['student_id']; ?>" aria-label="Edit hold for <?= clearanceEscape($row['full_name']); ?>">Edit</a>
                                                        <button class="table-action" type="button" data-open-modal="resolve-modal" data-hold-id="<?= (int) $row['hold_id']; ?>" data-student-name="<?= clearanceEscape($row['full_name']); ?>">Resolve</button>
                                                        <button class="table-action delete-record" type="button" data-open-modal="release-modal" data-hold-id="<?= (int) $row['hold_id']; ?>" data-student-name="<?= clearanceEscape($row['full_name']); ?>">Release</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7">
                                                <div class="empty-state compact-empty-state">
                                                    <div class="empty-icon"><i class="fa-regular fa-folder-open" aria-hidden="true"></i></div><strong>No students match these filters</strong>
                                                    <p>Try changing your search or date range.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <nav class="clearance-pagination" aria-label="Clearance pagination">
                                <span>Page <?= $page; ?> of <?= $totalPages; ?></span>
                                <div><?php if ($page > 1): ?><a href="<?= clearanceEscape($pageUrl($page - 1)); ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a><?php endif; ?><?php if ($page < $totalPages): ?><a href="<?= clearanceEscape($pageUrl($page + 1)); ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a><?php endif; ?></div>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <div class="record-modal" id="hold-modal" hidden>
        <form class="record-form clearance-form" id="hold-form" method="post" action="clearance.php<?= $studentId ? '?student_id=' . (int) $studentId : ''; ?>">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Clearance administration</p>
                    <h2><?= $editHold ? 'Edit Active Hold' : 'Place Clearance Hold'; ?></h2>
                </div><button class="modal-close" type="button" data-close-modal aria-label="Close">&times;</button>
            </div>
            <input type="hidden" name="csrf_token" value="<?= clearanceEscape($csrfToken); ?>">
            <input type="hidden" name="action" value="<?= $editHold ? 'edit' : 'create'; ?>">
            <input type="hidden" name="hold_id" value="<?= (int) ($editHold['hold_id'] ?? 0); ?>">
            <div class="form-grid">
                <label class="full-field">Student
                    <select id="clearance-student" name="student_id" required <?= $editHold ? 'disabled' : ''; ?>>
                        <option value="">Select a student</option>
                        <?php foreach ($studentOptions as $option): ?>
                            <option value="<?= (int) $option['student_id']; ?>" data-number="<?= clearanceEscape($option['student_number']); ?>" data-grade="<?= clearanceEscape($option['grade_level'] ?: 'Year level not set'); ?>" <?= ((int) ($editHold['student_id'] ?? $studentId ?? 0) === (int) $option['student_id']) ? 'selected' : ''; ?>><?= clearanceEscape($option['full_name'] . ' - ' . $option['student_number']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($editHold): ?><input type="hidden" name="student_id" value="<?= (int) $editHold['student_id']; ?>"><?php endif; ?>
                    <small id="clearance-student-preview" class="field-hint"></small>
                </label>
                <label class="full-field">Reason for Clearance Hold<textarea name="reason" rows="3" maxlength="5000" required placeholder="Describe the unresolved disciplinary matter."><?= clearanceEscape($editHold['reason'] ?? ''); ?></textarea></label>
                <label class="full-field">Related Violation <span class="optional-label">Optional</span>
                    <select id="clearance-violation" name="violation_id">
                        <option value="">No related violation</option>
                        <?php foreach ($violationOptions as $option): ?>
                            <option value="<?= (int) $option['infraction_id']; ?>" data-student-id="<?= (int) $option['student_id']; ?>" data-sanction="<?= clearanceEscape($option['sanction_name'] ?? ''); ?>" <?= ((int) ($editHold['violation_id'] ?? 0) === (int) $option['infraction_id']) ? 'selected' : ''; ?>><?= clearanceEscape($option['student_number'] . ' - ' . $option['violation_name'] . ' (' . date('M j, Y', strtotime($option['date_time'])) . ' - ' . $option['status'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small id="clearance-sanction-preview" class="field-hint"></small>
                </label>
                <label>Hold Date<input type="date" name="hold_date" value="<?= clearanceEscape($editHold['hold_date'] ?? $today); ?>" max="<?= clearanceEscape($today); ?>" required></label>
                <label>Expected Resolution Date <span class="optional-label">Optional</span><input type="date" name="expected_resolution_date" min="<?= clearanceEscape($editHold['hold_date'] ?? $today); ?>" value="<?= clearanceEscape($editHold['expected_resolution_date'] ?? ''); ?>"></label>
                <label class="full-field">Additional Notes<textarea name="notes" rows="2" maxlength="5000" placeholder="Internal notes for the clearance record."><?= clearanceEscape($editHold['notes'] ?? ''); ?></textarea></label>
            </div>
            <p class="form-error" id="hold-form-error" role="alert"></p>
            <div class="modal-actions"><button class="secondary-button" type="button" data-close-modal>Cancel</button><button class="primary-button" type="submit"><?= $editHold ? 'Save Changes' : 'Place Hold'; ?></button></div>
        </form>
    </div>

    <div class="record-modal" id="resolve-modal" hidden>
        <form class="record-form clearance-form" method="post" action="clearance.php">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Update clearance</p>
                    <h2>Resolve Hold</h2>
                    <p class="modal-student" data-modal-student></p>
                </div><button class="modal-close" type="button" data-close-modal aria-label="Close">&times;</button>
            </div>
            <input type="hidden" name="csrf_token" value="<?= clearanceEscape($csrfToken); ?>"><input type="hidden" name="action" value="resolve"><input type="hidden" name="hold_id" data-modal-hold-id>
            <div class="form-grid">
                <label class="full-field">Resolution Type<select name="resolution_type" required>
                        <option value="">Select resolution</option>
                        <option>Violation Resolved</option>
                        <option>Sanction Completed</option>
                        <option>Administrative Approval</option>
                        <option>Hold Released</option>
                        <option>Other</option>
                    </select></label>
                <label class="full-field">Resolution Notes<textarea name="resolution_notes" rows="4" maxlength="5000" placeholder="Record how the issue was resolved."></textarea></label>
                <label>Resolution Date<input type="date" value="<?= clearanceEscape($today); ?>" readonly></label>
            </div>
            <div class="modal-actions"><button class="secondary-button" type="button" data-close-modal>Cancel</button><button class="primary-button" type="submit">Resolve Hold</button></div>
        </form>
    </div>

    <div class="record-modal" id="release-modal" hidden>
        <form class="record-form clearance-form" method="post" action="clearance.php">
            <div class="modal-heading">
                <div>
                    <p class="eyebrow">Administrative release</p>
                    <h2>Release Clearance Hold</h2>
                    <p class="modal-student" data-modal-student></p>
                </div><button class="modal-close" type="button" data-close-modal aria-label="Close">&times;</button>
            </div>
            <input type="hidden" name="csrf_token" value="<?= clearanceEscape($csrfToken); ?>"><input type="hidden" name="action" value="release"><input type="hidden" name="hold_id" data-modal-hold-id>
            <div class="form-grid">
                <label class="full-field">Release Reason<textarea name="release_reason" rows="2" maxlength="3000" required placeholder="Why is the hold being manually released?"></textarea></label>
                <label class="full-field">Release Notes<textarea name="release_notes" rows="3" maxlength="5000" required placeholder="Add the administrative context for this release."></textarea></label>
                <label>Release Date<input type="date" value="<?= clearanceEscape($today); ?>" readonly></label>
                <label>Released By<input type="text" value="<?= clearanceEscape($user['full_name'] ?? 'Administrator'); ?>" readonly></label>
            </div>
            <div class="modal-actions"><button class="secondary-button" type="button" data-close-modal>Cancel</button><button class="primary-button danger-button" type="submit">Release Hold</button></div>
        </form>
    </div>

    <script src="../../server/script.js?v=animations-v1"></script>
    <script>
        (() => {
            const modals = [...document.querySelectorAll('.record-modal')];
            const closeModal = (modal) => {
                if (modal) modal.hidden = true;
            };
            document.querySelectorAll('[data-open-modal]').forEach((button) => {
                button.addEventListener('click', () => {
                    const modal = document.getElementById(button.dataset.openModal);
                    if (!modal) return;
                    const holdIdInput = modal.querySelector('[data-modal-hold-id]');
                    const studentLabel = modal.querySelector('[data-modal-student]');
                    if (holdIdInput) holdIdInput.value = button.dataset.holdId || '';
                    if (studentLabel) studentLabel.textContent = button.dataset.studentName || '';
                    modal.hidden = false;
                    const firstField = modal.querySelector('select, textarea, input:not([type="hidden"])');
                    if (firstField) firstField.focus();
                });
            });
            document.querySelectorAll('[data-close-modal]').forEach((button) => button.addEventListener('click', () => closeModal(button.closest('.record-modal'))));
            modals.forEach((modal) => modal.addEventListener('click', (event) => {
                if (event.target === modal) closeModal(modal);
            }));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') modals.forEach(closeModal);
            });

            const holdForm = document.getElementById('hold-form');
            const isEditing = holdForm.querySelector('[name="action"]').value === 'edit';
            holdForm.addEventListener('submit', (event) => {
                if (!isEditing && !window.confirm('Place Clearance Hold? Are you sure you want to place this student\'s clearance on hold? The student will remain on hold until the issue is resolved or the hold is released.')) event.preventDefault();
            });
            document.querySelector('#release-modal form').addEventListener('submit', (event) => {
                if (!window.confirm('Release this clearance hold? This action will be recorded in the clearance history.')) event.preventDefault();
            });

            const studentSelect = document.getElementById('clearance-student');
            const violationSelect = document.getElementById('clearance-violation');
            const studentPreview = document.getElementById('clearance-student-preview');
            const sanctionPreview = document.getElementById('clearance-sanction-preview');
            const updateStudentDetails = () => {
                if (!studentSelect) return;
                const studentOption = studentSelect.options[studentSelect.selectedIndex];
                if (studentPreview) studentPreview.textContent = studentOption && studentOption.value ? `${studentOption.dataset.number} - ${studentOption.dataset.grade}` : '';
                if (violationSelect) {
                    [...violationSelect.options].forEach((option) => {
                        if (!option.value) return;
                        const matchesStudent = !studentSelect.value || option.dataset.studentId === studentSelect.value;
                        option.hidden = !matchesStudent;
                        if (!matchesStudent && option.selected) violationSelect.value = '';
                    });
                }
                updateSanctionDetails();
            };
            const updateSanctionDetails = () => {
                if (!violationSelect || !sanctionPreview) return;
                const selectedViolation = violationSelect.options[violationSelect.selectedIndex];
                sanctionPreview.textContent = selectedViolation && selectedViolation.dataset.sanction ? `Related sanction: ${selectedViolation.dataset.sanction}` : '';
            };
            if (studentSelect) studentSelect.addEventListener('change', updateStudentDetails);
            if (violationSelect) violationSelect.addEventListener('change', updateSanctionDetails);
            updateStudentDetails();

            <?php if ($editHold): ?>
                document.getElementById('hold-modal').hidden = false;
                updateStudentDetails();
            <?php endif; ?>
            const dismissButton = document.querySelector('.flash-dismiss');
            if (dismissButton) dismissButton.addEventListener('click', () => dismissButton.closest('.clearance-flash').remove());
        })();
    </script>
</body>

</html>


