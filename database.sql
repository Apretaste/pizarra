CREATE TABLE IF NOT EXISTS `_pizarra_users` (
  `email` varchar(50) NOT NULL,
  `reports` int(3) DEFAULT '0' COMMENT 'times the user had been reported',
  `penalized_until` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'If the user had been reported X times, will be penalized til this date'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


CREATE TABLE IF NOT EXISTS `_pizarra_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(50) NOT NULL,
  `text` varchar(140) NOT NULL,
  `likes` int(5) NOT NULL DEFAULT '0',
  `inserted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1316 ;

CREATE TABLE IF NOT EXISTS `_pizarra_seen_notes` (
	note int(11) NOT NULL,
	email varchar(50) NOT NULL,
	inserted timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (note, email)
);

CREATE TABLE _pizarra_comments(
	id int(11) AUTO_INCREMENT PRIMARY KEY,
	email varchar(255) not null,
	note int(11) not null references _pizarra_notes(id) on delete cascade on update cascade,
	text varchar(255) not null,
	inserted timestamp not null default current_timestamp,
	read_date timestamp null default null
);

DROP TABLE _pizarra_reputation;
CREATE TABLE _pizarra_reputation(
    user1 varchar(255) not null,
	user2 varchar(255) not null,
	reputation int(11) not null default 0,
	primary key (user1, user2)
);
