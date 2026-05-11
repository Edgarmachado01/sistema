<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__.'/db.php';
require_once __DIR__.'/_admin_auth.php';

if (isSaasAdminLogged()) {
    header('Location: /admin_dashboard.php');
    exit;
}

if (empty($_SESSION['SAAS_ADMIN_CSRF'])) {
    $_SESSION['SAAS_ADMIN_CSRF'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['SAAS_ADMIN_CSRF'];
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionToken = $_SESSION['SAAS_ADMIN_CSRF'] ?? '';
    $postToken = $_POST['csrf_token'] ?? '';

    if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
        $err = 'Nao foi possivel acessar com os dados informados.';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $senha = (string)($_POST['senha'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
            $err = 'Nao foi possivel acessar com os dados informados.';
        } else {
            try {
                $pdo = db();

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM users
                    WHERE tenant_id IS NULL
                      AND email = :email
                      AND is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($senha, (string)$user['password_hash'])) {
                    $stmtRoles = $pdo->prepare("
                        SELECT r.role_key
                        FROM user_roles ur
                        JOIN roles r ON r.id = ur.role_id
                        WHERE ur.user_id = :user_id
                    ");
                    $stmtRoles->execute([':user_id' => (int)$user['id']]);
                    $roles = array_column($stmtRoles->fetchAll(PDO::FETCH_ASSOC), 'role_key');

                    if (in_array('SYS_ADMIN', $roles, true)) {
                        session_regenerate_id(true);

                        $_SESSION['SAAS_ADMIN_ID'] = (int)$user['id'];
                        $_SESSION['SAAS_ADMIN_AUTHED'] = true;
                        $_SESSION['SAAS_ADMIN_ROLES'] = $roles;
                        $_SESSION['SAAS_ADMIN_NAME'] = trim((string)($user['name'] ?? ''));
                        $_SESSION['SAAS_ADMIN_EMAIL'] = trim((string)($user['email'] ?? ''));

                        header('Location: /admin_dashboard.php');
                        exit;
                    }
                }

                $err = 'Nao foi possivel acessar com os dados informados.';
            } catch (Exception $e) {
                error_log('admin_login.php autenticacao: '.$e->getMessage());
                $err = 'Nao foi possivel acessar com os dados informados.';
            }
        }
    }
}
?><!doctype html>
<html lang="pt-br" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin SaaS - HelpDesk Facil</title>
  <link rel="icon" type="image/png" href="/favicon.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      min-height: 100vh;
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      background:
        radial-gradient(circle at 20% 12%, rgba(59, 130, 246, .18), transparent 28rem),
        radial-gradient(circle at 84% 90%, rgba(20, 184, 166, .16), transparent 24rem),
        linear-gradient(135deg, #0f172a 0%, #172033 54%, #f8fafc 54%, #eef4fb 100%);
      color: #0f172a;
    }

    .hf-admin-login {
      width: 100%;
      max-width: 980px;
      padding: 1.25rem;
    }

    .hf-admin-card {
      display: grid;
      grid-template-columns: 1fr 430px;
      min-height: 560px;
      overflow: hidden;
      border: 1px solid rgba(148, 163, 184, .22);
      border-radius: 1.35rem;
      background: rgba(255, 255, 255, .96);
      box-shadow: 0 26px 70px rgba(15, 23, 42, .24);
      backdrop-filter: blur(10px);
    }

    .hf-admin-brand {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 2rem;
      color: #fff;
      background:
        linear-gradient(160deg, rgba(15, 23, 42, .96), rgba(30, 64, 175, .92)),
        radial-gradient(circle at 20% 20%, rgba(255, 255, 255, .20), transparent 20rem);
    }

    .hf-admin-logo {
      display: inline-flex;
      align-items: center;
      gap: .75rem;
      font-weight: 900;
      letter-spacing: .02em;
    }

    .hf-admin-logo-mark {
      width: 42px;
      height: 42px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: .9rem;
      color: #0f172a;
      background: #fff;
      box-shadow: 0 14px 30px rgba(0, 0, 0, .16);
    }

    .hf-admin-brand h1 {
      margin: 0 0 .65rem;
      font-size: clamp(2rem, 4vw, 3.2rem);
      font-weight: 900;
      letter-spacing: 0;
    }

    .hf-admin-brand p {
      max-width: 34rem;
      margin: 0;
      color: rgba(255, 255, 255, .78);
      font-size: 1rem;
      line-height: 1.7;
    }

    .hf-admin-form {
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 2rem;
    }

    .hf-admin-form h2 {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 900;
    }

    .hf-admin-form .text-muted {
      color: #64748b !important;
    }

    .form-label {
      color: #64748b;
      font-size: .78rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: .04em;
    }

    .hf-field {
      position: relative;
    }

    .hf-field i {
      position: absolute;
      left: .9rem;
      top: 50%;
      transform: translateY(-50%);
      color: #94a3b8;
      pointer-events: none;
    }

    .hf-field .form-control {
      min-height: 46px;
      padding-left: 2.55rem;
      border-radius: .82rem;
      border-color: #dbe3ee;
      background: #f8fafc;
    }

    .hf-field .form-control:focus {
      border-color: rgba(37, 99, 235, .55);
      box-shadow: 0 0 0 .22rem rgba(37, 99, 235, .12);
      background: #fff;
    }

    .hf-admin-btn {
      min-height: 46px;
      border-radius: .82rem;
      font-weight: 850;
      box-shadow: 0 14px 28px rgba(37, 99, 235, .22);
    }

    .hf-admin-alert {
      border: 1px solid rgba(248, 113, 113, .26);
      border-radius: .85rem;
      background: #fef2f2;
      color: #b91c1c;
      font-weight: 650;
    }

    @media (max-width: 860px) {
      body {
        align-items: flex-start;
        background: #eef4fb;
      }

      .hf-admin-card {
        grid-template-columns: 1fr;
      }

      .hf-admin-brand {
        min-height: 260px;
      }
    }
  </style>
</head>
<body>
  <main class="hf-admin-login">
    <section class="hf-admin-card">
      <aside class="hf-admin-brand">
        <div class="hf-admin-logo">
          <span class="hf-admin-logo-mark"><i class="bi bi-command"></i></span>
          <span>HelpDesk Facil</span>
        </div>

        <div>
          <h1>Admin SaaS</h1>
          <p>Controle executivo de empresas, planos, trials e assinaturas da plataforma.</p>
        </div>

        <small class="text-white-50">Acesso restrito ao dono do SaaS</small>
      </aside>

      <section class="hf-admin-form">
        <div class="mb-4">
          <h2>Entrar no Admin</h2>
          <div class="text-muted mt-1">Use sua conta global de administracao.</div>
        </div>

        <?php if ($err): ?>
          <div class="alert hf-admin-alert py-2 mb-3">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

          <div class="mb-3">
            <label class="form-label">E-mail</label>
            <div class="hf-field">
              <i class="bi bi-envelope"></i>
              <input class="form-control" type="email" name="email" autocomplete="username" required>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label">Senha</label>
            <div class="hf-field">
              <i class="bi bi-shield-lock"></i>
              <input class="form-control" type="password" name="senha" autocomplete="current-password" required>
            </div>
          </div>

          <button class="btn btn-primary w-100 hf-admin-btn">
            <i class="bi bi-box-arrow-in-right me-1"></i>Entrar
          </button>
        </form>
      </section>
    </section>
  </main>
</body>
</html>
