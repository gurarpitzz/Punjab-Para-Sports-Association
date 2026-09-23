<?php
// admin/users.php - Administrative Staff & RBAC Management

$pageTitle = "Staff & Access Control";
require_once __DIR__ . '/admin_header.php';

$successMsg = '';
$errorMsg   = '';

// Check if user is admin to perform user creation
$isAdmin = ($currentUser['role'] === 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin && $db) {
    $action = $_POST['action'] ?? '';
    $csrf   = $_POST['csrf_token'] ?? '';

    if (!verifyPpsaCsrf($csrf)) {
        $errorMsg = "Invalid security token.";
    } elseif ($action === 'create_user') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'reviewer';

        if (empty($fullName) || empty($email) || empty($password)) {
            $errorMsg = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorMsg = "Invalid email address format.";
        } elseif (strlen($password) < 8) {
            $errorMsg = "Password must be at least 8 characters.";
        } elseif (!in_array($role, ['admin', 'reviewer', 'classifier'])) {
            $errorMsg = "Invalid role selected.";
        } else {
            try {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $db->prepare("
                    INSERT INTO ppsa_users (full_name, email, password_hash, role, is_active, created_at)
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$fullName, $email, $hash, $role]);
                $newId = (int)$db->lastInsertId();

                ppsaAuditLog($currentUser['id'], 'create_user', 'ppsa_users', $newId, [
                    'email' => $email,
                    'role'  => $role
                ]);

                $successMsg = "Staff account for <strong>" . htmlspecialchars($fullName) . "</strong> created successfully!";
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000) {
                    $errorMsg = "A user with this email address already exists.";
                } else {
                    $errorMsg = "Error creating account: " . $e->getMessage();
                }
            }
        }
    }
}

$users = [];
if ($db) {
    try {
        $users = $db->query("SELECT id, email, full_name, role, is_active, last_login_at, created_at FROM ppsa_users ORDER BY id ASC")->fetchAll();
    } catch (\Throwable $e) {
        $errorMsg = "Error fetching users: " . $e->getMessage();
    }
}
?>

<div class="admin-page-header">
  <div>
    <h1 class="admin-page-title">Administrative Staff & Access Control</h1>
    <div class="admin-page-subtitle">Manage role-based access for Administrators, Document Reviewers, and State Classifiers</div>
  </div>
  <?php if ($isAdmin): ?>
    <div>
      <button onclick="document.getElementById('createUserModal').style.display='flex';" style="background:var(--navy);color:#fff;border:none;font-weight:700;font-size:0.88rem;padding:10px 18px;border-radius:6px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
        <span>+</span> Add Staff Member
      </button>
    </div>
  <?php endif; ?>
</div>

<?php if ($successMsg): ?>
  <div style="background:#ECFDF5;border-left:4px solid #10B981;padding:14px 18px;border-radius:6px;margin-bottom:20px;color:#065F46;font-size:0.9rem;">
    <?php echo $successMsg; ?>
  </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
  <div style="background:#FEF2F2;border-left:4px solid #EF4444;padding:14px 18px;border-radius:6px;margin-bottom:20px;color:#991B1B;font-size:0.9rem;">
    <?php echo htmlspecialchars($errorMsg); ?>
  </div>
<?php endif; ?>

<!-- Users Table -->
<div class="admin-card" style="padding:0;overflow:hidden;">
  <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;text-align:left;font-size:0.88rem;">
      <thead>
        <tr style="background:#F8FAFC;border-bottom:2px solid var(--border);color:var(--navy);font-weight:700;text-transform:uppercase;font-size:0.75rem;letter-spacing:0.04em;">
          <th style="padding:14px 16px;">Staff Member</th>
          <th style="padding:14px 16px;">Email</th>
          <th style="padding:14px 16px;">Role</th>
          <th style="padding:14px 16px;">Account Status</th>
          <th style="padding:14px 16px;">Last Login</th>
          <th style="padding:14px 16px;">Created On</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr>
            <td colspan="6" style="padding:40px;text-align:center;color:var(--text-muted);">No staff records found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($users as $u): ?>
            <tr style="border-bottom:1px solid var(--border);">
              <td style="padding:14px 16px;">
                <div style="font-weight:700;color:var(--navy);"><?php echo htmlspecialchars($u['full_name']); ?></div>
                <?php if ($u['id'] == $currentUser['id']): ?>
                  <span style="font-size:0.7rem;color:#00B074;font-weight:700;">(Your Active Session)</span>
                <?php endif; ?>
              </td>
              <td style="padding:14px 16px;font-family:monospace;font-size:0.85rem;color:var(--text);"><?php echo htmlspecialchars($u['email']); ?></td>
              <td style="padding:14px 16px;">
                <?php
                  $roleColors = [
                    'admin'      => ['bg' => 'rgba(255, 196, 0, 0.2)', 'color' => '#B45309'],
                    'reviewer'   => ['bg' => '#EFF6FF', 'color' => '#2563EB'],
                    'classifier' => ['bg' => '#E6FBF2', 'color' => '#00B074'],
                  ];
                  $rc = $roleColors[$u['role']] ?? ['bg' => '#F1F5F9', 'color' => '#475569'];
                ?>
                <span style="background:<?php echo $rc['bg']; ?>;color:<?php echo $rc['color']; ?>;font-weight:800;font-size:0.75rem;padding:3px 10px;border-radius:4px;text-transform:uppercase;">
                  <?php echo htmlspecialchars($u['role']); ?>
                </span>
              </td>
              <td style="padding:14px 16px;">
                <?php if (!empty($u['is_active'])): ?>
                  <span style="color:#10B981;font-weight:700;font-size:0.8rem;">● Active</span>
                <?php else: ?>
                  <span style="color:#EF4444;font-weight:700;font-size:0.8rem;">● Inactive</span>
                <?php endif; ?>
              </td>
              <td style="padding:14px 16px;color:var(--text-muted);font-size:0.82rem;">
                <?php echo $u['last_login_at'] ? date('d M Y, h:i A', strtotime($u['last_login_at'])) : 'Never'; ?>
              </td>
              <td style="padding:14px 16px;color:var(--text-muted);font-size:0.82rem;">
                <?php echo date('d M Y', strtotime($u['created_at'])); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create User Modal -->
<?php if ($isAdmin): ?>
<div id="createUserModal" style="display:none;position:fixed;inset:0;background:rgba(8,27,75,0.6);z-index:9999;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:10px;width:100%;max-width:480px;padding:28px;box-shadow:0 10px 30px rgba(0,0,0,0.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
      <h3 style="font-size:1.2rem;font-weight:800;color:var(--navy);margin:0;">Create Staff Member</h3>
      <button onclick="document.getElementById('createUserModal').style.display='none';" style="background:none;border:none;font-size:1.4rem;color:var(--text-muted);cursor:pointer;">&times;</button>
    </div>

    <form method="POST" action="users.php">
      <input type="hidden" name="csrf_token" value="<?php echo generatePpsaCsrf(); ?>">
      <input type="hidden" name="action" value="create_user">

      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:0.82rem;font-weight:700;color:var(--text);margin-bottom:6px;">Full Name</label>
        <input type="text" name="full_name" required placeholder="e.g. Jaswinder Singh" style="width:100%;height:40px;padding:0 12px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;">
      </div>

      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:0.82rem;font-weight:700;color:var(--text);margin-bottom:6px;">Official Email</label>
        <input type="email" name="email" required placeholder="official@punjabparasports.org" style="width:100%;height:40px;padding:0 12px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;">
      </div>

      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:0.82rem;font-weight:700;color:var(--text);margin-bottom:6px;">Temporary Password (min. 8 characters)</label>
        <input type="password" name="password" required minlength="8" placeholder="••••••••" style="width:100%;height:40px;padding:0 12px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;">
      </div>

      <div style="margin-bottom:20px;">
        <label style="display:block;font-size:0.82rem;font-weight:700;color:var(--text);margin-bottom:6px;">Administrative Role</label>
        <select name="role" required style="width:100%;height:40px;padding:0 12px;border:1.5px solid var(--border);border-radius:6px;font-size:0.88rem;background:#fff;">
          <option value="reviewer">Reviewer (Verify documents & profiles)</option>
          <option value="classifier">Classifier (Para classification specialist)</option>
          <option value="admin">Administrator (Full governance authority)</option>
        </select>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" onclick="document.getElementById('createUserModal').style.display='none';" style="padding:10px 16px;background:#F1F5F9;border:1px solid var(--border);border-radius:6px;font-weight:600;font-size:0.88rem;cursor:pointer;">Cancel</button>
        <button type="submit" style="padding:10px 20px;background:var(--navy);color:#fff;border:none;border-radius:6px;font-weight:700;font-size:0.88rem;cursor:pointer;">Save Account</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/admin_footer.php'; ?>
