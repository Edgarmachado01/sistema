<?php
require_once __DIR__.'/_layout_start.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

$pdo = db();
$tid = tenantId();
if (!$tid) { die('Tenant inválido.'); }

// Token CSRF para ações de exclusão via POST
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$q   = isset($_GET['q']) ? trim($_GET['q']) : '';
$pg  = max(1, (int)($_GET['pg'] ?? 1));
$per = 15;
$off = ($pg - 1) * $per;

$where  = "tenant_id = :tid AND deleted_at IS NULL";
$params = [':tid'=>$tid];

if ($q !== '') {
  $where .= " AND (nome LIKE :q OR sku LIKE :q OR categoria LIKE :q OR ncm LIKE :q)";
  $params[':q'] = "%{$q}%";
}

$st = $pdo->prepare("SELECT COUNT(*) FROM hf_produtos WHERE $where");
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total/$per));

$sql = "SELECT id, nome, sku, categoria, preco, custo, unidade, status
        FROM hf_produtos
        WHERE $where
        ORDER BY nome ASC
        LIMIT :per OFFSET :off";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) $st->bindValue($k,$v);
$st->bindValue(':per',$per,PDO::PARAM_INT);
$st->bindValue(':off',$off,PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include __DIR__.'/_sidebar.php'; ?>

<main class="hf-content produtos-page" style="overflow-x:hidden;">
  <div class="produtos-hero mb-3">
    <div>
      <div class="text-muted small text-uppercase fw-semibold">Produtos</div>
      <h4 class="mb-1">Catálogo de produtos</h4>
      <div class="text-muted small">
        <?= (int)$total ?> produto<?= $total === 1 ? '' : 's' ?> encontrado<?= $total === 1 ? '' : 's' ?>
      </div>
    </div>

    <a href="/produto_form.php" class="btn btn-primary btn-sm d-none d-md-inline-flex align-items-center gap-1">
      <i class="bi bi-plus-lg"></i>
      <span>Novo produto</span>
    </a>
  </div>

  <div class="hf-card produtos-filter-card p-3 mb-3">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-12 col-md-7 col-lg-6">
        <label class="form-label small text-muted mb-1">Buscar</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text"
                 name="q"
                 value="<?= htmlspecialchars($q) ?>"
                 class="form-control"
                 placeholder="Nome, SKU, categoria ou NCM">
        </div>
      </div>

      <div class="col-6 col-md-auto">
        <button class="btn btn-primary btn-sm w-100" type="submit">
          Filtrar
        </button>
      </div>

      <?php if ($q !== ''): ?>
        <div class="col-6 col-md-auto">
          <a class="btn btn-outline-secondary btn-sm w-100" href="/produtos.php">
            Limpar
          </a>
        </div>
      <?php endif; ?>
    </form>
  </div>

  <div class="hf-card p-0 produtos-list-modern">
    <div class="table-responsive">
      <table class="table align-middle mb-0" id="prdTable">
        <thead>
          <tr>
            <th>Produto</th>
            <th>SKU</th>
            <th>Categoria</th>
            <th>Un.</th>
            <th class="text-end">Preço</th>
            <th class="text-end">Custo</th>
            <th>Status</th>
            <th class="text-end" style="width:130px">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-5">
                <div class="mb-2"><i class="bi bi-box-seam fs-3"></i></div>
                Nenhum produto encontrado.
              </td>
            </tr>
          <?php else: foreach ($rows as $r):
            $ativo = (int)$r['status'] === 1;
          ?>
            <tr class="prd-row">
              <td data-label="Produto">
                <div class="produto-name"><?= htmlspecialchars($r['nome']) ?></div>
                <?php if (!empty($r['categoria'])): ?>
                  <div class="text-muted small d-md-none"><?= htmlspecialchars($r['categoria']) ?></div>
                <?php endif; ?>
              </td>

              <td data-label="SKU">
                <?= htmlspecialchars($r['sku'] ?: '-') ?>
              </td>

              <td data-label="Categoria">
                <?= htmlspecialchars($r['categoria'] ?: '-') ?>
              </td>

              <td data-label="Un.">
                <span class="badge rounded-pill text-bg-light border"><?= htmlspecialchars($r['unidade'] ?: '-') ?></span>
              </td>

              <td data-label="Preço" class="text-end">
                <strong>R$ <?= number_format((float)$r['preco'],2,',','.') ?></strong>
              </td>

              <td data-label="Custo" class="text-end">
                R$ <?= number_format((float)$r['custo'],2,',','.') ?>
              </td>

              <td data-label="Status">
                <?php if ($ativo): ?>
                  <span class="badge rounded-pill text-bg-success">Ativo</span>
                <?php else: ?>
                  <span class="badge rounded-pill text-bg-secondary">Inativo</span>
                <?php endif; ?>
              </td>

              <td data-label="Ações" class="text-end">
                <div class="produto-actions">
                  <a class="btn btn-sm btn-primary"
                     href="/produto_form.php?id=<?= (int)$r['id'] ?>"
                     title="Editar">
                    <i class="bi bi-pencil-square"></i>
                  </a>

                  <form method="post" action="/produto_delete.php" class="d-inline m-0 p-0" onsubmit="return confirm('Confirma excluir (soft delete)?');">
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

  <?php if ($pages>1): ?>
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

<a href="/produto_form.php" class="btn btn-primary rounded-circle shadow fab-new d-md-none" title="Novo produto">
  <i class="bi bi-plus-lg"></i>
</a>

<?php require_once __DIR__.'/_layout_end.php'; ?>

<style>
.produtos-page{
  background:
    radial-gradient(circle at top right, rgba(var(--bs-primary-rgb), .08), transparent 34rem),
    linear-gradient(180deg, rgba(var(--bs-primary-rgb), .03), transparent 18rem);
}
.produtos-hero{
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
[data-bs-theme="dark"] .produtos-hero{
  background:rgba(33,37,41,.72);
  border-color:rgba(255,255,255,.08);
}
.produtos-filter-card{
  border-radius:16px;
}
.produtos-filter-card .input-group-text{
  background:var(--bs-body-bg);
}
.produtos-list-modern{
  overflow:hidden;
  border-radius:18px;
}
.produtos-list-modern table{
  --bs-table-hover-bg:rgba(var(--bs-primary-rgb), .045);
}
.produtos-list-modern thead th{
  padding:.85rem .9rem;
  background:rgba(var(--bs-primary-rgb), .06);
  color:var(--bs-secondary-color);
  font-size:.76rem;
  letter-spacing:.04em;
  text-transform:uppercase;
  border-bottom:1px solid rgba(0,0,0,.06);
}
.produtos-list-modern tbody td{
  padding:.9rem;
  border-color:rgba(0,0,0,.055);
}
.prd-row{
  transition:background .15s ease, transform .15s ease;
}
.prd-row:hover{
  transform:translateY(-1px);
}
.produto-name{
  font-weight:700;
  color:var(--bs-body-color);
  word-break:break-word;
}
.produto-actions{
  display:inline-flex;
  align-items:center;
  justify-content:flex-end;
  gap:.35rem;
}
.produto-actions .btn{
  width:32px;
  height:32px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border-radius:10px;
  padding:0;
}
.fab-new{
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
  .produtos-list-modern table{
    table-layout:fixed;
  }
}
@media (max-width:767.98px){
  .produtos-hero{
    align-items:flex-start;
    padding:.9rem;
  }
  .produtos-list-modern{
    background:transparent;
    border:0;
    box-shadow:none;
  }
  .produtos-list-modern .table-responsive{
    overflow-x:visible;
  }
  .produtos-list-modern table,
  .produtos-list-modern thead,
  .produtos-list-modern tbody,
  .produtos-list-modern th,
  .produtos-list-modern td,
  .produtos-list-modern tr{
    display:block;
    width:100%;
    box-sizing:border-box;
  }
  .produtos-list-modern thead{
    display:none;
  }
  .produtos-list-modern tbody tr.prd-row{
    border:1px solid rgba(0,0,0,.08);
    border-radius:16px;
    padding:.85rem;
    margin:.75rem 0;
    background:var(--bs-body-bg);
    box-shadow:0 8px 20px rgba(15,23,42,.07);
  }
  .produtos-list-modern tbody td{
    display:grid;
    grid-template-columns:minmax(94px, 38%) 1fr;
    align-items:center;
    gap:.45rem;
    padding:.38rem 0;
    border:0!important;
    word-break:break-word;
  }
  .produtos-list-modern tbody td::before{
    content:attr(data-label);
    color:var(--bs-secondary-color);
    font-size:.8rem;
    font-weight:600;
  }
  .produtos-list-modern td[data-label="Produto"]{
    display:block;
    padding-bottom:.65rem;
    margin-bottom:.35rem;
    border-bottom:1px solid rgba(0,0,0,.06)!important;
  }
  .produtos-list-modern td[data-label="Produto"]::before{
    display:none;
  }
  .produtos-list-modern td[data-label="Preço"],
  .produtos-list-modern td[data-label="Custo"]{
    align-items:end;
  }
  .produtos-list-modern td[data-label="Preço"] strong{
    color:var(--bs-primary);
    font-size:1.05rem;
  }
  .produtos-list-modern td[data-label="Ações"]{
    display:flex;
    justify-content:flex-end;
    margin-top:.45rem;
    padding-top:.65rem;
    border-top:1px solid rgba(0,0,0,.06)!important;
  }
  .produtos-list-modern td[data-label="Ações"]::before{
    display:none;
  }
  .produto-actions .btn{
    width:38px;
    height:38px;
  }
}
</style>
