CREATE TABLE IF NOT EXISTS `_pizarra_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` char(100) NOT NULL,
  `text` varchar(140) NOT NULL,
  `likes` int(5) NOT NULL DEFAULT '0',
  `unlikes` int(5) NOT NULL DEFAULT '0',
  `comments` int(5) NOT NULL DEFAULT '0',
  `views` int(7) NOT NULL DEFAULT '0',
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `auto` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Autopost from ourside, 0=User insertion',
  `ad` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=31018 ;

CREATE TABLE IF NOT EXISTS `_pizarra_comments`(
	id int(11) AUTO_INCREMENT PRIMARY KEY,
	email varchar(255) not null,
	note int(11) not null references _pizarra_notes(id) on delete cascade on update cascade,
	text varchar(255) not null,
	inserted timestamp not null default current_timestamp,
	read_date timestamp null default null
);

CREATE TABLE IF NOT EXISTS `_pizarra_reputation`(
	user1 varchar(255) not null,
	user2 varchar(255) not null,
	reputation int(11) not null default 0,
	primary key (user1, user2)
);

CREATE TABLE IF NOT EXISTS `_pizarra_actions`(
	email varchar(255) not null references person(email) on delete cascade on update cascade,
	note int(11) not null references _pizarra_notes(id) on delete cascade on update cascade,
	action enum('like', 'unlike') default 'like',
	inserted timestamp not null default current_timestamp
);

DROP TABLE IF EXISTS _pizarra_block;
DROP TABLE IF EXISTS _pizarra_follow;
DROP TABLE IF EXISTS _pizarra_seen_notes;
DROP TABLE IF EXISTS _pizarra_reputation;

ALTER TABLE `_pizarra_notes` ADD `topic1` VARCHAR(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;
ALTER TABLE `_pizarra_notes` ADD `topic2` VARCHAR(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;
ALTER TABLE `_pizarra_notes` ADD `topic3` VARCHAR(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL;

CREATE TABLE IF NOT EXISTS `_pizarra_topics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `topic` varchar(20) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note` int(11) NOT NULL,
  `person` char(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=781 ;

CREATE TABLE IF NOT EXISTS `_pizarra_users` (
  `email` char(100) NOT NULL,
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `default_topic` varchar(30) DEFAULT 'general',
  `reputation` int(6) NOT NULL DEFAULT '100',
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
