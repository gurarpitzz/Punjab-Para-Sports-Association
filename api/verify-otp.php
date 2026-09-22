<?php
// api/verify-otp.php - Punjab Para Sports Association OTP Verification Endpoint

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($_POST['email'] ?? $input['email'] ?? '');
$otp   = trim($_POST['otp'] ?? $input['otp'] ?? '');

if (empty($email) || empty($otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Email and 6-digit OTP code are required.']);
    exit();
}

$emailKey = strtolower($email);
$sessionData = $_SESSION['ppsa_otp_' . md5($emailKey)] ?? null;

// Check file fallback if session was not found
if (!$sessionData) {
    $tempFile = __DIR__ . '/../scratch/otps/' . md5($emailKey) . '.json';
    if (file_exists($tempFile)) {
        $sessionData = json_decode(file_get_contents($tempFile), true);
    }
}

if (!$sessionData) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No active OTP verification found for this email. Please request a new code.']);
    exit();
}

if (time() > $sessionData['expires_at']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Verification code has expired. Please request a new code.']);
    exit();
}

if (trim($sessionData['code']) !== $otp) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid verification code. Please check your email and try again.']);
    exit();
}

// Mark as verified
$_SESSION['ppsa_otp_' . md5($emailKey)]['verified'] = true;
$tempFile = __DIR__ . '/../scratch/otps/' . md5($emailKey) . '.json';
if (file_exists($tempFile)) {
    @unlink($tempFile);
}

echo json_encode([
    'success' => true,
    'verified' => true,
    'message' => 'Email verified successfully.'
]);
