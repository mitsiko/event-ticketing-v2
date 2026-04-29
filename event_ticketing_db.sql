-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Apr 28, 2026 at 01:43 PM
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
-- Database: `event_ticketing_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendee`
--

CREATE TABLE `attendee` (
  `attendee_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('male','female','non_binary','prefer_not_to_say') NOT NULL,
  `birth_date` date NOT NULL,
  `attendee_type` enum('student','employee','alumni','guest') NOT NULL,
  `registered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `student_id` varchar(20) DEFAULT NULL,
  `program` varchar(100) DEFAULT NULL,
  `year_level` tinyint(4) DEFAULT NULL CHECK (`year_level` between 1 and 5),
  `department` varchar(100) DEFAULT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `alumni_id` varchar(20) DEFAULT NULL,
  `graduation_year` year(4) DEFAULT NULL,
  `guest_id` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attendee`
--

INSERT INTO `attendee` (`attendee_id`, `first_name`, `last_name`, `email`, `phone`, `gender`, `birth_date`, `attendee_type`, `registered_at`, `student_id`, `program`, `year_level`, `department`, `employee_id`, `job_title`, `alumni_id`, `graduation_year`, `guest_id`) VALUES
(1, 'Carlos', 'Mendoza', 'carlos.mendoza@university.edu.ph', '+639171110001', 'male', '2004-03-15', 'student', '2026-04-21 15:04:30', '2021-10001', 'BS Computer Science', 3, 'CCS', NULL, NULL, NULL, NULL, NULL),
(2, 'Liza', 'Quiambao', 'liza.quiambao@university.edu.ph', '+639171110002', 'female', '2003-07-22', 'student', '2026-04-21 15:04:30', '2020-10045', 'BS Information Technology', 4, 'CCS', NULL, NULL, NULL, NULL, NULL),
(3, 'Ramon', 'Villanueva', 'ramon.v@university.edu.ph', '+639171110003', 'male', '2005-01-09', 'student', '2026-04-21 15:04:30', '2022-20031', 'BS Business Administration', 2, 'CBA', NULL, NULL, NULL, NULL, NULL),
(4, 'Patricia', 'Torres', 'patricia.torres@university.edu.ph', '+639171110004', 'female', '1980-11-30', 'employee', '2026-04-21 15:04:30', NULL, NULL, NULL, NULL, 'EMP-0051', 'Department Chair', NULL, NULL, NULL),
(5, 'Miguel', 'Aguilar', 'miguel.aguilar@university.edu.ph', '+639171110005', 'male', '1985-06-14', 'employee', '2026-04-21 15:04:30', NULL, NULL, NULL, NULL, 'EMP-0089', 'Guidance Counselor', NULL, NULL, NULL),
(6, 'Christine', 'Tan', 'christine.tan@gmail.com', '+639171110006', 'female', '1998-09-03', 'alumni', '2026-04-21 15:04:30', NULL, NULL, NULL, NULL, NULL, NULL, 'ALM-0021', '2021', NULL),
(7, 'Andre', 'Bautista', 'andre.bautista@gmail.com', '+639171110007', 'male', '1996-04-17', 'alumni', '2026-04-21 15:04:30', NULL, NULL, NULL, NULL, NULL, NULL, 'ALM-0019', '2019', NULL),
(8, 'Maria', 'Cruz', 'maria.cruz@techcorp.com', '+639171110008', 'female', '1990-12-05', 'guest', '2026-04-21 15:04:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GST-0001'),
(9, 'James', 'Holloway', 'j.holloway@ngointernational.org', '+639171110009', 'prefer_not_to_say', '1988-08-21', 'guest', '2026-04-21 15:04:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'GST-0002'),
(10, 'Roberto', 'Malitao', 'malitaorob@gmail.com', '09496306019', 'male', '1963-01-22', 'employee', '2026-04-21 15:06:58', NULL, NULL, NULL, NULL, '2024-246-818', 'Professor', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `event`
--

CREATE TABLE `event` (
  `event_id` int(11) NOT NULL,
  `event_name` varchar(150) NOT NULL,
  `event_type` enum('academic','cultural','sports','concert','seminar','graduation','orientation','other') NOT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` text DEFAULT NULL,
  `audience_type` enum('student_only','employee_only','alumni_only','open_to_all') NOT NULL DEFAULT 'open_to_all',
  `requires_ticket` tinyint(1) NOT NULL DEFAULT 1,
  `status` enum('upcoming','ongoing','completed','cancelled') NOT NULL DEFAULT 'upcoming',
  `venue_id` int(11) NOT NULL,
  `org_id` int(11) NOT NULL
) ;

--
-- Dumping data for table `event`
--

INSERT INTO `event` (`event_id`, `event_name`, `event_type`, `event_date`, `start_time`, `end_time`, `description`, `audience_type`, `requires_ticket`, `status`, `venue_id`, `org_id`) VALUES
(1, 'University Foundation Day Concert', 'concert', '2026-05-15', '18:00:00', '22:00:00', 'Annual concert celebrating the university founding anniversary. Open to all.', 'open_to_all', 1, 'upcoming', 1, 5),
(2, 'CS Acquaintance Party', 'cultural', '2026-05-08', '15:00:00', '20:00:00', 'Welcome party for new CS students. For students only.', 'student_only', 1, 'upcoming', 5, 2),
(3, 'Alumni Homecoming 2026', 'cultural', '2026-06-20', '09:00:00', '18:00:00', 'Annual alumni reunion event. Alumni welcome.', 'alumni_only', 1, 'upcoming', 2, 3),
(4, 'Leadership & Governance Seminar', 'seminar', '2026-04-28', '08:00:00', '17:00:00', 'Leadership seminar for SSG officers and student leaders.', 'student_only', 1, 'upcoming', 3, 1),
(5, 'Intramural Opening Ceremony', 'sports', '2026-05-05', '07:00:00', '12:00:00', 'Opening ceremony for the annual intramurals. Open to all university community.', 'open_to_all', 0, 'upcoming', 2, 4),
(6, 'Cinema Primera', 'other', '2026-05-30', '08:00:00', '17:00:00', 'Showcasing student\'s love for films', 'open_to_all', 1, 'upcoming', 5, 1);

-- --------------------------------------------------------

--
-- Table structure for table `organization`
--

CREATE TABLE `organization` (
  `org_id` int(11) NOT NULL,
  `org_name` varchar(150) NOT NULL,
  `org_type` enum('student_org','alumni_org','external','university_office') NOT NULL,
  `adviser_first_name` varchar(50) DEFAULT NULL,
  `adviser_last_name` varchar(50) DEFAULT NULL,
  `contact_email` varchar(100) NOT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `is_accredited` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `organization`
--

INSERT INTO `organization` (`org_id`, `org_name`, `org_type`, `adviser_first_name`, `adviser_last_name`, `contact_email`, `contact_phone`, `is_accredited`) VALUES
(1, 'Supreme Student Government', 'student_org', 'Maria', 'Santos', 'ssg@university.edu.ph', '+639171234001', 1),
(2, 'Computer Science Society', 'student_org', 'Juan', 'dela Cruz', 'css@university.edu.ph', '+639171234002', 1),
(3, 'University Alumni Association', 'alumni_org', NULL, NULL, 'alumni@university.edu.ph', '+639171234003', 1),
(4, 'Office of Student Affairs', 'university_office', 'Ana', 'Reyes', 'osa@university.edu.ph', '+639171234004', 1),
(5, 'SoundWave Events (External)', 'external', NULL, NULL, 'booking@soundwaveevents.com', '+639171234005', 0);

-- --------------------------------------------------------

--
-- Table structure for table `ticket`
--

CREATE TABLE `ticket` (
  `ticket_id` int(11) NOT NULL,
  `ticket_code` varchar(50) NOT NULL,
  `purchase_date` datetime NOT NULL DEFAULT current_timestamp(),
  `is_validated` tinyint(1) NOT NULL DEFAULT 0,
  `validated_at` datetime DEFAULT NULL,
  `payment_status` enum('free','paid','pending','refunded') NOT NULL DEFAULT 'free',
  `payment_method` varchar(20) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `attendee_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket`
--

INSERT INTO `ticket` (`ticket_id`, `ticket_code`, `purchase_date`, `is_validated`, `validated_at`, `payment_status`, `payment_method`, `category_id`, `attendee_id`) VALUES
(1, 'TKT-SEED-0001', '2026-04-21 15:04:30', 1, '2026-04-22 16:25:26', 'paid', NULL, 1, 1),
(2, 'TKT-SEED-0002', '2026-04-21 15:04:30', 1, '2026-04-22 16:25:34', 'paid', NULL, 2, 4),
(3, 'TKT-SEED-0003', '2026-04-21 15:04:30', 1, '2026-04-22 16:25:49', 'paid', NULL, 3, 6),
(4, 'TKT-SEED-0004', '2026-04-21 15:04:30', 1, '2026-04-22 16:25:53', 'paid', NULL, 4, 8),
(5, 'TKT-SEED-0005', '2026-04-21 15:04:30', 0, NULL, 'paid', NULL, 5, 2),
(6, 'TKT-SEED-0006', '2026-04-21 15:04:30', 1, '2026-04-28 08:22:53', 'free', NULL, 6, 7),
(7, 'TKT-SEED-0007', '2026-04-21 15:04:30', 1, '2026-04-28 08:26:05', 'free', NULL, 7, 3),
(8, 'a3b9e35d-ebb9-446a-970f-41b24154e4cd', '2026-04-21 15:07:15', 1, '2026-04-22 16:25:20', 'paid', NULL, 2, 10),
(11, '33e2c5fd-ad8a-4893-bbf9-b793b073a4ec', '2026-04-27 22:24:24', 1, '2026-04-28 08:20:54', 'paid', NULL, 5, 1),
(12, 'c8f56f8b-99c3-45f4-bf88-88906a4e814b', '2026-04-28 08:47:57', 1, '2026-04-28 14:55:15', 'paid', NULL, 1, 2),
(13, '8699a068-b44a-4c6f-bb48-b71542af8cd4', '2026-04-28 17:30:17', 0, NULL, 'paid', 'cash', 1, 3),
(14, 'f2ade86e-3f97-410d-8dd6-d98784ad9b35', '2026-04-28 17:31:34', 0, NULL, 'paid', 'online', 4, 9),
(16, 'be1a7f82-ce66-4c9c-b292-8d58721865aa', '2026-04-28 17:38:52', 0, NULL, 'pending', NULL, 3, 7);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_category`
--

CREATE TABLE `ticket_category` (
  `category_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `category_name` varchar(80) NOT NULL,
  `eligible_type` enum('student','employee','alumni','guest','all') NOT NULL DEFAULT 'all',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 CHECK (`price` >= 0),
  `total_slots` int(11) NOT NULL CHECK (`total_slots` > 0),
  `slots_remaining` int(11) NOT NULL
) ;

--
-- Dumping data for table `ticket_category`
--

INSERT INTO `ticket_category` (`category_id`, `event_id`, `category_name`, `eligible_type`, `price`, `total_slots`, `slots_remaining`) VALUES
(1, 1, 'Student Ticket', 'student', 50.00, 1500, 1497),
(2, 1, 'Employee Ticket', 'employee', 50.00, 300, 298),
(3, 1, 'Alumni Ticket', 'alumni', 80.00, 500, 498),
(4, 1, 'Guest Ticket', 'guest', 150.00, 500, 498),
(5, 2, 'CS Student', 'student', 80.00, 400, 398),
(6, 3, 'Alumni Pass', 'alumni', 0.00, 1000, 999),
(7, 4, 'Student Leader', 'student', 0.00, 100, 99);

-- --------------------------------------------------------

--
-- Table structure for table `venue`
--

CREATE TABLE `venue` (
  `venue_id` int(11) NOT NULL,
  `venue_name` varchar(100) NOT NULL,
  `venue_type` enum('gymnasium','auditorium','classroom','field','courtyard','amphitheater','other') NOT NULL,
  `building` varchar(100) DEFAULT NULL,
  `floor_level` varchar(20) DEFAULT NULL,
  `capacity` int(11) NOT NULL CHECK (`capacity` > 0),
  `has_av_system` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `venue`
--

INSERT INTO `venue` (`venue_id`, `venue_name`, `venue_type`, `building`, `floor_level`, `capacity`, `has_av_system`) VALUES
(1, 'University Gymnasium', 'gymnasium', 'Main Campus', 'Ground Floor', 3000, 1),
(2, 'University Quadrangle', 'field', 'Main Campus', 'Outdoor', 5000, 0),
(3, 'Rizal Hall Auditorium', 'auditorium', 'Rizal Hall', '2nd Floor', 800, 1),
(4, 'College of Engineering Room', 'classroom', 'Engineering Building', '3rd Floor', 120, 1),
(5, 'College Amphitheater', 'amphitheater', 'Arts & Sciences Bldg', 'Ground Floor', 600, 1);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_event_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_event_summary` (
`event_id` int(11)
,`event_name` varchar(150)
,`event_type` enum('academic','cultural','sports','concert','seminar','graduation','orientation','other')
,`event_date` date
,`audience_type` enum('student_only','employee_only','alumni_only','open_to_all')
,`status` enum('upcoming','ongoing','completed','cancelled')
,`venue_name` varchar(100)
,`venue_type` enum('gymnasium','auditorium','classroom','field','courtyard','amphitheater','other')
,`venue_capacity` int(11)
,`org_name` varchar(150)
,`org_type` enum('student_org','alumni_org','external','university_office')
,`total_ticket_slots` decimal(32,0)
,`slots_remaining` decimal(32,0)
,`tickets_sold` decimal(33,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_ticket_details`
-- (See below for the actual view)
--
CREATE TABLE `vw_ticket_details` (
`ticket_id` int(11)
,`ticket_code` varchar(50)
,`purchase_date` datetime
,`is_validated` tinyint(1)
,`validated_at` datetime
,`payment_status` enum('free','paid','pending','refunded')
,`attendee_name` varchar(101)
,`attendee_email` varchar(100)
,`attendee_type` enum('student','employee','alumni','guest')
,`student_id` varchar(20)
,`employee_id` varchar(20)
,`alumni_id` varchar(20)
,`guest_id` varchar(20)
,`category_name` varchar(80)
,`eligible_type` enum('student','employee','alumni','guest','all')
,`price` decimal(10,2)
,`event_name` varchar(150)
,`event_date` date
,`start_time` time
,`audience_type` enum('student_only','employee_only','alumni_only','open_to_all')
,`venue_name` varchar(100)
,`building` varchar(100)
,`org_name` varchar(150)
);

-- --------------------------------------------------------

--
-- Structure for view `vw_event_summary`
--
DROP TABLE IF EXISTS `vw_event_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_event_summary`  AS SELECT `e`.`event_id` AS `event_id`, `e`.`event_name` AS `event_name`, `e`.`event_type` AS `event_type`, `e`.`event_date` AS `event_date`, `e`.`audience_type` AS `audience_type`, `e`.`status` AS `status`, `v`.`venue_name` AS `venue_name`, `v`.`venue_type` AS `venue_type`, `v`.`capacity` AS `venue_capacity`, `o`.`org_name` AS `org_name`, `o`.`org_type` AS `org_type`, sum(`tc`.`total_slots`) AS `total_ticket_slots`, sum(`tc`.`slots_remaining`) AS `slots_remaining`, sum(`tc`.`total_slots` - `tc`.`slots_remaining`) AS `tickets_sold` FROM (((`event` `e` join `venue` `v` on(`e`.`venue_id` = `v`.`venue_id`)) join `organization` `o` on(`e`.`org_id` = `o`.`org_id`)) join `ticket_category` `tc` on(`e`.`event_id` = `tc`.`event_id`)) GROUP BY `e`.`event_id`, `e`.`event_name`, `e`.`event_type`, `e`.`event_date`, `e`.`audience_type`, `e`.`status`, `v`.`venue_name`, `v`.`venue_type`, `v`.`capacity`, `o`.`org_name`, `o`.`org_type` ;

-- --------------------------------------------------------

--
-- Structure for view `vw_ticket_details`
--
DROP TABLE IF EXISTS `vw_ticket_details`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_ticket_details`  AS SELECT `t`.`ticket_id` AS `ticket_id`, `t`.`ticket_code` AS `ticket_code`, `t`.`purchase_date` AS `purchase_date`, `t`.`is_validated` AS `is_validated`, `t`.`validated_at` AS `validated_at`, `t`.`payment_status` AS `payment_status`, concat(`a`.`first_name`,' ',`a`.`last_name`) AS `attendee_name`, `a`.`email` AS `attendee_email`, `a`.`attendee_type` AS `attendee_type`, `a`.`student_id` AS `student_id`, `a`.`employee_id` AS `employee_id`, `a`.`alumni_id` AS `alumni_id`, `a`.`guest_id` AS `guest_id`, `tc`.`category_name` AS `category_name`, `tc`.`eligible_type` AS `eligible_type`, `tc`.`price` AS `price`, `e`.`event_name` AS `event_name`, `e`.`event_date` AS `event_date`, `e`.`start_time` AS `start_time`, `e`.`audience_type` AS `audience_type`, `v`.`venue_name` AS `venue_name`, `v`.`building` AS `building`, `o`.`org_name` AS `org_name` FROM (((((`ticket` `t` join `attendee` `a` on(`t`.`attendee_id` = `a`.`attendee_id`)) join `ticket_category` `tc` on(`t`.`category_id` = `tc`.`category_id`)) join `event` `e` on(`tc`.`event_id` = `e`.`event_id`)) join `venue` `v` on(`e`.`venue_id` = `v`.`venue_id`)) join `organization` `o` on(`e`.`org_id` = `o`.`org_id`)) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendee`
--
ALTER TABLE `attendee`
  ADD PRIMARY KEY (`attendee_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `student_id` (`student_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`),
  ADD UNIQUE KEY `alumni_id` (`alumni_id`),
  ADD UNIQUE KEY `guest_id` (`guest_id`);

--
-- Indexes for table `event`
--
ALTER TABLE `event`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `fk_event_venue` (`venue_id`),
  ADD KEY `fk_event_org` (`org_id`);

--
-- Indexes for table `organization`
--
ALTER TABLE `organization`
  ADD PRIMARY KEY (`org_id`),
  ADD UNIQUE KEY `contact_email` (`contact_email`);

--
-- Indexes for table `ticket`
--
ALTER TABLE `ticket`
  ADD PRIMARY KEY (`ticket_id`),
  ADD UNIQUE KEY `ticket_code` (`ticket_code`),
  ADD UNIQUE KEY `uq_one_ticket_per_event` (`category_id`,`attendee_id`),
  ADD KEY `fk_ticket_attendee` (`attendee_id`);

--
-- Indexes for table `ticket_category`
--
ALTER TABLE `ticket_category`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `fk_tc_event` (`event_id`);

--
-- Indexes for table `venue`
--
ALTER TABLE `venue`
  ADD PRIMARY KEY (`venue_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendee`
--
ALTER TABLE `attendee`
  MODIFY `attendee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `event`
--
ALTER TABLE `event`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `organization`
--
ALTER TABLE `organization`
  MODIFY `org_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `ticket`
--
ALTER TABLE `ticket`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `ticket_category`
--
ALTER TABLE `ticket_category`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `venue`
--
ALTER TABLE `venue`
  MODIFY `venue_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `event`
--
ALTER TABLE `event`
  ADD CONSTRAINT `fk_event_org` FOREIGN KEY (`org_id`) REFERENCES `organization` (`org_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_event_venue` FOREIGN KEY (`venue_id`) REFERENCES `venue` (`venue_id`) ON UPDATE CASCADE;

--
-- Constraints for table `ticket`
--
ALTER TABLE `ticket`
  ADD CONSTRAINT `fk_ticket_attendee` FOREIGN KEY (`attendee_id`) REFERENCES `attendee` (`attendee_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ticket_category` FOREIGN KEY (`category_id`) REFERENCES `ticket_category` (`category_id`) ON UPDATE CASCADE;

--
-- Constraints for table `ticket_category`
--
ALTER TABLE `ticket_category`
  ADD CONSTRAINT `fk_tc_event` FOREIGN KEY (`event_id`) REFERENCES `event` (`event_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
