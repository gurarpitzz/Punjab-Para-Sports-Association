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
require_once __DIR__ . '/../includes/uploads.php';

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

// 3. Sport & Event Matrix Validation (Supports Multi-Sport & Multi-Event)
$sportsDataRaw = $_POST['sportsData'] ?? null;
$parsedSports = [];

if (!empty($sportsDataRaw)) {
    $decoded = json_decode($sportsDataRaw, true);
    if (is_array($decoded) && count($decoded) > 0) {
        $parsedSports = $decoded;
    }
}

// Fallback to legacy single-sport inputs if sportsData not supplied
if (empty($parsedSports)) {
    $parsedSports[] = [
        'sport'           => $sportGame,
        'classification'  => $classification ?: null,
        'weight_category' => $weightCategory ?: null,
        'events'          => !empty($eventDiscipline) ? array_map('trim', explode(',', $eventDiscipline)) : []
    ];
}

// Validate each requested sport and each event discipline
$sportsSummaryParts = [];
$primarySport = null;
$primaryClass = null;
$primaryWeight = null;
$allEventsFlat = [];

foreach ($parsedSports as $sIndex => $sItem) {
    $sGame   = trim($sItem['sport'] ?? '');
    $sClass  = trim($sItem['classification'] ?? '');
    $sWeight = trim($sItem['weight_category'] ?? '');
    $sEvents = is_array($sItem['events'] ?? null) ? $sItem['events'] : [];

    if (empty($sGame)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Sport #" . ($sIndex + 1) . ": Designated sport is required."]);
        exit();
    }

    if (empty($sEvents)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Sport #" . ($sIndex + 1) . " ({$sGame}): Please select at least one event discipline."]);
        exit();
    }

    foreach ($sEvents as $evName) {
        $evTrimmed = trim($evName);
        if (empty($evTrimmed)) continue;
        $check = validatePpsaSportSelection($sGame, $sClass ?: null, $sWeight ?: null, $evTrimmed, $gender);
        if (!$check['valid']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $check['error']]);
            exit();
        }
        $allEventsFlat[] = $evTrimmed;
    }

    if ($primarySport === null) {
        $primarySport  = $sGame;
        $primaryClass  = $sClass ?: null;
        $primaryWeight = $sWeight ?: null;
    }

    $cLabel = $sClass ?: ($sWeight ?: 'Open');
    $sportsSummaryParts[] = ucwords(str_replace('_', ' ', $sGame)) . " ({$cLabel}: " . implode(', ', $sEvents) . ")";
}

$sportsSummaryString = implode(' | ', $sportsSummaryParts);
$primaryEventString  = implode(', ', array_unique($allEventsFlat));

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
    'passportPhoto'      => ['key' => 'photo',        'maxDim' => 800,  'quality' => 85],
    'identityProofDoc'   => ['key' => 'id_proof',     'maxDim' => 1600, 'quality' => 82],
    'disabilityCertDoc'  => ['key' => 'medical_cert', 'maxDim' => 1600, 'quality' => 82]
];

foreach ($fileInputs as $inputKey => $meta) {
    $storageKey = $meta['key'];
    $maxDim     = $meta['maxDim'];
    $quality    = $meta['quality'];

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

        // If it's an image, re-encode into optimized jpg format; if pdf, retain pdf
        $ext = ($mime === 'application/pdf') ? 'pdf' : 'jpg';
        $safeName = sprintf("%s_%s.%s", $storageKey, bin2hex(random_bytes(10)), $ext);
        $destination = $uploadBase . $safeName;

        if (optimizeAndSaveUploadedFile($fileTmp, $mime, $destination, $maxDim, $quality)) {
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

// 6. Database Persistence & Transactional Identity Anchoring
$db = getPpsaDb();
$insertedAppId = null;
$existingAthleteRecord = null;
$hasDuplicateFlag = 0;
$primaryMatchedId = null;

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed. Please try again.']);
    exit();
}

try {
    $db->beginTransaction();

    $cleanAadhaar = preg_replace('/[^0-9]/', '', $aadhaarNumber);

    // Secure Normalized Aadhaar Match: Check if human is already in Approved Master Registry
    $findMasterStmt = $db->prepare("
        SELECT ath.id AS athlete_id, ath.registration_no, ath.application_id, a.photo_path, a.id_proof_path, a.medical_certificate_path
        FROM ppsa_athletes ath
        JOIN ppsa_athlete_applications a ON a.id = ath.application_id
        WHERE REPLACE(a.aadhaar_number, ' ', '') = ?
        LIMIT 1
        FOR UPDATE
    ");
    $findMasterStmt->execute([$cleanAadhaar]);
    $existingAthleteRecord = $findMasterStmt->fetch(PDO::FETCH_ASSOC);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    if ($existingAthleteRecord) {
        // =========================================================================
        // CASE A: EXISTING APPROVED ATHLETE (Smart Identity Anchoring & ID Reuse)
        // =========================================================================
        $identityAthleteId = (int)$existingAthleteRecord['athlete_id'];
        $permanentRegNo    = $existingAthleteRecord['registration_no'];
        $primaryMatchedId  = (int)$existingAthleteRecord['application_id'];
        $hasDuplicateFlag  = 0; // Not a suspicious collision; this is an authorized participation addition

        // Fallback to existing documents if not re-uploaded
        $finalPhoto   = $uploadedPaths['photo'] ?: $existingAthleteRecord['photo_path'];
        $finalIdProof = $uploadedPaths['id_proof'] ?: $existingAthleteRecord['id_proof_path'];
        $finalMedCert = $uploadedPaths['medical_cert'] ?: $existingAthleteRecord['medical_certificate_path'];

        $appStmt = $db->prepare("
            INSERT INTO ppsa_athlete_applications (
                reference_id, permanent_registration_no, full_name, gender, dob, father_name, mother_name,
                mobile_phone, email, aadhaar_number, full_address, district, state,
                sport_game, classification, weight_category, event_discipline,
                impairment_type, venue, photo_path, id_proof_path, medical_certificate_path,
                status, has_duplicate_flag, duplicate_matched_id, ip_address, user_agent, created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, 'Ludhiana', ?, ?, ?,
                'pending', ?, ?, ?, ?, NOW()
            )
        ");

        $appStmt->execute([
            $referenceId, $permanentRegNo, $fullName, $gender, $dob, $fatherName, $motherName,
            $mobilePhone, $email, $aadhaarNumber, $fullAddress, $district, $state,
            $primarySport, $primaryClass, $primaryWeight, $primaryEventString,
            $impairmentType ?: null, $finalPhoto, $finalIdProof, $finalMedCert,
            $hasDuplicateFlag, $primaryMatchedId, $ip, $ua
        ]);

        $insertedAppId = (int)$db->lastInsertId();

        // Cross-Application Deduplication: Link sports/events directly under existing athlete_id
        foreach ($parsedSports as $sItem) {
            $sGame   = trim($sItem['sport']);
            $sClass  = trim($sItem['classification'] ?? '');
            $sWeight = trim($sItem['weight_category'] ?? '');
            $sEvents = is_array($sItem['events']) ? $sItem['events'] : [];

            // Check if athlete already has this sport & classification in ppsa_athlete_sports
            $chkSportStmt = $db->prepare("
                SELECT id FROM ppsa_athlete_sports 
                WHERE athlete_id = ? AND sport_game = ? AND (classification = ? OR (classification IS NULL AND ? = ''))
                LIMIT 1
            ");
            $chkSportStmt->execute([$identityAthleteId, $sGame, $sClass ?: null, $sClass]);
            $existingSportRow = $chkSportStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingSportRow) {
                $athleteSportId = (int)$existingSportRow['id'];
            } else {
                $insSportStmt = $db->prepare("
                    INSERT INTO ppsa_athlete_sports (athlete_id, application_id, sport_game, classification, weight_category, status)
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");
                $insSportStmt->execute([
                    $identityAthleteId, $insertedAppId, $sGame, $sClass ?: null, $sWeight ?: null
                ]);
                $athleteSportId = (int)$db->lastInsertId();
            }

            // Append events under athlete_sport_id without creating duplicate entries
            $insEvStmt = $db->prepare("
                INSERT IGNORE INTO ppsa_athlete_events (athlete_sport_id, event_discipline, status)
                VALUES (?, ?, 'pending')
            ");
            foreach ($sEvents as $evName) {
                $evTrimmed = trim($evName);
                if (!empty($evTrimmed)) {
                    $insEvStmt->execute([$athleteSportId, $evTrimmed]);
                }
            }
        }

    } else {
        // =========================================================================
        // CASE B: NEW ATHLETE REGISTRATION
        // =========================================================================
        $duplicateInspection = checkAthleteDuplicates([
            'aadhaar_number' => $aadhaarNumber,
            'mobile_phone'   => $mobilePhone,
            'email'          => $email,
            'full_name'      => $fullName,
            'dob'            => $dob
        ]);

        $hasDuplicateFlag = $duplicateInspection['has_duplicate'] ? 1 : 0;
        $primaryMatchedId = !empty($duplicateInspection['flags'][0]['target_id']) ? $duplicateInspection['flags'][0]['target_id'] : null;

        $appStmt = $db->prepare("
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

        $appStmt->execute([
            $referenceId, $fullName, $gender, $dob, $fatherName, $motherName,
            $mobilePhone, $email, $aadhaarNumber, $fullAddress, $district, $state,
            $primarySport, $primaryClass, $primaryWeight, $primaryEventString,
            $impairmentType ?: null, $uploadedPaths['photo'], $uploadedPaths['id_proof'], $uploadedPaths['medical_cert'],
            $hasDuplicateFlag, $primaryMatchedId, $ip, $ua
        ]);

        $insertedAppId = (int)$db->lastInsertId();

        // Insert sports and events with athlete_id = NULL (populated upon administrative approval)
        foreach ($parsedSports as $sItem) {
            $sGame   = trim($sItem['sport']);
            $sClass  = trim($sItem['classification'] ?? '');
            $sWeight = trim($sItem['weight_category'] ?? '');
            $sEvents = is_array($sItem['events']) ? $sItem['events'] : [];

            $insSportStmt = $db->prepare("
                INSERT INTO ppsa_athlete_sports (athlete_id, application_id, sport_game, classification, weight_category, status)
                VALUES (NULL, ?, ?, ?, ?, 'pending')
            ");
            $insSportStmt->execute([
                $insertedAppId, $sGame, $sClass ?: null, $sWeight ?: null
            ]);
            $athleteSportId = (int)$db->lastInsertId();

            $insEvStmt = $db->prepare("
                INSERT INTO ppsa_athlete_events (athlete_sport_id, event_discipline, status)
                VALUES (?, ?, 'pending')
            ");
            foreach ($sEvents as $evName) {
                $evTrimmed = trim($evName);
                if (!empty($evTrimmed)) {
                    $insEvStmt->execute([$athleteSportId, $evTrimmed]);
                }
            }
        }

        // Log duplicate collisions if any
        if ($hasDuplicateFlag && !empty($duplicateInspection['flags'])) {
            $flagStmt = $db->prepare("
                INSERT INTO ppsa_duplicate_flags (application_id, matched_application_id, match_field, severity, details)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($duplicateInspection['flags'] as $fl) {
                $flagStmt->execute([
                    $insertedAppId,
                    $fl['target_id'],
                    $fl['type'],
                    $fl['severity'],
                    $fl['message']
                ]);
            }
        }
    }

    // Audit Trail
    ppsaAuditLog(null, 'submit_athlete_application', 'ppsa_athlete_applications', $insertedAppId, [
        'reference_id'       => $referenceId,
        'sports_summary'     => $sportsSummaryString,
        'is_existing_athlete'=> (bool)$existingAthleteRecord,
        'has_duplicate'      => (bool)$hasDuplicateFlag
    ]);

    $db->commit();

} catch (\Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("PPSA Athlete Multi-Sport Intake DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database persistence failed: ' . $e->getMessage()]);
    exit();
}

// 7. Dispatch Application Received Email via Resend
sendPpsaApplicationReceivedEmail($email, $fullName, $referenceId, $primarySport, $primaryEventString, 'Ludhiana');

// 8. Output Success Confirmation Receipt
echo json_encode([
    'success'           => true,
    'reference_id'      => $referenceId,
    'full_name'         => $fullName,
    'sport_summary'     => $sportsSummaryString,
    'sport_game'        => ucwords(str_replace('_', ' ', $primarySport)),
    'event_discipline'  => $primaryEventString,
    'classification'    => $primaryClass ?: ($primaryWeight ?: 'Standard Entry'),
    'venue'             => 'Ludhiana',
    'status'            => 'PENDING REVIEW',
    'is_existing_athlete'=> !empty($existingAthleteRecord),
    'duplicate_warning' => $hasDuplicateFlag ? true : false,
    'message'           => 'Your registration application has been submitted successfully to the Punjab Para Sports Association.'
]);
