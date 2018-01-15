--
-- Table structure for table `_pizarra_actions`
--

CREATE TABLE `_pizarra_actions` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
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
  `email` varchar(255) NOT NULL,
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
  `text` varchar(100) NOT NULL,
  `review` tinyint(1) NOT NULL DEFAULT '0',
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_pizarra_notes`
--

CREATE TABLE `_pizarra_notes` (
  `id` int(11) NOT NULL,
  `email` char(100) NOT NULL,
  `text` varchar(140) NOT NULL,
  `likes` int(5) NOT NULL DEFAULT '0',
  `unlikes` int(5) NOT NULL DEFAULT '0',
  `comments` int(5) NOT NULL DEFAULT '0',
  `views` int(7) NOT NULL DEFAULT '0',
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `auto` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Autopost from ourside, 0=User insertion',
  `ad` tinyint(1) NOT NULL DEFAULT '0',
  `topic1` varchar(20) NOT NULL,
  `topic2` varchar(20) NOT NULL,
  `topic3` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_pizarra_topics`
--

CREATE TABLE `_pizarra_topics` (
  `id` int(11) NOT NULL,
  `topic` varchar(20) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note` int(11) NOT NULL,
  `person` char(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `_pizarra_users`
--

CREATE TABLE `_pizarra_users` (
  `email` char(100) NOT NULL,
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
  ADD KEY `note` (`note`);

--
-- Indexes for table `_pizarra_comments`
--
ALTER TABLE `_pizarra_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `note` (`note`),
  ADD KEY `email` (`email`);

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
  ADD KEY `email` (`email`);

--
-- Indexes for table `_pizarra_topics`
--
ALTER TABLE `_pizarra_topics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `_pizarra_users`
--
ALTER TABLE `_pizarra_users`
  ADD PRIMARY KEY (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `_pizarra_actions`
--
ALTER TABLE `_pizarra_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20710;
--
-- AUTO_INCREMENT for table `_pizarra_comments`
--
ALTER TABLE `_pizarra_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24382;
--
-- AUTO_INCREMENT for table `_pizarra_denounce`
--
ALTER TABLE `_pizarra_denounce`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;
--
-- AUTO_INCREMENT for table `_pizarra_notes`
--
ALTER TABLE `_pizarra_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45070;
--
-- AUTO_INCREMENT for table `_pizarra_topics`
--
ALTER TABLE `_pizarra_topics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1244;
