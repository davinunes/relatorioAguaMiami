-- MySQL dump - Database Structure for h2o
-- Synchronized with https://app.digitalinovation.com.br/esquema.php

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
-- Table structure for table `contatos_notificacao`
--

DROP TABLE IF EXISTS `contatos_notificacao`;
CREATE TABLE `contatos_notificacao` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sensor_id` int(10) unsigned DEFAULT NULL,
  `grupo_id` bigint(20) unsigned DEFAULT NULL,
  `numero` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sensor_id` (`sensor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `esp32_pings`
--

DROP TABLE IF EXISTS `esp32_pings`;
CREATE TABLE `esp32_pings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uuid` varchar(100) NOT NULL,
  `board` varchar(50) DEFAULT NULL,
  `site_esp` varchar(100) DEFAULT NULL,
  `ssid` varchar(50) DEFAULT NULL,
  `sensor_id` int(11) DEFAULT 0,
  `firmware_version` varchar(10) DEFAULT NULL,
  `remote_ip` varchar(45) DEFAULT NULL,
  `log_content` mediumblob DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_uuid` (`uuid`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `leituras`
--

DROP TABLE IF EXISTS `leituras`;
CREATE TABLE `leituras` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'id',
  `sensor` int(10) unsigned NOT NULL COMMENT 'id do sensor',
  `Valor` double NOT NULL COMMENT 'valor lido pelo sensor',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'data e hora do evento',
  PRIMARY KEY (`id`),
  KEY `idx_sensor_timestamp` (`sensor`,`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `reservatorio`
--

DROP TABLE IF EXISTS `reservatorio`;
CREATE TABLE `reservatorio` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'pk',
  `sensor` int(10) unsigned NOT NULL COMMENT 'ID do Sensor',
  `fosso` int(10) unsigned DEFAULT NULL COMMENT 'profundidade da caixa',
  `nome` varchar(100) NOT NULL COMMENT 'nome do reservatório',
  `alturaSonda` int(10) unsigned DEFAULT NULL COMMENT 'Altura da Sonda',
  `ativo` tinyint(1) DEFAULT 1 COMMENT 'Se o reservatorio está ativo ou não',
  PRIMARY KEY (`id`),
  KEY `idx_sensor_ativo` (`sensor`,`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Armazena os dados do reservatorio';

--
-- Table structure for table `usuario_sensores`
--

DROP TABLE IF EXISTS `usuario_sensores`;
CREATE TABLE `usuario_sensores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `sensor_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_usuario_sensor` (`usuario_id`,`sensor_id`),
  KEY `idx_sensor_usuario` (`sensor_id`,`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `login` varchar(50) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `user_role` varchar(20) DEFAULT 'user',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
