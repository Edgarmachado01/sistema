<?php
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function redirect_servico_seguro($location = '/servicos.php') {
  header('Location: '.$location);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_servico_seguro('/servicos.php');
}

$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
$postToken = (string)($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
  error_log('servico_save.php csrf invalido');
  redirect_servico_seguro('/servicos.php');
}

$pdo = db();
$tid = tenantId();
if (!$tid) {
  error_log('servico_save.php tenant invalido');
  redirect_servico_seguro('/servicos.php');
}

$id   = (int)($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
if ($nome==='') { header('Location: /servico_form.php?err=nome'); exit; }

function money_to_decimal($v){
  $v = trim((string)$v);
  if ($v==='') return 0.00;
  $v = str_replace(['.',' '],'',$v);
  $v = str_replace(',','.',$v);
  return (float)$v;
}

$data = [
  'categoria'     => trim($_POST['categoria'] ?? ''),
  'preco'         => money_to_decimal($_POST['preco'] ?? 0),
  'custo_ref'     => money_to_decimal($_POST['custo_ref'] ?? 0),
  'sla_dias'      => (($_POST['sla_dias'] ?? '') !== '' ? (int)$_POST['sla_dias'] : null),
  'garantia_dias' => (($_POST['garantia_dias'] ?? '') !== '' ? (int)$_POST['garantia_dias'] : null),
  'comissao_pct'  => (($_POST['comissao_pct'] ?? '') !== '' ? (float)$_POST['comissao_pct'] : null),
  'descricao'     => trim($_POST['descricao'] ?? ''),
  'status'        => (int)($_POST['status'] ?? 1),
];

try{
  if ($id>0) {
    $check = $pdo->prepare("SELECT id FROM hf_servicos WHERE id=:id AND tenant_id=:tid AND deleted_at IS NULL LIMIT 1");
    $check->execute([':id'=>$id, ':tid'=>$tid]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
      error_log('servico_save.php servico nao encontrado ou fora do tenant: id='.$id.' tenant_id='.$tid);
      redirect_servico_seguro('/servicos.php');
    }

    $sql = "UPDATE hf_servicos
            SET nome=:nome, categoria=:categoria, preco=:preco, custo_ref=:custo_ref,
                sla_dias=:sla_dias, garantia_dias=:garantia_dias, comissao_pct=:comissao_pct,
                descricao=:descricao, status=:status
            WHERE id=:id AND tenant_id=:tid AND deleted_at IS NULL";
    $st = $pdo->prepare($sql);
    $ok = $st->execute([
      ':nome'=>$nome, ':categoria'=>$data['categoria'], ':preco'=>$data['preco'],
      ':custo_ref'=>$data['custo_ref'], ':sla_dias'=>$data['sla_dias'], ':garantia_dias'=>$data['garantia_dias'],
      ':comissao_pct'=>$data['comissao_pct'], ':descricao'=>$data['descricao'],
      ':status'=>$data['status'], ':id'=>$id, ':tid'=>$tid
    ]);
  } else {
    $sql = "INSERT INTO hf_servicos
      (tenant_id, nome, categoria, preco, custo_ref, sla_dias, garantia_dias, comissao_pct, descricao, status)
      VALUES
      (:tid, :nome, :categoria, :preco, :custo_ref, :sla_dias, :garantia_dias, :comissao_pct, :descricao, :status)";
    $st = $pdo->prepare($sql);
    $ok = $st->execute([
      ':tid'=>$tid, ':nome'=>$nome, ':categoria'=>$data['categoria'], ':preco'=>$data['preco'],
      ':custo_ref'=>$data['custo_ref'], ':sla_dias'=>$data['sla_dias'], ':garantia_dias'=>$data['garantia_dias'],
      ':comissao_pct'=>$data['comissao_pct'], ':descricao'=>$data['descricao'], ':status'=>$data['status']
    ]);
    $id = (int)$pdo->lastInsertId();
  }
} catch(Exception $e){
  error_log('servico_save.php erro ao salvar: '.$e->getMessage());
  redirect_servico_seguro($id > 0 ? '/servico_form.php?id='.$id.'&err=save' : '/servico_form.php?err=save');
}

header('Location: /servico_form.php?id='.$id);