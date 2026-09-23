<?php
/**
 * mailer.php — Centralized email dispatch for Punjab Para Sports Association (PPSA)
 * Provider: Resend API (api.resend.com)
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/db.php';

if (!defined('MAILER_ENABLED')) {
    define('MAILER_ENABLED', true);
}

if (!defined('MAILER_TIMEOUT')) {
    define('MAILER_TIMEOUT', 10);
}

$GLOBALS['PPSA_LAST_MAILER_STATUS'] = [
    'success' => false,
    'dispatcher' => 'none',
    'message_id' => null,
    'error' => null,
    'http_code' => 0
];

function getPpsaLastMailerStatus(): array {
    return $GLOBALS['PPSA_LAST_MAILER_STATUS'] ?? [
        'success' => false,
        'dispatcher' => 'none',
        'message_id' => null,
        'error' => null,
        'http_code' => 0
    ];
}

/**
 * Native PHP mail() fallback for cPanel/Exim environments
 */
function sendNativePhpMail(string $to, string $subject, string $html): bool {
    $host = 'ajeetgraphics.com';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $parts = explode(':', $_SERVER['HTTP_HOST'])[0];
        if (filter_var($parts, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $host = $parts;
        }
    }
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Punjab Para Sports <noreply@' . $host . '>',
        'Reply-To: officeparapunjab@gmail.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return @mail($to, $subject, $html, implode("\r\n", $headers));
}

/**
 * Send an email via Resend API with native mail() fallback
 */
function sendEmail(string $to, string $subject, string $html, ?string $text = null, string $templateType = 'general'): bool
{
    $GLOBALS['PPSA_LAST_MAILER_STATUS'] = [
        'success' => false,
        'dispatcher' => 'none',
        'message_id' => null,
        'error' => null,
        'http_code' => 0
    ];

    if (!MAILER_ENABLED) {
        error_log("[PPSA Mailer] Mailer disabled. Mock send to {$to}: {$subject}");
        $GLOBALS['PPSA_LAST_MAILER_STATUS']['success'] = true;
        $GLOBALS['PPSA_LAST_MAILER_STATUS']['dispatcher'] = 'mock';
        return true;
    }

    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("[PPSA Mailer] Invalid recipient email: {$to}");
        $GLOBALS['PPSA_LAST_MAILER_STATUS']['error'] = "Invalid recipient email address.";
        return false;
    }

    $apiKey = defined('RESEND_API_KEY') ? RESEND_API_KEY : '';
    $resendSuccess = false;
    $messageId = null;
    $lastError = null;
    $httpCode = 0;

    if (!empty($apiKey)) {
        // Attempt 1: Using configured MAILER_FROM
        $fromCandidates = [MAILER_FROM];
        // If MAILER_FROM is not onboarding@resend.dev, add onboarding@resend.dev as automatic fallback
        if (strpos(MAILER_FROM, 'resend.dev') === false) {
            $fromCandidates[] = 'Punjab Para Sports <onboarding@resend.dev>';
        }

        foreach ($fromCandidates as $fromAddress) {
            $payload = [
                'from'    => $fromAddress,
                'to'      => $to,
                'subject' => $subject,
                'html'    => $html,
            ];
            if ($text !== null) {
                $payload['text'] = $text;
            }

            $headers = [
                'Authorization: Bearer ' . $apiKey,
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
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $resData = json_decode($response, true);
                $messageId = $resData['id'] ?? null;
                $resendSuccess = true;
                $GLOBALS['PPSA_LAST_MAILER_STATUS'] = [
                    'success' => true,
                    'dispatcher' => 'resend',
                    'message_id' => $messageId,
                    'error' => null,
                    'http_code' => $httpCode
                ];
                break;
            } else {
                $lastError = $curlErr ?: $response;
                // If error is not a domain verification error (e.g. invalid key or network issue), break early
                $resJson = json_decode($response, true);
                if (isset($resJson['message'])) {
                    $lastError = $resJson['message'];
                }
            }
        }
    } else {
        $lastError = 'RESEND_API_KEY not configured.';
    }

    if ($resendSuccess) {
        $finalSuccess = true;
        $dispatcherUsed = 'resend';
    } else {
        // Resend failed or was blocked by sandbox restrictions.
        // Fallback to PHP native mail() (works natively through cPanel/Exim)
        $nativeSent = sendNativePhpMail($to, $subject, $html);
        if ($nativeSent) {
            $finalSuccess = true;
            $dispatcherUsed = 'native_mail';
            $GLOBALS['PPSA_LAST_MAILER_STATUS'] = [
                'success' => true,
                'dispatcher' => 'native_mail',
                'message_id' => 'cpanel_' . uniqid(),
                'error' => null,
                'http_code' => 200
            ];
        } else {
            $finalSuccess = false;
            $dispatcherUsed = 'none';
            $GLOBALS['PPSA_LAST_MAILER_STATUS'] = [
                'success' => false,
                'dispatcher' => 'none',
                'message_id' => null,
                'error' => $lastError,
                'http_code' => $httpCode
            ];
            error_log("[PPSA Mailer] All dispatchers failed for {$to}. Resend Err: {$lastError}");
        }
    }

    // Log to ppsa_email_logs if database is available
    $db = getPpsaDb();
    if ($db) {
        try {
            $logStmt = $db->prepare("
                INSERT INTO ppsa_email_logs 
                (recipient_email, subject, template_type, status, resend_message_id, error_details, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $logStmt->execute([
                $to,
                $subject,
                $templateType,
                $finalSuccess ? 'sent' : 'failed',
                $messageId ?: ($dispatcherUsed === 'native_mail' ? 'native_exim' : null),
                $finalSuccess ? null : $lastError
            ]);
        } catch (\Throwable $e) {}
    }

    return $finalSuccess;
}

/**
 * Reusable PPSA Email Shell Wrapper
 */
function wrapPpsaEmailBody(string $heading, string $contentHtml): string {
    return '
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>' . htmlspecialchars($heading) . '</title>
    </head>
    <body style="margin:0;padding:0;background-color:#F8FAFC;font-family:\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased;">
      <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F8FAFC;padding:30px 10px;">
        <tr>
          <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(8,27,75,0.08);border:1px solid #E2E8F0;">
              <!-- Header Bar -->
              <tr>
                <td style="background-color:#0E1F4B;padding:26px 30px;text-align:center;">
                  <h1 style="color:#ffffff;margin:0 0 4px 0;font-size:20px;font-weight:800;letter-spacing:0.04em;">PUNJAB PARA SPORTS ASSOCIATION</h1>
                  <p style="color:#FFC400;margin:0;font-size:12px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;">STRONGER TOGETHER • ਪੰਜਾਬ ਪੈਰਾ ਸਪੋਰਟਸ</p>
                </td>
              </tr>
              <!-- Content -->
              <tr>
                <td style="padding:32px 30px;">
                  ' . $contentHtml . '
                </td>
              </tr>
              <!-- Footer -->
              <tr>
                <td style="background-color:#F1F5F9;padding:20px 30px;text-align:center;border-top:1px solid #E2E8F0;">
                  <p style="margin:0 0 6px 0;font-size:12px;color:#64748B;font-weight:600;">Punjab Para Sports Association (PPSA)</p>
                  <p style="margin:0 0 6px 0;font-size:11px;color:#94A3B8;">Recognised by Punjab State Sports Council & Affiliated with PCI</p>
                  <p style="margin:0;font-size:11px;color:#94A3B8;">Contact: officeparapunjab@gmail.com | Helpline: +91 98034-54949</p>
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>
    </body>
    </html>';
}

/**
 * Dispatch OTP Email
 */
function sendPpsaOtpEmail(string $toEmail, string $otpCode): bool {
    $subject = "Your PPSA Verification Code: {$otpCode}";
    $body = '
      <h2 style="color:#0E1F4B;font-size:18px;margin-top:0;">Email Verification Required</h2>
      <p style="color:#334155;font-size:14px;line-height:1.6;">You have initiated an athlete/official registration with the Punjab Para Sports Association. Please use the following single-use verification code to authenticate your request:</p>
      <div style="background:#EFF6FF;border:2px dashed #00B074;border-radius:8px;padding:16px;text-align:center;margin:24px 0;">
        <span style="font-family:monospace;font-size:32px;font-weight:900;letter-spacing:8px;color:#0E1F4B;">' . htmlspecialchars($otpCode) . '</span>
        <p style="margin:6px 0 0 0;font-size:12px;color:#00B074;font-weight:700;">Valid for 10 minutes</p>
      </div>
      <p style="color:#64748B;font-size:13px;line-height:1.5;">If you did not request this verification code, please ignore this email. Do not share this code with anyone.</p>
    ';
    return sendEmail($toEmail, $subject, wrapPpsaEmailBody("Email Verification", $body), "Your PPSA Verification Code: {$otpCode}. Valid for 10 minutes.", 'otp');
}

/**
 * Dispatch Application Received Confirmation Email
 */
function sendPpsaApplicationReceivedEmail(string $toEmail, string $applicantName, string $referenceId, string $sport, string $event, string $venue = 'Ludhiana'): bool {
    $subject = "PPSA Registration Received — {$referenceId}";
    $body = '
      <h2 style="color:#0E1F4B;font-size:18px;margin-top:0;">Registration Application Received</h2>
      <p style="color:#334155;font-size:14px;line-height:1.6;">Dear <strong>' . htmlspecialchars($applicantName) . '</strong>,</p>
      <p style="color:#334155;font-size:14px;line-height:1.6;">Thank you for registering with the <strong>Punjab Para Sports Association (PPSA)</strong> for the upcoming Punjab State Para Games. Your application has been logged into the administrative intake system for verification.</p>
      
      <table width="100%" cellpadding="10" cellspacing="0" style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;margin:20px 0;font-size:13px;">
        <tr><td style="color:#64748B;font-weight:600;width:40%;">Reference Tracking ID:</td><td style="color:#0E1F4B;font-weight:800;font-family:monospace;">' . htmlspecialchars($referenceId) . '</td></tr>
        <tr><td style="color:#64748B;font-weight:600;">Sport Discipline:</td><td style="color:#0E1F4B;font-weight:700;">' . htmlspecialchars(ucwords(str_replace('_', ' ', $sport))) . '</td></tr>
        <tr><td style="color:#64748B;font-weight:600;">Specific Event:</td><td style="color:#0E1F4B;font-weight:700;">' . htmlspecialchars($event) . '</td></tr>
        <tr><td style="color:#64748B;font-weight:600;">Competition Venue:</td><td style="color:#0E1F4B;font-weight:700;">' . htmlspecialchars($venue) . '</td></tr>
        <tr><td style="color:#64748B;font-weight:600;">Intake Status:</td><td style="color:#D97706;font-weight:700;">PENDING ADMINISTRATIVE REVIEW</td></tr>
      </table>

      <p style="color:#334155;font-size:14px;line-height:1.6;">Our state technical review committee will verify your identity documents and classification criteria. You will receive an email notification as soon as your profile is reviewed.</p>
    ';
    return sendEmail($toEmail, $subject, wrapPpsaEmailBody("Registration Received", $body), null, 'application_received');
}

/**
 * Dispatch Application Approved Email
 */
function sendPpsaApplicationApprovedEmail(string $toEmail, string $athleteName, string $regNo, string $sport, string $event): bool {
    $subject = "PPSA Registration Approved — {$regNo}";
    $body = '
      <div style="text-align:center;margin-bottom:20px;">
        <span style="display:inline-block;background:#DCFCE7;color:#16A34A;font-weight:800;font-size:13px;padding:6px 16px;border-radius:999px;border:1px solid #BBF7D0;">APPLICATION OFFICIALLY APPROVED</span>
      </div>
      <h2 style="color:#0E1F4B;font-size:20px;margin-top:0;text-align:center;">Congratulations, ' . htmlspecialchars($athleteName) . '!</h2>
      <p style="color:#334155;font-size:14px;line-height:1.6;">Your application to compete in the Punjab State Para Games under the <strong>Punjab Para Sports Association</strong> has been verified and approved.</p>
      
      <div style="background:#0E1F4B;border-radius:10px;padding:20px;text-align:center;margin:24px 0;color:#ffffff;">
        <span style="font-size:12px;color:#94A3B8;letter-spacing:0.1em;text-transform:uppercase;">Permanent State Registration Number</span>
        <h3 style="margin:8px 0 0 0;font-size:24px;font-family:monospace;letter-spacing:2px;color:#FFC400;">' . htmlspecialchars($regNo) . '</h3>
      </div>

      <table width="100%" cellpadding="8" cellspacing="0" style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;margin:20px 0;font-size:13px;">
        <tr><td style="color:#64748B;font-weight:600;">Sport Discipline:</td><td style="color:#0E1F4B;font-weight:700;">' . htmlspecialchars(ucwords(str_replace('_', ' ', $sport))) . '</td></tr>
        <tr><td style="color:#64748B;font-weight:600;">Registered Event:</td><td style="color:#0E1F4B;font-weight:700;">' . htmlspecialchars($event) . '</td></tr>
      </table>

      <p style="color:#334155;font-size:14px;line-height:1.6;">Please keep this registration number safe. It will be required for call-room entry, bib allocation, and accreditation badge collection at Ludhiana.</p>
    ';
    return sendEmail($toEmail, $subject, wrapPpsaEmailBody("Application Approved", $body), null, 'application_approved');
}

/**
 * Dispatch Correction Requested Email
 */
function sendPpsaCorrectionRequestedEmail(string $toEmail, string $athleteName, string $referenceId, string $notes): bool {
    $subject = "PPSA Registration Action Required — {$referenceId}";
    $body = '
      <div style="text-align:center;margin-bottom:20px;">
        <span style="display:inline-block;background:#FEF3C7;color:#D97706;font-weight:800;font-size:13px;padding:6px 16px;border-radius:999px;border:1px solid #FDE68A;">CORRECTION / MORE INFORMATION REQUIRED</span>
      </div>
      <h2 style="color:#0E1F4B;font-size:18px;margin-top:0;">Action Required on Your Application</h2>
      <p style="color:#334155;font-size:14px;line-height:1.6;">Dear <strong>' . htmlspecialchars($athleteName) . '</strong>,</p>
      <p style="color:#334155;font-size:14px;line-height:1.6;">During the review of your application (Ref: <code>' . htmlspecialchars($referenceId) . '</code>), our review team identified that additional information or document correction is required before we can proceed.</p>
      
      <div style="background:#FFFBEB;border-left:4px solid #D97706;padding:14px;border-radius:4px;margin:20px 0;">
        <strong style="color:#92400E;font-size:13px;display:block;margin-bottom:4px;">Reviewer Notes:</strong>
        <p style="color:#78350F;margin:0;font-size:14px;line-height:1.5;">' . nl2br(htmlspecialchars($notes)) . '</p>
      </div>

      <p style="color:#334155;font-size:14px;line-height:1.6;">Please reply directly to this email or contact the PPSA secretariat with the corrected documents or information to complete your registration.</p>
    ';
    return sendEmail($toEmail, $subject, wrapPpsaEmailBody("Action Required", $body), null, 'correction_required');
}

/**
 * Dispatch Application Rejected Email
 */
function sendPpsaApplicationRejectedEmail(string $toEmail, string $athleteName, string $referenceId, string $reason): bool {
    $subject = "PPSA Registration Update — {$referenceId}";
    $body = '
      <h2 style="color:#0E1F4B;font-size:18px;margin-top:0;">Registration Status Update</h2>
      <p style="color:#334155;font-size:14px;line-height:1.6;">Dear <strong>' . htmlspecialchars($athleteName) . '</strong>,</p>
      <p style="color:#334155;font-size:14px;line-height:1.6;">Thank you for your interest in registering with the Punjab Para Sports Association. Following administrative review of application <code>' . htmlspecialchars($referenceId) . '</code>, we regret to inform you that your application could not be approved at this time.</p>
      
      <div style="background:#FEF2F2;border-left:4px solid #DC2626;padding:14px;border-radius:4px;margin:20px 0;">
        <strong style="color:#991B1B;font-size:13px;display:block;margin-bottom:4px;">Administrative Reason:</strong>
        <p style="color:#7F1D1D;margin:0;font-size:14px;line-height:1.5;">' . nl2br(htmlspecialchars($reason)) . '</p>
      </div>

      <p style="color:#64748B;font-size:13px;line-height:1.5;">If you believe this decision was made in error or would like to appeal, please contact the PPSA office at officeparapunjab@gmail.com.</p>
    ';
    return sendEmail($toEmail, $subject, wrapPpsaEmailBody("Registration Status Update", $body), null, 'application_rejected');
}
