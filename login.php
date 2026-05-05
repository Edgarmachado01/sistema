<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/db.php';

// carrega branding se existir brand.php
$brand = ['name'=>'Help Fácil','primary'=>'#0d6efd','mode'=>'light','logo'=>null,'slug'=>null];
if (file_exists(__DIR__.'/brand.php')) {
  require_once __DIR__.'/brand.php';
  $brand = brandFromRequest();
}

$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $slug  = trim($_POST['empresa'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass  = (string)($_POST['senha'] ?? '');

  $tenant = $slug !== '' ? findTenantBySlug($slug) : null;
  $tenantId = $tenant['id'] ?? null;

  $user = findUserByEmail($tenantId, $email);
  if ($user && password_verify($pass, $user['password_hash'])) {
    $_SESSION['USER_ID']   = $user['id'];
    $_SESSION['TENANT_ID'] = $user['tenant_id'];              // null => SYS_ADMIN
    $_SESSION['ROLES']     = getUserRoles($user['id']);
    $_SESSION['TENANT_SLUG'] = $tenant['slug'] ?? null;
    header('Location: /home.php');
    exit;
  } else {
    $err = 'Usuário/senha/empresa inválidos ou inativos.';
  }
}
?><!doctype html>
<html lang="pt-br" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login — Help Fácil</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --hf-login-primary: var(--bs-primary, #0d6efd);
      --hf-login-primary-rgb: var(--bs-primary-rgb, 13, 110, 253);
    }

    body {
      min-height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background:
        radial-gradient(circle at 18% 0%, rgba(var(--hf-login-primary-rgb), .14), transparent 28rem),
        radial-gradient(circle at 86% 86%, rgba(16, 185, 129, .10), transparent 24rem),
        linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
      color: #0f172a;
    }

    .hf-login-shell {
      width: 100%;
      max-width: 460px;
      padding: 1.25rem;
    }

    .hf-login-card {
      overflow: hidden;
      border: 1px solid rgba(148, 163, 184, .24);
      border-radius: 1.15rem;
      background: rgba(255, 255, 255, .94);
      box-shadow: 0 18px 46px rgba(15, 23, 42, .10);
      backdrop-filter: blur(8px);
    }

    .hf-login-head {
      padding: 1.5rem 1.5rem 1rem;
      text-align: center;
      border-bottom: 1px solid rgba(226, 232, 240, .9);
      background: linear-gradient(180deg, rgba(248, 250, 252, .95), rgba(255, 255, 255, .95));
    }

    .hf-logo-wrap {
      min-height: 58px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: .85rem;
    }

    .hf-login-logo {
      max-height: 54px;
      max-width: 220px;
      object-fit: contain;
    }

    .hf-brand-fallback {
      display: inline-flex;
      align-items: center;
      gap: .65rem;
      color: #0f172a;
      font-size: 1.05rem;
      font-weight: 850;
    }

    .brand-dot {
      width: 38px;
      height: 38px;
      display: inline-grid;
      place-items: center;
      border-radius: .9rem;
      color: #fff;
      background: var(--hf-login-primary);
      box-shadow: 0 10px 22px rgba(var(--hf-login-primary-rgb), .22);
    }

    .hf-login-title {
      margin: 0;
      color: #0f172a;
      font-size: 1.18rem;
      font-weight: 850;
    }

    .hf-login-subtitle {
      margin-top: .25rem;
      color: #64748b;
      font-size: .9rem;
    }

    .hf-login-body {
      padding: 1.35rem 1.5rem 1.5rem;
    }

    .hf-login-alert {
      border: 1px solid rgba(248, 113, 113, .26);
      border-radius: .85rem;
      background: #fef2f2;
      color: #b91c1c;
      font-weight: 650;
    }

    .hf-login-body .form-label {
      margin-bottom: .35rem;
      color: #64748b;
      font-size: .76rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .04em;
    }

    .hf-input-icon {
      position: relative;
    }

    .hf-input-icon > i {
      position: absolute;
      left: .85rem;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      pointer-events: none;
    }

    .hf-input-icon .form-control {
      min-height: 44px;
      padding-left: 2.45rem;
      border-radius: .72rem;
      border-color: #dbe3ee;
      background-color: #f8fafc;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, .75);
    }

    .hf-input-icon .form-control:focus {
      border-color: rgba(var(--hf-login-primary-rgb), .55);
      box-shadow: 0 0 0 .2rem rgba(var(--hf-login-primary-rgb), .12);
      background-color: #fff;
    }

    .hf-btn-login {
      min-height: 44px;
      border-radius: .72rem;
      font-weight: 850;
      box-shadow: 0 10px 22px rgba(var(--hf-login-primary-rgb), .18);
    }

    .hf-login-foot {
      padding-top: 1rem;
      text-align: center;
    }

    .hf-login-foot small {
      color: #64748b;
    }

    .hf-tenant-pill {
      display: inline-flex;
      align-items: center;
      min-height: 26px;
      margin-left: .25rem;
      padding: .18rem .55rem;
      border-radius: 999px;
      color: var(--hf-login-primary);
      background: rgba(var(--hf-login-primary-rgb), .10);
      font-weight: 800;
    }

    @media (max-width: 575.98px) {
      body {
        align-items: flex-start;
      }

      .hf-login-shell {
        padding: 1rem;
      }

      .hf-login-head,
      .hf-login-body {
        padding-left: 1rem;
        padding-right: 1rem;
      }
    }
  </style>
  <?php if (function_exists('echoBrandStyle')) echoBrandStyle($brand); ?>
</head>
<body>
  <main class="hf-login-shell">
    <div class="hf-login-card">
      <div class="hf-login-head">
        <div class="hf-logo-wrap">
          <?php if (!empty($brand['logo'])): ?>
            <img src="<?=htmlspecialchars($brand['logo'])?>" alt="Logo" class="hf-login-logo">
          <?php else: ?>
            <div class="hf-brand-fallback">
              <span class="brand-dot"><i class="bi bi-tools"></i></span>
              <span><?=htmlspecialchars($brand['name'])?></span>
            </div>
          <?php endif; ?>
        </div>

        <h1 class="hf-login-title">Acesse sua conta</h1>
        <div class="hf-login-subtitle">Entre para gerenciar ordens, clientes e financeiro.</div>
      </div>

      <div class="hf-login-body">
        <?php if($err): ?>
          <div class="alert hf-login-alert py-2 mb-3">
            <i class="bi bi-exclamation-triangle me-2"></i><?=$err?>
          </div>
        <?php endif; ?>

        <form method="post">
          <div class="mb-3">
            <label class="form-label">Empresa (slug)</label>
            <div class="hf-input-icon">
              <i class="bi bi-building"></i>
              <input name="empresa" class="form-control" placeholder="ex: cjweb (deixe vazio para SYS_ADMIN)">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">E-mail</label>
            <div class="hf-input-icon">
              <i class="bi bi-envelope"></i>
              <input name="email" type="email" class="form-control" required>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label">Senha</label>
            <div class="hf-input-icon">
              <i class="bi bi-lock"></i>
              <input name="senha" type="password" class="form-control" required>
            </div>
          </div>

          <button class="btn btn-primary w-100 hf-btn-login">
            <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
          </button>
        </form>

        <div class="hf-login-foot">
          <small>
            Tema detectado para
            <span class="hf-tenant-pill"><?=htmlspecialchars($brand['slug'] ?? 'padrão')?></span>
          </small>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
