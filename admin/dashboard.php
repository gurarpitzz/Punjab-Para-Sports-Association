<?php
// admin/dashboard.php - Administrative Executive Dashboard with Dual Approvals Pipelines

$pageTitle = "Executive Dashboard";
require_once __DIR__ . '/admin_header.php';

$stats = [
    'total_athletes'      => 0,
    'pending_athletes'    => 0,
    'approved_athletes'   => 0,
    'rejected_athletes'   => 0,
    'correction_athletes' => 0,
    'duplicate_flags'     => 0,
    'total_officials'     => 0,
    'pending_officials'   => 0,
    'approved_officials'  => 0,
    'rejected_officials'  => 0,
    'correction_officials'=> 0,
    'sports' => [
        'para_athletics'        => 0,
        'powerlifting'          => 0,
        'para_badminton'        => 0,
        'wheelchair_basketball' => 0
    ],
    'official_roles' => [
        'coach'         => 0,
        'referee'       => 0,
        'classifier'    => 0,
        'volunteer'     => 0,
        'ramp_operator' => 0,
        'escort'        => 0
    ]
];

$recentAthletes = [];
$recentOfficials = [];

if ($db) {
    try {
        // Athlete Stats
        $stats['total_athletes']      = (int)$db->query("SELECT COUNT(*) FROM ppsa_athlete_applications")->fetchColumn();
        $stats['pending_athletes']    = (int)$db->query("SELECT COUNT(*) FROM ppsa_athlete_applications WHERE status = 'pending'")->fetchColumn();
        $stats['approved_athletes']   = (int)$db->query("SELECT COUNT(*) FROM ppsa_athlete_applications WHERE status = 'approved'")->fetchColumn();
        $stats['rejected_athletes']   = (int)$db->query("SELECT COUNT(*) FROM ppsa_athlete_applications WHERE status = 'rejected'")->fetchColumn();
        $stats['correction_athletes'] = (int)$db->query("SELECT COUNT(*) FROM ppsa_athlete_applications WHERE status = 'correction_required'")->fetchColumn();
        $stats['duplicate_flags']     = (int)$db->query("SELECT COUNT(*) FROM ppsa_duplicate_flags")->fetchColumn();
        
        // Official Stats
        $stats['total_officials']      = (int)$db->query("SELECT COUNT(*) FROM ppsa_official_applications")->fetchColumn();
        $stats['pending_officials']    = (int)$db->query("SELECT COUNT(*) FROM ppsa_official_applications WHERE status = 'pending'")->fetchColumn();
        $stats['approved_officials']   = (int)$db->query("SELECT COUNT(*) FROM ppsa_official_applications WHERE status = 'approved'")->fetchColumn();
        $stats['rejected_officials']   = (int)$db->query("SELECT COUNT(*) FROM ppsa_official_applications WHERE status = 'rejected'")->fetchColumn();
        $stats['correction_officials'] = (int)$db->query("SELECT COUNT(*) FROM ppsa_official_applications WHERE status = 'correction_required'")->fetchColumn();

        // Sports breakdown
        $sportStmt = $db->query("SELECT sport_game, COUNT(*) as count FROM ppsa_athlete_applications GROUP BY sport_game");
        while ($r = $sportStmt->fetch()) {
            if (isset($stats['sports'][$r['sport_game']])) {
                $stats['sports'][$r['sport_game']] = (int)$r['count'];
            }
        }

        // Official roles breakdown
        $offRoleStmt = $db->query("SELECT official_category, COUNT(*) as count FROM ppsa_official_applications GROUP BY official_category");
        while ($r = $offRoleStmt->fetch()) {
            $cat = strtolower(trim($r['official_category']));
            if (isset($stats['official_roles'][$cat])) {
                $stats['official_roles'][$cat] = (int)$r['count'];
            }
        }

        // Recent 6 intake applications
        $recentAthletes = $db->query("
            SELECT id, reference_id, full_name, gender, sport_game, classification, weight_category, event_discipline, district, status, has_duplicate_flag, created_at 
            FROM ppsa_athlete_applications 
            ORDER BY id DESC LIMIT 6
        ")->fetchAll();

        // Recent 6 officials
        $recentOfficials = $db->query("
            SELECT id, reference_id, full_name, official_category, district, status, created_at 
            FROM ppsa_official_applications 
            ORDER BY id DESC LIMIT 6
        ")->fetchAll();
    } catch (\Throwable $e) {
        error_log("Dashboard Query Error: " . $e->getMessage());
    }
}
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Executive Approvals Dashboard</h1>
    <div class="admin-page-subtitle">Punjab State Para Games — Distinct Approvals Streams for Athletes & Technical Officials</div>
  </div>
  <div style="display:flex;flex-wrap:wrap;gap:10px;">
    <a href="registrations.php" style="background:#2563EB;color:#fff;font-weight:700;font-size:0.88rem;padding:9px 18px;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:7px;box-shadow:0 2px 8px rgba(37,99,235,0.25);">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Review Athletes Queue (<?php echo $stats['pending_athletes']; ?>) &rarr;
    </a>
    <a href="officials.php" style="background:#D97706;color:#fff;font-weight:700;font-size:0.88rem;padding:9px 18px;border-radius:6px;text-decoration:none;display:inline-flex;align-items:center;gap:7px;box-shadow:0 2px 8px rgba(217,119,6,0.25);">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
      Review Officials Queue (<?php echo $stats['pending_officials']; ?>) &rarr;
    </a>
  </div>
</div>

<!-- ========================================== -->
<!-- STREAM 1: ATHLETE APPROVALS PIPELINE       -->
<!-- ========================================== -->
<div style="margin-bottom:32px;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #DBEAFE;">
    <div style="display:flex;align-items:center;gap:10px;">
      <span style="background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;padding:4px 12px;border-radius:999px;font-size:0.75rem;font-weight:800;letter-spacing:0.05em;text-transform:uppercase;">
        Stream A
      </span>
      <h2 style="font-family:'Outfit';font-size:1.35rem;font-weight:800;color:#1E3A8A;margin:0;display:flex;align-items:center;gap:8px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:#2563EB;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        State Athlete Approvals Pipeline
      </h2>
    </div>
    <a href="registrations.php" style="font-size:0.85rem;font-weight:700;color:#2563EB;text-decoration:none;">Open Full Athlete Queue &rarr;</a>
  </div>

  <!-- Athlete Stats Grid -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:16px;margin-bottom:18px;">
    <div class="admin-card" style="margin-bottom:0;border-left:4px solid #3B82F6;">
      <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Total Athletes</div>
      <div style="font-size:2rem;font-weight:900;color:var(--navy);margin:4px 0;font-family:'Outfit';"><?php echo $stats['total_athletes']; ?></div>
      <div style="font-size:0.75rem;color:#64748B;">Athlete applications submitted</div>
    </div>

    <div class="admin-card" style="margin-bottom:0;border-left:4px solid #EF4444;">
      <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Pending Review</div>
      <div style="font-size:2rem;font-weight:900;color:#EF4444;margin:4px 0;font-family:'Outfit';"><?php echo $stats['pending_athletes']; ?></div>
      <div style="font-size:0.75rem;color:#DC2626;">Awaiting verification</div>
    </div>

    <div class="admin-card" style="margin-bottom:0;border-left:4px solid var(--green);">
      <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Approved Athletes</div>
      <div style="font-size:2rem;font-weight:900;color:var(--green);margin:4px 0;font-family:'Outfit';"><?php echo $stats['approved_athletes']; ?></div>
      <div style="font-size:0.75rem;color:#16A34A;">Issued permanent state ID</div>
    </div>

    <div class="admin-card" style="margin-bottom:0;border-left:4px solid #8B5CF6;">
      <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Correction Needed</div>
      <div style="font-size:2rem;font-weight:900;color:#8B5CF6;margin:4px 0;font-family:'Outfit';"><?php echo $stats['correction_athletes']; ?></div>
      <div style="font-size:0.75rem;color:#7C3AED;">Awaiting athlete resubmission</div>
    </div>

    <div class="admin-card" style="margin-bottom:0;border-left:4px solid #DC2626;">
      <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Duplicate Flags</div>
      <div style="font-size:2rem;font-weight:900;color:#DC2626;margin:4px 0;font-family:'Outfit';"><?php echo $stats['duplicate_flags']; ?></div>
      <div style="font-size:0.75rem;color:#DC2626;">Identity collision risk</div>
    </div>
  </div>

  <!-- Sports Discipline Matrix -->
  <div class="admin-card" style="margin-bottom:18px;">
    <h3 style="font-family:'Outfit';font-size:1rem;font-weight:800;color:var(--navy);margin-bottom:12px;">Athlete Sports Entries Breakdown (Ludhiana)</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:12px;">
      <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid var(--border);">
        <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);">Para Athletics</div>
        <div style="font-size:1.4rem;font-weight:800;color:var(--navy);font-family:'Outfit';"><?php echo $stats['sports']['para_athletics']; ?></div>
        <div style="font-size:0.72rem;color:#64748B;">Track, Javelin, Shot Put, Discus</div>
      </div>
      <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid var(--border);">
        <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);">Power Lifting</div>
        <div style="font-size:1.4rem;font-weight:800;color:var(--navy);font-family:'Outfit';"><?php echo $stats['sports']['powerlifting']; ?></div>
        <div style="font-size:0.72rem;color:#64748B;">Bench Press Weight Classes</div>
      </div>
      <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid var(--border);">
        <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);">Para Badminton</div>
        <div style="font-size:1.4rem;font-weight:800;color:var(--navy);font-family:'Outfit';"><?php echo $stats['sports']['para_badminton']; ?></div>
        <div style="font-size:0.72rem;color:#64748B;">WH-1, WH-2, SL-3 to SS-6</div>
      </div>
      <div style="background:#F8FAFC;padding:12px 14px;border-radius:8px;border:1px solid var(--border);">
        <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);">Wheelchair Basketball</div>
        <div style="font-size:1.4rem;font-weight:800;color:var(--navy);font-family:'Outfit';"><?php echo $stats['sports']['wheelchair_basketball']; ?></div>
        <div style="font-size:0.72rem;color:#64748B;">State Tournament Team Rosters</div>
      </div>
    </div>
  </div>

  <!-- Recent Athlete Applications Table -->
  <div class="admin-card" style="padding:0;overflow:hidden;">
    <div style="padding:14px 18px;background:#F8FAFC;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
      <strong style="font-size:0.9rem;color:var(--navy);">Recent Athlete Applications in Queue</strong>
      <a href="registrations.php" style="font-size:0.8rem;color:#2563EB;font-weight:700;text-decoration:none;">View All Athletes &rarr;</a>
    </div>
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:0.85rem;text-align:left;">
        <thead>
          <tr style="background:#F1F5F9;border-bottom:1px solid var(--border);color:var(--text-muted);font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;">
            <th style="padding:10px 14px;">Reference ID</th>
            <th style="padding:10px 14px;">Athlete Name</th>
            <th style="padding:10px 14px;">Sport & Discipline</th>
            <th style="padding:10px 14px;">District</th>
            <th style="padding:10px 14px;">Status</th>
            <th style="padding:10px 14px;">Submitted</th>
            <th style="padding:10px 14px;text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentAthletes)): ?>
            <tr><td colspan="7" style="padding:24px;text-align:center;color:var(--text-muted);">No athlete applications submitted yet.</td></tr>
          <?php else: ?>
            <?php foreach ($recentAthletes as $ath): 
              $statusBadge = match($ath['status']) {
                'approved' => '<span style="background:#DCFCE7;color:#16A34A;font-weight:800;font-size:0.7rem;padding:2px 7px;border-radius:4px;">APPROVED</span>',
                'rejected' => '<span style="background:#FEE2E2;color:#DC2626;font-weight:800;font-size:0.7rem;padding:2px 7px;border-radius:4px;">REJECTED</span>',
                'correction_required' => '<span style="background:#FEF3C7;color:#D97706;font-weight:800;font-size:0.7rem;padding:2px 7px;border-radius:4px;">ACTION REQ</span>',
                default => '<span style="background:#EFF6FF;color:#2563EB;font-weight:800;font-size:0.7rem;padding:2px 7px;border-radius:4px;">PENDING</span>'
              };
            ?>
              <tr style="border-bottom:1px solid var(--border);transition:background 0.15s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
                <td style="padding:10px 14px;font-family:monospace;font-weight:700;color:var(--navy);">
                  <?php echo htmlspecialchars($ath['reference_id']); ?>
                  <?php if (!empty($ath['has_duplicate_flag'])): ?>
                    <span style="display:inline-block;background:#FEE2E2;color:#DC2626;font-size:0.65rem;font-weight:800;padding:1px 5px;border-radius:3px;margin-left:4px;">DUP</span>
                  <?php endif; ?>
                </td>
                <td style="padding:10px 14px;font-weight:700;color:var(--text);"><?php echo htmlspecialchars($ath['full_name']); ?></td>
                <td style="padding:10px 14px;">
                  <div style="font-weight:600;"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $ath['sport_game']))); ?></div>
                  <div style="font-size:0.75rem;color:var(--text-muted);"><?php echo htmlspecialchars($ath['event_discipline']); ?></div>
                </td>
                <td style="padding:10px 14px;"><?php echo htmlspecialchars($ath['district']); ?></td>
                <td style="padding:10px 14px;"><?php echo $statusBadge; ?></td>
                <td style="padding:10px 14px;color:var(--text-muted);font-size:0.78rem;"><?php echo date('d M Y, h:i A', strtotime($ath['created_at'])); ?></td>
                <td style="padding:10px 14px;text-align:right;">
                  <a href="athlete-details.php?id=<?php echo $ath['id']; ?>" style="display:inline-flex;align-items:center;gap:4px;background:#2563EB;color:#fff;font-size:0.78rem;font-weight:700;padding:5px 12px;border-radius:5px;text-decoration:none;">
                    Review Athlete &rarr;
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- STREAM 2: OFFICIAL APPROVALS PIPELINE      -->
<!-- ========================================== -->
<div style="margin-bottom:32px;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid #FDE68A;">
    <div style="display:flex;align-items:center;gap:10px;">
      <span style="background:#FFFBEB;color:#B45309;border:1px solid #FCD34D;padding:4px 12px;border-radius:999px;font-size:0.75rem;font-weight:800;letter-spacing:0.05em;text-transform:uppercase;">
        Stream B
      </span>
      <h2 style="font-family:'Outfit';font-size:1.35rem;font-weight:800;color:#92400E;margin:0;display:flex;align-items:center;gap:8px;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:#D97706;"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        Technical Officials & Staff Accreditation Pipeline
      </h2>
    </div>
    <a href="officials.php" style="font-size:0.85rem;font-weight:700;color:#D97706;text-decoration:none;">Open Full Officials Queue &rarr;</a>
  </div>

  <!-- Official Stats Grid -->
  <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(210px, 1fr));gap:16px;margin-bottom:18px;">
    <div class="admin-card" style="margin-bottom:0;border-left:4px solid #D97706;">
      <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Total Officials</div>
      <div style="font-size:2rem;font-weight:900;color:var(--navy);margin:4px 0;font-family:'Outfit';"><?php echo $stats['total_officials']; ?></div>
      <div style="font-size:0.75rem;color:#64748B;">Staff applicants submitted</div>
    </div>

    <div class="admin-card" style="margin-bottom:0;border-left:4px solid #F59E0B;">
      <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Pending Accreditation</div>
      <div style="font-size:2rem;font-weight:900;color:#D97706;margin:4px 0;font-family:'Outfit';"><?php echo $stats['pending_officials']; ?></div>
      <div style="font-size:0.75rem;color:#B45309;">Awaiting technical review</div>
    </div>

    <div class="admin-card" style="margin-bottom:0;border-left:4px solid var(--green);">
      <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Accredited Officials</div>
      <div style="font-size:2rem;font-weight:900;color:var(--green);margin:4px 0;font-family:'Outfit';"><?php echo $stats['approved_officials']; ?></div>
      <div style="font-size:0.75rem;color:#16A34A;">Issued official credential ID</div>
    </div>

    <div class="admin-card" style="margin-bottom:0;border-left:4px solid #8B5CF6;">
      <div style="font-size:0.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Correction Needed</div>
      <div style="font-size:2rem;font-weight:900;color:#8B5CF6;margin:4px 0;font-family:'Outfit';"><?php echo $stats['correction_officials']; ?></div>
      <div style="font-size:0.75rem;color:#7C3AED;">Awaiting certificate / credentials</div>
    </div>
  </div>

  <!-- Official Roles Distribution Matrix -->
  <div class="admin-card" style="margin-bottom:18px;">
    <h3 style="font-family:'Outfit';font-size:1rem;font-weight:800;color:var(--navy);margin-bottom:12px;">Official Designations & Technical Roles Breakdown</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(170px, 1fr));gap:12px;">
      <div style="background:#FFFBEB;padding:12px 14px;border-radius:8px;border:1px solid #FCD34D;">
        <div style="font-size:0.78rem;font-weight:700;color:#92400E;">Coaches</div>
        <div style="font-size:1.4rem;font-weight:800;color:#78350F;font-family:'Outfit';"><?php echo $stats['official_roles']['coach']; ?></div>
        <div style="font-size:0.72rem;color:#B45309;">State & District Para Coaches</div>
      </div>
      <div style="background:#FFFBEB;padding:12px 14px;border-radius:8px;border:1px solid #FCD34D;">
        <div style="font-size:0.78rem;font-weight:700;color:#92400E;">Referees / Tech Officials</div>
        <div style="font-size:1.4rem;font-weight:800;color:#78350F;font-family:'Outfit';"><?php echo $stats['official_roles']['referee']; ?></div>
        <div style="font-size:0.72rem;color:#B45309;">Judges, Umpires & Marshals</div>
      </div>
      <div style="background:#FFFBEB;padding:12px 14px;border-radius:8px;border:1px solid #FCD34D;">
        <div style="font-size:0.78rem;font-weight:700;color:#92400E;">Medical Classifiers</div>
        <div style="font-size:1.4rem;font-weight:800;color:#78350F;font-family:'Outfit';"><?php echo $stats['official_roles']['classifier']; ?></div>
        <div style="font-size:0.72rem;color:#B45309;">Physiotherapists & Doctors</div>
      </div>
      <div style="background:#FFFBEB;padding:12px 14px;border-radius:8px;border:1px solid #FCD34D;">
        <div style="font-size:0.78rem;font-weight:700;color:#92400E;">Volunteers & Escorts</div>
        <div style="font-size:1.4rem;font-weight:800;color:#78350F;font-family:'Outfit';"><?php echo ($stats['official_roles']['volunteer'] + $stats['official_roles']['escort'] + $stats['official_roles']['ramp_operator']); ?></div>
        <div style="font-size:0.72rem;color:#B45309;">Ramp Operators & Helpers</div>
      </div>
    </div>
  </div>

  <!-- Recent Official Applications Table -->
  <div class="admin-card" style="padding:0;overflow:hidden;">
    <div style="padding:14px 18px;background:#FFFBEB;border-bottom:1px solid #FDE68A;display:flex;justify-content:space-between;align-items:center;">
      <strong style="font-size:0.9rem;color:#78350F;">Recent Official Applications in Queue</strong>
      <a href="officials.php" style="font-size:0.8rem;color:#D97706;font-weight:700;text-decoration:none;">View All Officials &rarr;</a>
    </div>
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:0.85rem;text-align:left;">
        <thead>
          <tr style="background:#FEF3C7;border-bottom:1px solid #FDE68A;color:#92400E;font-size:0.72rem;text-transform:uppercase;letter-spacing:0.06em;">
            <th style="padding:10px 14px;">Reference ID</th>
            <th style="padding:10px 14px;">Official Name</th>
            <th style="padding:10px 14px;">Designation / Role</th>
            <th style="padding:10px 14px;">District</th>
            <th style="padding:10px 14px;">Status</th>
            <th style="padding:10px 14px;">Submitted</th>
            <th style="padding:10px 14px;text-align:right;">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recentOfficials)): ?>
            <tr><td colspan="7" style="padding:24px;text-align:center;color:var(--text-muted);">No official applications submitted yet.</td></tr>
          <?php else: ?>
            <?php foreach ($recentOfficials as $off): 
              $offStatusBadge = match($off['status']) {
                'approved' => '<span style="background:#DCFCE7;color:#16A34A;font-weight:800;font-size:0.7rem;padding:2px 7px;border-radius:4px;">APPROVED</span>',
                'rejected' => '<span style="background:#FEE2E2;color:#DC2626;font-weight:800;font-size:0.7rem;padding:2px 7px;border-radius:4px;">REJECTED</span>',
                'correction_required' => '<span style="background:#FEF3C7;color:#D97706;font-weight:800;font-size:0.7rem;padding:2px 7px;border-radius:4px;">ACTION REQ</span>',
                default => '<span style="background:#EFF6FF;color:#2563EB;font-weight:800;font-size:0.7rem;padding:2px 7px;border-radius:4px;">PENDING</span>'
              };
            ?>
              <tr style="border-bottom:1px solid var(--border);transition:background 0.15s;" onmouseover="this.style.background='#FFFDF7'" onmouseout="this.style.background='transparent'">
                <td style="padding:10px 14px;font-family:monospace;font-weight:700;color:var(--navy);">
                  <?php echo htmlspecialchars($off['reference_id']); ?>
                </td>
                <td style="padding:10px 14px;font-weight:700;color:var(--text);"><?php echo htmlspecialchars($off['full_name']); ?></td>
                <td style="padding:10px 14px;">
                  <span style="display:inline-block;background:#FEF3C7;color:#78350F;font-weight:700;font-size:0.75rem;padding:2px 8px;border-radius:4px;">
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $off['official_category']))); ?>
                  </span>
                </td>
                <td style="padding:10px 14px;"><?php echo htmlspecialchars($off['district']); ?></td>
                <td style="padding:10px 14px;"><?php echo $offStatusBadge; ?></td>
                <td style="padding:10px 14px;color:var(--text-muted);font-size:0.78rem;"><?php echo date('d M Y, h:i A', strtotime($off['created_at'])); ?></td>
                <td style="padding:10px 14px;text-align:right;">
                  <a href="official-details.php?id=<?php echo $off['id']; ?>" style="display:inline-flex;align-items:center;gap:4px;background:#D97706;color:#fff;font-size:0.78rem;font-weight:700;padding:5px 12px;border-radius:5px;text-decoration:none;">
                    Review Official &rarr;
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
