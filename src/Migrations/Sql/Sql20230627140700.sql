-- MySQL dump 10.13  Distrib 8.0.19, for Win64 (x86_64)
--
-- Host: localhost    Database: allrpginfo
-- ------------------------------------------------------
-- Server version	5.7.42

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `tag`
--

DROP TABLE IF EXISTS `tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tag` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `parent` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `content` varchar(6) DEFAULT NULL,
  `code` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `code` (`code`),
  KEY `parent` (`parent`),
  KEY `content` (`content`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api_application`
--

DROP TABLE IF EXISTS `api_application`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_application` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `hash` varchar(100) DEFAULT NULL,
  `google_api_key` varchar(255) DEFAULT NULL,
  `apple_api_key` varchar(255) DEFAULT NULL,
  `apple_api_cert` varchar(255) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bank_currency`
--

DROP TABLE IF EXISTS `bank_currency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_currency` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `default_one` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_currency_project_id_IDX` (`project_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `qrpg_history`
--

DROP TABLE IF EXISTS `qrpg_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qrpg_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `application_id` int(11) DEFAULT NULL,
  `qrpg_code_id` int(11) DEFAULT NULL,
  `success` text,
  `remove_copies_success` text,
  `currencies_success` text,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `project_id` (`project_id`),
  KEY `qrpg_code_id` (`qrpg_code_id`),
  KEY `qrpg_history_application_id_IDX` (`application_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `areahave`
--

DROP TABLE IF EXISTS `areahave`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `areahave` (
  `id` int(10) unsigned NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `gr` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gr` (`gr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `news`
--

DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `type` tinyint(4) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `show_date` datetime DEFAULT NULL,
  `from_date` date DEFAULT NULL,
  `to_date` date DEFAULT NULL,
  `active` enum('0','1') NOT NULL DEFAULT '0',
  `annotation` text,
  `quote` text,
  `content` longtext,
  `attachments` text,
  `attachments2` text,
  `data_came_from` text,
  `tags` text,
  `obj_type` varchar(25) DEFAULT NULL,
  `obj_id` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `active` (`active`),
  KEY `obj_type` (`obj_type`),
  KEY `obj_id` (`obj_id`),
  KEY `type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exchange_item`
--

DROP TABLE IF EXISTS `exchange_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_item` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `region` int(11) DEFAULT NULL,
  `exchange_category_ids` text,
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  `images` json DEFAULT NULL,
  `currency` varchar(3) DEFAULT NULL,
  `price_buy` int(11) DEFAULT NULL,
  `price_lease` int(11) DEFAULT NULL,
  `additional` text,
  `active` enum('0','1') NOT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `exchange_item_exchange_category_ids_IDX` (`exchange_category_ids`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bank_rule`
--

DROP TABLE IF EXISTS `bank_rule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_rule` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `qrpg_keys_from_ids` text,
  `currency_from_id` int(11) DEFAULT NULL,
  `amount_from` int(11) DEFAULT NULL,
  `qrpg_keys_to_ids` text,
  `currency_to_id` int(11) DEFAULT NULL,
  `amount_to` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bank_rule_project_id_IDX` (`project_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `publication`
--

DROP TABLE IF EXISTS `publication`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `publication` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `annotation` text,
  `content` longtext,
  `attachments` text,
  `active` enum('0','1') NOT NULL DEFAULT '0',
  `nocomments` enum('0','1') NOT NULL DEFAULT '0',
  `agreement` enum('0','1') NOT NULL DEFAULT '0',
  `tags` text,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `active` (`active`),
  FULLTEXT KEY `tags` (`tags`)
) ENGINE=InnoDB AUTO_INCREMENT=156 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `qrpg_code`
--

DROP TABLE IF EXISTS `qrpg_code`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qrpg_code` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `sid` varchar(255) DEFAULT NULL,
  `copies` int(11) DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `settings` text,
  `qrpg_keys` text,
  `not_qrpg_keys` text,
  `hacking_settings` text,
  `text_to_access` text,
  `gives_bad_qrpg_keys` text,
  `gives_bad_qrpg_keys_for_minutes` text,
  `description_bad` text,
  `description` text,
  `removes_qrpg_keys_user` text,
  `removes_qrpg_keys` text,
  `removes_copies_of_qrpg_codes` text,
  `gives_qrpg_keys` text,
  `gives_qrpg_keys_for_minutes` text,
  `gives_bank_currency_amount` text,
  `gives_bank_currency` text,
  `gives_bank_currency_total_times` text,
  `gives_bank_currency_once_in_minutes` text,
  `gives_bank_currency_total_times_user` text,
  `gives_bank_currency_once_in_minutes_user` text,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_transaction`
--

DROP TABLE IF EXISTS `project_transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `project_application_id` int(11) DEFAULT NULL,
  `conversation_message_id` int(11) DEFAULT NULL,
  `project_payment_type_id` int(11) DEFAULT NULL,
  `name` varchar(500) DEFAULT NULL,
  `content` text,
  `amount` int(11) DEFAULT '0',
  `verified` enum('0','1') NOT NULL DEFAULT '0',
  `last_update_user_id` int(11) DEFAULT NULL,
  `comission_percent` float NOT NULL DEFAULT '0',
  `comission_value` float NOT NULL DEFAULT '0',
  `payment_datetime` datetime DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `project_payment_type_id` (`project_payment_type_id`),
  KEY `project_application_id` (`project_application_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conversation_message`
--

DROP TABLE IF EXISTS `conversation_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_message` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `parent` int(11) DEFAULT NULL,
  `use_group_name` enum('0','1') NOT NULL DEFAULT '0',
  `icon` varchar(255) DEFAULT NULL,
  `content` text,
  `attachments` text,
  `message_action` varchar(255) DEFAULT NULL,
  `message_action_data` text,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `conversation_id` (`conversation_id`),
  KEY `message_action` (`message_action`),
  KEY `parent` (`parent`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `qrpg_hacking`
--

DROP TABLE IF EXISTS `qrpg_hacking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qrpg_hacking` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `project_application_id` int(11) NOT NULL,
  `qrpg_code_id` int(11) NOT NULL,
  `qrpg_code_group` int(11) NOT NULL,
  `qrpg_history_id` int(11) DEFAULT NULL,
  `matrix` text,
  `sequences` text,
  `input_length` int(11) DEFAULT NULL,
  `timer` int(11) DEFAULT NULL COMMENT 'в секундах',
  `started_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `qrpg_hacking_project_application_id_IDX` (`project_application_id`) USING BTREE,
  KEY `qrpg_hacking_qrpg_code_id_IDX` (`qrpg_code_id`) USING BTREE,
  KEY `qrpg_hacking_creator_id_IDX` (`creator_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `played`
--

DROP TABLE IF EXISTS `played`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `played` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `calendar_event_id` int(11) DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `locat` varchar(255) DEFAULT NULL,
  `specializ` varchar(255) DEFAULT NULL,
  `specializ2` varchar(255) DEFAULT NULL,
  `specializ3` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `active` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `calendar_event_id` (`calendar_event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `resource`
--

DROP TABLE IF EXISTS `resource`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resource` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `is_category` enum('0','1') NOT NULL DEFAULT '0',
  `code` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  `price` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `quantity_needed` int(11) DEFAULT NULL,
  `state` text,
  `whereabouts` text,
  `responsible_id` int(11) DEFAULT NULL,
  `bought_by` int(11) DEFAULT NULL,
  `distributed_item` enum('0','1') NOT NULL DEFAULT '0',
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `project_id` (`project_id`),
  KEY `distributed_item` (`distributed_item`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ruling_question`
--

DROP TABLE IF EXISTS `ruling_question`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruling_question` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `field_name` varchar(400) DEFAULT NULL,
  `field_values` text,
  `creator_id` int(11) DEFAULT NULL,
  `field_type` varchar(100) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `code` varchar(100) DEFAULT NULL,
  `show_if` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscription`
--

DROP TABLE IF EXISTS `subscription`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `author_name` varchar(255) DEFAULT NULL,
  `author_email` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `content` text,
  `obj_type` varchar(50) DEFAULT NULL,
  `obj_id` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `obj_type` (`obj_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `qrpg_key`
--

DROP TABLE IF EXISTS `qrpg_key`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qrpg_key` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `keydata` varchar(255) DEFAULT NULL,
  `consists_of` text,
  `img` int(11) DEFAULT NULL,
  `property_name` varchar(255) DEFAULT NULL,
  `property_description` text,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api_application_user`
--

DROP TABLE IF EXISTS `api_application_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_application_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `hash` varchar(100) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `app_id` (`app_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ruling_item`
--

DROP TABLE IF EXISTS `ruling_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruling_item` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `content` text,
  `ruling_tag_ids` text,
  `show_if` json DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  FULLTEXT KEY `ruling_item_ruling_tag_ids_IDX` (`ruling_tag_ids`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_plot`
--

DROP TABLE IF EXISTS `project_plot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_plot` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `parent` int(11) DEFAULT NULL,
  `description` text,
  `project_id` int(11) DEFAULT NULL,
  `project_character_ids` text,
  `hideother` enum('0','1') NOT NULL DEFAULT '0',
  `name` varchar(255) DEFAULT NULL,
  `code` int(11) DEFAULT NULL,
  `responsible_gamemaster_id` int(11) DEFAULT NULL,
  `type` int(11) DEFAULT NULL,
  `content` text,
  `applications_1_side_ids` text,
  `applications_2_side_ids` text,
  `todo` text COMMENT 'Что осталось доделать по этому сюжету',
  `last_update_user_id` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `site_id` (`project_id`),
  KEY `project_id` (`project_id`),
  KEY `parent` (`parent`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `achievement`
--

DROP TABLE IF EXISTS `achievement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `achievement` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `community`
--

DROP TABLE IF EXISTS `community`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `community` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `type` varchar(25) DEFAULT NULL,
  `description` text,
  `attachments` text,
  `access_to_childs` int(11) DEFAULT NULL,
  `tags` text,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  FULLTEXT KEY `tags` (`tags`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_application`
--

DROP TABLE IF EXISTS `project_application`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_application` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `offer_to_user_id` int(11) DEFAULT NULL,
  `offer_denied` enum('0','1') NOT NULL DEFAULT '0',
  `team_application` enum('0','1') NOT NULL DEFAULT '0',
  `project_character_id` int(11) DEFAULT NULL,
  `money` int(11) NOT NULL DEFAULT '0',
  `project_fee_ids` text,
  `money_provided` int(11) NOT NULL DEFAULT '0',
  `money_need_approve` enum('0','1') NOT NULL DEFAULT '0',
  `money_paid` enum('0','1') NOT NULL DEFAULT '0',
  `sorter` varchar(255) DEFAULT NULL,
  `project_group_ids` text,
  `user_requested_project_group_ids` text,
  `allinfo` longtext NOT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  `last_update_user_id` int(11) DEFAULT NULL,
  `deleted_by_player` enum('0','1') NOT NULL DEFAULT '0',
  `deleted_by_gamemaster` enum('0','1') NOT NULL DEFAULT '0',
  `player_got_info` enum('0','1') NOT NULL DEFAULT '0',
  `player_registered` enum('0','1') NOT NULL DEFAULT '0',
  `eco_money_paid` enum('0','1') NOT NULL DEFAULT '0',
  `registration_comments` text,
  `application_team_count` int(11) DEFAULT NULL,
  `distributed_item_ids` text,
  `qrpg_key` text,
  `signtochange` enum('0','1') NOT NULL DEFAULT '0',
  `signtocomments` enum('0','1') NOT NULL DEFAULT '0',
  `responsible_gamemaster_id` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `creator_id` (`creator_id`),
  KEY `offer_to_user_id` (`offer_to_user_id`),
  KEY `team_application` (`team_application`),
  KEY `money_paid` (`money_paid`),
  KEY `status` (`status`),
  KEY `responsible_gamemaster_id` (`responsible_gamemaster_id`),
  KEY `deleted_by_player` (`deleted_by_player`),
  KEY `deleted_by_gamemaster` (`deleted_by_gamemaster`),
  KEY `project_character_id` (`project_character_id`),
  KEY `money_need_approve` (`money_need_approve`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_group`
--

DROP TABLE IF EXISTS `project_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_group` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `parent` int(11) DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `code` int(11) NOT NULL DEFAULT '1',
  `content` varchar(255) NOT NULL DEFAULT '{menu}',
  `description` text,
  `image` varchar(500) DEFAULT NULL,
  `rights` int(11) NOT NULL,
  `disallow_applications` enum('0','1') NOT NULL DEFAULT '0',
  `responsible_gamemaster_id` int(11) DEFAULT NULL,
  `distributed_item_autoset` text,
  `disable_changes` enum('0','1') NOT NULL DEFAULT '0',
  `last_update_user_id` int(11) DEFAULT NULL,
  `user_can_request_access` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `rights` (`rights`),
  KEY `content` (`content`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `library`
--

DROP TABLE IF EXISTS `library`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `library` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `path` text,
  `type` int(11) DEFAULT NULL,
  `description` text,
  `version` int(11) DEFAULT NULL,
  `tags` text,
  `vimeo_status` varchar(25) DEFAULT NULL,
  `vimeo_path` varchar(255) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `type` (`type`),
  KEY `vimeo_status` (`vimeo_status`),
  FULLTEXT KEY `tags` (`tags`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calendar_event_gallery`
--

DROP TABLE IF EXISTS `calendar_event_gallery`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calendar_event_gallery` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `calendar_event_id` int(11) DEFAULT NULL,
  `thumb` varchar(1000) NOT NULL,
  `name` varchar(255) NOT NULL,
  `link` varchar(1000) NOT NULL,
  `author` varchar(255) NOT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `game_id` (`calendar_event_id`),
  KEY `calendar_event_id` (`calendar_event_id`),
  KEY `creator_id` (`creator_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `task_and_event`
--

DROP TABLE IF EXISTS `task_and_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_and_event` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `place` varchar(1000) DEFAULT NULL,
  `description` text,
  `date_from` datetime DEFAULT NULL,
  `date_to` datetime DEFAULT NULL,
  `do_not_count_as_busy` enum('0','1') NOT NULL DEFAULT '0',
  `percentage` int(11) DEFAULT NULL,
  `real_date_from` date DEFAULT NULL,
  `real_date_to` date DEFAULT NULL,
  `status` varchar(25) DEFAULT NULL,
  `priority` int(11) DEFAULT NULL,
  `repeat_mode` varchar(25) DEFAULT NULL,
  `repeat_until` datetime DEFAULT NULL,
  `attachments` text,
  `result` text,
  `tags` text,
  `color` varchar(40) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `status` (`status`),
  KEY `priority` (`priority`),
  KEY `do_not_count_as_busy` (`do_not_count_as_busy`),
  FULLTEXT KEY `tags` (`tags`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_application_history`
--

DROP TABLE IF EXISTS `project_application_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_application_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_application_id` int(11) DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `allinfo` longtext NOT NULL,
  `project_character_id` int(11) DEFAULT NULL,
  `money` int(11) DEFAULT NULL,
  `money_paid` enum('0','1') NOT NULL DEFAULT '0',
  `project_group_ids` text,
  `status` int(11) NOT NULL,
  `deleted_by_player` enum('0','1') NOT NULL DEFAULT '0',
  `player_got_info` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_creator` (`project_application_id`,`creator_id`),
  KEY `role_id` (`project_application_id`),
  KEY `project_application_id` (`project_application_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_filterset`
--

DROP TABLE IF EXISTS `project_filterset`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_filterset` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `link` varchar(1000) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_filterset_project_id_IDX` (`project_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `speciality`
--

DROP TABLE IF EXISTS `speciality`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `speciality` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `gr` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gr` (`gr`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sid` int(11) DEFAULT NULL,
  `login` varchar(255) DEFAULT NULL,
  `pass` varchar(255) DEFAULT NULL,
  `fio` varchar(255) DEFAULT NULL,
  `nick` varchar(255) DEFAULT NULL,
  `gender` int(11) DEFAULT NULL,
  `birth` date DEFAULT NULL,
  `city` int(11) DEFAULT NULL,
  `em` varchar(255) DEFAULT NULL,
  `em_verified` enum('0','1') NOT NULL DEFAULT '0',
  `phone` varchar(255) DEFAULT NULL,
  `telegram` varchar(255) DEFAULT NULL,
  `icq` varchar(255) DEFAULT NULL,
  `skype` varchar(255) DEFAULT NULL,
  `jabber` varchar(255) DEFAULT NULL,
  `vkontakte` varchar(255) DEFAULT NULL,
  `vkontakte_visible` varchar(255) DEFAULT NULL,
  `twitter` varchar(255) DEFAULT NULL,
  `livejournal` varchar(255) DEFAULT NULL,
  `googleplus` varchar(255) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `facebook_visible` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `additional` text,
  `sickness` text,
  `prefer` varchar(255) DEFAULT NULL,
  `prefer2` varchar(255) DEFAULT NULL,
  `prefer3` varchar(255) DEFAULT NULL,
  `prefer4` varchar(255) DEFAULT NULL,
  `speciality` varchar(255) DEFAULT NULL,
  `ingroup` varchar(255) DEFAULT NULL,
  `bazecount` int(11) DEFAULT NULL,
  `hidesome` varchar(255) DEFAULT NULL,
  `subs_type` int(11) DEFAULT NULL,
  `subs_objects` text,
  `rights` varchar(255) DEFAULT NULL,
  `status` varchar(500) DEFAULT NULL,
  `calendarstyle` enum('0','1') NOT NULL DEFAULT '0',
  `last_activity` int(11) DEFAULT NULL,
  `last_get_new_events` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `agreement` enum('0','1') NOT NULL DEFAULT '0',
  `block_save_referer` enum('0','1') NOT NULL DEFAULT '0',
  `block_auto_redirect` enum('0','1') NOT NULL DEFAULT '0',
  `refresh_token` text,
  `refresh_token_exp` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sid` (`sid`) USING BTREE,
  KEY `city` (`city`),
  KEY `subs_type` (`subs_type`),
  KEY `user_ingroup_IDX` (`ingroup`) USING BTREE,
  FULLTEXT KEY `user_refresh_token_IDX` (`refresh_token`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `article`
--

DROP TABLE IF EXISTS `article`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `article` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `parent` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `annotation` text,
  `content` longtext,
  `attachments` text,
  `active` enum('0','1') NOT NULL DEFAULT '0',
  `nocomments` enum('0','1') NOT NULL DEFAULT '0',
  `tags` text,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`),
  KEY `active` (`active`),
  FULLTEXT KEY `tags` (`tags`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `relation`
--

DROP TABLE IF EXISTS `relation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `relation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `obj_type_from` varchar(25) DEFAULT NULL,
  `obj_id_from` int(11) DEFAULT NULL,
  `type` varchar(25) DEFAULT NULL,
  `obj_type_to` varchar(25) DEFAULT NULL,
  `obj_id_to` int(11) DEFAULT NULL,
  `comment` text,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `obj_type_to` (`obj_type_to`),
  KEY `obj_type_from` (`obj_type_from`),
  KEY `obj_id_from` (`obj_id_from`),
  KEY `obj_id_to` (`obj_id_to`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user__push_subscriptions`
--

DROP TABLE IF EXISTS `user__push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user__push_subscriptions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `device_id` varchar(64) NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth` varchar(255) NOT NULL,
  `content_encoding` varchar(32) DEFAULT 'aesgcm',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_endpoint` (`endpoint`(255)),
  UNIQUE KEY `uniq_user_device` (`user_id`,`device_id`),
  CONSTRAINT `user__push_subscriptions_user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `document`
--

DROP TABLE IF EXISTS `document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `content` text,
  `outer_css` varchar(255) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gameworld`
--

DROP TABLE IF EXISTS `gameworld`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gameworld` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calendar_event`
--

DROP TABLE IF EXISTS `calendar_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calendar_event` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `region` int(11) DEFAULT NULL,
  `area` int(11) DEFAULT NULL,
  `gametype` varchar(255) DEFAULT NULL,
  `gametype2` varchar(255) DEFAULT NULL,
  `gametype3` int(11) DEFAULT NULL,
  `gametype4` varchar(255) DEFAULT NULL,
  `mg` varchar(255) DEFAULT NULL,
  `site` varchar(1000) DEFAULT NULL,
  `orderpage` varchar(255) DEFAULT NULL,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `date_arrival` date DEFAULT NULL,
  `playernum` varchar(255) DEFAULT NULL,
  `content` text,
  `logo` varchar(255) DEFAULT NULL,
  `tomoderate` enum('0','1') NOT NULL DEFAULT '0',
  `addip` varchar(255) DEFAULT NULL,
  `wascancelled` enum('0','1') NOT NULL DEFAULT '0',
  `moved` enum('0','1') NOT NULL DEFAULT '0',
  `kogdaigra_id` int(11) DEFAULT NULL,
  `agroup` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `calendar_event_mg_IDX` (`mg`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_character`
--

DROP TABLE IF EXISTS `project_character`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_character` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_group_ids` text,
  `setparentgroups` enum('0','1') NOT NULL DEFAULT '0',
  `team_character` enum('0','1') NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL,
  `applications_needed_count` int(11) DEFAULT NULL,
  `auto_new_character_creation` enum('0','1') NOT NULL DEFAULT '0',
  `team_applications_needed_count` int(11) DEFAULT NULL,
  `maybetaken` text,
  `taken` text,
  `hide_applications` enum('0','1') NOT NULL DEFAULT '0',
  `disallow_applications` enum('0','1') NOT NULL DEFAULT '0',
  `content` text,
  `comments` text,
  `project_id` int(11) DEFAULT NULL,
  `last_update_user_id` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `team_character` (`team_character`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_fee`
--

DROP TABLE IF EXISTS `project_fee`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_fee` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `cost` int(11) DEFAULT NULL,
  `parent` int(11) DEFAULT NULL,
  `content` text,
  `do_not_use_in_budget` enum('0','1') NOT NULL DEFAULT '0',
  `project_room_ids` text,
  `date_from` date DEFAULT NULL,
  `last_update_user_id` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gametype`
--

DROP TABLE IF EXISTS `gametype`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gametype` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `gametype` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `regstamp`
--

DROP TABLE IF EXISTS `regstamp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `regstamp` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `hash` varchar(255) DEFAULT NULL,
  `code` varchar(255) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_room`
--

DROP TABLE IF EXISTS `project_room`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_room` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `one_place_price` int(11) DEFAULT NULL,
  `places_count` int(11) DEFAULT NULL,
  `allow_player_select` enum('0','1') NOT NULL DEFAULT '0',
  `description` text,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report`
--

DROP TABLE IF EXISTS `report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `calendar_event_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `content` longtext NOT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `calendar_event_id` (`calendar_event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `calendar_event_group`
--

DROP TABLE IF EXISTS `calendar_event_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `calendar_event_group` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_payment_type`
--

DROP TABLE IF EXISTS `project_payment_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_payment_type` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `amount` int(11) NOT NULL DEFAULT '0',
  `registration_type` enum('0','1') NOT NULL DEFAULT '0',
  `paw_type` enum('0','1') NOT NULL DEFAULT '0',
  `yk_type` enum('0','1') NOT NULL DEFAULT '0',
  `pm_type` enum('0','1') NOT NULL DEFAULT '0',
  `pk_type` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bank_transaction`
--

DROP TABLE IF EXISTS `bank_transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `name` varchar(500) DEFAULT NULL,
  `from_project_application_id` int(11) DEFAULT NULL,
  `from_bank_currency_id` int(11) DEFAULT NULL,
  `amount_from` int(11) DEFAULT NULL,
  `to_project_application_id` int(11) DEFAULT NULL,
  `bank_currency_id` int(11) DEFAULT NULL,
  `amount` int(11) DEFAULT '0',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `bank_transaction_to_project_application_id` (`to_project_application_id`),
  KEY `bank_transaction_from_project_application_id_IDX` (`from_project_application_id`) USING BTREE,
  KEY `bank_transaction_bank_currency_id_IDX` (`bank_currency_id`) USING BTREE,
  KEY `bank_transaction_from_bank_currency_id_IDX` (`from_bank_currency_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conversation_message_status`
--

DROP TABLE IF EXISTS `conversation_message_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_message_status` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `message_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message_read` enum('0','1') NOT NULL DEFAULT '0',
  `message_deleted` enum('0','1') NOT NULL DEFAULT '0',
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_message_unique_key` (`message_id`,`user_id`),
  KEY `message_id` (`message_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ruling_tag`
--

DROP TABLE IF EXISTS `ruling_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ruling_tag` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `parent` int(11) DEFAULT '0',
  `content` varchar(10) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `show_in_cloud` enum('0','1') NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ruling_tag_parent_IDX` (`parent`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notion`
--

DROP TABLE IF EXISTS `notion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notion` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `calendar_event_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `rating` enum('-1','1') NOT NULL,
  `active` enum('0','1') NOT NULL DEFAULT '0',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `user_id` (`user_id`),
  KEY `calendar_event_id` (`calendar_event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project`
--

DROP TABLE IF EXISTS `project`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `type` varchar(25) DEFAULT NULL,
  `group_type` varchar(25) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `parent` int(11) DEFAULT NULL,
  `annotation` text,
  `description` text,
  `attachments` text,
  `date_from` date DEFAULT NULL,
  `date_to` date DEFAULT NULL,
  `access_to_childs` int(11) DEFAULT NULL,
  `tags` text,
  `google_id` varchar(255) DEFAULT NULL,
  `external_link` varchar(255) DEFAULT NULL,
  `sorter` int(11) DEFAULT NULL,
  `sorter2` int(11) DEFAULT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `player_count` int(11) DEFAULT NULL,
  `show_roleslist` enum('0','1') NOT NULL DEFAULT '0',
  `status` enum('0','1') NOT NULL DEFAULT '0',
  `showonlyacceptedroles` enum('0','1') NOT NULL DEFAULT '0',
  `oneorderfromplayer` enum('0','1') NOT NULL DEFAULT '0',
  `disable_taken_field` enum('0','1') NOT NULL DEFAULT '0',
  `ingame_css` varchar(1000) DEFAULT NULL,
  `show_budget_info` enum('0','1') NOT NULL DEFAULT '0',
  `paw_mnt_id` int(11) DEFAULT NULL,
  `paw_code` varchar(255) DEFAULT NULL,
  `yk_acc_id` varchar(255) DEFAULT NULL,
  `yk_code` varchar(255) DEFAULT NULL,
  `paymaster_merchant_id` varchar(255) DEFAULT NULL,
  `paymaster_code` varchar(100) DEFAULT NULL,
  `paykeeper_login` varchar(255) DEFAULT NULL,
  `paykeeper_pass` varchar(255) DEFAULT NULL,
  `paykeeper_server` varchar(255) DEFAULT NULL,
  `paykeeper_secret` varchar(255) DEFAULT NULL,
  `show_datetime_in_transaction` enum('0','1') NOT NULL DEFAULT '0',
  `helper_before_transaction_add` varchar(1000) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `type` (`type`),
  KEY `parent` (`parent`),
  KEY `status` (`status`),
  FULLTEXT KEY `tags` (`tags`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api_application_user_push`
--

DROP TABLE IF EXISTS `api_application_user_push`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `api_application_user_push` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `app_id` int(11) DEFAULT NULL,
  `google_token` varchar(255) DEFAULT NULL,
  `apple_token` varchar(255) DEFAULT NULL,
  `windows_token` varchar(500) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `app_id` (`app_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `banner`
--

DROP TABLE IF EXISTS `banner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banner` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  `link` text,
  `img` varchar(255) DEFAULT NULL,
  `type` int(11) DEFAULT NULL,
  `active` enum('0','1') NOT NULL DEFAULT '0',
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `type` (`type`),
  KEY `active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `conversation`
--

DROP TABLE IF EXISTS `conversation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `obj_type` varchar(50) DEFAULT NULL,
  `obj_id` int(11) DEFAULT NULL,
  `sub_obj_type` varchar(255) DEFAULT NULL,
  `avatar` text,
  `use_names_type` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `obj_id` (`obj_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `geography`
--

DROP TABLE IF EXISTS `geography`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `geography` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `parent` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `content` varchar(6) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent` (`parent`),
  KEY `content` (`content`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_application_geoposition`
--

DROP TABLE IF EXISTS `project_application_geoposition`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_application_geoposition` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `project_id` int(11) DEFAULT NULL,
  `project_application_id` int(11) DEFAULT NULL,
  `latitude` float DEFAULT NULL,
  `longitude` float DEFAULT NULL,
  `accuracy` float DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `project_application_geoposition_project_application_id_IDX` (`project_application_id`) USING BTREE,
  KEY `project_application_geoposition_creator_id_IDX` (`creator_id`) USING BTREE,
  KEY `project_application_geoposition_project_id_IDX` (`project_id`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `area`
--

DROP TABLE IF EXISTS `area`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `area` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tipe` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `city` int(11) DEFAULT NULL,
  `creator_id` int(11) DEFAULT NULL,
  `content` text,
  `havegood` varchar(255) DEFAULT NULL,
  `havebad` varchar(255) DEFAULT NULL,
  `map` varchar(255) DEFAULT NULL,
  `way` text,
  `coordinates` text,
  `tomoderate` enum('0','1') NOT NULL DEFAULT '0',
  `addip` varchar(255) DEFAULT NULL,
  `kogdaigra_id` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `external_map_link` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `creator_id` (`creator_id`),
  KEY `tipe` (`tipe`),
  KEY `city` (`city`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exchange_category`
--

DROP TABLE IF EXISTS `exchange_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exchange_category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `parent` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `content` varchar(6) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `subscription_push`
--

DROP TABLE IF EXISTS `subscription_push`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_push` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message_img` varchar(255) DEFAULT NULL,
  `header` varchar(255) DEFAULT NULL,
  `content` varchar(255) DEFAULT NULL,
  `obj_type` varchar(50) DEFAULT NULL,
  `obj_id` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `obj_type` (`obj_type`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `user_ip` varchar(40) DEFAULT NULL,
  `address` text,
  `obj_type` varchar(25) DEFAULT NULL,
  `obj_id` int(11) DEFAULT NULL,
  `action` varchar(25) DEFAULT NULL,
  `action_obj_type` varchar(25) DEFAULT NULL,
  `action_obj_id` varchar(100) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `obj_type` (`obj_type`),
  KEY `action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `project_application_field`
--

DROP TABLE IF EXISTS `project_application_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_application_field` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) DEFAULT NULL,
  `field_name` varchar(255) DEFAULT NULL,
  `field_type` varchar(255) DEFAULT NULL,
  `field_mustbe` enum('0','1') NOT NULL DEFAULT '0',
  `field_default` text,
  `field_rights` int(11) DEFAULT NULL,
  `show_if` text,
  `field_help` text,
  `field_values` text,
  `field_code` int(11) DEFAULT NULL,
  `field_width` int(11) DEFAULT NULL,
  `field_height` int(11) DEFAULT NULL,
  `ingame_settings` text,
  `show_in_filters` enum('0','1') NOT NULL DEFAULT '0',
  `show_in_table` enum('0','1') DEFAULT '0',
  `hide_field_on_application_create` enum('0','1') NOT NULL DEFAULT '0',
  `application_type` enum('0','1','2','3','4','5') NOT NULL DEFAULT '0',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `site_id` (`project_id`),
  KEY `project_id` (`project_id`),
  KEY `application_type` (`application_type`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-09-17 19:00:13

INSERT INTO area (tipe,name,city,creator_id,content,havegood,havebad,`map`,way,coordinates,tomoderate,addip,kogdaigra_id,created_at,updated_at,external_map_link) VALUES
	 (1,'Тестовый полигон',2,1,'<p>Описание тестового полигона</p>','-1-9-2-6-','-5-4-7-3-8-',NULL,NULL,NULL,'0',NULL,0,1758394726,1758394726,NULL);
   
INSERT INTO areahave (id,name,gr,created_at,updated_at) VALUES
	 (1,'вода',1,1171785896,1171785896),
	 (2,'строяк',1,1171785978,1171785978),
	 (3,'цивилы',2,1171786007,1171786007),
	 (4,'насекомые и клещи',2,1171785997,1171785997),
	 (5,'дикие (ядовитые) животные',2,1171786204,1171786204),
	 (6,'частный полигон',1,1171786110,1171786110),
	 (7,'органы власти',2,1171786173,1171786173),
	 (8,'ядовитые растения',2,1171786196,1171786196),
	 (9,'подъездные пути',1,1171786255,1171786255);

INSERT INTO banner (name,description,link,img,`type`,active,updated_at,created_at) VALUES
	 ('allrpg.info - Баннер','Фото <a href="https://www.allrpg.info/" target="_blank">allrpg.info</a><br><a href="https://www.allrpg.info/" target="_blank">allrpg.info</a>',NULL,'{allrpginfobanner.png:social_network_logo.png}',1,'1',1528204795,1496172881);

INSERT INTO calendar_event (creator_id,name,region,area,gametype,gametype2,gametype3,gametype4,mg,site,orderpage,date_from,date_to,date_arrival,playernum,content,logo,tomoderate,addip,wascancelled,moved,kogdaigra_id,agroup,created_at,updated_at) VALUES
	 (1,'Тестовое событие',2,1,'18','23',40,NULL,'Тестовая МГ',NULL,NULL,'2025-09-20','2025-09-20','2025-09-20','30','<p>Описание тестового события</p>',NULL,'0',NULL,'0','0',0,NULL,1758394781,1758394781);

INSERT INTO community (creator_id,name,`type`,description,attachments,access_to_childs,tags,updated_at,created_at) VALUES
	 (1,'Тестовая группа','{open}','<p>Описание</p><p>для</p><p><strong>тестовой</strong></p><p><em>группы</em></p>',NULL,1,NULL,1758393890,1758393880);

INSERT INTO conversation (creator_id,name,obj_type,obj_id,sub_obj_type,avatar,use_names_type,updated_at,created_at) VALUES
	 (1,'Первая тема','{community_conversation}',1,NULL,NULL,NULL,1758393990,1758393990),
	 (2,NULL,'{project_wall}',1,NULL,NULL,NULL,1758395328,1758395316),
	 (2,NULL,'{task_comment}',2,NULL,NULL,NULL,1758395801,1758395801),
	 (2,'Тестовое обсуждение','{project_conversation}',1,'{admin}',NULL,NULL,1758395930,1758395921),
	 (4,NULL,'{project_application_conversation}',2,'{from_player}',NULL,NULL,1758472794,1758472720),
	 (2,NULL,NULL,NULL,NULL,NULL,NULL,1758474506,1758474505);

INSERT INTO conversation_message (creator_id,conversation_id,parent,use_group_name,icon,content,attachments,message_action,message_action_data,updated_at,created_at) VALUES
	 (1,1,0,'1',NULL,'Первый
**текст**
__обсуждения__
> как надо','','','',1758393990,1758393990),
	 (2,2,0,'1',NULL,'Тестовое
**сообщение**
__на стене__
> проекта','','','',1758395316,1758395316),
	 (2,2,2,'0',NULL,'@Мастер[2], тестовый ответ.','','','',1758395328,1758395328),
	 (2,3,0,'0',NULL,'Задача создана.','','','',1758395801,1758395801),
	 (2,4,0,'1',NULL,'Текст тестового обсуждения','','','',1758395921,1758395921),
	 (2,4,5,'0',NULL,'@Мастер[2], ответ на тестовое обсуждение.','','','',1758395930,1758395930),
	 (4,5,0,'0',NULL,'**Оплата взноса**
                    
                    __Сумма__: 10
                    __Счет__: Лично мастеру','','{fee_payment}','{project_transaction_id: 1, resolved:8}',1758472720,1758472720),
	 (2,5,7,'0',NULL,'Оплата подтверждена.','','','',1758472794,1758472794),
	 (2,6,0,'0',NULL,'Тестовая суть вопроса
                            
        Тестовое
детальное
описание','','','',1758474505,1758474505);

INSERT INTO conversation_message_status (message_id,user_id,message_read,message_deleted,updated_at,created_at) VALUES
	 (1,1,'1','0',1758393990,1758393990),
	 (2,2,'1','0',1758395316,1758395316),
	 (3,2,'1','0',1758395328,1758395328),
	 (4,2,'1','0',1758395801,1758395801),
	 (5,2,'1','0',1758395921,1758395921),
	 (6,2,'1','0',1758395930,1758395930),
	 (7,4,'1','0',1758472720,1758472720),
	 (7,2,'1','0',1758472791,1758472791),
	 (8,2,'1','0',1758472794,1758472794),
	 (8,4,'0','0',1758472794,1758472794),
	 (9,2,'1','0',1758474507,1758474505),
	 (9,1,'0','0',1758474505,1758474505);

INSERT INTO exchange_category (creator_id,parent,name,content,created_at,updated_at) VALUES
	 (15,NULL,'Оружие','{menu}',1600959450,1600959450),
	 (15,NULL,'Костюм','{menu}',1600959453,1600959453),
	 (15,NULL,'Аксессуар','{menu}',1600959461,1600959461),
	 (15,NULL,'Доспех','{menu}',1600959471,1600959471),
	 (15,NULL,'Фэнтези','{menu}',1600959477,1600959477),
	 (15,NULL,'Фантастика','{menu}',1600959480,1600959480),
	 (15,NULL,'Историческое','{menu}',1600959484,1600959484),
	 (15,NULL,'Современное','{menu}',1600959545,1600959545),
	 (15,NULL,'Строяк','{menu}',1600959558,1600959580),
	 (15,NULL,'Туристический реквизит','{menu}',1600959570,1600959570);

INSERT INTO gametype (name,gametype,created_at,updated_at) VALUES
	 ('фэнтези',1,1302692608,1302692608),
	 ('городская',2,1302692608,1302692608),
	 ('страйкбол',3,1302692608,1302692608),
	 ('конвент',2,1302692608,1302692608),
	 ('техногенная игра',1,1302692608,1302692608),
	 ('историческая игра',1,1302692608,1302692608),
	 ('техногенное фэнтези',1,1302692608,1302692608),
	 ('научная фантастика',1,1302692608,1302692608),
	 ('городское фэнтези',1,1302692608,1302692608),
	 ('приключения',1,1302692608,1302692608),
	 ('кино и мультфильмы',1,1302692608,1302692608),
	 ('сказки, легенды, мифы',1,1302692608,1302692608),
	 ('кабинетная',2,1302692608,1302692608),
	 ('павильонная',2,1302692608,1302692608),
	 ('полигонная',2,1302692608,1302692608),
	 ('онлайн',2,1302692608,1302692608),
	 ('киберпанк',1,1302692608,1302692608),
	 ('альтернативная история',1,1302692608,1302692608),
	 ('мистерия',3,1302692608,1302692608),
	 ('бугурт',3,1302692608,1302692608),
	 ('сессионная',3,1302692608,1302692608),
	 ('концерт',2,1302692608,1302692608),
	 ('бал / дискотека',2,1302692608,1302692608),
	 ('маневры / турнир',2,1302692608,1302692608),
	 ('юмористическая',1,1302692608,1302692608),
	 ('не игра',1,1302692608,1302692608),
	 ('исторический фестиваль',2,1319113678,1319113678),
	 ('не определен',1,1302692608,1302692608),
	 ('хоррор',1,1302692608,1302692608);

INSERT INTO gameworld (name,created_at,updated_at) VALUES
	 ('Миры Стругацких',1317122679,1317122679),
	 ('Сказки, легенды, мифы',1317122679,1317122679),
	 ('Гарри Поттер',1317122679,1317122679),
	 ('Star Wars',1317122679,1317122679),
	 ('Мир Тьмы (Вампиры: Маскарад)',1317122679,1317122679),
	 ('Тайный Город',1317122679,1317122679),
	 ('Warhammer',1317122679,1317122679),
	 ('Реальный мир',1317122679,1317122679),
	 ('Киберпанк',1317122679,1317122679),
	 ('StarCraft',1317122679,1317122679),
	 ('Аниме',1317122679,1317122679),
	 ('Хроники Нарнии',1317122679,1317122679),
	 ('Матрица',1317122679,1317122679),
	 ('Миры Пола Андерсона',1317122679,1317122679),
	 ('Миры Майкла Муркока',1317122679,1317122679),
	 ('Дреннайский цикл (Дэвид Геммелл)',1317122679,1317122679),
	 ('Волкодав',1317122679,1317122679),
	 ('Мир Толкина',1317122679,1317122679),
	 ('Хроники Амбера',1317122679,1317122679),
	 ('Земноморье (Урсула ле Гуин)',1317122679,1317122679),
	 ('Колесо Времени (Роберт Джордан)',1317122679,1317122679),
	 ('Орден Манускрипта (Тэд Уильямс)',1317122679,1317122679),
	 ('Ведьмак (Анджей Сапковский)',1317122679,1317122679),
	 ('Песнь льда и пламени (Джордж Мартин)',1317122679,1317122679),
	 ('Чёрный отряд (Глен Кук)',1317122679,1317122679),
	 ('Dragonlance',1317122679,1317122679),
	 ('Forgotten Realms',1317122679,1317122679),
	 ('Миры Лукьяненко',1317122679,1317122679),
	 ('Плоский мир (Терри Пратчетт)',1317122679,1317122679),
	 ('Миры Роберта Асприна',1317122679,1317122679),
	 ('Лабиринты Ехо (Макс Фрай)',1317122679,1317122679),
	 ('Авторский мир',1317122679,1317122679),
	 ('Мир по мотивам разных произведений',1317122679,1317122679),
	 ('Прочее',1317122679,1317122679),
	 ('Мир Варкрафт',1317122679,1317122679),
	 ('Герои Магии и Меча',1317122679,1317122679),
	 ('Буджолд Л.',1317122679,1317122679),
	 ('Warhammer: 40K',1317122679,1317122679),
	 ('Миры Пехова',1317122679,1317122679),
	 ('Battlestar Galactica',1317122679,1317122679),
	 ('Ravenloft',1317122679,1317122679),
	 ('The Elder Scrolls',1317122679,1317122679),
	 ('Dragon Age',1317122679,1317122679),
	 ('Mass Effect',1317122679,1317122679);

INSERT INTO geography (creator_id,parent,name,content,updated_at,created_at) VALUES
	 (1,0,'Россия','{menu}',1170082278,1170082278),
	 (1,1,'Москва','{menu}',1170788562,1170788562),
	 (1,2,'Зеленоград','',1170082642,1170082642),
	 (1,2,'Восточный округ','',1170082642,1170082642),
	 (1,2,'Западный округ','',1170082642,1170082642),
	 (1,2,'Северный округ','',1170082642,1170082642),
	 (1,2,'Северо-Восточный округ','',1170082642,1170082642),
	 (1,2,'Северо-Западный округ','',1170082642,1170082642),
	 (1,2,'Центральный округ','',1170082642,1170082642),
	 (1,2,'Юго-Восточный округ','',1170082642,1170082642),
	 (1,2,'Юго-Западный округ','',1170082642,1170082642),
	 (1,2,'Южный округ','',1170082642,1170082642),
	 (1,1,'Московская область','{menu}',1170082642,1170082642),
	 (1,13,'Балашиха','',1170082642,1170082642),
	 (1,13,'Бронницы','',1170082642,1170082642),
	 (1,13,'Видное','',1170082642,1170082642),
	 (1,13,'Волоколамск','',1170082642,1170082642),
	 (1,13,'Воскресенск','',1170082642,1170082642),
	 (1,13,'Дзержинский','',1170082642,1170082642),
	 (1,13,'Дмитров','',1170082642,1170082642),
	 (1,13,'Долгопрудный','',1170082642,1170082642),
	 (1,13,'Домодедово','',1170082642,1170082642),
	 (1,13,'Дубна','',1170082642,1170082642),
	 (1,13,'Егорьевск','',1170082642,1170082642),
	 (1,13,'Железнодорожный','',1170082642,1170082642),
	 (1,13,'Жуковский','',1170082642,1170082642),
	 (1,13,'Сергиев Посад','',1170082642,1170082642),
	 (1,13,'Зарайск','',1170082642,1170082642),
	 (1,13,'Звенигород','',1170082642,1170082642),
	 (1,13,'Ивантеевка','',1170082642,1170082642),
	 (1,13,'Истра','',1170082642,1170082642),
	 (1,13,'Королев','',1170082642,1170082642),
	 (1,13,'Кашира','',1170082642,1170082642),
	 (1,13,'Климовск','',1170082642,1170082642),
	 (1,13,'Клин','',1170082642,1170082642),
	 (1,13,'Коломна','',1170082642,1170082642),
	 (1,13,'Красногорск','',1170082642,1170082642),
	 (1,13,'Лобня','',1170082642,1170082642),
	 (1,13,'Лыткарино','',1170082642,1170082642),
	 (1,13,'Люберцы','',1170082642,1170082642),
	 (1,13,'Красноармейск','',1170082642,1170082642),
	 (1,13,'Можайск','',1170082642,1170082642),
	 (1,13,'Мытищи','',1170082642,1170082642),
	 (1,13,'Наро-Фоминск','',1170082642,1170082642),
	 (1,13,'Ногинск','',1170082642,1170082642),
	 (1,13,'Одинцово','',1170082642,1170082642),
	 (1,13,'Озеры','',1170082642,1170082642),
	 (1,13,'Орехово-Зуево','',1170082642,1170082642),
	 (1,13,'Павловский Посад','',1170082642,1170082642),
	 (1,13,'Подольск','',1170082642,1170082642),
	 (1,13,'Пушкино','',1170082642,1170082642),
	 (1,13,'Пущино','',1170082642,1170082642),
	 (1,13,'Раменское','',1170082642,1170082642),
	 (1,13,'Реутов','',1170082642,1170082642),
	 (1,13,'Рошаль','',1170082642,1170082642),
	 (1,13,'Протвино','',1170082642,1170082642),
	 (1,13,'Серпухов','',1170082642,1170082642),
	 (1,13,'Солнечногорск','',1170082642,1170082642),
	 (1,13,'Ступино','',1170082642,1170082642),
	 (1,13,'Троицк','',1170082642,1170082642),
	 (1,13,'Фрязино','',1170082642,1170082642),
	 (1,13,'Химки','',1170082642,1170082642),
	 (1,13,'Чехов','',1170082642,1170082642),
	 (1,13,'Шатура','',1170082642,1170082642),
	 (1,13,'Щелково','',1170082642,1170082642),
	 (1,13,'Щербинка','',1170082642,1170082642),
	 (1,13,'Электросталь','',1170082642,1170082642),
	 (1,13,'Юбилейный','',1170082642,1170082642),
	 (1,13,'Краснознаменск','',1170082642,1170082642),
	 (1,13,'Яхрома','',1170082642,1170082642),
	 (1,13,'Краснозаводск','',1170082642,1170082642),
	 (1,13,'Пересвет','',1170082642,1170082642),
	 (1,13,'Хотьково','',1170082642,1170082642),
	 (1,13,'Дедовск','',1170082642,1170082642),
	 (1,13,'Ожерелье','',1170082642,1170082642),
	 (1,13,'Высоковск','',1170082642,1170082642),
	 (1,13,'Луховицы','',1170082642,1170082642),
	 (1,13,'Апрелевка','',1170082642,1170082642),
	 (1,13,'Верея','',1170082642,1170082642),
	 (1,13,'Электроугли','',1170082642,1170082642),
	 (1,13,'Дрезна','',1170082642,1170082642),
	 (1,13,'Куровское','',1170082642,1170082642),
	 (1,13,'Ликино-Дулево','',1170082642,1170082642),
	 (1,13,'Электрогорск','',1170082642,1170082642),
	 (1,13,'Руза','',1170082642,1170082642),
	 (1,13,'Талдом','',1170082642,1170082642),
	 (1,13,'Сходня','',1170082642,1170082642),
	 (1,13,'Лосино-Петровский','',1170082642,1170082642),
	 (1,1,'Санкт-Петербург','{menu}',1170082642,1170082642),
	 (1,89,'Адмиралтейский р-н','',1170082642,1170082642),
	 (1,89,'Василеостровский р-н','',1170082642,1170082642),
	 (1,89,'Выборгский р-н','',1170082642,1170082642),
	 (1,89,'Приморский р-н','',1170082642,1170082642),
	 (1,89,'Калининский р-н','',1170082642,1170082642),
	 (1,89,'Кировский р-н','',1170082642,1170082642),
	 (1,89,'Колпинский р-н','',1170082642,1170082642),
	 (1,89,'Красногвардейский р-н','',1170082642,1170082642),
	 (1,89,'Красносельский р-н','',1170082642,1170082642),
	 (1,89,'Кронштадтский р-н','',1170082642,1170082642),
	 (1,89,'Курортный р-н','',1170082642,1170082642),
	 (1,89,'Ломоносовский р-н','',1170082642,1170082642),
	 (1,89,'Московский р-н','',1170082642,1170082642),
	 (1,89,'Невский р-н','',1170082642,1170082642),
	 (1,89,'Павловский р-н','',1170082642,1170082642),
	 (1,89,'Петроградский р-н','',1170082642,1170082642),
	 (1,89,'Петродворцовый р-н','',1170082642,1170082642),
	 (1,89,'Пушкинский р-н','',1170082642,1170082642),
	 (1,89,'Фрунзенский р-н','',1170082642,1170082642),
	 (1,89,'Центральный р-н','',1170082642,1170082642),
	 (1,89,'Колпино','',1170082642,1170082642),
	 (1,89,'Красное Село','',1170082642,1170082642),
	 (1,89,'Кронштадт','',1170082642,1170082642),
	 (1,89,'Зеленогорск','',1170082642,1170082642),
	 (1,89,'Сестрорецк','',1170082642,1170082642),
	 (1,89,'Ломоносов','',1170082642,1170082642),
	 (1,89,'Павловск','',1170082642,1170082642),
	 (1,89,'Петергоф','',1170082642,1170082642),
	 (1,89,'Пушкин','',1170082642,1170082642),
	 (1,1,'Ленинградская область','{menu}',1170082642,1170082642),
	 (1,119,'Бокситогорск','',1170082642,1170082642),
	 (1,119,'Волхов','',1170082642,1170082642),
	 (1,119,'Всеволожск','',1170082642,1170082642),
	 (1,119,'Выборг','',1170082642,1170082642),
	 (1,119,'Гатчина','',1170082642,1170082642),
	 (1,119,'Ивангород','',1170082642,1170082642),
	 (1,119,'Кингисепп','',1170082642,1170082642),
	 (1,119,'Кириши','',1170082642,1170082642),
	 (1,119,'Кировск','',1170082642,1170082642),
	 (1,119,'Лодейное Поле','',1170082642,1170082642),
	 (1,119,'Луга','',1170082642,1170082642),
	 (1,119,'Пикалево','',1170082642,1170082642),
	 (1,119,'Подпорожье','',1170082642,1170082642),
	 (1,119,'Приозерск','',1170082642,1170082642),
	 (1,119,'Сертолово','',1170082642,1170082642),
	 (1,119,'Сланцы','',1170082642,1170082642),
	 (1,119,'Сосновый Бор','',1170082642,1170082642),
	 (1,119,'Тихвин','',1170082642,1170082642),
	 (1,119,'Тосно','',1170082642,1170082642),
	 (1,119,'Шлиссельбург','',1170082642,1170082642),
	 (1,119,'Волосово','',1170082642,1170082642),
	 (1,119,'Новая Ладога','',1170082642,1170082642),
	 (1,119,'Сясьстрой','',1170082642,1170082642),
	 (1,119,'Высоцк','',1170082642,1170082642),
	 (1,119,'Каменногорск','',1170082642,1170082642),
	 (1,119,'Приморск','',1170082642,1170082642),
	 (1,119,'Светогорск','',1170082642,1170082642),
	 (1,119,'Коммунар','',1170082642,1170082642),
	 (1,119,'Отрадное','',1170082642,1170082642),
	 (1,119,'Любань','',1170082642,1170082642),
	 (1,119,'Никольское','',1170082642,1170082642);

INSERT INTO project (creator_id,`type`,group_type,name,parent,annotation,description,attachments,date_from,date_to,access_to_childs,tags,google_id,external_link,sorter,sorter2,currency,player_count,show_roleslist,status,showonlyacceptedroles,oneorderfromplayer,disable_taken_field,ingame_css,show_budget_info,paw_mnt_id,paw_code,yk_acc_id,yk_code,paymaster_merchant_id,paymaster_code,paykeeper_login,paykeeper_pass,paykeeper_server,paykeeper_secret,show_datetime_in_transaction,helper_before_transaction_add,updated_at,created_at) VALUES
	 (2,'{open}',NULL,'Тестовый проект',NULL,'<p>Краткое</p><p><strong>описание</strong></p><p><em>тестового</em></p><p>проекта</p>','<p>Полное</p><p><em>описание</em></p><p><strong>тестового</strong></p><p>проекта</p>',NULL,'2023-09-20','2035-09-20',NULL,NULL,NULL,NULL,2,NULL,'RUR',100,'0','1','0','0','0',NULL,'0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'0',NULL,1758395283,1758395283);

INSERT INTO project_application (project_id,creator_id,offer_to_user_id,offer_denied,team_application,project_character_id,money,project_fee_ids,money_provided,money_need_approve,money_paid,sorter,project_group_ids,user_requested_project_group_ids,allinfo,status,last_update_user_id,deleted_by_player,deleted_by_gamemaster,player_got_info,player_registered,eco_money_paid,registration_comments,application_team_count,distributed_item_ids,qrpg_key,signtochange,signtocomments,responsible_gamemaster_id,created_at,updated_at) VALUES
	 (1,3,NULL,'0','0',2,100,'-2-',0,'0','0','Индивидуальный персонаж','-1-2-',NULL,'[virtual2][Индивидуальный персонаж]&lt;br&gt;',1,3,'0','0','0','0','0',NULL,NULL,NULL,NULL,'0','0',2,1758471526,1758471526),
	 (1,4,NULL,'0','1',1,100,'-2-',10,'0','0','Командный персонаж','-1-',NULL,'[virtual4][Командный персонаж]&lt;br&gt;',2,2,'0','0','0','0','0',NULL,10,NULL,NULL,'0','0',2,1758471914,1758472805);

INSERT INTO project_application_field (project_id,field_name,field_type,field_mustbe,field_default,field_rights,show_if,field_help,field_values,field_code,field_width,field_height,ingame_settings,show_in_filters,show_in_table,hide_field_on_application_create,application_type,created_at,updated_at) VALUES
	 (1,'Персонаж','h1','0',NULL,4,NULL,NULL,NULL,1,NULL,NULL,NULL,'0','0','0','0',1758395283,1758395283),
	 (1,'Имя персонажа','text','1',NULL,4,NULL,NULL,NULL,2,NULL,0,'-game-','1','0','0','0',1758395283,1758395999),
	 (1,'Команда','h1','0',NULL,3,NULL,NULL,NULL,1,NULL,0,NULL,'1','0','0','1',1758395961,1758395961),
	 (1,'Название команды','text','1',NULL,3,NULL,NULL,NULL,2,NULL,0,NULL,'1','0','0','1',1758395975,1758395975);

INSERT INTO project_application_history (project_application_id,creator_id,allinfo,project_character_id,money,money_paid,project_group_ids,status,deleted_by_player,player_got_info,created_at,updated_at) VALUES
	 (2,4,'[virtual4][Командный персонаж]&lt;br&gt;',1,100,'0','-1-',1,'0','0',1758471914,1758471914);

INSERT INTO project_character (project_group_ids,setparentgroups,team_character,name,applications_needed_count,auto_new_character_creation,team_applications_needed_count,maybetaken,taken,hide_applications,disallow_applications,content,comments,project_id,last_update_user_id,created_at,updated_at) VALUES
	 ('-1-','1','1','Командный персонаж',1,'0',10,NULL,NULL,'0','0','Описание
**командного**
персонажа',NULL,1,2,1758469553,1758470339),
	 ('-1-2-','1','0','Индивидуальный персонаж',1,'0',0,NULL,NULL,'0','0','Описание
__индивидуального__
персонажа',NULL,1,2,1758469611,1758469624);

INSERT INTO project_fee (project_id,creator_id,name,cost,parent,content,do_not_use_in_budget,project_room_ids,date_from,last_update_user_id,created_at,updated_at) VALUES
	 (1,2,'Основной взнос',NULL,NULL,'{menu}','0',NULL,NULL,2,1758395283,1758395283),
	 (1,2,NULL,100,1,NULL,'0',NULL,'2025-09-20',2,1758395283,1758470661);

INSERT INTO project_group (project_id,parent,name,code,content,description,image,rights,disallow_applications,responsible_gamemaster_id,distributed_item_autoset,disable_changes,last_update_user_id,user_can_request_access,created_at,updated_at) VALUES
	 (1,NULL,'Тестовая группа',1,'{menu}','Описание
**тестовой**
__группы__',NULL,0,'0',2,NULL,'0',2,'0',1758396477,1758396477),
	 (1,1,'Тестовая подгруппа',1,'{menu}','Описание
тестовой
подгруппы',NULL,0,'0',2,NULL,'0',2,'0',1758469321,1758469321);

INSERT INTO project_payment_type (creator_id,project_id,name,user_id,amount,registration_type,paw_type,yk_type,pm_type,pk_type,created_at,updated_at) VALUES
	 (2,1,'Лично мастеру',2,10,'0','0','0','0','0',1758395283,1758395283);

INSERT INTO project_transaction (creator_id,project_id,project_application_id,conversation_message_id,project_payment_type_id,name,content,amount,verified,last_update_user_id,comission_percent,comission_value,payment_datetime,created_at,updated_at) VALUES
	 (4,1,2,7,1,'Оплата взноса','',10,'1',4,0.0,0.0,NULL,1758472720,1758472720);

INSERT INTO publication (creator_id,name,author,annotation,content,attachments,active,nocomments,agreement,tags,updated_at,created_at) VALUES
	 (1,'FAQ',NULL,'Ответы на основные вопросы по сайту allrpg.info','<strong>Что такое id? Где я могу его посмотреть?</strong><br>id – идентификационный номер пользователя. Данный номер уникален, Вы получаете его в момент регистрации и можете увидеть его в пункте «<a href="https://www.allrpg.info/people/" target="_blank">Ваш профиль</a>».<br><br><strong>Как подключить социальные сети к моему профилю?</strong><br>Скопируйте ссылку на профиль в социальной сети и вставьте ее в соответствующее поле в Вашем «<a href="https://www.allrpg.info/profile/" target="_blank">Профиле</a>».<strong><br><br>Как мне добавить / изменить событие / полигон в «Календаре»?<br></strong>Перейдите в раздел «<a href="https://www.allrpg.info/calendar_event/" target="_blank">Календарь – Мои события</a>» или «<a href="https://www.allrpg.info/area/" target="_blank" title="Link: https://www.allrpg.info/area/">Календарь – Мои полигоны</a>» соответственно. Через те же пункты меню Вы можете изменить ранее внесенные события / полигоны.<br>Обратите внимание! Если Вы не зарегистрированы или не залогинены на allrpg.info, Вы точно так же можете добавить событие и/или полигон, однако, после обработки события администрацией (и его появления в инфотеке и календаре) Вы потеряете доступ к их изменениям.<br><br><strong>Как подать заявку на проект?</strong><br>Проверьте правильность заполнения информации о Вас в разделе «<a href="https://www.allrpg.info/profile/" target="_blank">Профиль</a>» и правильность Вашего <a href="https://www.allrpg.info/portfolio/" target="_blank" title="Link: https://www.allrpg.info/portfolio/">портфолио</a> игр: когда Вы подадите заявку на игру, данная информация станет доступна к просмотру мастерам проекта (за исключением Вашего e-mail&#39а). Затем перейдите в раздел «<a href="https://www.allrpg.info/myapplication/" target="_blank">Заявки – Подать заявку</a>» и выберите нужный проект. В дальнейшем Вы сможете проверить список всех Ваших заявок в разделе «<a href="https://www.allrpg.info/myapplication/" target="_blank">Заявки – Все мои заявки</a>».<br><br><strong>Как открыть новую систему заявок?</strong><br>Нажмите в разделе «<a href="https://www.allrpg.info/project/">Проекты</a>» на кнопку «<a href="https://www.allrpg.info/project/act=add">Создать проект</a>». Заполните все необходимые поля.<br><br><strong>Как пригласить пользователя или присоединиться к проекту?</strong><br>Чтобы присоединиться к проекту, просто перейдите на страницу проекта и нажмите кнопку «Запросить доступ».<br>Чтобы пригласить пользователя, перейдите на страницу проекта и нажмите кнопку «Пригласить друга» в соответствующей вкладке или в «Действиях».<br>Чтобы пригласить другого мастера, перейдите в раздел «<a href="https://www.allrpg.info/org/">Мастера / организаторы</a>» и добавьте своего друга с соответствующими правами. Если Вам нужно будет ограничить его доступ к определенным заявкам, это можно будет сделать с помощью наборов фильтров (см. ниже).<br><em>Обратите внимание!</em> Приглашение можно присылать только Вашим друзьям (это сделано для того, чтобы избежать разнообразных форм спама), так что сначала Вам нужно ими стать на allrpg.info. Найти нужного пользователя и отправить ему запрос можно через поиск.<br><br><strong>Что мне следует сделать после того, как система заявок будет создана?</strong><br>Прежде всего Вам следует переключиться на управление нужным проектом (ведь у Вас их может быть несколько), выбрав нужный в пункте «Проекты – Название вашего проекта». После этого в пункте «Проект» появятся пункты меню для управления.<ul><li>Страница проекта. В частности на ней Вы можете изменить права управления проектом других пользователей. Система настройки прав достаточно гибкая, чтобы Вы смогли выдать исключительно нужные права. Кроме того, именно здесь Вы можете публиковать все новости Вашего проекта и управлять задачами для сомастеров.</li><li>Редактирование проекта (значок карандаша рядом с названием проекта) – управление основными настройками Вашего проекта, в т.ч. его датами.</li><li>Для выдачи нужных прав сомастерам (после их присоединения к проекту) перейдите на страницу проекта, на вкладку «Права участников». Возле каждой аватарки представлен блок прав, которые Вы можете свободно переключать. Или воспользуйтесь разделом«<a href="https://www.allrpg.info/org/">Мастера / организаторы</a>».</li><li>Сетка ролей – управление и создание всей сетки ролей Вашего проекта.</li><li><a href="https://www.allrpg.info/application/">Список заявок</a> – управление всеми заявками, поданными на Ваш проект, а также комментариями (и рассылками) к ним.</li><li><a href="https://www.allrpg.info/plot/">Сюжеты и завязки</a> – управление взаимосвязями ролей Вашего проекта (заявки, принятые на соответствующие роли, будут автоматически видеть эту информацию, что существенно упрощает возможности прогруза игроков).</li><li><a href="https://www.allrpg.info/org/">Мастера / организаторы</a><span style="font-size: 14.4px;">&nbsp; – настройка прав организаторов проекта с ограничениями видимости заявок.</span></li><li><a href="https://www.allrpg.info/setup/">Форма заявки</a><span style="font-size: 14.4px;">&nbsp;– настройка формы подачи заявок игроков на Ваш проект.</span></li><li><a href="https://www.allrpg.info/filterset/">Наборы фильтров</a><span style="font-size: 14.4px;">&nbsp;– сохранение наборов фильтров списка заявок.</span></li><li><a href="https://www.allrpg.info/budget/">Бюджет</a><span style="font-size: 14.4px;"> – управление всем бюджетным планированием Вашего проекта.</span></li><li><a href="https://www.allrpg.info/fee/">Настройка взносов</a><span style="font-size: 14.4px;">&nbsp;– настройка автоматического изменения взносов по датам, а также разбитие взносов по категориям.</span></li><li><a href="https://www.allrpg.info/payment_type/">Методы оплаты</a><span style="font-size: 14.4px;">&nbsp;– настройка возможных методов оплаты и подключение оплаты картой онлайн.</span></li><li><a href="https://www.allrpg.info/transaction/">История взносов</a><span style="font-size: 14.4px;">&nbsp;– все финансовые операции по взносам на Ваш проект.</span></li><li><a href="https://www.allrpg.info/rooms/">Поселение</a><span style="font-size: 14.4px;"> – управление опциями расселения игроков / участников конвента.</span><br></li><li><a href="https://www.allrpg.info/document/">Генератор документов</a> – инструмент создания любых документов для игроков на основе HTML-шаблонов и данных из заявок.</li><li><a href="https://www.allrpg.info/registration/">Регистрация на месте</a> – модуль отметки прибытия игрока, а также эковзноса и раздатки.</li><li><a href="https://www.allrpg.info/qrpg_key/">QRpg: ключи, предметы и свойства</a> – настройка ключей для чтения текста с QR-кодов с помощью «<a href="https://www.allrpg.info/ingame/">Модуля игрока</a>».</li><li><a href="https://www.allrpg.info/qrpg_code/">QRpg: коды</a> – настройка кодов, текстов, которые будут видны игрокам, хакинга и т.п.</li><li><a href="https://www.allrpg.info/qrpg_history/">QRpg: история считываний</a> – все попытки прочтения кодов через «<a href="https://www.allrpg.info/ingame/">Модуль игрока</a>».</li><li><a href="https://www.allrpg.info/geoposition/">Геопозиция игроков</a> – карта местоположения игроков на местности.</li><li><a href="https://www.allrpg.info/bank_transaction/">Игровой банк: транзакции</a> – информация обо всех транзакциях игроков через игровой банк в «<a href="https://www.allrpg.info/ingame/">Модуле игрока</a>». Здесь же Вы вносите ресурсы на счёт персонажей</li><li><a href="https://www.allrpg.info/bank_currency/">Игровой банк: ресурсы</a> – настройка списка возможных ресурсов: микровалюта, макровалюта, очки опыта и т.д.</li><li><a href="https://www.allrpg.info/bank_rule/">Игровой банк: правила переводов</a>&nbsp;– настройка правил перевода ресурсов игроками (курсы, ограничения и т.п.)</li><li><a href="https://www.allrpg.info/character/">Список персонажей</a><span style="font-size: 14.4px;"> – справочник всех персонажей Вашего проекта.</span><br></li><li><a href="https://www.allrpg.info/group/">Группы персонажей</a> – управление группами персонажей Вашего проекта (для упрощения работы с сеткой и заявками, а также для технических групп).</li><li><a href="https://www.allrpg.info/csvimport/">Импорт из CSV</a><span style="font-size: 14.4px;"> – загрузка данных из формата CSV, подходит для переноса данных из других систем заявок.</span><br></li></ul>Прежде всего Вам следует:<ul><li>В разделе «Сетка ролей» удобно настроить всю сетку ролей.</li><li>Или же в разделе «<a href="https://www.allrpg.info/group/">Группы персонажей</a>» определить локации вашей игры, а в разделе «<a href="https://www.allrpg.info/character/">Список персонажей</a>» настроить список ролей, которые Вы бы хотели увидеть на своей игре.</li><li><span style="font-size: 14.4px;">В разделе «<a href="https://www.allrpg.info/setup/">Форма заявки</a>» настроить вид командной и вид индивидуальной заявки на Ваш проект (см. вопрос «Как работать с заявками на мой проект?»).</span><br></li><li>В разделе «<a href="https://www.allrpg.info/plot/">Сюжеты и завязки</a>» прописать взаимосвязи ролей Вашей игры (а также внешнего сюжета).</li><li>Для запуска системы заявок в редактировании свойств Вашего проекта:<ol><li>установить правильное значение в поле «Сортировка индивидуальных заявок»;</li><li>установить правильное значение в поле «Сортировка командных заявок» (если есть);</li><li>установить «Взнос»;</li><li>установить «Подача заявок» = «открыта».</li></ol></li></ul><strong>Как работать с заявками на мой проект?<br></strong>Прежде всего не забудьте «<a href="https://www.allrpg.info/setup/" target="_blank">Настроить форму заявки</a>» (индивидуальной – обязательно и командной – по желанию). В данном разделе при создании нового поля заявки подробно описана механика заполнения необходимых системе полей. Наша система позволит Вам создать фактически любой необходимый вариант заявки.<br><br>Мы очень рекомендуем Вам изучить также разделы «<a href="https://www.allrpg.info/group/" target="_blank">Группы персонажей</a>», «Сетка ролей» и «<a href="https://www.allrpg.info/plot/" target="_blank">Сюжеты и завязки</a>». Заполнение этих разделов позволит Вам не только отслеживать заполненность Вашей сетки ролей по мере поступления заявок, но и выводить автоматически эту сетку перед списком ролей на Вашем сайте, дабы игроки сразу могли увидеть, какие роли уже заняты, а какие еще вакантны. Кроме того взаимосвязи позволят Вам оценивать количество сюжетных веток, направленных на персонажа и автоматически прогружать игрока, чья заявка принята на роль.<br><br>После того как всё будет настроено, Вы сможете рассматривать все поступающие и измененные игроками заявки в разделе «<a href="https://www.allrpg.info/application/" target="_blank">Список заявок</a>». В нем же Вы сможете вносить изменения в заявки игроков, а также просматривать историю ее изменений. Кроме того Вы также можете воспользоваться функцией «Добавить комментарий» и оставить в заявке комментарий для игрока и/или для других мастеров, работающих с Вами на проекте.<br><br><strong>Что такое набор фильтров?</strong><br>Отфильтровав заявки, Вы можете сохранить данный набор фильтров для проекта: для этого после фильтрации в&nbsp;«<a href="https://www.allrpg.info/application/" target="_blank">Списке заявок</a>» нажмите на ссылку «Сохранить как набор фильтров» внизу, под таблицей отобранных заявок. Все наборы фильтров будут доступны Вам в <a href="https://www.allrpg.info/filterset/">соответствующем разделе</a>, кроме того они будут выводиться быстрыми кнопками над списком заявок.<br>Вы также можете ограничить доступ конкретных мастеров к заявкам, поставив им те или иные наборы фильтров в разделе&nbsp;«<a href="https://www.allrpg.info/org/">Мастера / организаторы</a>».<br><br><strong>Как подключить оплату взносов картой онлайн через PayMaster (проект от создателей WebMoney)?</strong><br>Перейдите к управлению Вашим проектом, затем перейдите в раздел «<a href="https://www.allrpg.info/payment_type/" target="_blank">Методы оплаты</a>» и нажмите кнопку «Подключить оплату взносов картой»: Вы увидите подробную инструкцию. Подключиться несложно, но, если у Вас возникнут сложности на любом этапе процесса, Вы можете связаться с нашей <a href="https://www.allrpg.info/help/" target="_blank">службой поддержки</a>, и мы обязательно оперативно поможем.<ul><li>Комиссия по платежам: 0.7% (картой с помощью Системы Быстрых Платежей от Банка России) или 2,95% (картой, по старинке).</li><li>Подключение: бесплатное и полностью автоматическое.</li><li>Вывод средств: бесплатный и автоматический, каждый рабочий день.</li><li>Ваш налог с платежей, как ИП на НПД: 3-4% вместо стандартных для ИП на УСН 6%, иногда налоговые вычеты вообще убирают налог.</li><li>Более никаких выплат или необходимости в кассовых аппаратах.</li></ul>И, конечно же, взносы будут автоматически отмечаться в заявках.<br><br><strong>Как сгенерировать документы на печать на основе заявок?<br></strong>Включите нужный проект, перейдите в раздел «<a href="https://www.allrpg.info/document/" target="_blank">Генератор документов</a>». Создайте нужный HTML-шаблон для Ваших документов. Сохраните его. Затем в низу раздела выберите те заявки, для которых Вы желаете создать документы.<br><br>	<strong>Как поместить сетку ролей к себе на сайт?</strong><br>Включите нужный проект, перейдите в раздел «Сетка ролей». Следуйте инструкциям, описанным в шапке сетки.<br><br><strong>Как работать с QRpg?</strong><br>С помощью QRpg, как части «<a href="https://www.allrpg.info/ingame/">Модуля игрока</a>», Вы можете радикально снизить взаимодействия игроков с региональными мастерами на игре. В самом простом применении QRpg дает возможность предоставлять доступ на основе ключей персонажа к неограниченным по размерам текстам через скан специальных QR-кодов. Для этого:<ul><li>перейдите к управлению нужным проектом.</li><li>перейдите в раздел «<a href="https://www.allrpg.info/qrpg_key/" target="_blank">QRpg: ключи, предметы и свойства</a>».</li><li>создайте все необходимые ключи и выберите для них иконку: она будет видна на коде, так что игроки сразу будут понимать, доступен им тот или иной код или нет.</li><li>затем Вы можете выставить доступные персонажу ключи в заявке игрока, где он их будет в дальнейшем видеть вместе с иконками.</li><li>кроме того Вы можете распечатать ключи в игровом паспорте персонажа с помощью генератора документов.</li><li>когда ключи готовы, создайте нужные «<a href="https://www.allrpg.info/qrpg_code/" target="_blank">QRpg: коды</a>», которые эти ключи будут открывать. Обратите внимание, что у одного кода может быть неограниченное количество вариантов текстов, каждый из которых открывается своим набором ключей.</li><li>когда Вы заполните все нужные поля кода, Вам станет доступна ссылка «сгенерировать», по которой Вы получите готовый QR-код с номером, иконками и обрезными полями.</li></ul>Игроку в процессе игры нужно только:<ul><li>залогиниться на allrpg.info на своём мобильном телефоне.</li><li>перейти в раздел «<a href="https://www.allrpg.info/ingame/">Заявки – Модуль игрока</a>», выбрать нужную заявку, нажать «Сканировать» и навестись на код. Система тут же покажет данные, если они доступны его персонажу.</li><li>если с Интернет-соединением возникнет проблема, появится кнопка повторного запроса. Поэтому в случае проблем можно: распознать код в одном месте, затем подойти туда, где Интернет стабилен, и расшифровать информацию.</li></ul><ul></ul><strong>Что еще умеет «Модуль игрока»?</strong><br><ul><li>Свойства: сканирование кода может выдать навсегда или на время свойство, при нажатии на которое будет разворачиваться текст, который можно показывать другим игрокам.</li><li>Электронный документ персонажа: внутриигровой паспорт – отдельно, страница с тайными статистиками – отдельно.</li><li>Предметы: сканирование кодов может передавать предмет от игрока к игроку. Или выдавать что-то на время. Или собирать Великий Артефакт из пяти кусков.</li><li>Коды внутри кодов: в текстовом описании кода может быть ссылка на другой код, кликнув на которую, игрок сразу переходит к нему. Позволяет делать деревья выборов.</li><li>mp3-плеер: указанные в описании кода или свойства ссылки на mp3-файл автоматически превращаются в mp3-плеер, чтобы прослушать запись.</li><li>Электронный банкинг: работа с обменом любыми ресурсами (не только микровалютой, но и макроресурсами) через коды или транзакции.</li><li>Хакинг: чтобы получить доступ к информации, нужно будет сыграть в мини-игру разного уровня сложности.</li><li>Комментарии заявки: для общения с мастерами, не выходя из интерфейса.</li><li>А для мастеров еще доступна геопозиция, которая позволяет с помощью фильтров моментально находить местоположение конкретного игрока на полигоне.</li></ul><br><strong>Как можно группировать персонажей / заявки?</strong><br>В настройках групп персонажей есть возможность выставить группе четыре разных уровня видимости: показывать в сетке ролей, показывать только количество заявок в сетке ролей, группа для сюжетов (не показывать в сетке ролей, но показывать игроку), мастерская группа (не показывать даже игроку в его заявке). Любой персонаж может состоять в любом количестве групп одновременно. Таким образом, его можно одновременно поместить в группу видимую в сетке, например: Семья Джонсонов. И в группу, в которой будет видно только количество игроков, например: Тайный Исследовательский Институт. И в группу для сюжетов, например: Выпускники Лиги Золотого Плюща. И в группу, которую он не будет видеть, например: Потенциальный Аватар для Ктулху. В дальнейшем с помощью фильтров в списке заявок вы сможете быстро фильтровать их по любым группам в любом сочетании.<br><br><strong>Что еще умеют фильтры в списке заявок?</strong><br>Кроме фильтрации по группам они могут отфильтровать Вам данные по любым полям из заявок. Всем, которые Вы включите в фильтры. Более того, ссылку на набор фильтров можно сохранять в закладки браузера, чтобы быстро переключаться между ними, и пересылать другим мастерам для удобства. С помощью фильтров также можно быстро произвести рассылку по определенному набору игроков: текст будет записан в комментарии всех соответствующих заявок, а также отправится на email игрокам автоматически.<br><br><strong>Что еще умеют поля заявок?</strong><br>Еще поля умеют прятаться при первичной подаче заявки игроком. Но главное – поля умеют быть зависимыми друг от друга в любой сложности вложенности. Проще говоря, определенный набор полей можно сделать доступным только определенной группе, или сделать сложную анкету, где после выбора в каждом из полей будет открываться очередной набор элементов, которые игроку будет нужно заполнить.<br><br><strong>Как перенести данные с других сервисов?</strong><br>Если Вы пользовались другими сервисами ведения базы заявок и хотите переместить данные на allrpg.info, Вам нужно:<ul><li>создать проект.</li><li>перейти к управлению данным проектом.</li><li>перейти в раздел «<a href="https://www.allrpg.info/csvimport/">Импорт из CSV</a>».</li><li>внести данные, следуя инструкции.</li></ul><strong>Как мне добавить / изменить статью в «Базе знаний»?</strong><br>Отправьте администрации сайта <a href="https://www.allrpg.info/help/" target="_blank">запрос</a> с просьбой выдать права на добавление статей. После того как администрация выдаст Вам права, Вам будет необходимо перелогиниться на портале. После перелогина Вы сможете вносить новые и изменять ранее внесенные статьи в разделе «<a href="https://www.allrpg.info/publications_edit/" target="_blank">База знаний – Статьи</a>».<br><br><strong>Не нашли ответ на свой вопрос?</strong><br>Напишите нам в <a href="/help/">поддержку</a>, и мы ответим, как можно быстрее.',NULL,'1','1','0','-41-',1668674260,1427798837);

INSERT INTO relation (creator_id,obj_type_from,obj_id_from,`type`,obj_type_to,obj_id_to,comment,updated_at,created_at) VALUES
	 (1,'{user}',1,'{admin}','{community}',1,NULL,1758393880,1758393880),
	 (1,'{user}',1,'{member}','{community}',1,NULL,1758393880,1758393880),
	 (1,'{user}',1,'{member}','{conversation}',1,NULL,1758393990,1758393990),
	 (2,'{user}',2,'{admin}','{project}',1,NULL,1758395283,1758395283),
	 (2,'{user}',2,'{member}','{project}',1,'{"view_roleslist_mode":"gamemaster"}',1758395283,1758395283),
	 (2,'{user}',2,'{member}','{conversation}',2,NULL,1758395316,1758395316),
	 (2,'{user}',2,'{admin}','{task}',2,NULL,1758395801,1758395801),
	 (2,'{user}',2,'{member}','{task}',2,NULL,1758395801,1758395801),
	 (2,'{task}',2,'{child}','{project}',1,NULL,1758395801,1758395801),
	 (2,'{user}',2,'{responsible}','{task}',2,NULL,1758395801,1758395801),
	 (2,'{user}',2,'{member}','{conversation}',3,NULL,1758395801,1758395801),
	 (2,'{user}',2,'{important}','{task}',2,NULL,1758395894,1758395894),
	 (2,'{user}',2,'{important}','{project}',1,NULL,1758395898,1758395898),
	 (2,'{user}',2,'{member}','{conversation}',4,NULL,1758395921,1758395921),
	 (2,'{character}',1,'{member}','{group}',1,'1',1758469553,1758469553),
	 (2,'{character}',2,'{member}','{group}',1,'2',1758469611,1758469611),
	 (2,'{character}',2,'{member}','{group}',2,'1',1758469611,1758469611),
	 (3,'{application}',1,'{member}','{group}',1,NULL,1758471526,1758471526),
	 (3,'{application}',1,'{member}','{group}',2,NULL,1758471526,1758471526),
	 (3,'{user}',3,'{member}','{project}',1,NULL,1758471526,1758471526),
	 (4,'{application}',2,'{member}','{group}',1,NULL,1758471914,1758471914),
	 (4,'{user}',4,'{member}','{project}',1,NULL,1758471914,1758471914),
	 (4,'{user}',4,'{member}','{conversation}',5,NULL,1758472720,1758472720),
	 (2,'{user}',2,'{member}','{conversation}',6,NULL,1758474506,1758474506),
	 (2,'{user}',1,'{member}','{conversation}',6,NULL,1758474506,1758474506),
	 (2,'{user}',2,'{important}','{calendar_event}',1,NULL,1758474732,1758474732);

INSERT INTO speciality (name,gr,created_at,updated_at) VALUES
	 ('Боевка',1,1283952244,1283952244),
	 ('Медицина',1,1283952244,1283952244),
	 ('Экономика',1,1283952244,1283952244),
	 ('Магия',1,1283952244,1283952244),
	 ('Власть',1,1283952244,1283952244),
	 ('Медицина',3,1283952244,1283952244),
	 ('Связь',3,1283952244,1283952244),
	 ('Питание',3,1283952244,1283952244),
	 ('Безопасность',3,1283952244,1283952244),
	 ('Администратор',3,1283952244,1283952244),
	 ('Координатор команды',2,1283952244,1283952244),
	 ('Координатор сайта',2,1283952244,1283952244),
	 ('Координатор-региональщик',2,1283952244,1283952244),
	 ('Мастер мертвятника',2,1283952244,1283952244),
	 ('Мастер по антуражу',2,1283952244,1283952244),
	 ('Мастер по боевке',2,1283952244,1283952244),
	 ('Мастер по заявкам',2,1283952244,1283952244),
	 ('Мастер по магии',2,1283952244,1283952244),
	 ('Мастер по медицине',2,1283952244,1283952244),
	 ('Мастер по религии',2,1283952244,1283952244),
	 ('Мастер по связям с общественностью',2,1283952244,1283952244),
	 ('Мастер по сюжету',2,1283952244,1283952244),
	 ('Мастер по экономике',2,1283952244,1283952244),
	 ('Мастер-администратор',2,1283952244,1283952244),
	 ('Главный мастер',2,1283952244,1283952244),
	 ('Мастер по спецэффектам',2,1283952244,1283952244),
	 ('Автотранспорт',3,1283952244,1283952244),
	 ('Мирный житель',1,1283952244,1283952244),
	 ('Религия',1,1283952244,1283952244),
	 ('Наука',1,1283952244,1283952244),
	 ('Фотография и видеосъемка',1,1283952244,1283952244),
	 ('Фото и видео',3,1283952244,1283952244),
	 ('Мастер по науке',2,1283952244,1283952244),
	 ('Мастер по законодательству и юриспруденции',2,1283952244,1283952244),
	 ('Культура и искусство',1,1283952244,1283952244),
	 ('Питание',1,1283952244,1283952244),
	 ('Мастер по культуре и менталитету',2,1283952244,1283952244),
	 ('Мастер по небоевым взаимодействиям',2,1283952329,1283952329),
	 ('Координатор игротехников',2,1317122846,1317122846),
	 ('Игротехник',2,1336141778,1336141778),
	 ('Мастер по IT',2,1283952244,1283952244);

INSERT INTO tag (creator_id,parent,name,content,code,updated_at,created_at) VALUES
	 (1,NULL,'Антураж',NULL,1,1213954236,1213954236),
	 (1,NULL,'Снаряжение',NULL,1,1213954236,1213954236),
	 (1,NULL,'Отыгрыш',NULL,2,1213954236,1213954236),
	 (1,NULL,'Правила',NULL,3,1213954236,1213954236),
	 (1,NULL,'Полигон',NULL,1,1213954236,1213954236),
	 (1,NULL,'Медицина',NULL,2,1213954236,1213954236),
	 (1,NULL,'Боевка',NULL,3,1213954236,1213954236),
	 (1,NULL,'Магия',NULL,3,1213954236,1213954236),
	 (1,NULL,'Аналитика',NULL,4,1213954236,1213954236),
	 (1,NULL,'Транспорт',NULL,2,1213954236,1213954236),
	 (1,NULL,'Питание',NULL,2,1213954236,1213954236),
	 (1,NULL,'Пресса',NULL,4,1213954236,1213954236),
	 (1,NULL,'Игрокам',NULL,1,1213954236,1213954236),
	 (1,NULL,'Мастерам',NULL,1,1213954236,1213954236),
	 (1,NULL,'Костюм',NULL,1,1213954236,1213954236),
	 (1,NULL,'Религия',NULL,3,1213954236,1213954236),
	 (1,NULL,'Наука',NULL,3,1213954236,1213954236),
	 (1,NULL,'Бюрократия',NULL,1,1213954236,1213954236),
	 (1,NULL,'Стратегия',NULL,3,1213954236,1213954236),
	 (1,NULL,'История',NULL,1,1213954236,1213954236),
	 (1,NULL,'Спецэффекты',NULL,2,1213954236,1213954236),
	 (1,NULL,'Моделирование',NULL,2,1213954236,1213954236),
	 (1,NULL,'Советы',NULL,1,1213954236,1213954236),
	 (1,NULL,'Секс',NULL,3,1213954236,1213954236),
	 (1,NULL,'Строяк',NULL,2,1213954236,1213954236),
	 (1,NULL,'Оружие',NULL,3,1213954236,1213954236),
	 (1,NULL,'Связь',NULL,2,1213954236,1213954236),
	 (1,NULL,'Безопасность',NULL,2,1213954236,1213954236),
	 (1,NULL,'Огнестрельное',NULL,3,1213954236,1213954236),
	 (1,NULL,'Холодное',NULL,3,1213954236,1213954236),
	 (1,NULL,'Стрелковое',NULL,3,1213954236,1213954236),
	 (1,NULL,'Политика',NULL,3,1213954236,1213954236),
	 (1,NULL,'Фотография',NULL,4,1213954236,1213954236),
	 (1,NULL,'Видеосъемка',NULL,4,1213954241,1213954241),
	 (1,NULL,'Психология',NULL,2,1213954886,1213954886),
	 (1,NULL,'Философия',NULL,2,1213954937,1213954937),
	 (1,NULL,'Культура',NULL,2,1213954944,1213954944),
	 (1,NULL,'Персоналии',NULL,1,1213955003,1213955003),
	 (1,NULL,'Юмор',NULL,4,1213955117,1213955117),
	 (1,NULL,'Доспех',NULL,3,1213955243,1213955243),
	 (1,NULL,'allrpg.info',NULL,1,1213955487,1213955487),
	 (1,NULL,'Искусство',NULL,3,1213955591,1213955591),
	 (1,NULL,'Экономика',NULL,3,1213964683,1213964683),
	 (1,NULL,'Оргвопросы',NULL,1,1213964692,1213964692),
	 (1,NULL,'Танцы',NULL,3,1213964705,1213964705),
	 (1,NULL,'Арис',NULL,4,1226915066,1226915066);

INSERT INTO task_and_event (creator_id,name,place,description,date_from,date_to,do_not_count_as_busy,percentage,real_date_from,real_date_to,status,priority,repeat_mode,repeat_until,attachments,`result`,tags,color,google_id,updated_at,created_at) VALUES
	 (2,'Тестовая задача проекта',NULL,NULL,'2025-09-20 23:08:00','2025-09-21 00:08:00','0',0,NULL,NULL,'{new}',4,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1758395801,1758395801);

INSERT INTO `user` (sid,login,pass,fio,nick,gender,birth,city,em,em_verified,phone,telegram,icq,skype,jabber,vkontakte,vkontakte_visible,twitter,livejournal,googleplus,facebook,facebook_visible,linkedin,photo,additional,sickness,prefer,prefer2,prefer3,prefer4,speciality,ingroup,bazecount,hidesome,subs_type,subs_objects,rights,status,calendarstyle,last_activity,last_get_new_events,updated_at,created_at,agreement,block_save_referer,block_auto_redirect,refresh_token,refresh_token_exp) VALUES
	 (1,'admin@allrpg.info','992f3c4e7ec9a0e96173284c816612bd','Админ allrpg.info',NULL,NULL,'2006-10-01',2,'admin@allrpg.info','1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'{identicon.png:181f610603474c5ce405283b2e3c9792.png}',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,50,'-2-',1,'-{conversation}-{task}-{event}-{project_wall}-{project_conversation}-{community_wall}-{community_conversation}-','-admin-help-send_images-',NULL,'0',NULL,1758395109,1758395113,1758310785,'1','0','0','d8dfcab423999acacb25ced35b6c091fd7f905198e518517713325d9ddad17872b77b31668569ea571ac8e5c56ff11fdade5da94f70394ed701939e35c525e52a41f34a1bbf6c94aabd29ff32ef9209e87b4b2244b52522f1695bc1a51d652db88d8d1a1','2025-10-20 22:01:39'),
	 (2,'master@allrpg.info','992f3c4e7ec9a0e96173284c816612bd','Мастер allrpg.info',NULL,NULL,'2006-01-01',2,'master@allrpg.info','1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'{identicon.png:da679feedd9e5527f1a2182071a50494.png}',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,50,'-2-',1,'-{conversation}-{task}-{event}-{project_wall}-{project_conversation}-{community_wall}-{community_conversation}-',NULL,NULL,'0',NULL,1758475827,1758475827,1758395205,'1','0','0','5b92a75993fa47c98aeb0e49b71de633f2cd923f3ec3ef7d65a0a9eff9cbc1f4b6989a01dab28b7b6db9806026a9398ac78ef8819128d42b626c6e4cd714b7aa0a6cf9eba7ffeba2b7fd7d199246d5bcc1693347e0eb0c9fa2f7a806d3031fc78b645a03','2025-10-21 19:50:59'),
	 (3,'player1@allrpg.info','992f3c4e7ec9a0e96173284c816612bd','Игрок1 allrpg.info','',NULL,'2006-01-01',2,'player1@allrpg.info','1','','',NULL,NULL,NULL,NULL,'',NULL,NULL,NULL,NULL,'',NULL,'{identicon.png:f64e47414799b0520bcfbc4f5b5fcad0.png}',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'-{conversation}-{task}-{event}-{project_wall}-{project_conversation}-{community_wall}-{community_conversation}-',NULL,NULL,'0',NULL,1758471729,1758471738,1758470573,'1','0','0','3347a5df245da7a3cba6e96690346af95204808a45685ed52db6bc6e34e7803e393a60c52da2e98b8c9cdc0baac78d45c7273f8dcd22e22e65bf34edf01fb31cad84540bfe8ebccea008f13b081490c6f3922929f96ca11392b28f2bc72000262e52310d','2025-10-21 19:10:23'),
	 (4,'player2@allrpg.info','992f3c4e7ec9a0e96173284c816612bd','Игрок2 allrpg.info','',NULL,'2006-01-01',2,'player2@allrpg.info','1','','',NULL,NULL,NULL,NULL,'',NULL,NULL,NULL,NULL,'',NULL,'{identicon.png:79ea2372a5cdc1538fc640bd2f9ac6af.png}',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'-{conversation}-{task}-{event}-{project_wall}-{project_conversation}-{community_wall}-{community_conversation}-',NULL,NULL,'0',NULL,1758472762,1758472780,1758471808,'1','0','0','35a590b19ea5c4dc077a4bf4d7ce047f6c27cdb906ad3e8d36abeb526f26c06f2a1ed051b9956cdb6962f4d242f95da628a17615a89c07b940f41ca097338c5f50fd705ccb2deefb8dc534f501ffdba83818a1c8262da4a9c7317f462766c41d15609b4b','2025-10-21 19:38:22');
