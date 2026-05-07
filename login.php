<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__.'/db.php';

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $sessionToken = $_SESSION['csrf_token'] ?? '';
  $postToken    = $_POST['csrf_token'] ?? '';

  if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
    $err = 'Não foi possível acessar com os dados informados.';
  } else {
    $slug  = trim($_POST['empresa'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = (string)($_POST['senha'] ?? '');

    if ($slug === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '') {
      $err = 'Não foi possível acessar com os dados informados.';
    } else {
      try {
        $tenant = findTenantBySlug($slug);

        if (!$tenant || empty($tenant['id'])) {
          $err = 'Não foi possível acessar com os dados informados.';
        } else {
          $tenantId = (int)$tenant['id'];
          $user = findUserByEmail($tenantId, $email);

          $userTenantId = isset($user['tenant_id']) ? (int)$user['tenant_id'] : 0;

          if (
            $user &&
            $userTenantId > 0 &&
            $userTenantId === $tenantId &&
            password_verify($pass, $user['password_hash'])
          ) {
            session_regenerate_id(true);

            $_SESSION['USER_ID']     = $user['id'];
            $_SESSION['TENANT_ID']   = $user['tenant_id'];
            $_SESSION['ROLES']       = getUserRoles($user['id']);
            $_SESSION['TENANT_SLUG'] = $tenant['slug'] ?? null;

            header('Location: /home.php');
            exit;
          }

          $err = 'Não foi possível acessar com os dados informados.';
        }
      } catch (Exception $e) {
        error_log('login.php autenticação: '.$e->getMessage());
        $err = 'Não foi possível acessar com os dados informados.';
      }
    }
  }
}
?><!doctype html>
<html lang="pt-br" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login — HelpDesk Fácil</title>
  <link rel="icon" type="image/png" href="/favicon.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --hf-login-primary: #0d6efd;
      --hf-login-primary-rgb: 13, 110, 253;
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
      min-height: 74px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: .85rem;
    }

    .hf-login-logo {
      max-height: 72px;
      max-width: 260px;
      object-fit: contain;
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
</head>
<body>
  <main class="hf-login-shell">
    <div class="hf-login-card">
      <div class="hf-login-head">
        <div class="hf-logo-wrap">
          <img src="/logo.png" alt="HelpDesk Fácil" class="hf-login-logo">
        </div>

       
        <div class="hf-login-subtitle">Acesse sua conta para gerenciar atendimentos, clientes e financeiro.</div>
      </div>

      <div class="hf-login-body">
        <?php if ($err): ?>
          <div class="alert hf-login-alert py-2 mb-3">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

          <div class="mb-3">
            <label class="form-label">Código da empresa</label>
            <div class="hf-input-icon">
              <i class="bi bi-building"></i>
              <input name="empresa" class="form-control" placeholder="Informe o código da sua empresa" autocomplete="organization" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">E-mail</label>
            <div class="hf-input-icon">
              <i class="bi bi-envelope"></i>
              <input name="email" type="email" class="form-control" autocomplete="username" required>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label">Senha</label>
            <div class="hf-input-icon">
              <i class="bi bi-lock"></i>
              <input name="senha" type="password" class="form-control" autocomplete="current-password" required>
            </div>
          </div>

          <button class="btn btn-primary w-100 hf-btn-login">
            <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
          </button>
        </form>

        <div class="hf-login-foot">
          <small>Acesso seguro ao painel</small>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
