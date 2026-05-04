<?php
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = db();
$tid = tenantId();
if (!$tid) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Tenant inválido']); exit; }

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$celular = trim($_POST['celular'] ?? '');
$documento = preg_replace('/\D+/','', $_POST['documento'] ?? '');

if ($nome===''){
  echo json_encode(['ok'=>false,'msg'=>'Informe o nome']); exit;
}

try{
  $st = $pdo->prepare("
    INSERT INTO hf_clientes (tenant_id,nome,documento,email,celular,status)
    VALUES (:tid,:nome,:doc,:email,:cel,1)
  ");
  $st->execute([
    ':tid'=>$tid,
    ':nome'=>$nome,
    ':doc'=>$documento ?: null,
    ':email'=>$email ?: null,
    ':cel'=>$celular ?: null
  ]);

  echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId(),'nome'=>$nome]);
} catch(Exception $e){
  echo json_encode(['ok'=>false,'msg'=>'Erro ao salvar']);
}
