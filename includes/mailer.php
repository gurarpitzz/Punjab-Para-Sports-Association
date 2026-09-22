<?php
/**
 * mailer.php — Centralized email dispatch for Punjab Para Sports Association (PPSA)
 * Provider: Resend API (api.resend.com)
 */

require_once __DIR__ . '/../config/app.php';

if (!defined('MAILER_ENABLED')) {
    define('MAILER_ENABLED', true);
}

if (!defined('MAILER_TIMEOUT')) {
    define('MAILER_TIMEOUT', 10);
}

/**
 * Send an email via Resend API
 *
 * @param string      $to          Recipient email
 * @param string      $subject     Email subject
 * @param string      $html        HTML body content
 * @param string|null $text        Plain text content
 * @return bool true on success, false on failure
 */
function sendEmail(string $to, string $subject, string $html, ?string $text = null): bool
{
    if (!MAILER_ENABLED) {
        error_log("[PPSA Mailer] Mailer disabled. Mock send to {$to}: {$subject}");
        return true;
    }

    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("[PPSA Mailer] Invalid recipient email: {$to}");
        return false;
    }

    $payload = [
        'from'    => MAILER_FROM,
        'to'      => $to,
        'subject' => $subject,
        'html'    => $html,
    ];

    if ($text !== null) {
        $payload['text'] = $text;
    }

    $headers = [
        'Authorization: Bearer ' . RESEND_API_KEY,
        'Content-Type: application/json',
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => MAILER_TIMEOUT,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);

    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("[PPSA Mailer] cURL Error: {$curlErr}");
        return false;
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        return true;
    }

    error_log("[PPSA Mailer] Resend API error (HTTP {$httpCode}): {$response}");
    return false;
}
