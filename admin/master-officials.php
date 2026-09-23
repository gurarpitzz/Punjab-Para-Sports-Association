<?php
// admin/master-officials.php - Master Approved PPSA Officials Registry with CSV Export

$pageTitle = "Master Official Registry";
require_once __DIR__ . '/admin_header.php';

$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$district = trim($_GET['district'] ?? '');

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $db) {
    try {
        $where = ["1=1"];
        $params = [];

        if ($search !== '') {
            $where[] = "(full_name LIKE ? OR registration_no LIKE ? OR mobile_phone LIKE ? OR email LIKE ?)";
            $wild = "%{$search}%";
            $params[] = $wild; $params[] = $wild; $params[] = $wild; $params[] = $wild;
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
        $stmt = $db->prepare("
            SELECT registration_no, full_name, official_category, mobile_phone, email, district, approved_at
            FROM ppsa_officials 
            WHERE {$whereClause}
            ORDER BY id DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ppsa_master_officials_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Official PPSA Registration No', 'Full Name', 'Official Category', 'Mobile Phone', 'Email', 'District', 'Approved Date']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['registration_no'],
                $row['full_name'],
                strtoupper(str_replace('_', ' ', $row['official_category'])),
                $row['mobile_phone'],
                $row['email'],
                $row['district'],
                date('d M Y, h:i A', strtotime($row['approved_at']))
            ]);
        }
        fclose($output);
        exit;
    } catch (\Throwable $e) {
        error_log("Official CSV Export Error: " . $e->getMessage());
    }
}

$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 25;
$offset   = ($page - 1) * $limit;

$officials = [];
$totalRows = 0;
$totalAthletesCount = 0;
$districtsList = [];

if ($db) {
    try {
        $totalAthletesCount = (int)$db->query("SELECT COUNT(*) FROM ppsa_athletes")->fetchColumn();
        $districtsList = $db->query("SELECT DISTINCT district FROM ppsa_officials WHERE district != '' ORDER BY district ASC")->fetchAll(PDO::FETCH_COLUMN);

        $where = ["1=1"];
        $params = [];

        if ($search !== '') {
            $where[] = "(full_name LIKE ? OR registration_no LIKE ? OR mobile_phone LIKE ? OR email LIKE ?)";
            $wild = "%{$search}%";
            $params[] = $wild; $params[] = $wild; $params[] = $wild; $params[] = $wild;
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

        $countStmt = $db->prepare("SELECT COUNT(*) FROM ppsa_officials WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT id, registration_no, application_id, full_name, official_category, mobile_phone, email, 
                   district, photo_path, approved_at
            FROM ppsa_officials
            WHERE {$whereClause}
            ORDER BY id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $officials = $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log("Master Officials Query Error: " . $e->getMessage());
    }
}

$totalPages = ceil($totalRows / $limit);
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Master Official Registry</h1>
    <div class="admin-page-subtitle">Accredited technical officials, coaches, classifiers, referees & state volunteers</div>
  </div>
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="master-officials.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" style="background:#00B074;color:#fff;font-weight:700;font-size:0.85rem;padding:9px 16px;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:7px;">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Export CSV
    </a>
    <span style="font-weight:700;font-size:0.9rem;color:var(--text-muted);">Total Accredited: <strong><?php echo $totalRows; ?></strong></span>
  </div>
</div>

<!-- Switcher Tabs Between Athletes and Officials Master Registries -->
<div style="display:flex;gap:10px;margin-bottom:20px;">
  <a href="athletes.php" style="padding:9px 20px;border-radius:8px;font-weight:700;font-size:0.88rem;text-decoration:none;background:#fff;color:var(--navy);border:1.5px solid var(--border);display:inline-flex;align-items:center;gap:8px;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg> Athletes Registry <span style="background:#E2E8F0;color:var(--navy);font-size:0.75rem;padding:2px 8px;border-radius:12px;font-weight:800;"><?php echo $totalAthletesCount; ?></span>
  </a>
  <a href="master-officials.php" style="padding:9px 20px;border-radius:8px;font-weight:700;font-size:0.88rem;text-decoration:none;background:var(--navy);color:#fff;border:1.5px solid var(--navy);display:inline-flex;align-items:center;gap:8px;box-shadow:0 2px 8px rgba(14,31,75,0.25);">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg> Officials Registry <span style="background:#FFC400;color:var(--navy);font-size:0.75rem;padding:2px 8px;border-radius:12px;font-weight:800;"><?php echo $totalRows; ?></span>
  </a>
</div>

<!-- Filters Bar -->
<div class="admin-card" style="padding:18px 20px;margin-bottom:20px;">
  <form method="GET" action="master-officials.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Name, Reg No, Mobile..." style="flex:1;min-width:240px;height:40px;padding:0 12px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;">
    
    <select name="category" style="height:40px;padding:0 10px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;background:#fff;">
      <option value="">All Official Roles</option>
      <option value="coach" <?php echo $category === 'coach' ? 'selected' : ''; ?>>Coach</option>
      <option value="referee" <?php echo $category === 'referee' ? 'selected' : ''; ?>>Referee / Technical Official</option>
      <option value="classifier" <?php echo $category === 'classifier' ? 'selected' : ''; ?>>Medical / Technical Classifier</option>
      <option value="volunteer" <?php echo $category === 'volunteer' ? 'selected' : ''; ?>>Volunteer Staff</option>
      <option value="ramp_operator" <?php echo $category === 'ramp_operator' ? 'selected' : ''; ?>>Boccia Ramp Operator</option>
      <option value="escort" <?php echo $category === 'escort' ? 'selected' : ''; ?>>Athlete Escort</option>
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
    <a href="master-officials.php" style="height:40px;padding:0 14px;background:#F1F5F9;color:var(--text);border:1px solid var(--border);border-radius:6px;font-weight:600;font-size:0.88rem;display:inline-flex;align-items:center;text-decoration:none;">
      Reset
    </a>
  </form>
</div>

<!-- Officials Registry Data Table -->
<div class="admin-card" style="padding:0;overflow:hidden;">
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.88rem;text-align:left;">
      <thead>
        <tr style="background:#F8FAFC;border-bottom:2px solid var(--border);color:var(--navy);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.5px;">
          <th style="padding:14px 16px;">State Reg Number</th>
          <th style="padding:14px 16px;">Official Identity</th>
          <th style="padding:14px 16px;">Role / Designation</th>
          <th style="padding:14px 16px;">District</th>
          <th style="padding:14px 16px;">Approved Date</th>
          <th style="padding:14px 16px;text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($officials)): ?>
          <tr>
            <td colspan="6" style="padding:48px 20px;text-align:center;color:var(--text-muted);">
              <div style="margin-bottom:12px;color:var(--text-muted);opacity:0.6;">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
              </div>
              <div style="font-size:1.1rem;font-weight:700;color:var(--navy);margin-bottom:6px;">No accredited officials in master registry</div>
              <div style="font-size:0.85rem;">Official applications will appear here once approved from the <a href="officials.php" style="color:var(--navy);font-weight:700;">Official Intake Queue</a>.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($officials as $off): ?>
            <tr style="border-bottom:1px solid var(--border);transition:background 0.15s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
              
              <!-- State Registration Number Badge -->
              <td style="padding:14px 16px;">
                <span style="font-family:monospace;font-weight:800;font-size:0.88rem;background:#E0F2FE;color:#0369A1;padding:4px 9px;border-radius:4px;border:1px solid #BAE6FD;letter-spacing:0.5px;">
                  <?php echo htmlspecialchars($off['registration_no']); ?>
                </span>
              </td>

              <!-- Official Identity -->
              <td style="padding:14px 16px;">
                <div style="display:flex;align-items:center;gap:12px;">
                  <?php if (!empty($off['photo_path'])): ?>
                    <img src="view-doc.php?file=<?php echo urlencode($off['photo_path']); ?>" alt="Official Photo" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:1.5px solid var(--border);">
                  <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:#E2E8F0;display:flex;align-items:center;justify-content:center;color:var(--navy);font-weight:800;font-size:0.9rem;">
                      <?php echo strtoupper(substr($off['full_name'], 0, 1)); ?>
                    </div>
                  <?php endif; ?>
                  <div>
                    <div style="font-weight:700;color:var(--navy);"><?php echo htmlspecialchars($off['full_name']); ?></div>
                    <div style="font-size:0.75rem;color:var(--text-muted);"><?php echo htmlspecialchars($off['mobile_phone']); ?></div>
                  </div>
                </div>
              </td>

              <!-- Role / Category -->
              <td style="padding:14px 16px;">
                <span style="display:inline-block;background:#FEF3C7;color:#92400E;font-size:0.75rem;font-weight:800;padding:3px 8px;border-radius:4px;text-transform:uppercase;border:1px solid #FDE68A;">
                  <?php echo htmlspecialchars(str_replace('_', ' ', $off['official_category'])); ?>
                </span>
              </td>

              <!-- District -->
              <td style="padding:14px 16px;font-weight:600;color:var(--text);">
                <?php echo htmlspecialchars($off['district']); ?>
              </td>

              <!-- Approved Date -->
              <td style="padding:14px 16px;font-size:0.82rem;color:var(--text-muted);">
                <?php echo date('d M Y', strtotime($off['approved_at'])); ?>
              </td>

              <!-- View Dossier -->
              <td style="padding:14px 16px;text-align:right;">
                <a href="official-details.php?id=<?php echo $off['application_id']; ?>" style="display:inline-block;background:var(--navy);color:#fff;font-size:0.8rem;font-weight:700;padding:6px 14px;border-radius:6px;text-decoration:none;">
                  View Dossier &rarr;
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
