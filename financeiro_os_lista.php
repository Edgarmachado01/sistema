<?php
// financeiro_os_lista.php — Lista de títulos financeiros das OS + visão geral com lançamentos


// === LAYOUT / AUTH / DB ===
require_once __DIR__.'/_layout_start.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';
requireAdmin();

$pdo = db();
$tid = function_exists('tenantId') ? (int)tenantId() : (int)($_SESSION['tenant_id'] ?? 0);
if ($tid <= 0) {
    error_log('financeiro_os_lista.php tenant invalido user=' . ($_SESSION['USER_ID'] ?? ''));
    header('Location: /login.php');
    exit;
}

$hoje        = date('Y-m-d');
$primeiroMes = date('Y-m-01');

// ===== Filtros =====
$data_ini = isset($_GET['data_ini']) && $_GET['data_ini'] !== '' ? $_GET['data_ini'] : $primeiroMes;
$data_fim = isset($_GET['data_fim']) && $_GET['data_fim'] !== '' ? $_GET['data_fim'] : $hoje;
$status   = isset($_GET['status']) ? trim($_GET['status']) : '';
$forma    = isset($_GET['forma_pagamento']) ? trim($_GET['forma_pagamento']) : '';
$busca    = isset($_GET['busca']) ? trim($_GET['busca']) : '';

// ===== Monta SQL (OS) =====
$where  = ['f.tenant_id = :tid'];
$params = [':tid' => $tid];

if ($data_ini) {
    $where[] = 'f.data_vencimento >= :di';
    $params[':di'] = $data_ini;
}
if ($data_fim) {
    $where[] = 'f.data_vencimento <= :df';
    $params[':df'] = $data_fim;
}
if ($status !== '') {
    // status sempre vindo da OS
    $where[] = 'o.status_financeiro = :st';
    $params[':st'] = $status;
}
if ($forma !== '') {
    // forma vindo da OS
    $where[] = 'o.forma_pagto = :fp';
    $params[':fp'] = $forma;
}
if ($busca !== '') {
    $where[] = '(c.nome LIKE :busca OR f.os_id = :osid)';
    $params[':busca'] = '%'.$busca.'%';
    $params[':osid']  = (int)$busca;
}

$sql = "
    SELECT
        f.id,
        f.os_id,
        f.cliente_id,
        f.data_os,
        f.data_vencimento,
        o.total           AS valor_total,

        -- SEMPRE pega do registro da OS
        o.data_pagto       AS data_pagamento,
        o.forma_pagto      AS forma_pagamento,
        o.valor_pago       AS valor_pago,
        o.status_financeiro,

        c.nome AS cliente_nome
    FROM os_financeiro f
    JOIN hf_os o
      ON o.id        = f.os_id
     AND o.tenant_id = f.tenant_id
    LEFT JOIN hf_clientes c
      ON c.id        = f.cliente_id
     AND c.tenant_id = f.tenant_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY f.data_vencimento ASC, f.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== Totais OS =====
$totalReceber = 0.0;
$totalAtraso  = 0.0;
$totalPago    = 0.0;

foreach ($rows as $r) {
    $valorTotal = (float)$r['valor_total'];
    $valorPago  = (float)$r['valor_pago'];
    $statusTit  = strtolower(trim($r['status_financeiro']));
    $venc       = $r['data_vencimento'];

    if ($statusTit === 'pago') {
        $totalPago += $valorPago > 0 ? $valorPago : $valorTotal;
    } elseif (in_array($statusTit, ['aberto','pendente','parcial','em_aberto'], true)) {
        $saldo = max(0, $valorTotal - $valorPago);
        $totalReceber += $saldo;
        if ($venc && $venc < $hoje) {
            $totalAtraso += $saldo;
        }
    }
}

// ===== Resumo dos Lançamentos (entradas / saídas) =====
$sqlLanc = "
    SELECT
        SUM(CASE WHEN tipo_mov = 'entrada' AND status = 'pago'   THEN valor ELSE 0 END) AS lanc_entradas_pagas,
        SUM(CASE WHEN tipo_mov = 'saida'   AND status = 'pago'   THEN valor ELSE 0 END) AS lanc_saidas_pagas,
        SUM(CASE WHEN tipo_mov = 'entrada' AND status = 'aberto' THEN valor ELSE 0 END) AS lanc_entradas_abertas,
        SUM(CASE WHEN tipo_mov = 'saida'   AND status = 'aberto' THEN valor ELSE 0 END) AS lanc_saidas_abertas
    FROM lancamentos
    WHERE tenant_id = :tid
";

$stmtLanc = $pdo->prepare($sqlLanc);
$stmtLanc->execute([':tid' => $tid]);
$lanc = $stmtLanc->fetch(PDO::FETCH_ASSOC) ?: [];

$lanc_entradas_pagas   = (float)($lanc['lanc_entradas_pagas']   ?? 0);
$lanc_saidas_pagas     = (float)($lanc['lanc_saidas_pagas']     ?? 0);
$lanc_entradas_abertas = (float)($lanc['lanc_entradas_abertas'] ?? 0);
$lanc_saidas_abertas   = (float)($lanc['lanc_saidas_abertas']   ?? 0);

$saldo_lanc_pagos   = $lanc_entradas_pagas - $lanc_saidas_pagas;
$saldo_lanc_abertos = $lanc_entradas_abertas - $lanc_saidas_abertas;

// ===== Visão geral (OS + lançamentos) =====
$saldo_geral_pagos    = $totalPago + $saldo_lanc_pagos;        // o que já entrou (OS pagas + lançamentos pagos)
$saldo_geral_previsto = $saldo_geral_pagos + $totalReceber + $saldo_lanc_abertos; // visão futura simples

// ===== Helpers =====
function fmtData($d) {
    if (!$d || $d === '0000-00-00') return '';
    $ts = strtotime($d);
    if (!$ts) return '';
    return date('d/m/Y', $ts);
}
function fmtMoeda($v) {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}
function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
?>

<?php include __DIR__.'/_sidebar.php'; ?>
<main class="hf-content hf-finance-page">
  <div class="container-fluid py-4 hf-finance-wrap">

    <div class="hf-finance-hero d-flex justify-content-between align-items-center mb-3">
        <div>
            <div class="hf-page-kicker">Gestão financeira</div>
            <h1 class="h4 mb-0">Financeiro</h1>
        </div>
        <a href="/os_form.php" class="btn btn-primary btn-sm hf-btn-new-os">
            <i class="bi bi-plus-lg me-1"></i>
            Nova OS
        </a>
    </div>

    <!-- Filtros -->
    <form class="card mb-3 p-3 hf-filter-card" method="get" action="financeiro_os_lista.php">
        <div class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Vencimento de</label>
                <input type="date" name="data_ini" value="<?=h($data_ini)?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">até</label>
                <input type="date" name="data_fim" value="<?=h($data_fim)?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status (OS)</label>
                <select name="status" class="form-select">
                    <option value="">(Todos)</option>
                    <option value="pendente"  <?= $status==='pendente'  ? 'selected' : '' ?>>Pendente</option>
                    <option value="aberta"    <?= $status==='aberta'    ? 'selected' : '' ?>>Aberta</option>
                    <option value="parcial"   <?= $status==='parcial'   ? 'selected' : '' ?>>Parcial</option>
                    <option value="pago"      <?= $status==='pago'      ? 'selected' : '' ?>>Pago</option>
                    <option value="cancelado" <?= $status==='cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Forma de pagamento (OS)</label>
                <input type="text" name="forma_pagamento" value="<?=h($forma)?>" class="form-control" placeholder="Pix, crédito...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Buscar (cliente ou Nº OS)</label>
                <input type="text" name="busca" value="<?=h($busca)?>" class="form-control" placeholder="Nome cliente ou número OS">
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-primary hf-btn-filter">
                    <i class="bi bi-search me-1"></i>
                    Filtrar
                </button>
            </div>
        </div>
    </form>

    <!-- Cards resumo OS -->
    <div class="row g-3 mb-3 hf-summary-row">
        <div class="col-md-4">
            <div class="hf-summary-card hf-summary-warning">
                <div class="hf-summary-icon">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div class="hf-summary-body">
                    <div class="hf-summary-label">OS - A receber (período)</div>
                    <div class="hf-summary-value"><?=fmtMoeda($totalReceber)?></div>
                    <div class="hf-summary-sub">Títulos em aberto ou parcial</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="hf-summary-card hf-summary-danger">
                <div class="hf-summary-icon">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="hf-summary-body">
                    <div class="hf-summary-label">OS - Em atraso</div>
                    <div class="hf-summary-value"><?=fmtMoeda($totalAtraso)?></div>
                    <div class="hf-summary-sub">Vencidos até hoje</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="hf-summary-card hf-summary-success">
                <div class="hf-summary-icon">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="hf-summary-body">
                    <div class="hf-summary-label">OS - Recebido (período)</div>
                    <div class="hf-summary-value"><?=fmtMoeda($totalPago)?></div>
                    <div class="hf-summary-sub">Pagamentos confirmados</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cards resumo Lançamentos + visão geral -->
    <div class="row g-3 mb-4 hf-summary-row">
        <div class="col-md-3">
            <div class="hf-summary-card">
                <div class="hf-summary-icon">
                    <i class="bi bi-arrow-down-circle"></i>
                </div>
                <div class="hf-summary-body">
                    <div class="hf-summary-label">Lançamentos - Entradas pagas</div>
                    <div class="hf-summary-value"><?= fmtMoeda($lanc_entradas_pagas) ?></div>
                    <div class="hf-summary-sub">Contas de entrada já pagas</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="hf-summary-card">
                <div class="hf-summary-icon">
                    <i class="bi bi-arrow-up-circle"></i>
                </div>
                <div class="hf-summary-body">
                    <div class="hf-summary-label">Lançamentos - Saídas pagas</div>
                    <div class="hf-summary-value text-danger"><?= fmtMoeda($lanc_saidas_pagas) ?></div>
                    <div class="hf-summary-sub">Contas de saída já pagas</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="hf-summary-card">
                <div class="hf-summary-icon">
                    <i class="bi bi-balance-scale"></i>
                </div>
                <div class="hf-summary-body">
                    <div class="hf-summary-label">Saldo lançamentos (pagos)</div>
                    <div class="hf-summary-value <?= $saldo_lanc_pagos >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= fmtMoeda($saldo_lanc_pagos) ?>
                    </div>
                    <div class="hf-summary-sub">Entradas - saídas (pagas)</div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="hf-summary-card hf-summary-success">
                <div class="hf-summary-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <div class="hf-summary-body">
                    <div class="hf-summary-label">Saldo geral (pagos)</div>
                    <div class="hf-summary-value <?= $saldo_geral_pagos >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?= fmtMoeda($saldo_geral_pagos) ?>
                    </div>
                    <div class="hf-summary-sub">OS pagas + lançamentos pagos</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== LISTA OS ========== -->

    <!-- DESKTOP/TABLET: TABELA -->
    <div class="card shadow-sm d-none d-md-block hf-table-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 hf-finance-table">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Nº OS</th>
                            <th>Cliente</th>
                            <th>Data OS</th>
                            <th>Vencimento</th>
                            <th>Pagamento</th>
                            <th>Forma</th>
                            <th class="text-end">Valor total</th>
                            <th class="text-end">Valor pago</th>
                            <th>Status</th>
                            <th class="text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rows): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4 text-muted">
                                Nenhum título encontrado para os filtros informados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                                $statusTit = strtolower(trim($r['status_financeiro']));
                                $badgeClass = 'secondary';
                                switch ($statusTit) {
                                    case 'aberto':
                                    case 'pendente':
                                    case 'em_aberto':
                                        $badgeClass = 'warning';  break;
                                    case 'parcial':
                                        $badgeClass = 'info';     break;
                                    case 'pago':
                                        $badgeClass = 'success';  break;
                                    case 'cancelado':
                                        $badgeClass = 'dark';     break;
                                }
                                $statusLabel = $statusTit ? ucfirst(str_replace('_',' ',$statusTit)) : '-';
                            ?>
                            <tr>
                                <td class="text-muted">#<?= (int)$r['id'] ?></td>
                                <td><span class="hf-os-number"><?= (int)$r['os_id'] ?></span></td>
                                <td><?= h($r['cliente_nome'] ?: ('#'.$r['cliente_id'])) ?></td>
                                <td><?= fmtData($r['data_os']) ?></td>
                                <td><?= fmtData($r['data_vencimento']) ?></td>
                                <td><?= fmtData($r['data_pagamento']) ?></td>
                                <td><?= h($r['forma_pagamento']) ?></td>
                                <td class="text-end fw-semibold"><?= fmtMoeda($r['valor_total']) ?></td>
                                <td class="text-end"><?= fmtMoeda($r['valor_pago']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $badgeClass ?> hf-status-badge">
                                        <?= h($statusLabel) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="/os_form.php?id=<?= (int)$r['os_id'] ?>" class="btn btn-sm btn-outline-primary hf-action-btn">
                                        <i class="bi bi-eye me-1"></i>
                                        Ver OS
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MOBILE: CARDS -->
    <div class="d-block d-md-none mt-3 hf-mobile-list">
        <?php if (!$rows): ?>
            <div class="alert alert-light text-center text-muted hf-empty-state">
                Nenhum título encontrado para os filtros informados.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($rows as $r): ?>
                    <?php
                        $statusTit = strtolower(trim($r['status_financeiro']));
                        $badgeClass = 'secondary';
                        $borderColor = '#cbd5e1';

                        switch ($statusTit) {
                            case 'aberto':
                            case 'pendente':
                            case 'em_aberto':
                                $badgeClass = 'warning';
                                $borderColor = '#f59e0b';
                                break;
                            case 'parcial':
                                $badgeClass = 'info';
                                $borderColor = '#0ea5e9';
                                break;
                            case 'pago':
                                $badgeClass = 'success';
                                $borderColor = '#16a34a';
                                break;
                            case 'cancelado':
                                $badgeClass = 'dark';
                                $borderColor = '#4b5563';
                                break;
                        }

                        $statusLabel = $statusTit ? ucfirst(str_replace('_',' ',$statusTit)) : '-';
                        $linkOs = '/os_form.php?id='.(int)$r['os_id'];
                    ?>
                    <div class="col-12 mb-3">
                        <div class="card shadow-sm h-100 hf-mobile-card" style="border-left:4px solid <?= $borderColor ?>;">
                            <div class="card-body p-3 d-flex flex-column">

                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="hf-mobile-os">
                                        OS #<?= (int)$r['os_id'] ?>
                                    </small>
                                    <span class="badge bg-<?= $badgeClass ?> hf-status-badge">
                                        <?= h($statusLabel) ?>
                                    </span>
                                </div>

                                <a href="<?= $linkOs ?>" class="text-decoration-none text-body flex-grow-1">
                                    <div class="fw-semibold mb-2 hf-mobile-client">
                                        <?= h($r['cliente_nome'] ?: ('Cliente #'.$r['cliente_id'])) ?>
                                    </div>

                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                        <span>Emissão: <?= fmtData($r['data_os']) ?: '-' ?></span>
                                        <span>Venc: <?= fmtData($r['data_vencimento']) ?: '-' ?></span>
                                    </div>

                                    <div class="d-flex justify-content-between small text-muted mb-1">
                                        <span>Pagto: <?= fmtData($r['data_pagamento']) ?: '-' ?></span>
                                        <span><?= h($r['forma_pagamento'] ?: '') ?></span>
                                    </div>

                                    <div class="mt-2 d-flex justify-content-between align-items-center hf-mobile-money-row">
                                        <div class="small text-muted">Valor</div>
                                        <div class="fw-bold">
                                            <?= fmtMoeda($r['valor_total']) ?>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center small mt-1">
                                        <span class="text-muted">Pago</span>
                                        <span><?= fmtMoeda($r['valor_pago']) ?></span>
                                    </div>
                                </a>

                                <div class="mt-3 d-flex justify-content-end">
                                    <a href="<?= $linkOs ?>" class="btn btn-sm btn-outline-primary hf-action-btn">
                                        <i class="bi bi-eye"></i> Detalhes
                                    </a>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

  </div>
</main>

<!-- Estilo visual desta tela -->
<style>
.hf-finance-page {
    min-height: calc(100vh - var(--topbar-h));
    background:
        radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .10), transparent 28rem),
        linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
}

.hf-finance-wrap {
    max-width: 1480px;
}

.hf-finance-hero {
    gap: 1rem;
    padding: .25rem .1rem .6rem;
}

.hf-page-kicker {
    font-size: .74rem;
    font-weight: 700;
    color: rgba(var(--bs-primary-rgb), .88);
    text-transform: uppercase;
    letter-spacing: .08em;
    margin-bottom: .15rem;
}

.hf-btn-new-os,
.hf-btn-filter,
.hf-action-btn {
    border-radius: .65rem;
    font-weight: 600;
    box-shadow: 0 8px 18px rgba(var(--bs-primary-rgb), .16);
}

.hf-btn-new-os {
    padding: .48rem .78rem;
    white-space: nowrap;
}

.hf-filter-card,
.hf-table-card,
.hf-mobile-card,
.hf-empty-state {
    border: 1px solid rgba(148, 163, 184, .24);
    border-radius: 1rem;
    background: rgba(255, 255, 255, .92);
    box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

.hf-filter-card {
    backdrop-filter: blur(8px);
}

.hf-filter-card .form-label {
    margin-bottom: .35rem;
    font-size: .76rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.hf-filter-card .form-control,
.hf-filter-card .form-select {
    min-height: 42px;
    border-radius: .72rem;
    border-color: #dbe3ee;
    background-color: #f8fafc;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .75);
}

.hf-filter-card .form-control:focus,
.hf-filter-card .form-select:focus {
    border-color: rgba(var(--bs-primary-rgb), .55);
    box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
    background-color: #fff;
}

.hf-summary-row {
    margin-bottom: 1.5rem;
}

.hf-summary-card {
    display: flex;
    align-items: center;
    gap: .85rem;
    min-height: 112px;
    padding: 1rem 1.05rem;
    border-radius: 1rem;
    background: rgba(255, 255, 255, .94);
    box-shadow: 0 14px 32px rgba(15, 23, 42, .07);
    border: 1px solid rgba(148, 163, 184, .24);
    transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
}

.hf-summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 18px 42px rgba(15, 23, 42, .10);
    border-color: rgba(var(--bs-primary-rgb), .22);
}

.hf-summary-icon {
    width: 44px;
    height: 44px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: rgba(99, 102, 241, .11);
    color: #4f46e5;
    flex-shrink: 0;
}

.hf-summary-body {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.hf-summary-label {
    font-size: .74rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #64748b;
    margin-bottom: .12rem;
    font-weight: 700;
}

.hf-summary-value {
    font-size: 1.36rem;
    font-weight: 800;
    line-height: 1.2;
    color: #0f172a;
}

.hf-summary-sub {
    font-size: .78rem;
    color: #94a3b8;
}

.hf-summary-warning .hf-summary-icon {
    background: rgba(245, 158, 11, .14);
    color: #b45309;
}
.hf-summary-danger .hf-summary-icon {
    background: rgba(239, 68, 68, .12);
    color: #b91c1c;
}
.hf-summary-success .hf-summary-icon {
    background: rgba(16, 185, 129, .13);
    color: #047857;
}

.hf-table-card {
    overflow: hidden;
}

.hf-finance-table {
    --bs-table-bg: transparent;
}

.hf-finance-table thead th {
    padding: .9rem .85rem;
    border-bottom: 1px solid rgba(148, 163, 184, .28);
    background: #f1f5f9;
    color: #475569;
    font-size: .74rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .055em;
    white-space: nowrap;
}

.hf-finance-table tbody td {
    padding: .85rem;
    border-color: rgba(226, 232, 240, .82);
    color: #334155;
}

.hf-finance-table tbody tr {
    transition: background-color .14s ease, box-shadow .14s ease;
}

.hf-finance-table tbody tr:hover {
    background: rgba(var(--bs-primary-rgb), .045);
    box-shadow: inset 3px 0 0 rgba(var(--bs-primary-rgb), .56);
}

.hf-os-number {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.25rem;
    padding: .18rem .45rem;
    border-radius: 999px;
    background: rgba(var(--bs-primary-rgb), .10);
    color: var(--bs-primary);
    font-weight: 800;
}

.hf-status-badge {
    border-radius: 999px;
    padding: .42rem .62rem;
    font-weight: 700;
    letter-spacing: .01em;
}

.hf-status-badge.bg-warning {
    color: #8a4b00 !important;
    background: #fff3cd !important;
}
.hf-status-badge.bg-info {
    color: #075985 !important;
    background: #dbeafe !important;
}
.hf-status-badge.bg-success {
    color: #047857 !important;
    background: #d1fae5 !important;
}
.hf-status-badge.bg-dark,
.hf-status-badge.bg-secondary {
    color: #475569 !important;
    background: #e2e8f0 !important;
}

.hf-action-btn {
    border-color: rgba(var(--bs-primary-rgb), .34);
    background: rgba(var(--bs-primary-rgb), .04);
}

.hf-action-btn:hover {
    color: #fff;
    background: var(--bs-primary);
    border-color: var(--bs-primary);
}

.hf-mobile-list .row {
    margin-left: 0;
    margin-right: 0;
}

.hf-mobile-card {
    border-top: 0;
    border-right: 0;
    border-bottom: 0;
    transition: transform .16s ease, box-shadow .16s ease;
}

.hf-mobile-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 18px 36px rgba(15, 23, 42, .10) !important;
}

.hf-mobile-os {
    color: #64748b;
    font-weight: 800;
    letter-spacing: .03em;
    text-transform: uppercase;
}

.hf-mobile-client {
    color: #0f172a;
    font-size: 1rem;
}

.hf-mobile-money-row {
    padding-top: .65rem;
    border-top: 1px solid rgba(226, 232, 240, .9);
}

@media (max-width: 767.98px) {
    .hf-finance-page {
        padding-left: .25rem;
        padding-right: .25rem;
    }

    .hf-finance-hero {
        align-items: flex-start !important;
    }

    .hf-btn-new-os {
        padding: .44rem .62rem;
    }

    .hf-filter-card {
        border-radius: .9rem;
    }

    .hf-summary-card {
        min-height: auto;
    }

    .hf-btn-filter {
        width: 100%;
        margin-top: .25rem;
    }
}

[data-bs-theme="dark"] .hf-finance-page {
    background:
        radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
        linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .hf-filter-card,
[data-bs-theme="dark"] .hf-table-card,
[data-bs-theme="dark"] .hf-mobile-card,
[data-bs-theme="dark"] .hf-summary-card,
[data-bs-theme="dark"] .hf-empty-state {
    background: rgba(17, 24, 39, .9);
    border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-summary-value,
[data-bs-theme="dark"] .hf-mobile-client {
    color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-filter-card .form-control,
[data-bs-theme="dark"] .hf-filter-card .form-select {
    background-color: rgba(15, 23, 42, .9);
    border-color: rgba(148, 163, 184, .24);
}

[data-bs-theme="dark"] .hf-finance-table thead th {
    background: rgba(30, 41, 59, .95);
    color: #cbd5e1;
}

[data-bs-theme="dark"] .hf-finance-table tbody td {
    color: #cbd5e1;
    border-color: rgba(51, 65, 85, .9);
}
</style>

<?php require_once __DIR__.'/_layout_end.php'; ?>
