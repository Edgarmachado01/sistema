<?php
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

$pdo = db();
$tid = tenantId();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /produtos.php');
  exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$tid || !$id) {
  header('Location: /produtos.php');
  exit;
}

$st = $pdo->prepare("UPDATE hf_produtos SET deleted_at=NOW() WHERE id=:id AND tenant_id=:tid AND deleted_at IS NULL");
$st->execute([':id'=>$id, ':tid'=>$tid]);

header('Location: /produtos.php');