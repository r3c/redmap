
CREATE TABLE `score` (
  `player` varchar(32) NOT NULL,
  `value` int(10) unsigned NOT NULL,
  PRIMARY KEY (`player`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `score` (`player`, `value`) VALUES
('me', 42);
