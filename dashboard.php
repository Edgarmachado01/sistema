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

// ===== Onboarding inicial do tenant =====
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
        $colDeletedOs       = pickColumnForTable($pdo, 'hf_os', ['deleted_at']);

        $whereClientes = 'tenant_id = ?' . ($colDeletedClientes ? " AND `{$colDeletedClientes}` IS NULL" : '');
        $whereProdutos = 'tenant_id = ?' . ($colDeletedProdutos ? " AND `{$colDeletedProdutos}` IS NULL" : '');
        $whereServicos = 'tenant_id = ?' . ($colDeletedServicos ? " AND `{$colDeletedServicos}` IS NULL" : '');
        $whereOs       = 'tenant_id = ?' . ($colDeletedOs ? " AND `{$colDeletedOs}` IS NULL" : '');

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
                'text' => 'Crie a base para abrir atendimentos com histórico.',
                'icon' => 'bi-people',
                'url' => '/cliente_form.php',
                'done' => ((int)($onboardingCounts['clientes_count'] ?? 0)) > 0,
            ],
            [
                'key' => 'servico',
                'title' => 'Cadastrar primeiro serviço',
                'text' => 'Padronize mão de obra, preço e garantia.',
                'icon' => 'bi-tools',
                'url' => '/servico_form.php',
                'done' => ((int)($onboardingCounts['servicos_count'] ?? 0)) > 0,
            ],
            [
                'key' => 'produto',
                'title' => 'Cadastrar primeiro produto',
                'text' => 'Organize peças, itens e valores usados nas OS.',
                'icon' => 'bi-box-seam',
                'url' => '/produto_form.php',
                'done' => ((int)($onboardingCounts['produtos_count'] ?? 0)) > 0,
            ],
            [
                'key' => 'os',
                'title' => 'Criar primeira OS',
                'text' => 'Registre o primeiro atendimento da operação.',
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

?>
<?php include __DIR__.'/_layout_start.php'; ?>
<?php include __DIR__.'/_sidebar.php'; ?>

<main class="hf-content hf-dashboard-page">
  <div class="container-fluid py-4 hf-dashboard-wrap">

    <div class="hf-dashboard-top mb-3">
      <div class="hf-dashboard-title">
        <div class="hf-page-kicker">Visão geral</div>
        <h4 class="mb-0">Dashboard</h4>
        <div class="hf-page-subtitle">
          Acompanhe ordens de serviço, SLA e indicadores financeiros do período.
        </div>
      </div>

      <!-- Filtro de período + técnico -->
      <form class="hf-dashboard-filter" method="get">
        <div class="hf-filter-field">
          <label class="form-label mb-1 small">Data inicial</label>
          <input type="date" name="data_ini" class="form-control form-control-sm"
                 value="<?php echo htmlspecialchars($data_ini); ?>">
        </div>
        <div class="hf-filter-field">
          <label class="form-label mb-1 small">Data final</label>
          <input type="date" name="data_fim" class="form-control form-control-sm"
                 value="<?php echo htmlspecialchars($data_fim); ?>">
        </div>
        <div class="hf-filter-field">
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
        <div class="hf-preset-group">
          <button type="button" class="btn btn-sm btn-outline-secondary btn-preset" data-preset="hoje">Hoje</button>
          <button type="button" class="btn btn-sm btn-outline-secondary btn-preset" data-preset="7d">7 dias</button>
          <button type="button" class="btn btn-sm btn-outline-secondary btn-preset" data-preset="mes_atual">Mês atual</button>
          <button type="button" class="btn btn-sm btn-outline-secondary btn-preset" data-preset="mes_anterior">Mês anterior</button>
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
              <p>Complete estes passos para colocar sua operação para rodar.</p>
            </div>
            <div class="hf-onboarding-progress-number"><?= (int)$onboardingCard['progress'] ?>%</div>
          </div>

          <div class="hf-onboarding-progress" aria-label="Progresso do onboarding">
            <span style="width: <?= (int)$onboardingCard['progress'] ?>%"></span>
          </div>

          <div class="hf-onboarding-summary">
            Você já concluiu <strong><?= (int)$onboardingCard['completed'] ?></strong> de <strong><?= (int)$onboardingCard['total'] ?></strong> etapas.
          </div>
        </div>

        <div class="hf-onboarding-next">
          <span>Próximo passo</span>
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

    <div class="hf-section-heading">
      <div>
        <h5>Ordens de Serviço</h5>
        <p>Volume, andamento e desempenho operacional.</p>
      </div>
    </div>

    <!-- ===== LINHA 1: OS ===== -->
    <div class="row g-3 mb-4">
      <!-- Total OS -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="hf-card hf-kpi hf-dashboard-kpi kpi-primary"
             style="cursor:pointer"
             onclick="window.location='<?php echo htmlspecialchars($urlTotalOs); ?>'">
          <div class="kpi-top">
            <div class="kpi-title">Total OS</div>
            <div class="kpi-icon"><i class="bi bi-clipboard-data"></i></div>
          </div>
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
          <div class="hf-progress">
            <div class="bar" style="width:100%"></div>
          </div>
        </div>
      </div>

      <!-- Pendentes -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="hf-card hf-kpi hf-dashboard-kpi kpi-danger"
             style="cursor:pointer"
             onclick="window.location='<?php echo htmlspecialchars($urlPendentes); ?>'">
          <div class="kpi-top">
            <div class="kpi-title">Pendentes</div>
            <div class="kpi-icon"><i class="bi bi-hourglass-split"></i></div>
          </div>
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
          <div class="hf-progress">
            <div class="bar" style="width:<?php echo $perc_pendentes; ?>%"></div>
          </div>
        </div>
      </div>

      <!-- Concluídas -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="hf-card hf-kpi hf-dashboard-kpi kpi-success"
             style="cursor:pointer"
             onclick="window.location='<?php echo htmlspecialchars($urlConcluidas); ?>'">
          <div class="kpi-top">
            <div class="kpi-title">Concluídas</div>
            <div class="kpi-icon"><i class="bi bi-check2-circle"></i></div>
          </div>
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
          <div class="hf-progress">
            <div class="bar" style="width:<?php echo $perc_concluidas; ?>%"></div>
          </div>
        </div>
      </div>

      <!-- SLA -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="hf-card hf-kpi hf-dashboard-kpi kpi-warning">
          <div class="kpi-top">
            <div class="kpi-title">SLA médio</div>
            <div class="kpi-icon"><i class="bi bi-speedometer2"></i></div>
          </div>
          <div class="kpi-value">
            <?php echo $sla_formatado; ?>
          </div>
          <div class="kpi-sub">tempo médio de atendimento</div>
          <div class="hf-progress">
            <div class="bar" style="width:<?php echo $perc_sla; ?>%"></div>
          </div>
        </div>
      </div>
    </div>

    <?php if (!$isAtendenteOnly): ?>
    <div class="hf-section-heading">
      <div>
        <h5>Financeiro</h5>
        <p>Previsão, recebimento e pendências financeiras.</p>
      </div>
    </div>

    <!-- ===== LINHA 2: FINANCEIRO ===== -->
    <div class="row g-3 mb-4">
      <!-- Total previsto -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="hf-card hf-kpi hf-dashboard-kpi kpi-primary"
             style="cursor:pointer"
             onclick="window.location='<?php echo htmlspecialchars($urlFinPrevisto); ?>'">
          <div class="kpi-top">
            <div class="kpi-title">Previsto no período</div>
            <div class="kpi-icon"><i class="bi bi-cash-stack"></i></div>
          </div>
          <div class="kpi-value">
            <?php echo money_br($total_previsto); ?>
          </div>
          <div class="kpi-sub">títulos com vencimento no período</div>
          <div class="hf-progress">
            <div class="bar" style="width:100%"></div>
          </div>
        </div>
      </div>

      <!-- Recebido -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="hf-card hf-kpi hf-dashboard-kpi kpi-success"
             style="cursor:pointer"
             onclick="window.location='<?php echo htmlspecialchars($urlFinRecebido); ?>'">
          <div class="kpi-top">
            <div class="kpi-title">Recebido</div>
            <div class="kpi-icon"><i class="bi bi-bank"></i></div>
          </div>
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
          <div class="hf-progress">
            <div class="bar" style="width:<?php echo $perc_recebido; ?>%"></div>
          </div>
        </div>
      </div>

      <!-- Em aberto -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="hf-card hf-kpi hf-dashboard-kpi kpi-warning"
             style="cursor:pointer"
             onclick="window.location='<?php echo htmlspecialchars($urlFinAberto); ?>'">
          <div class="kpi-top">
            <div class="kpi-title">Em aberto</div>
            <div class="kpi-icon"><i class="bi bi-file-earmark-text"></i></div>
          </div>
          <div class="kpi-value">
            <?php echo money_br($total_em_aberto); ?>
          </div>
          <div class="kpi-sub">a receber no período</div>
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
        <div class="hf-card hf-kpi hf-dashboard-kpi kpi-danger"
             style="cursor:pointer"
             onclick="window.location='<?php echo htmlspecialchars($urlFinAtrasado); ?>'">
          <div class="kpi-top">
            <div class="kpi-title">Atrasado</div>
            <div class="kpi-icon"><i class="bi bi-exclamation-triangle"></i></div>
          </div>
          <div class="kpi-value">
            <?php echo money_br($total_atrasado); ?>
          </div>
          <div class="kpi-sub">vencido e não pago</div>
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
    <div class="hf-card hf-dashboard-chart-card">
      <div class="hf-chart-head">
        <div>
          <h6 class="mb-0">OS x Recebido no período</h6>
          <small class="text-muted">Comparativo diário de volume operacional e valor recebido.</small>
        </div>
        <small class="text-muted hf-chart-period">
          <?php echo htmlspecialchars(date('d/m/Y', strtotime($data_ini)) . ' a ' . date('d/m/Y', strtotime($data_fim))); ?>
          <?php if ($tecnico_id > 0): ?>
            • Técnico filtrado
          <?php endif; ?>
        </small>
      </div>
      <canvas id="graficoOsFin" style="max-height:320px;"></canvas>
    </div>
    <?php endif; // !$isAtendenteOnly ?>

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

.hf-dashboard-filter .form-control:focus,
.hf-dashboard-filter .form-select:focus {
  border-color: rgba(var(--bs-primary-rgb), .55);
  box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
  background-color: #fff;
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

.hf-btn-apply {
  min-height: 34px;
  box-shadow: 0 8px 18px rgba(var(--bs-primary-rgb), .16);
}

.hf-onboarding-card {
  position: relative;
  display: grid;
  grid-template-columns: minmax(260px, 1fr) minmax(220px, 280px);
  gap: 1rem;
  margin: .35rem 0 1.2rem;
  padding: 1rem;
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, .22);
  border-radius: 1.15rem;
  background:
    radial-gradient(circle at 0% 0%, rgba(var(--bs-primary-rgb), .14), transparent 22rem),
    linear-gradient(135deg, rgba(255,255,255,.98), rgba(248,250,252,.94));
  box-shadow: 0 18px 46px rgba(15, 23, 42, .10);
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

.hf-onboarding-kicker {
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

.hf-onboarding-minimize:hover {
  color: #0f172a;
  background: #fff;
}

.hf-onboarding-title-row {
  margin-top: .55rem;
}

.hf-onboarding-title-row h5 {
  margin: 0;
  color: #0f172a;
  font-size: 1.15rem;
  font-weight: 900;
}

.hf-onboarding-title-row p {
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

.hf-onboarding-progress {
  height: 8px;
  margin-top: .9rem;
  overflow: hidden;
  border-radius: 999px;
  background: rgba(148, 163, 184, .18);
}

.hf-onboarding-progress span {
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
  transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
}

a.hf-onboarding-step:hover {
  transform: translateY(-1px);
  border-color: rgba(var(--bs-primary-rgb), .34);
  box-shadow: 0 12px 28px rgba(15, 23, 42, .09);
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
  min-height: 178px;
  padding: 1.05rem;
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
  transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
}

.hf-dashboard-kpi:hover {
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

.hf-dashboard-kpi .hf-progress {
  height: 5px;
  margin-top: 1.05rem;
  border-radius: 999px;
  background: rgba(148, 163, 184, .18);
  overflow: hidden;
}

.hf-dashboard-kpi .hf-progress .bar {
  height: 100%;
  border-radius: 999px;
  background: var(--kpi-color, var(--bs-primary));
}

.hf-dashboard-kpi.kpi-primary {
  --kpi-color: var(--bs-primary);
  --kpi-soft: rgba(var(--bs-primary-rgb), .10);
}

.hf-dashboard-kpi.kpi-success {
  --kpi-color: #16a34a;
  --kpi-soft: rgba(22, 163, 74, .12);
}

.hf-dashboard-kpi.kpi-danger {
  --kpi-color: #dc2626;
  --kpi-soft: rgba(220, 38, 38, .11);
}

.hf-dashboard-kpi.kpi-warning {
  --kpi-color: #d97706;
  --kpi-soft: rgba(245, 158, 11, .14);
}

.hf-dashboard-chart-card {
  padding: 1.1rem;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

.hf-chart-head {
  display: flex;
  justify-content: space-between;
  align-items: start;
  gap: 1rem;
  margin-bottom: 1rem;
}

.hf-chart-head h6 {
  color: #0f172a;
  font-weight: 850;
}

.hf-chart-period {
  white-space: nowrap;
}

@media (max-width: 991.98px) {
  .hf-dashboard-top {
    grid-template-columns: 1fr;
  }

  .hf-dashboard-filter {
    width: 100%;
  }

  .hf-onboarding-card {
    grid-template-columns: 1fr;
  }

  .hf-onboarding-steps {
    grid-template-columns: repeat(2, minmax(0, 1fr));
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

  .hf-btn-apply {
    width: 100%;
  }

  .hf-onboarding-card {
    padding: .85rem;
    border-radius: 1rem;
  }

  .hf-onboarding-title-row {
    align-items: flex-start;
  }

  .hf-onboarding-progress-number {
    font-size: 1.15rem;
  }

  .hf-onboarding-steps {
    grid-template-columns: 1fr;
  }

  .hf-onboarding-next .btn {
    width: 100%;
  }

  .hf-dashboard-kpi {
    min-height: auto;
  }

  .hf-chart-head {
    flex-direction: column;
  }

  .hf-chart-period {
    white-space: normal;
  }
}

[data-bs-theme="dark"] .hf-dashboard-page {
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .hf-dashboard-filter,
[data-bs-theme="dark"] .hf-dashboard-kpi,
[data-bs-theme="dark"] .hf-onboarding-card,
[data-bs-theme="dark"] .hf-dashboard-chart-card {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-section-heading h5,
[data-bs-theme="dark"] .hf-dashboard-kpi .kpi-value,
[data-bs-theme="dark"] .hf-onboarding-title-row h5,
[data-bs-theme="dark"] .hf-onboarding-progress-number,
[data-bs-theme="dark"] .hf-onboarding-summary strong,
[data-bs-theme="dark"] .hf-onboarding-next strong,
[data-bs-theme="dark"] .hf-onboarding-step-copy strong,
[data-bs-theme="dark"] .hf-chart-head h6 {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-dashboard-filter .form-control,
[data-bs-theme="dark"] .hf-dashboard-filter .form-select {
  background-color: rgba(15, 23, 42, .9);
  border-color: rgba(148, 163, 184, .24);
}

[data-bs-theme="dark"] .hf-onboarding-next,
[data-bs-theme="dark"] .hf-onboarding-step {
  background: rgba(15, 23, 42, .62);
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
