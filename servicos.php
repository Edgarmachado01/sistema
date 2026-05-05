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

$q   = isset($_GET['q']) ? trim($_GET['q']) : '';
$pg  = max(1, (int)($_GET['pg'] ?? 1));
$per = 15;
$off = ($pg - 1) * $per;

$where  = "tenant_id=:tid AND deleted_at IS NULL";
$params = [':tid'=>$tid];

if ($q !== '') {
  $where .= " AND (nome LIKE :q OR categoria LIKE :q)";
  $params[':q'] = "%{$q}%";
}

$st = $pdo->prepare("SELECT COUNT(*) FROM hf_servicos WHERE $where");
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total/$per));

$sql = "SELECT id,nome,categoria,preco,custo_ref,sla_dias,garantia_dias,comissao_pct,status
        FROM hf_servicos
        WHERE $where
        ORDER BY nome ASC
        LIMIT :per OFFSET :off";
$st = $pdo->prepare($sql);
foreach($params as $k=>$v) $st->bindValue($k,$v);
$st->bindValue(':per',$per,PDO::PARAM_INT);
$st->bindValue(':off',$off,PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include __DIR__.'/_sidebar.php'; ?>
<main class="hf-content hf-servicos-page">

  <div class="container-fluid py-4 hf-servicos-wrap">

    <!-- Título + filtros -->
    <div class="hf-servicos-top mb-3">
      <div class="hf-servicos-title">
        <div class="hf-page-kicker">Cadastro</div>
        <h4 class="mb-0">Serviços</h4>
        <div class="hf-page-subtitle">Consulte e gerencie serviços, preços, SLA, garantia e comissão.</div>
      </div>

      <div id="srvFilters" class="filters-bar">
        <form class="filters-form" method="get">
          <div class="hf-search-field">
            <i class="bi bi-search"></i>
            <input type="text"
                   name="q"
                   value="<?= htmlspecialchars($q) ?>"
                   class="form-control form-control-sm"
                   placeholder="Buscar nome/categoria...">
          </div>

          <button class="btn btn-primary btn-sm hf-btn-filter" type="submit" title="Pesquisar">
            <i class="bi bi-search"></i>
          </button>
        </form>

        <!-- Novo (desktop) -->
        <a href="/servico_form.php"
           class="btn btn-success btn-sm btn-new d-none d-md-inline-flex">
          <i class="bi bi-plus-lg me-1"></i><span>Novo</span>
        </a>
      </div>
    </div>

    <!-- Lista -->
    <div class="hf-card p-0 srv-list">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="srvTable">
          <thead class="table-light">
            <tr>
              <th>Nome</th>
              <th>Categoria</th>
              <th class="text-end">Preço (R$)</th>
              <th class="text-end">Custo (R$)</th>
              <th>SLA (dias)</th>
              <th>Garantia</th>
              <th>Comissão</th>
              <th>Status</th>
              <th class="text-end" style="width:120px">Ações</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$rows): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">Nenhum serviço encontrado.</td></tr>
          <?php else: foreach($rows as $r): ?>
            <tr class="srv-row">
              <td data-label="Nome">
                <div class="hf-service-cell">
                  <div class="hf-service-icon">
                    <i class="bi bi-tools"></i>
                  </div>
                  <div class="hf-service-name"><?= htmlspecialchars($r['nome']) ?></div>
                </div>
              </td>
              <td data-label="Categoria">
                <span class="hf-muted-text"><?= htmlspecialchars($r['categoria'] ?? '') ?></span>
              </td>
              <td data-label="Preço (R$)" class="text-end">
                <span class="hf-price-value"><?= number_format((float)$r['preco'],2,',','.') ?></span>
              </td>
              <td data-label="Custo (R$)" class="text-end">
                <span class="hf-cost-value"><?= number_format((float)$r['custo_ref'],2,',','.') ?></span>
              </td>
              <td data-label="SLA (dias)">
                <span class="hf-info-pill"><?= (int)($r['sla_dias'] ?? 0) ?> dias</span>
              </td>
              <td data-label="Garantia">
                <span class="hf-info-pill"><?= (int)($r['garantia_dias'] ?? 0) ?> dias</span>
              </td>
              <td data-label="Comissão">
                <?= $r['comissao_pct']!==null ? '<span class="hf-info-pill">'.number_format((float)$r['comissao_pct'],2,',','.') . '%</span>' : '' ?>
              </td>
              <td data-label="Status">
                <?= ((int)$r['status']===1)
                  ? '<span class="badge bg-success hf-status-badge">Ativo</span>'
                  : '<span class="badge bg-secondary hf-status-badge">Inativo</span>' ?>
              </td>
              <td data-label="Ações" class="text-end">
                <div class="d-inline-flex gap-1 hf-action-group">
                  <a class="btn btn-sm btn-outline-primary hf-action-btn" href="/servico_form.php?id=<?= (int)$r['id'] ?>" title="Editar">
                    <i class="bi bi-pencil-square"></i>
                  </a>
                  <form method="post" action="/servico_delete.php" class="d-inline m-0 p-0" onsubmit="return confirm('Confirma excluir (soft delete)?');">
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
            $url='?'.http_build_query(['q'=>$q,'pg'=>$i]); ?>
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
<a href="/servico_form.php" class="btn btn-primary rounded-circle shadow fab-new d-md-none" title="Novo serviço">
  <i class="bi bi-plus-lg"></i>
</a>

<?php require_once __DIR__.'/_layout_end.php'; ?>

<style>
.hf-servicos-page {
  min-height: calc(100vh - var(--topbar-h));
  overflow-x: hidden;
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .10), transparent 28rem),
    linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
}

.hf-servicos-wrap {
  max-width: 1480px;
}

.hf-servicos-top {
  display: grid;
  grid-template-columns: minmax(220px, 1fr) auto;
  gap: 1rem;
  align-items: start;
}

.hf-servicos-title {
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

/* Barra de filtros */
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

.hf-search-field {
  position: relative;
}

.hf-search-field > i {
  position: absolute;
  left: .7rem;
  top: 50%;
  transform: translateY(-50%);
  color: #94a3b8;
  pointer-events: none;
}

.hf-search-field .form-control {
  width: 280px;
  min-height: 34px;
  padding-left: 2rem;
  border-radius: .65rem;
  border-color: #dbe3ee;
  background-color: #f8fafc;
}

.hf-search-field .form-control:focus {
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

/* FAB mobile */
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

/* Lista */
.srv-list {
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

.srv-list table {
  --bs-table-bg: transparent;
}

.srv-list thead th {
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

.srv-list tbody td {
  padding: .9rem;
  border-color: rgba(226, 232, 240, .82);
  color: #334155;
}

.srv-list tbody tr {
  transition: background-color .14s ease, box-shadow .14s ease;
}

.srv-list tbody tr:hover {
  background: rgba(var(--bs-primary-rgb), .045);
  box-shadow: inset 3px 0 0 rgba(var(--bs-primary-rgb), .56);
}

.hf-service-cell {
  display: flex;
  align-items: center;
  gap: .65rem;
  min-width: 0;
  max-width: 100%;
}

.hf-service-icon {
  width: 36px;
  height: 36px;
  flex: 0 0 36px;
  display: grid;
  place-items: center;
  border-radius: .85rem;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .11);
  font-size: 1rem;
}

.hf-service-name {
  min-width: 0;
  max-width: 100%;
  color: #0f172a;
  font-size: .92rem;
  font-weight: 700;
  line-height: 1.3;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.hf-muted-text {
  color: #64748b;
  word-break: break-word;
}

.hf-price-value {
  color: #047857;
  font-weight: 900;
  white-space: nowrap;
}

.hf-cost-value {
  color: #475569;
  font-weight: 750;
  white-space: nowrap;
}

.hf-info-pill {
  display: inline-flex;
  align-items: center;
  min-height: 26px;
  padding: .18rem .55rem;
  border-radius: 999px;
  color: #475569;
  background: #e2e8f0;
  font-size: .78rem;
  font-weight: 800;
  white-space: nowrap;
}

.hf-status-badge {
  border-radius: 999px;
  padding: .42rem .62rem;
  font-weight: 800;
  letter-spacing: .01em;
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

.hf-pagination .page-link {
  border-radius: .55rem;
  margin-right: .25rem;
  border-color: rgba(148, 163, 184, .35);
  font-weight: 750;
}

/* Desktop mantém tabela */
@media (min-width: 768px) {
  .srv-list table {
    table-layout: fixed;
  }
}

@media (max-width: 991.98px) {
  .hf-servicos-top {
    grid-template-columns: 1fr;
  }

  .filters-bar {
    justify-content: stretch;
  }

  .filters-form,
  .hf-search-field,
  .hf-search-field .form-control {
    width: 100%;
  }
}

/* Mobile: cards e sem overflow lateral */
@media (max-width: 767.98px) {
  .hf-servicos-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .btn-new {
    display: none !important;
  }

  .hf-btn-filter {
    width: 100%;
  }

  .srv-list {
    padding: .85rem;
  }

  .srv-list .table-responsive {
    overflow-x: visible;
  }

  .srv-list table,
  .srv-list thead,
  .srv-list tbody,
  .srv-list th,
  .srv-list td,
  .srv-list tr {
    display: block;
    width: 100%;
    box-sizing: border-box;
  }

  .srv-list thead {
    display: none;
  }

  .srv-list tbody tr.srv-row {
    border: 1px solid rgba(226, 232, 240, .9);
    border-radius: .95rem;
    padding: .85rem;
    margin: .75rem 0;
    background: rgba(248, 250, 252, .82);
    box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
  }

  .srv-list tbody tr.srv-row:hover {
    box-shadow: 0 12px 28px rgba(15, 23, 42, .08);
  }

  .srv-list td {
    display: grid;
    grid-template-columns: minmax(100px, 42%) 1fr;
    align-items: center;
    gap: .55rem;
    padding: .4rem 0;
    border: 0 !important;
    word-break: break-word;
  }

  .srv-list td::before {
    content: attr(data-label);
    color: #64748b;
    font-size: .76rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
  }

  .srv-list td[data-label="Nome"] {
    grid-template-columns: 1fr;
  }

  .srv-list td[data-label="Nome"]::before {
    margin-bottom: .2rem;
  }

  .hf-service-cell {
    align-items: flex-start;
  }

  .hf-service-name {
    display: -webkit-box;
    white-space: normal;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    font-size: .95rem;
    line-height: 1.35;
  }

  .srv-list td[data-label="Preço (R$)"],
  .srv-list td[data-label="Custo (R$)"] {
    justify-content: space-between;
  }

  .srv-list td[data-label="Ações"] {
    display: flex;
    justify-content: flex-end;
    gap: .5rem;
    margin-top: .25rem;
    padding-top: .75rem;
    border-top: 1px solid rgba(226, 232, 240, .9) !important;
  }

  .srv-list td[data-label="Ações"]::before {
    display: none;
  }

  .srv-list td[data-label="Ações"] .btn {
    padding: .45rem .6rem;
  }
}

[data-bs-theme="dark"] .hf-servicos-page {
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .filters-bar,
[data-bs-theme="dark"] .srv-list {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-search-field .form-control {
  background-color: rgba(15, 23, 42, .9);
  border-color: rgba(148, 163, 184, .24);
}

[data-bs-theme="dark"] .srv-list thead th {
  background: rgba(30, 41, 59, .95);
  color: #cbd5e1;
}

[data-bs-theme="dark"] .srv-list tbody td {
  color: #cbd5e1;
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-service-name {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .srv-list tbody tr.srv-row {
  background: rgba(15, 23, 42, .82);
  border-color: rgba(148, 163, 184, .18);
}
</style>
