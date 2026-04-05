-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Apr 05, 2026 at 10:11 AM
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
(1, 'APT-20260405-001', 1, 1, 1, '2026-04-01', '09:00:00', '10:00:00', '2026-04-01', '09:00:00', '10:00:00', 'completed', 'verified', 'Completed successfully.', 7, 5, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(2, 'APT-20260405-002', 2, 6, 1, '2026-04-02', '10:00:00', '11:00:00', '2026-04-02', '10:00:00', '11:00:00', 'completed', 'verified', 'Routine check-up completed.', 8, 6, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(3, 'APT-20260405-003', 3, 8, 2, '2026-04-05', '13:00:00', '14:00:00', '2026-04-05', '13:00:00', '14:00:00', 'approved', 'submitted', 'Approved for today.', 9, 5, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(4, 'APT-20260405-004', 4, 9, 3, '2026-04-05', '15:00:00', '16:00:00', NULL, NULL, NULL, 'cancelled', '', 'Awaiting staff review.', 10, 10, '2026-04-05 07:42:54', '2026-04-05 07:51:12'),
(5, 'APT-20260405-005', 5, 10, 3, '2026-04-08', '09:00:00', '12:00:00', '2026-04-08', '09:00:00', '12:00:00', 'approved', 'pending', 'Root canal schedule confirmed.', 11, 6, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(6, 'APT-20260405-006', 6, 7, 2, '2026-04-10', '13:00:00', '16:00:00', '2026-04-10', '13:00:00', '16:00:00', 'approved', 'pending', 'Appointment approved by admin/staff', 12, 1, '2026-04-05 07:42:54', '2026-04-05 08:00:50'),
(7, 'APT-20260405-007', 7, 4, 1, '2026-04-03', '16:00:00', '18:00:00', NULL, NULL, NULL, 'rejected', 'rejected', 'Requested slot exceeded clinic cutoff.', 13, 5, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(8, 'APT-20260405-008', 8, 2, 1, '2026-04-06', '11:00:00', '12:00:00', '2026-04-06', '11:00:00', '12:00:00', 'cancelled', 'rejected', 'Cancelled by patient.', 14, 14, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(9, 'APT-20260405-009', 9, 3, 3, '2026-04-12', '10:00:00', '11:00:00', '2026-04-12', '10:00:00', '11:00:00', 'completed', 'verified', 'Restoration booked.', 15, 1, '2026-04-05 07:42:54', '2026-04-05 07:45:47'),
(10, 'APT-20260405-010', 10, 5, 2, '2026-04-05', '16:00:00', '18:00:00', '2026-04-05', '16:00:00', '18:00:00', 'completed', 'verified', 'Whitening session approved.', 16, 6, '2026-04-05 07:42:54', '2026-04-05 07:57:48'),
(11, 'APP-1775375930', 1, 1, 2, '2026-04-06', '09:00:00', '10:00:00', '2026-04-06', '09:00:00', '10:00:00', 'completed', 'verified', 'Appointment approved by admin/staff', 7, 1, '2026-04-05 07:58:50', '2026-04-05 08:00:27');

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
(1, 9, 'approved', 'completed', 1, 'Appointment marked as completed by admin/staff', '2026-04-05 07:45:43'),
(2, 4, 'pending', 'cancelled', 10, 'Appointment cancelled by patient', '2026-04-05 07:51:12'),
(3, 10, 'approved', 'completed', 6, 'Appointment marked as completed by admin/staff', '2026-04-05 07:57:48'),
(4, 11, 'approved', 'completed', 1, 'Appointment marked as completed by admin/staff', '2026-04-05 08:00:14'),
(5, 6, 'pending', 'approved', 1, 'Appointment approved by admin/staff', '2026-04-05 08:00:50');

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
(1, 2, 'Maria', 'Elena', 'Santos', 'General Dentistry', 'DENT-2026-001', 'Experienced general dentist focused on preventive care.', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(2, 3, 'Joshua', 'Miguel', 'Reyes', 'Orthodontics', 'DENT-2026-002', 'Handles braces, alignment, and long-term orthodontic care.', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(3, 4, 'Angela', 'Mae', 'Cruz', 'Endodontics', 'DENT-2026-003', 'Specializes in root canal and restorative treatments.', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54');

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
(1, 3, '2026-04-07', NULL, NULL, 'Blocked by admin', 1, '2026-04-05 07:46:08'),
(3, 3, '2026-04-06', NULL, NULL, 'Blocked by admin', 6, '2026-04-05 07:54:57');

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
(1, 7, 'Ken', 'Edward', 'Latido', 'male', '2004-06-14', 'Manila City', 'Maria Latido', '09980000001', 'Prefers afternoon appointments.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(2, 8, 'Kayenne', 'T.', 'Trinidad', 'female', '2004-09-03', 'Quezon City', 'Liza Trinidad', '09980000002', 'Has prior cleaning history.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(3, 9, 'Alwyn', 'S.', 'Chang', 'male', '2004-12-11', 'Pasig City', 'Victor Chang', '09980000003', 'Interested in braces consultation.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(4, 10, 'Alek', 'M.', 'Medran', 'male', '2004-03-29', 'Makati City', 'Helen Medran', '09980000004', 'Sensitive gums noted.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(5, 11, 'Sofia', 'Mae', 'Mendoza', 'female', '2001-07-22', 'Taguig City', 'Nina Mendoza', '09980000005', 'Returning patient.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(6, 12, 'John', 'Paolo', 'Ramos', 'male', '1999-02-15', 'Muntinlupa City', 'Celine Ramos', '09980000006', 'Requested weekend slots before.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(7, 13, 'Camille', 'Anne', 'Dela Cruz', 'female', '2002-01-30', 'Parañaque City', 'Rosa Dela Cruz', '09980000007', 'For restorative treatment follow-up.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(8, 14, 'Mark', 'Joseph', 'Villanueva', 'male', '1998-05-08', 'Las Piñas City', 'Leo Villanueva', '09980000008', 'Has cancelled once before.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(9, 15, 'Paula', 'Irene', 'Aquino', 'female', '2003-11-19', 'Caloocan City', 'Grace Aquino', '09980000009', 'Needs x-ray before extraction.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(10, 16, 'Luis', 'Andre', 'Fernandez', 'male', '2000-08-27', 'San Juan City', 'Mario Fernandez', '09980000010', 'No known dental history yet.', '2026-04-05 07:42:54', '2026-04-05 07:42:54');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
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

INSERT INTO `payments` (`payment_id`, `appointment_id`, `amount`, `payment_date`, `verification_status`, `verified_by`, `verification_notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1000.00, '2026-04-01 08:40:00', 'verified', 5, 'Paid and verified before procedure.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(2, 2, 500.00, '2026-04-02 09:35:00', 'verified', 6, 'Walk-in payment verified.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(3, 3, 1000.00, '2026-04-05 10:15:00', 'pending', NULL, 'Payment proof submitted, awaiting verification.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(4, 4, 500.00, NULL, 'pending', NULL, 'No payment submitted yet.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(5, 5, 4000.00, NULL, 'pending', NULL, 'To be paid before appointment date.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(6, 6, 30000.00, NULL, 'pending', NULL, 'Large treatment, pending confirmation.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(7, 7, 3000.00, '2026-04-03 14:20:00', 'rejected', 5, 'Rejected because appointment was not approved.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(8, 8, 1500.00, '2026-04-04 17:10:00', 'rejected', 6, 'Cancelled appointment; payment not accepted.', '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(9, 9, 1500.00, '2026-04-05 00:00:00', 'verified', 1, 'Payment verified by admin/staff', '2026-04-05 07:42:54', '2026-04-05 07:45:47'),
(10, 10, 3000.00, '2026-04-05 11:30:00', 'verified', 5, 'Verified for same-day treatment.', '2026-04-05 07:42:54', '2026-04-05 07:42:54');

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
(1, 'Teeth Cleaning', 'Teeth cleaning removes plaque, tartar and bacteria to maintain healthy teeth and gums.', '../assets/services/1775376565_Teeth Cleaning.jpeg', 60, 1000.00, 1, 1, '2026-04-05 06:37:03', '2026-04-05 08:09:25'),
(2, 'Tooth Extraction', 'Tooth extraction, or dental extraction, is the removal of a tooth from its socket in the jawbone.', '../assets/services/1775376591_Tooth Extraction.jpg', 60, 1500.00, 1, 1, '2026-04-05 06:40:06', '2026-04-05 08:09:51'),
(3, 'Tooth Restoration', 'Restore the function and integrity of missing tooth structure.', '../assets/services/1775376606_Tooth Restoration.jpg', 60, 1500.00, 1, 1, '2026-04-04 17:33:34', '2026-04-05 08:10:06'),
(4, 'Wisdom Tooth Extraction', 'Surgical removal of one or more wisdom teeth.', '../assets/services/1775376616_Wisdom Tooth Extraction.jpeg', 120, 3000.00, 1, 1, '2026-04-04 17:33:34', '2026-04-05 08:10:16'),
(5, 'Teeth Whitening', 'Professional bleaching to lighten the color of your teeth.', '../assets/services/1775376575_Teeth Whitening.jpeg', 120, 3000.00, 1, 1, '2026-04-04 17:33:34', '2026-04-05 08:09:35'),
(6, 'Dental Check-Up', 'Comprehensive examination of your teeth and gums.', '../assets/services/1775376530_Dental Checkup.jpeg', 60, 500.00, 1, 1, '2026-04-04 17:33:34', '2026-04-05 08:08:50'),
(7, 'Braces Installation', 'Orthodontic treatment to straighten teeth', '../assets/services/1775376521_Braces Installation.jpeg', 180, 30000.00, 1, 1, '2026-04-04 17:33:34', '2026-04-05 08:08:41'),
(8, 'Braces Adjustment', 'Routine tightening and adjustment of braces.', '../assets/services/1775376512_Braces Adjustment.jpeg', 60, 1000.00, 1, 1, '2026-04-04 17:33:34', '2026-04-05 08:08:32'),
(9, 'Dental X-Ray', 'Radiographic imaging of teeth and jaw.', '../assets/services/1775376538_Dental X-Ray.jpeg', 60, 500.00, 1, 1, '2026-04-04 17:33:34', '2026-04-05 08:08:58'),
(10, 'Root Canal Treatment', 'Treatment to repair and save a badly damaged or infected tooth.', '../assets/services/1775376550_Root Canal.jpeg', 180, 4000.00, 1, 1, '2026-04-04 17:33:34', '2026-04-05 08:09:10');

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
(1, 5, 'Mikaela', 'Joy', 'Garcia', 'Receptionist', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(2, 6, 'Ken', 'Luis', 'Torres', 'Clinic Staff', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54');

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
(1, 'system_admin', 'flossglossmail@gmail.com', '09777416394', '$2y$10$OCnoUXEvX8vMTGhuLZIO0uxI8xmgO..9er2H44X2QYOJaLHFbE4Za', 'active', 1, '2026-03-31 09:00:48', '2026-04-05 06:33:08'),
(2, 'dentist', 'maria.santos@flossgloss.com', '09170000002', '$2y$12$fj7fbM4e6MkY4iZ3BzW5/umPJnRFZxbs2k6SmZYnMM03bRtJLTO3i', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(3, 'dentist', 'joshua.reyes@flossgloss.com', '09170000003', '$2y$12$8yFnPL568bynM9HHPj/.WeaqUkR1B.og3ne/JBkCpext4ULqbv5Pm', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(4, 'dentist', 'angela.cruz@flossgloss.com', '09170000004', '$2y$12$dt8zyTKvbspnRza35LgXaOxiKvmHCt3ojBhpIsWsEbw4GtzPdJqjy', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(5, 'staff', 'mikaela.garcia@flossgloss.com', '09170000005', '$2y$12$LPyPmKHYrnh0vug28QBS6O67DecvHqfx2.esFafp.UwUtYKPZkAeO', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(6, 'staff', 'ken.torres@flossgloss.com', '09170000006', '$2y$12$YZWfVeYL2V3gUsXIlCr8kOgWz.aCoWc5UEmuuULnAqx4WmTiYucxy', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(7, 'patient', 'ken_latido@dlsu.edu.ph', '09170000007', '$2y$12$cHaD5VsbJev6FbedFPxMG.5yK17O/OLw85t/NtsHc17F7wDe6FI7W', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(8, 'patient', 'kayenne_t_trinidad@dlsu.edu.ph', '09170000008', '$2y$12$2AQgC/qrToV3AEOletbl2eRK1PixKJmVyCUVld5yg46bzNQ2k9oSS', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(9, 'patient', 'alwyn_change@dlsu.edu.ph', '09170000009', '$2y$12$zZyODZXk6OC87tz.JCvCQ.7oeGj2TxD5hgBVUK0MyURXCfU8jppYq', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(10, 'patient', 'alek_medran@dlsu.edu.ph', '09170000010', '$2y$12$go6VfW7QMXhBV/7c0GC0pu4pXZ/MM.p1iCyvx2SnuQd1hYSBpPzuu', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(11, 'patient', 'sofia.mendoza@gmail.com', '09170000011', '$2y$12$nsrnc7nY4fKxwieJDW2atutRo3mO4kIA1X0kupgB3.U5NnuKIf1K2', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(12, 'patient', 'john.ramos@gmail.com', '09170000012', '$2y$12$lwHlrGihNI4oFRvqiGYCIOGwIfnFUGcmCk4rrU3j7v1E3qAiRXF9K', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(13, 'patient', 'camille.delacruz@gmail.com', '09170000013', '$2y$12$TXAGQXPaAmAv0kZ6N03yWuQT27j8hcDg2yMr1vIoVqrjN4RUa5JZC', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(14, 'patient', 'mark.villanueva@gmail.com', '09170000014', '$2y$12$hozbw9F7th6J0nYWmOdle.L3Gu5dI41BIFdBKkPsrrib3mvT1.fJe', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(15, 'patient', 'paula.aquino@gmail.com', '09170000015', '$2y$12$zh/ktU.ZSw26JDGp/eurKORZ6qi24PBaaAfzAC3EaTQ36WWcUAwu.', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54'),
(16, 'patient', 'luis.fernandez@gmail.com', '09170000016', '$2y$12$YlzM9I/9mjY22bb4NYCyyuWnRVgYtU3sfOprhrrf/TYRptSIrGryW', 'active', 1, '2026-04-05 07:42:54', '2026-04-05 07:42:54');

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
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `appointment_status_history`
--
ALTER TABLE `appointment_status_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `dentist_profiles`
--
ALTER TABLE `dentist_profiles`
  MODIFY `dentist_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `dentist_schedule_blocks`
--
ALTER TABLE `dentist_schedule_blocks`
  MODIFY `block_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `medical_notes`
--
ALTER TABLE `medical_notes`
  MODIFY `note_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_profiles`
--
ALTER TABLE `patient_profiles`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
