<?php
require_once __DIR__.'/_admin_auth.php';
requireSaasAdmin();
require_once __DIR__.'/db.php';

$pdo = db();

if (!function_exists('hfAdminTenantsMoney')) {
    function hfAdminTenantsMoney($cents)
    {
        return 'R$ '.number_format(((float)$cents) / 100, 2, ',', '.');
    }
}

if (!function_exists('hfAdminTenantsDate')) {
    function hfAdminTenantsDate($value)
    {
        if (!$value) {
            return '-';
        }

        try {
            return (new DateTime((string)$value))->format('d/m/Y');
        } catch (Exception $e) {
            return '-';
        }
    }
}

if (!function_exists('hfAdminTenantsStatusLabel')) {
    function hfAdminTenantsStatusLabel($status)
    {
        $status = trim((string)$status);
        $labels = [
            'trial' => 'Trial',
            'ativo' => 'Ativa',
            'vencido' => 'Vencida',
            'bloqueado' => 'Bloqueada',
            'cancelado' => 'Cancelada',
        ];

        return $labels[$status] ?? ($status !== '' ? ucfirst($status) : 'Sem assinatura');
    }
}

if (!function_exists('hfAdminTenantsScalar')) {
    function hfAdminTenantsScalar(PDO $pdo, $sql, array $params = [])
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}

if (!function_exists('hfAdminTenantsRows')) {
    function hfAdminTenantsRows(PDO $pdo, $sql, array $params = [])
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$plan = trim((string)($_GET['plan'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$allowedStatuses = ['', 'trial', 'ativo', 'vencido', 'bloqueado', 'cancelado'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$latestSubscriptionSql = "
    SELECT ts.*
    FROM tenant_subscriptions ts
    JOIN (
        SELECT tenant_id, MAX(id) AS id
        FROM tenant_subscriptions
        GROUP BY tenant_id
    ) latest ON latest.id = ts.id
";

$plans = [];
$metrics = [
    'tenants_total' => 0,
    'trials_active' => 0,
    'blocked' => 0,
    'potential_mrr_cents' => 0,
];
$tenants = [];
$flashSuccess = $_SESSION['SAAS_ADMIN_FLASH_SUCCESS'] ?? '';
$flashError = $_SESSION['SAAS_ADMIN_FLASH_ERROR'] ?? '';
unset($_SESSION['SAAS_ADMIN_FLASH_SUCCESS'], $_SESSION['SAAS_ADMIN_FLASH_ERROR']);

try {
    $plans = hfAdminTenantsRows($pdo, "
        SELECT id, code, name
        FROM plans
        WHERE is_active = 1
        ORDER BY monthly_price_cents ASC, name ASC
    ");

    $metrics['tenants_total'] = (int)hfAdminTenantsScalar($pdo, "SELECT COUNT(*) FROM tenants");
    $metrics['trials_active'] = (int)hfAdminTenantsScalar($pdo, "
        SELECT COUNT(*)
        FROM ({$latestSubscriptionSql}) ts
        WHERE ts.status = 'trial'
          AND (ts.trial_end_at IS NULL OR ts.trial_end_at >= NOW())
    ");
    $metrics['blocked'] = (int)hfAdminTenantsScalar($pdo, "
        SELECT COUNT(*)
        FROM ({$latestSubscriptionSql}) ts
        WHERE ts.status = 'bloqueado'
    ");
    $metrics['potential_mrr_cents'] = (int)hfAdminTenantsScalar($pdo, "
        SELECT COALESCE(SUM(COALESCE(p.monthly_price_cents, 0)), 0)
        FROM ({$latestSubscriptionSql}) ts
        JOIN plans p ON p.id = ts.plan_id
        WHERE ts.status IN ('trial', 'ativo')
    ");

    $where = [];
    $params = [];

    if ($q !== '') {
        $where[] = "(t.name LIKE :q OR t.slug LIKE :q)";
        $params[':q'] = '%'.$q.'%';
    }

    if ($plan !== '') {
        $where[] = "p.code = :plan";
        $params[':plan'] = $plan;
    }

    if ($status !== '') {
        $where[] = "ts.status = :status";
        $params[':status'] = $status;
    }

    $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

    $tenants = hfAdminTenantsRows($pdo, "
        SELECT
            t.id,
            t.name AS tenant_name,
            t.slug,
            t.is_active,
            p.name AS plan_name,
            p.code AS plan_code,
            ts.status,
            ts.trial_end_at,
            (
                SELECT COUNT(*)
                FROM users u
                WHERE u.tenant_id = t.id
                  AND u.is_active = 1
            ) AS active_users
        FROM tenants t
        LEFT JOIN ({$latestSubscriptionSql}) ts ON ts.tenant_id = t.id
        LEFT JOIN plans p ON p.id = ts.plan_id
        {$whereSql}
        ORDER BY t.id DESC
        LIMIT 200
    ", $params);
} catch (Exception $e) {
    error_log('admin_tenants.php: '.$e->getMessage());
    $flashError = 'Nao foi possivel carregar as empresas agora.';
}

require_once __DIR__.'/_admin_layout_start.php';
?>

<style>
  .hf-admin-filter-grid {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 190px 190px auto;
    gap: .75rem;
    align-items: end;
  }

  .hf-tenant-name {
    display: flex;
    align-items: center;
    gap: .75rem;
  }

  .hf-tenant-avatar {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 38px;
    border-radius: .85rem;
    color: #fff;
    background: linear-gradient(135deg, #2563eb, #14b8a6);
    font-weight: 900;
  }

  .hf-plan-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .34rem .62rem;
    border-radius: 999px;
    background: #f8fafc;
    color: #334155;
    font-size: .78rem;
    font-weight: 850;
    white-space: nowrap;
  }

  .hf-plan-badge.is-cortesia {
    background: linear-gradient(135deg, rgba(13, 110, 253, .12), rgba(20, 184, 166, .16));
    color: #075985;
    border: 1px solid rgba(13, 110, 253, .18);
  }

  @media (max-width: 920px) {
    .hf-admin-filter-grid {
      grid-template-columns: 1fr 1fr;
    }
  }

  @media (max-width: 560px) {
    .hf-admin-filter-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="d-flex flex-column gap-4">
  <section class="hf-admin-card p-4">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
      <div>
        <div class="text-uppercase fw-bold text-primary small mb-2">Gestao comercial</div>
        <h2 class="h3 mb-1" style="font-weight: 950;">Empresas</h2>
        <p class="text-muted mb-0">Acompanhe tenants, planos, trials e status administrativo.</p>
      </div>
      <a class="btn btn-outline-primary" href="/admin_dashboard.php">
        <i class="bi bi-speedometer2 me-1"></i>Dashboard
      </a>
    </div>
  </section>

  <?php if ($flashSuccess): ?>
    <div class="alert alert-success border-0 shadow-sm mb-0">
      <i class="bi bi-check2-circle me-1"></i><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($flashError): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-0">
      <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <section class="hf-admin-kpis">
    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-buildings text-primary"></i>Total empresas</small>
      <strong><?= number_format((int)$metrics['tenants_total'], 0, ',', '.') ?></strong>
      <span>Tenants cadastrados</span>
    </article>
    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-hourglass-split text-primary"></i>Trials ativos</small>
      <strong><?= number_format((int)$metrics['trials_active'], 0, ',', '.') ?></strong>
      <span>Empresas em teste</span>
    </article>
    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-lock text-danger"></i>Bloqueadas</small>
      <strong><?= number_format((int)$metrics['blocked'], 0, ',', '.') ?></strong>
      <span>Status comercial bloqueado</span>
    </article>
    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-graph-up-arrow text-success"></i>MRR potencial</small>
      <strong><?= htmlspecialchars(hfAdminTenantsMoney($metrics['potential_mrr_cents']), ENT_QUOTES, 'UTF-8') ?></strong>
      <span>Trials + ativas</span>
    </article>
  </section>

  <section class="hf-admin-card p-4">
    <form method="get" class="hf-admin-filter-grid">
      <div>
        <label class="form-label text-muted fw-bold small">Busca</label>
        <input class="form-control" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Empresa ou codigo">
      </div>
      <div>
        <label class="form-label text-muted fw-bold small">Plano</label>
        <select class="form-select" name="plan">
          <option value="">Todos</option>
          <?php foreach ($plans as $item): ?>
            <option value="<?= htmlspecialchars((string)$item['code'], ENT_QUOTES, 'UTF-8') ?>" <?= $plan === (string)$item['code'] ? 'selected' : '' ?>>
              <?= htmlspecialchars((string)$item['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label text-muted fw-bold small">Status</label>
        <select class="form-select" name="status">
          <option value="">Todos</option>
          <?php foreach (['trial', 'ativo', 'vencido', 'bloqueado', 'cancelado'] as $itemStatus): ?>
            <option value="<?= htmlspecialchars($itemStatus, ENT_QUOTES, 'UTF-8') ?>" <?= $status === $itemStatus ? 'selected' : '' ?>>
              <?= htmlspecialchars(hfAdminTenantsStatusLabel($itemStatus), ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="d-grid">
        <button class="btn btn-primary">
          <i class="bi bi-search me-1"></i>Filtrar
        </button>
      </div>
    </form>
  </section>

  <section class="hf-admin-card">
    <div class="table-responsive">
      <table class="table hf-admin-table">
        <thead>
          <tr>
            <th>Empresa</th>
            <th>Plano</th>
            <th>Status assinatura</th>
            <th>Trial termina</th>
            <th class="text-end">Usuarios ativos</th>
            <th>Status tenant</th>
            <th class="text-end">Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$tenants): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Nenhuma empresa encontrada.</td>
            </tr>
          <?php endif; ?>

          <?php foreach ($tenants as $tenant): ?>
            <?php
              $tenantName = trim((string)($tenant['tenant_name'] ?? ''));
              $initial = strtoupper(substr($tenantName !== '' ? $tenantName : 'E', 0, 1));
              $subscriptionStatus = trim((string)($tenant['status'] ?? ''));
              $statusClass = $subscriptionStatus !== '' ? 'is-'.$subscriptionStatus : '';
              $isActive = (int)($tenant['is_active'] ?? 0) === 1;
              $planCode = trim((string)($tenant['plan_code'] ?? ''));
              $isCortesia = $planCode === 'cortesia';
            ?>
            <tr>
              <td>
                <div class="hf-tenant-name">
                  <span class="hf-tenant-avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
                  <div>
                    <div class="fw-bold"><?= htmlspecialchars($tenantName !== '' ? $tenantName : 'Empresa #'.(int)$tenant['id'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-muted small"><?= htmlspecialchars((string)($tenant['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="hf-plan-badge <?= $isCortesia ? 'is-cortesia' : '' ?>">
                  <?php if ($isCortesia): ?><i class="bi bi-stars"></i><?php endif; ?>
                  <?= htmlspecialchars((string)($tenant['plan_name'] ?? 'Sem plano'), ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
              <td>
                <span class="hf-status-pill <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars(hfAdminTenantsStatusLabel($subscriptionStatus), ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
              <td><?= htmlspecialchars(hfAdminTenantsDate($tenant['trial_end_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
              <td class="text-end fw-bold"><?= number_format((int)($tenant['active_users'] ?? 0), 0, ',', '.') ?></td>
              <td>
                <span class="hf-status-pill <?= $isActive ? 'is-ativo' : 'is-cancelado' ?>">
                  <?= $isActive ? 'Ativo' : 'Inativo' ?>
                </span>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="/admin_tenant_form.php?id=<?= (int)$tenant['id'] ?>">
                  <i class="bi bi-pencil-square me-1"></i>Editar
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php require_once __DIR__.'/_admin_layout_end.php'; ?>
