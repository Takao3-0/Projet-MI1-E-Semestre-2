/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.4.7-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: espace_etudiant
-- ------------------------------------------------------
-- Server version	11.4.7-MariaDB-0ubuntu0.25.04.1

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
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `role` varchar(20) DEFAULT 'etudiant',
  `yumland` tinyint(1) DEFAULT 1,
  `credit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `last_login_ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

--
-- Table structure for table `blocages`
--

DROP TABLE IF EXISTS `blocages`;
CREATE TABLE `blocages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bloque_par` int(11) NOT NULL,
  `motif` text DEFAULT NULL,
  `date_blocage` datetime NOT NULL DEFAULT current_timestamp(),
  `date_deblocage` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_actif` (`user_id`, `date_deblocage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Table structure for table `bannissements_ip`
--

DROP TABLE IF EXISTS `bannissements_ip`;
CREATE TABLE `bannissements_ip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Utilisateur concerné (levée groupée au déblocage compte)',
  `bloque_par` int(11) NOT NULL,
  `motif` text DEFAULT NULL,
  `date_ban` datetime NOT NULL DEFAULT current_timestamp(),
  `date_deban` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_actif` (`ip`, `date_deban`),
  KEY `idx_user_actif` (`user_id`, `date_deban`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Dump completed on 2026-05-18 00:00:00

-- ─── Comptes de démonstration ───────────────────────────────────────────────
-- Mot de passe de tous les comptes : "demo1234"  (sauf indication contraire)
-- Pour générer un nouveau hash : password_hash('demo1234', PASSWORD_BCRYPT)

LOCK TABLES `users` WRITE;
INSERT INTO `users` (`id`, `username`, `email`, `password`, `google_id`, `created_at`, `role`, `yumland`, `credit`) VALUES
(16, 'Cuisinier',      'Cuisinier@demo.fr',      '$2y$12$BegcppY63SJszE1tfHHE9eGaujuvxPXfYn6uhWQts0aUhUxxnhtzi', NULL, '2026-02-23 01:27:18', 'chef',      1, 0.00),
(17, 'Administrateur', 'Administrateur@demo.fr', '$2y$12$z7PjMKGZbyxuiaVOpVIpFeIvBHxvKoh1LfCg5AeolD5mIu5Q55wX6', NULL, '2026-02-23 01:27:44', 'fakeadmin', 1, 0.00),
(18, 'Livreur',        'Livreur@demo.fr',         '$2y$12$3nFkQUuLeOB4VdroBb4b0OTeWX0IfGBvE7MOjouB0iE/WJq1dIxTy', NULL, '2026-02-23 01:28:01', 'livreur',   1, 0.00),
(19, 'Client1',        'client1@demo.fr',         '$2y$12$v3yOdTTDPkiEV5ZR/D9QMuDvF8p.ds3sCvR5T39fkr4MeVaNdSfMa', NULL, '2026-04-05 12:55:25', 'etudiant',  1, 0.00),
(20, 'Client2',        'client2@demo.fr',         '$2y$12$y7cJX9ZIW/bRYwdMtb37oeqtqFs7UIRXXALHLJuPYrqnGH98aVQsC', NULL, '2026-04-05 12:55:50', 'etudiant',  1, 0.00),
(21, 'Client3',        'client3@demo.fr',         '$2y$12$/2SmEOEjnBJxVBdLolOGBuug5Ym.1d/vgRvQyv8vy9I13420eaIQK', NULL, '2026-04-05 12:56:24', 'etudiant',  1, 0.00),
(22, 'Client4',        'client4@demo.fr',         '$2y$12$H.xTDMRBOb1XH5wEyvb5/uTDa6A6q0I3GRTuBBuxCk5a.2nHm9XOu', NULL, '2026-04-05 12:56:37', 'etudiant',  1, 0.00),
(23, 'Client5',        'client5@demo.fr',         '$2y$12$Hi1DWYFhapCJiRDRRYTM8ezlMOhBptPN8hyZeC6IUzmuGm15sbxO6', NULL, '2026-04-05 12:56:48', 'etudiant',  1, 0.00),
(24, 'Admin1',         'admin1@demo.fr',           '$2y$12$xKTiwg7YTvvnfDQ56XDjn.YkrE/ET4SD8geOrBYQxNH/T/PXBNl6q', NULL, '2026-04-05 12:57:04', 'fakeadmin', 1, 0.00),
(25, 'Admin2',         'admin2@demo.fr',           '$2y$12$nDujeOBvzdiu7HofEWHI9.M3EKLo2RPtS7RgBb6ownsVz0yXFKwD.', NULL, '2026-04-05 12:57:13', 'fakeadmin', 1, 0.00),
(26, 'Garfield',       'garfield@demo.fr',         '$2y$12$RBDKz2J/uZ9Ni75a/FmXLORITDnTX/5AFBJcFyE1ou75dE0WHaP3W', NULL, '2026-04-05 14:35:57', 'etudiant',  1, 0.00);
UNLOCK TABLES;
