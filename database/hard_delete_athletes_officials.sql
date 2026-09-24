-- ============================================================================
-- PUNJAB PARA SPORTS ASSOCIATION (PPSA)
-- HARD DELETE SCRIPT: ATHLETES & OFFICIALS (InnoDB Safe via DELETE + AUTO_INCREMENT)
-- Target Database: tstpllmy_ppsa
-- Avoids MySQL #1701 TRUNCATE foreign key constraint limitation
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Athlete Multi-Sport & Multi-Event Relational Data
DELETE FROM `ppsa_athlete_events`;
DELETE FROM `ppsa_athlete_sports`;

-- 2. Athlete Application Logs & History
DELETE FROM `ppsa_athlete_status_history`;
DELETE FROM `ppsa_duplicate_flags`;

-- 3. Master Athletes & Applications
DELETE FROM `ppsa_athletes`;
DELETE FROM `ppsa_athlete_applications`;

-- 4. Master Officials & Applications
DELETE FROM `ppsa_officials`;
DELETE FROM `ppsa_official_applications`;

-- 5. Reset AUTO_INCREMENT Counters to 1
ALTER TABLE `ppsa_athlete_events` AUTO_INCREMENT = 1;
ALTER TABLE `ppsa_athlete_sports` AUTO_INCREMENT = 1;
ALTER TABLE `ppsa_athlete_status_history` AUTO_INCREMENT = 1;
ALTER TABLE `ppsa_duplicate_flags` AUTO_INCREMENT = 1;
ALTER TABLE `ppsa_athletes` AUTO_INCREMENT = 1;
ALTER TABLE `ppsa_athlete_applications` AUTO_INCREMENT = 1;
ALTER TABLE `ppsa_officials` AUTO_INCREMENT = 1;
ALTER TABLE `ppsa_official_applications` AUTO_INCREMENT = 1;

-- 6. Reset Registration Sequence Tracker to 0 (Fresh PPSA-ATH-2026-000001)
UPDATE `ppsa_registration_sequences` 
SET `last_sequence_number` = 0 
WHERE `sequence_type` IN ('athlete', 'official');

-- 7. Clean OTP Rate Limits & Expired OTPs
DELETE FROM `ppsa_email_otps`;
DELETE FROM `ppsa_otp_rate_limits`;
ALTER TABLE `ppsa_email_otps` AUTO_INCREMENT = 1;
ALTER TABLE `ppsa_otp_rate_limits` AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

-- Verification: Confirm all target tables are completely empty
SELECT 'ppsa_athlete_applications' AS table_name, COUNT(*) AS remaining_rows FROM `ppsa_athlete_applications`
UNION ALL
SELECT 'ppsa_athletes', COUNT(*) FROM `ppsa_athletes`
UNION ALL
SELECT 'ppsa_athlete_sports', COUNT(*) FROM `ppsa_athlete_sports`
UNION ALL
SELECT 'ppsa_athlete_events', COUNT(*) FROM `ppsa_athlete_events`
UNION ALL
SELECT 'ppsa_official_applications', COUNT(*) FROM `ppsa_official_applications`
UNION ALL
SELECT 'ppsa_officials', COUNT(*) FROM `ppsa_officials`;
