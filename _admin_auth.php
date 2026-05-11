<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/db.php';

function isSaasAdminLogged()
{
    if (empty($_SESSION['SAAS_ADMIN_AUTHED']) || empty($_SESSION['SAAS_ADMIN_ID'])) {
        return false;
    }

    $roles = $_SESSION['SAAS_ADMIN_ROLES'] ?? [];
    if (!is_array($roles) || !in_array('SYS_ADMIN', $roles, true)) {
        return false;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT u.id
            FROM users u
            WHERE u.id = :id
              AND u.is_active = 1
              AND u.tenant_id IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => (int)$_SESSION['SAAS_ADMIN_ID']]);

        if (!$stmt->fetchColumn()) {
            return false;
        }

        $stmtRole = $pdo->prepare("
            SELECT r.role_key
            FROM user_roles ur
            JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :user_id
        ");
        $stmtRole->execute([':user_id' => (int)$_SESSION['SAAS_ADMIN_ID']]);
        $dbRoles = array_column($stmtRole->fetchAll(PDO::FETCH_ASSOC), 'role_key');

        return in_array('SYS_ADMIN', $dbRoles, true);
    } catch (Exception $e) {
        error_log('_admin_auth.php isSaasAdminLogged: '.$e->getMessage());
        return false;
    }
}

function requireSaasAdmin()
{
    if (!isSaasAdminLogged()) {
        header('Location: /admin_login.php');
        exit;
    }
}

function saasAdminId()
{
    return isset($_SESSION['SAAS_ADMIN_ID']) ? (int)$_SESSION['SAAS_ADMIN_ID'] : null;
}
