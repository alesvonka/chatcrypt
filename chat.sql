-- Adminer 4.7.2 MySQL dump

SET NAMES utf8;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

CREATE DATABASE `chat_crypt` /*!40100 DEFAULT CHARACTER SET utf8 */;
USE `chat_crypt`;

DROP TABLE IF EXISTS `chat`;
CREATE TABLE `chat` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(100) NOT NULL,
  `group` varchar(100) NOT NULL,
  `user` varchar(100) NOT NULL,
  `message` text DEFAULT NULL,
  `message_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `chat_write`;
CREATE TABLE `chat_write` (
                            `chat_user` varchar(100) NOT NULL,
                            PRIMARY KEY (`chat_user`),
                            UNIQUE KEY `chat_user` (`chat_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- 2019-09-13 09:05:01
