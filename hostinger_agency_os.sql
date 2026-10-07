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

-- 10. Projects Table
CREATE TABLE `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT NOT NULL,
  `service_id` INT,
  `project_name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `priority` VARCHAR(20) DEFAULT 'Medium',
  `status` VARCHAR(50) DEFAULT 'In Progress',
  `team_lead_id` INT NOT NULL,
  `deadline` DATE,
  `created_by` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`),
  FOREIGN KEY (`team_lead_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
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
  `team_lead_id` INT NOT NULL,
  `created_by` INT NOT NULL,
  `priority` VARCHAR(20) DEFAULT 'Medium',
  `status` VARCHAR(50) DEFAULT 'PENDING',
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

-- SEED DATA FOR HOSTINGER
INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Super Admin', 'Full system access to all modules'),
(2, 'Agency Admin', 'Agency level managerial access'),
(3, 'Sales / Account Manager', 'Manages leads, clients, and document generation'),
(4, 'Team Lead', 'Manages projects, assigns tasks, reviews developer work'),
(5, 'Developer', 'Executes assigned tasks and submits work'),
(6, 'Reports Manager', 'Access to executive reports and analytics dashboard');

INSERT INTO `modules` (`id`, `code`, `name`, `description`) VALUES
(1, 'sales', 'Sales & CRM', 'Manage leads, follow-ups, and sales conversions'),
(2, 'client-management', 'Client Management', 'Manage client accounts, contracts, and onboarding'),
(3, 'template-generator', 'Template Generator', 'Generate proposals, quotes, contracts, and invoices'),
(4, 'task-management', 'Project & Task Management', 'Team Lead assignment, developer work, reviews, and SLAs'),
(5, 'reports', 'Reports & Analytics', 'Management metrics, revenue, and productivity dashboard');

-- Seed Users (Password for all: password123)
-- Password hash for 'password123': $2y$10$eE0mI7R6dI8y/Q5Cj.w3veG7/nU8iS8T7U9Xg1fW5g3yZ2K1.m5vK
INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `role_id`, `department`) VALUES
(1, 'admin', '$2y$10$eE0mI7R6dI8y/Q5Cj.w3veG7/nU8iS8T7U9Xg1fW5g3yZ2K1.m5vK', 'System Super Admin', 'admin@agency.com', 1, 'Management'),
(2, 'sales_john', '$2y$10$eE0mI7R6dI8y/Q5Cj.w3veG7/nU8iS8T7U9Xg1fW5g3yZ2K1.m5vK', 'John Sales Manager', 'john.sales@agency.com', 3, 'Sales'),
(3, 'lead_ajmal', '$2y$10$eE0mI7R6dI8y/Q5Cj.w3veG7/nU8iS8T7U9Xg1fW5g3yZ2K1.m5vK', 'Ajmal Team Lead', 'ajmal.lead@agency.com', 4, 'Development'),
(4, 'dev_rahul', '$2y$10$eE0mI7R6dI8y/Q5Cj.w3veG7/nU8iS8T7U9Xg1fW5g3yZ2K1.m5vK', 'Rahul Developer', 'rahul.dev@agency.com', 5, 'Development'),
(5, 'reports_sarah', '$2y$10$eE0mI7R6dI8y/Q5Cj.w3veG7/nU8iS8T7U9Xg1fW5g3yZ2K1.m5vK', 'Sarah Analytics Admin', 'sarah.reports@agency.com', 6, 'Analytics');

-- User Module Access
INSERT INTO `user_module_access` (`user_id`, `module_code`, `can_view`, `can_edit`, `can_delete`) VALUES
(1, 'sales', 1, 1, 1), (1, 'client-management', 1, 1, 1), (1, 'template-generator', 1, 1, 1), (1, 'task-management', 1, 1, 1), (1, 'reports', 1, 1, 1),
(2, 'sales', 1, 1, 1), (2, 'client-management', 1, 1, 1), (2, 'template-generator', 1, 1, 1),
(3, 'task-management', 1, 1, 0), (3, 'client-management', 1, 0, 0),
(4, 'task-management', 1, 1, 0),
(5, 'reports', 1, 0, 0);

-- Clients & Services
INSERT INTO `clients` (`id`, `company_name`, `contact_person`, `email`, `phone`, `address`, `tax_id`, `status`, `notes`, `created_by`) VALUES
(1, 'Futureace Healthcare Academy', 'Futureace Director', 'info@futureace.com', '+91 98950 12345', 'Kochi, Kerala, India', '32ABCDE1234F1Z9', 'Approved / Active', 'Healthcare academy seeking full digital marketing & branding.', 2),
(2, 'ABC Company', 'Rajesh Sharma', 'rajesh@abccompany.com', '+91 98765 43210', 'Suite 402, Business Tower, Mumbai, India', '27AAACA12341Z5', 'Approved / Active', 'Converted corporate client requiring full Web Dev suite.', 2);

INSERT INTO `client_services` (`id`, `client_id`, `service_name`, `package_name`, `price`, `currency`, `billing_terms`, `payment_terms`) VALUES
(1, 1, 'Digital Marketing & Social Media', 'Performance Marketing Suite', 70000.00, 'INR', 'Monthly in advance', 'Within 7 days of invoice'),
(2, 2, 'Website Development', 'Corporate Website', 150000.00, 'INR', '50% Advance, 50% on Completion', 'Net 15 days from Invoice');

SET FOREIGN_KEY_CHECKS=1;
