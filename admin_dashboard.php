<?php
require_once __DIR__.'/_admin_auth.php';
requireSaasAdmin();
require_once __DIR__.'/db.php';

$pdo = db();

if (!function_exists('hfAdminMoney')) {
    function hfAdminMoney($cents)
    {
        return 'R$ '.number_format(((float)$cents) / 100, 2, ',', '.');
    }
}

if (!function_exists('hfAdminDate')) {
    function hfAdminDate($value)
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

if (!function_exists('hfAdminStatusLabel')) {
    function hfAdminStatusLabel($status)
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

if (!function_exists('hfAdminPickColumn')) {
    function hfAdminPickColumn(PDO $pdo, $table, array $candidates)
    {
        if (!$candidates) {
            return null;
        }

        $placeholders = [];
        $params = [':table' => $table];
        foreach ($candidates as $index => $column) {
            $key = ':col'.$index;
            $placeholders[] = $key;
            $params[$key] = $column;
        }

        $sql = "
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME IN (".implode(',', $placeholders).")
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $column = $stmt->fetchColumn();

        return $column ?: null;
    }
}

if (!function_exists('hfAdminScalar')) {
    function hfAdminScalar(PDO $pdo, $sql, array $params = [])
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}

if (!function_exists('hfAdminRows')) {
    function hfAdminRows(PDO $pdo, $sql, array $params = [])
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$metrics = [
    'tenants_total' => 0,
    'trials_active' => 0,
    'subscriptions_active' => 0,
    'subscriptions_blocked' => 0,
    'potential_mrr_cents' => 0,
    'active_users_total' => 0,
    'monthly_os_total' => 0,
];
$recentTenants = [];

$latestSubscriptionSql = "
    SELECT ts.*
    FROM tenant_subscriptions ts
    JOIN (
        SELECT tenant_id, MAX(id) AS id
        FROM tenant_subscriptions
        GROUP BY tenant_id
    ) latest ON latest.id = ts.id
";

try {
    $metrics['tenants_total'] = (int)hfAdminScalar($pdo, "SELECT COUNT(*) FROM tenants");

    $metrics['trials_active'] = (int)hfAdminScalar($pdo, "
        SELECT COUNT(*)
        FROM ({$latestSubscriptionSql}) ts
        JOIN tenants t ON t.id = ts.tenant_id
        WHERE ts.status = 'trial'
          AND (ts.trial_end_at IS NULL OR ts.trial_end_at >= NOW())
    ");

    $metrics['subscriptions_active'] = (int)hfAdminScalar($pdo, "
        SELECT COUNT(*)
        FROM ({$latestSubscriptionSql}) ts
        JOIN tenants t ON t.id = ts.tenant_id
        WHERE ts.status = 'ativo'
    ");

    $metrics['subscriptions_blocked'] = (int)hfAdminScalar($pdo, "
        SELECT COUNT(*)
        FROM ({$latestSubscriptionSql}) ts
        JOIN tenants t ON t.id = ts.tenant_id
        WHERE ts.status = 'bloqueado'
    ");

    $metrics['potential_mrr_cents'] = (int)hfAdminScalar($pdo, "
        SELECT COALESCE(SUM(COALESCE(p.monthly_price_cents, 0)), 0)
        FROM ({$latestSubscriptionSql}) ts
        JOIN tenants t ON t.id = ts.tenant_id
        JOIN plans p ON p.id = ts.plan_id
        WHERE ts.status IN ('trial', 'ativo')
    ");

    $metrics['active_users_total'] = (int)hfAdminScalar($pdo, "
        SELECT COUNT(*)
        FROM users
        WHERE tenant_id IS NOT NULL
          AND is_active = 1
    ");

    $osDateCol = hfAdminPickColumn($pdo, 'hf_os', ['data_abertura', 'created_at']);
    $osDeletedCol = hfAdminPickColumn($pdo, 'hf_os', ['deleted_at']);

    if ($osDateCol) {
        $start = date('Y-m-01 00:00:00');
        $end = date('Y-m-d 00:00:00', strtotime(date('Y-m-01').' +1 month'));
        $deletedSql = $osDeletedCol ? " AND `{$osDeletedCol}` IS NULL" : '';

        $metrics['monthly_os_total'] = (int)hfAdminScalar($pdo, "
            SELECT COUNT(*)
            FROM hf_os
            WHERE tenant_id IS NOT NULL
              {$deletedSql}
              AND `{$osDateCol}` >= :start
              AND `{$osDateCol}` < :end
        ", [
            ':start' => $start,
            ':end' => $end,
        ]);
    }

    $recentTenants = hfAdminRows($pdo, "
        SELECT
            t.id,
            t.name AS tenant_name,
            t.slug,
            t.is_active,
            p.name AS plan_name,
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
        ORDER BY t.id DESC
        LIMIT 8
    ");
} catch (Exception $e) {
    error_log('admin_dashboard.php metricas: '.$e->getMessage());
}

require_once __DIR__.'/_admin_layout_start.php';
?>

<div class="d-flex flex-column gap-4">
  <section class="hf-admin-card p-4">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
      <div>
        <div class="text-uppercase fw-bold text-primary small mb-2">Visao executiva</div>
        <h2 class="h3 fw-black mb-1" style="font-weight: 950;">Dashboard SaaS</h2>
        <p class="text-muted mb-0">Acompanhe empresas, trials, assinaturas e volume de uso da plataforma.</p>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-primary" href="/admin_tenants.php">
          <i class="bi bi-buildings me-1"></i>Empresas
        </a>
        <a class="btn btn-outline-primary" href="/admin_subscriptions.php">
          <i class="bi bi-credit-card-2-front me-1"></i>Assinaturas
        </a>
      </div>
    </div>
  </section>

  <section class="hf-admin-kpis">
    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-buildings text-primary"></i>Total de empresas</small>
      <strong><?= number_format((int)$metrics['tenants_total'], 0, ',', '.') ?></strong>
      <span>Tenants cadastrados</span>
    </article>

    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-hourglass-split text-primary"></i>Trials ativos</small>
      <strong><?= number_format((int)$metrics['trials_active'], 0, ',', '.') ?></strong>
      <span>Empresas em teste</span>
    </article>

    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-check2-circle text-success"></i>Assinaturas ativas</small>
      <strong><?= number_format((int)$metrics['subscriptions_active'], 0, ',', '.') ?></strong>
      <span>Clientes pagantes</span>
    </article>

    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-lock text-danger"></i>Bloqueadas</small>
      <strong><?= number_format((int)$metrics['subscriptions_blocked'], 0, ',', '.') ?></strong>
      <span>Empresas com acesso bloqueado</span>
    </article>

    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-graph-up-arrow text-success"></i>Potencial MRR</small>
      <strong><?= htmlspecialchars(hfAdminMoney($metrics['potential_mrr_cents']), ENT_QUOTES, 'UTF-8') ?></strong>
      <span>Planos em trial ou ativo</span>
    </article>

    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-people text-primary"></i>Usuarios totais</small>
      <strong><?= number_format((int)$metrics['active_users_total'], 0, ',', '.') ?></strong>
      <span>Usuarios ativos dos tenants</span>
    </article>

    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-clipboard2-check text-warning"></i>OS do mes</small>
      <strong><?= number_format((int)$metrics['monthly_os_total'], 0, ',', '.') ?></strong>
      <span>Volume operacional mensal</span>
    </article>

    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-activity text-primary"></i>Conversao base</small>
      <?php
        $trialAndActive = (int)$metrics['trials_active'] + (int)$metrics['subscriptions_active'];
        $conversion = $trialAndActive > 0 ? round(((int)$metrics['subscriptions_active'] / $trialAndActive) * 100) : 0;
      ?>
      <strong><?= (int)$conversion ?>%</strong>
      <span>Ativas sobre trial + ativas</span>
    </article>
  </section>

  <section class="hf-admin-card">
    <div class="d-flex align-items-center justify-content-between gap-3 p-4 pb-2">
      <div>
        <h2 class="h5 mb-1 fw-bold">Empresas recentes</h2>
        <p class="text-muted mb-0">Ultimos tenants cadastrados com plano, status e usuarios ativos.</p>
      </div>
      <a class="btn btn-sm btn-outline-primary" href="/admin_tenants.php">Ver todas</a>
    </div>

    <div class="table-responsive">
      <table class="table hf-admin-table">
        <thead>
          <tr>
            <th>Empresa</th>
            <th>Plano</th>
            <th>Status</th>
            <th>Trial termina</th>
            <th class="text-end">Usuarios ativos</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$recentTenants): ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-4">Nenhuma empresa encontrada.</td>
            </tr>
          <?php endif; ?>

          <?php foreach ($recentTenants as $tenant): ?>
            <?php
              $status = trim((string)($tenant['status'] ?? ''));
              $statusClass = $status !== '' ? 'is-'.$status : '';
              $tenantName = trim((string)($tenant['tenant_name'] ?? ''));
              if ($tenantName === '') {
                  $tenantName = 'Empresa #'.(int)($tenant['id'] ?? 0);
              }
            ?>
            <tr>
              <td>
                <div class="fw-bold"><?= htmlspecialchars($tenantName, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="text-muted small"><?= htmlspecialchars((string)($tenant['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
              </td>
              <td><?= htmlspecialchars((string)($tenant['plan_name'] ?? 'Sem plano'), ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <span class="hf-status-pill <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars(hfAdminStatusLabel($status), ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
              <td><?= htmlspecialchars(hfAdminDate($tenant['trial_end_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
              <td class="text-end fw-bold"><?= number_format((int)($tenant['active_users'] ?? 0), 0, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php require_once __DIR__.'/_admin_layout_end.php'; ?>
