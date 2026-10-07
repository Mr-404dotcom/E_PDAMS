-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2026 at 03:53 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `prefect_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `behavior_interventions`
--

CREATE TABLE `behavior_interventions` (
  `intervention_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `intervention_type` varchar(100) NOT NULL,
  `date_started` date NOT NULL,
  `person_responsible` varchar(100) NOT NULL,
  `follow_up_date` date DEFAULT NULL,
  `description` text NOT NULL,
  `outcome` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Ongoing',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `behavior_points`
--

CREATE TABLE `behavior_points` (
  `point_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `date_recorded` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `behavior_points`
--

INSERT INTO `behavior_points` (`point_id`, `student_id`, `points`, `category`, `description`, `recorded_by`, `date_recorded`, `created_at`) VALUES
(1, 2, 5, 'Respectful Conduct', 'Volunteered to assist during school foundation day clean-up drive.', 1, '2026-09-20', '2026-09-30 15:05:43'),
(2, 4, 10, 'Academic Integrity', 'Turned in lost student wallet containing cash to the prefect office.', 1, '2026-09-22', '2026-09-30 15:05:43'),
(3, 1, -2, 'Tardiness', 'Unexcused morning tardiness.', 1, '2026-09-25', '2026-09-30 15:05:43'),
(4, 3, -15, 'Physical Altercation', 'Involved in cafeteria scuffle.', 1, '2026-09-27', '2026-09-30 15:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `clearance_hold`
--

CREATE TABLE `clearance_hold` (
  `hold_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `incident_id` int(11) DEFAULT NULL,
  `violation_id` int(11) DEFAULT NULL,
  `reason` text NOT NULL,
  `placed_by` int(11) DEFAULT NULL,
  `hold_date` date NOT NULL,
  `expected_resolution_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'ON_HOLD',
  `resolution_date` date DEFAULT NULL,
  `resolution_type` varchar(50) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `released_date` date DEFAULT NULL,
  `release_reason` text DEFAULT NULL,
  `release_notes` text DEFAULT NULL,
  `released_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clearance_hold`
--

INSERT INTO `clearance_hold` (`hold_id`, `student_id`, `incident_id`, `violation_id`, `reason`, `placed_by`, `hold_date`, `expected_resolution_date`, `status`, `resolution_date`, `resolution_type`, `resolution_notes`, `resolved_by`, `release_date`, `released_date`, `release_reason`, `release_notes`, `released_by`, `notes`, `created_at`, `updated_at`) VALUES
(1, 3, NULL, 3, 'Unresolved major physical altercation incident awaiting parent conference and guidance counseling.', 1, '2026-09-27', '2026-10-05', 'ON_HOLD', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Hold placed automatically due to Major severity offense.', '2026-09-30 15:05:43', '2026-09-30 15:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `disciplinary_schedule`
--

CREATE TABLE `disciplinary_schedule` (
  `schedule_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `infraction_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `hearing_date` date NOT NULL,
  `hearing_time` time NOT NULL,
  `location` varchar(100) NOT NULL,
  `assigned_officer` varchar(100) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Scheduled',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `incident_report`
--

CREATE TABLE `incident_report` (
  `incident_id` int(11) NOT NULL,
  `report_number` varchar(50) NOT NULL,
  `infraction_id` int(11) DEFAULT NULL,
  `student_id` int(11) NOT NULL,
  `violation` varchar(150) DEFAULT NULL,
  `report_title` varchar(200) NOT NULL,
  `incident_date` datetime NOT NULL,
  `incident_location` varchar(150) DEFAULT NULL,
  `description` text NOT NULL,
  `persons_involved` text DEFAULT NULL,
  `witnesses` text DEFAULT NULL,
  `evidence_details` text DEFAULT NULL,
  `action_taken` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `reported_by` int(11) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Filed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incident_report`
--

INSERT INTO `incident_report` (`incident_id`, `report_number`, `infraction_id`, `student_id`, `violation`, `report_title`, `incident_date`, `incident_location`, `description`, `persons_involved`, `witnesses`, `evidence_details`, `action_taken`, `recommendations`, `reported_by`, `status`, `created_at`) VALUES
(1, 'IR-2026-001', 3, 3, 'Fighting & Physical Altercation', 'Cafeteria Physical Dispute', '2026-09-27 23:05:43', 'Main Cafeteria, Table 8', 'A heated verbal argument broke out during lunch over table space which escalated to shoving and punching.', 'John Michael Reyes (Grade 9), Student B (Grade 9)', 'Cafeteria staff (Mr. A. Ramos), 3 student witnesses', 'CCTV footage clip recorded at 12:15 PM, damaged cafeteria chair', 'Both students immediately brought to Prefect Office, first aid administered for minor scratches.', 'Mandatory parent conference, referral to Guidance office for anger management counseling.', 1, 'Under Investigation', '2026-09-30 15:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `infraction_logging`
--

CREATE TABLE `infraction_logging` (
  `infraction_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `sanction_id` int(11) DEFAULT NULL,
  `offense_committed` text NOT NULL,
  `date_time` datetime NOT NULL,
  `evidence` text DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `infraction_logging`
--

INSERT INTO `infraction_logging` (`infraction_id`, `student_id`, `category_id`, `sanction_id`, `offense_committed`, `date_time`, `evidence`, `status`, `created_at`) VALUES
(1, 1, 2, NULL, 'Arrived 45 minutes late during morning flag ceremony without excuse slip.', '2026-09-25 23:05:43', 'Gate logbook entry #402', 'Pending', '2026-09-30 15:05:43'),
(2, 2, 4, NULL, 'Used loud inappropriate language during science lab period.', '2026-09-26 23:05:43', 'Teacher referral sheet from Mrs. Garcia', 'Pending', '2026-09-30 15:05:43'),
(3, 3, 7, NULL, 'Engaged in physical scuffle at the school cafeteria during lunch break.', '2026-09-27 23:05:43', 'Cafeteria CCTV footage clip and witness statements', 'Pending', '2026-09-30 15:05:43'),
(4, 5, 1, NULL, 'Incomplete school uniform: missing ID and non-regulation footwear.', '2026-09-29 23:05:43', 'Prefect morning inspection checklist', 'Resolved', '2026-09-30 15:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `parent_notification`
--

CREATE TABLE `parent_notification` (
  `notification_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `notification_type` varchar(50) NOT NULL,
  `recipient` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `channel` varchar(20) NOT NULL DEFAULT 'Email',
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reformation_programs`
--

CREATE TABLE `reformation_programs` (
  `program_id` int(11) NOT NULL,
  `program_name` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `duration_hours` int(11) NOT NULL DEFAULT 10,
  `coordinator` varchar(100) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reformation_programs`
--

INSERT INTO `reformation_programs` (`program_id`, `program_name`, `description`, `duration_hours`, `coordinator`, `status`, `created_at`) VALUES
(1, 'Peer Leadership & Anger Management', 'Structured counseling and group sessions aimed at conflict resolution and emotional control.', 15, 'Mrs. E. Villanueva (Guidance)', 'Active', '2026-09-30 15:05:43'),
(2, 'Campus Beautification & Community Service', 'Practical community service supporting campus cleanliness, library organization, and groundskeeping.', 10, 'Mr. R. Bautista (Prefect Staff)', 'Active', '2026-09-30 15:05:43'),
(3, 'Values Formation & Restorative Justice', 'Reflective workshops helping students understand accountability and empathy towards peers.', 12, 'Rev. D. Alcantara', 'Active', '2026-09-30 15:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `sanctions`
--

CREATE TABLE `sanctions` (
  `sanction_id` int(11) NOT NULL,
  `infraction_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `sanction_name` varchar(150) NOT NULL,
  `sanction_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `assigned_by` int(11) DEFAULT NULL,
  `completion_notes` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sanctions`
--

INSERT INTO `sanctions` (`sanction_id`, `infraction_id`, `student_id`, `sanction_name`, `sanction_type`, `description`, `start_date`, `end_date`, `status`, `assigned_by`, `completion_notes`, `completed_at`, `created_at`) VALUES
(1, 1, 1, 'Morning Campus Service', 'Community Service', '3 hours assisting the school library staff.', '2026-09-30', '2026-10-02', 'In Progress', 1, NULL, NULL, '2026-09-30 15:05:43'),
(2, 3, 3, 'Mandatory Guidance Counseling', 'Counseling', '3 mandatory counseling sessions with the school guidance office.', '2026-09-30', '2026-10-07', 'Pending', 1, NULL, NULL, '2026-09-30 15:05:43'),
(3, 3, 3, 'Parent Conference Requirement', 'Parent Conference', 'Formal conference with parents and Prefect of Discipline.', '2026-09-30', '2026-10-03', 'Pending', 1, NULL, NULL, '2026-09-30 15:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `smtp_settings`
--

CREATE TABLE `smtp_settings` (
  `setting_id` int(11) NOT NULL,
  `smtp_host` varchar(150) NOT NULL DEFAULT 'smtp.gmail.com',
  `smtp_port` int(11) NOT NULL DEFAULT 587,
  `smtp_user` varchar(150) NOT NULL DEFAULT '',
  `smtp_pass` varchar(255) NOT NULL DEFAULT '',
  `smtp_encryption` varchar(10) NOT NULL DEFAULT 'tls',
  `sender_name` varchar(100) NOT NULL DEFAULT 'E-PDAMS Prefect Office',
  `sender_email` varchar(150) NOT NULL DEFAULT 'prefect@school.edu',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `smtp_settings`
--

INSERT INTO `smtp_settings` (`setting_id`, `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `smtp_encryption`, `sender_name`, `sender_email`, `updated_at`) VALUES
(1, 'smtp.gmail.com', 587, 'justinenangcas.5@gmail.com', 'nuqh vjjf bzce vnwn', 'tls', 'E-PDAMS Prefect Office', 'prefect@school.edu', '2026-09-30 17:06:38');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `student_number` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `grade_level` varchar(50) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_contact` varchar(50) DEFAULT NULL,
  `parent_email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `student_number`, `first_name`, `middle_name`, `last_name`, `gender`, `grade_level`, `section`, `parent_name`, `parent_contact`, `parent_email`, `address`, `status`, `created_at`) VALUES
(1, '2024-0001', 'Juan', 'Carlos', 'Dela Cruz', 'Male', 'Grade 10', 'St. Thomas', 'Maria Dela Cruz', '09171234567', 'parent.delacruz@example.com', '123 Rizal St, Manila', 'Active', '2026-09-30 15:05:43'),
(2, '2024-0002', 'Maria', 'Clara', 'Santos', 'Female', 'Grade 11', 'STEM-A', 'Jose Santos', '09187654321', 'parent.santos@example.com', '456 Mabini Ave, Quezon City', 'Active', '2026-09-30 15:05:43'),
(3, '2024-0003', 'John', 'Michael', 'Reyes', 'Male', 'Grade 9', 'St. Peter', 'Elena Reyes', '09191122334', 'parent.reyes@example.com', '789 Luna Rd, Pasig', 'Active', '2026-09-30 15:05:43'),
(4, '2024-0004', 'Sarah', 'Grace', 'Tan', 'Female', 'Grade 12', 'ABM-B', 'Robert Tan', '09203344556', 'parent.tan@example.com', '101 Bonifacio Blvd, Taguig', 'Active', '2026-09-30 15:05:43'),
(5, '2024-0005', 'Mark', 'Anthony', 'Flores', 'Male', 'Grade 8', 'St. Paul', 'Teresa Flores', '09215566778', 'parent.flores@example.com', '202 Katipunan Ave, Marikina', 'Active', '2026-09-30 15:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `student_reformations`
--

CREATE TABLE `student_reformations` (
  `enrollment_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `infraction_id` int(11) DEFAULT NULL,
  `start_date` date NOT NULL,
  `expected_completion` date DEFAULT NULL,
  `hours_completed` int(11) NOT NULL DEFAULT 0,
  `progress_percent` int(11) NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'In Progress',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_reformations`
--

INSERT INTO `student_reformations` (`enrollment_id`, `program_id`, `student_id`, `infraction_id`, `start_date`, `expected_completion`, `hours_completed`, `progress_percent`, `status`, `notes`, `created_at`) VALUES
(1, 1, 3, 3, '2026-09-29', '2026-10-14', 3, 20, 'In Progress', 'Attended first orientation and introductory counseling session.', '2026-09-30 15:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'admin',
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `role`, `status`, `created_at`) VALUES
(1, 'admin', '$2y$10$Zmsc0wucl1AvGvXvCZX.jeO3ePatt6G9B1s2vN8EXT4BWqUjwGFWq', 'System Administrator', 'admin', 'active', '2026-09-30 15:05:43');

-- --------------------------------------------------------

--
-- Table structure for table `violation_category`
--

CREATE TABLE `violation_category` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `severity` enum('Minor','Moderate','Major') NOT NULL DEFAULT 'Minor',
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `violation_category`
--

INSERT INTO `violation_category` (`category_id`, `category_name`, `severity`, `status`, `created_at`) VALUES
(1, 'Uniform & Dress Code Violation', 'Minor', 'Active', '2026-09-30 15:05:43'),
(2, 'Tardiness & Habitual Lateness', 'Minor', 'Active', '2026-09-30 15:05:43'),
(3, 'Unauthorized Absence / Cutting Classes', 'Moderate', 'Active', '2026-09-30 15:05:43'),
(4, 'Classroom Disruption & Insubordination', 'Moderate', 'Active', '2026-09-30 15:05:43'),
(5, 'Academic Dishonesty & Cheating', 'Major', 'Active', '2026-09-30 15:05:43'),
(6, 'Bullying & Harassment', 'Major', 'Active', '2026-09-30 15:05:43'),
(7, 'Fighting & Physical Altercation', 'Major', 'Active', '2026-09-30 15:05:43'),
(8, 'Vandalism of School Property', 'Major', 'Active', '2026-09-30 15:05:43'),
(9, 'Mobile Phone Misuse during Class', 'Minor', 'Active', '2026-09-30 15:05:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `behavior_interventions`
--
ALTER TABLE `behavior_interventions`
  ADD PRIMARY KEY (`intervention_id`),
  ADD KEY `idx_intervention_student` (`student_id`);

--
-- Indexes for table `behavior_points`
--
ALTER TABLE `behavior_points`
  ADD PRIMARY KEY (`point_id`),
  ADD KEY `idx_behavior_student` (`student_id`);

--
-- Indexes for table `clearance_hold`
--
ALTER TABLE `clearance_hold`
  ADD PRIMARY KEY (`hold_id`),
  ADD KEY `idx_clearance_hold_student` (`student_id`),
  ADD KEY `idx_clearance_hold_status` (`status`);

--
-- Indexes for table `disciplinary_schedule`
--
ALTER TABLE `disciplinary_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `idx_schedule_student` (`student_id`);

--
-- Indexes for table `incident_report`
--
ALTER TABLE `incident_report`
  ADD PRIMARY KEY (`incident_id`),
  ADD UNIQUE KEY `report_number` (`report_number`),
  ADD KEY `idx_incident_student` (`student_id`);

--
-- Indexes for table `infraction_logging`
--
ALTER TABLE `infraction_logging`
  ADD PRIMARY KEY (`infraction_id`),
  ADD KEY `idx_infraction_student` (`student_id`),
  ADD KEY `idx_infraction_category` (`category_id`);

--
-- Indexes for table `parent_notification`
--
ALTER TABLE `parent_notification`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_parent_notification_student` (`student_id`);

--
-- Indexes for table `reformation_programs`
--
ALTER TABLE `reformation_programs`
  ADD PRIMARY KEY (`program_id`);

--
-- Indexes for table `sanctions`
--
ALTER TABLE `sanctions`
  ADD PRIMARY KEY (`sanction_id`),
  ADD KEY `idx_sanctions_infraction` (`infraction_id`),
  ADD KEY `idx_sanctions_student` (`student_id`);

--
-- Indexes for table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  ADD PRIMARY KEY (`setting_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `student_number` (`student_number`);

--
-- Indexes for table `student_reformations`
--
ALTER TABLE `student_reformations`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD KEY `idx_student_reformations_program` (`program_id`),
  ADD KEY `idx_student_reformations_student` (`student_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `violation_category`
--
ALTER TABLE `violation_category`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `behavior_interventions`
--
ALTER TABLE `behavior_interventions`
  MODIFY `intervention_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `behavior_points`
--
ALTER TABLE `behavior_points`
  MODIFY `point_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `clearance_hold`
--
ALTER TABLE `clearance_hold`
  MODIFY `hold_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `disciplinary_schedule`
--
ALTER TABLE `disciplinary_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incident_report`
--
ALTER TABLE `incident_report`
  MODIFY `incident_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `infraction_logging`
--
ALTER TABLE `infraction_logging`
  MODIFY `infraction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `parent_notification`
--
ALTER TABLE `parent_notification`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reformation_programs`
--
ALTER TABLE `reformation_programs`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sanctions`
--
ALTER TABLE `sanctions`
  MODIFY `sanction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `smtp_settings`
--
ALTER TABLE `smtp_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_reformations`
--
ALTER TABLE `student_reformations`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `violation_category`
--
ALTER TABLE `violation_category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `behavior_interventions`
--
ALTER TABLE `behavior_interventions`
  ADD CONSTRAINT `fk_intervention_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `behavior_points`
--
ALTER TABLE `behavior_points`
  ADD CONSTRAINT `fk_behavior_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `clearance_hold`
--
ALTER TABLE `clearance_hold`
  ADD CONSTRAINT `fk_clearance_hold_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `disciplinary_schedule`
--
ALTER TABLE `disciplinary_schedule`
  ADD CONSTRAINT `fk_schedule_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `incident_report`
--
ALTER TABLE `incident_report`
  ADD CONSTRAINT `fk_incident_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `infraction_logging`
--
ALTER TABLE `infraction_logging`
  ADD CONSTRAINT `fk_infraction_category` FOREIGN KEY (`category_id`) REFERENCES `violation_category` (`category_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_infraction_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `parent_notification`
--
ALTER TABLE `parent_notification`
  ADD CONSTRAINT `fk_parent_notification_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `sanctions`
--
ALTER TABLE `sanctions`
  ADD CONSTRAINT `fk_sanctions_infraction` FOREIGN KEY (`infraction_id`) REFERENCES `infraction_logging` (`infraction_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sanctions_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;

--
-- Constraints for table `student_reformations`
--
ALTER TABLE `student_reformations`
  ADD CONSTRAINT `fk_reformation_program` FOREIGN KEY (`program_id`) REFERENCES `reformation_programs` (`program_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reformation_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
