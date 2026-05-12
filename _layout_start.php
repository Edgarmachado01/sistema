<?php
if (session_status()===PHP_SESSION_NONE) session_start();

$hfGlobalFeedback = [];
if (!function_exists('hfPushGlobalFeedback')) {
  function hfPushGlobalFeedback(array &$bag, $type, $text) {
    $msg = trim((string)$text);
    if ($msg === '') return;
    $kind = strtolower(trim((string)$type));
    if (!in_array($kind, ['success', 'danger', 'warning', 'info'], true)) {
      $kind = 'info';
    }
    $bag[] = ['type' => $kind, 'text' => $msg];
  }
}

if (!empty($_SESSION['HF_GLOBAL_FEEDBACK']) && is_array($_SESSION['HF_GLOBAL_FEEDBACK'])) {
  foreach ($_SESSION['HF_GLOBAL_FEEDBACK'] as $item) {
    if (!is_array($item)) continue;
    hfPushGlobalFeedback($hfGlobalFeedback, $item['type'] ?? 'info', $item['text'] ?? '');
  }
  unset($_SESSION['HF_GLOBAL_FEEDBACK']);
}

$hfLegacyFlashMap = [
  'HF_FLASH_SUCCESS' => 'success',
  'HF_FLASH_ERROR' => 'danger',
  'HF_FLASH_WARNING' => 'warning',
  'HF_FLASH_INFO' => 'info',
];
foreach ($hfLegacyFlashMap as $sessionKey => $kind) {
  if (!empty($_SESSION[$sessionKey])) {
    hfPushGlobalFeedback($hfGlobalFeedback, $kind, $_SESSION[$sessionKey]);
    unset($_SESSION[$sessionKey]);
  }
}

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
  'id'      => null,
  'slug'    => null,
  'primary' => '#0d6efd',
  'mode'    => 'light',
  'logo'    => null,
  'name'    => 'Help Fácil',
];

if (!function_exists('hfValidHexColor')) {
  function hfValidHexColor($value) {
    return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', trim($value));
  }
}

$tenantBrandPrimary = null;
$tenantConfigPrimary = null;

// 1) Tema / cores vindos de brand.php
if (file_exists(__DIR__.'/brand.php')) {
  require_once __DIR__.'/brand.php';

  if ($tid > 0 && function_exists('normalizeBrand')) {
    try {
      $stmtBrand = $pdo->prepare("SELECT * FROM tenants WHERE id = :tid LIMIT 1");
      $stmtBrand->execute([':tid' => $tid]);
      if ($tenantBrand = $stmtBrand->fetch(PDO::FETCH_ASSOC)) {
        if (hfValidHexColor($tenantBrand['brand_primary'] ?? null)) {
          $tenantBrandPrimary = trim($tenantBrand['brand_primary']);
        }

        $b = normalizeBrand($tenantBrand);
        if (function_exists('mergeTenantConfigBrand')) {
          $b = mergeTenantConfigBrand($b);
        }
        if (is_array($b)) {
          $brand['id']      = $b['id']      ?? $brand['id'];
          $brand['slug']    = $b['slug']    ?? $brand['slug'];
          $brand['primary'] = $b['primary'] ?? $brand['primary'];
          $brand['mode']    = $b['mode']    ?? $brand['mode'];
          $brand['logo']    = $b['logo']    ?? $brand['logo'];
          $brand['name']    = $b['name']    ?? $brand['name'];
        }
      }
    } catch (Exception $e) {
      error_log('_layout_start.php brand tenant: '.$e->getMessage());
    }
  }

  if (empty($brand['id']) && function_exists('brandFromRequest')) {
    $b = brandFromRequest();
    if (is_array($b)) {
      $brand['id']      = $b['id']      ?? $brand['id'];
      $brand['slug']    = $b['slug']    ?? $brand['slug'];
      $brand['primary'] = $b['primary'] ?? $brand['primary'];
      $brand['mode']    = $b['mode']    ?? $brand['mode'];
      $brand['logo']    = $b['logo']    ?? $brand['logo'];
      $brand['name']    = $b['name']    ?? $brand['name'];
    }
  }
}

// 2) Fallback direto da tenant_config para instalações sem helpers novos em brand.php
if ($tid > 0) {
  try {
    $stmt = $pdo->prepare("
      SELECT nome_fantasia, logo_path, cor_primaria
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
        $brand['logo'] = $row['logo_path'];
      }
      if (hfValidHexColor($row['cor_primaria'] ?? null)) {
        $tenantConfigPrimary = trim($row['cor_primaria']);
        $brand['primary'] = $tenantConfigPrimary;
      }
    }
  } catch (Exception $e) {
    error_log('_layout_start.php tenant_config brand: '.$e->getMessage());
  }
}

// 3) Garante cor primária válida e converte pra RGB (Bootstrap)
if (hfValidHexColor($tenantConfigPrimary)) {
  $brand['primary'] = $tenantConfigPrimary;
} elseif (hfValidHexColor($tenantBrandPrimary)) {
  $brand['primary'] = $tenantBrandPrimary;
} else {
  $brand['primary'] = '#0d6efd';
}
?>
<!-- BRAND DEBUG: primary=<?= htmlspecialchars((string)($brand['primary'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, logo=<?= htmlspecialchars((string)($brand['logo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, name=<?= htmlspecialchars((string)($brand['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>, tenant=<?= htmlspecialchars((string)$tid, ENT_QUOTES, 'UTF-8') ?> -->
<?php
$hex = ltrim($brand['primary'], '#');
list($r,$g,$b) = sscanf($hex, "%02x%02x%02x");

$userName = trim((string)($_SESSION['USER_NAME'] ?? $_SESSION['NOME'] ?? $_SESSION['nome'] ?? ''));
$userEmail = trim((string)($_SESSION['USER_EMAIL'] ?? $_SESSION['EMAIL'] ?? $_SESSION['email'] ?? ''));

if ($userName === '' && $userEmail !== '') {
  $userName = trim(strstr($userEmail, '@', true) ?: $userEmail);
}
if ($userName === '') {
  $userName = 'Usuário logado';
}

$userInitial = strtoupper(substr($userName, 0, 1));
if ($userInitial === '') {
  $userInitial = 'U';
}

$userRole = 'Usuário';
if (function_exists('isAdminLoja') && isAdminLoja()) {
  $userRole = 'Administrador';
} elseif (function_exists('isFinanceiro') && isFinanceiro()) {
  $userRole = 'Financeiro';
} elseif (function_exists('isTecnico') && isTecnico()) {
  $userRole = 'Técnico';
} elseif (function_exists('isAtendente') && isAtendente()) {
  $userRole = 'Atendente';
} elseif (function_exists('isVisualizador') && isVisualizador()) {
  $userRole = 'Visualizador';
}

$trialBanner = null;
$trialWhatsappUrl = 'https://wa.me/5500000000000?text=Quero%20falar%20sobre%20meu%20teste%20gratis%20do%20HelpDesk%20Facil';

if ($tid > 0 && !(function_exists('isSysAdmin') && isSysAdmin())) {
  try {
    $stmtTrial = $pdo->prepare("
      SELECT
        ts.status,
        ts.trial_end_at,
        p.name AS plan_name,
        GREATEST(DATEDIFF(ts.trial_end_at, NOW()), 0) AS days_left
      FROM tenant_subscriptions ts
      JOIN plans p ON p.id = ts.plan_id
      WHERE ts.tenant_id = :tid
      ORDER BY ts.id DESC
      LIMIT 1
    ");
    $stmtTrial->execute([':tid' => $tid]);

    if ($subscription = $stmtTrial->fetch(PDO::FETCH_ASSOC)) {
      if (($subscription['status'] ?? '') === 'trial') {
        $trialBanner = [
          'plan_name' => trim((string)($subscription['plan_name'] ?? '')),
          'days_left' => (int)($subscription['days_left'] ?? 0),
          'trial_end_at' => $subscription['trial_end_at'] ?? null,
        ];
      }
    }
  } catch (Exception $e) {
    error_log('_layout_start.php trial banner: '.$e->getMessage());
  }
}
?>
<!doctype html>
<html lang="pt-br" data-bs-theme="<?= $brand['mode']==='dark'?'dark':'light' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($brand['name']) ?> — Painel</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>:root{ --bs-primary:#<?= $hex ?>; --bs-primary-rgb:<?= "$r,$g,$b" ?>; --brand:#<?= $hex ?>; --brand-rgb:<?= "$r,$g,$b" ?>; }</style>

  <link rel="stylesheet" href="assets/theme.css?v=8">
</head>
<body>

<style>
:root{ --topbar-h:64px }

.hf-app-topbar{
  position:sticky;
  top:0;
  z-index:1020;
  min-height:var(--topbar-h);
  padding:0;
  background:linear-gradient(180deg, rgba(var(--bs-primary-rgb), .98) 0%, rgba(var(--bs-primary-rgb), .90) 100%) !important;
  border-bottom:1px solid rgba(255,255,255,.16);
  box-shadow:0 8px 24px rgba(15,23,42,.14);
  backdrop-filter:saturate(140%) blur(8px);
}

.hf-topbar-inner{
  min-height:var(--topbar-h);
  display:flex;
  align-items:center;
  gap:.85rem;
  padding-top:.45rem;
  padding-bottom:.45rem;
}

.hf-menu-trigger{
  width:42px;
  height:42px;
  flex:0 0 42px;
  display:inline-grid;
  place-items:center;
  padding:0;
  border:1px solid rgba(255,255,255,.28);
  border-radius:.85rem;
  color:#0f172a;
  background:rgba(255,255,255,.94);
  box-shadow:0 6px 16px rgba(15,23,42,.12);
}

.hf-menu-trigger i{
  font-size:1.35rem;
  line-height:1;
}

.hf-menu-trigger:hover{
  background:#fff;
  transform:translateY(-1px);
}

.hf-menu-trigger:active{
  transform:scale(.98);
}

.hf-brand-link{
  min-width:0;
  max-width:min(56vw, 520px);
  display:flex;
  align-items:center;
  gap:.8rem;
  padding:0;
  margin:0;
  color:#fff;
  text-decoration:none;
}

.hf-brand-link:hover{
  color:#fff;
}

.hf-brand-logo-wrap{
  width:46px;
  height:46px;
  flex:0 0 46px;
  display:grid;
  place-items:center;
  overflow:hidden;
  border-radius:.9rem;
  background:transparent;
  border:0;
  box-shadow:none;
}

.hf-brand-logo{
  max-width:38px;
  max-height:36px;
  object-fit:contain;
}

.hf-brand-mark{
  color:var(--bs-primary);
  font-size:1.25rem;
}

.hf-brand-text{
  min-width:0;
  display:flex;
  flex-direction:column;
  justify-content:center;
  line-height:1.08;
}

.hf-brand-name{
  max-width:100%;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
  font-size:1.05rem;
  font-weight:850;
  letter-spacing:0;
}

.hf-brand-subtitle{
  margin-top:.2rem;
  color:rgba(255,255,255,.74);
  font-size:.72rem;
  font-weight:650;
}

.hf-top-actions{
  display:flex;
  align-items:center;
  gap:.5rem;
  min-width:0;
  flex:0 0 auto;
}

.hf-user-menu{
  min-width:0;
}

.hf-user-toggle{
  min-height:42px;
  max-width:280px;
  display:flex;
  align-items:center;
  gap:.58rem;
  padding:.3rem .75rem .3rem .34rem;
  border:1px solid rgba(255,255,255,.28);
  border-radius:999px;
  color:#fff;
  background:rgba(255,255,255,.14);
  box-shadow:none;
}

.hf-user-toggle:hover,
.hf-user-toggle:focus{
  color:#fff;
  background:rgba(255,255,255,.20);
  border-color:rgba(255,255,255,.36);
}

.hf-user-toggle::after{
  margin-left:.1rem;
  opacity:.82;
}

.hf-user-avatar{
  width:32px;
  height:32px;
  flex:0 0 32px;
  display:grid;
  place-items:center;
  border-radius:999px;
  color:var(--bs-primary);
  background:#fff;
  font-size:.8rem;
  font-weight:900;
}

.hf-user-copy{
  min-width:0;
  display:flex;
  flex-direction:column;
  align-items:flex-start;
  line-height:1.05;
}

.hf-user-name{
  max-width:170px;
  overflow:hidden;
  text-overflow:ellipsis;
  white-space:nowrap;
  font-size:.82rem;
  font-weight:800;
}

.hf-user-role{
  margin-top:.15rem;
  color:rgba(255,255,255,.72);
  font-size:.68rem;
  font-weight:700;
}

.hf-user-dropdown{
  min-width:220px;
  border:1px solid rgba(148,163,184,.24);
  border-radius:.9rem;
  box-shadow:0 18px 44px rgba(15,23,42,.16);
  overflow:hidden;
}

.hf-user-dropdown .dropdown-header{
  padding:.8rem .9rem .55rem;
}

.hf-user-dropdown .dropdown-item{
  display:flex;
  align-items:center;
  gap:.55rem;
  padding:.55rem .9rem;
  font-weight:650;
}

.hf-logout-btn{
  width:42px;
  height:42px;
  flex:0 0 42px;
  display:inline-grid;
  place-items:center;
  padding:0;
  border:0;
  border-radius:.85rem;
  color:#0f172a;
  background:rgba(255,255,255,.94);
  box-shadow:0 6px 16px rgba(15,23,42,.12);
}

.hf-logout-btn:hover{
  background:#fff;
  color:#dc3545;
  transform:translateY(-1px);
}

.hf-layout-shell{
  min-height:calc(100vh - var(--topbar-h));
  align-items:stretch;
}

.hf-sidebar{
  width:240px;
  min-height:calc(100vh - var(--topbar-h));
  background:var(--bs-body-bg);
  border-right:1px solid rgba(0,0,0,.06)
}

.hf-content{
  flex:1;
  min-width:0;
  padding:1.5rem 1.5rem 2rem;
}

.hf-trial-banner{
  padding:.85rem 1.5rem;
  background:
    linear-gradient(90deg, rgba(var(--bs-primary-rgb), .10), rgba(16,185,129,.10)),
    #fff;
  border-bottom:1px solid rgba(148,163,184,.22);
}

.hf-trial-banner-inner{
  max-width:1320px;
  margin:0 auto;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:1rem;
  color:#0f172a;
}

.hf-trial-banner-copy{
  min-width:0;
  display:flex;
  align-items:center;
  gap:.75rem;
  font-size:.92rem;
  font-weight:650;
}

.hf-trial-banner-icon{
  width:34px;
  height:34px;
  flex:0 0 34px;
  display:grid;
  place-items:center;
  border-radius:.85rem;
  color:var(--bs-primary);
  background:rgba(var(--bs-primary-rgb), .12);
}

.hf-trial-banner-copy strong{
  font-weight:850;
}

.hf-trial-banner-copy > span:not(.hf-trial-banner-icon){
  color:#475569;
}

.hf-trial-banner-action{
  flex:0 0 auto;
  border-radius:999px;
  font-size:.82rem;
  font-weight:800;
}

@media (max-width: 991.98px){
  .hf-topbar-inner{
    gap:.6rem;
  }

  .hf-brand-link{
    max-width:calc(100vw - 180px);
    gap:.62rem;
  }

  .hf-brand-logo-wrap{
    width:40px;
    height:40px;
    flex-basis:40px;
  }

  .hf-brand-logo{
    max-width:34px;
    max-height:31px;
  }

  .hf-brand-name{
    font-size:.95rem;
  }

  .hf-brand-subtitle,
  .hf-user-copy{
    display:none;
  }

  .hf-user-toggle{
    width:42px;
    height:42px;
    justify-content:center;
    padding:.25rem;
  }

  .hf-user-toggle::after{
    display:none;
  }

  .hf-sidebar{
    position:fixed;
    top:var(--topbar-h);
    left:-260px;
    height:calc(100vh - var(--topbar-h));
    height:calc(100dvh - var(--topbar-h));
    max-height:calc(100vh - var(--topbar-h));
    max-height:calc(100dvh - var(--topbar-h));
    width:240px;
    z-index:1045;
    background:var(--bs-body-bg);
    box-shadow:0 16px 40px rgba(0,0,0,.25);
    transition:left .2s ease;
    border-right:none;
    overflow-y:auto;
    overflow-x:hidden;
    -webkit-overflow-scrolling:touch;
    overscroll-behavior:contain;
    padding-bottom:calc(1.75rem + env(safe-area-inset-bottom, 0px));
  }

  .hf-sidebar .nav,
  .hf-sidebar-nav{
    padding-bottom:calc(2.75rem + env(safe-area-inset-bottom, 0px));
  }

  .hf-sidebar .nav-link,
  .hf-menu-item{
    min-height:42px;
  }

  body.sidebar-open .hf-sidebar{ left:0 }

  .hf-backdrop{ display:none }

  body.sidebar-open .hf-backdrop{
    display:block;
    position:fixed;
    inset:var(--topbar-h) 0 0 0;
    z-index:1040;
    background:rgba(0,0,0,.35);
    backdrop-filter:blur(1px)
  }

  body.sidebar-open{ overflow:hidden }

  .hf-content{
    padding:1rem 1rem 1.5rem;
  }
}

@media (max-width: 767.98px){
  .hf-trial-banner{
    padding:.8rem 1rem;
  }

  .hf-trial-banner-inner{
    align-items:flex-start;
    flex-direction:column;
    gap:.7rem;
  }

  .hf-trial-banner-action{
    width:100%;
  }
}

@media (max-width: 575.98px){
  :root{ --topbar-h:60px }

  .hf-topbar-inner{
    padding-left:.75rem;
    padding-right:.75rem;
  }

  .hf-menu-trigger{
    width:40px;
    height:40px;
    flex-basis:40px;
  }

  .hf-brand-link{
    max-width:calc(100vw - 148px);
  }

  .hf-brand-logo-wrap{
    width:38px;
    height:38px;
    flex-basis:38px;
  }

  .hf-brand-name{
    font-size:.88rem;
  }

  .hf-logout-btn{
    display:none;
  }
}
</style>

<nav class="navbar hf-topbar hf-app-topbar">
  <div class="container-fluid hf-topbar-inner">

    <button id="hf-menu-btn" class="btn hf-menu-trigger" type="button" aria-controls="hf-sidebar" aria-expanded="false" aria-label="Abrir menu">
      <i class="bi bi-list"></i>
    </button>

    <a class="navbar-brand hf-brand-link" href="/dashboard.php" title="<?= htmlspecialchars($brand['name']) ?>">
      <span class="hf-brand-text">
        <span class="hf-brand-name"><?= htmlspecialchars($brand['name']) ?></span>
        <span class="hf-brand-subtitle">Painel de gestão</span>
      </span>
    </a>

    <div class="ms-auto hf-top-actions">
      <div class="dropdown hf-user-menu">
        <button class="btn hf-user-toggle dropdown-toggle" type="button" id="hf-user-menu" data-bs-toggle="dropdown" aria-expanded="false" title="<?= htmlspecialchars($userName) ?>">
          <span class="hf-user-avatar"><?= htmlspecialchars($userInitial) ?></span>
          <span class="hf-user-copy">
            <span class="hf-user-name"><?= htmlspecialchars($userName) ?></span>
            <span class="hf-user-role"><?= htmlspecialchars($userRole) ?></span>
          </span>
        </button>

        <ul class="dropdown-menu dropdown-menu-end hf-user-dropdown" aria-labelledby="hf-user-menu">
          <li>
            <div class="dropdown-header">
              <div class="fw-bold text-body"><?= htmlspecialchars($userName) ?></div>
              <div class="small text-muted"><?= htmlspecialchars($userRole) ?></div>
            </div>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a class="dropdown-item" href="/change_password.php">
              <i class="bi bi-key"></i>
              <span>Trocar senha</span>
            </a>
          </li>
          <li>
            <a class="dropdown-item text-danger" href="/logout.php">
              <i class="bi bi-box-arrow-right"></i>
              <span>Sair</span>
            </a>
          </li>
        </ul>
      </div>

      <a class="btn hf-logout-btn" href="/logout.php" title="Sair" aria-label="Sair">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</nav>

<?php if ($trialBanner): ?>
  <?php
    $trialPlanName = $trialBanner['plan_name'] !== '' ? $trialBanner['plan_name'] : 'seu plano';
    $trialDaysLeft = (int)$trialBanner['days_left'];
    $trialDaysText = $trialDaysLeft === 1 ? '1 dia' : $trialDaysLeft.' dias';
  ?>
  <div class="hf-trial-banner" role="status" aria-live="polite">
    <div class="hf-trial-banner-inner">
      <div class="hf-trial-banner-copy">
        <span class="hf-trial-banner-icon">
          <i class="bi bi-stars" aria-hidden="true"></i>
        </span>
        <span>
          Voc&ecirc; est&aacute; utilizando o per&iacute;odo de teste do plano
          <strong><?= htmlspecialchars($trialPlanName, ENT_QUOTES, 'UTF-8') ?></strong>.
          Restam <strong><?= htmlspecialchars($trialDaysText, ENT_QUOTES, 'UTF-8') ?></strong>.
        </span>
      </div>

      <a class="btn btn-sm btn-outline-primary hf-trial-banner-action" href="<?= htmlspecialchars($trialWhatsappUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
        <i class="bi bi-whatsapp me-1" aria-hidden="true"></i>
        Falar no WhatsApp
      </a>
    </div>
  </div>
<?php endif; ?>

<div id="hf-backdrop" class="hf-backdrop"
     onclick="document.body.classList.remove('sidebar-open')"></div>

<div class="d-flex hf-layout-shell">
