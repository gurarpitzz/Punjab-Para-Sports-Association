-- ============================================================================
-- PUNJAB PARA SPORTS ASSOCIATION (PPSA) - INDEPENDENT DATABASE SCHEMA
-- Compatible with MySQL 5.7+ / 8.0+ / MariaDB 10.3+
-- Zero dependency on any external federations or databases
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Administrative Users & RBAC
CREATE TABLE IF NOT EXISTS `ppsa_users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(60) NOT NULL UNIQUE,
    `email` VARCHAR(120) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('admin', 'reviewer', 'classifier') NOT NULL DEFAULT 'reviewer',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ppsa_user_username` (`username`),
    INDEX `idx_ppsa_user_role` (`role`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Initial Superadmin Account (Username: admin / Password: PPSA@Admin2026!)
INSERT IGNORE INTO `ppsa_users` (`username`, `email`, `password_hash`, `full_name`, `role`, `is_active`) VALUES
('admin', 'admin@punjabparasports.org', '$2y$12$aThOj8QpvSK21WD.HulrxusgmrJW2EZfWqF5Av0Gvf9RFgQcP7xia', 'PPSA Administrator', 'admin', 1);

-- 2. Concurrency-Safe Atomic Registration Sequence Tracker
CREATE TABLE IF NOT EXISTS `ppsa_registration_sequences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sequence_type` VARCHAR(30) NOT NULL, -- 'athlete', 'official'
    `sequence_year` INT NOT NULL,
    `last_sequence_number` INT NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_ppsa_seq` (`sequence_type`, `sequence_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialize 2026 sequences if not present
INSERT IGNORE INTO `ppsa_registration_sequences` (`sequence_type`, `sequence_year`, `last_sequence_number`) VALUES
('athlete', 2026, 0),
('official', 2026, 0);

-- 3. Athlete Applications Intake Queue (Under Review / Approvals)
CREATE TABLE IF NOT EXISTS `ppsa_athlete_applications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_id` VARCHAR(50) NOT NULL UNIQUE, -- e.g. PPSA-APP-ATH-2026-104928
    `permanent_registration_no` VARCHAR(50) NULL UNIQUE, -- e.g. PPSA-ATH-2026-000001 (assigned on approval)
    
    -- Personal Information
    `full_name` VARCHAR(120) NOT NULL,
    `gender` ENUM('male', 'female', 'other') NOT NULL,
    `dob` DATE NOT NULL,
    `father_name` VARCHAR(120) NOT NULL,
    `mother_name` VARCHAR(120) NOT NULL,
    `mobile_phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    
    -- Identity & Address
    `aadhaar_number` VARCHAR(20) NOT NULL,
    `full_address` TEXT NOT NULL,
    `district` VARCHAR(60) NOT NULL,
    `state` VARCHAR(60) NOT NULL DEFAULT 'Punjab',
    `pincode` VARCHAR(10) NULL,
    
    -- Sport & Event Structure (Ludhiana State Games)
    `sport_game` VARCHAR(60) NOT NULL, -- 'para_athletics', 'powerlifting', 'para_badminton', 'wheelchair_basketball'
    `classification` VARCHAR(60) NULL, -- e.g. 'F-51 (WC)', 'WH-1', etc.
    `weight_category` VARCHAR(60) NULL, -- e.g. '55 kg' (Powerlifting)
    `event_discipline` VARCHAR(120) NOT NULL, -- e.g. 'Discus Throw', 'Bench Press', 'Men Single'
    `impairment_type` VARCHAR(100) NULL,
    `venue` VARCHAR(100) NOT NULL DEFAULT 'Ludhiana',
    
    -- Document File Paths (Quarantined Storage)
    `photo_path` VARCHAR(255) NULL,
    `id_proof_path` VARCHAR(255) NULL,
    `medical_certificate_path` VARCHAR(255) NULL,
    
    -- Review Workflow Status
    `status` ENUM('pending', 'under_review', 'approved', 'rejected', 'correction_required') NOT NULL DEFAULT 'pending',
    `rejection_reason` TEXT NULL,
    `correction_notes` TEXT NULL,
    `has_duplicate_flag` TINYINT(1) NOT NULL DEFAULT 0,
    `duplicate_matched_id` INT NULL,
    
    -- Metadata & Auditing
    `reviewed_by_user_id` INT NULL,
    `reviewed_at` DATETIME NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX `idx_ath_status` (`status`),
    INDEX `idx_ath_sport` (`sport_game`),
    INDEX `idx_ath_district` (`district`),
    INDEX `idx_ath_aadhaar` (`aadhaar_number`),
    INDEX `idx_ath_mobile` (`mobile_phone`),
    INDEX `idx_ath_email` (`email`),
    FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `ppsa_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Approved Master Athlete Registry
CREATE TABLE IF NOT EXISTS `ppsa_athletes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `registration_no` VARCHAR(50) NOT NULL UNIQUE, -- PPSA-ATH-2026-000001
    `application_id` INT NOT NULL UNIQUE,
    `full_name` VARCHAR(120) NOT NULL,
    `gender` ENUM('male', 'female', 'other') NOT NULL,
    `dob` DATE NOT NULL,
    `mobile_phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    `district` VARCHAR(60) NOT NULL,
    `sport_game` VARCHAR(60) NOT NULL,
    `classification` VARCHAR(60) NULL,
    `weight_category` VARCHAR(60) NULL,
    `event_discipline` VARCHAR(120) NOT NULL,
    `photo_path` VARCHAR(255) NULL,
    `approved_by_user_id` INT NULL,
    `approved_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_reg_sport` (`sport_game`),
    INDEX `idx_reg_district` (`district`),
    FOREIGN KEY (`application_id`) REFERENCES `ppsa_athlete_applications`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`approved_by_user_id`) REFERENCES `ppsa_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4a. Athlete Sports Participations (One Athlete -> Multiple Sports)
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

-- 4b. Athlete Events Participations (One Sport -> Multiple Permitted Events)
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

-- 5. Official Applications Intake Queue
CREATE TABLE IF NOT EXISTS `ppsa_official_applications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reference_id` VARCHAR(50) NOT NULL UNIQUE, -- e.g. PPSA-APP-OFF-2026-104928
    `permanent_registration_no` VARCHAR(50) NULL UNIQUE, -- e.g. PPSA-OFF-2026-000001
    
    `full_name` VARCHAR(120) NOT NULL,
    `gender` ENUM('male', 'female', 'other') NOT NULL,
    `dob` DATE NOT NULL,
    `mobile_phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    `aadhaar_number` VARCHAR(20) NOT NULL,
    `district` VARCHAR(60) NOT NULL,
    `state` VARCHAR(60) NOT NULL DEFAULT 'Punjab',
    
    -- Role Category
    `official_category` VARCHAR(60) NOT NULL, -- 'coach', 'referee', 'volunteer', 'classifier', 'ramp_operator', 'escort'
    `classifier_type` VARCHAR(60) NULL, -- 'physio', 'doctor', 'coach', 'other'
    `qualifications` TEXT NULL,
    `experience_years` INT NULL DEFAULT 0,
    
    -- Documents
    `photo_path` VARCHAR(255) NULL,
    `id_proof_path` VARCHAR(255) NULL,
    `cert_proof_path` VARCHAR(255) NULL,
    
    `status` ENUM('pending', 'under_review', 'approved', 'rejected', 'correction_required') NOT NULL DEFAULT 'pending',
    `rejection_reason` TEXT NULL,
    `correction_notes` TEXT NULL,
    `has_duplicate_flag` TINYINT(1) NOT NULL DEFAULT 0,
    
    `reviewed_by_user_id` INT NULL,
    `reviewed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_off_status` (`status`),
    INDEX `idx_off_category` (`official_category`),
    FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `ppsa_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Approved Master Officials Registry
CREATE TABLE IF NOT EXISTS `ppsa_officials` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `registration_no` VARCHAR(50) NOT NULL UNIQUE, -- PPSA-OFF-2026-000001
    `application_id` INT NOT NULL UNIQUE,
    `full_name` VARCHAR(120) NOT NULL,
    `official_category` VARCHAR(60) NOT NULL,
    `mobile_phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    `district` VARCHAR(60) NOT NULL,
    `photo_path` VARCHAR(255) NULL,
    `approved_by_user_id` INT NULL,
    `approved_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`application_id`) REFERENCES `ppsa_official_applications`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`approved_by_user_id`) REFERENCES `ppsa_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Email OTP Tracking & Verification
CREATE TABLE IF NOT EXISTS `ppsa_email_otps` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(120) NOT NULL,
    `otp_hash` VARCHAR(255) NOT NULL,
    `purpose` VARCHAR(40) NOT NULL DEFAULT 'athlete_registration',
    `attempts_count` INT NOT NULL DEFAULT 0,
    `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
    `expires_at` DATETIME NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_otp_email` (`email`, `is_verified`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. OTP Rate Limiting
CREATE TABLE IF NOT EXISTS `ppsa_otp_rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `identifier` VARCHAR(120) NOT NULL, -- IP address or email
    `action_type` VARCHAR(30) NOT NULL, -- 'send_otp', 'verify_otp'
    `request_count` INT NOT NULL DEFAULT 1,
    `window_started_at` DATETIME NOT NULL,
    UNIQUE KEY `uk_rate_window` (`identifier`, `action_type`, `window_started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Duplicate Detection Collision Log
CREATE TABLE IF NOT EXISTS `ppsa_duplicate_flags` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT NOT NULL,
    `matched_application_id` INT NULL,
    `matched_athlete_id` INT NULL,
    `match_field` VARCHAR(40) NOT NULL, -- 'aadhaar', 'mobile', 'email', 'name_dob'
    `severity` ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'warning',
    `details` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`application_id`) REFERENCES `ppsa_athlete_applications`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Audit Trail & Action Logs
CREATE TABLE IF NOT EXISTS `ppsa_audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `action` VARCHAR(60) NOT NULL, -- 'login', 'approve_athlete', 'reject_athlete', 'request_correction', etc.
    `entity_type` VARCHAR(40) NOT NULL, -- 'ppsa_athlete_applications', 'ppsa_users', etc.
    `entity_id` INT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `details_json` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_action` (`action`),
    INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Transactional Email Dispatch Logs
CREATE TABLE IF NOT EXISTS `ppsa_email_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipient_email` VARCHAR(120) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `template_type` VARCHAR(50) NOT NULL, -- 'otp', 'application_received', 'approved', 'rejected'
    `status` ENUM('sent', 'failed', 'queued') NOT NULL DEFAULT 'sent',
    `resend_message_id` VARCHAR(100) NULL,
    `error_details` TEXT NULL,
    `sent_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Application Status Transition History
CREATE TABLE IF NOT EXISTS `ppsa_athlete_status_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `application_id` INT NOT NULL,
    `from_status` VARCHAR(30) NOT NULL,
    `to_status` VARCHAR(30) NOT NULL,
    `changed_by_user_id` INT NULL,
    `reason_notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`application_id`) REFERENCES `ppsa_athlete_applications`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`changed_by_user_id`) REFERENCES `ppsa_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
