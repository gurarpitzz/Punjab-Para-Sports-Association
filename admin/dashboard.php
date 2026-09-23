<?php
// admin/dashboard.php - Administrative Executive Dashboard

$pageTitle = "Executive Dashboard";
require_once __DIR__ . '/admin_header.php';

$stats = [
    'total_athletes'     => 0,
    'pending_athletes'   => 0,
    'approved_athletes'  => 0,
    'rejected_athletes'  => 0,
    'correction_athletes'=> 0,
    'duplicate_flags'    => 0,
    'total_officials'    => 0,
    'pending_officials'  => 0,
    'sports' => [
        'para_athletics'        => 0,
        'powerlifting'          => 0,
        'para_badminton'        => 0,
        'wheelchair_basketball' => 0
    ]
];

$recentAthletes = [];
$recentOfficials = [];

if ($db) {
    try {
        $stats['total_athletes']      = (int)$db->query("SELECT COUNT(*) FROM ppsa_athlete_applications")->fetchColumn();
        $stats['pending_athletes']    = (int)$db->query("SELECT COUNT(*) FROM ppsa_athlete_applications WHERE status = 'pending'")->fetchColumn();
        $stats['approved_athletes']   = (int)$db->query("SELECT COUNT(*) FROM ppsa_athlete_applications WHERE status = 'approved'")->fetchColumn();
        $stats['rejected_athletes']   = (int)$db->query("SELECT COUNT(*) FROM ppsa_athlete_applications WHERE status = 'rejected'")->fetchColumn();
        $stats['correction_athletes'] = (int)$db->query("SELECT COUNT(*) FROM ppsa_athlete_applications WHERE status = 'correction_required'")->fetchColumn();
        $stats['duplicate_flags']     = (int)$db->query("SELECT COUNT(*) FROM ppsa_duplicate_flags")->fetchColumn();
        
        $stats['total_officials']     = (int)$db->query("SELECT COUNT(*) FROM ppsa_official_applications")->fetchColumn();
        $stats['pending_officials']   = (int)$db->query("SELECT COUNT(*) FROM ppsa_official_applications WHERE status = 'pending'")->fetchColumn();

        // Sports breakdown
        $sportStmt = $db->query("SELECT sport_game, COUNT(*) as count FROM ppsa_athlete_applications GROUP BY sport_game");
        while ($r = $sportStmt->fetch()) {
            if (isset($stats['sports'][$r['sport_game']])) {
                $stats['sports'][$r['sport_game']] = (int)$r['count'];
            }
        }

        // Recent 8 intake applications
        $recentAthletes = $db->query("
            SELECT id, reference_id, full_name, gender, sport_game, classification, weight_category, event_discipline, district, status, has_duplicate_flag, created_at 
            FROM ppsa_athlete_applications 
            ORDER BY id DESC LIMIT 8
        ")->fetchAll();

        // Recent 5 officials
        $recentOfficials = $db->query("
            SELECT id, reference_id, full_name, official_category, district, status, created_at 
            FROM ppsa_official_applications 
            ORDER BY id DESC LIMIT 5
        ")->fetchAll();
    } catch (\Throwable $e) {
        error_log("Dashboard Query Error: " . $e->getMessage());
    }
}
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Federation Intake Dashboard</h1>
    <div class="admin-page-subtitle">Punjab State Para Games (Ludhiana) Intake & Registration Overview</div>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="registrations.php" class="admin-menu-link active" style="background:var(--green);border-color:transparent;color:#fff;font-weight:700;">Open Athlete Queue &rarr;</a>
  </div>
</div>

<!-- Primary Stats Grid -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:16px;margin-bottom:24px;">
  <div class="admin-card" style="margin-bottom:0;border-left:4px solid #3B82F6;">
    <div style="font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Total Applications</div>
    <div style="font-size:2rem;font-weight:900;color:var(--navy);margin:4px 0;font-family:'Outfit';"><?php echo $stats['total_athletes']; ?></div>
    <div style="font-size:0.75rem;color:#64748B;">Athlete entries logged</div>
  </div>

  <div class="admin-card" style="margin-bottom:0;border-left:4px solid var(--warning);">
    <div style="font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Pending Review</div>
    <div style="font-size:2rem;font-weight:900;color:var(--warning);margin:4px 0;font-family:'Outfit';"><?php echo $stats['pending_athletes']; ?></div>
    <div style="font-size:0.75rem;color:#D97706;">Awaiting verification</div>
  </div>

  <div class="admin-card" style="margin-bottom:0;border-left:4px solid var(--green);">
    <div style="font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Approved Athletes</div>
    <div style="font-size:2rem;font-weight:900;color:var(--green);margin:4px 0;font-family:'Outfit';"><?php echo $stats['approved_athletes']; ?></div>
    <div style="font-size:0.75rem;color:#16A34A;">State registration issued</div>
  </div>

  <div class="admin-card" style="margin-bottom:0;border-left:4px solid #8B5CF6;">
    <div style="font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Correction Needed</div>
    <div style="font-size:2rem;font-weight:900;color:#8B5CF6;margin:4px 0;font-family:'Outfit';"><?php echo $stats['correction_athletes']; ?></div>
    <div style="font-size:0.75rem;color:#7C3AED;">Awaiting athlete resubmission</div>
  </div>

  <div class="admin-card" style="margin-bottom:0;border-left:4px solid var(--danger);">
    <div style="font-size:0.8rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Duplicate Flags</div>
    <div style="font-size:2rem;font-weight:900;color:var(--danger);margin:4px 0;font-family:'Outfit';"><?php echo $stats['duplicate_flags']; ?></div>
    <div style="font-size:0.75rem;color:#DC2626;">Aadhaar/Phone collision</div>
  </div>
</div>

<!-- Sports Matrix Distribution Breakdown -->
<div class="admin-card">
  <h2 style="font-family:'Outfit';font-size:1.15rem;font-weight:800;color:var(--navy);margin-bottom:16px;">State Games Discipline Entries (Ludhiana)</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;">
    <div style="background:#F8FAFC;padding:16px;border-radius:8px;border:1px solid var(--border);">
      <div style="font-size:0.82rem;font-weight:700;color:var(--text-muted);">Para Athletics</div>
      <div style="font-size:1.6rem;font-weight:800;color:var(--navy);font-family:'Outfit';"><?php echo $stats['sports']['para_athletics']; ?></div>
      <div style="font-size:0.72rem;color:#64748B;">Track, Javelin, Shot Put, Club, Discus</div>
    </div>
    <div style="background:#F8FAFC;padding:16px;border-radius:8px;border:1px solid var(--border);">
      <div style="font-size:0.82rem;font-weight:700;color:var(--text-muted);">Power Lifting</div>
      <div style="font-size:1.6rem;font-weight:800;color:var(--navy);font-family:'Outfit';"><?php echo $stats['sports']['powerlifting']; ?></div>
      <div style="font-size:0.72rem;color:#64748B;">Bench Press Weight Categories</div>
    </div>
    <div style="background:#F8FAFC;padding:16px;border-radius:8px;border:1px solid var(--border);">
      <div style="font-size:0.82rem;font-weight:700;color:var(--text-muted);">Para Badminton</div>
      <div style="font-size:1.6rem;font-weight:800;color:var(--navy);font-family:'Outfit';"><?php echo $stats['sports']['para_badminton']; ?></div>
      <div style="font-size:0.72rem;color:#64748B;">WH-1, WH-2, SL-3 to SS-6</div>
    </div>
    <div style="background:#F8FAFC;padding:16px;border-radius:8px;border:1px solid var(--border);">
      <div style="font-size:0.82rem;font-weight:700;color:var(--text-muted);">Wheelchair Basketball</div>
      <div style="font-size:1.6rem;font-weight:800;color:var(--navy);font-family:'Outfit';"><?php echo $stats['sports']['wheelchair_basketball']; ?></div>
      <div style="font-size:0.72rem;color:#64748B;">State Tournament Team Roster</div>
    </div>
  </div>
</div>

<!-- Recent Athlete Applications Queue -->
<div class="admin-card">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
    <h2 style="font-family:'Outfit';font-size:1.15rem;font-weight:800;color:var(--navy);">Recent Athlete Applications</h2>
    <a href="registrations.php" style="color:#00B074;font-weight:700;font-size:0.85rem;text-decoration:none;">View All Applications &rarr;</a>
  </div>

  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:0.88rem;text-align:left;">
      <thead>
        <tr style="border-bottom:2px solid var(--border);color:var(--text-muted);font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;">
          <th style="padding:10px 14px;">Reference ID</th>
          <th style="padding:10px 14px;">Athlete Name</th>
          <th style="padding:10px 14px;">Sport & Event</th>
          <th style="padding:10px 14px;">District</th>
          <th style="padding:10px 14px;">Status</th>
          <th style="padding:10px 14px;">Submitted</th>
          <th style="padding:10px 14px;text-align:right;">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($recentAthletes)): ?>
          <tr><td colspan="7" style="padding:30px;text-align:center;color:var(--text-muted);">No athlete applications submitted yet.</td></tr>
        <?php else: ?>
          <?php foreach ($recentAthletes as $ath): 
            $statusBadge = match($ath['status']) {
              'approved' => '<span style="background:#DCFCE7;color:#16A34A;font-weight:700;font-size:0.72rem;padding:3px 8px;border-radius:4px;">APPROVED</span>',
              'rejected' => '<span style="background:#FEE2E2;color:#DC2626;font-weight:700;font-size:0.72rem;padding:3px 8px;border-radius:4px;">REJECTED</span>',
              'correction_required' => '<span style="background:#FEF3C7;color:#D97706;font-weight:700;font-size:0.72rem;padding:3px 8px;border-radius:4px;">ACTION REQ</span>',
              default => '<span style="background:#EFF6FF;color:#2563EB;font-weight:700;font-size:0.72rem;padding:3px 8px;border-radius:4px;">PENDING</span>'
            };
          ?>
            <tr style="border-bottom:1px solid var(--border);transition:background 0.15s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
              <td style="padding:12px 14px;font-family:monospace;font-weight:700;color:var(--navy);">
                <?php echo htmlspecialchars($ath['reference_id']); ?>
                <?php if (!empty($ath['has_duplicate_flag'])): ?>
                  <span style="display:inline-block;background:#FEE2E2;color:#DC2626;font-size:0.65rem;font-weight:800;padding:1px 5px;border-radius:3px;margin-left:4px;">DUP</span>
                <?php endif; ?>
              </td>
              <td style="padding:12px 14px;font-weight:700;color:var(--text);"><?php echo htmlspecialchars($ath['full_name']); ?></td>
              <td style="padding:12px 14px;">
                <div style="font-weight:600;"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $ath['sport_game']))); ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted);"><?php echo htmlspecialchars($ath['event_discipline']); ?></div>
              </td>
              <td style="padding:12px 14px;"><?php echo htmlspecialchars($ath['district']); ?></td>
              <td style="padding:12px 14px;"><?php echo $statusBadge; ?></td>
              <td style="padding:12px 14px;color:var(--text-muted);font-size:0.8rem;"><?php echo date('d M Y, h:i A', strtotime($ath['created_at'])); ?></td>
              <td style="padding:12px 14px;text-align:right;">
                <a href="athlete-details.php?id=<?php echo $ath['id']; ?>" style="display:inline-block;background:var(--navy);color:#fff;font-size:0.78rem;font-weight:700;padding:6px 12px;border-radius:6px;text-decoration:none;">Review &rarr;</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
