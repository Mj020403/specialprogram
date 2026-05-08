-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 19, 2026 at 11:17 AM
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
-- Database: `harvest`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_activity_log`
--

CREATE TABLE `account_activity_log` (
  `activity_id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `actor_user_id` bigint(20) DEFAULT NULL,
  `activity_type` varchar(80) NOT NULL,
  `activity_summary` varchar(255) NOT NULL,
  `metadata_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assistance_records`
--

CREATE TABLE `assistance_records` (
  `assistance_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `assistance_date` date NOT NULL,
  `assistance_type` varchar(100) NOT NULL,
  `assistance_status` varchar(50) NOT NULL DEFAULT 'Planned',
  `provider_name` varchar(150) DEFAULT NULL,
  `amount_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `outcome_notes` text DEFAULT NULL,
  `next_followup_date` date DEFAULT NULL,
  `evidence_file_path` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `updated_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `module_name` varchar(100) NOT NULL,
  `action_name` varchar(100) NOT NULL,
  `record_id` bigint(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `barangay_id` int(11) NOT NULL,
  `barangay_name` varchar(100) NOT NULL,
  `barangay_lookup_key` varchar(120) NOT NULL,
  `gps_latitude` decimal(10,7) DEFAULT NULL,
  `gps_longitude` decimal(10,7) DEFAULT NULL,
  `map_notes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`barangay_id`, `barangay_name`, `barangay_lookup_key`, `gps_latitude`, `gps_longitude`, `map_notes`, `is_active`, `created_at`) VALUES
(1, 'Balagtas', 'BALAGTAS', 11.1124000, 124.5050000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(2, 'Bonoy', 'BONOY', 11.1220000, 124.4736000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(3, 'Bulak', 'BULAK', 11.1180000, 124.4820000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(4, 'Cambadbad', 'CAMBADBAD', 11.1330000, 124.4880000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(5, 'Candelaria', 'CANDELARIA', 11.1380000, 124.4950000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(6, 'Cansoso', 'CANSOSO', 11.1188000, 124.4620000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(7, 'Imelda', 'IMELDA', 11.1410000, 124.4720000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(8, 'Malazarte', 'MALAZARTE', 11.1167000, 124.4667000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(9, 'Mansaha-on', 'MANSAHA ON', 11.1360000, 124.4580000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(10, 'Mansalip', 'MANSALIP', 11.1230000, 124.4714000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(11, 'Masaba', 'MASABA', 11.1260000, 124.4550000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(12, 'Naulayan', 'NAULAYAN', 11.1310000, 124.4510000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(13, 'Riverside (Poblacion)', 'RIVERSIDE', 11.1245000, 124.4745000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(14, 'San Dionisio', 'SAN DIONISIO', 11.1278000, 124.4685000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(15, 'San Guillermo (Poblacion)', 'SAN GUILLERMO', 11.1236000, 124.4729000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(16, 'San Marcelino', 'SAN MARCELINO', 11.1304000, 124.4628000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(17, 'San Sebastian', 'SAN SEBASTIAN', 11.1290000, 124.4798000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(18, 'San Vicente', 'SAN VICENTE', 11.1163000, 124.4860000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(19, 'Santa Rosa', 'SANTA ROSA', 11.1138000, 124.4930000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(20, 'Santo Rosario', 'SANTO ROSARIO', 11.1098000, 124.4990000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09'),
(21, 'Talisay (Poblacion)', 'TALISAY', 11.1228000, 124.4765000, 'Starter dashboard point; replace with official barangay boundary/centroid later.', 1, '2026-04-01 09:56:09');

-- --------------------------------------------------------

--
-- Table structure for table `beneficiary_member_records`
--

CREATE TABLE `beneficiary_member_records` (
  `beneficiary_record_id` bigint(20) UNSIGNED NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `member_id` bigint(20) DEFAULT NULL,
  `sector_tags` text DEFAULT NULL,
  `indigent_status` varchar(50) DEFAULT NULL,
  `priority_level` varchar(50) DEFAULT NULL,
  `recommendation` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `source_module` varchar(50) NOT NULL DEFAULT 'beneficiaries',
  `updated_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `beneficiary_profiles`
--

CREATE TABLE `beneficiary_profiles` (
  `beneficiary_id` int(11) NOT NULL,
  `household_id` int(11) NOT NULL,
  `member_id` int(11) DEFAULT NULL,
  `sector_tags` varchar(255) DEFAULT NULL,
  `indigent_status` varchar(50) DEFAULT NULL,
  `beneficiary_notes` text DEFAULT NULL,
  `last_assistance_type` varchar(120) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL,
  `data_json` longtext DEFAULT NULL,
  `beneficiary_profile_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `beneficiary_sector_types`
--

CREATE TABLE `beneficiary_sector_types` (
  `sector_type_id` bigint(20) NOT NULL,
  `sector_code` varchar(60) NOT NULL,
  `sector_name` varchar(120) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 999,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `beneficiary_sector_types`
--

INSERT INTO `beneficiary_sector_types` (`sector_type_id`, `sector_code`, `sector_name`, `description`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'male', 'Male', 'Male beneficiaries', 10, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(2, 'female', 'Female', 'Female beneficiaries', 20, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(3, 'pwd', 'PWD', 'Persons with disabilities', 30, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(4, 'senior', 'Senior Citizen', 'Senior citizens', 40, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(5, 'indigent', 'Indigent', 'Households or members tagged indigent', 50, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(6, 'solo_parent', 'Solo Parent', 'Solo parent classification', 60, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(7, 'youth', 'Youth', 'Youth classification', 70, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18');

-- --------------------------------------------------------

--
-- Table structure for table `cbms_asset_records`
--

CREATE TABLE `cbms_asset_records` (
  `asset_id` bigint(20) UNSIGNED NOT NULL,
  `household_id` bigint(20) UNSIGNED NOT NULL,
  `asset_name` varchar(150) NOT NULL,
  `asset_category` varchar(120) DEFAULT NULL,
  `asset_brand` varchar(120) DEFAULT NULL,
  `asset_model` varchar(120) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cbms_household_profiles`
--

CREATE TABLE `cbms_household_profiles` (
  `cbms_household_profile_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `tenure_status` varchar(120) DEFAULT NULL,
  `housing_materials` varchar(255) DEFAULT NULL,
  `toilet_type` varchar(120) DEFAULT NULL,
  `water_source` varchar(120) DEFAULT NULL,
  `electricity_source` varchar(120) DEFAULT NULL,
  `internet_access` varchar(120) DEFAULT NULL,
  `waste_disposal_method` varchar(120) DEFAULT NULL,
  `monthly_household_income` decimal(12,2) DEFAULT NULL,
  `poverty_status` varchar(120) DEFAULT NULL,
  `source_module_code` varchar(60) NOT NULL DEFAULT 'cbms',
  `source_profile_json` longtext DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `updated_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `housing_type` varchar(120) DEFAULT NULL,
  `livelihood_summary` text DEFAULT NULL,
  `crop_summary` text DEFAULT NULL,
  `vehicle_count` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `data_json` longtext DEFAULT NULL,
  `farming_household` tinyint(1) NOT NULL DEFAULT 0,
  `farm_area_hectares` decimal(10,2) DEFAULT NULL,
  `fruit_tree_count_estimate` int(11) DEFAULT NULL,
  `special_program_notes` text DEFAULT NULL,
  `main_livelihood` varchar(150) DEFAULT NULL,
  `monthly_income_band` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cbms_housing_profiles`
--

CREATE TABLE `cbms_housing_profiles` (
  `housing_id` bigint(20) UNSIGNED NOT NULL,
  `household_id` bigint(20) UNSIGNED NOT NULL,
  `housing_type` varchar(120) DEFAULT NULL,
  `roof_material` varchar(120) DEFAULT NULL,
  `wall_material` varchar(120) DEFAULT NULL,
  `tenure_status` varchar(120) DEFAULT NULL,
  `electricity_source` varchar(120) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cbms_livelihood_profiles`
--

CREATE TABLE `cbms_livelihood_profiles` (
  `livelihood_id` bigint(20) UNSIGNED NOT NULL,
  `household_id` bigint(20) UNSIGNED NOT NULL,
  `primary_income_source` varchar(150) DEFAULT NULL,
  `main_livelihood` varchar(150) DEFAULT NULL,
  `monthly_income_band` varchar(100) DEFAULT NULL,
  `employment_notes` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cbms_pets`
--

CREATE TABLE `cbms_pets` (
  `pet_id` int(11) NOT NULL,
  `household_id` int(11) NOT NULL,
  `animal_type` varchar(80) NOT NULL,
  `animal_name` varchar(120) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pet_type` varchar(150) NOT NULL,
  `pet_count` int(11) NOT NULL DEFAULT 1,
  `cbms_pet_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cbms_sanitation_profiles`
--

CREATE TABLE `cbms_sanitation_profiles` (
  `sanitation_id` bigint(20) UNSIGNED NOT NULL,
  `household_id` bigint(20) UNSIGNED NOT NULL,
  `water_source` varchar(150) DEFAULT NULL,
  `toilet_type` varchar(150) DEFAULT NULL,
  `waste_disposal` varchar(150) DEFAULT NULL,
  `drainage_status` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cbms_vehicles`
--

CREATE TABLE `cbms_vehicles` (
  `cbms_vehicle_id` bigint(20) UNSIGNED NOT NULL,
  `household_id` int(11) NOT NULL,
  `vehicle_type` varchar(150) NOT NULL,
  `vehicle_brand` varchar(120) DEFAULT NULL,
  `vehicle_model` varchar(120) DEFAULT NULL,
  `year_model` varchar(20) DEFAULT NULL,
  `plate_number` varchar(60) DEFAULT NULL,
  `color` varchar(60) DEFAULT NULL,
  `ownership_status` varchar(80) DEFAULT NULL,
  `registration_status` varchar(80) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `vehicle_count` int(11) NOT NULL DEFAULT 1,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crops`
--

CREATE TABLE `crops` (
  `crop_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `crop_code` varchar(50) DEFAULT NULL,
  `crop_name` varchar(100) NOT NULL,
  `variety` varchar(100) DEFAULT NULL,
  `plot_name` varchar(100) DEFAULT NULL,
  `tree_count` int(11) NOT NULL DEFAULT 0,
  `planted_date` date DEFAULT NULL,
  `expected_fruiting_date` date DEFAULT NULL,
  `area_sqm` decimal(12,2) DEFAULT NULL,
  `gps_latitude` decimal(10,7) DEFAULT NULL,
  `gps_longitude` decimal(10,7) DEFAULT NULL,
  `qr_reference` varchar(100) DEFAULT NULL,
  `qr_image_path` varchar(255) DEFAULT NULL,
  `current_condition` enum('Good','Bad','Needs Rehab','For Validation') NOT NULL DEFAULT 'For Validation',
  `fruiting_status` enum('Fruiting','Not Fruiting','Unknown') NOT NULL DEFAULT 'Unknown',
  `crop_status` enum('Active','Inactive','Archived') NOT NULL DEFAULT 'Active',
  `remarks` text DEFAULT NULL,
  `created_by` bigint(20) NOT NULL,
  `updated_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `parent_department_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `code`, `name`, `parent_department_id`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'ADMIN', 'Administration', NULL, 'System administration and platform control', 1, '2026-04-18 23:49:15', NULL),
(2, 'AGRI', 'Agriculture', NULL, 'Agriculture operations and records', 1, '2026-04-18 23:49:15', NULL),
(3, 'MON', 'Monitoring', NULL, 'Monitoring and field activity team', 1, '2026-04-18 23:49:15', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `document_categories`
--

CREATE TABLE `document_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_categories`
--

INSERT INTO `document_categories` (`id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 'General Requests', 'General internal workflow routing', 1, '2026-04-18 23:49:15'),
(2, 'Field Operations', 'Operations related documents', 1, '2026-04-18 23:49:15'),
(3, 'System Changes', 'Developer and admin change flows', 1, '2026-04-18 23:49:15');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` bigint(20) NOT NULL,
  `event_code` varchar(50) DEFAULT NULL,
  `event_name` varchar(150) NOT NULL,
  `event_type` varchar(40) DEFAULT NULL,
  `barangay_id` int(11) DEFAULT NULL,
  `venue` varchar(150) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `event_status` enum('Scheduled','Ongoing','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  `created_by` bigint(20) NOT NULL,
  `target_profile_filter` varchar(50) DEFAULT NULL,
  `target_profile_label` varchar(100) DEFAULT NULL,
  `target_rules_json` longtext DEFAULT NULL,
  `invited_households_count` int(11) NOT NULL DEFAULT 0,
  `updated_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_attendance`
--

CREATE TABLE `event_attendance` (
  `attendance_id` bigint(20) NOT NULL,
  `event_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `recorded_by` bigint(20) NOT NULL,
  `attendance_status` enum('Present','Absent','Late','Excused') NOT NULL DEFAULT 'Present',
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `method` enum('Manual','QR Scan') NOT NULL DEFAULT 'Manual',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_program_targets`
--

CREATE TABLE `event_program_targets` (
  `event_program_target_id` bigint(20) NOT NULL,
  `event_id` bigint(20) NOT NULL,
  `program_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `family_members`
--

CREATE TABLE `family_members` (
  `member_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `family_unit_id` bigint(20) DEFAULT NULL,
  `member_sequence_no` int(11) DEFAULT NULL,
  `is_household_head` tinyint(1) NOT NULL DEFAULT 0,
  `full_name` varchar(150) NOT NULL,
  `first_name` varchar(80) DEFAULT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `suffix_name` varchar(20) DEFAULT NULL,
  `relationship_to_head` varchar(100) DEFAULT NULL,
  `sex` enum('Male','Female','Other') DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `place_of_birth` varchar(150) DEFAULT NULL,
  `weight_kg` decimal(8,2) DEFAULT NULL,
  `height_cm` decimal(8,2) DEFAULT NULL,
  `citizenship` varchar(100) DEFAULT NULL,
  `language_spoken` varchar(150) DEFAULT NULL,
  `religious_affiliation` varchar(150) DEFAULT NULL,
  `employment_status` varchar(150) DEFAULT NULL,
  `ofw_details` varchar(255) DEFAULT NULL,
  `current_skill` text DEFAULT NULL,
  `desired_skill` text DEFAULT NULL,
  `unemployed_current_skill` varchar(150) DEFAULT NULL,
  `unemployed_desired_skill` varchar(150) DEFAULT NULL,
  `average_monthly_income` decimal(12,2) DEFAULT NULL,
  `emerging_diseases` text DEFAULT NULL,
  `disability` text DEFAULT NULL,
  `source_profile_json` longtext DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `civil_status` varchar(50) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `email_address` varchar(120) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `education_level` varchar(100) DEFAULT NULL,
  `member_status` varchar(50) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `member_photo_path` varchar(255) DEFAULT NULL,
  `is_primary_farmer` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `remarks` text DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `member_tags` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `family_portal_updates`
--

CREATE TABLE `family_portal_updates` (
  `update_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `crop_id` bigint(20) DEFAULT NULL,
  `update_type` varchar(50) NOT NULL DEFAULT 'Harvest Photo',
  `title` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `activity_date` date DEFAULT NULL,
  `quantity_value` decimal(12,2) DEFAULT NULL,
  `quantity_unit` varchar(30) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `reviewed_by` bigint(20) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `points_awarded` decimal(8,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `family_submissions`
--

CREATE TABLE `family_submissions` (
  `family_submission_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `family_access_id` bigint(20) DEFAULT NULL,
  `submission_type` enum('PROFILE_UPDATE','CROP_UPDATE','HARVEST_UPDATE','FIELD_PHOTO','ASSISTANCE_REQUEST','EVENT_FEEDBACK','OTHER') NOT NULL DEFAULT 'OTHER',
  `crop_id` bigint(20) DEFAULT NULL,
  `event_id` bigint(20) DEFAULT NULL,
  `title` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `submitted_by_name` varchar(150) DEFAULT NULL,
  `submitted_by_contact` varchar(50) DEFAULT NULL,
  `submission_date` date DEFAULT NULL,
  `status` enum('pending','approved','rejected','needs_revision') NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint(20) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `households`
--

CREATE TABLE `households` (
  `household_id` bigint(20) NOT NULL,
  `household_code` varchar(50) DEFAULT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `registered_hh_no` varchar(120) DEFAULT NULL,
  `source_hh_no` varchar(50) DEFAULT NULL,
  `official_hh_no` varchar(120) DEFAULT NULL,
  `hh_base_no` varchar(60) DEFAULT NULL,
  `hh_suffix` varchar(30) DEFAULT NULL,
  `hh_is_excel_supplied` tinyint(1) NOT NULL DEFAULT 0,
  `household_cluster_key` varchar(100) DEFAULT NULL,
  `source_block_label` varchar(20) DEFAULT NULL,
  `source_sheet_name` varchar(150) DEFAULT NULL,
  `barangay_id` int(11) NOT NULL,
  `household_head_name` varchar(150) NOT NULL,
  `sex` enum('Male','Female','Other') DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `purok_sitio` varchar(100) DEFAULT NULL,
  `full_address` varchar(255) DEFAULT NULL,
  `gps_latitude` decimal(10,7) DEFAULT NULL,
  `gps_longitude` decimal(10,7) DEFAULT NULL,
  `area_sqm` decimal(12,2) DEFAULT NULL,
  `area_hectares` decimal(12,4) DEFAULT NULL,
  `household_size` int(11) NOT NULL DEFAULT 1,
  `family_count` int(11) NOT NULL DEFAULT 1,
  `household_group_key` varchar(190) DEFAULT NULL,
  `family_role_label` varchar(80) DEFAULT NULL,
  `program_participation_count` int(11) NOT NULL DEFAULT 0,
  `is_active_farmer` tinyint(1) NOT NULL DEFAULT 1,
  `is_fruit_planter` tinyint(1) NOT NULL DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `source_profile_json` longtext DEFAULT NULL,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `family_photo_path` varchar(255) DEFAULT NULL,
  `qr_reference` varchar(100) DEFAULT NULL,
  `qr_image_path` varchar(255) DEFAULT NULL,
  `last_profiled_at` datetime DEFAULT NULL,
  `head_member_id` bigint(20) DEFAULT NULL,
  `qr_scan_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `family_portal_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `family_last_accessed_at` datetime DEFAULT NULL,
  `record_status` enum('active','archived','deleted') NOT NULL DEFAULT 'active',
  `archived_at` datetime DEFAULT NULL,
  `archived_by` bigint(20) DEFAULT NULL,
  `archive_reason` text DEFAULT NULL,
  `reactivated_at` datetime DEFAULT NULL,
  `reactivated_by` bigint(20) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` bigint(20) DEFAULT NULL,
  `delete_reason` text DEFAULT NULL,
  `created_by` bigint(20) NOT NULL,
  `updated_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source_family_key` varchar(190) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `household_beneficiary_flags`
--

CREATE TABLE `household_beneficiary_flags` (
  `beneficiary_flag_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `indigent_flag` tinyint(1) NOT NULL DEFAULT 0,
  `low_income_flag` tinyint(1) NOT NULL DEFAULT 0,
  `senior_household_flag` tinyint(1) NOT NULL DEFAULT 0,
  `pwd_household_flag` tinyint(1) NOT NULL DEFAULT 0,
  `solo_parent_flag` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `reviewed_by` bigint(20) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_4ps` tinyint(1) NOT NULL DEFAULT 0,
  `has_senior` tinyint(1) NOT NULL DEFAULT 0,
  `has_pwd` tinyint(1) NOT NULL DEFAULT 0,
  `has_solo_parent` tinyint(1) NOT NULL DEFAULT 0,
  `has_pregnant_member` tinyint(1) NOT NULL DEFAULT 0,
  `has_philhealth` tinyint(1) NOT NULL DEFAULT 0,
  `receives_lgu_assistance` tinyint(1) NOT NULL DEFAULT 0,
  `priority_level` varchar(40) DEFAULT NULL,
  `priority_notes` text DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `updated_by` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `household_documents`
--

CREATE TABLE `household_documents` (
  `document_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `title` varchar(150) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `uploaded_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `household_points_log`
--

CREATE TABLE `household_points_log` (
  `point_log_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `source_type` varchar(50) NOT NULL,
  `source_id` bigint(20) DEFAULT NULL,
  `points_awarded` decimal(8,2) NOT NULL DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT NULL,
  `awarded_by` bigint(20) DEFAULT NULL,
  `awarded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `household_points_summary`
--

CREATE TABLE `household_points_summary` (
  `household_id` bigint(20) NOT NULL,
  `total_points` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_score` decimal(10,2) NOT NULL DEFAULT 0.00,
  `qualification_status` varchar(80) DEFAULT NULL,
  `approved_updates` int(11) NOT NULL DEFAULT 0,
  `last_calculated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `household_qualification`
--

CREATE TABLE `household_qualification` (
  `qualification_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `score` decimal(5,2) NOT NULL DEFAULT 0.00,
  `qualification_status` enum('Highly Qualified','Qualified','For Validation','Needs Support','Not Qualified') NOT NULL DEFAULT 'For Validation',
  `has_active_crop` tinyint(1) NOT NULL DEFAULT 0,
  `has_recent_monitoring` tinyint(1) NOT NULL DEFAULT 0,
  `has_good_condition` tinyint(1) NOT NULL DEFAULT 0,
  `has_fruiting_crop` tinyint(1) NOT NULL DEFAULT 0,
  `has_recent_attendance` tinyint(1) NOT NULL DEFAULT 0,
  `has_completed_interview` tinyint(1) NOT NULL DEFAULT 0,
  `latest_harvest_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `explanation` text DEFAULT NULL,
  `last_evaluated_at` datetime DEFAULT NULL,
  `evaluated_by_system` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `household_rule_checklists`
--

CREATE TABLE `household_rule_checklists` (
  `household_rule_checklist_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `checklist_type_id` int(11) NOT NULL,
  `is_checked` tinyint(1) NOT NULL DEFAULT 0,
  `checked_at` datetime DEFAULT NULL,
  `checked_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `household_rule_checklist_types`
--

CREATE TABLE `household_rule_checklist_types` (
  `checklist_type_id` int(11) NOT NULL,
  `item_code` varchar(80) NOT NULL,
  `item_label` varchar(160) NOT NULL,
  `checklist_group` varchar(80) NOT NULL DEFAULT 'Rule Compliance',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `household_rule_checklist_types`
--

INSERT INTO `household_rule_checklist_types` (`checklist_type_id`, `item_code`, `item_label`, `checklist_group`, `sort_order`, `is_active`, `created_at`) VALUES
(1486, 'no_garbage_scattered', 'No garbage scattered', 'Cleanliness', 10, 1, '2026-04-19 09:16:01'),
(1487, 'proper_waste_disposal', 'Proper waste disposal', 'Cleanliness', 20, 1, '2026-04-19 09:16:01'),
(1488, 'clean_surroundings', 'Clean surroundings', 'Cleanliness', 30, 1, '2026-04-19 09:16:01'),
(1489, 'no_dogs_roaming', 'No dogs roaming freely', 'Safety & Order', 40, 1, '2026-04-19 09:16:01'),
(1490, 'animals_controlled', 'Animals controlled', 'Safety & Order', 50, 1, '2026-04-19 09:16:01'),
(1491, 'peaceful_household', 'Peaceful household', 'Safety & Order', 60, 1, '2026-04-19 09:16:01'),
(1492, 'no_smoking_violation', 'No smoking violations', 'Discipline', 70, 1, '2026-04-19 09:16:01'),
(1493, 'no_topless_outside', 'No topless outside', 'Discipline', 80, 1, '2026-04-19 09:16:01'),
(1494, 'follows_barangay_rules', 'Following barangay rules', 'Discipline', 90, 1, '2026-04-19 09:16:01');

-- --------------------------------------------------------

--
-- Table structure for table `household_special_programs`
--

CREATE TABLE `household_special_programs` (
  `application_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `program_id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `applicant_contact` varchar(80) DEFAULT NULL,
  `land_location` varchar(180) DEFAULT NULL,
  `land_area_text` varchar(80) DEFAULT NULL,
  `ownership_type` varchar(80) DEFAULT NULL,
  `orientation_status` varchar(40) DEFAULT NULL,
  `application_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `target_notes` text DEFAULT NULL,
  `intake_notes` text DEFAULT NULL,
  `validation_notes` text DEFAULT NULL,
  `date_applied` date DEFAULT NULL,
  `date_reviewed` date DEFAULT NULL,
  `applied_by` int(11) DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `qualification_result` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `household_violations`
--

CREATE TABLE `household_violations` (
  `violation_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `violation_type_id` int(11) NOT NULL,
  `violation_status` varchar(20) NOT NULL DEFAULT 'Open',
  `observed_on` date DEFAULT NULL,
  `resolved_on` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `encoded_by` int(11) DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `household_violation_types`
--

CREATE TABLE `household_violation_types` (
  `violation_type_id` int(11) NOT NULL,
  `violation_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `severity_level` varchar(20) NOT NULL DEFAULT 'Common',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `household_violation_types`
--

INSERT INTO `household_violation_types` (`violation_type_id`, `violation_name`, `description`, `severity_level`, `is_active`, `created_at`) VALUES
(1, 'Smoking in household areas', NULL, 'Common', 1, '2026-04-19 01:01:18'),
(2, 'Improper garbage disposal', NULL, 'Common', 1, '2026-04-19 01:01:18'),
(3, 'Dog roaming freely', NULL, 'Common', 1, '2026-04-19 01:01:18'),
(4, 'Public topless behavior', NULL, 'Common', 1, '2026-04-19 01:01:18'),
(5, 'Noise disturbance', NULL, 'Common', 1, '2026-04-19 01:01:18'),
(6, 'Unsanitary surroundings', NULL, 'Common', 1, '2026-04-19 01:01:18'),
(7, 'Open burning of waste', NULL, 'Common', 1, '2026-04-19 01:01:18');

-- --------------------------------------------------------

--
-- Table structure for table `import_batches`
--

CREATE TABLE `import_batches` (
  `import_batch_id` bigint(20) NOT NULL,
  `batch_type` varchar(50) DEFAULT NULL,
  `original_file_name` varchar(255) DEFAULT NULL,
  `imported_by` bigint(20) DEFAULT NULL,
  `import_status` enum('processing','success','failed') NOT NULL DEFAULT 'processing',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `finished_at` datetime DEFAULT NULL,
  `import_type` enum('profiling','monitoring') NOT NULL,
  `source_file_name` varchar(255) DEFAULT NULL,
  `created_households` int(11) NOT NULL DEFAULT 0,
  `updated_households` int(11) NOT NULL DEFAULT 0,
  `created_members` int(11) NOT NULL DEFAULT 0,
  `updated_members` int(11) NOT NULL DEFAULT 0,
  `created_interviews` int(11) NOT NULL DEFAULT 0,
  `updated_interviews` int(11) NOT NULL DEFAULT 0,
  `created_crops` int(11) NOT NULL DEFAULT 0,
  `created_monitoring` int(11) NOT NULL DEFAULT 0,
  `issue_count` int(11) NOT NULL DEFAULT 0,
  `summary_json` longtext DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `import_logs`
--

CREATE TABLE `import_logs` (
  `id` int(11) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `imported_by` int(11) DEFAULT NULL,
  `households_created` int(11) DEFAULT 0,
  `families_created` int(11) DEFAULT 0,
  `members_created` int(11) DEFAULT 0,
  `warnings_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interviews`
--

CREATE TABLE `interviews` (
  `interview_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `interviewed_by` bigint(20) NOT NULL,
  `interview_date` date NOT NULL,
  `register_no` varchar(50) DEFAULT NULL,
  `date_encoded` datetime NOT NULL DEFAULT current_timestamp(),
  `allowed_fruit_backyard` tinyint(1) NOT NULL DEFAULT 0,
  `hh_planter_program` tinyint(1) NOT NULL DEFAULT 0,
  `fruit_planting_backyard_program` tinyint(1) NOT NULL DEFAULT 0,
  `intended_number_of_trees` int(11) NOT NULL DEFAULT 0,
  `current_number_of_trees` int(11) NOT NULL DEFAULT 0,
  `program_participation_count` int(11) NOT NULL DEFAULT 0,
  `primary_concern` text DEFAULT NULL,
  `source_of_livelihood` varchar(150) DEFAULT NULL,
  `water_source` varchar(150) DEFAULT NULL,
  `farm_location_notes` text DEFAULT NULL,
  `compliance_status` enum('Fully Compliant','Partially Compliant','Not Compliant','For Validation') NOT NULL DEFAULT 'For Validation',
  `remarks` text DEFAULT NULL,
  `status` enum('Draft','Completed','Archived') NOT NULL DEFAULT 'Completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `module_features`
--

CREATE TABLE `module_features` (
  `feature_id` bigint(20) NOT NULL,
  `module_id` bigint(20) NOT NULL,
  `feature_code` varchar(80) NOT NULL,
  `feature_name` varchar(120) NOT NULL,
  `route_path` varchar(255) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 999,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `module_features`
--

INSERT INTO `module_features` (`feature_id`, `module_id`, `feature_code`, `feature_name`, `route_path`, `sort_order`, `is_enabled`, `created_at`, `updated_at`) VALUES
(1, 1, 'dashboard', 'Dashboard', 'modules/special_program/dashboard.php', 10, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(2, 1, 'households', 'Households', 'modules/special_program/households.php', 20, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(3, 1, 'crops', 'Crops', 'modules/special_program/crops.php', 30, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(4, 1, 'monitoring', 'Monitoring', 'modules/special_program/monitoring.php', 40, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(5, 1, 'interviews', 'Interviews', 'modules/special_program/interviews.php', 50, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(6, 1, 'reports', 'Reports', 'modules/special_program/reports.php', 60, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(7, 2, 'dashboard', 'Dashboard', 'modules/beneficiaries/dashboard.php', 10, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(8, 2, 'households', 'Households', 'modules/beneficiaries/households.php', 20, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(9, 2, 'members', 'Member Registry', 'modules/beneficiaries/members.php', 30, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(10, 2, 'classifications', 'Classifications', 'modules/beneficiaries/classifications.php', 40, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(11, 2, 'assistance', 'Assistance', 'modules/beneficiaries/assistance.php', 50, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(12, 2, 'reports', 'Reports', 'modules/beneficiaries/reports.php', 60, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(13, 3, 'dashboard', 'Dashboard', 'modules/cbms/dashboard.php', 10, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(14, 3, 'households', 'Households', 'modules/cbms/households.php', 20, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(15, 3, 'members', 'Members', 'modules/cbms/members.php', 30, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(16, 3, 'housing', 'Housing', 'modules/cbms/housing.php', 40, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(17, 3, 'livelihood', 'Livelihood', 'modules/cbms/livelihood.php', 50, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(18, 3, 'animals', 'Animals', 'modules/cbms/animals.php', 60, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(19, 3, 'reports', 'Reports', 'modules/cbms/reports.php', 70, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(20, 4, 'dashboard', 'Dashboard', 'modules/mayor/dashboard.php', 10, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(21, 4, 'reports', 'Reports', 'modules/mayor/reports.php', 20, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(22, 4, 'demographics', 'Demographics', 'modules/mayor/demographics.php', 30, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(23, 4, 'agriculture', 'Agriculture', 'modules/mayor/agriculture.php', 40, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(24, 4, 'cbms', 'CBMS Overview', 'modules/mayor/cbms.php', 50, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(25, 5, 'dashboard', 'Dashboard', 'modules/developer/dashboard.php', 10, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(26, 5, 'users', 'Users', 'modules/developer/users.php', 20, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(27, 5, 'permissions', 'Permissions', 'modules/developer/permissions.php', 30, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(28, 5, 'modules', 'Modules', 'modules/developer/modules.php', 40, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(29, 5, 'imports', 'Imports', 'modules/developer/imports.php', 50, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(30, 5, 'settings', 'Settings', 'modules/developer/settings.php', 60, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18'),
(31, 5, 'logs', 'Logs', 'modules/developer/logs.php', 70, 1, '2026-04-10 14:10:18', '2026-04-10 14:10:18');

-- --------------------------------------------------------

--
-- Table structure for table `monitoring_visits`
--

CREATE TABLE `monitoring_visits` (
  `monitoring_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `crop_id` bigint(20) DEFAULT NULL,
  `monitored_by` bigint(20) NOT NULL,
  `monitoring_date` date NOT NULL,
  `visit_time` time DEFAULT NULL,
  `tree_count_observed` int(11) NOT NULL DEFAULT 0,
  `fruiting_status` enum('Fruiting','Not Fruiting','Unknown') NOT NULL DEFAULT 'Unknown',
  `crop_condition` enum('Good','Bad','Needs Rehab','For Validation') NOT NULL DEFAULT 'For Validation',
  `needs_rehabilitation` tinyint(1) NOT NULL DEFAULT 0,
  `harvest_kg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `monitoring_method` enum('Manual Search','QR Scan','Interview Follow-up','Event Follow-up') NOT NULL DEFAULT 'Manual Search',
  `weather_condition` varchar(100) DEFAULT NULL,
  `issue_observed` text DEFAULT NULL,
  `action_recommended` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` bigint(20) NOT NULL,
  `household_id` bigint(20) DEFAULT NULL,
  `crop_id` bigint(20) DEFAULT NULL,
  `notification_type` enum('Missing Interview','Needs Monitoring','Needs Rehab','Low Harvest','Upcoming Event','Qualification Updated','QR Scanned') NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `severity` enum('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `official_crops`
--

CREATE TABLE `official_crops` (
  `crop_id` bigint(20) NOT NULL,
  `crop_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `official_crops`
--

INSERT INTO `official_crops` (`crop_id`, `crop_name`, `is_active`, `created_by`, `created_at`) VALUES
(1, 'Cacao', 1, NULL, '2026-04-01 09:56:09'),
(2, 'Guava', 1, NULL, '2026-04-01 09:56:09'),
(3, 'Lanzones', 1, NULL, '2026-04-01 09:56:09'),
(4, 'Mangosteen', 1, NULL, '2026-04-01 09:56:09'),
(5, 'Durian', 1, 2, '2026-04-01 10:47:57');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `reset_request_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `requested_by` varchar(150) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `request_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `temp_password_hash` varchar(255) DEFAULT NULL,
  `temp_password_plain` varchar(120) DEFAULT NULL,
  `approved_by` bigint(20) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `review_notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_reset_requests`
--

INSERT INTO `password_reset_requests` (`reset_request_id`, `user_id`, `requested_by`, `reason`, `request_status`, `temp_password_hash`, `temp_password_plain`, `approved_by`, `approved_at`, `review_notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'taskforce', '', 'Rejected', NULL, NULL, 3, '2026-04-05 08:35:05', '', '2026-04-04 12:11:12', '2026-04-05 00:35:05'),
(2, 2, 'mayor@matagob.gov.ph', '', 'Approved', '$2y$10$Zu6RnyopDpJnnFlyKg3YIOd0Ax2hxpiQye9Plg25bTjen.05oxz/O', 'yehRnRhX8X', 3, '2026-04-04 21:00:30', '', '2026-04-04 12:59:36', '2026-04-04 13:00:30');

-- --------------------------------------------------------

--
-- Table structure for table `profile_update_requests`
--

CREATE TABLE `profile_update_requests` (
  `request_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `position_title` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `request_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `reviewed_by` bigint(20) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profile_update_requests`
--

INSERT INTO `profile_update_requests` (`request_id`, `user_id`, `full_name`, `email`, `contact_number`, `position_title`, `bio`, `avatar_path`, `request_status`, `reviewed_by`, `reviewed_at`, `review_notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'Default Task Force', 'taskforce@matagob.gov.ph', '09170000001', 'Task Force Officer', '', 'uploads/users/20260404125510-719c1f42.jpg', 'Approved', 3, '2026-04-04 18:56:16', '', '2026-04-04 10:55:10', '2026-04-04 10:56:16'),
(2, 2, 'Municipal Mayor', 'mayor@matagob.gov.ph', '09170000002', 'Mayor', '', NULL, 'Approved', 3, '2026-04-04 18:58:28', '', '2026-04-04 10:58:11', '2026-04-04 10:58:28'),
(3, 2, 'Municipal Mayor', 'mayor@matagob.gov.ph', '09170000002', 'Mayor', '', 'uploads/users/20260404125905-7c8ad219.jpg', 'Approved', 3, '2026-04-04 18:59:24', '', '2026-04-04 10:59:05', '2026-04-04 10:59:24');

-- --------------------------------------------------------

--
-- Table structure for table `qr_codes`
--

CREATE TABLE `qr_codes` (
  `qr_id` bigint(20) NOT NULL,
  `household_id` bigint(20) DEFAULT NULL,
  `crop_id` bigint(20) DEFAULT NULL,
  `qr_type` enum('HOUSEHOLD','CROP') NOT NULL,
  `qr_reference` varchar(100) NOT NULL,
  `qr_payload` text NOT NULL,
  `qr_image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `generated_by` bigint(20) NOT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_scanned_at` datetime DEFAULT NULL,
  `total_scans` int(11) NOT NULL DEFAULT 0,
  `scan_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qr_scan_logs`
--

CREATE TABLE `qr_scan_logs` (
  `scan_log_id` bigint(20) NOT NULL,
  `qr_id` bigint(20) NOT NULL,
  `scanned_by` bigint(20) NOT NULL,
  `scanned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `scan_location` varchar(255) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `action_taken` varchar(150) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qualification_history`
--

CREATE TABLE `qualification_history` (
  `qualification_history_id` bigint(20) NOT NULL,
  `household_id` bigint(20) NOT NULL,
  `score` decimal(5,2) NOT NULL,
  `qualification_status` enum('Highly Qualified','Qualified','For Validation','Needs Support','Not Qualified') NOT NULL,
  `explanation` text DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qualification_rules`
--

CREATE TABLE `qualification_rules` (
  `rule_id` bigint(20) NOT NULL,
  `rule_key` varchar(80) NOT NULL,
  `rule_label` varchar(120) NOT NULL,
  `points_value` decimal(8,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `monthly_cap` int(11) DEFAULT NULL,
  `per_crop_day_cap` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qualification_rules`
--

INSERT INTO `qualification_rules` (`rule_id`, `rule_key`, `rule_label`, `points_value`, `is_active`, `monthly_cap`, `per_crop_day_cap`, `description`, `created_at`, `updated_at`) VALUES
(1, 'harvest_update_points', 'Harvest update points', 20.00, 1, NULL, 1, 'Approved harvest update for a registered crop', '2026-04-05 06:27:00', '2026-04-05 06:27:00'),
(2, 'crop_update_points', 'Crop update points', 10.00, 1, NULL, 1, 'Approved crop progress update for a registered crop', '2026-04-05 06:27:00', '2026-04-05 06:27:00'),
(3, 'field_photo_points', 'Field photo points', 5.00, 1, 2, NULL, 'Approved field photo or proof update', '2026-04-05 06:27:00', '2026-04-05 06:27:00'),
(4, 'family_note_points', 'Family note points', 0.00, 1, NULL, NULL, 'Approved non-crop family note', '2026-04-05 06:27:00', '2026-04-05 06:27:00'),
(5, 'minimum_total_score_qualified', 'Minimum total score to qualify', 60.00, 1, NULL, NULL, 'Combined score needed for Qualified status', '2026-04-05 06:27:00', '2026-04-05 06:27:00'),
(6, 'minimum_total_score_validation', 'Minimum total score for validation', 40.00, 1, NULL, NULL, 'Combined score needed for validation and support staging', '2026-04-05 06:27:00', '2026-04-05 06:27:00');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `can_manage_users` tinyint(1) NOT NULL DEFAULT 0,
  `can_interview` tinyint(1) NOT NULL DEFAULT 0,
  `can_monitor` tinyint(1) NOT NULL DEFAULT 0,
  `can_manage_events` tinyint(1) NOT NULL DEFAULT 0,
  `can_take_attendance` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_dashboard` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_reports` tinyint(1) NOT NULL DEFAULT 0,
  `can_export_data` tinyint(1) NOT NULL DEFAULT 0,
  `can_scan_qr` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `description`, `can_manage_users`, `can_interview`, `can_monitor`, `can_manage_events`, `can_take_attendance`, `can_view_dashboard`, `can_view_reports`, `can_export_data`, `can_scan_qr`, `created_at`) VALUES
(1, 'TASK_FORCE', 'Field and system operational user with encoding and management rights', 1, 1, 1, 1, 1, 1, 1, 1, 1, '2026-04-01 09:56:09'),
(2, 'MAYOR', 'View-only executive user for dashboard and reports', 0, 0, 0, 0, 0, 1, 1, 1, 1, '2026-04-01 09:56:09'),
(3, 'DEVELOPER', 'System developer account with user governance rights', 1, 1, 1, 1, 1, 1, 1, 1, 1, '2026-04-04 10:54:04'),
(4, 'SPECIAL_PROGRAM', 'Special Program module staff', 0, 1, 1, 1, 1, 1, 1, 1, 1, '2026-04-10 14:10:18'),
(5, 'BENEFICIARIES', 'Beneficiaries module staff', 0, 0, 0, 0, 0, 1, 1, 1, 0, '2026-04-10 14:10:18'),
(6, 'BENEFICIARY_STAFF', 'Beneficiaries module encoder/staff', 0, 0, 0, 0, 0, 1, 1, 0, 0, '2026-04-10 14:10:18'),
(7, 'CBMS', 'CBMS module lead/staff', 0, 0, 0, 0, 0, 1, 1, 1, 0, '2026-04-10 14:10:18'),
(8, 'CBMS_STAFF', 'CBMS encoder/staff', 0, 0, 0, 0, 0, 1, 1, 0, 0, '2026-04-10 14:10:18'),
(9, 'ADMIN', 'Full platform administrator', 1, 1, 1, 1, 1, 1, 1, 1, 1, '2026-04-10 14:10:18');

-- --------------------------------------------------------

--
-- Table structure for table `role_feature_permissions`
--

CREATE TABLE `role_feature_permissions` (
  `permission_id` bigint(20) NOT NULL,
  `role_id` int(11) NOT NULL,
  `feature_id` bigint(20) NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT 1,
  `can_create` tinyint(1) NOT NULL DEFAULT 0,
  `can_update` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete` tinyint(1) NOT NULL DEFAULT 0,
  `can_export` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `signup_requests`
--

CREATE TABLE `signup_requests` (
  `signup_request_id` bigint(20) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `position_title` varchar(100) DEFAULT NULL,
  `desired_role` varchar(50) NOT NULL DEFAULT 'TASK_FORCE',
  `avatar_path` varchar(255) DEFAULT NULL,
  `request_status` varchar(30) NOT NULL DEFAULT 'Pending',
  `reviewed_by` bigint(20) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `special_programs`
--

CREATE TABLE `special_programs` (
  `program_id` int(11) NOT NULL,
  `program_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `special_programs`
--

INSERT INTO `special_programs` (`program_id`, `program_name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Gamefowl', 'Chicken breeding and sport support program.', 1, '2026-04-19 01:01:18', NULL),
(2, 'Livestock', 'Animal raising support program.', 1, '2026-04-19 01:01:18', NULL),
(3, 'Fruit Bearing Trees', 'Fruit tree planting and maintenance support.', 1, '2026-04-19 01:01:18', NULL),
(4, 'HVCD', 'High Value Crops Development Program.', 1, '2026-04-19 01:01:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `special_program_items`
--

CREATE TABLE `special_program_items` (
  `item_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `special_program_items`
--

INSERT INTO `special_program_items` (`item_id`, `program_id`, `item_name`, `description`, `is_active`, `created_at`) VALUES
(1, 1, 'American Gamefowl', NULL, 1, '2026-04-19 01:01:18'),
(2, 1, 'Sweater', NULL, 1, '2026-04-19 01:01:18'),
(3, 1, 'Roundhead', NULL, 1, '2026-04-19 01:01:18'),
(4, 1, 'Kelso', NULL, 1, '2026-04-19 01:01:18'),
(5, 1, 'Hatch', NULL, 1, '2026-04-19 01:01:18'),
(6, 2, 'Cattle', NULL, 1, '2026-04-19 01:01:18'),
(7, 2, 'Goat', NULL, 1, '2026-04-19 01:01:18'),
(8, 2, 'Pig', NULL, 1, '2026-04-19 01:01:18'),
(9, 2, 'Carabao', NULL, 1, '2026-04-19 01:01:18'),
(10, 2, 'Sheep', NULL, 1, '2026-04-19 01:01:18'),
(11, 3, 'Mango', NULL, 1, '2026-04-19 01:01:18'),
(12, 3, 'Coconut', NULL, 1, '2026-04-19 01:01:18'),
(13, 3, 'Banana', NULL, 1, '2026-04-19 01:01:18'),
(14, 3, 'Calamansi', NULL, 1, '2026-04-19 01:01:18'),
(15, 3, 'Jackfruit', NULL, 1, '2026-04-19 01:01:18'),
(16, 3, 'Avocado', NULL, 1, '2026-04-19 01:01:18'),
(17, 3, 'Guava', NULL, 1, '2026-04-19 01:01:18'),
(18, 4, 'Tomato', NULL, 1, '2026-04-19 01:01:18'),
(19, 4, 'Eggplant', NULL, 1, '2026-04-19 01:01:18'),
(20, 4, 'Chili', NULL, 1, '2026-04-19 01:01:18'),
(21, 4, 'Onion', NULL, 1, '2026-04-19 01:01:18'),
(22, 4, 'Coffee', NULL, 1, '2026-04-19 01:01:18'),
(23, 4, 'Cacao', NULL, 1, '2026-04-19 01:01:18'),
(24, 4, 'Garlic', NULL, 1, '2026-04-19 01:01:18'),
(25, 4, 'Ginger', NULL, 1, '2026-04-19 01:01:18'),
(26, 4, 'Pineapple', NULL, 1, '2026-04-19 01:01:18'),
(27, 4, 'Banana', NULL, 1, '2026-04-19 01:01:18');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'system_title', 'Matag-ob Platform', 'Official project title', '2026-04-19 09:10:48'),
(2, 'database_name', 'smart', 'Database name', '2026-04-01 09:56:09'),
(3, 'qr_prefix_household', 'QR-HH', 'QR prefix for household', '2026-04-01 09:56:09'),
(4, 'qr_prefix_crop', 'QR-CRP', 'QR prefix for crop', '2026-04-01 09:56:09'),
(5, 'household_prefix', 'HH', 'Household code prefix', '2026-04-01 09:56:09'),
(6, 'event_prefix', 'EVT', 'Event code prefix', '2026-04-01 09:56:09'),
(7, 'system_logo_path', 'uploads/system/20260417093713-c2909fb7.png', NULL, '2026-04-17 07:37:13'),
(8, 'system_subtitle', 'One municipality, one database, role-based workspaces.', 'Header subtitle', '2026-04-19 09:10:48'),
(9, 'system_loader_text', 'Loading HARVEST System workspace...', 'Loader text', '2026-04-18 23:51:43'),
(10, 'system_report_title', 'HARVEST System Consolidated Family Report', 'Printable report title', '2026-04-18 23:51:43'),
(11, 'system_report_subtitle', 'Harvest Assistance for Resource Validation, Evaluation, and Strategic Tracking', 'Printable report subtitle', '2026-04-18 23:51:43'),
(12, 'login_intro_badge_text', 'Welcome', 'Login badge text', '2026-04-18 23:51:43'),
(13, 'login_intro_description', 'Agricultural resource validation, evaluation, and strategic tracking in one platform.', 'Login badge description', '2026-04-18 23:51:43'),
(14, 'login_hero_title', 'Sign in once and open only the menus allowed for your role.', 'Login hero title', '2026-04-18 23:51:43'),
(15, 'login_hero_body', 'Task Force, CBMS, Mayor, and Developer work inside one shared system. Menus and permissions are controlled by user role.', 'Login hero body', '2026-04-18 23:51:43'),
(16, 'login_card_title', 'Welcome back', 'Login card title', '2026-04-18 23:51:43'),
(17, 'login_card_subtitle', 'Sign in to access your automation dashboard.', 'Login card subtitle', '2026-04-18 23:51:43'),
(18, 'family_portal_enabled', '0', 'Master switch for the family portal.', '2026-04-19 09:10:48'),
(19, 'family_scan_enabled', '0', 'Show or hide the Scan QR card on the login page.', '2026-04-19 09:10:48'),
(20, 'family_dashboard_enabled', '0', 'Allow or block family dashboard pages.', '2026-04-19 09:10:48'),
(21, 'family_submission_enabled', '0', 'Allow or block family submissions.', '2026-04-19 09:10:48'),
(22, 'documents.max_upload_size_mb', '25', 'Maximum upload size in megabytes.', '2026-04-19 09:10:48'),
(23, 'dashboard.overdue_days_threshold', '3', 'Number of days before tasks become overdue.', '2026-04-19 09:10:48'),
(24, 'system_entry_flow', 'login_first_role_based', 'Target flow: go directly to login, then open role-based menus.', '2026-04-18 03:55:57'),
(25, 'household_data_model', 'grouped_household_with_multiple_families', 'One household may contain multiple families. HH No. must come from source data only.', '2026-04-18 03:55:57'),
(26, 'hh_number_policy', 'excel_only_no_auto_generation', 'HH No. must only come from Excel/source data. Blank source means blank HH No.', '2026-04-18 03:55:57'),
(27, 'dashboard_primary_household_card_label', 'Total Households', 'Use this label for grouped household count.', '2026-04-18 04:34:45'),
(28, 'dashboard_secondary_family_card_label', 'Total Families', 'Use this label for family-unit count.', '2026-04-18 04:34:45'),
(29, 'dashboard_household_hint', 'A household may contain one or more families.', 'Reference note for dashboard wording.', '2026-04-18 04:34:45'),
(30, 'system_browser_title_suffix', 'HARVEST System', 'Browser title suffix', '2026-04-18 23:51:43'),
(31, 'system_header_search_placeholder', 'Search family member, head, code, or contact', 'Global search placeholder', '2026-04-18 23:51:43'),
(32, 'operational_report_title', 'HARVEST System Operational Dashboard Report', 'Operational report title', '2026-04-18 23:51:43'),
(33, 'operational_report_subtitle', 'Harvest Assistance for Resource Validation, Evaluation, and Strategic Tracking', 'Operational report subtitle', '2026-04-18 23:51:43'),
(34, 'system_reports_page_title', 'Operational family reports', 'Reports page heading', '2026-04-18 23:51:43'),
(35, 'system_reports_page_description', 'Review qualification load, compare barangays, spot suspicious household sizes, and export a cleaner report package for field validation.', 'Reports page intro', '2026-04-18 23:51:43'),
(36, 'system_reports_export_note', 'Exports follow the same barangay, status, and profile filters shown below.', 'Reports export note', '2026-04-18 23:51:43'),
(37, 'system_operational_page_title', 'Operational dashboard print report', 'Operational print heading', '2026-04-18 23:51:43'),
(38, 'system_operational_page_description', 'Printable executive summary for filtered households, barangays, and qualification status.', 'Operational print intro', '2026-04-18 23:51:43'),
(39, 'login_browser_title', 'Login - HARVEST System', 'Browser tab title', '2026-04-18 23:51:43'),
(40, 'login_badge_label', 'Unified Login', 'Login badge label', '2026-04-18 23:51:43'),
(41, 'login_panel_caption', 'Unified role-based municipal system', 'Login panel caption', '2026-04-18 23:51:43'),
(42, 'login_feature_one_title', 'Login first', 'Feature box 1 title', '2026-04-18 23:51:43'),
(43, 'login_feature_one_body', 'Users no longer choose a module first. The system reads the account role and opens the correct workspace automatically.', 'Feature box 1 body', '2026-04-18 23:51:43'),
(44, 'login_feature_two_title', 'Household-first data', 'Feature box 2 title', '2026-04-18 23:51:43'),
(45, 'login_feature_two_body', 'Households, families, and members stay in one shared database, with HH numbers coming only from source Excel data.', 'Feature box 2 body', '2026-04-18 23:51:43'),
(46, 'login_access_note', 'Task Force, CBMS, Mayor, Beneficiaries, and Developer use this same sign-in page.', 'Login access note', '2026-04-18 23:51:43'),
(47, 'login_submit_label', 'Sign In', 'Login button label', '2026-04-18 23:51:43'),
(48, 'family_access_title', 'Family access', 'Family access card title', '2026-04-18 23:51:43'),
(49, 'family_access_description', 'Families can scan or enter their QR reference to open their own dashboard.', 'Family access card description', '2026-04-18 23:51:43'),
(50, 'family_access_button_label', 'Scan QR', 'Family access button label', '2026-04-18 23:51:43'),
(51, 'mayor_dashboard_title', 'Mayor decision dashboard', 'Mayor dashboard heading', '2026-04-18 23:51:43'),
(52, 'mayor_dashboard_description', 'Executive view of households, interventions, barangay insights, and support queues.', 'Mayor dashboard intro', '2026-04-18 23:51:43');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` bigint(20) NOT NULL,
  `role_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `position_title` varchar(100) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_status` varchar(30) NOT NULL DEFAULT 'approved',
  `profile_reviewed_by` bigint(20) DEFAULT NULL,
  `profile_reviewed_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `role_id`, `full_name`, `username`, `password_hash`, `email`, `contact_number`, `position_title`, `avatar_path`, `bio`, `profile_status`, `profile_reviewed_by`, `profile_reviewed_at`, `is_active`, `last_login_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'Default Task Force', 'taskforce', '$2y$10$dmDRTWRf2VVDnlUgU0itAeoYXBsYr9XPdLYbW0cZfOkQLg6nqZqv2', 'taskforce@matagob.gov.ph', '09170000001', 'Task Force Officer', 'uploads/profile_pictures/20260405061508-c4646a5a.jpg', '', 'approved', NULL, NULL, 1, '2026-04-19 17:11:09', NULL, '2026-04-01 09:56:09', '2026-04-19 09:11:09'),
(2, 2, 'Municipal Mayor', 'mayor', '$2y$10$O3PNWcdeYzqLiRy3PBLNfeQjwMI/EqzRine7GvvBRp6T1FQhUnPey', 'mayor@matagob.gov.ph', '09170000002', 'Mayor', 'uploads/profile_pictures/20260411010023-76471358.png', '', 'approved', NULL, NULL, 1, '2026-04-19 17:09:32', NULL, '2026-04-01 09:56:09', '2026-04-19 09:09:32'),
(3, 3, 'System Developer', 'developer', 'e1a7b8ad45f95c9d0f401381236891d369ca80790393e307805e1dd700f8ecca', 'developer@matagob.gov.ph', '09170000003', 'System Developer', 'uploads/profile_pictures/20260405061428-f924577f.jpg', '', 'approved', NULL, NULL, 1, '2026-04-19 17:10:25', NULL, '2026-04-04 10:54:04', '2026-04-19 09:10:25');

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_family_consolidated`
-- (See below for the actual view)
--
CREATE TABLE `v_family_consolidated` (
`household_id` bigint(20)
,`household_code` varchar(50)
,`reference_no` varchar(50)
,`household_head_name` varchar(150)
,`barangay_name` varchar(100)
,`full_address` varchar(255)
,`contact_number` varchar(30)
,`area_sqm` decimal(12,2)
,`area_hectares` decimal(12,4)
,`household_size` int(11)
,`program_participation_count` int(11)
,`is_fruit_planter` tinyint(1)
,`is_active_farmer` tinyint(1)
,`member_count` bigint(21)
,`member_photo_count` bigint(21)
,`family_member_names` mediumtext
,`active_crop_count` bigint(21)
,`total_tree_count` decimal(32,0)
,`latest_interview_date` date
,`latest_monitoring_date` date
,`latest_crop_condition` varchar(14)
,`latest_fruiting_status` varchar(12)
,`total_harvest_kg` decimal(34,2)
,`total_events_attended` bigint(21)
,`score` decimal(5,2)
,`qualification_status` enum('Highly Qualified','Qualified','For Validation','Needs Support','Not Qualified')
,`explanation` text
,`last_evaluated_at` datetime
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_mayor_dashboard_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_mayor_dashboard_summary` (
`total_households` bigint(21)
,`total_family_members` bigint(21)
,`total_member_photos` bigint(21)
,`total_completed_interviews` bigint(21)
,`total_monitoring_visits` bigint(21)
,`total_active_crops` bigint(21)
,`total_tree_count` decimal(32,0)
,`total_harvest_kg` decimal(34,2)
,`highly_qualified_count` bigint(21)
,`qualified_count` bigint(21)
,`needs_support_count` bigint(21)
,`total_events` bigint(21)
,`total_attendance_records` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `workflow_steps`
--

CREATE TABLE `workflow_steps` (
  `id` int(11) NOT NULL,
  `workflow_template_id` int(11) NOT NULL,
  `step_name` varchar(150) NOT NULL,
  `step_order` int(11) NOT NULL DEFAULT 1,
  `role_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workflow_steps`
--

INSERT INTO `workflow_steps` (`id`, `workflow_template_id`, `step_name`, `step_order`, `role_name`, `created_at`) VALUES
(1, 1, 'Initial Review', 1, 'task_force', '2026-04-18 23:49:15'),
(2, 1, 'Final Approval', 2, 'mayor', '2026-04-18 23:49:15');

-- --------------------------------------------------------

--
-- Table structure for table `workflow_templates`
--

CREATE TABLE `workflow_templates` (
  `id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workflow_templates`
--

INSERT INTO `workflow_templates` (`id`, `category_id`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Standard Approval Flow', 'Basic approval chain template', 1, '2026-04-18 23:49:15', NULL);

-- --------------------------------------------------------

--

--

--

--

--

--

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_activity_log`
--
ALTER TABLE `account_activity_log`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_activity_actor` (`actor_user_id`),
  ADD KEY `idx_activity_type` (`activity_type`);

--
-- Indexes for table `assistance_records`
--
ALTER TABLE `assistance_records`
  ADD PRIMARY KEY (`assistance_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `fk_audit_user` (`user_id`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`barangay_id`),
  ADD UNIQUE KEY `barangay_name` (`barangay_name`),
  ADD UNIQUE KEY `uq_barangays_lookup_key` (`barangay_lookup_key`),
  ADD KEY `idx_barangays_geo` (`gps_latitude`,`gps_longitude`),
  ADD KEY `idx_barangays_name` (`barangay_name`);

--
-- Indexes for table `beneficiary_member_records`
--
ALTER TABLE `beneficiary_member_records`
  ADD PRIMARY KEY (`beneficiary_record_id`),
  ADD UNIQUE KEY `uniq_household_member` (`household_id`,`member_id`);

--
-- Indexes for table `beneficiary_profiles`
--
ALTER TABLE `beneficiary_profiles`
  ADD PRIMARY KEY (`beneficiary_id`),
  ADD UNIQUE KEY `uq_beneficiary_member` (`household_id`,`member_id`),
  ADD UNIQUE KEY `uniq_beneficiary_household_member` (`household_id`,`member_id`);

--
-- Indexes for table `beneficiary_sector_types`
--
ALTER TABLE `beneficiary_sector_types`
  ADD PRIMARY KEY (`sector_type_id`),
  ADD UNIQUE KEY `uq_beneficiary_sector_code` (`sector_code`);

--
-- Indexes for table `cbms_asset_records`
--
ALTER TABLE `cbms_asset_records`
  ADD PRIMARY KEY (`asset_id`),
  ADD KEY `idx_cbms_asset_household` (`household_id`);

--
-- Indexes for table `cbms_household_profiles`
--
ALTER TABLE `cbms_household_profiles`
  ADD PRIMARY KEY (`cbms_household_profile_id`),
  ADD UNIQUE KEY `uq_cbms_household_profile_household` (`household_id`),
  ADD UNIQUE KEY `household_id` (`household_id`),
  ADD UNIQUE KEY `uniq_cbms_household` (`household_id`);

--
-- Indexes for table `cbms_housing_profiles`
--
ALTER TABLE `cbms_housing_profiles`
  ADD PRIMARY KEY (`housing_id`),
  ADD UNIQUE KEY `uq_cbms_housing_household` (`household_id`);

--
-- Indexes for table `cbms_livelihood_profiles`
--
ALTER TABLE `cbms_livelihood_profiles`
  ADD PRIMARY KEY (`livelihood_id`),
  ADD UNIQUE KEY `uq_cbms_livelihood_household` (`household_id`);

--
-- Indexes for table `cbms_pets`
--
ALTER TABLE `cbms_pets`
  ADD PRIMARY KEY (`pet_id`),
  ADD UNIQUE KEY `uniq_cbms_pet` (`household_id`,`pet_type`);

--
-- Indexes for table `cbms_sanitation_profiles`
--
ALTER TABLE `cbms_sanitation_profiles`
  ADD PRIMARY KEY (`sanitation_id`),
  ADD UNIQUE KEY `uq_cbms_sanitation_household` (`household_id`);

--
-- Indexes for table `cbms_vehicles`
--
ALTER TABLE `cbms_vehicles`
  ADD PRIMARY KEY (`cbms_vehicle_id`),
  ADD UNIQUE KEY `uniq_cbms_vehicle` (`household_id`,`vehicle_type`);

--
-- Indexes for table `crops`
--
ALTER TABLE `crops`
  ADD PRIMARY KEY (`crop_id`),
  ADD UNIQUE KEY `crop_code` (`crop_code`),
  ADD UNIQUE KEY `qr_reference` (`qr_reference`),
  ADD KEY `fk_crops_created_by` (`created_by`),
  ADD KEY `fk_crops_updated_by` (`updated_by`),
  ADD KEY `idx_crops_household_status` (`household_id`,`crop_status`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_departments_parent` (`parent_department_id`);

--
-- Indexes for table `document_categories`
--
ALTER TABLE `document_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD UNIQUE KEY `event_code` (`event_code`),
  ADD KEY `fk_events_barangay` (`barangay_id`),
  ADD KEY `fk_events_created_by` (`created_by`),
  ADD KEY `fk_events_updated_by` (`updated_by`);

--
-- Indexes for table `event_attendance`
--
ALTER TABLE `event_attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `uq_event_household` (`event_id`,`household_id`),
  ADD KEY `fk_attendance_user` (`recorded_by`),
  ADD KEY `idx_event_attendance_household_created` (`household_id`,`created_at`);

--
-- Indexes for table `event_program_targets`
--
ALTER TABLE `event_program_targets`
  ADD PRIMARY KEY (`event_program_target_id`),
  ADD UNIQUE KEY `uniq_event_program_target` (`event_id`,`program_id`),
  ADD KEY `idx_ept_event` (`event_id`),
  ADD KEY `idx_ept_program` (`program_id`);

--
-- Indexes for table `family_members`
--
ALTER TABLE `family_members`
  ADD PRIMARY KEY (`member_id`),
  ADD KEY `idx_family_members_household_head` (`household_id`,`is_household_head`),
  ADD KEY `idx_family_members_name` (`full_name`),
  ADD KEY `idx_family_members_household_name_birth` (`household_id`,`full_name`,`birthdate`),
  ADD KEY `idx_family_members_lookup` (`household_id`,`full_name`,`birthdate`),
  ADD KEY `idx_family_members_household_active` (`household_id`,`is_active`),
  ADD KEY `idx_family_members_household_name` (`household_id`,`full_name`(120)),
  ADD KEY `idx_family_members_household_birthdate` (`household_id`,`birthdate`),
  ADD KEY `idx_family_members_occupation` (`occupation`),
  ADD KEY `idx_family_members_age` (`age`),
  ADD KEY `idx_family_members_family_unit_id` (`family_unit_id`);

--
-- Indexes for table `family_portal_updates`
--
ALTER TABLE `family_portal_updates`
  ADD PRIMARY KEY (`update_id`),
  ADD KEY `idx_family_updates_household` (`household_id`),
  ADD KEY `idx_family_updates_status` (`reviewed_status`);

--
-- Indexes for table `family_submissions`
--
ALTER TABLE `family_submissions`
  ADD PRIMARY KEY (`family_submission_id`),
  ADD KEY `idx_family_submissions_household` (`household_id`),
  ADD KEY `idx_family_submissions_family_access` (`family_access_id`),
  ADD KEY `idx_family_submissions_type` (`submission_type`),
  ADD KEY `idx_family_submissions_status` (`status`),
  ADD KEY `idx_family_submissions_crop` (`crop_id`),
  ADD KEY `idx_family_submissions_event` (`event_id`),
  ADD KEY `idx_family_submissions_reviewed_by` (`reviewed_by`);

--
-- Indexes for table `households`
--
ALTER TABLE `households`
  ADD PRIMARY KEY (`household_id`),
  ADD UNIQUE KEY `household_code` (`household_code`),
  ADD KEY `fk_households_created_by` (`created_by`),
  ADD KEY `fk_households_updated_by` (`updated_by`),
  ADD KEY `idx_households_geo` (`gps_latitude`,`gps_longitude`),
  ADD KEY `idx_households_record_status` (`record_status`),
  ADD KEY `idx_households_archived_at` (`archived_at`),
  ADD KEY `idx_households_deleted_at` (`deleted_at`),
  ADD KEY `idx_households_reference_no` (`reference_no`),
  ADD KEY `idx_households_barangay_head` (`barangay_id`,`household_head_name`),
  ADD KEY `idx_households_barangay_status` (`barangay_id`,`record_status`),
  ADD KEY `idx_households_registered_hh_no` (`registered_hh_no`),
  ADD KEY `idx_households_household_code` (`household_code`),
  ADD KEY `idx_households_registered_barangay` (`registered_hh_no`,`barangay_id`),
  ADD KEY `idx_households_barangay_record` (`barangay_id`,`record_status`,`household_id`),
  ADD KEY `idx_households_head_name` (`household_head_name`),
  ADD KEY `idx_households_source_hh_no` (`source_hh_no`),
  ADD KEY `idx_households_cluster_key` (`household_cluster_key`),
  ADD KEY `idx_households_source_sheet_hh` (`source_sheet_name`,`source_hh_no`),
  ADD KEY `idx_households_source_sheet` (`source_sheet_name`),
  ADD KEY `idx_households_official_hh_no` (`official_hh_no`),
  ADD KEY `idx_households_hh_base_no` (`hh_base_no`),
  ADD KEY `idx_households_group_key` (`household_group_key`),
  ADD KEY `idx_households_source_family_key` (`source_family_key`);

--
-- Indexes for table `household_beneficiary_flags`
--
ALTER TABLE `household_beneficiary_flags`
  ADD PRIMARY KEY (`beneficiary_flag_id`),
  ADD UNIQUE KEY `uq_household_beneficiary_flags_household` (`household_id`);

--
-- Indexes for table `household_documents`
--
ALTER TABLE `household_documents`
  ADD PRIMARY KEY (`document_id`);

--
-- Indexes for table `household_points_log`
--
ALTER TABLE `household_points_log`
  ADD PRIMARY KEY (`point_log_id`),
  ADD KEY `idx_points_household` (`household_id`),
  ADD KEY `idx_points_source` (`source_type`,`source_id`);

--
-- Indexes for table `household_points_summary`
--
ALTER TABLE `household_points_summary`
  ADD PRIMARY KEY (`household_id`);

--
-- Indexes for table `household_qualification`
--
ALTER TABLE `household_qualification`
  ADD PRIMARY KEY (`qualification_id`),
  ADD UNIQUE KEY `household_id` (`household_id`),
  ADD KEY `idx_household_qualification_status` (`qualification_status`,`household_id`);

--
-- Indexes for table `household_rule_checklists`
--
ALTER TABLE `household_rule_checklists`
  ADD PRIMARY KEY (`household_rule_checklist_id`),
  ADD UNIQUE KEY `uniq_household_rule_item` (`household_id`,`checklist_type_id`),
  ADD KEY `idx_hrc_household` (`household_id`),
  ADD KEY `idx_hrc_checked` (`is_checked`);

--
-- Indexes for table `household_rule_checklist_types`
--
ALTER TABLE `household_rule_checklist_types`
  ADD PRIMARY KEY (`checklist_type_id`),
  ADD UNIQUE KEY `uniq_rule_item_code` (`item_code`);

--
-- Indexes for table `household_special_programs`
--
ALTER TABLE `household_special_programs`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `idx_hsp_household` (`household_id`),
  ADD KEY `idx_hsp_status` (`application_status`),
  ADD KEY `idx_hsp_program` (`program_id`);

--
-- Indexes for table `household_violations`
--
ALTER TABLE `household_violations`
  ADD PRIMARY KEY (`violation_id`),
  ADD KEY `idx_hv_household` (`household_id`),
  ADD KEY `idx_hv_status` (`violation_status`);

--
-- Indexes for table `household_violation_types`
--
ALTER TABLE `household_violation_types`
  ADD PRIMARY KEY (`violation_type_id`),
  ADD UNIQUE KEY `uniq_violation_name` (`violation_name`);

--
-- Indexes for table `import_batches`
--
ALTER TABLE `import_batches`
  ADD PRIMARY KEY (`import_batch_id`),
  ADD KEY `idx_import_batches_type_created_at` (`import_type`,`created_at`),
  ADD KEY `idx_import_batches_created_by` (`created_by`),
  ADD KEY `idx_import_batches_status` (`import_status`),
  ADD KEY `idx_import_batches_started_at` (`started_at`);

--
-- Indexes for table `import_logs`
--
ALTER TABLE `import_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `interviews`
--
ALTER TABLE `interviews`
  ADD PRIMARY KEY (`interview_id`),
  ADD KEY `fk_interviews_user` (`interviewed_by`),
  ADD KEY `idx_interviews_household_status` (`household_id`,`status`);

--
-- Indexes for table `module_features`
--
ALTER TABLE `module_features`
  ADD PRIMARY KEY (`feature_id`),
  ADD UNIQUE KEY `uq_module_feature` (`module_id`,`feature_code`);

--
-- Indexes for table `monitoring_visits`
--
ALTER TABLE `monitoring_visits`
  ADD PRIMARY KEY (`monitoring_id`),
  ADD KEY `fk_monitor_crop` (`crop_id`),
  ADD KEY `fk_monitor_user` (`monitored_by`),
  ADD KEY `idx_monitoring_household_date` (`household_id`,`monitoring_date`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notifications_household` (`household_id`),
  ADD KEY `fk_notifications_crop` (`crop_id`);

--
-- Indexes for table `official_crops`
--
ALTER TABLE `official_crops`
  ADD PRIMARY KEY (`crop_id`),
  ADD UNIQUE KEY `crop_name` (`crop_name`);

--
-- Indexes for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`reset_request_id`),
  ADD KEY `idx_reset_user` (`user_id`),
  ADD KEY `idx_reset_status` (`request_status`);

--
-- Indexes for table `profile_update_requests`
--
ALTER TABLE `profile_update_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_profile_request_user` (`user_id`),
  ADD KEY `idx_profile_request_status` (`request_status`);

--
-- Indexes for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD PRIMARY KEY (`qr_id`),
  ADD UNIQUE KEY `qr_reference` (`qr_reference`),
  ADD KEY `fk_qr_crop` (`crop_id`),
  ADD KEY `fk_qr_generated_by` (`generated_by`),
  ADD KEY `idx_qr_codes_household_type_active` (`household_id`,`qr_type`,`is_active`);

--
-- Indexes for table `qr_scan_logs`
--
ALTER TABLE `qr_scan_logs`
  ADD PRIMARY KEY (`scan_log_id`),
  ADD KEY `fk_scanlog_qr` (`qr_id`),
  ADD KEY `fk_scanlog_user` (`scanned_by`);

--
-- Indexes for table `qualification_history`
--
ALTER TABLE `qualification_history`
  ADD PRIMARY KEY (`qualification_history_id`),
  ADD KEY `fk_qualification_history_household` (`household_id`);

--
-- Indexes for table `qualification_rules`
--
ALTER TABLE `qualification_rules`
  ADD PRIMARY KEY (`rule_id`),
  ADD UNIQUE KEY `uniq_qualification_rule_key` (`rule_key`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_feature_permissions`
--
ALTER TABLE `role_feature_permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `uq_role_feature` (`role_id`,`feature_id`),
  ADD KEY `fk_role_feature_permissions_feature` (`feature_id`);

--
-- Indexes for table `signup_requests`
--
ALTER TABLE `signup_requests`
  ADD PRIMARY KEY (`signup_request_id`),
  ADD KEY `idx_signup_username` (`username`),
  ADD KEY `idx_signup_status` (`request_status`);

--
-- Indexes for table `special_programs`
--
ALTER TABLE `special_programs`
  ADD PRIMARY KEY (`program_id`),
  ADD UNIQUE KEY `uniq_special_program_name` (`program_name`);

--
-- Indexes for table `special_program_items`
--
ALTER TABLE `special_program_items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `uniq_program_item` (`program_id`,`item_name`),
  ADD KEY `idx_program_items_program` (`program_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_users_role` (`role_id`);

--
-- Indexes for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_workflow_steps_template` (`workflow_template_id`);

--
-- Indexes for table `workflow_templates`
--
ALTER TABLE `workflow_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_workflow_category` (`category_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_activity_log`
--
ALTER TABLE `account_activity_log`
  MODIFY `activity_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assistance_records`
--
ALTER TABLE `assistance_records`
  MODIFY `assistance_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `barangay_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `beneficiary_member_records`
--
ALTER TABLE `beneficiary_member_records`
  MODIFY `beneficiary_record_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `beneficiary_profiles`
--
ALTER TABLE `beneficiary_profiles`
  MODIFY `beneficiary_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `beneficiary_sector_types`
--
ALTER TABLE `beneficiary_sector_types`
  MODIFY `sector_type_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `cbms_asset_records`
--
ALTER TABLE `cbms_asset_records`
  MODIFY `asset_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cbms_household_profiles`
--
ALTER TABLE `cbms_household_profiles`
  MODIFY `cbms_household_profile_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cbms_housing_profiles`
--
ALTER TABLE `cbms_housing_profiles`
  MODIFY `housing_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cbms_livelihood_profiles`
--
ALTER TABLE `cbms_livelihood_profiles`
  MODIFY `livelihood_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cbms_pets`
--
ALTER TABLE `cbms_pets`
  MODIFY `pet_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cbms_sanitation_profiles`
--
ALTER TABLE `cbms_sanitation_profiles`
  MODIFY `sanitation_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cbms_vehicles`
--
ALTER TABLE `cbms_vehicles`
  MODIFY `cbms_vehicle_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crops`
--
ALTER TABLE `crops`
  MODIFY `crop_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `document_categories`
--
ALTER TABLE `document_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `event_attendance`
--
ALTER TABLE `event_attendance`
  MODIFY `attendance_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_program_targets`
--
ALTER TABLE `event_program_targets`
  MODIFY `event_program_target_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `family_members`
--
ALTER TABLE `family_members`
  MODIFY `member_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `family_portal_updates`
--
ALTER TABLE `family_portal_updates`
  MODIFY `update_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `family_submissions`
--
ALTER TABLE `family_submissions`
  MODIFY `family_submission_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `households`
--
ALTER TABLE `households`
  MODIFY `household_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `household_beneficiary_flags`
--
ALTER TABLE `household_beneficiary_flags`
  MODIFY `beneficiary_flag_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `household_documents`
--
ALTER TABLE `household_documents`
  MODIFY `document_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `household_points_log`
--
ALTER TABLE `household_points_log`
  MODIFY `point_log_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `household_qualification`
--
ALTER TABLE `household_qualification`
  MODIFY `qualification_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `household_rule_checklists`
--
ALTER TABLE `household_rule_checklists`
  MODIFY `household_rule_checklist_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `household_rule_checklist_types`
--
ALTER TABLE `household_rule_checklist_types`
  MODIFY `checklist_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1495;

--
-- AUTO_INCREMENT for table `household_special_programs`
--
ALTER TABLE `household_special_programs`
  MODIFY `application_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `household_violations`
--
ALTER TABLE `household_violations`
  MODIFY `violation_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `household_violation_types`
--
ALTER TABLE `household_violation_types`
  MODIFY `violation_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1149;

--
-- AUTO_INCREMENT for table `import_batches`
--
ALTER TABLE `import_batches`
  MODIFY `import_batch_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `import_logs`
--
ALTER TABLE `import_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interviews`
--
ALTER TABLE `interviews`
  MODIFY `interview_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `module_features`
--
ALTER TABLE `module_features`
  MODIFY `feature_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `monitoring_visits`
--
ALTER TABLE `monitoring_visits`
  MODIFY `monitoring_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `official_crops`
--
ALTER TABLE `official_crops`
  MODIFY `crop_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `reset_request_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `profile_update_requests`
--
ALTER TABLE `profile_update_requests`
  MODIFY `request_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `qr_codes`
--
ALTER TABLE `qr_codes`
  MODIFY `qr_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qr_scan_logs`
--
ALTER TABLE `qr_scan_logs`
  MODIFY `scan_log_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qualification_history`
--
ALTER TABLE `qualification_history`
  MODIFY `qualification_history_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qualification_rules`
--
ALTER TABLE `qualification_rules`
  MODIFY `rule_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=571;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `role_feature_permissions`
--
ALTER TABLE `role_feature_permissions`
  MODIFY `permission_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `signup_requests`
--
ALTER TABLE `signup_requests`
  MODIFY `signup_request_id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `special_programs`
--
ALTER TABLE `special_programs`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=657;

--
-- AUTO_INCREMENT for table `special_program_items`
--
ALTER TABLE `special_program_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4431;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `workflow_steps`
--
ALTER TABLE `workflow_steps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `workflow_templates`
--
ALTER TABLE `workflow_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `cbms_household_profiles`
--
ALTER TABLE `cbms_household_profiles`
  ADD CONSTRAINT `fk_cbms_household_profiles_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE CASCADE;

--
-- Constraints for table `crops`
--
ALTER TABLE `crops`
  ADD CONSTRAINT `fk_crops_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_crops_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_crops_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_events_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`barangay_id`),
  ADD CONSTRAINT `fk_events_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_events_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `event_attendance`
--
ALTER TABLE `event_attendance`
  ADD CONSTRAINT `fk_attendance_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attendance_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attendance_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `family_members`
--
ALTER TABLE `family_members`
  ADD CONSTRAINT `fk_family_members_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE CASCADE;

--
-- Constraints for table `family_submissions`
--
ALTER TABLE `family_submissions`
  ADD CONSTRAINT `fk_family_submissions_crop` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`crop_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_family_submissions_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_family_submissions_family_access` FOREIGN KEY (`family_access_id`) REFERENCES `family_portal_access` (`family_access_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_family_submissions_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_family_submissions_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `households`
--
ALTER TABLE `households`
  ADD CONSTRAINT `fk_households_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`barangay_id`),
  ADD CONSTRAINT `fk_households_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_households_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `household_beneficiary_flags`
--
ALTER TABLE `household_beneficiary_flags`
  ADD CONSTRAINT `fk_household_beneficiary_flags_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE CASCADE;

--
-- Constraints for table `household_qualification`
--
ALTER TABLE `household_qualification`
  ADD CONSTRAINT `fk_qualification_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE CASCADE;

--
-- Constraints for table `import_batches`
--
ALTER TABLE `import_batches`
  ADD CONSTRAINT `fk_import_batches_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `interviews`
--
ALTER TABLE `interviews`
  ADD CONSTRAINT `fk_interviews_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_interviews_user` FOREIGN KEY (`interviewed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `module_features`
--
ALTER TABLE `module_features`
  ADD CONSTRAINT `fk_module_features_module` FOREIGN KEY (`module_id`) REFERENCES `system_modules` (`module_id`) ON DELETE CASCADE;

--
-- Constraints for table `monitoring_visits`
--
ALTER TABLE `monitoring_visits`
  ADD CONSTRAINT `fk_monitor_crop` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`crop_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_monitor_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_monitor_user` FOREIGN KEY (`monitored_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_crop` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`crop_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notifications_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE CASCADE;

--
-- Constraints for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD CONSTRAINT `fk_qr_crop` FOREIGN KEY (`crop_id`) REFERENCES `crops` (`crop_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_qr_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_qr_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE CASCADE;

--
-- Constraints for table `qr_scan_logs`
--
ALTER TABLE `qr_scan_logs`
  ADD CONSTRAINT `fk_scanlog_qr` FOREIGN KEY (`qr_id`) REFERENCES `qr_codes` (`qr_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scanlog_user` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `qualification_history`
--
ALTER TABLE `qualification_history`
  ADD CONSTRAINT `fk_qualification_history_household` FOREIGN KEY (`household_id`) REFERENCES `households` (`household_id`) ON DELETE CASCADE;

--
-- Constraints for table `role_feature_permissions`
--
ALTER TABLE `role_feature_permissions`
  ADD CONSTRAINT `fk_role_feature_permissions_feature` FOREIGN KEY (`feature_id`) REFERENCES `module_features` (`feature_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_role_feature_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_family_consolidated`  AS SELECT `h`.`household_id` AS `household_id`, `h`.`household_code` AS `household_code`, `h`.`reference_no` AS `reference_no`, `h`.`household_head_name` AS `household_head_name`, `b`.`barangay_name` AS `barangay_name`, `h`.`full_address` AS `full_address`, `h`.`contact_number` AS `contact_number`, `h`.`area_sqm` AS `area_sqm`, `h`.`area_hectares` AS `area_hectares`, `h`.`household_size` AS `household_size`, `h`.`program_participation_count` AS `program_participation_count`, `h`.`is_fruit_planter` AS `is_fruit_planter`, `h`.`is_active_farmer` AS `is_active_farmer`, (select count(0) from `family_members` `fm` where `fm`.`household_id` = `h`.`household_id` and `fm`.`is_active` = 1) AS `member_count`, (select count(0) from `family_members` `fm` where `fm`.`household_id` = `h`.`household_id` and `fm`.`is_active` = 1 and `fm`.`member_photo_path` is not null and `fm`.`member_photo_path` <> '') AS `member_photo_count`, (select group_concat(`fm`.`full_name` order by `fm`.`is_household_head` DESC,`fm`.`full_name` ASC separator ', ') from `family_members` `fm` where `fm`.`household_id` = `h`.`household_id` and `fm`.`is_active` = 1) AS `family_member_names`, (select count(0) from `crops` `c` where `c`.`household_id` = `h`.`household_id` and `c`.`crop_status` = 'Active') AS `active_crop_count`, (select coalesce(sum(`c`.`tree_count`),0) from `crops` `c` where `c`.`household_id` = `h`.`household_id` and `c`.`crop_status` = 'Active') AS `total_tree_count`, (select max(`i`.`interview_date`) from `interviews` `i` where `i`.`household_id` = `h`.`household_id`) AS `latest_interview_date`, (select max(`m`.`monitoring_date`) from `monitoring_visits` `m` where `m`.`household_id` = `h`.`household_id`) AS `latest_monitoring_date`, (select `m`.`crop_condition` from `monitoring_visits` `m` where `m`.`household_id` = `h`.`household_id` order by `m`.`monitoring_date` desc,`m`.`monitoring_id` desc limit 1) AS `latest_crop_condition`, (select `m`.`fruiting_status` from `monitoring_visits` `m` where `m`.`household_id` = `h`.`household_id` order by `m`.`monitoring_date` desc,`m`.`monitoring_id` desc limit 1) AS `latest_fruiting_status`, (select coalesce(sum(`m`.`harvest_kg`),0) from `monitoring_visits` `m` where `m`.`household_id` = `h`.`household_id`) AS `total_harvest_kg`, (select count(0) from `event_attendance` `ea` where `ea`.`household_id` = `h`.`household_id` and `ea`.`attendance_status` in ('Present','Late')) AS `total_events_attended`, `q`.`score` AS `score`, `q`.`qualification_status` AS `qualification_status`, `q`.`explanation` AS `explanation`, `q`.`last_evaluated_at` AS `last_evaluated_at` FROM ((`households` `h` left join `barangays` `b` on(`b`.`barangay_id` = `h`.`barangay_id`)) left join `household_qualification` `q` on(`q`.`household_id` = `h`.`household_id`)) ;



CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_mayor_dashboard_summary`  AS SELECT (select count(0) from `households`) AS `total_households`, (select count(0) from `family_members` where `family_members`.`is_active` = 1) AS `total_family_members`, (select count(0) from `family_members` where `family_members`.`is_active` = 1 and `family_members`.`member_photo_path` is not null and `family_members`.`member_photo_path` <> '') AS `total_member_photos`, (select count(0) from `interviews` where `interviews`.`status` = 'Completed') AS `total_completed_interviews`, (select count(0) from `monitoring_visits`) AS `total_monitoring_visits`, (select count(0) from `crops` where `crops`.`crop_status` = 'Active') AS `total_active_crops`, (select coalesce(sum(`crops`.`tree_count`),0) from `crops` where `crops`.`crop_status` = 'Active') AS `total_tree_count`, (select coalesce(sum(`monitoring_visits`.`harvest_kg`),0) from `monitoring_visits`) AS `total_harvest_kg`, (select count(0) from `household_qualification` where `household_qualification`.`qualification_status` = 'Highly Qualified') AS `highly_qualified_count`, (select count(0) from `household_qualification` where `household_qualification`.`qualification_status` = 'Qualified') AS `qualified_count`, (select count(0) from `household_qualification` where `household_qualification`.`qualification_status` = 'Needs Support') AS `needs_support_count`, (select count(0) from `events`) AS `total_events`, (select count(0) from `event_attendance` where `event_attendance`.`attendance_status` in ('Present','Late')) AS `total_attendance_records` ;



COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
