
CREATE TABLE `message` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sender` int(10) unsigned NOT NULL,
  `recipient` int(10) unsigned NOT NULL,
  `text` text NOT NULL,
  `time` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `message` (`id`, `sender`, `recipient`, `text`, `time`) VALUES
(1, 1, 2, 'Are you Ken?', 500),
(2, 2, 1, 'Yes I am!', 1000);
