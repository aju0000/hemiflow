<?php
// services/NotificationService.php - Event-based Client Notifications & SMS Workflows

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/SmsService.php';

class NotificationService {
    private $db;
    private $smsService;

    public function __construct() {
        $this->db = getDbConnection();
        $this->smsService = new SmsService();
    }

    /**
     * Create client notification and optionally send SMS
     */
    public function createNotification($clientId, $title, $message, $notificationType = 'GENERAL', $projectId = null, $taskId = null, $sendSms = true) {
        $smsSent = 0;
        $smsSentAt = null;

        // Fetch client details for phone
        $stmtClient = $this->db->prepare("SELECT company_name, phone, contact_person FROM clients WHERE id = ?");
        $stmtClient->execute([$clientId]);
        $client = $stmtClient->fetch();

        if ($sendSms && $client && !empty($client['phone'])) {
            $smsRes = $this->smsService->sendSms($clientId, $client['phone'], $message);
            if ($smsRes['success']) {
                $smsSent = 1;
                $smsSentAt = date('Y-m-d H:i:s');
            }
        }

        $stmt = $this->db->prepare("INSERT INTO client_notifications (client_id, project_id, task_id, notification_type, title, message, is_read, sms_sent, sms_sent_at) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)");
        $stmt->execute([$clientId, $projectId, $taskId, $notificationType, $title, $message, $smsSent, $smsSentAt]);

        return $this->db->lastInsertId();
    }

    /**
     * Workflow: Task Requires Client Approval
     */
    public function notifyApprovalRequired($task) {
        $clientId = $task['client_id'];
        $projectId = $task['project_id'];
        $taskId = $task['id'];

        $stmtClient = $this->db->prepare("SELECT company_name FROM clients WHERE id = ?");
        $stmtClient->execute([$clientId]);
        $companyName = $stmtClient->fetchColumn() ?: 'Valued Client';

        $title = "Action Required: " . $task['title'] . " Ready for Approval";
        $message = "Dear {$companyName},\n\nYour website task '{$task['title']}' is ready for approval.\n\nPlease access your Client Dashboard to review and approve:\n" . $this->getSecureDashboardLink($clientId) . "\n\nThank you.";

        return $this->createNotification($clientId, $title, $message, 'APPROVAL_REQUIRED', $projectId, $taskId, true);
    }

    /**
     * Workflow: SMS After Client Approval Confirmation
     */
    public function notifyClientApproved($task, $feedback = '') {
        $clientId = $task['client_id'];
        $projectId = $task['project_id'];
        $taskId = $task['id'];

        $stmtClient = $this->db->prepare("SELECT company_name FROM clients WHERE id = ?");
        $stmtClient->execute([$clientId]);
        $companyName = $stmtClient->fetchColumn() ?: 'Valued Client';

        $title = "Approval Received: " . $task['title'];
        $message = "Dear {$companyName},\n\nYour approval for the '{$task['title']}' task has been received successfully.\n\nOur team will proceed with the next step.\n\nThank you.";

        return $this->createNotification($clientId, $title, $message, 'TASK_UPDATED', $projectId, $taskId, true);
    }

    /**
     * Workflow: Client Revision Requested Confirmation
     */
    public function notifyRevisionRequested($task, $feedback = '') {
        $clientId = $task['client_id'];
        $projectId = $task['project_id'];
        $taskId = $task['id'];

        $stmtClient = $this->db->prepare("SELECT company_name FROM clients WHERE id = ?");
        $stmtClient->execute([$clientId]);
        $companyName = $stmtClient->fetchColumn() ?: 'Valued Client';

        $title = "Revision Request Confirmed: " . $task['title'];
        $message = "Dear {$companyName},\n\nWe have received your revision notes for '{$task['title']}'.\n\nOur development team is addressing your requested changes.\n\nThank you.";

        return $this->createNotification($clientId, $title, $message, 'REVISION_REQUESTED', $projectId, $taskId, true);
    }

    /**
     * Workflow: Google Meet Scheduled
     */
    public function notifyMeetingScheduled($meeting) {
        $clientId = $meeting['client_id'];
        $projectId = $meeting['project_id'];
        $taskId = $meeting['task_id'];

        $stmtClient = $this->db->prepare("SELECT company_name FROM clients WHERE id = ?");
        $stmtClient->execute([$clientId]);
        $companyName = $stmtClient->fetchColumn() ?: 'Valued Client';

        $meetingDateFormatted = date('d F Y', strtotime($meeting['meeting_date']));
        $title = "Upcoming Meeting: " . $meeting['title'];
        $message = "Dear {$companyName},\n\nYour project meeting '{$meeting['title']}' has been scheduled.\n\nDate: {$meetingDateFormatted}\nTime: {$meeting['meeting_time']}\n\nJoin: {$meeting['meet_url']}\n\nThank you.";

        return $this->createNotification($clientId, $title, $message, 'MEETING_SCHEDULED', $projectId, $taskId, true);
    }

    /**
     * Generate secure, random magic token URL for SMS login
     */
    public function getSecureDashboardLink($clientId) {
        // Generate cryptographically secure random 64-char token
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+48 hours'));

        // Store hash in client_tokens
        $stmt = $this->db->prepare("INSERT INTO client_tokens (client_id, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$clientId, $tokenHash, $expiresAt]);

        // Base domain protocol detection
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        return "{$protocol}://{$host}/client/access.php?token={$rawToken}";
    }
}
