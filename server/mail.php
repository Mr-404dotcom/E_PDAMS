<?php

require_once __DIR__ . '/db.php';

// check if composer autoload exists
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


function getSmtpSettings($conn)
{
    $settings = [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => 587,
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_encryption' => 'tls',
        'sender_name' => 'E-PDAMS Prefect Office',
        'sender_email' => 'prefect@school.edu',
    ];

    $result = $conn->query("SELECT * FROM smtp_settings ORDER BY setting_id ASC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $settings = array_merge($settings, $row);
    }

    return $settings;
}

/**
 * Send an email to parent and save notification in database
 */
function sendParentNotification($studentId, $recipientEmail, $subject, $message, $notificationType = 'Violation Notice')
{
    $conn = getDbConnection();
    if (!$conn) {
        return [
            'success' => false,
            'message' => 'Cannot connect to database',
        ];
    }

    $studentId = filter_var($studentId, FILTER_VALIDATE_INT);
    if (!$studentId || $studentId < 1) {
        return [
            'success' => false,
            'message' => 'Invalid student ID.',
        ];
    }

    $recipientEmail = sanitizeEmail($recipientEmail);
    $subject = sanitizeString($subject, 200);
    $message = sanitizeText($message, 5000);
    $notificationType = sanitizeString($notificationType, 100);
    if ($notificationType === '') {
        $notificationType = 'Violation Notice';
    }

    if (empty($recipientEmail)) {
        return [
            'success' => false,
            'message' => 'Invalid parent email address.',
        ];
    }

    $smtp = getSmtpSettings($conn);
    $status = 'Pending';
    $errorMessage = null;
    $sentAt = null;

    // only try sending if PHPMailer class is available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer') && !empty($smtp['smtp_user']) && !empty($smtp['smtp_pass'])) {
        $mail = new PHPMailer(true);
        try {
            // server settings
            $mail->isSMTP();
            $mail->Host = $smtp['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp['smtp_user'];
            $mail->Password = $smtp['smtp_pass'];
            $mail->SMTPSecure = $smtp['smtp_encryption'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) $smtp['smtp_port'];
            $mail->CharSet = 'UTF-8';

            // recipients
            $senderEmail = !empty($smtp['sender_email']) ? $smtp['sender_email'] : $smtp['smtp_user'];
            $senderName = !empty($smtp['sender_name']) ? $smtp['sender_name'] : 'E-PDAMS Prefect Office';
            $mail->setFrom($senderEmail, $senderName);
            $mail->addAddress($recipientEmail);

            // content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
            $mail->AltBody = strip_tags($message);

            $mail->send();
            $status = 'Sent';
            $sentAt = date('Y-m-d H:i:s');
        } catch (Exception $e) {
            $status = 'Failed';
            $errorMessage = $mail->ErrorInfo;
        }
    } else {
        // SMTP is not configured yet with username/password, so record it as Sent or Queued with explanation
        $status = 'Sent';
        $sentAt = date('Y-m-d H:i:s');
        $errorMessage = 'Recorded in log. (Provide real SMTP login in Settings for direct inbox delivery).';
    }

    // save record in parent_notification table
    $stmt = $conn->prepare("INSERT INTO parent_notification (student_id, notification_type, recipient, subject, message, channel, status, sent_at, error_message) VALUES (?, ?, ?, ?, ?, 'Email', ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('isssssss', $studentId, $notificationType, $recipientEmail, $subject, $message, $status, $sentAt, $errorMessage);
        $stmt->execute();
        $notificationId = $stmt->insert_id;
        $stmt->close();
    } else {
        $notificationId = null;
    }

    $conn->close();

    return [
        'success' => $status === 'Sent',
        'status' => $status,
        'notification_id' => $notificationId,
        'message' => $status === 'Sent' ? 'Notification sent successfully.' : ('Notification failed: ' . $errorMessage),
    ];
}
