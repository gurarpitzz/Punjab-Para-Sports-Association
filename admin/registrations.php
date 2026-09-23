<?php
// admin/registrations.php - Master Athlete Application Intake Queue

$pageTitle = "Athlete Intake Queue";
require_once __DIR__ . '/admin_header.php';

$search   = trim($_GET['search'] ?? '');
$status   = trim($_GET['status'] ?? '');
$sport    = trim($_GET['sport'] ?? '');
$district = trim($_GET['district'] ?? '');
$dupOnly  = !empty($_GET['duplicates_only']);

$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 20;
$offset   = ($page - 1) * $limit;

$applications = [];
$totalRows    = 0;
$districtsList = [];

if ($db) {
    try {
        // Fetch distinct districts for filter
        $districtsList = $db->query("SELECT DISTINCT district FROM ppsa_athlete_applications WHERE district != '' ORDER BY district ASC")->fetchAll(PDO::FETCH_COLUMN);

        $where = ["1=1"];
        $params = [];

        if ($search !== '') {
            $where[] = "(full_name LIKE ? OR reference_id LIKE ? OR aadhaar_number LIKE ? OR mobile_phone LIKE ?)";
            $wild = "%{$search}%";
            $params[] = $wild; $params[] = $wild; $params[] = $wild; $params[] = $wild;
        }

        if ($status !== '' && in_array($status, ['pending', 'under_review', 'approved', 'rejected', 'correction_required'])) {
            $where[] = "status = ?";
            $params[] = $status;
        }

        if ($sport !== '') {
            $where[] = "sport_game = ?";
            $params[] = $sport;
        }

        if ($district !== '') {
            $where[] = "district = ?";
            $params[] = $district;
        }

        if ($dupOnly) {
            $where[] = "has_duplicate_flag = 1";
        }

        $whereClause = implode(" AND ", $where);

        // Count total matching
        $countStmt = $db->prepare("SELECT COUNT(*) FROM ppsa_athlete_applications WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        // Fetch paginated
        $stmt = $db->prepare("
            SELECT id, reference_id, permanent_registration_no, full_name, gender, dob, mobile_phone,
                   sport_game, classification, weight_category, event_discipline, district, photo_path,
                   status, has_duplicate_flag, created_at
            FROM ppsa_athlete_applications
            WHERE {$whereClause}
            ORDER BY id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $applications = $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log("Registrations Query Error: " . $e->getMessage());
    }
}

$totalPages = ceil($totalRows / $limit);
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Athlete Intake Queue</h1>
    <div class="admin-page-subtitle">Verify identity documents, classifications, duplicate flags, and issue PPSA state numbers</div>
  </div>
  <div>
    <span style="font-weight:700;font-size:0.9rem;color:var(--text-muted);">Total Applications: <strong><?php echo $totalRows; ?></strong></span>
  </div>
</div>

<!-- Filters Bar -->
<div class="admin-card" style="padding:18px 20px;margin-bottom:20px;">
  <form method="GET" action="registrations.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Name, Ref ID, Aadhaar..." style="flex:1;min-width:240px;height:40px;padding:0 12px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;">
    
    <select name="status" style="height:40px;padding:0 10px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;background:#fff;">
      <option value="">All Review Statuses</option>
      <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
      <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
      <option value="correction_required" <?php echo $status === 'correction_required' ? 'selected' : ''; ?>>Correction Requested</option>
      <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
    </select>

    <select name="sport" style="height:40px;padding:0 10px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;background:#fff;">
      <option value="">All Sports</option>
      <option value="para_athletics" <?php echo $sport === 'para_athletics' ? 'selected' : ''; ?>>Para Athletics</option>
      <option value="powerlifting" <?php echo $sport === 'powerlifting' ? 'selected' : ''; ?>>Power Lifting</option>
      <option value="para_badminton" <?php echo $sport === 'para_badminton' ? 'selected' : ''; ?>>Para Badminton</option>
      <option value="wheelchair_basketball" <?php echo $sport === 'wheelchair_basketball' ? 'selected' : ''; ?>>Wheelchair Basketball</option>
    </select>

    <select name="district" style="height:40px;padding:0 10px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;background:#fff;">
      <option value="">All Districts</option>
      <?php foreach ($districtsList as $d): ?>
        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $district === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
      <?php endforeach; ?>
    </select>

    <label style="display:inline-flex;align-items:center;gap:6px;font-size:0.85rem;font-weight:700;color:var(--danger);cursor:pointer;">
      <input type="checkbox" name="duplicates_only" value="1" <?php echo $dupOnly ? 'checked' : ''; ?>>
      Duplicates Only
    </label>

    <button type="submit" style="height:40px;padding:0 18px;background:var(--navy);color:#fff;border:none;border-radius:6px;font-weight:700;cursor:pointer;">Filter</button>
    <?php if ($search || $status || $sport || $district || $dupOnly): ?>
      <a href="registrations.php" style="color:var(--text-muted);font-size:0.85rem;text-decoration:none;font-weight:600;margin-left:4px;">Reset</a>
    <?php endif; ?>
  </form>
</div>

<!-- Applications Table -->
<div class="admin-card" style="padding:0;overflow:hidden;">
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.88rem;text-align:left;">
      <thead>
        <tr style="background:#F8FAFC;border-bottom:2px solid var(--border);color:var(--text-muted);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;">
          <th style="padding:14px 16px;">Applicant</th>
          <th style="padding:14px 16px;">Reference ID</th>
          <th style="padding:14px 16px;">Sport & Event Discipline</th>
          <th style="padding:14px 16px;">District</th>
          <th style="padding:14px 16px;">Duplicate Signals</th>
          <th style="padding:14px 16px;">Status</th>
          <th style="padding:14px 16px;text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($applications)): ?>
          <tr><td colspan="7" style="padding:40px;text-align:center;color:var(--text-muted);">No applications match the specified criteria.</td></tr>
        <?php else: ?>
          <?php foreach ($applications as $app): 
            $statusBadge = match($app['status']) {
              'approved' => '<span style="background:#DCFCE7;color:#16A34A;font-weight:800;font-size:0.72rem;padding:3px 9px;border-radius:4px;">APPROVED</span>',
              'rejected' => '<span style="background:#FEE2E2;color:#DC2626;font-weight:800;font-size:0.72rem;padding:3px 9px;border-radius:4px;">REJECTED</span>',
              'correction_required' => '<span style="background:#FEF3C7;color:#D97706;font-weight:800;font-size:0.72rem;padding:3px 9px;border-radius:4px;">NEEDS INFO</span>',
              default => '<span style="background:#EFF6FF;color:#2563EB;font-weight:800;font-size:0.72rem;padding:3px 9px;border-radius:4px;">PENDING</span>'
            };
          ?>
            <tr style="border-bottom:1px solid var(--border);transition:background 0.15s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
              <!-- Applicant Profile with Photo -->
              <td style="padding:14px 16px;">
                <div style="display:flex;align-items:center;gap:12px;">
                  <?php if (!empty($app['photo_path'])): ?>
                    <img src="view-doc.php?file=<?php echo urlencode($app['photo_path']); ?>" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:1.5px solid var(--border);">
                  <?php else: ?>
                    <div style="width:42px;height:42px;border-radius:50%;background:#E2E8F0;display:flex;align-items:center;justify-content:center;color:#64748B;font-weight:800;font-size:14px;">
                      <?php echo strtoupper(substr($app['full_name'], 0, 1)); ?>
                    </div>
                  <?php endif; ?>
                  <div>
                    <strong style="color:var(--text);font-size:0.92rem;display:block;"><?php echo htmlspecialchars($app['full_name']); ?></strong>
                    <span style="font-size:0.75rem;color:var(--text-muted);"><?php echo ucfirst($app['gender']); ?> &bull; <?php echo htmlspecialchars($app['mobile_phone']); ?></span>
                  </div>
                </div>
              </td>

              <!-- Reference ID / Reg No -->
              <td style="padding:14px 16px;">
                <div style="font-family:monospace;font-weight:800;color:var(--navy);font-size:0.85rem;"><?php echo htmlspecialchars($app['reference_id']); ?></div>
                <?php if (!empty($app['permanent_registration_no'])): ?>
                  <div style="font-family:monospace;font-size:0.75rem;color:var(--green);font-weight:800;"><?php echo htmlspecialchars($app['permanent_registration_no']); ?></div>
                <?php endif; ?>
                <div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;"><?php echo date('d M Y', strtotime($app['created_at'])); ?></div>
              </td>

              <!-- Sport & Event -->
              <td style="padding:14px 16px;">
                <span style="display:inline-block;background:#F1F5F9;color:var(--navy);font-weight:700;font-size:0.75rem;padding:2px 8px;border-radius:4px;margin-bottom:2px;">
                  <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $app['sport_game']))); ?>
                </span>
                <div style="font-weight:600;font-size:0.85rem;color:var(--text);"><?php echo htmlspecialchars($app['event_discipline']); ?></div>
                <?php if (!empty($app['classification'])): ?>
                  <span style="font-size:0.75rem;color:var(--text-muted);font-weight:600;">Class: <?php echo htmlspecialchars($app['classification']); ?></span>
                <?php elseif (!empty($app['weight_category'])): ?>
                  <span style="font-size:0.75rem;color:var(--text-muted);font-weight:600;">Wt: <?php echo htmlspecialchars($app['weight_category']); ?></span>
                <?php endif; ?>
              </td>

              <!-- District -->
              <td style="padding:14px 16px;font-weight:600;color:var(--text);"><?php echo htmlspecialchars($app['district']); ?></td>

              <!-- Duplicate Risk Warning Badge -->
              <td style="padding:14px 16px;">
                <?php if (!empty($app['has_duplicate_flag'])): ?>
                  <span style="display:inline-flex;align-items:center;gap:4px;background:#FEE2E2;color:#DC2626;font-weight:800;font-size:0.72rem;padding:3px 8px;border-radius:4px;border:1px solid #FCA5A5;">
                    &#9888; DUPLICATE RISK
                  </span>
                <?php else: ?>
                  <span style="color:#94A3B8;font-size:0.78rem;">Clean</span>
                <?php endif; ?>
              </td>

              <!-- Status -->
              <td style="padding:14px 16px;"><?php echo $statusBadge; ?></td>

              <!-- Review Action Link -->
              <td style="padding:14px 16px;text-align:right;">
                <a href="athlete-details.php?id=<?php echo $app['id']; ?>" style="display:inline-block;background:var(--navy);color:#fff;font-size:0.82rem;font-weight:700;padding:7px 14px;border-radius:6px;text-decoration:none;transition:background 0.2s;" onmouseover="this.style.background='#00B074'" onmouseout="this.style.background='var(--navy)'">
                  Review Profile &rarr;
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-top:1px solid var(--border);background:#F8FAFC;">
      <span style="font-size:0.82rem;color:var(--text-muted);">Page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong></span>
      <div style="display:flex;gap:6px;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>" style="display:inline-block;padding:5px 11px;font-size:0.82rem;font-weight:700;border-radius:4px;text-decoration:none;<?php echo $p === $page ? 'background:var(--navy);color:#fff;' : 'background:#fff;color:var(--text);border:1px solid var(--border);'; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
