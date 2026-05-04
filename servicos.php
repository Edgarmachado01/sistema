<?php
require_once __DIR__.'/_layout_start.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

$pdo = db();
$tid = tenantId();
if (!$tid) die('Tenant inválido.');

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
<main class="hf-content" style="overflow-x:hidden;">

  <!-- Título + filtros -->
  <div class="d-flex flex-wrap align-items-center mb-3 gap-2">
    <h4 class="mb-0 me-auto">Serviços</h4>

    <div id="srvFilters" class="filters-bar d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0">
      <form class="filters-form d-flex align-items-center gap-2 flex-grow-1" method="get">
        <div class="input-group input-group-sm flex-grow-1">
          <input type="text"
                 name="q"
                 value="<?= htmlspecialchars($q) ?>"
                 class="form-control"
                 placeholder="Buscar nome/categoria...">
          <button class="btn btn-primary" type="submit" title="Pesquisar">
            <i class="bi bi-search"></i>
          </button>
        </div>
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
            <td data-label="Nome" class="fw-semibold" style="word-break:break-word;"><?= htmlspecialchars($r['nome']) ?></td>
            <td data-label="Categoria"><?= htmlspecialchars($r['categoria'] ?? '') ?></td>
            <td data-label="Preço (R$)" class="text-end"><?= number_format((float)$r['preco'],2,',','.') ?></td>
            <td data-label="Custo (R$)" class="text-end"><?= number_format((float)$r['custo_ref'],2,',','.') ?></td>
            <td data-label="SLA (dias)"><?= (int)($r['sla_dias'] ?? 0) ?></td>
            <td data-label="Garantia"><?= (int)($r['garantia_dias'] ?? 0) ?></td>
            <td data-label="Comissão"><?= $r['comissao_pct']!==null ? number_format((float)$r['comissao_pct'],2,',','.') . '%' : '' ?></td>
            <td data-label="Status">
              <?= ((int)$r['status']===1)
                ? '<span class="badge bg-success">Ativo</span>'
                : '<span class="badge bg-secondary">Inativo</span>' ?>
            </td>
            <td data-label="Ações" class="text-end">
              <div class="d-inline-flex gap-1">
                <a class="btn btn-sm btn-outline-primary" href="/servico_form.php?id=<?= (int)$r['id'] ?>" title="Editar"><i class="bi bi-pencil-square"></i></a>
                <a class="btn btn-sm btn-outline-danger" href="/servico_delete.php?id=<?= (int)$r['id'] ?>" onclick="return confirm('Confirma excluir (soft delete)?');" title="Excluir"><i class="bi bi-trash"></i></a>
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
      <ul class="pagination pagination-sm mb-0">
        <?php for($i=1;$i<=$pages;$i++):
          $url='?'.http_build_query(['q'=>$q,'pg'=>$i]); ?>
          <li class="page-item <?= $i===$pg?'active':'' ?>">
            <a class="page-link" href="<?= $url ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</main>

<!-- FAB (mobile) -->
<a href="/servico_form.php" class="btn btn-primary rounded-circle shadow fab-new d-md-none" title="Novo serviço">
  <i class="bi bi-plus-lg"></i>
</a>

<?php require_once __DIR__.'/_layout_end.php'; ?>

<style>
/* ===== Barra de filtros enxuta ===== */
.filters-bar{
  position: sticky;
  top: 0;
  z-index: 5;
  background: #fff;
  padding: .5rem .6rem;
  border: 1px solid #e9ecef;
  border-radius: .5rem;
}
.filters-form .btn{ white-space: nowrap; }

/* Botão “Novo” compacto (desktop) */
.btn-new{
  align-self: center;
  padding: .30rem .60rem;
  border-radius: .5rem;
  line-height: 1.1;
  height: 1.8125rem;
  white-space: nowrap;
  box-shadow: 0 1px 2px rgba(0,0,0,.05);
}

/* FAB mobile */
.fab-new{
  position: fixed;
  right: 16px;
  bottom: 16px;
  width: 56px; height: 56px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.25rem;
  z-index: 1050;
}

/* Desktop mantém tabela */
@media (min-width: 768px){
  .srv-list table{ table-layout: fixed; }
}

/* Mobile: cards e sem overflow lateral */
@media (max-width: 767.98px){
  .btn-new{ display: none !important; }
  .srv-list .table-responsive{ overflow-x: visible; }

  .srv-list table, .srv-list thead, .srv-list tbody,
  .srv-list th, .srv-list td, .srv-list tr{
    display: block; width: 100%; box-sizing: border-box;
  }
  .srv-list thead{ display: none; }

  .srv-list tbody tr.srv-row{
    border: 1px solid #e9ecef; border-radius: .75rem;
    padding: .75rem; margin: .65rem 0; background: #fff;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
  }

  .srv-list td{
    display: grid; grid-template-columns: minmax(100px, 46%) 1fr;
    align-items: center; gap: .4rem; padding: .3rem 0; border: 0!important;
    word-break: break-word;
  }
  .srv-list td::before{ content: attr(data-label); font-size: .84rem; color: #6c757d; }

  .srv-list td[data-label="Preço (R$)"],
  .srv-list td[data-label="Custo (R$)"]{
    justify-content: space-between;
  }

  .srv-list td[data-label="Ações"]{
    display:flex; justify-content:flex-end; gap:.5rem; margin-top:.25rem;
  }
  .srv-list td[data-label="Ações"] .btn{ padding:.45rem .6rem; }
}
</style>
