<?php
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function redirect_produto_seguro($location = '/produtos.php') {
  header('Location: '.$location);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  redirect_produto_seguro('/produtos.php');
}

$sessionToken = (string)($_SESSION['csrf_token'] ?? '');
$postToken = (string)($_POST['csrf_token'] ?? '');
if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
  error_log('produto_save.php csrf invalido');
  redirect_produto_seguro('/produtos.php');
}

$pdo = db();
$tid = tenantId();
if (!$tid) {
  error_log('produto_save.php tenant invalido');
  redirect_produto_seguro('/produtos.php');
}

$id   = (int)($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
if ($nome==='') { header('Location: /produto_form.php?err=nome'); exit; }

function money_to_decimal($v){
  // aceita "1.234,56" ou "1234.56" e normaliza para 1234.56
  $v = trim((string)$v);
  if ($v==='') return null;
  $v = str_replace(' ','',$v);
  if (strpos($v, ',') !== false) {
    $v = str_replace('.','',$v);
    $v = str_replace(',','.', $v);
  }
  return (float)$v;
}

function get_table_columns(PDO $pdo, $table){
  static $cache = [];
  if (isset($cache[$table])) return $cache[$table];

  $sql = "SELECT COLUMN_NAME
          FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :t";
  $st = $pdo->prepare($sql);
  $st->execute([':t'=>$table]);
  $cols = [];
  foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $c) {
    $cols[$c] = true;
  }
  $cache[$table] = $cols;
  return $cols;
}

function has_col(array $cols, $name){
  return isset($cols[$name]);
}

$data = [
  'sku'           => trim($_POST['sku'] ?? ''),
  'categoria'     => trim($_POST['categoria'] ?? ''),
  'unidade'       => trim($_POST['unidade'] ?? ''),
  'ncm'           => trim($_POST['ncm'] ?? ''),
  'garantia_dias' => (($_POST['garantia_dias'] ?? '') !== '' ? (int)$_POST['garantia_dias'] : null),
  'preco'         => money_to_decimal($_POST['preco'] ?? ''),
  'custo'         => money_to_decimal($_POST['custo'] ?? 0),
  'descricao'     => trim($_POST['descricao'] ?? ''),
  'status'        => (int)($_POST['status'] ?? 1),
];
if ($data['preco'] === null || $data['preco'] < 0) {
  header('Location: /produto_form.php'.($id > 0 ? '?id='.$id.'&err=preco' : '?err=preco')); exit;
}
if ($data['sku'] === '') {
  $data['sku'] = null;
}

try{
  $cols = get_table_columns($pdo, 'hf_produtos');

  if ($id>0) {
    $check = $pdo->prepare("SELECT id FROM hf_produtos WHERE id=:id AND tenant_id=:tid AND deleted_at IS NULL LIMIT 1");
    $check->execute([':id'=>$id, ':tid'=>$tid]);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
      error_log('produto_save.php produto nao encontrado ou fora do tenant: id='.$id.' tenant_id='.$tid);
      redirect_produto_seguro('/produtos.php');
    }

    $setParts = ["nome=:nome"];
    $bind = [':nome'=>$nome];
    $optionalMap = [
      'sku' => 'sku',
      'categoria' => 'categoria',
      'unidade' => 'unidade',
      'ncm' => 'ncm',
      'garantia_dias' => 'garantia_dias',
      'preco' => 'preco',
      'custo' => 'custo',
      'descricao' => 'descricao',
      'status' => 'status',
    ];
    foreach ($optionalMap as $col => $key) {
      if (has_col($cols, $col)) {
        $setParts[] = $col.'=:'.$key;
        $bind[':'.$key] = $data[$key];
      }
    }

    $sql = "UPDATE hf_produtos
            SET ".implode(', ', $setParts)."
            WHERE id=:id AND tenant_id=:tid AND deleted_at IS NULL";
    $st = $pdo->prepare($sql);
    $bind[':id'] = $id;
    $bind[':tid'] = $tid;
    $ok = $st->execute($bind);
  } else {
    $insertCols = ['tenant_id', 'nome'];
    $insertVals = [':tid', ':nome'];
    $bind = [':tid'=>$tid, ':nome'=>$nome];

    $optionalMap = [
      'sku' => 'sku',
      'categoria' => 'categoria',
      'unidade' => 'unidade',
      'ncm' => 'ncm',
      'garantia_dias' => 'garantia_dias',
      'preco' => 'preco',
      'custo' => 'custo',
      'descricao' => 'descricao',
      'status' => 'status',
    ];
    foreach ($optionalMap as $col => $key) {
      if (has_col($cols, $col)) {
        $insertCols[] = $col;
        $insertVals[] = ':'.$key;
        $bind[':'.$key] = $data[$key];
      }
    }

    $sql = "INSERT INTO hf_produtos
      (".implode(', ', $insertCols).")
      VALUES
      (".implode(', ', $insertVals).")";
    $st = $pdo->prepare($sql);
    $ok = $st->execute($bind);
    $id = (int)$pdo->lastInsertId();
  }
} catch(Throwable $e){
  error_log('produto_save.php erro ao salvar: '.$e->getMessage());
  redirect_produto_seguro($id > 0 ? '/produto_form.php?id='.$id.'&err=save' : '/produto_form.php?err=save');
}

header('Location: /produto_form.php?id='.$id);
