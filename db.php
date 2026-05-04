<?php
require_once __DIR__.'/config.php';

function db(){
  static $pdo;
  if ($pdo) return $pdo;
  $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}

function findTenantBySlug($slug){
  if ($slug==='') return null;
  $st = db()->prepare("SELECT * FROM tenants WHERE slug=? AND is_active=1 LIMIT 1");
  $st->execute([$slug]);
  return $st->fetch() ?: null;
}

function findUserByEmail($tenantIdOrNull, $email){
  $st = db()->prepare("SELECT * FROM users WHERE tenant_id <=> ? AND email=? AND is_active=1 LIMIT 1");
  $st->execute([$tenantIdOrNull, $email]);
  return $st->fetch() ?: null;
}

function getUserRoles($userId){
  $st = db()->prepare("SELECT r.role_key FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?");
  $st->execute([$userId]);
  return array_column($st->fetchAll(), 'role_key');
}
