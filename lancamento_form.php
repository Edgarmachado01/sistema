<?php
// lancamento_form.php — Cadastro/edição de lançamento (entrada/saída)

// DEBUG (se quiser, pode depois colocar display_errors 0 em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== BOOTSTRAP BÁSICO (sem enviar HTML ainda) =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__.'/auth.php';
requireLogin();
require_once __DIR__.'/db.php';

$pdo = db();
$tid = tenantId();

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

    <a class="nav-link <?= ($_GET['m']??'')==='cfg'?'active':'' ?>" href="/configuracoes.php?m=cfg" title="Configurações">
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
<main class="hf-content">
  <div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0"><?= $modoEdicao ? 'Editar lançamento' : 'Novo lançamento' ?></h4>
      <a href="/lancamentos.php?m=lanc" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Voltar
      </a>
    </div>

    <?php if (!empty($erros)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($erros as $e): ?>
            <li><?= htmlspecialchars((string)$e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" class="card shadow-sm">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <div class="card-body">

        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Tipo de movimento</label>
            <select name="tipo_mov" class="form-control" required>
              <option value="entrada" <?= $tipo_mov==='entrada'?'selected':'' ?>>Entrada</option>
              <option value="saida"   <?= $tipo_mov==='saida'?'selected':'' ?>>Saída</option>
            </select>
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">Tipo de conta</label>
            <select name="tipo_conta" class="form-control" required>
              <option value="avulsa"     <?= $tipo_conta==='avulsa'?'selected':'' ?>>Avulsa</option>
              <option value="recorrente" <?= $tipo_conta==='recorrente'?'selected':'' ?>>Recorrente</option>
            </select>
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">Data lançamento</label>
            <input type="date" name="data_lancamento" class="form-control"
                   value="<?= htmlspecialchars((string)$data_lancamento) ?>" required>
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">Data vencimento</label>
            <input type="date" name="data_vencimento" class="form-control"
                   value="<?= htmlspecialchars((string)$data_vencimento) ?>" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Descrição</label>
          <input type="text" name="descricao" class="form-control"
                 value="<?= htmlspecialchars((string)$descricao) ?>" required>
        </div>

        <div class="row">
          <div class="col-md-3 mb-3">
            <label class="form-label">Valor (R$)</label>
            <input type="text" name="valor" class="form-control"
                   value="<?= htmlspecialchars((string)$valor) ?>" required>
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-control" required>
              <option value="aberto"    <?= $status==='aberto'?'selected':'' ?>>Em aberto</option>
              <option value="pago"      <?= $status==='pago'?'selected':'' ?>>Pago</option>
              <option value="cancelado" <?= $status==='cancelado'?'selected':'' ?>>Cancelado</option>
            </select>
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">Data pagamento</label>
            <input type="date" name="data_pagamento" class="form-control"
                   value="<?= htmlspecialchars((string)$data_pagamento) ?>">
          </div>

          <div class="col-md-3 mb-3">
            <label class="form-label">Valor pago (R$)</label>
            <input type="text" name="valor_pago" class="form-control"
                   value="<?= htmlspecialchars((string)$valor_pago) ?>">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Forma de pagamento</label>
            <input type="text" name="forma_pagamento" class="form-control"
                   value="<?= htmlspecialchars((string)$forma_pagamento) ?>">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Observação</label>
          <textarea name="observacao" rows="3" class="form-control"><?= htmlspecialchars((string)$observacao) ?></textarea>
        </div>

      </div>
      <div class="card-footer text-end">
        <button type="submit" class="btn btn-success">
          <i class="bi bi-check-lg me-1"></i> Salvar
        </button>
      </div>
    </form>

  </div>
</main>

<?php
if (file_exists(__DIR__.'/layout_end.php')) {
    require __DIR__.'/layout_end.php';
} else {
    require __DIR__.'/_layout_end.php';
}
