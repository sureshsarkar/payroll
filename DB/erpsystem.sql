-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 31, 2026 at 11:28 AM
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
-- Database: `erpsystem`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor_type` varchar(32) DEFAULT NULL,
  `actor_name` varchar(255) DEFAULT NULL,
  `actor_role` varchar(64) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `module` varchar(64) DEFAULT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `actor_id`, `actor_type`, `actor_name`, `actor_role`, `action`, `module`, `subject_type`, `subject_id`, `description`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-19 13:29:41'),
(2, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-24 11:34:28'),
(3, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.34493.1 Chrome/148.0.7778.280 Electron/42.9.2 Safari/537.36', '2026-08-24 13:14:21'),
(4, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-25 05:09:17'),
(5, 1, 'admin', 'demo lms', 'admin', 'login', 'auth', 'App\\Models\\Admin', 1, 'Signed in (admin)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-25 07:03:25'),
(6, 1, 'admin', 'demo lms', 'admin', 'login', 'auth', 'App\\Models\\Admin', 1, 'Signed in (admin)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-25 11:08:32'),
(7, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.34493.1 Chrome/148.0.7778.280 Electron/42.9.2 Safari/537.36', '2026-08-25 13:17:50'),
(8, 1286, 'user', 'Suru Kumar', 'student', 'login', 'auth', 'App\\Models\\User', 1286, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:154.0) Gecko/20100101 Firefox/154.0', '2026-08-25 13:30:19'),
(9, 1, 'admin', 'demo lms', 'admin', 'login', 'auth', 'App\\Models\\Admin', 1, 'Signed in (admin)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-25 13:32:45'),
(10, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-31 08:18:55'),
(11, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(12, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(13, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(14, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(15, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(16, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(17, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(18, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(19, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(20, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(21, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(22, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(23, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(24, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(25, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(26, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(27, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(28, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(29, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(30, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(31, 1182, 'user', 'SantoshDHS', 'student', 'login', 'auth', 'App\\Models\\User', 1182, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:15'),
(32, 1182, 'user', 'SantoshDHS', 'student', 'logout', 'auth', 'App\\Models\\User', 1182, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:19'),
(33, 1182, 'user', 'SantoshDHS', 'student', 'login', 'auth', 'App\\Models\\User', 1182, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:19'),
(34, 1182, 'user', 'SantoshDHS', 'student', 'logout', 'auth', 'App\\Models\\User', 1182, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:19'),
(35, 1182, 'user', 'SantoshDHS', 'student', 'login', 'auth', 'App\\Models\\User', 1182, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:19'),
(36, 1182, 'user', 'SantoshDHS', 'student', 'logout', 'auth', 'App\\Models\\User', 1182, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:19'),
(37, 1182, 'user', 'SantoshDHS', 'student', 'login', 'auth', 'App\\Models\\User', 1182, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:19'),
(38, 1182, 'user', 'SantoshDHS', 'student', 'logout', 'auth', 'App\\Models\\User', 1182, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:19'),
(39, 1182, 'user', 'SantoshDHS', 'student', 'login', 'auth', 'App\\Models\\User', 1182, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:19'),
(40, 1182, 'user', 'SantoshDHS', 'student', 'logout', 'auth', 'App\\Models\\User', 1182, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:20'),
(41, 1, 'admin', 'demo lms', 'admin', 'login', 'auth', 'App\\Models\\Admin', 1, 'Signed in (admin)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:20'),
(42, 1, 'admin', 'demo lms', 'admin', 'logout', 'auth', 'App\\Models\\Admin', 1, 'Signed out (admin)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:20'),
(43, 1, 'admin', 'demo lms', 'admin', 'login', 'auth', 'App\\Models\\Admin', 1, 'Signed in (admin)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:20'),
(44, 1, 'admin', 'demo lms', 'admin', 'logout', 'auth', 'App\\Models\\Admin', 1, 'Signed out (admin)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:21'),
(45, 1286, 'user', 'Suru Kumar', 'student', 'login', 'auth', 'App\\Models\\User', 1286, 'Signed in (web)', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-31 08:25:26'),
(46, 1, 'admin', 'demo lms', 'admin', 'login', 'auth', 'App\\Models\\Admin', 1, 'Signed in (admin)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:25:40'),
(47, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(48, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(49, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(50, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(51, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(52, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(53, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(54, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(55, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(56, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(57, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(58, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(59, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(60, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(61, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(62, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(63, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(64, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(65, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(66, 1079, 'user', 'Coach Panel', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1079, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(67, 1182, 'user', 'SantoshDHS', 'student', 'login', 'auth', 'App\\Models\\User', 1182, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(68, 1182, 'user', 'SantoshDHS', 'student', 'logout', 'auth', 'App\\Models\\User', 1182, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(69, 1182, 'user', 'SantoshDHS', 'student', 'login', 'auth', 'App\\Models\\User', 1182, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(70, 1182, 'user', 'SantoshDHS', 'student', 'logout', 'auth', 'App\\Models\\User', 1182, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(71, 1182, 'user', 'SantoshDHS', 'student', 'login', 'auth', 'App\\Models\\User', 1182, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(72, 1182, 'user', 'SantoshDHS', 'student', 'logout', 'auth', 'App\\Models\\User', 1182, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(73, 1182, 'user', 'SantoshDHS', 'student', 'login', 'auth', 'App\\Models\\User', 1182, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(74, 1182, 'user', 'SantoshDHS', 'student', 'logout', 'auth', 'App\\Models\\User', 1182, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(75, 1182, 'user', 'SantoshDHS', 'student', 'login', 'auth', 'App\\Models\\User', 1182, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(76, 1182, 'user', 'SantoshDHS', 'student', 'logout', 'auth', 'App\\Models\\User', 1182, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(77, 1, 'admin', 'demo lms', 'admin', 'login', 'auth', 'App\\Models\\Admin', 1, 'Signed in (admin)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(78, 1, 'admin', 'demo lms', 'admin', 'logout', 'auth', 'App\\Models\\Admin', 1, 'Signed out (admin)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(79, 1, 'admin', 'demo lms', 'admin', 'login', 'auth', 'App\\Models\\Admin', 1, 'Signed in (admin)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:28'),
(80, 1, 'admin', 'demo lms', 'admin', 'logout', 'auth', 'App\\Models\\Admin', 1, 'Signed out (admin)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:29'),
(81, 1079, 'user', 'Coach Panel', 'instructor', 'login', 'auth', 'App\\Models\\User', 1079, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:26:43'),
(82, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:05'),
(83, 1278, 'user', 'Cockroach Coach', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1278, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:08'),
(84, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:08'),
(85, 1278, 'user', 'Cockroach Coach', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1278, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:08'),
(86, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(87, 1278, 'user', 'Cockroach Coach', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1278, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(88, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(89, 1278, 'user', 'Cockroach Coach', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1278, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(90, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(91, 1278, 'user', 'Cockroach Coach', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1278, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(92, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(93, 1278, 'user', 'Cockroach Coach', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1278, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(94, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(95, 1278, 'user', 'Cockroach Coach', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1278, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(96, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(97, 1278, 'user', 'Cockroach Coach', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1278, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(98, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(99, 1278, 'user', 'Cockroach Coach', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1278, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(100, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(101, 1278, 'user', 'Cockroach Coach', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1278, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(102, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(103, 1278, 'user', 'Cockroach Coach', 'instructor', 'logout', 'auth', 'App\\Models\\User', 1278, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(104, 1283, 'user', 'Debra Haney', 'student', 'login', 'auth', 'App\\Models\\User', 1283, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(105, 1283, 'user', 'Debra Haney', 'student', 'logout', 'auth', 'App\\Models\\User', 1283, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(106, 1283, 'user', 'Debra Haney', 'student', 'login', 'auth', 'App\\Models\\User', 1283, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(107, 1283, 'user', 'Debra Haney', 'student', 'logout', 'auth', 'App\\Models\\User', 1283, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(108, 1283, 'user', 'Debra Haney', 'student', 'login', 'auth', 'App\\Models\\User', 1283, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(109, 1283, 'user', 'Debra Haney', 'student', 'logout', 'auth', 'App\\Models\\User', 1283, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(110, 1283, 'user', 'Debra Haney', 'student', 'login', 'auth', 'App\\Models\\User', 1283, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(111, 1283, 'user', 'Debra Haney', 'student', 'logout', 'auth', 'App\\Models\\User', 1283, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(112, 1283, 'user', 'Debra Haney', 'student', 'login', 'auth', 'App\\Models\\User', 1283, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(113, 1283, 'user', 'Debra Haney', 'student', 'logout', 'auth', 'App\\Models\\User', 1283, 'Signed out (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:09'),
(114, 1278, 'user', 'Cockroach Coach', 'instructor', 'login', 'auth', 'App\\Models\\User', 1278, 'Signed in (web)', NULL, NULL, '127.0.0.1', 'Symfony', '2026-08-31 08:27:45');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_enabled_at` timestamp NULL DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `forget_password_token` varchar(255) DEFAULT NULL,
  `forget_password_token_expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `image`, `password`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_enabled_at`, `two_factor_confirmed_at`, `bio`, `status`, `created_at`, `updated_at`, `forget_password_token`, `forget_password_token_expires_at`) VALUES
(1, 'demo lms', 'admin@gmail.com', 'uploads/website-images/admin.jpg', '$2y$10$Bu3EvLGe01n9bhlMyD6oO.K5byFvwHSCYA.dgTzjkkrdoBAQDJy3S', 'eyJpdiI6IlpCVzB1Y2VGTHlzZ1JKaDRjV2dRVEE9PSIsInZhbHVlIjoiTm5DU2oyQ3YzcDM4M1B0Q2lJZG9NaWl3Q2tIY3VSaEVGYk05eFhrb09Kc1FqbjFDU1JGaEVNMlNYdExlY2s1WCIsIm1hYyI6ImVhNWI0YjU0NzQ5NDZiYzZjYzlkZDA2Nzc1YmU2NTFjMTQzNzFlYjZlMTFiMWViY2FkMTFmZDRjMjY3MDAwMWMiLCJ0YWciOiIifQ==', 'eyJpdiI6IjdIK3pxUXloUm0zTG5LYkNYQnY1Wnc9PSIsInZhbHVlIjoiWmZpSW02L3NFV3RHUEkxUzNLNEJyR3hsS2dTcW1vZlh0b1RzQXdqVFBpK09VaS8vSVhZcXowOU5RQkw1akxibnBFVTE2NFlDMlYvMDVJTjl5SzJBZm9Ja2RxdEpXR3VqWjVsaUt6aUdueXBzeCs2NVdtZHNwcFJYblZuMGRCb25QclhCVDVJTEVxZTgxQitLcGF3MnJhSS9XM3VXaHIydm5YMkxSTHZka1ZZPSIsIm1hYyI6IjNlNTZlYjJhYTY4MDYyODYxMThiYmEyODA0OWM5YjAxZmYwZTQ2ZGVhOTI5ZDJlZThjMmMwM2U1Mjk5NDY3MDUiLCJ0YWciOiIifQ==', '2026-05-04 06:11:11', NULL, NULL, 'active', '2024-08-14 21:23:20', '2026-05-04 06:11:11', NULL, NULL),
(3, 'E2E Admin 1780389191577', 'e2e-admin@mbsguru.test', NULL, '$2y$12$cYeM7H043UVl2ulQbbCAkOsonKjVVd4qGzDvYmWe14hCOVaP7FOaS', NULL, NULL, NULL, NULL, NULL, 'active', '2026-05-29 06:42:51', '2026-06-02 08:33:12', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attendances`
--

CREATE TABLE `attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT 'employee (users.id)',
  `attendance_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Present',
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `worked_minutes` int(10) UNSIGNED DEFAULT NULL,
  `source` varchar(20) NOT NULL DEFAULT 'manual',
  `marked_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'HR/Admin user id who marked it',
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendances`
--

INSERT INTO `attendances` (`id`, `company_id`, `user_id`, `attendance_date`, `status`, `check_in`, `check_out`, `worked_minutes`, `source`, `marked_by`, `remarks`, `created_at`, `updated_at`) VALUES
(19, 1, 1283, '2026-07-30', 'Present', NULL, NULL, NULL, 'manual', 1278, NULL, '2026-07-30 12:15:28', '2026-07-30 12:15:28'),
(20, 1, 1284, '2026-07-30', 'Present', NULL, NULL, NULL, 'manual', 1278, NULL, '2026-07-30 12:15:35', '2026-07-30 12:15:35'),
(21, 1, 1283, '2026-07-29', 'Present', NULL, NULL, NULL, 'manual', 1278, NULL, '2026-07-30 12:16:15', '2026-07-30 12:16:15'),
(22, 1, 1284, '2026-07-29', 'Present', NULL, NULL, NULL, 'manual', 1278, NULL, '2026-07-30 12:16:15', '2026-07-30 12:16:15'),
(23, 1, 1283, '2026-07-01', 'Present', '09:15:00', '17:30:00', 495, 'manual', 1278, NULL, '2026-07-31 11:41:07', '2026-07-31 11:41:57'),
(24, 1, 1283, '2026-07-02', 'Present', NULL, NULL, NULL, 'manual', 1278, NULL, '2026-07-31 11:41:13', '2026-07-31 11:41:13'),
(25, 1, 1283, '2026-07-31', 'Present', '09:30:00', '18:30:00', 540, 'manual', 1278, NULL, '2026-07-31 11:42:10', '2026-07-31 12:37:01'),
(26, 1, 1283, '2026-07-03', 'Present', '09:30:00', '18:30:00', 540, 'manual', 1278, NULL, '2026-07-31 11:50:29', '2026-07-31 11:50:54'),
(27, 1, 1283, '2026-07-04', 'Present', '09:30:00', '18:30:00', 540, 'manual', 1278, NULL, '2026-07-31 11:51:14', '2026-07-31 11:51:14'),
(28, 1, 1284, '2026-07-31', 'Present', '09:30:00', '18:30:00', 540, 'manual', 1278, NULL, '2026-07-31 11:51:33', '2026-07-31 12:38:11'),
(29, 1, 1283, '2026-07-06', 'Present', '09:34:00', '18:39:00', 545, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(30, 1, 1283, '2026-07-07', 'Present', '09:37:00', '18:51:00', 554, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(31, 1, 1283, '2026-07-08', 'Present', '09:30:00', '18:38:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(32, 1, 1283, '2026-07-09', 'Present', '09:30:00', '18:42:00', 552, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(33, 1, 1283, '2026-07-10', 'Present', '09:33:00', '18:45:00', 552, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(34, 1, 1283, '2026-07-13', 'Present', '09:32:00', '18:50:00', 558, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(35, 1, 1283, '2026-07-14', 'Present', '09:31:00', '18:49:00', 558, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(36, 1, 1283, '2026-07-15', 'Present', '09:36:00', '18:56:00', 560, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(37, 1, 1283, '2026-07-16', 'Present', '09:37:00', '18:39:00', 542, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(38, 1, 1283, '2026-07-17', 'Present', '09:32:00', '18:49:00', 557, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(39, 1, 1283, '2026-07-20', 'Present', '09:32:00', '18:30:00', 538, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(40, 1, 1283, '2026-07-21', 'Present', '09:33:00', '18:50:00', 557, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(41, 1, 1283, '2026-07-22', 'Present', '09:36:00', '18:52:00', 556, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(42, 1, 1283, '2026-07-23', 'Present', '09:40:00', '18:34:00', 534, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(43, 1, 1283, '2026-07-24', 'Present', '09:34:00', '18:49:00', 555, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(44, 1, 1283, '2026-07-27', 'Present', '09:37:00', '18:37:00', 540, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(45, 1, 1283, '2026-07-28', 'Present', '09:36:00', '18:55:00', 559, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:36:32', '2026-07-31 12:36:32'),
(46, 1, 1284, '2026-07-01', 'Present', '09:40:00', '18:51:00', 551, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(47, 1, 1284, '2026-07-02', 'Present', '09:30:00', '18:30:00', 540, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:53'),
(48, 1, 1284, '2026-07-03', 'Present', '09:39:00', '18:49:00', 550, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(49, 1, 1284, '2026-07-06', 'Present', '09:37:00', '18:47:00', 550, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(50, 1, 1284, '2026-07-07', 'Present', '09:34:00', '18:56:00', 562, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(51, 1, 1284, '2026-07-08', 'Present', '09:34:00', '18:46:00', 552, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(52, 1, 1284, '2026-07-09', 'Present', '09:37:00', '18:30:00', 533, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(53, 1, 1284, '2026-07-10', 'Present', '09:31:00', '18:47:00', 556, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(54, 1, 1284, '2026-07-13', 'Present', '09:38:00', '18:30:00', 532, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(55, 1, 1284, '2026-07-14', 'Present', '09:37:00', '18:32:00', 535, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(56, 1, 1284, '2026-07-15', 'Present', '09:30:00', '18:55:00', 565, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(57, 1, 1284, '2026-07-16', 'Present', '09:33:00', '18:40:00', 547, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(58, 1, 1284, '2026-07-17', 'Present', '09:39:00', '18:35:00', 536, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(59, 1, 1284, '2026-07-20', 'Present', '09:33:00', '18:34:00', 541, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(60, 1, 1284, '2026-07-21', 'Present', '09:39:00', '18:42:00', 543, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(61, 1, 1284, '2026-07-22', 'Present', '09:36:00', '18:49:00', 553, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(62, 1, 1284, '2026-07-23', 'Present', '09:32:00', '19:00:00', 568, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(63, 1, 1284, '2026-07-24', 'Present', '09:36:00', '18:55:00', 559, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(64, 1, 1284, '2026-07-27', 'Present', '09:35:00', '18:48:00', 553, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(65, 1, 1284, '2026-07-28', 'Present', '09:30:00', '18:39:00', 549, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 12:37:24', '2026-07-31 12:37:24'),
(66, 1, 1285, '2026-07-01', 'HalfDay', '09:34:00', '12:55:00', 201, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:55', '2026-07-31 13:49:17'),
(67, 1, 1285, '2026-07-02', 'Present', '09:36:00', '18:30:00', 534, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:55', '2026-07-31 13:48:55'),
(68, 1, 1285, '2026-07-03', 'Leave', NULL, NULL, NULL, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:55', '2026-07-31 13:50:09'),
(69, 1, 1285, '2026-07-06', 'Present', '09:34:00', '18:45:00', 551, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:55', '2026-07-31 13:48:55'),
(70, 1, 1285, '2026-07-07', 'Present', '09:39:00', '18:37:00', 538, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:55', '2026-07-31 13:48:55'),
(71, 1, 1285, '2026-07-08', 'Present', '09:36:00', '18:40:00', 544, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(72, 1, 1285, '2026-07-09', 'Present', '09:30:00', '18:31:00', 541, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(73, 1, 1285, '2026-07-10', 'Present', '09:35:00', '18:49:00', 554, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(74, 1, 1285, '2026-07-13', 'Present', '09:34:00', '18:38:00', 544, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(75, 1, 1285, '2026-07-14', 'Present', '09:36:00', '18:44:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(76, 1, 1285, '2026-07-15', 'Present', '09:30:00', '18:48:00', 558, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(77, 1, 1285, '2026-07-16', 'Present', '09:38:00', '18:57:00', 559, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(78, 1, 1285, '2026-07-17', 'Present', '09:33:00', '19:00:00', 567, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(79, 1, 1285, '2026-07-20', 'Present', '09:40:00', '18:59:00', 559, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(80, 1, 1285, '2026-07-21', 'Present', '09:33:00', '18:57:00', 564, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(81, 1, 1285, '2026-07-22', 'Present', '09:40:00', '18:55:00', 555, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(82, 1, 1285, '2026-07-23', 'Present', '09:37:00', '18:33:00', 536, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(83, 1, 1285, '2026-07-24', 'Present', '09:34:00', '18:46:00', 552, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(84, 1, 1285, '2026-07-27', 'Present', '09:37:00', '18:53:00', 556, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(85, 1, 1285, '2026-07-28', 'Present', '09:36:00', '18:34:00', 538, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(86, 1, 1285, '2026-07-29', 'Present', '09:38:00', '18:45:00', 547, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(87, 1, 1285, '2026-07-30', 'Present', '09:37:00', '18:59:00', 562, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(88, 1, 1285, '2026-07-31', 'Present', '09:36:00', '18:51:00', 555, 'manual', 1278, 'Monthly attendance quick fill', '2026-07-31 13:48:56', '2026-07-31 13:48:56'),
(89, 1, 1285, '2026-07-04', 'Holiday', NULL, NULL, NULL, 'manual', 1278, NULL, '2026-07-31 13:51:19', '2026-07-31 13:51:19'),
(90, 1, 1286, '2026-08-03', 'Present', '09:30:00', '18:30:00', 540, 'manual', 1278, NULL, '2026-08-03 10:49:44', '2026-08-03 10:50:20'),
(91, 1, 1286, '2026-08-04', 'HalfDay', '09:39:00', '14:00:00', 261, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-25 06:40:04'),
(92, 1, 1286, '2026-08-05', 'Present', '09:40:00', '18:51:00', 551, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(93, 1, 1286, '2026-08-06', 'Present', '09:38:00', '18:54:00', 556, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(94, 1, 1286, '2026-08-07', 'Present', '09:32:00', '18:53:00', 561, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(95, 1, 1286, '2026-08-10', 'Present', '09:34:00', '18:32:00', 538, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(96, 1, 1286, '2026-08-11', 'Present', '09:35:00', '18:33:00', 538, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(97, 1, 1286, '2026-08-12', 'Present', '09:40:00', '18:41:00', 541, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(98, 1, 1286, '2026-08-13', 'Present', '09:38:00', '18:46:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(99, 1, 1286, '2026-08-14', 'Present', '09:34:00', '18:52:00', 558, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(100, 1, 1286, '2026-08-17', 'Present', '09:36:00', '18:57:00', 561, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(101, 1, 1286, '2026-08-18', 'Present', '09:36:00', '18:42:00', 546, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(102, 1, 1286, '2026-08-19', 'Present', '09:34:00', '18:46:00', 552, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(103, 1, 1286, '2026-08-20', 'Present', '09:32:00', '18:50:00', 558, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(104, 1, 1286, '2026-08-21', 'Present', '09:31:00', '18:39:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(105, 1, 1286, '2026-08-24', 'Present', '09:33:00', '18:53:00', 560, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(106, 1, 1286, '2026-08-25', 'Present', '09:31:00', '18:34:00', 543, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(107, 1, 1286, '2026-08-26', 'Present', '09:35:00', '19:00:00', 565, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(108, 1, 1286, '2026-08-27', 'Present', '09:34:00', '18:39:00', 545, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(109, 1, 1286, '2026-08-28', 'Present', '09:38:00', '18:41:00', 543, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-03 10:49:58'),
(110, 1, 1286, '2026-08-31', 'Present', '09:35:00', '13:59:53', 264, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-03 10:49:58', '2026-08-31 08:29:53'),
(111, 1, 1286, '2026-08-01', 'Absent', '09:30:00', '18:30:00', 540, 'manual', 1278, NULL, '2026-08-03 10:50:30', '2026-08-25 05:16:23'),
(112, 1, 1286, '2026-08-08', 'Absent', '09:30:00', '18:30:00', 540, 'manual', 1278, NULL, '2026-08-03 10:50:35', '2026-08-25 05:16:33'),
(113, 1, 1286, '2026-08-15', 'Present', '09:30:00', '18:30:00', 540, 'manual', 1278, NULL, '2026-08-03 10:50:46', '2026-08-03 10:50:46'),
(114, 1, 1286, '2026-08-22', 'Present', '09:40:00', '19:00:00', 560, 'manual', 1278, NULL, '2026-08-03 10:50:53', '2026-08-03 10:50:53'),
(115, 1, 1286, '2026-08-29', 'Present', '09:40:00', '19:00:00', 560, 'manual', 1278, NULL, '2026-08-03 10:50:58', '2026-08-03 10:50:58'),
(116, 13, 1287, '2026-08-18', 'Present', '09:30:00', '18:30:00', 540, 'manual', 1278, NULL, '2026-08-18 13:55:42', '2026-08-18 13:55:49'),
(117, 1, 1283, '2026-08-24', 'Present', '09:40:00', '19:00:00', 560, 'manual', 1278, NULL, '2026-08-24 11:49:36', '2026-08-24 11:49:55'),
(118, 1, 1285, '2026-08-24', 'Present', NULL, NULL, NULL, 'manual', 1278, NULL, '2026-08-24 11:49:36', '2026-08-24 11:49:36'),
(119, 1, 1284, '2026-08-24', 'Present', NULL, NULL, NULL, 'manual', 1278, NULL, '2026-08-24 11:49:36', '2026-08-24 11:49:36'),
(120, 1, 1283, '2026-08-03', 'Present', '09:39:00', '18:33:00', 534, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(121, 1, 1283, '2026-08-04', 'Present', '09:31:00', '18:36:00', 545, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(122, 1, 1283, '2026-08-05', 'Present', '09:30:00', '18:40:00', 550, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(123, 1, 1283, '2026-08-06', 'Present', '09:32:00', '18:57:00', 565, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(124, 1, 1283, '2026-08-07', 'Present', '09:31:00', '18:58:00', 567, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(125, 1, 1283, '2026-08-10', 'Present', '09:34:00', '18:51:00', 557, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(126, 1, 1283, '2026-08-11', 'Present', '09:35:00', '18:32:00', 537, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(127, 1, 1283, '2026-08-12', 'Present', '09:36:00', '18:58:00', 562, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(128, 1, 1283, '2026-08-13', 'Present', '09:33:00', '18:51:00', 558, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(129, 1, 1283, '2026-08-14', 'Present', '09:37:00', '18:54:00', 557, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(130, 1, 1283, '2026-08-17', 'Present', '09:40:00', '18:43:00', 543, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(131, 1, 1283, '2026-08-18', 'Present', '09:33:00', '18:42:00', 549, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(132, 1, 1283, '2026-08-19', 'Present', '09:30:00', '18:57:00', 567, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(133, 1, 1283, '2026-08-20', 'Present', '09:30:00', '18:36:00', 546, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(134, 1, 1283, '2026-08-21', 'Present', '09:31:00', '19:00:00', 569, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(135, 1, 1283, '2026-08-25', 'Present', '09:31:00', '18:37:00', 546, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(136, 1, 1283, '2026-08-26', 'Present', '09:31:00', '18:31:00', 540, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(137, 1, 1283, '2026-08-27', 'Present', '09:35:00', '18:36:00', 541, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(138, 1, 1283, '2026-08-28', 'Present', '09:30:00', '18:56:00', 566, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(139, 1, 1283, '2026-08-31', 'Present', '09:32:00', '18:32:00', 540, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 11:50:05', '2026-08-24 11:50:05'),
(140, 1, 1285, '2026-08-01', 'Present', '09:35:00', '18:53:00', 558, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(141, 1, 1285, '2026-08-03', 'Present', '09:40:00', '18:43:00', 543, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(142, 1, 1285, '2026-08-04', 'Present', '09:39:00', '18:47:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(143, 1, 1285, '2026-08-05', 'Present', '09:37:00', '18:31:00', 534, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(144, 1, 1285, '2026-08-06', 'Present', '09:38:00', '19:00:00', 562, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(145, 1, 1285, '2026-08-07', 'Present', '09:38:00', '18:59:00', 561, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(146, 1, 1285, '2026-08-08', 'Present', '09:35:00', '18:50:00', 555, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(147, 1, 1285, '2026-08-10', 'Present', '09:37:00', '18:46:00', 549, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(148, 1, 1285, '2026-08-11', 'Present', '09:35:00', '18:45:00', 550, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(149, 1, 1285, '2026-08-12', 'Present', '09:38:00', '18:47:00', 549, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(150, 1, 1285, '2026-08-13', 'Present', '09:38:00', '18:59:00', 561, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(151, 1, 1285, '2026-08-14', 'Present', '09:32:00', '18:48:00', 556, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(152, 1, 1285, '2026-08-15', 'Present', '09:40:00', '18:51:00', 551, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(153, 1, 1285, '2026-08-17', 'Present', '09:31:00', '18:38:00', 547, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(154, 1, 1285, '2026-08-18', 'Present', '09:35:00', '18:59:00', 564, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(155, 1, 1285, '2026-08-19', 'Present', '09:38:00', '18:34:00', 536, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(156, 1, 1285, '2026-08-20', 'Present', '09:35:00', '18:40:00', 545, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(157, 1, 1285, '2026-08-21', 'Present', '09:39:00', '18:52:00', 553, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(158, 1, 1285, '2026-08-22', 'Present', '09:30:00', '18:49:00', 559, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(159, 1, 1285, '2026-08-25', 'Present', '09:30:00', '18:30:00', 540, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(160, 1, 1285, '2026-08-26', 'Present', '09:35:00', '18:33:00', 538, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(161, 1, 1285, '2026-08-27', 'Present', '09:37:00', '18:34:00', 537, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(162, 1, 1285, '2026-08-28', 'Present', '09:30:00', '18:43:00', 553, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(163, 1, 1285, '2026-08-29', 'Present', '09:39:00', '18:40:00', 541, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(164, 1, 1285, '2026-08-31', 'Present', '09:34:00', '18:41:00', 547, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:21:19', '2026-08-24 13:21:19'),
(165, 13, 1287, '2026-08-01', 'Present', '09:38:00', '18:43:00', 545, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(166, 13, 1287, '2026-08-03', 'Present', '09:36:00', '18:32:00', 536, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(167, 13, 1287, '2026-08-04', 'Present', '09:33:00', '18:38:00', 545, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(168, 13, 1287, '2026-08-05', 'Present', '09:35:00', '18:35:00', 540, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(169, 13, 1287, '2026-08-06', 'Present', '09:34:00', '18:52:00', 558, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(170, 13, 1287, '2026-08-07', 'Present', '09:37:00', '18:42:00', 545, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(171, 13, 1287, '2026-08-08', 'Present', '09:34:00', '18:40:00', 546, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(172, 13, 1287, '2026-08-10', 'Present', '09:32:00', '18:46:00', 554, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(173, 13, 1287, '2026-08-11', 'Present', '09:34:00', '18:35:00', 541, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(174, 13, 1287, '2026-08-12', 'Present', '09:34:00', '18:37:00', 543, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(175, 13, 1287, '2026-08-13', 'Present', '09:40:00', '18:36:00', 536, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(176, 13, 1287, '2026-08-14', 'Present', '09:39:00', '18:56:00', 557, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(177, 13, 1287, '2026-08-15', 'Present', '09:40:00', '18:39:00', 539, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(178, 13, 1287, '2026-08-17', 'Present', '09:37:00', '18:40:00', 543, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(179, 13, 1287, '2026-08-19', 'Present', '09:36:00', '18:59:00', 563, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(180, 13, 1287, '2026-08-20', 'Present', '09:30:00', '18:48:00', 558, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(181, 13, 1287, '2026-08-21', 'Present', '09:32:00', '18:54:00', 562, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(182, 13, 1287, '2026-08-22', 'Present', '09:35:00', '18:45:00', 550, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(183, 13, 1287, '2026-08-24', 'Present', '09:37:00', '18:32:00', 535, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(184, 13, 1287, '2026-08-25', 'Present', '09:36:00', '19:00:00', 564, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(185, 13, 1287, '2026-08-26', 'Present', '09:36:00', '18:45:00', 549, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(186, 13, 1287, '2026-08-27', 'Present', '09:35:00', '18:36:00', 541, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(187, 13, 1287, '2026-08-28', 'Present', '09:37:00', '18:45:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(188, 13, 1287, '2026-08-29', 'Present', '09:36:00', '18:52:00', 556, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(189, 13, 1287, '2026-08-31', 'Present', '09:40:00', '19:00:00', 560, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-24 13:39:15', '2026-08-24 13:39:15'),
(190, 1, 1283, '2026-05-01', 'Absent', NULL, NULL, NULL, 'manual', 1278, NULL, '2026-08-25 07:20:40', '2026-08-25 07:24:48'),
(191, 1, 1285, '2026-05-01', 'Absent', NULL, NULL, NULL, 'manual', 1278, NULL, '2026-08-25 07:20:40', '2026-08-25 07:24:35'),
(192, 1, 1284, '2026-05-01', 'Present', '09:30:00', '18:30:00', 540, 'manual', 1278, NULL, '2026-08-25 07:20:40', '2026-08-25 07:33:54'),
(193, 1, 1286, '2026-05-01', 'Present', '09:01:00', '18:54:00', 593, 'manual', 1278, 'attendance quick fill', '2026-08-25 07:20:40', '2026-08-25 07:23:43'),
(194, 1, 1286, '2026-05-02', 'Absent', '09:35:00', '18:55:00', 560, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:26:03'),
(195, 1, 1286, '2026-05-04', 'Present', '09:36:00', '18:33:00', 537, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(196, 1, 1286, '2026-05-05', 'Present', '09:38:00', '18:33:00', 535, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(197, 1, 1286, '2026-05-06', 'Present', '09:31:00', '18:34:00', 543, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(198, 1, 1286, '2026-05-07', 'Present', '09:35:00', '18:46:00', 551, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(199, 1, 1286, '2026-05-08', 'Present', '09:36:00', '18:59:00', 563, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(200, 1, 1286, '2026-05-09', 'Present', '09:40:00', '18:41:00', 541, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(201, 1, 1286, '2026-05-11', 'Present', '09:30:00', '18:54:00', 564, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(202, 1, 1286, '2026-05-12', 'Present', '09:34:00', '18:34:00', 540, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(203, 1, 1286, '2026-05-13', 'Present', '09:39:00', '18:44:00', 545, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(204, 1, 1286, '2026-05-14', 'Present', '09:34:00', '18:43:00', 549, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(205, 1, 1286, '2026-05-15', 'Present', '09:36:00', '18:40:00', 544, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(206, 1, 1286, '2026-05-16', 'Present', '09:30:00', '18:53:00', 563, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(207, 1, 1286, '2026-05-18', 'Present', '09:36:00', '18:48:00', 552, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(208, 1, 1286, '2026-05-19', 'Present', '09:37:00', '18:34:00', 537, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(209, 1, 1286, '2026-05-20', 'Present', '09:34:00', '18:39:00', 545, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(210, 1, 1286, '2026-05-21', 'Present', '09:37:00', '18:55:00', 558, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(211, 1, 1286, '2026-05-22', 'Present', '09:38:00', '18:58:00', 560, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(212, 1, 1286, '2026-05-23', 'Present', '09:33:00', '18:55:00', 562, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(213, 1, 1286, '2026-05-25', 'Present', '09:34:00', '18:38:00', 544, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(214, 1, 1286, '2026-05-26', 'Present', '09:32:00', '18:38:00', 546, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(215, 1, 1286, '2026-05-27', 'Present', '09:36:00', '18:46:00', 550, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(216, 1, 1286, '2026-05-28', 'Present', '09:37:00', '18:44:00', 547, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(217, 1, 1286, '2026-05-29', 'Present', '09:30:00', '18:55:00', 565, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(218, 1, 1286, '2026-05-30', 'Present', '09:35:00', '18:42:00', 547, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:22:13', '2026-08-25 07:22:13'),
(219, 1, 1284, '2026-05-02', 'Present', '09:34:00', '18:45:00', 551, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(220, 1, 1284, '2026-05-04', 'Present', '09:40:00', '18:33:00', 533, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(221, 1, 1284, '2026-05-05', 'Present', '09:33:00', '18:42:00', 549, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(222, 1, 1284, '2026-05-06', 'Present', '09:30:00', '18:30:00', 540, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(223, 1, 1284, '2026-05-07', 'Present', '09:32:00', '18:53:00', 561, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(224, 1, 1284, '2026-05-08', 'Present', '09:34:00', '18:42:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(225, 1, 1284, '2026-05-09', 'Present', '09:38:00', '18:34:00', 536, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(226, 1, 1284, '2026-05-11', 'Present', '09:31:00', '18:51:00', 560, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(227, 1, 1284, '2026-05-12', 'Present', '09:33:00', '18:52:00', 559, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(228, 1, 1284, '2026-05-13', 'Present', '09:39:00', '18:52:00', 553, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(229, 1, 1284, '2026-05-14', 'Present', '09:36:00', '18:30:00', 534, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(230, 1, 1284, '2026-05-15', 'Present', '09:39:00', '18:34:00', 535, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(231, 1, 1284, '2026-05-16', 'Present', '09:37:00', '18:39:00', 542, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(232, 1, 1284, '2026-05-18', 'Present', '09:32:00', '18:31:00', 539, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(233, 1, 1284, '2026-05-19', 'Present', '09:34:00', '18:32:00', 538, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(234, 1, 1284, '2026-05-20', 'Present', '09:37:00', '18:59:00', 562, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(235, 1, 1284, '2026-05-21', 'Present', '09:38:00', '18:37:00', 539, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(236, 1, 1284, '2026-05-22', 'Present', '09:38:00', '18:31:00', 533, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(237, 1, 1284, '2026-05-23', 'Present', '09:37:00', '18:58:00', 561, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(238, 1, 1284, '2026-05-25', 'Present', '09:30:00', '18:38:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(239, 1, 1284, '2026-05-26', 'Absent', '09:39:00', '18:59:00', 560, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:33:27'),
(240, 1, 1284, '2026-05-27', 'Present', '09:39:00', '18:43:00', 544, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(241, 1, 1284, '2026-05-28', 'Present', '09:30:00', '18:38:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(242, 1, 1284, '2026-05-29', 'Present', '09:40:00', '18:44:00', 544, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(243, 1, 1284, '2026-05-30', 'Present', '09:34:00', '18:42:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:17', '2026-08-25 07:24:17'),
(244, 1, 1285, '2026-05-02', 'Present', '09:30:00', '18:41:00', 551, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(245, 1, 1285, '2026-05-04', 'Present', '09:34:00', '18:57:00', 563, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(246, 1, 1285, '2026-05-05', 'Present', '09:30:00', '18:47:00', 557, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(247, 1, 1285, '2026-05-06', 'Present', '09:39:00', '18:34:00', 535, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(248, 1, 1285, '2026-05-07', 'Present', '09:37:00', '18:59:00', 562, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(249, 1, 1285, '2026-05-08', 'Present', '09:40:00', '18:52:00', 552, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(250, 1, 1285, '2026-05-09', 'Present', '09:37:00', '18:36:00', 539, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(251, 1, 1285, '2026-05-11', 'Present', '09:31:00', '18:39:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(252, 1, 1285, '2026-05-12', 'Present', '09:38:00', '18:41:00', 543, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(253, 1, 1285, '2026-05-13', 'Present', '09:35:00', '18:57:00', 562, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(254, 1, 1285, '2026-05-14', 'Present', '09:32:00', '18:38:00', 546, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(255, 1, 1285, '2026-05-15', 'Present', '09:36:00', '18:45:00', 549, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(256, 1, 1285, '2026-05-16', 'Present', '09:33:00', '18:46:00', 553, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(257, 1, 1285, '2026-05-18', 'Present', '09:39:00', '18:43:00', 544, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(258, 1, 1285, '2026-05-19', 'Present', '09:32:00', '18:37:00', 545, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(259, 1, 1285, '2026-05-20', 'Present', '09:31:00', '18:52:00', 561, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(260, 1, 1285, '2026-05-21', 'Present', '09:37:00', '18:45:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(261, 1, 1285, '2026-05-22', 'Present', '09:31:00', '18:38:00', 547, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(262, 1, 1285, '2026-05-23', 'Present', '09:34:00', '18:34:00', 540, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(263, 1, 1285, '2026-05-25', 'Present', '09:36:00', '18:49:00', 553, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(264, 1, 1285, '2026-05-26', 'Present', '09:38:00', '18:42:00', 544, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(265, 1, 1285, '2026-05-27', 'Present', '09:36:00', '18:36:00', 540, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(266, 1, 1285, '2026-05-28', 'Present', '09:39:00', '18:53:00', 554, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(267, 1, 1285, '2026-05-29', 'Present', '09:31:00', '18:47:00', 556, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(268, 1, 1285, '2026-05-30', 'Present', '09:39:00', '18:43:00', 544, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:28', '2026-08-25 07:24:28'),
(269, 1, 1283, '2026-05-02', 'HalfDay', '09:39:00', '13:34:00', 235, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:25:03'),
(270, 1, 1283, '2026-05-04', 'Present', '09:35:00', '18:41:00', 546, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(271, 1, 1283, '2026-05-05', 'Present', '09:30:00', '18:51:00', 561, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(272, 1, 1283, '2026-05-06', 'Present', '09:31:00', '18:46:00', 555, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(273, 1, 1283, '2026-05-07', 'Present', '09:31:00', '18:37:00', 546, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(274, 1, 1283, '2026-05-08', 'Present', '09:35:00', '18:43:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(275, 1, 1283, '2026-05-09', 'Present', '09:33:00', '18:45:00', 552, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(276, 1, 1283, '2026-05-11', 'Present', '09:36:00', '18:41:00', 545, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(277, 1, 1283, '2026-05-12', 'Present', '09:35:00', '18:54:00', 559, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(278, 1, 1283, '2026-05-13', 'Present', '09:39:00', '18:37:00', 538, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(279, 1, 1283, '2026-05-14', 'Present', '09:31:00', '18:35:00', 544, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(280, 1, 1283, '2026-05-15', 'Present', '09:31:00', '18:38:00', 547, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(281, 1, 1283, '2026-05-16', 'Present', '09:33:00', '18:39:00', 546, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(282, 1, 1283, '2026-05-18', 'Present', '09:33:00', '18:40:00', 547, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(283, 1, 1283, '2026-05-19', 'Present', '09:35:00', '18:58:00', 563, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(284, 1, 1283, '2026-05-20', 'Present', '09:32:00', '18:59:00', 567, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(285, 1, 1283, '2026-05-21', 'Present', '09:37:00', '18:54:00', 557, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(286, 1, 1283, '2026-05-22', 'Present', '09:31:00', '18:38:00', 547, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(287, 1, 1283, '2026-05-23', 'Present', '09:38:00', '18:33:00', 535, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(288, 1, 1283, '2026-05-25', 'Present', '09:31:00', '18:40:00', 549, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(289, 1, 1283, '2026-05-26', 'Present', '09:31:00', '18:56:00', 565, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(290, 1, 1283, '2026-05-27', 'Present', '09:31:00', '18:39:00', 548, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(291, 1, 1283, '2026-05-28', 'Present', '09:35:00', '18:39:00', 544, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(292, 1, 1283, '2026-05-29', 'Present', '09:31:00', '18:41:00', 550, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(293, 1, 1283, '2026-05-30', 'Present', '09:33:00', '18:42:00', 549, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:24:45', '2026-08-25 07:24:45'),
(294, 1, 1284, '2026-08-01', 'Present', '09:34:00', '18:44:00', 550, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(295, 1, 1284, '2026-08-03', 'Present', '09:37:00', '18:58:00', 561, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(296, 1, 1284, '2026-08-04', 'Present', '09:36:00', '18:46:00', 550, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(297, 1, 1284, '2026-08-05', 'Present', '09:30:00', '18:37:00', 547, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(298, 1, 1284, '2026-08-06', 'Present', '09:39:00', '18:38:00', 539, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(299, 1, 1284, '2026-08-07', 'Present', '09:36:00', '18:46:00', 550, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(300, 1, 1284, '2026-08-08', 'Present', '09:34:00', '18:54:00', 560, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(301, 1, 1284, '2026-08-10', 'Present', '09:40:00', '18:56:00', 556, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(302, 1, 1284, '2026-08-11', 'Present', '09:32:00', '18:57:00', 565, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(303, 1, 1284, '2026-08-12', 'Present', '09:32:00', '18:35:00', 543, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(304, 1, 1284, '2026-08-13', 'Present', '09:32:00', '18:58:00', 566, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(305, 1, 1284, '2026-08-14', 'Present', '09:33:00', '18:44:00', 551, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(306, 1, 1284, '2026-08-15', 'Present', '09:39:00', '19:00:00', 561, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(307, 1, 1284, '2026-08-17', 'Present', '09:40:00', '18:33:00', 533, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(308, 1, 1284, '2026-08-18', 'Present', '09:34:00', '18:37:00', 543, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(309, 1, 1284, '2026-08-19', 'Present', '09:33:00', '18:38:00', 545, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(310, 1, 1284, '2026-08-20', 'Present', '09:39:00', '18:40:00', 541, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(311, 1, 1284, '2026-08-21', 'Present', '09:38:00', '18:54:00', 556, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(312, 1, 1284, '2026-08-22', 'Present', '09:36:00', '18:46:00', 550, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(313, 1, 1284, '2026-08-25', 'Absent', '09:31:00', '18:58:00', 567, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:30:30'),
(314, 1, 1284, '2026-08-26', 'Present', '09:34:00', '18:36:00', 542, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(315, 1, 1284, '2026-08-27', 'Present', '09:32:00', '18:51:00', 559, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(316, 1, 1284, '2026-08-28', 'Present', '09:33:00', '18:59:00', 566, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(317, 1, 1284, '2026-08-29', 'Present', '09:35:00', '18:58:00', 563, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(318, 1, 1284, '2026-08-31', 'Present', '09:32:00', '18:42:00', 550, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 07:27:23', '2026-08-25 07:27:23'),
(319, 1, 1283, '2026-08-01', 'Present', '09:37:00', '18:34:00', 537, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 10:54:36', '2026-08-25 10:54:36'),
(320, 1, 1283, '2026-08-08', 'Present', '09:37:00', '19:00:00', 563, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 10:54:36', '2026-08-25 10:54:36'),
(321, 1, 1283, '2026-08-15', 'Present', '09:36:00', '18:32:00', 536, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 10:54:36', '2026-08-25 10:54:36'),
(322, 1, 1283, '2026-08-22', 'Present', '09:38:00', '18:30:00', 532, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 10:54:36', '2026-08-25 10:54:36'),
(323, 1, 1283, '2026-08-29', 'Present', '09:34:00', '18:38:00', 544, 'manual', 1278, 'Monthly attendance quick fill', '2026-08-25 10:54:36', '2026-08-25 10:54:36');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_regularizations`
--

CREATE TABLE `attendance_regularizations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT 'employee raising the request',
  `attendance_date` date NOT NULL,
  `requested_status` varchar(20) NOT NULL COMMENT 'desired status e.g. Present',
  `requested_check_in` time DEFAULT NULL,
  `requested_check_out` time DEFAULT NULL,
  `reason` varchar(500) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending|approved|rejected',
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `review_note` varchar(500) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bonuses`
--

CREATE TABLE `bonuses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `year` smallint(5) UNSIGNED NOT NULL,
  `month` tinyint(3) UNSIGNED NOT NULL,
  `is_applied` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'consumed by a payroll run',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('mbsguru_cache_activator.installed', 'a:37:{s:8:\"Language\";b:1;s:4:\"Blog\";b:1;s:13:\"GlobalSetting\";b:1;s:8:\"Currency\";b:1;s:12:\"BasicPayment\";b:1;s:12:\"Subscription\";b:1;s:11:\"PageBuilder\";b:1;s:19:\"PageTemplateBuilder\";b:1;s:8:\"Customer\";b:1;s:10:\"NewsLetter\";b:1;s:14:\"ContactMessage\";b:1;s:18:\"LandingPageMessage\";b:1;s:5:\"Order\";b:1;s:6:\"Coupon\";b:1;s:15:\"PaymentWithdraw\";b:1;s:11:\"Testimonial\";b:1;s:3:\"Faq\";b:1;s:8:\"Location\";b:1;s:17:\"InstructorRequest\";b:1;s:6:\"Course\";b:1;s:18:\"CertificateBuilder\";b:1;s:6:\"Badges\";b:1;s:8:\"Frontend\";b:1;s:5:\"Brand\";b:1;s:14:\"SiteAppearance\";b:1;s:13:\"FooterSetting\";b:1;s:10:\"SocialLink\";b:1;s:11:\"Menubuilder\";b:1;s:7:\"BkashPG\";b:1;s:13:\"CryptoPayment\";b:1;s:13:\"MercadoPagoPG\";b:1;s:7:\"Marquee\";b:1;s:10:\"Attendance\";b:1;s:10:\"HrEmployee\";b:1;s:5:\"Leave\";b:1;s:7:\"Payroll\";b:1;s:7:\"Company\";b:1;}', 1788768977),
('mbsguru_cache_bkashConfig', 'O:8:\"stdClass\":8:{s:13:\"bkash_sandbox\";s:1:\"1\";s:9:\"bkash_key\";s:9:\"bkash_key\";s:12:\"bkash_secret\";s:12:\"bkash_secret\";s:14:\"bkash_username\";s:14:\"bkash_username\";s:14:\"bkash_password\";s:14:\"bkash_password\";s:12:\"bkash_status\";s:8:\"inactive\";s:12:\"bkash_charge\";s:1:\"0\";s:11:\"bkash_image\";s:32:\"uploads/website-images/bkash.png\";}', 2102417635),
('mbsguru_cache_brand:platform_defaults', 'a:103:{s:8:\"app_name\";s:7:\"MBSGuru\";s:7:\"version\";s:5:\"2.3.0\";s:4:\"logo\";s:59:\"uploads/custom-images/wsus-img-2026-04-03-04-09-42-7557.png\";s:8:\"timezone\";s:12:\"Asia/Kolkata\";s:7:\"favicon\";s:59:\"uploads/custom-images/wsus-img-2026-04-17-01-51-02-4244.png\";s:13:\"cookie_status\";s:6:\"active\";s:6:\"border\";s:6:\"normal\";s:7:\"corners\";s:4:\"thin\";s:16:\"background_color\";s:7:\"#184dec\";s:10:\"text_color\";s:7:\"#fafafa\";s:12:\"border_color\";s:7:\"#0a58d6\";s:12:\"btn_bg_color\";s:7:\"#fffceb\";s:14:\"btn_text_color\";s:7:\"#222758\";s:9:\"link_text\";s:9:\"More Info\";s:4:\"link\";s:20:\"/page/privacy-policy\";s:8:\"btn_text\";s:3:\"Yes\";s:7:\"message\";s:170:\"This website uses essential cookies to ensure its proper operation and tracking cookies to understand how you interact with it. The latter will be set only upon approval.\";s:14:\"copyright_text\";s:59:\"2026 All Rights Reserved. Developed By Digital Hub Solution\";s:18:\"recaptcha_site_key\";s:40:\"6Ld9G1gtAAAAAKvTj0JZnFPyeISgBpaiPvXUBf3y\";s:20:\"recaptcha_secret_key\";N;s:16:\"recaptcha_status\";s:8:\"inactive\";s:11:\"tawk_status\";s:8:\"inactive\";s:14:\"tawk_chat_link\";s:14:\"tawk_chat_link\";s:24:\"google_tagmanager_status\";s:6:\"active\";s:20:\"google_tagmanager_id\";s:20:\"google_tagmanager_id\";s:12:\"pixel_status\";s:6:\"active\";s:12:\"pixel_app_id\";s:12:\"pixel_app_id\";s:21:\"facebook_login_status\";s:8:\"inactive\";s:15:\"facebook_app_id\";s:15:\"facebook_app_id\";s:19:\"facebook_app_secret\";s:19:\"facebook_app_secret\";s:21:\"facebook_redirect_url\";s:21:\"facebook_redirect_url\";s:19:\"google_login_status\";s:6:\"active\";s:15:\"gmail_client_id\";s:15:\"gmail_client_id\";s:15:\"gmail_secret_id\";s:15:\"gmail_secret_id\";s:18:\"gmail_redirect_url\";s:0:\"\";s:14:\"default_avatar\";s:41:\"uploads/website-images/default-avatar.png\";s:16:\"breadcrumb_image\";s:43:\"uploads/website-images/breadcrumb-image.jpg\";s:9:\"mail_host\";s:16:\"mail.mbsguru.com\";s:17:\"mail_sender_email\";s:19:\"noreply@mbsguru.com\";s:13:\"mail_username\";s:19:\"noreply@mbsguru.com\";s:13:\"mail_password\";s:16:\"N-*5W;r]us49~Q$b\";s:9:\"mail_port\";s:3:\"465\";s:15:\"mail_encryption\";s:3:\"ssl\";s:16:\"mail_sender_name\";s:24:\"MBS Guru Private Limited\";s:29:\"contact_message_receiver_mail\";s:21:\"sureshs.dhs@gmail.com\";s:13:\"pusher_app_id\";s:13:\"pusher_app_id\";s:14:\"pusher_app_key\";s:14:\"pusher_app_key\";s:17:\"pusher_app_secret\";s:17:\"pusher_app_secret\";s:18:\"pusher_app_cluster\";s:18:\"pusher_app_cluster\";s:13:\"pusher_status\";s:8:\"inactive\";s:15:\"club_point_rate\";s:1:\"1\";s:17:\"club_point_status\";s:6:\"active\";s:16:\"maintenance_mode\";s:1:\"0\";s:17:\"maintenance_title\";s:25:\"Website Under maintenance\";s:23:\"maintenance_description\";s:59:\"<p>Working on more modules coming soon with AI and Boot</p>\";s:16:\"last_update_date\";s:19:\"2024-08-15 03:23:17\";s:10:\"is_queable\";s:8:\"inactive\";s:15:\"commission_rate\";s:1:\"2\";s:12:\"site_address\";s:57:\"D-247/4A, D Block, Sector 63, Noida, Uttar Pradesh 201301\";s:10:\"site_email\";s:16:\"info@mbsguru.com\";s:10:\"site_theme\";s:4:\"main\";s:9:\"preloader\";s:59:\"uploads/custom-images/wsus-img-2026-04-03-04-09-27-4891.png\";s:13:\"primary_color\";s:7:\"#5751e1\";s:15:\"secondary_color\";s:7:\"#ffc224\";s:16:\"common_color_one\";s:7:\"#050071\";s:16:\"common_color_two\";s:7:\"#282568\";s:18:\"common_color_three\";s:7:\"#1C1A4A\";s:17:\"common_color_four\";s:7:\"#06042E\";s:17:\"common_color_five\";s:7:\"#4a44d1\";s:17:\"show_all_homepage\";s:1:\"1\";s:22:\"google_analytic_status\";s:8:\"inactive\";s:18:\"google_analytic_id\";s:18:\"google_analytic_id\";s:16:\"preloader_status\";s:1:\"0\";s:17:\"maintenance_image\";s:59:\"uploads/custom-images/wsus-img-2025-05-01-01-49-36-4009.png\";s:14:\"live_mail_send\";s:1:\"5\";s:16:\"wasabi_access_id\";s:16:\"wasabi_access_id\";s:17:\"wasabi_secret_key\";s:17:\"wasabi_secret_key\";s:13:\"wasabi_bucket\";s:13:\"wasabi_bucket\";s:13:\"wasabi_region\";s:9:\"us-east-1\";s:13:\"wasabi_status\";s:6:\"active\";s:13:\"aws_access_id\";s:13:\"aws_access_id\";s:14:\"aws_secret_key\";s:14:\"aws_secret_key\";s:10:\"aws_bucket\";s:10:\"aws_bucket\";s:10:\"aws_region\";s:9:\"us-east-1\";s:10:\"aws_status\";s:8:\"inactive\";s:20:\"header_topbar_status\";s:8:\"inactive\";s:17:\"cursor_dot_status\";s:8:\"inactive\";s:20:\"header_social_status\";s:8:\"inactive\";s:13:\"watermark_img\";s:59:\"uploads/custom-images/wsus-img-2026-04-03-04-18-23-1911.png\";s:8:\"position\";s:8:\"top_left\";s:7:\"opacity\";s:3:\"0.7\";s:9:\"max_width\";s:3:\"300\";s:16:\"watermark_status\";s:6:\"active\";s:18:\"years_of_exprience\";s:1:\"6\";s:17:\"satisfied_clients\";s:3:\"200\";s:17:\"countries_reached\";s:2:\"15\";s:17:\"classes_conducted\";s:1:\"2\";s:27:\"referral_commission_percent\";s:2:\"10\";s:22:\"attendance_min_percent\";s:2:\"50\";s:21:\"custom_domain_enabled\";s:1:\"1\";s:23:\"custom_domain_server_ip\";s:14:\"149.248.18.191\";s:31:\"custom_domain_requires_approval\";s:1:\"0\";s:27:\"custom_domain_max_per_coach\";s:1:\"1\";}', 1787060719),
('mbsguru_cache_coach_dashboard:1278:2026-08-18', 'a:5:{s:4:\"date\";s:10:\"2026-08-18\";s:14:\"yesterday_date\";s:10:\"2026-08-17\";s:9:\"aggregate\";a:6:{s:13:\"total_batches\";i:0;s:12:\"active_today\";i:0;s:10:\"idle_today\";i:0;s:7:\"healthy\";i:0;s:7:\"at_risk\";i:0;s:5:\"empty\";i:0;}s:6:\"active\";O:29:\"Illuminate\\Support\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:4:\"idle\";O:29:\"Illuminate\\Support\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}}', 1787060199),
('mbsguru_cache_coach_trial_config', 'a:4:{s:7:\"enabled\";b:1;s:4:\"days\";i:14;s:5:\"grace\";i:3;s:5:\"after\";s:4:\"soft\";}', 1787060439),
('mbsguru_cache_countries', 'O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:2:{i:0;O:35:\"Modules\\Location\\app\\Models\\Country\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"countries\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:5:{s:2:\"id\";i:1;s:4:\"name\";s:5:\"India\";s:6:\"status\";i:1;s:10:\"created_at\";s:19:\"2025-04-30 16:46:05\";s:10:\"updated_at\";s:19:\"2025-04-30 16:46:05\";}s:11:\"\0*\0original\";a:5:{s:2:\"id\";i:1;s:4:\"name\";s:5:\"India\";s:6:\"status\";i:1;s:10:\"created_at\";s:19:\"2025-04-30 16:46:05\";s:10:\"updated_at\";s:19:\"2025-04-30 16:46:05\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}i:1;O:35:\"Modules\\Location\\app\\Models\\Country\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:9:\"countries\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:5:{s:2:\"id\";i:2;s:4:\"name\";s:12:\"United State\";s:6:\"status\";i:1;s:10:\"created_at\";s:19:\"2025-04-30 16:46:14\";s:10:\"updated_at\";s:19:\"2025-04-30 16:46:14\";}s:11:\"\0*\0original\";a:5:{s:2:\"id\";i:2;s:4:\"name\";s:12:\"United State\";s:6:\"status\";i:1;s:10:\"created_at\";s:19:\"2025-04-30 16:46:14\";s:10:\"updated_at\";s:19:\"2025-04-30 16:46:14\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}', 2102419658),
('mbsguru_cache_cryptoConfig', 'O:8:\"stdClass\":6:{s:12:\"crypto_image\";s:36:\"uploads/website-images/coingate.webp\";s:13:\"crypto_status\";s:8:\"inactive\";s:14:\"crypto_sandbox\";s:1:\"1\";s:12:\"crypto_token\";s:12:\"crypto_token\";s:13:\"crypto_charge\";s:1:\"0\";s:23:\"crypto_receive_currency\";s:3:\"USD\";}', 2102417635),
('mbsguru_cache_csl:students_for_coach:1278', 'a:2:{i:0;i:1279;i:1;i:1282;}', 1787060719),
('mbsguru_cache_customCode', 'N;', 2102420691),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Attendance/module.json', 'a:7:{s:4:\"name\";s:10:\"Attendance\";s:5:\"alias\";s:10:\"attendance\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:58:\"Modules\\Attendance\\app\\Providers\\AttendanceServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250577),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Badges/module.json', 'a:7:{s:4:\"name\";s:6:\"Badges\";s:5:\"alias\";s:6:\"badges\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:50:\"Modules\\Badges\\app\\Providers\\BadgesServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250577),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/BasicPayment/module.json', 'a:7:{s:4:\"name\";s:12:\"BasicPayment\";s:5:\"alias\";s:12:\"basicpayment\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:62:\"Modules\\BasicPayment\\app\\Providers\\BasicPaymentServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250577),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/BkashPG/module.json', 'a:7:{s:4:\"name\";s:7:\"BkashPG\";s:5:\"alias\";s:7:\"bkashpg\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:52:\"Modules\\BkashPG\\app\\Providers\\BkashPGServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250577),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Blog/module.json', 'a:7:{s:4:\"name\";s:4:\"Blog\";s:5:\"alias\";s:4:\"blog\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:46:\"Modules\\Blog\\app\\Providers\\BlogServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Brand/module.json', 'a:7:{s:4:\"name\";s:5:\"Brand\";s:5:\"alias\";s:5:\"brand\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:48:\"Modules\\Brand\\app\\Providers\\BrandServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/CertificateBuilder/module.json', 'a:7:{s:4:\"name\";s:18:\"CertificateBuilder\";s:5:\"alias\";s:18:\"certificatebuilder\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:74:\"Modules\\CertificateBuilder\\app\\Providers\\CertificateBuilderServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Company/module.json', 'a:7:{s:4:\"name\";s:7:\"Company\";s:5:\"alias\";s:7:\"company\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:52:\"Modules\\Company\\app\\Providers\\CompanyServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/ContactMessage/module.json', 'a:7:{s:4:\"name\";s:14:\"ContactMessage\";s:5:\"alias\";s:14:\"contactmessage\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:66:\"Modules\\ContactMessage\\app\\Providers\\ContactMessageServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Coupon/module.json', 'a:7:{s:4:\"name\";s:6:\"Coupon\";s:5:\"alias\";s:6:\"coupon\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:50:\"Modules\\Coupon\\app\\Providers\\CouponServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Course/module.json', 'a:7:{s:4:\"name\";s:6:\"Course\";s:5:\"alias\";s:6:\"course\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:50:\"Modules\\Course\\app\\Providers\\CourseServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/CryptoPayment/module.json', 'a:7:{s:4:\"name\";s:13:\"CryptoPayment\";s:5:\"alias\";s:13:\"cryptopayment\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:64:\"Modules\\CryptoPayment\\app\\Providers\\CryptoPaymentServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Currency/module.json', 'a:7:{s:4:\"name\";s:8:\"Currency\";s:5:\"alias\";s:8:\"currency\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Currency\\app\\Providers\\CurrencyServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Customer/module.json', 'a:7:{s:4:\"name\";s:8:\"Customer\";s:5:\"alias\";s:8:\"customer\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Customer\\app\\Providers\\CustomerServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Faq/module.json', 'a:7:{s:4:\"name\";s:3:\"Faq\";s:5:\"alias\";s:3:\"faq\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:44:\"Modules\\Faq\\app\\Providers\\FaqServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/FooterSetting/module.json', 'a:7:{s:4:\"name\";s:13:\"FooterSetting\";s:5:\"alias\";s:13:\"footersetting\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:64:\"Modules\\FooterSetting\\app\\Providers\\FooterSettingServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Frontend/module.json', 'a:7:{s:4:\"name\";s:8:\"Frontend\";s:5:\"alias\";s:8:\"frontend\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Frontend\\app\\Providers\\FrontendServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/GlobalSetting/module.json', 'a:7:{s:4:\"name\";s:13:\"GlobalSetting\";s:5:\"alias\";s:13:\"globalsetting\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:64:\"Modules\\GlobalSetting\\app\\Providers\\GlobalSettingServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/HrEmployee/module.json', 'a:7:{s:4:\"name\";s:10:\"HrEmployee\";s:5:\"alias\";s:10:\"hremployee\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:58:\"Modules\\HrEmployee\\app\\Providers\\HrEmployeeServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/InstructorRequest/module.json', 'a:7:{s:4:\"name\";s:17:\"InstructorRequest\";s:5:\"alias\";s:17:\"instructorrequest\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:72:\"Modules\\InstructorRequest\\app\\Providers\\InstructorRequestServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/LandingPageMessage/module.json', 'a:7:{s:4:\"name\";s:18:\"LandingPageMessage\";s:5:\"alias\";s:18:\"landingpagemessage\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:74:\"Modules\\LandingPageMessage\\app\\Providers\\LandingPageMessageServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Language/module.json', 'a:7:{s:4:\"name\";s:8:\"Language\";s:5:\"alias\";s:8:\"language\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Language\\app\\Providers\\LanguageServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Leave/module.json', 'a:7:{s:4:\"name\";s:5:\"Leave\";s:5:\"alias\";s:5:\"leave\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:48:\"Modules\\Leave\\app\\Providers\\LeaveServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Location/module.json', 'a:7:{s:4:\"name\";s:8:\"Location\";s:5:\"alias\";s:8:\"location\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Location\\app\\Providers\\LocationServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Marquee/module.json', 'a:7:{s:4:\"name\";s:7:\"Marquee\";s:5:\"alias\";s:7:\"marquee\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:52:\"Modules\\Marquee\\app\\Providers\\MarqueeServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Menubuilder/module.json', 'a:7:{s:4:\"name\";s:11:\"Menubuilder\";s:5:\"alias\";s:11:\"menubuilder\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:60:\"Modules\\Menubuilder\\app\\Providers\\MenubuilderServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/MercadoPagoPG/module.json', 'a:7:{s:4:\"name\";s:13:\"MercadoPagoPG\";s:5:\"alias\";s:13:\"mercadopagopg\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:64:\"Modules\\MercadoPagoPG\\app\\Providers\\MercadoPagoPGServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/NewsLetter/module.json', 'a:7:{s:4:\"name\";s:10:\"NewsLetter\";s:5:\"alias\";s:10:\"newsletter\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:58:\"Modules\\NewsLetter\\app\\Providers\\NewsLetterServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Order/module.json', 'a:7:{s:4:\"name\";s:5:\"Order\";s:5:\"alias\";s:5:\"order\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:48:\"Modules\\Order\\app\\Providers\\OrderServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/PageBuilder/module.json', 'a:7:{s:4:\"name\";s:11:\"PageBuilder\";s:5:\"alias\";s:11:\"pagebuilder\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:60:\"Modules\\PageBuilder\\app\\Providers\\PageBuilderServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/PageTemplateBuilder/module.json', 'a:7:{s:4:\"name\";s:19:\"PageTemplateBuilder\";s:5:\"alias\";s:19:\"pagetemplatebuilder\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:76:\"Modules\\PageTemplateBuilder\\app\\Providers\\PageTemplateBuilderServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/PaymentWithdraw/module.json', 'a:7:{s:4:\"name\";s:15:\"PaymentWithdraw\";s:5:\"alias\";s:15:\"paymentwithdraw\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:68:\"Modules\\PaymentWithdraw\\app\\Providers\\PaymentWithdrawServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Payroll/module.json', 'a:7:{s:4:\"name\";s:7:\"Payroll\";s:5:\"alias\";s:7:\"payroll\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:52:\"Modules\\Payroll\\app\\Providers\\PayrollServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/SiteAppearance/module.json', 'a:7:{s:4:\"name\";s:14:\"SiteAppearance\";s:5:\"alias\";s:14:\"siteappearance\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:66:\"Modules\\SiteAppearance\\app\\Providers\\SiteAppearanceServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/SocialLink/module.json', 'a:7:{s:4:\"name\";s:10:\"SocialLink\";s:5:\"alias\";s:10:\"sociallink\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:58:\"Modules\\SocialLink\\app\\Providers\\SocialLinkServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Subscription/module.json', 'a:7:{s:4:\"name\";s:12:\"Subscription\";s:5:\"alias\";s:12:\"subscription\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:62:\"Modules\\Subscription\\app\\Providers\\SubscriptionServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Testimonial/module.json', 'a:7:{s:4:\"name\";s:11:\"Testimonial\";s:5:\"alias\";s:11:\"testimonial\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:60:\"Modules\\Testimonial\\app\\Providers\\TestimonialServiceProvider\";}s:5:\"files\";a:0:{}}', 1788250578),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Attendance/module.json', 'a:7:{s:4:\"name\";s:10:\"Attendance\";s:5:\"alias\";s:10:\"attendance\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:58:\"Modules\\Attendance\\app\\Providers\\AttendanceServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Badges/module.json', 'a:7:{s:4:\"name\";s:6:\"Badges\";s:5:\"alias\";s:6:\"badges\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:50:\"Modules\\Badges\\app\\Providers\\BadgesServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/BasicPayment/module.json', 'a:7:{s:4:\"name\";s:12:\"BasicPayment\";s:5:\"alias\";s:12:\"basicpayment\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:62:\"Modules\\BasicPayment\\app\\Providers\\BasicPaymentServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/BkashPG/module.json', 'a:7:{s:4:\"name\";s:7:\"BkashPG\";s:5:\"alias\";s:7:\"bkashpg\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:52:\"Modules\\BkashPG\\app\\Providers\\BkashPGServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Blog/module.json', 'a:7:{s:4:\"name\";s:4:\"Blog\";s:5:\"alias\";s:4:\"blog\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:46:\"Modules\\Blog\\app\\Providers\\BlogServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Brand/module.json', 'a:7:{s:4:\"name\";s:5:\"Brand\";s:5:\"alias\";s:5:\"brand\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:48:\"Modules\\Brand\\app\\Providers\\BrandServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/CertificateBuilder/module.json', 'a:7:{s:4:\"name\";s:18:\"CertificateBuilder\";s:5:\"alias\";s:18:\"certificatebuilder\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:74:\"Modules\\CertificateBuilder\\app\\Providers\\CertificateBuilderServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/ContactMessage/module.json', 'a:7:{s:4:\"name\";s:14:\"ContactMessage\";s:5:\"alias\";s:14:\"contactmessage\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:66:\"Modules\\ContactMessage\\app\\Providers\\ContactMessageServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Coupon/module.json', 'a:7:{s:4:\"name\";s:6:\"Coupon\";s:5:\"alias\";s:6:\"coupon\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:50:\"Modules\\Coupon\\app\\Providers\\CouponServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Course/module.json', 'a:7:{s:4:\"name\";s:6:\"Course\";s:5:\"alias\";s:6:\"course\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:50:\"Modules\\Course\\app\\Providers\\CourseServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/CryptoPayment/module.json', 'a:7:{s:4:\"name\";s:13:\"CryptoPayment\";s:5:\"alias\";s:13:\"cryptopayment\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:64:\"Modules\\CryptoPayment\\app\\Providers\\CryptoPaymentServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Currency/module.json', 'a:7:{s:4:\"name\";s:8:\"Currency\";s:5:\"alias\";s:8:\"currency\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Currency\\app\\Providers\\CurrencyServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Customer/module.json', 'a:7:{s:4:\"name\";s:8:\"Customer\";s:5:\"alias\";s:8:\"customer\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Customer\\app\\Providers\\CustomerServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Faq/module.json', 'a:7:{s:4:\"name\";s:3:\"Faq\";s:5:\"alias\";s:3:\"faq\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:44:\"Modules\\Faq\\app\\Providers\\FaqServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/FooterSetting/module.json', 'a:7:{s:4:\"name\";s:13:\"FooterSetting\";s:5:\"alias\";s:13:\"footersetting\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:64:\"Modules\\FooterSetting\\app\\Providers\\FooterSettingServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Frontend/module.json', 'a:7:{s:4:\"name\";s:8:\"Frontend\";s:5:\"alias\";s:8:\"frontend\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Frontend\\app\\Providers\\FrontendServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/GlobalSetting/module.json', 'a:7:{s:4:\"name\";s:13:\"GlobalSetting\";s:5:\"alias\";s:13:\"globalsetting\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:64:\"Modules\\GlobalSetting\\app\\Providers\\GlobalSettingServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/HrEmployee/module.json', 'a:7:{s:4:\"name\";s:10:\"HrEmployee\";s:5:\"alias\";s:10:\"hremployee\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:58:\"Modules\\HrEmployee\\app\\Providers\\HrEmployeeServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/InstructorRequest/module.json', 'a:7:{s:4:\"name\";s:17:\"InstructorRequest\";s:5:\"alias\";s:17:\"instructorrequest\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:72:\"Modules\\InstructorRequest\\app\\Providers\\InstructorRequestServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/LandingPageMessage/module.json', 'a:7:{s:4:\"name\";s:18:\"LandingPageMessage\";s:5:\"alias\";s:18:\"landingpagemessage\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:74:\"Modules\\LandingPageMessage\\app\\Providers\\LandingPageMessageServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Language/module.json', 'a:7:{s:4:\"name\";s:8:\"Language\";s:5:\"alias\";s:8:\"language\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Language\\app\\Providers\\LanguageServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Leave/module.json', 'a:7:{s:4:\"name\";s:5:\"Leave\";s:5:\"alias\";s:5:\"leave\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:48:\"Modules\\Leave\\app\\Providers\\LeaveServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Location/module.json', 'a:7:{s:4:\"name\";s:8:\"Location\";s:5:\"alias\";s:8:\"location\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Location\\app\\Providers\\LocationServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Marquee/module.json', 'a:7:{s:4:\"name\";s:7:\"Marquee\";s:5:\"alias\";s:7:\"marquee\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:52:\"Modules\\Marquee\\app\\Providers\\MarqueeServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Menubuilder/module.json', 'a:7:{s:4:\"name\";s:11:\"Menubuilder\";s:5:\"alias\";s:11:\"menubuilder\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:60:\"Modules\\Menubuilder\\app\\Providers\\MenubuilderServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/MercadoPagoPG/module.json', 'a:7:{s:4:\"name\";s:13:\"MercadoPagoPG\";s:5:\"alias\";s:13:\"mercadopagopg\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:64:\"Modules\\MercadoPagoPG\\app\\Providers\\MercadoPagoPGServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/NewsLetter/module.json', 'a:7:{s:4:\"name\";s:10:\"NewsLetter\";s:5:\"alias\";s:10:\"newsletter\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:58:\"Modules\\NewsLetter\\app\\Providers\\NewsLetterServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Order/module.json', 'a:7:{s:4:\"name\";s:5:\"Order\";s:5:\"alias\";s:5:\"order\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:48:\"Modules\\Order\\app\\Providers\\OrderServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/PageBuilder/module.json', 'a:7:{s:4:\"name\";s:11:\"PageBuilder\";s:5:\"alias\";s:11:\"pagebuilder\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:60:\"Modules\\PageBuilder\\app\\Providers\\PageBuilderServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/PageTemplateBuilder/module.json', 'a:7:{s:4:\"name\";s:19:\"PageTemplateBuilder\";s:5:\"alias\";s:19:\"pagetemplatebuilder\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:76:\"Modules\\PageTemplateBuilder\\app\\Providers\\PageTemplateBuilderServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/PaymentWithdraw/module.json', 'a:7:{s:4:\"name\";s:15:\"PaymentWithdraw\";s:5:\"alias\";s:15:\"paymentwithdraw\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:68:\"Modules\\PaymentWithdraw\\app\\Providers\\PaymentWithdrawServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Payroll/module.json', 'a:7:{s:4:\"name\";s:7:\"Payroll\";s:5:\"alias\";s:7:\"payroll\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:52:\"Modules\\Payroll\\app\\Providers\\PayrollServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/SiteAppearance/module.json', 'a:7:{s:4:\"name\";s:14:\"SiteAppearance\";s:5:\"alias\";s:14:\"siteappearance\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:66:\"Modules\\SiteAppearance\\app\\Providers\\SiteAppearanceServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/SocialLink/module.json', 'a:7:{s:4:\"name\";s:10:\"SocialLink\";s:5:\"alias\";s:10:\"sociallink\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:58:\"Modules\\SocialLink\\app\\Providers\\SocialLinkServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Subscription/module.json', 'a:7:{s:4:\"name\";s:12:\"Subscription\";s:5:\"alias\";s:12:\"subscription\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:62:\"Modules\\Subscription\\app\\Providers\\SubscriptionServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_E:\\xampp 8.2\\htdocs\\laravel\\erp\\Modules/Testimonial/module.json', 'a:7:{s:4:\"name\";s:11:\"Testimonial\";s:5:\"alias\";s:11:\"testimonial\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:60:\"Modules\\Testimonial\\app\\Providers\\TestimonialServiceProvider\";}s:5:\"files\";a:0:{}}', 1787145077),
('mbsguru_cache_instructor.dashboard.content:1278', 'a:3:{s:13:\"upcoming_live\";O:29:\"Illuminate\\Support\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:14:\"active_batches\";O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:14:\"recent_courses\";O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}}', 1787060199);
INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('mbsguru_cache_laravel-modules', 'a:37:{s:10:\"Attendance\";a:8:{s:4:\"name\";s:10:\"Attendance\";s:5:\"alias\";s:10:\"attendance\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:58:\"Modules\\Attendance\\app\\Providers\\AttendanceServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:56:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Attendance\";}s:6:\"Badges\";a:8:{s:4:\"name\";s:6:\"Badges\";s:5:\"alias\";s:6:\"badges\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:50:\"Modules\\Badges\\app\\Providers\\BadgesServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:52:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Badges\";}s:12:\"BasicPayment\";a:8:{s:4:\"name\";s:12:\"BasicPayment\";s:5:\"alias\";s:12:\"basicpayment\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:62:\"Modules\\BasicPayment\\app\\Providers\\BasicPaymentServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:58:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/BasicPayment\";}s:7:\"BkashPG\";a:8:{s:4:\"name\";s:7:\"BkashPG\";s:5:\"alias\";s:7:\"bkashpg\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:52:\"Modules\\BkashPG\\app\\Providers\\BkashPGServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:53:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/BkashPG\";}s:4:\"Blog\";a:8:{s:4:\"name\";s:4:\"Blog\";s:5:\"alias\";s:4:\"blog\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:46:\"Modules\\Blog\\app\\Providers\\BlogServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:50:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Blog\";}s:5:\"Brand\";a:8:{s:4:\"name\";s:5:\"Brand\";s:5:\"alias\";s:5:\"brand\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:48:\"Modules\\Brand\\app\\Providers\\BrandServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:51:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Brand\";}s:18:\"CertificateBuilder\";a:8:{s:4:\"name\";s:18:\"CertificateBuilder\";s:5:\"alias\";s:18:\"certificatebuilder\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:74:\"Modules\\CertificateBuilder\\app\\Providers\\CertificateBuilderServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:64:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/CertificateBuilder\";}s:7:\"Company\";a:8:{s:4:\"name\";s:7:\"Company\";s:5:\"alias\";s:7:\"company\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:52:\"Modules\\Company\\app\\Providers\\CompanyServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:53:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Company\";}s:14:\"ContactMessage\";a:8:{s:4:\"name\";s:14:\"ContactMessage\";s:5:\"alias\";s:14:\"contactmessage\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:66:\"Modules\\ContactMessage\\app\\Providers\\ContactMessageServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:60:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/ContactMessage\";}s:6:\"Coupon\";a:8:{s:4:\"name\";s:6:\"Coupon\";s:5:\"alias\";s:6:\"coupon\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:50:\"Modules\\Coupon\\app\\Providers\\CouponServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:52:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Coupon\";}s:6:\"Course\";a:8:{s:4:\"name\";s:6:\"Course\";s:5:\"alias\";s:6:\"course\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:50:\"Modules\\Course\\app\\Providers\\CourseServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:52:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Course\";}s:13:\"CryptoPayment\";a:8:{s:4:\"name\";s:13:\"CryptoPayment\";s:5:\"alias\";s:13:\"cryptopayment\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:64:\"Modules\\CryptoPayment\\app\\Providers\\CryptoPaymentServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:59:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/CryptoPayment\";}s:8:\"Currency\";a:8:{s:4:\"name\";s:8:\"Currency\";s:5:\"alias\";s:8:\"currency\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Currency\\app\\Providers\\CurrencyServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:54:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Currency\";}s:8:\"Customer\";a:8:{s:4:\"name\";s:8:\"Customer\";s:5:\"alias\";s:8:\"customer\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Customer\\app\\Providers\\CustomerServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:54:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Customer\";}s:3:\"Faq\";a:8:{s:4:\"name\";s:3:\"Faq\";s:5:\"alias\";s:3:\"faq\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:44:\"Modules\\Faq\\app\\Providers\\FaqServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:49:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Faq\";}s:13:\"FooterSetting\";a:8:{s:4:\"name\";s:13:\"FooterSetting\";s:5:\"alias\";s:13:\"footersetting\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:64:\"Modules\\FooterSetting\\app\\Providers\\FooterSettingServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:59:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/FooterSetting\";}s:8:\"Frontend\";a:8:{s:4:\"name\";s:8:\"Frontend\";s:5:\"alias\";s:8:\"frontend\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Frontend\\app\\Providers\\FrontendServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:54:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Frontend\";}s:13:\"GlobalSetting\";a:8:{s:4:\"name\";s:13:\"GlobalSetting\";s:5:\"alias\";s:13:\"globalsetting\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:64:\"Modules\\GlobalSetting\\app\\Providers\\GlobalSettingServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:59:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/GlobalSetting\";}s:10:\"HrEmployee\";a:8:{s:4:\"name\";s:10:\"HrEmployee\";s:5:\"alias\";s:10:\"hremployee\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:58:\"Modules\\HrEmployee\\app\\Providers\\HrEmployeeServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:56:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/HrEmployee\";}s:17:\"InstructorRequest\";a:8:{s:4:\"name\";s:17:\"InstructorRequest\";s:5:\"alias\";s:17:\"instructorrequest\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:72:\"Modules\\InstructorRequest\\app\\Providers\\InstructorRequestServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:63:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/InstructorRequest\";}s:18:\"LandingPageMessage\";a:8:{s:4:\"name\";s:18:\"LandingPageMessage\";s:5:\"alias\";s:18:\"landingpagemessage\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:74:\"Modules\\LandingPageMessage\\app\\Providers\\LandingPageMessageServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:64:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/LandingPageMessage\";}s:8:\"Language\";a:8:{s:4:\"name\";s:8:\"Language\";s:5:\"alias\";s:8:\"language\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Language\\app\\Providers\\LanguageServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:54:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Language\";}s:5:\"Leave\";a:8:{s:4:\"name\";s:5:\"Leave\";s:5:\"alias\";s:5:\"leave\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:48:\"Modules\\Leave\\app\\Providers\\LeaveServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:51:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Leave\";}s:8:\"Location\";a:8:{s:4:\"name\";s:8:\"Location\";s:5:\"alias\";s:8:\"location\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:54:\"Modules\\Location\\app\\Providers\\LocationServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:54:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Location\";}s:7:\"Marquee\";a:8:{s:4:\"name\";s:7:\"Marquee\";s:5:\"alias\";s:7:\"marquee\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:52:\"Modules\\Marquee\\app\\Providers\\MarqueeServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:53:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Marquee\";}s:11:\"Menubuilder\";a:8:{s:4:\"name\";s:11:\"Menubuilder\";s:5:\"alias\";s:11:\"menubuilder\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:60:\"Modules\\Menubuilder\\app\\Providers\\MenubuilderServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:57:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Menubuilder\";}s:13:\"MercadoPagoPG\";a:8:{s:4:\"name\";s:13:\"MercadoPagoPG\";s:5:\"alias\";s:13:\"mercadopagopg\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:64:\"Modules\\MercadoPagoPG\\app\\Providers\\MercadoPagoPGServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:59:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/MercadoPagoPG\";}s:10:\"NewsLetter\";a:8:{s:4:\"name\";s:10:\"NewsLetter\";s:5:\"alias\";s:10:\"newsletter\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:58:\"Modules\\NewsLetter\\app\\Providers\\NewsLetterServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:56:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/NewsLetter\";}s:5:\"Order\";a:8:{s:4:\"name\";s:5:\"Order\";s:5:\"alias\";s:5:\"order\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:48:\"Modules\\Order\\app\\Providers\\OrderServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:51:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Order\";}s:11:\"PageBuilder\";a:8:{s:4:\"name\";s:11:\"PageBuilder\";s:5:\"alias\";s:11:\"pagebuilder\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:60:\"Modules\\PageBuilder\\app\\Providers\\PageBuilderServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:57:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/PageBuilder\";}s:19:\"PageTemplateBuilder\";a:8:{s:4:\"name\";s:19:\"PageTemplateBuilder\";s:5:\"alias\";s:19:\"pagetemplatebuilder\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:76:\"Modules\\PageTemplateBuilder\\app\\Providers\\PageTemplateBuilderServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:65:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/PageTemplateBuilder\";}s:15:\"PaymentWithdraw\";a:8:{s:4:\"name\";s:15:\"PaymentWithdraw\";s:5:\"alias\";s:15:\"paymentwithdraw\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:68:\"Modules\\PaymentWithdraw\\app\\Providers\\PaymentWithdrawServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:61:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/PaymentWithdraw\";}s:7:\"Payroll\";a:8:{s:4:\"name\";s:7:\"Payroll\";s:5:\"alias\";s:7:\"payroll\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:52:\"Modules\\Payroll\\app\\Providers\\PayrollServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:53:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Payroll\";}s:14:\"SiteAppearance\";a:8:{s:4:\"name\";s:14:\"SiteAppearance\";s:5:\"alias\";s:14:\"siteappearance\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:66:\"Modules\\SiteAppearance\\app\\Providers\\SiteAppearanceServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:60:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/SiteAppearance\";}s:10:\"SocialLink\";a:8:{s:4:\"name\";s:10:\"SocialLink\";s:5:\"alias\";s:10:\"sociallink\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:58:\"Modules\\SocialLink\\app\\Providers\\SocialLinkServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:56:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/SocialLink\";}s:12:\"Subscription\";a:8:{s:4:\"name\";s:12:\"Subscription\";s:5:\"alias\";s:12:\"subscription\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:62:\"Modules\\Subscription\\app\\Providers\\SubscriptionServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:58:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Subscription\";}s:11:\"Testimonial\";a:8:{s:4:\"name\";s:11:\"Testimonial\";s:5:\"alias\";s:11:\"testimonial\";s:11:\"description\";s:0:\"\";s:8:\"keywords\";a:0:{}s:8:\"priority\";i:0;s:9:\"providers\";a:1:{i:0;s:60:\"Modules\\Testimonial\\app\\Providers\\TestimonialServiceProvider\";}s:5:\"files\";a:0:{}s:4:\"path\";s:57:\"E:\\xampp 8.2\\htdocs\\laravel\\erpsystem\\Modules/Testimonial\";}}', 1788250578),
('mbsguru_cache_layout_counts:web:instructor:1278:2cf0e974', 'a:7:{s:22:\"totalCoachUpcomingLive\";i:0;s:18:\"totalCouachCourses\";i:0;s:16:\"totalCoachOrders\";i:0;s:18:\"totalCoachStudents\";i:2;s:17:\"landingPageDomain\";s:27:\"cockroach-coach.mbsguru.com\";s:24:\"totalStudentUpcomingLive\";i:0;s:13:\"sidebarBadges\";a:5:{s:19:\"announcement_drafts\";i:0;s:13:\"enquiries_new\";i:0;s:12:\"fees_overdue\";i:0;s:14:\"orders_pending\";i:0;s:14:\"batches_active\";i:0;}}', 1787060719),
('mbsguru_cache_marketing_setting', 'O:8:\"stdClass\":9:{s:8:\"register\";s:1:\"0\";s:14:\"course_details\";s:1:\"0\";s:11:\"add_to_cart\";s:1:\"0\";s:16:\"remove_from_cart\";s:1:\"1\";s:8:\"checkout\";s:1:\"1\";s:13:\"order_success\";s:1:\"0\";s:12:\"order_failed\";s:1:\"0\";s:12:\"contact_page\";s:1:\"0\";s:18:\"instructor_contact\";s:1:\"0\";}', 2102417635),
('mbsguru_cache_membership_has_active_plans_instructor', 'b:1;', 1787059958),
('mbsguru_cache_membership_plans_active_instructor', 'O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:4:{i:0;O:25:\"App\\Models\\MembershipPlan\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:16:\"membership_plans\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:21:{s:2:\"id\";i:18;s:4:\"name\";s:16:\"Coach Free Trial\";s:4:\"slug\";s:16:\"coach-free-trial\";s:4:\"tier\";s:6:\"custom\";s:4:\"role\";s:10:\"instructor\";s:5:\"price\";s:4:\"0.00\";s:12:\"annual_price\";N;s:9:\"setup_fee\";s:4:\"0.00\";s:16:\"setup_fee_custom\";i:0;s:24:\"platform_commission_rate\";N;s:19:\"commission_min_rate\";N;s:19:\"commission_max_rate\";N;s:16:\"student_capacity\";N;s:15:\"payout_required\";i:1;s:17:\"direct_settlement\";i:0;s:13:\"duration_days\";i:14;s:8:\"features\";N;s:6:\"status\";s:6:\"active\";s:10:\"sort_order\";i:0;s:10:\"created_at\";s:19:\"2026-06-25 22:08:15\";s:10:\"updated_at\";s:19:\"2026-06-25 22:08:15\";}s:11:\"\0*\0original\";a:21:{s:2:\"id\";i:18;s:4:\"name\";s:16:\"Coach Free Trial\";s:4:\"slug\";s:16:\"coach-free-trial\";s:4:\"tier\";s:6:\"custom\";s:4:\"role\";s:10:\"instructor\";s:5:\"price\";s:4:\"0.00\";s:12:\"annual_price\";N;s:9:\"setup_fee\";s:4:\"0.00\";s:16:\"setup_fee_custom\";i:0;s:24:\"platform_commission_rate\";N;s:19:\"commission_min_rate\";N;s:19:\"commission_max_rate\";N;s:16:\"student_capacity\";N;s:15:\"payout_required\";i:1;s:17:\"direct_settlement\";i:0;s:13:\"duration_days\";i:14;s:8:\"features\";N;s:6:\"status\";s:6:\"active\";s:10:\"sort_order\";i:0;s:10:\"created_at\";s:19:\"2026-06-25 22:08:15\";s:10:\"updated_at\";s:19:\"2026-06-25 22:08:15\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:13:{s:8:\"features\";s:5:\"array\";s:5:\"price\";s:9:\"decimal:2\";s:12:\"annual_price\";s:9:\"decimal:2\";s:13:\"duration_days\";s:7:\"integer\";s:10:\"sort_order\";s:7:\"integer\";s:9:\"setup_fee\";s:9:\"decimal:2\";s:16:\"setup_fee_custom\";s:7:\"boolean\";s:24:\"platform_commission_rate\";s:9:\"decimal:2\";s:19:\"commission_min_rate\";s:9:\"decimal:2\";s:19:\"commission_max_rate\";s:9:\"decimal:2\";s:16:\"student_capacity\";s:7:\"integer\";s:15:\"payout_required\";s:7:\"boolean\";s:17:\"direct_settlement\";s:7:\"boolean\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:0:{}}i:1;O:25:\"App\\Models\\MembershipPlan\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:16:\"membership_plans\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:21:{s:2:\"id\";i:14;s:4:\"name\";s:7:\"Starter\";s:4:\"slug\";s:7:\"starter\";s:4:\"tier\";s:7:\"starter\";s:4:\"role\";s:10:\"instructor\";s:5:\"price\";s:7:\"1499.00\";s:12:\"annual_price\";N;s:9:\"setup_fee\";s:7:\"9999.00\";s:16:\"setup_fee_custom\";i:0;s:24:\"platform_commission_rate\";s:4:\"7.00\";s:19:\"commission_min_rate\";N;s:19:\"commission_max_rate\";N;s:16:\"student_capacity\";i:500;s:15:\"payout_required\";i:1;s:17:\"direct_settlement\";i:0;s:13:\"duration_days\";i:30;s:8:\"features\";s:200:\"[\"Up to 500 students\",\"\\u20b91,499 \\/ month subscription\",\"\\u20b99,999 one-time setup fee\",\"7% platform commission\",\"Payout request based settlement\",\"Ideal for individual coaches & small institutes\"]\";s:6:\"status\";s:6:\"active\";s:10:\"sort_order\";i:1;s:10:\"created_at\";s:19:\"2026-06-24 23:31:50\";s:10:\"updated_at\";s:19:\"2026-06-24 23:31:50\";}s:11:\"\0*\0original\";a:21:{s:2:\"id\";i:14;s:4:\"name\";s:7:\"Starter\";s:4:\"slug\";s:7:\"starter\";s:4:\"tier\";s:7:\"starter\";s:4:\"role\";s:10:\"instructor\";s:5:\"price\";s:7:\"1499.00\";s:12:\"annual_price\";N;s:9:\"setup_fee\";s:7:\"9999.00\";s:16:\"setup_fee_custom\";i:0;s:24:\"platform_commission_rate\";s:4:\"7.00\";s:19:\"commission_min_rate\";N;s:19:\"commission_max_rate\";N;s:16:\"student_capacity\";i:500;s:15:\"payout_required\";i:1;s:17:\"direct_settlement\";i:0;s:13:\"duration_days\";i:30;s:8:\"features\";s:200:\"[\"Up to 500 students\",\"\\u20b91,499 \\/ month subscription\",\"\\u20b99,999 one-time setup fee\",\"7% platform commission\",\"Payout request based settlement\",\"Ideal for individual coaches & small institutes\"]\";s:6:\"status\";s:6:\"active\";s:10:\"sort_order\";i:1;s:10:\"created_at\";s:19:\"2026-06-24 23:31:50\";s:10:\"updated_at\";s:19:\"2026-06-24 23:31:50\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:13:{s:8:\"features\";s:5:\"array\";s:5:\"price\";s:9:\"decimal:2\";s:12:\"annual_price\";s:9:\"decimal:2\";s:13:\"duration_days\";s:7:\"integer\";s:10:\"sort_order\";s:7:\"integer\";s:9:\"setup_fee\";s:9:\"decimal:2\";s:16:\"setup_fee_custom\";s:7:\"boolean\";s:24:\"platform_commission_rate\";s:9:\"decimal:2\";s:19:\"commission_min_rate\";s:9:\"decimal:2\";s:19:\"commission_max_rate\";s:9:\"decimal:2\";s:16:\"student_capacity\";s:7:\"integer\";s:15:\"payout_required\";s:7:\"boolean\";s:17:\"direct_settlement\";s:7:\"boolean\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:0:{}}i:2;O:25:\"App\\Models\\MembershipPlan\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:16:\"membership_plans\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:21:{s:2:\"id\";i:15;s:4:\"name\";s:6:\"Medium\";s:4:\"slug\";s:6:\"medium\";s:4:\"tier\";s:6:\"medium\";s:4:\"role\";s:10:\"instructor\";s:5:\"price\";s:7:\"6999.00\";s:12:\"annual_price\";N;s:9:\"setup_fee\";s:8:\"29999.00\";s:16:\"setup_fee_custom\";i:0;s:24:\"platform_commission_rate\";s:4:\"3.00\";s:19:\"commission_min_rate\";N;s:19:\"commission_max_rate\";N;s:16:\"student_capacity\";i:5000;s:15:\"payout_required\";i:1;s:17:\"direct_settlement\";i:0;s:13:\"duration_days\";i:30;s:8:\"features\";s:192:\"[\"Up to 5,000 students\",\"\\u20b96,999 \\/ month subscription\",\"\\u20b929,999 one-time setup fee\",\"3% platform commission\",\"Payout request based settlement\",\"For growing institutes needing scale\"]\";s:6:\"status\";s:6:\"active\";s:10:\"sort_order\";i:2;s:10:\"created_at\";s:19:\"2026-06-24 23:31:50\";s:10:\"updated_at\";s:19:\"2026-07-07 18:59:10\";}s:11:\"\0*\0original\";a:21:{s:2:\"id\";i:15;s:4:\"name\";s:6:\"Medium\";s:4:\"slug\";s:6:\"medium\";s:4:\"tier\";s:6:\"medium\";s:4:\"role\";s:10:\"instructor\";s:5:\"price\";s:7:\"6999.00\";s:12:\"annual_price\";N;s:9:\"setup_fee\";s:8:\"29999.00\";s:16:\"setup_fee_custom\";i:0;s:24:\"platform_commission_rate\";s:4:\"3.00\";s:19:\"commission_min_rate\";N;s:19:\"commission_max_rate\";N;s:16:\"student_capacity\";i:5000;s:15:\"payout_required\";i:1;s:17:\"direct_settlement\";i:0;s:13:\"duration_days\";i:30;s:8:\"features\";s:192:\"[\"Up to 5,000 students\",\"\\u20b96,999 \\/ month subscription\",\"\\u20b929,999 one-time setup fee\",\"3% platform commission\",\"Payout request based settlement\",\"For growing institutes needing scale\"]\";s:6:\"status\";s:6:\"active\";s:10:\"sort_order\";i:2;s:10:\"created_at\";s:19:\"2026-06-24 23:31:50\";s:10:\"updated_at\";s:19:\"2026-07-07 18:59:10\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:13:{s:8:\"features\";s:5:\"array\";s:5:\"price\";s:9:\"decimal:2\";s:12:\"annual_price\";s:9:\"decimal:2\";s:13:\"duration_days\";s:7:\"integer\";s:10:\"sort_order\";s:7:\"integer\";s:9:\"setup_fee\";s:9:\"decimal:2\";s:16:\"setup_fee_custom\";s:7:\"boolean\";s:24:\"platform_commission_rate\";s:9:\"decimal:2\";s:19:\"commission_min_rate\";s:9:\"decimal:2\";s:19:\"commission_max_rate\";s:9:\"decimal:2\";s:16:\"student_capacity\";s:7:\"integer\";s:15:\"payout_required\";s:7:\"boolean\";s:17:\"direct_settlement\";s:7:\"boolean\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:0:{}}i:3;O:25:\"App\\Models\\MembershipPlan\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:16:\"membership_plans\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:21:{s:2:\"id\";i:16;s:4:\"name\";s:10:\"Enterprise\";s:4:\"slug\";s:10:\"enterprise\";s:4:\"tier\";s:10:\"enterprise\";s:4:\"role\";s:10:\"instructor\";s:5:\"price\";s:8:\"24999.00\";s:12:\"annual_price\";N;s:9:\"setup_fee\";s:4:\"0.00\";s:16:\"setup_fee_custom\";i:1;s:24:\"platform_commission_rate\";s:4:\"1.00\";s:19:\"commission_min_rate\";s:4:\"0.00\";s:19:\"commission_max_rate\";s:4:\"1.00\";s:16:\"student_capacity\";N;s:15:\"payout_required\";i:0;s:17:\"direct_settlement\";i:1;s:13:\"duration_days\";i:30;s:8:\"features\";s:172:\"[\"Unlimited students\",\"Custom one-time setup fee\",\"Platform fee\",\"Direct settlement to your bank \\u2014 no payout requests\",\"For large brands & multi-branch organizations\"]\";s:6:\"status\";s:6:\"active\";s:10:\"sort_order\";i:3;s:10:\"created_at\";s:19:\"2026-06-24 23:31:50\";s:10:\"updated_at\";s:19:\"2026-07-13 22:29:13\";}s:11:\"\0*\0original\";a:21:{s:2:\"id\";i:16;s:4:\"name\";s:10:\"Enterprise\";s:4:\"slug\";s:10:\"enterprise\";s:4:\"tier\";s:10:\"enterprise\";s:4:\"role\";s:10:\"instructor\";s:5:\"price\";s:8:\"24999.00\";s:12:\"annual_price\";N;s:9:\"setup_fee\";s:4:\"0.00\";s:16:\"setup_fee_custom\";i:1;s:24:\"platform_commission_rate\";s:4:\"1.00\";s:19:\"commission_min_rate\";s:4:\"0.00\";s:19:\"commission_max_rate\";s:4:\"1.00\";s:16:\"student_capacity\";N;s:15:\"payout_required\";i:0;s:17:\"direct_settlement\";i:1;s:13:\"duration_days\";i:30;s:8:\"features\";s:172:\"[\"Unlimited students\",\"Custom one-time setup fee\",\"Platform fee\",\"Direct settlement to your bank \\u2014 no payout requests\",\"For large brands & multi-branch organizations\"]\";s:6:\"status\";s:6:\"active\";s:10:\"sort_order\";i:3;s:10:\"created_at\";s:19:\"2026-06-24 23:31:50\";s:10:\"updated_at\";s:19:\"2026-07-13 22:29:13\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:13:{s:8:\"features\";s:5:\"array\";s:5:\"price\";s:9:\"decimal:2\";s:12:\"annual_price\";s:9:\"decimal:2\";s:13:\"duration_days\";s:7:\"integer\";s:10:\"sort_order\";s:7:\"integer\";s:9:\"setup_fee\";s:9:\"decimal:2\";s:16:\"setup_fee_custom\";s:7:\"boolean\";s:24:\"platform_commission_rate\";s:9:\"decimal:2\";s:19:\"commission_min_rate\";s:9:\"decimal:2\";s:19:\"commission_max_rate\";s:9:\"decimal:2\";s:16:\"student_capacity\";s:7:\"integer\";s:15:\"payout_required\";s:7:\"boolean\";s:17:\"direct_settlement\";s:7:\"boolean\";}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:0:{}}}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}', 1787063269),
('mbsguru_cache_mercadopagoConfig', 'O:8:\"stdClass\":6:{s:17:\"mercadopago_image\";s:39:\"uploads/website-images/mercado-pago.png\";s:18:\"mercadopago_status\";s:8:\"inactive\";s:19:\"mercadopago_sandbox\";s:1:\"1\";s:18:\"mercadopago_charge\";s:1:\"0\";s:10:\"public_key\";s:10:\"public_key\";s:12:\"access_token\";s:12:\"access_token\";}', 2102417635),
('mbsguru_cache_payment_setting', 'O:8:\"stdClass\":34:{s:12:\"razorpay_key\";s:23:\"rzp_test_SXntDNlryg32iz\";s:15:\"razorpay_secret\";s:24:\"3Vve1BVDQm5cqXmdqnEBqztZ\";s:13:\"razorpay_name\";s:7:\"MBSGuru\";s:20:\"razorpay_description\";s:27:\"This is test payment window\";s:15:\"razorpay_charge\";s:1:\"1\";s:20:\"razorpay_theme_color\";s:7:\"#0223db\";s:15:\"razorpay_status\";s:6:\"active\";s:20:\"razorpay_currency_id\";s:1:\"3\";s:14:\"razorpay_image\";s:36:\"uploads/website-images/razorpay.jpeg\";s:22:\"flutterwave_public_key\";s:37:\"flutterwave-test-348949439-public-key\";s:22:\"flutterwave_secret_key\";s:35:\"demo-flutterwave-8384934-key-secret\";s:20:\"flutterwave_app_name\";s:3:\"Web\";s:18:\"flutterwave_charge\";s:1:\"0\";s:23:\"flutterwave_currency_id\";s:1:\"2\";s:18:\"flutterwave_status\";s:8:\"inactive\";s:17:\"flutterwave_image\";s:38:\"uploads/website-images/flutterwave.jpg\";s:19:\"paystack_public_key\";s:34:\"paystack-test-348949439-public-key\";s:19:\"paystack_secret_key\";s:32:\"demo-paystack-8384934-key-secret\";s:15:\"paystack_status\";s:8:\"inactive\";s:15:\"paystack_charge\";s:1:\"0\";s:14:\"paystack_image\";s:35:\"uploads/website-images/paystack.png\";s:20:\"paystack_currency_id\";s:1:\"2\";s:10:\"mollie_key\";s:25:\"mollie-test-348949439-key\";s:13:\"mollie_charge\";s:1:\"0\";s:12:\"mollie_image\";s:33:\"uploads/website-images/mollie.png\";s:13:\"mollie_status\";s:8:\"inactive\";s:18:\"mollie_currency_id\";s:1:\"5\";s:22:\"instamojo_account_mode\";s:7:\"Sandbox\";s:17:\"instamojo_api_key\";s:17:\"instamojo_api_key\";s:20:\"instamojo_auth_token\";s:20:\"instamojo_auth_token\";s:16:\"instamojo_charge\";s:1:\"0\";s:15:\"instamojo_image\";s:36:\"uploads/website-images/instamojo.png\";s:21:\"instamojo_currency_id\";s:1:\"3\";s:16:\"instamojo_status\";s:8:\"inactive\";}', 2102417640),
('mbsguru_cache_seo_setting', 'O:39:\"Illuminate\\Database\\Eloquent\\Collection\":2:{s:8:\"\0*\0items\";a:7:{s:9:\"home_page\";O:43:\"Modules\\GlobalSetting\\app\\Models\\SeoSetting\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:12:\"seo_settings\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:6:{s:2:\"id\";i:1;s:9:\"page_name\";s:9:\"home_page\";s:9:\"seo_title\";s:16:\"Home || MBS GURU\";s:15:\"seo_description\";s:16:\"Home || MBS GURU\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2026-03-10 11:51:17\";}s:11:\"\0*\0original\";a:6:{s:2:\"id\";i:1;s:9:\"page_name\";s:9:\"home_page\";s:9:\"seo_title\";s:16:\"Home || MBS GURU\";s:15:\"seo_description\";s:16:\"Home || MBS GURU\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2026-03-10 11:51:17\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}s:10:\"about_page\";O:43:\"Modules\\GlobalSetting\\app\\Models\\SeoSetting\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:12:\"seo_settings\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:6:{s:2:\"id\";i:2;s:9:\"page_name\";s:10:\"about_page\";s:9:\"seo_title\";s:17:\"About || MBS GURU\";s:15:\"seo_description\";s:17:\"About || MBS GURU\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2026-03-10 11:51:24\";}s:11:\"\0*\0original\";a:6:{s:2:\"id\";i:2;s:9:\"page_name\";s:10:\"about_page\";s:9:\"seo_title\";s:17:\"About || MBS GURU\";s:15:\"seo_description\";s:17:\"About || MBS GURU\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2026-03-10 11:51:24\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}s:11:\"course_page\";O:43:\"Modules\\GlobalSetting\\app\\Models\\SeoSetting\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:12:\"seo_settings\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:6:{s:2:\"id\";i:3;s:9:\"page_name\";s:11:\"course_page\";s:9:\"seo_title\";s:18:\"Course || MBS GURU\";s:15:\"seo_description\";s:18:\"Course || MBS GURU\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2026-03-10 11:51:31\";}s:11:\"\0*\0original\";a:6:{s:2:\"id\";i:3;s:9:\"page_name\";s:11:\"course_page\";s:9:\"seo_title\";s:18:\"Course || MBS GURU\";s:15:\"seo_description\";s:18:\"Course || MBS GURU\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2026-03-10 11:51:31\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}s:9:\"blog_page\";O:43:\"Modules\\GlobalSetting\\app\\Models\\SeoSetting\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:12:\"seo_settings\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:6:{s:2:\"id\";i:4;s:9:\"page_name\";s:9:\"blog_page\";s:9:\"seo_title\";s:16:\"Blog || MBS GURU\";s:15:\"seo_description\";s:16:\"Blog || MBS GURU\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2026-03-10 11:51:37\";}s:11:\"\0*\0original\";a:6:{s:2:\"id\";i:4;s:9:\"page_name\";s:9:\"blog_page\";s:9:\"seo_title\";s:16:\"Blog || MBS GURU\";s:15:\"seo_description\";s:16:\"Blog || MBS GURU\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2026-03-10 11:51:37\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}s:12:\"contact_page\";O:43:\"Modules\\GlobalSetting\\app\\Models\\SeoSetting\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:12:\"seo_settings\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:6:{s:2:\"id\";i:5;s:9:\"page_name\";s:12:\"contact_page\";s:9:\"seo_title\";s:19:\"Contact || MBS GURU\";s:15:\"seo_description\";s:19:\"Contact || MBS GURU\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2026-03-10 11:51:46\";}s:11:\"\0*\0original\";a:6:{s:2:\"id\";i:5;s:9:\"page_name\";s:12:\"contact_page\";s:9:\"seo_title\";s:19:\"Contact || MBS GURU\";s:15:\"seo_description\";s:19:\"Contact || MBS GURU\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2026-03-10 11:51:46\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}s:20:\"terms_and_conditions\";O:43:\"Modules\\GlobalSetting\\app\\Models\\SeoSetting\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:12:\"seo_settings\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:6:{s:2:\"id\";i:6;s:9:\"page_name\";s:20:\"terms_and_conditions\";s:9:\"seo_title\";s:28:\"Terms and Conditions || Yoga\";s:15:\"seo_description\";s:28:\"Terms and Conditions || Yoga\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2025-07-11 12:46:02\";}s:11:\"\0*\0original\";a:6:{s:2:\"id\";i:6;s:9:\"page_name\";s:20:\"terms_and_conditions\";s:9:\"seo_title\";s:28:\"Terms and Conditions || Yoga\";s:15:\"seo_description\";s:28:\"Terms and Conditions || Yoga\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2025-07-11 12:46:02\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}s:14:\"privacy_policy\";O:43:\"Modules\\GlobalSetting\\app\\Models\\SeoSetting\":30:{s:13:\"\0*\0connection\";s:5:\"mysql\";s:8:\"\0*\0table\";s:12:\"seo_settings\";s:13:\"\0*\0primaryKey\";s:2:\"id\";s:10:\"\0*\0keyType\";s:3:\"int\";s:12:\"incrementing\";b:1;s:7:\"\0*\0with\";a:0:{}s:12:\"\0*\0withCount\";a:0:{}s:19:\"preventsLazyLoading\";b:0;s:10:\"\0*\0perPage\";i:15;s:6:\"exists\";b:1;s:18:\"wasRecentlyCreated\";b:0;s:28:\"\0*\0escapeWhenCastingToString\";b:0;s:13:\"\0*\0attributes\";a:6:{s:2:\"id\";i:7;s:9:\"page_name\";s:14:\"privacy_policy\";s:9:\"seo_title\";s:26:\"Privacy Policy || MBS GURU\";s:15:\"seo_description\";s:26:\"Privacy Policy || MBS GURU\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2026-03-10 11:51:52\";}s:11:\"\0*\0original\";a:6:{s:2:\"id\";i:7;s:9:\"page_name\";s:14:\"privacy_policy\";s:9:\"seo_title\";s:26:\"Privacy Policy || MBS GURU\";s:15:\"seo_description\";s:26:\"Privacy Policy || MBS GURU\";s:10:\"created_at\";s:19:\"2025-06-30 16:02:42\";s:10:\"updated_at\";s:19:\"2026-03-10 11:51:52\";}s:10:\"\0*\0changes\";a:0:{}s:8:\"\0*\0casts\";a:0:{}s:17:\"\0*\0classCastCache\";a:0:{}s:21:\"\0*\0attributeCastCache\";a:0:{}s:13:\"\0*\0dateFormat\";N;s:10:\"\0*\0appends\";a:0:{}s:19:\"\0*\0dispatchesEvents\";a:0:{}s:14:\"\0*\0observables\";a:0:{}s:12:\"\0*\0relations\";a:0:{}s:10:\"\0*\0touches\";a:0:{}s:10:\"timestamps\";b:1;s:13:\"usesUniqueIds\";b:0;s:9:\"\0*\0hidden\";a:0:{}s:10:\"\0*\0visible\";a:0:{}s:11:\"\0*\0fillable\";a:0:{}s:10:\"\0*\0guarded\";a:1:{i:0;s:1:\"*\";}}}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}', 2102417635),
('mbsguru_cache_setting', 'O:8:\"stdClass\":103:{s:8:\"app_name\";s:7:\"MBSGuru\";s:7:\"version\";s:5:\"2.3.0\";s:4:\"logo\";s:59:\"uploads/custom-images/wsus-img-2026-04-03-04-09-42-7557.png\";s:8:\"timezone\";s:12:\"Asia/Kolkata\";s:7:\"favicon\";s:59:\"uploads/custom-images/wsus-img-2026-04-17-01-51-02-4244.png\";s:13:\"cookie_status\";s:6:\"active\";s:6:\"border\";s:6:\"normal\";s:7:\"corners\";s:4:\"thin\";s:16:\"background_color\";s:7:\"#184dec\";s:10:\"text_color\";s:7:\"#fafafa\";s:12:\"border_color\";s:7:\"#0a58d6\";s:12:\"btn_bg_color\";s:7:\"#fffceb\";s:14:\"btn_text_color\";s:7:\"#222758\";s:9:\"link_text\";s:9:\"More Info\";s:4:\"link\";s:20:\"/page/privacy-policy\";s:8:\"btn_text\";s:3:\"Yes\";s:7:\"message\";s:170:\"This website uses essential cookies to ensure its proper operation and tracking cookies to understand how you interact with it. The latter will be set only upon approval.\";s:14:\"copyright_text\";s:59:\"2026 All Rights Reserved. Developed By Digital Hub Solution\";s:18:\"recaptcha_site_key\";s:40:\"6Ld9G1gtAAAAAKvTj0JZnFPyeISgBpaiPvXUBf3y\";s:20:\"recaptcha_secret_key\";N;s:16:\"recaptcha_status\";s:8:\"inactive\";s:11:\"tawk_status\";s:8:\"inactive\";s:14:\"tawk_chat_link\";s:14:\"tawk_chat_link\";s:24:\"google_tagmanager_status\";s:6:\"active\";s:20:\"google_tagmanager_id\";s:20:\"google_tagmanager_id\";s:12:\"pixel_status\";s:6:\"active\";s:12:\"pixel_app_id\";s:12:\"pixel_app_id\";s:21:\"facebook_login_status\";s:8:\"inactive\";s:15:\"facebook_app_id\";s:15:\"facebook_app_id\";s:19:\"facebook_app_secret\";s:19:\"facebook_app_secret\";s:21:\"facebook_redirect_url\";s:21:\"facebook_redirect_url\";s:19:\"google_login_status\";s:6:\"active\";s:15:\"gmail_client_id\";s:15:\"gmail_client_id\";s:15:\"gmail_secret_id\";s:15:\"gmail_secret_id\";s:18:\"gmail_redirect_url\";s:0:\"\";s:14:\"default_avatar\";s:41:\"uploads/website-images/default-avatar.png\";s:16:\"breadcrumb_image\";s:43:\"uploads/website-images/breadcrumb-image.jpg\";s:9:\"mail_host\";s:16:\"mail.mbsguru.com\";s:17:\"mail_sender_email\";s:19:\"noreply@mbsguru.com\";s:13:\"mail_username\";s:19:\"noreply@mbsguru.com\";s:13:\"mail_password\";s:16:\"N-*5W;r]us49~Q$b\";s:9:\"mail_port\";s:3:\"465\";s:15:\"mail_encryption\";s:3:\"ssl\";s:16:\"mail_sender_name\";s:24:\"MBS Guru Private Limited\";s:29:\"contact_message_receiver_mail\";s:21:\"sureshs.dhs@gmail.com\";s:13:\"pusher_app_id\";s:13:\"pusher_app_id\";s:14:\"pusher_app_key\";s:14:\"pusher_app_key\";s:17:\"pusher_app_secret\";s:17:\"pusher_app_secret\";s:18:\"pusher_app_cluster\";s:18:\"pusher_app_cluster\";s:13:\"pusher_status\";s:8:\"inactive\";s:15:\"club_point_rate\";s:1:\"1\";s:17:\"club_point_status\";s:6:\"active\";s:16:\"maintenance_mode\";s:1:\"0\";s:17:\"maintenance_title\";s:25:\"Website Under maintenance\";s:23:\"maintenance_description\";s:59:\"<p>Working on more modules coming soon with AI and Boot</p>\";s:16:\"last_update_date\";s:19:\"2024-08-15 03:23:17\";s:10:\"is_queable\";s:8:\"inactive\";s:15:\"commission_rate\";s:1:\"2\";s:12:\"site_address\";s:57:\"D-247/4A, D Block, Sector 63, Noida, Uttar Pradesh 201301\";s:10:\"site_email\";s:16:\"info@mbsguru.com\";s:10:\"site_theme\";s:4:\"main\";s:9:\"preloader\";s:59:\"uploads/custom-images/wsus-img-2026-04-03-04-09-27-4891.png\";s:13:\"primary_color\";s:7:\"#5751e1\";s:15:\"secondary_color\";s:7:\"#ffc224\";s:16:\"common_color_one\";s:7:\"#050071\";s:16:\"common_color_two\";s:7:\"#282568\";s:18:\"common_color_three\";s:7:\"#1C1A4A\";s:17:\"common_color_four\";s:7:\"#06042E\";s:17:\"common_color_five\";s:7:\"#4a44d1\";s:17:\"show_all_homepage\";s:1:\"1\";s:22:\"google_analytic_status\";s:8:\"inactive\";s:18:\"google_analytic_id\";s:18:\"google_analytic_id\";s:16:\"preloader_status\";s:1:\"0\";s:17:\"maintenance_image\";s:59:\"uploads/custom-images/wsus-img-2025-05-01-01-49-36-4009.png\";s:14:\"live_mail_send\";s:1:\"5\";s:16:\"wasabi_access_id\";s:16:\"wasabi_access_id\";s:17:\"wasabi_secret_key\";s:17:\"wasabi_secret_key\";s:13:\"wasabi_bucket\";s:13:\"wasabi_bucket\";s:13:\"wasabi_region\";s:9:\"us-east-1\";s:13:\"wasabi_status\";s:6:\"active\";s:13:\"aws_access_id\";s:13:\"aws_access_id\";s:14:\"aws_secret_key\";s:14:\"aws_secret_key\";s:10:\"aws_bucket\";s:10:\"aws_bucket\";s:10:\"aws_region\";s:9:\"us-east-1\";s:10:\"aws_status\";s:8:\"inactive\";s:20:\"header_topbar_status\";s:8:\"inactive\";s:17:\"cursor_dot_status\";s:8:\"inactive\";s:20:\"header_social_status\";s:8:\"inactive\";s:13:\"watermark_img\";s:59:\"uploads/custom-images/wsus-img-2026-04-03-04-18-23-1911.png\";s:8:\"position\";s:8:\"top_left\";s:7:\"opacity\";s:3:\"0.7\";s:9:\"max_width\";s:3:\"300\";s:16:\"watermark_status\";s:6:\"active\";s:18:\"years_of_exprience\";s:1:\"6\";s:17:\"satisfied_clients\";s:3:\"200\";s:17:\"countries_reached\";s:2:\"15\";s:17:\"classes_conducted\";s:1:\"2\";s:27:\"referral_commission_percent\";s:2:\"10\";s:22:\"attendance_min_percent\";s:2:\"50\";s:21:\"custom_domain_enabled\";s:1:\"1\";s:23:\"custom_domain_server_ip\";s:14:\"149.248.18.191\";s:31:\"custom_domain_requires_approval\";s:1:\"0\";s:27:\"custom_domain_max_per_coach\";s:1:\"1\";}', 2102417635);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `state_id` bigint(20) UNSIGNED NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL COMMENT 'URL/reference handle, not a subdomain',
  `code` varchar(60) DEFAULT NULL COMMENT 'internal reference code',
  `owner_user_id` bigint(20) UNSIGNED NOT NULL COMMENT 'users.id of the HR who registered it',
  `industry` varchar(255) DEFAULT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Kolkata',
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active|suspended',
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `pf_number` varchar(60) DEFAULT NULL COMMENT 'establishment PF code',
  `esi_number` varchar(60) DEFAULT NULL COMMENT 'establishment ESI code',
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `name`, `slug`, `code`, `owner_user_id`, `industry`, `timezone`, `status`, `address`, `city`, `state`, `country`, `postal_code`, `logo_path`, `pf_number`, `esi_number`, `phone`, `email`, `created_at`, `updated_at`) VALUES
(1, 'Cockroach Coach\'s Company', 'cockroach-coachs-company', NULL, 1278, 'IT', 'Asia/Kolkata', 'active', 'E-47/6,OKHLA IND. AREA, PH-II,NEW DELHI-110020', 'New Delhi', 'New Delhi', 'India', '110022', 'uploads/company-logos/ZrMqNKbDCSw9Q8fW7xVKEV3rzSxwjG1CKbiaG51y.png', 'DSNHP0024001000', '20000601130000108', '8876543210', 'hr@cockroachcoach.com', '2026-08-18 12:49:19', '2026-08-31 08:19:56'),
(13, 'Randall Austin', 'randall-austin', NULL, 1278, 'Ea dolor dolore moll', 'Earum aut dolor eius', 'active', 'Voluptatum repudiand', 'Impedit quisquam et', 'Ut ut esse dolor nul', 'Sapiente repellendus', NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-18 13:54:17', '2026-08-18 13:54:17');

-- --------------------------------------------------------

--
-- Table structure for table `company_user`
--

CREATE TABLE `company_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'owner' COMMENT 'owner|hr_staff (only owner used in phase 1)',
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active|suspended',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_user`
--

INSERT INTO `company_user` (`id`, `company_id`, `user_id`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1278, 'owner', 'active', '2026-08-18 12:49:19', '2026-08-18 12:49:19'),
(13, 13, 1278, 'owner', 'active', '2026-08-18 13:54:17', '2026-08-18 13:54:17');

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'India', 1, '2025-04-30 11:16:05', '2025-04-30 11:16:05'),
(2, 'United State', 1, '2025-04-30 11:16:14', '2025-04-30 11:16:14');

-- --------------------------------------------------------

--
-- Table structure for table `custom_codes`
--

CREATE TABLE `custom_codes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `css` text DEFAULT NULL,
  `javascript` text DEFAULT NULL,
  `header_javascript` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `custom_paginations`
--

CREATE TABLE `custom_paginations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `section_name` varchar(255) NOT NULL,
  `item_qty` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `custom_paginations`
--

INSERT INTO `custom_paginations` (`id`, `section_name`, `item_qty`, `created_at`, `updated_at`) VALUES
(1, 'Blog List', 10, '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(2, 'Blog Comment', 10, '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(3, 'Media List', 10, '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(4, 'Language List', 50, '2024-08-14 21:23:17', '2024-08-14 21:23:17');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `head_user_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'HR/manager users.id',
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'self ref for sub-teams',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `company_id`, `name`, `code`, `head_user_id`, `parent_id`, `is_active`, `created_at`, `updated_at`) VALUES
(2, 1, 'Cutting', 'CUT', 1278, NULL, 1, '2026-07-30 12:13:26', '2026-07-30 12:13:26'),
(3, 1, 'Washer', 'WA', 1278, NULL, 1, '2026-07-30 12:14:14', '2026-07-30 12:14:14'),
(4, 1, 'Sylvester Sherman', 'Quas elit doloribus', NULL, NULL, 1, '2026-07-31 13:46:22', '2026-07-31 13:46:22'),
(16, 13, 'General', 'GEN', NULL, NULL, 1, '2026-08-18 13:54:17', '2026-08-18 13:54:17');

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `name`, `subject`, `message`, `created_at`, `updated_at`) VALUES
(1, 'password_reset', 'Password Reset', '<p>Dear {{user_name}},</p>\n                <p>We received a request to reset your password. Click the button below to choose a new one.</p>\n                <p style=\"color:#6b7280;font-size:13px;\">This link will expire in 1 hour. If you did not request a password reset, you can safely ignore this email.</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(2, 'contact_mail', 'Contact Email', '<p>Hello there,</p>\r\n<p>&nbsp;{{name}} has sent a new message. you can see the message details below.&nbsp;</p>\r\n<p>Email: {{email}}</p>\r\n<p>Phone: {{phone}}</p>\r\n<p>Message: {{message}}</p>', '2024-06-03 02:02:30', '2025-07-01 08:08:21'),
(3, 'subscribe_notification', 'Subscribe Notification', '<p>Hi there, Congratulations! Your Subscription has been created successfully. Please Click the following link and Verified Your Subscription. If you will not approve this link, you can not get any newsletter from us.</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(4, 'user_verification', 'User Verification', '<p>Dear {{user_name}},</p>\n                <p>Welcome! Your account has been created successfully. Please click the button below to activate your account.</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(5, 'approved_refund', 'Refund Request Approval', '<p>Dear {{user_name}},</p>\n                <p>We are happy to say that, we have sent {{refund_amount}} to your provided bank information. </p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(6, 'new_refund', 'New Refund Request', '<p>Hello admin,</p>\n                <p>{{user_name}} has submitted a new refund request. Please review it in the admin panel.</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(7, 'pending_wallet_payment', 'Wallet Payment Approval', '<p>Hello {{user_name}},</p>\n                <p>We have received your wallet payment request and will verify it against our bank account shortly.</p>\n                <p>Thanks &amp; regards</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(8, 'approved_withdraw', 'Withdraw Request Approval', '<p>Dear {{user_name}},</p>\n                <p>We are happy to say that, we have send a withdraw amount to your provided bank information.</p>\n                <p>Thanks &amp; Regards</p>\n                <p>WebSolutionUs</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(9, 'rejected_withdraw', 'Withdraw Request Rejected', '<p>Dear {{user_name}},</p>\n                <p> your withdraw request has been rejected.</p>\n                <p>Thanks &amp; Regards</p>\n                <p>WebSolutionUs</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(10, 'pending_withdraw', 'Withdraw Request Pending', '<p>Dear {{user_name}},</p>\n                <p> your withdraw request is waiting for approval.</p>\n                <p>Thanks &amp; Regards</p>\n                <p>WebSolutionUs</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(11, 'instructor_request_approved', 'Instructor Request Approval', '<p>Dear {{user_name}},</p>\n                <p>you are now approved as an instructor.</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(12, 'instructor_request_rejected', 'Instructor Request Rejected', '<p>Dear {{user_name}},</p>\n                <p>your request has been rejected. please resubmit your request with proper document. or contact us.</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(13, 'instructor_request_pending', 'Instructor Request is waiting for approval', '<p>Dear {{user_name}},</p>\n                <p>your request for become an instructor is waiting for approval. please wait. we will send you an email when your request is approved.</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(14, 'instructor_quick_contact', 'Mail for instructor contact form', '<p>Name: {{name}}</p>\n                <p>Email: {{email}}</p>\n                <p>Subject: {{subject}}</p>\n                <p>{{message}}</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(15, 'order_completed', 'Your order has been placed', '<p>Hi {{name}},</p>\n                <p>Thank you for your purchase! Your order has been placed successfully.</p>\n                <p><strong>Invoice ID:</strong> {{order_id}}</p>\n                <p><strong>Amount paid:</strong> {{paid_amount}}</p>\n                <p><strong>Payment method:</strong> {{payment_method}}</p>', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(16, 'qna_reply_mail', 'QNA Replay mail', '<p>Hi {{user_name}}, your instructor has replied to your question. Please see the answer below:</p>\r\n<p>Course: {{course}}</p>\r\n<p>Lesson: {{lesson}}</p>\r\n<p>Question: {{question}}</p>', '2024-08-25 03:36:19', '2024-08-25 03:36:19'),
(17, 'live_class_mail', 'Live class notification mail', '<p>Hi {{user_name}},</p>\r\n                <p>Your live class is starting at {{start_time}}. Please see the details below:</p>\r\n                <p><strong>Course:</strong> {{course}}</p>\r\n                <p><strong>Lesson:</strong> {{lesson}}</p>\r\n                <p><strong>Meeting Link:</strong> <a href=\"{{join_url}}\">{{join_url}}</a></p>', '2024-09-03 13:01:51', '2024-09-03 13:01:51'),
(18, 'payment_status', 'Update Payment Status', '<p>Hi {{name}},</p>\n                <p>Here is an update on your order.</p>\n                <p><strong>Invoice ID:</strong> {{order_id}}</p>\n                <p><strong>Amount paid:</strong> {{paid_amount}}</p>\n                <p><strong>Payment status:</strong> {{payment_status}}</p>', '2024-06-02 20:02:30', '2024-06-02 20:02:30'),
(19, 'gift_course', 'Gift Course Notification', '<p>Hi {{name}},</p><p>{{sender_name}} has gifted you a course! Click the link below to enroll and claim your course. <strong>Do not share this link with anyone.</strong></p><p><strong>Claim Course:</strong> <a href=\"{{link}}\">{{link}}</a></p><p><strong>Visit Course:</strong> <a href=\"{{course_link}}\">{{course_name}}</a></p><p><strong>Sender Email:</strong> {{sender_email}}</p><p><strong>Message from Sender:</strong> {{message}}</p><p>Enjoy your learning!</p>', '2024-06-02 20:02:30', '2024-06-02 20:02:30'),
(20, 'landing_mail', 'Landing Email', '<p>Hello there,</p>\r\n<p>&nbsp;Mr./Mrs. {{name}} has sent a new message. You can see the message details below.&nbsp;</p>\r\n<p>Email: {{email}}</p>\r\n<p>Phone: {{phone}}</p>\r\n<p>Service: {{service}}</p>\r\n<p>Message: {{message}}</p>', '2024-06-03 02:02:30', '2025-07-01 08:08:21'),
(21, 'notif_course_approved', 'Your course \"{{course_title}}\" has been approved', '<p>Hi {{user_name}},</p>\n<p>Great news — your course <strong>{{course_title}}</strong> has been approved by our team and is now live and visible to students.</p>\n<p>You can view it from your dashboard.</p>\n<p>Thanks for teaching with us!</p>', '2026-05-04 08:47:58', '2026-05-04 08:47:58'),
(22, 'notif_course_rejected', 'Update on your course \"{{course_title}}\"', '<p>Hi {{user_name}},</p>\n<p>Your course <strong>{{course_title}}</strong> has been marked as <strong>{{status}}</strong> by our review team.</p>\n<p>Please open the course from your dashboard to see the feedback and resubmit when ready.</p>', '2026-05-04 08:47:58', '2026-05-04 08:47:58'),
(23, 'notif_new_enrollment', 'New student enrolled in {{course_title}}', '<p>Hi {{coach_name}},</p>\n<p><strong>{{student_name}}</strong> just enrolled in your course <strong>{{course_title}}</strong>.</p>\n<p>You can see your full sales list from your coach dashboard.</p>', '2026-05-04 08:47:58', '2026-05-04 08:47:58'),
(24, 'notif_course_completed', 'Course completed: {{course_title}}', '<p>Hi {{user_name}},</p>\n<p>Congratulations on finishing <strong>{{course_title}}</strong>! 🎉</p>\n<p>Your certificate is ready to download from your enrolled courses page.</p>', '2026-05-04 08:47:58', '2026-05-04 08:47:58'),
(25, 'notif_quiz_result', '{{quiz_title}} — your result', '<p>Hi {{user_name}},</p>\n<p>Your attempt on <strong>{{quiz_title}}</strong> has been graded.</p>\n<p><strong>Score:</strong> {{score}} / {{total}}<br>\n<strong>Status:</strong> {{status}}</p>\n<p>Open the result page for the full breakdown.</p>', '2026-05-04 08:47:58', '2026-05-04 08:47:58'),
(26, 'notif_live_class_scheduled', 'New Live Class Scheduled: {{lesson_title}}', '<p>Dear {{user_name}},</p>\n<p>A new live class has been scheduled for your enrolled batch.</p>\n<p><strong>Course:</strong> {{course_title}}<br>\n<strong>Batch:</strong> {{batch_name}}<br>\n<strong>Live Class:</strong> {{lesson_title}}<br>\n<strong>Date &amp; Time:</strong> {{start_time}}<br>\n<strong>Coach:</strong> {{coach_name}}</p>\n<p>Please join the class on time from your student panel using the button below.</p>', '2026-05-04 08:47:58', '2026-06-05 18:58:36'),
(27, 'notif_live_class_starting_soon', 'Live class in {{minutes}} min — {{class_title}}', '<p>Hi {{user_name}},</p>\n\n<p>Your live class starts in <strong>{{minutes}} minutes</strong>. Here are the details:</p>\n\n<p><strong>Live class:</strong> {{class_title}}<br>\n<strong>Course:</strong> {{course_title}}<br>\n<strong>Batch:</strong> {{batch_name}}<br>\n<strong>Date &amp; time:</strong> {{starts_at}}<br>\n<strong>Instructor:</strong> {{coach_name}}</p>\n\n<p>Click the button below to join the class on time.</p>', '2026-05-04 08:47:58', '2026-06-26 15:18:39'),
(28, 'notif_refund_approved', 'Refund approved — order #{{order_id}}', '<p>Hi {{user_name}},</p>\n<p>Your refund of <strong>{{refund_amount}}</strong> for order <strong>#{{order_id}}</strong> has been approved and is now being processed.</p>\n<p>You should see the funds back in your account within a few business days.</p>', '2026-05-04 08:47:58', '2026-05-04 08:47:58'),
(29, 'notif_refund_rejected', 'Refund request not approved — order #{{order_id}}', '<p>Hi {{user_name}},</p>\n<p>We were unable to approve your refund request for order <strong>#{{order_id}}</strong>.</p>\n<p><strong>Reason:</strong> {{reason}}</p>\n<p>{{description}}</p>\n<p>If you have questions please reply to this email.</p>', '2026-05-04 08:47:58', '2026-05-04 08:47:58'),
(30, 'notif_new_refund_request', 'New refund request from {{student_name}}', '<p>Hi Admin,</p>\n<p><strong>{{student_name}}</strong> has submitted a new refund request for order <strong>#{{order_id}}</strong>.</p>\n<p>Open the request from your admin panel to review and approve or reject.</p>', '2026-05-04 08:47:58', '2026-05-04 08:47:58'),
(31, 'notif_withdrawal_approved', 'Payout approved — {{amount}}', '<p>Hi {{user_name}},</p>\n<p>Your payout of <strong>{{amount}}</strong> has been approved and is on its way to your bank.</p>\n<p>You can review your payout history from the payouts page.</p>', '2026-05-04 08:47:58', '2026-05-04 08:47:58'),
(32, 'notif_withdrawal_rejected', 'Payout request not approved — {{amount}}', '<p>Hi {{user_name}},</p>\n<p>Your payout request of <strong>{{amount}}</strong> has been rejected.</p>\n<p>Open the payouts page for more information or contact support.</p>', '2026-05-04 08:47:58', '2026-05-04 08:47:58'),
(33, 'notif_new_withdrawal_request', 'New payout request from {{coach_name}}', '<p>Hi Admin,</p>\n<p><strong>{{coach_name}}</strong> has requested a payout of <strong>{{amount}}</strong>.</p>\n<p>Open the withdraw list from your admin panel to review and process.</p>', '2026-05-04 08:47:58', '2026-05-04 08:47:58'),
(34, 'notif_payment_due', 'Reminder — order #{{order_id}} payment pending', '<p>Hi {{user_name}},</p>\n<p>Your order <strong>#{{order_id}}</strong> has been waiting for payment for <strong>{{days_open}} day(s)</strong>.</p>\n<p>Complete the payment to activate your enrollment and start learning.</p>', '2026-05-04 08:47:58', '2026-05-04 08:47:58'),
(35, 'notif_referral_reward', 'You earned a referral reward — {{reward_amount}}', '<p>Hi {{user_name}},</p>\n<p>Great news! <strong>{{referred_name}}</strong> just activated their membership using your referral code.</p>\n<p>You have earned <strong>{{reward_amount}}</strong> in your referral wallet — apply it to your next membership purchase or renewal at checkout.</p>\n<p>Keep sharing your link to earn more rewards.</p>', '2026-05-04 10:25:48', '2026-05-04 10:25:48'),
(36, 'notif_membership_activated', 'Your {{plan_name}} is now active', '<p>Hi {{user_name}},</p>\n<p>Welcome aboard! Your <strong>{{plan_name}}</strong> membership is now active.</p>\n<p><strong>Active until:</strong> {{expires_at}}</p>\n<p><strong>Cash paid:</strong> {{cash_paid}}<br>\n<strong>Wallet credit used:</strong> {{wallet_used}}</p>\n<p>You can manage your membership anytime from the membership page in your dashboard.</p>', '2026-05-04 10:34:37', '2026-05-04 10:34:37'),
(37, 'notif_trial_expiring', 'Your free trial ends in {{days_left}} day(s)', '<p>Hi {{user_name}},</p>\n<p>Your <strong>Coach Free Trial</strong> ends on <strong>{{expires_at}}</strong> ({{days_left}} day(s) from now).</p>\n<p>To keep creating courses, scheduling live classes, and using the rest of the coach toolkit, pick a plan from your membership page.</p>\n<p>If you applied a referral code at signup, you may have wallet credit you can use at checkout.</p>', '2026-05-04 11:16:34', '2026-05-04 11:20:43'),
(38, 'notif_coach_trial_welcome', 'Welcome to coaching — your {{trial_days}}-day free trial is live', '<p>Hi {{user_name}},</p>\n<p>Your <strong>{{trial_days}}-day Coach Free Trial</strong> is now active. You have full access to:</p>\n<ul>\n  <li>Course creation + chapter management</li>\n  <li>Live class scheduling (Zoom / Jitsi / YouTube)</li>\n  <li>Coach analytics + sales tracking</li>\n  <li>Coach-staff invites + permissions</li>\n</ul>\n<p>Your trial expires on <strong>{{expires_at}}</strong>. We will remind you 3 days before so nothing surprises you.</p>\n<p>Tip: share your referral link with friends — when they activate a paid plan you earn wallet credit you can apply at your own checkout.</p>', '2026-05-04 11:20:09', '2026-05-04 11:20:09'),
(39, 'notif_batch_announcement', 'New announcement — {{course_title}}', '<p>Hi {{user_name}},</p>\n\n<p>{{coach_name}} posted a new announcement {{batch_line}}for <strong>{{course_title}}</strong>:</p>\n\n<p><strong>{{title}}</strong></p>\n\n<blockquote style=\"border-left:4px solid #5751e1; padding:8px 12px; margin:12px 0; color:#333;\">\n{{message}}\n</blockquote>\n\n<p>Open your dashboard to view the full announcement.</p>', '2026-05-18 08:05:01', '2026-06-16 12:00:50'),
(40, 'notif_low_attendance_alert', 'Low attendance alert — {{batch_title}}', '<p>Hi {{user_name}},</p>\n\n<p>Today\'s attendance for <strong>{{batch_title}}</strong> was below the\nhealthy threshold:</p>\n\n<ul>\n  <li><strong>{{attended}}</strong> of <strong>{{total_students}}</strong> students attended ({{percent}}%)</li>\n</ul>\n\n<p>You can review the per-student roster and follow up with absentees from the\nbatch attendance dashboard.</p>', '2026-05-18 09:38:27', '2026-05-18 09:38:27'),
(41, 'notif_fee_demand_published', 'New fee due — {{fee_title}}', '<p>Hi {{user_name}},</p>\n\n<p>A new fee has been published for your batch <strong>{{batch_title}}</strong>:</p>\n\n<p><strong>{{fee_title}}</strong> — {{amount}}<br>\nDue: {{due_date}}</p>\n\n<p>Please complete the payment from your fees page before the due date.</p>', '2026-06-22 08:45:33', '2026-06-22 14:15:33'),
(42, 'notif_fee_payment_receipt', 'Payment received — receipt {{receipt_no}}', '<p>Hi {{user_name}},</p>\n\n<p>We have received your payment. Here is your receipt:</p>\n\n<p><strong>Amount:</strong> {{amount}}<br>\n<strong>For:</strong> {{fee_title}}<br>\n<strong>Receipt no:</strong> {{receipt_no}}<br>\n<strong>Paid on:</strong> {{paid_at}}<br>\n<strong>Method:</strong> {{gateway}}</p>\n\n<p>Thank you. You can view all your fees and receipts from your dashboard.</p>', '2026-06-22 08:45:33', '2026-06-22 14:15:33'),
(43, 'notif_new_landing_lead', 'New lead — {{service}}', '<p>Hi {{coach_name}},</p>\n\n<p>You received a new enquiry from your website{{vertical}}:</p>\n\n<p><strong>{{lead_name}}</strong><br>\n{{lead_email}}<br>\n{{lead_phone}}</p>\n\n<p><strong>Interested in:</strong> {{service}}</p>\n\n<blockquote style=\"border-left:4px solid #5751e1; padding:8px 12px; margin:12px 0; color:#333;\">\n{{message}}\n</blockquote>\n\n<p>Open your leads dashboard to follow up.</p>', '2026-06-22 08:45:33', '2026-06-22 14:15:33'),
(44, 'notif_student_welcome', 'Welcome to {{organization_name}}, {{student_name}}!', '<p>Hi {{student_name}},</p>\n\n<p>Welcome to <strong>{{organization_name}}</strong>! Your student account has been created successfully and is ready to use.</p>\n\n<p><strong>Your login email:</strong> {{student_email}}</p>\n\n<p><strong>Next steps:</strong></p>\n<ol>\n  <li>Log in to your student panel using the button below.</li>\n  <li>Browse and access your purchased or enrolled courses.</li>\n  <li>Join live classes and track your progress from your dashboard.</li>\n</ol>\n\n<p>We\'re excited to have you on board. If you have any questions, just reply to this email.</p>', '2026-06-26 09:48:39', '2026-06-26 15:18:39'),
(45, 'notif_new_student_registered', 'New student registered — {{student_name}}', '<p>Hi {{coach_name}},</p>\n\n<p>A new student just registered through your website (<strong>{{organization_name}}</strong>):</p>\n\n<p><strong>{{student_name}}</strong><br>\n{{student_email}}<br>\nRegistered: {{registered_at}}</p>\n\n<p>Open your students dashboard to view their details and follow up.</p>', '2026-06-26 09:48:39', '2026-06-26 15:18:39'),
(46, 'notif_course_sale_to_coach', 'New course sale: {{course_title}}', '<p>Hi {{coach_name}},</p>\n<p>Good news — you just made a sale! A student has purchased one of your courses.</p>\n<table style=\"border-collapse:collapse;width:100%;max-width:520px;margin:14px 0;font-size:14px;\">\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Student</td><td style=\"padding:6px 10px;font-weight:600;\">{{student_name}}</td></tr>\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Course</td><td style=\"padding:6px 10px;font-weight:600;\">{{course_title}}</td></tr>\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Order ID</td><td style=\"padding:6px 10px;\">{{order_id}}</td></tr>\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Date &amp; time</td><td style=\"padding:6px 10px;\">{{purchased_at}}</td></tr>\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Amount paid</td><td style=\"padding:6px 10px;font-weight:600;\">{{amount}}</td></tr>\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Payment status</td><td style=\"padding:6px 10px;\">{{payment_status}}</td></tr>\n</table>\n<p>You can <a href=\"{{order_url}}\">view the order</a> or <a href=\"{{student_url}}\">see the enrolled student</a> in your coach panel.</p>\n<p>Keep up the great work!</p>', '2026-06-30 15:45:41', '2026-06-30 15:45:41'),
(47, 'notif_live_class_started', 'Your Live Class Has Started – Join Now', '<p>Hello {{user_name}},</p>\n<p>Your live class has <strong>started</strong>. Please join now.</p>\n<table style=\"border-collapse:collapse;width:100%;max-width:520px;margin:14px 0;font-size:14px;\">\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Class Title</td><td style=\"padding:6px 10px;font-weight:600;\">{{class_title}}</td></tr>\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Course</td><td style=\"padding:6px 10px;\">{{course_title}}</td></tr>\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Batch</td><td style=\"padding:6px 10px;\">{{batch_name}}</td></tr>\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Teacher / Coach</td><td style=\"padding:6px 10px;\">{{coach_name}}</td></tr>\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Start Time</td><td style=\"padding:6px 10px;\">{{start_time}}</td></tr>\n</table>\n<p>Please join the class immediately using the button below.</p>', '2026-06-30 17:28:56', '2026-06-30 17:28:56'),
(48, 'notif_trial_booking_to_student', 'Your trial session with {{coach_name}} is booked', '<p>Hi {{student_name}},</p>\n<p>Thank you for booking a trial session with <strong>{{coach_name}}</strong>. Here are your details:</p>\n<table style=\"border-collapse:collapse;font-size:14px;\">\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Plan</td><td style=\"padding:4px 0;\">{{plan_type}} &middot; {{course_type}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Time slot</td><td style=\"padding:4px 0;\">{{time_slot}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Amount</td><td style=\"padding:4px 0;\">{{amount}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Payment</td><td style=\"padding:4px 0;\">{{payment_status}}</td></tr>\n</table>\n<p>We will contact you shortly to confirm your session. We look forward to seeing you.</p>', '2026-07-03 09:39:37', '2026-07-06 16:58:23'),
(49, 'notif_trial_booking_to_coach', 'New trial booking: {{student_name}}', '<p>Hi {{coach_name}},</p>\n<p>You have a new trial-session booking from your website.</p>\n<table style=\"border-collapse:collapse;font-size:14px;\">\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Name</td><td style=\"padding:4px 0;\">{{student_name}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Email</td><td style=\"padding:4px 0;\">{{student_email}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Mobile</td><td style=\"padding:4px 0;\">{{student_mobile}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Plan</td><td style=\"padding:4px 0;\">{{plan_type}} &middot; {{course_type}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Time slot</td><td style=\"padding:4px 0;\">{{time_slot}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Reason</td><td style=\"padding:4px 0;\">{{reason}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Amount</td><td style=\"padding:4px 0;\">{{amount}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Payment</td><td style=\"padding:4px 0;\">{{payment_status}}</td></tr>\n</table>\n<p>You can view and manage this enquiry from your coach panel.</p>', '2026-07-03 09:39:37', '2026-07-06 16:58:23'),
(50, 'notif_instant_meeting_invite', '{{coach_name}} is inviting you to a meeting — Join now', '<p>Hi {{student_name}},</p>\n<p><strong>{{coach_name}}</strong> is inviting you to a 1:1 live meeting right now.</p>\n<p>{{topic}}</p>\n<p>Click the button below to join — your coach is waiting.</p>', '2026-07-03 13:08:21', '2026-07-06 16:58:23'),
(51, 'notif_trial_student_welcome', 'Welcome to {{organization_name}} — your account & trial are ready', '<p>Hi {{student_name}},</p>\n<p>Welcome to <strong>{{organization_name}}</strong>! Your student account has been created and your trial session is confirmed.</p>\n<p><strong>Your login details</strong></p>\n<table style=\"border-collapse:collapse;font-size:14px;\">\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Login URL</td><td style=\"padding:4px 0;\"><a href=\"{{login_url}}\">{{login_url}}</a></td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Email</td><td style=\"padding:4px 0;\">{{student_email}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Temporary password</td><td style=\"padding:4px 0;\"><code>{{temp_password}}</code></td></tr>\n</table>\n<p style=\"color:#b45309;\">For your security, please change this password after your first login.</p>\n<p><strong>Trial session</strong></p>\n<table style=\"border-collapse:collapse;font-size:14px;\">\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Time slot</td><td style=\"padding:4px 0;\">{{time_slot}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Amount</td><td style=\"padding:4px 0;\">{{amount}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Payment</td><td style=\"padding:4px 0;\">{{payment_status}}</td></tr>\n</table>\n<p>Need help? Contact us at {{support_email}}.</p>', '2026-07-04 02:54:47', '2026-07-06 16:58:23'),
(52, 'notif_payment_failed_student', 'Your payment didn\'t go through — order #{{order_id}}', '<p>Hi {{user_name}},</p>\n<p>Unfortunately your payment of <strong>{{amount}}</strong> for order <strong>#{{order_id}}</strong> was not completed, so your order is still pending.</p>\n<p>No money has been taken. You can try the payment again whenever you\'re ready.</p>', '2026-07-09 19:43:02', '2026-07-09 19:43:02'),
(53, 'notif_payment_failed_coach', 'A checkout didn\'t complete — order #{{order_id}}', '<p>Hi {{coach_name}},</p>\n<p>A payment for order <strong>#{{order_id}}</strong> from <strong>{{student_name}}</strong> did not complete.</p>\n<table cellpadding=\"0\" cellspacing=\"0\" style=\"margin:12px 0;\">\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Amount</td><td style=\"padding:6px 10px;font-weight:600;\">{{amount}}</td></tr>\n  <tr><td style=\"padding:6px 10px;color:#6b7280;\">Order</td><td style=\"padding:6px 10px;font-weight:600;\">#{{order_id}}</td></tr>\n</table>\n<p>The customer has been invited to retry. You can follow up from your orders if you\'d like.</p>', '2026-07-09 19:43:02', '2026-07-09 19:43:02'),
(54, 'notif_pricing_booking_paid_to_coach', 'Payment received: {{student_name}} — {{amount}}', '<p>Hi {{coach_name}},</p>\n<p>You received a new <strong>paid</strong> plan booking from your website.</p>\n<table style=\"border-collapse:collapse;font-size:14px;\">\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Name</td><td style=\"padding:4px 0;\">{{student_name}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Email</td><td style=\"padding:4px 0;\">{{student_email}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Mobile</td><td style=\"padding:4px 0;\">{{student_mobile}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Plan</td><td style=\"padding:4px 0;\">{{category}} &middot; {{course_type}} &middot; {{time_period}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Amount paid</td><td style=\"padding:4px 0;\"><strong>{{amount}}</strong></td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Gateway</td><td style=\"padding:4px 0;\">{{gateway}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Transaction ID</td><td style=\"padding:4px 0;\">{{transaction_id}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Paid on</td><td style=\"padding:4px 0;\">{{paid_at}}</td></tr>\n</table>\n<p>You can view and manage this enquiry from your coach panel.</p>', '2026-07-14 08:20:58', '2026-07-14 13:50:58'),
(55, 'notif_pricing_booking_receipt_to_student', 'Payment receipt — {{amount}} to {{coach_name}}', '<p>Hi {{student_name}},</p>\n<p>Thank you — your payment to <strong>{{coach_name}}</strong> was received. Here is your receipt:</p>\n<table style=\"border-collapse:collapse;font-size:14px;\">\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Plan</td><td style=\"padding:4px 0;\">{{category}} &middot; {{course_type}} &middot; {{time_period}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Amount paid</td><td style=\"padding:4px 0;\"><strong>{{amount}}</strong></td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Payment method</td><td style=\"padding:4px 0;\">{{gateway}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Transaction ID</td><td style=\"padding:4px 0;\">{{transaction_id}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Paid on</td><td style=\"padding:4px 0;\">{{paid_at}}</td></tr>\n</table>\n<p>We will be in touch shortly to confirm your booking. Please keep this receipt for your records.</p>', '2026-07-14 08:20:58', '2026-07-14 13:50:58'),
(56, 'notif_booking_enquiry_to_coach', 'New booking: {{student_name}} ({{payment_status}})', '<p>Hi {{coach_name}},</p>\n<p>You have a new booking enquiry from your website.</p>\n<table style=\"border-collapse:collapse;font-size:14px;\">\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Name</td><td style=\"padding:4px 0;\">{{student_name}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Email</td><td style=\"padding:4px 0;\">{{student_email}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Mobile</td><td style=\"padding:4px 0;\">{{student_mobile}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Class</td><td style=\"padding:4px 0;\">{{class_name}} &middot; {{slot}} &middot; {{trainer}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Plan</td><td style=\"padding:4px 0;\">{{plan_type}} &middot; {{course_type}} &middot; {{time_period}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Amount</td><td style=\"padding:4px 0;\">{{amount}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Payment status</td><td style=\"padding:4px 0;\"><strong>{{payment_status}}</strong></td></tr>\n</table>\n<p>You can manage this enquiry from your coach panel.</p>', '2026-07-14 10:51:02', '2026-07-14 16:21:02'),
(57, 'notif_booking_enquiry_to_student', 'Your booking with {{coach_name}} — {{payment_status}}', '<p>Hi {{student_name}},</p>\n<p>Thank you — your booking request with <strong>{{coach_name}}</strong> has been received.</p>\n<table style=\"border-collapse:collapse;font-size:14px;\">\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Class</td><td style=\"padding:4px 0;\">{{class_name}} &middot; {{slot}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Plan</td><td style=\"padding:4px 0;\">{{plan_type}} &middot; {{course_type}} &middot; {{time_period}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Amount</td><td style=\"padding:4px 0;\">{{amount}}</td></tr>\n  <tr><td style=\"padding:4px 12px 4px 0;color:#64748b;\">Payment status</td><td style=\"padding:4px 0;\"><strong>{{payment_status}}</strong></td></tr>\n</table>\n<p>If a payment is pending, you can complete it from the booking window. We will be in touch shortly.</p>', '2026-07-14 10:51:02', '2026-07-14 16:21:02');

-- --------------------------------------------------------

--
-- Table structure for table `employee_profiles`
--

CREATE TABLE `employee_profiles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT 'users.id of the employee',
  `employee_code` varchar(40) DEFAULT NULL,
  `department_id` bigint(20) UNSIGNED DEFAULT NULL,
  `reporting_hr_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'users.id of the HR (instructor)',
  `designation` varchar(255) DEFAULT NULL,
  `employment_type` varchar(30) DEFAULT NULL COMMENT 'full_time|part_time|contract|intern',
  `date_of_joining` date DEFAULT NULL,
  `date_of_exit` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active|onboarding|exited|suspended',
  `photo_path` varchar(255) DEFAULT NULL,
  `father_or_spouse_name` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `personal_email` varchar(255) DEFAULT NULL,
  `current_address` text DEFAULT NULL,
  `permanent_address` text DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bank_account_number` text DEFAULT NULL,
  `bank_ifsc_code` varchar(30) DEFAULT NULL,
  `pf_number` text DEFAULT NULL,
  `uan_number` text DEFAULT NULL,
  `esi_number` text DEFAULT NULL,
  `pan_number` text DEFAULT NULL,
  `aadhaar_number` text DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(30) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_profiles`
--

INSERT INTO `employee_profiles` (`id`, `company_id`, `user_id`, `employee_code`, `department_id`, `reporting_hr_id`, `designation`, `employment_type`, `date_of_joining`, `date_of_exit`, `status`, `photo_path`, `father_or_spouse_name`, `date_of_birth`, `phone`, `personal_email`, `current_address`, `permanent_address`, `bank_name`, `bank_account_number`, `bank_ifsc_code`, `pf_number`, `uan_number`, `esi_number`, `pan_number`, `aadhaar_number`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`, `updated_at`) VALUES
(2, 1, 1283, 'EMP03', 2, 1278, 'Velit et excepturi', 'full_time', '2026-07-16', NULL, 'active', 'uploads/employee-photos/oxGOevrB8fVkDeTDpQ8B2SmW3PoPXLFe5y45VGOB.png', 'Kylan Terry', '2000-08-25', '6511060074', 'sureshs.dhs@gmail.com', 'Possimus enim anim', 'Sunt consequatur f', 'ICICI', 'eyJpdiI6IkpJcnE2TXQxNzh4aS9ubHVYbldCbkE9PSIsInZhbHVlIjoidE92Q2IwNEtUdHlJNHNMZC9nTnhLQT09IiwibWFjIjoiN2QyN2U1NjNjMTU2ZmVkYmM2OWVjZDEwMDg1YzYyYWFkYWEyNWJmYTljNDNmOWU1Yjg5ODE3MTVmNDIyN2QxYyIsInRhZyI6IiJ9', 'ICICI98755', 'eyJpdiI6IlhmRVQ2eGZ2SG13WlFNZ0pta3BlUWc9PSIsInZhbHVlIjoiQXoyMDNlSzV5TjRicUdMS3dQcmVtZz09IiwibWFjIjoiNTFiN2RjNzYwNDEyYjQwMzIyNmExM2RjNDE5MjA3YWU1YWJjY2NlZWM5MmJmZGFhMjY1ZTljOGMyODhkOWFlYyIsInRhZyI6IiJ9', 'eyJpdiI6ImZDWHZpNThpdFpBZzJvUVJJdWhEdEE9PSIsInZhbHVlIjoieVVsWjk1V0ZjekFVR1hwcFdPUlJsUT09IiwibWFjIjoiYThiYjY5ZTg4MTZlMTk5ZTVjYzM5MDc1OTU4N2ZjNGJhOGE5OTI5ZjVkMGMwYjZkYjRkZTFkNGQ0YzhjYjA5ZiIsInRhZyI6IiJ9', 'eyJpdiI6ImNFa0FicytJeGw1OVl6dCtFZU9jVVE9PSIsInZhbHVlIjoiSTMzL1ZOQnpsTWxlSzBVcytZSkZoZz09IiwibWFjIjoiODZlNDEwODljNDZhYjE4ZGI3OGNjZWNkNTI3YjBlNTA4Mjg0NjY0ODVhMGE2ZDRiNjZmMzI2ZmJjYTI3NTM3NyIsInRhZyI6IiJ9', 'eyJpdiI6IndiUUJ1ZWdURldxdFBxb1Q2SllOTEE9PSIsInZhbHVlIjoieFJMNzZKUDU5NUdYS1dWSEdZelRKQT09IiwibWFjIjoiMjU5ODEzNjU4ZTNiZGRjZTdkODRlZThiZWM4NGIwNzRlN2Q4NjRjNTg3N2E2YjBhZWJmNGFmNTBiODhjZDExZCIsInRhZyI6IiJ9', 'eyJpdiI6IlhHclVnTGUrTUNEU29SeEdZS3lBeXc9PSIsInZhbHVlIjoiSjBOd1FobDBrZG0wQzdzams4RTZFUT09IiwibWFjIjoiNmQ4MDA4ODU4YjIzNmRlMjE1NjMyMDAzMzcwOTU0MTU1NjM0OTdlYjcxMjMwZDc3NWU2ZmEwZWIxNGJjOWIwYSIsInRhZyI6IiJ9', 'Deva', NULL, '2026-07-30 12:13:04', '2026-08-25 06:57:33'),
(3, 1, 1284, 'EMP01', 2, 1278, 'Praesentium voluptat', 'full_time', '2026-07-23', NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-30 12:14:57', '2026-07-31 13:36:11'),
(4, 1, 1285, 'EMP02', 3, 1278, 'Sed rerum distinctio', 'full_time', '2015-06-17', NULL, 'active', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-31 13:47:02', '2026-07-31 13:47:33'),
(5, 1, 1286, 'E0004', 3, 1278, 'Machine Operator', 'full_time', '2026-08-03', NULL, 'active', 'uploads/employee-photos/cMB4qTYaEIo5HT3hk1l6yU8fhjt3S4HqCqzWONTs.jpg', 'Kylan Terry', '2000-02-01', '987654324', 'sureshs.dhs@gmail.com', 'Noida 201301, UP', 'Noida 201301', 'ICICI', 'eyJpdiI6IktoMUVvdDdsd3NzMFF3QkFIbTNKUlE9PSIsInZhbHVlIjoiaGpSVjUwdFpGcXlNVVFjKzBPTG5hQT09IiwibWFjIjoiZjdlNTUxNjEzNGQxYzExZDNkNDZhYjFlOWUzZTFmZmE5ZDRhY2MxODgyMDNhNTk5MDAzZGEzNGMxNzU3OGE1MCIsInRhZyI6IiJ9', 'ICICI98755', 'eyJpdiI6IkNseGYwS3dQUEJzSTR2ZGppSGZhREE9PSIsInZhbHVlIjoiQ1pGSTI4MTRSK0NET09RdFNOREI4dz09IiwibWFjIjoiOTM2ZmFmZGJhYzA0ZTBjNWI3MTJjYTc5NDdlZTVlYWQyNWExNDBiZWQ0ZGI4MTk4ZTQwMDFkNTkwYmEyZjI4OCIsInRhZyI6IiJ9', 'eyJpdiI6IkdPa2RkeWV3SlZPMDF4R3NvMmVnakE9PSIsInZhbHVlIjoiTmljVk93RC9RQ2Y5dmxVTUtqSy90dz09IiwibWFjIjoiOGU4ZDdiNzlhNDQ1OWY3NWE2ODY2YzBmYzA3NWJiOWNhZTViODIyNWI0YWJkMGYxYThhNTlhZjJiODU1Y2QzNiIsInRhZyI6IiJ9', 'eyJpdiI6IjFPSWJZQUJtU1dxTnlsd0NXZFh1WHc9PSIsInZhbHVlIjoialJPakh4ZkRIdDhhTUNJL2dFQW5OUT09IiwibWFjIjoiMTc0YzA1MWExOWRhMDJiNjY0Y2RlMDE4ZTE0YjlkYjViNDYxOWI0N2VhNTIzMzdjODc1N2FiN2ZmZWYzZGQxZCIsInRhZyI6IiJ9', 'eyJpdiI6InlvcHNxYzNkdWxsTTRsdG5JN213c0E9PSIsInZhbHVlIjoib29sZVZGSGcvaXRxbzVmSEFpVmVIQT09IiwibWFjIjoiNDA0NmE5MmUxNTA5YWQwN2Y1MjBkZTUxNzBhZmU1M2U3MzI1MWUzYzMzODE1YWMzMGMzODMwNGI4Y2ExMDAyMyIsInRhZyI6IiJ9', 'eyJpdiI6Ik9PMWhXaktmeUE0UmI4bVBTUmRnZkE9PSIsInZhbHVlIjoiQWVkelRLazcwWHliM1dBWXdsaUdCUT09IiwibWFjIjoiMDMzZGVlODU5OGE3ZTkxMDYzOWU5MTFkNGI0OGZhYjAxMjQzNjBmY2JmNzEyNjkzMmNkZDNjNmEzZjgyYjUxMSIsInRhZyI6IiJ9', 'Suresh Sarkar', '876543658', '2026-08-03 10:48:45', '2026-08-25 07:19:10'),
(6, 13, 1287, 'Dolore inventore ape', 16, 1278, 'Voluptas facere maio', 'contract', '1987-03-10', '1984-02-13', 'suspended', 'uploads/employee-photos/67hYr9rWkzPQTATShvdSQpxOBA3BmuoTGBQGkQBW.png', 'Ayanna Trevino', '1989-03-15', '+1 (473) 871-5056', 'totacube@mailinator.com', 'Officia dolorem ad d', 'Quam aut incidunt n', 'Noah Head', 'eyJpdiI6IjNLQ1JNdGkxV0xCd2ZGMitVNzNhS1E9PSIsInZhbHVlIjoiUENlclFMMUNtY1kzWFF4bEZ3MUJSZz09IiwibWFjIjoiZTI2OWYwZjFiYTIyODdmZDZlM2YyOGEwZGVlMWFhMzg1ZjQxNDQwOWVlZjRkYTU4ZTgzNDJhZDdjMzE2ZGNhYSIsInRhZyI6IiJ9', 'Iure dolorem sint eu', 'eyJpdiI6IkxqS2pjQVhKMFFiaXlnam9MN1B1OXc9PSIsInZhbHVlIjoiS3VxQzJubWk1UUs2QVV3WE9YdnUwZz09IiwibWFjIjoiMzdmNmE4M2Y2NjU5OTE3ODc3ZWFkN2RkYjM0NDk4YTkxODQ0N2EzNmM2OGExZGI2NDljMTUzM2IyNGJiNmY5NCIsInRhZyI6IiJ9', 'eyJpdiI6IlQyMUJJR2pMWXhNSnVCdVh1ajhyZUE9PSIsInZhbHVlIjoiTEFON3N0czV2MURmOE42Y005Q3I2QT09IiwibWFjIjoiZjVkNTJhMzNhOGM3MWFkZWE4Njk5Zjg1NzJkZTNjMzY4ZDcwZjliYmQ4NGE1ZGViYWE3ZWY2M2FiMmMyNWM2NCIsInRhZyI6IiJ9', 'eyJpdiI6IlEyUXBQcjFYRjlJNlF1OTV3ejB4OWc9PSIsInZhbHVlIjoiM21iOUJlNEt1bU9JbkdnZ1AyWWhuZz09IiwibWFjIjoiN2ZlODRkMWUzYzg2NjA1NzQ3Zjg5YjU0ZDA2Nzk0NzZjODczYjFmNmQzNmE0NDhiMmY5MzJmMDRiNzkyZTI0NiIsInRhZyI6IiJ9', 'eyJpdiI6IjByMEZEQ0k1SjFVc0lhczVCRGNka1E9PSIsInZhbHVlIjoiZmdoYWhmT3ZHMGRBQVEwU091NGNwQT09IiwibWFjIjoiMzE4YjljMjUxZWJkMGE5MjM0ZDg2MGZhZWM1MzI5ZGQ3OGJhMzU1N2EyOTYwNGNjMDI1ODE4NGMzZDJiN2JhMyIsInRhZyI6IiJ9', 'eyJpdiI6IkthMjI5djNOL3d3cnFFTHU0QXpWeHc9PSIsInZhbHVlIjoibVVsekVSYnBLaGNsNUc5NUxBNGJyUT09IiwibWFjIjoiMmNiNDk4MDRlMThiZGQ2OGJjZjc1MmEyNWQwYTRlMTBiZWFmMDBkOGMyNWFjMTJhMWJmYzM1OTBlYzQ4YThhOCIsInRhZyI6IiJ9', 'Quail House', '+1 (932) 368-9496', '2026-08-18 13:54:57', '2026-08-24 13:40:22');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `queue`, `payload`, `attempts`, `reserved_at`, `available_at`, `created_at`) VALUES
(1073, 'default', '{\"uuid\":\"d599ccfb-cfbc-405d-a827-cec75b9dc85b\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":14:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1278;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:37:\\\"App\\\\Notifications\\\\NewLoginAlertToUser\\\":6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:169:\\\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:47:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/public\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:2:\\\"id\\\";s:36:\\\"35c1fe6d-73dc-405c-8431-ec882845243c\\\";}s:4:\\\"data\\\";a:6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:169:\\\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:47:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/public\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:9:\\\"createdAt\\\";s:25:\\\"2026-07-30T15:10:07+05:30\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}}\"}}', 0, NULL, 1785404408, 1785404408),
(1074, 'default', '{\"uuid\":\"f28d24ea-bd9a-4fe1-bc9a-dd8d3f8906d2\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":14:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1079;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:37:\\\"App\\\\Notifications\\\\NewLoginAlertToUser\\\":6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:175:\\\"We noticed a sign-in to your account from IP 127.0.0.1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:2:\\\"id\\\";s:36:\\\"94496399-5198-427f-b29f-0e015f6c6a33\\\";}s:4:\\\"data\\\";a:6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:175:\\\"We noticed a sign-in to your account from IP 127.0.0.1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:9:\\\"createdAt\\\";s:25:\\\"2026-07-30T17:36:44+05:30\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}}\"}}', 0, NULL, 1785413204, 1785413204),
(1075, 'default', '{\"uuid\":\"d1780d67-015c-41e1-b38e-83fae9fb4ea9\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":14:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1278;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:37:\\\"App\\\\Notifications\\\\NewLoginAlertToUser\\\":6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:169:\\\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:2:\\\"id\\\";s:36:\\\"472524c9-4a13-4a51-8254-9decc2f9aeb7\\\";}s:4:\\\"data\\\";a:6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:169:\\\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:9:\\\"createdAt\\\";s:25:\\\"2026-08-03T16:09:55+05:30\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}}\"}}', 0, NULL, 1785753596, 1785753596),
(1076, 'default', '{\"uuid\":\"be884674-7746-4510-99eb-eef8693806d6\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":14:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1280;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:37:\\\"App\\\\Notifications\\\\NewLoginAlertToUser\\\":6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:169:\\\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:2:\\\"id\\\";s:36:\\\"b105558c-e289-4212-a3d5-e4686e0a45ac\\\";}s:4:\\\"data\\\";a:6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:169:\\\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:9:\\\"createdAt\\\";s:25:\\\"2026-08-18T16:14:28+05:30\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}}\"}}', 0, NULL, 1787049868, 1787049868),
(1077, 'default', '{\"uuid\":\"330ad3cd-cc52-40fe-b4f1-c9e35471ec9d\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":14:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1278;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:37:\\\"App\\\\Notifications\\\\NewLoginAlertToUser\\\":6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:169:\\\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:2:\\\"id\\\";s:36:\\\"673fb917-9751-4630-8549-86f4a5f2b5cd\\\";}s:4:\\\"data\\\";a:6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:169:\\\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:9:\\\"createdAt\\\";s:25:\\\"2026-08-24T18:44:22+05:30\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}}\"}}', 0, NULL, 1787577262, 1787577262),
(1078, 'default', '{\"uuid\":\"00146220-2ae3-48fb-8bae-08fb2e784fc2\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":14:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1255;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:44:\\\"App\\\\Notifications\\\\ReferralRewardEarnedToUser\\\":7:{s:54:\\\"\\u0000App\\\\Notifications\\\\ReferralRewardEarnedToUser\\u0000referral\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:19:\\\"App\\\\Models\\\\Referral\\\";s:2:\\\"id\\\";i:25;s:9:\\\"relations\\\";a:1:{i:0;s:8:\\\"referred\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:5:\\\"title\\\";s:29:\\\"You earned a referral reward!\\\";s:4:\\\"body\\\";s:92:\\\"Cockroach Coach just activated their membership. ₹100.00 credited to your referral wallet.\\\";s:3:\\\"url\\\";s:43:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/referral\\\";s:4:\\\"icon\\\";s:7:\\\"fa-gift\\\";s:9:\\\"iconColor\\\";s:7:\\\"#10b981\\\";s:2:\\\"id\\\";s:36:\\\"1751065e-cfb7-4112-8f0f-4d58feb78f7b\\\";}s:4:\\\"data\\\";a:6:{s:5:\\\"title\\\";s:29:\\\"You earned a referral reward!\\\";s:4:\\\"body\\\";s:92:\\\"Cockroach Coach just activated their membership. ₹100.00 credited to your referral wallet.\\\";s:3:\\\"url\\\";s:43:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/referral\\\";s:4:\\\"icon\\\";s:7:\\\"fa-gift\\\";s:9:\\\"iconColor\\\";s:7:\\\"#10b981\\\";s:9:\\\"createdAt\\\";s:25:\\\"2026-08-25T10:42:54+05:30\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}}\"}}', 0, NULL, 1787634774, 1787634774),
(1079, 'default', '{\"uuid\":\"cc96dd16-ef2d-4a4f-a501-a3c2b344577d\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":14:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1278;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:43:\\\"App\\\\Notifications\\\\MembershipActivatedToUser\\\":7:{s:55:\\\"\\u0000App\\\\Notifications\\\\MembershipActivatedToUser\\u0000membership\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:25:\\\"App\\\\Models\\\\UserMembership\\\";s:2:\\\"id\\\";i:48;s:9:\\\"relations\\\";a:2:{i:0;s:4:\\\"plan\\\";i:1;s:4:\\\"user\\\";}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:5:\\\"title\\\";s:21:\\\"Your Medium is active\\\";s:4:\\\"body\\\";s:26:\\\"Active until Sep 24, 2026.\\\";s:3:\\\"url\\\";s:45:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/membership\\\";s:4:\\\"icon\\\";s:13:\\\"fa-shield-alt\\\";s:9:\\\"iconColor\\\";s:7:\\\"#10b981\\\";s:2:\\\"id\\\";s:36:\\\"e161f252-7067-40d6-aeea-4e743ccf91d0\\\";}s:4:\\\"data\\\";a:6:{s:5:\\\"title\\\";s:21:\\\"Your Medium is active\\\";s:4:\\\"body\\\";s:26:\\\"Active until Sep 24, 2026.\\\";s:3:\\\"url\\\";s:45:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/membership\\\";s:4:\\\"icon\\\";s:13:\\\"fa-shield-alt\\\";s:9:\\\"iconColor\\\";s:7:\\\"#10b981\\\";s:9:\\\"createdAt\\\";s:25:\\\"2026-08-25T10:43:02+05:30\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}}\"}}', 0, NULL, 1787634782, 1787634782),
(1080, 'default', '{\"uuid\":\"12e4bfec-7a70-46d8-ac58-dabe59ff3240\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":14:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1278;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:37:\\\"App\\\\Notifications\\\\NewLoginAlertToUser\\\":6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:169:\\\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:2:\\\"id\\\";s:36:\\\"1f381d36-fe02-4584-a9c3-e248e7803170\\\";}s:4:\\\"data\\\";a:6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:169:\\\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:9:\\\"createdAt\\\";s:25:\\\"2026-08-31T13:48:55+05:30\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}}\"}}', 0, NULL, 1788164335, 1788164335),
(1081, 'default', '{\"uuid\":\"d8fbf576-b3b7-47ab-80fc-010eeca3d84c\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":14:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1182;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:37:\\\"App\\\\Notifications\\\\NewLoginAlertToUser\\\":6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:175:\\\"We noticed a sign-in to your account from IP 127.0.0.1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:22:\\\"http:\\/\\/localhost\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:2:\\\"id\\\";s:36:\\\"2fc9119f-315e-46d6-acd1-e85e227644d4\\\";}s:4:\\\"data\\\";a:6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:175:\\\"We noticed a sign-in to your account from IP 127.0.0.1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:22:\\\"http:\\/\\/localhost\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:9:\\\"createdAt\\\";s:25:\\\"2026-08-31T13:55:15+05:30\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}}\"}}', 0, NULL, 1788164715, 1788164715),
(1082, 'default', '{\"uuid\":\"b1631b8e-3a31-458b-a056-0e488134f14a\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":14:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1286;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:37:\\\"App\\\\Notifications\\\\NewLoginAlertToUser\\\":6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:169:\\\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:2:\\\"id\\\";s:36:\\\"c4d38cf0-cb1a-4195-8d1f-cb245dcd01be\\\";}s:4:\\\"data\\\";a:6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:169:\\\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:9:\\\"createdAt\\\";s:25:\\\"2026-08-31T13:55:26+05:30\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}}\"}}', 0, NULL, 1788164726, 1788164726),
(1083, 'default', '{\"uuid\":\"b203eb8f-87b6-4be8-9dd1-714cf0e0d174\",\"displayName\":\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\",\"job\":\"Illuminate\\\\Queue\\\\CallQueuedHandler@call\",\"maxTries\":null,\"maxExceptions\":null,\"failOnTimeout\":false,\"backoff\":null,\"timeout\":null,\"retryUntil\":null,\"data\":{\"commandName\":\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\",\"command\":\"O:38:\\\"Illuminate\\\\Broadcasting\\\\BroadcastEvent\\\":14:{s:5:\\\"event\\\";O:60:\\\"Illuminate\\\\Notifications\\\\Events\\\\BroadcastNotificationCreated\\\":3:{s:10:\\\"notifiable\\\";O:45:\\\"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier\\\":5:{s:5:\\\"class\\\";s:15:\\\"App\\\\Models\\\\User\\\";s:2:\\\"id\\\";i:1278;s:9:\\\"relations\\\";a:0:{}s:10:\\\"connection\\\";s:5:\\\"mysql\\\";s:15:\\\"collectionClass\\\";N;}s:12:\\\"notification\\\";O:37:\\\"App\\\\Notifications\\\\NewLoginAlertToUser\\\":6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:175:\\\"We noticed a sign-in to your account from IP 127.0.0.1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:2:\\\"id\\\";s:36:\\\"d69717fd-1d61-443b-8abc-006732bd87f3\\\";}s:4:\\\"data\\\";a:6:{s:5:\\\"title\\\";s:27:\\\"New sign-in to your account\\\";s:4:\\\"body\\\";s:175:\\\"We noticed a sign-in to your account from IP 127.0.0.1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\\\";s:3:\\\"url\\\";s:40:\\\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\\\";s:4:\\\"icon\\\";s:16:\\\"fa-shield-halved\\\";s:9:\\\"iconColor\\\";s:7:\\\"#f59e0b\\\";s:9:\\\"createdAt\\\";s:25:\\\"2026-08-31T13:57:05+05:30\\\";}}s:5:\\\"tries\\\";N;s:7:\\\"timeout\\\";N;s:7:\\\"backoff\\\";N;s:13:\\\"maxExceptions\\\";N;s:10:\\\"connection\\\";N;s:5:\\\"queue\\\";N;s:15:\\\"chainConnection\\\";N;s:10:\\\"chainQueue\\\";N;s:19:\\\"chainCatchCallbacks\\\";N;s:5:\\\"delay\\\";N;s:11:\\\"afterCommit\\\";N;s:10:\\\"middleware\\\";a:0:{}s:7:\\\"chained\\\";a:0:{}}\"}}', 0, NULL, 1788164825, 1788164825);

-- --------------------------------------------------------

--
-- Table structure for table `languages`
--

CREATE TABLE `languages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `direction` varchar(255) NOT NULL DEFAULT 'ltr',
  `status` varchar(255) NOT NULL DEFAULT '1',
  `is_default` varchar(255) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `languages`
--

INSERT INTO `languages` (`id`, `name`, `code`, `icon`, `direction`, `status`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 'English', 'en', NULL, 'ltr', '1', '1', '2024-08-14 21:23:17', '2026-07-16 17:57:06'),
(2, 'Hindi', 'hi', NULL, 'ltr', '1', '0', '2024-08-14 21:23:17', '2026-07-16 17:57:06');

-- --------------------------------------------------------

--
-- Table structure for table `leaves`
--

CREATE TABLE `leaves` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL COMMENT 'employee applying',
  `leave_type_id` bigint(20) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days` decimal(5,1) NOT NULL COMMENT 'supports half-days',
  `reason` varchar(500) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending|approved|rejected|cancelled',
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `review_note` varchar(500) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leaves`
--

INSERT INTO `leaves` (`id`, `company_id`, `user_id`, `leave_type_id`, `start_date`, `end_date`, `days`, `reason`, `status`, `approved_by`, `review_note`, `reviewed_at`, `created_at`, `updated_at`) VALUES
(3, 1, 1286, 1, '2026-08-25', '2026-08-25', 1.0, 'I need full day leave', 'pending', NULL, NULL, NULL, '2026-08-25 13:31:17', '2026-08-25 13:31:17');

-- --------------------------------------------------------

--
-- Table structure for table `leave_balances`
--

CREATE TABLE `leave_balances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `leave_type_id` bigint(20) UNSIGNED NOT NULL,
  `year` smallint(5) UNSIGNED NOT NULL,
  `allotted` decimal(6,1) NOT NULL DEFAULT 0.0,
  `used` decimal(6,1) NOT NULL DEFAULT 0.0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_balances`
--

INSERT INTO `leave_balances` (`id`, `company_id`, `user_id`, `leave_type_id`, `year`, `allotted`, `used`, `created_at`, `updated_at`) VALUES
(2, NULL, 1182, 1, 2026, 12.0, 0.0, '2026-08-25 13:18:21', '2026-08-25 13:18:21'),
(3, NULL, 1182, 19, 2026, 12.0, 0.0, '2026-08-25 13:18:21', '2026-08-25 13:18:21'),
(4, NULL, 1182, 3, 2026, 15.0, 0.0, '2026-08-25 13:18:21', '2026-08-25 13:18:21'),
(5, NULL, 1182, 21, 2026, 15.0, 0.0, '2026-08-25 13:18:21', '2026-08-25 13:18:21'),
(6, NULL, 1182, 2, 2026, 10.0, 0.0, '2026-08-25 13:18:21', '2026-08-25 13:18:21'),
(7, NULL, 1182, 20, 2026, 10.0, 0.0, '2026-08-25 13:18:21', '2026-08-25 13:18:21'),
(9, 1, 1286, 1, 2026, 12.0, 0.0, '2026-08-25 13:30:42', '2026-08-25 13:30:42'),
(10, 1, 1286, 3, 2026, 15.0, 0.0, '2026-08-25 13:30:42', '2026-08-25 13:30:42'),
(11, 1, 1286, 2, 2026, 10.0, 0.0, '2026-08-25 13:30:42', '2026-08-25 13:30:42'),
(13, 1, 1283, 1, 2026, 12.0, 0.0, '2026-08-31 08:27:09', '2026-08-31 08:27:09'),
(14, 1, 1283, 3, 2026, 15.0, 0.0, '2026-08-31 08:27:09', '2026-08-31 08:27:09'),
(15, 1, 1283, 2, 2026, 10.0, 0.0, '2026-08-31 08:27:09', '2026-08-31 08:27:09');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `is_paid` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'paid leave vs loss-of-pay',
  `annual_quota` decimal(5,1) NOT NULL DEFAULT 0.0 COMMENT 'days per year',
  `carry_forward` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`id`, `company_id`, `name`, `code`, `is_paid`, `annual_quota`, `carry_forward`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Casual Leave', 'CL', 1, 12.0, 0, 1, '2026-07-30 11:07:37', '2026-07-30 11:07:37'),
(2, 1, 'Sick Leave', 'SL', 1, 10.0, 0, 1, '2026-07-30 11:07:37', '2026-07-30 11:07:37'),
(3, 1, 'Earned Leave', 'EL', 1, 15.0, 1, 1, '2026-07-30 11:07:37', '2026-07-30 11:07:37'),
(4, 1, 'Loss of Pay', 'LOP', 0, 0.0, 0, 1, '2026-07-30 11:07:37', '2026-07-30 11:07:37'),
(19, 13, 'Casual Leave', 'CL', 1, 12.0, 0, 1, '2026-08-18 13:54:17', '2026-08-18 13:54:17'),
(20, 13, 'Sick Leave', 'SL', 1, 10.0, 0, 1, '2026-08-18 13:54:17', '2026-08-18 13:54:17'),
(21, 13, 'Earned Leave', 'EL', 1, 15.0, 1, 1, '2026-08-18 13:54:17', '2026-08-18 13:54:17'),
(22, 13, 'Loss of Pay', 'LOP', 0, 0.0, 0, 1, '2026-08-18 13:54:17', '2026-08-18 13:54:17');

-- --------------------------------------------------------

--
-- Table structure for table `loans_advances`
--

CREATE TABLE `loans_advances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'advance' COMMENT 'loan|advance',
  `principal` decimal(12,2) NOT NULL,
  `monthly_recovery` decimal(12,2) NOT NULL DEFAULT 0.00,
  `recovered` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active|closed',
  `note` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `marketing_settings`
--

CREATE TABLE `marketing_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `marketing_settings`
--

INSERT INTO `marketing_settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
(1, 'register', '0', '2024-06-02 20:02:30', '2025-10-16 12:58:34'),
(2, 'course_details', '0', '2024-06-02 20:02:30', '2025-10-16 12:58:34'),
(3, 'add_to_cart', '0', '2024-06-02 20:02:30', '2025-10-16 12:58:34'),
(4, 'remove_from_cart', '1', '2024-06-02 20:02:30', '2025-10-16 12:58:34'),
(5, 'checkout', '1', '2024-06-02 20:02:30', '2024-06-24 18:17:45'),
(6, 'order_success', '0', '2024-06-02 20:02:30', '2025-10-16 12:58:34'),
(7, 'order_failed', '0', '2024-06-02 20:02:30', '2025-10-16 12:58:34'),
(8, 'contact_page', '0', '2024-06-02 20:02:30', '2025-10-16 12:58:34'),
(9, 'instructor_contact', '0', '2024-06-02 20:02:30', '2025-10-16 12:58:34');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '2014_10_12_000000_create_users_table', 1),
(2, '2014_10_12_100000_create_password_reset_tokens_table', 1),
(3, '2019_08_19_000000_create_failed_jobs_table', 1),
(4, '2019_12_14_000001_create_personal_access_tokens_table', 1),
(5, '2023_11_05_045432_create_admins_table', 1),
(6, '2023_11_05_114814_create_languages_table', 1),
(7, '2023_11_06_043247_create_settings_table', 1),
(8, '2023_11_06_054251_create_seo_settings_table', 1),
(9, '2023_11_06_094842_create_custom_paginations_table', 1),
(10, '2023_11_06_115856_create_email_templates_table', 1),
(11, '2023_11_07_051924_create_multi_currencies_table', 1),
(12, '2023_11_07_103108_create_basic_payments_table', 1),
(13, '2023_11_07_104315_create_blog_categories_table', 1),
(14, '2023_11_07_104328_create_blog_category_translations_table', 1),
(15, '2023_11_07_104336_create_blogs_table', 1),
(16, '2023_11_07_104343_create_blog_translations_table', 1),
(17, '2023_11_07_104546_create_blog_comments_table', 1),
(18, '2023_11_09_035236_create_payment_gateways_table', 1),
(19, '2023_11_09_100621_create_jobs_table', 1),
(20, '2023_11_16_035458_add_user_info_to_users', 1),
(21, '2023_11_16_061508_add_forget_info_to_users', 1),
(22, '2023_11_16_063639_add_phone_to_users', 1),
(23, '2023_11_19_055229_add_image_to_users', 1),
(24, '2023_11_19_064341_create_banned_histories_table', 1),
(25, '2023_11_21_043030_create_news_letters_table', 1),
(26, '2023_11_21_094702_create_contact_messages_table', 1),
(27, '2023_11_22_105539_create_permission_tables', 1),
(28, '2023_11_29_055540_create_orders_table', 1),
(29, '2023_11_29_095126_create_coupons_table', 1),
(30, '2023_11_29_104658_create_testimonials_table', 1),
(31, '2023_11_29_104704_create_testimonial_translations_table', 1),
(32, '2023_11_29_105234_create_coupon_histories_table', 1),
(33, '2023_11_29_113632_add_min_price_to_coupon', 1),
(34, '2023_11_30_044838_create_faqs_table', 1),
(35, '2023_11_30_044844_create_faq_translations_table', 1),
(36, '2023_11_30_095404_add_wallet_balance_to_users', 1),
(37, '2023_12_04_071839_create_withraw_methods_table', 1),
(38, '2023_12_04_095319_create_withdraw_requests_table', 1),
(39, '2024_01_01_054644_create_socialite_credentials_table', 1),
(40, '2024_01_03_092007_create_custom_codes_table', 1),
(41, '2024_02_28_064128_add_forgot_info_to_admins', 1),
(42, '2024_03_28_095207_create_menus_wp_table', 1),
(43, '2024_03_28_095208_create_menu_translations_table', 1),
(44, '2024_03_28_095209_create_menu_items_wp_table', 1),
(45, '2024_03_28_095210_create_menu_item_translations_table', 1),
(46, '2024_03_28_095211_add-role-id-to-menu-items-table', 1),
(47, '2024_04_03_042331_add_new_columns_to_users', 1),
(48, '2024_04_03_044043_create_user_education_table', 1),
(49, '2024_04_03_044103_create_user_experiences_table', 1),
(50, '2024_04_03_044134_create_user_skill_topics_table', 1),
(51, '2024_04_05_060046_create_countries_table', 1),
(52, '2024_04_05_060133_create_states_table', 1),
(53, '2024_04_05_060149_create_cities_table', 1),
(54, '2024_04_08_041719_create_instructor_requests_table', 1),
(55, '2024_04_08_042513_create_instructor_request_settings_table', 1),
(56, '2024_04_15_103628_create_course_categories_table', 1),
(57, '2024_04_15_112656_create_course_category_translations_table', 1),
(58, '2024_04_18_031942_create_course_languages_table', 1),
(59, '2024_04_18_044110_create_course_levels_table', 1),
(60, '2024_04_18_044125_create_course_level_translations_table', 1),
(61, '2024_04_18_070749_create_courses_table', 1),
(62, '2024_04_21_093245_create_course_partner_instructors_table', 1),
(63, '2024_04_21_094654_create_course_selected_levels_table', 1),
(64, '2024_04_21_094841_create_course_selected_languages_table', 1),
(65, '2024_04_21_095342_create_course_selected_filter_options_table', 1),
(66, '2024_04_22_114039_create_course_chapters_table', 1),
(67, '2024_04_23_090340_create_course_chapter_items_table', 1),
(68, '2024_04_23_090700_create_course_chapter_lessons_table', 1),
(69, '2024_04_24_093046_create_quizzes_table', 1),
(70, '2024_04_24_114441_create_quiz_questions_table', 1),
(71, '2024_04_28_034905_create_quiz_question_answers_table', 1),
(72, '2024_05_06_094927_create_order_items_table', 1),
(73, '2024_05_06_094946_create_enrollments_table', 1),
(74, '2024_05_12_035535_create_course_progress_table', 1),
(75, '2024_05_13_041532_create_quiz_results_table', 1),
(76, '2024_05_13_101033_create_lesson_questions_table', 1),
(77, '2024_05_13_101258_create_lesson_replies_table', 1),
(78, '2024_05_14_095807_create_announcements_table', 1),
(79, '2024_05_14_114640_create_course_reviews_table', 1),
(80, '2024_05_16_034644_create_certificate_builders_table', 1),
(81, '2024_05_16_041919_create_certificate_builder_items_table', 1),
(82, '2024_05_16_110701_create_badges_table', 1),
(83, '2024_05_20_052819_create_brands_table', 1),
(84, '2024_05_20_094331_create_featured_course_sections_table', 1),
(85, '2024_05_21_060612_create_featured_instructors_table', 1),
(86, '2024_05_21_060634_create_featured_instructor_translations_table', 1),
(87, '2024_05_26_032547_create_section_settings_table', 1),
(88, '2024_05_26_052359_create_footer_settings_table', 1),
(89, '2024_05_26_065953_create_social_links_table', 1),
(90, '2024_05_26_164008_create_contact_sections_table', 1),
(91, '2024_05_27_045919_create_custom_pages_table', 1),
(92, '2024_05_27_050016_create_custom_page_translations_table', 1),
(93, '2024_06_02_045115_add_softdelete_to_courses_table', 1),
(94, '2024_06_02_080423_create_course_delete_requests_table', 1),
(95, '2024_02_10_060044_create_configurations_table', 1),
(96, '2024_09_01_042120_create_course_live_classes_table', 1),
(97, '2024_09_01_042119_create_zoom_credentials_table', 1),
(98, '2024_09_04_122554_create_jitsi_settings_table', 1),
(99, '2024_09_10_103347_create_marketing_settings_table', 1),
(100, '2024_09_29_090219_create_instructor_request_setting_translations_table', 2),
(101, '2024_10_08_060425_create_homes_table', 3),
(102, '2024_10_08_060618_create_sections_table', 3),
(103, '2024_10_08_060636_create_section_translations_table', 3),
(110, '2024_12_09_064934_favorite_course_user', 4),
(111, '2024_12_10_051251_create_custom_addons_table', 4),
(112, '2025_01_13_082341_create_carts_table', 5),
(113, '2024_11_24_045801_create_bkash_p_g_models_table', 6),
(114, '2025_01_09_103147_create_crypto_p_g_table', 7),
(115, '2025_01_14_084523_create_mercadopagopg_table', 8),
(116, '2025_06_20_125738_create_marquees_table', 9),
(117, '2026_05_01_000000_fix_coach_staff_roles_added_by_type', 10),
(118, '2018_08_08_100000_create_telescope_entries_table', 11),
(119, '2025_01_20_000000_create_course_batches_table', 12),
(120, '2026_02_17_152920_create_coach_staff_role_table', 12),
(121, '2026_02_17_175146_create_coach_landing_pages_table', 12),
(123, '2026_02_20_105948_create_coach_staff_permissions_table', 12),
(124, '2026_02_24_111739_create_landing_page_enquiries_table', 12),
(125, '2026_02_17_175823_create_landing_sections_table', 13),
(126, '2026_05_01_191242_create_notifications_table', 14),
(127, '2026_05_04_112050_add_notification_preferences_to_users_table', 15),
(128, '2026_05_04_113328_add_two_factor_to_admins_table', 16),
(129, '2026_05_04_114416_add_two_factor_to_users_table', 17),
(130, '2026_05_04_063606_create_push_subscriptions_table', 18),
(131, '2026_05_04_121136_add_referral_to_users_table', 19),
(132, '2026_05_04_121140_create_referral_commissions_table', 19),
(133, '2026_05_04_000000_seed_notification_email_templates', 20),
(134, '2026_05_04_153800_create_membership_plans_table', 21),
(135, '2026_05_04_153810_create_user_memberships_table', 21),
(136, '2026_05_04_153820_add_referral_wallet_to_users_table', 22),
(137, '2026_05_04_153830_create_referral_wallet_transactions_table', 22),
(138, '2026_05_04_153840_create_referrals_table', 22),
(139, '2026_05_04_153850_create_referral_settings_table', 22),
(140, '2026_05_04_160000_seed_referral_reward_email_template', 23),
(141, '2026_05_04_170000_seed_membership_activated_email_template', 24),
(142, '2026_05_04_180000_seed_coach_trial_plan', 25),
(143, '2026_05_04_190000_seed_trial_expiring_email_template', 26),
(144, '2026_05_04_200000_seed_coach_trial_welcome_email_template', 27),
(145, '2026_05_04_210000_seed_default_paid_plans', 28),
(146, '2026_05_04_220000_make_inr_base_currency', 29),
(147, '2026_05_04_230000_add_perf_indexes', 30),
(148, '2026_05_05_140000_add_idempotency_uniques_to_orders_and_enrollments', 31),
(149, '2026_05_05_150000_add_fk_indexes_for_perf', 32),
(150, '2026_05_05_160000_add_usage_limits_to_coupons', 33),
(151, '2026_05_05_151812_create_sessions_table', 34),
(152, '2026_05_05_152319_create_cache_table', 35),
(153, '2026_05_05_170000_encrypt_secret_settings', 36),
(154, '2026_03_01_000000_add_role_slug_to_coach_staff_roles', 37),
(155, '2026_05_06_110000_add_zoom_oauth_token_columns', 37),
(156, '2026_05_06_120000_encrypt_zoom_credentials', 37),
(157, '2026_05_06_130000_encrypt_jitsi_settings', 37),
(158, '2026_05_06_140000_add_forget_password_token_expiry', 37),
(159, '2026_05_07_180000_drop_jitsi_settings_table', 37),
(160, '2026_05_07_180100_remove_legacy_jitsi_live_class_rows', 37),
(161, '2026_05_07_180200_narrow_course_live_classes_type_enum', 37),
(162, '2026_05_07_190000_drop_configurations_table', 37),
(163, '2026_05_07_191000_drop_custom_addons_table', 37),
(164, '2026_05_07_200000_add_health_columns_to_zoom_credentials', 38),
(165, '2026_05_07_210000_add_verification_columns_to_course_live_classes', 39),
(166, '2026_05_07_220000_backfill_and_constrain_course_live_classes_course_id', 40),
(167, '2026_05_07_230000_create_live_class_attendances_table', 41),
(168, '2026_05_07_240000_create_lesson_notes_table', 42),
(169, '2026_05_07_250000_create_live_class_recordings_table', 43),
(170, '2026_05_07_300000_clear_legacy_passcodes_from_course_live_classes', 44),
(171, '2026_05_08_100000_zoom_credentials_to_s2s_oauth', 45),
(172, '2026_05_08_110000_add_sdk_credentials_to_zoom_credentials', 46),
(173, '2026_05_08_140000_add_meeting_sdk_columns_to_zoom_credentials', 47),
(174, '2026_05_08_150000_ensure_zoom_refresh_token_column', 48),
(175, '2026_05_11_170000_add_attendance_threshold_to_courses', 49),
(176, '2026_05_12_100000_extend_landing_page_enquiries_for_crm', 50),
(177, '2026_05_12_100100_create_lead_notes_table', 51),
(178, '2026_05_12_110000_create_lead_audit_logs_and_email_sends', 52),
(179, '2026_05_12_120000_add_manual_override_to_live_class_attendances', 53),
(180, '2026_05_12_130000_create_live_class_reminders_sent', 54),
(181, '2026_05_12_140000_add_template_mapping_to_landing_page_enquiries', 55),
(182, '2026_05_12_150000_add_utm_to_landing_page_enquiries', 56),
(183, '2026_05_12_160000_add_forget_password_token_expiry_to_admins', 57),
(184, '2026_05_13_100000_create_push_devices_table', 58),
(185, '2026_05_15_175951_seed_missing_admin_permissions', 59),
(186, '2026_05_18_120000_seed_additional_admin_roles', 60),
(187, '2026_05_18_140000_add_batch_id_to_enrollments', 61),
(188, '2026_05_18_140100_extend_announcements_with_batch_status_sent_at', 61),
(189, '2026_05_18_140200_seed_announcement_admin_permissions', 61),
(190, '2026_05_18_160000_create_announcement_batches_pivot', 62),
(191, '2026_05_18_170000_seed_notif_batch_announcement_email_template', 63),
(192, '2026_05_18_180000_add_announcement_manager_role', 64),
(193, '2026_05_18_190000_add_scheduling_to_announcements', 65),
(194, '2026_05_18_191000_add_is_pinned_to_announcements', 66),
(195, '2026_05_18_192000_create_announcement_reads_table', 67),
(196, '2026_05_18_193000_create_announcement_attachments_table', 68),
(197, '2026_05_18_194000_seed_low_attendance_email_template', 69),
(198, '2026_05_18_200000_add_verified_attendance_columns', 70),
(199, '2026_05_18_210000_clear_coach_id_on_student_users', 71),
(200, '2026_05_18_220000_add_attendance_min_percent_to_course_batches', 72),
(201, '2026_05_18_230000_add_session_token_to_live_class_attendances', 73),
(202, '2026_05_18_240000_add_demo_columns_to_users', 74),
(203, '2026_05_18_250000_add_commission_columns_to_users', 75),
(204, '2026_05_18_260000_dedupe_course_slugs_and_add_unique', 76),
(205, '2026_05_19_100000_widen_courses_type_enum', 77),
(206, '2026_05_19_110000_make_enrollments_order_id_nullable', 78),
(207, '2026_05_19_120000_backfill_courses_type_for_existing_live_classes', 79),
(208, '2026_05_19_130000_create_fee_demands_table', 80),
(209, '2026_05_19_140000_create_fee_payments_table', 80),
(210, '2026_05_20_100000_add_audience_type_to_announcements', 81),
(211, '2026_05_20_120000_create_teacher_batch_assignments_table', 82),
(212, '2026_05_20_140000_seed_teacher_panel_permission_slugs', 83),
(213, '2026_05_20_130000_create_direct_messages_table', 84),
(214, '2026_05_21_100000_create_coach_student_links_table', 85),
(215, '2026_05_21_120000_create_coach_brand_settings_table', 86),
(216, '2026_05_21_130000_create_coach_domains_table', 87),
(217, '2026_05_21_140000_add_email_config_to_coach_brand_settings', 88),
(218, '2026_05_21_150000_backfill_coach_subdomains', 89),
(219, '2026_05_22_100000_audit_db_integrity_cleanup', 90),
(220, '2026_05_25_100000_coach_site_section_builder', 91),
(221, '2026_05_25_200000_coach_site_full_customization', 92),
(222, '2026_05_25_300000_theme_system_foundation', 93),
(223, '2026_05_26_100000_add_preview_seconds_to_courses', 94),
(224, '2026_05_26_204953_add_tenancy_coach_id_to_user_facing_tables', 95),
(225, '2026_05_27_100000_add_gift_claim_to_coach_student_links_source_enum', 96),
(226, '2026_05_27_150000_seed_announcement_store_update_permissions', 97),
(227, '2026_05_28_100000_seed_subscriptions_management_permission', 98),
(228, '2026_05_28_110000_seed_instructor_request_update_permission', 99),
(229, '2026_05_29_120000_add_missing_composite_indexes', 100),
(230, '2026_05_29_130000_orphan_cleanup_pre_fk', 101),
(231, '2026_05_29_140000_add_missing_foreign_keys', 101),
(235, '2026_06_01_120000_change_commission_rate_to_decimal', 102),
(236, '2026_06_01_120100_change_invoice_id_to_varchar_and_index', 102),
(237, '2026_06_01_120200_change_batch_id_to_bigint_with_fk', 102),
(238, '2026_06_02_120000_create_activity_logs_table', 103),
(239, '2026_06_02_120100_seed_activity_log_permission', 104),
(240, '2026_06_03_120000_extend_referral_commissions_lifecycle', 105),
(241, '2026_06_05_120000_add_lifecycle_columns_to_course_live_classes', 106),
(242, '2026_06_05_130000_enrich_live_class_scheduled_email_template', 107),
(243, '2026_06_06_120000_allow_multiple_batches_per_course_enrollment', 108),
(244, '2026_06_09_120000_add_status_to_coach_domains_and_create_events', 109),
(245, '2026_06_09_130000_seed_custom_domain_admin_permissions', 109),
(246, '2026_06_12_120000_add_tenant_filter_indexes', 110),
(247, '2026_06_12_130000_add_coach_id_to_certificate_builders', 111),
(248, '2026_06_12_140000_add_funnel_fields_to_landing_page_enquiries', 112),
(249, '2026_06_13_100000_create_tax_tables', 113),
(250, '2026_06_13_100100_add_tax_fields_to_orders_courses', 113),
(251, '2026_06_13_120000_add_tax_components', 114),
(252, '2026_06_16_100000_fix_notif_batch_announcement_placeholders', 115),
(253, '2026_06_16_130000_republish_theme_switch_broken_sites', 116),
(254, '2026_06_16_140000_create_coach_theme_settings', 117),
(255, '2026_06_16_200000_add_coach_id_to_coupons', 118),
(256, '2026_06_16_210000_tenant_fk_integrity', 119),
(257, '2026_06_16_220000_ensure_coupon_columns', 120),
(258, '2026_06_17_100000_create_coach_site_footers', 121),
(259, '2026_06_17_110000_create_coach_menus', 121),
(260, '2026_06_17_120000_add_layout_to_coach_menu_items', 122),
(261, '2026_06_17_130000_drop_telescope_tables', 123),
(262, '2026_06_22_100000_seed_missing_notif_email_templates', 124),
(263, '2026_06_22_110000_create_notification_email_logs_table', 125),
(264, '2026_06_22_120000_add_follow_up_reminded_at_to_landing_page_enquiries', 126),
(265, '2026_06_22_130000_create_user_login_devices_table', 127),
(266, '2026_06_22_140000_create_coach_active_meetings_table', 128),
(267, '2026_06_23_120000_add_typography_config_to_coach_site_settings', 129),
(268, '2026_06_23_140000_create_coach_blogs_table', 130),
(269, '2026_06_24_120000_create_coach_pricing_enquiries_table', 131),
(270, '2026_06_24_140000_add_plan_pricing_fields_to_membership_plans', 132),
(271, '2026_06_25_000000_create_page_template_tables', 133),
(272, '2026_06_25_160000_add_user_role_status_perf_indexes', 134),
(273, '2026_06_25_180000_add_trial_tracking_to_users', 135),
(274, '2026_06_26_120000_add_booking_metadata_to_pricing_enquiries', 136),
(275, '2026_06_26_140000_seed_registration_and_live_class_email_templates', 137),
(276, '2026_06_26_160000_create_coach_email_templates_table', 138),
(277, '2026_06_26_180000_add_wallet_settled_at_to_orders', 139),
(278, '2026_06_29_120000_create_coach_payment_gateways_table', 140),
(279, '2026_06_29_120100_add_gateway_owner_metadata_to_orders', 140),
(280, '2026_06_30_120000_seed_course_sale_to_coach_email_template', 141),
(281, '2026_06_30_140000_create_live_class_start_notifications_table', 142),
(282, '2026_06_30_140100_seed_live_class_started_email_template', 142),
(283, '2026_07_02_120000_widen_course_live_classes_type_enum_external', 143),
(284, '2026_07_03_120000_create_coach_trial_session_tables', 144),
(285, '2026_07_03_120100_seed_trial_session_email_templates', 144),
(286, '2026_07_03_120200_seed_trial_session_admin_permission', 144),
(287, '2026_07_03_130000_create_instant_meetings_table', 144),
(288, '2026_07_03_130100_seed_instant_meeting_email_template', 144),
(289, '2026_07_03_140000_add_student_link_to_coach_trial_enquiries', 144),
(290, '2026_07_03_140100_seed_trial_student_welcome_email_template', 144),
(291, '2026_07_04_120000_create_staff_permission_overrides_table', 144),
(292, '2026_07_04_120100_seed_coach_permission_catalog', 144),
(293, '2026_07_06_100000_add_my_plan_permission', 144),
(294, '2026_07_08_140000_add_certificate_enterprise_style_and_credentials', 145),
(295, '2026_07_09_120000_add_certificate_template_and_accent', 146),
(296, '2026_07_09_130000_add_certificate_typography_layout', 147),
(297, '2026_07_09_150000_add_unique_index_to_email_templates_name', 148),
(298, '2026_07_09_160000_strip_hardcoded_brand_currency_from_email_templates', 149),
(299, '2026_07_09_170000_seed_payment_failed_email_templates', 150),
(300, '2026_07_09_180000_polish_email_template_copy', 151),
(301, '2026_07_10_120000_add_coach_orders_create_permission', 152),
(302, '2026_07_10_130000_add_coach_and_smtp_source_to_email_sends', 153),
(303, '2026_07_10_140000_add_reference_and_upi_to_fee_payments', 154),
(304, '2026_07_11_120000_create_offline_payments_table', 155),
(305, '2026_07_11_130000_add_offline_payment_config', 156),
(306, '2026_07_11_140000_add_offline_receipt_email_toggle', 157),
(307, '2026_07_11_150000_add_tax_to_offline_payments', 158),
(308, '2026_07_13_120000_add_payment_fields_to_pricing_enquiries', 159),
(309, '2026_07_13_120100_create_coach_pricing_payments_table', 160),
(310, '2026_07_14_120000_seed_pricing_payment_email_template', 161),
(311, '2026_07_14_120100_seed_pricing_student_receipt_email_template', 162),
(312, '2026_07_13_120200_add_pricing_collect_payment_toggle', 163),
(313, '2026_07_14_130000_seed_booking_enquiry_email_templates', 164),
(314, '2026_07_15_120000_create_coach_trainers_table', 165),
(315, '2026_07_15_120100_create_trainer_session_packages_table', 165),
(316, '2026_07_15_130000_add_trainer_booking_cols_to_pricing_enquiries', 165),
(317, '2026_07_15_140000_seed_trainers_permission_catalog', 165),
(318, '2026_07_15_150000_add_certificate_and_booking_config_to_trainers', 166),
(319, '2026_07_15_160000_create_student_batch_assignments_table', 167),
(320, '2026_07_15_170000_seed_student_batch_permission_catalog', 167),
(321, '2026_07_15_180000_create_student_temporary_slots_table', 168),
(322, '2026_07_15_190000_seed_temp_slot_permission_catalog', 168),
(323, '2026_07_16_120000_add_social_pinterest_to_coach_site_settings', 169),
(324, '2026_07_30_155308_create_attendances_table', 170),
(325, '2026_07_30_160001_create_departments_table', 171),
(326, '2026_07_30_160002_create_employee_profiles_table', 171),
(327, '2026_07_30_160003_create_attendance_regularizations_table', 172),
(328, '2026_07_30_160004_create_leave_types_table', 173),
(329, '2026_07_30_160005_create_leaves_table', 173),
(330, '2026_07_30_160006_create_leave_balances_table', 173),
(331, '2026_07_30_160007_create_salary_structures_table', 174),
(332, '2026_07_30_160008_create_salary_components_table', 174),
(333, '2026_07_30_160009_create_salary_revisions_table', 174),
(334, '2026_07_30_160010_create_payroll_runs_table', 174),
(335, '2026_07_30_160011_create_payroll_items_table', 174),
(336, '2026_07_30_160012_create_loans_advances_table', 174),
(337, '2026_07_30_160013_create_bonuses_table', 174),
(338, '2026_07_31_170000_add_employee_personal_and_statutory_details', 175),
(339, '2026_08_18_170001_create_companies_table', 176),
(340, '2026_08_18_170002_create_company_user_table', 176),
(341, '2026_08_18_170010_add_company_id_to_hremployee_tables', 177),
(342, '2026_08_18_170011_add_company_id_to_attendance_tables', 177),
(343, '2026_08_18_170012_add_company_id_to_leave_tables', 177),
(344, '2026_08_18_170013_add_company_id_to_payroll_tables', 177),
(345, '2026_08_18_170020_backfill_company_id', 178),
(346, '2026_08_18_170030_scope_unique_indexes_to_company', 179),
(347, '2026_08_24_090000_add_letterhead_to_companies_table', 180),
(348, '2026_08_25_120000_add_contact_to_companies_table', 181),
(349, '2026_08_31_133929_drop_lms_tables', 182);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_permissions`
--

CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `model_has_permissions`
--

INSERT INTO `model_has_permissions` (`permission_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\Admin', 3),
(2, 'App\\Models\\Admin', 3),
(3, 'App\\Models\\Admin', 3),
(4, 'App\\Models\\Admin', 3),
(5, 'App\\Models\\Admin', 3),
(6, 'App\\Models\\Admin', 3),
(7, 'App\\Models\\Admin', 3),
(8, 'App\\Models\\Admin', 3),
(9, 'App\\Models\\Admin', 3),
(10, 'App\\Models\\Admin', 3),
(11, 'App\\Models\\Admin', 3),
(12, 'App\\Models\\Admin', 3),
(13, 'App\\Models\\Admin', 3),
(14, 'App\\Models\\Admin', 3),
(15, 'App\\Models\\Admin', 3),
(16, 'App\\Models\\Admin', 3),
(17, 'App\\Models\\Admin', 3),
(18, 'App\\Models\\Admin', 3),
(19, 'App\\Models\\Admin', 3),
(20, 'App\\Models\\Admin', 3),
(21, 'App\\Models\\Admin', 3),
(22, 'App\\Models\\Admin', 3),
(23, 'App\\Models\\Admin', 3),
(24, 'App\\Models\\Admin', 3),
(25, 'App\\Models\\Admin', 3),
(26, 'App\\Models\\Admin', 3),
(27, 'App\\Models\\Admin', 3),
(28, 'App\\Models\\Admin', 3),
(29, 'App\\Models\\Admin', 3),
(30, 'App\\Models\\Admin', 3),
(31, 'App\\Models\\Admin', 3),
(32, 'App\\Models\\Admin', 3),
(33, 'App\\Models\\Admin', 3),
(34, 'App\\Models\\Admin', 3),
(35, 'App\\Models\\Admin', 3),
(36, 'App\\Models\\Admin', 3),
(37, 'App\\Models\\Admin', 3),
(38, 'App\\Models\\Admin', 3),
(39, 'App\\Models\\Admin', 3),
(40, 'App\\Models\\Admin', 3),
(41, 'App\\Models\\Admin', 3),
(42, 'App\\Models\\Admin', 3),
(43, 'App\\Models\\Admin', 3),
(44, 'App\\Models\\Admin', 3),
(45, 'App\\Models\\Admin', 3),
(46, 'App\\Models\\Admin', 3),
(47, 'App\\Models\\Admin', 3),
(48, 'App\\Models\\Admin', 3),
(49, 'App\\Models\\Admin', 3),
(50, 'App\\Models\\Admin', 3),
(51, 'App\\Models\\Admin', 3),
(52, 'App\\Models\\Admin', 3),
(53, 'App\\Models\\Admin', 3),
(54, 'App\\Models\\Admin', 3),
(55, 'App\\Models\\Admin', 3),
(56, 'App\\Models\\Admin', 3),
(57, 'App\\Models\\Admin', 3),
(58, 'App\\Models\\Admin', 3),
(59, 'App\\Models\\Admin', 3),
(60, 'App\\Models\\Admin', 3),
(61, 'App\\Models\\Admin', 3),
(62, 'App\\Models\\Admin', 3),
(63, 'App\\Models\\Admin', 3),
(64, 'App\\Models\\Admin', 3),
(65, 'App\\Models\\Admin', 3),
(66, 'App\\Models\\Admin', 3),
(67, 'App\\Models\\Admin', 3),
(68, 'App\\Models\\Admin', 3),
(69, 'App\\Models\\Admin', 3),
(70, 'App\\Models\\Admin', 3),
(71, 'App\\Models\\Admin', 3),
(72, 'App\\Models\\Admin', 3),
(73, 'App\\Models\\Admin', 3),
(74, 'App\\Models\\Admin', 3),
(75, 'App\\Models\\Admin', 3),
(78, 'App\\Models\\Admin', 3),
(79, 'App\\Models\\Admin', 3),
(80, 'App\\Models\\Admin', 3),
(81, 'App\\Models\\Admin', 3),
(82, 'App\\Models\\Admin', 3),
(83, 'App\\Models\\Admin', 3),
(84, 'App\\Models\\Admin', 3),
(85, 'App\\Models\\Admin', 3),
(86, 'App\\Models\\Admin', 3),
(87, 'App\\Models\\Admin', 3),
(88, 'App\\Models\\Admin', 3),
(89, 'App\\Models\\Admin', 3),
(90, 'App\\Models\\Admin', 3),
(91, 'App\\Models\\Admin', 3),
(92, 'App\\Models\\Admin', 3),
(93, 'App\\Models\\Admin', 3),
(94, 'App\\Models\\Admin', 3),
(95, 'App\\Models\\Admin', 3),
(96, 'App\\Models\\Admin', 3),
(97, 'App\\Models\\Admin', 3),
(98, 'App\\Models\\Admin', 3),
(99, 'App\\Models\\Admin', 3),
(100, 'App\\Models\\Admin', 3),
(101, 'App\\Models\\Admin', 3),
(102, 'App\\Models\\Admin', 3),
(103, 'App\\Models\\Admin', 3),
(104, 'App\\Models\\Admin', 3),
(105, 'App\\Models\\Admin', 3),
(106, 'App\\Models\\Admin', 3),
(107, 'App\\Models\\Admin', 3),
(108, 'App\\Models\\Admin', 3),
(109, 'App\\Models\\Admin', 3),
(110, 'App\\Models\\Admin', 3),
(111, 'App\\Models\\Admin', 3),
(112, 'App\\Models\\Admin', 3),
(113, 'App\\Models\\Admin', 3),
(114, 'App\\Models\\Admin', 3),
(115, 'App\\Models\\Admin', 3),
(116, 'App\\Models\\Admin', 3),
(117, 'App\\Models\\Admin', 3),
(118, 'App\\Models\\Admin', 3),
(119, 'App\\Models\\Admin', 3),
(120, 'App\\Models\\Admin', 3),
(121, 'App\\Models\\Admin', 3),
(122, 'App\\Models\\Admin', 3),
(123, 'App\\Models\\Admin', 3),
(124, 'App\\Models\\Admin', 3),
(125, 'App\\Models\\Admin', 3),
(126, 'App\\Models\\Admin', 3),
(127, 'App\\Models\\Admin', 3),
(128, 'App\\Models\\Admin', 3),
(129, 'App\\Models\\Admin', 3),
(130, 'App\\Models\\Admin', 3),
(131, 'App\\Models\\Admin', 3),
(132, 'App\\Models\\Admin', 3),
(133, 'App\\Models\\Admin', 3),
(134, 'App\\Models\\Admin', 3),
(135, 'App\\Models\\Admin', 3),
(136, 'App\\Models\\Admin', 3),
(137, 'App\\Models\\Admin', 3),
(138, 'App\\Models\\Admin', 3),
(139, 'App\\Models\\Admin', 3),
(140, 'App\\Models\\Admin', 3),
(141, 'App\\Models\\Admin', 3),
(142, 'App\\Models\\Admin', 3),
(143, 'App\\Models\\Admin', 3),
(144, 'App\\Models\\Admin', 3),
(145, 'App\\Models\\Admin', 3),
(146, 'App\\Models\\Admin', 3),
(147, 'App\\Models\\Admin', 3),
(148, 'App\\Models\\Admin', 3),
(149, 'App\\Models\\Admin', 3),
(150, 'App\\Models\\Admin', 3),
(151, 'App\\Models\\Admin', 3),
(152, 'App\\Models\\Admin', 3),
(153, 'App\\Models\\Admin', 3),
(154, 'App\\Models\\Admin', 3),
(155, 'App\\Models\\Admin', 3),
(156, 'App\\Models\\Admin', 3);

-- --------------------------------------------------------

--
-- Table structure for table `model_has_roles`
--

CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `model_has_roles`
--

INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(1, 'App\\Models\\Admin', 1);

-- --------------------------------------------------------

--
-- Table structure for table `multi_currencies`
--

CREATE TABLE `multi_currencies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `currency_name` varchar(255) NOT NULL,
  `country_code` varchar(255) NOT NULL,
  `currency_code` varchar(255) NOT NULL,
  `currency_icon` varchar(255) NOT NULL,
  `is_default` varchar(255) NOT NULL,
  `currency_rate` float NOT NULL,
  `currency_position` varchar(255) NOT NULL DEFAULT 'before_price',
  `status` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `multi_currencies`
--

INSERT INTO `multi_currencies` (`id`, `currency_name`, `country_code`, `currency_code`, `currency_icon`, `is_default`, `currency_rate`, `currency_position`, `status`, `created_at`, `updated_at`) VALUES
(1, '$-USD', 'US', 'USD', '$', 'no', 0.010884, 'before_price', 'inactive', '2024-08-14 21:23:17', '2026-03-10 09:53:50'),
(2, '₦-Naira', 'NG', 'NGN', '₦', 'no', 4.54234, 'before_price', 'inactive', '2024-08-14 21:23:17', '2025-04-25 10:12:25'),
(3, '₹-Rupee', 'IN', 'INR', '₹', 'yes', 1, 'before_price', 'active', '2024-08-14 21:23:17', '2026-03-10 09:53:50'),
(4, '₱-Peso', 'PH', 'PHP', '₱', 'no', 0.599369, 'before_price', 'inactive', '2024-08-14 21:23:17', '2025-04-25 10:13:08'),
(5, '$-CAD', 'CA', 'CAD', '$', 'no', 0.013822, 'before_price', 'inactive', '2024-08-14 21:23:17', '2026-03-10 09:23:11'),
(6, '৳-Taka', 'BD', 'BDT', '৳', 'no', 0.870701, 'before_price', 'inactive', '2024-08-14 21:23:17', '2025-04-25 10:11:30');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) UNSIGNED NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`) VALUES
('005956f9-e178-464e-a9ac-8992bb3267e9', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 49.36.189.33. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-24 15:36:53', '2026-07-24 15:36:53'),
('00709a07-9ca6-46b6-8dcb-1940397d7cb6', 'App\\Notifications\\InstantMeetingInviteToStudent', 'App\\Models\\User', 1218, '{\"title\":\"Your coach is inviting you to a meeting\",\"body\":\"Virendra Kumar wants to start a 1:1 session with you now. Tap to join.\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instant-meeting\\/9\\/room\",\"icon\":\"fa-video\",\"iconColor\":\"#16a34a\"}', NULL, '2026-07-13 18:54:40', '2026-07-13 18:54:40'),
('00a5c5dc-ae97-476f-a58c-f37f722813c0', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 122.161.65.162. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-18 13:38:40', '2026-07-18 12:51:41', '2026-07-18 13:38:40'),
('017778fd-28f8-4c31-a4c8-9fd7a556218c', 'App\\Notifications\\PricingBookingPaidToCoach', 'App\\Models\\User', 1124, '{\"title\":\"Payment received: Suresh Sarkar \\u2014 \\u20b930,000.00\",\"body\":\"Yoga Guru Virendra Kumar \\u00b7 Individual Plan \\u00b7 20 Session Validity 45 days\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-circle-check\",\"iconColor\":\"#16a34a\"}', '2026-07-16 10:43:23', '2026-07-15 15:10:14', '2026-07-16 10:43:23'),
('025e2bad-9a19-4a3f-84a9-23cd3b67ba02', 'App\\Notifications\\CoachAssignedCourseToStudent', 'App\\Models\\User', 1259, '{\"event\":\"coach_assigned_course\",\"title\":\"\\ud83c\\udf93 Hatha Yoga for Beginners: Build Strength, Flexibility & Inner Calm\",\"body\":\"Yoga Singh just enrolled you\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/student\\/enrolled-courses\",\"icon\":\"fa-graduation-cap\",\"iconColor\":\"#16a34a\",\"course_id\":282,\"order_id\":123,\"coach_id\":1255}', NULL, '2026-07-13 12:03:08', '2026-07-13 12:03:08'),
('026c4f22-ce4f-49d7-b741-f366001225f1', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 49.36.189.181. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-27 14:09:31', '2026-07-27 14:09:31'),
('043e294a-d94e-4a32-a319-88ee8dac10f4', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1233, '{\"title\":\"Your free trial ends today\",\"body\":\"Pick a plan to keep using coach features without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-13 14:30:08', '2026-07-13 14:30:08'),
('04522e2c-4b19-4724-abe7-126101595fff', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1233, '{\"title\":\"Your free trial ends tomorrow\",\"body\":\"Upgrade now to avoid losing access to courses + live classes.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-12 14:30:08', '2026-07-12 14:30:08'),
('051622ef-9fe3-4ba6-a910-1262be429483', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1124, '{\"title\":\"Your Enterprise has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-30 06:00:03', '2026-07-30 06:00:03'),
('07055771-aa6e-4ddc-8736-fa9dfef2c769', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1278, '{\"title\":\"New sale: Political Science\",\"body\":\"Gudda purchased \\\"Political Science\\\" \\u2014 999.00 INR.\",\"url\":\"https:\\/\\/cockroach-coach.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-27 13:52:44', '2026-07-21 17:58:16', '2026-07-27 13:52:44'),
('0aa2069e-d474-4c56-895e-4210d2bbde6c', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 106.221.235.15. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-19 13:52:30', '2026-07-19 13:52:30'),
('0ab48c31-097e-474d-91ba-7d87e2de496b', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1195, '{\"title\":\"Your Coach Free Trial has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-11 06:00:06', '2026-07-11 06:00:06'),
('0ab7151a-95d1-4425-96b4-b9bc68951bcb', 'App\\Notifications\\NewLandingPageEnquiryToCoach', 'App\\Models\\User', 1255, '{\"title\":\"New lead from your landing page\",\"body\":\"Suresh Sarkar is interested in \\\"General enquiry\\\"\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/instructor\\/landing-page-enquiry\\/37\\/show\",\"icon\":\"fa-bullseye\",\"iconColor\":\"#0d9488\"}', '2026-07-10 13:13:10', '2026-07-09 17:48:35', '2026-07-10 13:13:10'),
('0b56c1b5-899c-4bb5-a917-d888030b2045', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1261, '{\"title\":\"Your free trial ends in 3 days\",\"body\":\"Browse plans and pick one that fits.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-18 14:30:05', '2026-07-18 14:30:05'),
('0d141ce6-31cd-4597-a5c6-c798af6bfb33', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1196, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 122.161.65.162. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/photongears.io\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-18 10:54:22', '2026-07-18 10:54:22'),
('0ddcc45e-473f-4ad9-996c-a417ff10c0bb', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1276, '{\"title\":\"Your free trial ends tomorrow\",\"body\":\"Upgrade now to avoid losing access to courses + live classes.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-28 14:30:05', '2026-07-28 14:30:05'),
('0df32600-16b2-4b2c-a4a0-6f0312a54641', 'App\\Notifications\\PricingBookingPaidToCoach', 'App\\Models\\User', 1124, '{\"title\":\"Payment received: Suresh Sarkar \\u2014 \\u20b94,800.00\",\"body\":\"Online \\u00b7 Individual \\u00b7 3 Month\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-circle-check\",\"iconColor\":\"#16a34a\"}', '2026-07-14 18:42:58', '2026-07-14 14:00:15', '2026-07-14 18:42:58'),
('0e0609e3-f71c-4221-b586-4d15aa70e894', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 49.36.189.241. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-20 13:45:26', '2026-07-20 13:45:26'),
('0e267479-14f1-48ed-b020-5791a114091e', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1266, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 14.96.24.10. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-10 13:50:57', '2026-07-10 13:50:57'),
('0f58993f-ab2f-4aaa-8bfa-b85ec113e000', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1256, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 49.36.189.181. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-27 13:33:47', '2026-07-27 13:33:47'),
('0fd6bbfe-5378-4736-a91b-64122c014235', 'App\\Notifications\\ReferralRewardEarnedToUser', 'App\\Models\\User', 1255, '{\"title\":\"You earned a referral reward!\",\"body\":\"Kuch Bhi just activated their membership. \\u20b9100.00 credited to your referral wallet.\",\"url\":\"https:\\/\\/mbsguru.com\\/referral\",\"icon\":\"fa-gift\",\"iconColor\":\"#10b981\"}', '2026-07-21 12:39:28', '2026-07-21 12:29:23', '2026-07-21 12:39:28'),
('13e8ab10-ea2f-404f-920c-b90b6f52b9d3', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1260, '{\"title\":\"Your free trial ends tomorrow\",\"body\":\"Upgrade now to avoid losing access to courses + live classes.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-20 14:30:05', '2026-07-20 14:30:05'),
('150085fb-4000-43f7-8ef9-edd9367951d8', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1260, '{\"title\":\"Your Coach Free Trial has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-22 06:00:04', '2026-07-22 06:00:04'),
('15442e80-f012-431a-8e0f-52d8d8a57d54', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1257, '{\"title\":\"Your free trial ends today\",\"body\":\"Pick a plan to keep using coach features without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-21 14:30:06', '2026-07-21 14:30:06'),
('1751065e-cfb7-4112-8f0f-4d58feb78f7b', 'App\\Notifications\\ReferralRewardEarnedToUser', 'App\\Models\\User', 1255, '{\"title\":\"You earned a referral reward!\",\"body\":\"Cockroach Coach just activated their membership. \\u20b9100.00 credited to your referral wallet.\",\"url\":\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/referral\",\"icon\":\"fa-gift\",\"iconColor\":\"#10b981\"}', NULL, '2026-08-25 05:12:54', '2026-08-25 05:12:54'),
('19dbfe48-b906-42cc-8b77-0be5dde1d319', 'App\\Notifications\\StudentBatchAssignedToStudent', 'App\\Models\\User', 1264, '{\"event\":\"student_batch_assigned\",\"title\":\"\\ud83d\\udc65 Strength Yoga\",\"body\":\"Virendra Kumar assigned you to a batch\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/student\\/dashboard\",\"icon\":\"fa-users\",\"iconColor\":\"#4f46e5\",\"batch_id\":823,\"course_id\":277,\"coach_id\":1124}', '2026-07-17 12:58:53', '2026-07-16 10:23:37', '2026-07-17 12:58:53'),
('1a1f5397-b13e-4198-b97b-95df198ad815', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1258, '{\"title\":\"Your free trial ends tomorrow\",\"body\":\"Upgrade now to avoid losing access to courses + live classes.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-20 14:30:05', '2026-07-20 14:30:05'),
('1f3209d5-05ca-4408-a8b8-a7f76e58ab00', 'App\\Notifications\\BookingEnquiryToCoach', 'App\\Models\\User', 1124, '{\"title\":\"New booking: Virendra Strength yoga\",\"body\":\"Strength Yoga \\u00b7 05:00 AM \\u00b7 Pending\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-calendar-check\",\"iconColor\":\"#f97316\"}', '2026-07-17 17:58:28', '2026-07-16 18:27:43', '2026-07-17 17:58:28'),
('1f381d36-fe02-4584-a9c3-e248e7803170', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1278, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-08-31 08:18:55', '2026-08-31 08:18:55'),
('1f39fabd-efa7-4d27-ba13-e379deea544c', 'App\\Notifications\\PaymentDueReminderToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Payment pending \\u2014 order #yWQOmLK4IC\",\"body\":\"Your order has been waiting for payment for 3 days. Complete payment to activate your enrollment.\",\"url\":\"https:\\/\\/mbsguru.com\\/student\\/order-details\\/123\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-16 14:30:02', '2026-07-16 14:30:02'),
('20d81c9f-dfa8-4b16-a156-1516b51656ca', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1263, '{\"title\":\"Your free trial ends today\",\"body\":\"Pick a plan to keep using coach features without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-21 14:30:07', '2026-07-21 14:30:07'),
('213b1b4d-e80e-4f16-aae0-bc967cd87cdc', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1261, '{\"title\":\"Your free trial ends tomorrow\",\"body\":\"Upgrade now to avoid losing access to courses + live classes.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-20 14:30:05', '2026-07-20 14:30:05'),
('22cc9b0c-6bd3-4236-a4fc-85781e7f1c86', 'App\\Notifications\\BookingEnquiryToCoach', 'App\\Models\\User', 1124, '{\"title\":\"New booking: Suresh Sarkar\",\"body\":\"Pending\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-calendar-check\",\"iconColor\":\"#f97316\"}', '2026-07-16 10:43:23', '2026-07-15 15:28:40', '2026-07-16 10:43:23'),
('22fbc513-02d4-4cdb-bd9e-19fcf8c46f20', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1256, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 103.222.253.213. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/scc.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-21 19:10:07', '2026-07-17 12:06:12', '2026-07-21 19:10:07'),
('2414beed-aff8-460f-a2c7-f67cf46419af', 'App\\Notifications\\NewLessonQuestionToCoach', 'App\\Models\\User', 1255, '{\"title\":\"Yoga Singh Student asked: \\\"sad\\\"\",\"body\":\"[Meditation & Pranayama: Mindfulness for Inner Peace] asdasd\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/instructor\\/lesson-question\",\"icon\":\"fa-question-circle\",\"iconColor\":\"#3b82f6\"}', '2026-07-08 17:22:30', '2026-07-08 17:05:09', '2026-07-08 17:22:30'),
('267357a0-57d6-42d7-a6e5-10566aee2674', 'App\\Notifications\\BookingEnquiryToCoach', 'App\\Models\\User', 1124, '{\"title\":\"New booking: Santosh DHS\",\"body\":\"Strength Yoga \\u00b7 05:00 AM \\u00b7 Pending\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-calendar-check\",\"iconColor\":\"#f97316\"}', '2026-07-15 12:33:08', '2026-07-15 11:14:34', '2026-07-15 12:33:08'),
('2a00af17-5b3f-497b-b1b2-8939952f6a0d', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1256, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 122.161.76.206. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-15 22:38:42', '2026-07-09 11:59:55', '2026-07-15 22:38:42'),
('2b70b359-81e1-482d-a914-b29147c47102', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1278, '{\"title\":\"New sale: Political Science\",\"body\":\"Cockroach Student purchased \\\"Political Science\\\" \\u2014 999.00 INR.\",\"url\":\"https:\\/\\/cockroach-coach.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-27 13:52:44', '2026-07-21 12:57:52', '2026-07-27 13:52:44'),
('2f135ba7-4b1a-4072-ab4f-aefc8714bdc0', 'App\\Notifications\\LiveClassScheduledToStudent', 'App\\Models\\User', 1259, '{\"title\":\"New live class scheduled\",\"body\":\"\\\"Hatha Yoga for Beginners: Build Strength, Flexibil...\\\" \\u2014 Test Class (Morning Yoga Foundation Batch) on 10 Jul, 2026 - 04:51 pm\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/student\\/live-classes\",\"icon\":\"fa-video\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-10 16:50:56', '2026-07-10 16:50:56'),
('2f3430e1-9800-4556-993b-7de40383f60e', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1268, '{\"title\":\"Your free trial ends in 3 days\",\"body\":\"Browse plans and pick one that fits.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-20 14:30:05', '2026-07-20 14:30:05'),
('2f45843d-e992-4494-bc08-346768d9d989', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 49.36.213.224. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-19 13:58:27', '2026-07-19 13:58:27'),
('2fc9119f-315e-46d6-acd1-e85e227644d4', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1182, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 127.0.0.1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"http:\\/\\/localhost\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-08-31 08:25:15', '2026-08-31 08:25:15'),
('3271ae15-9b69-456e-a91a-c39e942701cc', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1256, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 49.36.189.33. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-24 16:29:48', '2026-07-24 16:29:48'),
('327319f0-0182-4c4d-8208-d981092a60ee', 'App\\Notifications\\CoachAssignedCourseToStudent', 'App\\Models\\User', 1269, '{\"event\":\"coach_assigned_course\",\"title\":\"\\ud83c\\udf93 Customer Relationship Management Essentials\",\"body\":\"Slidus Coach just enrolled you\",\"url\":\"https:\\/\\/mbsguru.com\\/student\\/enrolled-courses\",\"icon\":\"fa-graduation-cap\",\"iconColor\":\"#16a34a\",\"course_id\":257,\"order_id\":114,\"coach_id\":1213}', NULL, '2026-07-09 13:40:37', '2026-07-09 13:40:37'),
('34c39cdd-8300-4e51-9175-c27439b8a58f', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1213, '{\"title\":\"Your Coach Free Trial has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-10 06:00:06', '2026-07-10 06:00:06'),
('35c1fe6d-73dc-405c-8431-ec882845243c', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1278, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"http:\\/\\/cockroach-coach.mbsguru.com\\/laravel\\/erpsystem\\/public\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-31 13:45:37', '2026-07-30 09:40:07', '2026-07-31 13:45:37'),
('3680d803-e38a-4348-b582-b1292f0382a4', 'App\\Notifications\\NewLessonQuestionToCoach', 'App\\Models\\User', 1255, '{\"title\":\"Yoga Singh Student asked: \\\"sad\\\"\",\"body\":\"[Meditation & Pranayama: Mindfulness for Inner Peace] asdasd\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/instructor\\/lesson-question\",\"icon\":\"fa-question-circle\",\"iconColor\":\"#3b82f6\"}', '2026-07-08 17:22:30', '2026-07-08 17:05:09', '2026-07-08 17:22:30'),
('3829c43d-b338-4898-8f6b-95d632e24e8b', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1261, '{\"title\":\"Your Coach Free Trial has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-22 06:00:04', '2026-07-22 06:00:04'),
('3c945395-878d-47b6-b4a7-3ff8ab549b57', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1195, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 122.161.65.162. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/photongears.io\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-18 10:56:32', '2026-07-18 10:56:32'),
('3f6c5160-8277-4986-9d7f-b082211f2c42', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1273, '{\"title\":\"New sale: Advanced Hatha Yoga Certification\",\"body\":\"Advaityog Student purchased \\\"Advanced Hatha Yoga Certification\\\" \\u2014 2,500.00 INR.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-18 13:38:40', '2026-07-13 10:38:11', '2026-07-18 13:38:40'),
('402e7802-ed17-4adc-8509-26fe4294179a', 'App\\Notifications\\LeadFollowUpReminderToStaff', 'App\\Models\\User', 1273, '{\"title\":\"Follow-up due: Jessamine Griffith Rai\",\"body\":\"It\'s time to follow up with Jessamine Griffith Rai about Full Stack Web Development with React. Open the lead to log your call.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/instructor\\/landing-page-enquiry\\/45\\/show\",\"icon\":\"fa-bell\",\"iconColor\":\"#0ea5e9\"}', '2026-07-18 13:38:40', '2026-07-13 20:45:02', '2026-07-18 13:38:40'),
('41f1cc3f-0303-49f3-96dc-f5a0fe89e4d4', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1218, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 122.161.50.151. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-16 09:02:36', '2026-07-16 09:02:36'),
('4216be2e-ea93-4c45-b063-de9e7169b107', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1124, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 125.99.186.242. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-17 15:25:55', '2026-07-17 15:25:55'),
('44ad5388-c9d6-4d8d-87bc-a227f129bd37', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1268, '{\"title\":\"Your free trial ends today\",\"body\":\"Pick a plan to keep using coach features without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-23 14:30:05', '2026-07-23 14:30:05'),
('457a1b10-7c8a-4fbf-aed9-cc0654407617', 'App\\Notifications\\CourseCompletedToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Course completed: Complete Yoga Journey \\u2013 From Beginner to Advanced\",\"body\":\"Great work! Your certificate is ready to download.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/student\\/enrolled-courses\",\"icon\":\"fa-trophy\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-20 12:12:45', '2026-07-20 12:12:45'),
('45fab32f-d6be-477c-9f29-62a1ad0a1711', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1124, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 122.161.50.151. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-16 10:43:23', '2026-07-16 09:00:02', '2026-07-16 10:43:23'),
('4691d23e-0152-4a9b-ba39-f13eb1d28148', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1256, '{\"title\":\"Your free trial ends in 3 days\",\"body\":\"Browse plans and pick one that fits.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#3b82f6\"}', '2026-07-21 19:10:07', '2026-07-18 14:30:05', '2026-07-21 19:10:07'),
('472524c9-4a13-4a51-8254-9decc2f9aeb7', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1278, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"http:\\/\\/cockroach-coach.mbsguru.com\\/laravel\\/erpsystem\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-08-03 10:39:55', '2026-08-03 10:39:55'),
('478ec703-983a-414e-8183-3ecb6b0567b6', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 106.192.192.38. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-18 13:38:40', '2026-07-12 10:27:54', '2026-07-18 13:38:40'),
('4be2a412-9097-4eea-a1de-3f23838191ed', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1258, '{\"title\":\"Your Coach Free Trial has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-22 06:00:04', '2026-07-22 06:00:04'),
('505ce066-1fa9-4061-b8b2-aaa90efd22be', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1124, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 106.219.238.46. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-17 17:58:38', '2026-07-16 16:46:16', '2026-07-17 17:58:38'),
('50ac80c9-fa5b-470b-a37c-176ad407353c', 'App\\Notifications\\CoachTrialWelcomeToUser', 'App\\Models\\User', 1280, '{\"title\":\"Welcome \\u2014 your free trial is active\",\"body\":\"Full coach access until Aug 04, 2026.\",\"url\":\"https:\\/\\/mbsguru.com\\/instructor\\/dashboard\",\"icon\":\"fa-flask\",\"iconColor\":\"#7c3aed\"}', NULL, '2026-07-21 12:26:07', '2026-07-21 12:26:07'),
('528578ae-904a-49f0-aff1-874c3048d9cb', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1255, '{\"title\":\"New sale: Hatha Yoga for Beginners: Build Strength, Flexibil...\",\"body\":\"Yoga Singh Student purchased \\\"Hatha Yoga for Beginners: Build Strength, Flexibil...\\\" \\u2014 3,300.00 INR.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-20 11:57:23', '2026-07-10 16:49:57', '2026-07-20 11:57:23'),
('528ba8fb-fdd0-4b3f-a48f-1012680666f1', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1255, '{\"title\":\"New sale: Complete Yoga Journey \\u2013 From Beginner to Advanced\",\"body\":\"Yoga Singh Student purchased \\\"Complete Yoga Journey \\u2013 From Beginner to Advanced\\\" \\u2014 999.00 INR.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-08 17:22:30', '2026-07-08 17:08:11', '2026-07-08 17:22:30'),
('538c7b4c-c6c0-4e1d-9db2-740bf8d0db2a', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1264, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 103.222.253.213. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-17 12:58:53', '2026-07-17 10:23:48', '2026-07-17 12:58:53'),
('54c2fb87-0d94-4af0-aa43-0ba28bbce904', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 49.43.170.169. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-18 13:38:40', '2026-07-13 11:11:14', '2026-07-18 13:38:40'),
('54e7c18a-e6aa-4ead-97dd-c098ae4b7930', 'App\\Notifications\\CoachTrialWelcomeToUser', 'App\\Models\\User', 1278, '{\"title\":\"Welcome \\u2014 your free trial is active\",\"body\":\"Full coach access until Aug 04, 2026.\",\"url\":\"https:\\/\\/mbsguru.com\\/instructor\\/dashboard\",\"icon\":\"fa-flask\",\"iconColor\":\"#7c3aed\"}', '2026-07-27 13:52:44', '2026-07-21 12:00:40', '2026-07-27 13:52:44'),
('568ba477-9a5e-4ada-98c7-6a63e21e9220', 'App\\Notifications\\LiveClassStartingSoonToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Live class in 5 min\",\"body\":\"\\\"The Intro vide for yoga\\\" \\u2014 join now from your dashboard.\",\"url\":\"https:\\/\\/us05web.zoom.us\\/j\\/85728247644?pwd=niPYpar0piUsFUxwbdUhEj27Gz2UtJ.1\",\"icon\":\"fa-video\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-10 16:35:04', '2026-07-10 16:35:04'),
('57ea6ce9-6a54-4aa4-8d61-90a7955a425e', 'App\\Notifications\\StudentTemporarySlotAssigned', 'App\\Models\\User', 1264, '{\"event\":\"student_temporary_slot\",\"title\":\"\\ud83d\\udcc5 Temporary class \\u00b7 16 Jul\",\"body\":\"Virendra Kumar scheduled a temporary class\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/student\\/dashboard\",\"icon\":\"fa-calendar-day\",\"iconColor\":\"#d97706\",\"batch_id\":825,\"slot_date\":\"2026-07-16\",\"coach_id\":1124}', '2026-07-17 12:58:53', '2026-07-16 10:25:47', '2026-07-17 12:58:53'),
('58597678-6e63-4341-8bd4-f9ee56518445', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1256, '{\"title\":\"Your free trial ends tomorrow\",\"body\":\"Upgrade now to avoid losing access to courses + live classes.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', '2026-07-21 19:10:07', '2026-07-20 14:30:05', '2026-07-21 19:10:07'),
('58c5482f-b878-4d2b-84a2-ffb2b6ad921a', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1257, '{\"title\":\"Your free trial ends tomorrow\",\"body\":\"Upgrade now to avoid losing access to courses + live classes.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-20 14:30:05', '2026-07-20 14:30:05'),
('590dbe6b-cafd-4f2f-b9fb-702bc5919c25', 'App\\Notifications\\LeadAssignedToStaff', 'App\\Models\\User', 1265, '{\"title\":\"New lead assigned to you\",\"body\":\"Sarkar \\u2014 Development. Open the lead to follow up.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/instructor\\/landing-page-enquiry\\/39\\/show\",\"icon\":\"fa-user-plus\",\"iconColor\":\"#0d9488\"}', NULL, '2026-07-13 12:37:58', '2026-07-13 12:37:58'),
('5b396740-5ae1-4c5e-9598-d22454c086ec', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1124, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 122.161.65.162. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-16 10:43:23', '2026-07-16 07:28:00', '2026-07-16 10:43:23'),
('5c9c9f0f-3de8-4c3f-8a46-4054cede312e', 'App\\Notifications\\PaymentDueReminderToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Payment pending \\u2014 order #yWQOmLK4IC\",\"body\":\"Your order has been waiting for payment for 7 days. Complete payment to activate your enrollment.\",\"url\":\"https:\\/\\/mbsguru.com\\/student\\/order-details\\/123\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-20 14:30:03', '2026-07-20 14:30:03'),
('5cbefc01-7e17-443e-86cf-76156a8f194f', 'App\\Notifications\\FeeDemandPublishedToStudent', 'App\\Models\\User', 1216, '{\"title\":\"New fee due\",\"body\":\"\\\"Fee Monthly\\\" \\u2014 \\u20b95,000.00 due by Jul 11, 2026\",\"url\":\"https:\\/\\/scc.mbsguru.com\\/student\\/fees\",\"icon\":\"fa-coins\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-11 13:12:28', '2026-07-11 13:12:28'),
('5f54a0ff-f1b7-4601-827b-459c2ebcb678', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1124, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 122.161.76.206. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-14 18:42:58', '2026-07-09 17:10:56', '2026-07-14 18:42:58'),
('5f9354d5-d4bb-4700-b5d8-30351e6460b8', 'App\\Notifications\\PaymentDueReminderToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Payment pending \\u2014 order #zCxS9C706j\",\"body\":\"Your order has been waiting for payment for 3 days. Complete payment to activate your enrollment.\",\"url\":\"https:\\/\\/mbsguru.com\\/student\\/order-details\\/126\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-23 14:30:02', '2026-07-23 14:30:02'),
('5fe98484-301d-4c6c-9671-1b0cb05803e9', 'App\\Notifications\\LiveClassStartedToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Your live class has started \\u2014 join now\",\"body\":\"\\\"The Intro vide for yoga\\\" has started. Join now from your dashboard.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/student\\/live-classes\",\"icon\":\"fa-video\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-10 16:24:04', '2026-07-10 16:24:04'),
('609dd68b-b813-4053-bc9c-c21c85c3d2a8', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1259, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 14.96.24.10. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-08 17:04:21', '2026-07-08 17:04:21'),
('613dddde-81cb-4758-8e65-ae74edb98e4e', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1258, '{\"title\":\"Your free trial ends today\",\"body\":\"Pick a plan to keep using coach features without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-21 14:30:06', '2026-07-21 14:30:06'),
('65039547-153e-4a5d-a0a5-fee77e405c7a', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1263, '{\"title\":\"Your Coach Free Trial has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-22 06:00:04', '2026-07-22 06:00:04'),
('673fb917-9751-4630-8549-86f4a5f2b5cd', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1278, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"http:\\/\\/cockroach-coach.mbsguru.com\\/laravel\\/erpsystem\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-08-24 13:14:22', '2026-08-24 13:14:22'),
('67492e7a-488a-4fab-9ed6-309054e05d46', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1255, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 14.96.24.10. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-20 11:57:41', '2026-07-14 10:28:35', '2026-07-20 11:57:41'),
('6a9f3837-1e3a-4110-9c6a-6a640c027d05', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1255, '{\"title\":\"Your free trial ends in 3 days\",\"body\":\"Browse plans and pick one that fits.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#3b82f6\"}', '2026-07-20 11:57:10', '2026-07-18 14:30:05', '2026-07-20 11:57:10'),
('6b3b4059-9379-45aa-ab79-6807ea8d3662', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1273, '{\"title\":\"Your Coach Free Trial has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-26 06:00:04', '2026-07-26 06:00:04'),
('6e5351f1-b100-4b9c-ba5d-4ecff701cf9a', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 106.219.195.113. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-18 13:38:40', '2026-07-12 11:51:45', '2026-07-18 13:38:40'),
('6e9af323-5c9a-4f88-ac84-37aec42853ce', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1257, '{\"title\":\"Your Coach Free Trial has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-22 06:00:04', '2026-07-22 06:00:04'),
('6f4f5d40-aa1b-48e6-b2cf-b002f877ade6', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1273, '{\"title\":\"Your free trial ends tomorrow\",\"body\":\"Upgrade now to avoid losing access to courses + live classes.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-24 14:30:05', '2026-07-24 14:30:05'),
('72d65bf3-1933-4151-9d55-ee1cd358f0e9', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1276, '{\"title\":\"Your free trial ends today\",\"body\":\"Pick a plan to keep using coach features without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-29 14:30:04', '2026-07-29 14:30:04'),
('73a23bc3-dd33-44c4-abcc-1752307b58db', 'App\\Notifications\\MembershipActivatedToUser', 'App\\Models\\User', 1280, '{\"title\":\"Your Medium is active\",\"body\":\"Active until Aug 20, 2026.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-shield-alt\",\"iconColor\":\"#10b981\"}', NULL, '2026-07-21 12:29:23', '2026-07-21 12:29:23'),
('73f2bcb2-28d9-417c-9f25-cd5f461379f3', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1195, '{\"title\":\"Your free trial ends today\",\"body\":\"Pick a plan to keep using coach features without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-10 14:30:09', '2026-07-10 14:30:09'),
('749cbb08-8908-4134-bd44-d044f9f30058', 'App\\Notifications\\LiveClassStartedToStudent', 'App\\Models\\User', 1279, '{\"title\":\"Your live class has started \\u2014 join now\",\"body\":\"\\\"Political Science\\\" has started. Join now from your dashboard.\",\"url\":\"https:\\/\\/cockroach-coach.mbsguru.com\\/student\\/live-classes\",\"icon\":\"fa-video\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-21 13:37:02', '2026-07-21 13:37:02'),
('7605fd6b-7559-4692-92da-b2cebee4ef87', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1276, '{\"title\":\"Your Coach Free Trial has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-30 06:00:04', '2026-07-30 06:00:04'),
('766bb0fb-bab5-4c96-bba0-747e5653a157', 'App\\Notifications\\PaymentDueReminderToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Payment pending \\u2014 order #KnYspW8YNf\",\"body\":\"Your order has been waiting for payment for 1 day. Complete payment to activate your enrollment.\",\"url\":\"https:\\/\\/mbsguru.com\\/student\\/order-details\\/112\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-09 14:30:05', '2026-07-09 14:30:05'),
('79e95f52-4853-4b71-a1a1-57883c9d3ab1', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1256, '{\"title\":\"New sale: Java Crouse\",\"body\":\"Rahul Mishra purchased \\\"Java Crouse\\\" \\u2014 10,000.00 INR.\",\"url\":\"https:\\/\\/scc.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-15 22:38:42', '2026-07-12 10:24:24', '2026-07-15 22:38:42'),
('7ab878c2-4b1d-400a-9d25-e63220d887e9', 'App\\Notifications\\WithdrawalApprovedToCoach', 'App\\Models\\User', 1124, '{\"title\":\"Payout approved\",\"body\":\"Your withdrawal of \\u20b9200.00 has been approved.\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/payout\",\"icon\":\"fa-money-bill-wave\",\"iconColor\":\"#10b981\"}', '2026-07-17 17:58:33', '2026-07-16 18:11:36', '2026-07-17 17:58:33'),
('7bd707dd-2fb0-4db5-922d-00841ac1a1f0', 'App\\Notifications\\CoachAssignedCourseToStudent', 'App\\Models\\User', 1259, '{\"event\":\"coach_assigned_course\",\"title\":\"\\ud83c\\udf93 Hatha Yoga for Beginners: Build Strength, Flexibility & Inner Calm\",\"body\":\"Yoga Singh just enrolled you\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/student\\/enrolled-courses\",\"icon\":\"fa-graduation-cap\",\"iconColor\":\"#16a34a\",\"course_id\":282,\"order_id\":116,\"coach_id\":1255}', NULL, '2026-07-10 16:49:48', '2026-07-10 16:49:48'),
('7f68618b-6a64-4c09-ba58-4282dadd2c95', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1124, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 106.219.237.31. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-16 13:59:32', '2026-07-16 13:59:32'),
('810fa0e4-0f95-42e5-ae7f-4020c90eac2a', 'App\\Notifications\\BookingEnquiryToCoach', 'App\\Models\\User', 1124, '{\"title\":\"New booking: Mara Mccarty\",\"body\":\"Pending\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-calendar-check\",\"iconColor\":\"#f97316\"}', '2026-07-16 10:43:23', '2026-07-15 16:27:11', '2026-07-16 10:43:23'),
('811d6743-26d5-4169-aeb0-faea33732756', 'App\\Notifications\\InstantMeetingInviteToStudent', 'App\\Models\\User', 1279, '{\"title\":\"Your coach is inviting you to a meeting\",\"body\":\"Cockroach Coach wants to start a 1:1 session with you now. Tap to join.\",\"url\":\"https:\\/\\/cockroach-coach.mbsguru.com\\/instant-meeting\\/10\\/room\",\"icon\":\"fa-video\",\"iconColor\":\"#16a34a\"}', NULL, '2026-07-21 15:05:37', '2026-07-21 15:05:37'),
('82924e94-cd55-4523-9048-9223b5ec0a17', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 14.96.24.10. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-18 13:38:40', '2026-07-15 17:07:48', '2026-07-18 13:38:40'),
('82f17ec8-ea87-4848-8bde-738d32ea5dce', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1276, '{\"title\":\"Your free trial ends in 3 days\",\"body\":\"Browse plans and pick one that fits.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-26 14:30:05', '2026-07-26 14:30:05'),
('836bd8e4-cfe2-4d07-a34b-4dc4c0acb91d', 'App\\Notifications\\PricingBookingPaidToCoach', 'App\\Models\\User', 1124, '{\"title\":\"Payment received: Suresh Sarkar \\u2014 \\u20b910,000.00\",\"body\":\"Mansi Rawat \\u00b7 Individual Plan \\u00b7 10 Session Validity 25 days\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-circle-check\",\"iconColor\":\"#16a34a\"}', '2026-07-16 10:43:23', '2026-07-15 15:15:53', '2026-07-16 10:43:23'),
('859c76cb-44c0-4d01-a5d4-df8dd74f1862', 'App\\Notifications\\CoachAssignedCourseToStudent', 'App\\Models\\User', 1259, '{\"event\":\"coach_assigned_course\",\"title\":\"\\ud83c\\udf93 Complete Yoga Journey \\u2013 From Beginner to Advanced\",\"body\":\"Yoga Singh just enrolled you\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/student\\/enrolled-courses\",\"icon\":\"fa-graduation-cap\",\"iconColor\":\"#16a34a\",\"course_id\":280,\"order_id\":113,\"coach_id\":1255}', NULL, '2026-07-08 17:06:13', '2026-07-08 17:06:13'),
('860440b9-834c-40c4-afa3-6525d65d17eb', 'App\\Notifications\\PaymentDueReminderToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Payment pending \\u2014 order #KnYspW8YNf\",\"body\":\"Your order has been waiting for payment for 7 days. Complete payment to activate your enrollment.\",\"url\":\"https:\\/\\/mbsguru.com\\/student\\/order-details\\/112\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-15 14:30:03', '2026-07-15 14:30:03'),
('892e8528-d139-4b2e-89e7-7c6af4e50204', 'App\\Notifications\\StudentBatchAssignedToStudent', 'App\\Models\\User', 1216, '{\"event\":\"student_batch_assigned\",\"title\":\"\\ud83d\\udc65 Morning 10 to 12\",\"body\":\"SCC assigned you to a batch\",\"url\":\"https:\\/\\/mbsguru.com\\/student\\/dashboard\",\"icon\":\"fa-users\",\"iconColor\":\"#4f46e5\",\"batch_id\":862,\"course_id\":275,\"coach_id\":1256}', NULL, '2026-07-15 22:01:40', '2026-07-15 22:01:40'),
('8b7eb1a2-3582-4f2c-b23f-d648f736c782', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1277, '{\"title\":\"Your free trial ends in 3 days\",\"body\":\"Browse plans and pick one that fits.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-28 14:30:03', '2026-07-28 14:30:03'),
('8d3bf081-79d3-4380-8650-7067d7f1cb43', 'App\\Notifications\\CoachTrialWelcomeToUser', 'App\\Models\\User', 1273, '{\"title\":\"Welcome \\u2014 your free trial is active\",\"body\":\"Full coach access until Jul 25, 2026.\",\"url\":\"https:\\/\\/mbsguru.com\\/instructor\\/dashboard\",\"icon\":\"fa-flask\",\"iconColor\":\"#7c3aed\"}', '2026-07-12 07:39:27', '2026-07-11 18:52:55', '2026-07-12 07:39:27');
INSERT INTO `notifications` (`id`, `type`, `notifiable_type`, `notifiable_id`, `data`, `read_at`, `created_at`, `updated_at`) VALUES
('8df3cce4-03dd-4111-bf56-df41af6d2f09', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1268, '{\"title\":\"Your free trial ends tomorrow\",\"body\":\"Upgrade now to avoid losing access to courses + live classes.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-22 14:30:06', '2026-07-22 14:30:06'),
('9050a516-2288-427e-a27d-148dc117ac6c', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1256, '{\"title\":\"Your free trial ends today\",\"body\":\"Pick a plan to keep using coach features without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#ef4444\"}', '2026-07-21 19:10:07', '2026-07-21 14:30:05', '2026-07-21 19:10:07'),
('932f87a5-d6dc-4bee-ab0b-6d0b1fc2b728', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1279, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 14.96.24.10. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/cockroach-coach.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-24 10:31:16', '2026-07-24 10:31:16'),
('94496399-5198-427f-b29f-0e015f6c6a33', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1079, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 127.0.0.1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"http:\\/\\/sss.mbsguru.com\\/laravel\\/erpsystem\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-30 12:06:44', '2026-07-30 12:06:44'),
('9803ba43-1a7e-4931-888e-99341e2ec8fd', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1256, '{\"title\":\"New sale: Java Crouse\",\"body\":\"Rahul Mishra purchased \\\"Java Crouse\\\" \\u2014 10,000.00 INR.\",\"url\":\"https:\\/\\/scc.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-15 22:38:42', '2026-07-12 10:19:27', '2026-07-15 22:38:42'),
('99f7b7ed-b05d-4188-a10a-56d090cfe160', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1260, '{\"title\":\"Your free trial ends today\",\"body\":\"Pick a plan to keep using coach features without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-21 14:30:06', '2026-07-21 14:30:06'),
('9aac3d94-7b8f-44a1-ae7a-c9ebefa273c6', 'App\\Notifications\\InstantMeetingInviteToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Your coach is inviting you to a meeting\",\"body\":\"Yoga Singh wants to start a 1:1 session with you now. Tap to join.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/instant-meeting\\/7\\/room\",\"icon\":\"fa-video\",\"iconColor\":\"#16a34a\"}', NULL, '2026-07-09 18:56:59', '2026-07-09 18:56:59'),
('9bbd2c32-3019-4e45-a5c1-e2893b669daa', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1259, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 14.96.24.10. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-09 12:03:28', '2026-07-09 12:03:28'),
('9cd24ed3-c28c-4cec-a6a0-64d49a48e239', 'App\\Notifications\\WebsiteThemeChanged', 'App\\Models\\User', 1273, '{\"title\":\"Website Theme Changed Successfully\",\"body\":\"Your website theme is now \\\"Personal Growth Mentor\\\". Website: Advaityog. Changed on 11 Jul 2026, 07:47 PM. Your website data \\u2014 pages, content, images, courses, menus and SEO \\u2014 remains completely safe; only the design and presentation changed. If you did NOT make this change, please contact support immediately.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/instructor\\/web-page\",\"icon\":\"fa-palette\",\"iconColor\":\"#6366f1\"}', '2026-07-12 07:39:27', '2026-07-11 19:47:03', '2026-07-12 07:39:27'),
('9cfc5d62-f6b2-4d1a-8dfa-83286a877128', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1233, '{\"title\":\"Your Coach Free Trial has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-14 06:00:03', '2026-07-14 06:00:03'),
('9f9b4602-853d-4b1c-ae2e-86bc7dec0421', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1278, '{\"title\":\"New sale: Education Policy\",\"body\":\"Cockroach Student purchased \\\"Education Policy\\\" \\u2014 4,000.00 INR.\",\"url\":\"https:\\/\\/cockroach-coach.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-27 13:52:44', '2026-07-27 11:28:08', '2026-07-27 13:52:44'),
('a07f7587-3b42-45f0-8ba6-8cfebca05ae5', 'App\\Notifications\\PaymentDueReminderToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Payment pending \\u2014 order #zCxS9C706j\",\"body\":\"Your order has been waiting for payment for 7 days. Complete payment to activate your enrollment.\",\"url\":\"https:\\/\\/mbsguru.com\\/student\\/order-details\\/126\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-27 14:30:03', '2026-07-27 14:30:03'),
('a0c8fd76-e2b8-4906-b29c-26658ee95583', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1273, '{\"title\":\"Your free trial ends in 3 days\",\"body\":\"Browse plans and pick one that fits.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-22 14:30:05', '2026-07-22 14:30:05'),
('a22733c5-178c-4ab5-be8d-1b853337eab2', 'App\\Notifications\\BookingEnquiryToCoach', 'App\\Models\\User', 1124, '{\"title\":\"New booking: Suresh Sarkar\",\"body\":\"Strength Yoga \\u00b7 07:30 AM \\u00b7 Pending\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-calendar-check\",\"iconColor\":\"#f97316\"}', '2026-07-14 18:42:58', '2026-07-14 16:47:50', '2026-07-14 18:42:58'),
('a2f2bd8c-291b-453b-a287-af9ecdff7d3a', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1258, '{\"title\":\"Your free trial ends in 3 days\",\"body\":\"Browse plans and pick one that fits.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-18 14:30:05', '2026-07-18 14:30:05'),
('a3122860-8fc3-4a16-9c35-560319758c57', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1257, '{\"title\":\"Your free trial ends in 3 days\",\"body\":\"Browse plans and pick one that fits.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-18 14:30:05', '2026-07-18 14:30:05'),
('a3d29856-0a1a-44de-9685-e47519877222', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1263, '{\"title\":\"Your free trial ends in 3 days\",\"body\":\"Browse plans and pick one that fits.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-18 14:30:05', '2026-07-18 14:30:05'),
('a50e975f-1d4f-468f-be56-6d198308d955', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1256, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 125.99.186.242. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/scc.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-21 19:10:07', '2026-07-17 17:05:40', '2026-07-21 19:10:07'),
('a5bf1de9-ca47-4800-ba61-9df082f065bf', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1273, '{\"title\":\"Your free trial ends today\",\"body\":\"Pick a plan to keep using coach features without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-25 14:30:05', '2026-07-25 14:30:05'),
('a66a4a8b-cbcd-47d2-ba16-284ee5c4d58b', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1256, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 122.161.65.162. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/scc.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-15 22:38:42', '2026-07-15 21:50:17', '2026-07-15 22:38:42'),
('a7365ee6-9d02-4b73-919d-39ede790301e', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1079, '{\"title\":\"Your Enterprise has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-30 06:00:04', '2026-07-30 06:00:04'),
('a80c49e8-cbbf-409c-a225-b66c1bf16b83', 'App\\Notifications\\CoachAssignedCourseToStudent', 'App\\Models\\User', 1264, '{\"event\":\"coach_assigned_course\",\"title\":\"\\ud83c\\udf93 Yoga Teacher training course\",\"body\":\"Virendra Kumar just enrolled you\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/student\\/enrolled-courses\",\"icon\":\"fa-graduation-cap\",\"iconColor\":\"#16a34a\",\"course_id\":279,\"order_id\":124,\"coach_id\":1124}', '2026-07-17 12:58:07', '2026-07-16 11:17:44', '2026-07-17 12:58:07'),
('aa6f068b-2b4b-4a0d-90eb-9b844be495a2', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1279, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 14.96.24.10. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/cockroach-coach.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-23 10:48:32', '2026-07-23 10:48:32'),
('ac53f7a3-0b2e-4da1-8b1f-33835ca2b5ff', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1255, '{\"title\":\"New sale: The Intro vide for yoga\",\"body\":\"Yoga Singh Student purchased \\\"The Intro vide for yoga\\\" \\u2014 1,000.00 INR.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-10 13:13:10', '2026-07-09 17:09:21', '2026-07-10 13:13:10'),
('ac8ee7c5-66cb-4d73-9f41-5c0e75776bab', 'App\\Notifications\\PricingBookingPaidToCoach', 'App\\Models\\User', 1124, '{\"title\":\"Payment received: Suresh Sarkar \\u2014 \\u20b96,000.00\",\"body\":\"Mansi Rawat \\u00b7 Individual Plan \\u00b7 5 Session Validity 10 days\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-circle-check\",\"iconColor\":\"#16a34a\"}', '2026-07-16 10:43:23', '2026-07-15 15:29:09', '2026-07-16 10:43:23'),
('b02061a6-abeb-410a-bfc1-3e5f58443858', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1255, '{\"title\":\"New sale: Yoga for mental\",\"body\":\"Yoga Singh Student purchased \\\"Yoga for mental\\\" \\u2014 999.00 INR.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-21 12:39:50', '2026-07-20 12:06:12', '2026-07-21 12:39:50'),
('b0a5a4e8-95c3-463e-8d2d-985796946b16', 'App\\Notifications\\LeadAssignedToStaff', 'App\\Models\\User', 1271, '{\"title\":\"New lead assigned to you\",\"body\":\"AK Hz \\u2014 General enquiry. Open the lead to follow up.\",\"url\":\"https:\\/\\/slidus.com\\/instructor\\/landing-page-enquiry\\/41\\/show\",\"icon\":\"fa-user-plus\",\"iconColor\":\"#0d9488\"}', '2026-07-10 15:44:37', '2026-07-10 15:43:47', '2026-07-10 15:44:37'),
('b0a7826d-fc37-496e-84f7-225d3cf846ee', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 106.192.202.187. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-28 22:11:22', '2026-07-28 22:11:22'),
('b105558c-e289-4212-a3d5-e4686e0a45ac', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1280, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"http:\\/\\/kuch-bhi.mbsguru.com\\/laravel\\/erpsystem\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-08-18 10:44:28', '2026-08-18 10:44:28'),
('b10d1522-6ea7-4e70-89b7-2dfdd4cb50c5', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 14.96.24.10. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-18 13:38:40', '2026-07-13 10:22:24', '2026-07-18 13:38:40'),
('b3c730fb-6a8c-4fcf-86ae-a7353f3948a8', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 106.219.224.207. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-18 13:38:40', '2026-07-16 21:25:59', '2026-07-18 13:38:40'),
('b65ce540-08eb-412e-87c7-85f286ae195e', 'App\\Notifications\\LeadAssignedToStaff', 'App\\Models\\User', 1266, '{\"title\":\"New lead assigned to you\",\"body\":\"Suresh Sarkar \\u2014 SEO. Open the lead to follow up.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/instructor\\/landing-page-enquiry\\/40\\/show\",\"icon\":\"fa-user-plus\",\"iconColor\":\"#0d9488\"}', NULL, '2026-07-13 12:33:09', '2026-07-13 12:33:09'),
('b8086bcb-cacc-4872-b576-969ee419f99c', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 103.208.68.15. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-21 14:46:45', '2026-07-21 14:46:45'),
('bafc3b8a-7e4e-4bf9-b50b-d8f308355159', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 45.119.31.7. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-18 13:38:40', '2026-07-17 14:25:44', '2026-07-18 13:38:40'),
('bb2718a4-85af-48c9-8eac-27202692ddc0', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1195, '{\"title\":\"Your free trial ends tomorrow\",\"body\":\"Upgrade now to avoid losing access to courses + live classes.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-09 14:30:09', '2026-07-09 14:30:09'),
('bcd491f6-c347-48d3-aaa5-3cdde03a5d6f', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1268, '{\"title\":\"Your Coach Free Trial has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-24 06:00:04', '2026-07-24 06:00:04'),
('bd0b3cf3-9040-4c31-8d07-cd6cb62b7b11', 'App\\Notifications\\InstantMeetingInviteToStudent', 'App\\Models\\User', 1216, '{\"title\":\"Your coach is inviting you to a meeting\",\"body\":\"Software Coaching Center wants to start a 1:1 session with you now. Tap to join.\",\"url\":\"https:\\/\\/scc.mbsguru.com\\/instant-meeting\\/8\\/room\",\"icon\":\"fa-video\",\"iconColor\":\"#16a34a\"}', NULL, '2026-07-13 10:51:09', '2026-07-13 10:51:09'),
('bed9ac39-0c51-4654-8844-298d2c974377', 'App\\Notifications\\MembershipActivatedToUser', 'App\\Models\\User', 1278, '{\"title\":\"Your Starter is active\",\"body\":\"Active until Aug 20, 2026.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-shield-alt\",\"iconColor\":\"#10b981\"}', '2026-07-27 13:52:44', '2026-07-21 12:23:11', '2026-07-27 13:52:44'),
('c3288c3e-14b8-49b0-a9cd-0e2a2f91d171', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1256, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 122.161.77.75. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/scc.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-24 06:20:24', '2026-07-24 06:20:24'),
('c43c8286-6653-461f-bdde-c9549380eabf', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 106.219.238.2. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-18 13:38:40', '2026-07-16 08:59:36', '2026-07-18 13:38:40'),
('c4958f4c-36e7-4764-a0a1-1f56fc3eb069', 'App\\Notifications\\PricingBookingPaidToCoach', 'App\\Models\\User', 1124, '{\"title\":\"Payment received: Mara Mccarty \\u2014 \\u20b930,000.00\",\"body\":\"Rajesh Chauhan \\u00b7 Individual Plan \\u00b7 50 Session Validity 90 days - \\u20b930000\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-circle-check\",\"iconColor\":\"#16a34a\"}', '2026-07-16 10:43:23', '2026-07-15 16:31:11', '2026-07-16 10:43:23'),
('c4d38cf0-cb1a-4195-8d1f-cb245dcd01be', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1286, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP ::1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-08-31 08:25:26', '2026-08-31 08:25:26'),
('c7fb7dd2-f216-4bba-8f36-2ff67a9063ad', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1256, '{\"title\":\"New sale: Java Crouse\",\"body\":\"Rahul Mishra purchased \\\"Java Crouse\\\" \\u2014 10,000.00 INR.\",\"url\":\"https:\\/\\/scc.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-15 22:38:42', '2026-07-12 10:05:38', '2026-07-15 22:38:42'),
('c89d4b41-ff4e-4d91-ac94-aa86b9b37c60', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1223, '{\"title\":\"Your Starter has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-26 06:00:03', '2026-07-26 06:00:03'),
('cc645c6c-caf5-43f6-b613-97bd88ee285a', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1259, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 14.96.24.10. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-13 12:19:51', '2026-07-13 12:19:51'),
('cdbbcad2-f092-4537-ab4a-b3b23ce83c02', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1213, '{\"title\":\"Your free trial ends today\",\"body\":\"Pick a plan to keep using coach features without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-09 14:30:09', '2026-07-09 14:30:09'),
('cf158324-ec59-4650-8685-e2e96a761aee', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1124, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 14.96.24.10. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-14 18:42:58', '2026-07-10 10:29:44', '2026-07-14 18:42:58'),
('cf7e149a-ae95-4571-b247-892ec0355212', 'App\\Notifications\\LiveClassScheduledToStudent', 'App\\Models\\User', 1259, '{\"title\":\"New live class scheduled\",\"body\":\"\\\"The Intro vide for yoga\\\" \\u2014 Vide Yoga Live Class (New Vide Yoga Batch) on 10 Jul, 2026 - 04:40 pm\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/student\\/live-classes\",\"icon\":\"fa-video\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-10 16:32:43', '2026-07-10 16:32:43'),
('d041266a-52c3-49d8-a868-b05ddb095c8a', 'App\\Notifications\\CoachAssignedCourseToStudent', 'App\\Models\\User', 1259, '{\"event\":\"coach_assigned_course\",\"title\":\"\\ud83c\\udf93 The Intro vide for yoga\",\"body\":\"Yoga Singh just enrolled you\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/student\\/enrolled-courses\",\"icon\":\"fa-graduation-cap\",\"iconColor\":\"#16a34a\",\"course_id\":281,\"order_id\":115,\"coach_id\":1255}', NULL, '2026-07-09 17:08:38', '2026-07-09 17:08:38'),
('d18e5ab0-eac9-4793-a0dc-51d64f8f954c', 'App\\Notifications\\BookingEnquiryToCoach', 'App\\Models\\User', 1124, '{\"title\":\"New booking: Suresh Sarkar\",\"body\":\"Pending\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-calendar-check\",\"iconColor\":\"#f97316\"}', '2026-07-16 10:43:23', '2026-07-15 15:15:10', '2026-07-16 10:43:23'),
('d2396b5a-bfc0-4ca4-b2d9-d6af60fe91b4', 'App\\Notifications\\BookingEnquiryToCoach', 'App\\Models\\User', 1124, '{\"title\":\"New booking: Suresh Sarkar\",\"body\":\"Pending\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-calendar-check\",\"iconColor\":\"#f97316\"}', '2026-07-16 10:43:23', '2026-07-15 15:07:16', '2026-07-16 10:43:23'),
('d32b17e6-56b8-491b-8b2a-548adc4df69d', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1261, '{\"title\":\"Your free trial ends today\",\"body\":\"Pick a plan to keep using coach features without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-21 14:30:07', '2026-07-21 14:30:07'),
('d36d1747-4cf7-46df-bf9b-003a48ed4284', 'App\\Notifications\\BookingEnquiryToCoach', 'App\\Models\\User', 1124, '{\"title\":\"New booking: Santosh DHS\",\"body\":\"Strength Yoga \\u00b7 05:00 AM \\u00b7 Pending\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-calendar-check\",\"iconColor\":\"#f97316\"}', '2026-07-14 18:42:58', '2026-07-14 16:57:32', '2026-07-14 18:42:58'),
('d69717fd-1d61-443b-8abc-006732bd87f3', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1278, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 127.0.0.1. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-08-31 08:27:05', '2026-08-31 08:27:05'),
('d72d77be-52fa-4cf5-8109-affe3cf73f73', 'App\\Notifications\\LeadFollowUpReminderToStaff', 'App\\Models\\User', 1273, '{\"title\":\"Follow-up due: Jessamine Griffith Rai\",\"body\":\"It\'s time to follow up with Jessamine Griffith Rai about Full Stack Web Development with React. Open the lead to log your call.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/instructor\\/landing-page-enquiry\\/45\\/show\",\"icon\":\"fa-bell\",\"iconColor\":\"#0ea5e9\"}', '2026-07-12 07:39:27', '2026-07-11 20:45:04', '2026-07-12 07:39:27'),
('d8a10dad-efe4-400f-bbc2-9bfc09f06ff0', 'App\\Notifications\\InstantMeetingInviteToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Your coach is inviting you to a meeting\",\"body\":\"Yoga Singh wants to start a 1:1 session with you now. Tap to join.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/instant-meeting\\/6\\/room\",\"icon\":\"fa-video\",\"iconColor\":\"#16a34a\"}', NULL, '2026-07-09 18:54:42', '2026-07-09 18:54:42'),
('dc65891b-a098-4b89-b7c7-3d522e08f3d1', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 106.192.199.189. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-12 07:39:27', '2026-07-11 19:44:58', '2026-07-12 07:39:27'),
('de85050d-666b-409f-870f-01ce248d530f', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1260, '{\"title\":\"Your free trial ends in 3 days\",\"body\":\"Browse plans and pick one that fits.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-18 14:30:05', '2026-07-18 14:30:05'),
('e161f252-7067-40d6-aeea-4e743ccf91d0', 'App\\Notifications\\MembershipActivatedToUser', 'App\\Models\\User', 1278, '{\"title\":\"Your Medium is active\",\"body\":\"Active until Sep 24, 2026.\",\"url\":\"http:\\/\\/localhost\\/laravel\\/erpsystem\\/membership\",\"icon\":\"fa-shield-alt\",\"iconColor\":\"#10b981\"}', NULL, '2026-08-25 05:13:02', '2026-08-25 05:13:02'),
('e2fcdd4b-08d9-4e0f-97c1-523d10921cc6', 'App\\Notifications\\LeadAssignedToStaff', 'App\\Models\\User', 1265, '{\"title\":\"New lead assigned to you\",\"body\":\"Suresh Sarkar \\u2014 SEO. Open the lead to follow up.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/instructor\\/landing-page-enquiry\\/40\\/show\",\"icon\":\"fa-user-plus\",\"iconColor\":\"#0d9488\"}', NULL, '2026-07-10 15:34:28', '2026-07-10 15:34:28'),
('e39a77c4-6a12-4252-9eb4-1c5467a6462b', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 106.219.238.99. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-18 13:38:40', '2026-07-15 15:51:47', '2026-07-18 13:38:40'),
('e49b476e-ede7-49c4-b5e9-7d3e0619ce09', 'App\\Notifications\\CoachTrialWelcomeToUser', 'App\\Models\\User', 1268, '{\"title\":\"Welcome \\u2014 your free trial is active\",\"body\":\"Full coach access until Jul 23, 2026.\",\"url\":\"https:\\/\\/www.mbsguru.com\\/instructor\\/dashboard\",\"icon\":\"fa-flask\",\"iconColor\":\"#7c3aed\"}', NULL, '2026-07-09 06:29:46', '2026-07-09 06:29:46'),
('e57a61b7-e9e3-43f6-b921-9cd70d8c8fab', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1233, '{\"title\":\"Your free trial ends in 3 days\",\"body\":\"Browse plans and pick one that fits.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-10 14:30:09', '2026-07-10 14:30:09'),
('e593bba4-8ed1-4cf9-90f7-de929abf67ae', 'App\\Notifications\\CoachTrialWelcomeToUser', 'App\\Models\\User', 1277, '{\"title\":\"Welcome \\u2014 your free trial is active\",\"body\":\"Full coach access until Jul 31, 2026.\",\"url\":\"https:\\/\\/mbsguru.com\\/instructor\\/dashboard\",\"icon\":\"fa-flask\",\"iconColor\":\"#7c3aed\"}', '2026-07-17 17:32:38', '2026-07-17 15:27:41', '2026-07-17 17:32:38'),
('e5ab0117-4424-42a6-86c9-7d18e6d25e4c', 'App\\Notifications\\CoachTrialWelcomeToUser', 'App\\Models\\User', 1276, '{\"title\":\"Welcome \\u2014 your free trial is active\",\"body\":\"Full coach access until Jul 29, 2026.\",\"url\":\"https:\\/\\/mbsguru.com\\/instructor\\/dashboard\",\"icon\":\"fa-flask\",\"iconColor\":\"#7c3aed\"}', NULL, '2026-07-15 15:49:36', '2026-07-15 15:49:36'),
('e767e79c-53c5-4335-ad8c-835173e8f92f', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 103.222.253.213. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-28 17:46:03', '2026-07-28 17:46:03'),
('e7a95d9f-6dd7-4f4f-a452-d8147d8ce243', 'App\\Notifications\\TrialExpiringSoonToCoach', 'App\\Models\\User', 1263, '{\"title\":\"Your free trial ends tomorrow\",\"body\":\"Upgrade now to avoid losing access to courses + live classes.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-20 14:30:05', '2026-07-20 14:30:05'),
('eb34f04d-da1d-4b81-a514-af645d0b94a9', 'App\\Notifications\\CourseCompletedToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Course completed: Complete Yoga Journey \\u2013 From Beginner to Advanced\",\"body\":\"Great work! Your certificate is ready to download.\",\"url\":\"https:\\/\\/yoga-singh.mbsguru.com\\/student\\/enrolled-courses\",\"icon\":\"fa-trophy\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-08 17:14:11', '2026-07-08 17:14:11'),
('eb9ad7a5-33d8-4231-9a4a-52c7925e5ff7', 'App\\Notifications\\PaymentDueReminderToStudent', 'App\\Models\\User', 1218, '{\"title\":\"Payment pending \\u2014 order #sb2zZ7A7Lj\",\"body\":\"Your order has been waiting for payment for 7 days. Complete payment to activate your enrollment.\",\"url\":\"https:\\/\\/mbsguru.com\\/student\\/order-details\\/109\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-10 14:30:05', '2026-07-10 14:30:05'),
('ec288aaf-3578-4c22-8f0d-9b27d01e1d1c', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1124, '{\"title\":\"New sale: Yoga Teacher training course\",\"body\":\"Yoga Singh Student purchased \\\"Yoga Teacher training course\\\" \\u2014 4,000.00 INR.\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', NULL, '2026-07-16 11:16:21', '2026-07-16 11:16:21'),
('ec39316b-724d-4795-837d-2f0e738f0cfb', 'App\\Notifications\\LiveClassScheduledToStudent', 'App\\Models\\User', 1279, '{\"title\":\"New live class scheduled\",\"body\":\"\\\"Political Science\\\" \\u2014 Test Live Class (Political Science Batch) on 21 Jul, 2026 - 01:40 pm\",\"url\":\"https:\\/\\/cockroach-coach.mbsguru.com\\/student\\/live-classes\",\"icon\":\"fa-video\",\"iconColor\":\"#3b82f6\"}', NULL, '2026-07-21 13:35:41', '2026-07-21 13:35:41'),
('ed41d118-aa96-4532-8830-9d600c66e8fe', 'App\\Notifications\\PaymentDueReminderToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Payment pending \\u2014 order #zCxS9C706j\",\"body\":\"Your order has been waiting for payment for 1 day. Complete payment to activate your enrollment.\",\"url\":\"https:\\/\\/mbsguru.com\\/student\\/order-details\\/126\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-21 14:30:03', '2026-07-21 14:30:03'),
('edf79e00-0221-4052-b0bc-729076aa4181', 'App\\Notifications\\CourseCompletedToStudent', 'App\\Models\\User', 1269, '{\"title\":\"Course completed: Customer Relationship Management Essentials\",\"body\":\"Great work! Your certificate is ready to download.\",\"url\":\"https:\\/\\/slidus.com\\/student\\/enrolled-courses\",\"icon\":\"fa-trophy\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-09 13:41:18', '2026-07-09 13:41:18'),
('f07ca166-eeae-41e4-92a0-78734fc14c02', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1273, '{\"title\":\"New sale: Pranayama & Meditation Mastery\",\"body\":\"Advaityog Student purchased \\\"Pranayama & Meditation Mastery\\\" \\u2014 1,500.00 INR.\",\"url\":\"https:\\/\\/advaityog.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-18 13:38:40', '2026-07-13 10:37:18', '2026-07-18 13:38:40'),
('f39931ba-4938-4a4c-95f2-d011a36bab12', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1256, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 122.161.66.140. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/scc.mbsguru.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', '2026-07-15 22:38:42', '2026-07-13 21:15:55', '2026-07-15 22:38:42'),
('f8108aa3-3748-4ed6-aae2-e6741a39285c', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1256, '{\"title\":\"New sale: Java Crouse\",\"body\":\"Rahul Mishra purchased \\\"Java Crouse\\\" \\u2014 9,000.00 INR.\",\"url\":\"https:\\/\\/scc.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', '2026-07-15 22:38:42', '2026-07-12 09:00:30', '2026-07-15 22:38:42'),
('f893a347-995d-4e2c-b6f3-964c381bb7d0', 'App\\Notifications\\MembershipActivatedToUser', 'App\\Models\\User', 1277, '{\"title\":\"Your Medium is active\",\"body\":\"Active until Aug 16, 2026.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-shield-alt\",\"iconColor\":\"#10b981\"}', '2026-07-17 17:32:28', '2026-07-17 15:41:40', '2026-07-17 17:32:28'),
('f8a314b3-1a15-4e6c-bb0e-644f0c0b3681', 'App\\Notifications\\MembershipExpiredToUser', 'App\\Models\\User', 1256, '{\"title\":\"Your Coach Free Trial has expired\",\"body\":\"Your access has ended. Renew now to continue without interruption.\",\"url\":\"https:\\/\\/mbsguru.com\\/membership\",\"icon\":\"fa-hourglass-end\",\"iconColor\":\"#ef4444\"}', NULL, '2026-07-22 06:00:04', '2026-07-22 06:00:04'),
('fbbf7575-5f28-4e66-a16a-ba40f8451fdd', 'App\\Notifications\\PaymentDueReminderToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Payment pending \\u2014 order #KnYspW8YNf\",\"body\":\"Your order has been waiting for payment for 3 days. Complete payment to activate your enrollment.\",\"url\":\"https:\\/\\/mbsguru.com\\/student\\/order-details\\/112\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-11 14:30:04', '2026-07-11 14:30:04'),
('fc18b2f7-35f0-4a3f-9478-9e2e15520b69', 'App\\Notifications\\PricingBookingPaidToCoach', 'App\\Models\\User', 1124, '{\"title\":\"Payment received: Suresh Sarkar \\u2014 \\u20b96,000.00\",\"body\":\"Online \\u00b7 Individual Plan \\u00b7 3 Months\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/pricing-enquiries\",\"icon\":\"fa-circle-check\",\"iconColor\":\"#16a34a\"}', '2026-07-14 18:42:58', '2026-07-14 16:48:53', '2026-07-14 18:42:58'),
('fd345963-f940-4499-b7c1-133425360c57', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1213, '{\"title\":\"New sale: Customer Relationship Management Essentials\",\"body\":\"Slidus Student purchased \\\"Customer Relationship Management Essentials\\\" \\u2014 3,250.00 INR.\",\"url\":\"https:\\/\\/mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', NULL, '2026-07-09 13:40:51', '2026-07-09 13:40:51'),
('fdd55eb2-c4b1-4e6b-a1a0-28ba93f58443', 'App\\Notifications\\CourseSaleToCoach', 'App\\Models\\User', 1124, '{\"title\":\"New sale: Yoga Teacher training course\",\"body\":\"Suresh Sarkar purchased \\\"Yoga Teacher training course\\\" \\u2014 4,000.00 INR.\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/instructor\\/coach-orders\",\"icon\":\"fa-cart-shopping\",\"iconColor\":\"#16a34a\"}', NULL, '2026-07-16 11:17:56', '2026-07-16 11:17:56'),
('ff753378-1369-452a-b79f-4927942b718c', 'App\\Notifications\\StudentTemporarySlotAssigned', 'App\\Models\\User', 1264, '{\"event\":\"student_temporary_slot\",\"title\":\"\\ud83d\\udcc5 Temporary class \\u00b7 16 Jul\",\"body\":\"Virendra Kumar scheduled a temporary class\",\"url\":\"https:\\/\\/virendrastrengthyoga.mbsguru.com\\/student\\/dashboard\",\"icon\":\"fa-calendar-day\",\"iconColor\":\"#d97706\",\"batch_id\":824,\"slot_date\":\"2026-07-16\",\"coach_id\":1124}', '2026-07-17 12:58:53', '2026-07-16 11:15:43', '2026-07-17 12:58:53'),
('ff86730b-8306-4cc2-8387-ea65f436bccb', 'App\\Notifications\\PaymentDueReminderToStudent', 'App\\Models\\User', 1259, '{\"title\":\"Payment pending \\u2014 order #yWQOmLK4IC\",\"body\":\"Your order has been waiting for payment for 1 day. Complete payment to activate your enrollment.\",\"url\":\"https:\\/\\/mbsguru.com\\/student\\/order-details\\/123\",\"icon\":\"fa-hourglass-half\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-14 14:30:03', '2026-07-14 14:30:03'),
('ffb435a5-6cfb-4db6-8658-803cdad39987', 'App\\Notifications\\NewLoginAlertToUser', 'App\\Models\\User', 1273, '{\"title\":\"New sign-in to your account\",\"body\":\"We noticed a sign-in to your account from IP 49.36.213.224. If this was you, no action is needed. If you do NOT recognise it, change your password immediately and contact support.\",\"url\":\"https:\\/\\/www.slidus.com\\/login\",\"icon\":\"fa-shield-halved\",\"iconColor\":\"#f59e0b\"}', NULL, '2026-07-19 13:57:43', '2026-07-19 13:57:43');

-- --------------------------------------------------------

--
-- Table structure for table `notification_email_logs`
--

CREATE TABLE `notification_email_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `coach_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notifiable_type` varchar(255) DEFAULT NULL,
  `notifiable_id` bigint(20) UNSIGNED DEFAULT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `notification_class` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'sent',
  `error` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_email_logs`
--

INSERT INTO `notification_email_logs` (`id`, `coach_id`, `notifiable_type`, `notifiable_id`, `recipient_email`, `notification_class`, `title`, `status`, `error`, `created_at`, `updated_at`) VALUES
(1, 1278, 'App\\Models\\User', 1278, 'cockroach@gmail.com', 'App\\Notifications\\NewLoginAlertToUser', 'New sign-in to your account', 'sent', NULL, '2026-08-24 13:14:26', '2026-08-24 13:14:26'),
(2, NULL, 'App\\Models\\User', 1255, 'yogasingh@gmail.com', 'App\\Notifications\\ReferralRewardEarnedToUser', 'You earned a referral reward!', 'sent', NULL, '2026-08-25 05:13:02', '2026-08-25 05:13:02'),
(3, NULL, 'App\\Models\\User', 1278, 'cockroach@gmail.com', 'App\\Notifications\\MembershipActivatedToUser', 'Your Medium is active', 'sent', NULL, '2026-08-25 05:13:03', '2026-08-25 05:13:03'),
(4, 1278, 'App\\Models\\User', 1278, 'cockroach@gmail.com', 'App\\Notifications\\NewLoginAlertToUser', 'New sign-in to your account', 'sent', NULL, '2026-08-31 08:18:59', '2026-08-31 08:18:59'),
(5, NULL, 'App\\Models\\User', 1182, 'Santosh@gmail.com', 'App\\Notifications\\NewLoginAlertToUser', 'New sign-in to your account', 'sent', NULL, '2026-08-31 08:25:18', '2026-08-31 08:25:18'),
(6, 1278, 'App\\Models\\User', 1286, 'pexesapol@mailinator.com', 'App\\Notifications\\NewLoginAlertToUser', 'New sign-in to your account', 'sent', NULL, '2026-08-31 08:25:29', '2026-08-31 08:25:29'),
(7, 1278, 'App\\Models\\User', 1278, 'cockroach@gmail.com', 'App\\Notifications\\NewLoginAlertToUser', 'New sign-in to your account', 'sent', NULL, '2026-08-31 08:27:08', '2026-08-31 08:27:08');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_items`
--

CREATE TABLE `payroll_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payroll_run_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `payable_days` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `lop_days` decimal(5,1) NOT NULL DEFAULT 0.0,
  `gross` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_earnings` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `lop_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_pay` decimal(12,2) NOT NULL DEFAULT 0.00,
  `earnings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`earnings`)),
  `deductions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`deductions`)),
  `payslip_path` varchar(255) DEFAULT NULL,
  `notified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_items`
--

INSERT INTO `payroll_items` (`id`, `company_id`, `payroll_run_id`, `user_id`, `payable_days`, `lop_days`, `gross`, `total_earnings`, `total_deductions`, `lop_amount`, `net_pay`, `earnings`, `deductions`, `payslip_path`, `notified_at`, `created_at`, `updated_at`) VALUES
(2, 1, 3, 1283, 31, 0.0, 28010.00, 28010.00, 2000.00, 0.00, 26010.00, '[{\"name\":\"Basic\",\"amount\":20000},{\"name\":\"HRA\",\"amount\":8000},{\"name\":\"Special Allowance\",\"amount\":10}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2026-07/payslip-1283.pdf', NULL, '2026-07-31 11:24:45', '2026-08-25 07:05:29'),
(3, 1, 4, 1286, 29, 2.5, 56000.00, 56000.00, 6516.13, 4516.13, 49483.87, '[{\"name\":\"Basic\",\"amount\":40000},{\"name\":\"HRA\",\"amount\":16000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true},{\"name\":\"Loss of Pay (2.5d)\",\"amount\":4516.13,\"statutory\":false}]', 'payslips/2026-08/payslip-1286.pdf', NULL, '2026-08-03 11:04:50', '2026-08-25 07:05:16'),
(4, 1, 4, 1283, 31, 0.0, 28010.00, 28010.00, 2000.00, 0.00, 26010.00, '[{\"name\":\"Basic\",\"amount\":20000},{\"name\":\"HRA\",\"amount\":8000},{\"name\":\"Special Allowance\",\"amount\":10}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2026-08/payslip-1283.pdf', NULL, '2026-08-03 11:04:50', '2026-08-25 05:40:48'),
(5, 1, 4, 1285, 31, 0.0, 21000.00, 21000.00, 2157.50, 0.00, 18842.50, '[{\"name\":\"Basic\",\"amount\":15000},{\"name\":\"HRA\",\"amount\":6000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"ESIC\",\"code\":\"ESIC\",\"amount\":157.5,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2026-08/payslip-1285.pdf', NULL, '2026-08-03 11:04:50', '2026-08-03 11:06:23'),
(6, 1, 4, 1284, 31, 0.0, 70000.00, 70000.00, 2000.00, 0.00, 68000.00, '[{\"name\":\"Basic\",\"amount\":50000},{\"name\":\"HRA\",\"amount\":20000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2026-08/payslip-1284.pdf', NULL, '2026-08-03 11:04:50', '2026-08-03 11:06:23'),
(7, 13, 5, 1287, 31, 0.0, 28000.00, 28000.00, 2000.00, 0.00, 26000.00, '[{\"name\":\"Basic\",\"amount\":20000},{\"name\":\"HRA\",\"amount\":8000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', NULL, NULL, '2026-08-24 12:07:48', '2026-08-24 12:07:48'),
(8, 1, 6, 1283, 31, 0.0, 28010.00, 28010.00, 2000.00, 0.00, 26010.00, '[{\"name\":\"Basic\",\"amount\":20000},{\"name\":\"HRA\",\"amount\":8000},{\"name\":\"Special Allowance\",\"amount\":10}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2025-08/payslip-1283.pdf', NULL, '2026-08-25 07:10:45', '2026-08-25 07:11:41'),
(9, 1, 6, 1285, 31, 0.0, 21000.00, 21000.00, 2157.50, 0.00, 18842.50, '[{\"name\":\"Basic\",\"amount\":15000},{\"name\":\"HRA\",\"amount\":6000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"ESIC\",\"code\":\"ESIC\",\"amount\":157.5,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2025-08/payslip-1285.pdf', NULL, '2026-08-25 07:10:45', '2026-08-25 07:11:42'),
(10, 1, 6, 1284, 31, 0.0, 70000.00, 70000.00, 2000.00, 0.00, 68000.00, '[{\"name\":\"Basic\",\"amount\":50000},{\"name\":\"HRA\",\"amount\":20000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2025-08/payslip-1284.pdf', NULL, '2026-08-25 07:10:45', '2026-08-25 07:11:41'),
(11, 1, 6, 1286, 31, 0.0, 56000.00, 56000.00, 2000.00, 0.00, 54000.00, '[{\"name\":\"Basic\",\"amount\":40000},{\"name\":\"HRA\",\"amount\":16000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2025-08/payslip-1286.pdf', NULL, '2026-08-25 07:10:45', '2026-08-25 07:11:42'),
(12, 1, 7, 1283, 31, 0.0, 28010.00, 28010.00, 2000.00, 0.00, 26010.00, '[{\"name\":\"Basic\",\"amount\":20000},{\"name\":\"HRA\",\"amount\":8000},{\"name\":\"Special Allowance\",\"amount\":10}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2024-08/payslip-1283.pdf', NULL, '2026-08-25 07:12:05', '2026-08-25 07:12:27'),
(13, 1, 7, 1285, 31, 0.0, 21000.00, 21000.00, 2157.50, 0.00, 18842.50, '[{\"name\":\"Basic\",\"amount\":15000},{\"name\":\"HRA\",\"amount\":6000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"ESIC\",\"code\":\"ESIC\",\"amount\":157.5,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2024-08/payslip-1285.pdf', NULL, '2026-08-25 07:12:05', '2026-08-25 07:12:27'),
(14, 1, 7, 1284, 31, 0.0, 70000.00, 70000.00, 2000.00, 0.00, 68000.00, '[{\"name\":\"Basic\",\"amount\":50000},{\"name\":\"HRA\",\"amount\":20000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2024-08/payslip-1284.pdf', NULL, '2026-08-25 07:12:05', '2026-08-25 07:12:27'),
(15, 1, 7, 1286, 31, 0.0, 56000.00, 56000.00, 2000.00, 0.00, 54000.00, '[{\"name\":\"Basic\",\"amount\":40000},{\"name\":\"HRA\",\"amount\":16000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2024-08/payslip-1286.pdf', NULL, '2026-08-25 07:12:05', '2026-08-25 07:12:28'),
(16, 1, 8, 1283, 30, 1.5, 28010.00, 28010.00, 3355.32, 1355.32, 24654.68, '[{\"name\":\"Basic\",\"amount\":20000},{\"name\":\"HRA\",\"amount\":8000},{\"name\":\"Special Allowance\",\"amount\":10}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true},{\"name\":\"Loss of Pay (1.5d)\",\"amount\":1355.32,\"statutory\":false}]', 'payslips/2026-05/payslip-1283.pdf', NULL, '2026-08-25 07:19:37', '2026-08-25 07:35:41'),
(17, 1, 8, 1285, 30, 1.0, 21000.00, 21000.00, 2834.92, 677.42, 18165.08, '[{\"name\":\"Basic\",\"amount\":15000},{\"name\":\"HRA\",\"amount\":6000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"ESIC\",\"code\":\"ESIC\",\"amount\":157.5,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true},{\"name\":\"Loss of Pay (1d)\",\"amount\":677.42,\"statutory\":false}]', 'payslips/2026-05/payslip-1285.pdf', NULL, '2026-08-25 07:19:37', '2026-08-25 07:35:41'),
(18, 1, 8, 1284, 30, 1.0, 70000.00, 70000.00, 4258.06, 2258.06, 65741.94, '[{\"name\":\"Basic\",\"amount\":50000},{\"name\":\"HRA\",\"amount\":20000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true},{\"name\":\"Loss of Pay (1d)\",\"amount\":2258.06,\"statutory\":false}]', 'payslips/2026-05/payslip-1284.pdf', NULL, '2026-08-25 07:19:37', '2026-08-25 07:35:41'),
(19, 1, 8, 1286, 30, 1.0, 56000.00, 56000.00, 3806.45, 1806.45, 52193.55, '[{\"name\":\"Basic\",\"amount\":40000},{\"name\":\"HRA\",\"amount\":16000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true},{\"name\":\"Loss of Pay (1d)\",\"amount\":1806.45,\"statutory\":false}]', 'payslips/2026-05/payslip-1286.pdf', NULL, '2026-08-25 07:19:37', '2026-08-25 07:35:41'),
(20, 1, 9, 1283, 30, 0.0, 28010.00, 28010.00, 2000.00, 0.00, 26010.00, '[{\"name\":\"Basic\",\"amount\":20000},{\"name\":\"HRA\",\"amount\":8000},{\"name\":\"Special Allowance\",\"amount\":10}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2026-09/payslip-1283.pdf', NULL, '2026-08-25 07:35:00', '2026-08-25 07:35:45'),
(21, 1, 9, 1285, 30, 0.0, 21000.00, 21000.00, 2157.50, 0.00, 18842.50, '[{\"name\":\"Basic\",\"amount\":15000},{\"name\":\"HRA\",\"amount\":6000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"ESIC\",\"code\":\"ESIC\",\"amount\":157.5,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2026-09/payslip-1285.pdf', NULL, '2026-08-25 07:35:00', '2026-08-25 07:35:45'),
(22, 1, 9, 1284, 30, 0.0, 70000.00, 70000.00, 2000.00, 0.00, 68000.00, '[{\"name\":\"Basic\",\"amount\":50000},{\"name\":\"HRA\",\"amount\":20000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2026-09/payslip-1284.pdf', NULL, '2026-08-25 07:35:00', '2026-08-25 07:35:45'),
(23, 1, 9, 1286, 30, 0.0, 56000.00, 56000.00, 2000.00, 0.00, 54000.00, '[{\"name\":\"Basic\",\"amount\":40000},{\"name\":\"HRA\",\"amount\":16000},{\"name\":\"Special Allowance\",\"amount\":0}]', '[{\"name\":\"Provident Fund (PF)\",\"code\":\"PF\",\"amount\":1800,\"statutory\":true},{\"name\":\"Professional Tax\",\"code\":\"PT\",\"amount\":200,\"statutory\":true}]', 'payslips/2026-09/payslip-1286.pdf', NULL, '2026-08-25 07:35:00', '2026-08-25 07:35:45');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_runs`
--

CREATE TABLE `payroll_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `year` smallint(5) UNSIGNED NOT NULL,
  `month` tinyint(3) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft' COMMENT 'draft|hr_submitted|admin_approved|paid',
  `employee_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `total_net` decimal(14,2) NOT NULL DEFAULT 0.00,
  `prepared_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payroll_runs`
--

INSERT INTO `payroll_runs` (`id`, `company_id`, `year`, `month`, `status`, `employee_count`, `total_net`, `prepared_by`, `approved_by`, `submitted_at`, `approved_at`, `created_at`, `updated_at`) VALUES
(3, 1, 2026, 7, 'admin_approved', 1, 26010.00, 1278, 1, '2026-07-31 11:24:51', '2026-08-03 11:06:33', '2026-07-31 11:24:45', '2026-08-25 07:05:29'),
(4, 1, 2026, 8, 'admin_approved', 4, 162336.37, 1278, 1, '2026-08-03 11:05:13', '2026-08-03 11:06:21', '2026-08-03 11:04:50', '2026-08-25 07:05:16'),
(5, 13, 2026, 8, 'draft', 1, 26000.00, 1278, NULL, NULL, NULL, '2026-08-18 13:55:29', '2026-08-24 12:07:48'),
(6, 1, 2025, 8, 'admin_approved', 4, 166852.50, 1278, 1, '2026-08-25 07:11:13', '2026-08-25 07:11:41', '2026-08-25 07:10:45', '2026-08-25 07:11:41'),
(7, 1, 2024, 8, 'admin_approved', 4, 166852.50, 1278, 1, '2026-08-25 07:12:14', '2026-08-25 07:12:27', '2026-08-25 07:12:05', '2026-08-25 07:12:27'),
(8, 1, 2026, 5, 'admin_approved', 4, 160755.25, 1278, 1, '2026-08-25 07:35:11', '2026-08-25 07:35:40', '2026-08-25 07:19:37', '2026-08-25 07:35:40'),
(9, 1, 2026, 9, 'admin_approved', 4, 166852.50, 1278, 1, '2026-08-25 07:35:21', '2026-08-25 07:35:44', '2026-08-25 07:35:00', '2026-08-25 07:35:44');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `guard_name`, `group_name`, `created_at`, `updated_at`) VALUES
(1, 'dashboard.view', 'admin', 'dashboard', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(2, 'admin.profile.view', 'admin', 'admin profile', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(3, 'admin.profile.edit', 'admin', 'admin profile', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(4, 'admin.profile.update', 'admin', 'admin profile', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(5, 'admin.profile.delete', 'admin', 'admin profile', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(6, 'admin.view', 'admin', 'admin', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(7, 'admin.create', 'admin', 'admin', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(8, 'admin.store', 'admin', 'admin', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(9, 'admin.edit', 'admin', 'admin', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(10, 'admin.update', 'admin', 'admin', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(11, 'admin.delete', 'admin', 'admin', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(12, 'blog.category.view', 'admin', 'blog category', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(13, 'blog.category.create', 'admin', 'blog category', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(14, 'blog.category.translate', 'admin', 'blog category', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(15, 'blog.category.store', 'admin', 'blog category', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(16, 'blog.category.edit', 'admin', 'blog category', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(17, 'blog.category.update', 'admin', 'blog category', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(18, 'blog.category.delete', 'admin', 'blog category', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(19, 'blog.view', 'admin', 'blog', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(20, 'blog.create', 'admin', 'blog', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(21, 'blog.translate', 'admin', 'blog', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(22, 'blog.store', 'admin', 'blog', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(23, 'blog.edit', 'admin', 'blog', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(24, 'blog.update', 'admin', 'blog', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(25, 'blog.delete', 'admin', 'blog', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(26, 'blog.comment.view', 'admin', 'blog comment', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(27, 'blog.comment.update', 'admin', 'blog comment', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(28, 'blog.comment.delete', 'admin', 'blog comment', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(29, 'role.view', 'admin', 'role', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(30, 'role.create', 'admin', 'role', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(31, 'role.store', 'admin', 'role', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(32, 'role.assign', 'admin', 'role', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(33, 'role.edit', 'admin', 'role', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(34, 'role.update', 'admin', 'role', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(35, 'role.delete', 'admin', 'role', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(36, 'setting.view', 'admin', 'setting', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(37, 'setting.update', 'admin', 'setting', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(38, 'basic.payment.view', 'admin', 'basic payment', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(39, 'basic.payment.update', 'admin', 'basic payment', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(40, 'contect.message.view', 'admin', 'contect message', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(41, 'contect.message.delete', 'admin', 'contect message', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(42, 'currency.view', 'admin', 'currency', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(43, 'currency.create', 'admin', 'currency', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(44, 'currency.store', 'admin', 'currency', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(45, 'currency.edit', 'admin', 'currency', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(46, 'currency.update', 'admin', 'currency', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(47, 'currency.delete', 'admin', 'currency', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(48, 'media.view', 'admin', 'media', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(49, 'media.create', 'admin', 'media', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(50, 'media.store', 'admin', 'media', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(51, 'media.edit', 'admin', 'media', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(52, 'media.update', 'admin', 'media', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(53, 'media.delete', 'admin', 'media', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(54, 'customer.view', 'admin', 'customer', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(55, 'customer.bulk.mail', 'admin', 'customer', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(56, 'customer.create', 'admin', 'customer', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(57, 'customer.store', 'admin', 'customer', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(58, 'customer.edit', 'admin', 'customer', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(59, 'customer.update', 'admin', 'customer', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(60, 'customer.delete', 'admin', 'customer', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(61, 'language.view', 'admin', 'language', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(62, 'language.create', 'admin', 'language', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(63, 'language.store', 'admin', 'language', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(64, 'language.edit', 'admin', 'language', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(65, 'language.update', 'admin', 'language', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(66, 'language.delete', 'admin', 'language', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(67, 'language.translate', 'admin', 'language', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(68, 'language.single.translate', 'admin', 'language', '2024-06-03 02:02:31', '2024-06-03 02:02:31'),
(69, 'menu.view', 'admin', 'menu builder', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(70, 'menu.create', 'admin', 'menu builder', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(71, 'menu.store', 'admin', 'menu builder', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(72, 'menu.edit', 'admin', 'menu builder', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(73, 'menu.update', 'admin', 'menu builder', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(74, 'menu.delete', 'admin', 'menu builder', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(75, 'page.management', 'admin', 'page builder', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(78, 'newsletter.view', 'admin', 'newsletter', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(79, 'newsletter.mail', 'admin', 'newsletter', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(80, 'newsletter.delete', 'admin', 'newsletter', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(81, 'testimonial.view', 'admin', 'testimonial', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(82, 'testimonial.create', 'admin', 'testimonial', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(83, 'testimonial.translate', 'admin', 'testimonial', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(84, 'testimonial.store', 'admin', 'testimonial', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(85, 'testimonial.edit', 'admin', 'testimonial', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(86, 'testimonial.update', 'admin', 'testimonial', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(87, 'testimonial.delete', 'admin', 'testimonial', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(88, 'faq.view', 'admin', 'faq', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(89, 'faq.create', 'admin', 'faq', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(90, 'faq.translate', 'admin', 'faq', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(91, 'faq.store', 'admin', 'faq', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(92, 'faq.edit', 'admin', 'faq', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(93, 'faq.update', 'admin', 'faq', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(94, 'faq.delete', 'admin', 'faq', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(95, 'location.view', 'admin', 'locations', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(96, 'location.create', 'admin', 'locations', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(97, 'location.store', 'admin', 'locations', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(98, 'location.edit', 'admin', 'locations', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(99, 'location.update', 'admin', 'locations', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(100, 'location.delete', 'admin', 'locations', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(101, 'instructor.request.list', 'admin', 'instructor request', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(102, 'instructor.request.setting', 'admin', 'instructor request', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(103, 'course.management', 'admin', 'courses', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(104, 'course.certificate.management', 'admin', 'course certificate management', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(105, 'badge.management', 'admin', 'Badges', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(106, 'order.management', 'admin', 'order management', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(107, 'coupon.management', 'admin', 'coupon management', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(108, 'withdraw.management', 'admin', 'withdraw management', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(109, 'appearance.management', 'admin', 'site appearance management', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(110, 'section.management', 'admin', 'site appearance management', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(111, 'brand.management', 'admin', 'brand management', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(112, 'footer.management', 'admin', 'footer management', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(113, 'social.link.management', 'admin', 'social link management', '2024-06-03 02:02:32', '2024-06-03 02:02:32'),
(114, 'addon.view', 'admin', 'Addons', '2024-12-17 02:03:45', '2024-12-17 02:03:45'),
(115, 'addon.install', 'admin', 'Addons', '2024-12-17 02:03:45', '2024-12-17 02:03:45'),
(116, 'addon.update', 'admin', 'Addons', '2024-12-17 02:03:45', '2024-12-17 02:03:45'),
(117, 'addon.status.change', 'admin', 'Addons', '2024-12-17 02:03:45', '2024-12-17 02:03:45'),
(118, 'addon.remove', 'admin', 'Addons', '2024-12-17 02:03:45', '2024-12-17 02:03:45'),
(119, 'landing-page.message.delete', 'admin', 'landing page message', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(120, 'landing-page.message.view', 'admin', 'landing page message', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(121, 'landing-page-message.delete', 'admin', 'landing page message', '2026-05-12 07:58:55', '2026-05-12 07:58:55'),
(122, 'coach-landing-page.view', 'admin', 'coach landing pages', '2026-05-12 07:58:55', '2026-05-12 07:58:55'),
(123, 'membership-plan.view', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(124, 'membership-plan.create', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(125, 'membership-plan.store', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(126, 'membership-plan.edit', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(127, 'membership-plan.update', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(128, 'membership-plan.delete', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(129, 'referral.update-percent', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(130, 'referral.view', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(131, 'referral.approve', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(132, 'referral.pay', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(133, 'referral.reverse', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(134, 'referral.settings.view', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(135, 'referral.settings.update', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(136, 'referral.reject', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(137, 'settings.view', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(138, 'user-membership.view', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(139, 'user-membership.confirm', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(140, 'user-membership.cancel', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(141, 'user-membership.refund', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(142, 'user-membership.extend', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(143, 'landingpage.message.view', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(144, 'landingpage.message.delete', 'admin', NULL, '2026-05-15 12:26:12', '2026-05-15 12:26:12'),
(145, 'subscriptions.management', 'admin', 'subscription management', '2026-05-28 06:27:43', '2026-05-28 06:27:43'),
(146, 'announcement.view', 'admin', NULL, '2026-05-18 06:32:33', '2026-05-18 06:32:33'),
(147, 'announcement.toggle-status', 'admin', NULL, '2026-05-18 06:32:33', '2026-05-18 06:32:33'),
(148, 'announcement.delete', 'admin', NULL, '2026-05-18 06:32:33', '2026-05-18 06:32:33'),
(149, 'theme.view', 'admin', NULL, '2026-05-25 13:22:57', '2026-05-25 13:22:57'),
(150, 'theme.create', 'admin', NULL, '2026-05-25 13:22:57', '2026-05-25 13:22:57'),
(151, 'theme.update', 'admin', NULL, '2026-05-25 13:22:57', '2026-05-25 13:22:57'),
(152, 'theme.delete', 'admin', NULL, '2026-05-25 13:22:57', '2026-05-25 13:22:57'),
(153, 'announcement.store', 'admin', NULL, '2026-05-27 13:17:01', '2026-05-27 13:17:01'),
(154, 'announcement.update', 'admin', NULL, '2026-05-27 13:17:01', '2026-05-27 13:17:01'),
(155, 'instructor.request.update', 'admin', 'instructor request', '2026-05-28 06:40:46', '2026-05-28 06:40:46'),
(156, 'activity-log.view', 'admin', 'activity log', '2026-06-02 07:31:04', '2026-06-02 07:31:04'),
(157, 'custom_domain.view', 'admin', NULL, '2026-06-09 15:26:10', '2026-06-09 15:26:10'),
(158, 'custom_domain.manage', 'admin', NULL, '2026-06-09 15:26:10', '2026-06-09 15:26:10'),
(159, 'custom_domain.settings', 'admin', NULL, '2026-06-09 15:26:10', '2026-06-09 15:26:10'),
(160, 'trial-session.view', 'admin', NULL, '2026-07-03 09:39:37', '2026-07-03 09:39:37');

-- --------------------------------------------------------

--
-- Table structure for table `personal_access_tokens`
--

CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `personal_access_tokens`
--

INSERT INTO `personal_access_tokens` (`id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(21, 'App\\Models\\User', 1026, 'student', 'f14a15b40e2d307637364426bb44a55a608d27e44c569ee7942d9cf686c11246', '[\"*\"]', '2026-01-29 10:57:54', NULL, '2026-01-21 12:28:18', '2026-01-29 10:57:54'),
(23, 'App\\Models\\User', 1026, 'student', '65553ffbcff8f14ffd62b968d423423adc4b5c9a90dc5203a1d248d1f913b686', '[\"*\"]', '2026-01-29 17:00:44', NULL, '2026-01-21 13:37:24', '2026-01-29 17:00:44'),
(24, 'App\\Models\\User', 1026, 'student', 'ca64601d44b97bec85cf9ececdc84aa5439665a09537bda895895d9cca4e4f6f', '[\"*\"]', '2026-01-29 18:20:51', NULL, '2026-01-21 15:29:15', '2026-01-29 18:20:51'),
(26, 'App\\Models\\User', 1026, 'student', 'a686900127be7bd524fa4e68d6aa5f9e528e9f285db148e1ea0fe9108dac70f0', '[\"*\"]', NULL, NULL, '2026-01-27 14:26:35', '2026-01-27 14:26:35'),
(29, 'App\\Models\\User', 1026, 'student', '5a0208dd22be6ee4a6994819412cc222c6c2f05c69f450a38218c536fd170b65', '[\"*\"]', '2026-01-29 12:23:33', NULL, '2026-01-29 10:57:13', '2026-01-29 12:23:33'),
(31, 'App\\Models\\User', 1026, 'student', '6803b5cab73cd71d3fd992e8031441b8057d57a5133158dde35cb8967d3b18a1', '[\"*\"]', '2026-01-29 12:54:56', NULL, '2026-01-29 12:36:05', '2026-01-29 12:54:56'),
(36, 'App\\Models\\User', 1026, 'student', 'b7ed3ee44560715dc566bac11d8b00c4c5e9d26723283763b03bf6d28ea8ded5', '[\"*\"]', '2026-01-29 16:21:50', NULL, '2026-01-29 15:52:19', '2026-01-29 16:21:50'),
(39, 'App\\Models\\User', 1026, 'student', '2384d82ba8b2ffeccc000cf2ef969200607bb73719ee722aa0afc77a34b35244', '[\"*\"]', NULL, NULL, '2026-01-29 16:22:38', '2026-01-29 16:22:38'),
(40, 'App\\Models\\User', 1026, 'student', 'eebc3ca3ed7249145aef087684dd22b3f837b5eb43652f3c32f6974b7e2e829c', '[\"*\"]', NULL, NULL, '2026-01-29 16:22:54', '2026-01-29 16:22:54'),
(41, 'App\\Models\\User', 1026, 'student', '84b83e667199403fcbafb1f392df0e719a227b8d1754a851675b94a84e1726a9', '[\"*\"]', NULL, NULL, '2026-01-29 16:23:01', '2026-01-29 16:23:01'),
(51, 'App\\Models\\User', 1028, 'student', 'd93c718f6824061e8df73dd3dd8b639bd657455bbb7a9381de841fc2d54b7bb3', '[\"*\"]', '2026-02-12 18:40:01', NULL, '2026-02-12 18:29:18', '2026-02-12 18:40:01'),
(52, 'App\\Models\\User', 1077, 'student', '37d7115ad25e20198ef724dae563d6f40b3ac465ab02f6a0918afd803a45536a', '[\"*\"]', NULL, NULL, '2026-02-12 19:09:17', '2026-02-12 19:09:17'),
(53, 'App\\Models\\User', 1079, 'student', 'ea6befde263c0fa868cda47d4e6601398c56023145305e5f300d6ba5ee765e30', '[\"*\"]', NULL, NULL, '2026-02-13 15:57:51', '2026-02-13 15:57:51'),
(54, 'App\\Models\\User', 1079, 'student', '335402afa3ade9ef0782ecfbb1ce1368f3ef785e3df83ebb26f1d03e85641827', '[\"*\"]', NULL, NULL, '2026-02-13 16:34:11', '2026-02-13 16:34:11'),
(55, 'App\\Models\\User', 1079, 'student', 'e8293feeaaee8d58a113879ccb6584b048bd6142b0e23e3687b59a4793ee4323', '[\"*\"]', NULL, NULL, '2026-02-13 17:01:16', '2026-02-13 17:01:16'),
(56, 'App\\Models\\User', 1028, 'student', 'a94fc8dc459efda32309d234c16d5e54bf4d8cc647044e2f9124e2af223e45df', '[\"*\"]', NULL, NULL, '2026-02-13 17:06:04', '2026-02-13 17:06:04'),
(57, 'App\\Models\\User', 1028, 'student', '6ad7c3002a63c7643a374588ddb82b5e91337c44afc22774b95453cb612618af', '[\"*\"]', NULL, NULL, '2026-02-13 17:06:41', '2026-02-13 17:06:41'),
(58, 'App\\Models\\User', 1079, 'student', '88f42720b527f185180fe6ced80617e860c1b934c49c9c3f6e8a87d3aac10bfe', '[\"*\"]', NULL, NULL, '2026-02-13 17:08:19', '2026-02-13 17:08:19'),
(59, 'App\\Models\\User', 1028, 'student', 'cf87c25eaa3036cebf514da0307df5a1a2c380d7305061a09975169c469d68cb', '[\"*\"]', NULL, NULL, '2026-02-13 17:09:07', '2026-02-13 17:09:07'),
(60, 'App\\Models\\User', 1079, 'student', 'fc023a1e13cad29869f3b83600233cc7a3bb3c5db1c2b1360fe09eb1473166eb', '[\"*\"]', NULL, NULL, '2026-02-13 17:14:29', '2026-02-13 17:14:29'),
(61, 'App\\Models\\User', 1079, 'student', 'bdb725dd8111542e1078993019d3c19b7c83de28a7ca61dcf13059b23f743440', '[\"*\"]', NULL, NULL, '2026-02-13 17:16:46', '2026-02-13 17:16:46'),
(62, 'App\\Models\\User', 1079, 'student', '50bb1800a1bb16b742ae9b45d2497e1fbb5ac4da514c105815e86e5414139bfc', '[\"*\"]', NULL, NULL, '2026-02-13 17:19:43', '2026-02-13 17:19:43'),
(63, 'App\\Models\\User', 1079, 'student', 'e77c523247e51e885d3b722358a1b935aa955b6288173ef18313144c2c74f872', '[\"*\"]', NULL, NULL, '2026-02-13 17:20:17', '2026-02-13 17:20:17'),
(64, 'App\\Models\\User', 1028, 'student', 'ead6cbee133bdea89b99e814d11c36b2c85bc99b4eafb9e8043b7bb9ff00dc10', '[\"*\"]', NULL, NULL, '2026-02-13 17:22:41', '2026-02-13 17:22:41'),
(65, 'App\\Models\\User', 1078, 'student', '0a09da008a164df59c324e65bf53f3035a0f5311f029c6a744c4188b4d60406b', '[\"*\"]', NULL, NULL, '2026-02-13 17:26:59', '2026-02-13 17:26:59'),
(66, 'App\\Models\\User', 1079, 'student', '1758554571ae6753077abbfbc120b33a72d911327e56776514063936723844f4', '[\"*\"]', '2026-02-13 17:40:03', NULL, '2026-02-13 17:39:47', '2026-02-13 17:40:03'),
(67, 'App\\Models\\User', 1079, 'student', '53ae606c02ff98c420b4d804aa9991e521f5e8a50381c41c1f6edbc112f4a3c0', '[\"*\"]', NULL, NULL, '2026-02-13 17:53:18', '2026-02-13 17:53:18'),
(68, 'App\\Models\\User', 1078, 'student', 'd07667f4aa40d35deae3a835f312e30370ed858b52d0f133da2cdbcc6b24652d', '[\"*\"]', NULL, NULL, '2026-02-13 17:54:29', '2026-02-13 17:54:29'),
(69, 'App\\Models\\User', 1026, 'student', 'a910daac64fc6c3772f1e8376f751d31d3ecca44310d8d4c954908a6c5d64284', '[\"*\"]', '2026-02-13 17:55:49', NULL, '2026-02-13 17:55:19', '2026-02-13 17:55:49'),
(70, 'App\\Models\\User', 1028, 'student', 'e76e5b8853eb1b3f02f5887b1cf54653e75a3b5854ae57284cf6a4fe25ed9b41', '[\"*\"]', '2026-02-16 10:29:54', NULL, '2026-02-13 17:56:21', '2026-02-16 10:29:54'),
(71, 'App\\Models\\User', 1079, 'student', 'fb3a6e62edfb634cc54b16ab550967ed3ff081071ceeb6b25e452a549ef3559c', '[\"*\"]', '2026-02-16 10:31:21', NULL, '2026-02-16 10:31:11', '2026-02-16 10:31:21'),
(72, 'App\\Models\\User', 1079, 'student', '999304e9a05d0a98a0efe32f62bdba7b488df22987177069aba61d1959ea8cea', '[\"*\"]', '2026-02-16 12:04:37', NULL, '2026-02-16 11:08:43', '2026-02-16 12:04:37'),
(73, 'App\\Models\\User', 1078, 'student', '7b697257a97767d711ceb42cf5d970c205a16e80f53e9b645fca4e9c4c55db5d', '[\"*\"]', '2026-02-16 11:53:22', NULL, '2026-02-16 11:53:02', '2026-02-16 11:53:22'),
(74, 'App\\Models\\User', 1079, 'student', '8b19e542541ef56a9fbe7ff2ccdf582698bc869fbb6e96c5fe117798984683d3', '[\"*\"]', '2026-04-30 16:52:27', NULL, '2026-02-16 12:04:22', '2026-04-30 16:52:27'),
(75, 'App\\Models\\User', 1079, 'student', '90414506e425c222bf9f7e6ee4fc03306dde5530510c8921be3fc85b35336bf2', '[\"*\"]', '2026-03-02 16:16:24', NULL, '2026-02-16 19:06:13', '2026-03-02 16:16:24'),
(76, 'App\\Models\\User', 1079, 'student', 'e6af7379bd4d85601dff8afbee0404197d6e9a139b2d3b906ba47f0b8ba97913', '[\"*\"]', NULL, NULL, '2026-02-16 19:24:31', '2026-02-16 19:24:31'),
(77, 'App\\Models\\User', 1079, 'student', 'a5e7b98e42ab7566504fe6a033d8746a6e07d9e01494f4f8872a3795741b4760', '[\"*\"]', '2026-02-20 00:39:38', NULL, '2026-02-16 23:55:28', '2026-02-20 00:39:38'),
(78, 'App\\Models\\User', 1079, 'student', 'e14559ac1e772989f65e00de4206390beee29868d9e1f982d5a2689457979b5e', '[\"*\"]', NULL, NULL, '2026-02-17 11:16:56', '2026-02-17 11:16:56'),
(79, 'App\\Models\\User', 1078, 'student', '1c345223eb25a42e12aead05178e1b3e6136fc7581846de6932c82b5811d5d7c', '[\"*\"]', NULL, NULL, '2026-02-17 11:17:17', '2026-02-17 11:17:17'),
(80, 'App\\Models\\User', 1079, 'student', 'ce1e432c51d7d90c95014b770489a456b1da4bfeaa78d06bdca0c72b4c42c1b1', '[\"*\"]', NULL, NULL, '2026-02-17 11:17:26', '2026-02-17 11:17:26'),
(81, 'App\\Models\\User', 1079, 'student', '819087e62974744684dcd40a177344436fda6b408e2698b344e9493c89125e21', '[\"*\"]', '2026-03-01 19:16:43', NULL, '2026-02-17 11:47:44', '2026-03-01 19:16:43'),
(82, 'App\\Models\\User', 1079, 'student', 'bcf35d329f760b53ed82d8049204723288caaffea806dffff989d6bd4d5d3aa1', '[\"*\"]', '2026-03-01 19:04:17', NULL, '2026-02-17 12:25:17', '2026-03-01 19:04:17'),
(83, 'App\\Models\\User', 1079, 'student', '95deea73b68fbcf3fed8e46a21918f6cefbb56697df7aa6375cdf4a8d22a53e7', '[\"*\"]', '2026-02-25 12:55:57', NULL, '2026-02-19 11:45:08', '2026-02-25 12:55:57'),
(84, 'App\\Models\\User', 1026, 'student', '76c426be1322c8f4cc36c47a447647476ac3a2c2387335ef3a01875d3c668ff2', '[\"*\"]', NULL, NULL, '2026-02-19 16:08:50', '2026-02-19 16:08:50'),
(85, 'App\\Models\\User', 1079, 'student', 'aa913aa14367e9eb4d4729a93e72b4790fd61e0a8272dca105977e971f69a89c', '[\"*\"]', NULL, NULL, '2026-02-19 16:12:35', '2026-02-19 16:12:35'),
(86, 'App\\Models\\User', 1079, 'student', '85c41efb8b7531e38c8f02df12216c74c0ddf917dc78b3595b4108ce1b04fa17', '[\"*\"]', NULL, NULL, '2026-02-19 16:24:16', '2026-02-19 16:24:16'),
(87, 'App\\Models\\User', 1079, 'student', 'e735458333d5cd12e02374e6634563b6b7a427a278890f5173322a7e6976eae3', '[\"*\"]', NULL, NULL, '2026-02-19 23:57:34', '2026-02-19 23:57:34'),
(88, 'App\\Models\\User', 1079, 'student', 'c7fa91ea34b9e339ddf9e4070e595367e5efba1a5ac6a054b2ca4e9cd2987309', '[\"*\"]', NULL, NULL, '2026-02-20 00:07:27', '2026-02-20 00:07:27'),
(89, 'App\\Models\\User', 1079, 'student', '6b788dc7158654a56088cc34e2b9396c7cb1355fe0e9e7f171a16be1ba41db80', '[\"*\"]', NULL, NULL, '2026-02-20 00:08:33', '2026-02-20 00:08:33'),
(91, 'App\\Models\\User', 1079, 'student', 'aa78313e62d31ebdd295f1eff4a0baf0f94a6f3446a8ed17f73de6612214da63', '[\"*\"]', '2026-02-25 01:37:26', NULL, '2026-02-20 01:58:52', '2026-02-25 01:37:26'),
(96, 'App\\Models\\User', 1079, 'student', '47cb8f8ac231fdec4b4ee584a1cada65cc52da16e197c3bca94e42b4350f0ae8', '[\"*\"]', '2026-03-02 16:55:26', NULL, '2026-02-20 13:08:08', '2026-03-02 16:55:26'),
(97, 'App\\Models\\User', 1028, 'student', '43ca402ea28a7cd8db8bd1a257c42bf7dd18a96e02990028e8c5c26f28786e71', '[\"*\"]', '2026-02-25 11:45:39', NULL, '2026-02-25 11:34:28', '2026-02-25 11:45:39'),
(98, 'App\\Models\\User', 1087, 'student', 'ede180185ab15f5c9181c77c64b823b8ca9619bc024e6a76ffd414bd3a10be96', '[\"*\"]', '2026-02-25 15:34:56', NULL, '2026-02-25 13:05:29', '2026-02-25 15:34:56'),
(99, 'App\\Models\\User', 1079, 'student', '6b56b1d63caa8d36f9c8b618bec9bb322f74b0384c79c2162d41a1a7da4a03c2', '[\"*\"]', '2026-02-26 02:14:24', NULL, '2026-02-26 01:05:38', '2026-02-26 02:14:24'),
(100, 'App\\Models\\User', 1079, 'student', '6b580daa10c77e87e0acd4eedc7a003eb0bee144b3e01aa9d2063e6aebabf963', '[\"*\"]', '2026-02-26 02:22:46', NULL, '2026-02-26 01:15:50', '2026-02-26 02:22:46'),
(101, 'App\\Models\\User', 1079, 'student', '1e16df8c28bd20819d12a6016a776748582f8ebb8d62383d6125593538c32ea7', '[\"*\"]', '2026-02-26 02:26:55', NULL, '2026-02-26 02:24:25', '2026-02-26 02:26:55'),
(102, 'App\\Models\\User', 1079, 'student', '34dbf8f6a4a8a3a71f8a160100c7ddda2c6a5379355c31b222a71ef92dd74104', '[\"*\"]', NULL, NULL, '2026-02-26 02:29:01', '2026-02-26 02:29:01'),
(103, 'App\\Models\\User', 1077, 'student', '2d9d1d04b8493bc7b43059076179537c9057d8b6806102d34d39717b11b07801', '[\"*\"]', NULL, NULL, '2026-02-26 02:29:28', '2026-02-26 02:29:28'),
(104, 'App\\Models\\User', 1079, 'student', 'd3314ab6f58f001aa2180f90194f45ec58166b32786d73c014c2401589fb33d7', '[\"*\"]', NULL, NULL, '2026-02-26 02:29:39', '2026-02-26 02:29:39'),
(105, 'App\\Models\\User', 1079, 'student', 'fac0b802c9e9ac242412dc84d74f3d1042b2e875639dc50ced3df59466463ef8', '[\"*\"]', NULL, NULL, '2026-02-27 11:42:27', '2026-02-27 11:42:27'),
(107, 'App\\Models\\User', 1087, 'student', 'c7e5ada2df8873b7bb522a61cfdc495042bb6ccefaf77557ac0d8889d669406f', '[\"*\"]', NULL, NULL, '2026-02-27 12:08:09', '2026-02-27 12:08:09'),
(108, 'App\\Models\\User', 1079, 'student', '5e7ece5b7ba170232b1b7d2d6e83f1f7740f5ba556a0e64fa9020fd0ad745de8', '[\"*\"]', NULL, NULL, '2026-02-27 12:18:41', '2026-02-27 12:18:41'),
(109, 'App\\Models\\User', 1086, 'student', '25411a1c15d610f3751947a303dfbe4cc31e23f3e3ba875a1e43539f6a468876', '[\"*\"]', NULL, NULL, '2026-02-27 12:19:03', '2026-02-27 12:19:03'),
(110, 'App\\Models\\User', 1079, 'student', 'd54b04388cf7fe01e15c31fc20e20bcb5c1e5820ec7dc856a38e714e89d41bcd', '[\"*\"]', NULL, NULL, '2026-02-27 12:23:24', '2026-02-27 12:23:24'),
(111, 'App\\Models\\User', 1079, 'student', 'a1a6493319748a26f9112ef3e4d09f7ed43dd3b7fd0e54c759296117391fa612', '[\"*\"]', NULL, NULL, '2026-02-27 12:37:59', '2026-02-27 12:37:59'),
(112, 'App\\Models\\User', 1079, 'student', '109eaa191e675fe82bf065f63178f0fb0326a9965990e1aa2d85bcedc97ed15c', '[\"*\"]', '2026-02-27 12:40:31', NULL, '2026-02-27 12:40:02', '2026-02-27 12:40:31'),
(113, 'App\\Models\\User', 1093, 'student', '567d0c65b2d95e3322c38f3b1ba4f81446d5a464b77f163236c5b5d13725483f', '[\"*\"]', NULL, NULL, '2026-03-01 11:08:17', '2026-03-01 11:08:17'),
(114, 'App\\Models\\User', 1094, 'student', '4671c4616ad38cc189c96c65a280ad0c5b6de6ec6e4986e73ac887b387e76703', '[\"*\"]', NULL, NULL, '2026-03-01 11:12:27', '2026-03-01 11:12:27'),
(115, 'App\\Models\\User', 1094, 'student', '473fef192cdb1fa0b6885f1181ea3a1c7e317ffa8b02dbecd6b8b6e3f04b05e8', '[\"*\"]', '2026-03-01 20:03:58', NULL, '2026-03-01 18:39:09', '2026-03-01 20:03:58'),
(116, 'App\\Models\\User', 1079, 'student', '9bb5f6f332bace32476ca2c4d5c9c10d7008a5d7431414fc00d53ae5c7374350', '[\"*\"]', NULL, NULL, '2026-03-01 18:58:33', '2026-03-01 18:58:33'),
(117, 'App\\Models\\User', 1079, 'student', '4ff4392db5178a911224f665e0a6be6614b603f7f493ea5e5476873988064db7', '[\"*\"]', '2026-03-01 19:39:30', NULL, '2026-03-01 19:03:37', '2026-03-01 19:39:30'),
(118, 'App\\Models\\User', 1079, 'student', 'bbbf79872d34460640828f771bd55f7f6c31395a8f2c3f5794f4885de9af1a26', '[\"*\"]', '2026-03-01 19:40:31', NULL, '2026-03-01 19:40:25', '2026-03-01 19:40:31'),
(119, 'App\\Models\\User', 1094, 'student', '55dd59e4c057d7bc6c6b5a99803ea602d2b18aaaa233da7dc375dc46aa8a6d31', '[\"*\"]', '2026-03-01 20:15:19', NULL, '2026-03-01 20:04:07', '2026-03-01 20:15:19'),
(120, 'App\\Models\\User', 1093, 'student', 'b3d2c62d5006869a5135ddf505c01489596a8f982a4f011035a193710cc5d61f', '[\"*\"]', '2026-04-30 16:06:00', NULL, '2026-03-01 20:18:12', '2026-04-30 16:06:00'),
(122, 'App\\Models\\User', 1079, 'student', '62c7720c7596f0a6995f0fba8b2d7d6187082bcf3e6ab9e31b86deecfcf601d7', '[\"*\"]', '2026-03-02 01:50:46', NULL, '2026-03-02 01:19:53', '2026-03-02 01:50:46'),
(123, 'App\\Models\\User', 1079, 'student', 'eb7d15d69971899de73fe0248443b53e1b7e366e006337337ff3759e739530c0', '[\"*\"]', '2026-03-02 16:44:32', NULL, '2026-03-02 16:07:28', '2026-03-02 16:44:32'),
(124, 'App\\Models\\User', 1079, 'student', '02dc31781cfe97bbcd5c579d4dd07777afec1ae0efd1816d93d18b6d325eb557', '[\"*\"]', '2026-03-02 16:46:28', NULL, '2026-03-02 16:15:55', '2026-03-02 16:46:28'),
(125, 'App\\Models\\User', 1079, 'student', '398168b2504756a02614290d6822aeafcb5758f16e85d22345408347ec78c667', '[\"*\"]', '2026-03-02 20:48:53', NULL, '2026-03-02 19:35:34', '2026-03-02 20:48:53'),
(126, 'App\\Models\\User', 1079, 'student', '5d1acd830f0d5d20735c409848b6c0367bd66927a6323b255c3a1e30efe50c9a', '[\"*\"]', '2026-03-03 00:32:27', NULL, '2026-03-02 23:54:06', '2026-03-03 00:32:27'),
(127, 'App\\Models\\User', 1093, 'student', 'a4750cc4ccb91f2b933552bd4f6caa14e2cbeac29457219ea5b16fe781c60e1f', '[\"*\"]', '2026-03-03 22:29:38', NULL, '2026-03-03 00:07:19', '2026-03-03 22:29:38'),
(128, 'App\\Models\\User', 1093, 'student', '26a2351e01de9fca6e437cffeba10ff3600bc34d06d9c23929f3c402661c0b9d', '[\"*\"]', NULL, NULL, '2026-03-03 00:14:44', '2026-03-03 00:14:44'),
(129, 'App\\Models\\User', 1093, 'student', '812459803efc13628671ef2139f491d65896ee414e5229efd6b90f9cecfcf73a', '[\"*\"]', '2026-03-03 00:34:59', NULL, '2026-03-03 00:34:57', '2026-03-03 00:34:59'),
(130, 'App\\Models\\User', 1093, 'student', '7881394e8b9dd3c5eb7c7db82957ff049db24fbc256dae33fb0adafed604b8cf', '[\"*\"]', '2026-03-03 00:46:56', NULL, '2026-03-03 00:39:03', '2026-03-03 00:46:56'),
(131, 'App\\Models\\User', 1093, 'student', '13d73d08ea26387c21db291a579224827aaba04bf13532fc1f3dfc57620ea0ae', '[\"*\"]', '2026-04-30 16:00:56', NULL, '2026-03-03 11:20:03', '2026-04-30 16:00:56'),
(133, 'App\\Models\\User', 1093, 'student', 'cbf2d96d91c96694c1acc9200959692c255e108d90a9b5a1dc242f80bb6a2afb', '[\"*\"]', NULL, NULL, '2026-03-03 11:40:12', '2026-03-03 11:40:12'),
(135, 'App\\Models\\User', 1079, 'student', '25a817f101303ecf50f4bd30694c8cdf5cfe347f2110995fe7db38a770428e24', '[\"*\"]', '2026-03-03 15:41:39', NULL, '2026-03-03 15:40:50', '2026-03-03 15:41:39'),
(136, 'App\\Models\\User', 1079, 'student', 'ce890f1bf305d3fe59e85061810fc9b8d9fb04fd6c2825c83d3533211916cf56', '[\"*\"]', '2026-03-05 15:25:18', NULL, '2026-03-03 18:48:45', '2026-03-05 15:25:18'),
(137, 'App\\Models\\User', 1079, 'student', '5e3f2cfe91967cb2fd2f7ee0830cfdb148d64aa2492534576ffb6ebd44d08802', '[\"*\"]', '2026-04-18 19:32:56', NULL, '2026-04-17 23:43:04', '2026-04-18 19:32:56'),
(138, 'App\\Models\\User', 1079, 'student', '1cbf6e8b102d869e91bcae0f441992cc1056fc1806f3e33e5109b8dcc46735a3', '[\"*\"]', NULL, NULL, '2026-04-18 08:26:46', '2026-04-18 08:26:46'),
(140, 'App\\Models\\User', 1093, 'student', 'd1deefc8a1a28401851db3095088fe1c623c8d0d2edb492d9da325bb1b03e8af', '[\"*\"]', '2026-04-19 17:14:21', NULL, '2026-04-18 23:09:19', '2026-04-19 17:14:21'),
(142, 'App\\Models\\User', 1079, 'student', 'd354c7d626d455a7bc421e992830cd3b046640bf0df05946455f2a898ccffb1f', '[\"*\"]', '2026-04-19 14:25:52', NULL, '2026-04-19 14:21:16', '2026-04-19 14:25:52'),
(150, 'App\\Models\\User', 1079, 'student', '4ca96826fc1aca7f0d9e01c22bf8aa0a3be008bcc0959b2a5f78c8626d580d77', '[\"*\"]', '2026-04-22 18:30:04', NULL, '2026-04-22 18:28:25', '2026-04-22 18:30:04'),
(151, 'App\\Models\\User', 1093, 'student', '7ce84b6eaf98056da7126b212dc945ab669628bda3cb3d2ca055b9bed8674166', '[\"*\"]', '2026-04-30 19:08:04', NULL, '2026-04-22 18:47:50', '2026-04-30 19:08:04'),
(154, 'App\\Models\\User', 1079, 'student', '3a47d35f2db23051ab74e04f5945c6bd349dc59c4c5cc2dec5f4b0aec4cbe479', '[\"*\"]', '2026-04-28 20:23:06', NULL, '2026-04-28 20:22:14', '2026-04-28 20:23:06'),
(158, 'App\\Models\\User', 1093, 'student', '9ed01f5bffa438a1cf7d9dabd6f7514c45fb0d37b9175887e7c11d5bc0696859', '[\"*\"]', '2026-05-01 11:58:39', NULL, '2026-04-29 21:35:48', '2026-05-01 11:58:39'),
(159, 'App\\Models\\User', 1123, 'student', 'a92a1faadefe68af1f116872312bc4dc9d63b24cdbd40e02e2e618a373e4e89e', '[\"*\"]', '2026-04-30 11:14:43', NULL, '2026-04-30 11:14:41', '2026-04-30 11:14:43'),
(160, 'App\\Models\\User', 1093, 'student', '3c3ba3923dae4b0a44bdbcd7a61c612d354193442a65750ddf813ae4b8dddf2a', '[\"*\"]', '2026-04-30 16:51:44', NULL, '2026-04-30 11:39:15', '2026-04-30 16:51:44'),
(162, 'App\\Models\\User', 1128, 'student', 'b58fef30d131023f167814ff83e6934bba08b2312f094319bf908e89c70705c0', '[\"*\"]', '2026-05-01 09:45:37', NULL, '2026-04-30 12:41:08', '2026-05-01 09:45:37'),
(165, 'App\\Models\\User', 1132, 'student', 'cf5dde6d81363f6c04c100719b52a125b8586cf373d37cedd00741eed4768dfe', '[\"*\"]', '2026-04-30 16:45:15', NULL, '2026-04-30 16:45:14', '2026-04-30 16:45:15'),
(166, 'App\\Models\\User', 1079, 'student', '86d41540c7b3ea24d4f04bc1862291cadf06d5c194810008e3dc7350388d70d0', '[\"*\"]', '2026-04-30 16:55:23', NULL, '2026-04-30 16:49:24', '2026-04-30 16:55:23'),
(167, 'App\\Models\\User', 1133, 'student', '72a9f78a1535946b67a04784ea541052c4d727d696a4c3ebe5bbc4780a5bb19c', '[\"*\"]', '2026-05-01 11:37:00', NULL, '2026-05-01 11:28:53', '2026-05-01 11:37:00'),
(168, 'App\\Models\\User', 1119, 'student', 'f489fdec9cd8754887a8a80302c304a424576a94c050f730b401e18f510718d0', '[\"*\"]', '2026-05-01 11:36:18', NULL, '2026-05-01 11:28:55', '2026-05-01 11:36:18'),
(169, 'App\\Models\\User', 1093, 'student', '4cdceb72036a21df921d66a668ba926fa06c821ba8156720a7527aca0982f258', '[\"role:student\"]', NULL, NULL, '2026-05-13 06:17:59', '2026-05-13 06:17:59'),
(170, 'App\\Models\\User', 1093, 'student', '862a1b592c9d216d71cc4342c3228fadf6a42e0732fbdb40a1c38ca0ecfe5698', '[\"role:student\"]', NULL, NULL, '2026-05-13 06:19:48', '2026-05-13 06:19:48'),
(172, 'App\\Models\\User', 1000, 'probe', 'f5ef878db7650222d64cd47795d723cd086455f4045ecd7eadcadab34de4bb74', '[\"*\"]', '2026-05-13 11:38:45', NULL, '2026-05-13 09:56:43', '2026-05-13 11:38:45'),
(173, 'App\\Models\\User', 1093, 'probe', 'eeddb4ad801706a450c5d13c25d86ea4cff56cff903a7d4d22eb5a5521fc9591', '[\"*\"]', '2026-05-13 10:48:03', NULL, '2026-05-13 09:57:19', '2026-05-13 10:48:03'),
(174, 'App\\Models\\User', 1000, 'probe', 'deef143997e08528fe1c4b86707c30e77febd65f2f391aab3dd2ab48fecb315b', '[\"*\"]', '2026-05-13 12:18:00', NULL, '2026-05-13 12:17:48', '2026-05-13 12:18:00'),
(175, 'App\\Models\\User', 1093, 'probe', '371ebb12976b1059491ce25301d07e275d7aa504edcd0e1a2e0f91528b245e73', '[\"*\"]', '2026-05-13 12:32:36', NULL, '2026-05-13 12:31:39', '2026-05-13 12:32:36'),
(177, 'App\\Models\\User', 1093, 'probe', '7e413eb58d6c44790abbc91597ff4bb3444943ced94674d4f520e914968c7b24', '[\"*\"]', '2026-05-13 13:05:21', NULL, '2026-05-13 13:04:54', '2026-05-13 13:05:21'),
(178, 'App\\Models\\User', 1000, 'probe', '64076aab9487f08cbf8bfbe8cf2f3228224bc16c0a0805a375c67d9ce93960be', '[\"*\"]', '2026-05-13 13:33:23', NULL, '2026-05-13 13:32:40', '2026-05-13 13:33:23'),
(179, 'App\\Models\\User', 1093, 'probe', 'e72531bb7234e3958f3f073ad4e1a4d10e7ebed73445d24fc10c1c62581f8db5', '[\"*\"]', '2026-05-13 13:34:54', NULL, '2026-05-13 13:34:37', '2026-05-13 13:34:54'),
(181, 'App\\Models\\User', 1093, 'probe', 'fae506fab4d1e1344923d7e57ce109471d85d3c59a7ac6ec2bd896768803d7ba', '[\"*\"]', '2026-05-14 05:30:22', NULL, '2026-05-14 05:30:05', '2026-05-14 05:30:22'),
(182, 'App\\Models\\User', 1093, 'probe', '6053725de6a5425bf61ad9f20e84b5d61478cf967f06c625f629a4b6bc8df2df', '[\"*\"]', '2026-05-14 06:15:32', NULL, '2026-05-14 05:37:49', '2026-05-14 06:15:32'),
(183, 'App\\Models\\User', 1093, 'probe', 'cf4e59777cbab716c3b4a17638ac042dd822676946fda4f5e03350f395013374', '[\"*\"]', '2026-05-14 06:45:43', NULL, '2026-05-14 06:45:30', '2026-05-14 06:45:43'),
(184, 'App\\Models\\User', 1093, 'probe', 'b1a3f26ffd24e817f500ebf73cb431dc99378671952345a7804a17432d586fe1', '[\"*\"]', '2026-05-14 07:09:44', NULL, '2026-05-14 07:09:11', '2026-05-14 07:09:44'),
(185, 'App\\Models\\User', 1093, 'probe', 'ac09dd515e4d48410214004db1be1ed0ddb37b6c1d6967f52d5a5370f8450a70', '[\"*\"]', '2026-05-14 07:39:47', NULL, '2026-05-14 07:23:16', '2026-05-14 07:39:47'),
(186, 'App\\Models\\User', 1093, 'probe', '08f5e8945ac4128eddb3ff0929f61e42d7d3d451ee3e8b829ea6bac3e04f78e0', '[\"*\"]', '2026-05-14 07:48:37', NULL, '2026-05-14 07:43:22', '2026-05-14 07:48:37'),
(187, 'App\\Models\\User', 1093, 'probe', '970294e1b260c3d8947e02d93f7d27e5acf82a9f204188ec7f24e043d3112c95', '[\"*\"]', '2026-05-14 11:51:48', NULL, '2026-05-14 11:50:34', '2026-05-14 11:51:48'),
(188, 'App\\Models\\User', 1093, 'phase25-test', '83355a5b468c6666637bae6041f23328da4c8b8d92ece30c3d6404d854633b9f', '[\"*\"]', '2026-05-15 09:59:45', NULL, '2026-05-14 13:30:03', '2026-05-15 09:59:45'),
(190, 'App\\Models\\User', 1079, 'instructor', 'f87bd4d5c26666286072bcf7ba1e04827e4317934972d1df3348b33f73fa3dfe', '[\"role:instructor\"]', NULL, NULL, '2026-05-15 10:10:18', '2026-05-15 10:10:18'),
(193, 'App\\Models\\User', 1079, 'instructor', '3744bc461d57c49477375dd6de5dec705aefe156b36994888a394d2f800c0ec8', '[\"role:instructor\"]', NULL, NULL, '2026-05-15 11:23:49', '2026-05-15 11:23:49'),
(194, 'App\\Models\\User', 1079, 'test-android-coach', '6dd5ccb6d0a3585d8e0d67a06d4b0abd6604555eb9017c93369053ea47c1a36c', '[\"*\"]', '2026-05-15 12:31:43', NULL, '2026-05-15 11:33:13', '2026-05-15 12:31:43'),
(195, 'App\\Models\\User', 1079, 'instructor', 'df79b142c7176f452d3e2edbb5332f5315163058b14f391a204760db0ace246d', '[\"role:instructor\"]', '2026-05-18 10:46:38', NULL, '2026-05-15 11:46:46', '2026-05-18 10:46:38'),
(196, 'App\\Models\\User', 1079, 'instructor', 'fde43ee6edc712fee3c01b443281cfcd1395831314d4dfd7b25cc11b21643f2a', '[\"role:instructor\"]', '2026-05-20 10:53:28', NULL, '2026-05-18 12:43:28', '2026-05-20 10:53:28'),
(197, 'App\\Models\\User', 1079, 'instructor', '4d9a03671928cfb30978be825f187a235d775e1bebcb335ab27028c7f9f0c180', '[\"role:instructor\"]', '2026-05-20 11:16:40', NULL, '2026-05-20 10:56:13', '2026-05-20 11:16:40'),
(199, 'App\\Models\\User', 1079, 'instructor', '83778a84acd6a9cdb4e960e2b9a738039f3f153ffdca625d2975bcb160519d39', '[\"role:instructor\"]', '2026-05-20 12:07:06', NULL, '2026-05-20 11:23:49', '2026-05-20 12:07:06'),
(200, 'App\\Models\\User', 1160, 'instructor', '1e87ae4bca2d09bc745c10e5addbe1b43afb8d25888eaabe688da176e7cce00c', '[\"role:instructor\"]', '2026-06-04 11:35:03', NULL, '2026-06-04 11:34:20', '2026-06-04 11:35:03'),
(201, 'App\\Models\\User', 1079, 'instructor', '301d09388a1000692829e655af69e3b03b7b636aa79b805601149dc0d51a7701', '[\"role:instructor\"]', NULL, NULL, '2026-06-04 11:43:20', '2026-06-04 11:43:20'),
(202, 'App\\Models\\User', 1079, 'instructor', '65b1fef81fa11288eb801ceca4af0a58d982f991084ee4d113b1247e2f35a275', '[\"role:instructor\"]', NULL, NULL, '2026-06-04 11:46:30', '2026-06-04 11:46:30'),
(206, 'App\\Models\\User', 1170, 'student', '32233e7e7f176e6ce1fd7343740aec36316fca0dea780ad9ece0f140aa25fba0', '[\"role:student\"]', '2026-06-04 12:32:42', NULL, '2026-06-04 12:25:28', '2026-06-04 12:32:42'),
(207, 'App\\Models\\User', 1170, 'student', '9ccbf9c57ac9b4f8d3e356258582da0da0551e84ece90a52e0a69ae8a0c5f41c', '[\"role:student\"]', '2026-06-04 13:22:03', NULL, '2026-06-04 13:13:36', '2026-06-04 13:22:03'),
(208, 'App\\Models\\User', 1079, 'instructor', 'b33fcc57aea785cf6ea732645036563798b752aae39160b08208f1f6c66ea8f2', '[\"role:instructor\"]', '2026-06-05 16:43:45', NULL, '2026-06-05 16:43:29', '2026-06-05 16:43:45'),
(209, 'App\\Models\\User', 1079, 'instructor', 'e8c9d0e0735afb0537dc703a9e4c492ce5fefee8e7c0493554799c2a8b6de6ee', '[\"role:instructor\"]', '2026-06-22 17:25:13', NULL, '2026-06-18 18:23:50', '2026-06-22 17:25:13'),
(210, 'App\\Models\\User', 1079, 'instructor', 'cad4edec49bce13227e2c6f3c50e4b8c92a81c987592ce38ea49402252943139', '[\"role:instructor\"]', NULL, NULL, '2026-06-19 15:54:42', '2026-06-19 15:54:42'),
(211, 'App\\Models\\User', 1079, 'instructor', '48c183d3042471b6bc823850e9850f19e862c44928a0cc2d13af39a97b9bcfc4', '[\"role:instructor\"]', NULL, NULL, '2026-06-19 15:56:41', '2026-06-19 15:56:41'),
(212, 'App\\Models\\User', 1079, 'instructor', 'dbf2fde5e3470a5c86581e0eae15c9143ac4ff13980ce05a0f9a0be704feb032', '[\"role:instructor\"]', '2026-06-19 15:59:49', NULL, '2026-06-19 15:57:56', '2026-06-19 15:59:49'),
(213, 'App\\Models\\User', 1079, 'instructor', '6591eb288c2b55c972ca0f0edcb5eed0cf4e160141937b9680301b66ab59fb03', '[\"role:instructor\"]', NULL, NULL, '2026-06-19 16:02:52', '2026-06-19 16:02:52'),
(214, 'App\\Models\\User', 1079, 'instructor', 'c1bc550a6b74d511e31c2cae603fbca1e126251f52a4a38e011eb91819137607', '[\"role:instructor\"]', '2026-06-22 17:41:57', NULL, '2026-06-22 17:40:50', '2026-06-22 17:41:57'),
(215, 'App\\Models\\User', 1079, 'instructor', 'a608f700504b16cdb795113d3cd06f55de2c2d43709b5f8d3c8107d4c480806e', '[\"role:instructor\"]', '2026-06-22 17:44:03', NULL, '2026-06-22 17:42:07', '2026-06-22 17:44:03'),
(216, 'App\\Models\\User', 1079, 'instructor', '10f71c75098a7dd2def30273e450815626abb89f1b4e21ccb9858bfe55a0c64a', '[\"role:instructor\"]', '2026-06-22 17:54:17', NULL, '2026-06-22 17:51:57', '2026-06-22 17:54:17'),
(218, 'App\\Models\\User', 1170, 'student', '78f2812a2dee47abbc3e598d5446011eae6f3aff81970165705b8eb42f370cdf', '[\"role:student\"]', '2026-06-22 19:18:35', NULL, '2026-06-22 19:05:49', '2026-06-22 19:18:35'),
(221, 'App\\Models\\User', 1170, 'student', '8621b4b421ca248f0e9d223150904364e0e044c7cdaef8da246ecbab12278fd1', '[\"role:student\"]', '2026-06-24 19:33:54', NULL, '2026-06-24 19:33:42', '2026-06-24 19:33:54'),
(222, 'App\\Models\\User', 1079, 'instructor', 'ec4e5cfeb4bdcb9818f01f0563b21c776fdb8a3c52a5fcc7aba664cbf62f02c0', '[\"role:instructor\"]', '2026-06-24 19:34:38', NULL, '2026-06-24 19:34:27', '2026-06-24 19:34:38'),
(227, 'App\\Models\\User', 1237, 'instructor', '1a84888111e9ff05415223ab8b0d87684b764933d530f17482bdd28d6f11d9ba', '[\"role:instructor\"]', '2026-06-30 12:38:01', NULL, '2026-06-30 12:33:19', '2026-06-30 12:38:01'),
(231, 'App\\Models\\User', 1237, 'instructor', '6643129466d79a97b121ba00b5bccd17e5a4ebc8864ea9934e03045614dd1b8e', '[\"role:instructor\"]', '2026-06-30 14:48:48', NULL, '2026-06-30 14:44:39', '2026-06-30 14:48:48'),
(232, 'App\\Models\\User', 1079, 'instructor', '1a3a8c6eee616c563bf4f2789d8bf586d45d6d5a97e88d8eb05dbb7eb90de165', '[\"role:instructor\"]', '2026-07-02 19:16:00', NULL, '2026-06-30 14:52:31', '2026-07-02 19:16:00'),
(233, 'App\\Models\\User', 1237, 'instructor', 'aa1c4f8012486b9b5a38fc6240802e047ed7c2fd0ef1a33bad7fa759d1832151', '[\"role:instructor\"]', '2026-06-30 16:58:58', NULL, '2026-06-30 16:58:20', '2026-06-30 16:58:58'),
(234, 'App\\Models\\User', 1079, 'instructor', '36f16600ec5238aa41a4dad4a69548126cc04a00ecd82a1ae75233f3543411bf', '[\"role:instructor\"]', NULL, NULL, '2026-06-30 19:00:27', '2026-06-30 19:00:27'),
(244, 'App\\Models\\User', 1079, 'instructor', 'd46801de557ba06349b2a9d98a427a4f9c27a632506f82e5583871e80279e33b', '[\"role:instructor\"]', '2026-07-01 18:05:19', NULL, '2026-07-01 17:36:17', '2026-07-01 18:05:19'),
(251, 'App\\Models\\User', 1079, 'instructor', '4db00166764b61bb4db39874c1820d1f50f9353fa75fb89bf270c509834cd13a', '[\"role:instructor\"]', '2026-07-07 11:55:40', NULL, '2026-07-03 10:34:32', '2026-07-07 11:55:40'),
(254, 'App\\Models\\User', 1124, 'instructor', '7777a45c92b2145bedaded0e4cd48de884588d2a76ff4a7ac945de32005d72fc', '[\"role:instructor\"]', '2026-07-08 09:31:20', NULL, '2026-07-03 20:23:48', '2026-07-08 09:31:20'),
(255, 'App\\Models\\User', 1237, 'instructor', '811a813c0582c5261d923da6515f9dc31f5bb4d80315a6f951e103ba32725c02', '[\"role:instructor\"]', '2026-07-06 16:00:55', NULL, '2026-07-06 15:53:38', '2026-07-06 16:00:55'),
(257, 'App\\Models\\User', 1259, 'student', '2caec1de13f317ae0fc47a98c737d87fb2dca08196d21ac8e2bf1cc2ffb86a77', '[\"role:student\"]', '2026-07-07 23:24:41', NULL, '2026-07-07 23:09:28', '2026-07-07 23:24:41'),
(258, 'App\\Models\\User', 1124, 'instructor', 'b0f200d6519f1e4d21abbc7bcb276636363f1162a3aa6b6137a5f862169a2083', '[\"role:instructor\"]', '2026-07-28 08:09:02', NULL, '2026-07-22 13:41:14', '2026-07-28 08:09:02');

-- --------------------------------------------------------

--
-- Table structure for table `push_subscriptions`
--

CREATE TABLE `push_subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `subscribable_type` varchar(255) NOT NULL,
  `subscribable_id` bigint(20) UNSIGNED NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `public_key` varchar(255) DEFAULT NULL,
  `auth_token` varchar(255) DEFAULT NULL,
  `content_encoding` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `guard_name`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'admin', '2024-08-14 21:23:18', '2024-08-14 21:23:18'),
(2, 'Admin Role', 'admin', '2026-02-16 13:46:02', '2026-02-16 13:46:02'),
(3, 'Course Manager', 'admin', '2026-05-18 05:36:35', '2026-05-18 05:36:35'),
(4, 'Finance', 'admin', '2026-05-18 05:36:35', '2026-05-18 05:36:35'),
(5, 'Content Editor', 'admin', '2026-05-18 05:36:35', '2026-05-18 05:36:35'),
(6, 'Announcement Manager', 'admin', '2026-05-18 08:08:07', '2026-05-18 08:08:07'),
(7, 'e2e-role-1780045966632', 'admin', '2026-05-29 09:12:49', '2026-05-29 09:12:49'),
(13, 'e2e-role-1780060088539', 'admin', '2026-05-29 13:08:14', '2026-05-29 13:08:14');

-- --------------------------------------------------------

--
-- Table structure for table `role_has_permissions`
--

CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_has_permissions`
--

INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(2, 1),
(3, 1),
(4, 1),
(5, 1),
(6, 1),
(7, 1),
(8, 1),
(9, 1),
(10, 1),
(11, 1),
(12, 1),
(13, 1),
(14, 1),
(15, 1),
(16, 1),
(17, 1),
(18, 1),
(19, 1),
(19, 3),
(19, 5),
(20, 1),
(20, 3),
(20, 5),
(21, 1),
(22, 1),
(23, 1),
(23, 3),
(23, 5),
(24, 1),
(25, 1),
(25, 3),
(25, 5),
(26, 1),
(27, 1),
(28, 1),
(29, 1),
(30, 1),
(31, 1),
(32, 1),
(33, 1),
(34, 1),
(35, 1),
(36, 1),
(37, 1),
(38, 1),
(39, 1),
(40, 1),
(41, 1),
(42, 1),
(43, 1),
(44, 1),
(45, 1),
(46, 1),
(47, 1),
(48, 1),
(48, 3),
(48, 5),
(49, 1),
(50, 1),
(51, 1),
(52, 1),
(53, 1),
(54, 1),
(55, 1),
(56, 1),
(57, 1),
(58, 1),
(59, 1),
(60, 1),
(61, 1),
(62, 1),
(63, 1),
(64, 1),
(65, 1),
(66, 1),
(67, 1),
(68, 1),
(69, 1),
(69, 5),
(70, 1),
(71, 1),
(72, 1),
(73, 1),
(74, 1),
(75, 1),
(75, 5),
(78, 1),
(79, 1),
(80, 1),
(81, 1),
(81, 3),
(81, 5),
(82, 1),
(82, 3),
(82, 5),
(83, 1),
(84, 1),
(85, 1),
(85, 3),
(85, 5),
(86, 1),
(87, 1),
(87, 3),
(87, 5),
(88, 1),
(88, 3),
(88, 5),
(89, 1),
(89, 3),
(89, 5),
(90, 1),
(91, 1),
(92, 1),
(92, 3),
(92, 5),
(93, 1),
(94, 1),
(94, 3),
(94, 5),
(95, 1),
(96, 1),
(97, 1),
(98, 1),
(99, 1),
(100, 1),
(101, 1),
(102, 1),
(103, 1),
(104, 1),
(105, 1),
(105, 3),
(106, 1),
(106, 4),
(107, 1),
(108, 1),
(108, 4),
(109, 1),
(110, 1),
(111, 1),
(112, 1),
(113, 1),
(114, 1),
(115, 1),
(116, 1),
(117, 1),
(118, 1),
(119, 1),
(120, 1),
(121, 1),
(122, 1),
(122, 2),
(123, 1),
(123, 7),
(123, 13),
(124, 1),
(125, 1),
(126, 1),
(127, 1),
(128, 1),
(129, 1),
(130, 1),
(130, 4),
(131, 1),
(131, 4),
(132, 1),
(132, 4),
(133, 1),
(133, 4),
(134, 1),
(134, 4),
(135, 1),
(135, 4),
(136, 1),
(136, 4),
(137, 1),
(138, 1),
(138, 4),
(139, 1),
(139, 4),
(140, 1),
(140, 4),
(141, 1),
(141, 4),
(142, 1),
(142, 4),
(143, 1),
(144, 1),
(145, 1),
(145, 4),
(146, 1),
(146, 3),
(146, 6),
(147, 1),
(147, 3),
(147, 6),
(148, 1),
(148, 6),
(149, 1),
(149, 2),
(150, 1),
(150, 2),
(151, 1),
(151, 2),
(152, 1),
(152, 2),
(153, 1),
(153, 6),
(154, 1),
(154, 6),
(155, 1),
(156, 1),
(157, 1),
(158, 1),
(159, 1),
(160, 1);

-- --------------------------------------------------------

--
-- Table structure for table `salary_components`
--

CREATE TABLE `salary_components` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `salary_structure_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(20) NOT NULL COMMENT 'earning|deduction',
  `name` varchar(255) NOT NULL,
  `code` varchar(30) DEFAULT NULL,
  `calc_type` varchar(30) NOT NULL DEFAULT 'fixed' COMMENT 'fixed|percent_of_basic|percent_of_gross',
  `value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_statutory` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salary_components`
--

INSERT INTO `salary_components` (`id`, `company_id`, `salary_structure_id`, `type`, `name`, `code`, `calc_type`, `value`, `is_statutory`, `sort_order`, `created_at`, `updated_at`) VALUES
(4, 1, 2, 'earning', 'Basic', 'BASIC', 'fixed', 20000.00, 0, 1, '2026-07-30 12:17:56', '2026-07-30 12:17:56'),
(5, 1, 2, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-07-30 12:17:56', '2026-07-30 12:17:56'),
(6, 1, 2, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-07-30 12:17:56', '2026-07-30 12:17:56'),
(7, 1, 3, 'earning', 'Basic', 'BASIC', 'fixed', 20000.00, 0, 1, '2026-07-30 12:18:24', '2026-07-30 12:18:24'),
(8, 1, 3, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-07-30 12:18:24', '2026-07-30 12:18:24'),
(9, 1, 3, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-07-30 12:18:24', '2026-07-30 12:18:24'),
(10, 1, 4, 'earning', 'Basic', 'BASIC', 'fixed', 20000.00, 0, 1, '2026-07-31 12:41:00', '2026-07-31 12:41:00'),
(11, 1, 4, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-07-31 12:41:00', '2026-07-31 12:41:00'),
(12, 1, 4, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-07-31 12:41:00', '2026-07-31 12:41:00'),
(13, 1, 5, 'earning', 'Basic', 'BASIC', 'fixed', 50000.00, 0, 1, '2026-07-31 12:41:05', '2026-07-31 12:41:05'),
(14, 1, 5, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-07-31 12:41:05', '2026-07-31 12:41:05'),
(15, 1, 5, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-07-31 12:41:05', '2026-07-31 12:41:05'),
(16, 1, 6, 'earning', 'Basic', 'BASIC', 'fixed', 15000.00, 0, 1, '2026-07-31 13:51:53', '2026-07-31 13:51:53'),
(17, 1, 6, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-07-31 13:51:53', '2026-07-31 13:51:53'),
(18, 1, 6, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-07-31 13:51:53', '2026-07-31 13:51:53'),
(19, 1, 7, 'earning', 'Basic', 'BASIC', 'fixed', 50000.00, 0, 1, '2026-07-31 13:54:03', '2026-07-31 13:54:03'),
(20, 1, 7, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-07-31 13:54:03', '2026-07-31 13:54:03'),
(21, 1, 7, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-07-31 13:54:03', '2026-07-31 13:54:03'),
(22, 1, 8, 'earning', 'Basic', 'BASIC', 'fixed', 45000.00, 0, 1, '2026-08-03 11:04:37', '2026-08-03 11:04:37'),
(23, 1, 8, 'earning', 'HRA', 'HRA', 'percent_of_basic', 35.00, 0, 2, '2026-08-03 11:04:37', '2026-08-03 11:04:37'),
(24, 1, 8, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-08-03 11:04:37', '2026-08-03 11:04:37'),
(25, 1, 9, 'earning', 'Basic', 'BASIC', 'fixed', 45000.00, 0, 1, '2026-08-03 11:20:31', '2026-08-03 11:20:31'),
(26, 1, 9, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-08-03 11:20:31', '2026-08-03 11:20:31'),
(27, 1, 9, 'earning', 'Special Allowance', 'SPL', 'fixed', 5.00, 0, 3, '2026-08-03 11:20:31', '2026-08-03 11:20:31'),
(28, 1, 10, 'earning', 'Basic', 'BASIC', 'fixed', 20000.00, 0, 1, '2026-08-03 11:20:42', '2026-08-03 11:20:42'),
(29, 1, 10, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-08-03 11:20:42', '2026-08-03 11:20:42'),
(30, 1, 10, 'earning', 'Special Allowance', 'SPL', 'fixed', 10.00, 0, 3, '2026-08-03 11:20:42', '2026-08-03 11:20:42'),
(31, 1, 11, 'earning', 'Basic', 'BASIC', 'fixed', 45000.00, 0, 1, '2026-08-03 11:42:37', '2026-08-03 11:42:37'),
(32, 1, 11, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-08-03 11:42:37', '2026-08-03 11:42:37'),
(33, 1, 11, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-08-03 11:42:37', '2026-08-03 11:42:37'),
(34, 1, 12, 'earning', 'Basic', 'BASIC', 'fixed', 45000.00, 0, 1, '2026-08-03 11:48:31', '2026-08-03 11:48:31'),
(35, 1, 12, 'earning', 'HRA', 'HRA', 'percent_of_basic', 20.00, 0, 2, '2026-08-03 11:48:31', '2026-08-03 11:48:31'),
(36, 1, 12, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-08-03 11:48:31', '2026-08-03 11:48:31'),
(37, 13, 13, 'earning', 'Basic', 'BASIC', 'fixed', 20000.00, 0, 1, '2026-08-18 13:56:59', '2026-08-18 13:56:59'),
(38, 13, 13, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-08-18 13:56:59', '2026-08-18 13:56:59'),
(39, 13, 13, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-08-18 13:56:59', '2026-08-18 13:56:59'),
(40, 13, 14, 'earning', 'Basic', 'BASIC', 'fixed', 20000.00, 0, 1, '2026-08-24 13:39:36', '2026-08-24 13:39:36'),
(41, 13, 14, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-08-24 13:39:36', '2026-08-24 13:39:36'),
(42, 13, 14, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-08-24 13:39:36', '2026-08-24 13:39:36'),
(43, 1, 15, 'earning', 'Basic', 'BASIC', 'fixed', 45000.00, 0, 1, '2026-08-25 06:40:49', '2026-08-25 06:40:49'),
(44, 1, 15, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-08-25 06:40:49', '2026-08-25 06:40:49'),
(45, 1, 15, 'earning', 'Special Allowance', 'SPL', 'fixed', 300.00, 0, 3, '2026-08-25 06:40:49', '2026-08-25 06:40:49'),
(46, 1, 16, 'earning', 'Basic', 'BASIC', 'fixed', 45000.00, 0, 1, '2026-08-25 06:41:17', '2026-08-25 06:41:17'),
(47, 1, 16, 'earning', 'HRA', 'HRA', 'percent_of_basic', 20.00, 0, 2, '2026-08-25 06:41:17', '2026-08-25 06:41:17'),
(48, 1, 16, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-08-25 06:41:17', '2026-08-25 06:41:17'),
(49, 1, 17, 'earning', 'Basic', 'BASIC', 'fixed', 45000.00, 0, 1, '2026-08-25 06:41:24', '2026-08-25 06:41:24'),
(50, 1, 17, 'earning', 'HRA', 'HRA', 'percent_of_basic', 10.00, 0, 2, '2026-08-25 06:41:24', '2026-08-25 06:41:24'),
(51, 1, 17, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-08-25 06:41:24', '2026-08-25 06:41:24'),
(52, 1, 18, 'earning', 'Basic', 'BASIC', 'fixed', 45000.00, 0, 1, '2026-08-25 06:41:28', '2026-08-25 06:41:28'),
(53, 1, 18, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-08-25 06:41:28', '2026-08-25 06:41:28'),
(54, 1, 18, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-08-25 06:41:28', '2026-08-25 06:41:28'),
(55, 1, 19, 'earning', 'Basic', 'BASIC', 'fixed', 40000.00, 0, 1, '2026-08-25 06:41:38', '2026-08-25 06:41:38'),
(56, 1, 19, 'earning', 'HRA', 'HRA', 'percent_of_basic', 40.00, 0, 2, '2026-08-25 06:41:38', '2026-08-25 06:41:38'),
(57, 1, 19, 'earning', 'Special Allowance', 'SPL', 'fixed', 0.00, 0, 3, '2026-08-25 06:41:38', '2026-08-25 06:41:38');

-- --------------------------------------------------------

--
-- Table structure for table `salary_revisions`
--

CREATE TABLE `salary_revisions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `old_ctc` decimal(12,2) DEFAULT NULL,
  `new_ctc` decimal(12,2) NOT NULL,
  `effective_from` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_structures`
--

CREATE TABLE `salary_structures` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `ctc_annual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gross_monthly` decimal(12,2) NOT NULL DEFAULT 0.00,
  `effective_from` date NOT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `salary_structures`
--

INSERT INTO `salary_structures` (`id`, `company_id`, `user_id`, `ctc_annual`, `gross_monthly`, `effective_from`, `is_current`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 1, 1283, 336000.00, 28000.00, '2026-07-01', 0, 1278, '2026-07-30 12:17:56', '2026-08-03 11:20:42'),
(3, 1, 1283, 336000.00, 28000.00, '2026-07-01', 0, 1278, '2026-07-30 12:18:24', '2026-08-03 11:20:42'),
(4, 1, 1284, 336000.00, 28000.00, '2026-07-01', 0, 1278, '2026-07-31 12:41:00', '2026-07-31 13:54:03'),
(5, 1, 1284, 840000.00, 70000.00, '2026-07-01', 0, 1278, '2026-07-31 12:41:05', '2026-07-31 13:54:03'),
(6, 1, 1285, 252000.00, 21000.00, '2026-07-01', 1, 1278, '2026-07-31 13:51:53', '2026-07-31 13:51:53'),
(7, 1, 1284, 840000.00, 70000.00, '2026-07-01', 1, 1278, '2026-07-31 13:54:03', '2026-07-31 13:54:03'),
(8, 1, 1286, 729000.00, 60750.00, '2026-08-01', 0, 1278, '2026-08-03 11:04:37', '2026-08-25 06:41:38'),
(9, 1, 1286, 756060.00, 63005.00, '2026-08-01', 0, 1278, '2026-08-03 11:20:31', '2026-08-25 06:41:38'),
(10, 1, 1283, 336120.00, 28010.00, '2026-08-01', 1, 1278, '2026-08-03 11:20:42', '2026-08-03 11:20:42'),
(11, 1, 1286, 756000.00, 63000.00, '2026-08-01', 0, 1278, '2026-08-03 11:42:37', '2026-08-25 06:41:38'),
(12, 1, 1286, 648000.00, 54000.00, '2026-08-01', 0, 1278, '2026-08-03 11:48:31', '2026-08-25 06:41:38'),
(13, 13, 1287, 336000.00, 28000.00, '2026-08-01', 0, 1278, '2026-08-18 13:56:59', '2026-08-24 13:39:36'),
(14, 13, 1287, 336000.00, 28000.00, '2026-08-01', 1, 1278, '2026-08-24 13:39:36', '2026-08-24 13:39:36'),
(15, 1, 1286, 759600.00, 63300.00, '2026-08-01', 0, 1278, '2026-08-25 06:40:49', '2026-08-25 06:41:38'),
(16, 1, 1286, 648000.00, 54000.00, '2026-08-01', 0, 1278, '2026-08-25 06:41:17', '2026-08-25 06:41:38'),
(17, 1, 1286, 594000.00, 49500.00, '2026-08-01', 0, 1278, '2026-08-25 06:41:24', '2026-08-25 06:41:38'),
(18, 1, 1286, 756000.00, 63000.00, '2026-08-01', 0, 1278, '2026-08-25 06:41:28', '2026-08-25 06:41:38'),
(19, 1, 1286, 672000.00, 56000.00, '2026-08-01', 1, 1278, '2026-08-25 06:41:38', '2026-08-25 06:41:38');

-- --------------------------------------------------------

--
-- Table structure for table `seo_settings`
--

CREATE TABLE `seo_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `page_name` varchar(255) NOT NULL,
  `seo_title` text NOT NULL,
  `seo_description` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seo_settings`
--

INSERT INTO `seo_settings` (`id`, `page_name`, `seo_title`, `seo_description`, `created_at`, `updated_at`) VALUES
(1, 'home_page', 'Home || MBS GURU', 'Home || MBS GURU', '2025-06-30 10:32:42', '2026-03-10 06:21:17'),
(2, 'about_page', 'About || MBS GURU', 'About || MBS GURU', '2025-06-30 10:32:42', '2026-03-10 06:21:24'),
(3, 'course_page', 'Course || MBS GURU', 'Course || MBS GURU', '2025-06-30 10:32:42', '2026-03-10 06:21:31'),
(4, 'blog_page', 'Blog || MBS GURU', 'Blog || MBS GURU', '2025-06-30 10:32:42', '2026-03-10 06:21:37'),
(5, 'contact_page', 'Contact || MBS GURU', 'Contact || MBS GURU', '2025-06-30 10:32:42', '2026-03-10 06:21:46'),
(6, 'terms_and_conditions', 'Terms and Conditions || Yoga', 'Terms and Conditions || Yoga', '2025-06-30 10:32:42', '2025-07-11 07:16:02'),
(7, 'privacy_policy', 'Privacy Policy || MBS GURU', 'Privacy Policy || MBS GURU', '2025-06-30 10:32:42', '2026-03-10 06:21:52');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('2nDdbYyQppZWbAuJAWjumxkrEHNP8YVTckoEyKxC', NULL, '127.0.0.1', 'Symfony', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiNmJ4RFQ0aTBTWnZXOU5YSGE3c0VTcFZyWTQ4NFRwWm0wR3Z1NFpPRSI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im5ldyI7YTowOnt9czozOiJvbGQiO2E6MDp7fX1zOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czozMToiaHR0cDovL2xvY2FsaG9zdC9hZG1pbi9zZXR0aW5ncyI7fXM6NTI6ImxvZ2luX2FkbWluXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTt9', 1788164721),
('30C04EbogF9SnsBD5q6Vb26lUwEqTRtfT8hlfVKG', NULL, '127.0.0.1', 'curl/8.15.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiZlhCZVd0RFdTUVk3N1JEbXczbkdBTWRkbnZSbXVaNFlyelRDNHhPdCI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czozNzoiaHR0cDovLzEyNy4wLjAuMTo4MTIzL2ZvcmdvdC1wYXNzd29yZCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1788164649),
('4gwkcqz4OitzWEKNSmH36RoYx26QtUZ9m6rbU819', 1, '127.0.0.1', 'Symfony', 'YTo1OntzOjUyOiJsb2dpbl9hZG1pbl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjE7czo2OiJfdG9rZW4iO3M6NDA6IjNuaTVudUtEZXZwd2piQW9hRUxDUThqVzUxbUZ3amU1OGhBYm55SXMiO3M6NDoibGFuZyI7czoyOiJlbiI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly9sb2NhbGhvc3QvYWRtaW4vc2V0dGluZ3MiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1788164741),
('6YXKDV76GzRWZuNFolFQbBoQCoEmQBzQnhENzdFD', 1278, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'YTo2OntzOjY6Il90b2tlbiI7czo0MDoidHdITWVjZjBJckdrUFZiUUpxczRyVGdxRHhvanRhOVhiYXJRV0tFSSI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czo5NDoiaHR0cDovL2xvY2FsaG9zdC9sYXJhdmVsL2VycHN5c3RlbS91cGxvYWRzL2N1c3RvbS1pbWFnZXMvd3N1cy1pbWctMjAyNi0wNC0wMy0wNC0wOS0yNy00ODkxLnBuZyI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjEyNzg7czoxNzoiYWN0aXZlX2NvbXBhbnlfaWQiO2k6MTt9', 1788168404),
('BRdrIjwfrgMN6f6Sr7T606d3lLnvlo2rWwDVhJnu', 1079, '127.0.0.1', 'Symfony', 'YTo2OntzOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxMDc5O3M6NjoiX3Rva2VuIjtzOjQwOiJDYlowUWdHZUZlaktjVTV1QWpsZnpKdXpvTWFGOVdkZjVFczZqOENvIjtzOjQ6ImxhbmciO3M6MjoiZW4iO3M6NDoiaW5mbyI7czo0MzoiUmVnaXN0ZXIgeW91ciBmaXJzdCBjb21wYW55IHRvIGdldCBzdGFydGVkLiI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJuZXciO2E6MDp7fXM6Mzoib2xkIjthOjE6e2k6MDtzOjQ6ImluZm8iO319czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6Mjg6Imh0dHA6Ly9sb2NhbGhvc3QvaHIvb3ZlcnZpZXciO319', 1788164803),
('fAouuCJUHBaKlYle9x5EDjZye5F3xZovZQdIpnR3', NULL, '127.0.0.1', 'curl/8.15.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoieUZseHY5UXdKVFJPcEk0b3NzRTNQMTczWjRZblR0RE5tdVZOUk0wciI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czoyNzoiaHR0cDovLzEyNy4wLjAuMTo4MTIzL2xvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1788164641),
('krbf4aOod2fjOMoiN0SAl0XJfT17x0bsv5rnJScd', NULL, '127.0.0.1', 'curl/8.15.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiRnVjMHlTOXB6QjN4bnFjWmxWRkFVQWNiMUlPaWRHQld3WDBmRFM4NiI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czoyNzoiaHR0cDovLzEyNy4wLjAuMTo4MTIzL2xvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1788164634),
('kXd3h5hRShb8yrxnN88pWfKPcL6EYc6KBM0rI7YO', NULL, '127.0.0.1', 'curl/8.15.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiTnk0RG82NDlvNWdhV2FtRVNoS29jSDlxRXZKdFdqa1F0M0lQbkhRUCI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czozMDoiaHR0cDovLzEyNy4wLjAuMTo4MTIzL3JlZ2lzdGVyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1788164657),
('lVeG5PHpLdVfcE7zifZPpyvPmBcooN05jLpjW7DS', NULL, '127.0.0.1', 'curl/8.15.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiVjVPVnVKQUR0b1NwVjlUTmRMenNTUlNJYXNaZlpYOGszV0dEWHBWWiI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czozNzoiaHR0cDovLzEyNy4wLjAuMTo4MTIzL2ZvcmdvdC1wYXNzd29yZCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1788164657),
('OI75MDkW6TNXpLByEPtFmdAWnna0OtqpFs88Mebv', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.40609.0 Chrome/148.0.7778.280 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiYU4xbmk1VVdKUWZmQzJzenJwbE5DOGVKVFpZN2hobzJxMmtYQklzeiI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czo5NDoiaHR0cDovL2xvY2FsaG9zdC9sYXJhdmVsL2VycHN5c3RlbS91cGxvYWRzL2N1c3RvbS1pbWFnZXMvd3N1cy1pbWctMjAyNi0wNC0xNy0wMS01MS0wMi00MjQ0LnBuZyI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1788164459),
('OMBQN5EuSPU2xtynmL9pjfORu7TruDmNVuetsP4J', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiOGdMQnlTU1VpdnJ3QnJLMHRJcms1dkZBakpsOXdYMkVMeDRSZ0VlbiI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czo0MDoiaHR0cDovL2xvY2FsaG9zdC9sYXJhdmVsL2VycHN5c3RlbS9sb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1788163191),
('SBl4H2YnIpATSqGXcY7Q1teZtUEdcM4N09alR9iO', 1283, '127.0.0.1', 'Symfony', 'YTo2OntzOjY6Il90b2tlbiI7czo0MDoieW84bjNvQXFrQ3B3UXFzUUx6azQ1RGxOQzN0UnJuZkcyRGp0OEdObyI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im5ldyI7YTowOnt9czozOiJvbGQiO2E6MTp7aTowO3M6MTE6InByb2ZpbGVfdGFiIjt9fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjMyOiJodHRwOi8vbG9jYWxob3N0L3N0dWRlbnQvc2V0dGluZyI7fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjEyODM7czoxMToicHJvZmlsZV90YWIiO3M6NzoicHJvZmlsZSI7fQ==', 1788164829),
('t83qzjUFa8f9ZUCYY1oi7YeEcBtrxschbVwSwe5p', NULL, '127.0.0.1', 'curl/8.15.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiUmpVdkFDT015c3JUTzU3SGVXOTljUUdQa2x5dUp3RHRiaWpRN056biI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czozMzoiaHR0cDovLzEyNy4wLjAuMTo4MTIzL2FkbWluL2xvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1788164650),
('tmOsgprW3yLTcN188UNtehbKyhT6Xs4LXukFycg1', 1286, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'YTo2OntzOjY6Il90b2tlbiI7czo0MDoiZmhjaTVMb1NnaDl1UU1GWG9UQUw2ZjNjUWJxWWVWaW9BUXY4TFVOdCI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czo0NzoiaHR0cDovL2xvY2FsaG9zdC9sYXJhdmVsL2VycHN5c3RlbS9oci9lbXBsb3llZXMiO31zOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czo1NToiaHR0cDovL2xvY2FsaG9zdC9sYXJhdmVsL2VycHN5c3RlbS9ub3RpZmljYXRpb25zL3JlY2VudCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NDoibGFuZyI7czoyOiJlbiI7czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTI4Njt9', 1788165063),
('uB6pbQJiYDovufcqdTi44KROBjFC6z6EMfjagEzQ', NULL, '::1', 'curl/8.15.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoibVlBcHR2RGh1NkhUd2xxREUwVXZJbjVzb21KR3ZJWE1lZEFvSEdCUSI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czo0NzoiaHR0cDovL2xvY2FsaG9zdC9sYXJhdmVsL2VycHN5c3RlbS9wdWJsaWMvbG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1788164610),
('uD4QM5IDYftxw4q8Th5U58lJacZMzY7cDfSj7m9e', NULL, '127.0.0.1', 'curl/8.15.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoicUtVNXhOUTFzbDllU3dBZEc4WEpvYm8yRllRR09ROE5kN204NE1JNCI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czozMzoiaHR0cDovLzEyNy4wLjAuMTo4MTIzL2FkbWluL2xvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1788164657),
('VQqnwuBnrwwsa0D22lIrllqHlJXPe1p87xmeYOVi', 1278, '127.0.0.1', 'Symfony', 'YTo0OntzOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxMjc4O3M6NTA6ImxvZ2luX3dlYl9iMzEyZjczNzQ2YTNjYjA0ZTUyYThhY2E3MDlhODA1OTYyN2U4ZmY0IjtpOjEyNzg7czoxNzoiYWN0aXZlX2NvbXBhbnlfaWQiO2k6MTtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1788164865),
('vTDoNUenoNxDUiWZWe3mokQhQV5Md1h2DhmkZyzO', NULL, '127.0.0.1', 'curl/8.15.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiR1JZcXE1TktGaDFNV28zaTBDallZMmo1UGY1cGhwTW9iZ3N0cjNvaiI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czoyNzoiaHR0cDovLzEyNy4wLjAuMTo4MTIzL2xvZ2luIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1788164641),
('XELvc4Wwc8XfDcBekUIylluDilDE6iNSNw2i7DF3', NULL, '127.0.0.1', 'Symfony', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiS1ZZYjRvcGFvcHE2YlVRYlVVOE81dmNsSEtlUW9ycDBzdDdzTDdSaiI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjY6Il9mbGFzaCI7YToyOntzOjM6Im5ldyI7YTowOnt9czozOiJvbGQiO2E6MDp7fX1zOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czozMToiaHR0cDovL2xvY2FsaG9zdC9hZG1pbi9zZXR0aW5ncyI7fXM6NTI6ImxvZ2luX2FkbWluXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTt9', 1788164789),
('XYYMRYyLCyPIEhTZvASS8uxBT0srLlkwiGtYLNvn', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.40609.0 Chrome/148.0.7778.280 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoialRzNjJtc0E0TXhnWWhOY0x5dXhodDRzUGxUMllPYzVSeDBUQnB1eCI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czo0NzoiaHR0cDovL2xvY2FsaG9zdC9sYXJhdmVsL2VycHN5c3RlbS9wdWJsaWMvbG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1788164469),
('YEABPj2VMLORDyAW9YsSLtL0YLcjQ2SfD4NNgTf4', NULL, '127.0.0.1', 'curl/8.15.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiU2xRTkFvNnJsU2JxT2JYRXo2U1dxNGswYXFNWlR6OEpPNHZCbFV1WCI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czozMDoiaHR0cDovLzEyNy4wLjAuMTo4MTIzL3JlZ2lzdGVyIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1788164649),
('YU8TUHrz7V0Wd8AuffyOvV93Od4raam7jf2VgDUq', NULL, '127.0.0.1', 'curl/8.15.0', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiZmxkR1V0M2ZycG1CNUQxdk8yY1p1NmxQRjBmSHFibkFvNTRsUFRoSiI7czo0OiJsYW5nIjtzOjI6ImVuIjtzOjk6Il9wcmV2aW91cyI7YToxOntzOjM6InVybCI7czoyMToiaHR0cDovLzEyNy4wLjAuMTo4MTIzIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1788164650);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES
(1, 'app_name', 'MBSGuru', '2024-06-03 02:02:30', '2026-07-16 17:52:44'),
(2, 'version', '2.3.0', '2024-06-03 02:02:30', '2024-06-03 02:02:30'),
(3, 'logo', 'uploads/custom-images/wsus-img-2026-04-03-04-09-42-7557.png', '2024-06-03 02:02:30', '2026-04-03 16:09:42'),
(4, 'timezone', 'Asia/Kolkata', '2024-06-03 02:02:30', '2026-07-16 17:52:44'),
(5, 'favicon', 'uploads/custom-images/wsus-img-2026-04-17-01-51-02-4244.png', '2024-06-03 02:02:30', '2026-04-17 13:51:02'),
(6, 'cookie_status', 'active', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(7, 'border', 'normal', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(8, 'corners', 'thin', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(9, 'background_color', '#184dec', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(10, 'text_color', '#fafafa', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(11, 'border_color', '#0a58d6', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(12, 'btn_bg_color', '#fffceb', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(13, 'btn_text_color', '#222758', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(14, 'link_text', 'More Info', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(15, 'link', '/page/privacy-policy', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(16, 'btn_text', 'Yes', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(17, 'message', 'This website uses essential cookies to ensure its proper operation and tracking cookies to understand how you interact with it. The latter will be set only upon approval.', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(18, 'copyright_text', '2026 All Rights Reserved. Developed By Digital Hub Solution', '2024-08-14 21:23:17', '2026-04-03 16:09:59'),
(19, 'recaptcha_site_key', '6Ld9G1gtAAAAAKvTj0JZnFPyeISgBpaiPvXUBf3y', '2024-08-14 21:23:17', '2026-07-17 13:55:50'),
(20, 'recaptcha_secret_key', 'enc:v1:eyJpdiI6Im9STVc5OHBqWTRCWDR1SEtkQkZ0WEE9PSIsInZhbHVlIjoiQitwZGhrT1pQRjlHT1V2dit6OTRPZmhvVC8yRDkzZnh6emd1bS94N2dyYjZJUkM2R2ZOM2NWaSs5WkJpR0NTayIsIm1hYyI6IjQ4YjBmZGQ4YjVkZTc4ZmNmYzQxMDY0YTYwZGE3MDNkNDU3MTMyZmRlNmMwYjM0NDMyODI5NWM0MDU1ODdlYTEiLCJ0YWci', '2024-08-14 21:23:17', '2026-07-17 13:55:50'),
(21, 'recaptcha_status', 'inactive', '2024-08-14 21:23:17', '2026-07-17 13:55:50'),
(22, 'tawk_status', 'inactive', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(23, 'tawk_chat_link', 'tawk_chat_link', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(24, 'google_tagmanager_status', 'active', '2024-08-14 21:23:17', '2026-07-17 12:06:44'),
(25, 'google_tagmanager_id', 'google_tagmanager_id', '2024-08-14 21:23:17', '2026-07-17 12:06:44'),
(26, 'pixel_status', 'active', '2024-08-14 21:23:17', '2026-07-17 11:29:49'),
(27, 'pixel_app_id', 'pixel_app_id', '2024-08-14 21:23:17', '2026-07-17 11:29:49'),
(28, 'facebook_login_status', 'inactive', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(29, 'facebook_app_id', 'facebook_app_id', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(30, 'facebook_app_secret', 'enc:v1:eyJpdiI6InpRcTJpSW1XSVovNFlyZkZOMGlWOGc9PSIsInZhbHVlIjoiNGpoRVE1Y2JYdUJwbFFHQXQrTHRRSG9VaXM4NUVzL1JaRXYrazZ0SGt1RT0iLCJtYWMiOiIxZWQ0OTVlMmM2OTQxM2M4MTM0ZDZhMjVlOGZlZDk5YmNmMThmMTEwNDU3MTYwYWJiMzA1Y2YwMjU4ZTM2MDBhIiwidGFnIjoiIn0=', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(31, 'facebook_redirect_url', 'facebook_redirect_url', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(32, 'google_login_status', 'active', '2024-08-14 21:23:17', '2026-07-17 12:06:51'),
(33, 'gmail_client_id', 'gmail_client_id', '2024-08-14 21:23:17', '2026-07-17 12:06:51'),
(34, 'gmail_secret_id', 'enc:v1:eyJpdiI6IjhVVVlCblM0aktxbTZnaUIwcVpTUVE9PSIsInZhbHVlIjoiM2hYN0JWb1Nqc0RaV0xJZ2RRblFBQT09IiwibWFjIjoiZGEwZTlmNGUwMDEyNjQ0YzdkOGQ5ODE0YTllNGY0MzJhMTYyZTljOTMwZTA0ZWIzMGU1OTIzYmFiZmQ1NWQ0ZCIsInRhZyI6IiJ9', '2024-08-14 21:23:17', '2026-07-17 12:06:51'),
(35, 'gmail_redirect_url', '', '2024-08-14 21:23:17', '2026-07-17 12:06:51'),
(36, 'default_avatar', 'uploads/website-images/default-avatar.png', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(37, 'breadcrumb_image', 'uploads/website-images/breadcrumb-image.jpg', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(38, 'mail_host', 'mail.mbsguru.com', '2024-08-14 21:23:17', '2026-06-23 12:08:44'),
(39, 'mail_sender_email', 'noreply@mbsguru.com', '2024-08-14 21:23:17', '2026-06-23 12:08:44'),
(40, 'mail_username', 'noreply@mbsguru.com', '2024-08-14 21:23:17', '2026-06-23 12:08:44'),
(41, 'mail_password', 'enc:v1:eyJpdiI6IldLdzFobG1UV3BUTHYvU3ZyWVpZd0E9PSIsInZhbHVlIjoiUDNQV2tIMU9lWGdUTGRuV3RqZUJZUUlOd0pERnZDd2JEMTFSVUJibzJ0UT0iLCJtYWMiOiI0NzNhOThkYjczZTdjYzBkYzJjNmQ0OWNmYTA2MTk1MmY2YWUyOGNjMDlkN2EyOWQ1NTU5MTYxNWFjNGM0MjZkIiwidGFnIjoiIn0=', '2024-08-14 21:23:17', '2026-06-23 12:08:44'),
(42, 'mail_port', '465', '2024-08-14 21:23:17', '2026-06-23 12:08:44'),
(43, 'mail_encryption', 'ssl', '2024-08-14 21:23:17', '2026-06-23 12:08:44'),
(44, 'mail_sender_name', 'MBS Guru Private Limited', '2024-08-14 21:23:17', '2026-06-23 12:08:44'),
(45, 'contact_message_receiver_mail', 'sureshs.dhs@gmail.com', '2024-08-14 21:23:17', '2026-06-23 12:08:44'),
(46, 'pusher_app_id', 'pusher_app_id', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(47, 'pusher_app_key', 'pusher_app_key', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(48, 'pusher_app_secret', 'enc:v1:eyJpdiI6IkoxY0poZHRBYjNxcWZmaW5ZVDlaUGc9PSIsInZhbHVlIjoiSHAwbEo0ZUl5NkhmK2dqUUwvajF2TDIyV3RGWGdqYkhZV0I1WFFsbmhrTT0iLCJtYWMiOiI0N2IwMzRmZDU2MGUzYzViOTY5YWM1Yjc5MmFkZWVjMjQwMDhjYWVlNzI1OWM1MzMyNTk1YmFkMzk5ZjhjYTExIiwidGFnIjoiIn0=', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(49, 'pusher_app_cluster', 'pusher_app_cluster', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(50, 'pusher_status', 'inactive', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(51, 'club_point_rate', '1', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(52, 'club_point_status', 'active', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(53, 'maintenance_mode', '0', '2024-08-14 21:23:17', '2025-05-27 11:55:11'),
(54, 'maintenance_title', 'Website Under maintenance', '2024-08-14 21:23:17', '2025-05-27 11:55:24'),
(55, 'maintenance_description', '<p>Working on more modules coming soon with AI and Boot</p>', '2024-08-14 21:23:17', '2025-05-27 11:55:24'),
(56, 'last_update_date', '2024-08-15 03:23:17', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(57, 'is_queable', 'inactive', '2024-08-14 21:23:17', '2026-07-16 17:52:44'),
(58, 'commission_rate', '2', '2024-08-14 21:23:17', '2025-08-01 17:47:44'),
(59, 'site_address', 'D-247/4A, D Block, Sector 63, Noida, Uttar Pradesh 201301', '2024-08-14 21:23:17', '2026-07-16 17:52:44'),
(60, 'site_email', 'info@mbsguru.com', '2024-08-14 21:23:17', '2026-07-16 17:52:44'),
(61, 'site_theme', 'main', '2024-08-14 21:23:17', '2025-04-25 10:55:52'),
(62, 'preloader', 'uploads/custom-images/wsus-img-2026-04-03-04-09-27-4891.png', '2024-08-14 21:23:17', '2026-04-03 16:09:27'),
(63, 'primary_color', '#5751e1', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(64, 'secondary_color', '#ffc224', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(65, 'common_color_one', '#050071', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(66, 'common_color_two', '#282568', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(67, 'common_color_three', '#1C1A4A', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(68, 'common_color_four', '#06042E', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(69, 'common_color_five', '#4a44d1', '2024-08-14 21:23:17', '2024-08-14 21:23:17'),
(70, 'show_all_homepage', '1', '2024-08-14 21:23:17', '2025-04-25 10:53:26'),
(71, 'google_analytic_status', 'inactive', '2024-08-14 21:23:17', '2026-07-17 12:05:45'),
(72, 'google_analytic_id', 'google_analytic_id', '2024-08-14 21:23:17', '2026-07-17 12:05:45'),
(73, 'preloader_status', '0', '2024-08-14 21:23:17', '2026-07-16 17:52:44'),
(74, 'maintenance_image', 'uploads/custom-images/wsus-img-2025-05-01-01-49-36-4009.png', '2024-08-14 21:23:17', '2025-05-01 08:19:36'),
(75, 'live_mail_send', '5', '2024-09-03 13:02:14', '2026-07-16 17:52:44'),
(80, 'wasabi_access_id', 'wasabi_access_id', '2024-10-03 12:52:13', '2026-07-17 11:29:59'),
(81, 'wasabi_secret_key', 'enc:v1:eyJpdiI6IjJkRGNMVFRHVkVkdnFicWtyc240ZFE9PSIsInZhbHVlIjoienBrby8rZkdLUTMxYUQvYzViWklEV1hPWnFURWJPZFJkTkhpOTYrK045UT0iLCJtYWMiOiIxMjExY2RlYmUwMWE1ZTUwMzRmOGE2YTdjNTMwMjZkYTA0ZDYwMjI5M2IyNTgxNjU4NDY5NjE3NTljMDE5YzIzIiwidGFnIjoiIn0=', '2024-10-03 12:52:13', '2026-07-17 11:29:59'),
(82, 'wasabi_bucket', 'wasabi_bucket', '2024-10-03 12:52:13', '2026-07-17 11:29:59'),
(83, 'wasabi_region', 'us-east-1', '2024-10-03 12:52:13', '2026-07-17 11:29:59'),
(84, 'wasabi_status', 'active', '2024-10-03 12:52:13', '2026-07-17 11:29:59'),
(85, 'aws_access_id', 'aws_access_id', '2024-10-03 12:52:13', '2024-10-03 12:52:13'),
(86, 'aws_secret_key', 'enc:v1:eyJpdiI6InNXSFNlV2Ztd1NIK1o2ZVpFN2U0U3c9PSIsInZhbHVlIjoiUkFaOHlFNDJWZlI1cTFJdDVQUFpGUT09IiwibWFjIjoiNzE5OWU0OTMzZjk4ZDkwNTY3MGRjOGMyYTg0MmM5M2RjMjA5ZmY4ZjEyMWJmYjQzMGEwZGM3ZGE2MmQyYWE3NCIsInRhZyI6IiJ9', '2024-10-03 12:52:13', '2024-10-03 12:52:13'),
(87, 'aws_bucket', 'aws_bucket', '2024-10-03 12:52:13', '2024-10-03 12:52:13'),
(88, 'aws_region', 'us-east-1', '2024-10-03 12:52:13', '2024-10-03 12:52:13'),
(89, 'aws_status', 'inactive', '2024-10-03 12:52:13', '2024-10-03 12:52:13'),
(90, 'header_topbar_status', 'inactive', '2024-10-03 12:52:13', '2026-07-16 17:52:44'),
(91, 'cursor_dot_status', 'inactive', '2024-10-03 12:52:13', '2026-07-16 17:52:44'),
(92, 'header_social_status', 'inactive', '2024-10-03 12:52:13', '2026-07-16 17:52:44'),
(93, 'watermark_img', 'uploads/custom-images/wsus-img-2026-04-03-04-18-23-1911.png', '2024-06-02 20:02:30', '2026-04-03 16:18:23'),
(94, 'position', 'top_left', '2024-06-02 20:02:30', '2026-04-03 16:18:23'),
(95, 'opacity', '0.7', '2024-06-02 20:02:30', '2026-04-03 16:18:23'),
(96, 'max_width', '300', '2024-06-02 20:02:30', '2026-04-03 16:18:23'),
(97, 'watermark_status', 'active', '2024-06-02 20:02:30', '2026-04-03 16:18:23'),
(98, 'years_of_exprience', '6', '2024-06-03 02:02:30', '2026-07-16 17:52:44'),
(99, 'satisfied_clients', '200', '2024-06-03 02:02:30', '2026-07-16 17:52:44'),
(100, 'countries_reached', '15', '2024-06-03 02:02:30', '2026-07-16 17:52:44'),
(101, 'classes_conducted', '2', '2024-06-03 02:02:30', '2026-07-16 17:52:44'),
(102, 'referral_commission_percent', '10', '2026-05-04 06:57:07', '2026-05-04 06:57:07'),
(103, 'attendance_min_percent', '50', '2026-05-18 09:56:13', '2026-05-18 09:56:13'),
(104, 'custom_domain_enabled', '1', '2026-06-09 15:26:10', '2026-06-09 15:41:52'),
(105, 'custom_domain_server_ip', '149.248.18.191', '2026-06-09 15:26:10', '2026-06-09 15:41:52'),
(106, 'custom_domain_requires_approval', '0', '2026-06-09 15:26:10', '2026-06-09 15:26:10'),
(107, 'custom_domain_max_per_coach', '1', '2026-06-09 15:26:10', '2026-06-09 15:26:10');

-- --------------------------------------------------------

--
-- Table structure for table `socialite_credentials`
--

CREATE TABLE `socialite_credentials` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `provider_name` varchar(255) NOT NULL,
  `provider_id` varchar(255) DEFAULT NULL,
  `access_token` varchar(255) DEFAULT NULL,
  `refresh_token` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `states`
--

CREATE TABLE `states` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `country_id` bigint(20) UNSIGNED NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `coach_id` bigint(20) UNSIGNED DEFAULT NULL,
  `parent_coach_id` bigint(20) UNSIGNED DEFAULT NULL,
  `coach_unique_id` varchar(255) DEFAULT NULL,
  `referral_code` varchar(20) DEFAULT NULL,
  `referred_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `referred_at` timestamp NULL DEFAULT NULL,
  `added_by` bigint(20) UNSIGNED DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_enabled_at` timestamp NULL DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `trial_used_at` timestamp NULL DEFAULT NULL,
  `trial_started_at` timestamp NULL DEFAULT NULL,
  `trial_expired_at` timestamp NULL DEFAULT NULL,
  `is_demo` tinyint(1) NOT NULL DEFAULT 0,
  `demo_expires_at` timestamp NULL DEFAULT NULL,
  `is_banned` varchar(255) NOT NULL DEFAULT 'no',
  `verification_token` varchar(255) DEFAULT NULL,
  `notification_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `forget_password_token` varchar(255) DEFAULT NULL,
  `forget_password_token_expires_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `image` varchar(255) NOT NULL DEFAULT '/uploads/website-images/frontend-avatar.png',
  `cover` varchar(255) NOT NULL DEFAULT '/uploads/website-images/frontend-cover.png',
  `wallet_balance` decimal(8,2) NOT NULL DEFAULT 0.00,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `commission_mode` enum('temporary','permanent','free') DEFAULT NULL,
  `commission_note` varchar(255) DEFAULT NULL,
  `referral_wallet_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bio` text DEFAULT NULL,
  `short_bio` text DEFAULT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `country_id` bigint(20) UNSIGNED DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `instagram` varchar(255) DEFAULT NULL,
  `youtube` varchar(255) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `github` varchar(255) DEFAULT NULL,
  `onboarding_theme_chosen_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `coach_id`, `parent_coach_id`, `coach_unique_id`, `referral_code`, `referred_by_user_id`, `referred_at`, `added_by`, `branch_id`, `name`, `username`, `email`, `role`, `role_id`, `email_verified_at`, `password`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_enabled_at`, `two_factor_confirmed_at`, `remember_token`, `created_at`, `updated_at`, `status`, `trial_used_at`, `trial_started_at`, `trial_expired_at`, `is_demo`, `demo_expires_at`, `is_banned`, `verification_token`, `notification_preferences`, `forget_password_token`, `forget_password_token_expires_at`, `phone`, `address`, `image`, `cover`, `wallet_balance`, `commission_rate`, `commission_mode`, `commission_note`, `referral_wallet_balance`, `bio`, `short_bio`, `job_title`, `gender`, `age`, `country_id`, `state`, `city`, `facebook`, `instagram`, `youtube`, `twitter`, `linkedin`, `website`, `github`, `onboarding_theme_chosen_at`) VALUES
(1079, NULL, NULL, 'MBS1078', 'niduiwe', NULL, NULL, NULL, NULL, 'Coach Panel', 'coachpanel', 'sureshs.dhs1@gmail.com', 'instructor', NULL, '2026-02-13 13:35:27', '$2y$12$cASOfczIuMTz.VZq4HfzJu.tQIjBvkeCo5H/ETgLmXEY48JXX.pnK', 'eyJpdiI6ImwxdEVpUGdGaVdBUVVZdDEwZWMzQ1E9PSIsInZhbHVlIjoiWE90SXhJS2FOSDFqQ2lWTGtuSm13M2djdkVBdmFhaDJBWjlYSVZJS3pSaExGMmgvR1RzanRmOFFZb1FJMElzbSIsIm1hYyI6Ijc5ZTdkYjVjZDlhNjM4NDg0N2QwYTNhNGFhN2U5N2E3NzEwMTI2MDVjMjkwMzNkODZlMWNhOTg4YmMxNTI2NzMiLCJ0YWciOiIifQ==', 'eyJpdiI6IkxnRlJ6TkowTDhrOFZwbHJFRk9TUVE9PSIsInZhbHVlIjoiYTZSRVc1andvQTBLUVNxOTg5dHFKSG83R2lNMmpxbkZHUzBSUm8vVlAybUdXY3F6U001Y2ZKVThxMzc2YTlBNjQvSTM1Y25GbmFTRURQbXpzNDJmN2pPV2FwbE1VUUIxTUgxVHIrcUxaRy9KdGh0eE1ZNTJreHJ5Z1V0ai85dHZpeWttQnltZGJvWllhdUtPYkVUdituaVBybkFxdVA0MDJuOGFJTGRjd0JNPSIsIm1hYyI6IjhhYTZlMDQwM2VmZTNmMDllNjA0NzlhMzFhNTIyYzVjNGVmZDFmNmEzZWFjMWZiZDc0NzQ1NTNiNTY0NjMwMjYiLCJ0YWciOiIifQ==', '2026-07-02 13:59:33', NULL, NULL, '2026-02-13 13:03:14', '2026-07-07 13:22:42', 'active', '2026-06-26 10:59:12', '2026-06-26 10:59:12', '2026-07-10 10:59:12', 0, NULL, 'no', '5fsIldTaaMPMVBgjB1U86JFSgeemOTvKRWCQ53tPFoztmTqa1doajk82AW0KRes2XOX6oX7Gg3AxCzjKVgN6TbQJjS45hdSNXk5b', NULL, NULL, NULL, '9876543765', 'D-247/4A, Sector 63, Noida, Uttar Pradesh 201301', 'uploads/custom-images/wsus-img-2026-07-02-04-13-59-8918.jpg', '/uploads/website-images/frontend-cover.png', 939.00, NULL, NULL, NULL, 0.00, 'asdadasdasd', 'sdasdasdasda', 'CEO', 'male', 25, 1, 'Uttar Pradesh', 'Noida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-05-29 12:47:51'),
(1124, NULL, NULL, NULL, '6n4fdmx', NULL, NULL, NULL, NULL, 'Virendra Kumar', NULL, 'virendra.strengthyoga@gmail.com', 'instructor', NULL, '2026-04-30 10:19:19', '$2y$12$54p5Ofw8VI8ayN.4p9SRQeqT95a3LB1CBV5Zlrc6bMMQSh7FhBHSK', NULL, NULL, NULL, NULL, 'P2QTdPzTPYGtL2Ip7JNAzs8saA1VMp8BJ81oQch6flNeaccIFHyjbSqdMAOi', '2026-04-29 20:40:23', '2026-07-17 16:47:44', 'active', NULL, NULL, NULL, 0, NULL, 'no', 'MZNqhdNrenpg8h4bylWRebxzPqNf2P9Hst7e0dCZWNyaaDpxEZiAYs1HIbt5P9FwnU4tCmnMqMCNelEYE757rYDU53JtvCOQSxAq', NULL, NULL, NULL, '7776543765', 'D-247/4A Procapitus Business Park, Noida, sector 63, India (UP)', 'uploads/custom-images/wsus-img-2026-07-16-02-01-06-6094.jpg', '/uploads/website-images/frontend-cover.png', 8264.50, 0.00, 'free', NULL, 10.00, 'Virendra Kumar is a certified yoga trainer and wellness coach committed to promoting a healthy and balanced lifestyle. He is a QCI (YCB) Certified Yoga Teacher recognized by the Ministry of AYUSH, Government of India, and holds a Master\'s degree in Yoga (M.A. Yoga).', 'Passionate yoga instructor with multiple certifications and world record achievements, dedicated to helping individuals improve their physical, mental, and emotional well-being through personalized yoga training.', 'Certified Yoga Trainer & Wellness Coach', 'male', 41, 1, 'Uttar Pradesh', 'Noida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1136, 1079, NULL, NULL, '7ez9k2h', NULL, NULL, 1079, NULL, 'Rahul Kumar Mishra', NULL, 'rahul@gmail.com', 'staff-user', 2, '2026-05-20 09:20:54', '$2y$12$J1ZCUtExUPSMBrg970l2TuBIE/qZAXxNFflv85kXDMciS9d6ciDo2', NULL, NULL, NULL, NULL, NULL, '2026-05-20 09:20:54', '2026-05-26 13:34:26', 'active', NULL, NULL, NULL, 0, NULL, 'no', '6b201876a754b09773f39d9eaf45b21574da406656b32844af066fb463c59232', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1177, 1175, NULL, NULL, NULL, NULL, NULL, 1175, NULL, 'Alam Sir', NULL, 'badrrealam.dhs@gmail.com', 'Course Manager', 3, '2026-06-04 16:12:56', '$2y$12$Tq50xK5L52d.YAEDU5vdt.TsRMiN5hEdxBSktrMMW0MCy6rJTlECK', NULL, NULL, NULL, NULL, NULL, '2026-06-04 16:12:56', '2026-06-04 16:12:56', 'active', NULL, NULL, NULL, 0, NULL, 'no', '676dd5ea533abccb4e44836e7bb47a0449d467b7272837829213d5d88f881003', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1182, NULL, NULL, NULL, 'bcdfrwi', NULL, NULL, 1079, NULL, 'SantoshDHS', NULL, 'Santosh@gmail.com', 'student', NULL, '2026-06-05 11:06:39', '$2y$12$K5vknmnZN3AS2hwlZmix9.GGDgd/obnET9hWEnYkVCEMUcbT2oJrS', NULL, NULL, NULL, NULL, 'QYXoijmPI2b6UM7tb2rQm8PFEGaLDMDtW4DuA9hUFlMfluzoLvvnx6wRwkVF', '2026-06-05 11:06:39', '2026-07-02 15:06:55', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1187, NULL, NULL, NULL, 'hcbt7hw', NULL, NULL, 1175, NULL, 'Badre Alam', NULL, 'badrealam.dhs@gmail.com', 'student', NULL, '2026-06-08 11:14:12', '$2y$12$4KvMYTl51xao/RPLeRxbHuYGYNiw06Cj7tTQ..dtqm0G9tmePmIKC', NULL, NULL, NULL, NULL, NULL, '2026-06-08 11:14:12', '2026-06-08 11:14:13', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1195, NULL, NULL, 'MBS1194', 'eimfxm7', NULL, NULL, NULL, NULL, 'Photongears', 'photo-ngears', 'photongears@gmail.com', 'instructor', NULL, '2026-06-09 17:15:34', '$2y$12$IVBCdS0S/PstkjPa49Uv1.ZeH7YO5xLF8J9/xiT0NB/9IY5IdvxT6', NULL, NULL, NULL, NULL, 'S8WtsCeoOEvth2phPE7AwKeGizw8RXnaRWeF9vqRwplQ20hmQZ7v5mHwaYtg', '2026-06-09 17:15:07', '2026-06-26 12:55:52', 'active', '2026-06-26 12:55:52', '2026-06-26 12:55:52', '2026-07-10 12:55:52', 0, NULL, 'no', 'E1dg0OWZbJcwoL4fDpBQ5qRRZR6TBJYLkPu4eoElemzgv7jvOUrFOVk280pn87WKDGbMA75yoESJ5eqjNJH7Io8TIO24jblM81YB', NULL, NULL, NULL, '9876543765', 'D-247/4A, Sector 63, Noida, Uttar Pradesh 201301', 'uploads/custom-images/wsus-img-2026-06-19-05-56-47-2110.png', '/uploads/website-images/frontend-cover.png', 108102.82, NULL, NULL, NULL, 0.00, 'test', 'test', 'CEO', 'male', 25, 1, 'Uttar Pradesh', 'Noida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-16 11:27:06'),
(1196, NULL, NULL, NULL, 'feb9fc8', NULL, NULL, 1195, NULL, 'Photongears Student', NULL, 'photongearsstudent@gmail.com', 'student', NULL, '2026-06-10 13:05:57', '$2y$12$Oz5GCZFGQO27JYCtRz34ouKrnwVAMf2v4Dirl6yH6Q4WsVDhuxevG', NULL, NULL, NULL, NULL, NULL, '2026-06-10 13:05:57', '2026-06-10 15:25:55', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 'male', 25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1212, NULL, NULL, 'MBS1211', '2zgwqk9', NULL, NULL, NULL, NULL, 'Shivani', 'shivani', 'shivanitomar1097@gmail.com', 'student', NULL, '2026-06-16 10:38:42', '$2y$12$rYRWdf9xo3pJCF6yLopPv.Fr60nZqAb9cp.wUkLVTHESkhnjI3x2G', NULL, NULL, NULL, NULL, NULL, '2026-06-16 10:38:42', '2026-06-16 10:38:43', 'active', NULL, NULL, NULL, 0, NULL, 'no', 'V6Wqswp1NPN3Y8Gq77jIVXSlMI1FyF1GX0PQMJgW0iIeqeiZEDDXGVwju4I6eFi7pmKbnxMBAWZO6w4niYZFpHUXTno2IqzTjLqJ', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1213, NULL, NULL, 'MBS1212', '727rf7z', NULL, NULL, NULL, NULL, 'Slidus Coach', 'slidus-coach', 'slidus@gmail.com', 'instructor', NULL, '2026-06-17 11:58:28', '$2y$12$beKhFWUkUGZoqY/O2FhpGOeUMxKKyg/OvBWC1md3bzcIGPh3sO1ui', NULL, NULL, NULL, NULL, 'L3jseehQXXrnOjvAf8qzstUPGd8lCpUbcsIhOoJqgwi6mTo0MCtpaqEblj6c', '2026-06-17 11:09:16', '2026-07-09 13:40:51', 'active', '2026-06-25 16:38:15', '2026-06-25 16:38:15', '2026-07-09 16:38:15', 0, NULL, 'no', 'ka9dmIiOCZ7uNvmGU8nQl3ZKvteLvtlQqEd5yhflS3s2Wv5DQreSwqnaXlRtzIpZWaneIvZWSntddPXsCM8foozMjsLu1bLQ57sl', NULL, NULL, NULL, '999943765', 'D-247/4A Procapitus Business Park, Noida, sector 63, India (UP)', 'uploads/custom-images/wsus-img-2026-06-17-12-03-32-3617.png', '/uploads/website-images/frontend-cover.png', 6125.00, NULL, NULL, NULL, 0.00, 'I am a CRM specialist dedicated to helping businesses improve customer engagement, optimize sales processes, and enhance team productivity. Through Slidus, I provide innovative CRM solutions that simplify lead management, automate repetitive tasks, track customer interactions, and deliver actionable business insights. My goal is to empower organizations with technology that drives efficiency, strengthens customer relationships, and supports sustainable business growth.', 'Helping businesses streamline customer relationships, automate workflows, and accelerate growth with powerful CRM solutions.', 'Founder & CRM Solutions Expert', 'male', 25, 1, 'Uttar Pradesh', 'Noida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1216, NULL, NULL, NULL, 'agay69t', NULL, NULL, 1195, NULL, 'Rahul Mishra', NULL, 'rahul.mishra@digitalhubsolution.com', 'student', NULL, '2026-06-22 12:27:06', '$2y$12$40Wh0/RSphIw1P6T38Y.m.VsAimCfjuS/0T.yezbzqu0aKT5cYFH.', NULL, NULL, NULL, NULL, NULL, '2026-06-22 12:27:06', '2026-06-22 14:48:48', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1217, NULL, NULL, NULL, NULL, NULL, NULL, 1195, NULL, 'Santosh DHS', NULL, 'sales@digitalhubsolution.com', 'student', NULL, '2026-06-22 15:16:59', '$2y$12$3seDC8di37xnioevrNoD1.Lpln4JdI3Q74tnTiv7POr0a3e2XXE/6', NULL, NULL, NULL, NULL, NULL, '2026-06-22 15:16:59', '2026-06-22 15:16:59', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1218, NULL, NULL, NULL, '3vvda2a', NULL, NULL, 1124, NULL, 'Pooja Rani', NULL, 'virendra.best.8@gmail.com', 'student', NULL, '2026-06-22 18:06:45', '$2y$12$xctiyTc.fFU4mf4L7LqkQuT2P7zltXYA7tayGvsHc7iXGUIIZiOkm', NULL, NULL, NULL, NULL, 'rd8msa7OQIpv8gEzqArsBwlfUvzm7rfAC80BmLnpx4wIEsBcHzMZVEgwXZkq', '2026-06-22 18:06:45', '2026-06-22 18:07:32', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1223, NULL, NULL, 'MBS1218', NULL, NULL, NULL, NULL, NULL, 'Suresh Sarkar Coach', 'suresh-sarkar-coach', 'tttt.dhs@gmail.com', 'instructor', NULL, '2026-06-25 16:04:53', '$2y$12$.wfV2jga3Q.wFy18/Zh7y.cLO6HhNbCfaV6hp2eUPI0W6j20STs/u', NULL, NULL, NULL, NULL, NULL, '2026-06-25 16:01:55', '2026-06-26 16:40:05', 'active', '2026-06-25 18:09:11', '2026-06-25 18:09:11', '2026-07-09 18:09:11', 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 10625.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 'male', 30, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1230, NULL, NULL, NULL, '3myx5tu', NULL, NULL, 1223, NULL, 'Friday', NULL, 'friday@gmail.com', 'student', NULL, '2026-06-26 13:02:29', '$2y$12$ErT9xBCsfkImKKw15ssvmuCFQ8QpkDeqaSAZ2jdj7guHQRYCPqTXS', NULL, NULL, NULL, NULL, NULL, '2026-06-26 13:02:29', '2026-06-26 13:03:24', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1231, NULL, NULL, 'MBS1230', NULL, NULL, NULL, NULL, NULL, 'Sumit Rana', 'sumit-rana', 'sureshsss@digitalhubsolution.com', 'student', NULL, '2026-06-26 15:26:24', '$2y$12$O8Rm7tMlBoC2ubK8ZzPuxeKyvQyvDMQYqm2BsLjyKxIFabAx0tkUK', NULL, NULL, NULL, NULL, NULL, '2026-06-26 15:26:24', '2026-06-26 15:26:24', 'active', NULL, NULL, NULL, 0, NULL, 'no', 'ooQiwfKkrDyGdlCAIMbpgJxq94W2OSJtI1B3mY5dMZ81BLmcSM0FH7AYj7i3U7b7PQNLHCqte5blyYJIH99XQVTuk0MkS1yCwla0', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1233, NULL, NULL, 'MBS1232', 'wbm9kcs', 1079, '2026-06-29 11:14:36', NULL, NULL, 'Suresh Sarkar', 'suresh-sarkar', 'hsddd@gmail.com', 'instructor', NULL, '2026-06-29 11:49:27', '$2y$12$ft.ieOe/0Q0me4isT3P8j.ctzpeb2/HmbLMzpIcVado8IWoAFDQZq', NULL, NULL, NULL, NULL, NULL, '2026-06-29 11:14:36', '2026-06-30 10:11:50', 'active', '2026-06-29 11:14:36', '2026-06-29 11:14:36', '2026-07-13 11:14:36', 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 940.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1235, NULL, NULL, 'MBS1234', 'azuin68', NULL, NULL, NULL, NULL, 'Sumit Rana', 'sumit-rana', 'summit@gmail.com', 'student', NULL, '2026-06-29 13:04:45', '$2y$12$KtlhIUCM6aQHiaLP5J7/SOpv3.NpZmWE/9LIPovNTcMrJq8HjCcMm', NULL, NULL, NULL, NULL, NULL, '2026-06-29 13:04:45', '2026-06-29 13:04:46', 'active', NULL, NULL, NULL, 0, NULL, 'no', 'AkXdjKKGDJKQNKcEayRIvEcpmV7zp4MEc6ZBUlmHb5JTA4m70BzeHMoTBuhvt9qHY3F1KPzO9oUe0dURmqGSzICCWpfkM2UixFtI', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1236, NULL, NULL, NULL, '9tv4esq', NULL, NULL, NULL, NULL, 'keshu', NULL, 'keshu@maildrop.cc', 'student', NULL, '2026-06-30 09:59:43', '$2y$12$AUswNdMND6QmGDwvZL527OkcfLujwzPQN0LOM0M91/kRjYkehMlxS', 'eyJpdiI6IkVmNmRLSjhiMWxtTVh2akp0a0FBaXc9PSIsInZhbHVlIjoiQUdwb0Y4TFViTTBObkV0ajR0S2ViblpwQmVRY1J6UExvTW9kYitFY3lJMzAwNGFVMTdicjBoUE1Ua0E4SkVFMiIsIm1hYyI6ImFmMWE4ZDFlNDNkZjg2YTRmMjU0YWYxNGZkOTlhZmQ4NTM0MDMyZWE3MTViZWZkYjY5NTdjMjQyZWIwZmEyYTQiLCJ0YWciOiIifQ==', 'eyJpdiI6IjNZalc0MWMvcFZzdTBQeHJMUWxTMnc9PSIsInZhbHVlIjoiclJ2NWhDaENoZW1GY3g3b1dod1p4K0R5TmxNMWpvYVllYzRybU9MaUdjZjRXbUgzK29uSkY5K2lIWkM0NjBqdldHMzZsOWtFbmltTEcyOTltbmlkTC9Tc3liRzJGM1B6Q3BTTk4zVzI1OEtIV2N3YXdDbnZMM3pNUUJrTENVejV3bE9LTlRodllyRVUwZGQzNlVndml0YTdqSm9vWEJhTVBVdnFFZWZUaE9BPSIsIm1hYyI6IjE1MTY3OWRiYWNjZjQzOTkyMGY1ZDhiZTU4MjJlZGVhODNkZWFhMTJhM2QxZjk4NDI3NGNjMTM1OTFlZjM0MWMiLCJ0YWciOiIifQ==', '2026-06-30 10:01:09', NULL, NULL, '2026-06-30 09:57:03', '2026-06-30 10:01:09', 'active', NULL, NULL, NULL, 0, NULL, 'no', 'KDB2MTbnYuFFTQGfBALieEG2b8fZuTHIhhozu9M7KU2fgRDxsYnOP4JXJ7Loj6ETPmDbP7SHDDvNYdUr2d0LgRa2hbqbd9NmEdBy', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1237, NULL, NULL, 'MBS1236', NULL, 1079, '2026-06-30 11:23:31', NULL, NULL, 'Suresh Sarkar', 'suresh-sarkar', 'sureshs.dhs@gmail.com', 'instructor', NULL, '2026-06-30 11:23:55', '$2y$12$nYzHwS6TfaWcsg.4LE5ZRuVyLG7HSOempEF60cNoc/cREOpPdxgUm', NULL, NULL, NULL, NULL, NULL, '2026-06-30 11:23:31', '2026-06-30 13:50:55', 'active', '2026-06-30 11:23:31', '2026-06-30 11:23:31', '2026-07-14 11:23:31', 0, NULL, 'no', NULL, NULL, NULL, NULL, '9876543765', NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 882.99, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 'male', 25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1239, NULL, NULL, NULL, 'agbrkkw', NULL, NULL, 1237, NULL, 'Tuesday Student', NULL, 'tuesdaystudent@gmail.com', 'student', NULL, '2026-06-30 11:57:51', '$2y$12$hKRYBwM/RDVlE39Nm7gpo.e0PoBAWBguF9IWEOv7CYajn0wu3FXqe', NULL, NULL, NULL, NULL, NULL, '2026-06-30 11:57:51', '2026-06-30 11:58:59', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1242, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Virendra Client Coach', NULL, 'virendracoach@gmail.com', 'instructor', NULL, NULL, '$2y$12$EKnaPMBeHQugDmxpAEPTyeCL1GWC.cltI2yhBLzjWZcILcfWny3Ha', NULL, NULL, NULL, NULL, NULL, '2026-06-30 19:03:45', '2026-06-30 19:03:45', 'active', NULL, NULL, NULL, 0, NULL, 'no', 'RwuWMRd9SynjvaXodDPpTnWh3s5arTO5TK3r0pqDZ407FcsSH2XD6NQMj4M3rF2aQ0Z2Y1DVZvSdlpN8p1h6K2vcZI3TM4fR5rqx', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1247, 1124, NULL, NULL, NULL, NULL, NULL, 1124, NULL, 'Yoga', NULL, 'yoga@gmail.com', 'Staff User', 4, '2026-07-02 18:17:55', '$2y$12$G/RY4t3PjkjciRYApcHuKueEWzF1s/RisZI6jSTE7ef.23Pf2P7FO', NULL, NULL, NULL, NULL, NULL, '2026-07-02 18:17:55', '2026-07-02 18:17:55', 'active', NULL, NULL, NULL, 0, NULL, 'no', 'e8898b3cf0d181ab270a092e37967f2b402c3909e79c2c1228e5a7009854eece', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1249, 1124, NULL, NULL, NULL, NULL, NULL, 1124, NULL, 'Mansi Rawat', NULL, 'mansirawat97143@gmail.com', 'Staff User', 4, '2026-07-03 17:52:30', '$2y$12$84aqH3PmHHnS9lKZpkpWVOB.hIMbfIGTyNg1NLBR4wPFC/3ObbTNC', NULL, NULL, NULL, NULL, NULL, '2026-07-03 17:52:30', '2026-07-03 17:52:30', 'active', NULL, NULL, NULL, 0, NULL, 'no', 'dbe032b7cbfb30931d4b547f65a5908113df7fd6423b44cff34be397d2fb4a1c', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1254, 1237, NULL, NULL, NULL, NULL, NULL, 1237, NULL, 'Suresh+Sarkar STF', NULL, 'sureshsarkar2020@gmail.com', 'Manager', 7, '2026-07-06 11:05:56', '$2y$12$bNqRTNcktsCS0RYYTeOgfebBkPeWu65RFbClPX4JZRf8kWfITSs1K', NULL, NULL, NULL, NULL, NULL, '2026-07-06 11:05:56', '2026-07-06 11:18:17', 'active', NULL, NULL, NULL, 0, NULL, 'no', 'ca406002832f162c281a4afe6e13dd881e62a041b9c028d020c47fae83e36a9a', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 'male', 25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1255, NULL, NULL, 'MBS1254', 'tdjc65t', NULL, NULL, NULL, NULL, 'Yoga Singh', 'yoga-singh', 'yogasingh@gmail.com', 'instructor', NULL, '2026-07-07 10:16:17', '$2y$12$SfIo8Y/HBpDoq1DXoG40guw9CNUU8DHKfGT5anZYRhQqEbZJfb5Ae', NULL, NULL, NULL, NULL, NULL, '2026-07-07 10:14:42', '2026-08-25 05:12:51', 'active', '2026-07-07 10:14:42', '2026-07-07 10:14:42', '2026-07-21 10:14:42', 0, NULL, 'no', 'qY5tBS0L0GMdkh5KFMMiy52qdHH9LFxCLCdKRBJfTQbcmKhmxgCKwC1VXzUQkVDXq9MptoavxJOSk3UFDIgJ8LGZxfemyHX2cHyO', NULL, NULL, NULL, '9876543765', 'D-247/4A Procapitus Business Park, Noida, sector 63, India (UP)', 'uploads/custom-images/wsus-img-2026-07-07-12-35-25-7797.png', '/uploads/website-images/frontend-cover.png', 7594.20, NULL, NULL, NULL, 200.00, 'dfg', 'dfgdf', 'fdg', 'male', 25, 1, 'Uttar Pradesh', 'Noida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1256, NULL, NULL, 'MBS1255', NULL, NULL, NULL, NULL, NULL, 'SCC', 'software-coaching-center', 'Santosh@digitalhubsolution.com', 'instructor', NULL, '2026-07-07 11:18:40', '$2y$12$OeOWdZpg0OFgIl7IhYUzNe/6gbjdyLaudVQBwi5/JhFDpiujojev2', NULL, NULL, NULL, NULL, 'Q5v3D10l7aJwnjJnhytWbIQ47jhQBjZygyR3BfsOA3xfo9ual7rp0v9eD1RZ', '2026-07-07 11:18:12', '2026-07-13 10:52:30', 'active', '2026-07-07 11:18:12', '2026-07-07 11:18:12', '2026-07-21 11:18:12', 0, NULL, 'no', NULL, NULL, NULL, NULL, '9876543765', 'D-247/4A, Sector 63, Noida, Uttar Pradesh 201301', 'uploads/custom-images/wsus-img-2026-07-12-12-21-50-2207.png', 'uploads/custom-images/wsus-img-2026-07-12-12-22-06-6016.png', 28420.00, NULL, NULL, NULL, 0.00, 'sefsfsdf', 'sdfsfsds', 'CEO', 'male', 40, 1, 'Uttar Pradesh', 'Noida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1257, NULL, NULL, 'MBS1256', NULL, NULL, NULL, NULL, NULL, 'Priyam Shukla', 'priyam-shukla', 'priyam.digitalhubsolution@gmail.com', 'instructor', NULL, '2026-07-07 11:40:50', '$2y$12$WMNHLQ27aOqrC59mvN6Vp.zdsjR2htqzS5/5B0zxmGbobXm0yG.CC', NULL, NULL, NULL, NULL, NULL, '2026-07-07 11:40:25', '2026-07-07 11:45:42', 'active', '2026-07-07 11:40:25', '2026-07-07 11:40:25', '2026-07-21 11:40:25', 0, NULL, 'no', NULL, NULL, NULL, NULL, '+91 84487 88862', 'D-247, 4A, D Block, Sector 63, Noida', 'uploads/custom-images/wsus-img-2026-07-07-11-43-52-4594.jpg', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, 'fkewjio3wjndk', 'dnenfewjnk3wjm', 'Social Media Manager', 'male', 25, 1, 'Uttar Pradesh', 'Noida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1258, NULL, NULL, 'MBS1257', NULL, NULL, NULL, NULL, NULL, 'Sristi Malik', 'sristi-malik', 'sristi.malik@digitalhubsolution.com', 'instructor', NULL, '2026-07-07 11:58:26', '$2y$12$lQajRRKU8RM6f9dgm/YrIOPXCgooBLtH1MvQd3/XyeVgNuzCParkG', NULL, NULL, NULL, NULL, NULL, '2026-07-07 11:57:44', '2026-07-07 12:00:52', 'active', '2026-07-07 11:57:44', '2026-07-07 11:57:44', '2026-07-21 11:57:44', 0, NULL, 'no', NULL, NULL, NULL, NULL, '84476-77013', NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 'female', 24, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1259, NULL, NULL, NULL, 'vx5ew8k', NULL, NULL, 1255, NULL, 'Yoga Singh Student', NULL, 'yogasinghstudent@gmail.com', 'student', NULL, '2026-07-07 12:23:49', '$2y$12$uYlvRqElCE1ZGv3TEjz1iufSWtyQAP6T3iouwDBXeiJTgbtp5gm8y', NULL, NULL, NULL, NULL, NULL, '2026-07-07 12:23:49', '2026-07-07 12:30:33', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, '8711060074', 'D-247/4A Procapitus Business Park, Noida, sector 63, India (UP)', 'uploads/custom-images/wsus-img-2026-07-07-12-30-33-6869.jpg', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, 'sdfsdfsdf', 'zsfdsdf', 'Iusto magna ullam om', 'male', 25, 1, 'Uttar Pradesh', 'Noida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1260, NULL, NULL, 'MBS1259', 'xq8d9tb', NULL, NULL, NULL, NULL, 'Avneesh Patel', 'avneesh-patel', 'avneeshpatel@gmail.com', 'instructor', NULL, '2026-07-07 13:06:43', '$2y$12$Pta28C.cdURNGAa5YzZ5bucVeZd3iZM4SVuOtW.3GO9mgLf0DuWzi', NULL, NULL, NULL, NULL, NULL, '2026-07-07 13:05:55', '2026-07-07 13:56:21', 'active', '2026-07-07 13:05:55', '2026-07-07 13:05:55', '2026-07-21 13:05:55', 0, NULL, 'no', 'blLbDEP9aEv7nOMqc21QqORyfGvbuNPMTOBw0vOkOrAq6j71AemFihnFQNQFWIFufXZjkEqIQBNUP1YZQVeSCsBGWHypDXY8SvPv', NULL, NULL, NULL, '6389896544', 'Quia eveniet omnis', 'uploads/custom-images/wsus-img-2026-07-07-01-56-21-5552.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, 'Lorem Ipsum is simply dummy text of the printing and typesetting industry.', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry.', 'Lorem Ipsum is simply dummy text of the printing and typesetting industry.', 'male', 27, 1, 'Iure architecto susc', 'Molestiae dolor reic', 'https://www.bote.org', NULL, NULL, 'https://www.bedewuniwe.co', 'https://www.niredezylezyrar.me', 'https://www.tabyhor.org.uk', 'https://www.dutuxebiqe.org.au', NULL),
(1261, NULL, NULL, 'MBS1260', NULL, NULL, NULL, NULL, NULL, 'Shweta', 'shweta', 'shwetakardam.dhs@gmail.com', 'instructor', NULL, '2026-07-07 14:48:56', '$2y$12$UODbYma7rBmXyw/XSj/m.eagazdmJArJla9Z57MPp.6n.fsWjAmnG', NULL, NULL, NULL, NULL, NULL, '2026-07-07 14:48:34', '2026-07-07 14:54:52', 'active', '2026-07-07 14:48:34', '2026-07-07 14:48:34', '2026-07-21 14:48:34', 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 'female', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1263, NULL, NULL, 'MBS1262', NULL, NULL, NULL, NULL, NULL, 'Abstriq Technologies', 'abstriq-technologies', 'abstriqtechnologies1@gmail.com', 'instructor', NULL, '2026-07-07 21:49:10', '$2y$12$7AbV85uT52zVxhZ6dmNYweOxYhidFjpm8wqaMYgYNLwqdokYYG3gu', NULL, NULL, NULL, NULL, NULL, '2026-07-07 21:48:29', '2026-07-11 18:54:44', 'active', '2026-07-07 21:48:29', '2026-07-07 21:48:29', '2026-07-21 21:48:29', 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1264, NULL, NULL, NULL, 'qx7j66d', NULL, NULL, 1124, NULL, 'Suresh Sarkar', NULL, 'sureshsarkar201811@gmail.com', 'student', NULL, '2026-07-08 12:02:48', '$2y$12$Nke81EoPZixk49HQj7j/dubu5lCRdG72eOTs3PiJhwYRk4sxeOQYW', NULL, NULL, NULL, NULL, NULL, '2026-07-08 12:02:48', '2026-07-17 10:25:08', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, 'D-247/4A Procapitus Business Park, Noida, sector 63, India (UP)', '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, 1, 'Uttar Pradesh', 'Noida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1265, 1255, NULL, NULL, NULL, NULL, NULL, 1255, NULL, 'YogaSingh Staff', NULL, 'yogasinghstaff@gmail.com', 'Manager', 8, '2026-07-08 12:59:31', '$2y$12$xdvoDe7VLJg0QBcCqEYY3u3T/bN.ABfhMTbJUednVUw5OjMGrZfRS', NULL, NULL, NULL, NULL, NULL, '2026-07-08 12:59:31', '2026-07-09 17:41:29', 'active', NULL, NULL, NULL, 0, NULL, 'no', 'f57352fae484d4aa10dceb1dcbb5854501abad3318682511a00e36c76e8c709c', NULL, NULL, NULL, '9876667673', NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 'male', 25, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1266, 1255, NULL, NULL, NULL, NULL, NULL, 1255, NULL, 'Yoga Singh Staff 1', NULL, 'yogasinghstaff1@gmail.com', 'Manager', 8, '2026-07-08 13:00:19', '$2y$12$5FSwLlpudTE4DbD.TrouS.K03KwhzavCGSw9jE1mh2.7VPi6ub35e', NULL, NULL, NULL, NULL, NULL, '2026-07-08 13:00:19', '2026-07-08 13:00:19', 'active', NULL, NULL, NULL, 0, NULL, 'no', '2a8ef734836cca640c784235cb454f3183dbaf1004af47e87464fafd6edfbb28', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1267, 1255, NULL, NULL, NULL, NULL, NULL, 1255, NULL, 'Yoga Singh Staff 2', NULL, 'yogasinghstaff2@gmail.com', 'Manager', 8, '2026-07-08 13:01:32', '$2y$12$8bl64LZzUnNF92jKTn2hPefSaOe6pigRSS6PdaBfl/G6a/VmUjvay', NULL, NULL, NULL, NULL, NULL, '2026-07-08 13:01:32', '2026-07-08 13:01:32', 'active', NULL, NULL, NULL, 0, NULL, 'no', '579962f8d5e2321c764110286e6447ea6ef114265a1cb5b04a5495a083cd4bea', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1268, NULL, NULL, 'MBS1267', NULL, NULL, NULL, NULL, NULL, 'sadsad', 'sadsad', 'xinami97@gmail.com', 'instructor', NULL, '2026-07-09 06:30:39', '$2y$12$yE9nvqHsR4BNFsrz0Jpo7eARV6/JTWNKSp450PAQfuZuBFwwWhVNK', NULL, NULL, NULL, NULL, NULL, '2026-07-09 06:29:46', '2026-07-09 06:35:08', 'active', '2026-07-09 06:29:46', '2026-07-09 06:29:46', '2026-07-23 06:29:46', 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, 'uploads/custom-images/wsus-img-2026-07-09-06-35-08-9311.txt', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1269, NULL, NULL, NULL, 'm44cuie', NULL, NULL, 1213, NULL, 'Slidus Student', NULL, 'slidusstudent@gmail.com', 'student', NULL, '2026-07-09 13:39:19', '$2y$12$Vne0MIK3eeFQqOMVsueAc.gIS/OfMOl9pFGVsNNEzUHpQpY4ZkJFK', NULL, NULL, NULL, NULL, NULL, '2026-07-09 13:39:19', '2026-07-09 13:40:11', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1270, NULL, NULL, NULL, NULL, NULL, NULL, 1265, NULL, 'Yoga Singh Student 1', NULL, 'yogasinghstudent1@gmail.com', 'student', NULL, '2026-07-09 17:42:56', '$2y$12$HYuWchcg1i32wTw/em9uiubf3sAKQMoBHZkpqMELhXXAuGc/ag1w.', NULL, NULL, NULL, NULL, NULL, '2026-07-09 17:42:56', '2026-07-09 17:42:56', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1271, 1213, NULL, NULL, NULL, NULL, NULL, 1213, NULL, 'Slidus Staff', NULL, 'slidusstaff@gmail.com', 'Sales Manager', 9, '2026-07-10 13:55:18', '$2y$12$I23gDITV0GRAmehcqanvCOS1cvjbPo16kmFvZFrqPCHP4yksIlsIy', NULL, NULL, NULL, NULL, NULL, '2026-07-10 13:55:18', '2026-07-10 13:55:18', 'active', NULL, NULL, NULL, 0, NULL, 'no', 'b16bec3ffa896aa075596c6b70f9bd4478acc215ae28d125759a7756bc8367af', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1272, NULL, NULL, NULL, NULL, NULL, NULL, 1255, NULL, 'Yoga Singh Student 2', NULL, 'yogasinghstudent2@gmail.com', 'student', NULL, '2026-07-10 17:22:03', '$2y$12$ve0Sy13CoG1Sjrmk6pbxjOl/VpSlP.agwz6si2IbUx7iOJBBIh9Ka', NULL, NULL, NULL, NULL, NULL, '2026-07-10 17:22:03', '2026-07-10 17:22:03', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1273, NULL, NULL, 'MBS1272', '4qxph4z', NULL, NULL, NULL, NULL, 'advaityog', 'advaityog', 'advaityog@gmail.com', 'instructor', NULL, '2026-07-11 18:54:16', '$2y$12$hXoNH248.dqQLTMe0C25TeqOcTj8PjiyU//fqOLL.nIVozpv5W172', NULL, NULL, NULL, NULL, '2pPWGq2pz69cjf7EyvrzxjYN9jbP9KBGFN8zjEuEGr7He20rwHqXt43551jB', '2026-07-11 18:52:55', '2026-07-17 15:01:13', 'active', '2026-07-11 18:52:55', '2026-07-11 18:52:55', '2026-07-25 18:52:55', 0, NULL, 'no', 'G8rVvxvBP9HbdIVIP60Yuq6WcWnirEXjh00PNcq26nPpCrAIaIySM22zomV5mXNRf8TUKIH2nvmtWCEx623TJUXI9jnsNCfezcQq', NULL, NULL, NULL, '9198661033', NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 3920.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 'female', 37, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1274, NULL, NULL, NULL, NULL, NULL, NULL, 1273, NULL, 'Advaityog Student', NULL, 'advaityogstudent@gmail.com', 'student', NULL, '2026-07-13 10:36:11', '$2y$12$5DBNwP5Vv8YdrJ04HS2zTe/aHOB63jKfjGbbYOf8HttUxh7LAgGmG', NULL, NULL, NULL, NULL, NULL, '2026-07-13 10:36:11', '2026-07-13 10:36:11', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1275, NULL, NULL, NULL, NULL, NULL, NULL, 1273, NULL, 'rahu mishra', NULL, 'dofrahul@gmail.com', 'student', NULL, '2026-07-13 11:13:05', '$2y$12$.Yn27ETEh5O6.9XytlpS0OTd3kXrX27OnUYsBLZVCElAqjNrLF15S', NULL, NULL, NULL, NULL, NULL, '2026-07-13 11:13:05', '2026-07-13 11:13:05', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1276, NULL, NULL, 'MBS1275', NULL, NULL, NULL, NULL, NULL, 'Nitesh', 'nitesh', 'nitras531@gmail.com', 'instructor', NULL, NULL, '$2y$12$nle.zqh/w9y7suBumt34uuUnvlTRhDJE9PpZGLE1zgWZgwbOleADu', NULL, NULL, NULL, NULL, NULL, '2026-07-15 15:49:36', '2026-07-15 15:49:36', 'active', '2026-07-15 15:49:36', '2026-07-15 15:49:36', '2026-07-29 15:49:36', 0, NULL, 'no', 'ZiXDo7wQk5qmNTJmZY5qbm0YYWyiAGuuL4PAzD9pVsvaoEzV7ZfxePjLBTor6kKxzEcug63IMR2Ky1IYb4glLjWqtChJygSOsEHj', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1277, NULL, NULL, 'MBS1276', NULL, 1124, '2026-07-17 15:27:41', NULL, NULL, 'Guru Test', 'guru-test', 'gurutest@gmail.com', 'instructor', NULL, '2026-07-17 15:29:51', '$2y$12$7F.YuEkAmZZcUZC0GyBlEOLNIxLGMgHq..neGbn5BY2T23A7fXtNO', NULL, NULL, NULL, NULL, NULL, '2026-07-17 15:27:41', '2026-07-17 15:29:51', 'active', '2026-07-17 15:27:41', '2026-07-17 15:27:41', '2026-07-31 15:27:41', 0, NULL, 'no', 'ORy6b0lEubOpPU5uR7rVXr2ITtyXlgRjQGofEQUFAnN1HcnBZhyDkQT7nJwMVzYjqUkkqYU6EgHL9jL2I6pboRCa2hPKPGkEPNFc', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1278, NULL, NULL, 'MBS1277', 'juh34xg', 1255, '2026-07-21 12:00:40', NULL, NULL, 'Cockroach', 'cockroach-coach', 'cockroach@gmail.com', 'instructor', NULL, '2026-07-21 12:02:00', '$2y$12$7rhEmRaULTqHnoR5w6CMyuo4it37ksmbSpsKKYKTAJRhMc9ZEhGwS', NULL, NULL, NULL, NULL, NULL, '2026-07-21 12:00:40', '2026-08-31 08:32:06', 'active', '2026-07-21 12:00:40', '2026-07-21 12:00:40', '2026-08-04 12:00:40', 0, NULL, 'no', '8Y7dVOWBqnYUVL1NtCUqYm2Y2RWz2iL0wOq3mmdxI2J0cVqbOtFeIcfgkFK7JEkjay88y8fyeVztk9mrbFt14Wc6lNBYLowSIK7y', NULL, NULL, NULL, '9876543765', 'D-247/4A Procapitus Business Park, Noida, sector 63, India (UP)', 'uploads/custom-images/wsus-img-2026-08-25-10-43-24-2646.jpg', '/uploads/website-images/frontend-cover.png', 4648.33, NULL, NULL, NULL, 0.00, 'asdasdasdas', 'sadasdsad', 'Iusto magna ullam om', 'male', 25, 1, 'Uttar Pradesh', 'Noida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1279, NULL, NULL, NULL, '6m5wrap', NULL, NULL, 1278, NULL, 'Cockroach Student', NULL, 'cockroachstudent@gmail.com', 'student', NULL, '2026-07-21 12:19:26', '$2y$12$U6swnZE6nigWVD7lU/0ZielydSVvHVFMgP5ImbqvEKIJcvYlToC1S', NULL, NULL, NULL, NULL, NULL, '2026-07-21 12:19:26', '2026-07-21 12:43:01', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, '9876543456', 'Sector 63', 'uploads/custom-images/wsus-img-2026-07-21-12-43-01-3220.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, 'wqerwetert', 'tyhjyukhjm', 'ryrtyrty', 'male', 25, 1, 'UP', 'Noida', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1280, NULL, NULL, 'MBS1279', NULL, 1255, '2026-07-21 12:26:07', NULL, NULL, 'Kuch Bhi', 'kuch-bhi', 'kuchbhi@gmail.com', 'instructor', NULL, '2026-07-21 12:27:17', '$2y$12$.QMzNodmWD6Lw59TyTNRzOeAoqku5qmid/IxgO2rePWi/uzXXUbja', NULL, NULL, NULL, NULL, NULL, '2026-07-21 12:26:07', '2026-07-21 12:27:17', 'active', '2026-07-21 12:26:07', '2026-07-21 12:26:07', '2026-08-04 12:26:07', 0, NULL, 'no', '6FQXpak6uueDvKWiynEfw3ebIKLpac2zSQOTARarRnjD43FRlqJX0wnJPk16eQLz1oFywBTFrUEwJm7U4JJOCK0VpuiuZWb0tb2u', NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1281, NULL, NULL, NULL, 'bxqbxd2', NULL, NULL, 1256, NULL, 'Photongears', NULL, 'sss@digitalhubsolution.com', 'student', NULL, '2026-07-21 16:20:31', '$2y$12$n0uA7kaBRBczox4PPAhTAuLAUkgNagImTlKglzp8fwNoe.qkAqm/W', NULL, NULL, NULL, NULL, NULL, '2026-07-21 16:20:31', '2026-07-21 19:07:35', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1282, NULL, NULL, NULL, 'wqgm65h', NULL, NULL, 1278, NULL, 'Gudda', NULL, 'gudda@gmail.com', 'student', NULL, '2026-07-21 17:49:56', '$2y$12$yhIm/Qk5XJpyuR/47uBFOufbgzwIKDcg.4ZVu/DnXWNbUWUCg5clu', NULL, NULL, NULL, NULL, NULL, '2026-07-21 17:49:56', '2026-07-21 17:50:21', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1283, 1278, NULL, NULL, NULL, NULL, NULL, 1278, NULL, 'Debra Haney', NULL, 'myducudiq@mailinator.com', 'student', NULL, NULL, '$2y$12$hwIyz5P5p5EaERZhdQzVuOVLPaybflI0rYgzYRST2WVQd5Kw/XDme', NULL, NULL, NULL, NULL, NULL, '2026-07-30 12:13:04', '2026-07-30 12:13:04', '1', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1284, 1278, NULL, NULL, NULL, NULL, NULL, 1278, NULL, 'Marah Gutierrez', NULL, 'tykor@mailinator.com', 'student', NULL, NULL, '$2y$12$7qwgFHwrs07VAhNUDuGXiO7zhqkMr4eCY0zR3/1k3cAOgMphILN72', NULL, NULL, NULL, NULL, NULL, '2026-07-30 12:14:57', '2026-07-30 12:14:57', '1', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1285, 1278, NULL, NULL, NULL, NULL, NULL, 1278, NULL, 'Katell Rich', NULL, 'rawop@mailinator.com', 'student', NULL, NULL, '$2y$12$GJcRXP7UlrHfgxW6bnHEpuI1MvN0Wa/H.JO4pLjwtL9ubV0wgDYvm', NULL, NULL, NULL, NULL, NULL, '2026-07-31 13:47:02', '2026-07-31 13:47:02', '1', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, '/uploads/website-images/frontend-avatar.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(1286, 1278, NULL, NULL, 'wxv2yiz', NULL, NULL, 1278, NULL, 'Suru Kumar', NULL, 'pexesapol@mailinator.com', 'student', NULL, '2026-07-21 17:49:56', '$2y$12$U6swnZE6nigWVD7lU/0ZielydSVvHVFMgP5ImbqvEKIJcvYlToC1S', NULL, NULL, NULL, NULL, NULL, '2026-08-03 10:48:45', '2026-08-25 13:32:22', 'active', NULL, NULL, NULL, 0, NULL, 'no', NULL, NULL, NULL, NULL, NULL, NULL, 'uploads/custom-images/wsus-img-2026-08-25-07-02-21-7755.png', '/uploads/website-images/frontend-cover.png', 0.00, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_education`
--

CREATE TABLE `user_education` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `degree` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `current` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_education`
--

INSERT INTO `user_education` (`id`, `user_id`, `organization`, `degree`, `start_date`, `end_date`, `current`, `created_at`, `updated_at`) VALUES
(1, 1260, 'test', 'test digree', '2026-07-07', '2027-07-07', NULL, '2026-07-07 13:54:33', '2026-07-07 13:54:33');

-- --------------------------------------------------------

--
-- Table structure for table `user_experiences`
--

CREATE TABLE `user_experiences` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `current` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_experiences`
--

INSERT INTO `user_experiences` (`id`, `user_id`, `company`, `position`, `start_date`, `end_date`, `current`, `created_at`, `updated_at`) VALUES
(4, 1260, 'test', 'test position', '2026-07-07', '2027-07-07', NULL, '2026-07-07 13:53:58', '2026-07-07 13:53:58');

-- --------------------------------------------------------

--
-- Table structure for table `user_login_devices`
--

CREATE TABLE `user_login_devices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `fingerprint` varchar(64) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_login_devices`
--

INSERT INTO `user_login_devices` (`id`, `user_id`, `fingerprint`, `ip`, `user_agent`, `last_seen_at`, `created_at`, `updated_at`) VALUES
(1, 1124, 'a9aa3e5001e6bd5c94e9f8eccdc2ffcb6f372cdc', '122.161.50.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 18:05:20', '2026-06-22 18:05:20', '2026-06-22 18:05:20'),
(2, 1218, 'a9aa3e5001e6bd5c94e9f8eccdc2ffcb6f372cdc', '122.161.50.68', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-22 18:07:32', '2026-06-22 18:07:32', '2026-06-22 18:07:32'),
(3, 1195, '78aa705a74b28601c805329ad488508e46e67d2b', '122.161.52.57', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-23 11:49:39', '2026-06-23 11:49:39', '2026-06-23 11:49:39'),
(4, 1079, '78aa705a74b28601c805329ad488508e46e67d2b', '122.161.52.57', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-23 17:09:11', '2026-06-23 12:12:32', '2026-06-23 17:09:11'),
(5, 1124, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-13 16:28:56', '2026-06-23 12:28:29', '2026-07-13 16:28:56'),
(6, 1213, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-10 12:52:32', '2026-06-23 12:59:01', '2026-07-10 12:52:32'),
(7, 1221, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-23 14:51:12', '2026-06-23 14:51:12', '2026-06-23 14:51:12'),
(8, 1124, '985c391741a5e83a8d619d02c684914130cad134', '122.161.53.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 08:27:19', '2026-06-23 18:03:53', '2026-06-25 08:27:19'),
(9, 1218, '985c391741a5e83a8d619d02c684914130cad134', '122.161.53.245', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 08:29:04', '2026-06-23 18:06:00', '2026-06-25 08:29:04'),
(10, 1079, 'cd496b172660b16ea77faf676b776751763d2d9d', '122.161.52.233', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 15:54:40', '2026-06-24 10:35:39', '2026-06-24 15:54:40'),
(11, 1195, 'cd496b172660b16ea77faf676b776751763d2d9d', '122.161.52.233', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 17:07:58', '2026-06-24 11:09:45', '2026-06-24 17:07:58'),
(12, 1213, 'cd496b172660b16ea77faf676b776751763d2d9d', '122.161.52.233', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-24 17:08:21', '2026-06-24 17:08:21', '2026-06-24 17:08:21'),
(13, 1124, '7eb4596f1526d39234f74302b8e0472fdbf406f6', '14.96.24.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 13:21:07', '2026-06-25 13:18:58', '2026-06-25 13:21:07'),
(14, 1195, '7eb4596f1526d39234f74302b8e0472fdbf406f6', '14.96.24.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 13:22:03', '2026-06-25 13:22:03', '2026-06-25 13:22:03'),
(15, 1079, '886ac883b83ec6122fa70622749e3890af42ec11', '122.161.52.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 13:24:06', '2026-06-25 13:24:06', '2026-06-25 13:24:06'),
(16, 1222, 'e84401efc252a26bf78ed19bae2d3b2cabed7838', '14.96.24.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-25 13:38:21', '2026-06-25 13:38:21', '2026-06-25 13:38:21'),
(17, 1222, '886ac883b83ec6122fa70622749e3890af42ec11', '122.161.52.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 13:43:05', '2026-06-25 13:43:05', '2026-06-25 13:43:05'),
(18, 1223, '7eb4596f1526d39234f74302b8e0472fdbf406f6', '14.96.24.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 16:05:08', '2026-06-25 16:05:08', '2026-06-25 16:05:08'),
(19, 1124, 'fc8bb3b01cb746ad91b31982fb9d51f030608f63', '122.161.49.64', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.5 Mobile/15E148 Safari/604.1', '2026-06-25 16:07:47', '2026-06-25 16:07:47', '2026-06-25 16:07:47'),
(20, 1225, 'e84401efc252a26bf78ed19bae2d3b2cabed7838', '14.96.24.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-25 16:29:56', '2026-06-25 16:28:23', '2026-06-25 16:29:56'),
(21, 1226, 'e84401efc252a26bf78ed19bae2d3b2cabed7838', '14.96.24.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-25 16:33:04', '2026-06-25 16:33:04', '2026-06-25 16:33:04'),
(22, 1223, '886ac883b83ec6122fa70622749e3890af42ec11', '122.161.52.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 16:36:52', '2026-06-25 16:36:52', '2026-06-25 16:36:52'),
(23, 1213, '886ac883b83ec6122fa70622749e3890af42ec11', '122.161.52.23', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-25 16:38:14', '2026-06-25 16:38:14', '2026-06-25 16:38:14'),
(24, 1227, 'e84401efc252a26bf78ed19bae2d3b2cabed7838', '14.96.24.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-25 17:37:07', '2026-06-25 17:37:07', '2026-06-25 17:37:07'),
(25, 1229, 'e84401efc252a26bf78ed19bae2d3b2cabed7838', '14.96.24.11', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-25 18:44:16', '2026-06-25 18:44:16', '2026-06-25 18:44:16'),
(26, 1079, '2ced72d23727d255f7d4837d9323f571405f97cc', '122.161.52.174', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 15:42:02', '2026-06-26 10:59:11', '2026-06-26 15:42:02'),
(27, 1223, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 11:24:49', '2026-06-26 11:24:49', '2026-06-26 11:24:49'),
(28, 1229, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-26 12:00:02', '2026-06-26 11:59:43', '2026-06-26 12:00:02'),
(29, 1195, '2ced72d23727d255f7d4837d9323f571405f97cc', '122.161.52.174', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 12:55:49', '2026-06-26 12:55:49', '2026-06-26 12:55:49'),
(30, 1230, 'eab67e0666bb70cac42cddb166b5f24bfa44583c', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 UCPC/1.1.0.15', '2026-06-26 13:03:24', '2026-06-26 13:03:24', '2026-06-26 13:03:24'),
(31, 1124, 'eab67e0666bb70cac42cddb166b5f24bfa44583c', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 UCPC/1.1.0.15', '2026-06-26 13:44:31', '2026-06-26 13:44:31', '2026-06-26 13:44:31'),
(32, 1231, '9510fe5984e2d25000f0c885402a49da056439ad', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-26 15:26:24', '2026-06-26 15:26:24', '2026-06-26 15:26:24'),
(33, 1124, '51c83ea39be877a144c39d88d8999552fe477837', '122.161.53.156', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-26 17:24:32', '2026-06-26 17:24:32', '2026-06-26 17:24:32'),
(34, 1232, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-06-26 18:15:23', '2026-06-26 18:15:23', '2026-06-26 18:15:23'),
(35, 1079, '4a313d4f29e76cf1d152d8b5c55f9a740bb3c5b7', '223.177.132.237', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-28 07:41:58', '2026-06-26 21:41:26', '2026-06-28 07:41:58'),
(36, 1079, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 17:46:04', '2026-06-29 11:13:46', '2026-07-03 17:46:04'),
(37, 1233, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-29 11:49:40', '2026-06-29 11:49:40', '2026-06-29 11:49:40'),
(38, 1234, 'eab67e0666bb70cac42cddb166b5f24bfa44583c', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 UCPC/1.1.0.15', '2026-06-29 12:15:05', '2026-06-29 12:15:05', '2026-06-29 12:15:05'),
(39, 1235, '9510fe5984e2d25000f0c885402a49da056439ad', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 13:04:46', '2026-06-29 13:04:46', '2026-06-29 13:04:46'),
(40, 1079, '590e07638f00ca41894d0534377004827f06e4fe', '122.161.52.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-29 15:03:56', '2026-06-29 15:03:56', '2026-06-29 15:03:56'),
(41, 1124, '9510fe5984e2d25000f0c885402a49da056439ad', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36 Edg/149.0.0.0', '2026-06-29 19:06:13', '2026-06-29 15:05:37', '2026-06-29 19:06:13'),
(42, 1195, '590e07638f00ca41894d0534377004827f06e4fe', '122.161.52.20', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-29 17:44:58', '2026-06-29 17:44:58', '2026-06-29 17:44:58'),
(43, 1124, '184a568bc49db10c6119497fd1ea0bececbbb24c', '122.161.50.71', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-29 18:24:18', '2026-06-29 18:24:18', '2026-06-29 18:24:18'),
(44, 1218, '184a568bc49db10c6119497fd1ea0bececbbb24c', '122.161.50.71', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-29 18:28:44', '2026-06-29 18:28:44', '2026-06-29 18:28:44'),
(45, 1079, '340622f2581d5809a10e95550d2d8911f6f316c6', '122.161.53.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 10:41:23', '2026-06-30 10:41:23', '2026-06-30 10:41:23'),
(46, 1195, '340622f2581d5809a10e95550d2d8911f6f316c6', '122.161.53.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 10:42:53', '2026-06-30 10:42:53', '2026-06-30 10:42:53'),
(47, 1237, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-06 16:03:13', '2026-06-30 11:24:24', '2026-07-06 16:03:13'),
(48, 1238, 'eab67e0666bb70cac42cddb166b5f24bfa44583c', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 UCPC/1.1.0.15', '2026-06-30 11:54:01', '2026-06-30 11:54:01', '2026-06-30 11:54:01'),
(49, 1239, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 11:58:58', '2026-06-30 11:58:58', '2026-06-30 11:58:58'),
(50, 1079, '1828dd1aa3e2c5960ecb447e7c7ad045446c7b5a', '106.219.232.70', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 13:25:05', '2026-06-30 13:25:05', '2026-06-30 13:25:05'),
(51, 1195, 'a85f3d4ec61acaabc17771ea95bf2e643b946e62', '106.219.234.70', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 15:36:25', '2026-06-30 15:36:25', '2026-06-30 15:36:25'),
(52, 1218, 'f547c74999db623ee70a3458700ecc70f5bf7395', '122.161.53.32', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-06-30 17:46:35', '2026-06-30 17:46:35', '2026-06-30 17:46:35'),
(53, 1218, '7e38aa6d0a11c592258735dbcfc9b5490b59303e', '122.161.53.32', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_5_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/149.0.7827.137 Mobile/15E148 Safari/604.1', '2026-07-01 09:29:23', '2026-07-01 09:29:23', '2026-07-01 09:29:23'),
(54, 1079, 'eab67e0666bb70cac42cddb166b5f24bfa44583c', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 UCPC/1.1.0.15', '2026-07-02 13:37:27', '2026-07-01 10:32:26', '2026-07-02 13:37:27'),
(55, 1243, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-02 13:22:59', '2026-07-01 19:03:33', '2026-07-02 13:22:59'),
(56, 1218, '363d30c0031624a43d21ba4ad82536add645d761', '122.161.51.4', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_5_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/149.0.7827.137 Mobile/15E148 Safari/604.1', '2026-07-02 05:51:59', '2026-07-02 05:51:59', '2026-07-02 05:51:59'),
(57, 1238, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-02 13:19:00', '2026-07-02 13:19:00', '2026-07-02 13:19:00'),
(58, 1162, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-02 15:08:21', '2026-07-02 13:40:21', '2026-07-02 15:08:21'),
(59, 1182, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-02 14:52:58', '2026-07-02 14:49:44', '2026-07-02 14:52:58'),
(60, 1218, '9a12c5b1b292559ad25694b8ec0825a680d3b1dc', '122.161.50.98', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_5_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/149.0.7827.137 Mobile/15E148 Safari/604.1', '2026-07-02 15:12:58', '2026-07-02 15:12:58', '2026-07-02 15:12:58'),
(61, 1124, '363d30c0031624a43d21ba4ad82536add645d761', '122.161.51.4', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_5_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/149.0.7827.137 Mobile/15E148 Safari/604.1', '2026-07-02 16:45:53', '2026-07-02 16:45:53', '2026-07-02 16:45:53'),
(62, 1124, '3773ac6e1239341d79126a0039b6d0dce20dfee3', '122.161.51.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 18:56:10', '2026-07-02 16:46:16', '2026-07-03 18:56:10'),
(63, 1218, '3773ac6e1239341d79126a0039b6d0dce20dfee3', '122.161.51.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 08:54:59', '2026-07-02 16:48:55', '2026-07-03 08:54:59'),
(64, 1248, 'eab67e0666bb70cac42cddb166b5f24bfa44583c', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 UCPC/1.1.0.15', '2026-07-03 11:47:20', '2026-07-03 11:47:20', '2026-07-03 11:47:20'),
(65, 1248, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-03 18:01:59', '2026-07-03 18:01:59', '2026-07-03 18:01:59'),
(66, 1250, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-03 18:29:24', '2026-07-03 18:29:24', '2026-07-03 18:29:24'),
(67, 1249, '3773ac6e1239341d79126a0039b6d0dce20dfee3', '122.161.51.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-03 18:45:14', '2026-07-03 18:45:14', '2026-07-03 18:45:14'),
(68, 1162, 'eab67e0666bb70cac42cddb166b5f24bfa44583c', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 UCPC/1.1.0.15', '2026-07-03 18:53:47', '2026-07-03 18:53:47', '2026-07-03 18:53:47'),
(69, 1124, 'cb0a65341ad0071e90f3f5997ca7e550fccb365d', '122.161.75.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 08:27:45', '2026-07-04 08:27:45', '2026-07-04 08:27:45'),
(70, 1079, 'cb0a65341ad0071e90f3f5997ca7e550fccb365d', '122.161.75.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 18:32:05', '2026-07-04 08:37:51', '2026-07-04 18:32:05'),
(71, 1182, 'cb0a65341ad0071e90f3f5997ca7e550fccb365d', '122.161.75.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 22:29:47', '2026-07-04 08:39:48', '2026-07-04 22:29:47'),
(72, 1124, '5ab34a588f6ff60b18fe57ff38b9f9c69a0498ce', '106.219.238.222', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 09:34:44', '2026-07-04 09:34:44', '2026-07-04 09:34:44'),
(73, 1251, '5ab34a588f6ff60b18fe57ff38b9f9c69a0498ce', '106.219.238.222', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 09:42:00', '2026-07-04 09:42:00', '2026-07-04 09:42:00'),
(74, 1252, '5ab34a588f6ff60b18fe57ff38b9f9c69a0498ce', '106.219.238.222', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 09:45:51', '2026-07-04 09:45:51', '2026-07-04 09:45:51'),
(75, 1124, '126ebaa0e362bae449228fc14067e93b65bb11d5', '106.219.232.14', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 16:37:15', '2026-07-04 16:37:15', '2026-07-04 16:37:15'),
(76, 1253, '126ebaa0e362bae449228fc14067e93b65bb11d5', '106.219.232.14', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 16:42:20', '2026-07-04 16:42:20', '2026-07-04 16:42:20'),
(77, 1250, 'cb0a65341ad0071e90f3f5997ca7e550fccb365d', '122.161.75.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 18:33:36', '2026-07-04 18:33:36', '2026-07-04 18:33:36'),
(78, 1253, '8a21c89f5753dd7b5c5c9c9a55e240ea2efb180d', '106.219.233.14', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-04 18:48:44', '2026-07-04 18:48:44', '2026-07-04 18:48:44'),
(79, 1162, 'fbd339acde4e69e5dcfac3f80fdfc14b079c8fe3', '106.192.207.114', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-07-04 22:22:36', '2026-07-04 22:22:36', '2026-07-04 22:22:36'),
(80, 1250, 'fbd339acde4e69e5dcfac3f80fdfc14b079c8fe3', '106.192.207.114', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-07-04 22:23:39', '2026-07-04 22:23:39', '2026-07-04 22:23:39'),
(81, 1124, 'fbd339acde4e69e5dcfac3f80fdfc14b079c8fe3', '106.192.207.114', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-07-04 22:25:08', '2026-07-04 22:25:08', '2026-07-04 22:25:08'),
(82, 1253, 'fbd339acde4e69e5dcfac3f80fdfc14b079c8fe3', '106.192.207.114', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-07-04 22:26:39', '2026-07-04 22:26:39', '2026-07-04 22:26:39'),
(83, 1254, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-06 16:47:07', '2026-07-06 11:07:29', '2026-07-06 16:47:07'),
(84, 1195, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-06 12:11:40', '2026-07-06 12:11:40', '2026-07-06 12:11:40'),
(85, 1079, 'ba1a7f7cc3a2f2013c4efc4e950d563855e50143', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-06 15:38:18', '2026-07-06 13:58:59', '2026-07-06 15:38:18'),
(86, 1196, 'ba1a7f7cc3a2f2013c4efc4e950d563855e50143', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-07 11:34:42', '2026-07-06 16:12:09', '2026-07-07 11:34:42'),
(87, 1182, 'ba1a7f7cc3a2f2013c4efc4e950d563855e50143', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-06 17:01:15', '2026-07-06 17:01:15', '2026-07-06 17:01:15'),
(88, 1124, 'e11b437cdc27a68f7199a0f1865bfb53feccb57d', '122.161.50.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-14 18:10:24', '2026-07-07 09:43:17', '2026-07-14 18:10:24'),
(89, 1255, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-13 16:17:27', '2026-07-07 10:16:39', '2026-07-13 16:17:27'),
(90, 1079, '54ec56e341ecdb6e81c5f092da8a92f827b25fa5', '122.161.51.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-07 10:45:42', '2026-07-07 10:45:42', '2026-07-07 10:45:42'),
(91, 1182, '54ec56e341ecdb6e81c5f092da8a92f827b25fa5', '122.161.51.93', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-07 10:51:37', '2026-07-07 10:51:37', '2026-07-07 10:51:37'),
(92, 1256, 'ba1a7f7cc3a2f2013c4efc4e950d563855e50143', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-27 10:39:46', '2026-07-07 11:19:02', '2026-07-27 10:39:46'),
(93, 1195, 'ba1a7f7cc3a2f2013c4efc4e950d563855e50143', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-07 17:17:12', '2026-07-07 11:35:20', '2026-07-07 17:17:12'),
(94, 1257, '31b11d42bed2e2f1060542b6ce4535fe1b7839a5', '103.222.253.213', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-07 11:41:06', '2026-07-07 11:41:06', '2026-07-07 11:41:06'),
(95, 1258, 'a9ec052686c5245b576f0a07db4251f857b3db90', '125.99.186.242', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-07 11:58:59', '2026-07-07 11:58:59', '2026-07-07 11:58:59'),
(96, 1259, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-07 12:28:34', '2026-07-07 12:28:34', '2026-07-07 12:28:34'),
(97, 1260, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-07 13:11:06', '2026-07-07 13:11:06', '2026-07-07 13:11:06'),
(98, 1261, 'fd18b0ecb73e57fd01e95d088aa0535d5146a577', '125.99.186.242', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-07-07 14:49:46', '2026-07-07 14:49:46', '2026-07-07 14:49:46'),
(99, 1218, 'e11b437cdc27a68f7199a0f1865bfb53feccb57d', '122.161.50.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-13 16:01:41', '2026-07-07 16:53:00', '2026-07-13 16:01:41'),
(100, 1263, 'dc3574f0b0f8f12ef6b81e2ef86a3727d47b380f', '122.161.51.173', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-07 21:49:15', '2026-07-07 21:49:15', '2026-07-07 21:49:15'),
(101, 1259, '8fedeead70a899d4ce642b7cbd84d752ac0dc5f3', '106.192.205.42', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-07-07 22:52:15', '2026-07-07 22:52:15', '2026-07-07 22:52:15'),
(102, 1256, '67aacddea53452b374fb69b8e1d59530c75a8930', '122.161.75.212', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-07 22:52:51', '2026-07-07 22:52:51', '2026-07-07 22:52:51'),
(103, 1266, 'eab67e0666bb70cac42cddb166b5f24bfa44583c', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 UCPC/1.1.0.15', '2026-07-13 12:15:04', '2026-07-08 13:37:38', '2026-07-13 12:15:04'),
(104, 1259, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-20 12:03:16', '2026-07-08 17:04:21', '2026-07-20 12:03:16'),
(105, 1268, '9205b0c005195539c2af9e7926b33d5c79040746', '45.8.25.65', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-07-09 06:30:47', '2026-07-09 06:30:47', '2026-07-09 06:30:47'),
(106, 1256, 'c27a7632a41217e64bd06f2a0f18aece171ec331', '122.161.76.206', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 22:09:08', '2026-07-09 11:59:55', '2026-07-12 22:09:08'),
(107, 1259, 'eab67e0666bb70cac42cddb166b5f24bfa44583c', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 UCPC/1.1.0.15', '2026-07-20 12:09:08', '2026-07-09 12:03:28', '2026-07-20 12:09:08'),
(108, 1269, 'eab67e0666bb70cac42cddb166b5f24bfa44583c', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 UCPC/1.1.0.15', '2026-07-10 12:57:47', '2026-07-09 13:40:10', '2026-07-10 12:57:47'),
(109, 1265, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-13 16:19:46', '2026-07-09 15:39:29', '2026-07-13 16:19:46'),
(110, 1124, 'c27a7632a41217e64bd06f2a0f18aece171ec331', '122.161.76.206', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-09 17:10:56', '2026-07-09 17:10:56', '2026-07-09 17:10:56'),
(111, 1124, 'ba1a7f7cc3a2f2013c4efc4e950d563855e50143', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 12:13:51', '2026-07-10 10:29:44', '2026-07-20 12:13:51'),
(112, 1266, '94a0b9573e4b88596bdf5ea547b003406521b0a9', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-10 16:02:43', '2026-07-10 13:50:57', '2026-07-10 16:02:43'),
(113, 1271, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-10 13:55:37', '2026-07-10 13:55:37', '2026-07-10 13:55:37'),
(114, 1216, 'c27a7632a41217e64bd06f2a0f18aece171ec331', '122.161.76.206', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-11 13:13:29', '2026-07-11 13:13:29', '2026-07-11 13:13:29'),
(115, 1273, 'c27a7632a41217e64bd06f2a0f18aece171ec331', '122.161.76.206', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-12 07:39:02', '2026-07-11 19:40:13', '2026-07-12 07:39:02'),
(116, 1273, 'ba615cab0a91cd77e37100dfb96cfe5b19da5413', '106.192.199.189', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-11 19:44:58', '2026-07-11 19:44:58', '2026-07-11 19:44:58'),
(117, 1273, 'c9f5906b9d53f2f966efbaeae70c1972770be0ee', '106.192.192.38', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-07-12 10:28:09', '2026-07-12 10:27:54', '2026-07-12 10:28:09'),
(118, 1273, '535433a8651fd360ca2bf0e53b628e1490549f92', '106.219.195.113', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/30.0 Chrome/143.0.0.0 Mobile Safari/537.36', '2026-07-12 11:51:45', '2026-07-12 11:51:45', '2026-07-12 11:51:45'),
(119, 1273, 'f6e40a17e9bc7f8d2ae8965b96af50ae857bbcda', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36', '2026-07-13 10:22:24', '2026-07-13 10:22:24', '2026-07-13 10:22:24'),
(120, 1273, '48e8c085a2b8504f94eb4ba9d183b827d973be8d', '49.43.170.169', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 11:11:14', '2026-07-13 11:11:14', '2026-07-13 11:11:14'),
(121, 1259, '94a0b9573e4b88596bdf5ea547b003406521b0a9', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-13 12:19:51', '2026-07-13 12:19:51', '2026-07-13 12:19:51'),
(122, 1256, 'a2c58bcf17211c2b52d4986ab1b99bfbc047d50b', '122.161.66.140', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-13 21:15:55', '2026-07-13 21:15:55', '2026-07-13 21:15:55'),
(123, 1255, 'ba1a7f7cc3a2f2013c4efc4e950d563855e50143', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-21 11:57:03', '2026-07-14 10:28:35', '2026-07-21 11:57:03'),
(124, 1273, '8a307b6d9828d25ba2104fa8d9c723fae8516194', '106.219.238.99', 'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', '2026-07-15 15:51:47', '2026-07-15 15:51:47', '2026-07-15 15:51:47'),
(125, 1273, 'ba1a7f7cc3a2f2013c4efc4e950d563855e50143', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 11:36:47', '2026-07-15 17:07:48', '2026-07-20 11:36:47'),
(126, 1256, 'e27f6c02be5bbd11490155f7d51474474f4a55ab', '122.161.65.162', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 12:24:57', '2026-07-15 21:50:17', '2026-07-18 12:24:57'),
(127, 1124, 'e27f6c02be5bbd11490155f7d51474474f4a55ab', '122.161.65.162', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 10:48:25', '2026-07-16 07:28:00', '2026-07-18 10:48:25'),
(128, 1273, '831c87a0fcdda74a65483933287e60d9715d3cd6', '106.219.238.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-16 08:59:36', '2026-07-16 08:59:36', '2026-07-16 08:59:36'),
(129, 1124, '4235702503b29ea659d316c803009f66c3b0a46b', '122.161.50.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-20 09:48:55', '2026-07-16 09:00:02', '2026-07-20 09:48:55'),
(130, 1218, '4235702503b29ea659d316c803009f66c3b0a46b', '122.161.50.151', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-16 18:26:22', '2026-07-16 09:02:36', '2026-07-16 18:26:22'),
(131, 1264, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-16 14:59:59', '2026-07-16 10:22:06', '2026-07-16 14:59:59'),
(132, 1124, '6ce4f14b5165d2ff7234d638b22ecd00eddb8896', '106.219.237.31', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-07-16 13:59:43', '2026-07-16 13:59:32', '2026-07-16 13:59:43'),
(133, 1124, '6968fe63ef6f72a0fb627af5652f7f195b6a84ff', '106.219.238.46', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Mobile Safari/537.36', '2026-07-16 16:47:21', '2026-07-16 16:46:16', '2026-07-16 16:47:21'),
(134, 1273, '4e07d8526055f5fa78088065e6fe0b43b1dd9000', '106.219.224.207', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-16 21:25:59', '2026-07-16 21:25:59', '2026-07-16 21:25:59'),
(135, 1264, '1eb0ea86947203dbceb8420030a9c18ddc9a67cd', '103.222.253.213', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 12:54:54', '2026-07-17 10:23:48', '2026-07-17 12:54:54'),
(136, 1256, '1eb0ea86947203dbceb8420030a9c18ddc9a67cd', '103.222.253.213', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 13:56:10', '2026-07-17 12:06:12', '2026-07-17 13:56:10'),
(137, 1273, '76876e5f85eb89c27fcfd517dfddbaecbdc216c2', '45.119.31.7', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-17 14:25:47', '2026-07-17 14:25:44', '2026-07-17 14:25:47'),
(138, 1124, '91160cb61e8e552208074d47710669c03f9e0133', '125.99.186.242', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 15:25:55', '2026-07-17 15:25:55', '2026-07-17 15:25:55'),
(139, 1277, '91160cb61e8e552208074d47710669c03f9e0133', '125.99.186.242', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 15:31:49', '2026-07-17 15:31:49', '2026-07-17 15:31:49'),
(140, 1256, '91160cb61e8e552208074d47710669c03f9e0133', '125.99.186.242', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-17 17:05:50', '2026-07-17 17:05:40', '2026-07-17 17:05:50'),
(141, 1196, 'e27f6c02be5bbd11490155f7d51474474f4a55ab', '122.161.65.162', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 12:17:20', '2026-07-18 10:54:22', '2026-07-18 12:17:20'),
(142, 1195, 'e27f6c02be5bbd11490155f7d51474474f4a55ab', '122.161.65.162', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 12:17:58', '2026-07-18 10:56:32', '2026-07-18 12:17:58'),
(143, 1273, 'e27f6c02be5bbd11490155f7d51474474f4a55ab', '122.161.65.162', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-18 12:51:41', '2026-07-18 12:51:41', '2026-07-18 12:51:41'),
(144, 1273, '9b253cc0a6ae93e0662ce2fa24394b103e8cde1b', '106.221.235.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-19 14:18:53', '2026-07-19 13:52:30', '2026-07-19 14:18:53'),
(145, 1273, '3a15da11d98e9d44687be6f31ddbf37318d804e8', '49.36.213.224', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Safari/605.1.15', '2026-07-19 13:57:43', '2026-07-19 13:57:43', '2026-07-19 13:57:43'),
(146, 1273, '4bdf4c73b2839e226fedbd87af78ddcb9342dd55', '49.36.213.224', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-19 13:58:27', '2026-07-19 13:58:27', '2026-07-19 13:58:27'),
(147, 1273, '7a52eb87e00aa5c61dac5834437629757fa6166b', '49.36.189.241', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-20 16:51:03', '2026-07-20 13:45:26', '2026-07-20 16:51:03'),
(148, 1278, 'ba1a7f7cc3a2f2013c4efc4e950d563855e50143', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-28 11:41:09', '2026-07-21 12:02:41', '2026-07-28 11:41:09'),
(149, 1280, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-21 12:28:11', '2026-07-21 12:28:11', '2026-07-21 12:28:11'),
(150, 1279, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-21 12:41:09', '2026-07-21 12:41:09', '2026-07-21 12:41:09'),
(151, 1273, 'fee1b571b271ef683c49a1f09580ae872e0a4194', '103.208.68.15', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-21 14:46:45', '2026-07-21 14:46:45', '2026-07-21 14:46:45'),
(152, 1282, '3db5fe5221b38f096756de5f6e2c8ef909d3a325', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:152.0) Gecko/20100101 Firefox/152.0', '2026-07-21 17:50:21', '2026-07-21 17:50:21', '2026-07-21 17:50:21'),
(153, 1281, 'ba1a7f7cc3a2f2013c4efc4e950d563855e50143', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-22 13:39:37', '2026-07-21 19:07:35', '2026-07-22 13:39:37'),
(154, 1279, 'ba1a7f7cc3a2f2013c4efc4e950d563855e50143', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 10:23:23', '2026-07-23 10:48:32', '2026-07-24 10:23:23'),
(155, 1256, '14b3b01ca5d01d190231cf8dd795aa58939b94d3', '122.161.77.75', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-07-24 06:20:24', '2026-07-24 06:20:24', '2026-07-24 06:20:24'),
(156, 1279, '2e1c609c255d60cc2d6ee52fab7ebce9a2d15a1e', '14.96.24.10', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:153.0) Gecko/20100101 Firefox/153.0', '2026-07-27 10:58:06', '2026-07-24 10:31:16', '2026-07-27 10:58:06'),
(157, 1273, '81daef95390f86f311fc58c2885ecbe3491d30f1', '49.36.189.33', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-24 15:36:53', '2026-07-24 15:36:53', '2026-07-24 15:36:53'),
(158, 1256, '81daef95390f86f311fc58c2885ecbe3491d30f1', '49.36.189.33', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-24 16:29:48', '2026-07-24 16:29:48', '2026-07-24 16:29:48'),
(159, 1256, 'ab2fa3f5b521bb2aeeb3666a5d6a53d30888117f', '49.36.189.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-27 13:33:47', '2026-07-27 13:33:47', '2026-07-27 13:33:47'),
(160, 1273, 'ab2fa3f5b521bb2aeeb3666a5d6a53d30888117f', '49.36.189.181', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-27 16:12:07', '2026-07-27 14:09:31', '2026-07-27 16:12:07'),
(161, 1273, 'a1304473ac29ed29c99e1dd4416dee1b9aeadfb6', '103.222.253.213', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-28 17:46:03', '2026-07-28 17:46:03', '2026-07-28 17:46:03'),
(162, 1273, '47f00c82558764ff6b05fdb549cfcb4ac4ee7b01', '106.192.202.187', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', '2026-07-28 22:11:22', '2026-07-28 22:11:22', '2026-07-28 22:11:22'),
(163, 1278, '86c2afd1700b9698fbf78267d72a8dcbd809f9f2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-25 05:09:18', '2026-07-30 09:40:06', '2026-08-25 05:09:18'),
(164, 1079, '85cd447062a006dc56478d871aa24110582dfb9b', '127.0.0.1', 'Symfony', '2026-08-31 08:26:43', '2026-07-30 12:06:43', '2026-08-31 08:26:43'),
(165, 1278, 'f230fd9a56f76b726b024f98bedc3cb03f24b05d', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 10:39:55', '2026-08-03 10:39:55', '2026-08-03 10:39:55'),
(166, 1280, '86c2afd1700b9698fbf78267d72a8dcbd809f9f2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-18 10:44:26', '2026-08-18 10:44:26', '2026-08-18 10:44:26'),
(167, 1278, '21074262408ef8396c03d8742d2a90359bc8bd79', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Claude/1.34493.1 Chrome/148.0.7778.280 Electron/42.9.2 Safari/537.36', '2026-08-25 13:17:50', '2026-08-24 13:14:22', '2026-08-25 13:17:50'),
(168, 1286, '17be0a89c1b0e2cbca82e8efdfd1db8fc48498d2', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:154.0) Gecko/20100101 Firefox/154.0', '2026-08-25 13:30:19', '2026-08-25 13:30:19', '2026-08-25 13:30:19'),
(169, 1278, '4632f51f31d07db358e170a8becb7cb56d85dbae', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-31 08:18:55', '2026-08-31 08:18:55', '2026-08-31 08:18:55'),
(170, 1182, '85cd447062a006dc56478d871aa24110582dfb9b', '127.0.0.1', 'Symfony', '2026-08-31 08:26:28', '2026-08-31 08:25:15', '2026-08-31 08:26:28'),
(171, 1286, '4632f51f31d07db358e170a8becb7cb56d85dbae', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-08-31 08:25:26', '2026-08-31 08:25:26', '2026-08-31 08:25:26'),
(172, 1278, '85cd447062a006dc56478d871aa24110582dfb9b', '127.0.0.1', 'Symfony', '2026-08-31 08:27:45', '2026-08-31 08:27:05', '2026-08-31 08:27:45'),
(173, 1283, '85cd447062a006dc56478d871aa24110582dfb9b', '127.0.0.1', 'Symfony', '2026-08-31 08:27:09', '2026-08-31 08:27:09', '2026-08-31 08:27:09');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  ADD KEY `activity_logs_actor_type_actor_id_index` (`actor_type`,`actor_id`),
  ADD KEY `activity_logs_actor_id_index` (`actor_id`),
  ADD KEY `activity_logs_action_index` (`action`),
  ADD KEY `activity_logs_module_index` (`module`),
  ADD KEY `activity_logs_created_at_index` (`created_at`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admins_email_unique` (`email`);

--
-- Indexes for table `attendances`
--
ALTER TABLE `attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attendances_user_id_attendance_date_unique` (`user_id`,`attendance_date`),
  ADD KEY `attendances_attendance_date_index` (`attendance_date`),
  ADD KEY `attendances_status_index` (`status`),
  ADD KEY `attendances_marked_by_index` (`marked_by`),
  ADD KEY `attendances_company_id_index` (`company_id`);

--
-- Indexes for table `attendance_regularizations`
--
ALTER TABLE `attendance_regularizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_regularizations_user_id_attendance_date_index` (`user_id`,`attendance_date`),
  ADD KEY `attendance_regularizations_status_index` (`status`),
  ADD KEY `attendance_regularizations_company_id_index` (`company_id`);

--
-- Indexes for table `bonuses`
--
ALTER TABLE `bonuses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bonuses_user_id_year_month_index` (`user_id`,`year`,`month`),
  ADD KEY `bonuses_company_id_index` (`company_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cities_state_id_foreign` (`state_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `companies_slug_unique` (`slug`),
  ADD UNIQUE KEY `companies_code_unique` (`code`),
  ADD KEY `companies_owner_user_id_index` (`owner_user_id`),
  ADD KEY `companies_status_index` (`status`);

--
-- Indexes for table `company_user`
--
ALTER TABLE `company_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `company_user_company_id_user_id_unique` (`company_id`,`user_id`),
  ADD KEY `company_user_user_id_index` (`user_id`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `custom_codes`
--
ALTER TABLE `custom_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `custom_paginations`
--
ALTER TABLE `custom_paginations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `departments_company_id_code_unique` (`company_id`,`code`),
  ADD KEY `departments_head_user_id_index` (`head_user_id`),
  ADD KEY `departments_parent_id_index` (`parent_id`),
  ADD KEY `departments_company_id_index` (`company_id`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_templates_name_unique` (`name`);

--
-- Indexes for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_profiles_user_id_unique` (`user_id`),
  ADD UNIQUE KEY `employee_profiles_company_id_employee_code_unique` (`company_id`,`employee_code`),
  ADD KEY `employee_profiles_department_id_index` (`department_id`),
  ADD KEY `employee_profiles_reporting_hr_id_index` (`reporting_hr_id`),
  ADD KEY `employee_profiles_status_index` (`status`),
  ADD KEY `employee_profiles_company_id_index` (`company_id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `languages`
--
ALTER TABLE `languages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `languages_name_unique` (`name`),
  ADD UNIQUE KEY `languages_code_unique` (`code`);

--
-- Indexes for table `leaves`
--
ALTER TABLE `leaves`
  ADD PRIMARY KEY (`id`),
  ADD KEY `leaves_user_id_status_index` (`user_id`,`status`),
  ADD KEY `leaves_leave_type_id_index` (`leave_type_id`),
  ADD KEY `leaves_start_date_end_date_index` (`start_date`,`end_date`),
  ADD KEY `leaves_company_id_index` (`company_id`);

--
-- Indexes for table `leave_balances`
--
ALTER TABLE `leave_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `leave_balances_user_id_leave_type_id_year_unique` (`user_id`,`leave_type_id`,`year`),
  ADD KEY `leave_balances_company_id_index` (`company_id`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `leave_types_company_id_code_unique` (`company_id`,`code`),
  ADD KEY `leave_types_company_id_index` (`company_id`);

--
-- Indexes for table `loans_advances`
--
ALTER TABLE `loans_advances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loans_advances_user_id_status_index` (`user_id`,`status`),
  ADD KEY `loans_advances_company_id_index` (`company_id`);

--
-- Indexes for table `marketing_settings`
--
ALTER TABLE `marketing_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  ADD KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  ADD KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`);

--
-- Indexes for table `multi_currencies`
--
ALTER TABLE `multi_currencies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

--
-- Indexes for table `notification_email_logs`
--
ALTER TABLE `notification_email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notification_email_logs_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`),
  ADD KEY `notification_email_logs_coach_id_index` (`coach_id`),
  ADD KEY `notification_email_logs_recipient_email_index` (`recipient_email`),
  ADD KEY `notification_email_logs_notification_class_index` (`notification_class`),
  ADD KEY `notification_email_logs_status_index` (`status`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payroll_items`
--
ALTER TABLE `payroll_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payroll_items_payroll_run_id_user_id_unique` (`payroll_run_id`,`user_id`),
  ADD KEY `payroll_items_user_id_index` (`user_id`),
  ADD KEY `payroll_items_company_id_index` (`company_id`);

--
-- Indexes for table `payroll_runs`
--
ALTER TABLE `payroll_runs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payroll_runs_company_id_year_month_unique` (`company_id`,`year`,`month`),
  ADD KEY `payroll_runs_status_index` (`status`),
  ADD KEY `payroll_runs_company_id_index` (`company_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  ADD KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`);

--
-- Indexes for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint`),
  ADD KEY `push_subscriptions_subscribable_type_subscribable_id_index` (`subscribable_type`,`subscribable_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`);

--
-- Indexes for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD PRIMARY KEY (`permission_id`,`role_id`),
  ADD KEY `role_has_permissions_role_id_foreign` (`role_id`);

--
-- Indexes for table `salary_components`
--
ALTER TABLE `salary_components`
  ADD PRIMARY KEY (`id`),
  ADD KEY `salary_components_salary_structure_id_type_index` (`salary_structure_id`,`type`),
  ADD KEY `salary_components_company_id_index` (`company_id`);

--
-- Indexes for table `salary_revisions`
--
ALTER TABLE `salary_revisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `salary_revisions_user_id_index` (`user_id`),
  ADD KEY `salary_revisions_company_id_index` (`company_id`);

--
-- Indexes for table `salary_structures`
--
ALTER TABLE `salary_structures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `salary_structures_user_id_is_current_index` (`user_id`,`is_current`),
  ADD KEY `salary_structures_company_id_index` (`company_id`);

--
-- Indexes for table `seo_settings`
--
ALTER TABLE `seo_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `socialite_credentials`
--
ALTER TABLE `socialite_credentials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `states`
--
ALTER TABLE `states`
  ADD PRIMARY KEY (`id`),
  ADD KEY `states_country_id_foreign` (`country_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_referral_code_unique` (`referral_code`),
  ADD KEY `users_referred_by_user_id_index` (`referred_by_user_id`),
  ADD KEY `users_role_idx` (`role`),
  ADD KEY `users_is_demo_idx` (`is_demo`),
  ADD KEY `users_coach_id_foreign` (`coach_id`),
  ADD KEY `users_parent_coach_id_foreign` (`parent_coach_id`),
  ADD KEY `users_added_by_foreign` (`added_by`),
  ADD KEY `fk_users_country_id` (`country_id`),
  ADD KEY `users_role_status_idx` (`role`,`status`),
  ADD KEY `users_created_at_idx` (`created_at`),
  ADD KEY `users_trial_used_at_index` (`trial_used_at`);

--
-- Indexes for table `user_education`
--
ALTER TABLE `user_education`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_education_user_id` (`user_id`);

--
-- Indexes for table `user_experiences`
--
ALTER TABLE `user_experiences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_user_experiences_user_id` (`user_id`);

--
-- Indexes for table `user_login_devices`
--
ALTER TABLE `user_login_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_login_devices_user_id_fingerprint_unique` (`user_id`,`fingerprint`),
  ADD KEY `user_login_devices_user_id_index` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=115;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `attendances`
--
ALTER TABLE `attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=324;

--
-- AUTO_INCREMENT for table `attendance_regularizations`
--
ALTER TABLE `attendance_regularizations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bonuses`
--
ALTER TABLE `bonuses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `company_user`
--
ALTER TABLE `company_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `custom_codes`
--
ALTER TABLE `custom_codes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_paginations`
--
ALTER TABLE `custom_paginations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `employee_profiles`
--
ALTER TABLE `employee_profiles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1084;

--
-- AUTO_INCREMENT for table `languages`
--
ALTER TABLE `languages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `leaves`
--
ALTER TABLE `leaves`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `leave_balances`
--
ALTER TABLE `leave_balances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `loans_advances`
--
ALTER TABLE `loans_advances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `marketing_settings`
--
ALTER TABLE `marketing_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=350;

--
-- AUTO_INCREMENT for table `multi_currencies`
--
ALTER TABLE `multi_currencies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notification_email_logs`
--
ALTER TABLE `notification_email_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payroll_items`
--
ALTER TABLE `payroll_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `payroll_runs`
--
ALTER TABLE `payroll_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=161;

--
-- AUTO_INCREMENT for table `personal_access_tokens`
--
ALTER TABLE `personal_access_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=259;

--
-- AUTO_INCREMENT for table `push_subscriptions`
--
ALTER TABLE `push_subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `salary_components`
--
ALTER TABLE `salary_components`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `salary_revisions`
--
ALTER TABLE `salary_revisions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_structures`
--
ALTER TABLE `salary_structures`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `seo_settings`
--
ALTER TABLE `seo_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT for table `socialite_credentials`
--
ALTER TABLE `socialite_credentials`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `states`
--
ALTER TABLE `states`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1288;

--
-- AUTO_INCREMENT for table `user_education`
--
ALTER TABLE `user_education`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_experiences`
--
ALTER TABLE `user_experiences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_login_devices`
--
ALTER TABLE `user_login_devices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=174;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cities`
--
ALTER TABLE `cities`
  ADD CONSTRAINT `cities_state_id_foreign` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_permissions`
--
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `model_has_roles`
--
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_has_permissions`
--
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `states`
--
ALTER TABLE `states`
  ADD CONSTRAINT `states_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_country_id` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_coach_id_foreign` FOREIGN KEY (`coach_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_parent_coach_id_foreign` FOREIGN KEY (`parent_coach_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_education`
--
ALTER TABLE `user_education`
  ADD CONSTRAINT `fk_user_education_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_experiences`
--
ALTER TABLE `user_experiences`
  ADD CONSTRAINT `fk_user_experiences_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
