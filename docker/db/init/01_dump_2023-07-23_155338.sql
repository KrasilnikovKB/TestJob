CREATE DATABASE IF NOT EXISTS `test` DEFAULT CHARACTER SET utf8;

USE `test`;

DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id` varchar(36) NOT NULL,
  `external_id` varchar(36) NOT NULL,
  `status` enum('wait','fail','done') NOT NULL,
  `email` varchar(64) DEFAULT NULL,
  `answer` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` datetime NOT NULL INVISIBLE DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `external_id` (`external_id`),
  UNIQUE KEY `email` (`email`) COMMENT 'странное ТЗ - email должен быть уникальным',
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

