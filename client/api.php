<?php
// client/api.php - Secure Client Portal REST API Endpoints

header('Content-Type: application/json');
require_once __DIR__ . '/includes/client_auth.php';
require_once __DIR__ . '/../services/NotificationService.php';

requireClientAuth();

$clientId = intval($_SESSION['client_id']);
$userId = intval($_SESSION['user_id']);

$action = $_GET['action'] ?? '';
$db = getDbConnection();
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    switch ($action) {

        case 'get_dashboard_summary':
            $activeProjects = $db->prepare("SELECT COUNT(*) FROM projects WHERE client_id = ? AND status != 'Completed'");
            $activeProjects->execute([$clientId]);

            $inProgressTasks = $db->prepare("SELECT COUNT(*) FROM tasks WHERE client_id = ? AND status IN ('IN PROGRESS', 'UNDER REVIEW', 'REVISION REQUIRED', 'CLIENT REVISION REQUESTED') AND is_deleted = 0");
            $inProgressTasks->execute([$clientId]);

            $awaitingApproval = $db->prepare("SELECT COUNT(*) FROM tasks WHERE client_id = ? AND status = 'SENT FOR CLIENT APPROVAL' AND is_deleted = 0");
            $awaitingApproval->execute([$clientId]);

            $completedTasks = $db->prepare("SELECT COUNT(*) FROM tasks WHERE client_id = ? AND status = 'COMPLETED' AND is_deleted = 0");
            $completedTasks->execute([$clientId]);

            // Recent Project Progress
            $stmtProj = $db->prepare("
                SELECT p.*,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_deleted = 0) as total_tasks,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'COMPLETED' AND t.is_deleted = 0) as completed_tasks
                FROM projects p WHERE p.client_id = ? ORDER BY p.id DESC LIMIT 5
            ");
            $stmtProj->execute([$clientId]);
            $projects = $stmtProj->fetchAll();

            foreach ($projects as &$p) {
                $p['progress_pct'] = $p['total_tasks'] > 0 ? round(($p['completed_tasks'] / $p['total_tasks']) * 100) : 0;
            }

            // Action Required Tasks
            $stmtAction = $db->prepare("SELECT t.*, p.project_name FROM tasks t JOIN projects p ON t.project_id = p.id WHERE t.client_id = ? AND t.status = 'SENT FOR CLIENT APPROVAL' AND t.is_deleted = 0 ORDER BY t.id DESC");
            $stmtAction->execute([$clientId]);
            $pendingActionTasks = $stmtAction->fetchAll();

            // Upcoming Meetings
            $stmtMeet = $db->prepare("SELECT * FROM client_meetings WHERE client_id = ? AND status = 'Scheduled' AND meeting_date >= CURRENT_DATE ORDER BY meeting_date ASC, meeting_time ASC LIMIT 3");
            $stmtMeet->execute([$clientId]);
            $upcomingMeetings = $stmtMeet->fetchAll();

            // Client Activities Timeline
            $stmtAct = $db->prepare("
                SELECT ta.*, t.task_code, t.title as task_title
                FROM task_activities ta
                JOIN tasks t ON ta.task_id = t.id
                WHERE t.client_id = ? AND ta.visibility = 'CLIENT'
                ORDER BY ta.id DESC LIMIT 6
            ");
            $stmtAct->execute([$clientId]);
            $recentActivities = $stmtAct->fetchAll();

            echo json_encode([
                'success' => true,
                'metrics' => [
                    'active_projects' => $activeProjects->fetchColumn(),
                    'in_progress_tasks' => $inProgressTasks->fetchColumn(),
                    'awaiting_approval' => $awaitingApproval->fetchColumn(),
                    'completed_tasks' => $completedTasks->fetchColumn()
                ],
                'projects' => $projects,
                'pending_action_tasks' => $pendingActionTasks,
                'upcoming_meetings' => $upcomingMeetings,
                'recent_activities' => $recentActivities
            ]);
            break;

        case 'get_projects':
            $stmt = $db->prepare("
                SELECT p.*,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.is_deleted = 0) as total_tasks,
                (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'COMPLETED' AND t.is_deleted = 0) as completed_tasks
                FROM projects p WHERE p.client_id = ? ORDER BY p.id DESC
            ");
            $stmt->execute([$clientId]);
            $projects = $stmt->fetchAll();

            foreach ($projects as &$p) {
                $p['progress_pct'] = $p['total_tasks'] > 0 ? round(($p['completed_tasks'] / $p['total_tasks']) * 100) : 0;
            }

            echo json_encode(['success' => true, 'projects' => $projects]);
            break;

        case 'get_project_details':
            $projectId = intval($_GET['project_id'] ?? 0);
            verifyClientOwnership('projects', $projectId);

            $stmtProj = $db->prepare("SELECT p.*, cs.service_name, cs.package_name FROM projects p LEFT JOIN client_services cs ON p.service_id = cs.id WHERE p.id = ? AND p.client_id = ?");
            $stmtProj->execute([$projectId, $clientId]);
            $project = $stmtProj->fetch();

            $stmtTasks = $db->prepare("SELECT t.* FROM tasks t WHERE t.project_id = ? AND t.client_id = ? AND t.is_deleted = 0 ORDER BY t.id ASC");
            $stmtTasks->execute([$projectId, $clientId]);
            $tasks = $stmtTasks->fetchAll();

            $totalTasks = count($tasks);
            $completedTasks = 0;
            foreach ($tasks as &$t) {
                if ($t['status'] === 'COMPLETED') $completedTasks++;
            }

            $project['total_tasks'] = $totalTasks;
            $project['completed_tasks'] = $completedTasks;
            $project['progress_pct'] = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

            echo json_encode(['success' => true, 'project' => $project, 'tasks' => $tasks]);
            break;

        case 'get_tasks':
            $statusFilter = $_GET['status'] ?? null;
            $projectId = intval($_GET['project_id'] ?? 0);

            $sql = "SELECT t.*, p.project_name FROM tasks t JOIN projects p ON t.project_id = p.id WHERE t.client_id = ? AND t.is_deleted = 0";
            $params = [$clientId];

            if ($projectId > 0) {
                $sql .= " AND t.project_id = ?";
                $params[] = $projectId;
            }

            if ($statusFilter === 'ACTION_REQUIRED') {
                $sql .= " AND t.status = 'SENT FOR CLIENT APPROVAL'";
            } elseif ($statusFilter === 'IN_PROGRESS') {
                $sql .= " AND t.status IN ('PENDING', 'IN PROGRESS', 'UNDER REVIEW', 'REVISION REQUIRED', 'CLIENT REVISION REQUESTED')";
            } elseif ($statusFilter === 'COMPLETED') {
                $sql .= " AND t.status = 'COMPLETED'";
            }

            $sql .= " ORDER BY t.id DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'tasks' => $stmt->fetchAll()]);
            break;

        case 'get_task_details':
            $taskId = intval($_GET['task_id'] ?? 0);
            verifyClientOwnership('tasks', $taskId);

            $stmt = $db->prepare("SELECT t.*, p.project_name FROM tasks t JOIN projects p ON t.project_id = p.id WHERE t.id = ? AND t.client_id = ? AND t.is_deleted = 0");
            $stmt->execute([$taskId, $clientId]);
            $task = $stmt->fetch();

            // Client-safe activity timeline (only visibility = 'CLIENT')
            $stmtAct = $db->prepare("SELECT action, old_value, new_value, remarks, created_at FROM task_activities WHERE task_id = ? AND visibility = 'CLIENT' ORDER BY id ASC");
            $stmtAct->execute([$taskId]);
            $activities = $stmtAct->fetchAll();

            // Attachments
            $stmtAtt = $db->prepare("SELECT id, file_name, file_size, created_at FROM task_attachments WHERE task_id = ? ORDER BY id DESC");
            $stmtAtt->execute([$taskId]);
            $attachments = $stmtAtt->fetchAll();

            // Approval history
            $stmtAppr = $db->prepare("SELECT * FROM client_approvals WHERE task_id = ? AND client_id = ? ORDER BY id DESC");
            $stmtAppr->execute([$taskId, $clientId]);
            $approvals = $stmtAppr->fetchAll();

            echo json_encode([
                'success' => true,
                'task' => $task,
                'activities' => $activities,
                'attachments' => $attachments,
                'approvals' => $approvals
            ]);
            break;

        case 'client_approve_task':
            $taskId = intval($input['task_id'] ?? 0);
            $comment = trim($input['comment'] ?? 'Approved by Client');
            verifyClientOwnership('tasks', $taskId);

            $stmtTask = $db->prepare("SELECT * FROM tasks WHERE id = ? AND client_id = ?");
            $stmtTask->execute([$taskId, $clientId]);
            $task = $stmtTask->fetch();

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            $now = date('Y-m-d H:i:s');

            // 1. Update Task Status
            $stmtUpd = $db->prepare("UPDATE tasks SET status = 'COMPLETED', client_approval_status = 'Approved', completion_time = ?, remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$now, $comment, $taskId]);

            // 2. Insert into client_approvals (preserving history)
            $stmtAppr = $db->prepare("INSERT INTO client_approvals (task_id, project_id, client_id, approval_type, approval_status, client_feedback, approved_by, approved_at) VALUES (?, ?, ?, 'Task Deliverable', 'Approved', ?, ?, ?)");
            $stmtAppr->execute([$taskId, $task['project_id'], $clientId, $comment, $userId, $now]);

            // 3. Insert Client Activity Log
            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks, visibility) VALUES (?, ?, 'CLIENT_APPROVED', ?, 'COMPLETED', ?, 'CLIENT')");
            $stmtAct->execute([$taskId, $userId, $task['status'], "Approved by client: " . $comment]);

            // 4. Trigger Notification & SMS Confirmation
            $notifService = new NotificationService();
            $notifService->notifyClientApproved($task, $comment);

            echo json_encode(['success' => true, 'message' => 'Thank you! Work approval has been saved and our team has been notified.']);
            break;

        case 'client_request_revision':
            $taskId = intval($input['task_id'] ?? 0);
            $comment = trim($input['comment'] ?? '');
            verifyClientOwnership('tasks', $taskId);

            if (empty($comment)) {
                echo json_encode(['success' => false, 'message' => 'Please describe the changes or revisions required.']);
                exit;
            }

            $stmtTask = $db->prepare("SELECT * FROM tasks WHERE id = ? AND client_id = ?");
            $stmtTask->execute([$taskId, $clientId]);
            $task = $stmtTask->fetch();

            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found.']);
                exit;
            }

            $now = date('Y-m-d H:i:s');

            // 1. Update Task Status to CLIENT REVISION REQUESTED
            $stmtUpd = $db->prepare("UPDATE tasks SET status = 'CLIENT REVISION REQUESTED', client_approval_status = 'Revision Requested', remarks = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmtUpd->execute([$comment, $taskId]);

            // 2. Insert into client_approvals (preserving history)
            $stmtAppr = $db->prepare("INSERT INTO client_approvals (task_id, project_id, client_id, approval_type, approval_status, client_feedback, approved_by, approved_at) VALUES (?, ?, ?, 'Task Revision Request', 'Revision Requested', ?, ?, ?)");
            $stmtAppr->execute([$taskId, $task['project_id'], $clientId, $comment, $userId, $now]);

            // 3. Insert Client Activity Log
            $stmtAct = $db->prepare("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks, visibility) VALUES (?, ?, 'CLIENT_REVISION_REQUESTED', ?, 'CLIENT REVISION REQUESTED', ?, 'CLIENT')");
            $stmtAct->execute([$taskId, $userId, $task['status'], "Client requested revision: " . $comment]);

            // 4. Trigger Notification & SMS Confirmation
            $notifService = new NotificationService();
            $notifService->notifyRevisionRequested($task, $comment);

            echo json_encode(['success' => true, 'message' => 'Revision request submitted successfully! Our development team lead has been alerted to review your notes.']);
            break;

        case 'get_approvals':
            $stmt = $db->prepare("
                SELECT ca.*, t.task_code, t.title as task_title, p.project_name
                FROM client_approvals ca
                JOIN tasks t ON ca.task_id = t.id
                LEFT JOIN projects p ON ca.project_id = p.id
                WHERE ca.client_id = ?
                ORDER BY ca.id DESC
            ");
            $stmt->execute([$clientId]);
            echo json_encode(['success' => true, 'approvals' => $stmt->fetchAll()]);
            break;

        case 'get_meetings':
            $stmt = $db->prepare("SELECT m.*, p.project_name, t.task_code, t.title as task_title FROM client_meetings m LEFT JOIN projects p ON m.project_id = p.id LEFT JOIN tasks t ON m.task_id = t.id WHERE m.client_id = ? ORDER BY m.meeting_date ASC, m.meeting_time ASC");
            $stmt->execute([$clientId]);
            echo json_encode(['success' => true, 'meetings' => $stmt->fetchAll()]);
            break;

        case 'get_notifications':
            $stmt = $db->prepare("SELECT * FROM client_notifications WHERE client_id = ? ORDER BY id DESC");
            $stmt->execute([$clientId]);
            echo json_encode(['success' => true, 'notifications' => $stmt->fetchAll()]);
            break;

        case 'mark_notification_read':
            $notifId = intval($input['notification_id'] ?? 0);
            if ($notifId > 0) {
                $stmt = $db->prepare("UPDATE client_notifications SET is_read = 1 WHERE id = ? AND client_id = ?");
                $stmt->execute([$notifId, $clientId]);
            } else {
                $stmt = $db->prepare("UPDATE client_notifications SET is_read = 1 WHERE client_id = ?");
                $stmt->execute([$clientId]);
            }
            echo json_encode(['success' => true, 'message' => 'Notification marked as read.']);
            break;

        case 'get_documents':
            $stmt = $db->prepare("SELECT d.*, u.full_name as generated_by_name FROM documents d JOIN users u ON d.generated_by = u.id WHERE d.client_id = ? ORDER BY d.id DESC");
            $stmt->execute([$clientId]);
            echo json_encode(['success' => true, 'documents' => $stmt->fetchAll()]);
            break;

        case 'update_profile':
            // Allow safe updates only (Contact person, Phone, Address)
            $contactPerson = trim($input['contact_person'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $address = trim($input['address'] ?? '');

            if (empty($contactPerson)) {
                echo json_encode(['success' => false, 'message' => 'Contact Person is required.']);
                exit;
            }

            $stmt = $db->prepare("UPDATE clients SET contact_person = ?, phone = ?, address = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$contactPerson, $phone, $address, $clientId]);

            echo json_encode(['success' => true, 'message' => 'Company profile updated successfully.']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action requested.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server Error: ' . $e->getMessage()]);
}
