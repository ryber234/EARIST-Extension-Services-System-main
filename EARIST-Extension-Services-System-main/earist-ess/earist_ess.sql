-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 11, 2025 at 04:15 PM
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
-- Database: `earist_ess`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `table_affected` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `table_affected`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'User Logout', 'general', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 10:37:42'),
(2, 1, 'User Logout', 'general', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 10:37:57'),
(3, 1, 'User Logout', 'general', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 10:44:33'),
(4, 1, 'User Logout', 'general', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 10:55:53'),
(5, 2, 'User Logout', 'general', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 10:59:05'),
(6, 1, 'User Logout', 'general', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 11:07:18'),
(7, 1, 'User Logout', 'general', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 11:14:39'),
(8, 1, 'User Logout', 'general', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 11:24:54'),
(9, 1, 'Profile Image Updated', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 11:45:58'),
(10, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 11:46:39'),
(11, 1, 'Cleared audit logs older than 90 days', 'audit_logs', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 11:56:46'),
(12, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 11:58:13'),
(13, 1, 'User Logout', 'general', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 12:00:35'),
(14, 1, 'User Logout', 'general', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 12:01:24'),
(15, 1, 'User Logout', 'general', NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 12:02:22'),
(16, 3, 'Program Created from Recommendation', 'programs', 6, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 12:03:58'),
(17, 3, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 12:04:58'),
(18, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 13:58:16'),
(19, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 14:00:44'),
(20, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 14:06:33'),
(21, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 14:10:46'),
(22, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 14:10:59'),
(23, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 14:32:20'),
(24, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 14:51:43'),
(25, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 14:55:09'),
(26, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 15:24:27'),
(27, 2, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 15:26:08'),
(28, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 15:35:04'),
(29, 1, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 16:16:16'),
(30, 2, 'User Logout', NULL, NULL, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-06-10 16:18:18');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('Info','Success','Warning','Error') DEFAULT 'Info',
  `link_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `link_url`, `is_read`, `created_at`) VALUES
(1, 1, 'Welcome to EARIST ESS', 'Welcome to the EARIST Extension Service System! You have been set up as a System Administrator with full access to all system features.', 'Success', NULL, 0, '2025-06-10 10:06:16'),
(2, 2, 'Welcome to EARIST ESS', 'Welcome to the EARIST Extension Service System! You have been set up as an Authorized User for the College of Engineering.', 'Success', NULL, 0, '2025-06-10 10:06:16'),
(3, 3, 'Welcome to EARIST ESS', 'Welcome to the EARIST Extension Service System! You have been set up as an Authorized User for the Registrar Office.', 'Success', NULL, 0, '2025-06-10 10:06:16'),
(4, 1, 'System Status', 'The EARIST Extension Service System has been successfully installed and configured. All database tables have been created and sample data has been populated.', 'Info', NULL, 0, '2025-06-10 10:06:16'),
(5, 1, 'Test Notification', 'This is a test notification from the system administrator.', 'Info', NULL, 0, '2025-06-10 16:12:43');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `program_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `objectives` text DEFAULT NULL,
  `type_of_service` enum('Health','Education','Agriculture','Technology','Environment','Social Services','Skills Training') NOT NULL,
  `target_beneficiaries` text DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `date_start` date NOT NULL,
  `date_end` date DEFAULT NULL,
  `time_start` time NOT NULL,
  `time_end` time DEFAULT NULL,
  `expected_participants` int(11) NOT NULL DEFAULT 0,
  `actual_participants` int(11) DEFAULT 0,
  `budget_allocated` decimal(12,2) NOT NULL DEFAULT 0.00,
  `budget_used` decimal(12,2) DEFAULT 0.00,
  `status` enum('Planned','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Planned',
  `approval_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Approved',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`program_id`, `title`, `description`, `objectives`, `type_of_service`, `target_beneficiaries`, `location`, `barangay`, `date_start`, `date_end`, `time_start`, `time_end`, `expected_participants`, `actual_participants`, `budget_allocated`, `budget_used`, `status`, `approval_status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Computer Literacy Training for Seniors', 'Comprehensive computer skills training program designed specifically for senior citizens to help them navigate the digital world confidently.', 'To provide senior citizens with basic computer skills including internet browsing, email management, and social media usage.', 'Technology', 'Senior Citizens aged 60 and above', 'EARIST Computer Laboratory', 'Barangay San Roque', '2025-07-15', NULL, '09:00:00', NULL, 25, 0, 15000.00, 0.00, 'Planned', 'Approved', 1, '2025-06-10 10:06:15', '2025-06-10 10:06:15'),
(2, 'Community Health and Wellness Seminar', 'Health awareness program focusing on preventive healthcare, nutrition, and wellness practices for community members.', 'To educate community members about preventive healthcare measures and promote healthy lifestyle choices.', 'Health', 'General Public, Families', 'San Roque Barangay Hall', 'Barangay San Roque', '2025-07-20', NULL, '14:00:00', NULL, 50, 0, 20000.00, 0.00, 'Planned', 'Approved', 2, '2025-06-10 10:06:15', '2025-06-10 10:06:15'),
(3, 'Sustainable Organic Farming Workshop', 'Hands-on workshop teaching sustainable farming techniques, organic composting, and urban gardening methods.', 'To teach farmers and gardening enthusiasts sustainable farming practices and organic food production methods.', 'Agriculture', 'Local Farmers, Gardening Enthusiasts', 'EARIST Agricultural Center', 'Barangay Malanday', '2025-08-01', NULL, '08:00:00', NULL, 30, 0, 25000.00, 0.00, 'Planned', 'Approved', 3, '2025-06-10 10:06:15', '2025-06-10 10:06:15'),
(4, 'Youth Leadership and Skills Development', 'Leadership training program for young adults focusing on communication skills, project management, and community engagement.', 'To develop leadership capabilities among youth and prepare them for community service and professional growth.', 'Education', 'Youth aged 18-25', 'EARIST Conference Room', 'Barangay San Roque', '2025-08-10', NULL, '13:00:00', NULL, 40, 0, 18000.00, 0.00, 'Completed', 'Approved', 1, '2025-06-10 10:06:15', '2025-06-10 11:08:44'),
(5, 'Environmental Awareness Campaign', 'Community education program on environmental conservation, waste management, and climate change awareness.', 'To raise environmental awareness and promote sustainable practices among community members.', 'Environment', 'Students, Community Members', 'EARIST Auditorium', 'Barangay Malanday', '2025-08-15', NULL, '10:00:00', NULL, 100, 0, 12000.00, 0.00, 'Planned', 'Approved', 2, '2025-06-10 10:06:15', '2025-06-10 10:06:15'),
(6, 'Community Community Development Program', 'A specialized program tailored to community needs using departmental expertise.', NULL, 'Education', 'Community members, Local residents', 'Barangay San Roque', 'Barangay San Roque', '2025-06-12', NULL, '20:06:00', NULL, 34, 0, 26178.00, 0.00, 'Planned', 'Approved', 3, '2025-06-10 12:03:58', '2025-06-10 12:03:58');

-- --------------------------------------------------------

--
-- Table structure for table `program_faculty`
--

CREATE TABLE `program_faculty` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `role` varchar(50) DEFAULT 'Organizer'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_feedback`
--

CREATE TABLE `program_feedback` (
  `feedback_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `participant_name` varchar(100) NOT NULL,
  `participant_email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `feedback_text` text NOT NULL,
  `suggestions` text DEFAULT NULL,
  `is_anonymous` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_images`
--

CREATE TABLE `program_images` (
  `image_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `image_caption` text DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_participants`
--

CREATE TABLE `program_participants` (
  `participant_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `participant_name` varchar(100) NOT NULL,
  `participant_email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `organization` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `attendance_status` enum('Registered','Attended','No Show') DEFAULT 'Registered',
  `status` enum('Registered','Confirmed','Cancelled') DEFAULT 'Registered'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `program_requests`
--

CREATE TABLE `program_requests` (
  `request_id` int(11) NOT NULL,
  `requester_name` varchar(100) NOT NULL,
  `requester_email` varchar(100) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `organization` varchar(100) DEFAULT NULL,
  `barangay` varchar(100) NOT NULL,
  `municipality` varchar(100) NOT NULL,
  `program_type` varchar(50) NOT NULL,
  `program_title` varchar(255) NOT NULL,
  `program_description` text NOT NULL,
  `preferred_date` date DEFAULT NULL,
  `urgency_level` enum('Low','Medium','High') DEFAULT 'Medium',
  `status` enum('Pending','Under Review','Approved','Rejected') DEFAULT 'Pending',
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `program_requests`
--

INSERT INTO `program_requests` (`request_id`, `requester_name`, `requester_email`, `contact_number`, `organization`, `barangay`, `municipality`, `program_type`, `program_title`, `program_description`, `preferred_date`, `urgency_level`, `status`, `admin_notes`, `processed_by`, `processed_at`, `created_at`) VALUES
(1, 'Maria Santos', 'maria.santos@email.com', '09123456789', 'Barangay San Roque Council', 'Barangay San Roque', 'Marikina City', 'Health', 'Maternal Health Seminar', 'Request for a maternal health education program for pregnant mothers and new parents in our barangay.', '2025-09-01', 'High', 'Pending', NULL, NULL, NULL, '2025-06-10 10:06:15'),
(2, 'Juan Dela Cruz', 'juan.delacruz@email.com', '09987654321', 'Malanday Farmers Association', 'Barangay Malanday', 'Marikina City', 'Agriculture', 'Hydroponics Training', 'We would like to request training on hydroponic farming techniques for our farmer members.', '2025-09-15', 'Medium', 'Pending', NULL, NULL, NULL, '2025-06-10 10:06:15');

-- --------------------------------------------------------

--
-- Table structure for table `program_resources`
--

CREATE TABLE `program_resources` (
  `resource_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `resource_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit` varchar(50) DEFAULT 'pcs',
  `cost_per_unit` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(12,2) DEFAULT 0.00,
  `provider` varchar(255) DEFAULT NULL,
  `status` enum('Planned','Ordered','Delivered','Used') DEFAULT 'Planned',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('Admin','Authorized User') NOT NULL DEFAULT 'Authorized User',
  `department` varchar(100) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `first_name`, `last_name`, `role`, `department`, `profile_image`, `status`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'admin', 'admin@earist.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 'Admin', 'Information Technology', 'uploads/profiles/68481af68c9ae_2025-06-10_19-45-58.jpg', 'Active', '2025-06-10 10:06:15', '2025-06-11 13:55:36', '2025-06-11 13:55:36'),
(2, 'coe_head', 'coe@earist.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'COE', 'Head', 'Authorized User', 'College of Engineering', NULL, 'Active', '2025-06-10 10:06:15', '2025-06-10 16:16:38', '2025-06-10 16:16:38'),
(3, 'registrar', 'registrar@earist.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Registrar', 'Office', 'Authorized User', 'Registrar Office', NULL, 'Active', '2025-06-10 10:06:15', '2025-06-10 12:02:27', '2025-06-10 12:02:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_audit_logs_user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`program_id`),
  ADD KEY `idx_programs_status` (`status`),
  ADD KEY `idx_programs_created_by` (`created_by`),
  ADD KEY `idx_programs_date_start` (`date_start`);

--
-- Indexes for table `program_faculty`
--
ALTER TABLE `program_faculty`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `program_feedback`
--
ALTER TABLE `program_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `idx_program_feedback_program_id` (`program_id`);

--
-- Indexes for table `program_images`
--
ALTER TABLE `program_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `program_participants`
--
ALTER TABLE `program_participants`
  ADD PRIMARY KEY (`participant_id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `program_requests`
--
ALTER TABLE `program_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `program_resources`
--
ALTER TABLE `program_resources`
  ADD PRIMARY KEY (`resource_id`),
  ADD KEY `program_id` (`program_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `program_faculty`
--
ALTER TABLE `program_faculty`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_feedback`
--
ALTER TABLE `program_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_images`
--
ALTER TABLE `program_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_participants`
--
ALTER TABLE `program_participants`
  MODIFY `participant_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `program_requests`
--
ALTER TABLE `program_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `program_resources`
--
ALTER TABLE `program_resources`
  MODIFY `resource_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `programs`
--
ALTER TABLE `programs`
  ADD CONSTRAINT `programs_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `program_faculty`
--
ALTER TABLE `program_faculty`
  ADD CONSTRAINT `program_faculty_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `program_faculty_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `program_feedback`
--
ALTER TABLE `program_feedback`
  ADD CONSTRAINT `program_feedback_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE;

--
-- Constraints for table `program_images`
--
ALTER TABLE `program_images`
  ADD CONSTRAINT `program_images_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE;

--
-- Constraints for table `program_participants`
--
ALTER TABLE `program_participants`
  ADD CONSTRAINT `program_participants_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE;

--
-- Constraints for table `program_requests`
--
ALTER TABLE `program_requests`
  ADD CONSTRAINT `program_requests_ibfk_1` FOREIGN KEY (`processed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `program_resources`
--
ALTER TABLE `program_resources`
  ADD CONSTRAINT `program_resources_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
