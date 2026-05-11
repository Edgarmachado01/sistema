<?php
require_once __DIR__.'/_admin_auth.php';
requireSaasAdmin();
require_once __DIR__.'/db.php';

$pdo = db();

if (!function_exists('hfAdminSubMoney')) {
    function hfAdminSubMoney($cents)
    {
        return 'R$ '.number_format(((float)$cents) / 100, 2, ',', '.');
    }
}

if (!function_exists('hfAdminSubDate')) {
    function hfAdminSubDate($value)
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

if (!function_exists('hfAdminSubStatusLabel')) {
    function hfAdminSubStatusLabel($status)
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

if (!function_exists('hfAdminSubScalar')) {
    function hfAdminSubScalar(PDO $pdo, $sql, array $params = [])
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}

if (!function_exists('hfAdminSubRows')) {
    function hfAdminSubRows(PDO $pdo, $sql, array $params = [])
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('hfAdminSubPickColumn')) {
    function hfAdminSubPickColumn(PDO $pdo, $table, array $candidates)
    {
        if (!$candidates) {
            return null;
        }

        $params = [':table' => $table];
        $placeholders = [];
        foreach ($candidates as $index => $column) {
            $key = ':col'.$index;
            $placeholders[] = $key;
            $params[$key] = $column;
        }

        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME IN (".implode(',', $placeholders).")
            LIMIT 1
        ");
        $stmt->execute($params);
        $column = $stmt->fetchColumn();

        return $column ?: null;
    }
}

if (!function_exists('hfAdminSubCycleLabel')) {
    function hfAdminSubCycleLabel($periodStart, $periodEnd, $status, $planCode)
    {
        $planCode = trim((string)$planCode);
        $status = trim((string)$status);

        if ($planCode === 'cortesia') {
            return 'Cortesia';
        }

        if ($status === 'trial') {
            return 'Trial';
        }

        if (!$periodStart || !$periodEnd) {
            return 'Mensal';
        }

        try {
            $start = new DateTime((string)$periodStart);
            $end = new DateTime((string)$periodEnd);
            $days = (int)$start->diff($end)->format('%a');
            return $days >= 330 ? 'Anual' : 'Mensal';
        } catch (Exception $e) {
            return 'Mensal';
        }
    }
}

if (empty($_SESSION['SAAS_ADMIN_SUBS_CSRF'])) {
    $_SESSION['SAAS_ADMIN_SUBS_CSRF'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['SAAS_ADMIN_SUBS_CSRF'];

$flashSuccess = (string)($_SESSION['SAAS_ADMIN_FLASH_SUCCESS'] ?? '');
$flashError = (string)($_SESSION['SAAS_ADMIN_FLASH_ERROR'] ?? '');
unset($_SESSION['SAAS_ADMIN_FLASH_SUCCESS'], $_SESSION['SAAS_ADMIN_FLASH_ERROR']);

$q = trim((string)($_GET['q'] ?? ''));
$plan = trim((string)($_GET['plan'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$filter = trim((string)($_GET['filter'] ?? ''));

$allowedStatuses = ['', 'trial', 'ativo', 'vencido', 'bloqueado', 'cancelado'];
$allowedFilters = ['', 'due7', 'trials', 'blocked', 'cortesia'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}
if (!in_array($filter, $allowedFilters, true)) {
    $filter = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'confirm_payment') {
        $sessionToken = (string)($_SESSION['SAAS_ADMIN_SUBS_CSRF'] ?? '');
        $postToken = (string)($_POST['csrf_token'] ?? '');

        if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
            $_SESSION['SAAS_ADMIN_FLASH_ERROR'] = 'Sessao expirada. Recarregue e tente novamente.';
            header('Location: /admin_subscriptions.php');
            exit;
        }

        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        $billingCycle = trim((string)($_POST['billing_cycle'] ?? 'mensal'));
        if ($tenantId <= 0 || !in_array($billingCycle, ['mensal', 'anual'], true)) {
            $_SESSION['SAAS_ADMIN_FLASH_ERROR'] = 'Dados de confirmacao invalidos.';
            header('Location: /admin_subscriptions.php');
            exit;
        }

        try {
            $stmtCurrent = $pdo->prepare("
                SELECT ts.id, p.code AS plan_code
                FROM tenant_subscriptions ts
                JOIN plans p ON p.id = ts.plan_id
                WHERE ts.tenant_id = :tenant_id
                ORDER BY ts.id DESC
                LIMIT 1
            ");
            $stmtCurrent->execute([':tenant_id' => $tenantId]);
            $current = $stmtCurrent->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$current) {
                $_SESSION['SAAS_ADMIN_FLASH_ERROR'] = 'Assinatura nao encontrada para esta empresa.';
                header('Location: /admin_subscriptions.php');
                exit;
            }

            if ((string)($current['plan_code'] ?? '') === 'cortesia') {
                $_SESSION['SAAS_ADMIN_FLASH_ERROR'] = 'Plano cortesia nao precisa de confirmacao de pagamento.';
                header('Location: /admin_subscriptions.php');
                exit;
            }

            $periodStart = new DateTime();
            $periodEnd = clone $periodStart;
            if ($billingCycle === 'anual') {
                $periodEnd->add(new DateInterval('P365D'));
            } else {
                $periodEnd->add(new DateInterval('P30D'));
            }

            $stmtUpdate = $pdo->prepare("
                UPDATE tenant_subscriptions
                SET status = 'ativo',
                    current_period_start = :period_start,
                    current_period_end = :period_end,
                    blocked_at = NULL,
                    cancelled_at = NULL,
                    updated_at = NOW()
                WHERE id = :id
                  AND tenant_id = :tenant_id
            ");
            $stmtUpdate->execute([
                ':period_start' => $periodStart->format('Y-m-d H:i:s'),
                ':period_end' => $periodEnd->format('Y-m-d H:i:s'),
                ':id' => (int)$current['id'],
                ':tenant_id' => $tenantId,
            ]);

            $_SESSION['SAAS_ADMIN_FLASH_SUCCESS'] = 'Pagamento confirmado com sucesso.';
            header('Location: /admin_subscriptions.php');
            exit;
        } catch (Exception $e) {
            error_log('admin_subscriptions.php confirm_payment: '.$e->getMessage());
            $_SESSION['SAAS_ADMIN_FLASH_ERROR'] = 'Nao foi possivel confirmar o pagamento agora.';
            header('Location: /admin_subscriptions.php');
            exit;
        }
    }
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
    'total' => 0,
    'trials_active' => 0,
    'active' => 0,
    'expired' => 0,
    'blocked' => 0,
    'cortesia' => 0,
    'potential_mrr_cents' => 0,
];
$subscriptions = [];

try {
    $plans = hfAdminSubRows($pdo, "
        SELECT id, code, name
        FROM plans
        WHERE is_active = 1
        ORDER BY monthly_price_cents ASC, name ASC
    ");

    $metrics['total'] = (int)hfAdminSubScalar($pdo, "
        SELECT COUNT(*)
        FROM ({$latestSubscriptionSql}) ts
    ");
    $metrics['trials_active'] = (int)hfAdminSubScalar($pdo, "
        SELECT COUNT(*)
        FROM ({$latestSubscriptionSql}) ts
        JOIN plans p ON p.id = ts.plan_id
        WHERE ts.status = 'trial'
          AND p.code <> 'cortesia'
          AND (ts.trial_end_at IS NULL OR ts.trial_end_at >= NOW())
    ");
    $metrics['active'] = (int)hfAdminSubScalar($pdo, "
        SELECT COUNT(*)
        FROM ({$latestSubscriptionSql}) ts
        WHERE ts.status = 'ativo'
    ");
    $metrics['expired'] = (int)hfAdminSubScalar($pdo, "
        SELECT COUNT(*)
        FROM ({$latestSubscriptionSql}) ts
        WHERE ts.status = 'vencido'
    ");
    $metrics['blocked'] = (int)hfAdminSubScalar($pdo, "
        SELECT COUNT(*)
        FROM ({$latestSubscriptionSql}) ts
        WHERE ts.status = 'bloqueado'
    ");
    $metrics['cortesia'] = (int)hfAdminSubScalar($pdo, "
        SELECT COUNT(*)
        FROM ({$latestSubscriptionSql}) ts
        JOIN plans p ON p.id = ts.plan_id
        WHERE p.code = 'cortesia'
    ");
    $metrics['potential_mrr_cents'] = (int)hfAdminSubScalar($pdo, "
        SELECT COALESCE(SUM(COALESCE(p.monthly_price_cents, 0)), 0)
        FROM ({$latestSubscriptionSql}) ts
        JOIN plans p ON p.id = ts.plan_id
        WHERE ts.status IN ('trial', 'ativo')
          AND p.code <> 'cortesia'
    ");

    $osDateCol = hfAdminSubPickColumn($pdo, 'hf_os', ['data_abertura', 'created_at']);
    $osDeletedCol = hfAdminSubPickColumn($pdo, 'hf_os', ['deleted_at']);
    $monthStart = date('Y-m-01 00:00:00');
    $monthEnd = date('Y-m-d 00:00:00', strtotime(date('Y-m-01').' +1 month'));

    if ($osDateCol) {
        $osDeletedSql = $osDeletedCol ? "AND o.`{$osDeletedCol}` IS NULL" : '';
        $monthlyOsSql = "
            (
                SELECT COUNT(*)
                FROM hf_os o
                WHERE o.tenant_id = t.id
                  {$osDeletedSql}
                  AND o.`{$osDateCol}` >= :month_start
                  AND o.`{$osDateCol}` < :month_end
            ) AS monthly_os_count
        ";
    } else {
        $monthlyOsSql = "0 AS monthly_os_count";
    }

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

    if ($filter === 'due7') {
        $where[] = "ts.current_period_end IS NOT NULL AND ts.current_period_end >= NOW() AND ts.current_period_end < DATE_ADD(NOW(), INTERVAL 7 DAY)";
    } elseif ($filter === 'trials') {
        $where[] = "ts.status = 'trial' AND p.code <> 'cortesia' AND (ts.trial_end_at IS NULL OR ts.trial_end_at >= NOW())";
    } elseif ($filter === 'blocked') {
        $where[] = "ts.status = 'bloqueado'";
    } elseif ($filter === 'cortesia') {
        $where[] = "p.code = 'cortesia'";
    }

    if ($osDateCol) {
        $params[':month_start'] = $monthStart;
        $params[':month_end'] = $monthEnd;
    }

    $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

    $subscriptions = hfAdminSubRows($pdo, "
        SELECT
            t.id AS tenant_id,
            t.name AS tenant_name,
            t.slug,
            p.name AS plan_name,
            p.code AS plan_code,
            p.monthly_price_cents,
            ts.status,
            ts.trial_start_at,
            ts.trial_end_at,
            ts.current_period_start,
            ts.current_period_end,
            (
                SELECT COUNT(*)
                FROM users u
                WHERE u.tenant_id = t.id
                  AND u.is_active = 1
            ) AS active_users,
            {$monthlyOsSql}
        FROM tenants t
        JOIN ({$latestSubscriptionSql}) ts ON ts.tenant_id = t.id
        JOIN plans p ON p.id = ts.plan_id
        {$whereSql}
        ORDER BY
            CASE
                WHEN ts.status = 'bloqueado' THEN 1
                WHEN ts.status = 'vencido' THEN 2
                WHEN ts.status = 'trial' THEN 3
                ELSE 4
            END,
            ts.current_period_end IS NULL,
            ts.current_period_end ASC,
            t.id DESC
        LIMIT 250
    ", $params);
} catch (Exception $e) {
    error_log('admin_subscriptions.php: '.$e->getMessage());
    $flashError = 'Nao foi possivel carregar as assinaturas agora.';
}

require_once __DIR__.'/_admin_layout_start.php';
?>

<style>
  .hf-admin-sub-filter-grid {
    display: grid;
    grid-template-columns: minmax(220px, 1fr) 180px 180px 220px auto;
    gap: .75rem;
    align-items: end;
  }

  .hf-sub-company {
    display: flex;
    align-items: center;
    gap: .75rem;
  }

  .hf-sub-avatar {
    width: 38px;
    height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 38px;
    border-radius: .85rem;
    color: #fff;
    background: linear-gradient(135deg, #0d6efd, #14b8a6);
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

  .hf-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .45rem .7rem;
    border: 1px solid rgba(148, 163, 184, .28);
    border-radius: 999px;
    color: #475569;
    background: #fff;
    font-size: .82rem;
    font-weight: 800;
    text-decoration: none;
  }

  .hf-filter-chip:hover,
  .hf-filter-chip.active {
    color: #0d6efd;
    border-color: rgba(13, 110, 253, .30);
    background: rgba(13, 110, 253, .07);
  }

  .hf-sub-actions {
    display: inline-flex;
    flex-direction: column;
    gap: .45rem;
    align-items: flex-end;
  }

  .hf-sub-confirm-form {
    display: inline-flex;
    gap: .35rem;
    align-items: center;
    justify-content: flex-end;
  }

  .hf-sub-confirm-form .form-select {
    min-width: 110px;
    font-size: .76rem;
    padding-top: .26rem;
    padding-bottom: .26rem;
  }

  @media (max-width: 1160px) {
    .hf-admin-sub-filter-grid {
      grid-template-columns: 1fr 1fr 1fr;
    }
  }

  @media (max-width: 700px) {
    .hf-admin-sub-filter-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="d-flex flex-column gap-4">
  <section class="hf-admin-card p-4">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
      <div>
        <div class="text-uppercase fw-bold text-primary small mb-2">Controle comercial</div>
        <h2 class="h3 mb-1" style="font-weight: 950;">Assinaturas</h2>
        <p class="text-muted mb-0">Visao central das assinaturas, trials, vencimentos e uso por empresa.</p>
      </div>
      <a class="btn btn-outline-primary" href="/admin_tenants.php">
        <i class="bi bi-buildings me-1"></i>Empresas
      </a>
    </div>
  </section>

  <?php if ($flashError): ?>
    <div class="alert alert-danger border-0 shadow-sm mb-0">
      <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($flashSuccess): ?>
    <div class="alert alert-success border-0 shadow-sm mb-0">
      <i class="bi bi-check2-circle me-1"></i><?= htmlspecialchars($flashSuccess, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <section class="hf-admin-kpis">
    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-credit-card-2-front text-primary"></i>Total assinaturas</small>
      <strong><?= number_format((int)$metrics['total'], 0, ',', '.') ?></strong>
      <span>Assinaturas atuais</span>
    </article>
    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-hourglass-split text-primary"></i>Trials ativos</small>
      <strong><?= number_format((int)$metrics['trials_active'], 0, ',', '.') ?></strong>
      <span>Empresas em teste</span>
    </article>
    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-check2-circle text-success"></i>Ativas</small>
      <strong><?= number_format((int)$metrics['active'], 0, ',', '.') ?></strong>
      <span>Assinaturas liberadas</span>
    </article>
    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-exclamation-circle text-warning"></i>Vencidas</small>
      <strong><?= number_format((int)$metrics['expired'], 0, ',', '.') ?></strong>
      <span>Precisam de atencao</span>
    </article>
    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-lock text-danger"></i>Bloqueadas</small>
      <strong><?= number_format((int)$metrics['blocked'], 0, ',', '.') ?></strong>
      <span>Status comercial bloqueado</span>
    </article>
    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-stars text-primary"></i>Cortesia</small>
      <strong><?= number_format((int)$metrics['cortesia'], 0, ',', '.') ?></strong>
      <span>Sem cobranca</span>
    </article>
    <article class="hf-admin-card hf-admin-kpi">
      <small><i class="bi bi-graph-up-arrow text-success"></i>MRR potencial</small>
      <strong><?= htmlspecialchars(hfAdminSubMoney($metrics['potential_mrr_cents']), ENT_QUOTES, 'UTF-8') ?></strong>
      <span>Ativas + trials pagos</span>
    </article>
  </section>

  <section class="hf-admin-card p-4">
    <form method="get" class="hf-admin-sub-filter-grid">
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
              <?= htmlspecialchars(hfAdminSubStatusLabel($itemStatus), ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label text-muted fw-bold small">Filtro rapido</label>
        <select class="form-select" name="filter">
          <option value="">Todos</option>
          <option value="due7" <?= $filter === 'due7' ? 'selected' : '' ?>>Vencendo em 7 dias</option>
          <option value="trials" <?= $filter === 'trials' ? 'selected' : '' ?>>Trials ativos</option>
          <option value="blocked" <?= $filter === 'blocked' ? 'selected' : '' ?>>Bloqueadas</option>
          <option value="cortesia" <?= $filter === 'cortesia' ? 'selected' : '' ?>>Cortesia</option>
        </select>
      </div>
      <div class="d-grid">
        <button class="btn btn-primary">
          <i class="bi bi-search me-1"></i>Filtrar
        </button>
      </div>
    </form>

    <div class="d-flex flex-wrap gap-2 mt-3">
      <a class="hf-filter-chip <?= $filter === 'due7' ? 'active' : '' ?>" href="/admin_subscriptions.php?filter=due7">
        <i class="bi bi-calendar-week"></i>Vencendo em 7 dias
      </a>
      <a class="hf-filter-chip <?= $filter === 'trials' ? 'active' : '' ?>" href="/admin_subscriptions.php?filter=trials">
        <i class="bi bi-hourglass-split"></i>Trials ativos
      </a>
      <a class="hf-filter-chip <?= $filter === 'blocked' ? 'active' : '' ?>" href="/admin_subscriptions.php?filter=blocked">
        <i class="bi bi-lock"></i>Bloqueadas
      </a>
      <a class="hf-filter-chip <?= $filter === 'cortesia' ? 'active' : '' ?>" href="/admin_subscriptions.php?filter=cortesia">
        <i class="bi bi-stars"></i>Cortesia
      </a>
    </div>
  </section>

  <section class="hf-admin-card">
    <div class="table-responsive">
      <table class="table hf-admin-table">
        <thead>
          <tr>
            <th>Empresa</th>
            <th>Plano</th>
            <th>Status</th>
            <th>Inicio trial</th>
            <th>Fim trial</th>
            <th>Fim periodo</th>
            <th>Ciclo</th>
            <th class="text-end">Valor mensal</th>
            <th class="text-end">Valor anual</th>
            <th class="text-end">Usuarios</th>
            <th class="text-end">OS mes</th>
            <th class="text-end">Acoes</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$subscriptions): ?>
            <tr>
              <td colspan="12" class="text-center text-muted py-4">Nenhuma assinatura encontrada.</td>
            </tr>
          <?php endif; ?>

          <?php foreach ($subscriptions as $item): ?>
            <?php
              $tenantName = trim((string)($item['tenant_name'] ?? ''));
              $initial = strtoupper(substr($tenantName !== '' ? $tenantName : 'E', 0, 1));
              $statusValue = trim((string)($item['status'] ?? ''));
              $planCode = trim((string)($item['plan_code'] ?? ''));
              $isCortesia = $planCode === 'cortesia';
              $monthlyCents = (int)($item['monthly_price_cents'] ?? 0);
              $annualCents = $monthlyCents > 0 ? ($monthlyCents * 10) : 0;
              $cycleLabel = hfAdminSubCycleLabel(
                  $item['current_period_start'] ?? null,
                  $item['current_period_end'] ?? null,
                  $statusValue,
                  $planCode
              );
            ?>
            <tr>
              <td>
                <div class="hf-sub-company">
                  <span class="hf-sub-avatar"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
                  <div>
                    <div class="fw-bold"><?= htmlspecialchars($tenantName !== '' ? $tenantName : 'Empresa #'.(int)$item['tenant_id'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="text-muted small"><?= htmlspecialchars((string)($item['slug'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                  </div>
                </div>
              </td>
              <td>
                <span class="hf-plan-badge <?= $isCortesia ? 'is-cortesia' : '' ?>">
                  <?php if ($isCortesia): ?><i class="bi bi-stars"></i><?php endif; ?>
                  <?= htmlspecialchars((string)($item['plan_name'] ?? 'Sem plano'), ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
              <td>
                <span class="hf-status-pill <?= htmlspecialchars($statusValue !== '' ? 'is-'.$statusValue : '', ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars(hfAdminSubStatusLabel($statusValue), ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>
              <td><?= htmlspecialchars(hfAdminSubDate($item['trial_start_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($isCortesia ? '-' : hfAdminSubDate($item['trial_end_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($isCortesia ? '-' : hfAdminSubDate($item['current_period_end'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= htmlspecialchars($cycleLabel, ENT_QUOTES, 'UTF-8') ?></td>
              <td class="text-end fw-bold"><?= htmlspecialchars(hfAdminSubMoney($monthlyCents), ENT_QUOTES, 'UTF-8') ?></td>
              <td class="text-end fw-bold"><?= htmlspecialchars($isCortesia ? '-' : hfAdminSubMoney($annualCents), ENT_QUOTES, 'UTF-8') ?></td>
              <td class="text-end fw-bold"><?= number_format((int)($item['active_users'] ?? 0), 0, ',', '.') ?></td>
              <td class="text-end fw-bold"><?= number_format((int)($item['monthly_os_count'] ?? 0), 0, ',', '.') ?></td>
              <td class="text-end">
                <div class="hf-sub-actions">
                  <?php if (!$isCortesia): ?>
                    <form method="post" class="hf-sub-confirm-form" onsubmit="return confirm('Confirmar pagamento manual desta assinatura?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                      <input type="hidden" name="action" value="confirm_payment">
                      <input type="hidden" name="tenant_id" value="<?= (int)$item['tenant_id'] ?>">
                      <select class="form-select form-select-sm" name="billing_cycle">
                        <option value="mensal" <?= $cycleLabel === 'Mensal' ? 'selected' : '' ?>>Mensal</option>
                        <option value="anual" <?= $cycleLabel === 'Anual' ? 'selected' : '' ?>>Anual</option>
                      </select>
                      <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check2-circle me-1"></i>Confirmar
                      </button>
                    </form>
                  <?php endif; ?>
                  <a class="btn btn-sm btn-outline-primary" href="/admin_tenant_form.php?id=<?= (int)$item['tenant_id'] ?>">
                    <i class="bi bi-pencil-square me-1"></i>Editar
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php require_once __DIR__.'/_admin_layout_end.php'; ?>
