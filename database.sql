-- phpMyAdmin SQL Dump
-- version 4.6.6deb5
-- https://www.phpmyadmin.net/
--
-- Host: 10.0.0.6:3306
-- Generation Time: Jan 22, 2019 at 08:53 AM
-- Server version: 5.7.24-0ubuntu0.18.04.1-log
-- PHP Version: 7.2.11-3+ubuntu18.04.1+deb.sury.org+1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `apretaste`
--

-- --------------------------------------------------------

--
-- Table structure for table `_pizarra_actions`
--

CREATE TABLE `_pizarra_actions` (
  `id` int(11) NOT NULL,
  `id_person` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `note` int(11) NOT NULL,
  `action` enum('like','unlike') DEFAULT 'like',
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_pizarra_comments`
--

CREATE TABLE `_pizarra_comments` (
  `id` int(11) NOT NULL,
  `id_person` int(11) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `note` int(11) NOT NULL,
  `text` varchar(255) NOT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_pizarra_denounce`
--

CREATE TABLE `_pizarra_denounce` (
  `id` int(11) NOT NULL,
  `email` char(100) NOT NULL,
  `denouncer` char(100) NOT NULL,
  `reason` varchar(20) NOT NULL,
  `text` varchar(500) NOT NULL,
  `review` tinyint(1) NOT NULL DEFAULT '0',
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_pizarra_notes`
--

CREATE TABLE `_pizarra_notes` (
  `id` int(11) NOT NULL,
  `id_person` int(11) NOT NULL,
  `email` char(100) DEFAULT NULL,
  `text` varchar(300) NOT NULL,
  `likes` int(5) NOT NULL DEFAULT '0',
  `unlikes` int(5) NOT NULL DEFAULT '0',
  `comments` int(5) NOT NULL DEFAULT '0',
  `views` int(7) NOT NULL DEFAULT '0',
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ad` tinyint(1) NOT NULL DEFAULT '0',
  `topic1` varchar(20) DEFAULT NULL,
  `topic2` varchar(20) DEFAULT NULL,
  `topic3` varchar(20) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_pizarra_topics`
--

CREATE TABLE `_pizarra_topics` (
  `id` int(11) NOT NULL,
  `id_person` int(11) NOT NULL,
  `topic` varchar(20) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note` int(11) NOT NULL,
  `person` char(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_pizarra_users`
--

CREATE TABLE `_pizarra_users` (
  `id_person` int(11) NOT NULL,
  `email` char(100) DEFAULT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `default_topic` varchar(30) DEFAULT 'general',
  `reputation` int(6) NOT NULL DEFAULT '100'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `_pizarra_actions`
--
ALTER TABLE `_pizarra_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `note` (`note`),
  ADD KEY `id_person` (`id_person`);

--
-- Indexes for table `_pizarra_comments`
--
ALTER TABLE `_pizarra_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `note` (`note`),
  ADD KEY `email` (`email`),
  ADD KEY `id_person` (`id_person`);

--
-- Indexes for table `_pizarra_denounce`
--
ALTER TABLE `_pizarra_denounce`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `_pizarra_notes`
--
ALTER TABLE `_pizarra_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `id_person` (`id_person`);

--
-- Indexes for table `_pizarra_topics`
--
ALTER TABLE `_pizarra_topics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `topic` (`topic`);

--
-- Indexes for table `_pizarra_users`
--
ALTER TABLE `_pizarra_users`
  ADD PRIMARY KEY (`id_person`),
  ADD UNIQUE KEY `id_person_2` (`id_person`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `_pizarra_actions`
--
ALTER TABLE `_pizarra_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=373578;
--
-- AUTO_INCREMENT for table `_pizarra_comments`
--
ALTER TABLE `_pizarra_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=216616;
--
-- AUTO_INCREMENT for table `_pizarra_denounce`
--
ALTER TABLE `_pizarra_denounce`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=419;
--
-- AUTO_INCREMENT for table `_pizarra_notes`
--
ALTER TABLE `_pizarra_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111727;
--
-- AUTO_INCREMENT for table `_pizarra_topics`
--
ALTER TABLE `_pizarra_topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=316024;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
