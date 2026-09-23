<?php
// includes/db.php - PPSA PDO Database Connection & Transaction Helper

require_once __DIR__ . '/../config/app.php';

/**
 * Returns the singleton PDO connection instance
 * @return PDO|null
 */
function getPpsaDb(): ?PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
    $dbName = defined('DB_NAME') ? DB_NAME : '';
    $dbUser = defined('DB_USER') ? DB_USER : '';
    $dbPass = defined('DB_PASSWORD') ? DB_PASSWORD : (defined('DB_PASS') ? DB_PASS : '');

    if (empty($dbName) || empty($dbUser)) {
        // Database credentials not yet configured
        return null;
    }

    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("PPSA Database Connection Error: " . $e->getMessage());
        return null;
    }
}

/**
 * Atomic Registration Sequence Number Generator
 * Generates e.g. PPSA-ATH-2026-000001 or PPSA-OFF-2026-000001
 * Uses row locking (FOR UPDATE) within transactions to guarantee uniqueness
 */
function generatePpsaSequenceNo(PDO $db, string $type = 'athlete', int $year = 2026): string {
    $prefix = ($type === 'athlete') ? 'PPSA-ATH' : 'PPSA-OFF';

    // Lock sequence row
    $stmt = $db->prepare("
        SELECT last_sequence_number 
        FROM ppsa_registration_sequences 
        WHERE sequence_type = ? AND sequence_year = ? 
        FOR UPDATE
    ");
    $stmt->execute([$type, $year]);
    $row = $stmt->fetch();

    if ($row) {
        $nextSeq = (int)$row['last_sequence_number'] + 1;
        $upStmt = $db->prepare("
            UPDATE ppsa_registration_sequences 
            SET last_sequence_number = ? 
            WHERE sequence_type = ? AND sequence_year = ?
        ");
        $upStmt->execute([$nextSeq, $type, $year]);
    } else {
        $nextSeq = 1;
        $insStmt = $db->prepare("
            INSERT INTO ppsa_registration_sequences 
            (sequence_type, sequence_year, last_sequence_number) 
            VALUES (?, ?, 1)
        ");
        $insStmt->execute([$type, $year]);
    }

    return sprintf("%s-%d-%06d", $prefix, $year, $nextSeq);
}

/**
 * Log administrative or system action into audit trail
 */
function ppsaAuditLog(?int $userId, string $action, string $entityType, ?int $entityId, array $metadata = []): void {
    $db = getPpsaDb();
    if (!$db) return;

    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI', 0, 255);
        $json = !empty($metadata) ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $db->prepare("
            INSERT INTO ppsa_audit_logs 
            (user_id, action, entity_type, entity_id, ip_address, user_agent, details_json, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $action, $entityType, $entityId, $ip, $ua, $json]);
    } catch (\Throwable $e) {
        error_log("Failed to write PPSA audit log: " . $e->getMessage());
    }
}
