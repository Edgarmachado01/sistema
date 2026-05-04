<?php
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

$pdo = db();
$tid = tenantId();
if (!$tid) { http_response_code(400); exit('Tenant inválido'); }

$id   = (int)($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
if ($nome==='') { header('Location: /produto_form.php?err=nome'); exit; }

function money_to_decimal($v){
  // aceita "1.234,56" ou "1234.56" e normaliza para 1234.56
  $v = trim((string)$v);
  if ($v==='') return 0.00;
  $v = str_replace(['.',' '],'',$v);
  $v = str_replace(',','.',$v);
  return (float)$v;
}

$data = [
  'sku'           => trim($_POST['sku'] ?? ''),
  'categoria'     => trim($_POST['categoria'] ?? ''),
  'unidade'       => trim($_POST['unidade'] ?? ''),
  'ncm'           => trim($_POST['ncm'] ?? ''),
  'garantia_dias' => ($_POST['garantia_dias'] !== '' ? (int)$_POST['garantia_dias'] : null),
  'preco'         => money_to_decimal($_POST['preco'] ?? 0),
  'custo'         => money_to_decimal($_POST['custo'] ?? 0),
  'descricao'     => trim($_POST['descricao'] ?? ''),
  'status'        => (int)($_POST['status'] ?? 1),
];

try{
  if ($id>0) {
    $sql = "UPDATE hf_produtos
            SET nome=:nome, sku=:sku, categoria=:categoria, unidade=:unidade, ncm=:ncm,
                garantia_dias=:garantia_dias, preco=:preco, custo=:custo,
                descricao=:descricao, status=:status
            WHERE id=:id AND tenant_id=:tid AND deleted_at IS NULL";
    $st = $pdo->prepare($sql);
    $ok = $st->execute([
      ':nome'=>$nome, ':sku'=>$data['sku'], ':categoria'=>$data['categoria'],
      ':unidade'=>$data['unidade'], ':ncm'=>$data['ncm'], ':garantia_dias'=>$data['garantia_dias'],
      ':preco'=>$data['preco'], ':custo'=>$data['custo'], ':descricao'=>$data['descricao'],
      ':status'=>$data['status'], ':id'=>$id, ':tid'=>$tid
    ]);
  } else {
    $sql = "INSERT INTO hf_produtos
      (tenant_id, nome, sku, categoria, unidade, ncm, garantia_dias, preco, custo, descricao, status)
      VALUES
      (:tid, :nome, :sku, :categoria, :unidade, :ncm, :garantia_dias, :preco, :custo, :descricao, :status)";
    $st = $pdo->prepare($sql);
    $ok = $st->execute([
      ':tid'=>$tid, ':nome'=>$nome, ':sku'=>$data['sku'], ':categoria'=>$data['categoria'],
      ':unidade'=>$data['unidade'], ':ncm'=>$data['ncm'], ':garantia_dias'=>$data['garantia_dias'],
      ':preco'=>$data['preco'], ':custo'=>$data['custo'], ':descricao'=>$data['descricao'],
      ':status'=>$data['status']
    ]);
    $id = (int)$pdo->lastInsertId();
  }
} catch(Exception $e){
  // logar se quiser
}

header('Location: /produto_form.php?id='.$id);
