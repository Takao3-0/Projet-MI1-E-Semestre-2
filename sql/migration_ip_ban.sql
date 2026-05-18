-- Base : espace_etudiant
-- À exécuter une fois sur le serveur MariaDB (migration Phase 3).

-- Dernière IP connue (mise à jour à chaque connexion réussie) — sert à bannir l’IP lors d’un blocage admin.
ALTER TABLE `users`
    ADD COLUMN `last_login_ip` VARCHAR(45) DEFAULT NULL AFTER `credit`;

-- Blocages compte utilisateur (actif si date_deblocage IS NULL).
CREATE TABLE IF NOT EXISTS `blocages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `bloque_par` int(11) NOT NULL,
    `motif` text DEFAULT NULL,
    `date_blocage` datetime NOT NULL DEFAULT current_timestamp(),
    `date_deblocage` datetime DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_user_actif` (`user_id`, `date_deblocage`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

-- Bannissements IP (actif si date_deban IS NULL).
CREATE TABLE IF NOT EXISTS `bannissements_ip` (
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
