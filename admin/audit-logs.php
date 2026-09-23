<?php
// admin/audit-logs.php - Immutable Administrative Governance & Audit Trail

$pageTitle = "System Audit Logs";
require_once __DIR__ . '/admin_header.php';

$actionFilter = trim($_GET['action_type'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$limit        = 30;
$offset       = ($page - 1) * $limit;

$logs = [];
$totalRows = 0;
$actionsList = [];

if ($db) {
    try {
        $actionsList = $db->query("SELECT DISTINCT action FROM ppsa_audit_logs ORDER BY action ASC")->fetchAll(PDO::FETCH_COLUMN);

        $where = ["1=1"];
        $params = [];

        if ($actionFilter !== '') {
            $where[] = "a.action = ?";
            $params[] = $actionFilter;
        }

        $whereClause = implode(" AND ", $where);

        $countStmt = $db->prepare("SELECT COUNT(*) FROM ppsa_audit_logs a WHERE {$whereClause}");
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        $stmt = $db->prepare("
            SELECT a.*, u.full_name as user_full_name, u.role as user_role, u.email as user_email
            FROM ppsa_audit_logs a
            LEFT JOIN ppsa_users u ON a.user_id = u.id
            WHERE {$whereClause}
            ORDER BY a.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $logs = $stmt->fetchAll();
    } catch (\Throwable $e) {
        error_log("Audit Logs Error: " . $e->getMessage());
    }
}

$totalPages = ceil($totalRows / $limit);
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Administrative Audit Logs</h1>
    <div class="admin-page-subtitle">Immutable chronological trail of administrative decisions, reviews, and security events</div>
  </div>
  <div>
    <span style="font-weight:700;font-size:0.9rem;color:var(--text-muted);">Total Events: <strong><?php echo $totalRows; ?></strong></span>
  </div>
</div>

<!-- Filters Bar -->
<div class="admin-card" style="padding:18px 20px;margin-bottom:20px;">
  <form method="GET" action="audit-logs.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
    <select name="action_type" style="height:40px;padding:0 12px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;background:#fff;min-width:240px;">
      <option value="">All Action Types</option>
      <?php foreach ($actionsList as $act): ?>
        <option value="<?php echo htmlspecialchars($act); ?>" <?php echo $actionFilter === $act ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $act))); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <button type="submit" style="height:40px;padding:0 20px;background:var(--navy);color:#fff;border:none;border-radius:6px;font-weight:700;font-size:0.88rem;cursor:pointer;">
      Filter Logs
    </button>
    <a href="audit-logs.php" style="height:40px;padding:0 14px;background:#F1F5F9;color:var(--text);border:1px solid var(--border);border-radius:6px;font-weight:600;font-size:0.88rem;display:inline-flex;align-items:center;text-decoration:none;">
      Reset
    </a>
  </form>
</div>

<!-- Audit Trail Table -->
<div class="admin-card" style="padding:0;overflow:hidden;">
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;text-align:left;font-size:0.85rem;">
      <thead>
        <tr style="background:#F8FAFC;border-bottom:2px solid var(--border);color:var(--navy);font-weight:700;text-transform:uppercase;font-size:0.75rem;letter-spacing:0.04em;">
          <th style="padding:14px 16px;">Timestamp</th>
          <th style="padding:14px 16px;">Staff / Actor</th>
          <th style="padding:14px 16px;">Action Event</th>
          <th style="padding:14px 16px;">Target Entity</th>
          <th style="padding:14px 16px;">Details & Payload</th>
          <th style="padding:14px 16px;">IP Address</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr>
            <td colspan="6" style="padding:50px 20px;text-align:center;color:var(--text-muted);">
              <div style="font-size:1.1rem;font-weight:700;color:var(--navy);margin-bottom:6px;">No audit log events recorded</div>
              <div>Administrative actions will appear in this timeline as decisions are made.</div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($logs as $log): ?>
            <tr style="border-bottom:1px solid var(--border);transition:background 0.15s;" onmouseover="this.style.background='#F8FAFC'" onmouseout="this.style.background='transparent'">
              
              <!-- Timestamp -->
              <td style="padding:12px 16px;white-space:nowrap;color:var(--text-muted);font-size:0.8rem;">
                <div style="color:var(--text);font-weight:600;"><?php echo date('d M Y', strtotime($log['created_at'])); ?></div>
                <div><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></div>
              </td>

              <!-- Staff User -->
              <td style="padding:12px 16px;">
                <?php if (!empty($log['user_full_name'])): ?>
                  <strong style="color:var(--navy);display:block;"><?php echo htmlspecialchars($log['user_full_name']); ?></strong>
                  <span style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;"><?php echo htmlspecialchars($log['user_role']); ?></span>
                <?php else: ?>
                  <span style="color:var(--text-muted);font-style:italic;">System / Guest</span>
                <?php endif; ?>
              </td>

              <!-- Action -->
              <td style="padding:12px 16px;">
                <?php
                  $color = '#0E1F4B';
                  if (strpos($log['action'], 'approve') !== false) $color = '#00B074';
                  elseif (strpos($log['action'], 'reject') !== false) $color = '#DC2626';
                  elseif (strpos($log['action'], 'correction') !== false) $color = '#D97706';
                  elseif (strpos($log['action'], 'login') !== false) $color = '#2563EB';
                ?>
                <span style="display:inline-block;background:#F1F5F9;color:<?php echo $color; ?>;font-weight:800;font-size:0.75rem;padding:3px 8px;border-radius:4px;border:1px solid rgba(0,0,0,0.06);">
                  <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['action']))); ?>
                </span>
              </td>

              <!-- Entity -->
              <td style="padding:12px 16px;font-family:monospace;font-size:0.8rem;color:var(--navy);">
                <?php echo htmlspecialchars($log['entity_type']); ?> #<?php echo (int)$log['entity_id']; ?>
              </td>

              <!-- Details JSON Payload -->
              <td style="padding:12px 16px;max-width:350px;">
                <?php if (!empty($log['details_json'])): ?>
                  <?php
                    $parsed = json_decode($log['details_json'], true);
                  ?>
                  <?php if (is_array($parsed)): ?>
                    <div style="font-size:0.78rem;color:var(--text);background:#F8FAFC;padding:6px 10px;border-radius:4px;border:1px solid var(--border);line-height:1.4;">
                      <?php foreach ($parsed as $k => $v): ?>
                        <div><strong style="color:var(--text-muted);"><?php echo htmlspecialchars($k); ?>:</strong> <?php echo htmlspecialchars(is_array($v) ? json_encode($v) : (string)$v); ?></div>
                      <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                    <span style="font-family:monospace;font-size:0.78rem;"><?php echo htmlspecialchars($log['details_json']); ?></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="color:#94A3B8;">—</span>
                <?php endif; ?>
              </td>

              <!-- IP Address -->
              <td style="padding:12px 16px;font-family:monospace;font-size:0.78rem;color:var(--text-muted);white-space:nowrap;">
                <?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?>
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
