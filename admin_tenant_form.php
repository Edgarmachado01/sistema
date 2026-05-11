<?php
require_once __DIR__.'/_admin_auth.php';
requireSaasAdmin();
require_once __DIR__.'/db.php';

$pdo = db();
$tenantId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$tenant = null;
$subscription = null;
$plans = [];
$flashError = $_SESSION['SAAS_ADMIN_FLASH_ERROR'] ?? '';
unset($_SESSION['SAAS_ADMIN_FLASH_ERROR']);

if (empty($_SESSION['SAAS_ADMIN_TENANT_CSRF'])) {
    $_SESSION['SAAS_ADMIN_TENANT_CSRF'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['SAAS_ADMIN_TENANT_CSRF'];

if (!function_exists('hfAdminTenantDateInput')) {
    function hfAdminTenantDateInput($value)
    {
        if (!$value) {
            return '';
        }

        try {
            return (new DateTime((string)$value))->format('Y-m-d');
        } catch (Exception $e) {
            return '';
        }
    }
}

try {
    if ($tenantId <= 0) {
        throw new RuntimeException('Tenant invalido.');
    }

    $stmtTenant = $pdo->prepare("
        SELECT id, name, slug, is_active
        FROM tenants
        WHERE id = :id
        LIMIT 1
    ");
    $stmtTenant->execute([':id' => $tenantId]);
    $tenant = $stmtTenant->fetch(PDO::FETCH_ASSOC);

    if (!$tenant) {
        throw new RuntimeException('Tenant nao encontrado: '.$tenantId);
    }

    $stmtPlans = $pdo->prepare("
        SELECT id, code, name, monthly_price_cents
        FROM plans
        WHERE is_active = 1
        ORDER BY monthly_price_cents ASC, name ASC
    ");
    $stmtPlans->execute();
    $plans = $stmtPlans->fetchAll(PDO::FETCH_ASSOC);

    $stmtSubscription = $pdo->prepare("
        SELECT
            ts.id,
            ts.plan_id,
            ts.status,
            ts.trial_end_at,
            ts.current_period_end,
            p.name AS plan_name
        FROM tenant_subscriptions ts
        LEFT JOIN plans p ON p.id = ts.plan_id
        WHERE ts.tenant_id = :tenant_id
        ORDER BY ts.id DESC
        LIMIT 1
    ");
    $stmtSubscription->execute([':tenant_id' => $tenantId]);
    $subscription = $stmtSubscription->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Exception $e) {
    error_log('admin_tenant_form.php: '.$e->getMessage());
    $_SESSION['SAAS_ADMIN_FLASH_ERROR'] = 'Nao foi possivel abrir esta empresa.';
    header('Location: /admin_tenants.php');
    exit;
}

require_once __DIR__.'/_admin_layout_start.php';
?>

<style>
  .hf-admin-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
  }

  .hf-admin-section-title {
    display: flex;
    align-items: center;
    gap: .6rem;
    margin-bottom: 1rem;
  }

  .hf-admin-section-title span {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: .85rem;
    color: #fff;
    background: linear-gradient(135deg, #2563eb, #14b8a6);
  }

  .hf-admin-section-title h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 900;
  }

  .hf-admin-section-title small {
    color: #64748b;
  }

  .hf-cortesia-hint {
    display: none;
    margin-top: 1rem;
    border: 1px solid rgba(13, 110, 253, .18);
    border-radius: .9rem;
    background: linear-gradient(135deg, rgba(13, 110, 253, .08), rgba(20, 184, 166, .10));
    color: #075985;
    font-weight: 650;
  }

  .hf-cortesia-hint.is-visible {
    display: block;
  }

  @media (max-width: 760px) {
    .hf-admin-form-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="d-flex flex-column gap-4">
  <section class="hf-admin-card p-4">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
      <div>
        <div class="text-uppercase fw-bold text-primary small mb-2">Empresa</div>
        <h2 class="h3 mb-1" style="font-weight: 950;"><?= htmlspecialchars((string)$tenant['name'], ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-muted mb-0">Edite dados administrativos, plano e status comercial.</p>
      </div>
      <a class="btn btn-outline-primary" href="/admin_tenants.php">
        <i class="bi bi-arrow-left me-1"></i>Voltar
      </a>
    </div>
  </section>

  <?php if ($flashError): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-0">
      <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <form method="post" action="/admin_tenant_save.php" class="d-flex flex-column gap-4">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="tenant_id" value="<?= (int)$tenant['id'] ?>">

    <section class="hf-admin-card p-4">
      <div class="hf-admin-section-title">
        <span><i class="bi bi-buildings"></i></span>
        <div>
          <h2>Dados da empresa</h2>
          <small>Identidade e acesso estrutural do tenant.</small>
        </div>
      </div>

      <div class="hf-admin-form-grid">
        <div>
          <label class="form-label text-muted fw-bold small">Nome da empresa</label>
          <input class="form-control" name="name" value="<?= htmlspecialchars((string)$tenant['name'], ENT_QUOTES, 'UTF-8') ?>" maxlength="160" required>
        </div>
        <div>
          <label class="form-label text-muted fw-bold small">Slug / codigo</label>
          <input class="form-control" name="slug" value="<?= htmlspecialchars((string)$tenant['slug'], ENT_QUOTES, 'UTF-8') ?>" maxlength="40" required>
        </div>
        <div>
          <label class="form-label text-muted fw-bold small">Status tenant</label>
          <select class="form-select" name="tenant_active">
            <option value="1" <?= (int)$tenant['is_active'] === 1 ? 'selected' : '' ?>>Ativo</option>
            <option value="0" <?= (int)$tenant['is_active'] === 0 ? 'selected' : '' ?>>Inativo</option>
          </select>
        </div>
      </div>
    </section>

    <section class="hf-admin-card p-4">
      <div class="hf-admin-section-title">
        <span><i class="bi bi-credit-card-2-front"></i></span>
        <div>
          <h2>Assinatura comercial</h2>
          <small>Gerenciamento administrativo, sem bloqueio automatico de acesso ainda.</small>
        </div>
      </div>

      <div class="hf-admin-form-grid">
        <div>
          <label class="form-label text-muted fw-bold small">Plano</label>
          <select class="form-select" name="plan_id" id="hfAdminPlanSelect" required>
            <option value="">Selecione</option>
            <?php foreach ($plans as $plan): ?>
              <option value="<?= (int)$plan['id'] ?>" data-plan-code="<?= htmlspecialchars((string)$plan['code'], ENT_QUOTES, 'UTF-8') ?>" <?= (int)($subscription['plan_id'] ?? 0) === (int)$plan['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars((string)$plan['name'], ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label text-muted fw-bold small">Status assinatura</label>
          <?php $currentStatus = (string)($subscription['status'] ?? 'trial'); ?>
          <select class="form-select" name="status" id="hfAdminStatusSelect" required>
            <?php foreach (['trial' => 'Trial', 'ativo' => 'Ativa', 'vencido' => 'Vencida', 'bloqueado' => 'Bloqueada', 'cancelado' => 'Cancelada'] as $value => $label): ?>
              <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $currentStatus === $value ? 'selected' : '' ?>>
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label text-muted fw-bold small">Fim do trial</label>
          <input class="form-control" type="date" name="trial_end_at" value="<?= htmlspecialchars(hfAdminTenantDateInput($subscription['trial_end_at'] ?? null), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
          <label class="form-label text-muted fw-bold small">Fim do periodo atual</label>
          <input class="form-control" type="date" name="current_period_end" value="<?= htmlspecialchars(hfAdminTenantDateInput($subscription['current_period_end'] ?? null), ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>

      <div class="hf-cortesia-hint p-3" id="hfCortesiaHint">
        <i class="bi bi-stars me-1"></i>
        Plano Cortesia deve ficar como assinatura ativa. Limites 0 continuam significando uso ilimitado.
      </div>
    </section>

    <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">
      <a class="btn btn-outline-secondary" href="/admin_tenants.php">Cancelar</a>
      <button class="btn btn-primary">
        <i class="bi bi-check2-circle me-1"></i>Salvar alteracoes
      </button>
    </div>
  </form>
</div>

<script>
  (function () {
    var planSelect = document.getElementById('hfAdminPlanSelect');
    var statusSelect = document.getElementById('hfAdminStatusSelect');
    var hint = document.getElementById('hfCortesiaHint');

    if (!planSelect || !statusSelect || !hint) {
      return;
    }

    function refreshCortesiaState() {
      var selected = planSelect.options[planSelect.selectedIndex];
      var isCortesia = selected && selected.getAttribute('data-plan-code') === 'cortesia';

      hint.classList.toggle('is-visible', !!isCortesia);

      if (isCortesia && statusSelect.value === 'trial') {
        statusSelect.value = 'ativo';
      }
    }

    planSelect.addEventListener('change', refreshCortesiaState);
    refreshCortesiaState();
  })();
</script>

<?php require_once __DIR__.'/_admin_layout_end.php'; ?>
