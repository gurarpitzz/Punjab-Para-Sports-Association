<?php
// includes/duplicate_detector.php - Intelligent Application Duplicate Collision Detector

require_once __DIR__ . '/db.php';

function checkAthleteDuplicates(array $applicantData): array {
    $db = getPpsaDb();
    if (!$db) return ['has_duplicate' => false, 'flags' => []];

    $aadhaar = preg_replace('/[^0-9]/', '', $applicantData['aadhaar_number'] ?? '');
    $mobile  = preg_replace('/[^0-9]/', '', $applicantData['mobile_phone'] ?? '');
    $email   = strtolower(trim($applicantData['email'] ?? ''));
    $name    = strtolower(trim($applicantData['full_name'] ?? ''));
    $dob     = trim($applicantData['dob'] ?? '');

    $flags = [];

    // 1. Check Exact Aadhaar in Applications and Master Registry
    if (!empty($aadhaar)) {
        $stmt = $db->prepare("
            SELECT id, reference_id, full_name, status, created_at 
            FROM ppsa_athlete_applications 
            WHERE REPLACE(aadhaar_number, ' ', '') = ?
            LIMIT 3
        ");
        $stmt->execute([$aadhaar]);
        while ($row = $stmt->fetch()) {
            $flags[] = [
                'type'     => 'aadhaar',
                'severity' => 'critical',
                'target_id'=> $row['id'],
                'message'  => "Duplicate Aadhaar collision with Application {$row['reference_id']} ({$row['full_name']}, status: {$row['status']})"
            ];
        }
    }

    // 2. Check Exact Mobile in Applications
    if (!empty($mobile) && strlen($mobile) >= 10) {
        $phoneSuffix = substr($mobile, -10);
        $stmt = $db->prepare("
            SELECT id, reference_id, full_name, status 
            FROM ppsa_athlete_applications 
            WHERE mobile_phone LIKE ?
            LIMIT 3
        ");
        $stmt->execute(["%{$phoneSuffix}"]);
        while ($row = $stmt->fetch()) {
            $flags[] = [
                'type'     => 'mobile',
                'severity' => 'warning',
                'target_id'=> $row['id'],
                'message'  => "Phone number already associated with Application {$row['reference_id']} ({$row['full_name']})"
            ];
        }
    }

    // 3. Check Exact Email in Applications
    if (!empty($email)) {
        $stmt = $db->prepare("
            SELECT id, reference_id, full_name, status 
            FROM ppsa_athlete_applications 
            WHERE LOWER(email) = ?
            LIMIT 3
        ");
        $stmt->execute([$email]);
        while ($row = $stmt->fetch()) {
            $flags[] = [
                'type'     => 'email',
                'severity' => 'warning',
                'target_id'=> $row['id'],
                'message'  => "Email address already associated with Application {$row['reference_id']} ({$row['full_name']})"
            ];
        }
    }

    // 4. Check Identical DOB + Name Similarity
    if (!empty($dob) && !empty($name)) {
        $stmt = $db->prepare("
            SELECT id, reference_id, full_name, status 
            FROM ppsa_athlete_applications 
            WHERE dob = ?
            LIMIT 5
        ");
        $stmt->execute([$dob]);
        while ($row = $stmt->fetch()) {
            $simName = strtolower(trim($row['full_name']));
            similar_text($name, $simName, $percent);
            if ($percent >= 80) {
                $flags[] = [
                    'type'     => 'name_dob',
                    'severity' => 'critical',
                    'target_id'=> $row['id'],
                    'message'  => sprintf("High similarity (%.1f%%) in Name & identical DOB with Application %s (%s)", $percent, $row['reference_id'], $row['full_name'])
                ];
            }
        }
    }

    return [
        'has_duplicate' => !empty($flags),
        'flags'         => $flags
    ];
}
