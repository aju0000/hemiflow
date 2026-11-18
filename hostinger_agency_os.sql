-- Hostinger & cPanel MySQL / MariaDB Complete Database Export for HemiFlow Agency OS
-- Generated on 2026-11-13 12:46:34
-- Character Set: utf8mb4

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `shoot_assignments`;
DROP TABLE IF EXISTS `shoot_schedules`;
DROP TABLE IF EXISTS `renewal_assignments`;
DROP TABLE IF EXISTS `domain_server_renewals`;
DROP TABLE IF EXISTS `chat_messages`;
DROP TABLE IF EXISTS `chat_participants`;
DROP TABLE IF EXISTS `chat_conversations`;
DROP TABLE IF EXISTS `user_notifications`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `sms_logs`;
DROP TABLE IF EXISTS `client_approvals`;
DROP TABLE IF EXISTS `task_reviews`;
DROP TABLE IF EXISTS `task_activities`;
DROP TABLE IF EXISTS `task_comments`;
DROP TABLE IF EXISTS `task_attachments`;
DROP TABLE IF EXISTS `task_assignments`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `project_competitors`;
DROP TABLE IF EXISTS `project_members`;
DROP TABLE IF EXISTS `project_handlers`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `document_versions`;
DROP TABLE IF EXISTS `documents`;
DROP TABLE IF EXISTS `document_templates`;
DROP TABLE IF EXISTS `document_types`;
DROP TABLE IF EXISTS `client_notifications`;
DROP TABLE IF EXISTS `client_meetings`;
DROP TABLE IF EXISTS `client_tokens`;
DROP TABLE IF EXISTS `client_services`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `hr_notifications`;
DROP TABLE IF EXISTS `employee_documents`;
DROP TABLE IF EXISTS `holidays`;
DROP TABLE IF EXISTS `leave_requests`;
DROP TABLE IF EXISTS `leave_allocations`;
DROP TABLE IF EXISTS `leave_types`;
DROP TABLE IF EXISTS `presence_logs`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `user_module_access`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `designations`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `modules`;
DROP TABLE IF EXISTS `roles`;


-- Table: roles
CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: modules
CREATE TABLE `modules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: departments
CREATE TABLE `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `code` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT DEFAULT 1,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: designations
CREATE TABLE `designations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_id` INT NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: users
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `role_id` INT NOT NULL,
  `client_id` INT DEFAULT NULL,
  `department` VARCHAR(100) DEFAULT 'General',
  `is_active` TINYINT DEFAULT 1,
  `signup_token` VARCHAR(100) DEFAULT NULL,
  `work_status` VARCHAR(50) DEFAULT 'IN_OFFICE',
  `status_note` VARCHAR(255) DEFAULT NULL,
  `status_updated_at` DATETIME DEFAULT NULL,
  `can_chat` TINYINT DEFAULT 1,
  `can_create_group` TINYINT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: user_module_access
CREATE TABLE `user_module_access` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `module_code` VARCHAR(50) NOT NULL,
  `can_view` TINYINT DEFAULT 1,
  `can_edit` TINYINT DEFAULT 0,
  `can_delete` TINYINT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: employees
CREATE TABLE `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNIQUE DEFAULT NULL,
  `employee_code` VARCHAR(50) NOT NULL UNIQUE,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) DEFAULT '',
  `full_name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(50) DEFAULT NULL,
  `department_id` INT DEFAULT NULL,
  `designation_id` INT DEFAULT NULL,
  `reporting_manager_id` INT DEFAULT NULL,
  `date_of_joining` DATE NOT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `employment_type` VARCHAR(50) DEFAULT 'Full-time',
  `employment_status` VARCHAR(50) DEFAULT 'Active',
  `work_location` VARCHAR(50) DEFAULT 'On-site',
  `emergency_contact_name` VARCHAR(150) DEFAULT NULL,
  `emergency_contact_phone` VARCHAR(50) DEFAULT NULL,
  `emergency_contact_relation` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `avatar_url` VARCHAR(255) DEFAULT NULL,
  `hr_notes` TEXT DEFAULT NULL,
  `work_status` VARCHAR(50) DEFAULT 'IN_OFFICE',
  `status_note` VARCHAR(255) DEFAULT NULL,
  `status_updated_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT DEFAULT 1,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: attendance
CREATE TABLE `attendance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `attendance_date` DATE NOT NULL,
  `check_in` DATETIME DEFAULT NULL,
  `check_out` DATETIME DEFAULT NULL,
  `total_hours` DECIMAL(5, 2) DEFAULT 0.00,
  `status` VARCHAR(50) DEFAULT 'Present',
  `notes` TEXT DEFAULT NULL,
  `section_1` VARCHAR(10) DEFAULT NULL,
  `section_2` VARCHAR(10) DEFAULT NULL,
  `is_corrected` TINYINT DEFAULT 0,
  `corrected_by` INT DEFAULT NULL,
  `correction_reason` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_emp_date` (`employee_id`, `attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: presence_logs
CREATE TABLE `presence_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `employee_id` INT DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL,
  `status_note` VARCHAR(255) DEFAULT NULL,
  `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ended_at` DATETIME DEFAULT NULL,
  `duration_minutes` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: leave_types
CREATE TABLE `leave_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `code` VARCHAR(20) NOT NULL UNIQUE,
  `default_days_per_year` INT DEFAULT 12,
  `is_paid` TINYINT DEFAULT 1,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: leave_allocations
CREATE TABLE `leave_allocations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `leave_type_id` INT NOT NULL,
  `year` INT NOT NULL,
  `total_allocated` DECIMAL(5, 1) DEFAULT 12.0,
  `used_days` DECIMAL(5, 1) DEFAULT 0.0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_emp_leave_yr` (`employee_id`, `leave_type_id`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: leave_requests
CREATE TABLE `leave_requests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `leave_type_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `total_days` DECIMAL(5, 1) NOT NULL,
  `reason` TEXT NOT NULL,
  `status` VARCHAR(50) DEFAULT 'Pending',
  `reviewed_by` INT DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `manager_comment` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: holidays
CREATE TABLE `holidays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `holiday_name` VARCHAR(150) NOT NULL,
  `holiday_date` DATE NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `is_optional` TINYINT DEFAULT 0,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: employee_documents
CREATE TABLE `employee_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `document_title` VARCHAR(200) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` INT DEFAULT 0,
  `file_type` VARCHAR(100) DEFAULT NULL,
  `is_confidential` TINYINT DEFAULT 0,
  `uploaded_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: hr_notifications
CREATE TABLE `hr_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` VARCHAR(50) DEFAULT 'HR_ALERT',
  `link_url` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: clients
CREATE TABLE `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(200) NOT NULL,
  `contact_person` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `tax_id` VARCHAR(50) DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'Approved / Active',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: client_services
CREATE TABLE `client_services` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `service_name` VARCHAR(150) NOT NULL,
  `package_name` VARCHAR(150) NOT NULL,
  `price` DECIMAL(12, 2) NOT NULL,
  `currency` VARCHAR(10) DEFAULT 'INR',
  `billing_terms` VARCHAR(200) DEFAULT 'Monthly in advance',
  `payment_terms` VARCHAR(200) DEFAULT 'Net 15 days',
  `status` VARCHAR(50) DEFAULT 'Active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: client_tokens
CREATE TABLE `client_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: client_meetings
CREATE TABLE `client_meetings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `project_id` INT DEFAULT NULL,
  `task_id` INT DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `meeting_date` DATE NOT NULL,
  `meeting_time` VARCHAR(50) NOT NULL,
  `meet_url` VARCHAR(255) NOT NULL,
  `status` VARCHAR(50) DEFAULT 'Scheduled',
  `created_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: client_notifications
CREATE TABLE `client_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `project_id` INT DEFAULT NULL,
  `task_id` INT DEFAULT NULL,
  `notification_type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT DEFAULT 0,
  `sms_sent` TINYINT DEFAULT 0,
  `sms_sent_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: document_types
CREATE TABLE `document_types` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `icon_name` VARCHAR(50) DEFAULT 'file-text',
  `is_active` TINYINT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: document_templates
CREATE TABLE `document_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `document_type` VARCHAR(50) NOT NULL,
  `template_name` VARCHAR(200) NOT NULL,
  `subject_template` VARCHAR(255) DEFAULT NULL,
  `content_template` TEXT NOT NULL,
  `variables_json` TEXT DEFAULT NULL,
  `version` INT DEFAULT 1,
  `is_active` TINYINT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: documents
CREATE TABLE `documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `document_number` VARCHAR(50) NOT NULL UNIQUE,
  `client_id` INT NOT NULL,
  `service_id` INT DEFAULT NULL,
  `template_id` INT DEFAULT NULL,
  `document_type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `custom_fields_json` TEXT DEFAULT NULL,
  `content_html` TEXT NOT NULL,
  `status` VARCHAR(50) DEFAULT 'Draft',
  `generated_by` INT NOT NULL,
  `version` INT DEFAULT 1,
  `handover_project_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: document_versions
CREATE TABLE `document_versions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `document_id` INT NOT NULL,
  `version_number` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `custom_fields_json` TEXT DEFAULT NULL,
  `content_html` TEXT NOT NULL,
  `change_summary` VARCHAR(255) DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: projects
CREATE TABLE `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `service_id` INT DEFAULT NULL,
  `project_name` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `about_project` TEXT DEFAULT NULL,
  `project_url` VARCHAR(255) DEFAULT NULL,
  `deliverables_summary` TEXT DEFAULT NULL,
  `target_audience` TEXT DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `budget_info` VARCHAR(150) DEFAULT NULL,
  `priority` VARCHAR(20) DEFAULT 'Medium',
  `status` VARCHAR(50) DEFAULT 'In Progress',
  `team_lead_id` INT NOT NULL,
  `handlers_json` TEXT DEFAULT NULL,
  `working_employees_json` TEXT DEFAULT NULL,
  `observers_json` TEXT DEFAULT NULL,
  `department` VARCHAR(100) DEFAULT 'Development',
  `deadline` DATE DEFAULT NULL,
  `is_current` TINYINT DEFAULT 1,
  `client_approved` TINYINT DEFAULT 0,
  `client_approved_by` INT DEFAULT NULL,
  `client_approved_at` DATETIME DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: project_handlers
CREATE TABLE `project_handlers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_proj_handler` (`project_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: project_members
CREATE TABLE `project_members` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `added_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_proj_member` (`project_id`, `user_id`),
  INDEX `idx_proj_member_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: project_competitors
CREATE TABLE `project_competitors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `competitor_name` VARCHAR(200) NOT NULL,
  `website_url` VARCHAR(255) DEFAULT NULL,
  `social_links` VARCHAR(255) DEFAULT NULL,
  `strengths` TEXT DEFAULT NULL,
  `weaknesses` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: tasks
CREATE TABLE `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_code` VARCHAR(50) NOT NULL UNIQUE,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `client_id` INT NOT NULL,
  `project_id` INT NOT NULL,
  `parent_task_id` INT DEFAULT NULL,
  `task_type` VARCHAR(20) DEFAULT 'task',
  `service_id` INT DEFAULT NULL,
  `department` VARCHAR(100) DEFAULT 'Development',
  `assigned_to` INT DEFAULT NULL,
  `assignees_json` TEXT DEFAULT NULL,
  `observers_json` TEXT DEFAULT NULL,
  `team_lead_id` INT NOT NULL,
  `created_by` INT NOT NULL,
  `priority` VARCHAR(20) DEFAULT 'Medium',
  `status` VARCHAR(50) DEFAULT 'PENDING',
  `start_date` DATETIME DEFAULT NULL,
  `end_date` DATETIME DEFAULT NULL,
  `deadline` DATETIME DEFAULT NULL,
  `sla_hours` INT DEFAULT 24,
  `sla_start_time` DATETIME DEFAULT NULL,
  `sla_due_time` DATETIME DEFAULT NULL,
  `completion_time` DATETIME DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `client_approval_required` TINYINT DEFAULT 0,
  `client_approval_status` VARCHAR(50) DEFAULT 'Not Required',
  `handler_approved` TINYINT DEFAULT 0,
  `handler_approved_by` INT DEFAULT NULL,
  `handler_approved_at` DATETIME DEFAULT NULL,
  `lead_approved` TINYINT DEFAULT 0,
  `lead_approved_by` INT DEFAULT NULL,
  `lead_approved_at` DATETIME DEFAULT NULL,
  `client_approved_by_handler` TINYINT DEFAULT 0,
  `client_approved_by` INT DEFAULT NULL,
  `client_approved_at` DATETIME DEFAULT NULL,
  `is_deleted` TINYINT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: task_assignments
CREATE TABLE `task_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role_type` VARCHAR(20) DEFAULT 'assignee',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: task_attachments
CREATE TABLE `task_attachments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `uploaded_by` INT NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_url` VARCHAR(500) NOT NULL,
  `file_path` VARCHAR(500) DEFAULT '',
  `mime_type` VARCHAR(120) DEFAULT '',
  `file_size` BIGINT DEFAULT 0,
  `caption` TEXT DEFAULT NULL,
  `attachment_stage` VARCHAR(50) DEFAULT 'work',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: task_comments
CREATE TABLE `task_comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `comment_text` TEXT NOT NULL,
  `attachment_url` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: task_activities
CREATE TABLE `task_activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `old_value` VARCHAR(255) DEFAULT NULL,
  `new_value` VARCHAR(255) DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `visibility` VARCHAR(20) DEFAULT 'INTERNAL',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: task_reviews
CREATE TABLE `task_reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `team_lead_id` INT NOT NULL,
  `developer_id` INT NOT NULL,
  `review_action` VARCHAR(50) NOT NULL,
  `review_comment` TEXT DEFAULT NULL,
  `required_changes` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: client_approvals
CREATE TABLE `client_approvals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `project_id` INT DEFAULT NULL,
  `client_id` INT NOT NULL,
  `approval_type` VARCHAR(50) DEFAULT 'Task Deliverable',
  `approval_status` VARCHAR(50) NOT NULL,
  `client_feedback` TEXT DEFAULT NULL,
  `approved_by` INT DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: sms_logs
CREATE TABLE `sms_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `recipient_phone` VARCHAR(50) NOT NULL,
  `message` TEXT NOT NULL,
  `status` VARCHAR(20) DEFAULT 'SENT',
  `provider_response` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: activity_logs
CREATE TABLE `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `module` VARCHAR(50) DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) DEFAULT NULL,
  `entity_id` INT DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: user_notifications
CREATE TABLE `user_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `type` VARCHAR(50) DEFAULT 'general',
  `link_url` VARCHAR(255) DEFAULT '',
  `is_read` TINYINT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: shoot_schedules
CREATE TABLE `shoot_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT DEFAULT NULL,
  `client_name` VARCHAR(255) NOT NULL,
  `shoot_date` DATETIME NOT NULL,
  `videos_planned` INT DEFAULT 1,
  `scripts_approved` VARCHAR(50) DEFAULT 'Pending',
  `shoot_venue` VARCHAR(255) DEFAULT '',
  `venue_blocked_status` VARCHAR(100) DEFAULT 'Pending from Venue Side',
  `client_participants_count` INT DEFAULT 0,
  `shoot_type` VARCHAR(100) NOT NULL,
  `assigned_employees_json` TEXT DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'Scheduled',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_shoot_date` (`shoot_date`),
  INDEX `idx_shoot_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: shoot_assignments
CREATE TABLE `shoot_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shoot_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role_note` VARCHAR(100) DEFAULT 'Team Member',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_shoot_user` (`shoot_id`, `user_id`),
  INDEX `idx_shoot_assign_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: domain_server_renewals
CREATE TABLE `domain_server_renewals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT DEFAULT NULL,
  `client_name` VARCHAR(255) NOT NULL,
  `contact_number` VARCHAR(50) DEFAULT '',
  `domain_name` VARCHAR(255) NOT NULL,
  `domain_expiry` DATE DEFAULT NULL,
  `domain_charges` DECIMAL(10,2) DEFAULT 0.00,
  `server_expiry` DATE DEFAULT NULL,
  `server_charges` DECIMAL(10,2) DEFAULT 0.00,
  `assigned_employees_json` TEXT DEFAULT NULL,
  `renewal_status` VARCHAR(50) DEFAULT 'Active',
  `hosting_provider` VARCHAR(150) DEFAULT '',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_domain_expiry` (`domain_expiry`),
  INDEX `idx_server_expiry` (`server_expiry`),
  INDEX `idx_renewal_client` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: renewal_assignments
CREATE TABLE `renewal_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `renewal_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role_note` VARCHAR(100) DEFAULT 'Assigned Incharge',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_renewal_user` (`renewal_id`, `user_id`),
  INDEX `idx_renewal_assign_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: chat_conversations
CREATE TABLE `chat_conversations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(20) NOT NULL DEFAULT 'direct',
  `title` VARCHAR(150) DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_by` INT NOT NULL,
  `last_message_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: chat_participants
CREATE TABLE `chat_participants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role` VARCHAR(20) DEFAULT 'member',
  `last_read_at` DATETIME DEFAULT NULL,
  `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_conv_user` (`conversation_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: chat_messages
CREATE TABLE `chat_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `sender_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `message_type` VARCHAR(20) DEFAULT 'text',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- Dumping data for table `roles` (24 rows)
-- --------------------------------------------------------
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Super Admin', 'Full system access to all modules'),
(2, 'Agency Admin', 'Agency level managerial access'),
(3, 'Sales / Account Manager', 'Manages leads, clients, and document generation'),
(4, 'Team Lead', 'Manages projects, assigns tasks, reviews developer work'),
(5, 'Developer', 'Executes assigned tasks and submits work'),
(6, 'Reports Manager', 'Access to executive reports and analytics dashboard'),
(7, 'Client', 'Client portal view for approvals'),
(8, 'Designer', ''),
(9, 'Video Editor', ''),
(10, 'Ai Editor', ''),
(11, 'CRM', ''),
(12, 'SEO', ''),
(13, 'Digital Marketor', ''),
(14, 'Interns', ''),
(15, 'Accountant', ''),
(16, 'HR', ''),
(17, 'Ai Developer', 'Role for Ai Developer'),
(18, 'DM Lead', 'Role for DM Lead'),
(19, 'DM', 'Role for DM'),
(20, 'Content Writer', 'Role for Content Writer'),
(21, 'Sales', 'Role for Sales'),
(22, 'Support', 'Role for Support'),
(23, 'Performance Marketer', 'Role for Performance Marketer'),
(24, 'Performance Marketing', 'Role for Performance Marketing');

-- --------------------------------------------------------
-- Dumping data for table `modules` (7 rows)
-- --------------------------------------------------------
INSERT INTO `modules` (`id`, `code`, `name`, `description`) VALUES
(1, 'sales', 'Sales & CRM', 'Manage leads, follow-ups, and sales conversions'),
(2, 'client-management', 'Client Management', 'Manage client accounts, contracts, and onboarding'),
(3, 'template-generator', 'Template Generator', 'Generate proposals, quotes, contracts, and invoices'),
(4, 'task-management', 'Project & Task Management', 'Team Lead assignment, developer work, reviews, and SLAs'),
(5, 'reports', 'Reports & Analytics', 'Management metrics, revenue, and productivity dashboard'),
(6, 'client_dashboard', 'Client Dashboard', 'Restricted Client Portal view'),
(7, 'hr-management', 'HR Management', 'Employee profiles, attendance tracking, leave requests, holidays, and HR documents');

-- --------------------------------------------------------
-- Dumping data for table `departments` (13 rows)
-- --------------------------------------------------------
INSERT INTO `departments` (`id`, `name`, `created_by`, `created_at`, `code`, `description`, `is_active`) VALUES
(1, 'Development', NULL, '2026-10-08 06:28:00', 'DEV', NULL, 1),
(2, 'Management', NULL, '2026-10-08 06:28:00', 'MGMT', NULL, 1),
(3, 'Sales', NULL, '2026-10-08 06:28:00', 'SALES', NULL, 1),
(4, 'Design / UI/UX', NULL, '2026-10-08 06:28:00', 'DESIGN', NULL, 1),
(5, 'Analytics', NULL, '2026-10-08 06:28:00', 'ANLYT', NULL, 1),
(6, 'Support', NULL, '2026-10-08 06:28:00', 'SUPP', NULL, 1),
(8, 'Marketing', NULL, '2026-10-08 06:28:00', 'MKTG', NULL, 1),
(14, 'Cyber Security 771', NULL, '2026-10-16 10:14:04', 'CY771', 'Cyber', 1),
(15, 'Security Research 883', NULL, '2026-10-16 10:14:18', 'SEC883', 'Security Audit Team', 1),
(16, 'Admin', 1, '2026-10-27 10:33:36', 'ADMI', NULL, 1),
(17, 'Creative', 1, '2026-10-27 10:33:36', 'CREA', NULL, 1),
(18, 'HR', 1, '2026-10-27 10:33:36', 'HR', NULL, 1),
(19, 'CRM', 1, '2026-10-27 10:33:36', 'CRM', NULL, 1);

-- --------------------------------------------------------
-- Dumping data for table `designations` (10 rows)
-- --------------------------------------------------------
INSERT INTO `designations` (`id`, `department_id`, `title`, `description`, `is_active`, `created_at`) VALUES
(1, 1, 'Full Stack Developer', 'Web and backend software development', 1, '2026-10-16 09:04:13'),
(2, 1, 'Frontend Developer', 'UI implementation and client web apps', 1, '2026-10-16 09:04:13'),
(3, 1, 'Lead Architect', 'Technical architecture and code review', 1, '2026-10-16 09:04:13'),
(4, 2, 'Managing Director / CEO', 'Executive leadership and operations', 1, '2026-10-16 09:04:13'),
(5, 2, 'HR Manager', 'Human resources, talent and culture', 1, '2026-10-16 09:04:13'),
(6, 3, 'Sales Account Manager', 'Client acquisition and proposals', 1, '2026-10-16 09:04:13'),
(7, 4, 'UI/UX Designer', 'Product design, wireframing and prototyping', 1, '2026-10-16 09:04:13'),
(8, 8, 'Digital Marketing Lead', 'Performance marketing and campaigns', 1, '2026-10-16 09:04:13'),
(13, 14, 'Cyber Lead 771', 'Analyst', 1, '2026-10-16 10:14:04'),
(14, 15, 'SecOps Lead 883', 'Lead SecOps Engineer', 1, '2026-10-16 10:14:18');

-- --------------------------------------------------------
-- Dumping data for table `document_types` (8 rows)
-- --------------------------------------------------------
INSERT INTO `document_types` (`id`, `name`, `description`, `icon_name`, `is_active`, `created_at`) VALUES
(1, 'Proposal', 'Sales proposal and project pitch document', 'file-text', 1, '2026-10-08 11:53:04'),
(2, 'Invoice', 'Tax & billing invoice document', 'credit-card', 1, '2026-10-08 11:53:04'),
(3, 'Quotation', 'Price estimate and quotation', 'dollar-sign', 1, '2026-10-08 11:53:04'),
(4, 'Contract', 'Binding commercial agreement', 'file-check', 1, '2026-10-08 11:53:04'),
(5, 'Service Agreement', 'Service level and agreement document', 'shield', 1, '2026-10-08 11:53:04'),
(6, 'MOM', 'Minutes of Meeting notes', 'clipboard', 1, '2026-10-08 11:53:04'),
(7, 'Payment Reminder', 'Outstanding invoice payment reminder', 'bell', 1, '2026-10-08 11:53:04'),
(8, 'Renewal Letter', 'Subscription / contract renewal letter', 'refresh-cw', 1, '2026-10-08 11:53:04');

-- --------------------------------------------------------
-- Dumping data for table `document_templates` (6 rows)
-- --------------------------------------------------------
INSERT INTO `document_templates` (`id`, `document_type`, `template_name`, `subject_template`, `content_template`, `variables_json`, `version`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Proposal', 'Agency Management System Proposal (Multi-Page)', 'Agency Management System Proposal | Hemito Digital', '<div class=\"proposal-body\">
<style>
    @page {
        size: A4;
        margin: 0;
    }

    .proposal-body * {
        box-sizing: border-box;
    }

    .proposal-body {
        margin: 0;
        padding: 0;
        background: #eeeeee;
        font-family: Arial, Helvetica, sans-serif;
        color: #111111;
    }

    /* ==============================
       A4 PAGE
    ============================== */

    .proposal-body .page {
        width: 210mm;
        min-height: 297mm;
        background: #ffffff;
        margin: 20px auto;
        position: relative;
        padding: 25mm 20mm 28mm 20mm;
        page-break-after: always;
        overflow: hidden;
    }

    /* ==============================
       HEADER
    ============================== */

    .proposal-body .header {
        width: 100%;
        height: 48px;
        display: flex;
        justify-content: flex-end;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .proposal-body .header img {
        width: 145px;
        height: auto;
        object-fit: contain;
    }

    /* ==============================
       FOOTER
    ============================== */

    .proposal-body .footer {
        position: absolute;
        left: 0;
        bottom: 0;
        width: 100%;
        height: 16mm;
        background: #1f7fbd;
        color: #ffffff;

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 12px;
        text-align: center;
    }

    .proposal-body .footer a {
        color: #ffffff;
        text-decoration: none;
    }

    /* ==============================
       COVER PAGE
    ============================== */

    .proposal-body .cover {
        padding-top: 18mm;
        display: flex;
        flex-direction: column;
    }

    .proposal-body .cover-logo {
        text-align: center;
        margin-top: 5mm;
    }

    .proposal-body .cover-logo img {
        width: 190px;
    }

    .proposal-body .cover-title {
        margin-top: 18mm;
        text-align: center;
    }

    .proposal-body .cover-title .small-title {
        font-size: 30px;
        font-weight: 400;
        line-height: 1.2;
    }

    .proposal-body .cover-title .main-title {
        font-size: 29px;
        font-weight: 700;
        color: #1f7fbd;
        text-transform: uppercase;
        line-height: 1.25;
    }

    .proposal-body .cover-title .black-title {
        font-size: 29px;
        font-weight: 400;
    }

    .proposal-body .cover-description {
        text-align: center;
        font-size: 17px;
        margin-top: 10px;
        color: #444;
    }

    .proposal-body .cover-illustration {
        width: 330px;
        height: 220px;
        margin: 20mm auto 10mm;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .proposal-body .cover-illustration img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .proposal-body .submission-area {
        display: flex;
        justify-content: space-between;
        margin-top: auto;
        margin-bottom: 25mm;
        gap: 30px;
    }

    .proposal-body .submission-box {
        width: 48%;
        font-size: 15px;
        line-height: 1.55;
    }

    .proposal-body .submission-box strong {
        font-weight: 700;
    }

    .proposal-body .submission-title {
        font-size: 15px;
        margin-bottom: 3px;
    }

    .proposal-body .blue-line {
        width: 3px;
        background: #1f7fbd;
        min-height: 100px;
    }

    /* ==============================
       GENERAL CONTENT
    ============================== */

    .proposal-body .section-title {
        font-size: 28px;
        font-weight: 700;
        margin: 5px 0 22px;
        color: #111111;
    }

    .proposal-body .section-title span {
        color: #1f7fbd;
    }

    .proposal-body .sub-title {
        font-size: 20px;
        font-weight: 700;
        margin: 22px 0 10px;
    }

    .proposal-body .content {
        font-size: 15px;
        line-height: 1.6;
        text-align: justify;
    }

    .proposal-body .content p {
        margin: 0 0 14px;
    }

    .proposal-body .content ul {
        margin: 8px 0 15px 22px;
        padding: 0;
    }

    .proposal-body .content li {
        margin-bottom: 7px;
    }

    /* ==============================
       BLUE HIGHLIGHT BOX
    ============================== */

    .proposal-body .blue-box {
        background: #1f7fbd;
        color: #ffffff;
        padding: 18px 22px;
        border-radius: 2px;
        margin: 20px 0;
    }

    .proposal-body .blue-box h3 {
        margin: 0 0 8px;
        font-size: 19px;
    }

    .proposal-body .blue-box p {
        margin: 0;
        font-size: 14px;
        line-height: 1.5;
    }

    /* ==============================
       MODULE LABEL
    ============================== */

    .proposal-body .module-label {
        display: inline-block;
        background: #1f7fbd;
        color: #ffffff;
        padding: 7px 18px;
        font-size: 13px;
        font-weight: 700;
        margin-bottom: 10px;
    }

    /* ==============================
       WORKFLOW
    ============================== */

    .proposal-body .workflow {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-top: 25px;
        gap: 5px;
    }

    .proposal-body .workflow-item {
        flex: 1;
        text-align: center;
        position: relative;
    }

    .proposal-body .workflow-circle {
        width: 48px;
        height: 48px;
        border: 4px solid #1f7fbd;
        border-radius: 50%;
        margin: 0 auto 10px;

        display: flex;
        align-items: center;
        justify-content: center;

        color: #1f7fbd;
        font-weight: 700;
        font-size: 15px;
    }

    .proposal-body .workflow-item h4 {
        margin: 0;
        font-size: 12px;
        line-height: 1.3;
    }

    .proposal-body .workflow-arrow {
        color: #1f7fbd;
        font-size: 25px;
        margin-top: 10px;
    }

    /* ==============================
       MODULE CARDS
    ============================== */

    .proposal-body .module-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
        margin-top: 20px;
    }

    .proposal-body .module-card {
        border: 1px solid #cccccc;
        padding: 16px;
        min-height: 125px;
    }

    .proposal-body .module-card h3 {
        color: #1f7fbd;
        font-size: 17px;
        margin: 0 0 7px;
    }

    .proposal-body .module-card p {
        font-size: 13px;
        line-height: 1.5;
        margin: 0;
    }

    /* ==============================
       TASK FLOW
    ============================== */

    .proposal-body .task-flow {
        margin-top: 20px;
    }

    .proposal-body .task-step {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
    }

    .proposal-body .task-number {
        width: 32px;
        height: 32px;
        background: #1f7fbd;
        color: #ffffff;
        border-radius: 50%;

        display: flex;
        align-items: center;
        justify-content: center;

        font-size: 12px;
        font-weight: 700;
        flex-shrink: 0;
    }

    .proposal-body .task-name {
        margin-left: 12px;
        font-size: 14px;
        font-weight: 600;
    }

    .proposal-body .task-arrow {
        color: #1f7fbd;
        margin-left: 14px;
        font-weight: bold;
    }

    /* ==============================
       STATUS BOXES
    ============================== */

    .proposal-body .status-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-top: 15px;
    }

    .proposal-body .status {
        border: 1px solid #d4d4d4;
        padding: 12px;
        text-align: center;
        font-size: 13px;
        font-weight: 600;
    }

    .proposal-body .status.active {
        border-color: #1f7fbd;
        color: #1f7fbd;
    }

    .proposal-body .status.completed {
        background: #1f7fbd;
        color: #ffffff;
        border-color: #1f7fbd;
    }

    /* ==============================
       CLIENT DASHBOARD
    ============================== */

    .proposal-body .dashboard-box {
        border: 2px solid #1f7fbd;
        padding: 18px;
        margin-top: 20px;
    }

    .proposal-body .dashboard-header {
        background: #1f7fbd;
        color: #ffffff;
        padding: 14px;
        font-size: 18px;
        font-weight: 700;
    }

    .proposal-body .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        margin-top: 15px;
    }

    .proposal-body .dashboard-card {
        border: 1px solid #d0d0d0;
        padding: 15px;
    }

    .proposal-body .dashboard-card strong {
        display: block;
        color: #1f7fbd;
        font-size: 15px;
        margin-bottom: 5px;
    }

    .proposal-body .dashboard-card span {
        font-size: 13px;
    }

    /* ==============================
       APPROVAL FLOW
    ============================== */

    .proposal-body .approval-flow {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 20px;
    }

    .proposal-body .approval-step {
        flex: 1;
        text-align: center;
        border: 1px solid #cccccc;
        padding: 14px 8px;
        min-height: 85px;
    }

    .proposal-body .approval-step strong {
        display: block;
        color: #1f7fbd;
        font-size: 13px;
        margin-bottom: 5px;
    }

    .proposal-body .approval-step span {
        font-size: 11px;
        line-height: 1.3;
    }

    .proposal-body .approval-arrow {
        color: #1f7fbd;
        font-size: 20px;
    }

    /* ==============================
       FEATURE LIST
    ============================== */

    .proposal-body .feature-list {
        columns: 2;
        column-gap: 35px;
        margin-top: 15px;
    }

    .proposal-body .feature-list li {
        break-inside: avoid;
        font-size: 14px;
        margin-bottom: 8px;
    }

    /* ==============================
       TABLE
    ============================== */

    .proposal-body table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        font-size: 13px;
    }

    .proposal-body th {
        background: #1f7fbd;
        color: #ffffff;
        padding: 12px;
        text-align: left;
        border: 1px solid #111111;
    }

    .proposal-body td {
        padding: 11px;
        border: 1px solid #333333;
        vertical-align: top;
    }

    /* ==============================
       TECHNOLOGY
    ============================== */

    .proposal-body .tech-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-top: 20px;
    }

    .proposal-body .tech-item {
        border: 1px solid #1f7fbd;
        color: #1f7fbd;
        padding: 12px;
        text-align: center;
        font-size: 13px;
        font-weight: 700;
    }

    /* ==============================
       PRINT
    ============================== */

    @media print {
        body {
            background: #ffffff;
        }
        .proposal-body .page {
            margin: 0;
            box-shadow: none;
            page-break-after: always;
        }
    }

    @media screen {
        .proposal-body .page {
            box-shadow: 0 3px 20px rgba(0,0,0,0.12);
        }
    }
</style>

<!-- PAGE 1 — COVER -->
<div class=\"page cover\">
    <div class=\"cover-logo\">
        <img src=\"asset/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">
    </div>
    <div class=\"cover-title\">
        <div class=\"small-title\">Comprehensive</div>
        <div class=\"main-title\">Agency Management</div>
        <div class=\"black-title\">&amp; Client Workflow Platform</div>
    </div>
    <div class=\"cover-description\">
        Digital Workflow &amp; Project Management Solution
    </div>
    <div class=\"cover-illustration\">
        <img src=\"asset/hemitologo-dark-blue.webp\" alt=\"Agency Management System\">
    </div>
    <div class=\"submission-area\">
        <div class=\"submission-box\">
            <div class=\"submission-title\">Prepared for:</div>
            <strong>{{CLIENT_COMPANY}}</strong><br>
            2nd Floor, ACEL Tower,<br>
            Ambady Lane, Near Little Flower Church,<br>
            Elamkulam, Kadavanthra, Kochi - 682020
        </div>
        <div class=\"blue-line\"></div>
        <div class=\"submission-box\">
            <div class=\"submission-title\">Solution:</div>
            <strong>Agency Management &amp; Client Workflow Platform</strong><br>
            Sales Management, Project Management, Task Management, Client Dashboard &amp; Reporting
        </div>
    </div>
    <div class=\"footer\">
        <a href=\"https://www.hemitodigital.com\">www.hemitodigital.com</a> &nbsp; | &nbsp;
        <a href=\"mailto:sales@hemitodigital.com\">sales@hemitodigital.com</a> &nbsp; | &nbsp;
        +91-8921992187
    </div>
</div>

<!-- PAGE 2 — OVERVIEW -->
<div class=\"page\">
    <div class=\"header\">
        <img src=\"asset/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">
    </div>
    <h1 class=\"section-title\">About the <span>Platform</span></h1>
    <div class=\"content\">
        <p>The proposed Agency Management &amp; Client Workflow Platform is designed to centralize and streamline Hemito Digital\'s internal business operations and client communication.</p>
        <p>The platform will connect the sales, project management, development and client approval workflows in a single system. This will provide better visibility into ongoing work, improve task coordination and simplify communication between Hemito Digital and its clients.</p>
        <p>The solution will also provide management with centralized reporting and performance visibility across projects, tasks, clients and teams.</p>
    </div>
    <div class=\"blue-box\">
        <h3>Objective</h3>
        <p>To provide a structured digital workflow that allows Hemito Digital to manage the complete journey from client acquisition and approval through project execution, development, client review and final completion.</p>
    </div>
    <h2 class=\"sub-title\">Core Workflow</h2>
    <div class=\"workflow\">
        <div class=\"workflow-item\"><div class=\"workflow-circle\">01</div><h4>Sales</h4></div>
        <div class=\"workflow-arrow\">→</div>
        <div class=\"workflow-item\"><div class=\"workflow-circle\">02</div><h4>Client Approval</h4></div>
        <div class=\"workflow-arrow\">→</div>
        <div class=\"workflow-item\"><div class=\"workflow-circle\">03</div><h4>Project</h4></div>
        <div class=\"workflow-arrow\">→</div>
        <div class=\"workflow-item\"><div class=\"workflow-circle\">04</div><h4>Team Lead</h4></div>
        <div class=\"workflow-arrow\">→</div>
        <div class=\"workflow-item\"><div class=\"workflow-circle\">05</div><h4>Task Assignment</h4></div>
        <div class=\"workflow-arrow\">→</div>
        <div class=\"workflow-item\"><div class=\"workflow-circle\">06</div><h4>Development</h4></div>
    </div>
    <div class=\"workflow\" style=\"margin-top:30px;\">
        <div class=\"workflow-item\"><div class=\"workflow-circle\">07</div><h4>Review</h4></div>
        <div class=\"workflow-arrow\">→</div>
        <div class=\"workflow-item\"><div class=\"workflow-circle\">08</div><h4>Client Approval</h4></div>
        <div class=\"workflow-arrow\">→</div>
        <div class=\"workflow-item\"><div class=\"workflow-circle\">09</div><h4>Completion</h4></div>
        <div class=\"workflow-arrow\">→</div>
        <div class=\"workflow-item\"><div class=\"workflow-circle\">10</div><h4>Reports</h4></div>
    </div>
    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>

<!-- PAGE 3 — MODULE 3 -->
<div class=\"page\">
    <div class=\"header\">
        <img src=\"asset/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">
    </div>
    <div class=\"module-label\">MODULE 03</div>
    <h1 class=\"section-title\">Template <span>Generator</span></h1>
    <div class=\"content\">
        <p>The Template Generator module provides a structured method for creating standardized business documents using predefined templates and existing client and project information.</p>
        <p>The module reduces repetitive manual document preparation and ensures consistency across client-facing documents.</p>
    </div>
    <h2 class=\"sub-title\">Key Features</h2>
    <ul class=\"feature-list\">
        <li>Proposal generation</li>
        <li>Quotation generation</li>
        <li>Contract generation</li>
        <li>Service agreements</li>
        <li>Invoice documents</li>
        <li>Meeting / MOM documents</li>
        <li>Payment reminders</li>
        <li>Renewal documents</li>
        <li>Client information auto-population</li>
        <li>Project information auto-population</li>
        <li>Document preview</li>
        <li>PDF generation</li>
        <li>Document version management</li>
        <li>Document history</li>
    </ul>
    <h2 class=\"sub-title\">Document Workflow</h2>
    <div class=\"task-flow\">
        <div class=\"task-step\"><div class=\"task-number\">01</div><div class=\"task-name\">Select Client</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">02</div><div class=\"task-name\">Select Project</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">03</div><div class=\"task-name\">Select Template</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">04</div><div class=\"task-name\">Auto Populate Information</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">05</div><div class=\"task-name\">Preview Document</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">06</div><div class=\"task-name\">Save / Generate PDF</div></div>
    </div>
    <div class=\"blue-box\">
        <h3>Template-Based Documentation</h3>
        <p>Documents can use predefined placeholders such as client name, project name, service details, amount, dates and other project information. This enables consistent and efficient document generation.</p>
    </div>
    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>

<!-- PAGE 4 — MODULE 4 -->
<div class=\"page\">
    <div class=\"header\">
        <img src=\"asset/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">
    </div>
    <div class=\"module-label\">MODULE 04</div>
    <h1 class=\"section-title\">Project &amp; <span>Task Management</span></h1>
    <div class=\"content\">
        <p>The Project &amp; Task Management module manages the execution stage after a client requirement has been approved. The system allows the Development Team Lead to convert project requirements into actionable tasks and assign them to the appropriate team members.</p>
    </div>
    <h2 class=\"sub-title\">Project Structure</h2>
    <div class=\"module-grid\">
        <div class=\"module-card\"><h3>Client</h3><p>Central client record containing the client\'s projects, requirements and related information.</p></div>
        <div class=\"module-card\"><h3>Project</h3><p>Represents a specific service or engagement being delivered to the client.</p></div>
        <div class=\"module-card\"><h3>Task</h3><p>Individual pieces of work created from the project requirement.</p></div>
        <div class=\"module-card\"><h3>Subtask</h3><p>Smaller activities required to complete a larger task.</p></div>
    </div>
    <h2 class=\"sub-title\">Development Workflow</h2>
    <div class=\"workflow\">
        <div class=\"workflow-item\"><div class=\"workflow-circle\">01</div><h4>Requirement</h4></div><div class=\"workflow-arrow\">→</div>
        <div class=\"workflow-item\"><div class=\"workflow-circle\">02</div><h4>Lead Creates Task</h4></div><div class=\"workflow-arrow\">→</div>
        <div class=\"workflow-item\"><div class=\"workflow-circle\">03</div><h4>Assignment</h4></div><div class=\"workflow-arrow\">→</div>
        <div class=\"workflow-item\"><div class=\"workflow-circle\">04</div><h4>Development</h4></div><div class=\"workflow-arrow\">→</div>
        <div class=\"workflow-item\"><div class=\"workflow-circle\">05</div><h4>Review</h4></div>
    </div>
    <h2 class=\"sub-title\">Task Status</h2>
    <div class=\"status-grid\">
        <div class=\"status\">Pending</div>
        <div class=\"status active\">In Progress</div>
        <div class=\"status active\">Under Review</div>
        <div class=\"status active\">Client Approval</div>
        <div class=\"status\">Revision Required</div>
        <div class=\"status completed\">Completed</div>
    </div>
    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>

<!-- PAGE 5 — CLIENT DASHBOARD -->
<div class=\"page\">
    <div class=\"header\">
        <img src=\"asset/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">
    </div>
    <div class=\"module-label\">CLIENT MODULE</div>
    <h1 class=\"section-title\">Client <span>Dashboard</span></h1>
    <div class=\"content\">
        <p>A dedicated Client Dashboard will provide clients with a secure and transparent view of their projects and work progress.</p>
        <p>Clients will only be able to access their own dashboard, projects, tasks, documents, meetings and approval requests. Internal agency information will remain restricted.</p>
    </div>
    <div class=\"dashboard-box\">
        <div class=\"dashboard-header\">Client Dashboard</div>
        <div class=\"dashboard-grid\">
            <div class=\"dashboard-card\"><strong>Active Projects</strong><span>View current projects and overall progress.</span></div>
            <div class=\"dashboard-card\"><strong>Task Status</strong><span>See where each task currently stands.</span></div>
            <div class=\"dashboard-card\"><strong>Project Timeline</strong><span>Track the progress of project activities.</span></div>
            <div class=\"dashboard-card\"><strong>Pending Approvals</strong><span>Review work waiting for client approval.</span></div>
            <div class=\"dashboard-card\"><strong>Meetings</strong><span>View scheduled meetings and Google Meet links.</span></div>
            <div class=\"dashboard-card\"><strong>Documents</strong><span>Access client-specific project documents.</span></div>
        </div>
    </div>
    <h2 class=\"sub-title\">Client Approval Workflow</h2>
    <div class=\"approval-flow\">
        <div class=\"approval-step\"><strong>Development</strong><span>Work is completed by the development team.</span></div><div class=\"approval-arrow\">→</div>
        <div class=\"approval-step\"><strong>Team Review</strong><span>Team Lead reviews the completed work.</span></div><div class=\"approval-arrow\">→</div>
        <div class=\"approval-step\"><strong>Client Review</strong><span>Client reviews the submitted work.</span></div><div class=\"approval-arrow\">→</div>
        <div class=\"approval-step\"><strong>Approval / Revision</strong><span>Client approves or requests changes.</span></div>
    </div>
    <div class=\"blue-box\">
        <h3>Client-Only Access</h3>
        <p>The Client Dashboard will be completely isolated from internal agency modules. Clients will not have access to Sales, Development Management, Employee information, Reports or other client accounts.</p>
    </div>
    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>

<!-- PAGE 6 — GOOGLE MEET + SMS -->
<div class=\"page\">
    <div class=\"header\">
        <img src=\"asset/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">
    </div>
    <h1 class=\"section-title\">Client <span>Communication</span></h1>
    <h2 class=\"sub-title\">Google Meet Integration</h2>
    <div class=\"content\">
        <p>Project-related meetings can be scheduled by authorized Hemito Digital users and displayed directly within the client\'s dashboard.</p>
    </div>
    <div class=\"task-flow\">
        <div class=\"task-step\"><div class=\"task-number\">01</div><div class=\"task-name\">Meeting Created</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">02</div><div class=\"task-name\">Google Meet Link Added</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">03</div><div class=\"task-name\">Client Notification</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">04</div><div class=\"task-name\">SMS Notification</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">05</div><div class=\"task-name\">Client Joins Meeting</div></div>
    </div>
    <h2 class=\"sub-title\">SMS Notifications</h2>
    <div class=\"content\">
        <p>Important project events can trigger SMS notifications to the client\'s registered mobile number.</p>
    </div>
    <ul>
        <li>Client approval required</li>
        <li>Client approval received</li>
        <li>Revision requested</li>
        <li>Meeting scheduled</li>
        <li>Meeting updated</li>
        <li>Project completion</li>
        <li>Important project updates</li>
    </ul>
    <div class=\"blue-box\">
        <h3>Example Notification</h3>
        <p>Your project task is ready for approval. Please log in to your Client Dashboard to review the work and provide your approval.</p>
    </div>
    <h2 class=\"sub-title\">Secure Client Access</h2>
    <div class=\"content\">
        <p>SMS notifications will direct the client to the secure Client Dashboard. Authentication and server-side authorization will ensure that the client can only access information associated with their own account.</p>
    </div>
    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>

<!-- PAGE 7 — MODULE 9 -->
<div class=\"page\">
    <div class=\"header\">
        <img src=\"asset/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">
    </div>
    <div class=\"module-label\">MODULE 09</div>
    <h1 class=\"section-title\">Reports &amp; <span>Management Dashboard</span></h1>
    <div class=\"content\">
        <p>The Reports &amp; Management Dashboard provides centralized visibility into the performance of clients, projects, tasks, teams and overall agency operations.</p>
        <p>Dashboard information will be generated from actual system data to provide management with real-time operational visibility.</p>
    </div>
    <div class=\"module-grid\">
        <div class=\"module-card\"><h3>Client Overview</h3><p>Active, paused and completed client accounts.</p></div>
        <div class=\"module-card\"><h3>Project Overview</h3><p>Active, completed, delayed and on-hold projects.</p></div>
        <div class=\"module-card\"><h3>Task Overview</h3><p>Pending, active, review, approval and completed tasks.</p></div>
        <div class=\"module-card\"><h3>Team Performance</h3><p>Workload, completed tasks and pending work.</p></div>
        <div class=\"module-card\"><h3>SLA Monitoring</h3><p>On-time tasks, delayed tasks and SLA breaches.</p></div>
        <div class=\"module-card\"><h3>Sales Overview</h3><p>Leads, conversions and sales performance.</p></div>
    </div>
    <h2 class=\"sub-title\">Management Drill-Down</h2>
    <div class=\"task-flow\">
        <div class=\"task-step\"><div class=\"task-number\">01</div><div class=\"task-name\">Management Dashboard</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">02</div><div class=\"task-name\">Department / Project</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">03</div><div class=\"task-name\">Team Lead</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">04</div><div class=\"task-name\">Developer</div><div class=\"task-arrow\">→</div></div>
        <div class=\"task-step\"><div class=\"task-number\">05</div><div class=\"task-name\">Individual Task</div></div>
    </div>
    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>

<!-- PAGE 8 — ACCESS & TECHNOLOGY -->
<div class=\"page\">
    <div class=\"header\">
        <img src=\"asset/hemitologo-dark-blue.webp\" alt=\"Hemito Digital\">
    </div>
    <h1 class=\"section-title\">Access Control &amp; <span>Technology</span></h1>
    <div class=\"content\">
        <p>The platform will use role-based and module-level access control so that each user can access only the functionality relevant to their responsibilities.</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>Role</th>
                <th>Primary Access</th>
            </tr>
        </thead>
        <tbody>
            <tr><td><strong>Super Admin</strong></td><td>Complete system access</td></tr>
            <tr><td><strong>Agency Admin</strong></td><td>Agency-wide operational access</td></tr>
            <tr><td><strong>Sales / Account Manager</strong></td><td>Sales, clients and project information</td></tr>
            <tr><td><strong>Development Team Lead</strong></td><td>Projects, tasks, assignments and reviews</td></tr>
            <tr><td><strong>Developer</strong></td><td>Assigned tasks and development activities</td></tr>
            <tr><td><strong>Management</strong></td><td>Reports and management dashboards</td></tr>
            <tr><td><strong>Client</strong></td><td>Client Dashboard only</td></tr>
        </tbody>
    </table>
    <h2 class=\"sub-title\">Technology Stack</h2>
    <div class=\"tech-grid\">
        <div class=\"tech-item\">PHP</div>
        <div class=\"tech-item\">MySQL</div>
        <div class=\"tech-item\">HTML5</div>
        <div class=\"tech-item\">CSS3</div>
        <div class=\"tech-item\">JavaScript</div>
        <div class=\"tech-item\">AJAX</div>
        <div class=\"tech-item\">Bootstrap</div>
        <div class=\"tech-item\">PDO</div>
    </div>
    <h2 class=\"sub-title\">Security</h2>
    <ul>
        <li>Role-based authentication</li>
        <li>Module-level authorization</li>
        <li>Client-specific data isolation</li>
        <li>Secure session management</li>
        <li>Prepared SQL statements</li>
        <li>Secure file access</li>
        <li>Activity and audit logging</li>
        <li>Client approval history</li>
    </ul>
    <div class=\"blue-box\">
        <h3>Outcome</h3>
        <p>The platform will provide Hemito Digital with a centralized workflow for managing clients, projects, development tasks, approvals and management reporting while providing clients with transparent and secure visibility into their work.</p>
    </div>
    <div class=\"footer\">www.hemitodigital.com | sales@hemitodigital.com | +91-8921992187</div>
</div>
</div>', '[\"CLIENT_COMPANY\",\"CLIENT_CONTACT\",\"SERVICE_NAME\",\"PACKAGE_NAME\",\"SERVICE_PRICE\",\"TOTAL_AMOUNT\",\"AGENCY_NAME\",\"CUSTOM_SCOPE\"]', 1, 1, '2026-10-06 08:18:08', '2026-10-06 08:18:08'),
(2, 'Invoice', 'Official Tax Invoice Template', 'Tax Invoice {{DOCUMENT_NUMBER}} - {{CLIENT_COMPANY}}', '<div style=\"font-family: \'Segoe UI\', Helvetica, Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border: 1px solid #e2e8f0; border-radius: 8px;\">
  <div style=\"display: flex; justify-content: space-between; align-items: start; margin-bottom: 30px;\">
    <div>
      <h1 style=\"color: #0f172a; margin: 0; font-size: 28px; font-weight: 800;\">TAX INVOICE</h1>
      <p style=\"color: #2563eb; margin: 4px 0 0 0; font-size: 14px; font-weight: 700;\">{{AGENCY_NAME}}</p>
      <p style=\"color: #64748b; margin: 4px 0 0 0; font-size: 12px; line-height: 1.4;\">{{AGENCY_ADDRESS}}<br>GSTIN: {{AGENCY_TAX_ID}}</p>
    </div>
    <div style=\"text-align: right; background: #f8fafc; padding: 16px; border-radius: 8px; border: 1px solid #e2e8f0;\">
      <p style=\"margin: 0; font-size: 13px; color: #475569;\"><strong>Invoice No:</strong> <span style=\"color:#2563eb; font-weight:700;\">{{DOCUMENT_NUMBER}}</span></p>
      <p style=\"margin: 6px 0 0 0; font-size: 13px; color: #475569;\"><strong>Invoice Date:</strong> {{CURRENT_DATE}}</p>
      <p style=\"margin: 6px 0 0 0; font-size: 13px; color: #dc2626;\"><strong>Due Date:</strong> {{DUE_DATE}}</p>
    </div>
  </div>

  <div style=\"display: flex; justify-content: space-between; background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 30px;\">
    <div>
      <h4 style=\"margin: 0 0 6px 0; color: #64748b; font-size: 11px; text-transform: uppercase;\">BILLED TO:</h4>
      <p style=\"margin: 0; font-size: 16px; font-weight: 700; color: #0f172a;\">{{CLIENT_COMPANY}}</p>
      <p style=\"margin: 4px 0 0 0; font-size: 13px; color: #475569;\">Attn: {{CLIENT_CONTACT}}</p>
      <p style=\"margin: 2px 0 0 0; font-size: 13px; color: #475569;\">Email: {{CLIENT_EMAIL}} | Phone: {{CLIENT_PHONE}}</p>
    </div>
    <div style=\"text-align: right;\">
      <h4 style=\"margin: 0 0 6px 0; color: #64748b; font-size: 11px; text-transform: uppercase;\">CLIENT GST / TAX ID:</h4>
      <p style=\"margin: 0; font-size: 14px; font-weight: 600; color: #1e293b;\">{{CLIENT_TAX_ID}}</p>
    </div>
  </div>

  <table style=\"width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 14px;\">
    <thead>
      <tr style=\"background: #2563eb; color: #ffffff; text-align: left;\">
        <th style=\"padding: 12px 16px;\">Item Description</th>
        <th style=\"padding: 12px 16px; text-align: center;\">Qty</th>
        <th style=\"padding: 12px 16px; text-align: right;\">Rate ({{CURRENCY}})</th>
        <th style=\"padding: 12px 16px; text-align: right;\">Amount ({{CURRENCY}})</th>
      </tr>
    </thead>
    <tbody>
      <tr style=\"border-bottom: 1px solid #e2e8f0;\">
        <td style=\"padding: 14px 16px;\">
          <strong>{{SERVICE_NAME}}</strong>
          <p style=\"margin: 4px 0 0 0; font-size: 12px; color: #64748b;\">{{PACKAGE_NAME}} - Professional Agency Services</p>
        </td>
        <td style=\"padding: 14px 16px; text-align: center;\">1</td>
        <td style=\"padding: 14px 16px; text-align: right;\">₹ {{SERVICE_PRICE}}</td>
        <td style=\"padding: 14px 16px; text-align: right; font-weight: 600;\">₹ {{SERVICE_PRICE}}</td>
      </tr>
    </tbody>
  </table>

  <div style=\"display: flex; justify-content: flex-end; margin-bottom: 30px;\">
    <div style=\"width: 320px;\">
      <div style=\"display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #475569;\">
        <span>Subtotal:</span>
        <span style=\"font-weight: 600; color: #1e293b;\">₹ {{SERVICE_PRICE}}</span>
      </div>
      <div style=\"display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e2e8f0; font-size: 14px; color: #475569;\">
        <span>CGST (9%) + SGST (9%):</span>
        <span style=\"font-weight: 600; color: #1e293b;\">₹ {{TAX_AMOUNT}}</span>
      </div>
      <div style=\"display: flex; justify-content: space-between; padding: 12px 14px; background: #eff6ff; border-radius: 6px; font-weight: 700; color: #1e40af; font-size: 16px; margin-top: 8px;\">
        <span>TOTAL DUE:</span>
        <span>₹ {{TOTAL_AMOUNT}}</span>
      </div>
    </div>
  </div>

  <div style=\"background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; font-size: 12px; color: #475569;\">
    <p style=\"margin: 0 0 6px 0; font-weight: 700; color: #0f172a;\">BANK DETAILS FOR REMITTANCE:</p>
    <p style=\"margin: 0; line-height: 1.6;\">Account Name: {{AGENCY_NAME}}<br>Bank: HDFC Bank Ltd | Branch: Kadavanthra, Kochi<br>Account No: 50200012345678 | IFSC: HDFC0000123</p>
  </div>
</div>', '[\"DOCUMENT_NUMBER\",\"CLIENT_COMPANY\",\"CLIENT_CONTACT\",\"SERVICE_NAME\",\"SERVICE_PRICE\",\"TAX_AMOUNT\",\"TOTAL_AMOUNT\"]', 1, 1, '2026-10-06 08:18:08', '2026-10-06 08:18:08'),
(3, 'Quotation', 'Official Commercial Quotation', 'Commercial Quotation for {{CLIENT_COMPANY}}', '<div style=\"font-family: \'Segoe UI\', Helvetica, Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border-radius: 8px; border: 1px solid #cbd5e1;\">
  <div style=\"display: flex; justify-content: space-between; border-bottom: 2px solid #0f172a; padding-bottom: 16px; margin-bottom: 24px;\">
    <div>
      <h2 style=\"margin: 0; color: #0f172a; font-size: 24px; font-weight: 800;\">COMMERCIAL QUOTATION</h2>
      <p style=\"margin: 4px 0 0 0; color: #64748b; font-size: 13px;\">{{AGENCY_NAME}}</p>
    </div>
    <div style=\"text-align: right;\">
      <p style=\"margin: 0; font-size: 13px;\"><strong>Quote Ref:</strong> {{DOCUMENT_NUMBER}}</p>
      <p style=\"margin: 4px 0 0 0; font-size: 13px;\"><strong>Date:</strong> {{CURRENT_DATE}}</p>
    </div>
  </div>

  <div style=\"margin-bottom: 24px; background: #f8fafc; padding: 16px; border-radius: 6px;\">
    <h4 style=\"margin: 0 0 6px 0; color: #0f172a;\">Quotation For: {{CLIENT_COMPANY}}</h4>
    <p style=\"margin: 0; font-size: 13px; color: #475569;\">Contact Person: {{CLIENT_CONTACT}} ({{CLIENT_EMAIL}})</p>
  </div>

  <table style=\"width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 14px;\">
    <thead>
      <tr style=\"background: #f1f5f9; text-align: left;\">
        <th style=\"padding: 10px 14px; border: 1px solid #cbd5e1;\">Service Offered</th>
        <th style=\"padding: 10px 14px; border: 1px solid #cbd5e1;\">Package Tier</th>
        <th style=\"padding: 10px 14px; border: 1px solid #cbd5e1; text-align: right;\">Price ({{CURRENCY}})</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style=\"padding: 12px 14px; border: 1px solid #cbd5e1;\"><strong>{{SERVICE_NAME}}</strong></td>
        <td style=\"padding: 12px 14px; border: 1px solid #cbd5e1;\">{{PACKAGE_NAME}}</td>
        <td style=\"padding: 12px 14px; border: 1px solid #cbd5e1; text-align: right;\">₹ {{SERVICE_PRICE}}</td>
      </tr>
      <tr style=\"background: #f8fafc; font-weight: 700;\">
        <td colspan=\"2\" style=\"padding: 12px 14px; border: 1px solid #cbd5e1; text-align: right;\">Estimated Total (incl. taxes):</td>
        <td style=\"padding: 12px 14px; border: 1px solid #cbd5e1; text-align: right; color: #2563eb;\">₹ {{TOTAL_AMOUNT}}</td>
      </tr>
    </tbody>
  </table>

  <div style=\"font-size: 13px; color: #475569;\">
    <p><strong>Scope Overview:</strong> {{CUSTOM_SCOPE}}</p>
    <p><strong>Validity:</strong> Quotation valid for 15 days from date of issuance.</p>
  </div>
</div>', '[\"CLIENT_COMPANY\",\"SERVICE_NAME\",\"PACKAGE_NAME\",\"TOTAL_AMOUNT\"]', 1, 1, '2026-10-06 08:18:08', '2026-10-06 08:18:08'),
(4, 'Contract', 'Master Services Agreement (Contract)', 'Master Services Agreement - {{CLIENT_COMPANY}}', '<div style=\"font-family: \'Segoe UI\', Helvetica, Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border: 1px solid #cbd5e1; border-radius: 8px;\">
  <h2 style=\"text-align: center; color: #0f172a; font-size: 22px; border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 24px;\">MASTER SERVICES CONTRACT</h2>
  
  <p style=\"font-size: 14px; line-height: 1.6;\">This Master Services Agreement (\"Agreement\") is entered into on this date <strong>{{CURRENT_DATE}}</strong> by and between <strong>{{AGENCY_NAME}}</strong> (\"Service Provider\") and <strong>{{CLIENT_COMPANY}}</strong> (\"Client\").</p>

  <h3 style=\"font-size: 15px; color: #0f172a; margin-top: 20px;\">1. Scope of Work</h3>
  <p style=\"font-size: 13px; line-height: 1.6; color: #334155;\">Service Provider shall deliver digital agency services for <strong>{{SERVICE_NAME}}</strong> under the <strong>{{PACKAGE_NAME}}</strong> plan as detailed below:</p>
  <div style=\"background: #f8fafc; padding: 14px; border-radius: 6px; font-size: 13px; border-left: 3px solid #2563eb; margin-bottom: 16px;\">
    {{CUSTOM_SCOPE}}
  </div>

  <h3 style=\"font-size: 15px; color: #0f172a; margin-top: 20px;\">2. Financial Terms & Payment Terms</h3>
  <p style=\"font-size: 13px; line-height: 1.6; color: #334155;\">The total contract value is <strong>₹ {{TOTAL_AMOUNT}} {{CURRENCY}}</strong> (Base Fee: ₹ {{SERVICE_PRICE}}). Billing terms: <strong>{{BILLING_TERMS}}</strong>. Payment terms: <strong>{{PAYMENT_TERMS}}</strong>.</p>

  <h3 style=\"font-size: 15px; color: #0f172a; margin-top: 20px;\">3. Intellectual Property & Confidentiality</h3>
  <p style=\"font-size: 13px; line-height: 1.6; color: #334155;\">All deliverables, source files, and campaign assets developed specifically for Client shall become Client property upon full settlement of invoice fees. Both parties agree to maintain strict confidentiality of proprietary data.</p>

  <div style=\"display: flex; justify-content: space-between; margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0;\">
    <div>
      <p style=\"margin: 0; font-weight: 700;\">{{AGENCY_NAME}}</p>
      <p style=\"margin: 4px 0 0 0; font-size: 12px; color: #64748b;\">Authorized Representative Signature</p>
    </div>
    <div>
      <p style=\"margin: 0; font-weight: 700;\">{{CLIENT_COMPANY}}</p>
      <p style=\"margin: 4px 0 0 0; font-size: 12px; color: #64748b;\">Client Authorized Signatory</p>
    </div>
  </div>
</div>', '[\"CLIENT_COMPANY\",\"SERVICE_NAME\",\"PACKAGE_NAME\",\"TOTAL_AMOUNT\"]', 2, 1, '2026-10-06 08:18:08', '2026-10-07 05:47:39'),
(5, 'Service Agreement', 'Service Level Agreement (SLA)', 'SLA & Service Level Agreement - {{CLIENT_COMPANY}}', '<div style=\"font-family: \'Segoe UI\', Helvetica, Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border-radius: 8px; border: 1px solid #cbd5e1;\">
  <h2 style=\"color: #0f172a; font-size: 22px; border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 20px;\">SERVICE LEVEL AGREEMENT (SLA)</h2>
  
  <p style=\"font-size: 14px; color: #475569;\"><strong>Client:</strong> {{CLIENT_COMPANY}} | <strong>Date:</strong> {{CURRENT_DATE}} | <strong>Doc Ref:</strong> {{DOCUMENT_NUMBER}}</p>

  <h3 style=\"font-size: 15px; color: #0f172a; margin-top: 20px;\">1. Service Availability & Response Metrics</h3>
  <p style=\"font-size: 13px; line-height: 1.6; color: #334155;\">This Service Level Agreement governs technical support and deliverable maintenance for <strong>{{SERVICE_NAME}}</strong>.</p>
  <ul style=\"font-size: 13px; color: #334155; line-height: 1.8;\">
    <li><strong>Critical Priority Issues:</strong> Response within 2 hours; resolution window 12 hours.</li>
    <li><strong>Standard Maintenance & Updates:</strong> Turnaround within 24–48 hours.</li>
    <li><strong>System Uptime Target:</strong> 99.9% availability for hosted services.</li>
  </ul>

  <h3 style=\"font-size: 15px; color: #0f172a; margin-top: 20px;\">2. Maintenance & Scope Boundary</h3>
  <div style=\"background: #f8fafc; padding: 14px; border-radius: 6px; font-size: 13px; color: #334155;\">
    {{CUSTOM_SCOPE}}
  </div>
</div>', '[\"CLIENT_COMPANY\",\"SERVICE_NAME\"]', 1, 1, '2026-10-06 08:18:08', '2026-10-06 08:18:08'),
(6, 'MOM', 'Minutes of Meeting (MOM)', 'Minutes of Meeting - {{CLIENT_COMPANY}}', '<div style=\"font-family: \'Segoe UI\', Helvetica, Arial, sans-serif; color: #1e293b; max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border-radius: 8px; border: 1px solid #cbd5e1;\">
  <div style=\"border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px;\">
    <h2 style=\"margin: 0; color: #0f172a; font-size: 22px;\">MINUTES OF MEETING (MOM)</h2>
    <p style=\"margin: 4px 0 0 0; color: #64748b; font-size: 13px;\">Date: {{CURRENT_DATE}} | Meeting Ref: {{DOCUMENT_NUMBER}}</p>
  </div>

  <div style=\"background: #f8fafc; padding: 16px; border-radius: 6px; margin-bottom: 20px; font-size: 13px;\">
    <p style=\"margin: 0 0 4px 0;\"><strong>Client Company:</strong> {{CLIENT_COMPANY}}</p>
    <p style=\"margin: 0 0 4px 0;\"><strong>Attendees (Client):</strong> {{CLIENT_CONTACT}} ({{CLIENT_EMAIL}})</p>
    <p style=\"margin: 0;\"><strong>Attendees (Agency):</strong> {{AGENCY_NAME}} Team Lead & Account Manager</p>
  </div>

  <h3 style=\"font-size: 15px; color: #0f172a;\">Key Discussion Points & Action Items</h3>
  <div style=\"font-size: 13px; line-height: 1.7; color: #334155; background: #ffffff; border: 1px solid #e2e8f0; padding: 16px; border-radius: 6px;\">
    {{CUSTOM_NOTES}}
  </div>
</div>', '[\"CLIENT_COMPANY\",\"CLIENT_CONTACT\"]', 1, 1, '2026-10-06 08:18:08', '2026-10-06 08:18:08');

-- --------------------------------------------------------
-- Dumping data for table `users` (32 rows)
-- --------------------------------------------------------
INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role_id`, `client_id`, `department`, `is_active`, `created_at`, `updated_at`, `signup_token`, `work_status`, `status_note`, `status_updated_at`, `can_chat`, `can_create_group`) VALUES
(1, 'admin', '$2y$10$k7OHigmz0gH.HFMwQ3viHeN3DosyBDpA9SDiRcX7g8vrzG36ohaly', 'System Super Admin', 'admin@agency.com', 1, NULL, 'Management', 1, '2026-10-06 08:18:08', '2026-10-06 08:18:08', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(71, 'accounts', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Accounts', 'accounts@hemitodigital.com', 1, NULL, 'Admin', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(72, 'ajina', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Ajina', 'ajina@hemitodigital.com', 17, NULL, 'Development', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(73, 'albin', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Albin', 'albin@hemitodigital.com', 9, NULL, 'Creative', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(74, 'anex', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Anex', 'anex@hemitodigital.com', 18, NULL, 'Marketing', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(75, 'arun', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Arun', 'arun@hemitodigital.com', 4, NULL, 'Management', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(76, 'aswathy', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Aswathy', 'aswathy@hemitodigital.com', 19, NULL, 'Marketing', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(77, 'dheeraj', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Dheeraj', 'dheeraj@hemitodigital.com', 9, NULL, 'Creative', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(78, 'hr', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Hr', 'hr@hemitodigital.com', 16, NULL, 'HR', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(79, 'ivangeorge', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Ivangeorge', 'ivangeorge@hemitodigital.com', 1, NULL, 'Admin', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(80, 'joel', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Joel', 'joel@hemitodigital.com', 8, NULL, 'Creative', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(81, 'jossy', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Jossy', 'jossy@hemitodigital.com', 12, NULL, 'Marketing', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(82, 'niffiya', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Niffiya', 'niffiya@hemitodigital.com', 19, NULL, 'Marketing', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(83, 'prasobh', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Prasobh', 'prasobh@hemitodigital.com', 1, NULL, 'Admin', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(84, 'prince', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Prince', 'prince@hemitodigital.com', 8, NULL, 'Creative', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(85, 'production', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Production', 'production@hemitodigital.com', 11, NULL, 'CRM', 1, '2026-11-10 07:28:04', '2026-11-10 07:28:04', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(86, 'reshma.b', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Reshma B', 'reshma.b@hemitodigital.com', 20, NULL, 'Creative', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(87, 'rishikesh', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Rishikesh', 'rishikesh@hemitodigital.com', 20, NULL, 'Creative', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(88, 'sales', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Sales', 'sales@hemitodigital.com', 21, NULL, 'Sales', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(89, 'support', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Support', 'support@hemitodigital.com', 22, NULL, 'Support', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(90, 'sniya', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Sniya', 'sniya@hemito.in', 19, NULL, 'Marketing', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(91, 'fousiya', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Fousiya', 'fousiya@hemito.in', 19, NULL, 'Marketing', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(92, 'jithin', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Jithin', 'jithin@hemito.in', 19, NULL, 'Marketing', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(93, 'drupath', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Drupath', 'drupath@hemito.in', 23, NULL, 'Marketing', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(94, 'rahul.er', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Rahul Er', 'rahul.er@hemito.in', 8, NULL, 'Creative', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(95, 'anaswara', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Anaswara', 'anaswara@hemito.in', 12, NULL, 'Marketing', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(96, 'jaganath', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Jaganath', 'jaganath@hemito.in', 10, NULL, 'Creative', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(97, 'sruthi', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Sruthi', 'sruthi@hemito.in', 11, NULL, 'CRM', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(98, 'ajmal', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Ajmal', 'ajmal@hemito.in', 5, NULL, 'Development', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', '', '2026-11-11 10:45:43', 1, 1),
(99, 'george', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'George', 'george@hemito.in', 5, NULL, 'Development', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(100, 'rahul', '$2y$10$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Rahul', 'rahul@hemito.in', 8, NULL, 'Creative', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1),
(101, 'fayiz', '$2y$10$YW/QWrL1raL0zQdu0We2v.V3wrljFAEIxTw86DFj8tXpPFpZiggzi', 'Fayiz', 'fayiz@hemito.in', 24, NULL, 'Marketing', 1, '2026-11-10 07:28:05', '2026-11-10 07:28:05', NULL, 'IN_OFFICE', NULL, '2026-11-11 09:15:20', 1, 1);

-- --------------------------------------------------------
-- Dumping data for table `user_module_access` (120 rows)
-- --------------------------------------------------------
INSERT INTO `user_module_access` (`id`, `user_id`, `module_code`, `can_view`, `can_edit`, `can_delete`) VALUES
(54, 1, 'sales', 1, 1, 1),
(55, 1, 'client-management', 1, 1, 1),
(56, 1, 'template-generator', 1, 1, 1),
(57, 1, 'task-management', 1, 1, 1),
(58, 1, 'reports', 1, 1, 1),
(59, 1, 'hr-management', 1, 1, 1),
(118, 71, 'sales', 1, 1, 1),
(119, 71, 'client-management', 1, 1, 1),
(120, 71, 'template-generator', 1, 1, 1),
(121, 71, 'task-management', 1, 1, 1),
(122, 71, 'reports', 1, 1, 1),
(123, 71, 'hr-management', 1, 1, 1),
(124, 71, 'user-management', 1, 1, 1),
(125, 72, 'task-management', 1, 1, 0),
(126, 72, 'hr-management', 1, 0, 0),
(127, 73, 'task-management', 1, 1, 0),
(128, 73, 'hr-management', 1, 0, 0),
(129, 74, 'task-management', 1, 1, 1),
(130, 74, 'client-management', 1, 0, 0),
(131, 74, 'template-generator', 1, 1, 0),
(132, 74, 'reports', 1, 0, 0),
(133, 74, 'hr-management', 1, 0, 0),
(134, 75, 'task-management', 1, 1, 1),
(135, 75, 'client-management', 1, 0, 0),
(136, 75, 'template-generator', 1, 1, 0),
(137, 75, 'reports', 1, 0, 0),
(138, 75, 'hr-management', 1, 0, 0),
(139, 76, 'sales', 1, 0, 0),
(140, 76, 'client-management', 1, 0, 0),
(141, 76, 'task-management', 1, 1, 0),
(142, 76, 'reports', 1, 0, 0),
(143, 76, 'hr-management', 1, 0, 0),
(144, 77, 'task-management', 1, 1, 0),
(145, 77, 'hr-management', 1, 0, 0),
(146, 78, 'hr-management', 1, 1, 1),
(147, 78, 'reports', 1, 1, 0),
(148, 78, 'task-management', 1, 0, 0),
(149, 79, 'sales', 1, 1, 1),
(150, 79, 'client-management', 1, 1, 1),
(151, 79, 'template-generator', 1, 1, 1),
(152, 79, 'task-management', 1, 1, 1),
(153, 79, 'reports', 1, 1, 1),
(154, 79, 'hr-management', 1, 1, 1),
(155, 79, 'user-management', 1, 1, 1),
(156, 80, 'task-management', 1, 1, 0),
(157, 80, 'hr-management', 1, 0, 0),
(158, 81, 'sales', 1, 0, 0),
(159, 81, 'client-management', 1, 0, 0),
(160, 81, 'task-management', 1, 1, 0),
(161, 81, 'reports', 1, 0, 0);

INSERT INTO `user_module_access` (`id`, `user_id`, `module_code`, `can_view`, `can_edit`, `can_delete`) VALUES
(162, 81, 'hr-management', 1, 0, 0),
(163, 82, 'sales', 1, 0, 0),
(164, 82, 'client-management', 1, 0, 0),
(165, 82, 'task-management', 1, 1, 0),
(166, 82, 'reports', 1, 0, 0),
(167, 82, 'hr-management', 1, 0, 0),
(168, 83, 'sales', 1, 1, 1),
(169, 83, 'client-management', 1, 1, 1),
(170, 83, 'template-generator', 1, 1, 1),
(171, 83, 'task-management', 1, 1, 1),
(172, 83, 'reports', 1, 1, 1),
(173, 83, 'hr-management', 1, 1, 1),
(174, 83, 'user-management', 1, 1, 1),
(175, 84, 'task-management', 1, 1, 0),
(176, 84, 'hr-management', 1, 0, 0),
(177, 85, 'task-management', 1, 1, 0),
(178, 85, 'hr-management', 1, 0, 0),
(179, 86, 'task-management', 1, 1, 0),
(180, 86, 'hr-management', 1, 0, 0),
(181, 87, 'task-management', 1, 1, 0),
(182, 87, 'hr-management', 1, 0, 0),
(183, 88, 'sales', 1, 1, 1),
(184, 88, 'client-management', 1, 1, 1),
(185, 88, 'template-generator', 1, 1, 1),
(187, 88, 'hr-management', 1, 0, 0),
(188, 89, 'task-management', 1, 1, 0),
(189, 89, 'hr-management', 1, 0, 0),
(190, 90, 'sales', 1, 0, 0),
(191, 90, 'client-management', 1, 0, 0),
(192, 90, 'task-management', 1, 1, 0),
(193, 90, 'reports', 1, 0, 0),
(194, 90, 'hr-management', 1, 0, 0),
(195, 91, 'sales', 1, 0, 0),
(196, 91, 'client-management', 1, 0, 0),
(197, 91, 'task-management', 1, 1, 0),
(198, 91, 'reports', 1, 0, 0),
(199, 91, 'hr-management', 1, 0, 0),
(200, 92, 'sales', 1, 0, 0),
(201, 92, 'client-management', 1, 0, 0),
(202, 92, 'task-management', 1, 1, 0),
(203, 92, 'reports', 1, 0, 0),
(204, 92, 'hr-management', 1, 0, 0),
(205, 93, 'sales', 1, 0, 0),
(206, 93, 'client-management', 1, 0, 0),
(207, 93, 'task-management', 1, 1, 0),
(208, 93, 'reports', 1, 0, 0),
(209, 93, 'hr-management', 1, 0, 0),
(210, 94, 'task-management', 1, 1, 0),
(211, 94, 'hr-management', 1, 0, 0);

INSERT INTO `user_module_access` (`id`, `user_id`, `module_code`, `can_view`, `can_edit`, `can_delete`) VALUES
(212, 95, 'sales', 1, 0, 0),
(213, 95, 'client-management', 1, 0, 0),
(214, 95, 'task-management', 1, 1, 0),
(215, 95, 'reports', 1, 0, 0),
(216, 95, 'hr-management', 1, 0, 0),
(217, 96, 'task-management', 1, 1, 0),
(218, 96, 'hr-management', 1, 0, 0),
(219, 97, 'task-management', 1, 1, 0),
(220, 97, 'hr-management', 1, 0, 0),
(221, 98, 'task-management', 1, 1, 0),
(222, 98, 'hr-management', 1, 0, 0),
(223, 99, 'task-management', 1, 1, 0),
(224, 99, 'hr-management', 1, 0, 0),
(225, 100, 'task-management', 1, 1, 0),
(226, 100, 'hr-management', 1, 0, 0),
(227, 101, 'sales', 1, 0, 0),
(228, 101, 'client-management', 1, 0, 0),
(229, 101, 'task-management', 1, 1, 0),
(230, 101, 'reports', 1, 0, 0),
(231, 101, 'hr-management', 1, 0, 0);

-- --------------------------------------------------------
-- Dumping data for table `employees` (42 rows)
-- --------------------------------------------------------
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `first_name`, `last_name`, `full_name`, `email`, `phone`, `department_id`, `designation_id`, `reporting_manager_id`, `date_of_joining`, `date_of_birth`, `employment_type`, `employment_status`, `work_location`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relation`, `address`, `avatar_url`, `hr_notes`, `is_active`, `created_by`, `created_at`, `updated_at`, `work_status`, `status_note`, `status_updated_at`) VALUES
(1, 1, 'EMP-001', 'System', 'Super Admin', 'System Super Admin', 'admin@agency.com', '9876543201', 2, 4, NULL, '2025-01-15', '1995-06-15', 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-16 09:04:13', '2026-10-16 09:04:13', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(2, 75, 'EMP-002', 'arun', '', 'arun', 'arun@gmail.om', '9876543227', 2, 3, NULL, '2025-01-15', '1995-06-15', 'Full-time', 'Archived', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-10-16 09:04:13', '2026-10-16 09:04:13', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(3, 98, 'EMP-003', 'Ajmal', '', 'Ajmal', 'ajmalsageer56@gmail.com', '9876543228', 1, 1, NULL, '2025-01-15', '1995-06-15', 'Full-time', 'Archived', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-10-16 09:04:13', '2026-10-16 09:04:13', 'IN_OFFICE', '', '2026-11-11 10:45:43'),
(4, NULL, 'EMP-004', 'Manu', '', 'Manu', 'manu12@gmail.com', '9876543229', 3, 6, NULL, '2025-01-15', '1995-06-15', 'Full-time', 'Archived', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 0, 1, '2026-10-16 09:04:14', '2026-10-16 09:04:14', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(5, NULL, 'EMP-005', 'Neha', 'Sharma', 'Neha Sharma', 'neha.387@hemitodigital.com', '+91 98765 43210', NULL, NULL, 1, '2026-10-16', NULL, 'Full-time', 'Archived', 'Office', 'Rajesh Sharma', '+91 98765 00000', 'Father', '', NULL, 'Campus placement hire.', 0, 1, '2026-10-16 10:01:43', '2026-10-16 10:01:43', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(6, NULL, 'EMP-006', 'Neha', 'Sharma', 'Neha Sharma', 'neha.402@hemitodigital.com', '+91 98765 43210', NULL, NULL, 1, '2026-10-16', NULL, 'Full-time', 'Archived', 'Office', 'Rajesh Sharma', '+91 98765 00000', 'Father', '', NULL, 'Campus placement hire.', 0, 1, '2026-10-16 10:03:30', '2026-10-16 10:03:30', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(8, NULL, 'EMP-007', 'Aarav', 'Verma', 'Aarav Verma', 'aarav.142@hemitodigital.com', '+91 99999 88888', NULL, NULL, 1, '2026-10-16', NULL, 'Full-time', 'Archived', 'Office', '', '', '', '', NULL, '', 0, 1, '2026-10-16 10:13:08', '2026-10-16 10:13:08', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(9, NULL, 'EMP-008', 'Aarav', 'Verma', 'Aarav Verma', 'aarav.771@hemitodigital.com', '+91 99999 88888', 14, 13, 1, '2026-10-16', NULL, 'Full-time', 'Archived', 'Office', '', '', '', '', NULL, '', 0, 1, '2026-10-16 10:14:04', '2026-10-16 10:14:04', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(10, NULL, 'EMP-009', 'Neha', 'Sharma', 'Neha Sharma', 'neha.883@hemitodigital.com', '+91 98765 43210', 15, 14, 1, '2026-10-16', NULL, 'Full-time', 'Archived', 'Office', 'Rajesh Sharma', '+91 98765 00000', 'Father', '', NULL, 'Campus placement hire.', 0, 1, '2026-10-16 10:14:18', '2026-10-16 10:14:18', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(11, NULL, '123', 'test', '', 'test', 'test@gmail.com', '1223', 2, 5, 1, '2026-10-16', '2000-10-01', 'Full-time', 'Archived', 'Office', '21343', '123123', '123123', 'asdsd', NULL, '', 0, 1, '2026-10-16 10:41:50', '2026-10-16 10:41:50', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(12, 78, 'EMP-010', 'hr', '', 'hr', 'hr@gmail.com', '213213213', 2, 5, 1, '2026-10-13', '2000-10-01', 'Full-time', 'Archived', 'Office', 'test', '123123', 'tets', 'asddsad', NULL, '', 0, 1, '2026-10-16 10:46:27', '2026-10-16 10:46:27', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(13, 71, 'EMP-011', 'Accounts', '', 'Accounts', 'accounts@hemitodigital.com', NULL, 16, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(14, 72, 'EMP-012', 'Ajina', '', 'Ajina', 'ajina@hemitodigital.com', NULL, 1, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(15, 73, 'EMP-013', 'Albin', '', 'Albin', 'albin@hemitodigital.com', NULL, 17, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(16, 74, 'EMP-014', 'Anex', '', 'Anex', 'anex@hemitodigital.com', NULL, 8, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(17, NULL, 'EMP-015', 'Arun', '', 'Arun', 'arun@hemitodigital.com', NULL, 2, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(18, 76, 'EMP-016', 'Aswathy', '', 'Aswathy', 'aswathy@hemitodigital.com', NULL, 8, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(19, 77, 'EMP-017', 'Dheeraj', '', 'Dheeraj', 'dheeraj@hemitodigital.com', NULL, 17, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(20, NULL, 'EMP-018', 'Hr', '', 'Hr', 'hr@hemitodigital.com', NULL, 18, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(21, 79, 'EMP-019', 'Ivangeorge', '', 'Ivangeorge', 'ivangeorge@hemitodigital.com', NULL, 16, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(22, 80, 'EMP-020', 'Joel', '', 'Joel', 'joel@hemitodigital.com', NULL, 17, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(23, 81, 'EMP-021', 'Jossy', '', 'Jossy', 'jossy@hemitodigital.com', NULL, 8, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(24, 82, 'EMP-022', 'Niffiya', '', 'Niffiya', 'niffiya@hemitodigital.com', NULL, 8, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(25, 83, 'EMP-023', 'Prasobh', '', 'Prasobh', 'prasobh@hemitodigital.com', NULL, 16, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(26, 84, 'EMP-024', 'Prince', '', 'Prince', 'prince@hemitodigital.com', NULL, 17, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(27, 85, 'EMP-025', 'Production', '', 'Production', 'production@hemitodigital.com', NULL, 19, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(28, 86, 'EMP-026', 'Reshma', 'B', 'Reshma B', 'reshma.b@hemitodigital.com', NULL, 17, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(29, 87, 'EMP-027', 'Rishikesh', '', 'Rishikesh', 'rishikesh@hemitodigital.com', NULL, 17, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(30, 88, 'EMP-028', 'Sales', '', 'Sales', 'sales@hemitodigital.com', NULL, 3, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(31, 89, 'EMP-029', 'Support', '', 'Support', 'support@hemitodigital.com', NULL, 6, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(32, 90, 'EMP-030', 'Sniya', '', 'Sniya', 'sniya@hemito.in', NULL, 8, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(33, 91, 'EMP-031', 'Fousiya', '', 'Fousiya', 'fousiya@hemito.in', NULL, 8, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(34, 92, 'EMP-032', 'Jithin', '', 'Jithin', 'jithin@hemito.in', NULL, 8, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(35, 93, 'EMP-033', 'Drupath', '', 'Drupath', 'drupath@hemito.in', NULL, 8, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(36, 94, 'EMP-034', 'Rahul', 'Er', 'Rahul Er', 'rahul.er@hemito.in', NULL, 17, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(37, 95, 'EMP-035', 'Anaswara', '', 'Anaswara', 'anaswara@hemito.in', NULL, 8, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(38, 96, 'EMP-036', 'Jaganath', '', 'Jaganath', 'jaganath@hemito.in', NULL, 17, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(39, 97, 'EMP-037', 'Sruthi', '', 'Sruthi', 'sruthi@hemito.in', NULL, 19, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(40, NULL, 'EMP-038', 'Ajmal', '', 'Ajmal', 'ajmal@hemito.in', NULL, 1, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(41, 99, 'EMP-039', 'George', '', 'George', 'george@hemito.in', NULL, 1, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(42, 100, 'EMP-040', 'Rahul', '', 'Rahul', 'rahul@hemito.in', NULL, 17, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20'),
(43, 101, 'EMP-041', 'Fayiz', '', 'Fayiz', 'fayiz@hemito.in', NULL, 8, NULL, NULL, '2026-01-01', NULL, 'Full-time', 'Active', 'On-site', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, '2026-10-27 10:33:36', '2026-10-27 10:33:36', 'IN_OFFICE', NULL, '2026-11-11 09:15:20');

-- --------------------------------------------------------
-- Dumping data for table `leave_types` (4 rows)
-- --------------------------------------------------------
INSERT INTO `leave_types` (`id`, `name`, `code`, `default_days_per_year`, `is_paid`, `description`, `is_active`, `created_at`) VALUES
(1, 'Casual Leave', 'CL', 12, 1, 'Standard casual leave for personal reasons', 1, '2026-10-16 09:04:13'),
(2, 'Sick Leave', 'SL', 10, 1, 'Medical leave for health-related absence', 1, '2026-10-16 09:04:13'),
(3, 'Earned Leave', 'EL', 15, 1, 'Annual paid vacation leave earned by tenure', 1, '2026-10-16 09:04:13'),
(4, 'Unpaid Leave', 'UL', 0, 0, 'Leave without pay when other balances are exhausted', 1, '2026-10-16 09:04:13');

-- --------------------------------------------------------
-- Dumping data for table `leave_allocations` (44 rows)
-- --------------------------------------------------------
INSERT INTO `leave_allocations` (`id`, `employee_id`, `leave_type_id`, `year`, `total_allocated`, `used_days`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 2026, 12, 2, '2026-10-16 09:04:13', '2026-10-16 09:52:00'),
(2, 1, 2, 2026, 10, 0, '2026-10-16 09:04:13', '2026-10-16 09:04:13'),
(3, 1, 3, 2026, 15, 0, '2026-10-16 09:04:13', '2026-10-16 09:04:13'),
(4, 1, 4, 2026, 0, 0, '2026-10-16 09:04:13', '2026-10-16 09:04:13'),
(5, 2, 1, 2026, 12, 0, '2026-10-16 09:04:13', '2026-10-16 09:04:13'),
(6, 2, 2, 2026, 10, 0, '2026-10-16 09:04:13', '2026-10-16 09:04:13'),
(7, 2, 3, 2026, 15, 0, '2026-10-16 09:04:13', '2026-10-16 09:04:13'),
(8, 2, 4, 2026, 0, 0, '2026-10-16 09:04:13', '2026-10-16 09:04:13'),
(9, 3, 1, 2026, 12, 0, '2026-10-16 09:04:14', '2026-10-16 09:04:14'),
(10, 3, 2, 2026, 10, 0, '2026-10-16 09:04:14', '2026-10-16 09:04:14'),
(11, 3, 3, 2026, 15, 0, '2026-10-16 09:04:14', '2026-10-16 09:04:14'),
(12, 3, 4, 2026, 0, 0, '2026-10-16 09:04:14', '2026-10-16 09:04:14'),
(13, 4, 1, 2026, 12, 2, '2026-10-16 09:04:14', '2026-10-16 10:49:06'),
(14, 4, 2, 2026, 10, 0, '2026-10-16 09:04:14', '2026-10-16 09:04:14'),
(15, 4, 3, 2026, 15, 0, '2026-10-16 09:04:14', '2026-10-16 09:04:14'),
(16, 4, 4, 2026, 0, 0, '2026-10-16 09:04:14', '2026-10-16 09:04:14'),
(17, 5, 1, 2026, 12, 2, '2026-10-16 10:01:43', '2026-10-16 10:01:43'),
(18, 5, 2, 2026, 10, 0, '2026-10-16 10:01:43', '2026-10-16 10:01:43'),
(19, 5, 3, 2026, 15, 0, '2026-10-16 10:01:43', '2026-10-16 10:01:43'),
(20, 5, 4, 2026, 0, 0, '2026-10-16 10:01:43', '2026-10-16 10:01:43'),
(21, 6, 1, 2026, 12, 2, '2026-10-16 10:03:30', '2026-10-16 10:03:30'),
(22, 6, 2, 2026, 10, 0, '2026-10-16 10:03:30', '2026-10-16 10:03:30'),
(23, 6, 3, 2026, 15, 0, '2026-10-16 10:03:30', '2026-10-16 10:03:30'),
(24, 6, 4, 2026, 0, 0, '2026-10-16 10:03:30', '2026-10-16 10:03:30'),
(25, 8, 1, 2026, 12, 0, '2026-10-16 10:13:08', '2026-10-16 10:13:08'),
(26, 8, 2, 2026, 10, 0, '2026-10-16 10:13:08', '2026-10-16 10:13:08'),
(27, 8, 3, 2026, 15, 0, '2026-10-16 10:13:08', '2026-10-16 10:13:08'),
(28, 8, 4, 2026, 0, 0, '2026-10-16 10:13:08', '2026-10-16 10:13:08'),
(29, 9, 1, 2026, 12, 0, '2026-10-16 10:14:04', '2026-10-16 10:14:04'),
(30, 9, 2, 2026, 10, 0, '2026-10-16 10:14:04', '2026-10-16 10:14:04'),
(31, 9, 3, 2026, 15, 0, '2026-10-16 10:14:04', '2026-10-16 10:14:04'),
(32, 9, 4, 2026, 0, 0, '2026-10-16 10:14:04', '2026-10-16 10:14:04'),
(33, 10, 1, 2026, 12, 2, '2026-10-16 10:14:18', '2026-10-16 10:14:18'),
(34, 10, 2, 2026, 10, 0, '2026-10-16 10:14:18', '2026-10-16 10:14:18'),
(35, 10, 3, 2026, 15, 0, '2026-10-16 10:14:18', '2026-10-16 10:14:18'),
(36, 10, 4, 2026, 0, 0, '2026-10-16 10:14:18', '2026-10-16 10:14:18'),
(37, 11, 1, 2026, 12, 0, '2026-10-16 10:41:50', '2026-10-16 10:41:50'),
(38, 11, 2, 2026, 10, 0, '2026-10-16 10:41:50', '2026-10-16 10:41:50'),
(39, 11, 3, 2026, 15, 0, '2026-10-16 10:41:50', '2026-10-16 10:41:50'),
(40, 11, 4, 2026, 0, 0, '2026-10-16 10:41:50', '2026-10-16 10:41:50'),
(41, 12, 1, 2026, 12, 0, '2026-10-16 10:46:27', '2026-10-16 10:46:27'),
(42, 12, 2, 2026, 10, 0, '2026-10-16 10:46:27', '2026-10-16 10:46:27'),
(43, 12, 3, 2026, 15, 0, '2026-10-16 10:46:27', '2026-10-16 10:46:27'),
(44, 12, 4, 2026, 0, 0, '2026-10-16 10:46:27', '2026-10-16 10:46:27');

-- --------------------------------------------------------
-- Dumping data for table `holidays` (7 rows)
-- --------------------------------------------------------
INSERT INTO `holidays` (`id`, `holiday_name`, `holiday_date`, `description`, `is_optional`, `created_by`, `created_at`) VALUES
(1, 'New Year\'s Day', '2026-01-01', 'Celebration of the New Year', 0, NULL, '2026-10-16 09:04:13'),
(2, 'Republic Day', '2026-01-26', 'National Holiday - Republic Day', 0, NULL, '2026-10-16 09:04:13'),
(3, 'May Day / Labour Day', '2026-05-01', 'International Workers\' Day', 0, NULL, '2026-10-16 09:04:13'),
(4, 'Independence Day', '2026-08-15', 'National Holiday - Independence Day', 0, NULL, '2026-10-16 09:04:13'),
(5, 'Gandhi Jayanti', '2026-10-02', 'Mahatma Gandhi Birthday', 0, NULL, '2026-10-16 09:04:13'),
(6, 'Diwali / Deepavali', '2026-11-08', 'Festival of Lights', 0, NULL, '2026-10-16 09:04:13'),
(7, 'Christmas Day', '2026-12-25', 'Christmas Celebration', 0, NULL, '2026-10-16 09:04:13');

-- --------------------------------------------------------
-- Dumping data for table `attendance` (19 rows)
-- --------------------------------------------------------
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `check_in`, `check_out`, `total_hours`, `status`, `notes`, `is_corrected`, `corrected_by`, `correction_reason`, `ip_address`, `created_at`, `updated_at`, `section_1`, `section_2`) VALUES
(1, 1, '2026-10-16', '2026-10-16 09:30:00', NULL, 0, 'Present', 'Morning check-in', 0, NULL, NULL, NULL, '2026-10-16 09:04:13', '2026-10-16 09:04:13', 'P', 'P'),
(2, 2, '2026-10-16', '2026-10-16 09:30:00', NULL, 0, 'Present', 'Morning check-in', 0, NULL, NULL, NULL, '2026-10-16 09:04:13', '2026-10-16 09:04:13', 'P', 'P'),
(3, 3, '2026-10-16', '2026-10-16 09:30:00', NULL, 0, 'Present', 'Morning check-in', 0, NULL, NULL, NULL, '2026-10-16 09:04:14', '2026-10-16 09:04:14', 'P', 'P'),
(4, 4, '2026-10-16', '2026-10-16 09:30:00', NULL, 0, 'On Leave', 'Approved Leave: Casual Leave', 0, NULL, NULL, NULL, '2026-10-16 09:04:14', '2026-10-16 09:04:14', 'L', 'L'),
(5, 1, '2026-10-20', NULL, NULL, 0, 'On Leave', 'Approved Leave: Casual Leave', 0, NULL, NULL, NULL, '2026-10-16 09:52:00', '2026-10-16 09:52:00', 'L', 'L'),
(6, 1, '2026-10-21', NULL, NULL, 0, 'On Leave', 'Approved Leave: Casual Leave', 0, NULL, NULL, NULL, '2026-10-16 09:52:00', '2026-10-16 09:52:00', 'L', 'L'),
(7, 5, '2026-10-27', NULL, NULL, 0, 'On Leave', 'Approved Leave: Casual Leave', 0, NULL, NULL, NULL, '2026-10-16 10:01:43', '2026-10-16 10:01:43', 'L', 'L'),
(8, 5, '2026-10-28', NULL, NULL, 0, 'On Leave', 'Approved Leave: Casual Leave', 0, NULL, NULL, NULL, '2026-10-16 10:01:43', '2026-10-16 10:01:43', 'L', 'L'),
(9, 5, '2026-10-14', '2026-10-14 09:15:00', '2026-10-14 18:30:00', 9.25, 'Present', 'Manual HR Entry: Biometric machine network glitch in Morning', 1, 1, 'Biometric machine network glitch in Morning', NULL, '2026-10-16 10:01:43', '2026-10-16 10:01:43', 'P', 'P'),
(10, 6, '2026-10-27', NULL, NULL, 0, 'On Leave', 'Approved Leave: Casual Leave', 0, NULL, NULL, NULL, '2026-10-16 10:03:30', '2026-10-16 10:03:30', 'L', 'L'),
(11, 6, '2026-10-28', NULL, NULL, 0, 'On Leave', 'Approved Leave: Casual Leave', 0, NULL, NULL, NULL, '2026-10-16 10:03:30', '2026-10-16 10:03:30', 'L', 'L'),
(12, 6, '2026-10-14', '2026-10-14 09:15:00', '2026-10-14 18:30:00', 9.25, 'Present', 'Manual HR Entry: Biometric machine network glitch in Morning', 1, 1, 'Biometric machine network glitch in Morning', NULL, '2026-10-16 10:03:30', '2026-10-16 10:03:30', 'P', 'P'),
(13, 10, '2026-10-27', NULL, NULL, 0, 'On Leave', 'Approved Leave: Casual Leave', 0, NULL, NULL, NULL, '2026-10-16 10:14:18', '2026-10-16 10:14:18', 'L', 'L'),
(14, 10, '2026-10-28', NULL, NULL, 0, 'On Leave', 'Approved Leave: Casual Leave', 0, NULL, NULL, NULL, '2026-10-16 10:14:18', '2026-10-16 10:14:18', 'L', 'L'),
(15, 10, '2026-10-14', '2026-10-14 09:15:00', '2026-10-14 18:30:00', 9.25, 'Present', 'Manual HR Entry: Biometric machine network glitch in Morning', 1, 1, 'Biometric machine network glitch in Morning', NULL, '2026-10-16 10:14:19', '2026-10-16 10:14:19', 'P', 'P'),
(16, 4, '2026-10-17', NULL, NULL, 0, 'On Leave', 'Approved Leave: Casual Leave', 0, NULL, NULL, NULL, '2026-10-16 10:49:06', '2026-10-16 10:49:06', 'L', 'L'),
(17, 12, '2026-10-16', '2026-10-16 12:49:15', '2026-10-16 12:49:32', 0, 'Half Day', 'Self Web Clock-In', 0, NULL, NULL, NULL, '2026-10-16 10:49:15', '2026-10-16 10:49:32', 'P', NULL),
(18, 12, '2026-10-20', '2026-10-20 07:45:04', '2026-10-20 07:45:08', 0, 'Half Day', 'Self Web Clock-In', 0, NULL, NULL, NULL, '2026-10-20 05:45:04', '2026-10-20 05:45:08', 'P', NULL),
(19, 3, '2026-10-20', '2026-10-20 11:01:14', NULL, 0, 'Present', 'Self Web Clock-In', 0, NULL, NULL, NULL, '2026-10-20 09:01:14', '2026-10-20 09:01:14', 'P', 'P');

-- --------------------------------------------------------
-- Dumping data for table `leave_requests` (6 rows)
-- --------------------------------------------------------
INSERT INTO `leave_requests` (`id`, `employee_id`, `leave_type_id`, `start_date`, `end_date`, `total_days`, `reason`, `status`, `reviewed_by`, `reviewed_at`, `manager_comment`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-10-20', '2026-10-21', 2, 'Family function in hometown', 'Approved', 1, '2026-10-16 11:52:00', 'Approved, have a great time.', '2026-10-16 09:52:00', '2026-10-16 09:52:00'),
(2, 5, 1, '2026-10-27', '2026-10-28', 2, 'Family vacation', 'Approved', 1, '2026-10-16 12:01:43', 'Approved, enjoy the vacation.', '2026-10-16 10:01:43', '2026-10-16 10:01:43'),
(3, 6, 1, '2026-10-27', '2026-10-28', 2, 'Family vacation', 'Approved', 1, '2026-10-16 12:03:30', 'Approved, enjoy the vacation.', '2026-10-16 10:03:30', '2026-10-16 10:03:30'),
(4, 10, 1, '2026-10-27', '2026-10-28', 2, 'Family vacation', 'Approved', 1, '2026-10-16 12:14:18', 'Approved, enjoy the vacation.', '2026-10-16 10:14:18', '2026-10-16 10:14:18'),
(5, 4, 1, '2026-10-16', '2026-10-17', 2, 'for lazy', 'Approved', 1, '2026-10-16 12:49:06', 'go man enjoy', '2026-10-16 10:48:07', '2026-10-16 10:49:06'),
(6, 3, 1, '2026-11-12', '2026-11-13', 2, 'sadasd', 'Pending', NULL, NULL, NULL, '2026-11-12 05:34:58', '2026-11-12 05:34:58');

-- --------------------------------------------------------
-- Dumping data for table `presence_logs` (8 rows)
-- --------------------------------------------------------
INSERT INTO `presence_logs` (`id`, `user_id`, `employee_id`, `status`, `status_note`, `started_at`, `ended_at`, `duration_minutes`, `created_at`) VALUES
(5, 98, 3, 'TEA_BREAK', '', '2026-11-11 10:26:38', '2026-11-11 10:27:28', 1, '2026-11-11 09:26:38'),
(6, 98, 3, 'IN_OFFICE', '', '2026-11-11 10:27:28', '2026-11-11 10:27:32', 0, '2026-11-11 09:27:28'),
(7, 98, 3, 'MEETING', '', '2026-11-11 10:27:32', '2026-11-11 10:28:36', 1, '2026-11-11 09:27:32'),
(8, 98, 3, 'IN_OFFICE', '', '2026-11-11 10:28:36', '2026-11-11 10:45:15', 17, '2026-11-11 09:28:36'),
(9, 98, 3, 'TEA_BREAK', '', '2026-11-11 10:45:15', '2026-11-11 10:45:20', 0, '2026-11-11 09:45:15'),
(10, 98, 3, 'TEA_BREAK', '', '2026-11-11 10:45:20', '2026-11-11 10:45:41', 0, '2026-11-11 09:45:20'),
(11, 98, 3, 'TEA_BREAK', '', '2026-11-11 10:45:41', '2026-11-11 10:45:43', 0, '2026-11-11 09:45:41'),
(12, 98, 3, 'IN_OFFICE', '', '2026-11-11 10:45:43', NULL, 0, '2026-11-11 09:45:43');

-- --------------------------------------------------------
-- Dumping data for table `employee_documents` (1 rows)
-- --------------------------------------------------------
INSERT INTO `employee_documents` (`id`, `employee_id`, `document_title`, `category`, `file_name`, `file_path`, `file_size`, `file_type`, `is_confidential`, `uploaded_by`, `created_at`) VALUES
(1, 1, 'Executive Appointment Letter 2026', 'Appointment Letter', 'sample_offer_letter.txt', 'uploads/employee_docs/doc_emp1_1792146325_90c98044.txt', 70, 'text/plain', 1, 1, '2026-10-16 10:25:25');

-- --------------------------------------------------------
-- Dumping data for table `chat_conversations` (1 rows)
-- --------------------------------------------------------
INSERT INTO `chat_conversations` (`id`, `type`, `title`, `description`, `created_by`, `last_message_at`, `created_at`) VALUES
(1, 'direct', NULL, NULL, 98, '2026-11-12 06:19:57', '2026-11-12 06:19:57');

-- --------------------------------------------------------
-- Dumping data for table `chat_participants` (2 rows)
-- --------------------------------------------------------
INSERT INTO `chat_participants` (`id`, `conversation_id`, `user_id`, `role`, `last_read_at`, `joined_at`) VALUES
(1, 1, 98, 'member', '2026-11-12 06:19:59', '2026-11-12 05:19:57'),
(2, 1, 71, 'member', NULL, '2026-11-12 05:19:57');

SET FOREIGN_KEY_CHECKS=1;
