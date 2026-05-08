<?php
require_once __DIR__.'/auth.php';
requireLogin();

require_once __DIR__.'/db.php';
require_once __DIR__.'/_plan_usage.php';

$pdo = db();

$roles = $_SESSION['ROLES'] ?? [];
$isAtendenteOnly = hasRole('ATENDENTE') && !hasRole('TENANT_ADMIN') && !hasRole('SYS_ADMIN');

$tid = function_exists('tenantId')
    ? (int) tenantId()
    : (int) ($_SESSION['tenant_id'] ?? 0);

$hoje = date('Y-m-d');
$primeiroMes = date('Y-m-01');

$preset = isset($_GET['preset']) ? (string)$_GET['preset'] : '';
$data_ini = isset($_GET['data_ini']) && $_GET['data_ini'] !== '' ? (string)$_GET['data_ini'] : $primeiroMes;
$data_fim = isset($_GET['data_fim']) && $_GET['data_fim'] !== '' ? (string)$_GET['data_fim'] : $hoje;

switch ($preset) {
    case 'hoje':
        $data_ini = $hoje;
        $data_fim = $hoje;
        break;
    case '7d':
        $data_ini = date('Y-m-d', strtotime('-6 days', strtotime($hoje)));
        $data_fim = $hoje;
        break;
    case 'mes_atual':
        $data_ini = date('Y-m-01');
        $data_fim = $hoje;
        break;
    case 'mes_anterior':
        $data_ini = date('Y-m-01', strtotime('first day of last month'));
        $data_fim = date('Y-m-t', strtotime('last day of last month'));
        break;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ini)) {
    $data_ini = $primeiroMes;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim)) {
    $data_fim = $hoje;
}
if ($data_ini > $data_fim) {
    $tmp = $data_ini;
    $data_ini = $data_fim;
    $data_fim = $tmp;
}

$periodStart = $data_ini.' 00:00:00';
$periodEnd = date('Y-m-d 00:00:00', strtotime($data_fim.' +1 day'));

if (!function_exists('pickColumnForTable')) {
    function pickColumnForTable(PDO $pdo, $table, array $candidates) {
        if (empty($candidates)) return null;

        $in = implode("','", array_map('strval', $candidates));
        $sql = "
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME IN ('{$in}')
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':table' => $table]);
        $col = $stmt->fetchColumn();

        return $col ?: null;
    }
}

if (!function_exists('hfDashMoney')) {
    function hfDashMoney($value) {
        return 'R$ '.number_format((float)$value, 2, ',', '.');
    }
}

if (!function_exists('hfDashCount')) {
    function hfDashCount(PDO $pdo, $sql, array $params = []) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}

if (!function_exists('hfDashScalar')) {
    function hfDashScalar(PDO $pdo, $sql, array $params = []) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float)($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('hfDashFetchAll')) {
    function hfDashFetchAll(PDO $pdo, $sql, array $params = []) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$deletedOs = pickColumnForTable($pdo, 'hf_os', ['deleted_at']);
$deletedClientes = pickColumnForTable($pdo, 'hf_clientes', ['deleted_at']);
$osDateCol = pickColumnForTable($pdo, 'hf_os', ['data_abertura']);
$osCreatedCol = pickColumnForTable($pdo, 'hf_os', ['created_at']);
$osPaidCol = pickColumnForTable($pdo, 'hf_os', ['data_pagto', 'data_pagamento', 'dt_pagamento']);

$osDeletedSql = $deletedOs ? " AND o.`{$deletedOs}` IS NULL" : '';
$osDeletedPlainSql = $deletedOs ? " AND `{$deletedOs}` IS NULL" : '';
$clientesDeletedSql = $deletedClientes ? " AND `{$deletedClientes}` IS NULL" : '';

if ($osDateCol && $osCreatedCol) {
    $osDateExpr = "COALESCE(o.`{$osDateCol}`, o.`{$osCreatedCol}`)";
    $osDatePlainExpr = "COALESCE(`{$osDateCol}`, `{$osCreatedCol}`)";
} elseif ($osDateCol) {
    $osDateExpr = "o.`{$osDateCol}`";
    $osDatePlainExpr = "`{$osDateCol}`";
} elseif ($osCreatedCol) {
    $osDateExpr = "o.`{$osCreatedCol}`";
    $osDatePlainExpr = "`{$osCreatedCol}`";
} else {
    $osDateExpr = "NOW()";
    $osDatePlainExpr = "NOW()";
}

$statusAbertas = ['aberta'];
$statusAndamento = ['em_andamento'];
$statusPendentes = ['aberta', 'em_andamento', 'aguardando_aprovacao', 'aguardando_peca', 'pendente'];
$statusConcluidas = ['concluida', 'finalizada', 'fechada'];

$metrics = [
    'os_abertas' => 0,
    'os_andamento' => 0,
    'os_pendentes' => 0,
    'os_concluidas_periodo' => 0,
    'os_periodo' => 0,
    'os_antigas' => 0,
    'clientes' => 0,
    'faturamento_periodo' => 0.0,
    'recebido_periodo' => 0.0,
    'saldo_aberto' => 0.0,
];
$attentionOs = [];
$attentionFinance = [];

try {
    $metrics['os_abertas'] = hfDashCount($pdo, "
        SELECT COUNT(*)
        FROM hf_os o
        WHERE o.tenant_id = :tid
          {$osDeletedSql}
          AND o.status IN ('aberta')
    ", [':tid' => $tid]);

    $metrics['os_andamento'] = hfDashCount($pdo, "
        SELECT COUNT(*)
        FROM hf_os o
        WHERE o.tenant_id = :tid
          {$osDeletedSql}
          AND o.status IN ('em_andamento')
    ", [':tid' => $tid]);

    $metrics['os_pendentes'] = hfDashCount($pdo, "
        SELECT COUNT(*)
        FROM hf_os o
        WHERE o.tenant_id = :tid
          {$osDeletedSql}
          AND o.status IN ('aberta','em_andamento','aguardando_aprovacao','aguardando_peca','pendente')
    ", [':tid' => $tid]);

    $metrics['os_concluidas_periodo'] = hfDashCount($pdo, "
        SELECT COUNT(*)
        FROM hf_os o
        WHERE o.tenant_id = :tid
          {$osDeletedSql}
          AND o.status IN ('concluida','finalizada','fechada')
          AND {$osDateExpr} >= :ini
          AND {$osDateExpr} < :fim
    ", [
        ':tid' => $tid,
        ':ini' => $periodStart,
        ':fim' => $periodEnd,
    ]);

    $metrics['os_periodo'] = hfDashCount($pdo, "
        SELECT COUNT(*)
        FROM hf_os o
        WHERE o.tenant_id = :tid
          {$osDeletedSql}
          AND {$osDateExpr} >= :ini
          AND {$osDateExpr} < :fim
    ", [
        ':tid' => $tid,
        ':ini' => $periodStart,
        ':fim' => $periodEnd,
    ]);

    $metrics['os_antigas'] = hfDashCount($pdo, "
        SELECT COUNT(*)
        FROM hf_os o
        WHERE o.tenant_id = :tid
          {$osDeletedSql}
          AND o.status IN ('aberta','em_andamento','aguardando_aprovacao','aguardando_peca','pendente')
          AND {$osDateExpr} < :limite
    ", [
        ':tid' => $tid,
        ':limite' => date('Y-m-d 00:00:00', strtotime('-7 days')),
    ]);

    $metrics['clientes'] = hfDashCount($pdo, "
        SELECT COUNT(*)
        FROM hf_clientes
        WHERE tenant_id = :tid
          {$clientesDeletedSql}
    ", [':tid' => $tid]);

    $metrics['faturamento_periodo'] = hfDashScalar($pdo, "
        SELECT COALESCE(SUM(COALESCE(o.total, 0)), 0)
        FROM hf_os o
        WHERE o.tenant_id = :tid
          {$osDeletedSql}
          AND COALESCE(o.status, '') <> 'cancelada'
          AND {$osDateExpr} >= :ini
          AND {$osDateExpr} < :fim
    ", [
        ':tid' => $tid,
        ':ini' => $periodStart,
        ':fim' => $periodEnd,
    ]);

    if ($osPaidCol) {
        $metrics['recebido_periodo'] = hfDashScalar($pdo, "
            SELECT COALESCE(SUM(COALESCE(o.valor_pago, 0)), 0)
            FROM hf_os o
            WHERE o.tenant_id = :tid
              {$osDeletedSql}
              AND o.`{$osPaidCol}` >= :ini
              AND o.`{$osPaidCol}` < :fim
        ", [
            ':tid' => $tid,
            ':ini' => $periodStart,
            ':fim' => $periodEnd,
        ]);
    } else {
        $metrics['recebido_periodo'] = hfDashScalar($pdo, "
            SELECT COALESCE(SUM(COALESCE(o.valor_pago, 0)), 0)
            FROM hf_os o
            WHERE o.tenant_id = :tid
              {$osDeletedSql}
              AND {$osDateExpr} >= :ini
              AND {$osDateExpr} < :fim
        ", [
            ':tid' => $tid,
            ':ini' => $periodStart,
            ':fim' => $periodEnd,
        ]);
    }

    $metrics['saldo_aberto'] = hfDashScalar($pdo, "
        SELECT COALESCE(SUM(GREATEST(COALESCE(o.total, 0) - COALESCE(o.valor_pago, 0), 0)), 0)
        FROM hf_os o
        WHERE o.tenant_id = :tid
          {$osDeletedSql}
          AND COALESCE(o.status, '') <> 'cancelada'
          AND GREATEST(COALESCE(o.total, 0) - COALESCE(o.valor_pago, 0), 0) > 0
    ", [':tid' => $tid]);

    $attentionOs = hfDashFetchAll($pdo, "
        SELECT
            o.id,
            o.numero,
            o.status,
            {$osDateExpr} AS data_ref,
            COALESCE(c.nome, 'Cliente nao informado') AS cliente
        FROM hf_os o
        LEFT JOIN hf_clientes c
          ON c.id = o.cliente_id
         AND c.tenant_id = o.tenant_id
        WHERE o.tenant_id = :tid
          {$osDeletedSql}
          AND o.status IN ('aberta','em_andamento','aguardando_aprovacao','aguardando_peca','pendente')
        ORDER BY data_ref ASC, o.id ASC
        LIMIT 4
    ", [':tid' => $tid]);

    $attentionFinance = hfDashFetchAll($pdo, "
        SELECT
            o.id,
            o.numero,
            o.status_financeiro,
            GREATEST(COALESCE(o.total, 0) - COALESCE(o.valor_pago, 0), 0) AS saldo,
            COALESCE(c.nome, 'Cliente nao informado') AS cliente
        FROM hf_os o
        LEFT JOIN hf_clientes c
          ON c.id = o.cliente_id
         AND c.tenant_id = o.tenant_id
        WHERE o.tenant_id = :tid
          {$osDeletedSql}
          AND COALESCE(o.status, '') <> 'cancelada'
          AND GREATEST(COALESCE(o.total, 0) - COALESCE(o.valor_pago, 0), 0) > 0
        ORDER BY {$osDateExpr} ASC, o.id ASC
        LIMIT 4
    ", [':tid' => $tid]);
} catch (Exception $e) {
    error_log('dashboard.php metricas: '.$e->getMessage());
}

$planUsageCard = null;
if ($tid > 0 && !(function_exists('isSysAdmin') && isSysAdmin())) {
    try {
        $planUsage = hfTenantUsage($pdo, $tid);
        $hasPlanInfo = !empty($planUsage['plan_name']) || !empty($planUsage['plan_code']) || !empty($planUsage['subscription_status']);

        if ($hasPlanInfo) {
            $planName = trim((string)($planUsage['plan_name'] ?? ''));
            if ($planName === '') {
                $planName = trim((string)($planUsage['plan_code'] ?? 'Plano'));
            }

            $statusKey = (string)($planUsage['subscription_status'] ?? '');
            $statusLabels = [
                'trial' => 'Trial',
                'ativo' => 'Ativo',
                'vencido' => 'Vencido',
                'bloqueado' => 'Bloqueado',
                'cancelado' => 'Cancelado',
            ];

            $userLimit = (int)($planUsage['user_limit'] ?? 0);
            $osLimit = (int)($planUsage['monthly_os_limit'] ?? 0);

            $trialText = '';
            if (!empty($planUsage['is_trial']) && !empty($planUsage['trial_end_at'])) {
                $trialEndTs = strtotime((string)$planUsage['trial_end_at']);
                if ($trialEndTs) {
                    $today = strtotime(date('Y-m-d'));
                    $trialEndDay = strtotime(date('Y-m-d', $trialEndTs));
                    $daysLeft = max(0, (int)ceil(($trialEndDay - $today) / 86400));
                    $trialText = 'Teste ate '.date('d/m/Y', $trialEndTs).' - '.($daysLeft === 1 ? '1 dia restante' : $daysLeft.' dias restantes');
                }
            }

            $planUsageCard = [
                'plan_name' => $planName,
                'status' => $statusLabels[$statusKey] ?? ($statusKey !== '' ? ucfirst($statusKey) : 'Sem status'),
                'active_users' => (int)($planUsage['active_users'] ?? 0),
                'user_limit' => $userLimit,
                'user_limit_label' => $userLimit > 0 ? (string)$userLimit : 'Ilimitado',
                'users_usage_percent' => (int)($planUsage['users_usage_percent'] ?? 0),
                'is_near_user_limit' => !empty($planUsage['is_near_user_limit']),
                'monthly_os_count' => (int)($planUsage['monthly_os_count'] ?? 0),
                'monthly_os_limit' => $osLimit,
                'monthly_os_limit_label' => $osLimit > 0 ? number_format($osLimit, 0, ',', '.') : 'Ilimitado',
                'os_usage_percent' => (int)($planUsage['os_usage_percent'] ?? 0),
                'is_near_os_limit' => !empty($planUsage['is_near_os_limit']),
                'is_trial' => !empty($planUsage['is_trial']),
                'trial_text' => $trialText,
            ];
        }
    } catch (Exception $e) {
        error_log('dashboard.php plan usage card: '.$e->getMessage());
    }
}

$onboardingCard = null;
$showOnboarding = $tid > 0
    && function_exists('isAdminLoja')
    && isAdminLoja()
    && !(function_exists('isSysAdmin') && isSysAdmin());

if ($showOnboarding) {
    try {
        $colDeletedClientes = pickColumnForTable($pdo, 'hf_clientes', ['deleted_at']);
        $colDeletedProdutos = pickColumnForTable($pdo, 'hf_produtos', ['deleted_at']);
        $colDeletedServicos = pickColumnForTable($pdo, 'hf_servicos', ['deleted_at']);
        $colDeletedOs = pickColumnForTable($pdo, 'hf_os', ['deleted_at']);

        $whereClientes = 'tenant_id = ?' . ($colDeletedClientes ? " AND `{$colDeletedClientes}` IS NULL" : '');
        $whereProdutos = 'tenant_id = ?' . ($colDeletedProdutos ? " AND `{$colDeletedProdutos}` IS NULL" : '');
        $whereServicos = 'tenant_id = ?' . ($colDeletedServicos ? " AND `{$colDeletedServicos}` IS NULL" : '');
        $whereOs = 'tenant_id = ?' . ($colDeletedOs ? " AND `{$colDeletedOs}` IS NULL" : '');

        $sqlOnboarding = "
            SELECT
              (SELECT COUNT(*) FROM hf_clientes WHERE {$whereClientes}) AS clientes_count,
              (SELECT COUNT(*) FROM hf_produtos WHERE {$whereProdutos}) AS produtos_count,
              (SELECT COUNT(*) FROM hf_servicos WHERE {$whereServicos}) AS servicos_count,
              (SELECT COUNT(*) FROM hf_os WHERE {$whereOs}) AS os_count,
              (SELECT COUNT(*) FROM hf_os WHERE {$whereOs} AND status IN ('concluida','finalizada','fechada')) AS os_concluidas_count
        ";
        $stmtOnboarding = $pdo->prepare($sqlOnboarding);
        $stmtOnboarding->execute([$tid, $tid, $tid, $tid, $tid]);
        $onboardingCounts = $stmtOnboarding->fetch(PDO::FETCH_ASSOC) ?: [];

        $onboardingSteps = [
            [
                'key' => 'cliente',
                'title' => 'Cadastrar primeiro cliente',
                'text' => 'Crie a base para abrir atendimentos com historico.',
                'icon' => 'bi-people',
                'url' => '/cliente_form.php',
                'done' => ((int)($onboardingCounts['clientes_count'] ?? 0)) > 0,
            ],
            [
                'key' => 'servico',
                'title' => 'Cadastrar primeiro servico',
                'text' => 'Padronize mao de obra, preco e garantia.',
                'icon' => 'bi-tools',
                'url' => '/servico_form.php',
                'done' => ((int)($onboardingCounts['servicos_count'] ?? 0)) > 0,
            ],
            [
                'key' => 'produto',
                'title' => 'Cadastrar primeiro produto',
                'text' => 'Organize pecas, itens e valores usados nas OS.',
                'icon' => 'bi-box-seam',
                'url' => '/produto_form.php',
                'done' => ((int)($onboardingCounts['produtos_count'] ?? 0)) > 0,
            ],
            [
                'key' => 'os',
                'title' => 'Criar primeira OS',
                'text' => 'Registre o primeiro atendimento da operacao.',
                'icon' => 'bi-clipboard2-check',
                'url' => '/os_form.php',
                'done' => ((int)($onboardingCounts['os_count'] ?? 0)) > 0,
            ],
            [
                'key' => 'os_concluida',
                'title' => 'Finalizar primeira OS',
                'text' => 'Feche o ciclo e veja o controle funcionando.',
                'icon' => 'bi-check2-circle',
                'url' => '/os_list.php',
                'done' => ((int)($onboardingCounts['os_concluidas_count'] ?? 0)) > 0,
            ],
        ];

        $completedSteps = 0;
        $nextStep = null;
        foreach ($onboardingSteps as $step) {
            if (!empty($step['done'])) {
                $completedSteps++;
            } elseif ($nextStep === null) {
                $nextStep = $step;
            }
        }

        $totalSteps = count($onboardingSteps);
        if ($completedSteps < $totalSteps && $nextStep !== null) {
            $onboardingCard = [
                'steps' => $onboardingSteps,
                'completed' => $completedSteps,
                'total' => $totalSteps,
                'progress' => (int)round(($completedSteps / max($totalSteps, 1)) * 100),
                'next' => $nextStep,
            ];
        }
    } catch (Exception $e) {
        error_log('dashboard.php onboarding: '.$e->getMessage());
    }
}

$periodLabel = date('d/m/Y', strtotime($data_ini)).' a '.date('d/m/Y', strtotime($data_fim));
?>
<?php include __DIR__.'/_layout_start.php'; ?>
<?php include __DIR__.'/_sidebar.php'; ?>

<main class="hf-content hf-dashboard-page">
  <div class="container-fluid py-4 hf-dashboard-wrap">

    <div class="hf-dashboard-top mb-3">
      <div class="hf-dashboard-title">
        <div class="hf-page-kicker">Visao geral</div>
        <h4 class="mb-0">Dashboard</h4>
        <div class="hf-page-subtitle">
          Central operacional com indicadores confiaveis do periodo.
        </div>
      </div>

      <form class="hf-dashboard-filter" method="get">
        <div class="hf-filter-field">
          <label class="form-label mb-1 small">Data inicial</label>
          <input type="date" name="data_ini" class="form-control form-control-sm" value="<?= htmlspecialchars($data_ini, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="hf-filter-field">
          <label class="form-label mb-1 small">Data final</label>
          <input type="date" name="data_fim" class="form-control form-control-sm" value="<?= htmlspecialchars($data_fim, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <input type="hidden" name="preset" value="">
        <div class="hf-preset-group">
          <button type="button" class="btn btn-sm btn-outline-secondary btn-preset" data-preset="hoje">Hoje</button>
          <button type="button" class="btn btn-sm btn-outline-secondary btn-preset" data-preset="7d">7 dias</button>
          <button type="button" class="btn btn-sm btn-outline-secondary btn-preset" data-preset="mes_atual">Mes atual</button>
          <button type="button" class="btn btn-sm btn-outline-secondary btn-preset" data-preset="mes_anterior">Mes anterior</button>
        </div>
        <div>
          <button type="submit" class="btn btn-sm btn-primary hf-btn-apply">
            <i class="bi bi-funnel me-1"></i>Aplicar
          </button>
        </div>
      </form>
    </div>

    <?php if ($onboardingCard): ?>
      <?php
        $nextStep = $onboardingCard['next'];
        $onboardingStorageKey = 'hf_onboarding_minimized_'.$tid;
      ?>
      <section class="hf-onboarding-card" data-onboarding-card data-storage-key="<?= htmlspecialchars($onboardingStorageKey, ENT_QUOTES, 'UTF-8') ?>">
        <div class="hf-onboarding-main">
          <div class="hf-onboarding-head">
            <span class="hf-onboarding-kicker">
              <i class="bi bi-stars" aria-hidden="true"></i>
              Primeiros passos
            </span>
            <button type="button" class="btn hf-onboarding-minimize" data-onboarding-minimize title="Minimizar onboarding" aria-label="Minimizar onboarding">
              <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
          </div>

          <div class="hf-onboarding-title-row">
            <div>
              <h5>Comece pelo essencial</h5>
              <p>Complete estes passos para colocar sua operacao para rodar.</p>
            </div>
            <div class="hf-onboarding-progress-number"><?= (int)$onboardingCard['progress'] ?>%</div>
          </div>

          <div class="hf-onboarding-progress" aria-label="Progresso do onboarding">
            <span style="width: <?= (int)$onboardingCard['progress'] ?>%"></span>
          </div>

          <div class="hf-onboarding-summary">
            Voce ja concluiu <strong><?= (int)$onboardingCard['completed'] ?></strong> de <strong><?= (int)$onboardingCard['total'] ?></strong> etapas.
          </div>
        </div>

        <div class="hf-onboarding-next">
          <span>Proximo passo</span>
          <strong><?= htmlspecialchars($nextStep['title'], ENT_QUOTES, 'UTF-8') ?></strong>
          <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($nextStep['url'], ENT_QUOTES, 'UTF-8') ?>">
            Continuar
            <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
          </a>
        </div>

        <div class="hf-onboarding-steps">
          <?php foreach ($onboardingCard['steps'] as $step): ?>
            <?php
              $isDone = !empty($step['done']);
              $isNext = !$isDone && $step['key'] === $nextStep['key'];
              $stepClass = 'hf-onboarding-step'
                . ($isDone ? ' is-done' : '')
                . ($isNext ? ' is-next' : '');
            ?>
            <?php if ($isDone): ?>
              <div class="<?= $stepClass ?>">
            <?php else: ?>
              <a class="<?= $stepClass ?>" href="<?= htmlspecialchars($step['url'], ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
                <span class="hf-onboarding-step-icon">
                  <i class="bi <?= $isDone ? 'bi-check-lg' : htmlspecialchars($step['icon'], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                </span>
                <span class="hf-onboarding-step-copy">
                  <strong><?= htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                  <small><?= htmlspecialchars($step['text'], ENT_QUOTES, 'UTF-8') ?></small>
                </span>
            <?php if ($isDone): ?>
              </div>
            <?php else: ?>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php if ($planUsageCard): ?>
      <section class="hf-plan-usage-card">
        <div class="hf-plan-usage-head">
          <div>
            <span class="hf-plan-usage-kicker">Uso do plano</span>
            <h5><?= htmlspecialchars($planUsageCard['plan_name'], ENT_QUOTES, 'UTF-8') ?></h5>
          </div>
          <span class="hf-plan-status <?= $planUsageCard['is_trial'] ? 'is-trial' : '' ?>">
            <?= htmlspecialchars($planUsageCard['status'], ENT_QUOTES, 'UTF-8') ?>
          </span>
        </div>

        <?php if ($planUsageCard['trial_text'] !== ''): ?>
          <div class="hf-plan-trial-note">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <?= htmlspecialchars($planUsageCard['trial_text'], ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <div class="hf-plan-usage-grid">
          <div class="hf-plan-meter <?= $planUsageCard['is_near_user_limit'] ? 'is-warning' : '' ?>">
            <div class="hf-plan-meter-top">
              <span><i class="bi bi-people" aria-hidden="true"></i> Usuarios ativos</span>
              <strong>
                <?= (int)$planUsageCard['active_users'] ?> /
                <?= htmlspecialchars($planUsageCard['user_limit_label'], ENT_QUOTES, 'UTF-8') ?>
              </strong>
            </div>
            <div class="hf-plan-meter-bar">
              <span style="width: <?= (int)$planUsageCard['users_usage_percent'] ?>%"></span>
            </div>
            <?php if ($planUsageCard['is_near_user_limit']): ?>
              <small>Voce esta perto do limite de usuarios do plano.</small>
            <?php endif; ?>
          </div>

          <div class="hf-plan-meter <?= $planUsageCard['is_near_os_limit'] ? 'is-warning' : '' ?>">
            <div class="hf-plan-meter-top">
              <span><i class="bi bi-clipboard2-check" aria-hidden="true"></i> OS neste mes</span>
              <strong>
                <?= number_format((int)$planUsageCard['monthly_os_count'], 0, ',', '.') ?> /
                <?= htmlspecialchars($planUsageCard['monthly_os_limit_label'], ENT_QUOTES, 'UTF-8') ?>
              </strong>
            </div>
            <div class="hf-plan-meter-bar">
              <span style="width: <?= (int)$planUsageCard['os_usage_percent'] ?>%"></span>
            </div>
            <?php if ($planUsageCard['is_near_os_limit']): ?>
              <small>Voce esta perto do limite mensal de OS.</small>
            <?php endif; ?>
          </div>
        </div>
      </section>
    <?php endif; ?>

    <div class="hf-section-heading">
      <div>
        <h5>Indicadores principais</h5>
        <p>Periodo: <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-md-6 col-xl-4">
        <a class="hf-card hf-kpi hf-dashboard-kpi kpi-primary" href="/os_list.php?status=aberta">
          <div class="kpi-top">
            <div class="kpi-title">OS abertas</div>
            <div class="kpi-icon"><i class="bi bi-clipboard-plus"></i></div>
          </div>
          <div class="kpi-value"><?= number_format($metrics['os_abertas'], 0, ',', '.') ?></div>
          <div class="kpi-sub">Aguardando inicio ou triagem.</div>
        </a>
      </div>

      <div class="col-12 col-md-6 col-xl-4">
        <a class="hf-card hf-kpi hf-dashboard-kpi kpi-warning" href="/os_list.php?status=em_andamento">
          <div class="kpi-top">
            <div class="kpi-title">Em andamento</div>
            <div class="kpi-icon"><i class="bi bi-tools"></i></div>
          </div>
          <div class="kpi-value"><?= number_format($metrics['os_andamento'], 0, ',', '.') ?></div>
          <div class="kpi-sub">Servicos em execucao no momento.</div>
        </a>
      </div>

      <div class="col-12 col-md-6 col-xl-4">
        <a class="hf-card hf-kpi hf-dashboard-kpi kpi-success" href="/os_list.php?status=concluida">
          <div class="kpi-top">
            <div class="kpi-title">Concluidas no periodo</div>
            <div class="kpi-icon"><i class="bi bi-check2-circle"></i></div>
          </div>
          <div class="kpi-value"><?= number_format($metrics['os_concluidas_periodo'], 0, ',', '.') ?></div>
          <div class="kpi-sub"><?= number_format($metrics['os_periodo'], 0, ',', '.') ?> OS criadas no periodo.</div>
        </a>
      </div>

      <?php if (!$isAtendenteOnly): ?>
      <div class="col-12 col-md-6 col-xl-4">
        <a class="hf-card hf-kpi hf-dashboard-kpi kpi-warning" href="/financeiro_os_lista.php?status=pendente">
          <div class="kpi-top">
            <div class="kpi-title">A receber</div>
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
          </div>
          <div class="kpi-value"><?= hfDashMoney($metrics['saldo_aberto']) ?></div>
          <div class="kpi-sub">Saldo aberto em OS nao canceladas.</div>
        </a>
      </div>

      <div class="col-12 col-md-6 col-xl-4">
        <a class="hf-card hf-kpi hf-dashboard-kpi kpi-success" href="/financeiro_os_lista.php?status=pago">
          <div class="kpi-top">
            <div class="kpi-title">Recebido</div>
            <div class="kpi-icon"><i class="bi bi-cash-coin"></i></div>
          </div>
          <div class="kpi-value"><?= hfDashMoney($metrics['recebido_periodo']) ?></div>
          <div class="kpi-sub"><?= hfDashMoney($metrics['faturamento_periodo']) ?> faturado no periodo.</div>
        </a>
      </div>
      <?php endif; ?>

      <div class="col-12 col-md-6 col-xl-4">
        <a class="hf-card hf-kpi hf-dashboard-kpi kpi-primary" href="/clientes.php">
          <div class="kpi-top">
            <div class="kpi-title">Clientes</div>
            <div class="kpi-icon"><i class="bi bi-people"></i></div>
          </div>
          <div class="kpi-value"><?= number_format($metrics['clientes'], 0, ',', '.') ?></div>
          <div class="kpi-sub">Base ativa de clientes cadastrados.</div>
        </a>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-xl-8">
        <section class="hf-card hf-attention-card">
          <div class="hf-card-head">
            <div>
              <h5>Atenção agora</h5>
              <p>Itens que merecem acompanhamento operacional.</p>
            </div>
            <?php if ($metrics['os_antigas'] > 0): ?>
              <span class="hf-soft-alert"><?= (int)$metrics['os_antigas'] ?> OS +7 dias</span>
            <?php endif; ?>
          </div>

          <div class="hf-attention-grid">
            <div>
              <h6>OS abertas há mais tempo</h6>
              <?php if ($attentionOs): ?>
                <div class="hf-attention-list">
                  <?php foreach ($attentionOs as $row): ?>
                    <?php
                      $num = (int)($row['numero'] ?? $row['id']);
                      $dataRef = !empty($row['data_ref']) ? date('d/m/Y', strtotime($row['data_ref'])) : '-';
                    ?>
                    <a class="hf-attention-item" href="/os_form.php?id=<?= (int)$row['id'] ?>">
                      <span class="hf-attention-icon"><i class="bi bi-clipboard2"></i></span>
                      <span>
                        <strong>#<?= $num ?> - <?= htmlspecialchars($row['cliente'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <small><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string)$row['status'])), ENT_QUOTES, 'UTF-8') ?> desde <?= htmlspecialchars($dataRef, ENT_QUOTES, 'UTF-8') ?></small>
                      </span>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="hf-empty-state">Nenhuma OS pendente no momento.</div>
              <?php endif; ?>
            </div>

            <div>
              <h6>Pendências financeiras</h6>
              <?php if (!$isAtendenteOnly && $attentionFinance): ?>
                <div class="hf-attention-list">
                  <?php foreach ($attentionFinance as $row): ?>
                    <?php $num = (int)($row['numero'] ?? $row['id']); ?>
                    <a class="hf-attention-item" href="/os_form.php?id=<?= (int)$row['id'] ?>">
                      <span class="hf-attention-icon is-money"><i class="bi bi-currency-dollar"></i></span>
                      <span>
                        <strong>#<?= $num ?> - <?= htmlspecialchars($row['cliente'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <small><?= hfDashMoney($row['saldo'] ?? 0) ?> em aberto</small>
                      </span>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php elseif ($isAtendenteOnly): ?>
                <div class="hf-empty-state">Financeiro restrito para este perfil.</div>
              <?php else: ?>
                <div class="hf-empty-state">Nenhum saldo em aberto.</div>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($planUsageCard && ($planUsageCard['is_near_user_limit'] || $planUsageCard['is_near_os_limit'])): ?>
            <div class="hf-plan-alert-line">
              <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
              O uso do plano esta perto do limite. Avalie o crescimento da operacao.
            </div>
          <?php endif; ?>
        </section>
      </div>

      <div class="col-12 col-xl-4">
        <section class="hf-card hf-shortcuts-card">
          <div class="hf-card-head">
            <div>
              <h5>Atalhos rápidos</h5>
              <p>Ações frequentes para ganhar tempo.</p>
            </div>
          </div>

          <div class="hf-shortcut-grid">
            <a href="/os_form.php"><i class="bi bi-plus-circle"></i><span>Nova OS</span></a>
            <a href="/cliente_form.php"><i class="bi bi-person-plus"></i><span>Novo cliente</span></a>
            <a href="/produto_form.php"><i class="bi bi-box-seam"></i><span>Novo produto</span></a>
            <a href="/servico_form.php"><i class="bi bi-tools"></i><span>Novo servico</span></a>
            <a href="/relatorios.php"><i class="bi bi-bar-chart-line"></i><span>Relatorios</span></a>
            <?php if (!$isAtendenteOnly): ?>
              <a href="/financeiro_os_lista.php"><i class="bi bi-cash-coin"></i><span>Financeiro</span></a>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </div>

  </div>
</main>

<style>
.hf-dashboard-page {
  min-height: calc(100vh - var(--topbar-h));
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .10), transparent 28rem),
    linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
}

.hf-dashboard-wrap {
  max-width: 1480px;
}

.hf-dashboard-top {
  display: grid;
  grid-template-columns: minmax(220px, 1fr) auto;
  gap: 1rem;
  align-items: start;
}

.hf-dashboard-title {
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

.hf-dashboard-filter {
  display: flex;
  flex-wrap: wrap;
  align-items: end;
  gap: .55rem;
  padding: .75rem;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .92);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
  backdrop-filter: blur(8px);
}

.hf-dashboard-filter .form-label {
  color: #64748b;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.hf-dashboard-filter .form-control,
.hf-dashboard-filter .form-select {
  min-height: 34px;
  border-radius: .65rem;
  border-color: #dbe3ee;
  background-color: #f8fafc;
}

.hf-filter-field {
  min-width: 138px;
}

.hf-preset-group {
  display: flex;
  flex-wrap: wrap;
  gap: .35rem;
  padding-bottom: .05rem;
}

.hf-preset-group .btn,
.hf-btn-apply {
  border-radius: .65rem;
  font-weight: 750;
}

.hf-onboarding-card,
.hf-plan-usage-card,
.hf-card {
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

.hf-onboarding-card {
  position: relative;
  display: grid;
  grid-template-columns: minmax(260px, 1fr) minmax(220px, 280px);
  gap: 1rem;
  margin: .35rem 0 1.2rem;
  padding: 1rem;
  overflow: hidden;
  background:
    radial-gradient(circle at 0% 0%, rgba(var(--bs-primary-rgb), .14), transparent 22rem),
    linear-gradient(135deg, rgba(255,255,255,.98), rgba(248,250,252,.94));
}

.hf-onboarding-card.is-hidden {
  display: none;
}

.hf-onboarding-main {
  min-width: 0;
}

.hf-onboarding-head,
.hf-onboarding-title-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.hf-onboarding-kicker,
.hf-plan-usage-kicker {
  display: inline-flex;
  align-items: center;
  gap: .42rem;
  color: rgba(var(--bs-primary-rgb), .92);
  font-size: .74rem;
  font-weight: 850;
  text-transform: uppercase;
  letter-spacing: .08em;
}

.hf-onboarding-minimize {
  width: 32px;
  height: 32px;
  flex: 0 0 32px;
  display: inline-grid;
  place-items: center;
  padding: 0;
  border: 1px solid rgba(148, 163, 184, .26);
  border-radius: 999px;
  color: #64748b;
  background: rgba(255,255,255,.72);
}

.hf-onboarding-title-row {
  margin-top: .55rem;
}

.hf-onboarding-title-row h5,
.hf-card-head h5 {
  margin: 0;
  color: #0f172a;
  font-size: 1.15rem;
  font-weight: 900;
}

.hf-onboarding-title-row p,
.hf-card-head p {
  margin: .18rem 0 0;
  color: #64748b;
  font-size: .92rem;
}

.hf-onboarding-progress-number {
  color: #0f172a;
  font-size: 1.45rem;
  font-weight: 950;
  line-height: 1;
}

.hf-onboarding-progress,
.hf-plan-meter-bar {
  height: 8px;
  margin-top: .9rem;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(148, 163, 184, .18);
}

.hf-onboarding-progress span,
.hf-plan-meter-bar span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--bs-primary), #16a34a);
}

.hf-onboarding-summary {
  margin-top: .65rem;
  color: #64748b;
  font-size: .9rem;
}

.hf-onboarding-summary strong {
  color: #0f172a;
}

.hf-onboarding-next {
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: .5rem;
  padding: .95rem;
  border: 1px solid rgba(var(--bs-primary-rgb), .18);
  border-radius: .95rem;
  background: rgba(255,255,255,.76);
}

.hf-onboarding-next span {
  color: #64748b;
  font-size: .72rem;
  font-weight: 850;
  text-transform: uppercase;
  letter-spacing: .07em;
}

.hf-onboarding-next strong {
  color: #0f172a;
  font-size: .98rem;
  font-weight: 900;
}

.hf-onboarding-next .btn {
  align-self: flex-start;
  border-radius: .75rem;
  font-weight: 850;
}

.hf-onboarding-steps {
  grid-column: 1 / -1;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: .65rem;
}

.hf-onboarding-step {
  min-width: 0;
  display: flex;
  gap: .65rem;
  padding: .82rem;
  border: 1px solid rgba(148, 163, 184, .20);
  border-radius: .95rem;
  color: inherit;
  text-decoration: none;
  background: rgba(255,255,255,.76);
}

.hf-onboarding-step.is-next {
  border-color: rgba(var(--bs-primary-rgb), .42);
  background: rgba(var(--bs-primary-rgb), .07);
}

.hf-onboarding-step.is-done {
  background: rgba(22, 163, 74, .08);
}

.hf-onboarding-step-icon {
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  display: grid;
  place-items: center;
  border-radius: .85rem;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .11);
}

.hf-onboarding-step.is-done .hf-onboarding-step-icon {
  color: #16a34a;
  background: rgba(22, 163, 74, .13);
}

.hf-onboarding-step-copy {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: .12rem;
}

.hf-onboarding-step-copy strong {
  color: #0f172a;
  font-size: .86rem;
  font-weight: 850;
  line-height: 1.18;
}

.hf-onboarding-step-copy small {
  color: #64748b;
  font-size: .75rem;
  line-height: 1.25;
}

.hf-plan-usage-card {
  display: grid;
  grid-template-columns: minmax(220px, .8fr) minmax(340px, 1.2fr);
  gap: 1rem;
  align-items: stretch;
  margin: .35rem 0 1.2rem;
  padding: 1rem;
}

.hf-plan-usage-head {
  min-width: 0;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: .8rem;
}

.hf-plan-usage-head h5 {
  margin: .2rem 0 0;
  color: #0f172a;
  font-size: 1.05rem;
  font-weight: 900;
}

.hf-plan-status {
  flex: 0 0 auto;
  padding: .32rem .62rem;
  border-radius: 999px;
  color: #166534;
  background: rgba(22, 163, 74, .12);
  font-size: .76rem;
  font-weight: 850;
}

.hf-plan-status.is-trial {
  color: #075985;
  background: rgba(14, 165, 233, .13);
}

.hf-plan-trial-note {
  grid-column: 1 / -1;
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  margin-top: -.35rem;
  color: #475569;
  font-size: .86rem;
  font-weight: 650;
}

.hf-plan-usage-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .8rem;
}

.hf-plan-meter {
  min-width: 0;
  padding: .85rem;
  border: 1px solid rgba(148, 163, 184, .20);
  border-radius: .9rem;
  background: #f8fafc;
}

.hf-plan-meter-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: .7rem;
}

.hf-plan-meter-top span {
  display: inline-flex;
  align-items: center;
  gap: .42rem;
  color: #64748b;
  font-size: .82rem;
  font-weight: 800;
}

.hf-plan-meter-top strong {
  flex: 0 0 auto;
  color: #0f172a;
  font-size: .92rem;
  font-weight: 900;
}

.hf-plan-meter.is-warning {
  border-color: rgba(217, 119, 6, .30);
  background: rgba(255, 251, 235, .9);
}

.hf-plan-meter.is-warning .hf-plan-meter-bar span {
  background: linear-gradient(90deg, #d97706, #f59e0b);
}

.hf-plan-meter small {
  display: block;
  margin-top: .48rem;
  color: #92400e;
  font-size: .76rem;
  font-weight: 700;
}

.hf-section-heading {
  display: flex;
  justify-content: space-between;
  align-items: end;
  margin: 1.15rem 0 .7rem;
}

.hf-section-heading h5 {
  margin: 0;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 850;
}

.hf-section-heading p {
  margin: .18rem 0 0;
  color: #64748b;
  font-size: .86rem;
}

.hf-dashboard-kpi {
  position: relative;
  min-height: 168px;
  display: block;
  padding: 1.05rem;
  overflow: hidden;
  color: inherit;
  text-decoration: none;
  transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
}

.hf-dashboard-kpi:hover {
  color: inherit;
  transform: translateY(-2px);
  box-shadow: 0 18px 42px rgba(15, 23, 42, .11);
  border-color: rgba(var(--bs-primary-rgb), .24);
}

.hf-dashboard-kpi::before {
  content: "";
  position: absolute;
  inset: 0 auto 0 0;
  width: 4px;
  background: var(--kpi-color, var(--bs-primary));
  opacity: .9;
}

.hf-dashboard-kpi .kpi-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: .75rem;
  margin-bottom: .8rem;
}

.hf-dashboard-kpi .kpi-title {
  margin: 0;
  color: #64748b;
  font-size: .76rem;
  font-weight: 850;
  text-transform: uppercase;
  letter-spacing: .055em;
}

.hf-dashboard-kpi .kpi-icon {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: grid;
  place-items: center;
  border-radius: .85rem;
  color: var(--kpi-color, var(--bs-primary));
  background: var(--kpi-soft, rgba(var(--bs-primary-rgb), .10));
  font-size: 1.15rem;
}

.hf-dashboard-kpi .kpi-value {
  color: #0f172a;
  font-size: clamp(1.45rem, 2.3vw, 1.9rem);
  font-weight: 900;
  line-height: 1.08;
  word-break: break-word;
}

.hf-dashboard-kpi .kpi-sub {
  min-height: 28px;
  margin-top: .38rem;
  color: #64748b;
  font-size: .84rem;
}

.hf-dashboard-kpi.kpi-primary {
  --kpi-color: var(--bs-primary);
  --kpi-soft: rgba(var(--bs-primary-rgb), .10);
}

.hf-dashboard-kpi.kpi-success {
  --kpi-color: #16a34a;
  --kpi-soft: rgba(22, 163, 74, .12);
}

.hf-dashboard-kpi.kpi-warning {
  --kpi-color: #d97706;
  --kpi-soft: rgba(245, 158, 11, .14);
}

.hf-dashboard-kpi.kpi-danger {
  --kpi-color: #dc2626;
  --kpi-soft: rgba(220, 38, 38, .11);
}

.hf-attention-card,
.hf-shortcuts-card {
  height: 100%;
  padding: 1rem;
}

.hf-card-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: .9rem;
}

.hf-soft-alert {
  flex: 0 0 auto;
  padding: .32rem .62rem;
  border-radius: 999px;
  color: #991b1b;
  background: rgba(239, 68, 68, .11);
  font-size: .76rem;
  font-weight: 850;
}

.hf-attention-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.hf-attention-grid h6 {
  margin: 0 0 .55rem;
  color: #0f172a;
  font-size: .86rem;
  font-weight: 850;
}

.hf-attention-list {
  display: grid;
  gap: .55rem;
}

.hf-attention-item {
  display: flex;
  align-items: center;
  gap: .65rem;
  padding: .7rem;
  border: 1px solid rgba(148, 163, 184, .18);
  border-radius: .85rem;
  color: inherit;
  text-decoration: none;
  background: #f8fafc;
}

.hf-attention-item:hover {
  color: inherit;
  border-color: rgba(var(--bs-primary-rgb), .28);
  background: #fff;
}

.hf-attention-icon {
  width: 34px;
  height: 34px;
  flex: 0 0 34px;
  display: grid;
  place-items: center;
  border-radius: .85rem;
  color: #d97706;
  background: rgba(245, 158, 11, .13);
}

.hf-attention-icon.is-money {
  color: #16a34a;
  background: rgba(22, 163, 74, .12);
}

.hf-attention-item strong {
  display: block;
  color: #0f172a;
  font-size: .84rem;
  font-weight: 850;
}

.hf-attention-item small {
  display: block;
  color: #64748b;
  font-size: .76rem;
}

.hf-empty-state {
  padding: .8rem;
  border: 1px dashed rgba(148, 163, 184, .35);
  border-radius: .85rem;
  color: #64748b;
  background: rgba(248, 250, 252, .72);
  font-size: .86rem;
}

.hf-plan-alert-line {
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-top: .9rem;
  padding: .72rem .8rem;
  border-radius: .85rem;
  color: #92400e;
  background: rgba(255, 251, 235, .95);
  font-size: .86rem;
  font-weight: 750;
}

.hf-shortcut-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: .65rem;
}

.hf-shortcut-grid a {
  min-height: 74px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  gap: .42rem;
  padding: .85rem;
  border: 1px solid rgba(148, 163, 184, .20);
  border-radius: .9rem;
  color: #0f172a;
  text-decoration: none;
  background: #f8fafc;
  font-weight: 850;
}

.hf-shortcut-grid a:hover {
  border-color: rgba(var(--bs-primary-rgb), .34);
  background: #fff;
}

.hf-shortcut-grid i {
  color: var(--bs-primary);
  font-size: 1.2rem;
}

@media (max-width: 991.98px) {
  .hf-dashboard-top,
  .hf-onboarding-card,
  .hf-plan-usage-card,
  .hf-attention-grid {
    grid-template-columns: 1fr;
  }

  .hf-dashboard-filter {
    width: 100%;
  }

  .hf-onboarding-steps {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .hf-plan-usage-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 767.98px) {
  .hf-dashboard-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .hf-dashboard-filter {
    display: grid;
    grid-template-columns: 1fr;
  }

  .hf-filter-field {
    min-width: 0;
  }

  .hf-preset-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
  }

  .hf-btn-apply,
  .hf-onboarding-next .btn {
    width: 100%;
  }

  .hf-onboarding-steps,
  .hf-shortcut-grid {
    grid-template-columns: 1fr;
  }

  .hf-plan-usage-head,
  .hf-plan-meter-top {
    align-items: flex-start;
    flex-direction: column;
  }

  .hf-dashboard-kpi {
    min-height: auto;
  }
}

[data-bs-theme="dark"] .hf-dashboard-page {
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .hf-dashboard-filter,
[data-bs-theme="dark"] .hf-card,
[data-bs-theme="dark"] .hf-onboarding-card,
[data-bs-theme="dark"] .hf-plan-usage-card {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-section-heading h5,
[data-bs-theme="dark"] .hf-card-head h5,
[data-bs-theme="dark"] .hf-dashboard-kpi .kpi-value,
[data-bs-theme="dark"] .hf-onboarding-title-row h5,
[data-bs-theme="dark"] .hf-onboarding-progress-number,
[data-bs-theme="dark"] .hf-onboarding-summary strong,
[data-bs-theme="dark"] .hf-onboarding-next strong,
[data-bs-theme="dark"] .hf-onboarding-step-copy strong,
[data-bs-theme="dark"] .hf-plan-usage-head h5,
[data-bs-theme="dark"] .hf-plan-meter-top strong,
[data-bs-theme="dark"] .hf-attention-grid h6,
[data-bs-theme="dark"] .hf-attention-item strong,
[data-bs-theme="dark"] .hf-shortcut-grid a {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-dashboard-filter .form-control,
[data-bs-theme="dark"] .hf-dashboard-filter .form-select,
[data-bs-theme="dark"] .hf-onboarding-next,
[data-bs-theme="dark"] .hf-onboarding-step,
[data-bs-theme="dark"] .hf-plan-meter,
[data-bs-theme="dark"] .hf-attention-item,
[data-bs-theme="dark"] .hf-shortcut-grid a {
  background: rgba(15, 23, 42, .72);
  border-color: rgba(148, 163, 184, .18);
}
</style>

<?php if ($onboardingCard): ?>
<script>
(function(){
  var card = document.querySelector('[data-onboarding-card]');
  if (!card) return;

  var key = card.getAttribute('data-storage-key');
  if (!key) return;

  try {
    if (localStorage.getItem(key) === '1') {
      card.classList.add('is-hidden');
    }
  } catch (e) {}

  var btn = card.querySelector('[data-onboarding-minimize]');
  if (!btn) return;

  btn.addEventListener('click', function(){
    try {
      localStorage.setItem(key, '1');
    } catch (e) {}
    card.classList.add('is-hidden');
  });
})();
</script>
<?php endif; ?>

<script>
document.querySelectorAll('.btn-preset').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var form = btn.closest('form');
    if (!form) return;
    var input = form.querySelector('input[name="preset"]');
    if (input) input.value = btn.dataset.preset || '';
    form.submit();
  });
});
</script>

<?php include __DIR__.'/_layout_end.php'; ?>
