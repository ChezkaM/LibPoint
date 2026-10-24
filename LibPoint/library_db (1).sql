-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 24, 2026 at 09:05 PM
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
-- Database: `library_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `author` varchar(100) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `copies` int(11) DEFAULT 1,
  `fee_per_day` decimal(10,2) DEFAULT 5.00,
  `genre` varchar(100) DEFAULT 'General',
  `dewey` varchar(50) DEFAULT '000',
  `format_tag` varchar(50) DEFAULT 'Hardcover',
  `status` varchar(50) DEFAULT 'Available',
  `shelf_location` varchar(100) DEFAULT 'Unassigned',
  `archived` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `isbn`, `copies`, `fee_per_day`, `genre`, `dewey`, `format_tag`, `status`, `shelf_location`, `archived`) VALUES
(1, 'The Great Gatsby', 'F. Scott Fitzgerald', '978-0743273565', 3, 5.00, 'General', '000', 'Hardcover', 'Available', 'Unassigned', 0),
(2, 'To Kill a Mockingbird', 'Harper Lee', '978-0061935466', 2, 5.00, 'General', '000', 'Hardcover', 'Available', 'Unassigned', 0),
(3, '1984', 'George Orwell', '978-0451524935', 4, 5.00, 'General', '000', 'Hardcover', 'Available', 'Unassigned', 0),
(4, 'Harry Potter and the Sorcerer\'s Stone', 'J.K. Rowling', '978-0590353427', 5, 8.00, 'General', '000', 'Hardcover', 'Available', 'Unassigned', 0),
(5, 'The Hobbit', 'J.R.R. Tolkien', '978-0547928227', 3, 6.00, 'General', '000', 'Hardcover', 'Available', 'Unassigned', 0);

-- --------------------------------------------------------

--
-- Table structure for table `borrowed_books`
--

CREATE TABLE `borrowed_books` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `date_borrowed` date NOT NULL,
  `due_date` date NOT NULL,
  `date_returned` date DEFAULT NULL,
  `fee` decimal(10,2) DEFAULT 0.00,
  `penalty` decimal(10,2) DEFAULT 0.00,
  `date_paid` date DEFAULT NULL,
  `status` enum('borrowed','returned','overdue') DEFAULT 'borrowed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowed_books`
--

INSERT INTO `borrowed_books` (`id`, `user_id`, `book_id`, `date_borrowed`, `due_date`, `date_returned`, `fee`, `penalty`, `date_paid`, `status`) VALUES
(1, 8, 4, '2026-04-28', '2026-05-05', '2026-05-07', 56.00, 20.00, '2026-05-04', 'returned'),
(2, 10, 4, '2026-04-28', '2026-05-05', '2026-04-30', 56.00, 0.00, NULL, 'returned'),
(3, 9, 2, '2026-04-28', '2026-05-04', '2026-05-11', 30.00, 70.00, '2026-05-02', 'returned'),
(4, 9, 3, '2026-04-30', '2026-05-30', '2026-05-30', 150.00, 0.00, NULL, 'returned'),
(5, 9, 1, '2026-04-30', '2026-05-01', '2026-05-02', 5.00, 10.00, '2026-05-02', 'returned'),
(6, 9, 1, '2026-05-04', '2026-05-05', '2026-05-14', 5.00, 90.00, '2026-05-04', 'returned'),
(7, 11, 3, '2026-05-04', '2026-05-06', '2026-05-08', 10.00, 20.00, '2026-05-04', 'returned'),
(8, 8, 1, '2026-05-04', '2026-05-06', '2026-05-09', 10.00, 30.00, '2026-05-05', 'returned'),
(9, 9, 4, '2026-05-06', '2026-05-07', '2026-05-07', 8.00, 0.00, NULL, 'returned'),
(10, 11, 4, '2026-05-07', '2026-05-09', '2026-05-07', 16.00, 0.00, NULL, 'returned'),
(11, 11, 4, '2026-05-07', '2026-05-10', '2026-05-11', 24.00, 10.00, '2026-05-07', 'returned'),
(12, 9, 4, '2026-05-10', '2026-05-11', '2026-05-13', 8.00, 20.00, '2026-05-09', 'returned'),
(13, 11, 4, '2026-09-11', '2026-09-18', '2026-09-11', 56.00, 0.00, NULL, 'returned'),
(14, 13, 2, '2026-09-24', '2026-10-01', '2026-09-24', 35.00, 0.00, NULL, 'returned'),
(15, 13, 2, '2026-09-24', '2026-10-01', '2026-09-26', 35.00, 0.00, NULL, 'returned');

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `pickup_date` date NOT NULL,
  `status` enum('pending','approved','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reserved_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `user_id`, `book_id`, `pickup_date`, `status`, `created_at`, `reserved_at`) VALUES
(1, 13, 4, '2026-09-25', 'pending', '2026-09-24 17:10:17', '2026-09-25 01:46:36');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `address`, `created_at`) VALUES
(7, 'Administrator', 'admin@library.com', '$2y$10$uI8s9mfqNlajawfgP4D0hOVT0wH7iHOJ1ltZMtJZqOSw66DB/a4rS', 'admin', NULL, NULL, '2026-04-28 08:56:34'),
(8, 'Kai', 'kai@library.com', '$2y$10$wY09/nKyfRprjSJbMvIt8unRPP5jRULy6gO.fJ7ERWtrvHPCyorB6', 'user', NULL, NULL, '2026-04-28 08:56:34'),
(9, 'Doson Garcia', 'dos@library.com', '$2y$10$q2uIXSzcIR9zr5oT/zJG2OBH7qlp39hgu6ddo0NDdh3ozNxXZZzOO', 'user', '09154468231', '', '2026-04-28 08:56:34'),
(10, 'Kathleen Buhay', 'kath@library.com', '$2y$10$a7.iicbDQP6llNcT92yBo.e5OkktxHEHCbRtP9LyVq.q2e/vL5Z7O', 'user', '', '', '2026-04-28 08:56:34'),
(11, 'Kareena', 'kt@library.com', '$2y$10$b48hnERCr.s9J307vAOdIuMzKUHowC0d6AOG0YDNBnn70R5MiX4VS', 'user', '09218650097', '', '2026-05-02 10:45:06'),
(12, 'mackenze', 'krnsdonv@library.com', '$2y$10$zKIidbV5ou1dTy61gBR20uN5bBFI.PdmUWSAN/mrrx9Z9.xJ.ubte', 'user', '9034753745', '', '2026-05-04 01:27:13'),
(13, 'Alyssa Manago', 'alyssafranchezka@gmail.com', '$2y$10$EKNNCHdQ8j6fU78blAB0UOCUfDxrV9QI./jnLA6Hmll.7vD5ThEB2', 'user', '09272825190', '42 Zobel parañaque', '2026-09-24 16:24:10');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `borrowed_books`
--
ALTER TABLE `borrowed_books`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

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
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `borrowed_books`
--
ALTER TABLE `borrowed_books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `borrowed_books`
--
ALTER TABLE `borrowed_books`
  ADD CONSTRAINT `borrowed_books_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `borrowed_books_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`);

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
