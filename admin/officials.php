<?php
// admin/officials.php - Master Officials Application Intake Queue

$pageTitle = "Official Intake Queue";
require_once __DIR__ . '/admin_header.php';

$search   = trim($_GET['search'] ?? '');
$status   = trim($_GET['status'] ?? '');
$category = trim($_GET['category'] ?? '');
$district = trim($_GET['district'] ?? '');

$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 20;
$offset   = ($page - 1) * $limit;

$officials = [];
$totalRows = 0;
$districtsList = [];

if ($db) {
    try {
        $districtsList = $db->query("SELECT DISTINCT district FROM ppsa_official_applications WHERE district != '' ORDER BY district ASC")->fetchAll(PDO::FETCH_COLUMN);

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

        if ($category !== '') {
            $where[] = "official_category = ?";
            $params[] = $category;
        }

        if ($district !== '') {
            $where[] = "district = ?";
            $params[] = $district;
        }

        $whereClause = implode(" AND ", $where);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM ppsa_official_applications WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT id, reference_id, permanent_registration_no, full_name, gender, dob, mobile_phone,
                   official_category, classifier_type, qualifications, experience_years, district, photo_path,
                   status, has_duplicate_flag, created_at
            FROM ppsa_official_applications
            WHERE {$whereClause}
            ORDER BY id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $officials = $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log("Officials Query Error: " . $e->getMessage());
    }
}

$totalPages = ceil($totalRows / $limit);
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Official Intake Queue</h1>
    <div class="admin-page-subtitle">Review Coaches, Technical Officials, Classifiers, Ramp Operators & Escorts for State Games accreditation</div>
  </div>
  <div>
    <span style="font-weight:700;font-size:0.9rem;color:var(--text-muted);">Total Applications: <strong><?php echo $totalRows; ?></strong></span>
  </div>
</div>

<!-- Filters Bar -->
<div class="admin-card" style="padding:18px 20px;margin-bottom:20px;">
  <form method="GET" action="officials.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Name, Ref ID, Aadhaar, Phone..." style="flex:1;min-width:240px;height:40px;padding:0 12px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;">
    
    <select name="status" style="height:40px;padding:0 10px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;background:#fff;">
      <option value="">All Review Statuses</option>
      <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
      <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
      <option value="correction_required" <?php echo $status === 'correction_required' ? 'selected' : ''; ?>>Correction Requested</option>
      <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
    </select>

    <select name="category" style="height:40px;padding:0 10px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;background:#fff;">
      <option value="">All Official Roles</option>
      <option value="coach" <?php echo $category === 'coach' ? 'selected' : ''; ?>>Coach</option>
      <option value="referee" <?php echo $category === 'referee' ? 'selected' : ''; ?>>Referee / Technical Official</option>
      <option value="classifier" <?php echo $category === 'classifier' ? 'selected' : ''; ?>>Classifier</option>
      <option value="ramp_operator" <?php echo $category === 'ramp_operator' ? 'selected' : ''; ?>>Ramp Operator / Assistant</option>
      <option value="escort" <?php echo $category === 'escort' ? 'selected' : ''; ?>>Escort / Guide</option>
      <option value="volunteer" <?php echo $category === 'volunteer' ? 'selected' : ''; ?>>Volunteer</option>
    </select>

    <select name="district" style="height:40px;padding:0 10px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;background:#fff;">
      <option value="">All Districts</option>
      <?php foreach ($districtsList as $d): ?>
        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $district === $d ? 'selected' : ''; ?>><?php echo htmlspecialchars($d); ?></option>
      <?php endforeach; ?>
    </select>

    <button type="submit" style="height:40px;padding:0 20px;background:var(--navy);color:#fff;border:none;border-radius:6px;font-weight:700;font-size:0.88rem;cursor:pointer;">
      Filter
    </button>
    <a href="officials.php" style="height:40px;padding:0 14px;background:#F1F5F9;color:var(--text);border:1px solid var(--border);border-radius:6px;font-weight:600;font-size:0.88rem;display:inline-flex;align-items:center;text-decoration:none;">
      Reset
    </a>
  </form>
</div>

<!-- Officials Table Card -->
<div class="admin-card" style="padding:0;overflow:hidden;">
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;text-align:left;font-size:0.88rem;">
      <thead>
        <tr style="background:#F8FAFC;border-bottom:2px solid var(--border);color:var(--navy);font-weight:700;text-transform:uppercase;font-size:0.75rem;letter-spacing:0.04em;">
          <th style="padding:14px 16px;">Applicant</th>
          <th style="padding:14px 16px;">Role / Designation</th>
          <th style="padding:14px 16px;">Experience & Specs</th>
          <th style="padding:14px 16px;">District</th>
          <th style="padding:14px 16px;">Contact</th>
          <th style="padding:14px 16px;">Status</th>
          <th style="padding:14px 16px;text-align:right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($officials)): ?>
          <tr>
            <td colspan="7" style="padding:50px 20px;text-align:center;color:var(--text-muted);">
              <div style="font-size:1.1rem;font-weight:700;color:var(--navy);margin-bottom:6px;">No official applications found</div>
              <div>Try adjusting your search filters or check back when new applications are submitted.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($officials as $off): ?>
            <?php
              $statusMap = [
                'pending'             => ['label' => 'Pending Review', 'bg' => '#FEF3C7', 'color' => '#D97706'],
                'under_review'        => ['label' => 'Under Review', 'bg' => '#EFF6FF', 'color' => '#2563EB'],
                'approved'            => ['label' => 'Approved', 'bg' => '#DCFCE7', 'color' => '#16A34A'],
                'rejected'            => ['label' => 'Rejected', 'bg' => '#FEE2E2', 'color' => '#DC2626'],
                'correction_required' => ['label' => 'Correction Req', 'bg' => '#FFFBEB', 'color' => '#B45309'],
              ];
              $st = $statusMap[$off['status']] ?? ['label' => ucfirst($off['status']), 'bg' => '#F1F5F9', 'color' => '#475569'];
            ?>
            <tr style="border-bottom:1px solid var(--border);transition:background 0.15s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
              
              <!-- Applicant Identity -->
              <td style="padding:14px 16px;">
                <div style="display:flex;align-items:center;gap:12px;">
                  <div style="width:42px;height:42px;border-radius:6px;background:#E2E8F0;overflow:hidden;flex-shrink:0;">
                    <?php if (!empty($off['photo_path'])): ?>
                      <img src="view-doc.php?file=<?php echo urlencode($off['photo_path']); ?>" alt="Photo" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                      <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#94A3B8;font-weight:800;font-size:1rem;">
                        <?php echo strtoupper(substr($off['full_name'], 0, 1)); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div>
                    <strong style="color:var(--navy);font-size:0.92rem;display:block;"><?php echo htmlspecialchars($off['full_name']); ?></strong>
                    <span style="font-family:monospace;font-size:0.75rem;color:var(--text-muted);"><?php echo htmlspecialchars($off['reference_id']); ?></span>
                    <?php if (!empty($off['permanent_registration_no'])): ?>
                      <span style="display:block;font-family:monospace;font-size:0.75rem;color:#00B074;font-weight:700;"><?php echo htmlspecialchars($off['permanent_registration_no']); ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </td>

              <!-- Role / Designation -->
              <td style="padding:14px 16px;">
                <span style="display:inline-block;background:var(--navy);color:#fff;font-weight:700;font-size:0.75rem;padding:3px 10px;border-radius:4px;">
                  <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $off['official_category']))); ?>
                </span>
                <?php if (!empty($off['classifier_type'])): ?>
                  <div style="font-size:0.75rem;color:var(--text-muted);font-weight:600;margin-top:2px;">
                    Type: <?php echo htmlspecialchars(ucfirst($off['classifier_type'])); ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- Experience & Qualifications -->
              <td style="padding:14px 16px;">
                <div style="font-weight:600;color:var(--text);font-size:0.85rem;">
                  <?php echo (int)$off['experience_years']; ?> Years Experience
                </div>
                <?php if (!empty($off['qualifications'])): ?>
                  <div style="font-size:0.75rem;color:var(--text-muted);max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?php echo htmlspecialchars($off['qualifications']); ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- District -->
              <td style="padding:14px 16px;font-weight:600;color:var(--text);"><?php echo htmlspecialchars($off['district']); ?></td>

              <!-- Contact -->
              <td style="padding:14px 16px;font-size:0.82rem;">
                <div><?php echo htmlspecialchars($off['mobile_phone']); ?></div>
                <div style="color:var(--text-muted);font-size:0.75rem;"><?php echo htmlspecialchars($off['email']); ?></div>
              </td>

              <!-- Status -->
              <td style="padding:14px 16px;">
                <span style="background:<?php echo $st['bg']; ?>;color:<?php echo $st['color']; ?>;font-weight:700;font-size:0.75rem;padding:3px 8px;border-radius:999px;">
                  <?php echo $st['label']; ?>
                </span>
              </td>

              <!-- Review Action Link -->
              <td style="padding:14px 16px;text-align:right;">
                <a href="official-details.php?id=<?php echo $off['id']; ?>" style="display:inline-block;background:var(--navy);color:#fff;font-size:0.82rem;font-weight:700;padding:7px 14px;border-radius:6px;text-decoration:none;transition:background 0.2s;" onmouseover="this.style.background='#00B074'" onmouseout="this.style.background='var(--navy)'">
                  Review &rarr;
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
