
CREATE TABLE `score_memory` (
  `player` varchar(32) NOT NULL,
  `value` int(10) unsigned NOT NULL,
  PRIMARY KEY (`player`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `score_memory` (`player`, `value`) VALUES
('me', 42);

CREATE TABLE `score_myisam` (
  `player` varchar(32) NOT NULL,
  `value` int(10) unsigned NOT NULL,
  PRIMARY KEY (`player`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `score_myisam` (`player`, `value`) VALUES
('me', 42);
