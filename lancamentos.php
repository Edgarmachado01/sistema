<?php
// lancamentos.php — Lançamentos (entradas / saídas avulsas e recorrentes)

// DEBUG (pode desligar depois)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$PAGE_TITLE = 'Lançamentos';

// Usa layout_start padrão
if (file_exists(__DIR__.'/layout_start.php')) {
    require __DIR__.'/layout_start.php';
} else {
    require __DIR__.'/_layout_start.php';
}

// Já temos sessão, auth, db(), tenantId() etc aqui
$pdo = db();
$tid = tenantId();

// -------------------------
// Exclusão simples (?del=ID)
// -------------------------
if (isset($_GET['del']) && ctype_digit($_GET['del'])) {
    $delId = (int) $_GET['del'];

    $stmtDel = $pdo->prepare("
        DELETE FROM lancamentos 
        WHERE id = :id AND tenant_id = :tid
    ");
    $stmtDel->execute([
        ':id'  => $delId,
        ':tid' => $tid
    ]);
}

// -------------------------
// Filtro tipo_conta (todas / avulsa / recorrente)
// -------------------------
$filtro_tipo_conta = isset($_GET['tipo_conta']) ? $_GET['tipo_conta'] : 'todas';

$whereExtra = '';
$params = [':tid' => $tid];

if ($filtro_tipo_conta === 'avulsa' || $filtro_tipo_conta === 'recorrente') {
    $whereExtra = " AND tipo_conta = :tipo_conta";
    $params[':tipo_conta'] = $filtro_tipo_conta;
}

// -------------------------
// Busca lançamentos
// -------------------------
$sql = "
    SELECT 
        id,
        tipo_mov,
        tipo_conta,
        descricao,
        valor,
        data_lancamento,
        data_vencimento,
        status,
        data_pagamento,
        valor_pago,
        forma_pagamento
    FROM lancamentos
    WHERE tenant_id = :tid
    $whereExtra
    ORDER BY data_vencimento ASC, id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lancamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------
// Helper de situação (aberto / atrasado / pago / cancelado)
// -------------------------
function hfSituacaoLancamento(array $l)
{
    $status   = $l['status'];
    $dataVenc = $l['data_vencimento'];

    if ($status === 'pago') {
        return ['Pago', 'success'];
    }
    if ($status === 'cancelado') {
        return ['Cancelado', 'secondary'];
    }

    $hoje = new DateTimeImmutable(date('Y-m-d'));
    if (!empty($dataVenc)) {
        $venc = new DateTimeImmutable($dataVenc);
        if ($venc < $hoje) {
            return ['Atrasado', 'danger'];
        }
    }

    return ['Em aberto', 'warning'];
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
      <h4 class="mb-0">Lançamentos (Entradas e Saídas)</h4>
      <a href="/lancamento_form.php?m=lanc" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i> Novo lançamento
      </a>
    </div>

    <!-- Filtro tipo de conta -->
    <ul class="nav nav-pills mb-3">
      <li class="nav-item">
        <a class="nav-link <?= $filtro_tipo_conta==='todas'?'active':'' ?>"
           href="/lancamentos.php?m=lanc&tipo_conta=todas">Todas</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $filtro_tipo_conta==='avulsa'?'active':'' ?>"
           href="/lancamentos.php?m=lanc&tipo_conta=avulsa">Avulsas</a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $filtro_tipo_conta==='recorrente'?'active':'' ?>"
           href="/lancamentos.php?m=lanc&tipo_conta=recorrente">Recorrentes</a>
      </li>
    </ul>

    <?php if (empty($lancamentos)): ?>

      <div class="alert alert-info">Nenhum lançamento encontrado.</div>

    <?php else: ?>

      <!-- ===== DESKTOP / TABLET (TABELA) ===== -->
      <div class="d-none d-md-block">
        <div class="card shadow-sm">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width:120px;">Tipo</th>
                    <th>Descrição</th>
                    <th style="width:110px;" class="text-end">Valor</th>
                    <th style="width:110px;">Venc.</th>
                    <th style="width:110px;">Lanç.</th>
                    <th style="width:110px;">Status</th>
                    <th style="width:60px;"></th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($lancamentos as $l): 
                    list($situacao, $badge) = hfSituacaoLancamento($l);
                    $isEntrada = ($l['tipo_mov'] === 'entrada');
                    $urlEdit = '/lancamento_form.php?m=lanc&id='.(int)$l['id'];
                ?>
                  <tr>
                    <td>
                      <span class="<?= $isEntrada ? 'text-success' : 'text-danger' ?>">
                        <?= ucfirst($l['tipo_mov']) ?>
                      </span>
                      <?php if ($l['tipo_conta']==='recorrente'): ?>
                        · <span title="Recorrente">♻️</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <a href="<?= $urlEdit ?>" class="text-decoration-none text-body">
                        <div class="fw-semibold"><?= htmlspecialchars($l['descricao']) ?></div>
                        <?php if (!empty($l['forma_pagamento'])): ?>
                          <small class="text-muted">
                            Forma: <?= htmlspecialchars($l['forma_pagamento']) ?>
                          </small>
                        <?php endif; ?>
                      </a>
                    </td>
                    <td class="text-end">
                      R$ <?= number_format($l['valor'], 2, ',', '.') ?>
                    </td>
                    <td><?= date('d/m/Y', strtotime($l['data_vencimento'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($l['data_lancamento'])) ?></td>
                    <td>
                      <span class="badge bg-<?= $badge ?>"><?= $situacao ?></span>
                      <?php if ($l['status']==='pago' && !empty($l['data_pagamento'])): ?>
                        <br><small class="text-muted">
                          Pago: <?= date('d/m/Y', strtotime($l['data_pagamento'])) ?>
                        </small>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <a href="/lancamentos.php?m=lanc&tipo_conta=<?= urlencode($filtro_tipo_conta) ?>&del=<?= (int)$l['id'] ?>"
                         class="btn btn-sm btn-outline-danger"
                         onclick="return confirm('Excluir este lançamento?');">
                        <i class="bi bi-trash"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- ===== MOBILE (CARDS) ===== -->
      <div class="d-block d-md-none mt-3">
        <div class="row">
          <?php foreach ($lancamentos as $l): 
            list($situacao, $badge) = hfSituacaoLancamento($l);
            $isEntrada = ($l['tipo_mov'] === 'entrada');
            $corBorda = $isEntrada ? '#28a745' : '#dc3545';
            $urlEdit = '/lancamento_form.php?m=lanc&id='.(int)$l['id'];
          ?>
            <div class="col-12 mb-3">
              <div class="card shadow-sm h-100" style="border-left:4px solid <?= $corBorda ?>;">
                <div class="card-body p-2 d-flex flex-column">

                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-muted">
                      <?= ucfirst($l['tipo_mov']) ?>
                      <?= $l['tipo_conta']==='recorrente' ? ' · ♻️' : '' ?>
                    </small>
                    <span class="badge bg-<?= $badge ?>"><?= $situacao ?></span>
                  </div>

                  <a href="<?= $urlEdit ?>" class="text-decoration-none text-body flex-grow-1">
                    <div class="fw-bold mb-1" style="font-size:1.1rem;">
                      R$ <?= number_format($l['valor'], 2, ',', '.') ?>
                    </div>

                    <small class="text-muted d-block">
                      Venc: <?= date('d/m/Y', strtotime($l['data_vencimento'])) ?>
                    </small>
                    <small class="text-muted d-block">
                      Lan: <?= date('d/m/Y', strtotime($l['data_lancamento'])) ?>
                    </small>

                    <div class="mt-2 small">
                      <?= htmlspecialchars($l['descricao']) ?>
                    </div>

                    <?php if (!empty($l['forma_pagamento'])): ?>
                      <div class="mt-1 small text-muted">
                        Forma: <?= htmlspecialchars($l['forma_pagamento']) ?>
                      </div>
                    <?php endif; ?>

                    <?php if ($l['status']==='pago' && !empty($l['data_pagamento'])): ?>
                      <div class="mt-1 small text-muted">
                        Pago em: <?= date('d/m/Y', strtotime($l['data_pagamento'])) ?>
                      </div>
                    <?php endif; ?>
                  </a>

                  <div class="mt-2 d-flex justify-content-end gap-1">
                    <a href="<?= $urlEdit ?>" class="btn btn-sm btn-outline-primary">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <a href="/lancamentos.php?m=lanc&tipo_conta=<?= urlencode($filtro_tipo_conta) ?>&del=<?= (int)$l['id'] ?>"
                       class="btn btn-sm btn-outline-danger"
                       onclick="return confirm('Excluir este lançamento?');">
                      <i class="bi bi-trash"></i>
                    </a>
                  </div>

                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    <?php endif; ?>

  </div>
</main>

<?php
// Final do layout
if (file_exists(__DIR__.'/layout_end.php')) {
    require __DIR__.'/layout_end.php';
} else {
    require __DIR__.'/_layout_end.php';
}
