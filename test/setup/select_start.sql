
CREATE TABLE `company` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `ipo` int(10) unsigned NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `company` (`id`, `name`, `ipo`) VALUES
(1, 'Google', 2004),
(2, 'Facebook', 2012),
(3, 'Amazon', 1997),
(4, 'Apple', NULL);

CREATE TABLE `employee` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `company` int(10) unsigned NOT NULL,
  `manager` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `employee` (`id`, `name`, `company`, `manager`) VALUES
(1, 'Alice', 1, 0),
(2, 'Bob', 1, 1),
(3, 'Carol', 2, 0),
(4, 'Dave', 2, 3),
(5, 'Eve', 3, 0),
(6, 'Mallory', 4, 0);

CREATE TABLE `report` (
  `employee` int(10) unsigned NOT NULL,
  `day` int(10) unsigned NOT NULL,
  `summary` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `report`
  ADD FULLTEXT KEY `summary` (`summary`);

INSERT INTO `report` (`employee`, `day`, `summary`) VALUES
(1, 1, 'Joined the company'),
(1, 2, 'Read a few things'),
(1, 3, 'Left the company');
