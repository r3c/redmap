
CREATE TABLE IF NOT EXISTS `book` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `year` smallint(5) unsigned DEFAULT NULL
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `book` (`id`, `name`, `year`) VALUES
(1, 'My First Book', 2001),
(2, 'My Second Book', 2002),
(3, 'A Third Book', 2003),
(4, 'Unknown Book', NULL);

ALTER TABLE `book`
  ADD PRIMARY KEY (`id`),
  ADD FULLTEXT KEY `name` (`name`);
