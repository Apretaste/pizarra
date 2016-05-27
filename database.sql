-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Apr 26, 2016 at 04:30 PM
-- Server version: 5.5.49-0ubuntu0.14.04.1
-- PHP Version: 5.5.9-1ubuntu4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


-- --------------------------------------------------------

--
-- Table structure for table `_pizarra_block`
--

CREATE TABLE IF NOT EXISTS `_pizarra_block` (
  `email` char(100) NOT NULL,
  `blocked` char(100) NOT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`email`,`blocked`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_pizarra_follow`
--

<<<<<<< HEAD
CREATE TABLE IF NOT EXISTS `_pizarra_users` (
  `email` varchar(50) NOT NULL,
  `reports` int(3) DEFAULT '0' COMMENT 'times the user had been reported',
  `penalized_until` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'If the user had been reported X times, will be penalized til this date'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


CREATE TABLE IF NOT EXISTS _pizarra_locks (
  `email` varchar(50) NOT NULL,
  `user_locked` varchar(50) NOT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (email, user_locked)
);

CREATE TABLE IF NOT EXISTS _pizarra_follow (
  `email` varchar(50) NOT NULL,
  `followed` varchar(50) NOT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (email, followed)
);


--
-- Indexes for dumped tables
--
=======
CREATE TABLE IF NOT EXISTS `_pizarra_follow` (
  `email` char(100) NOT NULL,
  `followed` char(100) NOT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`email`,`followed`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
>>>>>>> 86c7ba06e3afae18f83896ddde6afe74cf78c0b3

-- --------------------------------------------------------

--
-- Table structure for table `_pizarra_notes`
--

CREATE TABLE IF NOT EXISTS `_pizarra_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(50) NOT NULL,
  `text` varchar(140) NOT NULL,
  `likes` int(5) NOT NULL DEFAULT '0',
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1316 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
