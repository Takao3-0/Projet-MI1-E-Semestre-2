/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.4.7-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: commandes_yumland
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
-- Table structure for table `articles`
--

DROP TABLE IF EXISTS `articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `articles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` int(11) DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `prix` decimal(5,2) NOT NULL,
  `categorie` varchar(50) NOT NULL,
  `type_post` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles`
--

LOCK TABLES `articles` WRITE;
/*!40000 ALTER TABLE `articles` DISABLE KEYS */;
INSERT INTO `articles` VALUES
(1,1001,'CY Smash Burger',8.90,'Burger','burger','Double steak, cheddar, sauce maison'),
(2,1002,'Triple Cheese Burger',10.90,'Burger','burger','Triple steak haché, cheddar fondu, sauce maison, oignons croustillants'),
(3,1003,'Chicken Burger',7.90,'Burger','burger','Filet de poulet pané, salade, tomate, mayo citronnée'),
(4,1004,'Veggie Burger',8.50,'Burger','burger','Galette de légumes, fromage, roquette, sauce yaourt'),
(5,1005,'Bacon King',11.90,'Burger','burger','Triple steak, bacon grillé, cheddar, BBQ'),
(6,1101,'Pizza Margherita',9.50,'Pizza','pizza','Sauce tomate, mozzarella, basilic frais'),
(7,1102,'Pizza 4 Fromages',11.00,'Pizza','pizza','Mozzarella, gorgonzola, chèvre, parmesan'),
(8,1103,'Pizza Pepperoni',10.50,'Pizza','pizza','Sauce tomate, mozzarella, pepperoni piquant'),
(9,1201,'Wrap Poulet Avocat',7.50,'Wrap','wrap','Poulet grillé, avocat, salade, sauce ranch'),
(10,1202,'Tacos Classique',8.50,'Tacos','wrap','Viande hachée, frites, sauce fromagère, salade'),
(11,1203,'Tacos XL',10.90,'Tacos','wrap','Double viande, double fromage, frites, sauce algérienne'),
(12,1301,'Frites Maison',3.50,'Accompagnement','side','Frites fraîches, sel de Guérande'),
(13,1302,'Nuggets x8',5.90,'Accompagnement','side','Nuggets croustillants avec sauce au choix'),
(14,1303,'Onion Rings',4.50,'Accompagnement','side','Rondelles d\'oignon panées, sauce barbecue'),
(15,1401,'Cookie Géant',2.90,'Dessert','dessert','Pépites de chocolat, tout chaud'),
(16,1402,'Brownie',3.50,'Dessert','dessert','Brownie fondant au chocolat noir'),
(17,1403,'Glace 2 Boules',4.00,'Dessert','dessert','Vanille, chocolat, fraise ou caramel'),
(18,1501,'Coca-Cola 33cl',2.00,'Boisson','boisson','Canette classique bien fraîche'),
(19,1502,'Eau Minérale 50cl',1.50,'Boisson','boisson','Eau plate ou pétillante'),
(20,1503,'Jus d\'Orange Frais',3.50,'Boisson','boisson','Pressé sur place, 100% pur jus'),
(21,1006,'Le thibault',10.50,'Burger','burger','Test'),
(22,1007,'Le thibault',10.50,'Burger','burger','Test'),
(23,1504,'Ice Tea 50cl',2.00,'Boisson','boisson','Ice Tea'),
(24,1104,'La Abdel',13.50,'Pizza','pizza','Une pizza au jambon, bacon, merguez.');
/*!40000 ALTER TABLE `articles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `avis_clients`
--

DROP TABLE IF EXISTS `avis_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `avis_clients` (
  `id_avis` int(11) NOT NULL AUTO_INCREMENT,
  `id_client` int(11) NOT NULL,
  `nom_client` varchar(100) NOT NULL,
  `mail_client` varchar(255) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `note_livraison` tinyint(4) DEFAULT NULL CHECK (`note_livraison` >= 0 and `note_livraison` <= 5),
  `note_qualite` tinyint(4) DEFAULT NULL CHECK (`note_qualite` >= 0 and `note_qualite` <= 5),
  `date_avis` datetime DEFAULT current_timestamp(),
  `id_commande` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_avis`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `avis_clients`
--

LOCK TABLES `avis_clients` WRITE;
/*!40000 ALTER TABLE `avis_clients` DISABLE KEYS */;
INSERT INTO `avis_clients` VALUES
(1,0,'Anonyme','anonyme@cy-restaurant.fr','',0,0,'2026-04-05 21:12:21',NULL),
(2,0,'Anonyme','anonyme@cy-restaurant.fr','fecf',5,0,'2026-04-05 21:14:06',NULL),
(3,0,'Anonyme','anonyme@cy-restaurant.fr','Il est où ce commena',4,4,'2026-04-29 11:59:58',NULL);
/*!40000 ALTER TABLE `avis_clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commande_items`
--

DROP TABLE IF EXISTS `commande_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `commande_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `commande_id` int(11) NOT NULL,
  `produit_id` int(11) DEFAULT NULL,
  `nom` varchar(255) NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `quantite` int(11) NOT NULL,
  `paye` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 si couvert par un paiement (créditable a la suppression)',
  PRIMARY KEY (`id`),
  KEY `commande_id` (`commande_id`),
  CONSTRAINT `commande_items_ibfk_1` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commande_items`
--

LOCK TABLES `commande_items` WRITE;
/*!40000 ALTER TABLE `commande_items` DISABLE KEYS */;
INSERT INTO `commande_items` VALUES
(1,4,1005,'Bacon King',11.90,2,1),
(2,4,1102,'Pizza 4 Fromages',11.00,2,1),
(3,4,1201,'Wrap Poulet Avocat',7.50,2,1),
(4,5,1302,'Nuggets x8',5.90,1,1),
(5,5,1301,'Frites Maison',3.50,1,1),
(6,5,1503,'Jus d\'Orange Frais',3.50,1,1),
(7,6,1005,'Bacon King',11.90,1,1),
(8,7,1005,'Bacon King',11.90,1,1),
(9,7,1102,'Pizza 4 Fromages',11.00,1,1),
(10,7,1201,'Wrap Poulet Avocat',7.50,1,1),
(11,8,1102,'Pizza 4 Fromages',11.00,1,1),
(12,8,1201,'Wrap Poulet Avocat',7.50,1,1),
(13,8,1401,'Cookie Géant',2.90,1,1),
(14,9,1001,'CY Smash Burger',8.90,1,1),
(15,9,1102,'Pizza 4 Fromages',11.00,1,1),
(16,9,1203,'Tacos XL',10.90,1,1),
(17,10,1005,'Bacon King',11.90,2,1),
(18,10,1003,'Chicken Burger',7.90,1,1),
(19,10,1102,'Pizza 4 Fromages',11.00,1,1),
(20,10,1201,'Wrap Poulet Avocat',7.50,2,1),
(21,10,1203,'Tacos XL',10.90,1,1),
(22,10,1301,'Frites Maison',3.50,1,1),
(23,10,1403,'Glace 2 Boules',4.00,1,1),
(24,10,1503,'Jus d\'Orange Frais',3.50,1,1),
(25,11,1001,'CY Smash Burger',8.90,2,1),
(26,12,1203,'Tacos XL',10.90,1,1),
(27,12,1301,'Frites Maison',3.50,1,1),
(28,12,1501,'Coca-Cola 33cl',2.00,1,1),
(29,13,1201,'Wrap Poulet Avocat',7.50,1,1),
(30,13,1202,'Tacos Classique',8.50,1,1),
(31,14,-3,'Menu Maxi Family',25.00,1,1),
(32,14,-2,'Menu Bucket Chicken',15.90,1,1),
(33,15,-2,'Menu Bucket Chicken',15.90,1,1),
(34,15,1007,'Le thibault',10.50,1,1),
(35,15,1302,'Nuggets x8',5.90,1,1),
(36,16,-1,'Menu Best-of Burger',10.50,1,1),
(37,16,1006,'Le thibault',10.50,1,1),
(38,16,1501,'Coca-Cola 33cl',2.00,1,1),
(39,16,1502,'Eau Minérale 50cl',1.50,1,1),
(40,17,1104,'La Abdel',13.50,1,1),
(41,18,1102,'Pizza 4 Fromages',11.00,7,1),
(42,19,-3,'Menu Maxi Family',25.00,1,1),
(43,19,1102,'Pizza 4 Fromages',11.00,1,1),
(44,19,1504,'Ice Tea 50cl',2.00,1,1),
(45,20,-2,'Menu Bucket Chicken',15.90,1,1),
(47,21,1001,'CY Smash Burger',8.90,1,1),
(48,20,1001,'CY Smash Burger',8.90,1,1),
(49,20,1303,'Onion Rings',4.50,1,1),
(50,20,1203,'Tacos XL',10.90,1,1);
/*!40000 ALTER TABLE `commande_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commandes`
--

DROP TABLE IF EXISTS `commandes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `commandes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `date_commande` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `nb_articles` int(11) NOT NULL,
  `statut` varchar(50) DEFAULT 'en_attente',
  `statut_production` varchar(255) DEFAULT 'En attente cuisine',
  `livreur_ID` int(11) DEFAULT 0,
  `heure_livraison` time DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `code_postal` varchar(10) DEFAULT NULL,
  `ville` varchar(100) DEFAULT NULL,
  `pays` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `montant_paye` decimal(10,2) NOT NULL DEFAULT 0.00,
  `frais_livraison` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commandes`
--

LOCK TABLES `commandes` WRITE;
/*!40000 ALTER TABLE `commandes` DISABLE KEYS */;
INSERT INTO `commandes` VALUES
(1,NULL,NULL,NULL,0.00,0,'Erreur paiment CYBANK','NULL',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0.00,0.00),
(2,NULL,NULL,NULL,27.40,3,'En attente','NULL',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,27.40,0.00),
(3,NULL,NULL,NULL,27.40,3,'Payé','NULL',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,27.40,0.00),
(4,2,NULL,'2026-04-03 21:10:19',60.80,6,'Payé','Livré',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,60.80,0.00),
(5,2,NULL,'2026-04-03 21:10:49',12.90,3,'Payé','Livré',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,12.90,0.00),
(6,2,NULL,'2026-04-03 21:50:59',11.90,1,'Payé','Livré',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,11.90,0.00),
(7,2,'a.gourdon02@gmail.com','2026-04-03 22:01:13',30.40,3,'Payé','Livré',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,30.40,0.00),
(8,2,'a.gourdon02@gmail.com','2026-04-03 22:03:38',21.40,3,'Payé','Récupérée livreur',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,21.40,0.00),
(9,16,NULL,'2026-04-04 16:12:54',30.80,3,'Payé','Récupérée livreur',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,30.80,0.00),
(10,2,'a.gourdon02@gmail.com','2026-04-04 21:30:20',79.60,10,'Payé','Livré',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,79.60,0.00),
(11,11,'thibault.joubert.lafont@gmail.com','2026-04-05 14:56:11',17.80,2,'Payé','Récupérée livreur',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,17.80,0.00),
(12,11,'thibault.joubert.lafont@gmail.com','2026-04-05 15:00:53',16.40,3,'Payé','Récupérée livreur',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,16.40,0.00),
(13,2,'a.gourdon02@gmail.com','2026-04-05 23:29:16',16.00,2,'Payé','Récupérée livreur',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,16.00,0.00),
(14,2,'a.gourdon02@gmail.com','2026-04-06 01:39:19',40.90,2,'Payé','Livré',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,40.90,0.00),
(15,2,'a.gourdon02@gmail.com','2026-04-06 06:32:01',32.30,3,'Payé','Livré',0,NULL,'9 Boulevard Aragao','75005','Paris',NULL,'+33643399220','Gourdon','Alexandre',32.30,0.00),
(16,2,'a.gourdon02@gmail.com','2026-04-07 10:13:00',24.50,4,'Payé','Livré',0,NULL,'9 BOULEVARD ARAGO, Batiment C Boite 42 Etage 6 Appart6','75005','Paris',NULL,'+33643399220','Gourdon','Alexandre',24.50,0.00),
(17,2,'a.gourdon02@gmail.com','2026-04-07 12:44:42',13.50,1,'Payé','Livré',0,NULL,'9 BOULEVARD ARAGO, Batiment C Boite 42 Etage 6 Appart6','75013','Paris',NULL,'+33643399220','Gourdon','Alexandre',13.50,0.00),
(18,27,'jd@mail.com','2026-04-29 13:49:20',77.00,7,'Payé','Livré',0,NULL,'12 rue du poney qui tousse','95000','Cergy',NULL,'0626793198','Doe','Jaune',77.00,0.00),
(19,2,'a.gourdon02@gmail.com','2026-05-08 23:28:53',38.00,3,'Payé','Livré',0,NULL,'Paris, Île-de-France, France','75005','Paris 5e Arrondissement',NULL,'+33643399220','Gourdon','Alexandre',38.00,0.00),
(20,2,'a.gourdon02@gmail.com','2026-05-08 23:46:42',40.20,4,'Payé','En attente cuisine',0,NULL,'Chez RED','75005','Paris',NULL,'+33643399220','Gourdon','Alexandre',15.90,0.00),
(21,2,'a.gourdon02@gmail.com','2026-05-09 09:34:17',8.90,1,'Payé','Récupérée livreur',18,NULL,'Paris, Île-de-France, France','75005','Paris',NULL,'+33643399220','Gourdon','Alexandre',8.90,0.00);
/*!40000 ALTER TABLE `commandes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `composition_menu`
--

DROP TABLE IF EXISTS `composition_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `composition_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code_menu` int(11) NOT NULL,
  `code_article` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `composition_menu`
--

LOCK TABLES `composition_menu` WRITE;
/*!40000 ALTER TABLE `composition_menu` DISABLE KEYS */;
INSERT INTO `composition_menu` VALUES
(1,1,1001),
(2,1,1301),
(3,1,1501),
(4,2,1101),
(5,2,1503),
(6,2,1401),
(7,3,1005),
(8,3,1501),
(9,3,1301),
(10,3,1005),
(11,3,1501),
(12,3,1301);
/*!40000 ALTER TABLE `composition_menu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `menus`
--

DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `menus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code_menu` int(11) NOT NULL,
  `nom_menu` varchar(255) NOT NULL,
  `prix` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_menu` (`code_menu`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `menus`
--

LOCK TABLES `menus` WRITE;
/*!40000 ALTER TABLE `menus` DISABLE KEYS */;
INSERT INTO `menus` VALUES
(1,1,'Menu Best-of Burger',10.50,'Le grand classique : 1 Cy Smash Burger, 1 frite maison, 1 Coca-Cola 50cl.'),
(2,2,'Menu Bucket Chicken',15.90,'Menu Pizza : 1 Pizza Margherita, 1 Jus d\'Orange Frais et son Cookie Géant.'),
(3,3,'Menu Maxi Family',25.00,'Le pack à deux : 2 Bacon King, 2 frites maison, et 2 Ice Tea 50cl.');
/*!40000 ALTER TABLE `menus` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-05-12 18:36:52
