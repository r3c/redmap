
CREATE TABLE `source` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `source` (`id`, `name`) VALUES
(1, 'Apple'),
(2, 'Banana'),
(3, 'Carrot'),
(4, 'Orange');

CREATE TABLE `target` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` int(10) NOT NULL,
  `name` varchar(64) NOT NULL,
  `counter` int(10) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
