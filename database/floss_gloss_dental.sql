-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Mar 31, 2026 at 11:03 AM
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
-- Database: `floss_gloss_dental`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `appointment_code` varchar(30) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `requested_date` date NOT NULL,
  `requested_start_time` time NOT NULL,
  `requested_end_time` time NOT NULL,
  `final_date` date DEFAULT NULL,
  `final_start_time` time DEFAULT NULL,
  `final_end_time` time DEFAULT NULL,
  `status` enum('pending','approved','rejected','reschedule_requested','rescheduled','cancelled','completed','no_show') NOT NULL DEFAULT 'pending',
  `payment_status` enum('not_required','pending','submitted','verified','rejected') NOT NULL DEFAULT 'pending',
  `approval_notes` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `reschedule_reason` text DEFAULT NULL,
  `patient_concern` text DEFAULT NULL,
  `created_by_patient` int(11) NOT NULL,
  `last_updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_status_history`
--

CREATE TABLE `appointment_status_history` (
  `history_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `old_status` enum('pending','approved','rejected','reschedule_requested','rescheduled','cancelled','completed','no_show') DEFAULT NULL,
  `new_status` enum('pending','approved','rejected','reschedule_requested','rescheduled','cancelled','completed','no_show') NOT NULL,
  `action_by` int(11) DEFAULT NULL,
  `action_notes` text DEFAULT NULL,
  `action_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dentist_availability`
--

CREATE TABLE `dentist_availability` (
  `availability_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `slot_limit` int(11) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dentist_profiles`
--

CREATE TABLE `dentist_profiles` (
  `dentist_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `specialization` varchar(150) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dentist_schedule_blocks`
--

CREATE TABLE `dentist_schedule_blocks` (
  `block_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `block_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dentist_services`
--

CREATE TABLE `dentist_services` (
  `dentist_service_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_notifications`
--

CREATE TABLE `email_notifications` (
  `email_notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `email_type` enum('registration','appointment_requested','appointment_approved','appointment_rejected','appointment_rescheduled','appointment_cancelled','appointment_completed','payment_verified','general') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `send_status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_notes`
--

CREATE TABLE `medical_notes` (
  `note_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `encoded_by` int(11) NOT NULL,
  `note_type` enum('medical_history','appointment_note','treatment_note','general_note') NOT NULL DEFAULT 'general_note',
  `chief_complaint` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `note_text` text NOT NULL,
  `is_confidential` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_profiles`
--

CREATE TABLE `patient_profiles` (
  `patient_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `sex` enum('male','female','prefer_not_to_say') DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','gcash','bank_transfer','card','other') DEFAULT 'other',
  `reference_number` varchar(100) DEFAULT NULL,
  `proof_image_path` varchar(255) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `verification_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verification_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 30,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_profiles`
--

CREATE TABLE `staff_profiles` (
  `staff_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_profiles`
--

INSERT INTO `staff_profiles` (`staff_id`, `user_id`, `first_name`, `middle_name`, `last_name`, `position`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'System', NULL, 'Administrator', 'System Administrator', 1, '2026-03-31 09:00:49', '2026-03-31 09:00:49');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `role` enum('patient','staff','system_admin','dentist') NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_status` enum('active','inactive','deactivated','suspended') NOT NULL DEFAULT 'active',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role`, `email`, `phone`, `password_hash`, `account_status`, `email_verified`, `created_at`, `updated_at`) VALUES
(1, 'system_admin', 'admin@flossgloss.com', '09170000000', '$2y$10$ReplaceThisWithARealGeneratedHash1234567890123456789012', 'active', 1, '2026-03-31 09:00:48', '2026-03-31 09:00:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD UNIQUE KEY `appointment_code` (`appointment_code`),
  ADD KEY `fk_appointment_created_by_patient` (`created_by_patient`),
  ADD KEY `fk_appointment_last_updated_by` (`last_updated_by`),
  ADD KEY `idx_appointments_patient` (`patient_id`),
  ADD KEY `idx_appointments_dentist` (`dentist_id`),
  ADD KEY `idx_appointments_service` (`service_id`),
  ADD KEY `idx_appointments_status` (`status`),
  ADD KEY `idx_appointments_final_date` (`final_date`),
  ADD KEY `idx_appointments_requested_date` (`requested_date`);

--
-- Indexes for table `appointment_status_history`
--
ALTER TABLE `appointment_status_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `fk_history_action_by` (`action_by`),
  ADD KEY `idx_history_appointment` (`appointment_id`);

--
-- Indexes for table `dentist_availability`
--
ALTER TABLE `dentist_availability`
  ADD PRIMARY KEY (`availability_id`),
  ADD KEY `fk_availability_dentist` (`dentist_id`),
  ADD KEY `fk_availability_created_by` (`created_by`);

--
-- Indexes for table `dentist_profiles`
--
ALTER TABLE `dentist_profiles`
  ADD PRIMARY KEY (`dentist_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `license_number` (`license_number`),
  ADD KEY `idx_dentist_last_name` (`last_name`);

--
-- Indexes for table `dentist_schedule_blocks`
--
ALTER TABLE `dentist_schedule_blocks`
  ADD PRIMARY KEY (`block_id`),
  ADD KEY `fk_schedule_block_dentist` (`dentist_id`),
  ADD KEY `fk_schedule_block_created_by` (`created_by`);

--
-- Indexes for table `dentist_services`
--
ALTER TABLE `dentist_services`
  ADD PRIMARY KEY (`dentist_service_id`),
  ADD UNIQUE KEY `uq_dentist_service` (`dentist_id`,`service_id`),
  ADD KEY `fk_dentist_services_service` (`service_id`);

--
-- Indexes for table `email_notifications`
--
ALTER TABLE `email_notifications`
  ADD PRIMARY KEY (`email_notification_id`),
  ADD KEY `fk_email_notification_appointment` (`appointment_id`),
  ADD KEY `idx_email_notifications_user` (`user_id`);

--
-- Indexes for table `medical_notes`
--
ALTER TABLE `medical_notes`
  ADD PRIMARY KEY (`note_id`),
  ADD KEY `fk_medical_note_appointment` (`appointment_id`),
  ADD KEY `fk_medical_note_encoded_by` (`encoded_by`),
  ADD KEY `idx_medical_notes_patient` (`patient_id`);

--
-- Indexes for table `patient_profiles`
--
ALTER TABLE `patient_profiles`
  ADD PRIMARY KEY (`patient_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_patient_last_name` (`last_name`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `fk_payment_verified_by` (`verified_by`),
  ADD KEY `idx_payments_appointment` (`appointment_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD UNIQUE KEY `service_name` (`service_name`),
  ADD KEY `fk_service_created_by` (`created_by`),
  ADD KEY `idx_services_active` (`is_active`);

--
-- Indexes for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `fk_staff_created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`account_status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_status_history`
--
ALTER TABLE `appointment_status_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dentist_availability`
--
ALTER TABLE `dentist_availability`
  MODIFY `availability_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dentist_profiles`
--
ALTER TABLE `dentist_profiles`
  MODIFY `dentist_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dentist_schedule_blocks`
--
ALTER TABLE `dentist_schedule_blocks`
  MODIFY `block_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dentist_services`
--
ALTER TABLE `dentist_services`
  MODIFY `dentist_service_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_notifications`
--
ALTER TABLE `email_notifications`
  MODIFY `email_notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_notes`
--
ALTER TABLE `medical_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_profiles`
--
ALTER TABLE `patient_profiles`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointment_created_by_patient` FOREIGN KEY (`created_by_patient`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointment_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `dentist_profiles` (`dentist_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointment_last_updated_by` FOREIGN KEY (`last_updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointment_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointment_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON UPDATE CASCADE;

--
-- Constraints for table `appointment_status_history`
--
ALTER TABLE `appointment_status_history`
  ADD CONSTRAINT `fk_history_action_by` FOREIGN KEY (`action_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_history_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `dentist_availability`
--
ALTER TABLE `dentist_availability`
  ADD CONSTRAINT `fk_availability_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_availability_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `dentist_profiles` (`dentist_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `dentist_profiles`
--
ALTER TABLE `dentist_profiles`
  ADD CONSTRAINT `fk_dentist_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `dentist_schedule_blocks`
--
ALTER TABLE `dentist_schedule_blocks`
  ADD CONSTRAINT `fk_schedule_block_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_schedule_block_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `dentist_profiles` (`dentist_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `dentist_services`
--
ALTER TABLE `dentist_services`
  ADD CONSTRAINT `fk_dentist_services_dentist` FOREIGN KEY (`dentist_id`) REFERENCES `dentist_profiles` (`dentist_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dentist_services_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `email_notifications`
--
ALTER TABLE `email_notifications`
  ADD CONSTRAINT `fk_email_notification_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `medical_notes`
--
ALTER TABLE `medical_notes`
  ADD CONSTRAINT `fk_medical_note_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_medical_note_encoded_by` FOREIGN KEY (`encoded_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_medical_note_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patient_profiles`
--
ALTER TABLE `patient_profiles`
  ADD CONSTRAINT `fk_patient_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payment_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `fk_service_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD CONSTRAINT `fk_staff_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
