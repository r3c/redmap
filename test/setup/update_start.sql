
CREATE TABLE `player` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `player` (`id`, `name`) VALUES
(1, 'Alice'),
(2, 'Bob');

CREATE TABLE `log` (
  `id` int(10) unsigned NOT NULL,
  `player` int(10) unsigned NOT NULL,
  `score` int(10) unsigned NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `log` (`id`, `player`, `score`) VALUES
(1, 1, 3),
(2, 1, 5),
(3, 2, 1),
(4, 2, NULL);
