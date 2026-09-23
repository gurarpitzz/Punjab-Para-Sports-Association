<?php
// admin/athletes.php - Master Approved State Para Athlete Registry with CSV Export

$pageTitle = "Master Athlete Registry";
require_once __DIR__ . '/admin_header.php';

$search   = trim($_GET['search'] ?? '');
$sport    = trim($_GET['sport'] ?? '');
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
        if ($sport !== '') {
            $where[] = "sport_game = ?";
            $params[] = $sport;
        }
        if ($district !== '') {
            $where[] = "district = ?";
            $params[] = $district;
        }

        $whereClause = implode(" AND ", $where);
        $stmt = $db->prepare("
            SELECT registration_no, full_name, gender, dob, mobile_phone, email, district, 
                   sport_game, classification, weight_category, event_discipline, approved_at
            FROM ppsa_athletes 
            WHERE {$whereClause}
            ORDER BY id DESC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ppsa_master_athletes_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['State Registration No', 'Full Name', 'Gender', 'DOB', 'Mobile Phone', 'Email', 'District', 'Sport Game', 'Classification', 'Weight Category', 'Event Discipline', 'Approved Date']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['registration_no'],
                $row['full_name'],
                ucfirst($row['gender']),
                $row['dob'],
                $row['mobile_phone'],
                $row['email'],
                $row['district'],
                ucwords(str_replace('_', ' ', $row['sport_game'])),
                $row['classification'],
                $row['weight_category'],
                $row['event_discipline'],
                $row['approved_at']
            ]);
        }
        fclose($output);
        exit();
    } catch (\Throwable $e) {
        error_log("CSV Export Error: " . $e->getMessage());
    }
}

$page     = max(1, (int)($_GET['page'] ?? 1));
$limit    = 25;
$offset   = ($page - 1) * $limit;

$athletes = [];
$totalRows = 0;
$districtsList = [];

if ($db) {
    try {
        $districtsList = $db->query("SELECT DISTINCT district FROM ppsa_athletes WHERE district != '' ORDER BY district ASC")->fetchAll(PDO::FETCH_COLUMN);

        $where = ["1=1"];
        $params = [];

        if ($search !== '') {
            $where[] = "(full_name LIKE ? OR registration_no LIKE ? OR mobile_phone LIKE ? OR email LIKE ?)";
            $wild = "%{$search}%";
            $params[] = $wild; $params[] = $wild; $params[] = $wild; $params[] = $wild;
        }
        if ($sport !== '') {
            $where[] = "sport_game = ?";
            $params[] = $sport;
        }
        if ($district !== '') {
            $where[] = "district = ?";
            $params[] = $district;
        }

        $whereClause = implode(" AND ", $where);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM ppsa_athletes WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT id, registration_no, application_id, full_name, gender, dob, mobile_phone, email, 
                   district, sport_game, classification, weight_category, event_discipline, photo_path, approved_at
            FROM ppsa_athletes
            WHERE {$whereClause}
            ORDER BY id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $athletes = $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log("Athletes Query Error: " . $e->getMessage());
    }
}

$totalPages = ceil($totalRows / $limit);
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Master Athlete Registry</h1>
    <div class="admin-page-subtitle">Officially certified Punjab Para Sports athletes accredited for the State Games</div>
  </div>
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="athletes.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" style="background:#00B074;color:#fff;font-weight:700;font-size:0.85rem;padding:9px 16px;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
      <span>&#128190;</span> Export CSV
    </a>
    <span style="font-weight:700;font-size:0.9rem;color:var(--text-muted);">Total Accredited: <strong><?php echo $totalRows; ?></strong></span>
  </div>
</div>

<!-- Filters Bar -->
<div class="admin-card" style="padding:18px 20px;margin-bottom:20px;">
  <form method="GET" action="athletes.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by Name, Reg No, Mobile..." style="flex:1;min-width:240px;height:40px;padding:0 12px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;">
    
    <select name="sport" style="height:40px;padding:0 10px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;background:#fff;">
      <option value="">All 4 State Games Sports</option>
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

    <button type="submit" style="height:40px;padding:0 20px;background:var(--navy);color:#fff;border:none;border-radius:6px;font-weight:700;font-size:0.88rem;cursor:pointer;">
      Filter
    </button>
    <a href="athletes.php" style="height:40px;padding:0 14px;background:#F1F5F9;color:var(--text);border:1px solid var(--border);border-radius:6px;font-weight:600;font-size:0.88rem;display:inline-flex;align-items:center;text-decoration:none;">
      Reset
    </a>
  </form>
</div>

<!-- Registry Table Card -->
<div class="admin-card" style="padding:0;overflow:hidden;">
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;text-align:left;font-size:0.88rem;">
      <thead>
        <tr style="background:#F8FAFC;border-bottom:2px solid var(--border);color:var(--navy);font-weight:700;text-transform:uppercase;font-size:0.75rem;letter-spacing:0.04em;">
          <th style="padding:14px 16px;">State Reg Number</th>
          <th style="padding:14px 16px;">Athlete Identity</th>
          <th style="padding:14px 16px;">Sport & Discipline</th>
          <th style="padding:14px 16px;">Classification</th>
          <th style="padding:14px 16px;">District</th>
          <th style="padding:14px 16px;">Approved Date</th>
          <th style="padding:14px 16px;text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($athletes)): ?>
          <tr>
            <td colspan="7" style="padding:50px 20px;text-align:center;color:var(--text-muted);">
              <div style="font-size:1.1rem;font-weight:700;color:var(--navy);margin-bottom:6px;">No accredited athletes in master registry</div>
              <div>Approved athlete registrations from the intake queue will appear here.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($athletes as $ath): ?>
            <tr style="border-bottom:1px solid var(--border);transition:background 0.15s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
              
              <!-- State Registration No -->
              <td style="padding:14px 16px;">
                <span style="font-family:monospace;font-weight:800;color:#00B074;background:#E6FBF2;padding:4px 10px;border-radius:4px;border:1px solid #A7F3D0;display:inline-block;font-size:0.88rem;">
                  <?php echo htmlspecialchars($ath['registration_no']); ?>
                </span>
              </td>

              <!-- Athlete Name & Photo -->
              <td style="padding:14px 16px;">
                <div style="display:flex;align-items:center;gap:12px;">
                  <div style="width:42px;height:42px;border-radius:6px;background:#E2E8F0;overflow:hidden;flex-shrink:0;">
                    <?php if (!empty($ath['photo_path'])): ?>
                      <img src="view-doc.php?file=<?php echo urlencode($ath['photo_path']); ?>" alt="Photo" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                      <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:#94A3B8;font-weight:800;">
                        <?php echo strtoupper(substr($ath['full_name'], 0, 1)); ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div>
                    <strong style="color:var(--navy);font-size:0.92rem;display:block;"><?php echo htmlspecialchars($ath['full_name']); ?></strong>
                    <span style="font-size:0.75rem;color:var(--text-muted);"><?php echo htmlspecialchars(ucfirst($ath['gender'])); ?> • <?php echo htmlspecialchars($ath['mobile_phone']); ?></span>
                  </div>
                </div>
              </td>

              <!-- Sport & Event -->
              <td style="padding:14px 16px;">
                <span style="display:inline-block;background:#F1F5F9;color:var(--navy);font-weight:700;font-size:0.75rem;padding:2px 8px;border-radius:4px;margin-bottom:2px;">
                  <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $ath['sport_game']))); ?>
                </span>
                <div style="font-weight:600;font-size:0.85rem;color:var(--text);"><?php echo htmlspecialchars($ath['event_discipline']); ?></div>
              </td>

              <!-- Classification / Weight -->
              <td style="padding:14px 16px;">
                <?php if (!empty($ath['classification'])): ?>
                  <span style="font-weight:700;color:#00B074;font-size:0.82rem;background:#E6FBF2;padding:2px 8px;border-radius:4px;">
                    <?php echo htmlspecialchars($ath['classification']); ?>
                  </span>
                <?php elseif (!empty($ath['weight_category'])): ?>
                  <span style="font-weight:700;color:var(--navy);font-size:0.82rem;background:#F1F5F9;padding:2px 8px;border-radius:4px;">
                    <?php echo htmlspecialchars($ath['weight_category']); ?>
                  </span>
                <?php else: ?>
                  <span style="color:var(--text-muted);font-size:0.75rem;">Open</span>
                <?php endif; ?>
              </td>

              <!-- District -->
              <td style="padding:14px 16px;font-weight:600;color:var(--text);"><?php echo htmlspecialchars($ath['district']); ?></td>

              <!-- Approved Date -->
              <td style="padding:14px 16px;font-size:0.82rem;color:var(--text-muted);">
                <?php echo date('d M Y', strtotime($ath['approved_at'])); ?>
              </td>

              <!-- Actions -->
              <td style="padding:14px 16px;text-align:right;">
                <a href="athlete-details.php?id=<?php echo $ath['application_id']; ?>" style="display:inline-block;background:var(--navy);color:#fff;font-size:0.8rem;font-weight:700;padding:6px 12px;border-radius:5px;text-decoration:none;">
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
