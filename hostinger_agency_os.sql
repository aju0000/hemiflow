-- Hostinger MySQL Import SQL Script for Agency OS
-- Compatible with Hostinger MySQL / MariaDB (phpMyAdmin)

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `client_approvals`;
DROP TABLE IF EXISTS `task_reviews`;
DROP TABLE IF EXISTS `task_activities`;
DROP TABLE IF EXISTS `task_attachments`;
DROP TABLE IF EXISTS `task_comments`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `document_versions`;
DROP TABLE IF EXISTS `documents`;
DROP TABLE IF EXISTS `document_templates`;
DROP TABLE IF EXISTS `client_services`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `user_module_access`;
DROP TABLE IF EXISTS `modules`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `users`;

-- 1. Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `role_id` INT NOT NULL,
  `client_id` INT DEFAULT NULL,
  `department` VARCHAR(100) DEFAULT 'General',
  `can_chat` TINYINT DEFAULT 1,
  `can_create_group` TINYINT DEFAULT 1,
  `is_active` TINYINT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Roles Table
CREATE TABLE `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `description` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Modules Table
CREATE TABLE `modules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. User Module Access Table
CREATE TABLE `user_module_access` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `module_code` VARCHAR(50) NOT NULL,
  `can_view` TINYINT DEFAULT 1,
  `can_edit` TINYINT DEFAULT 0,
  `can_delete` TINYINT DEFAULT 0,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Clients Table
CREATE TABLE `clients` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_name` VARCHAR(200) NOT NULL,
  `contact_person` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(50),
  `address` TEXT,
  `tax_id` VARCHAR(50),
  `status` VARCHAR(50) DEFAULT 'Approved / Active',
  `notes` TEXT,
  `created_by` INT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11b. Task Assignments Table (Multiple Assignees & Observers)
CREATE TABLE `task_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role_type` VARCHAR(20) DEFAULT 'assignee',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Client Services Table
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
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Document Templates Table
CREATE TABLE `document_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `document_type` VARCHAR(50) NOT NULL,
  `template_name` VARCHAR(200) NOT NULL,
  `subject_template` VARCHAR(255),
  `content_template` LONGTEXT NOT NULL,
  `variables_json` TEXT,
  `version` INT DEFAULT 1,
  `is_active` TINYINT DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Documents Table
CREATE TABLE `documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `document_number` VARCHAR(50) NOT NULL UNIQUE,
  `client_id` INT NOT NULL,
  `service_id` INT,
  `template_id` INT,
  `document_type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `custom_fields_json` LONGTEXT,
  `content_html` LONGTEXT NOT NULL,
  `status` VARCHAR(50) DEFAULT 'Draft',
  `generated_by` INT NOT NULL,
  `version` INT DEFAULT 1,
  `handover_project_id` INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`generated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Document Versions Table
CREATE TABLE `document_versions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `document_id` INT NOT NULL,
  `version_number` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `custom_fields_json` LONGTEXT,
  `content_html` LONGTEXT NOT NULL,
  `change_summary` VARCHAR(255),
  `created_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`document_id`) REFERENCES `documents`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11b. Task Assignments Table (Multiple Assignees & Observers)
CREATE TABLE `task_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role_type` VARCHAR(20) DEFAULT 'assignee',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Projects Table
CREATE TABLE `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `service_id` INT,
  `project_name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `about_project` TEXT,
  `project_url` VARCHAR(255),
  `deliverables_summary` TEXT,
  `target_audience` TEXT,
  `start_date` DATE,
  `budget_info` VARCHAR(150),
  `priority` VARCHAR(20) DEFAULT 'Medium',
  `status` VARCHAR(50) DEFAULT 'In Progress',
  `team_lead_id` INT NOT NULL,
  `department` VARCHAR(100) DEFAULT 'Development',
  `deadline` DATE,
  `is_current` TINYINT DEFAULT 1,
  `created_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`),
  FOREIGN KEY (`team_lead_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10b. Project Competitors Details Table
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
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11b. Task Assignments Table (Multiple Assignees & Observers)
CREATE TABLE `task_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role_type` VARCHAR(20) DEFAULT 'assignee',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Tasks Table
CREATE TABLE `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_code` VARCHAR(50) NOT NULL UNIQUE,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `client_id` INT NOT NULL,
  `project_id` INT NOT NULL,
  `service_id` INT,
  `department` VARCHAR(100) DEFAULT 'Development',
  `assigned_to` INT,
  `assignees_json` TEXT DEFAULT NULL,
  `observers_json` TEXT DEFAULT NULL,
  `team_lead_id` INT NOT NULL,
  `created_by` INT NOT NULL,
  `priority` VARCHAR(20) DEFAULT 'Medium',
  `status` VARCHAR(50) DEFAULT 'PENDING',
  `start_date` DATETIME DEFAULT NULL,
  `end_date` DATETIME DEFAULT NULL,
  `deadline` DATETIME,
  `sla_hours` INT DEFAULT 24,
  `sla_start_time` DATETIME,
  `sla_due_time` DATETIME,
  `completion_time` DATETIME,
  `remarks` TEXT,
  `client_approval_required` TINYINT DEFAULT 0,
  `client_approval_status` VARCHAR(50) DEFAULT 'Not Required',
  `is_deleted` TINYINT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`),
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`),
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`),
  FOREIGN KEY (`team_lead_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11b. Task Assignments Table (Multiple Assignees & Observers)
CREATE TABLE `task_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role_type` VARCHAR(20) DEFAULT 'assignee',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Task Comments Table
CREATE TABLE `task_comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `comment_text` TEXT NOT NULL,
  `attachment_url` VARCHAR(255),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Task Activities Table
CREATE TABLE `task_activities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `old_value` VARCHAR(255),
  `new_value` VARCHAR(255),
  `remarks` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Activity Logs Table
CREATE TABLE `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT,
  `module` VARCHAR(50),
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50),
  `entity_id` INT,
  `details` TEXT,
  `ip_address` VARCHAR(50),
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Internal Employee & Group Chat Tables
CREATE TABLE `chat_conversations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `type` VARCHAR(20) NOT NULL DEFAULT 'direct',
  `title` VARCHAR(150) DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_by` INT NOT NULL,
  `last_message_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `chat_participants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `role` VARCHAR(20) DEFAULT 'member',
  `last_read_at` DATETIME DEFAULT NULL,
  `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_conv_user` (`conversation_id`, `user_id`),
  FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `chat_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `conversation_id` INT NOT NULL,
  `sender_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `message_type` VARCHAR(20) DEFAULT 'text',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`sender_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEED DATA FOR HOSTINGER (Synchronized with 32 users & departments)
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
(24, 'Performance Marketing', 'Role for Performance Marketing')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `description`=VALUES(`description`);

INSERT INTO `modules` (`id`, `code`, `name`, `description`) VALUES
(1, 'sales', 'Sales & CRM', 'Manage leads, follow-ups, and sales conversions'),
(2, 'client-management', 'Client Management', 'Manage client accounts, contracts, and onboarding'),
(3, 'template-generator', 'Template Generator', 'Generate proposals, quotes, contracts, and invoices'),
(4, 'task-management', 'Project & Task Management', 'Team Lead assignment, developer work, reviews, and SLAs'),
(5, 'reports', 'Reports & Analytics', 'Management metrics, revenue, and productivity dashboard'),
(6, 'hr-management', 'HR Management', 'Employee profiles, attendance, leaves, and documents')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

INSERT INTO `departments` (`id`, `name`, `code`, `description`, `is_active`) VALUES
(1, 'Development', 'DEV', '', 1),
(2, 'Management', 'MGMT', '', 1),
(3, 'Sales', 'SALES', '', 1),
(4, 'Design / UI/UX', 'DESIGN', '', 1),
(5, 'Analytics', 'ANLYT', '', 1),
(6, 'Support', 'SUPP', '', 1),
(8, 'Marketing', 'MKTG', '', 1),
(14, 'Cyber Security 771', 'CY771', 'Cyber', 1),
(15, 'Security Research 883', 'SEC883', 'Security Audit Team', 1),
(16, 'Admin', 'ADMI', '', 1),
(17, 'Creative', 'CREA', '', 1),
(18, 'HR', 'HR', '', 1),
(19, 'CRM', 'CRM', '', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `code`=VALUES(`code`);

-- Users Seed (All 32 users, default password: password123)
INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role_id`, `department`, `is_active`) VALUES
(1, 'admin', 'y$k7OHigmz0gH.HFMwQ3viHeN3DosyBDpA9SDiRcX7g8vrzG36ohaly', 'System Super Admin', 'admin@agency.com', 1, 'Management', 1),
(71, 'accounts', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Accounts', 'accounts@hemitodigital.com', 1, 'Admin', 1),
(72, 'ajina', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Ajina', 'ajina@hemitodigital.com', 17, 'Development', 1),
(73, 'albin', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Albin', 'albin@hemitodigital.com', 9, 'Creative', 1),
(74, 'anex', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Anex', 'anex@hemitodigital.com', 18, 'Marketing', 1),
(75, 'arun', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Arun', 'arun@hemitodigital.com', 4, 'Management', 1),
(76, 'aswathy', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Aswathy', 'aswathy@hemitodigital.com', 19, 'Marketing', 1),
(77, 'dheeraj', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Dheeraj', 'dheeraj@hemitodigital.com', 9, 'Creative', 1),
(78, 'hr', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Hr', 'hr@hemitodigital.com', 16, 'HR', 1),
(79, 'ivangeorge', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Ivangeorge', 'ivangeorge@hemitodigital.com', 1, 'Admin', 1),
(80, 'joel', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Joel', 'joel@hemitodigital.com', 8, 'Creative', 1),
(81, 'jossy', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Jossy', 'jossy@hemitodigital.com', 12, 'Marketing', 1),
(82, 'niffiya', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Niffiya', 'niffiya@hemitodigital.com', 19, 'Marketing', 1),
(83, 'prasobh', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Prasobh', 'prasobh@hemitodigital.com', 1, 'Admin', 1),
(84, 'prince', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Prince', 'prince@hemitodigital.com', 8, 'Creative', 1),
(85, 'production', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Production', 'production@hemitodigital.com', 11, 'CRM', 1),
(86, 'reshma.b', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Reshma B', 'reshma.b@hemitodigital.com', 20, 'Creative', 1),
(87, 'rishikesh', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Rishikesh', 'rishikesh@hemitodigital.com', 20, 'Creative', 1),
(88, 'sales', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Sales', 'sales@hemitodigital.com', 21, 'Sales', 1),
(89, 'support', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Support', 'support@hemitodigital.com', 22, 'Support', 1),
(90, 'sniya', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Sniya', 'sniya@hemito.in', 19, 'Marketing', 1),
(91, 'fousiya', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Fousiya', 'fousiya@hemito.in', 19, 'Marketing', 1),
(92, 'jithin', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Jithin', 'jithin@hemito.in', 19, 'Marketing', 1),
(93, 'drupath', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Drupath', 'drupath@hemito.in', 23, 'Marketing', 1),
(94, 'rahul.er', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Rahul Er', 'rahul.er@hemito.in', 8, 'Creative', 1),
(95, 'anaswara', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Anaswara', 'anaswara@hemito.in', 12, 'Marketing', 1),
(96, 'jaganath', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Jaganath', 'jaganath@hemito.in', 10, 'Creative', 1),
(97, 'sruthi', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Sruthi', 'sruthi@hemito.in', 11, 'CRM', 1),
(98, 'ajmal', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Ajmal', 'ajmal@hemito.in', 5, 'Development', 1),
(99, 'george', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'George', 'george@hemito.in', 5, 'Development', 1),
(100, 'rahul', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Rahul', 'rahul@hemito.in', 8, 'Creative', 1),
(101, 'fayiz', 'y$qCwpV5Cul0blFscx3Io8kuy6gmGnxe2IrLPFn5zqmcX035mwe.3Ce', 'Fayiz', 'fayiz@hemito.in', 24, 'Marketing', 1)
ON DUPLICATE KEY UPDATE `password_hash`=VALUES(`password_hash`), `full_name`=VALUES(`full_name`), `role_id`=VALUES(`role_id`), `department`=VALUES(`department`);

-- User Module Access
INSERT INTO `user_module_access` (`user_id`, `module_code`, `can_view`, `can_edit`, `can_delete`) VALUES
(1, 'client-management', 1, 1, 1),
(1, 'hr-management', 1, 1, 1),
(1, 'reports', 1, 1, 1),
(1, 'sales', 1, 1, 1),
(1, 'task-management', 1, 1, 1),
(1, 'template-generator', 1, 1, 1),
(71, 'client-management', 1, 1, 1),
(71, 'hr-management', 1, 1, 1),
(71, 'reports', 1, 1, 1),
(71, 'sales', 1, 1, 1),
(71, 'task-management', 1, 1, 1),
(71, 'template-generator', 1, 1, 1),
(71, 'user-management', 1, 1, 1),
(72, 'hr-management', 1, 0, 0),
(72, 'task-management', 1, 1, 0),
(73, 'hr-management', 1, 0, 0),
(73, 'task-management', 1, 1, 0),
(74, 'client-management', 1, 0, 0),
(74, 'hr-management', 1, 0, 0),
(74, 'reports', 1, 0, 0),
(74, 'task-management', 1, 1, 1),
(74, 'template-generator', 1, 1, 0),
(75, 'client-management', 1, 0, 0),
(75, 'hr-management', 1, 0, 0),
(75, 'reports', 1, 0, 0),
(75, 'task-management', 1, 1, 1),
(75, 'template-generator', 1, 1, 0),
(76, 'client-management', 1, 0, 0),
(76, 'hr-management', 1, 0, 0),
(76, 'reports', 1, 0, 0),
(76, 'sales', 1, 0, 0),
(76, 'task-management', 1, 1, 0),
(77, 'hr-management', 1, 0, 0),
(77, 'task-management', 1, 1, 0),
(78, 'hr-management', 1, 1, 1),
(78, 'reports', 1, 1, 0),
(78, 'task-management', 1, 0, 0),
(79, 'client-management', 1, 1, 1),
(79, 'hr-management', 1, 1, 1),
(79, 'reports', 1, 1, 1),
(79, 'sales', 1, 1, 1),
(79, 'task-management', 1, 1, 1),
(79, 'template-generator', 1, 1, 1),
(79, 'user-management', 1, 1, 1),
(80, 'hr-management', 1, 0, 0),
(80, 'task-management', 1, 1, 0),
(81, 'client-management', 1, 0, 0),
(81, 'hr-management', 1, 0, 0),
(81, 'reports', 1, 0, 0),
(81, 'sales', 1, 0, 0),
(81, 'task-management', 1, 1, 0),
(82, 'client-management', 1, 0, 0),
(82, 'hr-management', 1, 0, 0),
(82, 'reports', 1, 0, 0),
(82, 'sales', 1, 0, 0),
(82, 'task-management', 1, 1, 0),
(83, 'client-management', 1, 1, 1),
(83, 'hr-management', 1, 1, 1),
(83, 'reports', 1, 1, 1),
(83, 'sales', 1, 1, 1),
(83, 'task-management', 1, 1, 1),
(83, 'template-generator', 1, 1, 1),
(83, 'user-management', 1, 1, 1),
(84, 'hr-management', 1, 0, 0),
(84, 'task-management', 1, 1, 0),
(85, 'hr-management', 1, 0, 0),
(85, 'task-management', 1, 1, 0),
(86, 'hr-management', 1, 0, 0),
(86, 'task-management', 1, 1, 0),
(87, 'hr-management', 1, 0, 0),
(87, 'task-management', 1, 1, 0),
(88, 'client-management', 1, 1, 1),
(88, 'hr-management', 1, 0, 0),
(88, 'sales', 1, 1, 1),
(88, 'task-management', 1, 0, 0),
(88, 'template-generator', 1, 1, 1),
(89, 'hr-management', 1, 0, 0),
(89, 'task-management', 1, 1, 0),
(90, 'client-management', 1, 0, 0),
(90, 'hr-management', 1, 0, 0),
(90, 'reports', 1, 0, 0),
(90, 'sales', 1, 0, 0),
(90, 'task-management', 1, 1, 0),
(91, 'client-management', 1, 0, 0),
(91, 'hr-management', 1, 0, 0),
(91, 'reports', 1, 0, 0),
(91, 'sales', 1, 0, 0),
(91, 'task-management', 1, 1, 0),
(92, 'client-management', 1, 0, 0),
(92, 'hr-management', 1, 0, 0),
(92, 'reports', 1, 0, 0),
(92, 'sales', 1, 0, 0),
(92, 'task-management', 1, 1, 0),
(93, 'client-management', 1, 0, 0),
(93, 'hr-management', 1, 0, 0),
(93, 'reports', 1, 0, 0),
(93, 'sales', 1, 0, 0),
(93, 'task-management', 1, 1, 0),
(94, 'hr-management', 1, 0, 0),
(94, 'task-management', 1, 1, 0),
(95, 'client-management', 1, 0, 0),
(95, 'hr-management', 1, 0, 0),
(95, 'reports', 1, 0, 0),
(95, 'sales', 1, 0, 0),
(95, 'task-management', 1, 1, 0),
(96, 'hr-management', 1, 0, 0),
(96, 'task-management', 1, 1, 0),
(97, 'hr-management', 1, 0, 0),
(97, 'task-management', 1, 1, 0),
(98, 'hr-management', 1, 0, 0),
(98, 'task-management', 1, 1, 0),
(99, 'hr-management', 1, 0, 0),
(99, 'task-management', 1, 1, 0),
(100, 'hr-management', 1, 0, 0),
(100, 'task-management', 1, 1, 0),
(101, 'client-management', 1, 0, 0),
(101, 'hr-management', 1, 0, 0),
(101, 'reports', 1, 0, 0),
(101, 'sales', 1, 0, 0),
(101, 'task-management', 1, 1, 0);

-- Active Employees Roster
INSERT INTO `employees` (`id`, `user_id`, `employee_code`, `first_name`, `last_name`, `full_name`, `email`, `department_id`, `date_of_joining`, `employment_type`, `employment_status`, `work_location`, `is_active`) VALUES
(1, 1, 'EMP-001', 'System', 'Super Admin', 'System Super Admin', 'admin@agency.com', 2, '2025-01-15', 'Full-time', 'Active', 'On-site', 1),
(13, 71, 'EMP-011', 'Accounts', '', 'Accounts', 'accounts@hemitodigital.com', 16, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(14, 72, 'EMP-012', 'Ajina', '', 'Ajina', 'ajina@hemitodigital.com', 1, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(15, 73, 'EMP-013', 'Albin', '', 'Albin', 'albin@hemitodigital.com', 17, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(16, 74, 'EMP-014', 'Anex', '', 'Anex', 'anex@hemitodigital.com', 8, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(17, NULL, 'EMP-015', 'Arun', '', 'Arun', 'arun@hemitodigital.com', 2, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(18, 76, 'EMP-016', 'Aswathy', '', 'Aswathy', 'aswathy@hemitodigital.com', 8, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(19, 77, 'EMP-017', 'Dheeraj', '', 'Dheeraj', 'dheeraj@hemitodigital.com', 17, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(20, NULL, 'EMP-018', 'Hr', '', 'Hr', 'hr@hemitodigital.com', 18, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(21, 79, 'EMP-019', 'Ivangeorge', '', 'Ivangeorge', 'ivangeorge@hemitodigital.com', 16, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(22, 80, 'EMP-020', 'Joel', '', 'Joel', 'joel@hemitodigital.com', 17, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(23, 81, 'EMP-021', 'Jossy', '', 'Jossy', 'jossy@hemitodigital.com', 8, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(24, 82, 'EMP-022', 'Niffiya', '', 'Niffiya', 'niffiya@hemitodigital.com', 8, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(25, 83, 'EMP-023', 'Prasobh', '', 'Prasobh', 'prasobh@hemitodigital.com', 16, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(26, 84, 'EMP-024', 'Prince', '', 'Prince', 'prince@hemitodigital.com', 17, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(27, 85, 'EMP-025', 'Production', '', 'Production', 'production@hemitodigital.com', 19, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(28, 86, 'EMP-026', 'Reshma', 'B', 'Reshma B', 'reshma.b@hemitodigital.com', 17, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(29, 87, 'EMP-027', 'Rishikesh', '', 'Rishikesh', 'rishikesh@hemitodigital.com', 17, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(30, 88, 'EMP-028', 'Sales', '', 'Sales', 'sales@hemitodigital.com', 3, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(31, 89, 'EMP-029', 'Support', '', 'Support', 'support@hemitodigital.com', 6, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(32, 90, 'EMP-030', 'Sniya', '', 'Sniya', 'sniya@hemito.in', 8, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(33, 91, 'EMP-031', 'Fousiya', '', 'Fousiya', 'fousiya@hemito.in', 8, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(34, 92, 'EMP-032', 'Jithin', '', 'Jithin', 'jithin@hemito.in', 8, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(35, 93, 'EMP-033', 'Drupath', '', 'Drupath', 'drupath@hemito.in', 8, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(36, 94, 'EMP-034', 'Rahul', 'Er', 'Rahul Er', 'rahul.er@hemito.in', 17, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(37, 95, 'EMP-035', 'Anaswara', '', 'Anaswara', 'anaswara@hemito.in', 8, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(38, 96, 'EMP-036', 'Jaganath', '', 'Jaganath', 'jaganath@hemito.in', 17, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(39, 97, 'EMP-037', 'Sruthi', '', 'Sruthi', 'sruthi@hemito.in', 19, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(40, NULL, 'EMP-038', 'Ajmal', '', 'Ajmal', 'ajmal@hemito.in', 1, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(41, 99, 'EMP-039', 'George', '', 'George', 'george@hemito.in', 1, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(42, 100, 'EMP-040', 'Rahul', '', 'Rahul', 'rahul@hemito.in', 17, '2026-01-01', 'Full-time', 'Active', 'On-site', 1),
(43, 101, 'EMP-041', 'Fayiz', '', 'Fayiz', 'fayiz@hemito.in', 8, '2026-01-01', 'Full-time', 'Active', 'On-site', 1)
ON DUPLICATE KEY UPDATE `user_id`=VALUES(`user_id`), `full_name`=VALUES(`full_name`);

SET FOREIGN_KEY_CHECKS=1;
