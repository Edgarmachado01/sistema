<?php
if (session_status()===PHP_SESSION_NONE) session_start();

function requireLogin(){
  if (empty($_SESSION['USER_ID'])) {
    header('Location: /login.php');
    exit;
  }
}

function tenantId(){
  return $_SESSION['TENANT_ID'] ?? null;
}

/**
 * Verifica se o usuário logado tem um determinado role_key
 * Ex.: hasRole('TENANT_ADMIN'), hasRole('ATENDENTE')
 */
function hasRole($role){
  return in_array($role, $_SESSION['ROLES'] ?? [], true);
}

/**
 * Roles “brutos” da tabela roles.role_key
 */
function isSysAdmin(){
  return hasRole('SYS_ADMIN');
}

function isTenantAdmin(){
  return hasRole('TENANT_ADMIN');
}

function isTecnico(){
  return hasRole('TECNICO');
}

function isAtendente(){
  return hasRole('ATENDENTE');
}

function isFinanceiro(){
  return hasRole('FINANCEIRO');
}

function isVisualizador(){
  return hasRole('VISUALIZADOR');
}

/**
 * “Admin da loja”:
 * - SYS_ADMIN (você, plataforma)
 * - TENANT_ADMIN (dono da empresa)
 */
function isAdminLoja(){
  return isSysAdmin() || isTenantAdmin();
}
