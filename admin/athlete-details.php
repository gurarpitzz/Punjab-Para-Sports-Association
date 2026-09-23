<?php
// admin/athlete-details.php - Comprehensive Athlete Application Profile & Review Controller

$pageTitle = "Athlete Application Review";
require_once __DIR__ . '/admin_header.php';
require_once __DIR__ . '/../includes/mailer.php';

$appId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$appId) {
    header("Location: registrations.php");
    exit();
}

$successMsg = '';
$errorMsg   = '';

// Handle Administrative Review Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db) {
    $action = $_POST['review_action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyPpsaCsrf($csrfToken)) {
        $errorMsg = "Invalid security token. Please refresh and try again.";
    } else {
        try {
            // Fetch current application record with lock
            $stmt = $db->prepare("SELECT * FROM ppsa_athlete_applications WHERE id = ? FOR UPDATE");
            $db->beginTransaction();
            $stmt->execute([$appId]);
            $currentApp = $stmt->fetch();

            if (!$currentApp) {
                $db->rollBack();
                $errorMsg = "Application record not found.";
            } else {
                $previousStatus = $currentApp['status'];

                if ($action === 'approve') {
                    if ($previousStatus === 'approved') {
                        $db->rollBack();
                        $errorMsg = "This application has already been approved.";
                    } else {
                        // Reuse existing permanent State Registration Number if already assigned; otherwise mint new
                        $permanentRegNo = !empty($currentApp['permanent_registration_no']) ? $currentApp['permanent_registration_no'] : null;
                        if (!$permanentRegNo) {
                            $permanentRegNo = generatePpsaSequenceNo($db, 'athlete', 2026);
                        }

                        // Update Application Status
                        $upStmt = $db->prepare("
                            UPDATE ppsa_athlete_applications 
                            SET status = 'approved',
                                permanent_registration_no = ?,
                                reviewed_by_user_id = ?,
                                reviewed_at = NOW()
                            WHERE id = ?
                        ");
                        $upStmt->execute([$permanentRegNo, $currentUser['id'], $appId]);

                        // Upsert into Master Approved Registry `ppsa_athletes`
                        $athStmt = $db->prepare("
                            INSERT INTO ppsa_athletes 
                            (registration_no, application_id, full_name, gender, dob, mobile_phone, email, 
                             district, sport_game, classification, weight_category, event_discipline, 
                             photo_path, approved_by_user_id, approved_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE 
                                full_name = VALUES(full_name),
                                classification = VALUES(classification),
                                weight_category = VALUES(weight_category),
                                event_discipline = VALUES(event_discipline),
                                photo_path = VALUES(photo_path)
                        ");
                        $athStmt->execute([
                            $permanentRegNo,
                            $appId,
                            $currentApp['full_name'],
                            $currentApp['gender'],
                            $currentApp['dob'],
                            $currentApp['mobile_phone'],
                            $currentApp['email'],
                            $currentApp['district'],
                            $currentApp['sport_game'],
                            $currentApp['classification'],
                            $currentApp['weight_category'],
                            $currentApp['event_discipline'],
                            $currentApp['photo_path'],
                            $currentUser['id']
                        ]);

                        // Retrieve the master athlete id
                        $getAthId = $db->prepare("SELECT id FROM ppsa_athletes WHERE registration_no = ? LIMIT 1");
                        $getAthId->execute([$permanentRegNo]);
                        $masterAthleteId = (int)$getAthId->fetchColumn();

                        // Link and approve all sports and non-rejected events for this application
                        if ($masterAthleteId) {
                            $upSports = $db->prepare("
                                UPDATE ppsa_athlete_sports 
                                SET athlete_id = ?, status = 'approved' 
                                WHERE application_id = ?
                            ");
                            $upSports->execute([$masterAthleteId, $appId]);

                            $upEvents = $db->prepare("
                                UPDATE ppsa_athlete_events e
                                JOIN ppsa_athlete_sports s ON s.id = e.athlete_sport_id
                                SET e.status = 'approved'
                                WHERE s.application_id = ? AND e.status != 'rejected'
                            ");
                            $upEvents->execute([$appId]);
                        }

                        // Record in Status Transition History
                        $histStmt = $db->prepare("
                            INSERT INTO ppsa_athlete_status_history 
                            (application_id, from_status, to_status, changed_by_user_id, reason_notes, created_at)
                            VALUES (?, ?, 'approved', ?, 'Application approved and permanent PPSA state registration number issued/confirmed.', NOW())
                        ");
                        $histStmt->execute([$appId, $previousStatus, $currentUser['id']]);

                        $db->commit();

                        // Write to Audit Log
                        ppsaAuditLog($currentUser['id'], 'approve_athlete', 'ppsa_athlete_applications', $appId, [
                            'reference_id' => $currentApp['reference_id'],
                            'reg_no'       => $permanentRegNo,
                            'sport'        => $currentApp['sport_game'],
                            'event'        => $currentApp['event_discipline']
                        ]);

                        // Send Approved Email via Resend API
                        sendPpsaApplicationApprovedEmail(
                            $currentApp['email'],
                            $currentApp['full_name'],
                            $permanentRegNo,
                            $currentApp['sport_game'],
                            $currentApp['event_discipline']
                        );

                        $successMsg = "Application approved successfully! Permanent State Registration Number <strong>" . htmlspecialchars($permanentRegNo) . "</strong> is confirmed.";
                    }

                } elseif ($action === 'toggle_event_status') {
                    $targetEventId = (int)($_POST['event_id'] ?? 0);
                    $newEventStatus = ($_POST['event_status'] ?? '') === 'approved' ? 'approved' : 'rejected';
                    
                    if ($targetEventId > 0) {
                        $evUpStmt = $db->prepare("UPDATE ppsa_athlete_events SET status = ? WHERE id = ?");
                        $evUpStmt->execute([$newEventStatus, $targetEventId]);
                        $db->commit();
                        $successMsg = "Event discipline status updated to <strong>" . strtoupper($newEventStatus) . "</strong>.";
                    } else {
                        $db->rollBack();
                        $errorMsg = "Invalid event ID for status update.";
                    }

                } elseif ($action === 'request_correction') {
                    $correctionNotes = trim($_POST['correction_notes'] ?? '');
                    if (empty($correctionNotes)) {
                        $db->rollBack();
                        $errorMsg = "Please provide specific correction instructions for the athlete.";
                    } else {
                        $upStmt = $db->prepare("
                            UPDATE ppsa_athlete_applications 
                            SET status = 'correction_required',
                                correction_notes = ?,
                                reviewed_by_user_id = ?,
                                reviewed_at = NOW()
                            WHERE id = ?
                        ");
                        $upStmt->execute([$correctionNotes, $currentUser['id'], $appId]);

                        $histStmt = $db->prepare("
                            INSERT INTO ppsa_athlete_status_history 
                            (application_id, from_status, to_status, changed_by_user_id, reason_notes, created_at)
                            VALUES (?, ?, 'correction_required', ?, ?, NOW())
                        ");
                        $histStmt->execute([$appId, $previousStatus, $currentUser['id'], $correctionNotes]);

                        $db->commit();

                        ppsaAuditLog($currentUser['id'], 'request_correction', 'ppsa_athlete_applications', $appId, [
                            'reference_id' => $currentApp['reference_id'],
                            'notes'        => $correctionNotes
                        ]);

                        sendPpsaCorrectionRequestedEmail(
                            $currentApp['email'],
                            $currentApp['full_name'],
                            $currentApp['reference_id'],
                            $correctionNotes
                        );

                        $successMsg = "Status updated to 'Correction Required'. Notification email dispatched to the applicant.";
                    }

                } elseif ($action === 'reject') {
                    $rejectionReason = trim($_POST['rejection_reason'] ?? '');
                    if (empty($rejectionReason)) {
                        $db->rollBack();
                        $errorMsg = "Please provide an administrative reason for rejection.";
                    } else {
                        $upStmt = $db->prepare("
                            UPDATE ppsa_athlete_applications 
                            SET status = 'rejected',
                                rejection_reason = ?,
                                reviewed_by_user_id = ?,
                                reviewed_at = NOW()
                            WHERE id = ?
                        ");
                        $upStmt->execute([$rejectionReason, $currentUser['id'], $appId]);

                        $histStmt = $db->prepare("
                            INSERT INTO ppsa_athlete_status_history 
                            (application_id, from_status, to_status, changed_by_user_id, reason_notes, created_at)
                            VALUES (?, ?, 'rejected', ?, ?, NOW())
                        ");
                        $histStmt->execute([$appId, $previousStatus, $currentUser['id'], $rejectionReason]);

                        $db->commit();

                        ppsaAuditLog($currentUser['id'], 'reject_athlete', 'ppsa_athlete_applications', $appId, [
                            'reference_id' => $currentApp['reference_id'],
                            'reason'       => $rejectionReason
                        ]);

                        sendPpsaApplicationRejectedEmail(
                            $currentApp['email'],
                            $currentApp['full_name'],
                            $currentApp['reference_id'],
                            $rejectionReason
                        );

                        $successMsg = "Application rejected. Notification email dispatched.";
                    }
                } else {
                    $db->rollBack();
                    $errorMsg = "Unknown review action.";
                }
            }
        } catch (\Throwable $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $errorMsg = "Database error processing review: " . $e->getMessage();
        }
    }
}

// Fetch Full Application Details
$app = null;
$duplicateFlags = [];
$statusHistory  = [];

if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT a.*, u.full_name as reviewer_name, u.role as reviewer_role
            FROM ppsa_athlete_applications a
            LEFT JOIN ppsa_users u ON a.reviewed_by_user_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();

        if ($app) {
            // Fetch duplicate collision flags
            $dupStmt = $db->prepare("
                SELECT df.*, 
                       ma.full_name as matched_app_name, ma.reference_id as matched_app_ref, ma.created_at as matched_app_created,
                       ath.full_name as matched_ath_name, ath.registration_no as matched_ath_reg
                FROM ppsa_duplicate_flags df
                LEFT JOIN ppsa_athlete_applications ma ON df.matched_application_id = ma.id
                LEFT JOIN ppsa_athletes ath ON df.matched_athlete_id = ath.id
                WHERE df.application_id = ?
                ORDER BY df.id DESC
            ");
            $dupStmt->execute([$appId]);
            $duplicateFlags = $dupStmt->fetchAll();

            // Fetch review status transition history
            $histStmt = $db->prepare("
                SELECT sh.*, u.full_name as changed_by_name
                FROM ppsa_athlete_status_history sh
                LEFT JOIN ppsa_users u ON sh.changed_by_user_id = u.id
                WHERE sh.application_id = ?
                ORDER BY sh.id DESC
            ");
            $histStmt->execute([$appId]);
            $statusHistory = $histStmt->fetchAll();

            // Fetch Hierarchical Sports & Events
            $sportsWithEvents = [];
            try {
                $spStmt = $db->prepare("
                    SELECT s.* 
                    FROM ppsa_athlete_sports s
                    WHERE s.application_id = ? 
                       OR (s.athlete_id IS NOT NULL AND s.athlete_id = (SELECT id FROM ppsa_athletes WHERE application_id = ?))
                    ORDER BY s.id ASC
                ");
                $spStmt->execute([$appId, $appId]);
                $rawSports = $spStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rawSports as $rs) {
                    $evStmt = $db->prepare("
                        SELECT * FROM ppsa_athlete_events 
                        WHERE athlete_sport_id = ? 
                        ORDER BY id ASC
                    ");
                    $evStmt->execute([$rs['id']]);
                    $rs['events'] = $evStmt->fetchAll(PDO::FETCH_ASSOC);
                    $sportsWithEvents[] = $rs;
                }
            } catch (\Throwable $e) {
                // Table may be pending migration; graceful fallback to application columns
                $sportsWithEvents = [];
            }
        }
    } catch (\Throwable $e) {
        $errorMsg = "Error retrieving application: " . $e->getMessage();
    }
}

if (!$app) {
    echo "<div class='admin-card' style='padding:40px;text-align:center;'>";
    echo "<h2>Application Record Not Found</h2>";
    echo "<p style='color:var(--text-muted);margin:12px 0 20px;'>The requested application ID does not exist or database is offline.</p>";
    echo "<a href='registrations.php' style='background:var(--navy);color:#fff;padding:8px 18px;border-radius:6px;text-decoration:none;font-weight:700;'>&larr; Back to Intake Queue</a>";
    echo "</div>";
    require_once __DIR__ . '/admin_footer.php';
    exit();
}

// Calculate age from DOB
$ageStr = 'N/A';
if (!empty($app['dob'])) {
    try {
        $dobDate = new DateTime($app['dob']);
        $now = new DateTime();
        $ageStr = $now->diff($dobDate)->y . ' years';
    } catch (\Exception $e) {}
}

// Status badge helper
$statusMap = [
    'pending'             => ['label' => 'Pending Review', 'bg' => '#FEF3C7', 'color' => '#D97706', 'border' => '#FDE68A'],
    'under_review'        => ['label' => 'Under Review', 'bg' => '#EFF6FF', 'color' => '#2563EB', 'border' => '#BFDBFE'],
    'approved'            => ['label' => 'Approved', 'bg' => '#DCFCE7', 'color' => '#16A34A', 'border' => '#BBF7D0'],
    'rejected'            => ['label' => 'Rejected', 'bg' => '#FEE2E2', 'color' => '#DC2626', 'border' => '#FCA5A5'],
    'correction_required' => ['label' => 'Correction Required', 'bg' => '#FFFBEB', 'color' => '#B45309', 'border' => '#FCD34D'],
];
$sb = $statusMap[$app['status']] ?? ['label' => ucfirst($app['status']), 'bg' => '#F1F5F9', 'color' => '#475569', 'border' => '#CBD5E1'];
?>

<!-- Breadcrumb -->
<div style="margin-bottom:18px;">
  <a href="registrations.php" style="color:var(--text-muted);text-decoration:none;font-size:0.88rem;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
    &larr; Back to Athlete Intake Queue
  </a>
</div>

<!-- Alerts -->
<?php if ($successMsg): ?>
  <div style="background:#ECFDF5;border-left:4px solid #10B981;padding:16px 20px;border-radius:6px;margin-bottom:20px;color:#065F46;font-size:0.92rem;">
    <?php echo $successMsg; ?>
  </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
  <div style="background:#FEF2F2;border-left:4px solid #EF4444;padding:16px 20px;border-radius:6px;margin-bottom:20px;color:#991B1B;font-size:0.92rem;">
    <?php echo htmlspecialchars($errorMsg); ?>
  </div>
<?php endif; ?>

<!-- Application Profile Header Banner -->
<div class="admin-card" style="padding:24px 28px;margin-bottom:24px;border-left:5px solid var(--navy);">
  <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:20px;">
    <div style="display:flex;align-items:center;gap:20px;">
      <!-- Athlete Photo Thumbnail -->
      <div style="width:80px;height:80px;border-radius:8px;background:#E2E8F0;overflow:hidden;border:2px solid var(--border);flex-shrink:0;">
        <?php if (!empty($app['photo_path'])): ?>
          <img src="view-doc.php?file=<?php echo urlencode($app['photo_path']); ?>" alt="Athlete Photo" style="width:100%;height:100%;object-fit:cover;">
        <?php else: ?>
          <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#94A3B8;font-size:1.5rem;font-weight:800;">
            <?php echo strtoupper(substr($app['full_name'], 0, 1)); ?>
          </div>
        <?php endif; ?>
      </div>

      <div>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px;">
          <h1 style="font-family:'Outfit',sans-serif;font-size:1.6rem;font-weight:800;color:var(--navy);margin:0;">
            <?php echo htmlspecialchars($app['full_name']); ?>
          </h1>
          <span style="background:<?php echo $sb['bg']; ?>;color:<?php echo $sb['color']; ?>;border:1px solid <?php echo $sb['border']; ?>;font-size:0.75rem;font-weight:800;padding:3px 10px;border-radius:999px;text-transform:uppercase;letter-spacing:0.04em;">
            <?php echo $sb['label']; ?>
          </span>
          <?php if (!empty($app['has_duplicate_flag'])): ?>
            <span style="background:#FEE2E2;color:#DC2626;border:1px solid #FCA5A5;font-size:0.75rem;font-weight:800;padding:3px 10px;border-radius:999px;display:inline-flex;align-items:center;gap:5px;">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> DUPLICATE RISK
            </span>
          <?php endif; ?>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:16px;color:var(--text-muted);font-size:0.85rem;font-weight:600;">
          <span>Ref ID: <strong style="font-family:monospace;color:var(--navy);"><?php echo htmlspecialchars($app['reference_id']); ?></strong></span>
          <span>•</span>
          <span>Submitted: <strong><?php echo date('d M Y, h:i A', strtotime($app['created_at'])); ?></strong></span>
          <?php if (!empty($app['permanent_registration_no'])): ?>
            <span>•</span>
            <span>Permanent State ID: <strong style="font-family:monospace;color:#00B074;background:#E6FBF2;padding:2px 8px;border-radius:4px;border:1px solid #A7F3D0;"><?php echo htmlspecialchars($app['permanent_registration_no']); ?></strong></span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Quick Jump to Action -->
    <div>
      <a href="#review-actions-box" style="display:inline-flex;align-items:center;gap:6px;background:var(--navy);color:#fff;font-weight:700;font-size:0.88rem;padding:9px 18px;border-radius:6px;text-decoration:none;">
        Review Decision <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
      </a>
    </div>
  </div>
</div>

<!-- Duplicate Risk Warning Box (if flagged) -->
<?php if (!empty($duplicateFlags)): ?>
  <div class="admin-card" style="border:2px solid #FCA5A5;background:#FFF5F5;padding:20px;margin-bottom:24px;">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
      <span style="color:#DC2626;display:inline-flex;align-items:center;"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
      <h3 style="font-size:1.1rem;font-weight:800;color:#991B1B;margin:0;">Duplicate Intake Detection Warning</h3>
    </div>
    <p style="color:#7F1D1D;font-size:0.88rem;line-height:1.5;margin-bottom:14px;">
      The automated duplicate engine identified potential identity collision(s) for this applicant. Please review matched records before issuing a state registration number.
    </p>

    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:0.82rem;background:#fff;border-radius:6px;overflow:hidden;border:1px solid #FECACA;">
        <thead>
          <tr style="background:#FEE2E2;text-align:left;color:#991B1B;font-weight:700;">
            <th style="padding:8px 12px;">Match Field</th>
            <th style="padding:8px 12px;">Severity</th>
            <th style="padding:8px 12px;">Collision Details</th>
            <th style="padding:8px 12px;">Matched Application / Athlete</th>
            <th style="padding:8px 12px;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($duplicateFlags as $df): ?>
            <tr style="border-top:1px solid #FECACA;">
              <td style="padding:10px 12px;font-weight:700;text-transform:uppercase;color:#B91C1C;">
                <?php echo htmlspecialchars(str_replace('_', ' ', $df['match_field'])); ?>
              </td>
              <td style="padding:10px 12px;">
                <span style="background:<?php echo $df['severity'] === 'critical' ? '#EF4444' : '#F59E0B'; ?>;color:#fff;font-weight:800;font-size:0.7rem;padding:2px 6px;border-radius:4px;text-transform:uppercase;">
                  <?php echo htmlspecialchars($df['severity']); ?>
                </span>
              </td>
              <td style="padding:10px 12px;color:var(--text);"><?php echo htmlspecialchars($df['details']); ?></td>
              <td style="padding:10px 12px;">
                <?php if (!empty($df['matched_application_id'])): ?>
                  <div>App: <strong><?php echo htmlspecialchars($df['matched_app_name'] ?? 'ID #' . $df['matched_application_id']); ?></strong></div>
                  <div style="color:var(--text-muted);font-family:monospace;"><?php echo htmlspecialchars($df['matched_app_ref'] ?? ''); ?></div>
                <?php elseif (!empty($df['matched_athlete_id'])): ?>
                  <div>Approved Athlete: <strong><?php echo htmlspecialchars($df['matched_ath_name'] ?? 'ID #' . $df['matched_athlete_id']); ?></strong></div>
                  <div style="color:#00B074;font-family:monospace;font-weight:700;"><?php echo htmlspecialchars($df['matched_ath_reg'] ?? ''); ?></div>
                <?php endif; ?>
              </td>
              <td style="padding:10px 12px;">
                <?php if (!empty($df['matched_application_id'])): ?>
                  <a href="athlete-details.php?id=<?php echo $df['matched_application_id']; ?>" target="_blank" style="color:var(--navy);font-weight:700;text-decoration:none;">View Matched &rarr;</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- Main 2-Column Content Grid -->
<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;">

  <!-- Left Column: Athlete Profile & Documents -->
  <div>

    <!-- 1. Personal & Identity Details -->
    <div class="admin-card">
      <h2 style="font-size:1.15rem;font-weight:800;color:var(--navy);margin-bottom:18px;display:flex;align-items:center;gap:8px;">
        <span style="display:inline-block;width:8px;height:18px;background:var(--gold);border-radius:2px;"></span>
        Personal & Identity Details
      </h2>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;font-size:0.9rem;">
        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Full Name</span>
          <strong style="color:var(--text);font-size:1rem;"><?php echo htmlspecialchars($app['full_name']); ?></strong>
        </div>

        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Gender</span>
          <strong style="color:var(--text);"><?php echo htmlspecialchars(ucfirst($app['gender'])); ?></strong>
        </div>

        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Date of Birth (Age)</span>
          <strong style="color:var(--text);"><?php echo date('d F Y', strtotime($app['dob'])); ?> (<?php echo $ageStr; ?>)</strong>
        </div>

        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Aadhaar Number</span>
          <strong style="color:var(--navy);font-family:monospace;font-size:0.95rem;background:#F1F5F9;padding:2px 8px;border-radius:4px;">
            <?php echo htmlspecialchars(wordwrap($app['aadhaar_number'], 4, ' ', true)); ?>
          </strong>
        </div>

        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Father's Name</span>
          <strong style="color:var(--text);"><?php echo htmlspecialchars($app['father_name'] ?: 'Not Provided'); ?></strong>
        </div>

        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Mother's Name</span>
          <strong style="color:var(--text);"><?php echo htmlspecialchars($app['mother_name'] ?: 'Not Provided'); ?></strong>
        </div>

        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Mobile Phone</span>
          <strong style="color:var(--text);"><a href="tel:<?php echo htmlspecialchars($app['mobile_phone']); ?>" style="color:var(--navy);text-decoration:none;"><?php echo htmlspecialchars($app['mobile_phone']); ?></a></strong>
        </div>

        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Email Address</span>
          <strong style="color:var(--text);"><a href="mailto:<?php echo htmlspecialchars($app['email']); ?>" style="color:var(--navy);text-decoration:none;"><?php echo htmlspecialchars($app['email']); ?></a></strong>
        </div>
      </div>

      <hr style="border:0;border-top:1px solid var(--border);margin:20px 0;">

      <!-- Residential Address -->
      <div style="font-size:0.9rem;">
        <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;margin-bottom:4px;">Residential Address</span>
        <div style="color:var(--text);background:#F8FAFC;padding:12px 14px;border-radius:6px;border:1px solid var(--border);line-height:1.5;">
          <?php echo nl2br(htmlspecialchars($app['full_address'])); ?>
          <div style="margin-top:6px;font-weight:700;color:var(--navy);">
            District: <?php echo htmlspecialchars($app['district']); ?>, Punjab <?php if (!empty($app['pincode'])): ?>- PIN: <?php echo htmlspecialchars($app['pincode']); ?><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- 2. Sport Discipline & Classification Matrix (Multi-Sport & Multi-Event) -->
    <div class="admin-card">
      <h2 style="font-size:1.15rem;font-weight:800;color:var(--navy);margin-bottom:18px;display:flex;align-items:center;gap:8px;">
        <span style="display:inline-block;width:8px;height:18px;background:#00B074;border-radius:2px;"></span>
        Competition Discipline & Classification Matrix
      </h2>

      <?php if (!empty($sportsWithEvents)): ?>
        <div style="display:flex;flex-direction:column;gap:18px;">
          <?php foreach ($sportsWithEvents as $sIdx => $sData): 
            $sportStatusBadge = [
              'approved' => ['bg' => '#DCFCE7', 'color' => '#16A34A', 'label' => 'Approved'],
              'rejected' => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'label' => 'Rejected'],
              'pending'  => ['bg' => '#FEF3C7', 'color' => '#D97706', 'label' => 'Pending']
            ][$sData['status'] ?? 'pending'] ?? ['bg' => '#F1F5F9', 'color' => '#475569', 'label' => 'Pending'];
          ?>
            <div style="border:1.5px solid #E2E8F0;border-radius:8px;padding:16px 18px;background:#FFF;">
              <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #F1F5F9;">
                <div>
                  <span style="font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Sport #<?php echo $sIdx + 1; ?></span>
                  <h3 style="font-size:1.1rem;font-weight:800;color:var(--navy);margin:2px 0 0;">
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $sData['sport_game']))); ?>
                  </h3>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                  <?php if (!empty($sData['classification'])): ?>
                    <span style="font-size:0.85rem;font-weight:700;color:#00B074;background:#E6FBF2;border:1px solid #A7F3D0;padding:4px 10px;border-radius:6px;">
                      Class: <?php echo htmlspecialchars($sData['classification']); ?>
                    </span>
                  <?php endif; ?>
                  <?php if (!empty($sData['weight_category'])): ?>
                    <span style="font-size:0.85rem;font-weight:700;color:var(--navy);background:#EFF6FF;border:1px solid #BFDBFE;padding:4px 10px;border-radius:6px;">
                      Weight: <?php echo htmlspecialchars($sData['weight_category']); ?>
                    </span>
                  <?php endif; ?>
                  <span style="font-size:0.78rem;font-weight:800;background:<?php echo $sportStatusBadge['bg']; ?>;color:<?php echo $sportStatusBadge['color']; ?>;padding:4px 10px;border-radius:6px;text-transform:uppercase;">
                    <?php echo $sportStatusBadge['label']; ?>
                  </span>
                </div>
              </div>

              <!-- Events Under This Sport -->
              <div>
                <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;margin-bottom:8px;">
                  Registered Event Disciplines (<?php echo count($sData['events']); ?>):
                </span>
                <div style="display:flex;flex-wrap:wrap;gap:10px;">
                  <?php foreach ($sData['events'] as $ev): 
                    $evBg = $ev['status'] === 'approved' ? '#DCFCE7' : ($ev['status'] === 'rejected' ? '#FEE2E2' : '#FEF3C7');
                    $evColor = $ev['status'] === 'approved' ? '#166534' : ($ev['status'] === 'rejected' ? '#991B1B' : '#92400E');
                  ?>
                    <div style="display:inline-flex;align-items:center;gap:8px;background:<?php echo $evBg; ?>;color:<?php echo $evColor; ?>;border:1px solid rgba(0,0,0,0.08);padding:6px 12px;border-radius:6px;font-size:0.85rem;font-weight:700;">
                      <span><?php echo htmlspecialchars($ev['event_discipline']); ?></span>
                      <span style="font-size:0.72rem;text-transform:uppercase;padding:2px 6px;border-radius:4px;background:rgba(255,255,255,0.7);">
                        <?php echo htmlspecialchars($ev['status']); ?>
                      </span>

                      <!-- Inline Event Status Quick Toggles -->
                      <?php if ($canReview): ?>
                        <form method="POST" style="display:inline-flex;gap:3px;margin:0;">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                          <input type="hidden" name="action" value="toggle_event_status">
                          <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                          <?php if ($ev['status'] !== 'approved'): ?>
                            <button type="submit" name="event_status" value="approved" title="Approve this event" style="background:#16A34A;color:#fff;border:none;border-radius:3px;padding:2px 5px;font-size:0.7rem;cursor:pointer;line-height:1;">✓</button>
                          <?php endif; ?>
                          <?php if ($ev['status'] !== 'rejected'): ?>
                            <button type="submit" name="event_status" value="rejected" title="Reject this event" style="background:#DC2626;color:#fff;border:none;border-radius:3px;padding:2px 5px;font-size:0.7rem;cursor:pointer;line-height:1;">✕</button>
                          <?php endif; ?>
                        </form>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <!-- Legacy Fallback View -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;font-size:0.9rem;">
          <div>
            <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Sport Game</span>
            <span style="display:inline-block;background:var(--navy);color:#fff;font-weight:800;font-size:0.85rem;padding:4px 12px;border-radius:6px;margin-top:4px;">
              <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $app['sport_game']))); ?>
            </span>
          </div>
          <div>
            <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Specific Event Discipline</span>
            <strong style="color:var(--navy);font-size:1.05rem;display:block;margin-top:4px;"><?php echo htmlspecialchars($app['event_discipline']); ?></strong>
          </div>
          <?php if (!empty($app['classification'])): ?>
            <div>
              <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Para Classification</span>
              <strong style="color:#00B074;font-size:1rem;background:#E6FBF2;padding:3px 10px;border-radius:4px;border:1px solid #A7F3D0;display:inline-block;margin-top:4px;">
                <?php echo htmlspecialchars($app['classification']); ?>
              </strong>
            </div>
          <?php endif; ?>
          <?php if (!empty($app['weight_category'])): ?>
            <div>
              <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Weight Category</span>
              <strong style="color:var(--navy);font-size:1rem;background:#F1F5F9;padding:3px 10px;border-radius:4px;display:inline-block;margin-top:4px;">
                <?php echo htmlspecialchars($app['weight_category']); ?>
              </strong>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;font-size:0.9rem;margin-top:18px;padding-top:16px;border-top:1px solid var(--border);">
        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Impairment / Disability Type</span>
          <strong style="color:var(--text);display:block;margin-top:4px;"><?php echo htmlspecialchars($app['impairment_type'] ?: 'Not Specified'); ?></strong>
        </div>
        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Competition Venue</span>
          <strong style="color:var(--text);display:block;margin-top:4px;">Ludhiana State Games</strong>
        </div>
      </div>
    </div>

    <!-- 3. Uploaded Document Verification Panel -->
    <div class="admin-card">
      <h2 style="font-size:1.15rem;font-weight:800;color:var(--navy);margin-bottom:18px;display:flex;align-items:center;gap:8px;">
        <span style="display:inline-block;width:8px;height:18px;background:var(--navy);border-radius:2px;"></span>
        Document Inspection & Verification
      </h2>

      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;">
        <!-- Passport Photo -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:16px;background:#F8FAFC;text-align:center;">
          <div style="font-weight:700;font-size:0.85rem;color:var(--navy);margin-bottom:12px;">Passport Size Photograph</div>
          <?php if (!empty($app['photo_path'])): ?>
            <div style="width:140px;height:160px;margin:0 auto 12px;background:#fff;border-radius:6px;overflow:hidden;border:1px solid var(--border);box-shadow:0 2px 8px rgba(0,0,0,0.06);">
              <img src="view-doc.php?file=<?php echo urlencode($app['photo_path']); ?>" alt="Athlete Photo" style="width:100%;height:100%;object-fit:cover;">
            </div>
            <a href="view-doc.php?file=<?php echo urlencode($app['photo_path']); ?>" target="_blank" style="display:inline-block;background:var(--navy);color:#fff;font-size:0.78rem;font-weight:700;padding:6px 12px;border-radius:4px;text-decoration:none;">
              View Full Image &nearr;
            </a>
          <?php else: ?>
            <div style="padding:40px 10px;color:var(--danger);font-size:0.82rem;font-weight:600;">No photo uploaded</div>
          <?php endif; ?>
        </div>

        <!-- Aadhaar Card / ID Proof -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:16px;background:#F8FAFC;text-align:center;">
          <div style="font-weight:700;font-size:0.85rem;color:var(--navy);margin-bottom:12px;">Aadhaar / Punjab ID Proof</div>
          <?php if (!empty($app['id_proof_path'])): ?>
            <div style="width:140px;height:160px;margin:0 auto 12px;background:#fff;border-radius:6px;overflow:hidden;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
              <?php if (preg_match('/\.(jpg|jpeg|png)$/i', $app['id_proof_path'])): ?>
                <img src="view-doc.php?file=<?php echo urlencode($app['id_proof_path']); ?>" alt="ID Proof" style="width:100%;height:100%;object-fit:contain;">
              <?php else: ?>
                <div style="text-align:center;padding:10px;">
                  <span style="display:block;margin-bottom:6px;color:var(--navy);"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                  <span style="font-size:0.75rem;font-weight:700;color:var(--text-muted);">PDF Document</span>
                </div>
              <?php endif; ?>
            </div>
            <a href="view-doc.php?file=<?php echo urlencode($app['id_proof_path']); ?>" target="_blank" style="display:inline-block;background:var(--navy);color:#fff;font-size:0.78rem;font-weight:700;padding:6px 12px;border-radius:4px;text-decoration:none;">
              Inspect Document &nearr;
            </a>
          <?php else: ?>
            <div style="padding:40px 10px;color:var(--danger);font-size:0.82rem;font-weight:600;">No document uploaded</div>
          <?php endif; ?>
        </div>

        <!-- Medical / Disability Certificate -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:16px;background:#F8FAFC;text-align:center;">
          <div style="font-weight:700;font-size:0.85rem;color:var(--navy);margin-bottom:12px;">Disability Certificate / UDID</div>
          <?php if (!empty($app['medical_certificate_path'])): ?>
            <div style="width:140px;height:160px;margin:0 auto 12px;background:#fff;border-radius:6px;overflow:hidden;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.06);">
              <?php if (preg_match('/\.(jpg|jpeg|png)$/i', $app['medical_certificate_path'])): ?>
                <img src="view-doc.php?file=<?php echo urlencode($app['medical_certificate_path']); ?>" alt="Disability Cert" style="width:100%;height:100%;object-fit:contain;">
              <?php else: ?>
                <div style="text-align:center;padding:10px;">
                  <span style="display:block;margin-bottom:6px;color:#00B074;"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
                  <span style="font-size:0.75rem;font-weight:700;color:var(--text-muted);">UDID Certificate</span>
                </div>
              <?php endif; ?>
            </div>
            <a href="view-doc.php?file=<?php echo urlencode($app['medical_certificate_path']); ?>" target="_blank" style="display:inline-block;background:var(--navy);color:#fff;font-size:0.78rem;font-weight:700;padding:6px 12px;border-radius:4px;text-decoration:none;">
              Inspect Document &nearr;
            </a>
          <?php else: ?>
            <div style="padding:40px 10px;color:var(--danger);font-size:0.82rem;font-weight:600;">No certificate uploaded</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- 4. Review Timeline / Status History -->
    <?php if (!empty($statusHistory)): ?>
      <div class="admin-card">
        <h2 style="font-size:1.15rem;font-weight:800;color:var(--navy);margin-bottom:18px;">
          Review History & Audit Trail
        </h2>

        <div style="display:flex;flex-direction:column;gap:14px;">
          <?php foreach ($statusHistory as $sh): ?>
            <div style="display:flex;align-items:flex-start;gap:12px;border-left:3px solid var(--navy);padding-left:14px;">
              <div>
                <div style="font-size:0.85rem;font-weight:700;color:var(--navy);">
                  Transitioned to: <span style="text-transform:uppercase;color:<?php echo $statusMap[$sh['to_status']]['color'] ?? 'var(--navy)'; ?>;"><?php echo htmlspecialchars($sh['to_status']); ?></span>
                  <?php if (!empty($sh['changed_by_name'])): ?>
                    <span style="font-weight:400;color:var(--text-muted);">by <?php echo htmlspecialchars($sh['changed_by_name']); ?></span>
                  <?php endif; ?>
                </div>
                <div style="font-size:0.78rem;color:var(--text-muted);margin:2px 0 6px;">
                  <?php echo date('d M Y, h:i A', strtotime($sh['created_at'])); ?>
                </div>
                <?php if (!empty($sh['reason_notes'])): ?>
                  <div style="background:#F8FAFC;padding:8px 12px;border-radius:4px;font-size:0.82rem;color:var(--text);border:1px solid var(--border);">
                    <?php echo nl2br(htmlspecialchars($sh['reason_notes'])); ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>

  <!-- Right Column: Administrative Review Action Center -->
  <div id="review-actions-box">

    <!-- Action Box Card -->
    <div class="admin-card" style="border-top:4px solid var(--navy);position:sticky;top:84px;">
      <h3 style="font-size:1.15rem;font-weight:800;color:var(--navy);margin-bottom:12px;">Administrative Decision</h3>
      <p style="font-size:0.82rem;color:var(--text-muted);margin-bottom:18px;line-height:1.4;">
        Verify athlete credentials and select the appropriate review action below.
      </p>

      <!-- Current Status Notice -->
      <div style="background:<?php echo $sb['bg']; ?>;border:1px solid <?php echo $sb['border']; ?>;padding:12px 14px;border-radius:6px;margin-bottom:18px;">
        <span style="font-size:0.75rem;font-weight:700;color:<?php echo $sb['color']; ?>;text-transform:uppercase;display:block;">Current Status</span>
        <strong style="color:<?php echo $sb['color']; ?>;font-size:0.95rem;"><?php echo $sb['label']; ?></strong>
        <?php if (!empty($app['reviewed_by_user_id'])): ?>
          <div style="font-size:0.75rem;color:var(--text-muted);margin-top:4px;">
            Reviewed by <?php echo htmlspecialchars($app['reviewer_name'] ?? 'Staff'); ?> on <?php echo date('d M Y', strtotime($app['reviewed_at'])); ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Action 1: APPROVE -->
      <?php if ($app['status'] !== 'approved'): ?>
        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to APPROVE this athlete? This will issue a permanent PPSA state registration number and dispatch the official confirmation email.');" style="margin-bottom:14px;">
          <input type="hidden" name="csrf_token" value="<?php echo generatePpsaCsrf(); ?>">
          <input type="hidden" name="id" value="<?php echo $appId; ?>">
          <input type="hidden" name="review_action" value="approve">
          
          <button type="submit" style="width:100%;background:#00B074;color:#fff;border:none;font-weight:800;font-size:0.92rem;padding:12px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 3px 10px rgba(0,176,116,0.3);transition:background 0.2s;" onmouseover="this.style.background='#008f5d'" onmouseout="this.style.background='#00B074'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Approve & Issue PPSA State ID
          </button>
        </form>
      <?php else: ?>
        <div style="background:#E6FBF2;border:1px solid #A7F3D0;padding:14px;border-radius:6px;margin-bottom:14px;text-align:center;">
          <span style="color:#00B074;font-size:0.8rem;font-weight:700;display:block;">OFFICIALLY APPROVED</span>
          <strong style="font-family:monospace;font-size:1.1rem;color:var(--navy);"><?php echo htmlspecialchars($app['permanent_registration_no']); ?></strong>
        </div>
      <?php endif; ?>

      <!-- Action 2: REQUEST CORRECTION -->
      <details style="margin-bottom:14px;border:1px solid var(--border);border-radius:6px;background:#F8FAFC;">
        <summary style="padding:10px 14px;font-size:0.88rem;font-weight:700;color:#B45309;cursor:pointer;outline:none;user-select:none;display:flex;align-items:center;justify-content:space-between;">
          <span>Request Correction / More Info</span>
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
        </summary>
        <div style="padding:12px 14px;border-top:1px solid var(--border);">
          <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generatePpsaCsrf(); ?>">
            <input type="hidden" name="id" value="<?php echo $appId; ?>">
            <input type="hidden" name="review_action" value="request_correction">
            
            <label style="display:block;font-size:0.78rem;font-weight:700;color:var(--text);margin-bottom:6px;">Required Corrections / Notes:</label>
            <textarea name="correction_notes" rows="4" required placeholder="e.g. Upload clearer photo of Disability Certificate showing percentage clearly..." style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:4px;font-size:0.82rem;font-family:inherit;margin-bottom:10px;"><?php echo htmlspecialchars($app['correction_notes'] ?? ''); ?></textarea>
            
            <button type="submit" style="width:100%;background:#F59E0B;color:#fff;border:none;font-weight:700;font-size:0.82rem;padding:9px;border-radius:4px;cursor:pointer;">
              Send Correction Request Email
            </button>
          </form>
        </div>
      </details>

      <!-- Action 3: REJECT -->
      <?php if ($app['status'] !== 'rejected'): ?>
        <details style="margin-bottom:18px;border:1px solid #FECACA;border-radius:6px;background:#FEF2F2;">
          <summary style="padding:10px 14px;font-size:0.88rem;font-weight:700;color:#DC2626;cursor:pointer;outline:none;user-select:none;display:flex;align-items:center;justify-content:space-between;">
            <span>Reject Application</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </summary>
          <div style="padding:12px 14px;border-top:1px solid #FECACA;">
            <form method="POST" action="" onsubmit="return confirm('Are you sure you want to REJECT this application?');">
              <input type="hidden" name="csrf_token" value="<?php echo generatePpsaCsrf(); ?>">
              <input type="hidden" name="id" value="<?php echo $appId; ?>">
              <input type="hidden" name="review_action" value="reject">
              
              <label style="display:block;font-size:0.78rem;font-weight:700;color:#991B1B;margin-bottom:6px;">Administrative Rejection Reason:</label>
              <textarea name="rejection_reason" rows="3" required placeholder="e.g. Aadhaar address indicates resident outside Punjab State..." style="width:100%;padding:8px 10px;border:1.5px solid #FCA5A5;border-radius:4px;font-size:0.82rem;font-family:inherit;margin-bottom:10px;"><?php echo htmlspecialchars($app['rejection_reason'] ?? ''); ?></textarea>
              
              <button type="submit" style="width:100%;background:#DC2626;color:#fff;border:none;font-weight:700;font-size:0.82rem;padding:9px;border-radius:4px;cursor:pointer;">
                Confirm Rejection & Dispatch Email
              </button>
            </form>
          </div>
        </details>
      <?php endif; ?>

      <!-- Fast Contact Links -->
      <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px;">
        <span style="font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;margin-bottom:8px;">Direct Applicant Communications</span>
        <div style="display:flex;flex-direction:column;gap:8px;font-size:0.82rem;">
          <a href="mailto:<?php echo htmlspecialchars($app['email']); ?>?subject=Regarding your PPSA Registration (Ref: <?php echo htmlspecialchars($app['reference_id']); ?>)" style="color:var(--navy);font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg> Send Custom Email
          </a>
          <a href="tel:<?php echo htmlspecialchars($app['mobile_phone']); ?>" style="color:var(--navy);font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg> Call: <?php echo htmlspecialchars($app['mobile_phone']); ?>
          </a>
        </div>
      </div>

    </div>

  </div>

</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
