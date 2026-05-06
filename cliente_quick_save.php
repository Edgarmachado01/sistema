<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

header('Content-Type: application/json; charset=utf-8');

function quick_json($payload, $statusCode = 200) {
  http_response_code($statusCode);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

if (empty($_SESSION['USER_ID'])) {
  quick_json(['ok'=>false,'msg'=>'Ação inválida.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  quick_json(['ok'=>false,'msg'=>'Ação inválida.'], 405);
}

$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
$postToken = (string)($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
  error_log('cliente_quick_save.php csrf invalido');
  quick_json(['ok'=>false,'msg'=>'Ação inválida.'], 400);
}

$pdo = db();
$tid = tenantId();
if (!$tid) {
  error_log('cliente_quick_save.php tenant invalido');
  quick_json(['ok'=>false,'msg'=>'Tenant inválido'], 400);
}

$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$celular = trim($_POST['celular'] ?? '');
$documento = preg_replace('/\D+/','', $_POST['documento'] ?? '');

if ($nome===''){
  quick_json(['ok'=>false,'msg'=>'Informe o nome']);
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

  quick_json(['ok'=>true,'id'=>(int)$pdo->lastInsertId(),'nome'=>$nome]);
} catch(Exception $e){
  error_log('cliente_quick_save.php erro ao salvar: '.$e->getMessage());
  quick_json(['ok'=>false,'msg'=>'Erro ao salvar']);
}