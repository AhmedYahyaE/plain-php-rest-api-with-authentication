-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jul 07, 2023 at 10:01 PM
-- Server version: 8.0.28
-- PHP Version: 8.1.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tasksdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `tblimages`
--

CREATE TABLE `tblimages` (
  `id` bigint NOT NULL COMMENT 'Image ID Number - Primary Key',
  `title` varchar(255) NOT NULL COMMENT 'Image Title',
  `filename` varchar(30) NOT NULL COMMENT 'Image Filename',
  `mimetype` varchar(255) NOT NULL COMMENT 'Image Mime Type - e.g. image/png',
  `taskid` bigint NOT NULL COMMENT 'Task ID Number - Foreign Key'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Table to store task images';

--
-- Dumping data for table `tblimages`
--

INSERT INTO `tblimages` (`id`, `title`, `filename`, `mimetype`, `taskid`) VALUES
(9, 'test updating the image title', 'updated_filename.jpg', 'image/jpeg', 1),
(10, 'test image title', 'cat.jpg', 'image/jpeg', 1),
(14, 'Sweeping the floor', 'sweeping.jpg', 'image/jpeg', 8),
(15, 'Do the homework UPDATED', 'homework-UPDATED.jpg', 'image/jpeg', 8),
(16, 'Study some topics', 'study.jpg', 'image/jpeg', 7);

-- --------------------------------------------------------

--
-- Table structure for table `tblsessions`
--

CREATE TABLE `tblsessions` (
  `id` bigint NOT NULL COMMENT 'Session ID',
  `userid` bigint NOT NULL COMMENT 'User ID (foreign key to `id` column in `users` table)',
  `accesstoken` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Access Token',
  `accesstokenexpiry` datetime NOT NULL COMMENT 'Access Token Expiry Date/Time',
  `refreshtoken` varchar(100) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Refresh Token',
  `refreshtokenexpiry` datetime NOT NULL COMMENT 'Refresh Token Expiry Date/Time'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Sessions Table';

--
-- Dumping data for table `tblsessions`
--

INSERT INTO `tblsessions` (`id`, `userid`, `accesstoken`, `accesstokenexpiry`, `refreshtoken`, `refreshtokenexpiry`) VALUES
(13, 4, 'M2FhZjVhZGM0NGNiMjE0YmYxMTYzZjhkMDcwMTA3ZDI3MzFiZDYxMGUwMmFhZGNmMTY1NzU1MjI2NA==', '2022-07-11 17:31:04', 'YWMzZWFlMGUxZTdjNzZlNDIxMGYyY2FiMWZhMzhiN2YwY2IzZGZiYTExYmQ5YjRhMTY1NzU1MjI2NA==', '2022-07-25 17:11:04'),
(14, 4, 'MTZiYTVkZDhiNmE3Zjc4ZDUwZGIxY2IxYmEzY2NlMDA1ODBiZmRkYzk0OTA2OGZmMTY1NzU2Mjk3OA==', '2022-07-11 20:29:38', 'NzkzNDliODYzMWNkYzM2ZjgxNzk3YTVlMDI2ZGRmZWIwNjNmYzMwYTY5ZDVkOWM1MTY1NzU2Mjk3OA==', '2022-07-25 20:09:38'),
(15, 5, 'YTgwYTQ3NGNjYzg2MjU3MmQwODMwYWRkNjUxOGI1NjgzZGQwZjA1OTlhYTEzYzc4MTY1NzU2MTY4Nw==', '2022-07-11 20:08:07', 'ZmU2OGZhYzcxNWU2Mjc2Mzk2YTE1YmZkNmZjMDRhN2QwNDliYmU2OTIwNmViN2Q1MTY1NzU2MTY4Nw==', '2022-07-25 19:48:07'),
(16, 4, 'ZGU3ZWJiZDQyZDBiMjc0ODdmZjRlZTc3NDQzMDJmOWQ5YjAzMDM2YWYxMjdjNzFhMTY1Nzk3NjE0Mg==', '2022-07-16 15:15:42', 'YzM5ZTA5Y2I5MjkzYjVlMmU4MDBiNGQ1ZDU1OTg0Yzc0OGY2Y2ViNDI0ZDYxZTk3MTY1Nzk3NjE0Mg==', '2022-07-30 14:55:42'),
(17, 4, 'MjkxMTBiNTkzZTMwYWNhOGI0OTVhMmQ1MjkyM2Q2ZTJhZmEyNjlkNGY1YzMwMGU2MTY1Nzk5NjQ1Mw==', '2022-07-16 20:54:13', 'YjViNGJmMzUwZjM2ZTE1NjE1YWE3MTA0YTYyZjcwZTU2ZWVmMDRlZmI4NjdlYWI1MTY1Nzk5NjQ1Mw==', '2022-07-30 20:34:13'),
(18, 4, 'OTVjNjI1Mzg3ZjExNzg0OTUyNGZiZTM4MzAxYWM2YzdhNzc4YzhlNjY2ZTBmZjljMTY1Nzk5NjY4NA==', '2022-07-16 20:58:04', 'YmRhZmY3NDg5MjBhN2Y3Y2M1MTljZmM0YWI4NDg1NWQ3ZTRiMjk3NWJmMjMxNGFiMTY1Nzk5NjY4NA==', '2022-07-30 20:38:04'),
(19, 4, 'ZjdhMTNkMTE1YjIxMDI4NGM3MDBjYWI1ZjIwMmUxZGFmYjEyOTk0ODY4NzU0Yzc1MTY1Nzk5Njg0Nw==', '2022-07-16 21:00:47', 'ZGRjNWQ4YzQ5ZDNkYWRkNzVhZjgyMTdiYjk2MjhmYjE3NDMzN2Y0NTkyMGVjNjM5MTY1Nzk5Njg0Nw==', '2022-07-30 20:40:47'),
(20, 4, 'MzI1YzE1NDg4ODNhNTRmM2NkNTRlMmVjMjY2NWNkMTdlYjc3ZGI4ODExNmU5M2QxMTY1ODA5ODY4OQ==', '2022-07-18 01:18:09', 'ZmExNjY2Yzg2YTcwNTE4YzM4YjdkOTZkYTgzMzgwNTY4MTI0MDJhMzgzZGY5YjBhMTY1ODA5ODY4OQ==', '2022-08-01 00:58:09'),
(21, 4, 'ZmQzMDk2MTBmNDAyYWU5YjE0NzY4MGJlZjJkN2Y2NDgzNTZmNzhiMTg0ZGY3N2JmMTY1ODEwMzg3NA==', '2022-07-18 02:44:34', 'YTY5NWYyYmY5ZWZiYWI5NWM5MTIxMDEzZWU2MzcwYjUxYjkwN2IzNTFmMDg3YzA5MTY1ODEwMzg3NA==', '2022-08-01 02:24:34'),
(22, 4, 'ZjRkOGI0NmI1M2IzZDk1ZjU4MDI0YTc5Yzc0NmIxNmEyMTMyNzUxOTljN2QzZGZhMTY1ODEwMzkyNg==', '2022-07-18 01:45:26', 'NjI2ZjQ3ODRjYmU0ZDVjMDE4MzVkNWY4ZWFhMWJjYzZiYmM4YWJmNTFkNDVhMTMwMTY1ODEwMzkyNg==', '2022-08-01 02:25:26'),
(23, 4, 'NGQxMzlkZTZlYmM3NGM1NDU2NDA1ZGQyNjBkZjBmMDQxZmNkMzVmOWE1MjdlZjcyMTY1ODE4OTM1MA==', '2022-07-19 02:29:10', 'YzIwMDhmM2U2OTQ4ODJjNTVhYTIzZmY3NDMyMTczYTZkZjFlNGQyNzQxMmQ1Yzc3MTY1ODE4OTM1MA==', '2022-08-02 02:09:10'),
(24, 4, 'Zjk3NTJmZTc4ZWQ4YmZmZDI1MDc1OWNkMDNmYjNiMzFhNzNlOTY5OWIwNmY4Yzk5MTY1ODM0OTM3OA==', '2022-07-20 22:56:19', 'NTE4YWZiZDkwMDY3N2Q2MWFlYWNmNzRlOGRjZWU5NDczMjNmYmViNGJmYzI0ZTRiMTY1ODM0OTM3OA==', '2022-08-03 22:36:19'),
(25, 4, 'N2Y5ZDRiM2UzMmRmY2UzMjFhZTU3YThkZjYzMzdhNDk3ZDYyNGIyOTljMmJjM2UxMTY1ODUzNjY3MQ==', '2022-07-23 02:57:51', 'YWRiOGQwMjk5ZTIyYTFiMWYyYTBjMTA4OWY3NGVlOGZmN2I4MTMwNjVhOTg4NzczMTY1ODUzNjY3MQ==', '2022-08-06 02:37:51'),
(26, 4, 'OTgyNDczOWNjYzE0ZjljNjUzYjQxMjIyMGRmNWIwY2E0NjJjMzE4NDdjYzVhMzY4MTY1ODU0NzkyMw==', '2022-07-23 06:05:23', 'NzVmMzAxMjM2N2YyNTkzYjQzOTJlNGExYzY3NmJhOTczOGVmZmEyNjc1NjE5MTVjMTY1ODU0NzkyMw==', '2022-08-06 05:45:23'),
(27, 4, 'ODI5NWZmNjJhY2JjOWJiYTk1MGNjOTM2ZjRlMjA0YWU2Zjk1NGNiYTg2N2IzOTU2MTY1ODU1NTI4OA==', '2022-07-23 08:08:08', 'OGIyMmZmZDJlNDljNDhjOTIxYWVmZGU2YWIzNWYwYTdhYTgxZmMwNWE1MWRjYzk3MTY1ODU1NTI4OA==', '2022-08-06 07:48:08'),
(28, 4, 'MTFiYmE3NjUwZGFkMjdhMjlmNmYwZmU3MTM4NTk2OGQ2NWI3NzhhODBmNzQ2OWUyMTY1ODU2NDE1MQ==', '2022-07-23 10:35:51', 'ZDQ2N2IyYzI2M2YzN2U2NTBiMmIxM2EyN2ZkZTUyZTM0MWRiM2U0ZGFmNDM4N2FmMTY1ODU2NDE1MQ==', '2022-08-06 10:15:51'),
(29, 4, 'MjYwYTRjOGNmNDQzZDI3MjkwZDAwMTIzNGY3ZmMzMjhkNzU1MmVkMTcwZmEwODk4MTY4ODQyNzkxOQ==', '2023-07-04 03:05:19', 'YzYwZjNlN2I2ZDRlNWY1MThkNjZjZTJkMmM5YjUzOWVlZTJhOTdhZjRmYjY1NDRjMTY4ODQyNzkxOQ==', '2023-07-18 02:45:19'),
(30, 4, 'YWRjOWNiNWM2NTk3YzI5NzFkODc3MGJmMmM4ZDAwNWYyZTBiNTZmNjE1Y2UzMmZmMTY4ODQyODQ2NA==', '2023-07-04 03:14:24', 'YzBhMWFiYmUwZGIyZmZkMjQ3NTRiYWQyMjMzNmU2NWY5NzUyNjgxYWYwZTUyOGQ5MTY4ODQyODQ2NA==', '2023-07-18 02:54:24'),
(31, 6, 'Y2Q4NTAzZjFkZWZhOGUyZThiOTJlNGEwMGJmOWJlYzgxNDI4YzI2MjlkNDc4NmMxMTY4ODQzMDMxMA==', '2023-07-04 03:45:10', 'NDk1YjM1YTYxM2Q0OWRjYTVmODk5NThhOTg5ZDk2NjRjNjgzNGM3N2Y1YmNhNGVmMTY4ODQzMDMxMA==', '2023-07-18 03:25:10'),
(32, 6, 'NWI2MWNmZjc2YTgyOTkyZjM4NjQ3OTkwZmM4ODRlZTk5N2IxZmI4MmNkNTgxMWRhMTY4ODQ3MDU3MA==', '2023-07-04 14:56:10', 'Y2ViMzRmNGJiMTY5NzI4YTliNjIwZmM0NTM3NWI5OWUwY2EyMzNkNjMwMmEzYzUxMTY4ODQ3MDU3MA==', '2023-07-18 14:36:10'),
(35, 6, 'ZThlZGFhZmM3ZWQ0MDQzZmE1ZTE5YzBmMDJiZGUxNjUxMjIxNzQ0N2I1YTU3OTM3MTY4ODc1ODk4OQ==', '2023-07-07 23:03:09', 'ODlkNTE2ZmU4NzMyODE5Nzc1MjE0ZDkxMDliNzdjOTJkMzlhYzQwYjkyNzM5MTI0MTY4ODc1ODk4OQ==', '2023-07-21 22:43:09');

-- --------------------------------------------------------

--
-- Table structure for table `tbltasks`
--

CREATE TABLE `tbltasks` (
  `id` bigint NOT NULL COMMENT 'Task ID - Primary Key',
  `title` varchar(255) NOT NULL COMMENT 'Task Title',
  `description` mediumtext COMMENT 'Task Description',
  `deadline` datetime DEFAULT NULL COMMENT 'Task Deadline Date',
  `completed` enum('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Task Completion STATUS',
  `userid` bigint NOT NULL COMMENT 'User ID of owner of task'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Tasks table';

--
-- Dumping data for table `tbltasks`
--

INSERT INTO `tbltasks` (`id`, `title`, `description`, `deadline`, `completed`, `userid`) VALUES
(1, 'New Task 1 Title updated', NULL, NULL, 'N', 4),
(2, 'Michael\'s task to cut the lawn', NULL, NULL, 'N', 4),
(3, 'John\'s task to paint the fence', NULL, NULL, 'N', 5),
(6, 'test title', NULL, NULL, 'Y', 6),
(7, 'A task title example', NULL, NULL, 'N', 6),
(8, 'A task title example', 'New Task 8 Description updated', NULL, 'Y', 6);

-- --------------------------------------------------------

--
-- Table structure for table `tblusers`
--

CREATE TABLE `tblusers` (
  `id` bigint NOT NULL COMMENT 'User ID',
  `fullname` varchar(255) NOT NULL COMMENT 'Users Full Name',
  `username` varchar(255) NOT NULL COMMENT 'Users Username',
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL COMMENT 'Users Password',
  `useractive` enum('Y','N') NOT NULL DEFAULT 'Y' COMMENT 'Is User Active',
  `loginattempts` int NOT NULL DEFAULT '0' COMMENT 'Attempts to login'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Users Table';

--
-- Dumping data for table `tblusers`
--

INSERT INTO `tblusers` (`id`, `fullname`, `username`, `password`, `useractive`, `loginattempts`) VALUES
(4, 'Michael Jones', 'michael', '$2y$10$GAqE6GuAJECSlSZB/Y7Y0uBVnxtIoleZky0uiJ5UtaksHOKXDegCC', 'Y', 0),
(5, 'John Smith', 'john', '$2y$10$LenFpsHEdlX3BvYVM3aVkO7zzirRZgTagwPs0WEhGwDXjCZtQzspm', 'Y', 0),
(6, 'Ahmed Yahya', 'Ahmed', '$2y$10$7tB2RomsZ7CnzZAEbRkdd.EDVKnL9edOrisIlY90MMvTyDO0LRlSe', 'Y', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tblimages`
--
ALTER TABLE `tblimages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `filenamefortaskid` (`taskid`,`filename`);

--
-- Indexes for table `tblsessions`
--
ALTER TABLE `tblsessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `accesstoken` (`accesstoken`),
  ADD UNIQUE KEY `refreshtoken` (`refreshtoken`),
  ADD KEY `sessionuserid_fk` (`userid`);

--
-- Indexes for table `tbltasks`
--
ALTER TABLE `tbltasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `taskuserid_fk` (`userid`);

--
-- Indexes for table `tblusers`
--
ALTER TABLE `tblusers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tblimages`
--
ALTER TABLE `tblimages`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'Image ID Number - Primary Key', AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `tblsessions`
--
ALTER TABLE `tblsessions`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'Session ID', AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `tbltasks`
--
ALTER TABLE `tbltasks`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'Task ID - Primary Key', AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tblusers`
--
ALTER TABLE `tblusers`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT COMMENT 'User ID', AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tblimages`
--
ALTER TABLE `tblimages`
  ADD CONSTRAINT `imagetaskid_fk` FOREIGN KEY (`taskid`) REFERENCES `tbltasks` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `tblsessions`
--
ALTER TABLE `tblsessions`
  ADD CONSTRAINT `sessionuserid_fk` FOREIGN KEY (`userid`) REFERENCES `tblusers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;

--
-- Constraints for table `tbltasks`
--
ALTER TABLE `tbltasks`
  ADD CONSTRAINT `taskuserid_fk` FOREIGN KEY (`userid`) REFERENCES `tblusers` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
