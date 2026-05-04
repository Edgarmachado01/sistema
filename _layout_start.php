<?php
if (session_status()===PHP_SESSION_NONE) session_start();

require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

$pdo = db();

// Tenant atual (multi-empresa)
$tid = function_exists('tenantId')
    ? (int) tenantId()
    : (int) ($_SESSION['tenant_id'] ?? 0);

// BRAND por tenant – defaults
$brand = [
  'primary' => '#0d6efd',
  'mode'    => 'light',
  'logo'    => null,
  'name'    => 'Help Fácil',
];

// 1) Tema / cores vindos de brand.php (switcher, etc.)
if (file_exists(__DIR__.'/brand.php')) {
  require __DIR__.'/brand.php';
  if (function_exists('brandFromRequest')) {
    $b = brandFromRequest();
    if (is_array($b)) {
      $brand['primary'] = $b['primary'] ?? $brand['primary'];
      $brand['mode']    = $b['mode']    ?? $brand['mode'];
      $brand['logo']    = $b['logo']    ?? $brand['logo'];
      $brand['name']    = $b['name']    ?? $brand['name'];
    }
  }
}

// 2) Logo + nome fantasia vindos da tabela tenant_config
if ($tid > 0) {
  $stmt = $pdo->prepare("
      SELECT nome_fantasia, logo_path
      FROM tenant_config
      WHERE tenant_id = :tid
      LIMIT 1
  ");
  $stmt->execute([':tid' => $tid]);
  if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!empty($row['nome_fantasia'])) {
      $brand['name'] = $row['nome_fantasia'];
    }
    if (!empty($row['logo_path'])) {
      // caminho salvo em config_empresa.php (ex: uploads/logos/logo_tenant_1.png)
      $brand['logo'] = $row['logo_path'];
    }
  }
}

// 3) Converte cor primária pra RGB (Bootstrap)
$hex = ltrim($brand['primary'],'#');
if (strlen($hex)!==6) $hex='0d6efd';
list($r,$g,$b) = sscanf($hex, "%02x%02x%02x");
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="<?= $brand['mode']==='dark'?'dark':'light' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($brand['name']) ?> — Painel</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>:root{ --bs-primary:#<?= $hex ?>; --bs-primary-rgb:<?= "$r,$g,$b" ?>; }</style>

  <link rel="stylesheet" href="assets/theme.css?v=7">
</head>
<body>

<style>
:root{ --topbar-h:56px }
.hf-topbar{ background:#0d6efd; }
.hf-sidebar{ width:240px; min-height:100vh; background:var(--bs-body-bg); border-right:1px solid rgba(0,0,0,.06) }
.hf-content{ flex:1; min-width:0; padding:1.25rem 1.5rem 2rem }

@media (max-width: 991.98px){
  .hf-sidebar{
    position:fixed; top:var(--topbar-h); left:-260px;
    height:calc(100vh - var(--topbar-h)); width:240px; z-index:1045;
    background:var(--bs-body-bg); box-shadow:0 16px 40px rgba(0,0,0,.25); transition:left .2s ease;
    border-right:none;
  }
  body.sidebar-open .hf-sidebar{ left:0 }
  .hf-backdrop{ display:none }
  body.sidebar-open .hf-backdrop{
    display:block; position:fixed; inset:var(--topbar-h) 0 0 0; z-index:1040;
    background:rgba(0,0,0,.35); backdrop-filter:blur(1px)
  }
  body.sidebar-open{ overflow:hidden }
}
</style>

<nav class="navbar navbar-expand-lg hf-topbar">
  <div class="container-fluid">

    <button id="hf-menu-btn" class="btn btn-light btn-hamb me-2" type="button" aria-controls="hf-sidebar" aria-expanded="false" aria-label="Abrir menu">
      <i class="bi bi-list"></i>
    </button>

    <a class="navbar-brand text-white fw-semibold d-flex align-items-center gap-2" href="/dashboard.php">
      <?php if(!empty($brand['logo'])): ?>
        <!-- aqui você controla o tamanho visual do logo -->
        <img src="<?= htmlspecialchars($brand['logo']) ?>" style="height:28px; width:auto; object-fit:contain;" alt="Logo">
      <?php endif; ?>
      <!-- <span><?= htmlspecialchars($brand['name']) ?></span> -->
    </a>

    <div class="ms-auto d-flex align-items-center gap-2">
      <button class="btn btn-light btn-sm" onclick="hfToggleSwitcher()" title="Tema e cores">
        <i class="bi bi-gear"></i>
      </button>
      <a class="btn btn-light btn-sm" href="/logout.php" title="Sair">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</nav>

<div id="hf-backdrop" class="hf-backdrop"
     onclick="document.body.classList.remove('sidebar-open')"></div>

<div id="hf-switcher" class="hf-switcher">
  <h6 class="mb-2">Tema & cores</h6>
  <?php foreach ([
    'primary'=>'#0d6efd','success'=>'#198754','warning'=>'#ffc107',
    'danger'=>'#dc3545','pink'=>'#d63384','purple'=>'#6f42c1',
    'inverse'=>'#111827','dark'=>'#212529'
  ] as $key=>$hexColor): ?>
  <div class="form-check mb-1">
    <span class="hf-swatch" style="background:<?= $hexColor ?>"></span>
    <input class="form-check-input" type="radio" name="hf-theme" value="<?= $key ?>" onclick="hfSetTheme(this.value)">
    <?= ucfirst($key) ?>
  </div>
  <?php endforeach; ?>
  <button class="btn btn-outline-secondary btn-sm w-100"
          onclick="document.getElementById('hf-switcher').classList.remove('show')">Fechar</button>
</div>

<div class="d-flex">
