<?php
// admin/official-details.php - Comprehensive Official Application Review & Accreditation Controller

$pageTitle = "Official Application Review";
require_once __DIR__ . '/admin_header.php';
require_once __DIR__ . '/../includes/mailer.php';

$appId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$appId) {
    header("Location: officials.php");
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
            $stmt = $db->prepare("SELECT * FROM ppsa_official_applications WHERE id = ? FOR UPDATE");
            $db->beginTransaction();
            $stmt->execute([$appId]);
            $currentApp = $stmt->fetch();

            if (!$currentApp) {
                $db->rollBack();
                $errorMsg = "Official application record not found.";
            } else {
                $previousStatus = $currentApp['status'];

                if ($action === 'approve') {
                    if ($previousStatus === 'approved') {
                        $db->rollBack();
                        $errorMsg = "This official has already been approved.";
                    } else {
                        // Concurrency-Safe Atomic Official Sequence Number
                        $permanentRegNo = generatePpsaSequenceNo($db, 'official', 2026);

                        // Update Application Status
                        $upStmt = $db->prepare("
                            UPDATE ppsa_official_applications 
                            SET status = 'approved',
                                permanent_registration_no = ?,
                                reviewed_by_user_id = ?,
                                reviewed_at = NOW()
                            WHERE id = ?
                        ");
                        $upStmt->execute([$permanentRegNo, $currentUser['id'], $appId]);

                        // Upsert into Master Approved Registry `ppsa_officials`
                        $offStmt = $db->prepare("
                            INSERT INTO ppsa_officials 
                            (registration_no, application_id, full_name, official_category, mobile_phone, 
                             email, district, photo_path, approved_by_user_id, approved_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE 
                                full_name = VALUES(full_name),
                                official_category = VALUES(official_category),
                                photo_path = VALUES(photo_path)
                        ");
                        $offStmt->execute([
                            $permanentRegNo,
                            $appId,
                            $currentApp['full_name'],
                            $currentApp['official_category'],
                            $currentApp['mobile_phone'],
                            $currentApp['email'],
                            $currentApp['district'],
                            $currentApp['photo_path'],
                            $currentUser['id']
                        ]);

                        $db->commit();

                        // Write to Audit Log
                        ppsaAuditLog($currentUser['id'], 'approve_official', 'ppsa_official_applications', $appId, [
                            'reference_id' => $currentApp['reference_id'],
                            'reg_no'       => $permanentRegNo,
                            'category'     => $currentApp['official_category']
                        ]);

                        // Send Approved Email via Resend API
                        sendPpsaApplicationApprovedEmail(
                            $currentApp['email'],
                            $currentApp['full_name'],
                            $permanentRegNo,
                            ucwords(str_replace('_', ' ', $currentApp['official_category'])),
                            'Official Accreditation (State Games Ludhiana)'
                        );

                        $successMsg = "Official accreditation approved! Permanent State ID <strong>" . htmlspecialchars($permanentRegNo) . "</strong> has been issued and confirmation email dispatched.";
                    }

                } elseif ($action === 'request_correction') {
                    $correctionNotes = trim($_POST['correction_notes'] ?? '');
                    if (empty($correctionNotes)) {
                        $db->rollBack();
                        $errorMsg = "Please provide specific correction instructions.";
                    } else {
                        $upStmt = $db->prepare("
                            UPDATE ppsa_official_applications 
                            SET status = 'correction_required',
                                correction_notes = ?,
                                reviewed_by_user_id = ?,
                                reviewed_at = NOW()
                            WHERE id = ?
                        ");
                        $upStmt->execute([$correctionNotes, $currentUser['id'], $appId]);

                        $db->commit();

                        ppsaAuditLog($currentUser['id'], 'request_correction', 'ppsa_official_applications', $appId, [
                            'reference_id' => $currentApp['reference_id'],
                            'notes'        => $correctionNotes
                        ]);

                        sendPpsaCorrectionRequestedEmail(
                            $currentApp['email'],
                            $currentApp['full_name'],
                            $currentApp['reference_id'],
                            $correctionNotes
                        );

                        $successMsg = "Status updated to 'Correction Required'. Notification email dispatched.";
                    }

                } elseif ($action === 'reject') {
                    $rejectionReason = trim($_POST['rejection_reason'] ?? '');
                    if (empty($rejectionReason)) {
                        $db->rollBack();
                        $errorMsg = "Please provide an administrative reason for rejection.";
                    } else {
                        $upStmt = $db->prepare("
                            UPDATE ppsa_official_applications 
                            SET status = 'rejected',
                                rejection_reason = ?,
                                reviewed_by_user_id = ?,
                                reviewed_at = NOW()
                            WHERE id = ?
                        ");
                        $upStmt->execute([$rejectionReason, $currentUser['id'], $appId]);

                        $db->commit();

                        ppsaAuditLog($currentUser['id'], 'reject_official', 'ppsa_official_applications', $appId, [
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
                }
            }
        } catch (\Throwable $e) {
            if ($db && $db->inTransaction()) {
                $db->rollBack();
            }
            $errorMsg = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch Official Application Details
$app = null;
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT a.*, u.full_name as reviewer_name, u.role as reviewer_role
            FROM ppsa_official_applications a
            LEFT JOIN ppsa_users u ON a.reviewed_by_user_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$appId]);
        $app = $stmt->fetch();
    } catch (\Throwable $e) {
        $errorMsg = "Error retrieving application: " . $e->getMessage();
    }
}

if (!$app) {
    echo "<div class='admin-card' style='padding:40px;text-align:center;'>";
    echo "<h2>Official Application Record Not Found</h2>";
    echo "<p style='color:var(--text-muted);margin:12px 0 20px;'>The requested application does not exist or database is offline.</p>";
    echo "<a href='officials.php' style='background:var(--navy);color:#fff;padding:8px 18px;border-radius:6px;text-decoration:none;font-weight:700;'>&larr; Back to Official Queue</a>";
    echo "</div>";
    require_once __DIR__ . '/admin_footer.php';
    exit();
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

<div style="margin-bottom:18px;">
  <a href="officials.php" style="color:var(--text-muted);text-decoration:none;font-size:0.88rem;font-weight:600;display:inline-flex;align-items:center;gap:6px;">
    &larr; Back to Official Intake Queue
  </a>
</div>

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

<!-- Official Profile Header Banner -->
<div class="admin-card" style="padding:24px 28px;margin-bottom:24px;border-left:5px solid var(--navy);">
  <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:20px;">
    <div style="display:flex;align-items:center;gap:20px;">
      <div style="width:80px;height:80px;border-radius:8px;background:#E2E8F0;overflow:hidden;border:2px solid var(--border);flex-shrink:0;">
        <?php if (!empty($app['photo_path'])): ?>
          <img src="view-doc.php?file=<?php echo urlencode($app['photo_path']); ?>" alt="Official Photo" style="width:100%;height:100%;object-fit:cover;">
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
          <span style="background:<?php echo $sb['bg']; ?>;color:<?php echo $sb['color']; ?>;border:1px solid <?php echo $sb['border']; ?>;font-size:0.75rem;font-weight:800;padding:3px 10px;border-radius:999px;text-transform:uppercase;">
            <?php echo $sb['label']; ?>
          </span>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:16px;color:var(--text-muted);font-size:0.85rem;font-weight:600;">
          <span>Ref ID: <strong style="font-family:monospace;color:var(--navy);"><?php echo htmlspecialchars($app['reference_id']); ?></strong></span>
          <span>•</span>
          <span>Designation: <strong style="color:var(--navy);"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $app['official_category']))); ?></strong></span>
          <?php if (!empty($app['permanent_registration_no'])): ?>
            <span>•</span>
            <span>Accreditation ID: <strong style="font-family:monospace;color:#00B074;background:#E6FBF2;padding:2px 8px;border-radius:4px;"><?php echo htmlspecialchars($app['permanent_registration_no']); ?></strong></span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Main 2-Column Content Grid -->
<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;">

  <!-- Left Column: Official Profile & Documents -->
  <div>

    <!-- 1. Personal & Identity Details -->
    <div class="admin-card">
      <h2 style="font-size:1.15rem;font-weight:800;color:var(--navy);margin-bottom:18px;">
        Personal Information & Credentials
      </h2>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;font-size:0.9rem;">
        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Full Name</span>
          <strong style="color:var(--text);font-size:1rem;"><?php echo htmlspecialchars($app['full_name']); ?></strong>
        </div>

        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Official Role Category</span>
          <strong style="color:var(--navy);background:#F1F5F9;padding:3px 10px;border-radius:4px;display:inline-block;">
            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $app['official_category']))); ?>
          </strong>
        </div>

        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Date of Birth</span>
          <strong style="color:var(--text);"><?php echo date('d F Y', strtotime($app['dob'])); ?></strong>
        </div>

        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Aadhaar Number</span>
          <strong style="color:var(--navy);font-family:monospace;background:#F1F5F9;padding:2px 8px;border-radius:4px;">
            <?php echo htmlspecialchars(wordwrap($app['aadhaar_number'], 4, ' ', true)); ?>
          </strong>
        </div>

        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Punjab District</span>
          <strong style="color:var(--text);"><?php echo htmlspecialchars($app['district']); ?></strong>
        </div>

        <div>
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;">Years of Experience</span>
          <strong style="color:var(--text);"><?php echo (int)$app['experience_years']; ?> Years</strong>
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

      <?php if (!empty($app['qualifications'])): ?>
        <hr style="border:0;border-top:1px solid var(--border);margin:20px 0;">
        <div style="font-size:0.9rem;">
          <span style="color:var(--text-muted);display:block;font-size:0.78rem;font-weight:700;text-transform:uppercase;margin-bottom:6px;">Technical Qualifications / Certifications</span>
          <div style="background:#F8FAFC;padding:12px 14px;border-radius:6px;border:1px solid var(--border);color:var(--text);line-height:1.5;">
            <?php echo nl2br(htmlspecialchars($app['qualifications'])); ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- 2. Document Verification Panel -->
    <div class="admin-card">
      <h2 style="font-size:1.15rem;font-weight:800;color:var(--navy);margin-bottom:18px;">
        Document Verification & Inspection
      </h2>

      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;">
        <!-- Photo -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:16px;background:#F8FAFC;text-align:center;">
          <div style="font-weight:700;font-size:0.85rem;color:var(--navy);margin-bottom:12px;">Official Photograph</div>
          <?php if (!empty($app['photo_path'])): ?>
            <div style="width:140px;height:160px;margin:0 auto 12px;background:#fff;border-radius:6px;overflow:hidden;border:1px solid var(--border);">
              <img src="view-doc.php?file=<?php echo urlencode($app['photo_path']); ?>" alt="Photo" style="width:100%;height:100%;object-fit:cover;">
            </div>
            <a href="view-doc.php?file=<?php echo urlencode($app['photo_path']); ?>" target="_blank" style="display:inline-block;background:var(--navy);color:#fff;font-size:0.78rem;font-weight:700;padding:6px 12px;border-radius:4px;text-decoration:none;">
              View Full Image &nearr;
            </a>
          <?php else: ?>
            <div style="padding:40px 10px;color:var(--danger);font-size:0.82rem;font-weight:600;">No photo uploaded</div>
          <?php endif; ?>
        </div>

        <!-- Aadhaar / ID Proof -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:16px;background:#F8FAFC;text-align:center;">
          <div style="font-weight:700;font-size:0.85rem;color:var(--navy);margin-bottom:12px;">Aadhaar / ID Proof</div>
          <?php if (!empty($app['id_proof_path'])): ?>
            <div style="width:140px;height:160px;margin:0 auto 12px;background:#fff;border-radius:6px;overflow:hidden;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;">
              <?php if (preg_match('/\.(jpg|jpeg|png)$/i', $app['id_proof_path'])): ?>
                <img src="view-doc.php?file=<?php echo urlencode($app['id_proof_path']); ?>" alt="ID Proof" style="width:100%;height:100%;object-fit:contain;">
              <?php else: ?>
                <div style="text-align:center;">
                  <span style="font-size:2rem;color:var(--navy);display:block;">&#128196;</span>
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

        <!-- Certificate Proof -->
        <div style="border:1px solid var(--border);border-radius:8px;padding:16px;background:#F8FAFC;text-align:center;">
          <div style="font-weight:700;font-size:0.85rem;color:var(--navy);margin-bottom:12px;">Certificates / License Proof</div>
          <?php if (!empty($app['cert_proof_path'])): ?>
            <div style="width:140px;height:160px;margin:0 auto 12px;background:#fff;border-radius:6px;overflow:hidden;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;">
              <?php if (preg_match('/\.(jpg|jpeg|png)$/i', $app['cert_proof_path'])): ?>
                <img src="view-doc.php?file=<?php echo urlencode($app['cert_proof_path']); ?>" alt="Cert Proof" style="width:100%;height:100%;object-fit:contain;">
              <?php else: ?>
                <div style="text-align:center;">
                  <span style="font-size:2rem;color:#00B074;display:block;">&#128196;</span>
                  <span style="font-size:0.75rem;font-weight:700;color:var(--text-muted);">PDF Document</span>
                </div>
              <?php endif; ?>
            </div>
            <a href="view-doc.php?file=<?php echo urlencode($app['cert_proof_path']); ?>" target="_blank" style="display:inline-block;background:var(--navy);color:#fff;font-size:0.78rem;font-weight:700;padding:6px 12px;border-radius:4px;text-decoration:none;">
              Inspect Document &nearr;
            </a>
          <?php else: ?>
            <div style="padding:40px 10px;color:var(--text-muted);font-size:0.82rem;">No certificate attached</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- Right Column: Administrative Review Actions -->
  <div>
    <div class="admin-card" style="border-top:4px solid var(--navy);position:sticky;top:84px;">
      <h3 style="font-size:1.15rem;font-weight:800;color:var(--navy);margin-bottom:12px;">Accreditation Review</h3>
      
      <!-- Current Status -->
      <div style="background:<?php echo $sb['bg']; ?>;border:1px solid <?php echo $sb['border']; ?>;padding:12px 14px;border-radius:6px;margin-bottom:18px;">
        <span style="font-size:0.75rem;font-weight:700;color:<?php echo $sb['color']; ?>;text-transform:uppercase;display:block;">Current Status</span>
        <strong style="color:<?php echo $sb['color']; ?>;font-size:0.95rem;"><?php echo $sb['label']; ?></strong>
      </div>

      <!-- Action 1: APPROVE -->
      <?php if ($app['status'] !== 'approved'): ?>
        <form method="POST" action="official-details.php" onsubmit="return confirm('Are you sure you want to APPROVE this official? This will issue an official PPSA accreditation ID.');" style="margin-bottom:14px;">
          <input type="hidden" name="csrf_token" value="<?php echo generatePpsaCsrf(); ?>">
          <input type="hidden" name="id" value="<?php echo $appId; ?>">
          <input type="hidden" name="review_action" value="approve">
          
          <button type="submit" style="width:100%;background:#00B074;color:#fff;border:none;font-weight:800;font-size:0.92rem;padding:12px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
            <span>&#10003;</span> Approve & Issue PPSA Official ID
          </button>
        </form>
      <?php else: ?>
        <div style="background:#E6FBF2;border:1px solid #A7F3D0;padding:14px;border-radius:6px;margin-bottom:14px;text-align:center;">
          <span style="color:#00B074;font-size:0.8rem;font-weight:700;display:block;">OFFICIALLY ACCREDITED</span>
          <strong style="font-family:monospace;font-size:1.1rem;color:var(--navy);"><?php echo htmlspecialchars($app['permanent_registration_no']); ?></strong>
        </div>
      <?php endif; ?>

      <!-- Action 2: REQUEST CORRECTION -->
      <details style="margin-bottom:14px;border:1px solid var(--border);border-radius:6px;background:#F8FAFC;">
        <summary style="padding:10px 14px;font-size:0.88rem;font-weight:700;color:#B45309;cursor:pointer;outline:none;">
          Request Correction &darr;
        </summary>
        <div style="padding:12px 14px;border-top:1px solid var(--border);">
          <form method="POST" action="official-details.php">
            <input type="hidden" name="csrf_token" value="<?php echo generatePpsaCsrf(); ?>">
            <input type="hidden" name="id" value="<?php echo $appId; ?>">
            <input type="hidden" name="review_action" value="request_correction">
            
            <label style="display:block;font-size:0.78rem;font-weight:700;color:var(--text);margin-bottom:6px;">Required Corrections:</label>
            <textarea name="correction_notes" rows="3" required placeholder="Specify missing qualifications or unclear documents..." style="width:100%;padding:8px 10px;border:1.5px solid var(--border);border-radius:4px;font-size:0.82rem;font-family:inherit;margin-bottom:10px;"><?php echo htmlspecialchars($app['correction_notes'] ?? ''); ?></textarea>
            
            <button type="submit" style="width:100%;background:#F59E0B;color:#fff;border:none;font-weight:700;font-size:0.82rem;padding:9px;border-radius:4px;cursor:pointer;">
              Send Correction Request
            </button>
          </form>
        </div>
      </details>

      <!-- Action 3: REJECT -->
      <?php if ($app['status'] !== 'rejected'): ?>
        <details style="margin-bottom:18px;border:1px solid #FECACA;border-radius:6px;background:#FEF2F2;">
          <summary style="padding:10px 14px;font-size:0.88rem;font-weight:700;color:#DC2626;cursor:pointer;outline:none;">
            Reject Application &darr;
          </summary>
          <div style="padding:12px 14px;border-top:1px solid #FECACA;">
            <form method="POST" action="official-details.php" onsubmit="return confirm('Are you sure you want to REJECT this official application?');">
              <input type="hidden" name="csrf_token" value="<?php echo generatePpsaCsrf(); ?>">
              <input type="hidden" name="id" value="<?php echo $appId; ?>">
              <input type="hidden" name="review_action" value="reject">
              
              <label style="display:block;font-size:0.78rem;font-weight:700;color:#991B1B;margin-bottom:6px;">Rejection Reason:</label>
              <textarea name="rejection_reason" rows="3" required placeholder="Administrative reason..." style="width:100%;padding:8px 10px;border:1.5px solid #FCA5A5;border-radius:4px;font-size:0.82rem;font-family:inherit;margin-bottom:10px;"><?php echo htmlspecialchars($app['rejection_reason'] ?? ''); ?></textarea>
              
              <button type="submit" style="width:100%;background:#DC2626;color:#fff;border:none;font-weight:700;font-size:0.82rem;padding:9px;border-radius:4px;cursor:pointer;">
                Confirm Rejection
              </button>
            </form>
          </div>
        </details>
      <?php endif; ?>

      <!-- Fast Contact Links -->
      <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px;">
        <span style="font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;margin-bottom:8px;">Direct Communications</span>
        <div style="display:flex;flex-direction:column;gap:6px;font-size:0.82rem;">
          <a href="mailto:<?php echo htmlspecialchars($app['email']); ?>?subject=Regarding your PPSA Official Registration (Ref: <?php echo htmlspecialchars($app['reference_id']); ?>)" style="color:var(--navy);font-weight:600;text-decoration:none;">
            &#9993; Email Official
          </a>
          <a href="tel:<?php echo htmlspecialchars($app['mobile_phone']); ?>" style="color:var(--navy);font-weight:600;text-decoration:none;">
            &#128222; Call: <?php echo htmlspecialchars($app['mobile_phone']); ?>
          </a>
        </div>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
