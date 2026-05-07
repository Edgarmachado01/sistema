<?php
require_once __DIR__ . '/_layout_start.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/_relatorio_os_query.php';

requireLogin();

function hfRelOsH($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hfRelOsMoney($value)
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function hfRelOsDateBr($value, $withTime = false)
{
    if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $ts = strtotime((string)$value);
    if (!$ts) {
        return '-';
    }

    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts);
}

function hfRelOsLabel($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    return ucfirst(str_replace('_', ' ', $value));
}

function hfRelOsStatusBadge($status)
{
    $map = [
        'aberta' => 'secondary',
        'em_andamento' => 'warning',
        'concluida' => 'success',
        'cancelada' => 'dark',
    ];

    return $map[$status] ?? 'secondary';
}

function hfRelOsFinBadge($status)
{
    $map = [
        'pendente' => 'secondary',
        'parcial' => 'warning',
        'pago' => 'success',
    ];

    return $map[$status] ?? 'secondary';
}

function hfRelOsPriorityBadge($priority)
{
    $map = [
        'baixa' => 'secondary',
        'media' => 'info',
        'alta' => 'danger',
    ];

    return $map[$priority] ?? 'secondary';
}

$result = [
    'ok' => false,
    'message' => 'Nao foi possivel carregar o relatorio.',
    'filters' => hfRelOsReadFilters(),
    'rows' => [],
    'summary' => hfRelOsEmptySummary(),
];

try {
    $pdo = db();
    $tid = (int)tenantId();
    $result = hfRelOsFetch($pdo, $tid);
} catch (Throwable $e) {
    error_log('relatorio_os.php: ' . $e->getMessage());
}

$filters = $result['filters'];
$rows = $result['rows'];
$summary = $result['summary'];
$exportQuery = http_build_query($filters);
$exportUrl = '/relatorio_os_excel.php' . ($exportQuery ? '?' . $exportQuery : '');
?>

<?php include __DIR__ . '/_sidebar.php'; ?>

<main class="hf-content hf-rel-os-page">
  <div class="container-fluid py-4 hf-rel-os-wrap">
    <div class="hf-rel-os-top mb-3">
      <div class="hf-rel-os-title">
        <div class="hf-page-kicker">Relatorios</div>
        <h1 class="h4 mb-0">Ordens de Servico</h1>
        <div class="hf-page-subtitle">Acompanhamento operacional e financeiro das OS.</div>
      </div>

      <div class="hf-rel-os-top-actions">
        <a href="/relatorios.php?m=relatorios" class="btn btn-outline-secondary btn-sm hf-soft-btn">
          <i class="bi bi-arrow-left me-1"></i>
          Relatorios
        </a>
      </div>
    </div>

    <?php if (!$result['ok']): ?>
      <div class="alert alert-warning hf-alert mb-3" role="alert">
        <?= hfRelOsH($result['message']) ?>
      </div>
    <?php endif; ?>

    <form class="hf-filter-card mb-3" method="get" action="/relatorio_os.php">
      <input type="hidden" name="m" value="relatorios">

      <div class="row g-2 align-items-end">
        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Data inicial</label>
          <input type="date" name="data_ini" value="<?= hfRelOsH($filters['data_ini']) ?>" class="form-control">
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Data final</label>
          <input type="date" name="data_fim" value="<?= hfRelOsH($filters['data_fim']) ?>" class="form-control">
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Status OS</label>
          <select name="status" class="form-select">
            <option value="">Todos</option>
            <?php foreach (['aberta', 'em_andamento', 'concluida', 'cancelada'] as $status): ?>
              <option value="<?= hfRelOsH($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>>
                <?= hfRelOsH(hfRelOsLabel($status)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Status financeiro</label>
          <select name="status_financeiro" class="form-select">
            <option value="">Todos</option>
            <?php foreach (['pendente', 'parcial', 'pago'] as $statusFin): ?>
              <option value="<?= hfRelOsH($statusFin) ?>" <?= $filters['status_financeiro'] === $statusFin ? 'selected' : '' ?>>
                <?= hfRelOsH(hfRelOsLabel($statusFin)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Prioridade</label>
          <select name="prioridade" class="form-select">
            <option value="">Todas</option>
            <?php foreach (['baixa', 'media', 'alta'] as $priority): ?>
              <option value="<?= hfRelOsH($priority) ?>" <?= $filters['prioridade'] === $priority ? 'selected' : '' ?>>
                <?= hfRelOsH(hfRelOsLabel($priority)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Tecnico</label>
          <input type="text" name="tecnico" value="<?= hfRelOsH($filters['tecnico']) ?>" class="form-control" placeholder="Nome">
        </div>

        <div class="col-md-6 col-xl-3">
          <label class="form-label">Busca</label>
          <div class="hf-search-input">
            <i class="bi bi-search"></i>
            <input type="text" name="busca" value="<?= hfRelOsH($filters['busca']) ?>" class="form-control" placeholder="No OS ou cliente">
          </div>
        </div>

        <div class="col-sm-4 col-md-2 col-xl-2 d-grid">
          <button class="btn btn-primary hf-btn-filter" type="submit">
            <i class="bi bi-search me-1"></i>
            Filtrar
          </button>
        </div>

        <div class="col-sm-4 col-md-2 col-xl-2 d-grid">
          <a class="btn btn-outline-secondary hf-soft-btn" href="/relatorio_os.php?m=relatorios">
            <i class="bi bi-x-lg me-1"></i>
            Limpar
          </a>
        </div>

        <div class="col-sm-4 col-md-2 col-xl-2 d-grid">
          <a class="btn btn-success hf-export-btn" href="<?= hfRelOsH($exportUrl) ?>">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>
            Exportar Excel
          </a>
        </div>
      </div>
    </form>

    <div class="row g-2 mb-3 hf-summary-row">
      <div class="col-sm-6 col-xl-2">
        <div class="hf-summary-card">
          <div class="hf-summary-icon"><i class="bi bi-clipboard2-check"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Total de OS</div>
            <div class="hf-summary-value"><?= (int)$summary['total_os'] ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-2">
        <div class="hf-summary-card hf-summary-primary">
          <div class="hf-summary-icon"><i class="bi bi-currency-dollar"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Valor total</div>
            <div class="hf-summary-value"><?= hfRelOsMoney($summary['valor_total']) ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-2">
        <div class="hf-summary-card hf-summary-success">
          <div class="hf-summary-icon"><i class="bi bi-cash-stack"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Valor recebido</div>
            <div class="hf-summary-value"><?= hfRelOsMoney($summary['valor_recebido']) ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-2">
        <div class="hf-summary-card hf-summary-warning">
          <div class="hf-summary-icon"><i class="bi bi-wallet2"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Saldo em aberto</div>
            <div class="hf-summary-value"><?= hfRelOsMoney($summary['saldo_aberto']) ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-2">
        <div class="hf-summary-card">
          <div class="hf-summary-icon"><i class="bi bi-kanban"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Status OS</div>
            <div class="hf-status-counts">
              <span><strong><?= (int)$summary['abertas'] ?></strong> abertas</span>
              <span><strong><?= (int)$summary['em_andamento'] ?></strong> and.</span>
              <span><strong><?= (int)$summary['concluidas'] ?></strong> concl.</span>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-2">
        <div class="hf-summary-card hf-summary-info">
          <div class="hf-summary-icon"><i class="bi bi-graph-up-arrow"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Ticket medio</div>
            <div class="hf-summary-value"><?= hfRelOsMoney($summary['ticket_medio']) ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="hf-table-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 hf-rel-os-table">
          <thead class="table-light">
            <tr>
              <th style="width:90px">No OS</th>
              <th>Cliente</th>
              <th>Status</th>
              <th>Prioridade</th>
              <th>Tecnico</th>
              <th>Abertura</th>
              <th class="text-end">Total</th>
              <th class="text-end">Valor pago</th>
              <th class="text-end">Saldo</th>
              <th>Financeiro</th>
              <th>Forma</th>
              <th>Pagamento</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr>
                <td colspan="12" class="text-center text-muted py-5">
                  <div class="hf-empty-state">
                    <i class="bi bi-inbox"></i>
                    <strong>Nenhuma OS encontrada</strong>
                    <span>Ajuste os filtros para ampliar a consulta.</span>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $numero = (int)($row['numero'] ?: $row['id']);
                  $abertura = $row['data_abertura'] ?: ($row['created_at'] ?? '');
                  $total = (float)($row['total'] ?? 0);
                  $valorPago = (float)($row['valor_pago'] ?? 0);
                  $saldo = max(0, $total - $valorPago);
                  $status = (string)($row['status'] ?? '');
                  $priority = (string)($row['prioridade'] ?? '');
                  $statusFin = (string)($row['status_financeiro'] ?? '');
                ?>
                <tr class="hf-rel-os-row">
                  <td data-label="No OS">
                    <span class="hf-os-number">#<?= $numero ?></span>
                  </td>
                  <td data-label="Cliente">
                    <div class="hf-client-cell">
                      <div class="hf-client-avatar"><?= hfRelOsH(strtoupper(substr((string)$row['cliente'], 0, 1))) ?></div>
                      <div class="hf-client-name"><?= hfRelOsH($row['cliente'] ?? '') ?></div>
                    </div>
                  </td>
                  <td data-label="Status">
                    <span class="badge bg-<?= hfRelOsH(hfRelOsStatusBadge($status)) ?> hf-status-badge">
                      <?= hfRelOsH(hfRelOsLabel($status)) ?>
                    </span>
                  </td>
                  <td data-label="Prioridade">
                    <span class="badge bg-<?= hfRelOsH(hfRelOsPriorityBadge($priority)) ?> hf-status-badge">
                      <?= hfRelOsH(hfRelOsLabel($priority)) ?>
                    </span>
                  </td>
                  <td data-label="Tecnico"><?= hfRelOsH($row['tecnico'] ?: '-') ?></td>
                  <td data-label="Abertura"><span class="hf-date-pill"><?= hfRelOsH(hfRelOsDateBr($abertura, true)) ?></span></td>
                  <td data-label="Total" class="text-end fw-semibold hf-money-cell"><?= hfRelOsMoney($total) ?></td>
                  <td data-label="Valor pago" class="text-end"><?= hfRelOsMoney($valorPago) ?></td>
                  <td data-label="Saldo" class="text-end fw-semibold <?= $saldo > 0 ? 'hf-open-balance' : 'hf-paid-balance' ?>">
                    <?= hfRelOsMoney($saldo) ?>
                  </td>
                  <td data-label="Financeiro">
                    <span class="badge bg-<?= hfRelOsH(hfRelOsFinBadge($statusFin)) ?> hf-status-badge">
                      <?= hfRelOsH(hfRelOsLabel($statusFin)) ?>
                    </span>
                  </td>
                  <td data-label="Forma"><?= hfRelOsH(hfRelOsLabel($row['forma_pagto'] ?? '')) ?></td>
                  <td data-label="Pagamento"><span class="hf-date-pill"><?= hfRelOsH(hfRelOsDateBr($row['data_pagto'] ?? '')) ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</main>

<style>
.hf-rel-os-page {
  min-height: calc(100vh - var(--topbar-h));
  overflow-x: hidden;
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .07), transparent 26rem),
    linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%);
}

.hf-rel-os-wrap {
  max-width: 1360px;
}

.hf-rel-os-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.hf-rel-os-title {
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

.hf-rel-os-top-actions {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: .5rem;
  flex-wrap: wrap;
}

.hf-filter-card,
.hf-table-card,
.hf-alert {
  border: 1px solid rgba(148, 163, 184, .22);
  border-radius: .95rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 10px 28px rgba(15, 23, 42, .065);
}

.hf-filter-card {
  padding: .85rem;
  backdrop-filter: blur(8px);
}

.hf-filter-card .form-label {
  margin-bottom: .25rem;
  color: #64748b;
  font-size: .68rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.hf-filter-card .form-control,
.hf-filter-card .form-select {
  min-height: 38px;
  border-radius: .66rem;
  border-color: #dbe3ee;
  background-color: #f8fafc;
  font-size: .88rem;
}

.hf-filter-card .form-control:focus,
.hf-filter-card .form-select:focus {
  border-color: rgba(var(--bs-primary-rgb), .55);
  box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
  background-color: #fff;
}

.hf-search-input {
  position: relative;
}

.hf-search-input > i {
  position: absolute;
  left: .8rem;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}

.hf-search-input .form-control {
  padding-left: 2.25rem;
}

.hf-btn-filter,
.hf-export-btn,
.hf-soft-btn {
  min-height: 38px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: .66rem;
  padding-left: .72rem;
  padding-right: .72rem;
  font-weight: 800;
  font-size: .86rem;
}

.hf-btn-filter,
.hf-export-btn {
  box-shadow: 0 7px 16px rgba(var(--bs-primary-rgb), .13);
}

.hf-summary-card {
  min-height: 92px;
  display: flex;
  align-items: center;
  gap: .68rem;
  padding: .78rem .82rem;
  border: 1px solid rgba(148, 163, 184, .22);
  border-radius: .92rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 10px 26px rgba(15, 23, 42, .055);
}

.hf-summary-icon {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  display: grid;
  place-items: center;
  border-radius: .78rem;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .11);
  font-size: 1.08rem;
}

.hf-summary-body {
  min-width: 0;
}

.hf-summary-label {
  color: #64748b;
  font-size: .66rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .05em;
}

.hf-summary-value {
  margin-top: .1rem;
  color: #0f172a;
  font-size: 1.18rem;
  font-weight: 900;
  line-height: 1.15;
}

.hf-status-counts {
  display: flex;
  align-items: center;
  gap: .5rem;
  margin-top: .18rem;
  white-space: nowrap;
}

.hf-status-counts span {
  display: inline-flex;
  align-items: center;
  gap: .2rem;
  color: #64748b;
  font-size: .7rem;
  font-weight: 800;
  line-height: 1;
}

.hf-status-counts strong {
  color: #0f172a;
  font-size: .95rem;
  font-weight: 900;
}

.hf-summary-success .hf-summary-icon {
  color: #047857;
  background: rgba(16, 185, 129, .13);
}

.hf-summary-warning .hf-summary-icon {
  color: #b45309;
  background: rgba(245, 158, 11, .14);
}

.hf-summary-info .hf-summary-icon {
  color: #075985;
  background: rgba(14, 165, 233, .13);
}

.hf-table-card {
  overflow: hidden;
}

.hf-table-card .table-responsive {
  max-height: 68vh;
  overflow-x: visible;
}

.hf-rel-os-table {
  --bs-table-bg: transparent;
  width: 100%;
}

.hf-rel-os-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  padding: .72rem .58rem;
  border-bottom: 1px solid rgba(148, 163, 184, .22);
  background: rgba(248, 250, 252, .98);
  color: #475569;
  font-size: .64rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .045em;
  white-space: nowrap;
}

.hf-rel-os-table tbody td {
  padding: .68rem .58rem;
  border-color: rgba(226, 232, 240, .72);
  color: #334155;
  font-size: .82rem;
}

.hf-rel-os-table tbody tr {
  transition: background-color .16s ease, box-shadow .16s ease;
}

.hf-rel-os-table tbody tr:hover {
  background: rgba(var(--bs-primary-rgb), .035);
  box-shadow: inset 2px 0 0 rgba(var(--bs-primary-rgb), .38);
}

.hf-os-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2.35rem;
  padding: .2rem .46rem;
  border-radius: 999px;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .10);
  font-weight: 900;
}

.hf-client-cell {
  display: flex;
  align-items: center;
  gap: .62rem;
  min-width: 0;
}

.hf-client-avatar {
  width: 30px;
  height: 30px;
  flex: 0 0 30px;
  display: grid;
  place-items: center;
  border-radius: 999px;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .11);
  font-weight: 900;
}

.hf-client-name {
  min-width: 0;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #0f172a;
  font-weight: 700;
}

.hf-date-pill,
.hf-money-cell {
  white-space: nowrap;
}

.hf-open-balance {
  color: #b45309 !important;
}

.hf-paid-balance {
  color: #047857 !important;
}

.hf-status-badge {
  border-radius: 999px;
  padding: .32rem .46rem;
  font-size: .7rem;
  font-weight: 800;
  letter-spacing: .01em;
}

.hf-status-badge.bg-warning {
  color: #8a4b00 !important;
  background: #fff3cd !important;
}

.hf-status-badge.bg-success {
  color: #047857 !important;
  background: #d1fae5 !important;
}

.hf-status-badge.bg-secondary {
  color: #475569 !important;
  background: #e2e8f0 !important;
}

.hf-status-badge.bg-info {
  color: #075985 !important;
  background: #dbeafe !important;
}

.hf-status-badge.bg-danger {
  color: #b91c1c !important;
  background: #fee2e2 !important;
}

.hf-status-badge.bg-dark {
  color: #475569 !important;
  background: #cbd5e1 !important;
}

.hf-empty-state {
  display: inline-flex;
  align-items: center;
  flex-direction: column;
  gap: .3rem;
}

.hf-empty-state i {
  color: #94a3b8;
  font-size: 2rem;
}

.hf-empty-state strong {
  color: #475569;
}

.hf-empty-state span {
  color: #94a3b8;
  font-size: .88rem;
}

@media (min-width: 768px) {
  .hf-rel-os-table {
    table-layout: fixed;
  }

  .hf-rel-os-table th:nth-child(1) { width: 6%; }
  .hf-rel-os-table th:nth-child(2) { width: 17%; }
  .hf-rel-os-table th:nth-child(3) { width: 9%; }
  .hf-rel-os-table th:nth-child(4) { width: 8%; }
  .hf-rel-os-table th:nth-child(5) { width: 10%; }
  .hf-rel-os-table th:nth-child(6) { width: 11%; }
  .hf-rel-os-table th:nth-child(7),
  .hf-rel-os-table th:nth-child(8),
  .hf-rel-os-table th:nth-child(9) { width: 8%; }
  .hf-rel-os-table th:nth-child(10) { width: 9%; }
  .hf-rel-os-table th:nth-child(11) { width: 7%; }
  .hf-rel-os-table th:nth-child(12) { width: 7%; }

  .hf-rel-os-table td,
  .hf-rel-os-table th {
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

@media (max-width: 1199.98px) {
  .hf-rel-os-top {
    flex-direction: column;
  }

  .hf-rel-os-top-actions {
    width: 100%;
    justify-content: flex-start;
  }
}

@media (min-width: 768px) and (max-width: 1279.98px) {
  .hf-rel-os-table th:nth-child(4),
  .hf-rel-os-table td:nth-child(4),
  .hf-rel-os-table th:nth-child(11),
  .hf-rel-os-table td:nth-child(11),
  .hf-rel-os-table th:nth-child(12),
  .hf-rel-os-table td:nth-child(12) {
    display: none;
  }

  .hf-rel-os-table th:nth-child(1) { width: 7%; }
  .hf-rel-os-table th:nth-child(2) { width: 22%; }
  .hf-rel-os-table th:nth-child(3) { width: 10%; }
  .hf-rel-os-table th:nth-child(5) { width: 12%; }
  .hf-rel-os-table th:nth-child(6) { width: 13%; }
  .hf-rel-os-table th:nth-child(7),
  .hf-rel-os-table th:nth-child(8),
  .hf-rel-os-table th:nth-child(9) { width: 10%; }
  .hf-rel-os-table th:nth-child(10) { width: 12%; }
}

@media (max-width: 767.98px) {
  .hf-rel-os-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .hf-filter-card,
  .hf-table-card {
    border-radius: .9rem;
  }

  .hf-summary-card {
    min-height: auto;
  }

  .hf-table-card {
    padding: .85rem;
  }

  .hf-table-card .table-responsive {
    overflow-x: visible;
    max-height: none;
  }

  .hf-rel-os-table,
  .hf-rel-os-table thead,
  .hf-rel-os-table tbody,
  .hf-rel-os-table th,
  .hf-rel-os-table td,
  .hf-rel-os-table tr {
    display: block;
    width: 100%;
    box-sizing: border-box;
  }

  .hf-rel-os-table thead {
    display: none;
  }

  .hf-rel-os-table tbody tr.hf-rel-os-row {
    border: 1px solid rgba(226, 232, 240, .9);
    border-radius: .95rem;
    padding: .85rem;
    margin: .75rem 0;
    background: rgba(248, 250, 252, .82);
    box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
  }

  .hf-rel-os-table tbody td {
    display: grid;
    grid-template-columns: minmax(112px, 40%) 1fr;
    align-items: center;
    gap: .55rem;
    padding: .42rem 0;
    border: 0 !important;
    word-break: break-word;
  }

  .hf-rel-os-table td::before {
    content: attr(data-label);
    color: #64748b;
    font-size: .74rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .hf-rel-os-table td[data-label="Cliente"] {
    grid-template-columns: 1fr;
  }

  .hf-rel-os-table td[data-label="Cliente"]::before {
    margin-bottom: .2rem;
  }

  .hf-client-name {
    max-width: 100%;
    white-space: normal;
  }

  .hf-rel-os-table .text-end {
    text-align: left !important;
  }
}

[data-bs-theme="dark"] .hf-rel-os-page {
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .hf-filter-card,
[data-bs-theme="dark"] .hf-table-card,
[data-bs-theme="dark"] .hf-summary-card,
[data-bs-theme="dark"] .hf-alert {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-filter-card .form-control,
[data-bs-theme="dark"] .hf-filter-card .form-select {
  background-color: rgba(15, 23, 42, .9);
  border-color: rgba(148, 163, 184, .24);
}

[data-bs-theme="dark"] .hf-summary-value,
[data-bs-theme="dark"] .hf-client-name,
[data-bs-theme="dark"] .hf-status-counts strong {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-status-counts span {
  color: #cbd5e1;
}

[data-bs-theme="dark"] .hf-rel-os-table thead th {
  background: rgba(30, 41, 59, .98);
  color: #cbd5e1;
}

[data-bs-theme="dark"] .hf-rel-os-table tbody td,
[data-bs-theme="dark"] .hf-date-pill {
  color: #cbd5e1;
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-rel-os-table tbody tr.hf-rel-os-row {
  background: rgba(15, 23, 42, .82);
  border-color: rgba(148, 163, 184, .18);
}
</style>

<?php require_once __DIR__ . '/_layout_end.php'; ?>
