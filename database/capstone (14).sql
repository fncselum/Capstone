-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 29, 2025 at 05:30 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `capstone`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_transactions_view`
-- (See below for the actual view)
--
CREATE TABLE `active_transactions_view` (
`id` int(11)
,`user_id` int(11)
,`equipment_id` varchar(50)
,`equipment_name` varchar(200)
,`rfid_tag` varchar(50)
,`size_category` enum('Small','Medium','Large')
,`transaction_type` enum('Borrow','Return')
,`quantity` int(11)
,`transaction_date` timestamp
,`expected_return_date` datetime
,`actual_return_date` datetime
,`status` enum('Pending Approval','Active','Returned','Overdue','Lost','Damaged','Rejected')
,`penalty_applied` decimal(10,2)
,`notes` text
,`processed_by` varchar(100)
);

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rfid_tag` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `rfid_tag`, `status`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$v9oOtQSSlvxR21aF7zOvEe19glj7jA7QyBc32j0RCdccJmYMFC896', '0066696838', 'Active', '2025-10-29 23:59:43', '2025-10-05 14:38:53', '2025-10-29 15:59:43');

-- --------------------------------------------------------

--
-- Table structure for table `ai_comparison_jobs`
--

CREATE TABLE `ai_comparison_jobs` (
  `job_id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `priority` tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
  `payload` longtext DEFAULT NULL,
  `result` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_comparison_jobs`
--

INSERT INTO `ai_comparison_jobs` (`job_id`, `transaction_id`, `status`, `priority`, `payload`, `result`, `error_message`, `created_at`, `updated_at`, `processed_at`) VALUES
(1, 84, 'completed', 3, '{\"transaction_id\":84,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/basket_ball_1760449241.png\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761481667_84.jpg\",\"item_size\":\"medium\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":30,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:34:55\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 13:34:55', '2025-10-26 13:34:55'),
(2, 83, 'completed', 3, '{\"transaction_id\":83,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761417408_83.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"69.55\",\"offline_severity\":\"medium\"}', '{\"ai_similarity_score\":74.55,\"ai_severity_level\":\"none\",\"ai_detected_issues\":\"AI: No significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:34:57\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 13:34:57', '2025-10-26 13:34:57'),
(3, 82, 'completed', 3, '{\"transaction_id\":82,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761417339_82.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":27,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:34:59\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 13:34:59', '2025-10-26 13:34:59'),
(4, 81, 'completed', 3, '{\"transaction_id\":81,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761416614_81.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":33,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:01\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 13:35:01', '2025-10-26 13:35:01'),
(5, 80, 'completed', 3, '{\"transaction_id\":80,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761416512_80.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"47.19\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":44.19,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:04\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 13:35:04', '2025-10-26 13:35:04'),
(6, 79, 'completed', 3, '{\"transaction_id\":79,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761415944_79.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"71.72\",\"offline_severity\":\"none\"}', '{\"ai_similarity_score\":69.72,\"ai_severity_level\":\"medium\",\"ai_detected_issues\":\"AI: Minor wear detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:06\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 13:35:06', '2025-10-26 13:35:06'),
(7, 78, 'completed', 3, '{\"transaction_id\":78,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761391849_78.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":35,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:08\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 13:35:08', '2025-10-26 13:35:08'),
(8, 77, 'completed', 3, '{\"transaction_id\":77,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761390715_77.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":35,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:10\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 13:35:10', '2025-10-26 13:35:10'),
(9, 76, 'completed', 3, '{\"transaction_id\":76,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761383611_76.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":29,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:12\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 13:35:12', '2025-10-26 13:35:12'),
(10, 75, 'completed', 3, '{\"transaction_id\":75,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761382944_75.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":25,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:14\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 13:35:14', '2025-10-26 13:35:14'),
(11, 74, 'completed', 3, '{\"transaction_id\":74,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761380199_74.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":27,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\\nShape differences detected\\nColor\\/texture mismatch identified\",\"ai_detected_issues_list\":[\"Significant structural damage detected\",\"Shape differences detected\",\"Color\\/texture mismatch identified\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:44\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 14:02:44', '2025-10-26 14:02:44'),
(12, 73, 'completed', 3, '{\"transaction_id\":73,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761365347_73.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":33,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\",\"ai_detected_issues_list\":[\"Significant structural damage detected\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:46\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 14:02:46', '2025-10-26 14:02:46'),
(13, 70, 'completed', 3, '{\"transaction_id\":70,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761331493_70.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":25,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\\nShape differences detected\\nColor\\/texture mismatch identified\",\"ai_detected_issues_list\":[\"Significant structural damage detected\",\"Shape differences detected\",\"Color\\/texture mismatch identified\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:48\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 14:02:48', '2025-10-26 14:02:48'),
(14, 68, 'completed', 3, '{\"transaction_id\":68,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761330170_68.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":31,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Item mismatch \\u2013 different equipment returned\",\"ai_detected_issues_list\":[\"Item mismatch \\u2013 different equipment returned\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:50\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 14:02:50', '2025-10-26 14:02:50'),
(15, 66, 'completed', 3, '{\"transaction_id\":66,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761335793_66.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":29,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\\nShape differences detected\",\"ai_detected_issues_list\":[\"Significant structural damage detected\",\"Shape differences detected\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:52\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 14:02:52', '2025-10-26 14:02:52'),
(16, 65, 'completed', 3, '{\"transaction_id\":65,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761362354_65.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":29,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\",\"ai_detected_issues_list\":[\"Significant structural damage detected\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:54\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 14:02:54', '2025-10-26 14:02:54'),
(17, 63, 'completed', 3, '{\"transaction_id\":63,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761270414_63.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":25,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\\nShape differences detected\\nColor\\/texture mismatch identified\",\"ai_detected_issues_list\":[\"Significant structural damage detected\",\"Shape differences detected\",\"Color\\/texture mismatch identified\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:56\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 14:02:56', '2025-10-26 14:02:56'),
(18, 59, 'completed', 3, '{\"transaction_id\":59,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761205272_59.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":25,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Major scratches and dents observed\",\"ai_detected_issues_list\":[\"Major scratches and dents observed\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:58\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 14:02:58', '2025-10-26 14:02:58'),
(19, 56, 'completed', 3, '{\"transaction_id\":56,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761199791_56.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"39.26\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":42.26,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\\nColor\\/texture mismatch identified\",\"ai_detected_issues_list\":[\"Significant structural damage detected\",\"Color\\/texture mismatch identified\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:03:00\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 14:03:00', '2025-10-26 14:03:00'),
(20, 55, 'completed', 3, '{\"transaction_id\":55,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1761143126_55.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":31,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Major scratches and dents observed\\nShape differences detected\\nColor\\/texture mismatch identified\",\"ai_detected_issues_list\":[\"Major scratches and dents observed\",\"Shape differences detected\",\"Color\\/texture mismatch identified\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:03:02\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 14:03:02', '2025-10-26 14:03:02'),
(21, 49, 'processing', 3, '{\"transaction_id\":49,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1760953545_49.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', NULL, NULL, '2025-10-26 13:33:47', '2025-10-26 15:09:46', NULL),
(22, 45, 'completed', 3, '{\"transaction_id\":45,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1760942742_45.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":\"30\",\"final_blended_score\":\"30\",\"ai_confidence\":0,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI inference unavailable - using offline results\",\"ai_detected_issues_list\":[\"AI inference unavailable - using offline results\"],\"model_version\":\"offline-fallback\",\"blend_method\":\"offline_only\",\"offline_score\":\"30\",\"processed_at\":\"2025-10-26 16:10:55\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 15:10:55', '2025-10-26 15:10:55'),
(23, 44, 'completed', 3, '{\"transaction_id\":44,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1760935341_44.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":\"30\",\"final_blended_score\":\"30\",\"ai_confidence\":0,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI inference unavailable - using offline results\",\"ai_detected_issues_list\":[\"AI inference unavailable - using offline results\"],\"model_version\":\"offline-fallback\",\"blend_method\":\"offline_only\",\"offline_score\":\"30\",\"processed_at\":\"2025-10-26 16:10:55\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 15:10:55', '2025-10-26 15:10:55'),
(24, 43, 'completed', 3, '{\"transaction_id\":43,\"reference_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/mouse_1759904806.jpg\",\"return_path\":\"C:\\\\xampp\\\\htdocs\\\\Capstone\\\\uploads\\/transaction_photos\\/1760934659_43.jpg\",\"item_size\":\"small\",\"offline_similarity\":\"30\",\"offline_severity\":\"high\"}', '{\"ai_similarity_score\":\"30\",\"final_blended_score\":\"30\",\"ai_confidence\":0,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI inference unavailable - using offline results\",\"ai_detected_issues_list\":[\"AI inference unavailable - using offline results\"],\"model_version\":\"offline-fallback\",\"blend_method\":\"offline_only\",\"offline_score\":\"30\",\"processed_at\":\"2025-10-26 16:10:55\"}', NULL, '2025-10-26 13:33:47', '2025-10-26 15:10:55', '2025-10-26 15:10:55');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Sport Equipment', 'Sports and recreational equipment', '2025-10-05 14:38:53', '2025-10-05 14:38:53'),
(2, 'Lab Equipment', 'Laboratory and scientific equipment', '2025-10-05 14:38:53', '2025-10-05 14:38:53'),
(3, 'Digital Equipment', 'Digital and electronic devices', '2025-10-05 14:38:53', '2025-10-05 14:38:53'),
(4, 'Room Equipment', 'Classroom and room furniture/equipment', '2025-10-05 14:38:53', '2025-10-05 14:38:53'),
(5, 'School Equipment', 'General school supplies and equipment', '2025-10-05 14:38:53', '2025-10-05 14:38:53'),
(6, 'Others', 'Miscellaneous equipment not fitting other categories', '2025-10-05 14:38:53', '2025-10-05 14:38:53');

-- --------------------------------------------------------

--
-- Table structure for table `equipment`
--

CREATE TABLE `equipment` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `rfid_tag` varchar(50) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `size_category` enum('Small','Medium','Large') NOT NULL DEFAULT 'Medium',
  `description` text DEFAULT NULL,
  `image_path` varchar(500) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `equipment`
--

INSERT INTO `equipment` (`id`, `name`, `rfid_tag`, `category_id`, `quantity`, `size_category`, `description`, `image_path`, `image_url`, `created_at`, `updated_at`) VALUES
(1, 'Keyboard', '1', 3, 2, 'Medium', 'Keyboardness', 'uploads/keyboard_1759888342.jpg', NULL, '2025-10-08 01:52:22', '2025-10-18 13:05:29'),
(2, 'Mouse', '2', 3, 4, 'Small', 'CLiPtec Mouse', 'uploads/mouse_1759904806.jpg', NULL, '2025-10-08 06:26:46', '2025-10-18 06:52:08'),
(5, 'Basket Ball', '0037966618', 1, 4, 'Medium', 'For P.E only', 'uploads/basket_ball_1760449241.png', NULL, '2025-10-14 13:40:41', '2025-10-20 10:00:49'),
(6, 'Tukog', '401', 4, 6, 'Large', 'Iuli nig tarong', 'uploads/tukog_1760449709.png', NULL, '2025-10-14 13:48:29', '2025-10-18 06:53:05'),
(7, 'Printer', '3', 3, 1, 'Medium', 'Epson', 'uploads/printer_1760449747.jpg', NULL, '2025-10-14 13:49:07', '2025-10-14 13:49:07'),
(8, 'Volley Ball', '602', 1, 4, 'Small', 'P.e', 'uploads/volley_ball_1760449793.jpg', NULL, '2025-10-14 13:49:53', '2025-10-18 13:04:09'),
(9, 'Lanot', '402', 4, 10, 'Large', 'Iuli sad ni ha', 'uploads/lanot_1760449914.jpg', NULL, '2025-10-14 13:51:54', '2025-10-18 14:31:05');

--
-- Triggers `equipment`
--
DELIMITER $$
CREATE TRIGGER `trg_equipment_size_sync` AFTER UPDATE ON `equipment` FOR EACH ROW BEGIN
    IF NEW.size_category <> OLD.size_category THEN
        UPDATE inventory
        SET item_size = NEW.size_category
        WHERE equipment_id = NEW.rfid_tag;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_update_equipment_rfid` AFTER UPDATE ON `equipment` FOR EACH ROW BEGIN
    IF NEW.rfid_tag <> OLD.rfid_tag THEN
       
        UPDATE inventory
        SET equipment_id = NEW.rfid_tag
        WHERE equipment_id = OLD.rfid_tag;

   
        UPDATE transactions
        SET equipment_id = NEW.rfid_tag
        WHERE equipment_id = OLD.rfid_tag;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_update_inventory_rfid` AFTER UPDATE ON `equipment` FOR EACH ROW BEGIN
    IF NEW.rfid_tag <> OLD.rfid_tag THEN
        UPDATE inventory
        SET equipment_id = NEW.rfid_tag
        WHERE equipment_id = OLD.rfid_tag;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Stand-in structure for view `equipment_inventory_view`
-- (See below for the actual view)
--
CREATE TABLE `equipment_inventory_view` (
`id` int(11)
,`name` varchar(200)
,`rfid_tag` varchar(50)
,`category_name` varchar(100)
,`description` text
,`image_path` varchar(500)
,`image_url` varchar(500)
,`quantity` int(11)
,`size_category` enum('Small','Medium','Large')
,`borrowed_quantity` int(11)
,`available_quantity` bigint(12)
,`damaged_quantity` int(11)
,`item_condition` enum('Excellent','Good','Fair','Poor','Out of Service')
,`item_size` varchar(6)
,`availability_status` varchar(19)
,`location` varchar(200)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `equipment_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `item_size` enum('Small','Medium','Large') NOT NULL DEFAULT 'Medium',
  `available_quantity` int(11) NOT NULL DEFAULT 0,
  `borrowed_quantity` int(11) NOT NULL DEFAULT 0,
  `maintenance_quantity` int(11) NOT NULL DEFAULT 0,
  `damaged_quantity` int(11) NOT NULL DEFAULT 0,
  `item_condition` enum('Excellent','Good','Fair','Poor','Out of Service') DEFAULT 'Good',
  `availability_status` enum('Available','Not Available','Partially Available','Low Stock') DEFAULT 'Available',
  `minimum_stock_level` int(11) DEFAULT 1,
  `location` varchar(200) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `equipment_id`, `quantity`, `item_size`, `available_quantity`, `borrowed_quantity`, `maintenance_quantity`, `damaged_quantity`, `item_condition`, `availability_status`, `minimum_stock_level`, `location`, `notes`, `last_updated`, `created_at`) VALUES
(1, 1, 2, 'Medium', 0, 2, 0, 0, 'Good', 'Not Available', 1, NULL, NULL, '2025-10-28 11:28:16', '2025-10-18 12:33:22'),
(2, 2, 4, 'Small', 3, 1, 0, 0, 'Fair', 'Available', 1, NULL, NULL, '2025-10-29 13:36:09', '2025-10-08 06:26:46'),
(3, 602, 4, 'Small', 4, 0, 0, 0, 'Good', 'Available', 1, NULL, NULL, '2025-10-28 11:28:16', '2025-10-14 13:40:41'),
(4, 37966618, 4, 'Medium', 4, 0, 0, 0, 'Good', 'Available', 1, NULL, NULL, '2025-10-28 11:28:16', '2025-10-14 13:49:07'),
(5, 401, 6, 'Large', 3, 3, 0, 0, 'Good', 'Available', 1, NULL, NULL, '2025-10-28 11:28:16', '2025-10-14 13:49:53'),
(6, 3, 1, 'Medium', 1, 0, 0, 0, 'Good', 'Low Stock', 1, NULL, NULL, '2025-10-28 11:28:16', '2025-10-14 13:51:54'),
(7, 402, 10, 'Large', 10, 0, 0, 0, 'Good', 'Available', 1, NULL, NULL, '2025-10-28 11:28:16', '2025-10-18 13:57:14');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_logs`
--

CREATE TABLE `maintenance_logs` (
  `id` int(11) NOT NULL,
  `equipment_id` varchar(50) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `maintenance_type` enum('Repair','Preventive','Inspection','Cleaning','Calibration','Replacement') NOT NULL,
  `issue_description` text NOT NULL,
  `severity` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `maintenance_quantity` int(11) NOT NULL DEFAULT 1,
  `status` enum('Pending','In Progress','Completed','Cancelled') DEFAULT 'Pending',
  `reported_by` varchar(100) NOT NULL,
  `reported_date` datetime DEFAULT current_timestamp(),
  `assigned_to` varchar(100) DEFAULT NULL,
  `started_date` datetime DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `parts_replaced` text DEFAULT NULL,
  `downtime_hours` decimal(5,2) DEFAULT NULL,
  `before_condition` enum('Excellent','Good','Fair','Poor','Out of Service') DEFAULT NULL,
  `after_condition` enum('Excellent','Good','Fair','Poor','Out of Service') DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_logs`
--

INSERT INTO `maintenance_logs` (`id`, `equipment_id`, `equipment_name`, `maintenance_type`, `issue_description`, `severity`, `maintenance_quantity`, `status`, `reported_by`, `reported_date`, `assigned_to`, `started_date`, `completed_date`, `resolution_notes`, `cost`, `parts_replaced`, `downtime_hours`, `before_condition`, `after_condition`, `next_maintenance_date`, `created_at`, `updated_at`) VALUES
(2, '1', 'Keyboard', 'Cleaning', 'Keys are sticky and need thorough cleaning', 'Low', 1, 'Pending', 'Admin', '2025-10-28 17:22:01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Good', NULL, NULL, '2025-10-28 09:22:01', '2025-10-28 09:22:01'),
(3, '602', 'Mouse', 'Inspection', 'Regular maintenance inspection', 'Low', 1, 'Completed', 'Admin', '2025-10-28 17:22:01', 'Tech Team B', '2025-10-26 17:22:01', '2025-10-27 17:22:01', 'Inspection completed. All components working properly.', NULL, NULL, 0.50, 'Good', 'Excellent', NULL, '2025-10-28 09:22:01', '2025-10-28 09:22:01'),
(4, '2', 'Mouse', 'Inspection', 'Double-click', 'Low', 1, 'In Progress', 'admin', '2025-10-28 17:33:03', 'Jhon', '2025-10-28 18:25:51', NULL, 'rarw', 300.00, 'none', 2.00, 'Fair', 'Fair', '2025-10-29', '2025-10-28 09:33:03', '2025-10-28 10:25:51');

-- --------------------------------------------------------

--
-- Table structure for table `penalties`
--

CREATE TABLE `penalties` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `guideline_id` int(11) DEFAULT NULL,
  `equipment_id` varchar(50) DEFAULT NULL,
  `equipment_name` varchar(255) DEFAULT NULL,
  `penalty_type` enum('Late Return','Damage','Loss','Other') DEFAULT NULL,
  `penalty_amount` decimal(10,2) DEFAULT 0.00,
  `amount_owed` decimal(10,2) DEFAULT 0.00,
  `amount_note` text DEFAULT NULL,
  `days_overdue` int(11) DEFAULT 0,
  `daily_rate` decimal(10,2) DEFAULT 10.00,
  `damage_severity` enum('minor','moderate','severe','total_loss') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `damage_notes` text DEFAULT NULL,
  `status` enum('Pending','Under Review','Resolved','Cancelled','Appealed') DEFAULT 'Pending',
  `imposed_by` int(11) DEFAULT NULL,
  `date_imposed` timestamp NOT NULL DEFAULT current_timestamp(),
  `date_resolved` datetime DEFAULT NULL,
  `resolved_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penalty_damage_assessments`
--

CREATE TABLE `penalty_damage_assessments` (
  `id` int(11) NOT NULL,
  `penalty_id` int(11) NOT NULL,
  `detected_issues` text DEFAULT NULL,
  `similarity_score` decimal(5,2) DEFAULT NULL,
  `comparison_summary` varchar(500) DEFAULT NULL,
  `admin_assessment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penalty_guidelines`
--

CREATE TABLE `penalty_guidelines` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `penalty_type` varchar(100) DEFAULT NULL,
  `penalty_description` text DEFAULT NULL,
  `penalty_amount` decimal(10,2) DEFAULT 0.00,
  `penalty_points` int(11) DEFAULT 0,
  `document_path` varchar(255) DEFAULT NULL,
  `status` enum('draft','active','archived') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `penalty_guidelines`
--

INSERT INTO `penalty_guidelines` (`id`, `title`, `penalty_type`, `penalty_description`, `penalty_amount`, `penalty_points`, `document_path`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(4, 'Overdue Equipment Daily Fee', 'Late Return', 'Applies when borrowed equipment is returned beyond the expected return date. Students are charged ₱10.00 for each day late. Inclusion of Saturdays and Sundays should be reviewed by the IMC in-charge.', 10.00, 0, 'uploads/penalty_documents/penalty_1761697154_69015d823d288.docx', 'active', 1, '2025-10-29 00:19:14', '2025-10-29 11:14:55'),
(5, 'Damaged Equipment - Borrower Repair Requirement', 'Damage', 'When equipment is returned with damage, the borrower is responsible for repairing it. Record the required repair actions and estimated cost in the notes when issuing the penalty.', 0.00, 0, 'uploads/penalty_documents/penalty_1761697192_69015da875962.docx', 'active', 1, '2025-10-29 00:19:52', '2025-10-29 00:19:52'),
(9, 'Minor Scratches of Items', 'Scratches', 'Minor scratches flagged by the system will have a fine for any damages seen.', 5.00, 0, 'uploads/penalty_documents/penalty_1761752315_690234fbcdcc6.docx', 'active', 1, '2025-10-29 15:38:35', '2025-10-29 15:38:35'),
(10, 'dasdadasdasss', 'ssss', 'asdasda', 0.00, 0, 'uploads/penalty_documents/penalty_1761752941_6902376d5690f.docx', 'active', 1, '2025-10-29 15:49:01', '2025-10-29 15:49:01');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `equipment_id` varchar(50) NOT NULL,
  `item_size` enum('Small','Medium','Large') NOT NULL DEFAULT 'Medium',
  `transaction_type` enum('Borrow','Return') NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `expected_return_date` datetime DEFAULT NULL,
  `actual_return_date` datetime DEFAULT NULL,
  `condition_before` enum('Good','Out of Service') DEFAULT NULL,
  `condition_after` enum('Good','Out of Service') DEFAULT NULL,
  `status` enum('Pending Approval','Active','Returned','Overdue','Lost','Damaged','Rejected') NOT NULL DEFAULT 'Active',
  `approval_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `return_review_status` enum('Not Yet Returned','Pending','Manual Review Required','Verified','Flagged','Damage','Rejected') DEFAULT 'Not Yet Returned',
  `penalty_applied` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `processed_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `similarity_score` float DEFAULT NULL,
  `return_verification_status` enum('Not Yet Returned','Pending','Analyzing','Verified','Flagged','Damage','Rejected') DEFAULT 'Not Yet Returned',
  `detected_issues` text DEFAULT NULL,
  `severity_level` enum('none','low','medium','high') DEFAULT 'none',
  `ai_analysis_status` enum('pending','processing','completed','failed') DEFAULT NULL,
  `ai_analysis_message` varchar(255) DEFAULT NULL,
  `ai_similarity_score` float DEFAULT NULL,
  `ai_severity_level` enum('none','medium','high','critical') DEFAULT NULL,
  `ai_analysis_meta` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `user_id`, `equipment_id`, `item_size`, `transaction_type`, `quantity`, `transaction_date`, `expected_return_date`, `actual_return_date`, `condition_before`, `condition_after`, `status`, `approval_status`, `approved_by`, `approved_at`, `rejection_reason`, `return_review_status`, `penalty_applied`, `notes`, `processed_by`, `created_at`, `updated_at`, `similarity_score`, `return_verification_status`, `detected_issues`, `severity_level`, `ai_analysis_status`, `ai_analysis_message`, `ai_similarity_score`, `ai_severity_level`, `ai_analysis_meta`) VALUES
(26, 1, '1', 'Medium', 'Borrow', 1, '2025-10-17 09:47:36', '2025-10-18 17:47:00', NULL, 'Good', NULL, 'Active', 'Approved', 1, '2025-10-17 17:47:36', NULL, 'Not Yet Returned', 0.00, 'Borrowed via kiosk by student ID: 0066629842', '1', '2025-10-17 09:47:36', '2025-10-23 10:31:17', NULL, 'Not Yet Returned', NULL, 'none', NULL, NULL, NULL, NULL, NULL),
(39, 1, '1', 'Medium', 'Borrow', 1, '2025-10-19 10:11:32', '2025-10-20 18:11:00', NULL, 'Good', NULL, 'Active', 'Approved', 1, '2025-10-19 18:11:32', NULL, 'Not Yet Returned', 0.00, 'Borrowed via kiosk by student ID: 0066629842', '1', '2025-10-19 10:11:32', '2025-10-23 10:31:17', NULL, 'Not Yet Returned', NULL, 'none', NULL, NULL, NULL, NULL, NULL),
(43, 1, '2', 'Small', 'Return', 1, '2025-10-20 04:30:31', '2025-10-21 12:30:00', '2025-10-20 12:30:59', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-20 12:30:31', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842 | Returned via kiosk by student ID: 0066629842', '1', '2025-10-20 04:30:31', '2025-10-26 15:10:55', 30, 'Damage', 'AI inference unavailable - using offline results', 'high', 'completed', NULL, 30, 'high', '{\"ai_similarity_score\":\"30\",\"final_blended_score\":\"30\",\"ai_confidence\":0,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI inference unavailable - using offline results\",\"ai_detected_issues_list\":[\"AI inference unavailable - using offline results\"],\"model_version\":\"offline-fallback\",\"blend_method\":\"offline_only\",\"offline_score\":\"30\",\"processed_at\":\"2025-10-26 16:10:55\"}'),
(44, 1, '2', 'Small', 'Return', 1, '2025-10-20 04:40:40', '2025-10-21 12:40:00', '2025-10-20 12:42:21', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-20 12:40:40', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842 | Returned via kiosk by student ID: 0066629842 | Return verified by Admin admin.', '1', '2025-10-20 04:40:40', '2025-10-26 15:10:55', 30, 'Damage', 'AI inference unavailable - using offline results', 'high', 'completed', NULL, 30, 'high', '{\"ai_similarity_score\":\"30\",\"final_blended_score\":\"30\",\"ai_confidence\":0,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI inference unavailable - using offline results\",\"ai_detected_issues_list\":[\"AI inference unavailable - using offline results\"],\"model_version\":\"offline-fallback\",\"blend_method\":\"offline_only\",\"offline_score\":\"30\",\"processed_at\":\"2025-10-26 16:10:55\"}'),
(45, 1, '2', 'Small', 'Return', 1, '2025-10-20 05:52:03', '2025-10-21 13:52:00', '2025-10-20 14:45:42', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-20 13:52:03', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842 | Returned via kiosk by student ID: 0066629842', '1', '2025-10-20 05:52:03', '2025-10-26 15:10:55', 30, 'Damage', 'AI inference unavailable - using offline results', 'high', 'completed', NULL, 30, 'high', '{\"ai_similarity_score\":\"30\",\"final_blended_score\":\"30\",\"ai_confidence\":0,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI inference unavailable - using offline results\",\"ai_detected_issues_list\":[\"AI inference unavailable - using offline results\"],\"model_version\":\"offline-fallback\",\"blend_method\":\"offline_only\",\"offline_score\":\"30\",\"processed_at\":\"2025-10-26 16:10:55\"}'),
(49, 1, '2', 'Small', 'Return', 1, '2025-10-20 09:45:26', '2025-10-21 17:45:00', '2025-10-20 17:45:45', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-20 17:45:26', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842 | Returned via kiosk by student ID: 0066629842', '1', '2025-10-20 09:45:26', '2025-10-26 15:09:46', 30, 'Damage', 'Item mismatch detected – please check return.\nObject structure not recognized.', 'high', 'processing', 'AI analysis in progress', NULL, NULL, NULL),
(52, 1, '401', 'Large', 'Return', 1, '2025-10-20 09:54:14', '2025-10-21 17:54:00', '2025-10-21 15:21:13', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-20 18:04:03', NULL, 'Manual Review Required', 0.00, 'Borrowed via kiosk by student ID: 0066629842 | Returned via kiosk by student ID: 0066629842 | Return verified by Admin admin.', '1', '2025-10-20 09:54:14', '2025-10-26 12:25:43', NULL, 'Pending', 'Manual review required (large item).', 'medium', NULL, NULL, NULL, NULL, NULL),
(55, 1, '2', 'Small', 'Return', 2, '2025-10-22 14:25:02', '2025-10-23 22:24:00', '2025-10-22 22:25:26', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-22 22:25:02', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842 | Returned via kiosk by student ID: 0066629842', '1', '2025-10-22 14:25:02', '2025-10-26 14:03:02', 30, 'Damage', 'Major scratches and dents observed\nShape differences detected\nColor/texture mismatch identified', 'high', 'completed', NULL, 31, 'high', '{\"ai_similarity_score\":31,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Major scratches and dents observed\\nShape differences detected\\nColor\\/texture mismatch identified\",\"ai_detected_issues_list\":[\"Major scratches and dents observed\",\"Shape differences detected\",\"Color\\/texture mismatch identified\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:03:02\"}'),
(56, 1, '2', 'Small', 'Return', 1, '2025-10-22 14:26:26', '2025-10-23 22:26:00', '2025-10-23 14:09:51', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-22 22:26:26', NULL, 'Damage', 0.00, '0', '1', '2025-10-22 14:26:26', '2025-10-26 14:03:00', 39.26, 'Damage', 'Significant structural damage detected\nColor/texture mismatch identified', 'high', 'completed', NULL, 42.26, 'high', '{\"ai_similarity_score\":42.26,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\\nColor\\/texture mismatch identified\",\"ai_detected_issues_list\":[\"Significant structural damage detected\",\"Color\\/texture mismatch identified\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:03:00\"}'),
(59, 1, '2', 'Small', 'Return', 1, '2025-10-23 07:38:47', '2025-10-24 15:38:00', '2025-10-23 15:41:12', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-23 15:38:47', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-23 15:41:12', '1', '2025-10-23 07:38:47', '2025-10-26 14:02:58', 30, 'Damage', 'Major scratches and dents observed', 'high', 'completed', NULL, 25, 'high', '{\"ai_similarity_score\":25,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Major scratches and dents observed\",\"ai_detected_issues_list\":[\"Major scratches and dents observed\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:58\"}'),
(63, 1, '2', 'Small', 'Return', 1, '2025-10-24 01:08:41', '2025-10-25 09:08:00', '2025-10-24 09:46:54', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-24 09:08:41', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-24 09:46:54', '1', '2025-10-24 01:08:41', '2025-10-26 14:02:56', 30, 'Damage', 'Significant structural damage detected\nShape differences detected\nColor/texture mismatch identified', 'high', 'completed', NULL, 25, 'high', '{\"ai_similarity_score\":25,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\\nShape differences detected\\nColor\\/texture mismatch identified\",\"ai_detected_issues_list\":[\"Significant structural damage detected\",\"Shape differences detected\",\"Color\\/texture mismatch identified\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:56\"}'),
(65, 1, '2', 'Small', 'Return', 1, '2025-10-24 01:39:58', '2025-10-25 09:39:00', '2025-10-25 11:19:14', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-24 09:39:58', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-25 11:19:14', '1', '2025-10-24 01:39:58', '2025-10-26 14:02:54', 30, 'Damage', 'Significant structural damage detected', 'high', 'completed', NULL, 29, 'high', '{\"ai_similarity_score\":29,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\",\"ai_detected_issues_list\":[\"Significant structural damage detected\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:54\"}'),
(66, 1, '2', 'Small', 'Return', 1, '2025-10-24 01:45:49', '2025-10-25 09:45:00', '2025-10-25 03:56:33', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-24 09:45:49', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-25 03:56:33', '1', '2025-10-24 01:45:49', '2025-10-26 14:02:52', 30, 'Damage', 'Significant structural damage detected\nShape differences detected', 'high', 'completed', NULL, 29, 'high', '{\"ai_similarity_score\":29,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\\nShape differences detected\",\"ai_detected_issues_list\":[\"Significant structural damage detected\",\"Shape differences detected\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:52\"}'),
(68, 1, '2', 'Small', 'Return', 1, '2025-10-24 18:22:17', '2025-10-26 02:22:00', '2025-10-25 02:22:50', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-25 02:22:17', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-25 02:22:50', '1', '2025-10-24 18:22:17', '2025-10-26 14:02:50', 30, 'Damage', 'Item mismatch – different equipment returned', 'high', 'completed', NULL, 31, 'high', '{\"ai_similarity_score\":31,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Item mismatch \\u2013 different equipment returned\",\"ai_detected_issues_list\":[\"Item mismatch \\u2013 different equipment returned\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:50\"}'),
(70, 1, '2', 'Small', 'Return', 1, '2025-10-24 18:44:29', '2025-10-26 02:44:00', '2025-10-25 02:44:53', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-25 02:44:29', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-25 02:44:53', '1', '2025-10-24 18:44:29', '2025-10-26 14:02:48', 30, 'Damage', 'Significant structural damage detected\nShape differences detected\nColor/texture mismatch identified', 'high', 'completed', NULL, 25, 'high', '{\"ai_similarity_score\":25,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\\nShape differences detected\\nColor\\/texture mismatch identified\",\"ai_detected_issues_list\":[\"Significant structural damage detected\",\"Shape differences detected\",\"Color\\/texture mismatch identified\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:48\"}'),
(73, 1, '2', 'Small', 'Return', 1, '2025-10-25 04:08:37', '2025-10-26 12:08:00', '2025-10-25 12:09:07', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-25 12:08:37', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-25 12:09:07', '1', '2025-10-25 04:08:37', '2025-10-26 14:02:46', 30, 'Damage', 'Significant structural damage detected', 'high', 'completed', NULL, 33, 'high', '{\"ai_similarity_score\":33,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\",\"ai_detected_issues_list\":[\"Significant structural damage detected\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:46\"}'),
(74, 1, '2', 'Small', 'Return', 1, '2025-10-25 08:16:04', '2025-10-26 16:16:00', '2025-10-25 16:16:39', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-25 16:16:04', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-25 16:16:39', '1', '2025-10-25 08:16:04', '2025-10-26 14:02:44', 30, 'Damage', 'Significant structural damage detected\nShape differences detected\nColor/texture mismatch identified', 'high', 'completed', NULL, 27, 'high', '{\"ai_similarity_score\":27,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"Significant structural damage detected\\nShape differences detected\\nColor\\/texture mismatch identified\",\"ai_detected_issues_list\":[\"Significant structural damage detected\",\"Shape differences detected\",\"Color\\/texture mismatch identified\"],\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 15:02:44\"}'),
(75, 1, '2', 'Small', 'Return', 1, '2025-10-25 09:01:06', '2025-10-26 17:01:00', '2025-10-25 17:02:24', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-25 17:01:06', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-25 17:02:24', '1', '2025-10-25 09:01:06', '2025-10-26 13:35:14', 30, 'Damage', 'Item mismatch detected – please check return.\nObject structure not recognized.', 'high', 'completed', NULL, 25, 'high', '{\"ai_similarity_score\":25,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:14\"}'),
(76, 1, '2', 'Small', 'Return', 1, '2025-10-25 09:05:34', '2025-10-26 17:05:00', '2025-10-25 17:13:31', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-25 17:05:34', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-25 17:13:31', '1', '2025-10-25 09:05:34', '2025-10-26 13:35:12', 30, 'Damage', 'Item mismatch detected – please check return.\nObject structure not recognized.\nLow structural similarity.', 'high', 'completed', NULL, 29, 'high', '{\"ai_similarity_score\":29,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:12\"}'),
(77, 1, '2', 'Small', 'Return', 1, '2025-10-25 11:06:07', '2025-10-26 19:06:00', '2025-10-25 19:11:55', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-25 19:06:07', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-25 19:11:55', '1', '2025-10-25 11:06:07', '2025-10-26 13:35:10', 30, 'Damage', 'Item mismatch detected – please check return.\nObject structure not recognized.\nLow structural similarity.', 'high', 'completed', NULL, 35, 'high', '{\"ai_similarity_score\":35,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:10\"}'),
(78, 1, '2', 'Small', 'Return', 1, '2025-10-25 11:21:01', '2025-10-26 19:21:00', '2025-10-25 19:30:49', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-25 19:21:01', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-25 19:30:49\n[System] Comparison queued (awaiting analysis results)\n[System] Comparison complete: 39.86% similarity (SSIM 29.72%, pHash 55.08%) – Confidence: Low', '1', '2025-10-25 11:21:01', '2025-10-26 13:35:08', 30, 'Damage', 'Item mismatch detected – please check return.\nObject structure not recognized.\nLow structural similarity.', 'high', 'completed', NULL, 35, 'high', '{\"ai_similarity_score\":35,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:08\"}'),
(79, 1, '2', 'Small', 'Return', 1, '2025-10-25 18:11:30', '2025-10-27 02:11:00', '2025-10-26 02:12:24', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-26 02:11:30', NULL, 'Verified', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-26 02:12:24\n[System] Comparison queued (awaiting analysis results)\n[System] Comparison complete: 75.12% similarity (SSIM 62.64%, pHash 89.06%), Pixel 89.09% – Confidence: High\n[System] Detected issues: Item returned successfully – no damages detected.', '1', '2025-10-25 18:11:30', '2025-10-26 13:35:06', 71.72, 'Verified', 'Item returned successfully – no damages detected.', 'none', 'completed', NULL, 69.72, 'medium', '{\"ai_similarity_score\":69.72,\"ai_severity_level\":\"medium\",\"ai_detected_issues\":\"AI: Minor wear detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:06\"}'),
(80, 1, '2', 'Small', 'Return', 1, '2025-10-25 18:13:02', '2025-10-27 02:13:00', '2025-10-26 02:21:52', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-26 02:13:02', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-26 02:21:52\n[System] Comparison queued (awaiting analysis results)\n[System] Comparison complete: 56.44% similarity (SSIM 59.7%, pHash 35.94%), Pixel 76.43% – Confidence: Medium\n[System] Detected issues: Minor visual difference detected – verify manually. | Color/lighting variation detected.', '1', '2025-10-25 18:13:02', '2025-10-26 13:35:04', 47.19, 'Damage', 'Item mismatch detected – please check return.\nObject structure not recognized.\nLow structural similarity.', 'high', 'completed', NULL, 44.19, 'high', '{\"ai_similarity_score\":44.19,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:04\"}'),
(81, 1, '2', 'Small', 'Return', 1, '2025-10-25 18:22:48', '2025-10-27 02:22:00', '2025-10-26 02:23:34', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-26 02:22:48', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-26 02:23:34\n[System] Comparison queued (awaiting analysis results)\n[System] Comparison complete: 59.63% similarity (SSIM 33.52%, pHash 88.28%), Pixel 84.55% – Confidence: Medium\n[System] Detected issues: Minor visual difference detected – verify manually. | Color/lighting variation detected. | Surface texture variation detected.', '1', '2025-10-25 18:22:48', '2025-10-26 13:35:01', 30, 'Damage', 'Item mismatch detected – please check return.\nObject structure not recognized.\nLow structural similarity.', 'high', 'completed', NULL, 33, 'high', '{\"ai_similarity_score\":33,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:35:01\"}'),
(82, 1, '2', 'Small', 'Return', 1, '2025-10-25 18:35:06', '2025-10-27 02:35:00', '2025-10-26 02:35:39', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-26 02:35:06', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-26 02:35:39\n[System] Comparison queued (awaiting analysis results)\n[System] Comparison complete: 44.43% similarity (SSIM 11%, pHash 76.95%), Pixel 75.26% – Confidence: Low\n[System] Detected issues: Item mismatch detected – please check return. | Object structure not recognized. | Low structural similarity.', '1', '2025-10-25 18:35:06', '2025-10-26 13:34:59', 30, 'Damage', 'Item mismatch detected – please check return.\nObject structure not recognized.\nLow structural similarity.', 'high', 'completed', NULL, 27, 'high', '{\"ai_similarity_score\":27,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:34:59\"}'),
(83, 1, '2', 'Small', 'Return', 1, '2025-10-25 18:35:58', '2025-10-27 02:35:00', '2025-10-26 02:36:49', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-26 02:35:58', NULL, 'Flagged', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-26 02:36:49\n[System] Comparison queued (awaiting analysis results)\n[System] Comparison complete: 75.35% similarity (SSIM 62.29%, pHash 89.84%), Pixel 89.22% – Confidence: High\n[System] Detected issues: Item returned successfully – no damages detected.', '1', '2025-10-25 18:35:58', '2025-10-26 13:34:57', 69.55, 'Flagged', 'Minor visual difference detected – verify manually.\nSurface texture variation detected.', 'medium', 'completed', NULL, 74.55, 'none', '{\"ai_similarity_score\":74.55,\"ai_severity_level\":\"none\",\"ai_detected_issues\":\"AI: No significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:34:57\"}'),
(84, 1, '0037966618', 'Medium', 'Return', 1, '2025-10-26 12:27:17', '2025-10-27 20:27:00', '2025-10-26 20:27:47', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-26 20:27:17', NULL, 'Damage', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-26 20:27:47\n[System] Comparison queued (awaiting analysis results)\n[System] Comparison complete: 30% similarity (SSIM 23.16%, pHash 58.59%), Pixel 79.33% – Confidence: Low\n[System] Detected issues: Item mismatch detected – please check return. | Object structure not recognized. | Low structural similarity.', '1', '2025-10-26 12:27:17', '2025-10-26 13:34:55', 30, 'Damage', 'Item mismatch detected – please check return.\nObject structure not recognized.\nLow structural similarity.', 'high', 'completed', NULL, 30, 'high', '{\"ai_similarity_score\":30,\"ai_severity_level\":\"high\",\"ai_detected_issues\":\"AI: Significant damage detected\",\"ai_confidence\":0.85,\"model_version\":\"stub-v1.0\",\"processed_at\":\"2025-10-26 14:34:55\"}'),
(85, 1, '401', 'Large', 'Return', 1, '2025-10-26 12:28:07', '2025-10-27 20:28:00', '2025-10-26 20:28:41', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-26 20:28:24', NULL, 'Manual Review Required', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-26 20:28:41', NULL, '2025-10-26 12:28:07', '2025-10-26 12:45:26', NULL, 'Pending', 'Manual review required (large item).', 'medium', NULL, NULL, NULL, NULL, NULL),
(86, 1, '2', 'Small', 'Borrow', 1, '2025-10-26 12:31:01', '2025-10-27 20:31:00', NULL, 'Good', NULL, 'Active', 'Approved', 1, '2025-10-26 20:31:01', NULL, 'Pending', 0.00, 'Borrowed via kiosk by student ID: 0066629842', '1', '2025-10-26 12:31:01', '2025-10-26 12:31:01', NULL, 'Not Yet Returned', NULL, 'none', NULL, NULL, NULL, NULL, NULL),
(87, 1, '2', 'Small', 'Return', 1, '2025-10-29 13:34:13', '2025-10-30 21:34:00', '2025-10-29 21:36:09', 'Good', 'Good', 'Returned', 'Approved', 1, '2025-10-29 21:34:13', NULL, '', 0.00, 'Borrowed via kiosk by student ID: 0066629842Borrowed via kiosk by student ID: 0066629842\n[System] Return processed at 2025-10-29 21:36:09\n[System] Comparison queued (awaiting analysis results)\n[System] Comparison complete: 30% similarity (SSIM 56.02%, pHash 64.45%), Pixel 82.73% – Confidence: Low\n[System] Detected issues: Item mismatch detected – please check return. | Object structure not recognized.\n[System] Comparison failed: Invalid image data', '1', '2025-10-29 13:34:13', '2025-10-29 13:36:11', NULL, 'Pending', 'Comparison failed (invalid image data)', '', NULL, NULL, NULL, NULL, NULL);

--
-- Triggers `transactions`
--
DELIMITER $$
CREATE TRIGGER `update_return_statuses` BEFORE UPDATE ON `transactions` FOR EACH ROW BEGIN
    -- When status changes from Active to Returned
    IF OLD.status = 'Active' AND NEW.status = 'Returned' THEN
        SET NEW.return_review_status = 'Pending';
        SET NEW.return_verification_status = 'Pending';
    END IF;
    
    -- When status changes to Active (new borrow)
    IF NEW.status = 'Active' AND (OLD.status IS NULL OR OLD.status != 'Active') THEN
        SET NEW.return_review_status = 'Not Yet Returned';
        SET NEW.return_verification_status = 'Not Yet Returned';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_photos`
--

CREATE TABLE `transaction_photos` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `photo_type` enum('borrow','return','inspection','comparison','reference') DEFAULT 'return',
  `file_path` varchar(500) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transaction_photos`
--

INSERT INTO `transaction_photos` (`id`, `transaction_id`, `photo_type`, `file_path`, `notes`, `created_at`) VALUES
(3, 43, 'return', 'uploads/transaction_photos/1760934659_43.jpg', NULL, '2025-10-20 04:30:59'),
(4, 44, 'return', 'uploads/transaction_photos/1760935341_44.jpg', NULL, '2025-10-20 04:42:21'),
(5, 45, 'return', 'uploads/transaction_photos/1760942742_45.jpg', NULL, '2025-10-20 06:45:42'),
(6, 49, 'return', 'uploads/transaction_photos/1760953545_49.jpg', NULL, '2025-10-20 09:45:45'),
(10, 52, 'return', 'uploads/transaction_photos/1761031273_52.jpg', NULL, '2025-10-21 07:21:13'),
(11, 55, 'return', 'uploads/transaction_photos/1761143126_55.jpg', NULL, '2025-10-22 14:25:26'),
(12, 56, 'return', 'uploads/transaction_photos/1761199791_56.jpg', NULL, '2025-10-23 06:09:51'),
(18, 59, 'return', 'uploads/transaction_photos/1761205272_59.jpg', NULL, '2025-10-23 07:41:12'),
(21, 63, 'return', 'uploads/transaction_photos/1761270414_63.jpg', NULL, '2025-10-24 01:46:54'),
(22, 68, 'return', 'uploads/transaction_photos/1761330170_68.jpg', NULL, '2025-10-24 18:22:50'),
(23, 70, 'return', 'uploads/transaction_photos/1761331493_70.jpg', NULL, '2025-10-24 18:44:53'),
(24, 66, 'return', 'uploads/transaction_photos/1761335793_66.jpg', NULL, '2025-10-24 19:56:33'),
(25, 65, 'return', 'uploads/transaction_photos/1761362354_65.jpg', NULL, '2025-10-25 03:19:14'),
(26, 73, 'return', 'uploads/transaction_photos/1761365347_73.jpg', NULL, '2025-10-25 04:09:07'),
(27, 74, 'return', 'uploads/transaction_photos/1761380199_74.jpg', NULL, '2025-10-25 08:16:39'),
(28, 75, 'return', 'uploads/transaction_photos/1761382944_75.jpg', NULL, '2025-10-25 09:02:24'),
(29, 76, 'return', 'uploads/transaction_photos/1761383611_76.jpg', NULL, '2025-10-25 09:13:31'),
(37, 77, 'return', 'uploads/transaction_photos/1761390715_77.jpg', NULL, '2025-10-25 11:11:55'),
(40, 78, 'return', 'uploads/transaction_photos/1761391849_78.jpg', NULL, '2025-10-25 11:30:49'),
(41, 79, 'return', 'uploads/transaction_photos/1761415944_79.jpg', NULL, '2025-10-25 18:12:24'),
(44, 80, 'return', 'uploads/transaction_photos/1761416512_80.jpg', NULL, '2025-10-25 18:21:52'),
(45, 81, 'return', 'uploads/transaction_photos/1761416614_81.jpg', NULL, '2025-10-25 18:23:34'),
(46, 82, 'return', 'uploads/transaction_photos/1761417339_82.jpg', NULL, '2025-10-25 18:35:39'),
(47, 83, 'return', 'uploads/transaction_photos/1761417408_83.jpg', NULL, '2025-10-25 18:36:49'),
(48, 84, 'return', 'uploads/transaction_photos/1761481667_84.jpg', NULL, '2025-10-26 12:27:47'),
(49, 85, 'return', 'uploads/transaction_photos/1761481721_85.jpg', NULL, '2025-10-26 12:28:41'),
(50, 87, 'return', 'uploads/transaction_photos/1761744969_87.jpg', NULL, '2025-10-29 13:36:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `rfid_tag` varchar(50) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `status` enum('Active','Inactive','Suspended') DEFAULT 'Active',
  `penalty_points` int(11) DEFAULT 0,
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `rfid_tag`, `student_id`, `status`, `penalty_points`, `registered_at`, `updated_at`) VALUES
(1, '0066629842', '0066629842', 'Active', 7399410, '2025-10-13 12:21:43', '2025-10-19 09:50:17'),
(5, '0036690957', '0036690957', 'Active', 0, '2025-10-28 16:16:38', '2025-10-28 16:16:38');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_pending_damage_penalties`
-- (See below for the actual view)
--
CREATE TABLE `v_pending_damage_penalties` (
`id` int(11)
,`transaction_id` int(11)
,`user_id` int(11)
,`equipment_id` varchar(50)
,`equipment_name` varchar(255)
,`damage_severity` enum('minor','moderate','severe','total_loss')
,`damage_notes` text
,`status` enum('Pending','Under Review','Resolved','Cancelled','Appealed')
,`created_at` timestamp
,`detected_issues` text
,`similarity_score` decimal(5,2)
);

-- --------------------------------------------------------

--
-- Structure for view `active_transactions_view`
--
DROP TABLE IF EXISTS `active_transactions_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_transactions_view`  AS SELECT `t`.`id` AS `id`, `t`.`user_id` AS `user_id`, `t`.`equipment_id` AS `equipment_id`, `e`.`name` AS `equipment_name`, `e`.`rfid_tag` AS `rfid_tag`, `e`.`size_category` AS `size_category`, `t`.`transaction_type` AS `transaction_type`, `t`.`quantity` AS `quantity`, `t`.`transaction_date` AS `transaction_date`, `t`.`expected_return_date` AS `expected_return_date`, `t`.`actual_return_date` AS `actual_return_date`, `t`.`status` AS `status`, `t`.`penalty_applied` AS `penalty_applied`, `t`.`notes` AS `notes`, `t`.`processed_by` AS `processed_by` FROM (`transactions` `t` join `equipment` `e` on(`t`.`equipment_id` = `e`.`id`)) WHERE `t`.`status` = 'Active' ;

-- --------------------------------------------------------

--
-- Structure for view `equipment_inventory_view`
--
DROP TABLE IF EXISTS `equipment_inventory_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `equipment_inventory_view`  AS SELECT `e`.`id` AS `id`, `e`.`name` AS `name`, `e`.`rfid_tag` AS `rfid_tag`, `c`.`name` AS `category_name`, `e`.`description` AS `description`, `e`.`image_path` AS `image_path`, `e`.`image_url` AS `image_url`, `e`.`quantity` AS `quantity`, `e`.`size_category` AS `size_category`, coalesce(`i`.`borrowed_quantity`,0) AS `borrowed_quantity`, coalesce(`i`.`available_quantity`,greatest(`e`.`quantity` - coalesce(`i`.`borrowed_quantity`,0),0)) AS `available_quantity`, coalesce(`i`.`damaged_quantity`,0) AS `damaged_quantity`, `i`.`item_condition` AS `item_condition`, coalesce(`i`.`item_size`,`e`.`size_category`) AS `item_size`, CASE WHEN coalesce(`i`.`available_quantity`,greatest(`e`.`quantity` - coalesce(`i`.`borrowed_quantity`,0),0)) <= 0 THEN 'Out of Stock' WHEN coalesce(`i`.`available_quantity`,greatest(`e`.`quantity` - coalesce(`i`.`borrowed_quantity`,0),0)) < `e`.`quantity` THEN 'Partially Available' ELSE 'Available' END AS `availability_status`, `i`.`location` AS `location`, `e`.`created_at` AS `created_at` FROM ((`equipment` `e` left join `categories` `c` on(`e`.`category_id` = `c`.`id`)) left join `inventory` `i` on(`e`.`rfid_tag` = `i`.`equipment_id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `v_pending_damage_penalties`
--
DROP TABLE IF EXISTS `v_pending_damage_penalties`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pending_damage_penalties`  AS SELECT `p`.`id` AS `id`, `p`.`transaction_id` AS `transaction_id`, `p`.`user_id` AS `user_id`, `p`.`equipment_id` AS `equipment_id`, `p`.`equipment_name` AS `equipment_name`, `p`.`damage_severity` AS `damage_severity`, `p`.`damage_notes` AS `damage_notes`, `p`.`status` AS `status`, `p`.`created_at` AS `created_at`, `da`.`detected_issues` AS `detected_issues`, `da`.`similarity_score` AS `similarity_score` FROM (`penalties` `p` left join `penalty_damage_assessments` `da` on(`da`.`penalty_id` = `p`.`id`)) WHERE `p`.`penalty_type` = 'Damaged' AND `p`.`status` in ('Pending','Under Review') ORDER BY `p`.`created_at` DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `ai_comparison_jobs`
--
ALTER TABLE `ai_comparison_jobs`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `idx_ai_jobs_status_priority` (`status`,`priority`,`created_at`),
  ADD KEY `idx_ai_jobs_txn` (`transaction_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `equipment`
--
ALTER TABLE `equipment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rfid_tag` (`rfid_tag`),
  ADD KEY `fk_equipment_category` (`category_id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inventory_equipment` (`equipment_id`);

--
-- Indexes for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_equipment_id` (`equipment_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_maintenance_type` (`maintenance_type`),
  ADD KEY `idx_reported_date` (`reported_date`);

--
-- Indexes for table `penalties`
--
ALTER TABLE `penalties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_penalties_user` (`user_id`),
  ADD KEY `fk_penalties_transaction` (`transaction_id`),
  ADD KEY `idx_equipment_id` (`equipment_id`),
  ADD KEY `idx_damage_severity` (`damage_severity`),
  ADD KEY `idx_guideline_id` (`guideline_id`);

--
-- Indexes for table `penalty_damage_assessments`
--
ALTER TABLE `penalty_damage_assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_penalty_damage_penalty_id` (`penalty_id`);

--
-- Indexes for table `penalty_guidelines`
--
ALTER TABLE `penalty_guidelines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_penalty_type` (`penalty_type`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_transactions_user` (`user_id`),
  ADD KEY `idx_transaction_date` (`transaction_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_transactions_approval_status` (`approval_status`),
  ADD KEY `idx_transactions_item_size` (`item_size`),
  ADD KEY `fk_transactions_equipment_rfidtag` (`equipment_id`),
  ADD KEY `idx_severity_level` (`severity_level`);

--
-- Indexes for table `transaction_photos`
--
ALTER TABLE `transaction_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transaction_photos_transaction_id` (`transaction_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rfid_tag` (`rfid_tag`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ai_comparison_jobs`
--
ALTER TABLE `ai_comparison_jobs`
  MODIFY `job_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `equipment`
--
ALTER TABLE `equipment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `penalties`
--
ALTER TABLE `penalties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penalty_damage_assessments`
--
ALTER TABLE `penalty_damage_assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penalty_guidelines`
--
ALTER TABLE `penalty_guidelines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `transaction_photos`
--
ALTER TABLE `transaction_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `equipment`
--
ALTER TABLE `equipment`
  ADD CONSTRAINT `fk_equipment_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `maintenance_logs`
--
ALTER TABLE `maintenance_logs`
  ADD CONSTRAINT `maintenance_logs_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`rfid_tag`) ON DELETE CASCADE;

--
-- Constraints for table `penalties`
--
ALTER TABLE `penalties`
  ADD CONSTRAINT `fk_penalties_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_penalties_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `penalty_damage_assessments`
--
ALTER TABLE `penalty_damage_assessments`
  ADD CONSTRAINT `penalty_damage_assessments_ibfk_1` FOREIGN KEY (`penalty_id`) REFERENCES `penalties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_transactions_equi_tagpment_rfid` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`rfid_tag`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transactions_equipment_rfid` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`rfid_tag`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transactions_equipment_rfidtag` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`rfid_tag`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `transaction_photos`
--
ALTER TABLE `transaction_photos`
  ADD CONSTRAINT `fk_transaction_photos_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
