<?php
require_once __DIR__ . '/auth.php';
requireAdmin();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_relatorio_financeiro_query.php';

function hfRelFinH($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hfRelFinMoneyBr($value)
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function hfRelFinDateBr($value)
{
    if (!$value || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    $ts = strtotime((string)$value);
    if (!$ts) {
        return '-';
    }

    return date('d/m/Y', $ts);
}

function hfRelFinLabel($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    return ucfirst(str_replace('_', ' ', $value));
}

function hfRelFinBadgeClass($type, $value)
{
    $value = strtolower((string)$value);

    if ($type === 'origem') {
        return $value === 'os' ? 'primary' : 'info';
    }

    if ($type === 'tipo') {
        return $value === 'saida' ? 'danger' : 'success';
    }

    if ($type === 'status') {
        $map = [
            'aberto' => 'secondary',
            'parcial' => 'warning',
            'pago' => 'success',
            'cancelado' => 'dark',
        ];
        return $map[$value] ?? 'secondary';
    }

    if ($type === 'situacao') {
        $map = [
            'pago' => 'success',
            'em_aberto' => 'secondary',
            'parcial' => 'warning',
            'atrasado' => 'danger',
            'cancelado' => 'dark',
        ];
        return $map[$value] ?? 'secondary';
    }

    return 'secondary';
}

$result = [
    'ok' => false,
    'message' => 'Nao foi possivel carregar o relatorio financeiro.',
    'filters' => hfRelFinReadFilters(),
    'rows' => [],
    'resumo' => hfRelFinEmptySummary(),
];

try {
    $pdo = db();
    $tid = (int)tenantId();
    $result = hfRelFinFetch($pdo, $tid);
} catch (Throwable $e) {
    error_log('relatorio_financeiro.php: ' . $e->getMessage());
}

$filters = $result['filters'];
$rows = $result['rows'];
$resumo = $result['resumo'];
$exportQuery = http_build_query($filters);
$exportUrl = '/relatorio_financeiro_excel.php' . ($exportQuery ? '?' . $exportQuery : '');

require_once __DIR__ . '/_layout_start.php';
?>

<?php include __DIR__ . '/_sidebar.php'; ?>

<main class="hf-content hf-rel-fin-page">
  <div class="container-fluid py-4 hf-rel-fin-wrap">
    <div class="hf-rel-fin-top mb-3">
      <div class="hf-rel-fin-title">
        <div class="hf-page-kicker">Relatorios</div>
        <h1 class="h4 mb-0">Financeiro</h1>
        <div class="hf-page-subtitle">Acompanhe recebimentos, pendencias, saidas e saldo financeiro.</div>
      </div>

      <div class="hf-rel-fin-actions">
        <a href="/relatorios.php?m=relatorios" class="btn btn-outline-secondary btn-sm hf-soft-btn">
          <i class="bi bi-arrow-left me-1"></i>
          Relatorios
        </a>
      </div>
    </div>

    <?php if (!$result['ok']): ?>
      <div class="alert alert-warning hf-alert mb-3" role="alert">
        <?= hfRelFinH($result['message']) ?>
      </div>
    <?php endif; ?>

    <form class="hf-filter-card mb-3" method="get" action="/relatorio_financeiro.php">
      <input type="hidden" name="m" value="relatorios">

      <div class="row g-2 align-items-end">
        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Data inicial</label>
          <input type="date" name="data_ini" value="<?= hfRelFinH($filters['data_ini']) ?>" class="form-control">
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Data final</label>
          <input type="date" name="data_fim" value="<?= hfRelFinH($filters['data_fim']) ?>" class="form-control">
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Tipo de data</label>
          <select name="tipo_data" class="form-select">
            <?php foreach (['vencimento' => 'Vencimento', 'pagamento' => 'Pagamento', 'lancamento' => 'Lancamento'] as $key => $label): ?>
              <option value="<?= hfRelFinH($key) ?>" <?= $filters['tipo_data'] === $key ? 'selected' : '' ?>>
                <?= hfRelFinH($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Origem</label>
          <select name="origem" class="form-select">
            <?php foreach (['todos' => 'Todos', 'os' => 'OS', 'lancamentos' => 'Lancamentos'] as $key => $label): ?>
              <option value="<?= hfRelFinH($key) ?>" <?= $filters['origem'] === $key ? 'selected' : '' ?>>
                <?= hfRelFinH($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Tipo</label>
          <select name="tipo" class="form-select">
            <option value="">Todos</option>
            <?php foreach (['entrada' => 'Entrada', 'saida' => 'Saida'] as $key => $label): ?>
              <option value="<?= hfRelFinH($key) ?>" <?= $filters['tipo'] === $key ? 'selected' : '' ?>>
                <?= hfRelFinH($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="">Todos</option>
            <?php foreach (['aberto' => 'Aberto', 'parcial' => 'Parcial', 'pago' => 'Pago', 'cancelado' => 'Cancelado'] as $key => $label): ?>
              <option value="<?= hfRelFinH($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>>
                <?= hfRelFinH($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Forma pagamento</label>
          <input type="text" name="forma_pagamento" value="<?= hfRelFinH($filters['forma_pagamento']) ?>" class="form-control" placeholder="Pix, cartao...">
        </div>

        <div class="col-md-6 col-xl-4">
          <label class="form-label">Busca</label>
          <div class="hf-search-input">
            <i class="bi bi-search"></i>
            <input type="text" name="busca" value="<?= hfRelFinH($filters['busca']) ?>" class="form-control" placeholder="Cliente, descricao ou No OS">
          </div>
        </div>

        <div class="col-sm-4 col-md-2 col-xl-2 d-grid">
          <button class="btn btn-primary hf-btn-filter" type="submit">
            <i class="bi bi-search me-1"></i>
            Filtrar
          </button>
        </div>

        <div class="col-sm-4 col-md-2 col-xl-2 d-grid">
          <a class="btn btn-outline-secondary hf-soft-btn" href="/relatorio_financeiro.php?m=relatorios">
            <i class="bi bi-x-lg me-1"></i>
            Limpar
          </a>
        </div>

        <div class="col-sm-4 col-md-3 col-xl-2 d-grid">
          <a class="btn btn-success hf-export-btn" href="<?= hfRelFinH($exportUrl) ?>">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>
            Exportar Excel
          </a>
        </div>
      </div>
    </form>

    <div class="row g-2 mb-3 hf-summary-row">
      <div class="col-sm-6 col-xl-2">
        <div class="hf-summary-card hf-summary-success">
          <div class="hf-summary-icon"><i class="bi bi-cash-stack"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Recebido</div>
            <div class="hf-summary-value"><?= hfRelFinMoneyBr($resumo['recebido']) ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-2">
        <div class="hf-summary-card hf-summary-warning">
          <div class="hf-summary-icon"><i class="bi bi-wallet2"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">A receber</div>
            <div class="hf-summary-value"><?= hfRelFinMoneyBr($resumo['a_receber']) ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-2">
        <div class="hf-summary-card hf-summary-danger">
          <div class="hf-summary-icon"><i class="bi bi-exclamation-triangle"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Em atraso</div>
            <div class="hf-summary-value"><?= hfRelFinMoneyBr($resumo['em_atraso']) ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-2">
        <div class="hf-summary-card hf-summary-danger">
          <div class="hf-summary-icon"><i class="bi bi-arrow-up-circle"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Saidas pagas</div>
            <div class="hf-summary-value"><?= hfRelFinMoneyBr($resumo['saidas_pagas']) ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-2">
        <div class="hf-summary-card hf-summary-info">
          <div class="hf-summary-icon"><i class="bi bi-bank"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Saldo liquido</div>
            <div class="hf-summary-value <?= $resumo['saldo_liquido'] >= 0 ? 'text-success' : 'text-danger' ?>">
              <?= hfRelFinMoneyBr($resumo['saldo_liquido']) ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-2">
        <div class="hf-summary-card">
          <div class="hf-summary-icon"><i class="bi bi-graph-up-arrow"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Previsto liquido</div>
            <div class="hf-summary-value <?= $resumo['previsto_liquido'] >= 0 ? 'text-success' : 'text-danger' ?>">
              <?= hfRelFinMoneyBr($resumo['previsto_liquido']) ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="hf-table-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 hf-rel-fin-table">
          <thead class="table-light">
            <tr>
              <th>Origem</th>
              <th>No OS</th>
              <th>Cliente/Descricao</th>
              <th>Tipo</th>
              <th>Vencimento</th>
              <th>Pagamento</th>
              <th class="text-end">Previsto</th>
              <th class="text-end">Pago</th>
              <th class="text-end">Saldo</th>
              <th>Status</th>
              <th>Situacao</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr>
                <td colspan="11" class="text-center text-muted py-5">
                  <div class="hf-empty-state">
                    <i class="bi bi-inbox"></i>
                    <strong>Nenhum movimento encontrado</strong>
                    <span>Ajuste os filtros para ampliar a consulta.</span>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr class="hf-rel-fin-row">
                  <td data-label="Origem">
                    <span class="badge bg-<?= hfRelFinH(hfRelFinBadgeClass('origem', $row['origem'])) ?> hf-status-badge">
                      <?= hfRelFinH($row['origem']) ?>
                    </span>
                  </td>
                  <td data-label="No OS"><?= hfRelFinH($row['numero_os'] ?: '-') ?></td>
                  <td data-label="Cliente/Descricao">
                    <div class="hf-desc-cell"><?= hfRelFinH($row['cliente_descricao']) ?></div>
                  </td>
                  <td data-label="Tipo">
                    <span class="badge bg-<?= hfRelFinH(hfRelFinBadgeClass('tipo', $row['tipo'])) ?> hf-status-badge">
                      <?= hfRelFinH(hfRelFinLabel($row['tipo'])) ?>
                    </span>
                  </td>
                  <td data-label="Vencimento"><span class="hf-date-pill"><?= hfRelFinDateBr($row['vencimento']) ?></span></td>
                  <td data-label="Pagamento"><span class="hf-date-pill"><?= hfRelFinDateBr($row['pagamento']) ?></span></td>
                  <td data-label="Previsto" class="text-end fw-semibold hf-money-cell"><?= hfRelFinMoneyBr($row['valor_previsto']) ?></td>
                  <td data-label="Pago" class="text-end"><?= hfRelFinMoneyBr($row['valor_pago']) ?></td>
                  <td data-label="Saldo" class="text-end fw-semibold <?= $row['saldo'] > 0 ? 'hf-open-balance' : 'hf-paid-balance' ?>">
                    <?= hfRelFinMoneyBr($row['saldo']) ?>
                  </td>
                  <td data-label="Status">
                    <span class="badge bg-<?= hfRelFinH(hfRelFinBadgeClass('status', $row['status'])) ?> hf-status-badge">
                      <?= hfRelFinH(hfRelFinLabel($row['status'])) ?>
                    </span>
                  </td>
                  <td data-label="Situacao">
                    <span class="badge bg-<?= hfRelFinH(hfRelFinBadgeClass('situacao', $row['situacao'])) ?> hf-status-badge">
                      <?= hfRelFinH(hfRelFinLabel($row['situacao'])) ?>
                    </span>
                  </td>
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
.hf-rel-fin-page {
  min-height: calc(100vh - var(--topbar-h));
  overflow-x: hidden;
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .07), transparent 26rem),
    linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%);
}

.hf-rel-fin-wrap {
  max-width: 1360px;
}

.hf-rel-fin-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.hf-rel-fin-title {
  padding: .25rem .1rem .55rem;
}

.hf-page-kicker {
  margin-bottom: .12rem;
  color: rgba(var(--bs-primary-rgb), .88);
  font-size: .74rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .08em;
}

.hf-page-subtitle {
  margin-top: .2rem;
  color: #64748b;
  font-size: .9rem;
}

.hf-rel-fin-actions {
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
  font-size: .86rem;
  font-weight: 800;
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
  font-size: 1.08rem;
  font-weight: 900;
  line-height: 1.15;
}

.hf-summary-success .hf-summary-icon {
  color: #047857;
  background: rgba(16, 185, 129, .13);
}

.hf-summary-warning .hf-summary-icon {
  color: #b45309;
  background: rgba(245, 158, 11, .14);
}

.hf-summary-danger .hf-summary-icon {
  color: #b91c1c;
  background: rgba(239, 68, 68, .12);
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

.hf-rel-fin-table {
  --bs-table-bg: transparent;
  width: 100%;
}

.hf-rel-fin-table thead th {
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

.hf-rel-fin-table tbody td {
  padding: .68rem .58rem;
  border-color: rgba(226, 232, 240, .72);
  color: #334155;
  font-size: .82rem;
}

.hf-rel-fin-table tbody tr {
  transition: background-color .16s ease, box-shadow .16s ease;
}

.hf-rel-fin-table tbody tr:hover {
  background: rgba(var(--bs-primary-rgb), .035);
  box-shadow: inset 2px 0 0 rgba(var(--bs-primary-rgb), .38);
}

.hf-desc-cell {
  min-width: 0;
  overflow: hidden;
  color: #0f172a;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
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

.hf-status-badge.bg-primary {
  color: var(--bs-primary) !important;
  background: rgba(var(--bs-primary-rgb), .11) !important;
}

.hf-status-badge.bg-info {
  color: #075985 !important;
  background: #dbeafe !important;
}

.hf-status-badge.bg-success {
  color: #047857 !important;
  background: #d1fae5 !important;
}

.hf-status-badge.bg-warning {
  color: #8a4b00 !important;
  background: #fff3cd !important;
}

.hf-status-badge.bg-danger {
  color: #b91c1c !important;
  background: #fee2e2 !important;
}

.hf-status-badge.bg-secondary,
.hf-status-badge.bg-dark {
  color: #475569 !important;
  background: #e2e8f0 !important;
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
  .hf-rel-fin-table {
    table-layout: fixed;
  }

  .hf-rel-fin-table th:nth-child(1) { width: 8%; }
  .hf-rel-fin-table th:nth-child(2) { width: 7%; }
  .hf-rel-fin-table th:nth-child(3) { width: 22%; }
  .hf-rel-fin-table th:nth-child(4) { width: 8%; }
  .hf-rel-fin-table th:nth-child(5),
  .hf-rel-fin-table th:nth-child(6) { width: 9%; }
  .hf-rel-fin-table th:nth-child(7),
  .hf-rel-fin-table th:nth-child(8),
  .hf-rel-fin-table th:nth-child(9) { width: 9%; }
  .hf-rel-fin-table th:nth-child(10),
  .hf-rel-fin-table th:nth-child(11) { width: 9%; }

  .hf-rel-fin-table td,
  .hf-rel-fin-table th {
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

@media (max-width: 1199.98px) {
  .hf-rel-fin-top {
    flex-direction: column;
  }

  .hf-rel-fin-actions {
    width: 100%;
    justify-content: flex-start;
  }
}

@media (min-width: 768px) and (max-width: 1279.98px) {
  .hf-rel-fin-table th:nth-child(2),
  .hf-rel-fin-table td:nth-child(2),
  .hf-rel-fin-table th:nth-child(6),
  .hf-rel-fin-table td:nth-child(6) {
    display: none;
  }

  .hf-rel-fin-table th:nth-child(1) { width: 9%; }
  .hf-rel-fin-table th:nth-child(3) { width: 24%; }
  .hf-rel-fin-table th:nth-child(4) { width: 9%; }
  .hf-rel-fin-table th:nth-child(5) { width: 11%; }
  .hf-rel-fin-table th:nth-child(7),
  .hf-rel-fin-table th:nth-child(8),
  .hf-rel-fin-table th:nth-child(9) { width: 11%; }
  .hf-rel-fin-table th:nth-child(10),
  .hf-rel-fin-table th:nth-child(11) { width: 12%; }
}

@media (max-width: 767.98px) {
  .hf-rel-fin-page {
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

  .hf-rel-fin-table,
  .hf-rel-fin-table thead,
  .hf-rel-fin-table tbody,
  .hf-rel-fin-table th,
  .hf-rel-fin-table td,
  .hf-rel-fin-table tr {
    display: block;
    width: 100%;
    box-sizing: border-box;
  }

  .hf-rel-fin-table thead {
    display: none;
  }

  .hf-rel-fin-table tbody tr.hf-rel-fin-row {
    border: 1px solid rgba(226, 232, 240, .9);
    border-radius: .95rem;
    padding: .85rem;
    margin: .75rem 0;
    background: rgba(248, 250, 252, .82);
    box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
  }

  .hf-rel-fin-table tbody td {
    display: grid;
    grid-template-columns: minmax(112px, 40%) 1fr;
    align-items: center;
    gap: .55rem;
    padding: .42rem 0;
    border: 0 !important;
    word-break: break-word;
  }

  .hf-rel-fin-table td::before {
    content: attr(data-label);
    color: #64748b;
    font-size: .74rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .hf-rel-fin-table td[data-label="Cliente/Descricao"] {
    grid-template-columns: 1fr;
  }

  .hf-rel-fin-table td[data-label="Cliente/Descricao"]::before {
    margin-bottom: .2rem;
  }

  .hf-desc-cell {
    white-space: normal;
  }

  .hf-rel-fin-table .text-end {
    text-align: left !important;
  }
}

[data-bs-theme="dark"] .hf-rel-fin-page {
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
[data-bs-theme="dark"] .hf-desc-cell {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-rel-fin-table thead th {
  background: rgba(30, 41, 59, .98);
  color: #cbd5e1;
}

[data-bs-theme="dark"] .hf-rel-fin-table tbody td,
[data-bs-theme="dark"] .hf-date-pill {
  color: #cbd5e1;
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-rel-fin-table tbody tr.hf-rel-fin-row {
  background: rgba(15, 23, 42, .82);
  border-color: rgba(148, 163, 184, .18);
}
</style>

<?php require_once __DIR__ . '/_layout_end.php'; ?>
