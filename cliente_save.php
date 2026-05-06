<?php
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function redirect_cliente_seguro($location = '/clientes.php') {
  header('Location: '.$location);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_cliente_seguro('/clientes.php');
}

$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
$postToken = (string)($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
  error_log('cliente_save.php csrf invalido');
  redirect_cliente_seguro('/clientes.php');
}

$pdo = db();
$tid = tenantId();
if (!$tid) {
  error_log('cliente_save.php tenant invalido');
  redirect_cliente_seguro('/clientes.php');
}

$id   = (int)($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');

if ($nome==='') {
  header('Location: /cliente_form.php?err=nome'); exit;
}

$fields = [
  'documento','email','telefone','celular','cep','endereco','numero',
  'complemento','bairro','cidade','uf','obs','status'
];
$data = [];
foreach ($fields as $f) $data[$f] = isset($_POST[$f]) ? trim($_POST[$f]) : null;

try{
  if ($id>0) {
    $check = $pdo->prepare("SELECT id FROM hf_clientes WHERE id=:id AND tenant_id=:tid AND deleted_at IS NULL LIMIT 1");
    $check->execute([':id'=>$id, ':tid'=>$tid]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
      error_log('cliente_save.php cliente nao encontrado ou fora do tenant: id='.$id.' tenant_id='.$tid);
      redirect_cliente_seguro('/clientes.php');
    }

    // UPDATE
    $sql = "UPDATE hf_clientes
            SET nome=:nome, documento=:documento, email=:email, telefone=:telefone, celular=:celular,
                cep=:cep, endereco=:endereco, numero=:numero, complemento=:complemento,
                bairro=:bairro, cidade=:cidade, uf=:uf, obs=:obs, status=:status
            WHERE id=:id AND tenant_id=:tid AND deleted_at IS NULL";
    $st = $pdo->prepare($sql);
    $ok = $st->execute([
      ':nome'=>$nome, ':documento'=>$data['documento'], ':email'=>$data['email'],
      ':telefone'=>$data['telefone'], ':celular'=>$data['celular'], ':cep'=>$data['cep'],
      ':endereco'=>$data['endereco'], ':numero'=>$data['numero'], ':complemento'=>$data['complemento'],
      ':bairro'=>$data['bairro'], ':cidade'=>$data['cidade'], ':uf'=>$data['uf'],
      ':obs'=>$data['obs'], ':status'=>(int)$data['status'],
      ':id'=>$id, ':tid'=>$tid
    ]);
  } else {
    // INSERT
    $sql = "INSERT INTO hf_clientes
      (tenant_id, nome, documento, email, telefone, celular, cep, endereco, numero, complemento, bairro, cidade, uf, obs, status)
      VALUES
      (:tid, :nome, :documento, :email, :telefone, :celular, :cep, :endereco, :numero, :complemento, :bairro, :cidade, :uf, :obs, :status)";
    $st = $pdo->prepare($sql);
    $ok = $st->execute([
      ':tid'=>$tid, ':nome'=>$nome, ':documento'=>$data['documento'], ':email'=>$data['email'],
      ':telefone'=>$data['telefone'], ':celular'=>$data['celular'], ':cep'=>$data['cep'],
      ':endereco'=>$data['endereco'], ':numero'=>$data['numero'], ':complemento'=>$data['complemento'],
      ':bairro'=>$data['bairro'], ':cidade'=>$data['cidade'], ':uf'=>$data['uf'],
      ':obs'=>$data['obs'], ':status'=>(int)$data['status']
    ]);
    $id = (int)$pdo->lastInsertId();
  }
} catch(Exception $e){
  error_log('cliente_save.php erro ao salvar: '.$e->getMessage());
  redirect_cliente_seguro($id > 0 ? '/cliente_form.php?id='.$id.'&err=save' : '/cliente_form.php?err=save');
}

header('Location: /cliente_form.php?id='.$id);