<?php
// api/submit-athlete.php - Punjab Para Sports Association Athlete Application Intake API

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
require_once __DIR__ . '/../includes/sports_catalog.php';
require_once __DIR__ . '/../includes/duplicate_detector.php';
require_once __DIR__ . '/../includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit();
}

// 1. Mandatory Fields Retrieval & Trimming
$fullName       = trim($_POST['fullName'] ?? '');
$gender         = strtolower(trim($_POST['gender'] ?? ''));
$dob            = trim($_POST['dob'] ?? '');
$fatherName     = trim($_POST['fatherName'] ?? '');
$motherName     = trim($_POST['motherName'] ?? '');
$mobilePhone    = trim($_POST['mobilePhone'] ?? '');
$email          = strtolower(trim($_POST['email'] ?? ''));
$aadhaarNumber  = preg_replace('/[^0-9]/', '', $_POST['aadhaarNumber'] ?? '');
$fullAddress    = trim($_POST['fullAddress'] ?? '');
$district       = trim($_POST['district'] ?? '');
$state          = trim($_POST['state'] ?? 'Punjab');
$sportGame      = trim($_POST['sportGame'] ?? '');
$classification = trim($_POST['classification'] ?? '');
$weightCategory = trim($_POST['weightCategory'] ?? '');
$eventDiscipline= trim($_POST['eventDiscipline'] ?? '');
$impairmentType = trim($_POST['impairmentType'] ?? '');
$legalAgreed    = !empty($_POST['legalDeclaration']);

// 2. Personal & Contact Validations
if (empty($fullName) || empty($gender) || empty($dob) || empty($mobilePhone) || empty($email)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'All mandatory personal and contact fields are required.']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
    exit();
}

if (strlen($aadhaarNumber) !== 12) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid 12-digit Aadhaar number is mandatory for state verification.']);
    exit();
}

if (empty($district) || empty($fullAddress)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Permanent residential address and Punjab district are required.']);
    exit();
}

if (!$legalAgreed) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'You must agree to the athlete declaration to submit your application.']);
    exit();
}

// 3. Sport & Event Matrix Validation
$sportCheck = validatePpsaSportSelection($sportGame, $classification, $weightCategory, $eventDiscipline, $gender);
if (!$sportCheck['valid']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $sportCheck['error']]);
    exit();
}

// 4. Secure Document Upload Handler
$uploadedPaths = [
    'photo'        => null,
    'id_proof'     => null,
    'medical_cert' => null
];

$uploadBase = rtrim(UPLOAD_PATH, '/\\') . '/athletes/' . date('Y') . '/';
if (!is_dir($uploadBase)) {
    @mkdir($uploadBase, 0755, true);
}

$allowedMimes = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'application/pdf' => 'pdf'
];

$maxBytes = 5 * 1024 * 1024; // 5 MB

$fileInputs = [
    'passportPhoto'      => 'photo',
    'identityProofDoc'   => 'id_proof',
    'disabilityCertDoc'  => 'medical_cert'
];

foreach ($fileInputs as $inputKey => $storageKey) {
    if (isset($_FILES[$inputKey]) && $_FILES[$inputKey]['error'] === UPLOAD_ERR_OK) {
        $fileTmp  = $_FILES[$inputKey]['tmp_name'];
        $fileSize = $_FILES[$inputKey]['size'];

        if ($fileSize > $maxBytes) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Document {$inputKey} exceeds the maximum 5MB size limit."]);
            exit();
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $fileTmp);
        finfo_close($finfo);

        if (!isset($allowedMimes[$mime])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Invalid file format for {$inputKey}. Only JPG, PNG, WebP, and PDF are permitted."]);
            exit();
        }

        $ext = $allowedMimes[$mime];
        $safeName = sprintf("%s_%s.%s", $storageKey, bin2hex(random_bytes(10)), $ext);
        $destination = $uploadBase . $safeName;

        if (move_uploaded_file($fileTmp, $destination)) {
            $uploadedPaths[$storageKey] = 'uploads/athletes/' . date('Y') . '/' . $safeName;
        }
    }
}

// 5. Generate High-Entropy Reference ID
try {
    $randHex = strtoupper(bin2hex(random_bytes(3)));
} catch (\Throwable $e) {
    $randHex = strtoupper(substr(md5(uniqid()), 0, 6));
}
$referenceId = "PPSA-APP-ATH-" . date('Y') . "-" . $randHex;

// 6. Duplicate Detection Inspection
$duplicateInspection = checkAthleteDuplicates([
    'aadhaar_number' => $aadhaarNumber,
    'mobile_phone'    => $mobilePhone,
    'email'           => $email,
    'full_name'       => $fullName,
    'dob'             => $dob
]);

$hasDuplicateFlag = $duplicateInspection['has_duplicate'] ? 1 : 0;
$primaryMatchedId = !empty($duplicateInspection['flags'][0]['target_id']) ? $duplicateInspection['flags'][0]['target_id'] : null;

// 7. Database Persistence
$db = getPpsaDb();
$insertedId = null;

if ($db) {
    try {
        $stmt = $db->prepare("
            INSERT INTO ppsa_athlete_applications (
                reference_id, full_name, gender, dob, father_name, mother_name,
                mobile_phone, email, aadhaar_number, full_address, district, state,
                sport_game, classification, weight_category, event_discipline,
                impairment_type, venue, photo_path, id_proof_path, medical_certificate_path,
                status, has_duplicate_flag, duplicate_matched_id, ip_address, user_agent, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, 'Ludhiana', ?, ?, ?,
                'pending', ?, ?, ?, ?, NOW()
            )
        ");

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $stmt->execute([
            $referenceId, $fullName, $gender, $dob, $fatherName, $motherName,
            $mobilePhone, $email, $aadhaarNumber, $fullAddress, $district, $state,
            $sportGame, $classification ?: null, $weightCategory ?: null, $eventDiscipline,
            $impairmentType ?: null, $uploadedPaths['photo'], $uploadedPaths['id_proof'], $uploadedPaths['medical_cert'],
            $hasDuplicateFlag, $primaryMatchedId, $ip, $ua
        ]);

        $insertedId = (int)$db->lastInsertId();

        // Log duplicate collisions if any
        if ($hasDuplicateFlag && !empty($duplicateInspection['flags'])) {
            $flagStmt = $db->prepare("
                INSERT INTO ppsa_duplicate_flags (application_id, matched_application_id, match_field, severity, details)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($duplicateInspection['flags'] as $fl) {
                $flagStmt->execute([
                    $insertedId,
                    $fl['target_id'],
                    $fl['type'],
                    $fl['severity'],
                    $fl['message']
                ]);
            }
        }

        // Audit Trail
        ppsaAuditLog(null, 'submit_athlete_application', 'ppsa_athlete_applications', $insertedId, [
            'reference_id' => $referenceId,
            'sport'        => $sportGame,
            'event'        => $eventDiscipline,
            'has_duplicate'=> (bool)$hasDuplicateFlag
        ]);

    } catch (PDOException $e) {
        error_log("PPSA Athlete Intake DB Error: " . $e->getMessage());
    }
}

// 8. Dispatch Application Received Email via Resend
sendPpsaApplicationReceivedEmail($email, $fullName, $referenceId, $sportGame, $eventDiscipline, 'Ludhiana');

// 9. Output Success Confirmation Receipt
echo json_encode([
    'success'           => true,
    'reference_id'      => $referenceId,
    'full_name'         => $fullName,
    'sport_game'        => ucwords(str_replace('_', ' ', $sportGame)),
    'event_discipline'  => $eventDiscipline,
    'classification'    => $classification ?: ($weightCategory ?: 'Standard Entry'),
    'venue'             => 'Ludhiana',
    'status'            => 'PENDING REVIEW',
    'duplicate_warning' => $hasDuplicateFlag ? true : false,
    'message'           => 'Your registration application has been submitted successfully to the Punjab Para Sports Association.'
]);
