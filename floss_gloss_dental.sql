-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Apr 05, 2026 at 05:26 AM
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
  `created_by_patient` int(11) NOT NULL,
  `last_updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `appointment_code`, `patient_id`, `service_id`, `dentist_id`, `requested_date`, `requested_start_time`, `requested_end_time`, `final_date`, `final_start_time`, `final_end_time`, `status`, `payment_status`, `approval_notes`, `created_by_patient`, `last_updated_by`, `created_at`, `updated_at`) VALUES
(11, 'APP-1775340834', 1, 7, 1, '2026-04-12', '09:00:00', '10:00:00', '2026-04-13', '09:00:00', '10:00:00', 'completed', 'verified', 'Need po palitan', 2, 1, '2026-04-04 22:13:54', '2026-04-04 22:19:01'),
(12, 'APP-1775340868', 1, 13, 1, '2026-04-13', '16:00:00', '17:00:00', NULL, NULL, NULL, 'cancelled', 'pending', NULL, 2, 2, '2026-04-04 22:14:28', '2026-04-04 23:44:29'),
(13, 'APP-1775343794', 1, 9, 1, '2026-04-26', '15:00:00', '17:00:00', NULL, NULL, NULL, 'cancelled', 'pending', NULL, 2, 2, '2026-04-04 23:03:14', '2026-04-04 23:09:16'),
(14, 'APP-1775344251', 1, 12, 1, '2026-04-06', '14:00:00', '15:00:00', NULL, NULL, NULL, 'cancelled', 'pending', NULL, 2, 2, '2026-04-04 23:10:51', '2026-04-04 23:21:13'),
(15, 'APP-1775344928', 1, 13, 1, '2026-04-30', '16:00:00', '17:00:00', NULL, NULL, NULL, 'cancelled', 'pending', NULL, 2, 2, '2026-04-04 23:22:08', '2026-04-04 23:44:31'),
(16, 'APP-1775347380', 1, 12, 1, '2026-04-30', '09:00:00', '10:00:00', NULL, NULL, NULL, 'pending', 'pending', NULL, 2, NULL, '2026-04-05 00:03:00', '2026-04-05 00:03:00');

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

--
-- Dumping data for table `appointment_status_history`
--

INSERT INTO `appointment_status_history` (`history_id`, `appointment_id`, `old_status`, `new_status`, `action_by`, `action_notes`, `action_at`) VALUES
(16, 11, 'pending', 'approved', 1, 'Appointment approved by admin/staff', '2026-04-04 22:15:55'),
(17, 12, 'pending', 'approved', 1, 'Appointment approved by admin/staff', '2026-04-04 22:15:59'),
(18, 11, 'approved', 'approved', 1, 'Need po palitan', '2026-04-04 22:16:49'),
(19, 11, 'approved', 'completed', 1, 'Appointment marked as completed by admin/staff', '2026-04-04 22:18:57'),
(20, 13, 'pending', 'approved', 1, 'Appointment approved by admin/staff', '2026-04-04 23:04:39'),
(21, 13, 'approved', 'cancelled', 2, 'Appointment cancelled by patient', '2026-04-04 23:09:16'),
(22, 14, 'pending', 'cancelled', 2, 'Appointment cancelled by patient', '2026-04-04 23:21:13'),
(23, 15, 'pending', 'approved', 1, 'Appointment approved by admin/staff', '2026-04-04 23:26:14'),
(24, 15, 'approved', 'reschedule_requested', 2, 'Reschedule requested by patient', '2026-04-04 23:42:22'),
(25, 12, 'reschedule_requested', 'cancelled', 2, 'Appointment cancelled by patient', '2026-04-04 23:44:29'),
(26, 15, 'reschedule_requested', 'cancelled', 2, 'Appointment cancelled by patient', '2026-04-04 23:44:31');

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

--
-- Dumping data for table `dentist_profiles`
--

INSERT INTO `dentist_profiles` (`dentist_id`, `user_id`, `first_name`, `middle_name`, `last_name`, `specialization`, `license_number`, `bio`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 5, 'Miguel', 'Antonio', 'Santos', 'General Dentistry', 'PRC-DENT-2026-001', 'Dr. Miguel Antonio Santos is a general dentist handling routine consultations, oral exams, and preventive dental care.', 1, '2026-04-01 08:34:48', '2026-04-01 08:34:48'),
(8, 15, 'Rafael', 'Santos', 'Mendoza', 'Oral Surgery', 'PRC-DENT-2026-003', 'Dr. Rafael Santos Mendoza specializes in tooth extraction, wisdom tooth surgery, and minor oral surgical procedures.', 1, '2026-04-05 01:58:25', '2026-04-05 01:58:25');

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

--
-- Dumping data for table `dentist_schedule_blocks`
--

INSERT INTO `dentist_schedule_blocks` (`block_id`, `dentist_id`, `block_date`, `start_time`, `end_time`, `reason`, `created_by`, `created_at`) VALUES
(2, 1, '2026-04-08', NULL, NULL, 'Blocked by admin', 1, '2026-04-01 08:35:56'),
(3, 1, '2026-04-05', NULL, NULL, 'Blocked by admin', 1, '2026-04-03 21:59:59'),
(7, 1, '2026-05-01', NULL, NULL, 'Blocked by admin', 1, '2026-04-04 23:46:41');

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
  `encoded_by` int(11) NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `subject` text DEFAULT NULL,
  `note_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medical_notes`
--

INSERT INTO `medical_notes` (`note_id`, `patient_id`, `encoded_by`, `updated_by`, `subject`, `note_text`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'Tooth Canal', '67 67 67 67 hahahahahaa', '2026-03-31 19:18:29', '2026-04-05 02:52:22'),
(2, 2, 6, NULL, 'Teeth', 'HHAHAH', '2026-04-01 08:56:46', '2026-04-01 08:56:46'),
(3, 1, 1, NULL, 'HAHAHA', 'oi', '2026-04-05 00:11:51', '2026-04-05 00:11:51'),
(5, 2, 1, 1, 'yoyoy', 'hey men 6767', '2026-04-05 03:04:19', '2026-04-05 03:17:02');

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

--
-- Dumping data for table `patient_profiles`
--

INSERT INTO `patient_profiles` (`patient_id`, `user_id`, `first_name`, `middle_name`, `last_name`, `sex`, `birth_date`, `address`, `emergency_contact_name`, `emergency_contact_phone`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 'Ken Edward', 'Endaya', 'Latido', 'male', '2002-04-09', 'Unit 1507 Cityland Makati Executive Tower 4\r\n77 Sen. Gil Puyat, Pio Del Pilar', 'Harlene Bautista', '09090301830', NULL, '2026-03-31 09:11:38', '2026-03-31 09:11:38'),
(2, 3, 'Harlene', 'Morcilla', 'Bautista', 'female', '2003-06-27', 'Unit 1507 Cityland Makati Executive Tower 477 Sen. Gil Puyat, Pio Del Pilar', 'Ken Edward Latido', '09777416394', '', '2026-03-31 18:23:53', '2026-04-05 03:25:21'),
(3, 4, 'Kayenne', 'Trinidad', 'Trinidad', 'female', '2000-01-01', 'Manila', 'Alek Mendoza', '09123456789', NULL, '2026-04-01 03:46:34', '2026-04-01 03:46:34'),
(4, 7, 'yeen', NULL, 't', 'prefer_not_to_say', '2004-06-15', NULL, NULL, NULL, NULL, '2026-04-04 16:23:01', '2026-04-04 16:23:01');

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

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `appointment_id`, `amount`, `payment_method`, `reference_number`, `proof_image_path`, `payment_date`, `verification_status`, `verified_by`, `verification_notes`, `created_at`, `updated_at`) VALUES
(7, 11, 1500.00, 'other', NULL, NULL, '2026-04-05 00:00:00', 'verified', 1, 'Payment verified by admin/staff', '2026-04-04 22:15:55', '2026-04-04 22:19:01'),
(8, 12, 500.00, 'other', NULL, NULL, NULL, 'pending', NULL, NULL, '2026-04-04 22:15:59', '2026-04-04 22:15:59'),
(9, 13, 3000.00, 'other', NULL, NULL, NULL, 'pending', NULL, NULL, '2026-04-04 23:04:39', '2026-04-04 23:04:39'),
(10, 15, 500.00, 'other', NULL, NULL, NULL, 'pending', NULL, NULL, '2026-04-04 23:26:14', '2026-04-04 23:26:14');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 30,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `service_name`, `description`, `image_path`, `duration_minutes`, `price`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(7, 'Tooth restoration', 'Restore the function and integrity of missing tooth structure.', NULL, 60, 1500.00, 1, 1, '2026-04-04 17:33:34', '2026-04-04 17:33:34'),
(8, 'Wisdom tooth extraction', 'Surgical removal of one or more wisdom teeth.', NULL, 120, 3000.00, 1, 1, '2026-04-04 17:33:34', '2026-04-04 17:33:34'),
(9, 'Teeth Whitening', 'Professional bleaching to lighten the color of your teeth.', NULL, 120, 3000.00, 1, 1, '2026-04-04 17:33:34', '2026-04-04 17:33:34'),
(10, 'Dental Check-up', 'Comprehensive examination of your teeth and gums.', NULL, 60, 500.00, 1, 1, '2026-04-04 17:33:34', '2026-04-04 17:33:34'),
(11, 'Braces Installation', 'Orthodontic treatment to straighten teeth (Price ranges from ₱30,000 to ₱60,000).', NULL, 180, 30000.00, 1, 1, '2026-04-04 17:33:34', '2026-04-04 17:33:34'),
(12, 'Braces adjustment', 'Routine tightening and adjustment of braces.', NULL, 60, 1000.00, 1, 1, '2026-04-04 17:33:34', '2026-04-04 17:33:34'),
(13, 'Dental x-ray', 'Radiographic imaging of teeth and jaw.', NULL, 60, 500.00, 1, 1, '2026-04-04 17:33:34', '2026-04-04 17:33:34'),
(14, 'Root canal treatment', 'Treatment to repair and save a badly damaged or infected tooth.', NULL, 180, 4000.00, 1, 1, '2026-04-04 17:33:34', '2026-04-04 17:33:34');

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
(1, 1, 'System', NULL, 'Administrator', 'System Administrator', 1, '2026-03-31 09:00:49', '2026-03-31 09:00:49'),
(2, 6, 'Andrea', 'Lopez', 'Reyes', 'Receptionist', 1, '2026-04-01 08:34:48', '2026-04-01 08:34:48');

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
(1, 'system_admin', 'admin@flossgloss.com', '09170000000', '$2y$10$OCnoUXEvX8vMTGhuLZIO0uxI8xmgO..9er2H44X2QYOJaLHFbE4Za', 'active', 1, '2026-03-31 09:00:48', '2026-04-03 03:46:08'),
(2, 'patient', 'latidokenedwardendaya@gmail.com', '09777416394', '$2y$10$h0.swecfCvnPI4YlTz3Zn.iHZaIHSEAw/efB8xqlhJwR7BupC46SK', 'active', 0, '2026-03-31 09:11:38', '2026-04-05 01:42:06'),
(3, 'patient', 'harlene@gmail.com', '09090301830', '$2y$10$gaJl5RoUxt3MREkRzjSZsOiYArDOWMzWisNffx7Ndu92S00WCrI6.', 'active', 0, '2026-03-31 18:23:53', '2026-04-01 16:10:22'),
(4, 'patient', 'kayenne_trinidad@gmail.com', '09123456789', '$2y$10$Xtt844MsEUPKrvAc8wrxEuhQZS9hNfdehSMn98fvqS4grDEydXSrS', 'active', 0, '2026-04-01 03:46:34', '2026-04-01 06:11:34'),
(5, 'dentist', 'miguel.santos@flossgloss.com', '09171234567', '$2y$12$wTaCOiOfkCAbUgUEpl8CYuR4Ioe6.0.ViDEeRioO5y2Wt2AZFqvCG', 'active', 1, '2026-04-01 08:34:48', '2026-04-01 08:34:48'),
(6, 'staff', 'andrea.reyes@flossgloss.com', '09179876543', '$2y$12$KCuvyIo0GEPgpky580tkauBvNe/CPxtY2TvdT8P36erruRjd.HLrG', 'active', 1, '2026-04-01 08:34:48', '2026-04-03 03:47:36'),
(7, 'patient', 'kayennetrinidad@gmail.com', '09771234567', '$2y$10$gLJl5GXxjW8t38zhgw3KouwjwEn60zc5qPntl0SJyRnCTCH6vW5cS', 'active', 0, '2026-04-04 16:23:01', '2026-04-04 16:23:01'),
(15, 'dentist', 'rafael.mendoza@flossgloss.com', '09178889999', '$2y$10$123456789012345678901uW0j0m4m8mV9mQ4RrQn0mW7m6lS9vY2', 'active', 1, '2026-04-05 01:58:25', '2026-04-05 01:58:25');

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
  ADD KEY `fk_medical_note_encoded_by` (`encoded_by`),
  ADD KEY `idx_medical_notes_patient` (`patient_id`),
  ADD KEY `fk_medical_note_updated_by` (`updated_by`);

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
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `appointment_status_history`
--
ALTER TABLE `appointment_status_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `dentist_profiles`
--
ALTER TABLE `dentist_profiles`
  MODIFY `dentist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `dentist_schedule_blocks`
--
ALTER TABLE `dentist_schedule_blocks`
  MODIFY `block_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `email_notifications`
--
ALTER TABLE `email_notifications`
  MODIFY `email_notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_notes`
--
ALTER TABLE `medical_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `patient_profiles`
--
ALTER TABLE `patient_profiles`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
-- Constraints for table `email_notifications`
--
ALTER TABLE `email_notifications`
  ADD CONSTRAINT `fk_email_notification_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_notification_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `medical_notes`
--
ALTER TABLE `medical_notes`
  ADD CONSTRAINT `fk_medical_note_encoded_by` FOREIGN KEY (`encoded_by`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_medical_note_patient` FOREIGN KEY (`patient_id`) REFERENCES `patient_profiles` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_medical_note_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
