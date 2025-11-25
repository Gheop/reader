/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.3-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: gheop
-- ------------------------------------------------------
-- Server version	11.8.3-MariaDB-0+deb13u1 from Debian-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Dumping routines for database 'gheop'
--

--
-- Table structure for table `reader_flux`
--

DROP TABLE IF EXISTS `reader_flux`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reader_flux` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `link` varchar(255) NOT NULL,
  `language` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `rss` varchar(255) NOT NULL,
  `update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `unread_count_user_2` int(11) DEFAULT 0,
  `unread_count_user_1` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_rss` (`rss`(100))
) ENGINE=InnoDB AUTO_INCREMENT=1461 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC TRANSACTIONAL=0;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reader_item`
--

DROP TABLE IF EXISTS `reader_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reader_item` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `id_flux` smallint(5) unsigned NOT NULL,
  `pubdate` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `guid` varchar(2048) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `title` varchar(400) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `author` varchar(255) NOT NULL,
  `link` varchar(2048) NOT NULL,
  `description` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `youtube_description` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `link` (`link`(255)),
  KEY `reader_item_pubdate_IDX` (`pubdate`) USING BTREE,
  KEY `reader_item_id_flux_IDX` (`id_flux`) USING BTREE,
  KEY `idx_flux_pubdate_covering` (`id_flux`,`pubdate`,`id`),
  FULLTEXT KEY `search` (`title`),
  FULLTEXT KEY `search2` (`description`)
) ENGINE=InnoDB AUTO_INCREMENT=2429736 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_bin ROW_FORMAT=DYNAMIC TRANSACTIONAL=0;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gheop`@`localhost`*/ /*!50003 TRIGGER cache_after_item_insert
AFTER INSERT ON reader_item
FOR EACH ROW
BEGIN
    
    INSERT INTO reader_unread_cache (id_user, id_flux, id_item, pubdate)
    SELECT UF.id_user, NEW.id_flux, NEW.id, NEW.pubdate
    FROM reader_user_flux UF
    WHERE UF.id_flux = NEW.id_flux;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gheop`@`localhost`*/ /*!50003 TRIGGER update_unread_count_after_item_insert
AFTER INSERT ON reader_item
FOR EACH ROW
BEGIN
    
    IF EXISTS (SELECT 1 FROM reader_user_flux WHERE id_user = 1 AND id_flux = NEW.id_flux) THEN
        UPDATE reader_flux
        SET unread_count_user_1 = unread_count_user_1 + 1
        WHERE id = NEW.id_flux;
    END IF;
    
    
    IF EXISTS (SELECT 1 FROM reader_user_flux WHERE id_user = 2 AND id_flux = NEW.id_flux) THEN
        UPDATE reader_flux
        SET unread_count_user_2 = unread_count_user_2 + 1
        WHERE id = NEW.id_flux;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gheop`@`localhost`*/ /*!50003 TRIGGER update_unread_count_after_item_delete
AFTER DELETE ON reader_item
FOR EACH ROW
BEGIN
    
    IF NOT EXISTS (SELECT 1 FROM reader_user_item WHERE id_item = OLD.id AND id_user = 1) THEN
        UPDATE reader_flux
        SET unread_count_user_1 = GREATEST(0, unread_count_user_1 - 1)
        WHERE id = OLD.id_flux;
    END IF;
    
    
    IF NOT EXISTS (SELECT 1 FROM reader_user_item WHERE id_item = OLD.id AND id_user = 2) THEN
        UPDATE reader_flux
        SET unread_count_user_2 = GREATEST(0, unread_count_user_2 - 1)
        WHERE id = OLD.id_flux;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gheop`@`localhost`*/ /*!50003 TRIGGER cache_after_item_delete
AFTER DELETE ON reader_item
FOR EACH ROW
BEGIN
    
    DELETE FROM reader_unread_cache WHERE id_item = OLD.id;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `reader_user_flux`
--

DROP TABLE IF EXISTS `reader_user_flux`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reader_user_flux` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `id_user` tinyint(3) unsigned NOT NULL,
  `id_flux` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reader_user_flux_id_user_IDX` (`id_user`,`id_flux`) USING BTREE,
  KEY `id_user_2` (`id_user`,`id_flux`)
) ENGINE=InnoDB AUTO_INCREMENT=1525 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci PAGE_CHECKSUM=1 ROW_FORMAT=DYNAMIC TRANSACTIONAL=0;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reader_unread_cache`
--

DROP TABLE IF EXISTS `reader_unread_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reader_unread_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `id_flux` int(11) NOT NULL,
  `id_item` int(11) NOT NULL,
  `pubdate` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_user_item` (`id_user`,`id_item`),
  KEY `idx_user_pubdate` (`id_user`,`pubdate`),
  KEY `idx_user_flux_pubdate` (`id_user`,`id_flux`,`pubdate`),
  KEY `idx_item` (`id_item`)
) ENGINE=MEMORY AUTO_INCREMENT=131939 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reader_user_item`
--

DROP TABLE IF EXISTS `reader_user_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reader_user_item` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `id_user` tinyint(3) unsigned NOT NULL,
  `id_item` mediumint(8) unsigned NOT NULL,
  `date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reader_user_item_id_user_IDX` (`id_user`,`id_item`) USING BTREE,
  UNIQUE KEY `itemuserdateIX` (`id_item`,`id_user`,`date`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=2054322 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci PAGE_CHECKSUM=1 ROW_FORMAT=DYNAMIC TRANSACTIONAL=0;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gheop`@`localhost`*/ /*!50003 TRIGGER update_unread_count_after_read
AFTER INSERT ON reader_user_item
FOR EACH ROW
BEGIN
    DECLARE flux_id INT;
    
    
    SELECT id_flux INTO flux_id FROM reader_item WHERE id = NEW.id_item;
    
    
    IF NEW.id_user = 1 THEN
        UPDATE reader_flux
        SET unread_count_user_1 = GREATEST(0, unread_count_user_1 - 1)
        WHERE id = flux_id;
    ELSEIF NEW.id_user = 2 THEN
        UPDATE reader_flux
        SET unread_count_user_2 = GREATEST(0, unread_count_user_2 - 1)
        WHERE id = flux_id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gheop`@`localhost`*/ /*!50003 TRIGGER cache_after_read
AFTER INSERT ON reader_user_item
FOR EACH ROW
BEGIN
    
    DELETE FROM reader_unread_cache
    WHERE id_user = NEW.id_user AND id_item = NEW.id_item;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gheop`@`localhost`*/ /*!50003 TRIGGER update_unread_count_after_unread
AFTER DELETE ON reader_user_item
FOR EACH ROW
BEGIN
    DECLARE flux_id INT;
    
    
    SELECT id_flux INTO flux_id FROM reader_item WHERE id = OLD.id_item;
    
    
    IF OLD.id_user = 1 THEN
        UPDATE reader_flux
        SET unread_count_user_1 = unread_count_user_1 + 1
        WHERE id = flux_id;
    ELSEIF OLD.id_user = 2 THEN
        UPDATE reader_flux
        SET unread_count_user_2 = unread_count_user_2 + 1
        WHERE id = flux_id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_uca1400_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`gheop`@`localhost`*/ /*!50003 TRIGGER cache_after_unread
AFTER DELETE ON reader_user_item
FOR EACH ROW
BEGIN
    DECLARE flux_id INT;
    DECLARE item_pubdate TIMESTAMP;
    
    
    SELECT id_flux, pubdate INTO flux_id, item_pubdate
    FROM reader_item
    WHERE id = OLD.id_item;
    
    
    INSERT INTO reader_unread_cache (id_user, id_flux, id_item, pubdate)
    VALUES (OLD.id_user, flux_id, OLD.id_item, item_pubdate);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pseudo` varchar(20) DEFAULT NULL,
  `pwd` varchar(40) DEFAULT NULL,
  `mail` char(0) DEFAULT NULL,
  `quota` int(11) DEFAULT 1048576,
  `date_create` date DEFAULT NULL,
  `date_expire` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_pseudo_IDX` (`pseudo`,`pwd`) USING BTREE,
  KEY `pseudo` (`pseudo`),
  KEY `pwd` (`pwd`)
) ENGINE=Aria AUTO_INCREMENT=130 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci PAGE_CHECKSUM=1 ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2025-11-14  9:32:00
