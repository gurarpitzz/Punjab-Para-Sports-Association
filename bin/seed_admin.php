<?php
// bin/seed_admin.php - Secure Administrative Provisioning Script
// Can be executed via CLI: php bin/seed_admin.php

if (php_sapi_name() !== 'cli' && (!isset($_GET['key']) || $_GET['key'] !== getenv('SEED_KEY'))) {
    die("Access denied. Run this script via command line.\n");
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/db.php';

$db = getPpsaDb();
if (!$db) {
    echo "[ERROR] Database connection failed. Check your DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD environment variables.\n";
    exit(1);
}

$adminUsername = $argv[1] ?? ppsa_config('PPSA_ADMIN_USERNAME', 'admin');
$adminEmail    = ppsa_config('PPSA_ADMIN_EMAIL', 'admin@punjabparasports.org');
$adminPass     = $argv[2] ?? ppsa_config('PPSA_ADMIN_PASSWORD', 'PPSA@Admin2026!');

$generated = false;

$hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);

// Check if user exists by username or email
$stmt = $db->prepare("SELECT id FROM ppsa_users WHERE username = ? OR email = ?");
$stmt->execute([$adminUsername, $adminEmail]);
$existing = $stmt->fetch();

if ($existing) {
    $upStmt = $db->prepare("UPDATE ppsa_users SET username = ?, password_hash = ?, is_active = 1, role = 'admin' WHERE id = ?");
    $upStmt->execute([$adminUsername, $hash, $existing['id']]);
    echo "[SUCCESS] Updated existing administrator account: {$adminUsername}\n";
} else {
    $insStmt = $db->prepare("INSERT INTO ppsa_users (username, email, password_hash, full_name, role, is_active) VALUES (?, ?, ?, 'PPSA Administrator', 'admin', 1)");
    $insStmt->execute([$adminUsername, $adminEmail, $hash]);
    echo "[SUCCESS] Created new administrator account: {$adminUsername}\n";
}

echo "====================================================================\n";
echo "ADMINISTRATIVE ACCESS CREDENTIALS\n";
echo "Username: {$adminUsername}\n";
if ($generated) {
    echo "Password: {$adminPass} (Auto-generated temporary password)\n";
    echo "Please copy this password now and change it upon first login!\n";
} else {
    echo "Password: (Set from environment variable PPSA_ADMIN_PASSWORD)\n";
}
echo "====================================================================\n";
