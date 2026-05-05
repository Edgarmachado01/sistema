<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function requireLogin()
{
    if (empty($_SESSION['USER_ID'])) {
        header('Location: /login.php');
        exit;
    }
}

function requireAdmin()
{
    requireLogin();
    if (!isAdminLoja()) {
        http_response_code(403);
        exit('Acesso negado.');
    }
}

function tenantId()
{
    return $_SESSION['TENANT_ID'] ?? null;
}

/**
 * Verifica se o usuário logado tem um role_key.
 */
function hasRole($role)
{
    return in_array($role, $_SESSION['ROLES'] ?? [], true);
}

/**
 * Helpers legados (mantidos por compatibilidade).
 */
function isSysAdmin()
{
    return hasRole('SYS_ADMIN');
}

function isTenantAdmin()
{
    return hasRole('TENANT_ADMIN');
}

function isTecnico()
{
    return hasRole('TECNICO');
}

function isFinanceiro()
{
    return hasRole('FINANCEIRO');
}

function isVisualizador()
{
    return hasRole('VISUALIZADOR');
}

/**
 * Regra central (2 perfis):
 * - ADMIN: acesso total
 * - ATENDENTE: áreas operacionais
 *
 * Mantém compatibilidade com perfis legados de admin.
 */
function isAdminLoja()
{
    return hasRole('ADMIN') || isSysAdmin() || isTenantAdmin();
}

function isAtendente()
{
    return hasRole('ATENDENTE');
}
