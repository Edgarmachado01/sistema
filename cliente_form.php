<?php
require_once __DIR__.'/_layout_start.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

$pdo = db();
$tid = tenantId();
if (!$tid) { die('Tenant inválido.'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$row = [
  'id'=>0,'nome'=>'','documento'=>'','email'=>'','telefone'=>'','celular'=>'',
  'cep'=>'','endereco'=>'','numero'=>'','complemento'=>'','bairro'=>'','cidade'=>'','uf'=>'',
  'obs'=>'','status'=>1
];

if ($id>0) {
  $st = $pdo->prepare("SELECT * FROM hf_clientes WHERE id=:id AND tenant_id=:tid AND deleted_at IS NULL");
  $st->execute([':id'=>$id, ':tid'=>$tid]);
  $ex = $st->fetch(PDO::FETCH_ASSOC);
  if ($ex) $row = $ex;
}
?>
<?php include __DIR__.'/_sidebar.php'; ?>
<main class="hf-content">
  <div class="d-flex align-items-center mb-3">
    <h4 class="mb-0"><?= $id>0 ? 'Editar Cliente' : 'Novo Cliente' ?></h4>
    <div class="ms-auto">
      <a class="btn btn-outline-secondary btn-sm" href="/clientes.php"><i class="bi bi-arrow-left"></i> Voltar</a>
    </div>
  </div>

  <form class="hf-card p-3" method="post" action="/cliente_save.php" novalidate>
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Nome*</label>
        <input class="form-control" name="nome" required maxlength="150" value="<?= htmlspecialchars($row['nome']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Documento (CPF/CNPJ)</label>
        <input class="form-control" name="documento" maxlength="20" value="<?= htmlspecialchars($row['documento']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <option value="1" <?= (int)$row['status']===1?'selected':'' ?>>Ativo</option>
          <option value="0" <?= (int)$row['status']===0?'selected':'' ?>>Inativo</option>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" name="email" maxlength="120" value="<?= htmlspecialchars($row['email']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Telefone</label>
        <input class="form-control" name="telefone" maxlength="30" value="<?= htmlspecialchars($row['telefone']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Celular</label>
        <input class="form-control" name="celular" maxlength="30" value="<?= htmlspecialchars($row['celular']) ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label">CEP</label>
        <input class="form-control" name="cep" maxlength="10" value="<?= htmlspecialchars($row['cep']) ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Endereço</label>
        <input class="form-control" name="endereco" maxlength="150" value="<?= htmlspecialchars($row['endereco']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Número</label>
        <input class="form-control" name="numero" maxlength="20" value="<?= htmlspecialchars($row['numero']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Complemento</label>
        <input class="form-control" name="complemento" maxlength="100" value="<?= htmlspecialchars($row['complemento']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Bairro</label>
        <input class="form-control" name="bairro" maxlength="80" value="<?= htmlspecialchars($row['bairro']) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Cidade</label>
        <input class="form-control" name="cidade" maxlength="100" value="<?= htmlspecialchars($row['cidade']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">UF</label>
        <input class="form-control" name="uf" maxlength="2" value="<?= htmlspecialchars($row['uf']) ?>">
      </div>

      <div class="col-12">
        <label class="form-label">Observações</label>
        <textarea class="form-control" name="obs" rows="3"><?= htmlspecialchars($row['obs']) ?></textarea>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <a class="btn btn-outline-secondary" href="/clientes.php">Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</main>
<?php require_once __DIR__.'/_layout_end.php'; ?>
