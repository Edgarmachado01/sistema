<?php
// os_save.php — Salva OS + Itens + Fotos (PDO / multi-tenant) c/ fallbacks p/ userId/tenantId

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/auth.php';
requireLogin();

require_once __DIR__ . '/db.php';

// garante sessão
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Método inválido.');
}

// ===== FALLBACKS: tenantId() / userId() =====
$tid = function_exists('tenantId') ? (int)tenantId() : (int)($_SESSION['tenant_id'] ?? 0);
$uid = (int)($_SESSION['USER_ID'] ?? $_SESSION['user_id'] ?? (function_exists('userId') ? userId() : 0));

if ($tid <= 0) { http_response_code(400); exit('Tenant inválido.'); }
if ($uid <= 0) { http_response_code(401); exit('Usuário inválido.'); }

// -------- helpers --------
function brToFloat($v){
  $v = trim((string)$v);
  if ($v==='') return 0.0;
  $v = str_replace(['.', ' '], '', $v);
  $v = str_replace(',', '.', $v);
  return (float)$v;
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
$data_pagto_raw    = trim((string)($_POST['data_pagto'] ?? '')); // YYYY-MM-DD ou vazio

if ($id < 0) { http_response_code(400); exit('ID inválido.'); }
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

// normaliza em minúsculo
$status_financeiro = strtolower($status_financeiro);

// REGRA: se marcar "pago" e não informar valor/data, sistema completa
if ($status_financeiro === 'pago') {
    if ($valor_pago <= 0 && $total > 0) {
        // se não informou valor, considera que pagou o total
        $valor_pago = $total;
    }
    if ($data_pagto_raw === '') {
        // se não informou data, usa hoje
        $data_pagto = date('Y-m-d');
    } else {
        $data_pagto = $data_pagto_raw;
    }
} else {
    // outros status: data de pagamento pode ser nula
    $data_pagto = ($data_pagto_raw !== '') ? $data_pagto_raw : null;
}

if ($data_pagto_raw !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_pagto_raw)) {
  http_response_code(400);
  exit('Data de pagamento inválida.');
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

if ($cliente_id <= 0) { http_response_code(400); exit('Cliente obrigatório.'); }

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
    // primeiro tenta com created_by; se a coluna não existir, cai no catch e insere sem ela
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
      // fallback: sem created_by (apenas quando a coluna não existir)
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
  $pdo->prepare("DELETE FROM hf_os_itens WHERE os_id=:id")->execute([':id'=>$id]);

  $insItem = $pdo->prepare("
    INSERT INTO hf_os_itens (os_id, tipo, ref_id, descricao, qtd, valor_unit, total)
    VALUES (:os,:tipo,:ref,:desc,:qtd,:vu,:tot)
  ");

  $n = max(count($item_tipo), count($item_ref), count($item_desc), count($item_qtd), count($item_vu));
  for ($i=0; $i<$n; $i++){
    $tipo = strtoupper(trim($item_tipo[$i] ?? ''));
    if ($tipo !== 'P' && $tipo !== 'S') continue;

    $ref  = (int)($item_ref[$i] ?? 0);
    $desc = trim((string)($item_desc[$i] ?? ''));
    $qtd  = brToFloat($item_qtd[$i] ?? 0);
    $vu   = brToFloat($item_vu[$i] ?? 0);
    if ($qtd <= 0) continue;

    $tot  = $qtd * $vu;

    $insItem->execute([
      ':os'=>$id, ':tipo'=>$tipo, ':ref'=>$ref, ':desc'=>$desc,
      ':qtd'=>$qtd, ':vu'=>$vu, ':tot'=>$tot
    ]);
  }

  // ===========================================
  //  SINCRONIZAÇÃO COM TABELA os_financeiro
  // ===========================================
  try {
      // busca datas da OS para usar no financeiro
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

      // por enquanto, vencimento = data_os (se depois tiver campo próprio, troca aqui)
      $data_vencimento = $data_os;

      // status financeiro já foi calculado lá em cima
      $statusFinanceiro = $status_financeiro;

      // verifica se já existe registro de financeiro para esta OS
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
          // UPDATE
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
          // INSERT
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
      // não quebra o salvamento da OS se der erro no financeiro
      error_log('os_save.php financeiro sync: '.$eFin->getMessage());
  }
  // ===== FIM FINANCEIRO OS =====

  // ---- fotos: upload múltiplo ----
  $baseDir  = __DIR__ . '/uploads/os/' . $tid . '/' . date('Y') . '/' . date('m') . '/' . $id . '/';
  $thumbDir = $baseDir . 'thumbs/';
  if (!is_dir($thumbDir)) { @mkdir($thumbDir, 0775, true); }

  // helpers GD
  $mkImg = function($path){
    $info = @getimagesize($path);
    if (!$info) return null;
    switch ($info['mime']) {
      case 'image/jpeg': return imagecreatefromjpeg($path);
      case 'image/png':  return imagecreatefrompng($path);
      case 'image/gif':  return imagecreatefromgif($path);
      default: return null;
    }
  };
  $saveJpeg = function($srcPath, $dstPath, $maxW=1600, $maxH=1600, $q=85) use ($mkImg){
    $img = $mkImg($srcPath);
    if (!$img) return false;
    $w = imagesx($img); $h = imagesy($img);
    $ratio = min($maxW / max(1,$w), $maxH / max(1,$h), 1);
    $nw = (int) floor($w * $ratio);
    $nh = (int) floor($h * $ratio);
    $canvas = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($canvas, $img, 0,0,0,0, $nw,$nh, $w,$h);
    $ok = imagejpeg($canvas, $dstPath, $q);
    imagedestroy($canvas); imagedestroy($img);
    return $ok;
  };
  $saveThumb = function($srcPath, $dstPath, $size=320, $q=80) use ($mkImg){
    $img = $mkImg($srcPath);
    if (!$img) return false;
    $w = imagesx($img); $h = imagesy($img);
    $ratio = min($size / max(1,$w), $size / max(1,$h), 1);
    $nw = (int) floor($w * $ratio);
    $nh = (int) floor($h * $ratio);
    $canvas = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($canvas, $img, 0,0,0,0, $nw,$nh, $w,$h);
    $ok = imagejpeg($canvas, $dstPath, $q);
    imagedestroy($canvas); imagedestroy($img);
    return $ok;
  };

  if (!empty($_FILES['fotos']) && isset($_FILES['fotos']['tmp_name']) && is_array($_FILES['fotos']['tmp_name'])) {
    $cnt = count($_FILES['fotos']['tmp_name']);
    if ($cnt > 10) $cnt = 10;

    // tenta com tenant_id na tabela de fotos; se não existir, cai no fallback sem tenant_id
    $insFotoTenant = $pdo->prepare("
      INSERT INTO hf_os_fotos (tenant_id, os_id, caminho, thumb, original_nome, created_at)
      VALUES (:t,:os,:c,:th,:n, NOW())
    ");
    $insFotoNoTenant = $pdo->prepare("
      INSERT INTO hf_os_fotos (os_id, caminho, thumb, original_nome, created_at)
      VALUES (:os,:c,:th,:n, NOW())
    ");

    for ($i=0; $i<$cnt; $i++){
      $tmp  = $_FILES['fotos']['tmp_name'][$i] ?? null;
      $name = $_FILES['fotos']['name'][$i] ?? '';
      $type = $_FILES['fotos']['type'][$i] ?? '';
      $size = (int)($_FILES['fotos']['size'][$i] ?? 0);

      if (!$tmp || !is_uploaded_file($tmp)) continue;
      if ($size > 8*1024*1024) continue;
      if (!preg_match('#^image/(jpeg|png|gif)$#i', $type)) continue;

      $basename = date('Ymd_His') . '_' . substr(md5($name.microtime(true)),0,8) . '.jpg';
      $dest  = $baseDir  . $basename;
      $thumb = $thumbDir . $basename;

      if (!$saveJpeg($tmp, $dest, 1600, 1600, 85)) continue;
      $saveThumb($dest, $thumb, 320, 80);

      $cRel = 'uploads/os/' . $tid . '/' . date('Y') . '/' . date('m') . '/' . $id . '/' . $basename;
      $tRel = 'uploads/os/' . $tid . '/' . date('Y') . '/' . date('m') . '/' . $id . '/thumbs/' . $basename;

      try {
        $insFotoTenant->execute([':t'=>$tid, ':os'=>$id, ':c'=>$cRel, ':th'=>$tRel, ':n'=>$name]);
      } catch (Throwable $e) {
        $insFotoNoTenant->execute([':os'=>$id, ':c'=>$cRel, ':th'=>$tRel, ':n'=>$name]);
      }
    }
  }

  $pdo->commit();
  header("Location: /os_form.php?id={$id}");
  exit;

} catch(Throwable $e){
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  http_response_code(500);
  error_log('os_save.php fatal: '.$e->getMessage());
  echo '<pre>Erro ao salvar OS: '.$e->getMessage().'</pre>';
  exit;
}
