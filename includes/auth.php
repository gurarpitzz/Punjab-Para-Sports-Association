<?php
// includes/auth.php - Session Management, Role-Based Access Control & CSRF Protection

if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookie parameters
    $cookieParams = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    session_set_cookie_params($cookieParams);
    session_start();
}

/**
 * Check if an admin user is currently logged in
 */
function isPpsaLoggedIn(): bool {
    return !empty($_SESSION['ppsa_user_id']) && !empty($_SESSION['ppsa_user_email']);
}

/**
 * Get current authenticated user details
 */
function currentPpsaUser(): ?array {
    if (!isPpsaLoggedIn()) return null;
    return [
        'id'        => (int)$_SESSION['ppsa_user_id'],
        'email'     => $_SESSION['ppsa_user_email'],
        'full_name' => $_SESSION['ppsa_user_name'] ?? 'PPSA Staff',
        'role'      => $_SESSION['ppsa_user_role'] ?? 'reviewer'
    ];
}

/**
 * Require valid login or redirect to login.php
 */
function requirePpsaLogin(string $redirectUrl = 'login.php'): void {
    if (!isPpsaLoggedIn()) {
        $loginPath = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ? 'login.php' : 'admin/login.php';
        header("Location: {$loginPath}");
        exit();
    }
}

/**
 * Require a specific role or higher (e.g. 'admin')
 */
function requirePpsaRole(string $requiredRole): void {
    requirePpsaLogin();
    $role = $_SESSION['ppsa_user_role'] ?? 'reviewer';

    $hierarchy = ['viewer' => 1, 'reviewer' => 2, 'classifier' => 3, 'admin' => 4];
    $userLevel = $hierarchy[$role] ?? 0;
    $requiredLevel = $hierarchy[$requiredRole] ?? 0;

    if ($userLevel < $requiredLevel) {
        http_response_code(403);
        echo "<!DOCTYPE html><html><body style='font-family:sans-serif;text-align:center;padding:50px;'><h1>403 Forbidden</h1><p>You do not have administrative privileges to access this resource.</p><p><a href='dashboard.php'>Return to Dashboard</a></p></body></html>";
        exit();
    }
}

/**
 * Generate a cryptographically secure CSRF token
 */
function getCsrfToken(): string {
    if (empty($_SESSION['ppsa_csrf_token'])) {
        $_SESSION['ppsa_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['ppsa_csrf_token'];
}

/**
 * Validate submitted CSRF token
 */
function validateCsrfToken(?string $token): bool {
    if (empty($token) || empty($_SESSION['ppsa_csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['ppsa_csrf_token'], $token);
}
