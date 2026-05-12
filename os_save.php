<?php
// os_save.php — Salva OS + Itens + Fotos (PDO / multi-tenant) c/ fallbacks p/ userId/tenantId

require_once __DIR__ . '/auth.php';
requireLogin();

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$pdo = db();

function osSaveFlash($type, $text) {
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  if (!isset($_SESSION['HF_GLOBAL_FEEDBACK']) || !is_array($_SESSION['HF_GLOBAL_FEEDBACK'])) {
    $_SESSION['HF_GLOBAL_FEEDBACK'] = [];
  }
  $_SESSION['HF_GLOBAL_FEEDBACK'][] = ['type' => $type, 'text' => $text];
}

function osSaveBackToForm($id, $msg = '') {
  if ($msg !== '') osSaveFlash('danger', $msg);
  header('Location: /os_form.php'.($id > 0 ? '?id='.(int)$id : ''));
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /os_list.php');
  exit;
}

// CSRF
$sessionToken = $_SESSION['csrf_token'] ?? '';
$postToken    = $_POST['csrf_token'] ?? '';

if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
  error_log('os_save.php csrf invalido user=' . ($_SESSION['USER_ID'] ?? ''));
  osSaveFlash('danger', 'Sessao expirada. Recarregue a tela e tente novamente.');
  header('Location: /os_list.php');
  exit;
}

// ===== FALLBACKS: tenantId() / userId() =====
$tid = function_exists('tenantId') ? (int)tenantId() : (int)($_SESSION['tenant_id'] ?? 0);
$uid = (int)($_SESSION['USER_ID'] ?? $_SESSION['user_id'] ?? (function_exists('userId') ? userId() : 0));

if ($tid <= 0) { header('Location: /login.php'); exit; }
if ($uid <= 0) { header('Location: /login.php'); exit; }

// -------- helpers --------
function brToFloat($v){
  $v = trim((string)$v);
  if ($v==='') return 0.0;
  $v = str_replace(['.', ' '], '', $v);
  $v = str_replace(',', '.', $v);
  return (float)$v;
}

function fetchTenantEntityIds(PDO $pdo, $table, array $ids, $tenantId){
  if ($table !== 'hf_produtos' && $table !== 'hf_servicos') return [];

  $ids = array_values(array_unique(array_map('intval', $ids)));
  $ids = array_values(array_filter($ids, function($v){ return $v > 0; }));
  if (!$ids) return [];

  $params = [':tid' => (int)$tenantId];
  $ph = [];
  foreach ($ids as $k => $idv) {
    $p = ':id' . $k;
    $ph[] = $p;
    $params[$p] = (int)$idv;
  }

  $sql = "SELECT id
            FROM {$table}
           WHERE tenant_id = :tid
             AND deleted_at IS NULL
             AND id IN (" . implode(',', $ph) . ")";
  $st = $pdo->prepare($sql);
  $st->execute($params);

  $map = [];
  foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $idFound) {
    $map[(int)$idFound] = true;
  }
  return $map;
}

function osSaveMakeImage($path){
  $info = @getimagesize($path);
  if (!$info || empty($info['mime'])) return null;

  switch ($info['mime']) {
    case 'image/jpeg':
      return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null;
    case 'image/png':
      return function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null;
    case 'image/gif':
      return function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : null;
    default:
      return null;
  }
}

function osSaveJpeg($srcPath, $dstPath, $maxW=1600, $maxH=1600, $q=85){
  $img = osSaveMakeImage($srcPath);
  if (!$img) return false;

  $w = imagesx($img);
  $h = imagesy($img);
  $ratio = min($maxW / max(1,$w), $maxH / max(1,$h), 1);
  $nw = (int) floor($w * $ratio);
  $nh = (int) floor($h * $ratio);

  $canvas = imagecreatetruecolor($nw, $nh);
  $white = imagecolorallocate($canvas, 255, 255, 255);
  imagefill($canvas, 0, 0, $white);
  imagecopyresampled($canvas, $img, 0,0,0,0, $nw,$nh, $w,$h);

  $ok = imagejpeg($canvas, $dstPath, $q);
  imagedestroy($canvas);
  imagedestroy($img);

  return $ok;
}

// -------- coleta POST --------
$id            = (int)($_POST['id'] ?? 0);
$cliente_id    = (int)($_POST['cliente_id'] ?? 0);
$status        = (string)($_POST['status'] ?? 'aberta');
$prioridade    = (string)($_POST['prioridade'] ?? 'baixa');
$tecnico       = trim((string)($_POST['tecnico'] ?? ''));
$garantia_dias = (int)($_POST['garantia_dias'] ?? 0);

$valor_mao_obra= brToFloat($_POST['valor_mao_obra'] ?? 0);
$defeito       = (string)($_POST['defeito'] ?? '');
$laudo         = (string)($_POST['laudo'] ?? '');

$desconto      = brToFloat($_POST['desconto'] ?? 0);
$acrescimo     = brToFloat($_POST['acrescimo'] ?? 0);
$total         = brToFloat($_POST['total'] ?? 0);

// ===== FINANCEIRO (vindo da tela) =====
$forma_pagto       = trim((string)($_POST['forma_pagto'] ?? ''));
$status_financeiro = trim((string)($_POST['status_financeiro'] ?? 'pendente'));
$valor_pago        = brToFloat($_POST['valor_pago'] ?? 0);
$data_pagto_raw    = trim((string)($_POST['data_pagto'] ?? ''));

if ($id < 0) {
  osSaveFlash('danger', 'OS invalida para salvamento.');
  header('Location: /os_list.php');
  exit;
}
if ($garantia_dias < 0) { $garantia_dias = 0; }
if ($status === '') { $status = 'aberta'; }
if ($prioridade === '') { $prioridade = 'baixa'; }
if (strlen($tecnico) > 120) { $tecnico = substr($tecnico, 0, 120); }
if ($desconto < 0) { $desconto = 0; }
if ($acrescimo < 0) { $acrescimo = 0; }
if ($total < 0) { $total = 0; }
if ($valor_mao_obra < 0) { $valor_mao_obra = 0; }
if ($valor_pago < 0) { $valor_pago = 0; }

if ($status_financeiro === '') {
  $status_financeiro = 'pendente';
}

$status_financeiro = strtolower($status_financeiro);
if (!in_array($status_financeiro, ['pendente','parcial','pago'], true)) {
  $status_financeiro = 'pendente';
}
$data_pagto = ($data_pagto_raw !== '') ? $data_pagto_raw : null;

if ($data_pagto_raw !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_pagto_raw)) {
  osSaveBackToForm($id, 'Data de pagamento invalida.');
}

// Itens
$item_tipo = $_POST['item_tipo'] ?? [];
$item_ref  = $_POST['item_ref']  ?? [];
$item_desc = $_POST['item_desc'] ?? [];
$item_qtd  = $_POST['item_qtd']  ?? [];
$item_vu   = $_POST['item_vu']   ?? [];
if (!is_array($item_tipo)) $item_tipo = [];
if (!is_array($item_ref))  $item_ref  = [];
if (!is_array($item_desc)) $item_desc = [];
if (!is_array($item_qtd))  $item_qtd  = [];
if (!is_array($item_vu))   $item_vu   = [];

if ($cliente_id <= 0) { osSaveBackToForm($id, 'Selecione um cliente para salvar a OS.'); }

$stCliente = $pdo->prepare("SELECT id FROM hf_clientes WHERE id = :id AND tenant_id = :t AND deleted_at IS NULL LIMIT 1");
$stCliente->execute([':id' => $cliente_id, ':t' => $tid]);
if (!$stCliente->fetch(PDO::FETCH_ASSOC)) {
  error_log("os_save.php cliente invalido para tenant cliente_id={$cliente_id} tenant={$tid} user={$uid}");
  osSaveBackToForm($id, 'Cliente invalido para esta empresa.');
}

$itensNormalizados = [];
$produtoRefs = [];
$servicoRefs = [];
$n = max(count($item_tipo), count($item_ref), count($item_desc), count($item_qtd), count($item_vu));
for ($i=0; $i<$n; $i++){
  $tipo = strtoupper(trim($item_tipo[$i] ?? ''));
  if ($tipo !== 'P' && $tipo !== 'S') continue;

  $ref  = (int)($item_ref[$i] ?? 0);
  $desc = trim((string)($item_desc[$i] ?? ''));
  $qtd  = brToFloat($item_qtd[$i] ?? 0);
  $vu   = brToFloat($item_vu[$i] ?? 0);

  if ($qtd <= 0) continue;
  if ($vu < 0) $vu = 0;
  if ($ref < 0) $ref = 0;

  if ($ref > 0) {
    if ($tipo === 'P') $produtoRefs[] = $ref;
    if ($tipo === 'S') $servicoRefs[] = $ref;
  }

  $itensNormalizados[] = [
    'tipo' => $tipo,
    'ref' => $ref,
    'desc' => $desc,
    'qtd' => $qtd,
    'vu' => $vu,
    'tot' => round($qtd * $vu, 2),
  ];
}

$produtosValidos = fetchTenantEntityIds($pdo, 'hf_produtos', $produtoRefs, $tid);
$servicosValidos = fetchTenantEntityIds($pdo, 'hf_servicos', $servicoRefs, $tid);
foreach ($itensNormalizados as $item) {
  if ($item['ref'] <= 0) continue;
  if ($item['tipo'] === 'P' && empty($produtosValidos[$item['ref']])) {
    error_log("os_save.php item produto invalido ref={$item['ref']} tenant={$tid} user={$uid}");
    osSaveBackToForm($id, 'Produto selecionado nao esta disponivel.');
  }
  if ($item['tipo'] === 'S' && empty($servicosValidos[$item['ref']])) {
    error_log("os_save.php item servico invalido ref={$item['ref']} tenant={$tid} user={$uid}");
    osSaveBackToForm($id, 'Servico selecionado nao esta disponivel.');
  }
}

$subtotalItens = 0.0;
foreach ($itensNormalizados as $item) {
  $subtotalItens += (float)$item['tot'];
}
$total = round($subtotalItens + $valor_mao_obra - $desconto + $acrescimo, 2);
if ($total < 0) $total = 0;

$status_informado = $status_financeiro;

if ($status_informado === 'pendente') {
  if ($valor_pago > 0) {
    osSaveBackToForm($id, 'Status pendente exige valor recebido igual a zero. Use Parcial ou Pago.');
  }
  if (!empty($data_pagto)) {
    osSaveBackToForm($id, 'Status pendente nao deve ter data de pagamento.');
  }
}

if ($status_informado === 'parcial') {
  if (!($total > 0 && $valor_pago > 0)) {
    osSaveBackToForm($id, 'Status parcial exige valor recebido maior que zero.');
  }
  if ($valor_pago >= $total) {
    osSaveBackToForm($id, 'Valor recebido no status parcial deve ser menor que o total final. Use Pago.');
  }
  if (empty($data_pagto)) {
    osSaveBackToForm($id, 'Informe a data do pagamento para status parcial.');
  }
}

if ($status_informado === 'pago') {
  if ($valor_pago < $total) {
    osSaveBackToForm($id, 'Status pago exige valor recebido igual ao total final. Use Parcial para valor menor.');
  }
  if ($valor_pago > $total) {
    osSaveBackToForm($id, 'Status pago exige valor recebido exatamente igual ao total final. Revise o valor.');
  }
  if (empty($data_pagto)) {
    osSaveBackToForm($id, 'Informe a data do pagamento para status pago.');
  }
}

if ($total > 0) {
  if ($valor_pago <= 0) {
    $status_financeiro = 'pendente';
    $valor_pago = 0.0;
    $data_pagto = null;
  } elseif ($valor_pago < $total) {
    $status_financeiro = 'parcial';
    if (empty($data_pagto)) {
      osSaveBackToForm($id, 'Informe a data do pagamento para status parcial.');
    }
  } else {
    $status_financeiro = 'pago';
    if (empty($data_pagto)) {
      osSaveBackToForm($id, 'Informe a data do pagamento para status pago.');
    }
    if ($valor_pago > $total) {
      osSaveBackToForm($id, 'Valor recebido acima do total final. Revise o valor informado.');
    }
  }
} else {
  $status_financeiro = 'pendente';
  $valor_pago = 0.0;
  $data_pagto = null;
}

// Valida OS antes de qualquer UPDATE/DELETE quando for edicao
if ($id > 0) {
  $stOs = $pdo->prepare("
    SELECT id
    FROM hf_os
    WHERE id = :id
      AND tenant_id = :t
      AND deleted_at IS NULL
    LIMIT 1
  ");
  $stOs->execute([':id' => $id, ':t' => $tid]);

  if (!$stOs->fetch(PDO::FETCH_ASSOC)) {
    error_log("os_save.php OS invalida para edicao id={$id} tenant={$tid} user={$uid}");
    osSaveFlash('danger', 'OS nao encontrada para edicao.');
    header('Location: /os_list.php');
    exit;
  }
}

$pdo->beginTransaction();

try {
  // ---- cria/atualiza OS ----
  if ($id > 0) {
    $sql = "UPDATE hf_os SET
              cliente_id=:c, status=:s, prioridade=:p, tecnico=:tec,
              valor_mao_obra=:mao, defeito=:def, laudo=:lau,
              desconto=:desc, acrescimo=:acr, total=:tot,
              forma_pagto=:fp, valor_pago=:vp, data_pagto=:dp, status_financeiro=:sf,
              updated_at=NOW()
            WHERE id=:id AND tenant_id=:t AND deleted_at IS NULL";
    $pdo->prepare($sql)->execute([
      ':c'=>$cliente_id, ':s'=>$status, ':p'=>$prioridade, ':tec'=>$tecnico,
      ':mao'=>$valor_mao_obra, ':def'=>$defeito, ':lau'=>$laudo,
      ':desc'=>$desconto, ':acr'=>$acrescimo, ':tot'=>$total,
      ':fp'=>$forma_pagto, ':vp'=>$valor_pago, ':dp'=>$data_pagto, ':sf'=>$status_financeiro,
      ':id'=>$id, ':t'=>$tid
    ]);

    if ($garantia_dias > 0) {
      $pdo->prepare("UPDATE hf_os SET garantia_ate = DATE_ADD(COALESCE(data_abertura, created_at), INTERVAL :d DAY) WHERE id=:id AND tenant_id=:t")
          ->execute([':d'=>$garantia_dias, ':id'=>$id, ':t'=>$tid]);
    } else {
      $pdo->prepare("UPDATE hf_os SET garantia_ate = NULL WHERE id=:id AND tenant_id=:t")
          ->execute([':id'=>$id, ':t'=>$tid]);
    }

  } else {
    try {
      $sql = "INSERT INTO hf_os
                (tenant_id, cliente_id, status, prioridade, tecnico,
                 valor_mao_obra, defeito, laudo, desconto, acrescimo, total,
                 forma_pagto, valor_pago, data_pagto, status_financeiro,
                 created_by, created_at)
              VALUES
                (:t, :c, :s, :p, :tec,
                 :mao, :def, :lau, :desc, :acr, :tot,
                 :fp, :vp, :dp, :sf,
                 :uid, NOW())";
      $pdo->prepare($sql)->execute([
        ':t'=>$tid, ':c'=>$cliente_id, ':s'=>$status, ':p'=>$prioridade, ':tec'=>$tecnico,
        ':mao'=>$valor_mao_obra, ':def'=>$defeito, ':lau'=>$laudo,
        ':desc'=>$desconto, ':acr'=>$acrescimo, ':tot'=>$total,
        ':fp'=>$forma_pagto, ':vp'=>$valor_pago, ':dp'=>$data_pagto, ':sf'=>$status_financeiro,
        ':uid'=>$uid,
      ]);
      $id = (int)$pdo->lastInsertId();
    } catch (Throwable $e) {
      $msg = strtolower((string)$e->getMessage());
      if (strpos($msg, 'created_by') === false && strpos($msg, 'unknown column') === false) {
        throw $e;
      }

      $sql = "INSERT INTO hf_os
                (tenant_id, cliente_id, status, prioridade, tecnico,
                 valor_mao_obra, defeito, laudo, desconto, acrescimo, total,
                 forma_pagto, valor_pago, data_pagto, status_financeiro,
                 created_at)
              VALUES
                (:t, :c, :s, :p, :tec,
                 :mao, :def, :lau, :desc, :acr, :tot,
                 :fp, :vp, :dp, :sf,
                 NOW())";
      $pdo->prepare($sql)->execute([
        ':t'=>$tid, ':c'=>$cliente_id, ':s'=>$status, ':p'=>$prioridade, ':tec'=>$tecnico,
        ':mao'=>$valor_mao_obra, ':def'=>$defeito, ':lau'=>$laudo,
        ':desc'=>$desconto, ':acr'=>$acrescimo, ':tot'=>$total,
        ':fp'=>$forma_pagto, ':vp'=>$valor_pago, ':dp'=>$data_pagto, ':sf'=>$status_financeiro
      ]);
      $id = (int)$pdo->lastInsertId();
    }

    if ($garantia_dias > 0) {
      $pdo->prepare("UPDATE hf_os SET garantia_ate = DATE_ADD(COALESCE(data_abertura, created_at), INTERVAL :d DAY) WHERE id=:id AND tenant_id=:t")
          ->execute([':d'=>$garantia_dias, ':id'=>$id, ':t'=>$tid]);
    }
  }

  // ---- itens: limpa e regrava ----
  // Seguro: para edicao, este ponto so e alcancado apos validar id + tenant + deleted_at.
  // Para nova OS, o id acabou de ser criado no tenant atual.
  $pdo->prepare("DELETE FROM hf_os_itens WHERE os_id=:id")->execute([':id'=>$id]);

  $insItem = $pdo->prepare("
    INSERT INTO hf_os_itens (os_id, tipo, ref_id, descricao, qtd, valor_unit, total)
    VALUES (:os,:tipo,:ref,:desc,:qtd,:vu,:tot)
  ");

  foreach ($itensNormalizados as $item){
    $insItem->execute([
      ':os'=>$id, ':tipo'=>$item['tipo'], ':ref'=>$item['ref'], ':desc'=>$item['desc'],
      ':qtd'=>$item['qtd'], ':vu'=>$item['vu'], ':tot'=>$item['tot']
    ]);
  }

  // ===========================================
  //  SINCRONIZAÇÃO COM TABELA os_financeiro
  // ===========================================
  try {
      $stmtOs = $pdo->prepare("
          SELECT data_abertura, created_at
            FROM hf_os
           WHERE id = :id AND tenant_id = :t
           LIMIT 1
      ");
      $stmtOs->execute([':id' => $id, ':t' => $tid]);
      $osRow = $stmtOs->fetch(PDO::FETCH_ASSOC);

      if ($osRow) {
          $data_os = !empty($osRow['data_abertura'])
              ? $osRow['data_abertura']
              : substr((string)$osRow['created_at'], 0, 10);
      } else {
          $data_os = date('Y-m-d');
      }

      $data_vencimento = $data_os;
      $statusFinanceiro = $status_financeiro;

      $sqlCheck = "
          SELECT id
            FROM os_financeiro
           WHERE tenant_id = :tenant_id
             AND os_id     = :os_id
           LIMIT 1
      ";
      $stmtCheck = $pdo->prepare($sqlCheck);
      $stmtCheck->execute([
          ':tenant_id' => $tid,
          ':os_id'     => $id,
      ]);
      $fin = $stmtCheck->fetch(PDO::FETCH_ASSOC);

      $dadosFinanceiro = [
          ':tenant_id'       => $tid,
          ':os_id'           => $id,
          ':cliente_id'      => $cliente_id,
          ':data_os'         => $data_os,
          ':data_vencimento' => $data_vencimento,
          ':data_pagamento'  => $data_pagto,
          ':valor_total'     => $total,
          ':valor_pago'      => $valor_pago,
          ':forma_pagamento' => $forma_pagto,
          ':status'          => $statusFinanceiro,
      ];

      if ($fin) {
          $sqlUpd = "
              UPDATE os_financeiro
                 SET cliente_id      = :cliente_id,
                     data_os         = :data_os,
                     data_vencimento = :data_vencimento,
                     data_pagamento  = :data_pagamento,
                     valor_total     = :valor_total,
                     valor_pago      = :valor_pago,
                     forma_pagamento = :forma_pagamento,
                     status          = :status
               WHERE id        = :id
                 AND tenant_id = :tenant_id
          ";
          $dadosFinanceiro[':id'] = (int)$fin['id'];

          $stmtUpd = $pdo->prepare($sqlUpd);
          $stmtUpd->execute($dadosFinanceiro);
      } else {
          $sqlIns = "
              INSERT INTO os_financeiro (
                  tenant_id,
                  os_id,
                  cliente_id,
                  data_os,
                  data_vencimento,
                  data_pagamento,
                  valor_total,
                  valor_pago,
                  forma_pagamento,
                  status
              ) VALUES (
                  :tenant_id,
                  :os_id,
                  :cliente_id,
                  :data_os,
                  :data_vencimento,
                  :data_pagamento,
                  :valor_total,
                  :valor_pago,
                  :forma_pagamento,
                  :status
              )
          ";
          $stmtIns = $pdo->prepare($sqlIns);
          $stmtIns->execute($dadosFinanceiro);
      }
  } catch (Throwable $eFin) {
      error_log('os_save.php financeiro sync: '.$eFin->getMessage());
  }

  // ---- fotos: upload múltiplo ----
  $baseDir  = __DIR__ . '/uploads/os/' . $tid . '/' . date('Y') . '/' . date('m') . '/' . $id . '/';
  $thumbDir = $baseDir . 'thumbs/';
  if (!is_dir($thumbDir)) {
    @mkdir($thumbDir, 0775, true);
  }

  if (!empty($_FILES['fotos']) && isset($_FILES['fotos']['tmp_name']) && is_array($_FILES['fotos']['tmp_name'])) {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
      error_log('os_save.php upload ignorado: biblioteca GD indisponivel.');
    } else {
      $cnt = count($_FILES['fotos']['tmp_name']);
      if ($cnt > 10) $cnt = 10;

      $maxBytes = 2 * 1024 * 1024;
      $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];

      $insFotoTenant = $pdo->prepare("
        INSERT INTO hf_os_fotos (tenant_id, os_id, caminho, thumb, original_nome, created_at)
        VALUES (:t,:os,:c,:th,:n, NOW())
      ");
      $insFotoNoTenant = $pdo->prepare("
        INSERT INTO hf_os_fotos (os_id, caminho, thumb, original_nome, created_at)
        VALUES (:os,:c,:th,:n, NOW())
      ");

      for ($i=0; $i<$cnt; $i++){
        $tmp   = $_FILES['fotos']['tmp_name'][$i] ?? null;
        $name  = (string)($_FILES['fotos']['name'][$i] ?? '');
        $size  = (int)($_FILES['fotos']['size'][$i] ?? 0);
        $error = (int)($_FILES['fotos']['error'][$i] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK || !$tmp || !is_uploaded_file($tmp)) continue;
        if ($size <= 0 || $size > $maxBytes) continue;

        $info = @getimagesize($tmp);
        $mime = $info['mime'] ?? '';
        if (!$info || !in_array($mime, $allowedMimes, true)) continue;

        $basename = date('Ymd_His') . '_' . substr(md5($name . microtime(true) . random_int(1000, 9999)), 0, 8) . '.jpg';
        $dest  = $baseDir  . $basename;
        $thumb = $thumbDir . $basename;

        if (!osSaveJpeg($tmp, $dest, 1600, 1600, 85)) continue;
        osSaveJpeg($dest, $thumb, 320, 320, 80);

        $cRel = 'uploads/os/' . $tid . '/' . date('Y') . '/' . date('m') . '/' . $id . '/' . $basename;
        $tRel = 'uploads/os/' . $tid . '/' . date('Y') . '/' . date('m') . '/' . $id . '/thumbs/' . $basename;

        try {
          $insFotoTenant->execute([':t'=>$tid, ':os'=>$id, ':c'=>$cRel, ':th'=>$tRel, ':n'=>$name]);
        } catch (Throwable $e) {
          $insFotoNoTenant->execute([':os'=>$id, ':c'=>$cRel, ':th'=>$tRel, ':n'=>$name]);
        }
      }
    }
  }

  $pdo->commit();
  osSaveFlash('success', 'OS salva com sucesso.');
  header("Location: /os_form.php?id={$id}");
  exit;

} catch(Throwable $e){
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  error_log('os_save.php fatal: '.$e->getMessage());
  osSaveBackToForm($id, 'Nao foi possivel salvar a OS. Tente novamente.');
}
