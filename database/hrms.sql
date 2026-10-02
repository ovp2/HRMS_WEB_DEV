-- ================================================================
-- HRMS - Human Resource Management System
-- Production Ready Clean Database Schema
-- ================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+05:30";

-- Drop database if exists and recreate
DROP DATABASE IF EXISTS `hrms`;
CREATE DATABASE `hrms` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hrms`;

-- ================================================================
-- DEPARTMENTS TABLE
-- ================================================================
CREATE TABLE `departments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `head_id` INT(11) DEFAULT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_department_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- USERS TABLE
-- ================================================================
CREATE TABLE `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `role` ENUM('admin','employee') NOT NULL DEFAULT 'employee',
    `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    `avatar` VARCHAR(255) DEFAULT NULL,
    `last_login` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- EMPLOYEES TABLE
-- ================================================================
CREATE TABLE `employees` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `employee_id` VARCHAR(20) NOT NULL,
    `first_name` VARCHAR(50) NOT NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `date_of_birth` DATE DEFAULT NULL,
    `gender` ENUM('male','female','other') DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    `city` VARCHAR(50) DEFAULT NULL,
    `state` VARCHAR(50) DEFAULT NULL,
    `zip_code` VARCHAR(10) DEFAULT NULL,
    `department_id` INT(11) DEFAULT NULL,
    `designation` VARCHAR(100) DEFAULT NULL,
    `date_of_joining` DATE NOT NULL,
    `employment_type` ENUM('full-time','part-time','contract','intern') NOT NULL DEFAULT 'full-time',
    `salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `bank_name` VARCHAR(100) DEFAULT NULL,
    `bank_account` VARCHAR(50) DEFAULT NULL,
    `ifsc_code` VARCHAR(20) DEFAULT NULL,
    `pan_number` VARCHAR(20) DEFAULT NULL,
    `avatar` VARCHAR(255) DEFAULT NULL,
    `emergency_contact_name` VARCHAR(100) DEFAULT NULL,
    `emergency_contact_phone` VARCHAR(20) DEFAULT NULL,
    `status` ENUM('active','inactive','on-leave','terminated') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_id` (`employee_id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    KEY `idx_department` (`department_id`),
    CONSTRAINT `fk_employee_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_employee_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- ATTENDANCE TABLE
-- ================================================================
CREATE TABLE `attendance` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `date` DATE NOT NULL,
    `check_in` TIME DEFAULT NULL,
    `check_out` TIME DEFAULT NULL,
    `status` ENUM('present','absent','half-day','late','on-leave') NOT NULL DEFAULT 'present',
    `work_hours` DECIMAL(4,2) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_date` (`employee_id`, `date`),
    KEY `idx_date` (`date`),
    CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- LEAVES TABLE
-- ================================================================
CREATE TABLE `leaves` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `leave_type` ENUM('sick','casual','earned','maternity','paternity','unpaid') NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `total_days` INT(11) NOT NULL DEFAULT 1,
    `reason` TEXT NOT NULL,
    `status` ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    `approved_by` INT(11) DEFAULT NULL,
    `admin_remarks` TEXT DEFAULT NULL,
    `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_employee` (`employee_id`),
    KEY `idx_status` (`status`),
    KEY `idx_dates` (`start_date`, `end_date`),
    CONSTRAINT `fk_leave_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_leave_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- PAYROLL TABLE
-- ================================================================
CREATE TABLE `payroll` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `employee_id` INT(11) NOT NULL,
    `month` INT(2) NOT NULL,
    `year` INT(4) NOT NULL,
    `basic_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `hra` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `da` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `transport_allowance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `medical_allowance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `other_allowances` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `gross_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `pf_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `tax_deduction` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `other_deductions` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `total_deductions` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `net_salary` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    `payment_status` ENUM('pending','paid','hold') NOT NULL DEFAULT 'pending',
    `payment_date` DATE DEFAULT NULL,
    `payment_method` ENUM('bank_transfer','cheque','cash') DEFAULT 'bank_transfer',
    `remarks` TEXT DEFAULT NULL,
    `generated_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_employee_month_year` (`employee_id`, `month`, `year`),
    KEY `idx_period` (`month`, `year`),
    CONSTRAINT `fk_payroll_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_payroll_generator` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- NOTIFICATIONS TABLE
-- ================================================================
CREATE TABLE `notifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `link` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_read` (`user_id`, `is_read`),
    CONSTRAINT `fk_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- INITIAL SYSTEM DATA (NO FAKE EMPLOYEES)
-- ================================================================

-- 1. Real Company Departments
INSERT INTO `departments` (`id`, `name`, `description`, `head_id`, `status`) VALUES
(1, 'Engineering', 'Software development, system architecture, and cloud infrastructure.', NULL, 'active'),
(2, 'Human Resources', 'Recruitment, employee relations, and organizational development.', NULL, 'active'),
(3, 'Finance', 'Financial planning, corporate accounting, and budget audits.', NULL, 'active'),
(4, 'Marketing', 'Digital branding, growth marketing, and communications.', NULL, 'active'),
(5, 'Sales', 'Enterprise business development and strategic client relationships.', NULL, 'active'),
(6, 'Administration', 'Facilities management, office administration, and corporate compliance.', NULL, 'active'),
(7, 'IT Support', 'Internal network infrastructure, hardware maintenance, and IT helpdesk.', NULL, 'active');

-- 2. System Administrator Account
-- Username: admin / Password: admin123
INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `status`) VALUES
(1, 'admin', '$2y$10$Q/gV2tl/maY64rmeMx1gougynZMm8/PdR3225OeyYry675jAO0jdm', 'admin@hrms.local', 'admin', 'active');

-- 3. Initial System Notification
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `is_read`, `link`) VALUES
(1, 'System Ready', 'HRMS database initialized. You can now begin adding employees in the Employees module.', 'info', 0, 'http://localhost/hrms/admin/employees.php');

COMMIT;
