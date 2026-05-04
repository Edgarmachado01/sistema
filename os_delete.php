<?php
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

$pdo = db();
$tid = tenantId();
$id  = (int)($_GET['id'] ?? 0);
if (!$tid || $id<=0) { header('Location: /os_list.php'); exit; }

$st = $pdo->prepare("UPDATE hf_os SET deleted_at=NOW() WHERE id=:id AND tenant_id=:tid AND deleted_at IS NULL");
$st->execute([':id'=>$id, ':tid'=>$tid]);

header('Location: /os_list.php');
