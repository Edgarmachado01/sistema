<?php
require_once __DIR__.'/_layout_start.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

$pdo = db();
$tid = tenantId();
if (!$tid) { die('Tenant inválido.'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$row = [
  'id'=>0,'nome'=>'','sku'=>'','categoria'=>'','unidade'=>'',
  'ncm'=>'','garantia_dias'=>null,'preco'=>'0.00','custo'=>'0.00',
  'descricao'=>'','status'=>1
];

if ($id>0) {
  $st = $pdo->prepare("SELECT * FROM hf_produtos WHERE id=:id AND tenant_id=:tid AND deleted_at IS NULL");
  $st->execute([':id'=>$id, ':tid'=>$tid]);
  $ex = $st->fetch(PDO::FETCH_ASSOC);
  if ($ex) $row = $ex;
}
?>
<?php include __DIR__.'/_sidebar.php'; ?>
<main class="hf-content">
  <div class="d-flex align-items-center mb-3">
    <h4 class="mb-0"><?= $id>0 ? 'Editar Produto' : 'Novo Produto' ?></h4>
    <div class="ms-auto">
      <a class="btn btn-outline-secondary btn-sm" href="/produtos.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
  </div>

  <form class="hf-card p-3" method="post" action="/produto_save.php" novalidate>
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Nome*</label>
        <input class="form-control" name="nome" required maxlength="180" value="<?= htmlspecialchars($row['nome']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">SKU</label>
        <input class="form-control" name="sku" maxlength="60" value="<?= htmlspecialchars($row['sku']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Categoria</label>
        <input class="form-control" name="categoria" maxlength="120" value="<?= htmlspecialchars($row['categoria']) ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label">Unidade</label>
        <input class="form-control" name="unidade" maxlength="10" value="<?= htmlspecialchars($row['unidade']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">NCM</label>
        <input class="form-control" name="ncm" maxlength="20" value="<?= htmlspecialchars($row['ncm']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Garantia (dias)</label>
        <input type="number" class="form-control" name="garantia_dias" min="0" step="1" value="<?= (int)($row['garantia_dias'] ?? 0) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <option value="1" <?= (int)$row['status']===1?'selected':'' ?>>Ativo</option>
          <option value="0" <?= (int)$row['status']===0?'selected':'' ?>>Inativo</option>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Preço (R$)</label>
        <input type="text" class="form-control" name="preco" inputmode="decimal" value="<?= number_format((float)$row['preco'],2,',','.') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Custo (R$)</label>
        <input type="text" class="form-control" name="custo" inputmode="decimal" value="<?= number_format((float)$row['custo'],2,',','.') ?>">
      </div>

      <div class="col-12">
        <label class="form-label">Descrição</label>
        <textarea class="form-control" name="descricao" rows="3"><?= htmlspecialchars($row['descricao']) ?></textarea>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <a class="btn btn-outline-secondary" href="/produtos.php">Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</main>
<?php require_once __DIR__.'/_layout_end.php'; ?>
