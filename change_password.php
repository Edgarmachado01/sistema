<?php
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

$pdo = db();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$msg = '';
$err = '';

if (!empty($_SESSION['HF_PASSWORD_FLASH_OK'])) {
    $msg = $_SESSION['HF_PASSWORD_FLASH_OK'];
    unset($_SESSION['HF_PASSWORD_FLASH_OK']);
}

if (!empty($_SESSION['HF_PASSWORD_FLASH_ERROR'])) {
    $err = $_SESSION['HF_PASSWORD_FLASH_ERROR'];
    unset($_SESSION['HF_PASSWORD_FLASH_ERROR']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectTo = 'change_password.php?m=senha';

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postToken    = $_POST['csrf_token'] ?? '';

    if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
        $_SESSION['HF_PASSWORD_FLASH_ERROR'] = 'Sessão expirada. Recarregue a página e tente novamente.';
        header('Location: '.$redirectTo);
        exit;
    }

    $curr = (string)($_POST['senha_atual'] ?? '');
    $new1 = (string)($_POST['nova_senha'] ?? '');
    $new2 = (string)($_POST['confirmar'] ?? '');

    if (trim($curr) === '' || trim($new1) === '' || trim($new2) === '') {
        $_SESSION['HF_PASSWORD_FLASH_ERROR'] = 'Preencha todos os campos de senha.';
        header('Location: '.$redirectTo);
        exit;
    }

    if (strlen($new1) < 8) {
        $_SESSION['HF_PASSWORD_FLASH_ERROR'] = 'A nova senha deve ter no mínimo 8 caracteres.';
        header('Location: '.$redirectTo);
        exit;
    }

    if ($new1 !== $new2) {
        $_SESSION['HF_PASSWORD_FLASH_ERROR'] = 'Confirmação diferente da nova senha.';
        header('Location: '.$redirectTo);
        exit;
    }

    try {
        $st = $pdo->prepare("SELECT id, password_hash FROM users WHERE id = ? AND is_active = 1");
        $st->execute([$_SESSION['USER_ID']]);
        $u = $st->fetch(PDO::FETCH_ASSOC);

        if (!$u || !password_verify($curr, $u['password_hash'])) {
            $_SESSION['HF_PASSWORD_FLASH_ERROR'] = 'Senha atual inválida.';
            header('Location: '.$redirectTo);
            exit;
        }

        if (password_verify($new1, $u['password_hash'])) {
            $_SESSION['HF_PASSWORD_FLASH_ERROR'] = 'A nova senha deve ser diferente da senha atual.';
            header('Location: '.$redirectTo);
            exit;
        }

        $hash = password_hash($new1, PASSWORD_DEFAULT);
        $up = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $up->execute([$hash, $u['id']]);

        session_regenerate_id(true);

        $_SESSION['HF_PASSWORD_FLASH_OK'] = 'Senha atualizada com sucesso.';
        header('Location: '.$redirectTo);
        exit;

    } catch (Exception $e) {
        error_log('change_password.php trocar senha: '.$e->getMessage());

        $_SESSION['HF_PASSWORD_FLASH_ERROR'] = 'Erro ao atualizar senha. Tente novamente.';
        header('Location: '.$redirectTo);
        exit;
    }
}

// Layout padrão
include __DIR__.'/_layout_start.php';
include __DIR__.'/_sidebar.php';
?>

<main class="hf-content hf-password-page">
  <div class="container-fluid py-4 hf-password-wrap">

    <div class="hf-password-hero d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="hf-page-kicker">Conta</div>
        <h4 class="mb-0">
          <i class="bi bi-key-fill me-2"></i>Trocar senha
        </h4>
        <div class="hf-page-subtitle">Atualize sua senha de acesso ao sistema.</div>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-success alert-dismissible fade show hf-password-alert" role="alert">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
      </div>
    <?php endif; ?>

    <?php if ($err): ?>
      <div class="alert alert-danger alert-dismissible fade show hf-password-alert" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($err) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
      </div>
    <?php endif; ?>

    <div class="d-flex justify-content-center mt-3">
      <div class="hf-password-card w-100">
        <div class="hf-password-card-head">
          <div class="hf-password-icon">
            <i class="bi bi-shield-lock"></i>
          </div>
          <div>
            <h5>Segurança da conta</h5>
            <p>Informe a senha atual e defina uma nova credencial.</p>
          </div>
        </div>

        <form method="post" autocomplete="off" class="hf-password-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

          <div class="mb-3">
            <label class="form-label">Senha atual</label>
            <div class="hf-input-icon">
              <i class="bi bi-lock"></i>
              <input type="password" name="senha_atual" class="form-control" required>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Nova senha</label>
            <div class="hf-input-icon">
              <i class="bi bi-key"></i>
              <input type="password" name="nova_senha" class="form-control" required>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label">Confirmar nova senha</label>
            <div class="hf-input-icon">
              <i class="bi bi-check2-circle"></i>
              <input type="password" name="confirmar" class="form-control" required>
            </div>
          </div>

          <div class="d-flex justify-content-end">
            <button class="btn btn-primary hf-btn-save-password">
              <i class="bi bi-key me-1"></i> Salvar nova senha
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</main>

<style>
.hf-password-page {
  min-height: calc(100vh - var(--topbar-h));
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .10), transparent 28rem),
    linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
}

.hf-password-wrap {
  max-width: 1480px;
}

.hf-password-hero {
  gap: 1rem;
  padding: .25rem .1rem .55rem;
}

.hf-page-kicker {
  font-size: .74rem;
  font-weight: 800;
  color: rgba(var(--bs-primary-rgb), .88);
  text-transform: uppercase;
  letter-spacing: .08em;
  margin-bottom: .12rem;
}

.hf-page-subtitle {
  margin-top: .2rem;
  color: #64748b;
  font-size: .9rem;
}

.hf-password-alert {
  max-width: 720px;
  margin-left: auto;
  margin-right: auto;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: .9rem;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
}

.hf-password-card {
  max-width: 560px;
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

.hf-password-card-head {
  display: flex;
  align-items: flex-start;
  gap: .85rem;
  padding: 1.15rem;
  border-bottom: 1px solid rgba(226, 232, 240, .9);
  background: linear-gradient(180deg, rgba(248, 250, 252, .95), rgba(255, 255, 255, .95));
}

.hf-password-card-head h5 {
  margin: 0;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 800;
}

.hf-password-card-head p {
  margin: .18rem 0 0;
  color: #64748b;
  font-size: .86rem;
}

.hf-password-icon {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: grid;
  place-items: center;
  border-radius: .85rem;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .10);
  font-size: 1.15rem;
}

.hf-password-form {
  padding: 1.15rem;
}

.hf-password-form .form-label {
  margin-bottom: .35rem;
  font-size: .76rem;
  font-weight: 800;
  color: #64748b;
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
  border-color: rgba(var(--bs-primary-rgb), .55);
  box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
  background-color: #fff;
}

.hf-btn-save-password {
  min-height: 40px;
  border-radius: .72rem;
  font-weight: 800;
  padding-left: .95rem;
  padding-right: .95rem;
  box-shadow: 0 8px 18px rgba(var(--bs-primary-rgb), .16);
}

@media (max-width: 767.98px) {
  .hf-password-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .hf-password-hero {
    align-items: flex-start !important;
  }

  .hf-password-card-head,
  .hf-password-form {
    padding: 1rem;
  }

  .hf-btn-save-password {
    width: 100%;
  }
}

[data-bs-theme="dark"] .hf-password-page {
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .hf-password-card {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-password-card-head {
  background: linear-gradient(180deg, rgba(30, 41, 59, .95), rgba(17, 24, 39, .95));
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-password-card-head h5 {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-input-icon .form-control {
  background-color: rgba(15, 23, 42, .9);
  border-color: rgba(148, 163, 184, .24);
}
</style>

<?php include __DIR__.'/_layout_end.php'; ?>
