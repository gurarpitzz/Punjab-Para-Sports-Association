<?php
// api/submit-official.php - Punjab Para Sports Association Official Registration Intake API

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
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

$fullName         = trim($_POST['fullName'] ?? '');
$gender           = strtolower(trim($_POST['gender'] ?? ''));
$dob              = trim($_POST['dob'] ?? '');
$mobilePhone      = trim($_POST['mobilePhone'] ?? '');
$email            = strtolower(trim($_POST['email'] ?? ''));
$aadhaarNumber    = preg_replace('/[^0-9]/', '', $_POST['aadhaarNumber'] ?? '');
$district         = trim($_POST['district'] ?? '');
$state            = trim($_POST['state'] ?? 'Punjab');
$officialCategory = trim($_POST['officialCategory'] ?? '');
$classifierType   = trim($_POST['classifierType'] ?? '');
$qualifications   = trim($_POST['qualifications'] ?? '');
$experienceYears  = (int)($_POST['experienceYears'] ?? 0);
$legalAgreed      = !empty($_POST['legalDeclaration']);

if (empty($fullName) || empty($mobilePhone) || empty($email) || empty($officialCategory)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Full Name, Phone, Email, and Official Category are mandatory.']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid email address.']);
    exit();
}

if (!$legalAgreed) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'You must agree to the official code of conduct and declaration.']);
    exit();
}

// Upload Handling
$uploadedPaths = [
    'photo'    => null,
    'id_proof' => null,
    'cert'     => null
];

$uploadBase = rtrim(UPLOAD_PATH, '/\\') . '/officials/' . date('Y') . '/';
if (!is_dir($uploadBase)) {
    @mkdir($uploadBase, 0755, true);
}

$allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

$fileInputs = [
    'passportPhoto'   => 'photo',
    'identityProofDoc'=> 'id_proof',
    'certificateDoc'  => 'cert'
];

foreach ($fileInputs as $inputKey => $storageKey) {
    if (isset($_FILES[$inputKey]) && $_FILES[$inputKey]['error'] === UPLOAD_ERR_OK) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES[$inputKey]['tmp_name']);
        finfo_close($finfo);

        if (isset($allowedMimes[$mime]) && $_FILES[$inputKey]['size'] <= 5 * 1024 * 1024) {
            $ext = $allowedMimes[$mime];
            $safeName = sprintf("%s_%s.%s", $storageKey, bin2hex(random_bytes(10)), $ext);
            if (move_uploaded_file($_FILES[$inputKey]['tmp_name'], $uploadBase . $safeName)) {
                $uploadedPaths[$storageKey] = 'uploads/officials/' . date('Y') . '/' . $safeName;
            }
        }
    }
}

try {
    $randHex = strtoupper(bin2hex(random_bytes(3)));
} catch (\Throwable $e) {
    $randHex = strtoupper(substr(md5(uniqid()), 0, 6));
}
$referenceId = "PPSA-APP-OFF-" . date('Y') . "-" . $randHex;

$db = getPpsaDb();
if ($db) {
    try {
        $stmt = $db->prepare("
            INSERT INTO ppsa_official_applications (
                reference_id, full_name, gender, dob, mobile_phone, email,
                aadhaar_number, district, state, official_category, classifier_type,
                qualifications, experience_years, photo_path, id_proof_path, cert_proof_path,
                status, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                'pending', NOW()
            )
        ");
        $stmt->execute([
            $referenceId, $fullName, $gender, $dob, $mobilePhone, $email,
            $aadhaarNumber, $district, $state, $officialCategory, $classifierType ?: null,
            $qualifications ?: null, $experienceYears, $uploadedPaths['photo'], $uploadedPaths['id_proof'], $uploadedPaths['cert']
        ]);

        $insId = (int)$db->lastInsertId();
        ppsaAuditLog(null, 'submit_official_application', 'ppsa_official_applications', $insId, [
            'reference_id' => $referenceId,
            'category'     => $officialCategory
        ]);
    } catch (PDOException $e) {
        error_log("PPSA Official Intake DB Error: " . $e->getMessage());
    }
}

// Send Received Email
$subject = "PPSA Official Application Received — {$referenceId}";
$body = '
  <h2 style="color:#0E1F4B;margin-top:0;">Official Application Received</h2>
  <p style="color:#334155;">Dear <strong>' . htmlspecialchars($fullName) . '</strong>,</p>
  <p style="color:#334155;">Thank you for applying as a registered technical official / staff member with the Punjab Para Sports Association.</p>
  <table width="100%" cellpadding="8" cellspacing="0" style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:8px;margin:20px 0;font-size:13px;">
    <tr><td style="color:#64748B;font-weight:600;width:40%;">Reference ID:</td><td style="color:#0E1F4B;font-weight:800;font-family:monospace;">' . htmlspecialchars($referenceId) . '</td></tr>
    <tr><td style="color:#64748B;font-weight:600;">Category:</td><td style="color:#0E1F4B;font-weight:700;">' . htmlspecialchars(ucwords(str_replace('_', ' ', $officialCategory))) . '</td></tr>
    <tr><td style="color:#64748B;font-weight:600;">Status:</td><td style="color:#D97706;font-weight:700;">PENDING ADMINISTRATIVE REVIEW</td></tr>
  </table>
';
sendEmail($email, $subject, wrapPpsaEmailBody("Official Application Received", $body), null, 'official_received');

echo json_encode([
    'success'      => true,
    'reference_id' => $referenceId,
    'full_name'    => $fullName,
    'category'     => ucwords(str_replace('_', ' ', $officialCategory)),
    'status'       => 'PENDING REVIEW',
    'message'      => 'Your official application has been received and logged for administrative verification.'
]);
