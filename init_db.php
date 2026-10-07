<?php
// init_db.php - Complete Database initialization script for Agency OS (Modules 3, 4, 9)

require_once __DIR__ . '/db.php';

$db = getDbConnection();

echo "Initializing Agency OS Database for Module 4 Project & Task Management...\n";

// Drop existing tables for fresh setup if needed
$tables = [
    'sms_logs', 'client_tokens', 'client_notifications', 'client_meetings',
    'escalations', 'sla_records', 'client_approvals', 'task_reviews',
    'task_status_history', 'task_activities', 'task_attachments', 'task_comments',
    'task_assignments', 'tasks', 'document_versions', 'documents',
    'document_templates', 'projects', 'client_services', 'clients',
    'user_module_access', 'modules', 'roles', 'users'
];

foreach ($tables as $table) {
    $db->exec("DROP TABLE IF EXISTS {$table}");
}

// 1. Users
$db->exec("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    role_id INTEGER NOT NULL,
    client_id INTEGER DEFAULT NULL,
    department VARCHAR(100) DEFAULT 'General',
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE SET NULL
)");

// 2. Roles
$db->exec("CREATE TABLE roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT
)");

// 3. Modules
$db->exec("CREATE TABLE modules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT
)");

// 4. User Module Access
$db->exec("CREATE TABLE user_module_access (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    module_code VARCHAR(50) NOT NULL,
    can_view INTEGER DEFAULT 1,
    can_edit INTEGER DEFAULT 0,
    can_delete INTEGER DEFAULT 0,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// 5. Clients
$db->exec("CREATE TABLE clients (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    tax_id VARCHAR(50),
    status VARCHAR(50) DEFAULT 'Approved / Active',
    notes TEXT,
    created_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(created_by) REFERENCES users(id)
)");

// 6. Client Services
$db->exec("CREATE TABLE client_services (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    service_name VARCHAR(150) NOT NULL,
    package_name VARCHAR(150) NOT NULL,
    price DECIMAL(12, 2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    billing_terms VARCHAR(200) DEFAULT 'Monthly in advance',
    payment_terms VARCHAR(200) DEFAULT 'Net 15 days',
    status VARCHAR(50) DEFAULT 'Active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE
)");

// 7. Document Templates
$db->exec("CREATE TABLE document_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_type VARCHAR(50) NOT NULL,
    template_name VARCHAR(200) NOT NULL,
    subject_template VARCHAR(255),
    content_template TEXT NOT NULL,
    variables_json TEXT,
    version INTEGER DEFAULT 1,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// 8. Documents
$db->exec("CREATE TABLE documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_number VARCHAR(50) UNIQUE NOT NULL,
    client_id INTEGER NOT NULL,
    service_id INTEGER,
    template_id INTEGER,
    document_type VARCHAR(50) NOT NULL,
    title VARCHAR(200) NOT NULL,
    custom_fields_json TEXT,
    content_html TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'Draft',
    generated_by INTEGER NOT NULL,
    version INTEGER DEFAULT 1,
    handover_project_id INTEGER DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY(service_id) REFERENCES client_services(id),
    FOREIGN KEY(template_id) REFERENCES document_templates(id),
    FOREIGN KEY(generated_by) REFERENCES users(id)
)");

// 9. Document Versions
$db->exec("CREATE TABLE document_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_id INTEGER NOT NULL,
    version_number INTEGER NOT NULL,
    title VARCHAR(200) NOT NULL,
    custom_fields_json TEXT,
    content_html TEXT NOT NULL,
    change_summary VARCHAR(255),
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY(created_by) REFERENCES users(id)
)");

// 10. Projects (Module 4 Core Entity)
$db->exec("CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    service_id INTEGER,
    project_name VARCHAR(200) NOT NULL,
    description TEXT,
    priority VARCHAR(20) DEFAULT 'Medium',
    status VARCHAR(50) DEFAULT 'In Progress', -- Pending, In Progress, Under Review, Completed
    team_lead_id INTEGER NOT NULL,
    deadline DATE,
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(client_id) REFERENCES clients(id),
    FOREIGN KEY(team_lead_id) REFERENCES users(id),
    FOREIGN KEY(created_by) REFERENCES users(id)
)");

// 11. Tasks (Module 4 Core Entity with Full Details)
$db->exec("CREATE TABLE tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_code VARCHAR(50) UNIQUE NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    client_id INTEGER NOT NULL,
    project_id INTEGER NOT NULL,
    service_id INTEGER,
    department VARCHAR(100) DEFAULT 'Development',
    assigned_to INTEGER,
    team_lead_id INTEGER NOT NULL,
    created_by INTEGER NOT NULL,
    priority VARCHAR(20) DEFAULT 'Medium', -- Low, Medium, High, Urgent
    status VARCHAR(50) DEFAULT 'PENDING', -- PENDING, IN PROGRESS, UNDER REVIEW, REVISION REQUIRED, SENT FOR CLIENT APPROVAL, CLIENT REVISION REQUESTED, COMPLETED, DELAYED, ESCALATED
    deadline DATETIME,
    sla_hours INTEGER DEFAULT 24,
    sla_start_time DATETIME,
    sla_due_time DATETIME,
    completion_time DATETIME,
    remarks TEXT,
    client_approval_required INTEGER DEFAULT 0,
    client_approval_status VARCHAR(50) DEFAULT 'Not Required', -- Not Required, Pending, Approved, Revision Requested
    is_deleted INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(client_id) REFERENCES clients(id),
    FOREIGN KEY(project_id) REFERENCES projects(id),
    FOREIGN KEY(assigned_to) REFERENCES users(id),
    FOREIGN KEY(team_lead_id) REFERENCES users(id),
    FOREIGN KEY(created_by) REFERENCES users(id)
)");

// 12. Task Comments
$db->exec("CREATE TABLE task_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    comment_text TEXT NOT NULL,
    attachment_url VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id) REFERENCES users(id)
)");

// 13. Task Attachments
$db->exec("CREATE TABLE task_attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_url VARCHAR(255) NOT NULL,
    file_size INTEGER DEFAULT 0,
    uploaded_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY(uploaded_by) REFERENCES users(id)
)");

// 14. Task Activity History
$db->exec("CREATE TABLE task_activities (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    action VARCHAR(100) NOT NULL,
    old_value VARCHAR(255),
    new_value VARCHAR(255),
    remarks TEXT,
    visibility VARCHAR(20) DEFAULT 'INTERNAL', -- INTERNAL or CLIENT
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY(user_id) REFERENCES users(id)
)");

// 15. Task Reviews (Team Lead Review Records)
$db->exec("CREATE TABLE task_reviews (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    team_lead_id INTEGER NOT NULL,
    developer_id INTEGER NOT NULL,
    review_action VARCHAR(50) NOT NULL, -- APPROVE, REQUEST REVISION, SENT FOR CLIENT APPROVAL
    review_comment TEXT,
    required_changes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY(team_lead_id) REFERENCES users(id),
    FOREIGN KEY(developer_id) REFERENCES users(id)
)");

// 16. Client Approvals
$db->exec("CREATE TABLE client_approvals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    task_id INTEGER NOT NULL,
    project_id INTEGER DEFAULT NULL,
    client_id INTEGER NOT NULL,
    approval_type VARCHAR(50) DEFAULT 'Task Deliverable',
    approval_status VARCHAR(50) NOT NULL, -- Approved, Revision Requested, Pending
    client_feedback TEXT,
    approved_by INTEGER DEFAULT NULL,
    approved_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY(client_id) REFERENCES clients(id),
    FOREIGN KEY(approved_by) REFERENCES users(id)
)");

// 17. Client Meetings (Google Meet integration)
$db->exec("CREATE TABLE client_meetings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    project_id INTEGER DEFAULT NULL,
    task_id INTEGER DEFAULT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    meeting_date DATE NOT NULL,
    meeting_time VARCHAR(50) NOT NULL,
    meet_url VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'Scheduled', -- Scheduled, Completed, Cancelled
    created_by INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE SET NULL,
    FOREIGN KEY(created_by) REFERENCES users(id)
)");

// 18. Client Notifications
$db->exec("CREATE TABLE client_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    project_id INTEGER DEFAULT NULL,
    task_id INTEGER DEFAULT NULL,
    notification_type VARCHAR(50) NOT NULL, -- APPROVAL_REQUIRED, TASK_UPDATED, REVISION_REQUESTED, MEETING_SCHEDULED, MEETING_UPDATED, PROJECT_COMPLETED, GENERAL
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read INTEGER DEFAULT 0,
    sms_sent INTEGER DEFAULT 0,
    sms_sent_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY(project_id) REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY(task_id) REFERENCES tasks(id) ON DELETE SET NULL
)");

// 19. Client Secure Access Tokens (Magic Links)
$db->exec("CREATE TABLE client_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE
)");

// 20. SMS Dispatch & Audit Logs
$db->exec("CREATE TABLE sms_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    client_id INTEGER NOT NULL,
    recipient_phone VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'SENT', -- SENT, MOCK_SENT, FAILED
    provider_response TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE CASCADE
)");

// Seed Roles
$roles = [
    ['Super Admin', 'Full system access to all modules'],
    ['Agency Admin', 'Agency level managerial access'],
    ['Sales / Account Manager', 'Manages leads, clients, and document generation'],
    ['Team Lead', 'Manages projects, assigns tasks, reviews developer work'],
    ['Developer', 'Executes assigned tasks and submits work'],
    ['Reports Manager', 'Access to executive reports and analytics dashboard'],
    ['Client', 'Client portal view for approvals']
];
$stmtRole = $db->prepare("INSERT INTO roles (name, description) VALUES (?, ?)");
foreach ($roles as $r) {
    $stmtRole->execute($r);
}

// Seed Modules
$modules = [
    ['sales', 'Sales & CRM', 'Manage leads, follow-ups, and sales conversions'],
    ['client-management', 'Client Management', 'Manage client accounts, contracts, and onboarding'],
    ['template-generator', 'Template Generator', 'Generate proposals, quotes, contracts, and invoices'],
    ['task-management', 'Project & Task Management', 'Team Lead assignment, developer work, reviews, and SLAs'],
    ['reports', 'Reports & Analytics', 'Management metrics, revenue, and productivity dashboard'],
    ['client_dashboard', 'Client Dashboard', 'Restricted Client Portal view']
];
$stmtMod = $db->prepare("INSERT INTO modules (code, name, description) VALUES (?, ?, ?)");
foreach ($modules as $m) {
    $stmtMod->execute($m);
}

// Seed Internal Users
$passHash = password_hash('password123', PASSWORD_BCRYPT);
$users = [
    ['admin', $passHash, 'System Super Admin', 'admin@agency.com', 1, null, 'Management'],
    ['sales_john', $passHash, 'John Sales Manager', 'john.sales@agency.com', 3, null, 'Sales'],
    ['lead_ajmal', $passHash, 'Ajmal Team Lead', 'ajmal.lead@agency.com', 4, null, 'Development'],
    ['dev_rahul', $passHash, 'Rahul Developer', 'rahul.dev@agency.com', 5, null, 'Development'],
    ['reports_sarah', $passHash, 'Sarah Analytics Admin', 'sarah.reports@agency.com', 6, null, 'Analytics']
];
$stmtUser = $db->prepare("INSERT INTO users (username, password_hash, full_name, email, role_id, client_id, department) VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($users as $u) {
    $stmtUser->execute($u);
}

// Module Access for internal users
foreach (['sales', 'client-management', 'template-generator', 'task-management', 'reports'] as $mod) {
    $db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (1, ?, 1, 1, 1)")->execute([$mod]);
}
foreach (['sales', 'client-management', 'template-generator'] as $mod) {
    $db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (2, ?, 1, 1, 1)")->execute([$mod]);
}
$db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (3, 'task-management', 1, 1, 0)")->execute();
$db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (3, 'client-management', 1, 0, 0)")->execute();
$db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (4, 'task-management', 1, 1, 0)")->execute();
$db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (5, 'reports', 1, 0, 0)")->execute();

// Seed Sample Clients & Services
$db->exec("INSERT INTO clients (company_name, contact_person, email, phone, address, tax_id, status, notes, created_by)
VALUES ('ABC Company', 'Rajesh Sharma', 'rajesh@abccompany.com', '+91 98765 43210', 'Suite 402, Business Tower, Mumbai, India', '27AAACA12341Z5', 'Approved / Active', 'Converted corporate client requiring full Web Dev suite.', 2)");

// Seed Dedicated Client User for ABC Company (ID 1)
$stmtUser->execute(['client_abc', $passHash, 'Rajesh Sharma (ABC Company)', 'rajesh@abccompany.com', 7, 1, 'Client Portal']);
$clientUserId = $db->lastInsertId();
$db->prepare("INSERT INTO user_module_access (user_id, module_code, can_view, can_edit, can_delete) VALUES (?, 'client_dashboard', 1, 1, 0)")->execute([$clientUserId]);

$db->exec("INSERT INTO client_services (client_id, service_name, package_name, price, currency, billing_terms, payment_terms)
VALUES (1, 'Website Development', 'Corporate Website', 150000.00, 'INR', '50% Advance, 50% on Completion', 'Net 15 days from Invoice')");

// Seed Initial Handed Over Project for ABC Company
$db->exec("INSERT INTO projects (client_id, service_id, project_name, description, priority, status, team_lead_id, deadline, created_by)
VALUES (1, 1, 'ABC Corporate Website', 'Build a responsive corporate website with Home, About, Services, Contact, and CMS integration as specified in sales agreement.', 'High', 'In Progress', 3, '" . date('Y-m-d', strtotime('+14 days')) . "', 2)");

// Seed Realistic Developer Tasks
$now = date('Y-m-d H:i:s');
$dueIn8h = date('Y-m-d H:i:s', strtotime('+8 hours'));
$dueIn24h = date('Y-m-d H:i:s', strtotime('+24 hours'));
$dueIn48h = date('Y-m-d H:i:s', strtotime('+48 hours'));

$seedTasks = [
    [
        'TSK-101', 'Setup project environment & Git repository',
        'Configure Vite + React + TS workspace, ESLint rules, and deployment pipelines.',
        1, 1, 1, 'Development', 4, 3, 2, 'High', 'COMPLETED',
        date('Y-m-d H:i:s', strtotime('-1 day')), 8, date('Y-m-d H:i:s', strtotime('-12 hours')), date('Y-m-d H:i:s', strtotime('-4 hours')), date('Y-m-d H:i:s', strtotime('-2 hours')), 'Git repository initialized.', 0, 'Approved'
    ],
    [
        'TSK-102', 'Develop homepage layout & hero banner',
        'Build responsive corporate homepage UI with animated hero section, value propositions, and CTA buttons.',
        1, 1, 1, 'Development', 4, 3, 2, 'High', 'SENT FOR CLIENT APPROVAL',
        $dueIn8h, 8, $now, $dueIn8h, null, 'Submitted UI implementation for Client review.', 1, 'Pending'
    ],
    [
        'TSK-103', 'Create database schema & backend API',
        'Implement REST API endpoints for services, contact form submissions, and content management.',
        1, 1, 1, 'Development', 4, 3, 2, 'Urgent', 'IN PROGRESS',
        $dueIn24h, 24, $now, $dueIn24h, null, 'API controllers in progress.', 0, 'Not Required'
    ],
    [
        'TSK-104', 'Develop Services & About pages',
        'Create modular content sections for company history, leadership team, and service offerings.',
        1, 1, 1, 'Development', 4, 3, 2, 'Medium', 'PENDING',
        $dueIn48h, 48, $now, $dueIn48h, null, 'Awaiting backend API completion.', 1, 'Pending'
    ],
    [
        'TSK-105', 'Testing & Final Deployment',
        'Perform cross-browser compatibility testing, performance audit, and production deployment on IIS.',
        1, 1, 1, 'Development', 4, 3, 2, 'High', 'PENDING',
        date('Y-m-d H:i:s', strtotime('+7 days')), 72, $now, date('Y-m-d H:i:s', strtotime('+3 days')), null, 'Final phase before handover.', 1, 'Pending'
    ]
];

$stmtTask = $db->prepare("INSERT INTO tasks (
    task_code, title, description, client_id, project_id, service_id, department, assigned_to, team_lead_id, created_by, priority, status, deadline, sla_hours, sla_start_time, sla_due_time, completion_time, remarks, client_approval_required, client_approval_status
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($seedTasks as $t) {
    $stmtTask->execute($t);
}

// Seed Task Comments
$db->exec("INSERT INTO task_comments (task_id, user_id, comment_text) VALUES (2, 4, 'Submitted code for homepage layout. Tested on Desktop, Tablet, and Mobile viewports.')");
$db->exec("INSERT INTO task_comments (task_id, user_id, comment_text) VALUES (2, 3, 'Reviewing homepage layout. Work ready for client review and approval.')");

// Seed Task Activities with visibility
$db->exec("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks, visibility) VALUES (1, 4, 'TASK_COMPLETED', 'IN PROGRESS', 'COMPLETED', 'Setup project environment completed', 'CLIENT')");
$db->exec("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks, visibility) VALUES (2, 3, 'SENT_FOR_CLIENT_APPROVAL', 'UNDER REVIEW', 'SENT FOR CLIENT APPROVAL', 'Homepage development ready for client approval', 'CLIENT')");
$db->exec("INSERT INTO task_activities (task_id, user_id, action, old_value, new_value, remarks, visibility) VALUES (2, 3, 'INTERNAL_REVIEW_NOTE', 'DRAFT', 'INTERNAL', 'Internal code review note for dev', 'INTERNAL')");

// Seed Sample Client Meeting (Google Meet link)
$meetingDate = date('Y-m-d', strtotime('+2 days'));
$db->exec("INSERT INTO client_meetings (client_id, project_id, task_id, title, description, meeting_date, meeting_time, meet_url, status, created_by)
VALUES (1, 1, 2, 'Website Review & Approval Meeting', 'Walkthrough of Homepage layout, feedback collection, and milestone sign-off.', '{$meetingDate}', '11:00 AM', 'https://meet.google.com/abc-defg-hij', 'Scheduled', 3)");

// Seed Sample Notification
$db->exec("INSERT INTO client_notifications (client_id, project_id, task_id, notification_type, title, message, is_read, sms_sent, sms_sent_at)
VALUES (1, 1, 2, 'APPROVAL_REQUIRED', 'Website Homepage Ready for Approval', 'Dear ABC Company, your homepage layout task is ready for review and approval. Please review and sign off.', 0, 1, CURRENT_TIMESTAMP)");

// Seed document templates
require_once __DIR__ . '/seed_templates.php';

echo "Database re-initialized and seeded with Client Portal Module schema & seed data successfully!\n";


