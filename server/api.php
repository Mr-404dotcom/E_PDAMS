<?php


if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') !== 'api.php') {
    return;
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mail.php';


function sendJsonResponse($status, $message, $data = [], $httpCode = 200)
{
    http_response_code($httpCode);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function getJsonInput()
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse('error', 'Request body must contain valid JSON.', [], 400);
    }

    return is_array($decoded) ? $decoded : [];
}

// Helper to parse integer ID safely
function parseId($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id !== false ? (int) $id : null;
}

// Fetch generic resource row or list using safe whitelist and prepared statement
function fetchResource($conn, $resourceName, $id = null)
{
    $allowed = [
        'students' => ['table' => 'students', 'id' => 'student_id'],
        'users' => ['table' => 'users', 'id' => 'user_id'],
        'violations' => ['table' => 'infraction_logging', 'id' => 'infraction_id'],
        'sanctions' => ['table' => 'sanctions', 'id' => 'sanction_id'],
        'incident_reports' => ['table' => 'incident_report', 'id' => 'incident_id'],
        'schedules' => ['table' => 'disciplinary_schedule', 'id' => 'schedule_id'],
        'reformations' => ['table' => 'student_reformations', 'id' => 'enrollment_id'],
    ];

    if (!isset($allowed[$resourceName])) {
        return null;
    }

    $table = $allowed[$resourceName]['table'];
    $idColumn = $allowed[$resourceName]['id'];

    if ($id !== null) {
        $stmt = $conn->prepare("SELECT * FROM `$table` WHERE `$idColumn` = ? LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row;
    }

    $stmt = $conn->prepare("SELECT * FROM `$table` ORDER BY `$idColumn` DESC LIMIT 100");
    if (!$stmt) {
        return [];
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    return $rows;
}

// Fetch violations with student and category details
function fetchViolations($conn, $id = null, $studentId = null)
{
    $query = "SELECT i.infraction_id, i.student_id, s.student_number, 
                     CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name, 
                     vc.category_name, vc.severity, i.offense_committed, i.date_time, i.evidence, i.status, i.created_at,
                     (SELECT COUNT(*) FROM sanctions san WHERE san.infraction_id = i.infraction_id) AS sanctions_count
              FROM infraction_logging i 
              INNER JOIN students s ON s.student_id = i.student_id 
              INNER JOIN violation_category vc ON vc.category_id = i.category_id";

    if ($id !== null) {
        $stmt = $conn->prepare($query . ' WHERE i.infraction_id = ? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    if ($studentId !== null) {
        $stmt = $conn->prepare($query . ' WHERE i.student_id = ? ORDER BY i.date_time DESC');
        if (!$stmt) return [];
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
        return $rows;
    }

    $result = $conn->query($query . ' ORDER BY i.created_at DESC LIMIT 100');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

// Fetch sanctions linked to infractions
function fetchSanctions($conn, $id = null, $infractionId = null, $studentId = null)
{
    $query = "SELECT san.sanction_id, san.infraction_id, san.student_id, san.sanction_name, san.sanction_type, 
                     san.description, san.start_date, san.end_date, san.status, san.completion_notes, san.completed_at, san.created_at,
                     s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name,
                     i.offense_committed, vc.category_name, vc.severity
              FROM sanctions san
              INNER JOIN students s ON s.student_id = san.student_id
              INNER JOIN infraction_logging i ON i.infraction_id = san.infraction_id
              LEFT JOIN violation_category vc ON vc.category_id = i.category_id";

    if ($id !== null) {
        $stmt = $conn->prepare($query . ' WHERE san.sanction_id = ? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    if ($infractionId !== null) {
        $stmt = $conn->prepare($query . ' WHERE san.infraction_id = ? ORDER BY san.created_at DESC');
        if (!$stmt) return [];
        $stmt->bind_param('i', $infractionId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
        return $rows;
    }

    if ($studentId !== null) {
        $stmt = $conn->prepare($query . ' WHERE san.student_id = ? ORDER BY san.created_at DESC');
        if (!$stmt) return [];
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();
        return $rows;
    }

    $result = $conn->query($query . ' ORDER BY san.created_at DESC LIMIT 100');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

// Fetch incident reports
function fetchIncidentReports($conn, $id = null)
{
    $query = "SELECT ir.incident_id, ir.report_number, ir.infraction_id, ir.student_id, ir.violation, ir.report_title,
                     ir.incident_date, ir.incident_location, ir.description, ir.persons_involved, ir.witnesses,
                     ir.evidence_details, ir.action_taken, ir.recommendations, ir.status, ir.created_at,
                     s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name,
                     s.grade_level, s.section, s.parent_name, s.parent_contact, s.parent_email,
                     u.full_name AS reported_by_name
              FROM incident_report ir
              INNER JOIN students s ON s.student_id = ir.student_id
              LEFT JOIN users u ON u.user_id = ir.reported_by";

    if ($id !== null) {
        $stmt = $conn->prepare($query . ' WHERE ir.incident_id = ? LIMIT 1');
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    $result = $conn->query($query . ' ORDER BY ir.incident_date DESC, ir.incident_id DESC LIMIT 100');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

// Fetch student behavior scores based on standard formula
function fetchBehaviorOverview($conn, $studentId = null)
{
    // Formula:
    // Base points = 100
    // Deductions: Minor (-2), Moderate (-5), Major (-15)
    // Bonus/Commendations: sum of behavior_points
    // Clamped between 0 and 100
    $studentsSql = "SELECT s.student_id, s.student_number, 
                           CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS full_name,
                           s.grade_level, s.section, s.status,
                           (SELECT COUNT(*) FROM infraction_logging i WHERE i.student_id = s.student_id) AS total_infractions,
                           (SELECT COUNT(*) FROM infraction_logging i INNER JOIN violation_category vc ON vc.category_id = i.category_id WHERE i.student_id = s.student_id AND vc.severity = 'Minor') AS minor_count,
                           (SELECT COUNT(*) FROM infraction_logging i INNER JOIN violation_category vc ON vc.category_id = i.category_id WHERE i.student_id = s.student_id AND vc.severity = 'Moderate') AS moderate_count,
                           (SELECT COUNT(*) FROM infraction_logging i INNER JOIN violation_category vc ON vc.category_id = i.category_id WHERE i.student_id = s.student_id AND vc.severity = 'Major') AS major_count,
                           (SELECT COALESCE(SUM(points), 0) FROM behavior_points bp WHERE bp.student_id = s.student_id) AS custom_points,
                           (SELECT COUNT(*) FROM behavior_interventions bi WHERE bi.student_id = s.student_id AND bi.status = 'Ongoing') AS ongoing_interventions
                    FROM students s";

    if ($studentId !== null) {
        $stmt = $conn->prepare($studentsSql . " WHERE s.student_id = ? LIMIT 1");
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        if (!$row) return null;
        return calculateStudentBehaviorScore($row);
    }

    $result = $conn->query($studentsSql . " WHERE LOWER(s.status) <> 'inactive' ORDER BY s.last_name, s.first_name");
    $list = [];
    while ($row = $result->fetch_assoc()) {
        $list[] = calculateStudentBehaviorScore($row);
    }

    return $list;
}

// Calculates score and rating tier
function calculateStudentBehaviorScore($data)
{
    $base = 100;
    $minorDed = (int) $data['minor_count'] * 2;
    $moderateDed = (int) $data['moderate_count'] * 5;
    $majorDed = (int) $data['major_count'] * 15;
    $totalDeductions = $minorDed + $moderateDed + $majorDed;
    $custom = (int) $data['custom_points'];

    $rawScore = $base - $totalDeductions + $custom;
    $finalScore = max(0, min(100, $rawScore));

    if ($finalScore >= 90) {
        $status = 'Excellent';
        $statusClass = 'status-resolved';
    } elseif ($finalScore >= 80) {
        $status = 'Good';
        $statusClass = 'status-resolved';
    } elseif ($finalScore >= 70) {
        $status = 'Under Observation';
        $statusClass = 'status-observation';
    } else {
        $status = 'Intervention Required';
        $statusClass = 'status-intervention';
    }

    $data['calculated_score'] = $finalScore;
    $data['behavior_status'] = $status;
    $data['status_class'] = $statusClass;
    return $data;
}

// Router start
$method = $_SERVER['REQUEST_METHOD'];
$resource = $_GET['resource'] ?? 'students';
$action = $_GET['action'] ?? null;
$input = getJsonInput();

if ($action === 'health') {
    sendJsonResponse('success', 'API is running', [
        'timestamp' => date('c'),
        'server' => 'E-PDAMS API',
    ]);
}

$conn = getDbConnection();
if (!$conn) {
    sendJsonResponse('error', 'Database connection failed.', [], 500);
}

$allowedResources = [
    'students', 'users', 'violations', 'sanctions', 
    'incident_reports', 'notifications', 'behavior', 
    'schedules', 'reformations', 'smtp'
];

if (!in_array($resource, $allowedResources, true)) {
    sendJsonResponse('error', 'Unknown resource: ' . $resource, [], 404);
}

$id = parseId($_GET['id'] ?? ($input['id'] ?? null));

switch ($method) {
    case 'GET':
        // 1. Violations
        if ($resource === 'violations') {
            $studentId = parseId($_GET['student_id'] ?? null);
            $record = fetchViolations($conn, $id, $studentId);
            if ($id !== null && $record === null) {
                sendJsonResponse('error', 'Violation not found.', [], 404);
            }
            sendJsonResponse('success', 'Violations fetched successfully.', $record);
        }

        // 2. Sanctions (Supports multiple sanctions)
        if ($resource === 'sanctions') {
            $infractionId = parseId($_GET['infraction_id'] ?? null);
            $studentId = parseId($_GET['student_id'] ?? null);
            $record = fetchSanctions($conn, $id, $infractionId, $studentId);
            if ($id !== null && $record === null) {
                sendJsonResponse('error', 'Sanction not found.', [], 404);
            }
            sendJsonResponse('success', 'Sanctions fetched successfully.', $record);
        }

        // 3. Incident Reports
        if ($resource === 'incident_reports') {
            $record = fetchIncidentReports($conn, $id);
            if ($id !== null && $record === null) {
                sendJsonResponse('error', 'Incident report not found.', [], 404);
            }
            sendJsonResponse('success', 'Incident reports fetched successfully.', $record);
        }

        // 4. Notifications
        if ($resource === 'notifications') {
            $query = "SELECT pn.*, s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name
                      FROM parent_notification pn
                      INNER JOIN students s ON s.student_id = pn.student_id
                      ORDER BY pn.created_at DESC LIMIT 100";
            $res = $conn->query($query);
            $notifications = [];
            while ($r = $res->fetch_assoc()) $notifications[] = $r;
            sendJsonResponse('success', 'Notifications fetched successfully.', $notifications);
        }

        // 5. Behavior
        if ($resource === 'behavior') {
            $studentId = parseId($_GET['student_id'] ?? null);
            $overview = fetchBehaviorOverview($conn, $studentId);
            if ($studentId !== null && $overview === null) {
                sendJsonResponse('error', 'Student not found.', [], 404);
            }
            sendJsonResponse('success', 'Behavior data fetched successfully.', $overview);
        }

        // 6. Schedules
        if ($resource === 'schedules') {
            $query = "SELECT ds.*, s.student_number, CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name,
                             vc.category_name, i.offense_committed
                      FROM disciplinary_schedule ds
                      INNER JOIN students s ON s.student_id = ds.student_id
                      LEFT JOIN infraction_logging i ON i.infraction_id = ds.infraction_id
                      LEFT JOIN violation_category vc ON vc.category_id = i.category_id
                      ORDER BY ds.hearing_date ASC, ds.hearing_time ASC LIMIT 100";
            $res = $conn->query($query);
            $schedules = [];
            while ($r = $res->fetch_assoc()) $schedules[] = $r;
            sendJsonResponse('success', 'Disciplinary schedules fetched.', $schedules);
        }

        // 7. Reformation Programs
        if ($resource === 'reformations') {
            $programsRes = $conn->query("SELECT * FROM reformation_programs ORDER BY program_name ASC");
            $programs = [];
            while ($p = $programsRes->fetch_assoc()) $programs[] = $p;

            $enrolledQuery = "SELECT sr.*, rp.program_name, rp.duration_hours, s.student_number,
                                     CONCAT(s.first_name, ' ', COALESCE(s.middle_name, ''), ' ', s.last_name) AS student_name
                              FROM student_reformations sr
                              INNER JOIN reformation_programs rp ON rp.program_id = sr.program_id
                              INNER JOIN students s ON s.student_id = sr.student_id
                              ORDER BY sr.start_date DESC";
            $enrolledRes = $conn->query($enrolledQuery);
            $enrollments = [];
            while ($e = $enrolledRes->fetch_assoc()) $enrollments[] = $e;

            sendJsonResponse('success', 'Reformation programs fetched.', [
                'programs' => $programs,
                'enrollments' => $enrollments,
            ]);
        }

        // 8. SMTP Settings
        if ($resource === 'smtp') {
            $settings = getSmtpSettings($conn);
            unset($settings['smtp_pass']); // hide password for safety
            sendJsonResponse('success', 'SMTP settings fetched.', $settings);
        }

        // Count action
        if ($action === 'count') {
            $countQuery = "SELECT COUNT(*) AS total FROM `$resource`";
            $countResult = $conn->query($countQuery);
            $countRow = $countResult ? $countResult->fetch_assoc() : null;
            sendJsonResponse('success', 'Count fetched successfully.', [
                'resource' => $resource,
                'total' => (int) ($countRow['total'] ?? 0),
            ]);
        }

        // Generic fetch
        $record = fetchResource($conn, $resource, $id);
        if ($id !== null && $record === null) {
            sendJsonResponse('error', 'Record not found.', [], 404);
        }

        sendJsonResponse('success', ucfirst($resource) . ' fetched successfully.', $record);

    case 'POST':
        // 1. Violations
        if ($resource === 'violations') {
            $studentId = parseId($input['student_id'] ?? null);
            $categoryName = sanitizeString($input['category_name'] ?? '', 100);
            $offense = sanitizeString($input['offense_committed'] ?? '', 255);
            $dateTime = trim((string) ($input['date_time'] ?? date('Y-m-d H:i:s')));
            $evidence = sanitizeText($input['evidence'] ?? '', 2000);
            $status = sanitizeEnum($input['status'] ?? 'Pending', ['Pending', 'Resolved', 'Dismissed', 'Under Observation', 'Intervention Required'], 'Pending');
            $severity = sanitizeEnum($input['severity'] ?? 'Minor', ['Minor', 'Moderate', 'Major'], 'Minor');

            if ($studentId === null || $studentId <= 0 || $categoryName === '' || $offense === '') {
                sendJsonResponse('error', 'Student, violation category, and offense are required.', [], 400);
            }

            $timestamp = strtotime($dateTime);
            if ($timestamp === false) {
                sendJsonResponse('error', 'The violation date and time is invalid.', [], 400);
            }
            $dateTime = date('Y-m-d H:i:s', $timestamp);

            // check category using prepared statement
            $categoryCheck = $conn->prepare('SELECT category_id, severity FROM violation_category WHERE category_name = ? LIMIT 1');
            $categoryCheck->bind_param('s', $categoryName);
            $categoryCheck->execute();
            $categoryRow = $categoryCheck->get_result()->fetch_assoc();
            $categoryCheck->close();

            if ($categoryRow) {
                $categoryId = (int) $categoryRow['category_id'];
            } else {
                $categoryInsert = $conn->prepare("INSERT INTO violation_category (category_name, severity, status) VALUES (?, ?, 'Active')");
                $categoryInsert->bind_param('ss', $categoryName, $severity);
                $categoryInsert->execute();
                $categoryId = $categoryInsert->insert_id;
                $categoryInsert->close();
            }

            $stmt = $conn->prepare('INSERT INTO infraction_logging (student_id, category_id, offense_committed, date_time, evidence, status) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('iissss', $studentId, $categoryId, $offense, $dateTime, $evidence, $status);
            if (!$stmt->execute()) {
                $stmt->close();
                sendJsonResponse('error', 'Failed to add violation.', [], 500);
            }

            $newId = $stmt->insert_id;
            $stmt->close();
            sendJsonResponse('success', 'Violation added successfully.', ['infraction_id' => $newId]);
        }

        // 2. Sanctions (Multiple sanctions per violation)
        if ($resource === 'sanctions') {
            $infractionId = parseId($input['infraction_id'] ?? null);
            $studentId = parseId($input['student_id'] ?? null);
            $sanctionName = sanitizeString($input['sanction_name'] ?? '', 150);
            $sanctionType = sanitizeString($input['sanction_type'] ?? 'Community Service', 100);
            $description = sanitizeText($input['description'] ?? '', 2000);
            $startDate = !empty($input['start_date']) ? sanitizeString($input['start_date'], 20) : null;
            $endDate = !empty($input['end_date']) ? sanitizeString($input['end_date'], 20) : null;
            $status = sanitizeEnum($input['status'] ?? 'Pending', ['Pending', 'In Progress', 'Completed', 'Appealed', 'Cancelled'], 'Pending');
            $assignedBy = parseId($input['assigned_by'] ?? 1);

            // If studentId not provided, get from infraction using secure prepared statement
            if ($infractionId && !$studentId) {
                $qStmt = $conn->prepare("SELECT student_id FROM infraction_logging WHERE infraction_id = ? LIMIT 1");
                if ($qStmt) {
                    $qStmt->bind_param('i', $infractionId);
                    $qStmt->execute();
                    $qRes = $qStmt->get_result();
                    if ($r = $qRes->fetch_assoc()) {
                        $studentId = (int) $r['student_id'];
                    }
                    $qStmt->close();
                }
            }

            if (!$infractionId || !$studentId || $sanctionName === '') {
                sendJsonResponse('error', 'Infraction, student, and sanction name are required.', [], 400);
            }

            $stmt = $conn->prepare("INSERT INTO sanctions (infraction_id, student_id, sanction_name, sanction_type, description, start_date, end_date, status, assigned_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iissssssi', $infractionId, $studentId, $sanctionName, $sanctionType, $description, $startDate, $endDate, $status, $assignedBy);
            if (!$stmt->execute()) {
                $stmt->close();
                sendJsonResponse('error', 'Failed to add sanction.', [], 500);
            }

            $newId = $stmt->insert_id;
            $stmt->close();
            sendJsonResponse('success', 'Sanction assigned successfully.', ['sanction_id' => $newId]);
        }

        // 3. Incident Reports
        if ($resource === 'incident_reports') {
            $studentId = parseId($input['student_id'] ?? null);
            $infractionId = parseId($input['infraction_id'] ?? null);
            $reportTitle = sanitizeString($input['report_title'] ?? 'Incident Report', 200);
            $incidentDate = trim((string) ($input['incident_date'] ?? date('Y-m-d H:i:s')));
            $incidentLocation = sanitizeString($input['incident_location'] ?? '', 255);
            $description = sanitizeText($input['description'] ?? '', 5000);
            $personsInvolved = sanitizeText($input['persons_involved'] ?? '', 1000);
            $witnesses = sanitizeText($input['witnesses'] ?? '', 1000);
            $evidenceDetails = sanitizeText($input['evidence_details'] ?? '', 2000);
            $actionTaken = sanitizeText($input['action_taken'] ?? '', 2000);
            $recommendations = sanitizeText($input['recommendations'] ?? '', 2000);
            $reportedBy = parseId($input['reported_by'] ?? 1);
            $status = sanitizeEnum($input['status'] ?? 'Filed', ['Filed', 'Under Investigation', 'Resolved', 'Dismissed'], 'Filed');
            $violation = sanitizeString($input['violation'] ?? '', 200);

            if (!$studentId || $description === '') {
                sendJsonResponse('error', 'Student and incident description are required.', [], 400);
            }

            $timestamp = strtotime($incidentDate);
            $incidentDate = $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');

            // Auto-generate report number like IR-2026-0005 using prepared statement
            $year = date('Y');
            $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM incident_report");
            $countStmt->execute();
            $countRes = $countStmt->get_result();
            $nextNum = ((int) ($countRes->fetch_assoc()['total'] ?? 0)) + 1;
            $countStmt->close();
            $reportNumber = 'IR-' . $year . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $stmt = $conn->prepare("INSERT INTO incident_report (report_number, infraction_id, student_id, violation, report_title, incident_date, incident_location, description, persons_involved, witnesses, evidence_details, action_taken, recommendations, reported_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('siissssssssssis', $reportNumber, $infractionId, $studentId, $violation, $reportTitle, $incidentDate, $incidentLocation, $description, $personsInvolved, $witnesses, $evidenceDetails, $actionTaken, $recommendations, $reportedBy, $status);

            if (!$stmt->execute()) {
                $stmt->close();
                sendJsonResponse('error', 'Failed to save incident report.', [], 500);
            }

            $newId = $stmt->insert_id;
            $stmt->close();
            sendJsonResponse('success', 'Incident report created successfully.', [
                'incident_id' => $newId,
                'report_number' => $reportNumber,
            ]);
        }

        // 4. Notifications (Send Email via PHPMailer)
        if ($resource === 'notifications') {
            $studentId = parseId($input['student_id'] ?? null);
            $recipient = sanitizeEmail($input['recipient'] ?? '');
            $subject = sanitizeString($input['subject'] ?? '', 200);
            $message = sanitizeText($input['message'] ?? '', 5000);
            $type = sanitizeEnum($input['notification_type'] ?? 'Violation Notice', [
                'Violation Notice', 'Clearance Hold Alert', 'Sanction Notice', 'Hearing Notice', 'General Announcement'
            ], 'Violation Notice');

            if (!$studentId) {
                sendJsonResponse('error', 'A valid student is required.', [], 400);
            }
            if (!$recipient) {
                sendJsonResponse('error', 'A valid parent or recipient email address is required.', [], 400);
            }
            if ($subject === '' || $message === '') {
                sendJsonResponse('error', 'Subject and message cannot be empty.', [], 400);
            }

            $mailResult = sendParentNotification($studentId, $recipient, $subject, $message, $type);
            if ($mailResult['success']) {
                sendJsonResponse('success', $mailResult['message'], $mailResult);
            } else {
                sendJsonResponse('error', $mailResult['message'], $mailResult, 500);
            }
        }

        // 5. Behavior (Points or Interventions)
        if ($resource === 'behavior') {
            $subType = sanitizeEnum($input['type'] ?? 'point', ['point', 'intervention'], 'point');
            $studentId = parseId($input['student_id'] ?? null);

            if (!$studentId || $studentId <= 0) {
                sendJsonResponse('error', 'Valid student ID is required.', [], 400);
            }

            if ($subType === 'intervention') {
                $interventionType = sanitizeString($input['intervention_type'] ?? 'Behavior Contract', 100);
                $dateStarted = sanitizeString($input['date_started'] ?? date('Y-m-d'), 20);
                $personResponsible = sanitizeString($input['person_responsible'] ?? 'Guidance Counselor', 100);
                $followUp = !empty($input['follow_up_date']) ? sanitizeString($input['follow_up_date'], 20) : null;
                $desc = sanitizeText($input['description'] ?? '', 2000);
                $outcome = sanitizeText($input['outcome'] ?? '', 2000);
                $status = sanitizeEnum($input['status'] ?? 'Ongoing', ['Ongoing', 'Completed', 'Cancelled', 'Pending'], 'Ongoing');

                $stmt = $conn->prepare("INSERT INTO behavior_interventions (student_id, intervention_type, date_started, person_responsible, follow_up_date, description, outcome, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isssssss', $studentId, $interventionType, $dateStarted, $personResponsible, $followUp, $desc, $outcome, $status);
                if (!$stmt->execute()) {
                    $stmt->close();
                    sendJsonResponse('error', 'Failed to record intervention.', [], 500);
                }
                $newId = $stmt->insert_id;
                $stmt->close();
                sendJsonResponse('success', 'Intervention saved successfully.', ['intervention_id' => $newId]);
            } else {
                $points = (int) ($input['points'] ?? 5);
                $category = sanitizeString($input['category'] ?? 'Conduct', 100);
                $desc = sanitizeText($input['description'] ?? '', 1000);
                $dateRecorded = sanitizeString($input['date_recorded'] ?? date('Y-m-d'), 20);
                $recordedBy = parseId($input['recorded_by'] ?? 1);

                $stmt = $conn->prepare("INSERT INTO behavior_points (student_id, points, category, description, recorded_by, date_recorded) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('iissis', $studentId, $points, $category, $desc, $recordedBy, $dateRecorded);
                if (!$stmt->execute()) {
                    $stmt->close();
                    sendJsonResponse('error', 'Failed to record behavior points.', [], 500);
                }
                $newId = $stmt->insert_id;
                $stmt->close();
                sendJsonResponse('success', 'Behavior record saved successfully.', ['point_id' => $newId]);
            }
        }

        // 6. Schedules
        if ($resource === 'schedules') {
            $studentId = parseId($input['student_id'] ?? null);
            $infractionId = parseId($input['infraction_id'] ?? null);
            $title = sanitizeString($input['title'] ?? 'Disciplinary Conference', 200);
            $hearingDate = sanitizeString($input['hearing_date'] ?? date('Y-m-d'), 20);
            $hearingTime = sanitizeString($input['hearing_time'] ?? '09:00:00', 20);
            $location = sanitizeString($input['location'] ?? 'Prefect Office', 150);
            $officer = sanitizeString($input['assigned_officer'] ?? 'Prefect of Discipline', 150);
            $notes = sanitizeText($input['notes'] ?? '', 2000);
            $status = sanitizeEnum($input['status'] ?? 'Scheduled', ['Scheduled', 'Completed', 'Cancelled', 'Rescheduled'], 'Scheduled');

            if (!$studentId || $title === '') {
                sendJsonResponse('error', 'Student and schedule title are required.', [], 400);
            }

            $stmt = $conn->prepare("INSERT INTO disciplinary_schedule (student_id, infraction_id, title, hearing_date, hearing_time, location, assigned_officer, status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iisssssss', $studentId, $infractionId, $title, $hearingDate, $hearingTime, $location, $officer, $status, $notes);
            if (!$stmt->execute()) {
                $stmt->close();
                sendJsonResponse('error', 'Failed to create schedule.', [], 500);
            }
            $newId = $stmt->insert_id;
            $stmt->close();
            sendJsonResponse('success', 'Disciplinary hearing scheduled successfully.', ['schedule_id' => $newId]);
        }

        // 7. Reformation Programs Enrollment
        if ($resource === 'reformations') {
            $programId = parseId($input['program_id'] ?? null);
            $studentId = parseId($input['student_id'] ?? null);
            $infractionId = parseId($input['infraction_id'] ?? null);
            $startDate = sanitizeString($input['start_date'] ?? date('Y-m-d'), 20);
            $expectedCompletion = !empty($input['expected_completion']) ? sanitizeString($input['expected_completion'], 20) : null;
            $notes = sanitizeText($input['notes'] ?? '', 2000);

            if (!$programId || !$studentId) {
                sendJsonResponse('error', 'Program and student are required.', [], 400);
            }

            $stmt = $conn->prepare("INSERT INTO student_reformations (program_id, student_id, infraction_id, start_date, expected_completion, notes, status) VALUES (?, ?, ?, ?, ?, ?, 'In Progress')");
            $stmt->bind_param('iiisss', $programId, $studentId, $infractionId, $startDate, $expectedCompletion, $notes);
            if (!$stmt->execute()) {
                $stmt->close();
                sendJsonResponse('error', 'Failed to enroll student.', [], 500);
            }
            $newId = $stmt->insert_id;
            $stmt->close();
            sendJsonResponse('success', 'Student enrolled in reformation program.', ['enrollment_id' => $newId]);
        }

        // 8. SMTP Settings Update
        if ($resource === 'smtp') {
            $host = sanitizeString($input['smtp_host'] ?? 'smtp.gmail.com', 150);
            $port = filter_var($input['smtp_port'] ?? 587, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) ?: 587;
            $user = sanitizeString($input['smtp_user'] ?? '', 150);
            $pass = (string) ($input['smtp_pass'] ?? '');
            $enc = sanitizeEnum($input['smtp_encryption'] ?? 'tls', ['tls', 'ssl', 'none'], 'tls');
            $name = sanitizeString($input['sender_name'] ?? 'E-PDAMS Prefect Office', 150);
            $email = sanitizeEmail($input['sender_email'] ?? '') ?: 'prefect@school.edu';

            if ($pass !== '') {
                $stmt = $conn->prepare("UPDATE smtp_settings SET smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_pass = ?, smtp_encryption = ?, sender_name = ?, sender_email = ? WHERE setting_id = 1");
                $stmt->bind_param('sisssss', $host, $port, $user, $pass, $enc, $name, $email);
            } else {
                $stmt = $conn->prepare("UPDATE smtp_settings SET smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_encryption = ?, sender_name = ?, sender_email = ? WHERE setting_id = 1");
                $stmt->bind_param('sissss', $host, $port, $user, $enc, $name, $email);
            }
            $stmt->execute();
            $stmt->close();
            sendJsonResponse('success', 'SMTP settings updated successfully.');
        }

        // 9. Students insert
        if ($resource === 'students') {
            $studentNumber = sanitizeStudentNumber($input['student_number'] ?? '');
            $firstName = sanitizeString($input['first_name'] ?? '', 100);
            $middleName = sanitizeString($input['middle_name'] ?? '', 100);
            $lastName = sanitizeString($input['last_name'] ?? '', 100);
            $gender = sanitizeEnum($input['gender'] ?? '', ['Male', 'Female', ''], '');
            $gradeLevel = sanitizeString($input['grade_level'] ?? '', 50);
            $section = sanitizeString($input['section'] ?? '', 50);
            $status = sanitizeEnum($input['status'] ?? 'Active', ['Active', 'Inactive', 'Suspended', 'Transferred'], 'Active');
            $parentName = sanitizeString($input['parent_name'] ?? '', 100);
            $parentContact = sanitizePhone($input['parent_contact'] ?? '');
            $parentEmail = sanitizeEmail($input['parent_email'] ?? '');
            $address = sanitizeText($input['address'] ?? '', 2000);

            if ($studentNumber === '') {
                sendJsonResponse('error', 'Valid student number is required.', [], 400);
            }
            if ($firstName === '' || $lastName === '') {
                sendJsonResponse('error', 'First name and last name are required.', [], 400);
            }

            $stmt = $conn->prepare("INSERT INTO `students` (student_number, first_name, middle_name, last_name, gender, grade_level, section, status, parent_name, parent_contact, parent_email, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssssssss', $studentNumber, $firstName, $middleName, $lastName, $gender, $gradeLevel, $section, $status, $parentName, $parentContact, $parentEmail, $address);
            if (!$stmt->execute()) {
                $err = $stmt->errno === 1062 ? 'That student number already exists in the system.' : 'Failed to create student record.';
                $stmt->close();
                sendJsonResponse('error', $err, [], 400);
            }
            $newId = $stmt->insert_id;
            $stmt->close();
            sendJsonResponse('success', 'Student created successfully.', ['student_id' => $newId]);
        }

        // 10. Users insert
        if ($resource === 'users') {
            $username = sanitizeString($input['username'] ?? '', 50);
            $fullName = sanitizeString($input['full_name'] ?? '', 100);
            $password = (string) ($input['password'] ?? '');
            $role = sanitizeEnum($input['role'] ?? 'admin', ['admin', 'prefect', 'staff'], 'admin');
            $status = sanitizeEnum($input['status'] ?? 'Active', ['Active', 'Inactive', 'active', 'inactive'], 'Active');

            if ($username === '' || $fullName === '' || $password === '') {
                sendJsonResponse('error', 'Username, full name, and password are required.', [], 400);
            }
            if (!preg_match('/^[A-Za-z0-9._\-]{3,50}$/', $username)) {
                sendJsonResponse('error', 'Username must be 3 to 50 characters using letters, numbers, dots, underscores, or hyphens.', [], 400);
            }
            if (strlen($password) < 8) {
                sendJsonResponse('error', 'Password must be at least 8 characters long.', [], 400);
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO `users` (username, full_name, password, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssss', $username, $fullName, $hashedPassword, $role, $status);
            if (!$stmt->execute()) {
                $err = $stmt->errno === 1062 ? 'That username is already taken. Please choose another.' : 'Failed to create user.';
                $stmt->close();
                sendJsonResponse('error', $err, [], 400);
            }
            $newId = $stmt->insert_id;
            $stmt->close();
            sendJsonResponse('success', 'User created successfully.', ['user_id' => $newId]);
        }

        // 11. Reformation Programs insert
        if ($resource === 'reformation_programs') {
            $name = sanitizeString($input['program_name'] ?? '', 150);
            $desc = sanitizeText($input['description'] ?? '', 1000);
            $hours = max(1, (int)($input['duration_hours'] ?? 1));
            $coord = sanitizeString($input['coordinator'] ?? '', 100);
            $status = sanitizeEnum($input['status'] ?? 'Active', ['Active', 'Inactive'], 'Active');

            if ($name === '' || $desc === '' || $coord === '') {
                sendJsonResponse('error', 'Program name, description, and coordinator are required.', [], 400);
            }

            $stmt = $conn->prepare("INSERT INTO reformation_programs (program_name, description, duration_hours, coordinator, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('ssiss', $name, $desc, $hours, $coord, $status);
            if (!$stmt->execute()) {
                $stmt->close();
                sendJsonResponse('error', 'Failed to create program.', [], 500);
            }
            $stmt->close();
            sendJsonResponse('success', 'Program created successfully.');
        }

        sendJsonResponse('error', 'Invalid request payload.', [], 400);

    case 'PUT':
        if ($id === null || $id <= 0) {
            sendJsonResponse('error', 'A valid positive record id is required.', [], 400);
        }

        // 1. Sanctions update
        if ($resource === 'sanctions') {
            $status = sanitizeEnum($input['status'] ?? '', ['Pending', 'In Progress', 'Completed', 'Appealed', 'Cancelled']);
            $notes = sanitizeText($input['completion_notes'] ?? '', 2000);
            $completedAt = $status === 'Completed' ? date('Y-m-d H:i:s') : null;

            if ($status === null) {
                sendJsonResponse('error', 'Valid sanction status is required.', [], 400);
            }

            $stmt = $conn->prepare("UPDATE sanctions SET status = ?, completion_notes = ?, completed_at = ? WHERE sanction_id = ?");
            $stmt->bind_param('sssi', $status, $notes, $completedAt, $id);
            if (!$stmt->execute()) {
                $stmt->close();
                sendJsonResponse('error', 'Failed to update sanction.', [], 500);
            }
            $stmt->close();
            sendJsonResponse('success', 'Sanction updated successfully.', ['sanction_id' => $id, 'status' => $status]);
        }

        // 2. Incident Reports update
        if ($resource === 'incident_reports') {
            $status = sanitizeEnum($input['status'] ?? 'Filed', ['Filed', 'Under Investigation', 'Resolved', 'Dismissed'], 'Filed');
            $actionTaken = sanitizeText($input['action_taken'] ?? '', 2000);
            $recommendations = sanitizeText($input['recommendations'] ?? '', 2000);

            $stmt = $conn->prepare("UPDATE incident_report SET status = ?, action_taken = ?, recommendations = ? WHERE incident_id = ?");
            $stmt->bind_param('sssi', $status, $actionTaken, $recommendations, $id);
            if (!$stmt->execute()) {
                $stmt->close();
                sendJsonResponse('error', 'Failed to update incident report.', [], 500);
            }
            $stmt->close();
            sendJsonResponse('success', 'Incident report updated successfully.');
        }

        // 3. Violations status update
        if ($resource === 'violations') {
            $status = sanitizeEnum($input['status'] ?? '', ['Pending', 'Resolved', 'Dismissed', 'Under Observation', 'Intervention Required']);
            if ($status === null) {
                sendJsonResponse('error', 'Invalid violation status.', [], 400);
            }

            $stmt = $conn->prepare('UPDATE infraction_logging SET status = ? WHERE infraction_id = ?');
            $stmt->bind_param('si', $status, $id);
            if (!$stmt->execute()) {
                $stmt->close();
                sendJsonResponse('error', 'Failed to update violation status.', [], 500);
            }
            $stmt->close();
            sendJsonResponse('success', 'Violation status updated successfully.', ['infraction_id' => $id, 'status' => $status]);
        }

        // 4. Schedules update
        if ($resource === 'schedules') {
            $status = sanitizeEnum($input['status'] ?? 'Completed', ['Scheduled', 'Completed', 'Cancelled', 'Rescheduled'], 'Completed');
            $notes = sanitizeText($input['notes'] ?? '', 2000);
            $stmt = $conn->prepare("UPDATE disciplinary_schedule SET status = ?, notes = ? WHERE schedule_id = ?");
            $stmt->bind_param('ssi', $status, $notes, $id);
            $stmt->execute();
            $stmt->close();
            sendJsonResponse('success', 'Disciplinary schedule updated successfully.');
        }

        // 5. Reformation student progress update
        if ($resource === 'reformations') {
            $hours = max(0, (int) ($input['hours_completed'] ?? 0));
            $percent = max(0, min(100, (int) ($input['progress_percent'] ?? 0)));
            $status = sanitizeEnum($input['status'] ?? 'In Progress', ['In Progress', 'Completed', 'Dropped'], 'In Progress');
            $notes = sanitizeText($input['notes'] ?? '', 2000);

            $stmt = $conn->prepare("UPDATE student_reformations SET hours_completed = ?, progress_percent = ?, status = ?, notes = ? WHERE enrollment_id = ?");
            $stmt->bind_param('iissi', $hours, $percent, $status, $notes, $id);
            $stmt->execute();
            $stmt->close();
            sendJsonResponse('success', 'Student reformation progress updated.');
        }

        // 6. Students update
        if ($resource === 'students') {
            $fields = [];
            $types = '';
            $values = [];
            $fieldTypes = [
                'student_number' => 'student_number',
                'first_name' => 'string',
                'middle_name' => 'string',
                'last_name' => 'string',
                'gender' => 'string',
                'grade_level' => 'string',
                'section' => 'string',
                'parent_name' => 'string',
                'parent_contact' => 'phone',
                'parent_email' => 'email',
                'address' => 'text',
                'status' => 'status'
            ];

            foreach ($fieldTypes as $field => $type) {
                if (array_key_exists($field, $input)) {
                    $raw = $input[$field];
                    if ($type === 'student_number') {
                        $val = sanitizeStudentNumber($raw);
                    } elseif ($type === 'email') {
                        $val = sanitizeEmail($raw);
                    } elseif ($type === 'phone') {
                        $val = sanitizePhone($raw);
                    } elseif ($type === 'text') {
                        $val = sanitizeText($raw, 2000);
                    } elseif ($type === 'status') {
                        $val = sanitizeEnum($raw, ['Active', 'Inactive', 'Suspended', 'Transferred'], 'Active');
                    } else {
                        $val = sanitizeString($raw, 100);
                    }

                    $fields[] = "`$field` = ?";
                    $types .= 's';
                    $values[] = (string) $val;
                }
            }

            if (empty($fields)) {
                sendJsonResponse('error', 'No student fields provided to update.', [], 400);
            }

            $types .= 'i';
            $values[] = $id;
            $sql = "UPDATE `students` SET " . implode(', ', $fields) . " WHERE student_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $stmt->close();
            sendJsonResponse('success', 'Student updated successfully.', ['student_id' => $id]);
        }

        // 7. Users update
        if ($resource === 'users') {
            $fields = [];
            $types = '';
            $values = [];
            if (array_key_exists('username', $input)) {
                $u = sanitizeString($input['username'], 50);
                if (!preg_match('/^[A-Za-z0-9._\-]{3,50}$/', $u)) {
                    sendJsonResponse('error', 'Invalid username format (3-50 letters, numbers, dot, underscore, dash).', [], 400);
                }
                $fields[] = "`username` = ?";
                $types .= 's';
                $values[] = $u;
            }
            if (array_key_exists('full_name', $input)) {
                $fields[] = "`full_name` = ?";
                $types .= 's';
                $values[] = sanitizeString($input['full_name'], 100);
            }
            if (array_key_exists('role', $input)) {
                $fields[] = "`role` = ?";
                $types .= 's';
                $values[] = sanitizeEnum($input['role'], ['admin', 'prefect', 'staff'], 'admin');
            }
            if (array_key_exists('status', $input)) {
                $fields[] = "`status` = ?";
                $types .= 's';
                $values[] = sanitizeEnum($input['status'], ['Active', 'Inactive', 'active', 'inactive'], 'Active');
            }
            if (!empty($input['password'])) {
                if (strlen((string) $input['password']) < 8) {
                    sendJsonResponse('error', 'Password must be at least 8 characters long.', [], 400);
                }
                $fields[] = "`password` = ?";
                $types .= 's';
                $values[] = password_hash((string) $input['password'], PASSWORD_DEFAULT);
            }

            if (empty($fields)) {
                sendJsonResponse('error', 'No user fields provided to update.', [], 400);
            }

            $types .= 'i';
            $values[] = $id;
            $stmt = $conn->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE user_id = ?");
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $stmt->close();
            sendJsonResponse('success', 'User updated successfully.', ['user_id' => $id]);
        }

        // 8. Reformation Programs update
        if ($resource === 'reformation_programs') {
            $name = sanitizeString($input['program_name'] ?? '', 150);
            $desc = sanitizeText($input['description'] ?? '', 1000);
            $hours = max(1, (int)($input['duration_hours'] ?? 1));
            $coord = sanitizeString($input['coordinator'] ?? '', 100);

            if ($name === '' || $desc === '' || $coord === '') {
                sendJsonResponse('error', 'Program name, description, and coordinator are required.', [], 400);
            }

            $stmt = $conn->prepare("UPDATE reformation_programs SET program_name = ?, description = ?, duration_hours = ?, coordinator = ? WHERE program_id = ?");
            $stmt->bind_param('ssisi', $name, $desc, $hours, $coord, $id);
            if (!$stmt->execute()) {
                $stmt->close();
                sendJsonResponse('error', 'Failed to update program.', [], 500);
            }
            $stmt->close();
            sendJsonResponse('success', 'Program updated successfully.', ['program_id' => $id]);
        }

        sendJsonResponse('error', 'Invalid resource for update.', [], 400);

    case 'DELETE':
        if ($id === null || $id <= 0) {
            sendJsonResponse('error', 'A valid positive record id is required.', [], 400);
        }

        $tableMap = [
            'students' => ['table' => 'students', 'id' => 'student_id'],
            'users' => ['table' => 'users', 'id' => 'user_id'],
            'violations' => ['table' => 'infraction_logging', 'id' => 'infraction_id'],
            'sanctions' => ['table' => 'sanctions', 'id' => 'sanction_id'],
            'incident_reports' => ['table' => 'incident_report', 'id' => 'incident_id'],
            'schedules' => ['table' => 'disciplinary_schedule', 'id' => 'schedule_id'],
            'reformations' => ['table' => 'student_reformations', 'id' => 'enrollment_id'],
            'reformation_programs' => ['table' => 'reformation_programs', 'id' => 'program_id'],
        ];

        if (!isset($tableMap[$resource])) {
            sendJsonResponse('error', 'Deletion not permitted for this resource.', [], 400);
        }

        $targetTable = $tableMap[$resource]['table'];
        $targetIdCol = $tableMap[$resource]['id'];

        $stmt = $conn->prepare("DELETE FROM `$targetTable` WHERE `$targetIdCol` = ?");
        if ($stmt) {
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                $stmt->close();
                if ($affected === 0) {
                    sendJsonResponse('error', 'Record not found or already deleted.', [], 404);
                }
                sendJsonResponse('success', ucfirst($resource) . ' deleted successfully.', ['id' => $id]);
            }
            $stmt->close();
        }

        sendJsonResponse('error', 'Failed to delete record.', [], 500);

    default:
        sendJsonResponse('error', 'Unsupported HTTP method.', [], 405);
}
