<?php
// lancamento_form.php — Cadastro/edição de lançamento (entrada/saída)

// ===== BOOTSTRAP BÁSICO (sem enviar HTML ainda) =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

$pdo = db();
$tid = tenantId();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['csrf_token'];

$id = isset($_GET['id']) && ctype_digit($_GET['id']) ? (int)$_GET['id'] : 0;
$modoEdicao = $id > 0;

// -------------------------
// Variáveis padrão
// -------------------------
$tipo_mov        = 'saida';
$tipo_conta      = 'avulsa';
$descricao       = '';
$valor           = '';
$data_lancamento = date('Y-m-d');
$data_vencimento = date('Y-m-d');
$status          = 'aberto';
$data_pagamento  = '';
$valor_pago      = '';
$forma_pagamento = '';
$observacao      = '';

$erros = [];

// -------------------------
// Se edição: carregar dados do banco
// -------------------------
if ($modoEdicao && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare("
        SELECT *
        FROM lancamentos
        WHERE id = :id AND tenant_id = :tid
        LIMIT 1
    ");
    $stmt->execute([
        ':id'  => $id,
        ':tid' => $tid,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $tipo_mov        = $row['tipo_mov'];
        $tipo_conta      = $row['tipo_conta'];
        $descricao       = $row['descricao'] ?? '';
        $valor           = number_format($row['valor'], 2, ',', '.');
        $data_lancamento = $row['data_lancamento'];
        $data_vencimento = $row['data_vencimento'];
        $status          = $row['status'];
        $data_pagamento  = $row['data_pagamento'] ?? '';
        $valor_pago      = $row['valor_pago'] !== null ? number_format($row['valor_pago'], 2, ',', '.') : '';
        $forma_pagamento = $row['forma_pagamento'] ?? '';
        $observacao      = $row['observacao'] ?? '';
    } else {
        // não achou -> volta pra lista
        header('Location: /lancamentos.php?m=lanc');
        exit;
    }
}

// -------------------------
// POST (salvar) – ANTES DE QUALQUER HTML
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    $postToken = (string)($_POST['csrf_token'] ?? '');
    if ($sessionToken === '' || $postToken === '' || !hash_equals($sessionToken, $postToken)) {
        $erros[] = 'Sessao expirada. Recarregue a pagina e tente novamente.';
    }

    $idPost         = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int)$_POST['id'] : 0;
    $modoEdicao     = $idPost > 0;
    $id             = $idPost;

    $tipo_mov        = $_POST['tipo_mov']        ?? 'saida';
    $tipo_conta      = $_POST['tipo_conta']      ?? 'avulsa';
    $descricao       = trim($_POST['descricao']  ?? '');
    $valor           = str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0'));
    $data_lancamento = $_POST['data_lancamento'] ?? date('Y-m-d');
    $data_vencimento = $_POST['data_vencimento'] ?? date('Y-m-d');
    $status          = $_POST['status']          ?? 'aberto';
    $data_pagamento  = $_POST['data_pagamento']  ?? '';
    $valor_pago      = str_replace(',', '.', str_replace('.', '', $_POST['valor_pago'] ?? ''));
    $forma_pagamento = trim($_POST['forma_pagamento'] ?? '');
    $observacao      = trim($_POST['observacao'] ?? '');

    // Validações simples
    if ($descricao === '') {
        $erros[] = 'Descrição é obrigatória.';
    }
    if (!is_numeric($valor) || $valor <= 0) {
        $erros[] = 'Valor inválido.';
    }
    if ($data_lancamento === '') {
        $erros[] = 'Data de lançamento é obrigatória.';
    }
    if ($data_vencimento === '') {
        $erros[] = 'Data de vencimento é obrigatória.';
    }

    // Se status pago e não informou data_pagamento, usa hoje
    if ($status === 'pago' && $data_pagamento === '') {
        $data_pagamento = date('Y-m-d');
    }

    if (empty($erros)) {

        if ($modoEdicao) {
            // UPDATE
            $sql = "
                UPDATE lancamentos SET
                    tipo_mov        = :tipo_mov,
                    tipo_conta      = :tipo_conta,
                    descricao       = :descricao,
                    valor           = :valor,
                    data_lancamento = :data_lancamento,
                    data_vencimento = :data_vencimento,
                    status          = :status,
                    data_pagamento  = :data_pagamento,
                    valor_pago      = :valor_pago,
                    forma_pagamento = :forma_pagamento,
                    observacao      = :observacao
                WHERE id = :id AND tenant_id = :tenant_id
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':tipo_mov'        => $tipo_mov,
                ':tipo_conta'      => $tipo_conta,
                ':descricao'       => $descricao,
                ':valor'           => $valor,
                ':data_lancamento' => $data_lancamento,
                ':data_vencimento' => $data_vencimento,
                ':status'          => $status,
                ':data_pagamento'  => $data_pagamento ?: null,
                ':valor_pago'      => ($valor_pago !== '' ? $valor_pago : null),
                ':forma_pagamento' => ($forma_pagamento !== '' ? $forma_pagamento : null),
                ':observacao'      => ($observacao !== '' ? $observacao : null),
                ':id'              => $id,
                ':tenant_id'       => $tid,
            ]);

        } else {
            // INSERT
            $sql = "
                INSERT INTO lancamentos (
                    tenant_id,
                    tipo_mov,
                    tipo_conta,
                    descricao,
                    valor,
                    data_lancamento,
                    data_vencimento,
                    status,
                    data_pagamento,
                    valor_pago,
                    forma_pagamento,
                    observacao
                ) VALUES (
                    :tenant_id,
                    :tipo_mov,
                    :tipo_conta,
                    :descricao,
                    :valor,
                    :data_lancamento,
                    :data_vencimento,
                    :status,
                    :data_pagamento,
                    :valor_pago,
                    :forma_pagamento,
                    :observacao
                )
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':tenant_id'       => $tid,
                ':tipo_mov'        => $tipo_mov,
                ':tipo_conta'      => $tipo_conta,
                ':descricao'       => $descricao,
                ':valor'           => $valor,
                ':data_lancamento' => $data_lancamento,
                ':data_vencimento' => $data_vencimento,
                ':status'          => $status,
                ':data_pagamento'  => $data_pagamento ?: null,
                ':valor_pago'      => ($valor_pago !== '' ? $valor_pago : null),
                ':forma_pagamento' => ($forma_pagamento !== '' ? $forma_pagamento : null),
                ':observacao'      => ($observacao !== '' ? $observacao : null),
            ]);
        }

        header('Location: /lancamentos.php?m=lanc');
        exit;
    }
}

// ===================================================================
// DAQUI PRA BAIXO COMEÇA O HTML (layout_start + sidebar + formulário)
// ===================================================================

$PAGE_TITLE = $modoEdicao ? 'Editar Lançamento' : 'Novo Lançamento';

// Usa o mesmo layout base
if (file_exists(__DIR__.'/layout_start.php')) {
    require __DIR__.'/layout_start.php';
} else {
    require __DIR__.'/_layout_start.php';
}
?>

<!-- SIDEBAR -->
<aside id="hf-sidebar" class="hf-sidebar p-2">
  <nav class="nav flex-column">
    <div class="section">Principal</div>

    <a class="nav-link <?= ($_GET['m']??'')==='dash'?'active':'' ?>" href="/dashboard.php?m=dash" title="Dashboard">
      <div class="hf-ico"><i class="bi bi-speedometer2"></i></div><span>Dashboard</span>
    </a>

    <a class="nav-link <?= ($_GET['m']??'')==='os'?'active':'' ?>" href="/os_list.php?m=os" title="Ordens de Serviço">
      <div class="hf-ico"><i class="bi bi-clipboard2-check"></i></div><span>Ordens de Serviço</span>
    </a>

    <a class="nav-link <?= ($_GET['m']??'')==='clientes'?'active':'' ?>" href="/clientes.php?m=clientes" title="Clientes">
      <div class="hf-ico"><i class="bi bi-people"></i></div><span>Clientes</span>
    </a>

    <div class="section">Cadastros</div>

    <a class="nav-link <?= ($_GET['m']??'')==='produtos'?'active':'' ?>" href="/produtos.php?m=produtos" title="Produtos">
      <div class="hf-ico"><i class="bi bi-box-seam"></i></div><span>Produtos</span>
    </a>

    <a class="nav-link <?= ($_GET['m']??'')==='servicos'?'active':'' ?>" href="/servicos.php?m=servicos" title="Serviços">
      <div class="hf-ico"><i class="bi bi-tools"></i></div><span>Serviços</span>
    </a>

    <div class="section">Gestão</div>

    <a class="nav-link <?= ($_GET['m']??'')==='fin'?'active':'' ?>" href="/financeiro_os_lista.php?m=fin" title="Financeiro">
      <div class="hf-ico"><i class="bi bi-cash-coin"></i></div><span>Financeiro</span>
    </a>

    <a class="nav-link <?= ($_GET['m']??'')==='lanc'?'active':'' ?>" href="/lancamentos.php?m=lanc" title="Lançamentos">
      <div class="hf-ico"><i class="bi bi-journal-text"></i></div><span>Lançamentos</span>
    </a>

    <a class="nav-link <?= ($_GET['m']??'')==='hf'?'active':'' ?>" href="/config_empresa.php?m=hf" title="Configurações">
      <div class="hf-ico"><i class="bi bi-gear"></i></div><span>Configurações</span>
    </a>

    <div class="section">Conta</div>

    <a class="nav-link" href="/change_password.php" title="Trocar senha">
      <div class="hf-ico"><i class="bi bi-key"></i></div><span>Trocar senha</span>
    </a>

    <a class="nav-link" href="/admin_reset_password.php" title="Reset de senha">
      <div class="hf-ico"><i class="bi bi-shield-lock"></i></div><span>Reset de senha</span>
    </a>
  </nav>
</aside>

<!-- CONTEÚDO -->
<main class="hf-content hf-lanc-form-page">
  <div class="container-fluid py-4 hf-lanc-form-wrap">

    <div class="hf-lanc-form-top mb-3">
      <div class="hf-lanc-form-title">
        <div class="hf-page-kicker">Financeiro</div>
        <h4 class="mb-0"><?= $modoEdicao ? 'Editar lançamento' : 'Novo lançamento' ?></h4>
        <div class="hf-page-subtitle">Organize tipo, valores, datas, pagamento e observações do lançamento.</div>
      </div>

      <div class="hf-lanc-form-actions">
        <a href="/lancamentos.php?m=lanc" class="btn btn-outline-secondary btn-sm hf-top-action-btn">
          <i class="bi bi-arrow-left me-1"></i> Voltar
        </a>
      </div>
    </div>

    <?php if (!empty($erros)): ?>
      <div class="alert alert-danger hf-lanc-alert">
        <div class="fw-semibold mb-1">
          <i class="bi bi-exclamation-triangle me-2"></i>Revise os campos abaixo
        </div>
        <ul class="mb-0">
          <?php foreach ($erros as $e): ?>
            <li><?= htmlspecialchars((string)$e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="hf-lanc-form-shell">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="id" value="<?= (int)$id ?>">

      <div class="card mb-3 hf-form-section">
        <div class="card-header hf-section-header">
          <div class="hf-section-icon"><i class="bi bi-journal-text"></i></div>
          <div>
            <strong>Dados do lançamento</strong>
            <span>Tipo de movimento, tipo de conta e descrição principal.</span>
          </div>
        </div>
        <div class="card-body hf-section-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Tipo de movimento</label>
              <select name="tipo_mov" class="form-control" required>
                <option value="entrada" <?= $tipo_mov==='entrada'?'selected':'' ?>>Entrada</option>
                <option value="saida"   <?= $tipo_mov==='saida'?'selected':'' ?>>Saída</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Tipo de conta</label>
              <select name="tipo_conta" class="form-control" required>
                <option value="avulsa"     <?= $tipo_conta==='avulsa'?'selected':'' ?>>Avulsa</option>
                <option value="recorrente" <?= $tipo_conta==='recorrente'?'selected':'' ?>>Recorrente</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Descrição</label>
              <input type="text" name="descricao" class="form-control"
                     value="<?= htmlspecialchars((string)$descricao) ?>" required>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3 hf-form-section">
        <div class="card-header hf-section-header">
          <div class="hf-section-icon"><i class="bi bi-cash-coin"></i></div>
          <div>
            <strong>Financeiro</strong>
            <span>Valor, status, pagamento e forma de recebimento ou despesa.</span>
          </div>
        </div>
        <div class="card-body hf-section-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Valor (R$)</label>
              <input type="text" name="valor" class="form-control hf-money-input"
                     value="<?= htmlspecialchars((string)$valor) ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select name="status" class="form-control" required>
                <option value="aberto"    <?= $status==='aberto'?'selected':'' ?>>Em aberto</option>
                <option value="pago"      <?= $status==='pago'?'selected':'' ?>>Pago</option>
                <option value="cancelado" <?= $status==='cancelado'?'selected':'' ?>>Cancelado</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label">Valor pago (R$)</label>
              <input type="text" name="valor_pago" class="form-control"
                     value="<?= htmlspecialchars((string)$valor_pago) ?>">
            </div>

            <div class="col-md-3">
              <label class="form-label">Forma de pagamento</label>
              <input type="text" name="forma_pagamento" class="form-control"
                     value="<?= htmlspecialchars((string)$forma_pagamento) ?>">
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-3 hf-form-section">
        <div class="card-header hf-section-header">
          <div class="hf-section-icon"><i class="bi bi-calendar3"></i></div>
          <div>
            <strong>Informações adicionais</strong>
            <span>Datas de lançamento, vencimento, pagamento e observação.</span>
          </div>
        </div>
        <div class="card-body hf-section-body">
          <div class="row g-3">
            <div class="col-md-3">
              <label class="form-label">Data lançamento</label>
              <input type="date" name="data_lancamento" class="form-control"
                     value="<?= htmlspecialchars((string)$data_lancamento) ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Data vencimento</label>
              <input type="date" name="data_vencimento" class="form-control"
                     value="<?= htmlspecialchars((string)$data_vencimento) ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Data pagamento</label>
              <input type="date" name="data_pagamento" class="form-control"
                     value="<?= htmlspecialchars((string)$data_pagamento) ?>">
            </div>

            <div class="col-12">
              <label class="form-label">Observação</label>
              <textarea name="observacao" rows="3" class="form-control"><?= htmlspecialchars((string)$observacao) ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="hf-form-actions">
        <a class="btn btn-outline-secondary hf-btn-cancel" href="/lancamentos.php?m=lanc">
          <i class="bi bi-x-lg me-1"></i>Cancelar
        </a>
        <button type="submit" class="btn btn-success hf-btn-save">
          <i class="bi bi-check-lg me-1"></i> Salvar
        </button>
      </div>
    </form>

  </div>
</main>

<style>
.hf-lanc-form-page {
  min-height: calc(100vh - var(--topbar-h));
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .10), transparent 28rem),
    linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
}

.hf-lanc-form-wrap {
  max-width: 1480px;
}

.hf-lanc-form-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
}

.hf-lanc-form-title {
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

.hf-lanc-form-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: .5rem;
}

.hf-lanc-alert {
  border: 1px solid rgba(248, 113, 113, .26);
  border-radius: .95rem;
  background: #fef2f2;
  color: #991b1b;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
}

.hf-lanc-form-shell {
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

.hf-money-input {
  color: #047857;
  font-weight: 900;
}

.hf-top-action-btn,
.hf-btn-save,
.hf-btn-cancel {
  min-height: 36px;
  border-radius: .72rem;
  font-weight: 800;
}

.hf-btn-save {
  box-shadow: 0 8px 18px rgba(22, 163, 74, .16);
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
  .hf-lanc-form-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .hf-lanc-form-top {
    flex-direction: column;
  }

  .hf-lanc-form-actions {
    width: 100%;
    justify-content: stretch;
  }

  .hf-lanc-form-actions .btn {
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

[data-bs-theme="dark"] .hf-lanc-form-page {
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

<?php
if (file_exists(__DIR__.'/layout_end.php')) {
    require __DIR__.'/layout_end.php';
} else {
    require __DIR__.'/_layout_end.php';
}
