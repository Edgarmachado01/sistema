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

// Parâmetros de busca/paginação
$q   = isset($_GET['q']) ? trim($_GET['q']) : '';
$pg  = max(1, (int)($_GET['pg'] ?? 1));
$per = 15;
$off = ($pg - 1) * $per;

// Filtro
$where  = "tenant_id = :tid AND deleted_at IS NULL";
$params = [':tid'=>$tid];

if ($q !== '') {
  $where .= " AND (nome LIKE :q OR email LIKE :q OR documento LIKE :q OR telefone LIKE :q OR celular LIKE :q)";
  $params[':q'] = "%{$q}%";
}

// Total
$sqlCount = "SELECT COUNT(*) FROM hf_clientes WHERE $where";
$st = $pdo->prepare($sqlCount);
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total/$per));

// Dados
$sql = "SELECT id, nome, documento, email, telefone, celular, status, cidade, uf
        FROM hf_clientes
        WHERE $where
        ORDER BY nome ASC
        LIMIT :per OFFSET :off";
$st = $pdo->prepare($sql);
foreach ($params as $k=>$v) $st->bindValue($k, $v);
$st->bindValue(':per', $per, PDO::PARAM_INT);
$st->bindValue(':off', $off, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include __DIR__.'/_sidebar.php'; ?>
<main class="hf-content" style="overflow-x:hidden;">

  <!-- Título + Filtros (compacto, sem quebra) -->
  <div class="d-flex flex-wrap align-items-center mb-3 gap-2">
    <h4 class="mb-0 me-auto">Clientes</h4>

    <div id="cliFilters" class="filters-bar d-flex align-items-center gap-2 flex-grow-1 flex-md-grow-0">
      <form class="filters-form d-flex align-items-center gap-2 flex-grow-1" method="get">
        <!-- input-group junta input + botão, sem quebrar -->
        <div class="input-group input-group-sm flex-grow-1">
          <input type="text"
                 name="q"
                 value="<?= htmlspecialchars($q) ?>"
                 class="form-control"
                 placeholder="Buscar nome, doc, email, fone...">
          <button class="btn btn-primary" type="submit" title="Pesquisar">
            <i class="bi bi-search"></i>
          </button>
        </div>
      </form>

      <!-- Botão “Novo” compacto no desktop -->
      <a href="/cliente_form.php"
         class="btn btn-success btn-sm btn-new d-none d-md-inline-flex">
        <i class="bi bi-plus-lg me-1"></i><span>Novo</span>
      </a>
    </div>
  </div>

  <!-- Lista -->
  <div class="hf-card p-0 cli-list">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0" id="cliTable">
        <thead class="table-light">
          <tr>
            <th>Nome</th>
            <th>Documento</th>
            <th>Email</th>
            <th>Telefone</th>
            <th>Cidade/UF</th>
            <th>Status</th>
            <th class="text-end" style="width:120px">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">Nenhum cliente encontrado.</td></tr>
          <?php else: foreach ($rows as $r):
            $fone = $r['telefone'] ?: $r['celular'] ?: '';
            $cidadeUf = trim(($r['cidade'] ?: '').'/'.($r['uf'] ?: ''), '/');
          ?>
            <tr class="cli-row">
              <td data-label="Nome" class="fw-semibold" style="word-break:break-word;"><?= htmlspecialchars($r['nome']) ?></td>
              <td data-label="Documento"><?= htmlspecialchars($r['documento'] ?? '') ?></td>
              <td data-label="Email" style="word-break:break-word;"><?= htmlspecialchars($r['email'] ?? '') ?></td>
              <td data-label="Telefone"><?= htmlspecialchars($fone) ?></td>
              <td data-label="Cidade/UF"><?= htmlspecialchars($cidadeUf) ?></td>
              <td data-label="Status">
                <?php if ((int)$r['status']===1): ?>
                  <span class="badge bg-success">Ativo</span>
                <?php else: ?>
                  <span class="badge bg-secondary">Inativo</span>
                <?php endif; ?>
              </td>
              <td data-label="Ações" class="text-end">
                <div class="d-inline-flex gap-1">
                  <a class="btn btn-sm btn-outline-primary" href="/cliente_form.php?id=<?= (int)$r['id'] ?>" title="Editar"><i class="bi bi-pencil-square"></i></a>
                  <form method="post" action="/cliente_delete.php" class="d-inline m-0 p-0" onsubmit="return confirm('Confirma excluir (soft delete)?');">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"><i class="bi bi-trash"></i></button>
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
          $url = '?'.http_build_query(['q'=>$q,'pg'=>$i]); ?>
          <li class="page-item <?= $i===$pg?'active':'' ?>">
            <a class="page-link" href="<?= $url ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</main>

<!-- FAB (mobile) -->
<a href="/cliente_form.php" class="btn btn-primary rounded-circle shadow fab-new d-md-none" title="Novo cliente">
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
  padding: .5rem .6rem;         /* menos altura */
  border: 1px solid #e9ecef;
  border-radius: .5rem;
}
.filters-form .btn{ white-space: nowrap; } /* nunca quebra o botão */

/* Botão “Novo” compacto no desktop */
.btn-new{
  align-self: center;
  padding: .30rem .60rem;
  border-radius: .5rem;
  line-height: 1.1;
  height: 1.8125rem; /* ~29px */
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
  .cli-list table{ table-layout: fixed; }
}

/* Mobile: cards sem overflow lateral */
@media (max-width: 767.98px){
  .btn-new{ display: none !important; }
  .cli-list .table-responsive{ overflow-x: visible; }

  .cli-list table,
  .cli-list thead,
  .cli-list tbody,
  .cli-list th,
  .cli-list td,
  .cli-list tr{
    display: block;
    width: 100%;
    box-sizing: border-box;
  }
  .cli-list thead{ display: none; }

  .cli-list tbody tr.cli-row{
    border: 1px solid #e9ecef;
    border-radius: .75rem;
    padding: .75rem;
    margin: .65rem 0;
    background: #fff;
    box-shadow: 0 1px 2px rgba(0,0,0,.04);
  }

  .cli-list td{
    display: grid;
    grid-template-columns: minmax(88px, 42%) 1fr;
    align-items: center;
    gap: .4rem;
    padding: .3rem 0;
    border: 0 !important;
    word-break: break-word;
  }

  .cli-list td::before{
    content: attr(data-label);
    font-size: .84rem;
    color: #6c757d;
  }

  .cli-list td[data-label="Ações"]{
    display: flex; justify-content: flex-end; gap: .5rem; margin-top: .25rem;
  }
  .cli-list td[data-label="Ações"] .btn{ padding: .45rem .6rem; }
}
</style>
