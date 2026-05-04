<?php
require_once __DIR__.'/_layout_start.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

$pdo = db();
$tid = tenantId();
if (!$tid) die('Tenant inválido.');

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
<main class="hf-content" style="overflow-x:hidden;">
  <!-- Título + filtros + botão (desktop) -->
  <div class="d-flex align-items-start mb-3 position-relative flex-wrap gap-2">
    <h4 class="mb-0 me-auto">Ordens de Serviço</h4>

    <div class="d-flex flex-column flex-md-row gap-2 ms-md-auto w-100 w-md-auto" id="osFilters">
      <form class="d-flex flex-wrap gap-2" method="get">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control form-control-sm" placeholder="Nº OS ou Cliente">
        <select name="status" class="form-select form-select-sm">
          <option value="">Status (todos)</option>
          <?php foreach (['aberta','em_andamento','concluida','cancelada'] as $s): ?>
            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm"><i class="bi bi-search"></i></button>
      </form>

      <!-- Botão compacto só no desktop -->
      <a href="/os_form.php"
         class="btn btn-success btn-sm btn-new-os d-none d-md-inline-flex">
        <i class="bi bi-plus-lg me-1"></i><span>Nova OS</span>
      </a>
    </div>
  </div>

  <!-- Lista -->
  <div class="hf-card p-0 os-list">
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
            <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma OS encontrada.</td></tr>
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
              <td data-label="Nº"><span class="fw-semibold">#<?= $num ?></span></td>
              <td data-label="Cliente"><?= htmlspecialchars($r['cliente']) ?></td>
              <td data-label="Status">
  <span class="badge bg-<?= $badge[$r['status'] ?? 'secondary'] ?>"><?= $label ?></span>
</td>
<td data-label="Financeiro">
  <span class="badge bg-<?= $finClass ?>"><?= $finLabel ?></span>
</td>
              <td data-label="Prioridade"><?= htmlspecialchars(ucfirst($r['prioridade'] ?? '')) ?></td>
              <td data-label="Técnico"><?= htmlspecialchars($r['tecnico'] ?? '') ?></td>
              <td data-label="Abertura"><?= $dtAb ?></td>
              <td data-label="Total" class="text-end fw-semibold"><?= $tot ?></td>
              <td data-label="Ações" class="text-end">
                <div class="d-inline-flex gap-1">
                  <!-- Documento / Impressão -->
                  <a class="btn btn-sm btn-outline-secondary"
                     href="/os_documento.php?id=<?= (int)$r['id'] ?>"
                     target="_blank"
                     title="Documento / Impressão">
                    <i class="bi bi-file-earmark-text"></i>
                  </a>

                  <!-- Editar -->
                  <a class="btn btn-sm btn-outline-primary"
                     href="/os_form.php?id=<?= (int)$r['id'] ?>"
                     title="Editar">
                    <i class="bi bi-pencil-square"></i>
                  </a>

                  <!-- Excluir -->
                  <a class="btn btn-sm btn-outline-danger"
                     href="/os_delete.php?id=<?= (int)$r['id'] ?>"
                     onclick="return confirm('Confirma excluir (soft delete)?');"
                     title="Excluir">
                    <i class="bi bi-trash"></i>
                  </a>
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
          $url='?'.http_build_query(['q'=>$q,'status'=>$status,'pg'=>$i]); ?>
          <li class="page-item <?= $i===$pg?'active':'' ?>">
            <a class="page-link" href="<?= $url ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</main>

<!-- FAB (mobile) -->
<a href="/os_form.php" class="btn btn-primary rounded-circle shadow fab-new-os d-md-none" title="Nova OS">
  <i class="bi bi-plus-lg"></i>
</a>

<?php require_once __DIR__.'/_layout_end.php'; ?>

<style>
/* ====== Botão “+ Nova OS” compacto (desktop) ====== */
.btn-new-os{
  align-self: flex-start;
  padding: .30rem .60rem;
  border-radius: .5rem;
  line-height: 1.1;
  height: 1.8125rem; /* ~29px */
  white-space: nowrap;
  box-shadow: 0 1px 2px rgba(0,0,0,.05);
}

/* ====== Sticky filtros ====== */
#osFilters{
  position: sticky;
  top: 0;
  z-index: 5;
  background: #fff;
  padding: .5rem;
  border: 1px solid #e9ecef;
  border-radius: .5rem;
}
#osFilters form .form-control-sm,
#osFilters form .form-select-sm{ min-height: 29px; }

/* ====== FAB mobile ====== */
.fab-new-os{
  position: fixed;
  right: 16px;
  bottom: 16px;
  width: 56px; height: 56px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.25rem;
  z-index: 1050;
}

/* ====== Desktop mantém tabela normal ====== */
@media (min-width: 768px){
  .os-list table{ table-layout: fixed; }
}

/* ====== Mobile vira cards e SEM overflow lateral ====== */
@media (max-width: 767.98px){
  .btn-new-os{ display: none !important; }

  /* mata o scroll horizontal padrão do .table-responsive */
  .os-list .table-responsive{ overflow-x: visible; }

  .os-list table,
  .os-list thead,
  .os-list tbody,
  .os-list th,
  .os-list td,
  .os-list tr{
    display: block;
    width: 100%;
    box-sizing: border-box;
  }

  .os-list thead{ display: none; }

  .os-list tbody tr.os-row{
    border: 1px solid #e9ecef;
    border-radius: .75rem;
    padding: .75rem;
    margin: .65rem 0;          /* sem margem lateral pra não “estourar” */
    background: #fff;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
  }

  .os-list td{
    display: grid;
    grid-template-columns: minmax(88px, 42%) 1fr; /* rótulo adaptável */
    align-items: center;
    gap: .4rem;
    padding: .3rem 0;
    border: 0 !important;
    word-break: break-word;    /* evita “empurrar” a largura */
  }

  .os-list td::before{
    content: attr(data-label);
    font-size: .84rem;
    color: #6c757d;
  }

  .os-list td[data-label="Nº"] .fw-semibold{ font-size: 1.1rem; }
  .os-list td[data-label="Total"]{ align-items: end; }
  .os-list td[data-label="Total"] .fw-semibold{
    color: #0d6efd; font-weight: 700; font-size: 1.05rem;
  }

  .os-list td[data-label="Ações"]{
    display: flex; justify-content: flex-end; gap: .5rem; margin-top: .25rem;
  }
  .os-list td[data-label="Ações"] .btn{ padding: .45rem .6rem; }
}
</style>
