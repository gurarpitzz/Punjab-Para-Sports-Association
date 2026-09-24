-- ============================================================================
-- PUNJAB PARA SPORTS ASSOCIATION (PPSA)
-- HARD DELETE SCRIPT: ATHLETES & OFFICIALS CLEAN SLATE
-- Target Database: tstpllmy_ppsa
-- Description: Permanently removes all athlete applications, approved athletes,
--              sports participations, events, status histories, duplicate flags,
--              official applications, and approved officials.
--              Resets AUTO_INCREMENT and sequence counters to 0.
--              Preserves administrative users (ppsa_users) and system audit logs.
-- ============================================================================

-- Disable foreign key checks to allow clean cascading truncations
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Athlete Multi-Sport & Multi-Event Relational Data
TRUNCATE TABLE `ppsa_athlete_events`;
TRUNCATE TABLE `ppsa_athlete_sports`;

-- 2. Athlete Application Logs & History
TRUNCATE TABLE `ppsa_athlete_status_history`;
TRUNCATE TABLE `ppsa_duplicate_flags`;

-- 3. Master Athletes & Applications
TRUNCATE TABLE `ppsa_athletes`;
TRUNCATE TABLE `ppsa_athlete_applications`;

-- 4. Master Officials & Applications
TRUNCATE TABLE `ppsa_officials`;
TRUNCATE TABLE `ppsa_official_applications`;

-- 5. Reset Registration Sequence Counters to 0 (Fresh 000001 IDs for 2026)
UPDATE `ppsa_registration_sequences` 
SET `last_sequence_number` = 0 
WHERE `sequence_type` IN ('athlete', 'official');

-- 6. Clean OTP Rate Limits & Expired OTPs (Optional but recommended for testing)
TRUNCATE TABLE `ppsa_email_otps`;
TRUNCATE TABLE `ppsa_otp_rate_limits`;

-- Re-enable foreign key constraints
SET FOREIGN_KEY_CHECKS = 1;

-- Verification Check
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
