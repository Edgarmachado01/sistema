<?php
require_once __DIR__.'/auth.php';
requireAdmin();
require_once __DIR__.'/db.php';

$msg = $err = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $email = trim($_POST['email'] ?? '');
  $temp  = trim($_POST['nova']  ?? ''); // ex: gerar manualmente uma provisória
  if ($email==='' || $temp==='') { $err = 'Preencha e-mail e nova senha temporária.'; }
  else {
    // restringe ao mesmo tenant, exceto SYS_ADMIN global
    $tenant = tenantId();
    if ($tenant) {
      $st = db()->prepare("SELECT id FROM users WHERE email=? AND tenant_id <=> ?");
      $st->execute([$email, $tenant]);
    } else {
      $st = db()->prepare("SELECT id FROM users WHERE email=?");
      $st->execute([$email]);
    }
    $u = $st->fetch();
    if (!$u) { $err = 'Usuário não encontrado neste escopo.'; }
    else {
      $hash = password_hash($temp, PASSWORD_DEFAULT);
      $up = db()->prepare("UPDATE users SET password_hash=?, is_active=1 WHERE id=?");
      $up->execute([$hash, $u['id']]);
      $msg = "Senha temporária definida para {$email}.";
      // (opcional) enviar por e-mail essa temporária
    }
  }
}

include __DIR__.'/_layout_start.php';
include __DIR__.'/_sidebar.php';
?>

<main class="hf-content hf-admin-reset-page">
  <div class="container-fluid py-4 hf-admin-reset-wrap">

    <div class="hf-admin-reset-hero d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="hf-page-kicker">Administração</div>
        <h4 class="mb-0">
          <i class="bi bi-shield-lock-fill me-2"></i>Resetar senha
        </h4>
        <div class="hf-page-subtitle">Defina uma senha temporária para um usuário do seu escopo.</div>
      </div>
    </div>

    <?php if($msg): ?>
      <div class="alert alert-success hf-reset-alert">
        <i class="bi bi-check-circle me-2"></i><?=$msg?>
      </div>
    <?php endif; ?>

    <?php if($err): ?>
      <div class="alert alert-danger hf-reset-alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?=$err?>
      </div>
    <?php endif; ?>

    <div class="d-flex justify-content-center mt-3">
      <div class="hf-reset-card w-100">
        <div class="hf-reset-card-head">
          <div class="hf-reset-icon">
            <i class="bi bi-key"></i>
          </div>
          <div>
            <h5>Senha temporária</h5>
            <p>Informe o e-mail do usuário e a nova senha provisória.</p>
          </div>
        </div>

        <form method="post" class="hf-reset-form">
          <div class="mb-3">
            <label class="form-label">E-mail do usuário</label>
            <div class="hf-input-icon">
              <i class="bi bi-envelope"></i>
              <input type="email" name="email" class="form-control" required>
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label">Senha temporária</label>
            <div class="hf-input-icon">
              <i class="bi bi-lock"></i>
              <input type="text" name="nova" class="form-control" placeholder="Ex: CJweb@2025!" required>
            </div>
          </div>

          <div class="d-flex justify-content-end">
            <button class="btn btn-warning hf-btn-reset">
              <i class="bi bi-arrow-repeat me-1"></i>Resetar senha
            </button>
          </div>
        </form>
      </div>
    </div>

  </div>
</main>

<style>
.hf-admin-reset-page {
  min-height: calc(100vh - var(--topbar-h));
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .10), transparent 28rem),
    linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
}

.hf-admin-reset-wrap {
  max-width: 1480px;
}

.hf-admin-reset-hero {
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

.hf-reset-alert {
  max-width: 720px;
  margin-left: auto;
  margin-right: auto;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: .9rem;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
}

.hf-reset-card {
  max-width: 560px;
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

.hf-reset-card-head {
  display: flex;
  align-items: flex-start;
  gap: .85rem;
  padding: 1.15rem;
  border-bottom: 1px solid rgba(226, 232, 240, .9);
  background: linear-gradient(180deg, rgba(248, 250, 252, .95), rgba(255, 255, 255, .95));
}

.hf-reset-card-head h5 {
  margin: 0;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 800;
}

.hf-reset-card-head p {
  margin: .18rem 0 0;
  color: #64748b;
  font-size: .86rem;
}

.hf-reset-icon {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: grid;
  place-items: center;
  border-radius: .85rem;
  color: #b45309;
  background: rgba(245, 158, 11, .14);
  font-size: 1.15rem;
}

.hf-reset-form {
  padding: 1.15rem;
}

.hf-reset-form .form-label {
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

.hf-btn-reset {
  min-height: 40px;
  border-radius: .72rem;
  color: #111827;
  font-weight: 800;
  padding-left: .95rem;
  padding-right: .95rem;
  box-shadow: 0 8px 18px rgba(245, 158, 11, .18);
}

@media (max-width: 767.98px) {
  .hf-admin-reset-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .hf-admin-reset-hero {
    align-items: flex-start !important;
  }

  .hf-reset-card-head,
  .hf-reset-form {
    padding: 1rem;
  }

  .hf-btn-reset {
    width: 100%;
  }
}

[data-bs-theme="dark"] .hf-admin-reset-page {
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .hf-reset-card {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-reset-card-head {
  background: linear-gradient(180deg, rgba(30, 41, 59, .95), rgba(17, 24, 39, .95));
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-reset-card-head h5 {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-input-icon .form-control {
  background-color: rgba(15, 23, 42, .9);
  border-color: rgba(148, 163, 184, .24);
}
</style>

<?php include __DIR__.'/_layout_end.php'; ?>
