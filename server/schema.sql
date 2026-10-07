-- E-PDAMS Complete Database Schema
-- Database: prefect_db

CREATE DATABASE IF NOT EXISTS `prefect_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `prefect_db`;

-- 1. Users table (Admin, Prefect, Staff)
CREATE TABLE IF NOT EXISTS `users` (
    `user_id` INT(11) NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` VARCHAR(30) NOT NULL DEFAULT 'admin',
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Students table
CREATE TABLE IF NOT EXISTS `students` (
    `student_id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_number` VARCHAR(50) NOT NULL UNIQUE,
    `first_name` VARCHAR(50) NOT NULL,
    `middle_name` VARCHAR(50) NULL,
    `last_name` VARCHAR(50) NOT NULL,
    `gender` VARCHAR(20) NULL,
    `grade_level` VARCHAR(50) NULL,
    `section` VARCHAR(50) NULL,
    `parent_name` VARCHAR(100) NULL,
    `parent_contact` VARCHAR(50) NULL,
    `parent_email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Violation categories table
CREATE TABLE IF NOT EXISTS `violation_category` (
    `category_id` INT(11) NOT NULL AUTO_INCREMENT,
    `category_name` VARCHAR(100) NOT NULL UNIQUE,
    `severity` ENUM('Minor', 'Moderate', 'Major') NOT NULL DEFAULT 'Minor',
    `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Infraction logging table
CREATE TABLE IF NOT EXISTS `infraction_logging` (
    `infraction_id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` INT(11) NOT NULL,
    `category_id` INT(11) NOT NULL,
    `sanction_id` INT(11) NULL,
    `offense_committed` TEXT NOT NULL,
    `date_time` DATETIME NOT NULL,
    `evidence` TEXT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'Pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`infraction_id`),
    KEY `idx_infraction_student` (`student_id`),
    KEY `idx_infraction_category` (`category_id`),
    CONSTRAINT `fk_infraction_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_infraction_category` FOREIGN KEY (`category_id`) REFERENCES `violation_category` (`category_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Sanctions table (Allows multiple sanctions per violation)
CREATE TABLE IF NOT EXISTS `sanctions` (
    `sanction_id` INT(11) NOT NULL AUTO_INCREMENT,
    `infraction_id` INT(11) NOT NULL,
    `student_id` INT(11) NOT NULL,
    `sanction_name` VARCHAR(150) NOT NULL,
    `sanction_type` VARCHAR(50) NOT NULL,
    `description` TEXT NULL,
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'Pending',
    `assigned_by` INT(11) NULL,
    `completion_notes` TEXT NULL,
    `completed_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`sanction_id`),
    KEY `idx_sanctions_infraction` (`infraction_id`),
    KEY `idx_sanctions_student` (`student_id`),
    CONSTRAINT `fk_sanctions_infraction` FOREIGN KEY (`infraction_id`) REFERENCES `infraction_logging` (`infraction_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_sanctions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Incident report table
CREATE TABLE IF NOT EXISTS `incident_report` (
    `incident_id` INT(11) NOT NULL AUTO_INCREMENT,
    `report_number` VARCHAR(50) NOT NULL UNIQUE,
    `infraction_id` INT(11) NULL,
    `student_id` INT(11) NOT NULL,
    `violation` VARCHAR(150) NULL,
    `report_title` VARCHAR(200) NOT NULL,
    `incident_date` DATETIME NOT NULL,
    `incident_location` VARCHAR(150) NULL,
    `description` TEXT NOT NULL,
    `persons_involved` TEXT NULL,
    `witnesses` TEXT NULL,
    `evidence_details` TEXT NULL,
    `action_taken` TEXT NULL,
    `recommendations` TEXT NULL,
    `reported_by` INT(11) NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'Filed',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`incident_id`),
    KEY `idx_incident_student` (`student_id`),
    CONSTRAINT `fk_incident_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Clearance hold table
CREATE TABLE IF NOT EXISTS `clearance_hold` (
    `hold_id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` INT(11) NOT NULL,
    `incident_id` INT(11) NULL,
    `violation_id` INT(11) NULL,
    `reason` TEXT NOT NULL,
    `placed_by` INT(11) NULL,
    `hold_date` DATE NOT NULL,
    `expected_resolution_date` DATE NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'ON_HOLD',
    `resolution_date` DATE NULL,
    `resolution_type` VARCHAR(50) NULL,
    `resolution_notes` TEXT NULL,
    `resolved_by` INT(11) NULL,
    `release_date` DATE NULL,
    `released_date` DATE NULL,
    `release_reason` TEXT NULL,
    `release_notes` TEXT NULL,
    `released_by` INT(11) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`hold_id`),
    KEY `idx_clearance_hold_student` (`student_id`),
    KEY `idx_clearance_hold_status` (`status`),
    CONSTRAINT `fk_clearance_hold_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Parent notifications table
CREATE TABLE IF NOT EXISTS `parent_notification` (
    `notification_id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` INT(11) NOT NULL,
    `notification_type` VARCHAR(50) NOT NULL,
    `recipient` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `channel` VARCHAR(20) NOT NULL DEFAULT 'Email',
    `status` VARCHAR(20) NOT NULL DEFAULT 'Pending',
    `sent_at` DATETIME NULL,
    `error_message` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`notification_id`),
    KEY `idx_parent_notification_student` (`student_id`),
    CONSTRAINT `fk_parent_notification_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Behavior points table
CREATE TABLE IF NOT EXISTS `behavior_points` (
    `point_id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` INT(11) NOT NULL,
    `points` INT(11) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `recorded_by` INT(11) NULL,
    `date_recorded` DATE NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`point_id`),
    KEY `idx_behavior_student` (`student_id`),
    CONSTRAINT `fk_behavior_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Behavior interventions table
CREATE TABLE IF NOT EXISTS `behavior_interventions` (
    `intervention_id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` INT(11) NOT NULL,
    `intervention_type` VARCHAR(100) NOT NULL,
    `date_started` DATE NOT NULL,
    `person_responsible` VARCHAR(100) NOT NULL,
    `follow_up_date` DATE NULL,
    `description` TEXT NOT NULL,
    `outcome` TEXT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'Ongoing',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`intervention_id`),
    KEY `idx_intervention_student` (`student_id`),
    CONSTRAINT `fk_intervention_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Disciplinary schedule table
CREATE TABLE IF NOT EXISTS `disciplinary_schedule` (
    `schedule_id` INT(11) NOT NULL AUTO_INCREMENT,
    `student_id` INT(11) NOT NULL,
    `infraction_id` INT(11) NULL,
    `title` VARCHAR(150) NOT NULL,
    `hearing_date` DATE NOT NULL,
    `hearing_time` TIME NOT NULL,
    `location` VARCHAR(100) NOT NULL,
    `assigned_officer` VARCHAR(100) NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'Scheduled',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`schedule_id`),
    KEY `idx_schedule_student` (`student_id`),
    CONSTRAINT `fk_schedule_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Reformation programs table
CREATE TABLE IF NOT EXISTS `reformation_programs` (
    `program_id` INT(11) NOT NULL AUTO_INCREMENT,
    `program_name` VARCHAR(150) NOT NULL,
    `description` TEXT NOT NULL,
    `duration_hours` INT(11) NOT NULL DEFAULT 10,
    `coordinator` VARCHAR(100) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`program_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Student reformations enrollment
CREATE TABLE IF NOT EXISTS `student_reformations` (
    `enrollment_id` INT(11) NOT NULL AUTO_INCREMENT,
    `program_id` INT(11) NOT NULL,
    `student_id` INT(11) NOT NULL,
    `infraction_id` INT(11) NULL,
    `start_date` DATE NOT NULL,
    `expected_completion` DATE NULL,
    `hours_completed` INT(11) NOT NULL DEFAULT 0,
    `progress_percent` INT(11) NOT NULL DEFAULT 0,
    `status` VARCHAR(30) NOT NULL DEFAULT 'In Progress',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`enrollment_id`),
    KEY `idx_student_reformations_program` (`program_id`),
    KEY `idx_student_reformations_student` (`student_id`),
    CONSTRAINT `fk_reformation_program` FOREIGN KEY (`program_id`) REFERENCES `reformation_programs` (`program_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reformation_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. SMTP Settings table
CREATE TABLE IF NOT EXISTS `smtp_settings` (
    `setting_id` INT(11) NOT NULL AUTO_INCREMENT,
    `smtp_host` VARCHAR(150) NOT NULL DEFAULT 'smtp.gmail.com',
    `smtp_port` INT(11) NOT NULL DEFAULT 587,
    `smtp_user` VARCHAR(150) NOT NULL DEFAULT '',
    `smtp_pass` VARCHAR(255) NOT NULL DEFAULT '',
    `smtp_encryption` VARCHAR(10) NOT NULL DEFAULT 'tls',
    `sender_name` VARCHAR(100) NOT NULL DEFAULT 'E-PDAMS Prefect Office',
    `sender_email` VARCHAR(150) NOT NULL DEFAULT 'prefect@school.edu',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`setting_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default SMTP setting record
INSERT INTO `smtp_settings` (`setting_id`, `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `smtp_encryption`, `sender_name`, `sender_email`)
VALUES (1, 'smtp.gmail.com', 587, '', '', 'tls', 'E-PDAMS Prefect Office', 'prefect@school.edu')
ON DUPLICATE KEY UPDATE `setting_id` = 1;

-- Default violation categories
INSERT IGNORE INTO `violation_category` (`category_id`, `category_name`, `severity`, `status`) VALUES
(1, 'Uniform & Dress Code Violation', 'Minor', 'Active'),
(2, 'Tardiness & Habitual Lateness', 'Minor', 'Active'),
(3, 'Unauthorized Absence / Cutting Classes', 'Moderate', 'Active'),
(4, 'Classroom Disruption & Insubordination', 'Moderate', 'Active'),
(5, 'Academic Dishonesty & Cheating', 'Major', 'Active'),
(6, 'Bullying & Harassment', 'Major', 'Active'),
(7, 'Fighting & Physical Altercation', 'Major', 'Active'),
(8, 'Vandalism of School Property', 'Major', 'Active'),
(9, 'Mobile Phone Misuse during Class', 'Minor', 'Active');

-- Default administrator account (Username: admin, Password: admin12345678)
INSERT IGNORE INTO `users` (`user_id`, `username`, `password`, `full_name`, `role`, `status`) VALUES
(1, 'admin', '$2y$10$Zmsc0wucl1AvGvXvCZX.jeO3ePatt6G9B1s2vN8EXT4BWqUjwGFWq', 'System Administrator', 'admin', 'active');

-- Sample Students
INSERT IGNORE INTO `students` (`student_id`, `student_number`, `first_name`, `middle_name`, `last_name`, `gender`, `grade_level`, `section`, `parent_name`, `parent_contact`, `parent_email`, `address`, `status`) VALUES
(1, '2024-0001', 'Juan', 'Carlos', 'Dela Cruz', 'Male', 'Grade 10', 'St. Thomas', 'Maria Dela Cruz', '09171234567', 'parent.delacruz@example.com', '123 Rizal St, Manila', 'Active'),
(2, '2024-0002', 'Maria', 'Clara', 'Santos', 'Female', 'Grade 11', 'STEM-A', 'Jose Santos', '09187654321', 'parent.santos@example.com', '456 Mabini Ave, Quezon City', 'Active'),
(3, '2024-0003', 'John', 'Michael', 'Reyes', 'Male', 'Grade 9', 'St. Peter', 'Elena Reyes', '09191122334', 'parent.reyes@example.com', '789 Luna Rd, Pasig', 'Active'),
(4, '2024-0004', 'Sarah', 'Grace', 'Tan', 'Female', 'Grade 12', 'ABM-B', 'Robert Tan', '09203344556', 'parent.tan@example.com', '101 Bonifacio Blvd, Taguig', 'Active'),
(5, '2024-0005', 'Mark', 'Anthony', 'Flores', 'Male', 'Grade 8', 'St. Paul', 'Teresa Flores', '09215566778', 'parent.flores@example.com', '202 Katipunan Ave, Marikina', 'Active');

-- Sample Infractions
INSERT IGNORE INTO `infraction_logging` (`infraction_id`, `student_id`, `category_id`, `offense_committed`, `date_time`, `evidence`, `status`) VALUES
(1, 1, 2, 'Arrived 45 minutes late during morning flag ceremony without excuse slip.', NOW() - INTERVAL 5 DAY, 'Gate logbook entry #402', 'Pending'),
(2, 2, 4, 'Used loud inappropriate language during science lab period.', NOW() - INTERVAL 4 DAY, 'Teacher referral sheet from Mrs. Garcia', 'Pending'),
(3, 3, 7, 'Engaged in physical scuffle at the school cafeteria during lunch break.', NOW() - INTERVAL 3 DAY, 'Cafeteria CCTV footage clip and witness statements', 'Pending'),
(4, 5, 1, 'Incomplete school uniform: missing ID and non-regulation footwear.', NOW() - INTERVAL 1 DAY, 'Prefect morning inspection checklist', 'Resolved');

-- Sample Sanctions for Infractions
INSERT IGNORE INTO `sanctions` (`sanction_id`, `infraction_id`, `student_id`, `sanction_name`, `sanction_type`, `description`, `start_date`, `end_date`, `status`, `assigned_by`) VALUES
(1, 1, 1, 'Morning Campus Service', 'Community Service', '3 hours assisting the school library staff.', CURDATE(), CURDATE() + INTERVAL 2 DAY, 'In Progress', 1),
(2, 3, 3, 'Mandatory Guidance Counseling', 'Counseling', '3 mandatory counseling sessions with the school guidance office.', CURDATE(), CURDATE() + INTERVAL 7 DAY, 'Pending', 1),
(3, 3, 3, 'Parent Conference Requirement', 'Parent Conference', 'Formal conference with parents and Prefect of Discipline.', CURDATE(), CURDATE() + INTERVAL 3 DAY, 'Pending', 1);

-- Sample Incident Report
INSERT IGNORE INTO `incident_report` (`incident_id`, `report_number`, `infraction_id`, `student_id`, `violation`, `report_title`, `incident_date`, `incident_location`, `description`, `persons_involved`, `witnesses`, `evidence_details`, `action_taken`, `recommendations`, `reported_by`, `status`) VALUES
(1, 'IR-2026-001', 3, 3, 'Fighting & Physical Altercation', 'Cafeteria Physical Dispute', NOW() - INTERVAL 3 DAY, 'Main Cafeteria, Table 8', 'A heated verbal argument broke out during lunch over table space which escalated to shoving and punching.', 'John Michael Reyes (Grade 9), Student B (Grade 9)', 'Cafeteria staff (Mr. A. Ramos), 3 student witnesses', 'CCTV footage clip recorded at 12:15 PM, damaged cafeteria chair', 'Both students immediately brought to Prefect Office, first aid administered for minor scratches.', 'Mandatory parent conference, referral to Guidance office for anger management counseling.', 1, 'Under Investigation');

-- Sample Clearance Hold
INSERT IGNORE INTO `clearance_hold` (`hold_id`, `student_id`, `violation_id`, `reason`, `placed_by`, `hold_date`, `expected_resolution_date`, `status`, `notes`) VALUES
(1, 3, 3, 'Unresolved major physical altercation incident awaiting parent conference and guidance counseling.', 1, CURDATE() - INTERVAL 3 DAY, CURDATE() + INTERVAL 5 DAY, 'ON_HOLD', 'Hold placed automatically due to Major severity offense.');

-- Sample Behavior Points
INSERT IGNORE INTO `behavior_points` (`point_id`, `student_id`, `points`, `category`, `description`, `recorded_by`, `date_recorded`) VALUES
(1, 2, 5, 'Respectful Conduct', 'Volunteered to assist during school foundation day clean-up drive.', 1, CURDATE() - INTERVAL 10 DAY),
(2, 4, 10, 'Academic Integrity', 'Turned in lost student wallet containing cash to the prefect office.', 1, CURDATE() - INTERVAL 8 DAY),
(3, 1, -2, 'Tardiness', 'Unexcused morning tardiness.', 1, CURDATE() - INTERVAL 5 DAY),
(4, 3, -15, 'Physical Altercation', 'Involved in cafeteria scuffle.', 1, CURDATE() - INTERVAL 3 DAY);

-- Sample Reformation Programs
INSERT IGNORE INTO `reformation_programs` (`program_id`, `program_name`, `description`, `duration_hours`, `coordinator`, `status`) VALUES
(1, 'Peer Leadership & Anger Management', 'Structured counseling and group sessions aimed at conflict resolution and emotional control.', 15, 'Mrs. E. Villanueva (Guidance)', 'Active'),
(2, 'Campus Beautification & Community Service', 'Practical community service supporting campus cleanliness, library organization, and groundskeeping.', 10, 'Mr. R. Bautista (Prefect Staff)', 'Active'),
(3, 'Values Formation & Restorative Justice', 'Reflective workshops helping students understand accountability and empathy towards peers.', 12, 'Rev. D. Alcantara', 'Active');

-- Sample Student Reformation Enrollment
INSERT IGNORE INTO `student_reformations` (`enrollment_id`, `program_id`, `student_id`, `infraction_id`, `start_date`, `expected_completion`, `hours_completed`, `progress_percent`, `status`, `notes`) VALUES
(1, 1, 3, 3, CURDATE() - INTERVAL 1 DAY, CURDATE() + INTERVAL 14 DAY, 3, 20, 'In Progress', 'Attended first orientation and introductory counseling session.');
