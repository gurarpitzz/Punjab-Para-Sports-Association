<?php
// admin/login.php - Administrative Login Portal

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

if (isPpsaLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['username'] ?? $_POST['email'] ?? '');
    $pass       = $_POST['password'] ?? '';
    $token      = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($token)) {
        $error = "Session expired or invalid security token. Please try again.";
    } elseif (empty($loginInput) || empty($pass)) {
        $error = "Please enter both username and password.";
    } else {
        $db = getPpsaDb();
        if (!$db) {
            $dbErr = getPpsaDbError();
            $error = "Database offline: " . ($dbErr ?: "Please verify config/local.php exists in your config folder.");
        } else {
            $stmt = $db->prepare("SELECT * FROM ppsa_users WHERE (LOWER(username) = ? OR LOWER(email) = ?) AND is_active = 1 LIMIT 1");
            $lowerLogin = strtolower($loginInput);
            $stmt->execute([$lowerLogin, $lowerLogin]);
            $user = $stmt->fetch();

            if ($user && password_verify($pass, $user['password_hash'])) {
                // Successful login
                session_regenerate_id(true);
                $_SESSION['ppsa_user_id']    = $user['id'];
                $_SESSION['ppsa_username']   = $user['username'] ?? $loginInput;
                $_SESSION['ppsa_user_email'] = $user['email'] ?? '';
                $_SESSION['ppsa_user_name']  = $user['full_name'];
                $_SESSION['ppsa_user_role']  = $user['role'];

                // Update last login
                $upStmt = $db->prepare("UPDATE ppsa_users SET last_login_at = NOW() WHERE id = ?");
                $upStmt->execute([$user['id']]);

                ppsaAuditLog($user['id'], 'admin_login', 'ppsa_users', $user['id'], ['username' => $user['username'] ?? $loginInput]);

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid administrator username or password.";
            }
        }
    }
}

$csrf = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administrative Login — Punjab Para Sports Association</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: linear-gradient(135deg, #081B4B 0%, #0E1F4B 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .login-box {
      width: 100%;
      max-width: 440px;
      background: #ffffff;
      border-radius: 16px;
      padding: 40px 36px;
      box-shadow: 0 20px 45px rgba(0, 0, 0, 0.4);
    }
    .brand-emblem {
      width: 60px;
      height: 60px;
      background: #0E1F4B;
      border: 3px solid #FFC400;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #FFC400;
      font-family: 'Outfit', sans-serif;
      font-weight: 900;
      font-size: 24px;
      margin: 0 auto 16px;
    }
    .login-title {
      font-family: 'Outfit', sans-serif;
      font-weight: 800;
      font-size: 1.45rem;
      color: #0E1F4B;
      text-align: center;
      margin-bottom: 4px;
    }
    .login-sub {
      color: #64748B;
      font-size: 0.82rem;
      text-align: center;
      margin-bottom: 28px;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      font-weight: 700;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-label {
      display: block;
      font-weight: 700;
      font-size: 0.82rem;
      color: #334155;
      margin-bottom: 8px;
    }
    .form-input {
      width: 100%;
      height: 48px;
      padding: 0 14px;
      border: 1.5px solid #CBD5E1;
      border-radius: 8px;
      font-size: 0.95rem;
      font-family: inherit;
      transition: all 0.2s ease;
    }
    .form-input:focus {
      outline: none;
      border-color: #00B074;
      box-shadow: 0 0 0 3px rgba(0, 176, 116, 0.15);
    }
    .btn-submit {
      width: 100%;
      height: 50px;
      background: #00B074;
      color: #ffffff;
      font-weight: 800;
      font-size: 0.95rem;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-family: 'Outfit', sans-serif;
      letter-spacing: 0.04em;
      transition: all 0.2s ease;
      margin-top: 10px;
    }
    .btn-submit:hover {
      background: #0E1F4B;
    }
    .alert-error {
      background: #FEF2F2;
      border-left: 4px solid #EF4444;
      color: #991B1B;
      padding: 12px 14px;
      font-size: 0.85rem;
      border-radius: 6px;
      margin-bottom: 20px;
    }
    .back-home {
      display: block;
      text-align: center;
      margin-top: 24px;
      color: #64748B;
      text-decoration: none;
      font-size: 0.82rem;
      font-weight: 600;
    }
    .back-home:hover { color: #0E1F4B; }
  </style>
</head>
<body>

<div class="login-box">
  <div class="brand-emblem">P</div>
  <h1 class="login-title">PUNJAB PARA SPORTS</h1>
  <div class="login-sub">Administrative Intake Portal</div>

  <?php if (!empty($error)): ?>
    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="POST" action="login.php">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

    <div class="form-group">
      <label class="form-label" for="username">Username</label>
      <input type="text" id="username" name="username" class="form-input" required autocomplete="username" placeholder="Enter administrative username" autofocus>
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Password</label>
      <input type="password" id="password" name="password" class="form-input" required autocomplete="current-password" placeholder="••••••••••••">
    </div>

    <button type="submit" class="btn-submit">SIGN IN TO PORTAL</button>
  </form>

  <a href="../index.html" class="back-home">&larr; Return to Public Website</a>
</div>

</body>
</html>
