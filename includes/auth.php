<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| BASIC LOGIN CHECK
|--------------------------------------------------------------------------
*/
function requireLogin($redirect = '../auth/admin-login.php') {
    if (!isset($_SESSION['user_id'])) {
        header("Location: $redirect");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| ROLE CHECK
|--------------------------------------------------------------------------
| Example:
| requireRole(['system_admin', 'staff']);
|--------------------------------------------------------------------------
*/
function requireRole(array $allowedRoles, $redirect = '../auth/admin-login.php') {
    requireLogin($redirect);

    $role = $_SESSION['role'] ?? '';

    if (!in_array($role, $allowedRoles, true)) {
        header("Location: ../auth/unauthorized.php");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| SYSTEM ADMIN = FULL ACCESS
|--------------------------------------------------------------------------
*/
function requireClinicAccess(array $allowedRoles = [], $redirect = '../auth/admin-login.php') {
    requireLogin($redirect);

    $role = $_SESSION['role'] ?? '';

    if ($role === 'system_admin') {
        return;
    }

    if (!in_array($role, $allowedRoles, true)) {
        header("Location: ../auth/unauthorized.php");
        exit();
    }
}

/*
|--------------------------------------------------------------------------
| SIMPLE HELPER
|--------------------------------------------------------------------------
*/
function hasRole(array $allowedRoles): bool {
    $role = $_SESSION['role'] ?? '';

    if ($role === 'system_admin') {
        return true;
    }

    return in_array($role, $allowedRoles, true);
}

/*
|--------------------------------------------------------------------------
| CURRENT ROLE
|--------------------------------------------------------------------------
*/
function currentRole(): string {
    return $_SESSION['role'] ?? '';
}
?>