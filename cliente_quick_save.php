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
if ($celular===''){
  quick_json(['ok'=>false,'msg'=>'Informe o telefone']);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  quick_json(['ok'=>false,'msg'=>'E-mail inválido']);
}
if ($documento !== '') {
  if (!in_array(strlen($documento), [11, 14], true)) {
    quick_json(['ok'=>false,'msg'=>'CPF/CNPJ inválido']);
  }
}

try{
  // Compatibilidade entre schemas: alguns ambientes exigem telefone.
  $salvo = false;
  $tentativas = [
    [
      'sql' => "INSERT INTO hf_clientes (tenant_id,nome,documento,email,telefone,celular,status)
                VALUES (:tid,:nome,:doc,:email,:tel,:cel,1)",
      'params' => [
        ':tid'   => $tid,
        ':nome'  => $nome,
        ':doc'   => $documento ?: null,
        ':email' => $email ?: null,
        ':tel'   => $celular ?: null,
        ':cel'   => $celular ?: null,
      ],
    ],
    [
      'sql' => "INSERT INTO hf_clientes (tenant_id,nome,documento,email,celular,status)
                VALUES (:tid,:nome,:doc,:email,:cel,1)",
      'params' => [
        ':tid'   => $tid,
        ':nome'  => $nome,
        ':doc'   => $documento ?: null,
        ':email' => $email ?: null,
        ':cel'   => $celular ?: null,
      ],
    ],
  ];

  foreach ($tentativas as $t) {
    try {
      $st = $pdo->prepare($t['sql']);
      $st->execute($t['params']);
      $salvo = true;
      break;
    } catch (Throwable $eTentativa) {
      $msg = strtolower((string)$eTentativa->getMessage());
      if (strpos($msg, 'unknown column') === false) {
        throw $eTentativa;
      }
    }
  }

  if (!$salvo) {
    throw new RuntimeException('Falha ao salvar cliente rápido');
  }

  quick_json(['ok'=>true,'id'=>(int)$pdo->lastInsertId(),'nome'=>$nome]);
} catch(Exception $e){
  error_log('cliente_quick_save.php erro ao salvar: '.$e->getMessage());
  quick_json(['ok'=>false,'msg'=>'Erro ao salvar']);
}
