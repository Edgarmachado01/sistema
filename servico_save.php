<?php
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

$pdo = db();
$tid = tenantId();
if (!$tid) { http_response_code(400); exit('Tenant inválido'); }

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
  'sla_dias'      => ($_POST['sla_dias'] !== '' ? (int)$_POST['sla_dias'] : null),
  'garantia_dias' => ($_POST['garantia_dias'] !== '' ? (int)$_POST['garantia_dias'] : null),
  'comissao_pct'  => ($_POST['comissao_pct'] !== '' ? (float)$_POST['comissao_pct'] : null),
  'descricao'     => trim($_POST['descricao'] ?? ''),
  'status'        => (int)($_POST['status'] ?? 1),
];

try{
  if ($id>0) {
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
  // log se quiser
}

header('Location: /servico_form.php?id='.$id);
