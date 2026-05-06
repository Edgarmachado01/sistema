<?php
require_once __DIR__.'/_layout_start.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

$pdo = db();
$tid = tenantId();
if (!$tid) die('Tenant inválido.');

// Token CSRF para ações de exclusão via POST
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$q      = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$pg     = max(1, (int)($_GET['pg'] ?? 1));
$per    = 15;
$off    = ($pg-1)*$per;

$where  = "o.tenant_id=:tid AND o.deleted_at IS NULL";
$params = [':tid'=>$tid];

if ($q !== '') {
  $where .= " AND (CAST(o.numero AS CHAR) LIKE :q OR c.nome LIKE :q)";
  $params[':q'] = "%{$q}%";
}
if ($status!=='') {
  $where .= " AND o.status=:st";
  $params[':st'] = $status;
}

$sqlCount = "SELECT COUNT(*)
             FROM hf_os o
             JOIN hf_clientes c ON c.id=o.cliente_id AND c.deleted_at IS NULL
             WHERE $where";
$st = $pdo->prepare($sqlCount);
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total/$per));

$sql = "SELECT 
          o.id,o.numero,o.status,o.total,o.data_abertura,o.prioridade,o.tecnico,
          o.status_financeiro,o.valor_pago,
          c.nome AS cliente
        FROM hf_os o
        JOIN hf_clientes c ON c.id=o.cliente_id
        WHERE $where
        ORDER BY o.id DESC
        LIMIT :per OFFSET :off";
$st = $pdo->prepare($sql);
foreach($params as $k=>$v) $st->bindValue($k,$v);
$st->bindValue(':per',$per,PDO::PARAM_INT);
$st->bindValue(':off',$off,PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

$badge = ['aberta'=>'secondary','em_andamento'=>'warning','concluida'=>'success','cancelada'=>'dark'];
?>
<?php include __DIR__.'/_sidebar.php'; ?>
<main class="hf-content hf-os-page">
  <div class="container-fluid py-4 hf-os-wrap">

    <div class="hf-os-top mb-3">
      <div class="hf-os-title">
        <div class="hf-page-kicker">Operação</div>
        <h4 class="mb-0">Ordens de Serviço</h4>
        <div class="hf-page-subtitle">Acompanhe atendimentos, status, técnico e financeiro das OS.</div>
      </div>

      <div class="hf-os-actions">
        <form class="hf-os-filter" method="get">
          <div class="hf-filter-input">
            <i class="bi bi-search"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm" placeholder="Nº OS ou Cliente">
          </div>

          <select name="status" class="form-select form-select-sm">
            <option value="">Status (todos)</option>
            <?php foreach (['aberta','em_andamento','concluida','cancelada'] as $s): ?>
              <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
          </select>

          <button class="btn btn-primary btn-sm hf-btn-filter" title="Buscar">
            <i class="bi bi-search"></i>
          </button>
        </form>

        <a href="/os_form.php"
           class="btn btn-success btn-sm hf-btn-new-os d-none d-md-inline-flex">
          <i class="bi bi-plus-lg me-1"></i><span>Nova OS</span>
        </a>
      </div>
    </div>

    <!-- Lista -->
    <div class="hf-card p-0 hf-os-list">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="osTable">
          <thead class="table-light">
            <tr>
              <th style="width:90px">Nº</th>
              <th>Cliente</th>
              <th>Status</th>
              <th>Financeiro</th>
              <th>Prioridade</th>
              <th>Técnico</th>
              <th>Abertura</th>
              <th class="text-end">Total (R$)</th>
              <th class="text-end" style="width:140px">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php if(!$rows): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">Nenhuma OS encontrada.</td></tr>
            <?php else: foreach($rows as $r):
              $label = ucfirst(str_replace('_',' ',$r['status']));
              $num   = (int)($r['numero'] ?? $r['id']);
              $dtAb  = $r['data_abertura'] ? date('d/m/Y H:i', strtotime($r['data_abertura'])) : '-';
              $tot   = number_format((float)$r['total'],2,',','.');
              $fin  = $r['status_financeiro'] ?? 'pendente';
              $badgeFinMap = [
                'pendente' => 'secondary',
                'parcial'  => 'warning',
                'pago'     => 'success',
              ];
              $finLabel = ucfirst($fin);
              $finClass = $badgeFinMap[$fin] ?? 'secondary';
            ?>
              <tr class="os-row">
                <td data-label="Nº"><span class="hf-os-number">#<?= $num ?></span></td>
                <td data-label="Cliente">
                  <div class="hf-client-cell">
                    <div class="hf-client-avatar">
                      <?= strtoupper(substr((string)$r['cliente'], 0, 1)) ?>
                    </div>
                    <div class="hf-client-name"><?= htmlspecialchars($r['cliente']) ?></div>
                  </div>
                </td>
                <td data-label="Status">
                  <span class="badge bg-<?= $badge[$r['status'] ?? 'secondary'] ?> hf-status-badge"><?= $label ?></span>
                </td>
                <td data-label="Financeiro">
                  <span class="badge bg-<?= $finClass ?> hf-status-badge"><?= $finLabel ?></span>
                </td>
                <td data-label="Prioridade"><?= htmlspecialchars(ucfirst($r['prioridade'] ?? '')) ?></td>
                <td data-label="Técnico"><?= htmlspecialchars($r['tecnico'] ?? '') ?></td>
                <td data-label="Abertura"><span class="hf-date-pill"><?= $dtAb ?></span></td>
                <td data-label="Total" class="text-end fw-semibold hf-total-cell"><?= $tot ?></td>
                <td data-label="Ações" class="text-end">
                  <div class="d-inline-flex gap-1 hf-action-group">
                    <!-- Documento / Impressão -->
                    <a class="btn btn-sm btn-outline-secondary hf-action-btn"
                       href="/os_documento.php?id=<?= (int)$r['id'] ?>"
                       target="_blank"
                       title="Documento / Impressão">
                      <i class="bi bi-file-earmark-text"></i>
                    </a>

                    <!-- Editar -->
                    <a class="btn btn-sm btn-outline-primary hf-action-btn"
                       href="/os_form.php?id=<?= (int)$r['id'] ?>"
                       title="Editar">
                      <i class="bi bi-pencil-square"></i>
                    </a>

                    <!-- Excluir -->
                    <form method="post" action="/os_delete.php" class="d-inline m-0 p-0" onsubmit="return confirm('Confirma excluir (soft delete)?');">
                      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
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

    <?php if($pages>1): ?>
      <nav class="mt-3">
        <ul class="pagination pagination-sm mb-0 hf-pagination">
          <?php for($i=1;$i<=$pages;$i++):
            $url='?'.http_build_query(['q'=>$q,'status'=>$status,'pg'=>$i]); ?>
            <li class="page-item <?= $i===$pg?'active':'' ?>">
              <a class="page-link" href="<?= $url ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>

  </div>
</main>

<!-- FAB (mobile) -->
<a href="/os_form.php" class="btn btn-primary rounded-circle shadow fab-new-os d-md-none" title="Nova OS">
  <i class="bi bi-plus-lg"></i>
</a>

<style>
.hf-os-page {
  min-height: calc(100vh - var(--topbar-h));
  overflow-x: hidden;
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .10), transparent 28rem),
    linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
}

.hf-os-wrap {
  max-width: 1480px;
}

.hf-os-top {
  display: grid;
  grid-template-columns: minmax(220px, 1fr) auto;
  gap: 1rem;
  align-items: start;
}

.hf-os-title {
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

.hf-os-actions {
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

.hf-os-filter {
  display: flex;
  align-items: center;
  gap: .45rem;
}

.hf-os-filter .form-control,
.hf-os-filter .form-select {
  min-height: 34px;
  border-radius: .65rem;
  border-color: #dbe3ee;
  background-color: #f8fafc;
}

.hf-os-filter .form-control:focus,
.hf-os-filter .form-select:focus {
  border-color: rgba(var(--bs-primary-rgb), .55);
  box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
  background-color: #fff;
}

.hf-filter-input {
  position: relative;
}

.hf-filter-input > i {
  position: absolute;
  left: .7rem;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}

.hf-filter-input .form-control {
  width: 220px;
  padding-left: 2rem;
}

.hf-os-filter .form-select {
  width: 170px;
}
.hf-btn-filter,
.hf-btn-new-os {
  min-height: 34px;
  border-radius: .65rem;
  font-weight: 800;
  box-shadow: 0 8px 18px rgba(var(--bs-primary-rgb), .16);
}

.hf-btn-new-os {
  align-items: center;
  padding-left: .78rem;
  padding-right: .78rem;
  white-space: nowrap;
}

.fab-new-os {
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

.hf-os-list {
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

.hf-os-list table {
  --bs-table-bg: transparent;
}

.hf-os-list thead th {
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

.hf-os-list tbody td {
  padding: .9rem;
  border-color: rgba(226, 232, 240, .82);
  color: #334155;
}

.hf-os-list tbody tr {
  transition: background-color .14s ease, box-shadow .14s ease;
}

.hf-os-list tbody tr:hover {
  background: rgba(var(--bs-primary-rgb), .045);
  box-shadow: inset 3px 0 0 rgba(var(--bs-primary-rgb), .56);
}

.hf-os-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 2.6rem;
  padding: .24rem .55rem;
  border-radius: 999px;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .10);
  font-weight: 900;
}

.hf-client-cell {
  display: flex;
  align-items: center;
  gap: .65rem;
  min-width: 0;
  max-width: 100%;
}

.hf-client-avatar {
  width: 36px;
  height: 36px;
  flex: 0 0 36px;
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
  color: #0f172a;
  font-size: .9rem;
  font-weight: 600;
  line-height: 1.32;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hf-date-pill {
  color: #475569;
  font-weight: 650;
  white-space: nowrap;
}

.hf-total-cell {
  color: #0f172a;
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

.hf-status-badge.bg-success {
  color: #047857 !important;
  background: #d1fae5 !important;
}

.hf-status-badge.bg-secondary {
  color: #475569 !important;
  background: #e2e8f0 !important;
}

.hf-status-badge.bg-dark {
  color: #475569 !important;
  background: #cbd5e1 !important;
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

.hf-action-btn.btn-outline-secondary {
  color: #64748b;
  background: #f8fafc;
  border-color: #dbe3ee;
}

.hf-action-btn.btn-outline-secondary:hover {
  color: #fff;
  background: #64748b;
  border-color: #64748b;
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

.hf-pagination .page-link {
  border-radius: .55rem;
  margin-right: .25rem;
  border-color: rgba(148, 163, 184, .35);
  font-weight: 750;
}
@media (max-width: 767.98px) {
  .hf-client-name {
    display: -webkit-box;
    white-space: normal;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    font-size: .92rem;
    line-height: 1.35;
  }
}

@media (min-width: 768px) {
  .hf-os-list table {
    table-layout: fixed;
  }
}

@media (max-width: 991.98px) {
  .hf-os-top {
    grid-template-columns: 1fr;
  }

  .hf-os-actions {
    justify-content: stretch;
  }

  .hf-os-filter {
    width: 100%;
    flex-wrap: wrap;
  }
}

@media (max-width: 767.98px) {
  .hf-os-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .hf-btn-new-os {
    display: none !important;
  }

  .hf-filter-input,
  .hf-filter-input .form-control,
  .hf-os-filter .form-select,
  .hf-btn-filter {
    width: 100%;
  }

  .hf-os-list {
    padding: .85rem;
  }

  .hf-os-list .table-responsive {
    overflow-x: visible;
  }

  .hf-os-list table,
  .hf-os-list thead,
  .hf-os-list tbody,
  .hf-os-list th,
  .hf-os-list td,
  .hf-os-list tr {
    display: block;
    width: 100%;
    box-sizing: border-box;
  }

  .hf-os-list thead {
    display: none;
  }

  .hf-os-list tbody tr.os-row {
    border: 1px solid rgba(226, 232, 240, .9);
    border-radius: .95rem;
    padding: .85rem;
    margin: .75rem 0;
    background: rgba(248, 250, 252, .82);
    box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
  }

  .hf-os-list tbody tr.os-row:hover {
    box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
  }

  .hf-os-list td {
    display: grid;
    grid-template-columns: minmax(94px, 38%) 1fr;
    align-items: center;
    gap: .55rem;
    padding: .4rem 0;
    border: 0 !important;
    word-break: break-word;
  }

  .hf-os-list td::before {
    content: attr(data-label);
    color: #64748b;
    font-size: .76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .hf-os-list td[data-label="Cliente"] {
    grid-template-columns: 1fr;
  }

  .hf-os-list td[data-label="Cliente"]::before {
    margin-bottom: .2rem;
  }

  .hf-client-cell {
    align-items: flex-start;
  }

  .hf-os-list td[data-label="Ações"] {
    display: flex;
    justify-content: flex-end;
    gap: .5rem;
    margin-top: .25rem;
    padding-top: .75rem;
    border-top: 1px solid rgba(226, 232, 240, .9) !important;
  }

  .hf-os-list td[data-label="Ações"]::before {
    display: none;
  }

  .hf-total-cell {
    color: var(--bs-primary);
    font-size: 1.05rem;
    font-weight: 900 !important;
  }
}

[data-bs-theme="dark"] .hf-os-page {
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .hf-os-filter,
[data-bs-theme="dark"] .hf-os-list {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-os-filter .form-control,
[data-bs-theme="dark"] .hf-os-filter .form-select {
  background-color: rgba(15, 23, 42, .9);
  border-color: rgba(148, 163, 184, .24);
}

[data-bs-theme="dark"] .hf-os-list thead th {
  background: rgba(30, 41, 59, .95);
  color: #cbd5e1;
}

[data-bs-theme="dark"] .hf-os-list tbody td,
[data-bs-theme="dark"] .hf-date-pill {
  color: #cbd5e1;
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-client-name,
[data-bs-theme="dark"] .hf-total-cell {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-os-list tbody tr.os-row {
  background: rgba(15, 23, 42, .82);
  border-color: rgba(148, 163, 184, .18);
}
</style>

<?php require_once __DIR__.'/_layout_end.php'; ?>
