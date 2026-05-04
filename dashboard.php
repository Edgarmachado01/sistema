<?php
require_once __DIR__.'/auth.php';
requireLogin();

require_once __DIR__.'/db.php';

$pdo = db();

// ===== PERFIL DO USUÁRIO (para restringir financeiro) =====
$roles = $_SESSION['ROLES'] ?? [];
// Atendente "puro": tem ATENDENTE e NÃO tem TENANT_ADMIN nem SYS_ADMIN
$isAtendenteOnly = hasRole('ATENDENTE') && !hasRole('TENANT_ADMIN') && !hasRole('SYS_ADMIN');

// ===== CONFIG DE ROTAS (ajuste se o nome dos arquivos for outro) =====
$urlOsLista  = 'hf_os_lista.php';
$urlFinLista = 'financeiro_os_lista.php';

// ===== Tenant (multi-empresa / por empresa) =====
$tid = function_exists('tenantId')
    ? (int) tenantId()
    : (int) ($_SESSION['tenant_id'] ?? 0);

// ===== Período do dashboard =====
$hoje        = date('Y-m-d');
$primeiroMes = date('Y-m-01');

$preset = isset($_GET['preset']) ? $_GET['preset'] : '';

$data_ini = isset($_GET['data_ini']) && $_GET['data_ini'] !== '' ? $_GET['data_ini'] : $primeiroMes;
$data_fim = isset($_GET['data_fim']) && $_GET['data_fim'] !== '' ? $_GET['data_fim'] : $hoje;

// Aplica presets rápidos
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

// Normaliza datas
if ($data_ini > $data_fim) {
    $tmp      = $data_ini;
    $data_ini = $data_fim;
    $data_fim = $tmp;
}

// ===== Filtro por técnico =====
$tecnico_id = isset($_GET['tecnico']) ? (int) $_GET['tecnico'] : 0;

// ================= FUNÇÕES AUXILIARES =================

function pickColumnForTable(PDO $pdo, $table, array $candidates) {
    if (empty($candidates)) return null;

    $in = implode("','", array_map('strval', $candidates));

    $sql = "
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = :table
          AND COLUMN_NAME IN ('{$in}')
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':table' => $table]);
    $col  = $stmt->fetchColumn();

    return $col ?: null;
}

function calc_change($current, $previous) {
    if ($previous <= 0) return null;
    $change = (($current - $previous) / $previous) * 100;
    return round($change, 1);
}

function money_br($v) {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

// =================== BLOCO OS (hf_os) ===================

$tableOs = 'hf_os';

$colDataAbertura   = pickColumnForTable($pdo, $tableOs, ['data_abertura', 'data_os', 'dt_abertura', 'created_at', 'data_criacao']);
$colDataFechamento = pickColumnForTable($pdo, $tableOs, ['data_fechamento', 'dt_fechamento', 'closed_at', 'data_conclusao', 'data_encerramento']);
$colStatus         = pickColumnForTable($pdo, $tableOs, ['status', 'situacao', 'status_os', 'situacao_os']);
$colTenantOs       = pickColumnForTable($pdo, $tableOs, ['tenant_id', 'id_tenant', 'empresa_id']);
$colTecnicoOs      = pickColumnForTable($pdo, $tableOs, ['tecnico_id','id_tecnico','tecnico','responsavel_id','user_id','usuario_id']);

$whereBaseOs  = " FROM {$tableOs} WHERE 1=1 ";
$paramsBaseOs = [];

if ($colTenantOs && $tid > 0) {
    $whereBaseOs            .= " AND `{$colTenantOs}` = :tid ";
    $paramsBaseOs[':tid']    = $tid;
}

$usaPeriodoOs = false;
if ($colDataAbertura) {
    $whereBaseOs            .= " AND `{$colDataAbertura}` BETWEEN :ini AND :fim ";
    $paramsBaseOs[':ini']    = $data_ini;
    $paramsBaseOs[':fim']    = $data_fim;
    $usaPeriodoOs            = true;
}

if ($colTecnicoOs && $tecnico_id > 0) {
    $whereBaseOs               .= " AND `{$colTecnicoOs}` = :tec ";
    $paramsBaseOs[':tec']       = $tecnico_id;
}

// === Lista de técnicos para o filtro (vêm de users) ===
$tecnicos = [];
$colUserName = pickColumnForTable($pdo, 'users', ['name','nome','full_name']);
if ($colTecnicoOs && $colUserName) {
    $sqlTec = "
        SELECT DISTINCT u.id, u.`{$colUserName}` AS nome
        FROM {$tableOs} o
        JOIN users u ON o.`{$colTecnicoOs}` = u.id
        WHERE 1=1
    ";
    $paramsTec = [];
    if ($colTenantOs && $tid > 0) {
        $sqlTec          .= " AND o.`{$colTenantOs}` = :tid ";
        $paramsTec[':tid'] = $tid;
    }
    $sqlTec .= " ORDER BY nome ";

    $stmt = $pdo->prepare($sqlTec);
    $stmt->execute($paramsTec);
    $tecnicos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Total OS
$sqlTotalOs = "SELECT COUNT(*) " . $whereBaseOs;
$stmt = $pdo->prepare($sqlTotalOs);
$stmt->execute($paramsBaseOs);
$total_os = (int) $stmt->fetchColumn();

// Pendentes
$pendentes = 0;
if ($colStatus) {
    $statusPendentes = ['aberta','em_andamento','aguardando_aprovacao','aguardando_peca','pendente'];

    $placeholders = [];
    $paramsPend   = $paramsBaseOs;
    foreach ($statusPendentes as $idx => $st) {
        $key = ":st_pend_{$idx}";
        $placeholders[]    = $key;
        $paramsPend[$key]  = $st;
    }

    $sqlPend = "SELECT COUNT(*) " . $whereBaseOs . " AND `{$colStatus}` IN (" . implode(',', $placeholders) . ")";
    $stmt = $pdo->prepare($sqlPend);
    $stmt->execute($paramsPend);
    $pendentes = (int) $stmt->fetchColumn();
}

// Concluídas
$concluidas = 0;
if ($colStatus) {
    $statusConcluidas = ['concluida','finalizada','entregue','fechada'];

    $placeholders = [];
    $paramsConc   = $paramsBaseOs;
    foreach ($statusConcluidas as $idx => $st) {
        $key = ":st_conc_{$idx}";
        $placeholders[]    = $key;
        $paramsConc[$key]  = $st;
    }

    $sqlConc = "SELECT COUNT(*) " . $whereBaseOs . " AND `{$colStatus}` IN (" . implode(',', $placeholders) . ")";
    $stmt = $pdo->prepare($sqlConc);
    $stmt->execute($paramsConc);
    $concluidas = (int) $stmt->fetchColumn();
}

// SLA médio
$sla_medio = 0.0;
if ($colDataAbertura && $colDataFechamento && $colStatus) {
    $sqlSla = "
        SELECT AVG(DATEDIFF(
            COALESCE(`{$colDataFechamento}`, :hoje),
            `{$colDataAbertura}`
        )) " . $whereBaseOs . "
          AND `{$colStatus}` IN ('concluida','finalizada','entregue','fechada')
    ";

    $paramsSla = $paramsBaseOs;
    $paramsSla[':hoje'] = $hoje;

    $stmt = $pdo->prepare($sqlSla);
    $stmt->execute($paramsSla);
    $val = $stmt->fetchColumn();

    if ($val !== null) {
        $sla_medio = (float) $val;
    }
}

// Período anterior (para comparação)
$total_os_prev   = null;
$pendentes_prev  = null;
$concluidas_prev = null;

if ($usaPeriodoOs && $colDataAbertura) {
    $dias = (int) round((strtotime($data_fim) - strtotime($data_ini)) / 86400);
    if ($dias < 0) $dias = 0;

    $prev_fim = date('Y-m-d', strtotime($data_ini . ' -1 day'));
    $prev_ini = date('Y-m-d', strtotime($prev_fim . ' -' . $dias . ' day'));

    $wherePrevOs  = " FROM {$tableOs} WHERE 1=1 ";
    $paramsPrevOs = [];

    if ($colTenantOs && $tid > 0) {
        $wherePrevOs             .= " AND `{$colTenantOs}` = :tid ";
        $paramsPrevOs[':tid']     = $tid;
    }

    $wherePrevOs              .= " AND `{$colDataAbertura}` BETWEEN :ini AND :fim ";
    $paramsPrevOs[':ini']      = $prev_ini;
    $paramsPrevOs[':fim']      = $prev_fim;

    if ($colTecnicoOs && $tecnico_id > 0) {
        $wherePrevOs               .= " AND `{$colTecnicoOs}` = :tec ";
        $paramsPrevOs[':tec']       = $tecnico_id;
    }

    // total anterior
    $sqlTotalPrev = "SELECT COUNT(*) " . $wherePrevOs;
    $stmt = $pdo->prepare($sqlTotalPrev);
    $stmt->execute($paramsPrevOs);
    $total_os_prev = (int) $stmt->fetchColumn();

    if ($colStatus) {
        // pendentes anterior
        $statusPendentes = ['aberta','em_andamento','aguardando_aprovacao','aguardando_peca','pendente'];
        $placeholders = [];
        $paramsPendPrev = $paramsPrevOs;
        foreach ($statusPendentes as $idx => $st) {
            $key = ":st_pend_prev_{$idx}";
            $placeholders[]          = $key;
            $paramsPendPrev[$key]    = $st;
        }
        $sqlPendPrev = "SELECT COUNT(*) " . $wherePrevOs . " AND `{$colStatus}` IN (" . implode(',', $placeholders) . ")";
        $stmt = $pdo->prepare($sqlPendPrev);
        $stmt->execute($paramsPendPrev);
        $pendentes_prev = (int) $stmt->fetchColumn();

        // concluídas anterior
        $statusConcluidas = ['concluida','finalizada','entregue','fechada'];
        $placeholders = [];
        $paramsConcPrev = $paramsPrevOs;
        foreach ($statusConcluidas as $idx => $st) {
            $key = ":st_conc_prev_{$idx}";
            $placeholders[]          = $key;
            $paramsConcPrev[$key]    = $st;
        }
        $sqlConcPrev = "SELECT COUNT(*) " . $wherePrevOs . " AND `{$colStatus}` IN (" . implode(',', $placeholders) . ")";
        $stmt = $pdo->prepare($sqlConcPrev);
        $stmt->execute($paramsConcPrev);
        $concluidas_prev = (int) $stmt->fetchColumn();
    }
}

// Percentuais OS
$perc_pendentes  = ($total_os > 0) ? round(($pendentes  / max($total_os, 1)) * 100) : 0;
$perc_concluidas = ($total_os > 0) ? round(($concluidas / max($total_os, 1)) * 100) : 0;

$meta_sla      = 3.0;
$perc_sla      = $sla_medio > 0 ? min(100, round(($sla_medio / $meta_sla) * 100)) : 0;
$sla_formatado = $sla_medio > 0 ? number_format($sla_medio, 1, ',', '.') . 'd' : '--';

// Variação vs período anterior
$var_total_os   = ($total_os_prev   !== null) ? calc_change($total_os,   $total_os_prev)   : null;
$var_pendentes  = ($pendentes_prev  !== null) ? calc_change($pendentes,  $pendentes_prev)  : null;
$var_concluidas = ($concluidas_prev !== null) ? calc_change($concluidas, $concluidas_prev) : null;

// =================== BLOCO FINANCEIRO (os_financeiro) ===================
// Só carrega se NÃO for atendente-only
$tableFin        = 'os_financeiro';
$colFinTenant    = null;
$colFinValor     = null;
$colFinVenc      = null;
$colFinPagto     = null;
$colFinStatus    = null;
$total_previsto  = 0.0;
$total_recebido  = 0.0;
$total_em_aberto = 0.0;
$total_atrasado  = 0.0;
$perc_recebido   = 0;
$labelsJs        = [];
$osJs            = [];
$recJs           = [];

if (!$isAtendenteOnly) {

    $colFinTenant = pickColumnForTable($pdo, $tableFin, ['tenant_id', 'id_tenant', 'empresa_id']);
    $colFinValor  = pickColumnForTable($pdo, $tableFin, ['valor', 'valor_total', 'valor_bruto', 'vl_titulo']);
    $colFinVenc   = pickColumnForTable($pdo, $tableFin, ['data_vencimento', 'dt_vencimento', 'vencimento', 'dt_vencto']);
    $colFinPagto  = pickColumnForTable($pdo, $tableFin, ['data_pagamento', 'dt_pagamento', 'pagamento', 'dt_pgto']);
    $colFinStatus = pickColumnForTable($pdo, $tableFin, ['status', 'situacao', 'status_titulo']);

    if ($colFinValor && $colFinVenc) {
        $whereFin  = " FROM {$tableFin} WHERE 1=1 ";
        $paramsFin = [];

        if ($colFinTenant && $tid > 0) {
            $whereFin             .= " AND `{$colFinTenant}` = :tid ";
            $paramsFin[':tid']     = $tid;
        }

        // Período do financeiro baseado no vencimento
        $whereFin              .= " AND `{$colFinVenc}` BETWEEN :ini AND :fim ";
        $paramsFin[':ini']      = $data_ini;
        $paramsFin[':fim']      = $data_fim;

        // Total previsto no período
        $sqlPrev = "SELECT SUM(`{$colFinValor}`) " . $whereFin;
        $stmt = $pdo->prepare($sqlPrev);
        $stmt->execute($paramsFin);
        $total_previsto = (float) ($stmt->fetchColumn() ?: 0);

        // Total recebido (data_pagamento no período)
        if ($colFinPagto) {
            $whereFinRec  = " FROM {$tableFin} WHERE 1=1 ";
            $paramsFinRec = [];

            if ($colFinTenant && $tid > 0) {
                $whereFinRec              .= " AND `{$colFinTenant}` = :tid ";
                $paramsFinRec[':tid']      = $tid;
            }

            $whereFinRec               .= " AND `{$colFinPagto}` BETWEEN :ini AND :fim ";
            $paramsFinRec[':ini']       = $data_ini;
            $paramsFinRec[':fim']       = $data_fim;

            if ($colFinStatus) {
                $whereFinRec .= " AND `{$colFinStatus}` IN ('pago','liquidado','baixado') ";
            }

            $sqlRec = "SELECT SUM(`{$colFinValor}`) " . $whereFinRec;
            $stmt = $pdo->prepare($sqlRec);
            $stmt->execute($paramsFinRec);
            $total_recebido = (float) ($stmt->fetchColumn() ?: 0);
        }

        // Em aberto
        $whereFinAberto  = $whereFin;
        $whereFinAberto .= " AND (`{$colFinPagto}` IS NULL ";
        if ($colFinStatus) {
            $whereFinAberto .= " OR `{$colFinStatus}` NOT IN ('pago','liquidado','baixado')";
        }
        $whereFinAberto .= ")";

        $sqlAberto = "SELECT SUM(`{$colFinValor}`) " . $whereFinAberto;
        $stmt = $pdo->prepare($sqlAberto);
        $stmt->execute($paramsFin);
        $total_em_aberto = (float) ($stmt->fetchColumn() ?: 0);

        // Atrasado
        $whereFinAtraso  = " FROM {$tableFin} WHERE 1=1 ";
        $paramsFinAtr    = [];

        if ($colFinTenant && $tid > 0) {
            $whereFinAtraso            .= " AND `{$colFinTenant}` = :tid ";
            $paramsFinAtr[':tid']       = $tid;
        }

        $whereFinAtraso              .= " AND `{$colFinVenc}` < :hoje ";
        $paramsFinAtr[':hoje']        = $hoje;

        $whereFinAtraso .= " AND (`{$colFinPagto}` IS NULL ";
        if ($colFinStatus) {
            $whereFinAtraso .= " OR `{$colFinStatus}` NOT IN ('pago','liquidado','baixado')";
        }
        $whereFinAtraso .= ")";

        $sqlAtr = "SELECT SUM(`{$colFinValor}`) " . $whereFinAtraso;
        $stmt = $pdo->prepare($sqlAtr);
        $stmt->execute($paramsFinAtr);
        $total_atrasado = (float) ($stmt->fetchColumn() ?: 0);

        // % recebido
        if ($total_previsto > 0) {
            $perc_recebido = round(($total_recebido / $total_previsto) * 100);
        }
    }

    // ======= Dados para gráfico OS x Recebido =======
    $labels      = [];
    $osPorDia    = [];
    $recPorDia   = [];

    $iniTime = strtotime($data_ini);
    $fimTime = strtotime($data_fim);

    if ($iniTime && $fimTime && $iniTime <= $fimTime) {
        for ($t = $iniTime; $t <= $fimTime; $t += 86400) {
            $dia = date('Y-m-d', $t);
            $labels[]    = $dia;
            $osPorDia[$dia]  = 0;
            $recPorDia[$dia] = 0.0;
        }
    }

    // OS por dia (data de abertura)
    if ($colDataAbertura) {
        $whereGrafOs  = " FROM {$tableOs} WHERE 1=1 ";
        $paramsGrafOs = [];

        if ($colTenantOs && $tid > 0) {
            $whereGrafOs             .= " AND `{$colTenantOs}` = :tid ";
            $paramsGrafOs[':tid']     = $tid;
        }
        $whereGrafOs              .= " AND `{$colDataAbertura}` BETWEEN :ini AND :fim ";
        $paramsGrafOs[':ini']      = $data_ini;
        $paramsGrafOs[':fim']      = $data_fim;

        if ($colTecnicoOs && $tecnico_id > 0) {
            $whereGrafOs               .= " AND `{$colTecnicoOs}` = :tec ";
            $paramsGrafOs[':tec']       = $tecnico_id;
        }

        $sqlGrafOs = "SELECT DATE(`{$colDataAbertura}`) AS dia, COUNT(*) AS qtd " . $whereGrafOs . " GROUP BY dia ORDER BY dia";
        $stmt = $pdo->prepare($sqlGrafOs);
        $stmt->execute($paramsGrafOs);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $d = $row['dia'];
            if (isset($osPorDia[$d])) {
                $osPorDia[$d] = (int)$row['qtd'];
            }
        }
    }

    // Recebido por dia (data_pagamento)
    if ($colFinValor && $colFinPagto) {
        $whereGrafFin  = " FROM {$tableFin} WHERE 1=1 ";
        $paramsGrafFin = [];

        if ($colFinTenant && $tid > 0) {
            $whereGrafFin             .= " AND `{$colFinTenant}` = :tid ";
            $paramsGrafFin[':tid']     = $tid;
        }
        $whereGrafFin              .= " AND `{$colFinPagto}` BETWEEN :ini AND :fim ";
        $paramsGrafFin[':ini']      = $data_ini;
        $paramsGrafFin[':fim']      = $data_fim;

        if ($colFinStatus) {
            $whereGrafFin .= " AND `{$colFinStatus}` IN ('pago','liquidado','baixado') ";
        }

        $sqlGrafFin = "SELECT DATE(`{$colFinPagto}`) AS dia, SUM(`{$colFinValor}`) AS total " . $whereGrafFin . " GROUP BY dia ORDER BY dia";
        $stmt = $pdo->prepare($sqlGrafFin);
        $stmt->execute($paramsGrafFin);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $d = $row['dia'];
            if (isset($recPorDia[$d])) {
                $recPorDia[$d] = (float)$row['total'];
            }
        }
    }

    // Arrays finais pro JS
    $labelsJs   = array_map(function($d){ return date('d/m', strtotime($d)); }, $labels);
    $osJs       = array_values($osPorDia);
    $recJs      = array_values($recPorDia);
}

// ===== URLs dos cards (mantém filtros atuais) =====
$queryBase = [
    'data_ini' => $data_ini,
    'data_fim' => $data_fim,
];
if ($tecnico_id > 0) {
    $queryBase['tecnico'] = $tecnico_id;
}

$urlPendentes  = $urlOsLista . '?' . http_build_query($queryBase + ['filtro_status' => 'pendentes']);
$urlConcluidas = $urlOsLista . '?' . http_build_query($queryBase + ['filtro_status' => 'concluidas']);
$urlTotalOs    = $urlOsLista . '?' . http_build_query($queryBase + ['filtro_status' => 'todas']);

$urlFinPrevisto  = $urlFinLista . '?' . http_build_query($queryBase + ['filtro' => 'previsto']);
$urlFinRecebido  = $urlFinLista . '?' . http_build_query($queryBase + ['filtro' => 'recebido']);
$urlFinAberto    = $urlFinLista . '?' . http_build_query($queryBase + ['filtro' => 'aberto']);
$urlFinAtrasado  = $urlFinLista . '?' . http_build_query($queryBase + ['filtro' => 'atrasado']);

?>
<?php include __DIR__.'/_layout_start.php'; ?>
<?php include __DIR__.'/_sidebar.php'; ?>

<main class="hf-content">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0">Dashboard</h4>

    <!-- Filtro de período + técnico -->
    <form class="d-flex flex-wrap gap-2 align-items-end" method="get">
      <div>
        <label class="form-label mb-1 small">Data inicial</label>
        <input type="date" name="data_ini" class="form-control form-control-sm"
               value="<?php echo htmlspecialchars($data_ini); ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">Data final</label>
        <input type="date" name="data_fim" class="form-control form-control-sm"
               value="<?php echo htmlspecialchars($data_fim); ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">Técnico</label>
        <select name="tecnico" class="form-select form-select-sm">
          <option value="0">Todos</option>
          <?php foreach ($tecnicos as $tec): ?>
            <option value="<?php echo (int)$tec['id']; ?>" <?php echo $tecnico_id == (int)$tec['id'] ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($tec['nome']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <input type="hidden" name="preset" value="">
      <div class="d-flex gap-1 mb-1">
        <button type="button" class="btn btn-sm btn-outline-secondary btn-preset" data-preset="hoje">Hoje</button>
        <button type="button" class="btn btn-sm btn-outline-secondary btn-preset" data-preset="7d">7 dias</button>
        <button type="button" class="btn btn-sm btn-outline-secondary btn-preset" data-preset="mes_atual">Mês atual</button>
        <button type="button" class="btn btn-sm btn-outline-secondary btn-preset" data-preset="mes_anterior">Mês anterior</button>
      </div>
      <div>
        <button type="submit" class="btn btn-sm btn-primary">
          <i class="bi bi-funnel"></i> Aplicar
        </button>
      </div>
    </form>
  </div>

  <!-- ===== LINHA 1: OS ===== -->
  <div class="row g-3 mb-3">
    <!-- Total OS -->
    <div class="col-12 col-md-6 col-lg-3">
      <div class="hf-card hf-kpi"
           style="cursor:pointer"
           onclick="window.location='<?php echo htmlspecialchars($urlTotalOs); ?>'">
        <div class="kpi-title">Total OS</div>
        <div class="kpi-row">
          <div class="kpi-icon"><i class="bi bi-clipboard-data"></i></div>
          <div>
            <div class="kpi-value">
              <?php echo number_format($total_os, 0, ',', '.'); ?>
            </div>
            <div class="kpi-sub">
              <?php echo $usaPeriodoOs ? 'no período' : 'todas as OS'; ?>
              <?php if ($var_total_os !== null): ?>
                  <span class="ms-1 small <?php echo $var_total_os >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <i class="bi bi-arrow-<?php echo $var_total_os >= 0 ? 'up' : 'down'; ?>-short"></i>
                    <?php echo ($var_total_os >= 0 ? '+' : '').$var_total_os; ?>%
                  </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="hf-progress">
          <div class="bar" style="width:100%"></div>
        </div>
      </div>
    </div>

    <!-- Pendentes -->
    <div class="col-12 col-md-6 col-lg-3">
      <div class="hf-card hf-kpi kpi-danger"
           style="cursor:pointer"
           onclick="window.location='<?php echo htmlspecialchars($urlPendentes); ?>'">
        <div class="kpi-title">Pendentes</div>
        <div class="kpi-row">
          <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
          <div>
            <div class="kpi-value">
              <?php echo number_format($pendentes, 0, ',', '.'); ?>
            </div>
            <div class="kpi-sub">
              <?php
              if ($total_os > 0) {
                  echo $perc_pendentes.'% das OS';
              } else {
                  echo 'sem OS no período';
              }
              ?>
              <?php if ($var_pendentes !== null): ?>
                  <span class="ms-1 small <?php echo $var_pendentes <= 0 ? 'text-success' : 'text-danger'; ?>">
                    <i class="bi bi-arrow-<?php echo $var_pendentes <= 0 ? 'down' : 'up'; ?>-short"></i>
                    <?php echo ($var_pendentes >= 0 ? '+' : '').$var_pendentes; ?>%
                  </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="hf-progress">
          <div class="bar" style="width:<?php echo $perc_pendentes; ?>%"></div>
        </div>
      </div>
    </div>

    <!-- Concluídas -->
    <div class="col-12 col-md-6 col-lg-3">
      <div class="hf-card hf-kpi kpi-success"
           style="cursor:pointer"
           onclick="window.location='<?php echo htmlspecialchars($urlConcluidas); ?>'">
        <div class="kpi-title">Concluídas</div>
        <div class="kpi-row">
          <div class="kpi-icon"><i class="bi bi-check2-circle"></i></div>
          <div>
            <div class="kpi-value">
              <?php echo number_format($concluidas, 0, ',', '.'); ?>
            </div>
            <div class="kpi-sub">
              <?php
              if ($total_os > 0) {
                  echo $perc_concluidas.'% das OS';
              } else {
                  echo 'sem OS no período';
              }
              ?>
              <?php if ($var_concluidas !== null): ?>
                  <span class="ms-1 small <?php echo $var_concluidas >= 0 ? 'text-success' : 'text-danger'; ?>">
                    <i class="bi bi-arrow-<?php echo $var_concluidas >= 0 ? 'up' : 'down'; ?>-short"></i>
                    <?php echo ($var_concluidas >= 0 ? '+' : '').$var_concluidas; ?>%
                  </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="hf-progress">
          <div class="bar" style="width:<?php echo $perc_concluidas; ?>%"></div>
        </div>
      </div>
    </div>

    <!-- SLA -->
    <div class="col-12 col-md-6 col-lg-3">
      <div class="hf-card hf-kpi kpi-warning">
        <div class="kpi-title">SLA médio</div>
        <div class="kpi-row">
          <div class="kpi-icon"><i class="bi bi-speedometer2"></i></div>
          <div>
            <div class="kpi-value">
              <?php echo $sla_formatado; ?>
            </div>
            <div class="kpi-sub">tempo médio de atendimento</div>
          </div>
        </div>
        <div class="hf-progress">
          <div class="bar" style="width:<?php echo $perc_sla; ?>%"></div>
        </div>
      </div>
    </div>
  </div>

  <?php if (!$isAtendenteOnly): ?>
  <!-- ===== LINHA 2: FINANCEIRO ===== -->
  <div class="row g-3 mb-4">
    <!-- Total previsto -->
    <div class="col-12 col-md-6 col-lg-3">
      <div class="hf-card hf-kpi"
           style="cursor:pointer"
           onclick="window.location='<?php echo htmlspecialchars($urlFinPrevisto); ?>'">
        <div class="kpi-title">Previsto no período</div>
        <div class="kpi-row">
          <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
          <div>
            <div class="kpi-value">
              <?php echo money_br($total_previsto); ?>
            </div>
            <div class="kpi-sub">títulos com vencimento no período</div>
          </div>
        </div>
        <div class="hf-progress">
          <div class="bar" style="width:100%"></div>
        </div>
      </div>
    </div>

    <!-- Recebido -->
    <div class="col-12 col-md-6 col-lg-3">
      <div class="hf-card hf-kpi kpi-success"
           style="cursor:pointer"
           onclick="window.location='<?php echo htmlspecialchars($urlFinRecebido); ?>'">
        <div class="kpi-title">Recebido</div>
        <div class="kpi-row">
          <div class="kpi-icon"><i class="bi bi-bank"></i></div>
          <div>
            <div class="kpi-value">
              <?php echo money_br($total_recebido); ?>
            </div>
            <div class="kpi-sub">
              <?php
              if ($total_previsto > 0) {
                  echo $perc_recebido.'% do previsto';
              } else {
                  echo 'sem previsão no período';
              }
              ?>
            </div>
          </div>
        </div>
        <div class="hf-progress">
          <div class="bar" style="width:<?php echo $perc_recebido; ?>%"></div>
        </div>
      </div>
    </div>

    <!-- Em aberto -->
    <div class="col-12 col-md-6 col-lg-3">
      <div class="hf-card hf-kpi kpi-warning"
           style="cursor:pointer"
           onclick="window.location='<?php echo htmlspecialchars($urlFinAberto); ?>'">
        <div class="kpi-title">Em aberto</div>
        <div class="kpi-row">
          <div class="kpi-icon"><i class="bi bi-file-earmark-text"></i></div>
          <div>
            <div class="kpi-value">
              <?php echo money_br($total_em_aberto); ?>
            </div>
            <div class="kpi-sub">a receber no período</div>
          </div>
        </div>
        <div class="hf-progress">
          <?php
          $perc_aberto = ($total_previsto > 0) ? round(($total_em_aberto / $total_previsto) * 100) : 0;
          ?>
          <div class="bar" style="width:<?php echo $perc_aberto; ?>%"></div>
        </div>
      </div>
    </div>

    <!-- Atrasado -->
    <div class="col-12 col-md-6 col-lg-3">
      <div class="hf-card hf-kpi kpi-danger"
           style="cursor:pointer"
           onclick="window.location='<?php echo htmlspecialchars($urlFinAtrasado); ?>'">
        <div class="kpi-title">Atrasado</div>
        <div class="kpi-row">
          <div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
          <div>
            <div class="kpi-value">
              <?php echo money_br($total_atrasado); ?>
            </div>
            <div class="kpi-sub">vencido e não pago</div>
          </div>
        </div>
        <div class="hf-progress">
          <?php
          $baseAtr = max($total_previsto, $total_em_aberto, 1);
          $perc_atrasado = round(($total_atrasado / $baseAtr) * 100);
          ?>
          <div class="bar" style="width:<?php echo $perc_atrasado; ?>%"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== GRÁFICO OS x RECEBIDO ===== -->
  <div class="hf-card">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="mb-0">OS x Recebido no período</h6>
      <small class="text-muted">
        <?php echo htmlspecialchars(date('d/m/Y', strtotime($data_ini)) . ' a ' . date('d/m/Y', strtotime($data_fim))); ?>
        <?php if ($tecnico_id > 0): ?>
          • Técnico filtrado
        <?php endif; ?>
      </small>
    </div>
    <canvas id="graficoOsFin" style="max-height:320px;"></canvas>
  </div>
  <?php endif; // !$isAtendenteOnly ?>
</main>

<?php include __DIR__.'/_layout_end.php'; ?>

<?php if (!$isAtendenteOnly): ?>
<!-- Chart.js + presets JS (somente para quem pode ver financeiro) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.querySelectorAll('.btn-preset').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var form = btn.closest('form');
    form.querySelector('input[name="preset"]').value = btn.dataset.preset;
    form.submit();
  });
});

(function() {
  var ctx = document.getElementById('graficoOsFin');
  if (!ctx) return;

  var labels = <?php echo json_encode($labelsJs); ?>;
  var osData = <?php echo json_encode($osJs, JSON_NUMERIC_CHECK); ?>;
  var recData = <?php echo json_encode($recJs, JSON_NUMERIC_CHECK); ?>;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        {
          type: 'bar',
          label: 'Qtd OS',
          data: osData,
          yAxisID: 'y',
        },
        {
          type: 'line',
          label: 'Recebido',
          data: recData,
          yAxisID: 'y1',
        }
      ]
    },
    options: {
      responsive: true,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      stacked: false,
      plugins: {
        legend: {
          display: true
        },
      },
      scales: {
        y: {
          type: 'linear',
          position: 'left',
          title: { display: true, text: 'OS' },
          ticks: { precision: 0 }
        },
        y1: {
          type: 'linear',
          position: 'right',
          title: { display: true, text: 'Recebido (R$)' },
          grid: { drawOnChartArea: false }
        }
      }
    }
  });
})();
</script>
<?php else: ?>
<script>
document.querySelectorAll('.btn-preset').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var form = btn.closest('form');
    form.querySelector('input[name="preset"]').value = btn.dataset.preset;
    form.submit();
  });
});
</script>
<?php endif; ?>
