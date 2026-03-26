<?php
// ============================================================
//  EMS — Email Notification Utility (Simulated)
//  includes/mail_functions.php
// ============================================================

/**
 * Simulate sending an email by logging it to a file.
 * In production, this would use mail() or PHPMailer.
 *
 * @param string $to       Recipient email address.
 * @param string $subject  Email subject.
 * @param string $message  Email body.
 * @return bool            Always returns true for simulation.
 */
function ems_send_email(string $to, string $subject, string $message): bool
{
    $logFile = __DIR__ . '/../logs/mail_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    
    $entry = "[$timestamp] TO: $to\n";
    $entry .= "SUBJECT: $subject\n";
    $entry .= "MESSAGE:\n$message\n";
    $entry .= "------------------------------------------------------------\n";
    
    // Ensure logs directory exists
    if (!is_dir(__DIR__ . '/../logs')) {
        mkdir(__DIR__ . '/../logs', 0777, true);
    }
    
    file_put_contents($logFile, $entry, FILE_APPEND);
    
    error_log("[EMS Mail Simulation] Email sent to $to with subject: $subject");
    
    return true;
}

/**
 * Send approval notification to a provider.
 */
function send_approval_notification(string $email, string $name): bool
{
    $subject = "Your Training Provider Account has been Approved!";
    $message = "Dear $name,\n\nWe are pleased to inform you that your training provider registration for EduSkill Marketplace System (EMS) has been approved.\n\nYou can now log in and start managing your courses.\n\nBest regards,\nEMS Team";
    
    return ems_send_email($email, $subject, $message);
}

/**
 * Send rejection notification to a provider.
 */
function send_rejection_notification(string $email, string $name, string $reason = ''): bool
{
    $subject = "Update on your Training Provider Registration";
    $message = "Dear $name,\n\nThank you for your interest in EduSkill Marketplace System (EMS).\n\nUnfortunately, your registration has been rejected for the following reason:\n" . ($reason ?: "No specific reason provided.") . "\n\nIf you have any questions, please contact our support team.\n\nBest regards,\nEMS Team";
    
    return ems_send_email($email, $subject, $message);
}
?>
