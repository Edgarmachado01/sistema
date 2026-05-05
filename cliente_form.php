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
<main class="hf-content hf-cliente-form-page">
  <div class="container-fluid py-4 hf-cliente-form-wrap">

    <div class="hf-cliente-form-top mb-3">
      <div class="hf-cliente-form-title">
        <div class="hf-page-kicker">Cadastro</div>
        <h4 class="mb-0"><?= $id>0 ? 'Editar Cliente' : 'Novo Cliente' ?></h4>
        <div class="hf-page-subtitle">Organize dados cadastrais, contato, endereço e observações.</div>
      </div>

      <div class="hf-cliente-form-actions">
        <a class="btn btn-outline-secondary btn-sm hf-top-action-btn" href="/clientes.php">
          <i class="bi bi-arrow-left"></i> Voltar
        </a>
      </div>
    </div>

    <form class="hf-cliente-form-shell" method="post" action="/cliente_save.php" novalidate>
      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

      <div class="card mb-3 hf-form-section">
        <div class="card-header hf-section-header">
          <div class="hf-section-icon"><i class="bi bi-person-vcard"></i></div>
          <div>
            <strong>Dados do cliente</strong>
            <span>Identificação principal e situação cadastral.</span>
          </div>
        </div>
        <div class="card-body hf-section-body">
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
          </div>
        </div>
      </div>

      <div class="card mb-3 hf-form-section">
        <div class="card-header hf-section-header">
          <div class="hf-section-icon"><i class="bi bi-chat-dots"></i></div>
          <div>
            <strong>Contato</strong>
            <span>Canais para comunicação com o cliente.</span>
          </div>
        </div>
        <div class="card-body hf-section-body">
          <div class="row g-3">
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
          </div>
        </div>
      </div>

      <div class="card mb-3 hf-form-section">
        <div class="card-header hf-section-header">
          <div class="hf-section-icon"><i class="bi bi-geo-alt"></i></div>
          <div>
            <strong>Endereço</strong>
            <span>Localização para atendimento, entrega ou referência.</span>
          </div>
        </div>
        <div class="card-body hf-section-body">
          <div class="row g-3">
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
          </div>
        </div>
      </div>

      <div class="card mb-3 hf-form-section">
        <div class="card-header hf-section-header">
          <div class="hf-section-icon"><i class="bi bi-card-text"></i></div>
          <div>
            <strong>Observações</strong>
            <span>Informações adicionais importantes sobre o cliente.</span>
          </div>
        </div>
        <div class="card-body hf-section-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Observações</label>
              <textarea class="form-control" name="obs" rows="3"><?= htmlspecialchars($row['obs']) ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="hf-form-actions">
        <a class="btn btn-outline-secondary hf-btn-cancel" href="/clientes.php">
          <i class="bi bi-x-lg me-1"></i>Cancelar
        </a>
        <button class="btn btn-primary hf-btn-save" type="submit">
          <i class="bi bi-check-lg"></i> Salvar
        </button>
      </div>
    </form>

  </div>
</main>

<style>
.hf-cliente-form-page {
  min-height: calc(100vh - var(--topbar-h));
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .10), transparent 28rem),
    linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
}

.hf-cliente-form-wrap {
  max-width: 1480px;
}

.hf-cliente-form-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.hf-cliente-form-title {
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

.hf-cliente-form-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: .5rem;
}

.hf-cliente-form-shell {
  display: grid;
  gap: 1rem;
}

.hf-form-section {
  overflow: hidden;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

.hf-section-header {
  display: flex;
  align-items: flex-start;
  gap: .85rem;
  padding: 1.1rem 1.15rem;
  border-bottom: 1px solid rgba(226, 232, 240, .9);
  background: linear-gradient(180deg, rgba(248, 250, 252, .95), rgba(255, 255, 255, .95));
}

.hf-section-header strong {
  display: block;
  margin: 0;
  color: #0f172a;
  font-size: 1rem;
  font-weight: 850;
}

.hf-section-header span {
  display: block;
  margin-top: .18rem;
  color: #64748b;
  font-size: .86rem;
}

.hf-section-icon {
  width: 42px;
  height: 42px;
  flex: 0 0 42px;
  display: grid;
  place-items: center;
  border-radius: .85rem;
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .10);
  font-size: 1.15rem;
}

.hf-section-body {
  padding: 1.15rem;
}

.hf-form-section .form-label {
  margin-bottom: .35rem;
  font-size: .76rem;
  font-weight: 800;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.hf-form-section .form-control,
.hf-form-section .form-select {
  min-height: 42px;
  border-radius: .72rem;
  border-color: #dbe3ee;
  background-color: #f8fafc;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, .75);
}

.hf-form-section textarea.form-control {
  min-height: 96px;
}

.hf-form-section .form-control:focus,
.hf-form-section .form-select:focus {
  border-color: rgba(var(--bs-primary-rgb), .55);
  box-shadow: 0 0 0 .2rem rgba(var(--bs-primary-rgb), .12);
  background-color: #fff;
}

.hf-top-action-btn,
.hf-btn-save,
.hf-btn-cancel {
  min-height: 36px;
  border-radius: .72rem;
  font-weight: 800;
}

.hf-btn-save {
  box-shadow: 0 8px 18px rgba(var(--bs-primary-rgb), .16);
}

.hf-form-actions {
  position: sticky;
  bottom: 0;
  z-index: 10;
  display: flex;
  justify-content: flex-end;
  gap: .65rem;
  padding: 1rem;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .92);
  box-shadow: 0 -8px 26px rgba(15, 23, 42, .08);
  backdrop-filter: blur(8px);
}

@media (max-width: 767.98px) {
  .hf-cliente-form-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .hf-cliente-form-top {
    flex-direction: column;
  }

  .hf-cliente-form-actions {
    width: 100%;
    justify-content: stretch;
  }

  .hf-cliente-form-actions .btn {
    width: 100%;
  }

  .hf-section-header,
  .hf-section-body {
    padding: 1rem;
  }

  .hf-form-actions {
    flex-direction: column-reverse;
  }

  .hf-form-actions .btn {
    width: 100%;
  }
}

[data-bs-theme="dark"] .hf-cliente-form-page {
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .hf-form-section,
[data-bs-theme="dark"] .hf-form-actions {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-section-header {
  background: linear-gradient(180deg, rgba(30, 41, 59, .95), rgba(17, 24, 39, .95));
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-section-header strong {
  color: #e5e7eb;
}

[data-bs-theme="dark"] .hf-form-section .form-control,
[data-bs-theme="dark"] .hf-form-section .form-select {
  background-color: rgba(15, 23, 42, .9);
  border-color: rgba(148, 163, 184, .24);
}
</style>

<?php require_once __DIR__.'/_layout_end.php'; ?>
