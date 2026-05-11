<?php
// lancamentos.php — Lançamentos (entradas / saídas avulsas e recorrentes)


$PAGE_TITLE = 'Lançamentos';

require_once __DIR__.'/_layout_start.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

$pdo = db();
$tid = tenantId();
if (!$tid) {
  error_log('lancamentos.php tenant invalido user=' . ($_SESSION['USER_ID'] ?? ''));
  header('Location: /login.php');
  exit;
}

// Token CSRF para ações POST
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Exclusão via POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir_lancamento') {
  $sessionToken = $_SESSION['csrf_token'] ?? '';
  $postToken    = $_POST['csrf_token'] ?? '';

  if ($sessionToken !== '' && $postToken !== '' && hash_equals($sessionToken, $postToken)) {
    $delId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($delId) {
      $stmtDel = $pdo->prepare("
        DELETE FROM lancamentos
        WHERE id = :id AND tenant_id = :tid
      ");
      $stmtDel->execute([
        ':id'  => $delId,
        ':tid' => $tid
      ]);
    }
  }
}

// Filtro tipo_conta
$filtro_tipo_conta = isset($_GET['tipo_conta']) ? $_GET['tipo_conta'] : 'todas';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo_conta'])) {
  $filtro_tipo_conta = $_POST['tipo_conta'];
}

$whereExtra = '';
$params = [':tid' => $tid];

if ($filtro_tipo_conta === 'avulsa' || $filtro_tipo_conta === 'recorrente') {
  $whereExtra = " AND tipo_conta = :tipo_conta";
  $params[':tipo_conta'] = $filtro_tipo_conta;
}

// Busca lançamentos
$sql = "
  SELECT
    id,
    tipo_mov,
    tipo_conta,
    descricao,
    valor,
    data_lancamento,
    data_vencimento,
    status,
    data_pagamento,
    valor_pago,
    forma_pagamento
  FROM lancamentos
  WHERE tenant_id = :tid
  $whereExtra
  ORDER BY data_vencimento ASC, id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function hfSituacaoLancamento(array $l)
{
  $status   = $l['status'];
  $dataVenc = $l['data_vencimento'];

  if ($status === 'pago') {
    return ['Pago', 'success'];
  }
  if ($status === 'cancelado') {
    return ['Cancelado', 'secondary'];
  }

  $hoje = new DateTimeImmutable(date('Y-m-d'));
  if (!empty($dataVenc)) {
    $venc = new DateTimeImmutable($dataVenc);
    if ($venc < $hoje) {
      return ['Atrasado', 'danger'];
    }
  }

  return ['Em aberto', 'warning'];
}

function hfDateBr($date)
{
  if (empty($date)) return '-';
  return date('d/m/Y', strtotime($date));
}
?>

<?php include __DIR__.'/_sidebar.php'; ?>

<main class="hf-content hf-lanc-page">
  <div class="container-fluid py-4 hf-lanc-wrap">

    <div class="hf-lanc-top mb-3">
      <div class="hf-lanc-title">
        <div class="hf-page-kicker">Fluxo financeiro</div>
        <h4 class="mb-0">Lançamentos</h4>
        <div class="hf-page-subtitle">Consulte entradas, saídas avulsas e recorrentes.</div>
      </div>

      <div id="lancFilters" class="filters-bar">
        <form class="filters-form" method="get">
          <input type="hidden" name="m" value="lanc">

          <select name="tipo_conta" class="form-select form-select-sm">
            <option value="todas" <?= $filtro_tipo_conta==='todas'?'selected':'' ?>>Tipo (todos)</option>
            <option value="avulsa" <?= $filtro_tipo_conta==='avulsa'?'selected':'' ?>>Avulsas</option>
            <option value="recorrente" <?= $filtro_tipo_conta==='recorrente'?'selected':'' ?>>Recorrentes</option>
          </select>

          <button class="btn btn-primary btn-sm hf-btn-filter" type="submit" title="Filtrar">
            <i class="bi bi-search"></i>
          </button>
        </form>

        <a href="/lancamento_form.php?m=lanc"
           class="btn btn-success btn-sm btn-new d-none d-md-inline-flex">
          <i class="bi bi-plus-lg me-1"></i><span>Novo</span>
        </a>
      </div>
    </div>

    <div class="hf-card p-0 hf-lanc-list">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="lancTable">
          <thead class="table-light">
            <tr>
              <th style="width:130px">Tipo</th>
              <th>Descrição</th>
              <th class="text-end">Valor (R$)</th>
              <th>Vencimento</th>
              <th>Lançamento</th>
              <th>Status</th>
              <th class="text-end" style="width:120px">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($lancamentos)): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">Nenhum lançamento encontrado.</td>
              </tr>
            <?php else: foreach ($lancamentos as $l):
              list($situacao, $badge) = hfSituacaoLancamento($l);
              $isEntrada = ($l['tipo_mov'] === 'entrada');
              $urlEdit = '/lancamento_form.php?m=lanc&id='.(int)$l['id'];
            ?>
              <tr class="hf-lanc-row <?= $isEntrada ? 'is-entrada' : 'is-saida' ?>">
                <td data-label="Tipo">
                  <div class="hf-lanc-type-wrap">
                    <span class="hf-type-pill <?= $isEntrada ? 'is-entrada' : 'is-saida' ?>">
                      <i class="bi <?= $isEntrada ? 'bi-arrow-down-left' : 'bi-arrow-up-right' ?>"></i>
                      <?= ucfirst($l['tipo_mov']) ?>
                    </span>

                    <?php if ($l['tipo_conta'] === 'recorrente'): ?>
                      <span class="hf-recurring-pill" title="Recorrente">
                        <i class="bi bi-arrow-repeat"></i>
                      </span>
                    <?php endif; ?>
                  </div>
                </td>

                <td data-label="Descrição">
                  <a href="<?= $urlEdit ?>" class="text-decoration-none text-body hf-lanc-desc-link">
                    <div class="hf-lanc-desc"><?= htmlspecialchars($l['descricao']) ?></div>
                    <div class="hf-lanc-meta">
                      <span><?= ucfirst($l['tipo_conta']) ?></span>
                      <?php if (!empty($l['forma_pagamento'])): ?>
                        <span>Forma: <?= htmlspecialchars($l['forma_pagamento']) ?></span>
                      <?php endif; ?>
                      <?php if ($l['status'] === 'pago' && !empty($l['data_pagamento'])): ?>
                        <span>Pago: <?= hfDateBr($l['data_pagamento']) ?></span>
                      <?php endif; ?>
                    </div>
                  </a>
                </td>

                <td data-label="Valor (R$)" class="text-end">
                  <span class="hf-lanc-value <?= $isEntrada ? 'is-entrada' : 'is-saida' ?>">
                    <?= number_format((float)$l['valor'], 2, ',', '.') ?>
                  </span>
                </td>

                <td data-label="Vencimento">
                  <span class="hf-date-pill"><?= hfDateBr($l['data_vencimento']) ?></span>
                </td>

                <td data-label="Lançamento">
                  <span class="hf-date-pill"><?= hfDateBr($l['data_lancamento']) ?></span>
                </td>

                <td data-label="Status">
                  <span class="badge bg-<?= $badge ?> hf-status-badge"><?= $situacao ?></span>
                </td>

                <td data-label="Ações" class="text-end">
                  <div class="d-inline-flex gap-1 hf-action-group">
                    <a class="btn btn-sm btn-outline-primary hf-action-btn"
                       href="<?= $urlEdit ?>"
                       title="Editar">
                      <i class="bi bi-pencil-square"></i>
                    </a>

                    <form method="post" action="/lancamentos.php?m=lanc" class="d-inline m-0 p-0" onsubmit="return confirm('Excluir este lançamento?');">
                      <input type="hidden" name="acao" value="excluir_lancamento">
                      <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                      <input type="hidden" name="tipo_conta" value="<?= htmlspecialchars($filtro_tipo_conta, ENT_QUOTES, 'UTF-8') ?>">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger hf-action-btn hf-delete-btn" title="Excluir">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<a href="/lancamento_form.php?m=lanc" class="btn btn-primary rounded-circle shadow fab-new d-md-none" title="Novo lançamento">
  <i class="bi bi-plus-lg"></i>
</a>

<style>
.hf-lanc-page {
  min-height: calc(100vh - var(--topbar-h));
  overflow-x: hidden;
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .10), transparent 28rem),
    linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
}

.hf-lanc-wrap {
  max-width: 1480px;
}

.hf-lanc-top {
  display: grid;
  grid-template-columns: minmax(220px, 1fr) auto;
  gap: 1rem;
  align-items: start;
}

.hf-lanc-title {
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

.filters-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: .55rem;
  padding: .55rem;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .92);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
  backdrop-filter: blur(8px);
}

.filters-form {
  display: flex;
  align-items: center;
  gap: .45rem;
}

.filters-form .form-select {
  min-width: 180px;
  min-height: 34px;
  border-radius: .65rem;
  border-color: #dbe3ee;
  background-color: #f8fafc;
}

.filters-form .form-select:focus {
  border-color: rgba(var(--bs-primary-rgb), .55);
  box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
  background-color: #fff;
}

.hf-btn-filter,
.btn-new {
  min-height: 34px;
  border-radius: .65rem;
  font-weight: 800;
  box-shadow: 0 8px 18px rgba(var(--bs-primary-rgb), .16);
}

.btn-new {
  align-items: center;
  padding-left: .78rem;
  padding-right: .78rem;
  white-space: nowrap;
}

.fab-new {
  position: fixed;
  right: 16px;
  bottom: 16px;
  width: 56px;
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
  z-index: 1050;
}

.hf-lanc-list {
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

.hf-lanc-list table {
  --bs-table-bg: transparent;
}

.hf-lanc-list thead th {
  padding: .95rem .9rem;
  border-bottom: 1px solid rgba(148, 163, 184, .28);
  background: #f1f5f9;
  color: #475569;
  font-size: .74rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .055em;
  white-space: nowrap;
}

.hf-lanc-list tbody td {
  padding: .9rem;
  border-color: rgba(226, 232, 240, .82);
  color: #334155;
}

.hf-lanc-list tbody tr {
  transition: background-color .14s ease, box-shadow .14s ease;
}

.hf-lanc-list tbody tr:hover {
  background: rgba(var(--bs-primary-rgb), .045);
}

.hf-lanc-row.is-entrada:hover {
  box-shadow: inset 3px 0 0 #16a34a;
}

.hf-lanc-row.is-saida:hover {
  box-shadow: inset 3px 0 0 #dc2626;
}

.hf-lanc-type-wrap {
  display: flex;
  align-items: center;
  gap: .35rem;
  flex-wrap: wrap;
}

.hf-type-pill,
.hf-recurring-pill {
  display: inline-flex;
  align-items: center;
  gap: .32rem;
  min-height: 28px;
  border-radius: 999px;
  padding: .28rem .58rem;
  font-size: .78rem;
  font-weight: 800;
  white-space: nowrap;
}

.hf-type-pill.is-entrada {
  color: #047857;
  background: #d1fae5;
}

.hf-type-pill.is-saida {
  color: #b91c1c;
  background: #fee2e2;
}

.hf-recurring-pill {
  color: #075985;
  background: #e0f2fe;
  padding: .28rem .5rem;
}

.hf-lanc-desc {
  color: #0f172a;
  font-size: .92rem;
  font-weight: 700;
  line-height: 1.3;
}

.hf-lanc-desc-link:hover .hf-lanc-desc {
  color: var(--bs-primary);
}

.hf-lanc-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .45rem;
  margin-top: .22rem;
  color: #64748b;
  font-size: .8rem;
}

.hf-lanc-meta span {
  display: inline-flex;
  align-items: center;
}

.hf-lanc-meta span + span::before {
  content: "";
  width: 4px;
  height: 4px;
  margin-right: .45rem;
  border-radius: 999px;
  background: #cbd5e1;
}

.hf-lanc-value {
  display: inline-flex;
  justify-content: flex-end;
  font-weight: 900;
  white-space: nowrap;
}

.hf-lanc-value.is-entrada {
  color: #047857;
}

.hf-lanc-value.is-saida {
  color: #b91c1c;
}

.hf-date-pill {
  color: #475569;
  font-weight: 650;
  white-space: nowrap;
}

.hf-status-badge {
  border-radius: 999px;
  padding: .42rem .62rem;
  font-weight: 800;
  letter-spacing: .01em;
}

.hf-status-badge.bg-warning {
  color: #8a4b00 !important;
  background: #fff3cd !important;
}

.hf-status-badge.bg-danger {
  color: #b91c1c !important;
  background: #fee2e2 !important;
}

.hf-status-badge.bg-success {
  color: #047857 !important;
  background: #d1fae5 !important;
}

.hf-status-badge.bg-secondary {
  color: #475569 !important;
  background: #e2e8f0 !important;
}

.hf-action-group {
  white-space: nowrap;
}

.hf-action-btn {
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: .65rem;
  background: rgba(var(--bs-primary-rgb), .04);
  border-color: rgba(var(--bs-primary-rgb), .28);
  font-weight: 800;
}

.hf-action-btn:hover {
  color: #fff;
  background: var(--bs-primary);
  border-color: var(--bs-primary);
}

.hf-delete-btn {
  color: #dc2626;
  background: #fff5f5;
  border-color: #fecaca;
}

.hf-delete-btn:hover {
  color: #fff;
  background: #dc2626;
  border-color: #dc2626;
}

@media (min-width: 768px) {
  .hf-lanc-list table {
    table-layout: fixed;
  }
}

@media (max-width: 991.98px) {
  .hf-lanc-top {
    grid-template-columns: 1fr;
  }

  .filters-bar {
    justify-content: stretch;
  }

  .filters-form,
  .filters-form .form-select {
    width: 100%;
  }
}

@media (max-width: 767.98px) {
  .hf-lanc-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .btn-new {
    display: none !important;
  }

  .hf-btn-filter {
    width: 100%;
  }

  .hf-lanc-list {
    padding: .85rem;
  }

  .hf-lanc-list .table-responsive {
    overflow-x: visible;
  }

  .hf-lanc-list table,
  .hf-lanc-list thead,
  .hf-lanc-list tbody,
  .hf-lanc-list th,
  .hf-lanc-list td,
  .hf-lanc-list tr {
    display: block;
    width: 100%;
    box-sizing: border-box;
  }

  .hf-lanc-list thead {
    display: none;
  }

  .hf-lanc-list tbody tr.hf-lanc-row {
    border: 1px solid rgba(226, 232, 240, .9);
    border-radius: .95rem;
    padding: .85rem;
    margin: .75rem 0;
    background: rgba(248, 250, 252, .82);
    box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
  }

  .hf-lanc-list tbody tr.hf-lanc-row:hover {
    box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
  }

  .hf-lanc-list td {
    display: grid;
    grid-template-columns: minmax(104px, 40%) 1fr;
    align-items: center;
    gap: .55rem;
    padding: .4rem 0;
    border: 0 !important;
    word-break: break-word;
  }

  .hf-lanc-list td::before {
    content: attr(data-label);
    color: #64748b;
    font-size: .76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .hf-lanc-list td[data-label="Descrição"] {
    grid-template-columns: 1fr;
  }

  .hf-lanc-list td[data-label="Descrição"]::before {
    margin-bottom: .2rem;
  }

  .hf-lanc-desc {
    display: -webkit-box;
    white-space: normal;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    font-size: .95rem;
    line-height: 1.35;
  }

  .hf-lanc-list td[data-label="Valor (R$)"] {
    justify-content: space-between;
  }

  .hf-lanc-list td[data-label="Valor (R$)"] .hf-lanc-value {
    font-size: 1.08rem;
  }

  .hf-lanc-list td[data-label="Ações"] {
    display: flex;
    justify-content: flex-end;
    gap: .5rem;
    margin-top: .25rem;
    padding-top: .75rem;
    border-top: 1px solid rgba(226, 232, 240, .9) !important;
  }

  .hf-lanc-list td[data-label="Ações"]::before {
    display: none;
  }

  .hf-lanc-list td[data-label="Ações"] .btn {
    padding: .45rem .6rem;
  }
}

[data-bs-theme="dark"] .hf-lanc-page {
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .filters-bar,
[data-bs-theme="dark"] .hf-lanc-list {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .filters-form .form-select {
  background-color: rgba(15, 23, 42, .9);
  border-color: rgba(148, 163, 184, .24);
}

[data-bs-theme="dark"] .hf-lanc-list thead th {
  background: rgba(30, 41, 59, .95);
  color: #cbd5e1;
}

[data-bs-theme="dark"] .hf-lanc-list tbody td,
[data-bs-theme="dark"] .hf-date-pill {
  color: #cbd5e1;
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-lanc-desc {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-lanc-list tbody tr.hf-lanc-row {
  background: rgba(15, 23, 42, .82);
  border-color: rgba(148, 163, 184, .18);
}
</style>

<?php require_once __DIR__.'/_layout_end.php'; ?>
