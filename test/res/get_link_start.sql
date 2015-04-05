
CREATE TABLE `company` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `company` (`id`, `name`) VALUES
(1, 'Google'),
(2, 'Facebook'),
(3, 'Amazon'),
(4, 'Apple');

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
