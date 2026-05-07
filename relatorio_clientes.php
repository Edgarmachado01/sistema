<?php
require_once __DIR__ . '/auth.php';
requireLogin();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/_relatorio_clientes_query.php';

function hfRelCliH($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function hfRelCliMoneyBr($value)
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function hfRelCliDateBr($value)
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

function hfRelCliLabel($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '-';
    }

    return ucfirst(str_replace('_', ' ', $value));
}

function hfRelCliBadgeClass($type, $value)
{
    $value = strtolower((string)$value);

    if ($type === 'status') {
        return $value === 'ativo' ? 'success' : 'secondary';
    }

    if ($type === 'perfil') {
        $map = [
            'novo' => 'info',
            'recorrente' => 'success',
            'sem_os' => 'secondary',
            'com_os' => 'primary',
        ];
        return $map[$value] ?? 'secondary';
    }

    if ($type === 'os') {
        $map = [
            'aberta' => 'secondary',
            'em_andamento' => 'warning',
            'concluida' => 'success',
            'cancelada' => 'dark',
        ];
        return $map[$value] ?? 'secondary';
    }

    return 'secondary';
}

$result = [
    'ok' => false,
    'message' => 'Nao foi possivel carregar o relatorio de clientes.',
    'filters' => hfRelCliReadFilters(false),
    'rows' => [],
    'resumo' => hfRelCliEmptySummary(),
];

try {
    $pdo = db();
    $tid = (int)tenantId();
    $result = hfRelCliFetch($pdo, $tid);
} catch (Throwable $e) {
    error_log('relatorio_clientes.php: ' . $e->getMessage());
}

$filters = $result['filters'];
$rows = $result['rows'];
$resumo = $result['resumo'];
$exportQuery = http_build_query($filters);
$exportUrl = '/relatorio_clientes_excel.php' . ($exportQuery ? '?' . $exportQuery : '');

require_once __DIR__ . '/_layout_start.php';
?>

<?php include __DIR__ . '/_sidebar.php'; ?>

<main class="hf-content hf-rel-cli-page">
  <div class="container-fluid py-4 hf-rel-cli-wrap">
    <div class="hf-rel-cli-top mb-3">
      <div class="hf-rel-cli-title">
        <div class="hf-page-kicker">Relatorios</div>
        <h1 class="h4 mb-0">Clientes</h1>
        <div class="hf-page-subtitle">Analise regioes atendidas, recorrencia e movimentacao da carteira.</div>
      </div>

      <div class="hf-rel-cli-actions">
        <a href="/relatorios.php?m=relatorios" class="btn btn-outline-secondary btn-sm hf-soft-btn">
          <i class="bi bi-arrow-left me-1"></i>
          Relatorios
        </a>
      </div>
    </div>

    <?php if (!$result['ok']): ?>
      <div class="alert alert-warning hf-alert mb-3" role="alert">
        <?= hfRelCliH($result['message']) ?>
      </div>
    <?php endif; ?>

    <form class="hf-filter-card mb-3" method="get" action="/relatorio_clientes.php">
      <input type="hidden" name="m" value="relatorios">

      <div class="row g-2 align-items-end">
        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Data inicial</label>
          <input type="date" name="data_ini" value="<?= hfRelCliH($filters['data_ini']) ?>" class="form-control">
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Data final</label>
          <input type="date" name="data_fim" value="<?= hfRelCliH($filters['data_fim']) ?>" class="form-control">
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Base periodo</label>
          <select name="base_periodo" class="form-select">
            <option value="os_abertura" <?= $filters['base_periodo'] === 'os_abertura' ? 'selected' : '' ?>>Abertura da OS</option>
            <?php if (!empty($filters['has_created_at'])): ?>
              <option value="cliente_cadastro" <?= $filters['base_periodo'] === 'cliente_cadastro' ? 'selected' : '' ?>>Cadastro cliente</option>
            <?php endif; ?>
          </select>
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Status cliente</label>
          <select name="status_cliente" class="form-select">
            <option value="">Todos</option>
            <option value="ativo" <?= $filters['status_cliente'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
            <option value="inativo" <?= $filters['status_cliente'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
          </select>
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Cidade</label>
          <input type="text" name="cidade" value="<?= hfRelCliH($filters['cidade']) ?>" class="form-control" placeholder="Cidade">
        </div>

        <div class="col-sm-6 col-md-4 col-xl-2">
          <label class="form-label">Bairro</label>
          <input type="text" name="bairro" value="<?= hfRelCliH($filters['bairro']) ?>" class="form-control" placeholder="Bairro">
        </div>

        <div class="col-sm-4 col-md-2 col-xl-1">
          <label class="form-label">UF</label>
          <input type="text" name="uf" value="<?= hfRelCliH($filters['uf']) ?>" maxlength="2" class="form-control" placeholder="UF">
        </div>

        <div class="col-sm-8 col-md-4 col-xl-2">
          <label class="form-label">Perfil</label>
          <select name="perfil" class="form-select">
            <?php
              $perfis = [
                'todos' => 'Todos',
                'novos' => 'Novos',
                'recorrentes' => 'Recorrentes',
                'sem_os' => 'Sem OS',
                'com_os' => 'Com OS',
                'sem_os_periodo' => 'Sem OS no periodo',
              ];
              foreach ($perfis as $key => $label):
            ?>
              <option value="<?= hfRelCliH($key) ?>" <?= $filters['perfil'] === $key ? 'selected' : '' ?>>
                <?= hfRelCliH($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6 col-xl-3">
          <label class="form-label">Busca</label>
          <div class="hf-search-input">
            <i class="bi bi-search"></i>
            <input type="text" name="busca" value="<?= hfRelCliH($filters['busca']) ?>" class="form-control" placeholder="Nome, documento, telefone ou cidade">
          </div>
        </div>

        <div class="col-sm-4 col-md-2 col-xl-2 d-grid">
          <button class="btn btn-primary hf-btn-filter" type="submit">
            <i class="bi bi-search me-1"></i>
            Filtrar
          </button>
        </div>

        <div class="col-sm-4 col-md-2 col-xl-2 d-grid">
          <a class="btn btn-outline-secondary hf-soft-btn" href="/relatorio_clientes.php?m=relatorios">
            <i class="bi bi-x-lg me-1"></i>
            Limpar
          </a>
        </div>

        <div class="col-sm-4 col-md-3 col-xl-2 d-grid">
          <a class="btn btn-success hf-export-btn" href="<?= hfRelCliH($exportUrl) ?>">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>
            Exportar Excel
          </a>
        </div>
      </div>
    </form>

    <div class="row g-2 mb-3 hf-summary-row">
      <div class="col-sm-6 col-xl-3">
        <div class="hf-summary-card">
          <div class="hf-summary-icon"><i class="bi bi-people"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Total de clientes</div>
            <div class="hf-summary-value"><?= (int)$resumo['total_clientes'] ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="hf-summary-card hf-summary-success">
          <div class="hf-summary-icon"><i class="bi bi-clipboard2-check"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Clientes com OS</div>
            <div class="hf-summary-value"><?= (int)$resumo['clientes_com_os'] ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="hf-summary-card hf-summary-info">
          <div class="hf-summary-icon"><i class="bi bi-person-plus"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Clientes novos</div>
            <div class="hf-summary-value"><?= (int)$resumo['clientes_novos'] ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="hf-summary-card hf-summary-primary">
          <div class="hf-summary-icon"><i class="bi bi-arrow-repeat"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Recorrentes</div>
            <div class="hf-summary-value"><?= (int)$resumo['clientes_recorrentes'] ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="hf-summary-card hf-summary-warning">
          <div class="hf-summary-icon"><i class="bi bi-person-dash"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Sem OS</div>
            <div class="hf-summary-value"><?= (int)$resumo['clientes_sem_os'] ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="hf-summary-card">
          <div class="hf-summary-icon"><i class="bi bi-currency-dollar"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Ticket medio geral</div>
            <div class="hf-summary-value"><?= hfRelCliMoneyBr($resumo['ticket_medio_geral']) ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="hf-summary-card">
          <div class="hf-summary-icon"><i class="bi bi-geo-alt"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Cidade top</div>
            <div class="hf-summary-value hf-summary-text"><?= hfRelCliH($resumo['cidade_top'] ?: '-') ?></div>
          </div>
        </div>
      </div>

      <div class="col-sm-6 col-xl-3">
        <div class="hf-summary-card">
          <div class="hf-summary-icon"><i class="bi bi-signpost"></i></div>
          <div class="hf-summary-body">
            <div class="hf-summary-label">Bairro top</div>
            <div class="hf-summary-value hf-summary-text"><?= hfRelCliH($resumo['bairro_top'] ?: '-') ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="hf-table-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 hf-rel-cli-table">
          <thead class="table-light">
            <tr>
              <th>Cliente</th>
              <th>Documento</th>
              <th>Telefone</th>
              <th>Cidade/Bairro/UF</th>
              <th>Status</th>
              <th class="text-end">Total OS</th>
              <th class="text-end">OS periodo</th>
              <th>Ultima OS</th>
              <th>Status ultima</th>
              <th class="text-end">Faturado</th>
              <th class="text-end">Recebido</th>
              <th class="text-end">Saldo</th>
              <th class="text-end">Ticket</th>
              <th>Perfil</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr>
                <td colspan="14" class="text-center text-muted py-5">
                  <div class="hf-empty-state">
                    <i class="bi bi-inbox"></i>
                    <strong>Nenhum cliente encontrado</strong>
                    <span>Ajuste os filtros para ampliar a consulta.</span>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                  $local = trim(($row['cidade'] ?: '') . (($row['bairro'] ?? '') ? ' / ' . $row['bairro'] : '') . (($row['uf'] ?? '') ? ' / ' . $row['uf'] : ''), ' /');
                ?>
                <tr class="hf-rel-cli-row">
                  <td data-label="Cliente">
                    <div class="hf-client-cell">
                      <div class="hf-client-avatar"><?= hfRelCliH(strtoupper(substr((string)$row['cliente'], 0, 1))) ?></div>
                      <div class="hf-client-name"><?= hfRelCliH($row['cliente']) ?></div>
                    </div>
                  </td>
                  <td data-label="Documento"><?= hfRelCliH($row['documento'] ?: '-') ?></td>
                  <td data-label="Telefone"><?= hfRelCliH($row['telefone'] ?: '-') ?></td>
                  <td data-label="Cidade/Bairro/UF"><span class="hf-location-cell"><?= hfRelCliH($local ?: '-') ?></span></td>
                  <td data-label="Status">
                    <span class="badge bg-<?= hfRelCliH(hfRelCliBadgeClass('status', $row['status_cliente'])) ?> hf-status-badge">
                      <?= hfRelCliH(hfRelCliLabel($row['status_cliente'])) ?>
                    </span>
                  </td>
                  <td data-label="Total OS" class="text-end fw-semibold"><?= (int)$row['total_os'] ?></td>
                  <td data-label="OS periodo" class="text-end"><?= (int)$row['os_periodo'] ?></td>
                  <td data-label="Ultima OS"><span class="hf-date-pill"><?= hfRelCliDateBr($row['ultima_os']) ?></span></td>
                  <td data-label="Status ultima">
                    <span class="badge bg-<?= hfRelCliH(hfRelCliBadgeClass('os', $row['status_ultima_os'])) ?> hf-status-badge">
                      <?= hfRelCliH(hfRelCliLabel($row['status_ultima_os'])) ?>
                    </span>
                  </td>
                  <td data-label="Faturado" class="text-end fw-semibold hf-money-cell"><?= hfRelCliMoneyBr($row['total_faturado']) ?></td>
                  <td data-label="Recebido" class="text-end"><?= hfRelCliMoneyBr($row['valor_recebido']) ?></td>
                  <td data-label="Saldo" class="text-end fw-semibold <?= $row['saldo_aberto'] > 0 ? 'hf-open-balance' : 'hf-paid-balance' ?>">
                    <?= hfRelCliMoneyBr($row['saldo_aberto']) ?>
                  </td>
                  <td data-label="Ticket" class="text-end"><?= hfRelCliMoneyBr($row['ticket_medio']) ?></td>
                  <td data-label="Perfil">
                    <span class="badge bg-<?= hfRelCliH(hfRelCliBadgeClass('perfil', $row['perfil_cliente'])) ?> hf-status-badge">
                      <?= hfRelCliH(hfRelCliLabel($row['perfil_cliente'])) ?>
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
.hf-rel-cli-page {
  min-height: calc(100vh - var(--topbar-h));
  overflow-x: hidden;
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .07), transparent 26rem),
    linear-gradient(180deg, #f8fafc 0%, #eef3f8 100%);
}

.hf-rel-cli-wrap {
  max-width: 1400px;
}

.hf-rel-cli-top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
}

.hf-rel-cli-title {
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

.hf-rel-cli-actions {
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
  font-size: 1.12rem;
  font-weight: 900;
  line-height: 1.15;
}

.hf-summary-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
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

.hf-summary-primary .hf-summary-icon {
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .11);
}

.hf-table-card {
  overflow: hidden;
}

.hf-table-card .table-responsive {
  max-height: 68vh;
  overflow-x: visible;
}

.hf-rel-cli-table {
  --bs-table-bg: transparent;
  width: 100%;
}

.hf-rel-cli-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  padding: .72rem .5rem;
  border-bottom: 1px solid rgba(148, 163, 184, .22);
  background: rgba(248, 250, 252, .98);
  color: #475569;
  font-size: .62rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .035em;
  white-space: nowrap;
}

.hf-rel-cli-table tbody td {
  padding: .68rem .5rem;
  border-color: rgba(226, 232, 240, .72);
  color: #334155;
  font-size: .8rem;
}

.hf-rel-cli-table tbody tr {
  transition: background-color .16s ease, box-shadow .16s ease;
}

.hf-rel-cli-table tbody tr:hover {
  background: rgba(var(--bs-primary-rgb), .035);
  box-shadow: inset 2px 0 0 rgba(var(--bs-primary-rgb), .38);
}

.hf-client-cell {
  display: flex;
  align-items: center;
  gap: .52rem;
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
  color: #0f172a;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hf-location-cell,
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
  .hf-rel-cli-table {
    table-layout: fixed;
  }

  .hf-rel-cli-table th:nth-child(1) { width: 13%; }
  .hf-rel-cli-table th:nth-child(2) { width: 8%; }
  .hf-rel-cli-table th:nth-child(3) { width: 8%; }
  .hf-rel-cli-table th:nth-child(4) { width: 12%; }
  .hf-rel-cli-table th:nth-child(5) { width: 7%; }
  .hf-rel-cli-table th:nth-child(6),
  .hf-rel-cli-table th:nth-child(7) { width: 6%; }
  .hf-rel-cli-table th:nth-child(8),
  .hf-rel-cli-table th:nth-child(9) { width: 8%; }
  .hf-rel-cli-table th:nth-child(10),
  .hf-rel-cli-table th:nth-child(11),
  .hf-rel-cli-table th:nth-child(12),
  .hf-rel-cli-table th:nth-child(13) { width: 7%; }
  .hf-rel-cli-table th:nth-child(14) { width: 6%; }

  .hf-rel-cli-table td,
  .hf-rel-cli-table th {
    overflow: hidden;
    text-overflow: ellipsis;
  }
}

@media (max-width: 1199.98px) {
  .hf-rel-cli-top {
    flex-direction: column;
  }

  .hf-rel-cli-actions {
    width: 100%;
    justify-content: flex-start;
  }
}

@media (min-width: 768px) and (max-width: 1279.98px) {
  .hf-rel-cli-table th:nth-child(2),
  .hf-rel-cli-table td:nth-child(2),
  .hf-rel-cli-table th:nth-child(3),
  .hf-rel-cli-table td:nth-child(3),
  .hf-rel-cli-table th:nth-child(9),
  .hf-rel-cli-table td:nth-child(9),
  .hf-rel-cli-table th:nth-child(11),
  .hf-rel-cli-table td:nth-child(11),
  .hf-rel-cli-table th:nth-child(13),
  .hf-rel-cli-table td:nth-child(13) {
    display: none;
  }

  .hf-rel-cli-table th:nth-child(1) { width: 18%; }
  .hf-rel-cli-table th:nth-child(4) { width: 17%; }
  .hf-rel-cli-table th:nth-child(5) { width: 8%; }
  .hf-rel-cli-table th:nth-child(6),
  .hf-rel-cli-table th:nth-child(7) { width: 8%; }
  .hf-rel-cli-table th:nth-child(8) { width: 10%; }
  .hf-rel-cli-table th:nth-child(10),
  .hf-rel-cli-table th:nth-child(12) { width: 10%; }
  .hf-rel-cli-table th:nth-child(14) { width: 11%; }
}

@media (max-width: 767.98px) {
  .hf-rel-cli-page {
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

  .hf-rel-cli-table,
  .hf-rel-cli-table thead,
  .hf-rel-cli-table tbody,
  .hf-rel-cli-table th,
  .hf-rel-cli-table td,
  .hf-rel-cli-table tr {
    display: block;
    width: 100%;
    box-sizing: border-box;
  }

  .hf-rel-cli-table thead {
    display: none;
  }

  .hf-rel-cli-table tbody tr.hf-rel-cli-row {
    border: 1px solid rgba(226, 232, 240, .9);
    border-radius: .95rem;
    padding: .85rem;
    margin: .75rem 0;
    background: rgba(248, 250, 252, .82);
    box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
  }

  .hf-rel-cli-table tbody td {
    display: grid;
    grid-template-columns: minmax(112px, 40%) 1fr;
    align-items: center;
    gap: .55rem;
    padding: .42rem 0;
    border: 0 !important;
    word-break: break-word;
  }

  .hf-rel-cli-table td::before {
    content: attr(data-label);
    color: #64748b;
    font-size: .74rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .hf-rel-cli-table td[data-label="Cliente"] {
    grid-template-columns: 1fr;
  }

  .hf-rel-cli-table td[data-label="Cliente"]::before {
    margin-bottom: .2rem;
  }

  .hf-client-name,
  .hf-location-cell {
    white-space: normal;
  }

  .hf-rel-cli-table .text-end {
    text-align: left !important;
  }
}

[data-bs-theme="dark"] .hf-rel-cli-page {
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
[data-bs-theme="dark"] .hf-client-name {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-rel-cli-table thead th {
  background: rgba(30, 41, 59, .98);
  color: #cbd5e1;
}

[data-bs-theme="dark"] .hf-rel-cli-table tbody td,
[data-bs-theme="dark"] .hf-date-pill {
  color: #cbd5e1;
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-rel-cli-table tbody tr.hf-rel-cli-row {
  background: rgba(15, 23, 42, .82);
  border-color: rgba(148, 163, 184, .18);
}
</style>

<?php require_once __DIR__ . '/_layout_end.php'; ?>
