-- ============================================================================
-- PPSA MULTI-SPORT & MULTI-EVENT RELATIONAL ARCHITECTURE MIGRATION
-- Compatible with MySQL 5.7+ / 8.0+ / MariaDB 10.3+ / Percona MySQL
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Create ppsa_athlete_sports Table (One Athlete -> Multiple Sports)
CREATE TABLE IF NOT EXISTS `ppsa_athlete_sports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NULL,                         -- Identity Anchor (points to ppsa_athletes.id once approved)
    `application_id` INT NOT NULL,                 -- Originating Request Container
    `sport_game` VARCHAR(60) NOT NULL,
    `classification` VARCHAR(60) NULL,
    `weight_category` VARCHAR(60) NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `review_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_sport_athlete` (`athlete_id`),
    INDEX `idx_sport_app` (`application_id`),
    INDEX `idx_sport_status` (`status`),
    INDEX `idx_sport_game` (`sport_game`),
    UNIQUE KEY `uk_app_sport_class` (`application_id`, `sport_game`, `classification`),
    FOREIGN KEY (`athlete_id`) REFERENCES `ppsa_athletes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`application_id`) REFERENCES `ppsa_athlete_applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create ppsa_athlete_events Table (One Sport -> Multiple Permitted Events)
CREATE TABLE IF NOT EXISTS `ppsa_athlete_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_sport_id` INT NOT NULL,
    `event_discipline` VARCHAR(120) NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `review_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ev_sport` (`athlete_sport_id`),
    INDEX `idx_ev_status` (`status`),
    UNIQUE KEY `uk_sport_event` (`athlete_sport_id`, `event_discipline`),
    FOREIGN KEY (`athlete_sport_id`) REFERENCES `ppsa_athlete_sports`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- 3. Safe Backfill from existing applications (if any exist)
-- Backfill ppsa_athlete_sports for legacy applications
INSERT IGNORE INTO `ppsa_athlete_sports` 
(`application_id`, `athlete_id`, `sport_game`, `classification`, `weight_category`, `status`, `created_at`)
SELECT 
    a.id AS application_id,
    ath.id AS athlete_id,
    a.sport_game,
    a.classification,
    a.weight_category,
    CASE 
        WHEN a.status = 'approved' THEN 'approved'
        WHEN a.status = 'rejected' THEN 'rejected'
        ELSE 'pending'
    END AS status,
    a.created_at
FROM `ppsa_athlete_applications` a
LEFT JOIN `ppsa_athletes` ath ON ath.application_id = a.id
WHERE a.sport_game IS NOT NULL AND a.sport_game != '';

-- Backfill ppsa_athlete_events for legacy applications
INSERT IGNORE INTO `ppsa_athlete_events`
(`athlete_sport_id`, `event_discipline`, `status`, `created_at`)
SELECT 
    s.id AS athlete_sport_id,
    a.event_discipline,
    s.status,
    s.created_at
FROM `ppsa_athlete_sports` s
JOIN `ppsa_athlete_applications` a ON a.id = s.application_id
WHERE a.event_discipline IS NOT NULL AND a.event_discipline != '';
