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

<main class="hf-content os-page" style="overflow-x:hidden;">
  <div class="os-hero mb-3">
    <div>
      <div class="text-muted small text-uppercase fw-semibold">Ordens de Serviço</div>
      <h4 class="mb-1">Atendimento e execução</h4>
      <div class="text-muted small">
        <?= (int)$total ?> registro<?= $total === 1 ? '' : 's' ?> encontrado<?= $total === 1 ? '' : 's' ?>
      </div>
    </div>

    <a href="/os_form.php" class="btn btn-primary btn-sm d-none d-md-inline-flex align-items-center gap-1">
      <i class="bi bi-plus-lg"></i>
      <span>Nova OS</span>
    </a>
  </div>

  <div class="hf-card os-filter-card p-3 mb-3">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-12 col-md-5">
        <label class="form-label small text-muted mb-1">Buscar</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" class="form-control" placeholder="Nº OS ou cliente">
        </div>
      </div>

      <div class="col-8 col-md-3">
        <label class="form-label small text-muted mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">Todos</option>
          <?php foreach (['aberta','em_andamento','concluida','cancelada'] as $s): ?>
            <option value="<?= $s ?>" <?= $status===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-4 col-md-auto">
        <button class="btn btn-primary btn-sm w-100">
          Filtrar
        </button>
      </div>

      <?php if ($q !== '' || $status !== ''): ?>
        <div class="col-12 col-md-auto">
          <a class="btn btn-outline-secondary btn-sm w-100" href="/os_list.php">
            Limpar
          </a>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <div class="hf-card p-0 os-list-modern">
    <div class="table-responsive">
      <table class="table align-middle mb-0" id="osTable">
        <thead>
          <tr>
            <th style="width:90px">OS</th>
            <th>Cliente</th>
            <th>Status</th>
            <th>Financeiro</th>
            <th>Prioridade</th>
            <th>Técnico</th>
            <th>Abertura</th>
            <th class="text-end">Total</th>
            <th class="text-end" style="width:150px">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!$rows): ?>
            <tr>
              <td colspan="9" class="text-center text-muted py-5">
                <div class="mb-2"><i class="bi bi-inbox fs-3"></i></div>
                Nenhuma OS encontrada.
              </td>
            </tr>
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

            $prioridade = strtolower((string)($r['prioridade'] ?? ''));
            $prioClass = [
              'baixa' => 'success',
              'media' => 'warning',
              'alta'  => 'danger',
            ][$prioridade] ?? 'secondary';
          ?>
            <tr class="os-row">
              <td data-label="OS">
                <div class="os-number">#<?= $num ?></div>
              </td>

              <td data-label="Cliente">
                <div class="fw-semibold"><?= htmlspecialchars($r['cliente']) ?></div>
                <div class="text-muted small d-md-none"><?= $dtAb ?></div>
              </td>

              <td data-label="Status">
                <span class="os-pill os-pill-<?= htmlspecialchars($r['status'] ?? 'secondary') ?>">
                  <?= $label ?>
                </span>
              </td>

              <td data-label="Financeiro">
                <span class="badge rounded-pill text-bg-<?= $finClass ?>">
                  <?= $finLabel ?>
                </span>
              </td>

              <td data-label="Prioridade">
                <span class="badge rounded-pill text-bg-<?= $prioClass ?>">
                  <?= htmlspecialchars(ucfirst($r['prioridade'] ?? '')) ?>
                </span>
              </td>

              <td data-label="Técnico">
                <?= htmlspecialchars($r['tecnico'] ?: '-') ?>
              </td>

              <td data-label="Abertura">
                <?= $dtAb ?>
              </td>

              <td data-label="Total" class="text-end">
                <strong>R$ <?= $tot ?></strong>
              </td>

              <td data-label="Ações" class="text-end">
                <div class="os-actions">
                  <a class="btn btn-sm btn-light border"
                     href="/os_documento.php?id=<?= (int)$r['id'] ?>"
                     target="_blank"
                     title="Documento / Impressão">
                    <i class="bi bi-file-earmark-text"></i>
                  </a>

                  <a class="btn btn-sm btn-primary"
                     href="/os_form.php?id=<?= (int)$r['id'] ?>"
                     title="Editar">
                    <i class="bi bi-pencil-square"></i>
                  </a>

                  <form method="post" action="/os_delete.php" class="d-inline m-0 p-0" onsubmit="return confirm('Confirma excluir (soft delete)?');">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir">
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

<a href="/os_form.php" class="btn btn-primary rounded-circle shadow fab-new-os d-md-none" title="Nova OS">
  <i class="bi bi-plus-lg"></i>
</a>

<?php require_once __DIR__.'/_layout_end.php'; ?>

<style>
.os-page{
  background:
    radial-gradient(circle at top right, rgba(var(--bs-primary-rgb), .08), transparent 34rem),
    linear-gradient(180deg, rgba(var(--bs-primary-rgb), .03), transparent 18rem);
}
.os-hero{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:1rem;
  padding:1rem 1.1rem;
  border:1px solid rgba(0,0,0,.06);
  border-radius:18px;
  background:rgba(255,255,255,.78);
  box-shadow:0 8px 24px rgba(15,23,42,.06);
}
[data-bs-theme="dark"] .os-hero{
  background:rgba(33,37,41,.72);
  border-color:rgba(255,255,255,.08);
}
.os-filter-card{
  border-radius:16px;
}
.os-filter-card .input-group-text{
  background:var(--bs-body-bg);
}
.os-list-modern{
  overflow:hidden;
  border-radius:18px;
}
.os-list-modern table{
  --bs-table-hover-bg:rgba(var(--bs-primary-rgb), .045);
}
.os-list-modern thead th{
  padding:.85rem .9rem;
  background:rgba(var(--bs-primary-rgb), .06);
  color:var(--bs-secondary-color);
  font-size:.76rem;
  letter-spacing:.04em;
  text-transform:uppercase;
  border-bottom:1px solid rgba(0,0,0,.06);
}
.os-list-modern tbody td{
  padding:.9rem;
  border-color:rgba(0,0,0,.055);
}
.os-row{
  transition:background .15s ease, transform .15s ease;
}
.os-row:hover{
  transform:translateY(-1px);
}
.os-number{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:58px;
  padding:.3rem .55rem;
  border-radius:999px;
  background:rgba(var(--bs-primary-rgb), .09);
  color:var(--bs-primary);
  font-weight:700;
}
.os-pill{
  display:inline-flex;
  align-items:center;
  min-height:24px;
  padding:.22rem .58rem;
  border-radius:999px;
  font-size:.78rem;
  font-weight:700;
  line-height:1;
  white-space:nowrap;
}
.os-pill-aberta{
  background:rgba(108,117,125,.13);
  color:#5c636a;
}
.os-pill-em_andamento{
  background:rgba(255,193,7,.22);
  color:#8a6500;
}
.os-pill-concluida{
  background:rgba(25,135,84,.15);
  color:#147044;
}
.os-pill-cancelada{
  background:rgba(33,37,41,.14);
  color:#212529;
}
[data-bs-theme="dark"] .os-pill-aberta{ color:#cbd3da; }
[data-bs-theme="dark"] .os-pill-cancelada{ color:#e9ecef; }
.os-actions{
  display:inline-flex;
  align-items:center;
  justify-content:flex-end;
  gap:.35rem;
}
.os-actions .btn{
  width:32px;
  height:32px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border-radius:10px;
  padding:0;
}
.fab-new-os{
  position:fixed;
  right:16px;
  bottom:16px;
  width:56px;
  height:56px;
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:1.25rem;
  z-index:1050;
}
@media (min-width:768px){
  .os-list-modern table{
    table-layout:fixed;
  }
}
@media (max-width:767.98px){
  .os-hero{
    align-items:flex-start;
    padding:.9rem;
  }
  .os-list-modern{
    background:transparent;
    border:0;
    box-shadow:none;
  }
  .os-list-modern .table-responsive{
    overflow-x:visible;
  }
  .os-list-modern table,
  .os-list-modern thead,
  .os-list-modern tbody,
  .os-list-modern th,
  .os-list-modern td,
  .os-list-modern tr{
    display:block;
    width:100%;
    box-sizing:border-box;
  }
  .os-list-modern thead{
    display:none;
  }
  .os-list-modern tbody tr.os-row{
    border:1px solid rgba(0,0,0,.08);
    border-radius:16px;
    padding:.85rem;
    margin:.75rem 0;
    background:var(--bs-body-bg);
    box-shadow:0 8px 20px rgba(15,23,42,.07);
  }
  .os-list-modern tbody td{
    display:grid;
    grid-template-columns:minmax(94px, 38%) 1fr;
    align-items:center;
    gap:.45rem;
    padding:.38rem 0;
    border:0!important;
    word-break:break-word;
  }
  .os-list-modern tbody td::before{
    content:attr(data-label);
    color:var(--bs-secondary-color);
    font-size:.8rem;
    font-weight:600;
  }
  .os-list-modern td[data-label="Cliente"]{
    display:block;
    padding-bottom:.65rem;
    margin-bottom:.35rem;
    border-bottom:1px solid rgba(0,0,0,.06)!important;
  }
  .os-list-modern td[data-label="Cliente"]::before{
    display:none;
  }
  .os-list-modern td[data-label="Total"]{
    align-items:end;
  }
  .os-list-modern td[data-label="Total"] strong{
    color:var(--bs-primary);
    font-size:1.05rem;
  }
  .os-list-modern td[data-label="Ações"]{
    display:flex;
    justify-content:flex-end;
    margin-top:.45rem;
    padding-top:.65rem;
    border-top:1px solid rgba(0,0,0,.06)!important;
  }
  .os-list-modern td[data-label="Ações"]::before{
    display:none;
  }
  .os-actions .btn{
    width:38px;
    height:38px;
  }
}
</style>
