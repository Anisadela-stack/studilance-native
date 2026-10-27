-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 23, 2026 at 06:44 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `studilance_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `id` int NOT NULL,
  `job_id` int NOT NULL,
  `student_id` int NOT NULL,
  `proposal` text NOT NULL,
  `status` enum('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `decided_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`id`, `job_id`, `student_id`, `proposal`, `status`, `created_at`, `decided_at`) VALUES
(2, 3, 1, 'saya tertarik', 'accepted', '2026-10-20 12:52:59', NULL),
(3, 4, 1, 'saya ingin mengambil freelance ini', 'rejected', '2026-10-20 13:00:13', NULL),
(4, 5, 1, 'saya tertarik membuat poster', 'accepted', '2026-10-22 09:02:27', '2026-10-22 16:03:35'),
(5, 6, 1, 'saya mau', 'rejected', '2026-10-22 09:13:34', '2026-10-22 16:13:57');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `title` varchar(150) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `budget` decimal(12,2) NOT NULL DEFAULT '0.00',
  `deadline` date DEFAULT NULL,
  `status` enum('open','in_progress','completed','closed') NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`id`, `client_id`, `title`, `category`, `description`, `budget`, `deadline`, `status`, `created_at`) VALUES
(3, 2, 'buat vid beng beng', 'Editing Video', 'pro menggunakan capcut', 300000.00, '2026-10-22', 'open', '2026-10-20 07:38:29'),
(4, 2, 'html,css,java', 'Pembuatan Website', 'membuat web hotel', 2000000.00, '2026-10-29', 'open', '2026-10-20 12:59:18'),
(5, 2, 'poster hari santri', 'Lainnya', 'dibutuhkan segera', 200000.00, '2026-10-24', 'closed', '2026-10-22 09:01:42'),
(6, 2, 'vidio iklan santri', 'Editing Video', 'butuh cepatt', 1000000.00, '2026-10-26', 'open', '2026-10-22 09:08:53'),
(7, 2, 'membuat website organisasi', 'Pembuatan Website', 'website organisasi dengan elegan', 500000.00, '2026-11-04', 'open', '2026-10-23 06:34:49');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','client') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `bio` text,
  `skills` text,
  `portfolio_url` varchar(255) DEFAULT NULL,
  `experience` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `bio`, `skills`, `portfolio_url`, `experience`) VALUES
(1, 'ANISA DELA APRIANI', 'uracaaaee@gmail.com', '$2y$10$lY3sR5jirwahjk3y0c299OVgEWBvWgjC0cmRN4aJFjy2H5d3hYL/y', 'student', '2026-10-20 05:55:29', 'saya seorang mahasiswa di horizon university indonesia, saya tertarik di bidang ui ux design', 'ui ux design', '', 'membuat poster organisasi'),
(2, 'cae', 'uracaae29@gmail.com', '$2y$10$s.jUqU8NjZGQyBC2KkJ.ZO.q8b5Cajzl2eHWMMwHZLyJcLNjE7cuO', 'client', '2026-10-20 06:14:43', NULL, NULL, NULL, NULL),
(3, 'kael', 'mariaaiza99372@gmail.com', '$2y$10$zKlhYM3Q8MC1.yJWstNv5uOApDex8gez.Iv2nNZ26rh60n0NkMWRK', 'client', '2026-10-20 06:26:27', NULL, NULL, NULL, NULL),
(4, 'cay', 'xg4kv25eo17@gmail.com', '$2y$10$79HMKXT873i7x115MunvOObSU4im7yNMe.RWVe5omspCU9xUkren.', 'student', '2026-10-23 06:15:22', NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_application` (`job_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
