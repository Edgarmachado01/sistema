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

// Token CSRF para ações POST
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// -------------------------
// Exclusão via POST + CSRF
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir_lancamento') {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postToken    = $_POST['csrf_token'] ?? '';

    if ($sessionToken !== '' && $postToken !== '' && hash_equals($sessionToken, $postToken)) {
        $delId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($delId) {
            $stmtDel = $pdo->prepare("
                DELETE FROM lancamentos 
                WHERE id = :id AND tenant_id = :tid
            ");
            $stmtDel->execute([
                ':id'  => $delId,
                ':tid' => $tid
            ]);
        }
    }
}

// -------------------------
// Filtro tipo_conta (todas / avulsa / recorrente)
// -------------------------
$filtro_tipo_conta = isset($_GET['tipo_conta']) ? $_GET['tipo_conta'] : 'todas';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo_conta'])) {
    $filtro_tipo_conta = $_POST['tipo_conta'];
}

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
<main class="hf-content hf-lanc-page">
  <div class="container-fluid py-4 hf-lanc-wrap">

    <div class="hf-lanc-hero d-flex justify-content-between align-items-center mb-3">
      <div>
        <div class="hf-page-kicker">Fluxo financeiro</div>
        <h4 class="mb-0">Lançamentos</h4>
        <div class="hf-page-subtitle">Entradas, saídas avulsas e recorrentes</div>
      </div>

      <a href="/lancamento_form.php?m=lanc" class="btn btn-primary btn-sm hf-btn-new-lanc">
        <i class="bi bi-plus-lg me-1"></i> Novo lançamento
      </a>
    </div>

    <!-- Filtro tipo de conta -->
    <ul class="nav nav-pills mb-3 hf-lanc-tabs">
      <li class="nav-item">
        <a class="nav-link <?= $filtro_tipo_conta==='todas'?'active':'' ?>"
           href="/lancamentos.php?m=lanc&tipo_conta=todas">
          <i class="bi bi-grid-3x3-gap me-1"></i>Todas
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $filtro_tipo_conta==='avulsa'?'active':'' ?>"
           href="/lancamentos.php?m=lanc&tipo_conta=avulsa">
          <i class="bi bi-receipt me-1"></i>Avulsas
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= $filtro_tipo_conta==='recorrente'?'active':'' ?>"
           href="/lancamentos.php?m=lanc&tipo_conta=recorrente">
          <i class="bi bi-arrow-repeat me-1"></i>Recorrentes
        </a>
      </li>
    </ul>

    <?php if (empty($lancamentos)): ?>

      <div class="alert alert-info hf-empty-state">
        <i class="bi bi-info-circle me-2"></i>Nenhum lançamento encontrado.
      </div>

    <?php else: ?>

      <!-- ===== DESKTOP / TABLET (TABELA) ===== -->
      <div class="d-none d-md-block">
        <div class="card shadow-sm hf-lanc-table-card">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0 hf-lanc-table">
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
                  <tr class="hf-lanc-row <?= $isEntrada ? 'is-entrada' : 'is-saida' ?>">
                    <td>
                      <span class="hf-type-pill <?= $isEntrada ? 'is-entrada' : 'is-saida' ?>">
                        <i class="bi <?= $isEntrada ? 'bi-arrow-down-left' : 'bi-arrow-up-right' ?>"></i>
                        <?= ucfirst($l['tipo_mov']) ?>
                      </span>
                      <?php if ($l['tipo_conta']==='recorrente'): ?>
                        <span class="hf-recurring-pill" title="Recorrente">
                          <i class="bi bi-arrow-repeat"></i>
                        </span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <a href="<?= $urlEdit ?>" class="text-decoration-none text-body hf-lanc-desc-link">
                        <div class="fw-semibold hf-lanc-desc"><?= htmlspecialchars($l['descricao']) ?></div>
                        <div class="hf-lanc-meta">
                          <span><?= ucfirst($l['tipo_conta']) ?></span>
                          <?php if (!empty($l['forma_pagamento'])): ?>
                            <span>Forma: <?= htmlspecialchars($l['forma_pagamento']) ?></span>
                          <?php endif; ?>
                        </div>
                      </a>
                    </td>
                    <td class="text-end">
                      <span class="hf-lanc-value <?= $isEntrada ? 'is-entrada' : 'is-saida' ?>">
                        R$ <?= number_format($l['valor'], 2, ',', '.') ?>
                      </span>
                    </td>
                    <td>
                      <span class="hf-date-main"><?= date('d/m/Y', strtotime($l['data_vencimento'])) ?></span>
                    </td>
                    <td>
                      <span class="hf-date-main"><?= date('d/m/Y', strtotime($l['data_lancamento'])) ?></span>
                    </td>
                    <td>
                      <span class="badge bg-<?= $badge ?> hf-status-badge"><?= $situacao ?></span>
                      <?php if ($l['status']==='pago' && !empty($l['data_pagamento'])): ?>
                        <br><small class="text-muted">
                          Pago: <?= date('d/m/Y', strtotime($l['data_pagamento'])) ?>
                        </small>
                      <?php endif; ?>
                    </td>
                    <td class="text-end">
                      <form method="post" action="/lancamentos.php?m=lanc" class="d-inline m-0 p-0" onsubmit="return confirm('Excluir este lançamento?');">
                        <input type="hidden" name="acao" value="excluir_lancamento">
                        <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                        <input type="hidden" name="tipo_conta" value="<?= htmlspecialchars($filtro_tipo_conta, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger hf-delete-btn" title="Excluir">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
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
      <div class="d-block d-md-none mt-3 hf-lanc-mobile-list">
        <div class="row">
          <?php foreach ($lancamentos as $l): 
            list($situacao, $badge) = hfSituacaoLancamento($l);
            $isEntrada = ($l['tipo_mov'] === 'entrada');
            $corBorda = $isEntrada ? '#16a34a' : '#dc2626';
            $urlEdit = '/lancamento_form.php?m=lanc&id='.(int)$l['id'];
          ?>
            <div class="col-12 mb-3">
              <div class="card shadow-sm h-100 hf-lanc-mobile-card <?= $isEntrada ? 'is-entrada' : 'is-saida' ?>" style="border-left:4px solid <?= $corBorda ?>;">
                <div class="card-body p-3 d-flex flex-column">

                  <div class="d-flex justify-content-between align-items-center mb-2 gap-2">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                      <span class="hf-type-pill <?= $isEntrada ? 'is-entrada' : 'is-saida' ?>">
                        <i class="bi <?= $isEntrada ? 'bi-arrow-down-left' : 'bi-arrow-up-right' ?>"></i>
                        <?= ucfirst($l['tipo_mov']) ?>
                      </span>
                      <?php if ($l['tipo_conta']==='recorrente'): ?>
                        <span class="hf-recurring-pill" title="Recorrente">
                          <i class="bi bi-arrow-repeat"></i>
                        </span>
                      <?php endif; ?>
                    </div>
                    <span class="badge bg-<?= $badge ?> hf-status-badge"><?= $situacao ?></span>
                  </div>

                  <a href="<?= $urlEdit ?>" class="text-decoration-none text-body flex-grow-1">
                    <div class="hf-lanc-mobile-value <?= $isEntrada ? 'is-entrada' : 'is-saida' ?>">
                      R$ <?= number_format($l['valor'], 2, ',', '.') ?>
                    </div>

                    <div class="hf-lanc-mobile-desc">
                      <?= htmlspecialchars($l['descricao']) ?>
                    </div>

                    <div class="hf-mobile-info-grid">
                      <div>
                        <span>Vencimento</span>
                        <strong><?= date('d/m/Y', strtotime($l['data_vencimento'])) ?></strong>
                      </div>
                      <div>
                        <span>Lançamento</span>
                        <strong><?= date('d/m/Y', strtotime($l['data_lancamento'])) ?></strong>
                      </div>
                    </div>

                    <?php if (!empty($l['forma_pagamento'])): ?>
                      <div class="mt-2 small text-muted">
                        Forma: <?= htmlspecialchars($l['forma_pagamento']) ?>
                      </div>
                    <?php endif; ?>

                    <?php if ($l['status']==='pago' && !empty($l['data_pagamento'])): ?>
                      <div class="mt-1 small text-muted">
                        Pago em: <?= date('d/m/Y', strtotime($l['data_pagamento'])) ?>
                      </div>
                    <?php endif; ?>
                  </a>

                  <div class="mt-3 d-flex justify-content-end gap-2">
                    <a href="<?= $urlEdit ?>" class="btn btn-sm btn-outline-primary hf-edit-btn" title="Editar">
                      <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="/lancamentos.php?m=lanc" class="d-inline m-0 p-0" onsubmit="return confirm('Excluir este lançamento?');">
                      <input type="hidden" name="acao" value="excluir_lancamento">
                      <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                      <input type="hidden" name="tipo_conta" value="<?= htmlspecialchars($filtro_tipo_conta, ENT_QUOTES, 'UTF-8') ?>">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger hf-delete-btn" title="Excluir">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
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

<style>
.hf-lanc-page {
  min-height: calc(100vh - var(--topbar-h));
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .10), transparent 28rem),
    linear-gradient(180deg, #f7f9fc 0%, #eef3f8 100%);
}

.hf-lanc-wrap {
  max-width: 1480px;
}

.hf-lanc-hero {
  gap: 1rem;
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

.hf-btn-new-lanc {
  border-radius: .65rem;
  font-weight: 700;
  padding: .48rem .78rem;
  white-space: nowrap;
  box-shadow: 0 8px 18px rgba(var(--bs-primary-rgb), .16);
}

.hf-lanc-tabs {
  display: inline-flex;
  gap: .35rem;
  padding: .35rem;
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: .9rem;
  background: rgba(255, 255, 255, .88);
  box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
}

.hf-lanc-tabs .nav-link {
  border-radius: .65rem;
  color: #64748b;
  font-weight: 700;
  padding: .48rem .75rem;
}

.hf-lanc-tabs .nav-link:hover {
  color: var(--bs-primary);
  background: rgba(var(--bs-primary-rgb), .07);
}

.hf-lanc-tabs .nav-link.active {
  color: #fff;
  background: var(--bs-primary);
  box-shadow: 0 8px 18px rgba(var(--bs-primary-rgb), .20);
}

.hf-lanc-table-card,
.hf-lanc-mobile-card,
.hf-empty-state {
  border: 1px solid rgba(148, 163, 184, .24);
  border-radius: 1rem;
  background: rgba(255, 255, 255, .94);
  box-shadow: 0 14px 36px rgba(15, 23, 42, .08);
}

.hf-lanc-table-card {
  overflow: hidden;
}

.hf-lanc-table {
  --bs-table-bg: transparent;
}

.hf-lanc-table thead th {
  padding: .95rem .9rem;
  border-bottom: 1px solid rgba(148, 163, 184, .28);
  background: #f1f5f9;
  color: #475569;
  font-size: .74rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: .055em;
  white-space: nowrap;
}

.hf-lanc-table tbody td {
  padding: .9rem;
  border-color: rgba(226, 232, 240, .82);
  color: #334155;
}

.hf-lanc-row {
  transition: background-color .14s ease, box-shadow .14s ease;
}

.hf-lanc-row:hover {
  background: rgba(var(--bs-primary-rgb), .04);
}

.hf-lanc-row.is-entrada:hover {
  box-shadow: inset 3px 0 0 #16a34a;
}

.hf-lanc-row.is-saida:hover {
  box-shadow: inset 3px 0 0 #dc2626;
}

.hf-type-pill,
.hf-recurring-pill {
  display: inline-flex;
  align-items: center;
  gap: .32rem;
  min-height: 28px;
  border-radius: 999px;
  padding: .28rem .58rem;
  font-size: .78rem;
  font-weight: 800;
  white-space: nowrap;
}

.hf-type-pill.is-entrada {
  color: #047857;
  background: #d1fae5;
}

.hf-type-pill.is-saida {
  color: #b91c1c;
  background: #fee2e2;
}

.hf-recurring-pill {
  color: #075985;
  background: #e0f2fe;
  padding: .28rem .5rem;
  margin-left: .25rem;
}

.hf-lanc-desc {
  color: #0f172a;
}

.hf-lanc-desc-link:hover .hf-lanc-desc {
  color: var(--bs-primary);
}

.hf-lanc-meta {
  display: flex;
  flex-wrap: wrap;
  gap: .45rem;
  margin-top: .22rem;
  color: #64748b;
  font-size: .8rem;
}

.hf-lanc-meta span {
  display: inline-flex;
  align-items: center;
}

.hf-lanc-meta span + span::before {
  content: "";
  width: 4px;
  height: 4px;
  margin-right: .45rem;
  border-radius: 999px;
  background: #cbd5e1;
}

.hf-lanc-value {
  display: inline-flex;
  justify-content: flex-end;
  font-weight: 900;
  white-space: nowrap;
}

.hf-lanc-value.is-entrada,
.hf-lanc-mobile-value.is-entrada {
  color: #047857;
}

.hf-lanc-value.is-saida,
.hf-lanc-mobile-value.is-saida {
  color: #b91c1c;
}

.hf-date-main {
  color: #475569;
  font-weight: 650;
  white-space: nowrap;
}

.hf-status-badge {
  border-radius: 999px;
  padding: .42rem .62rem;
  font-weight: 800;
}

.hf-status-badge.bg-warning {
  color: #8a4b00 !important;
  background: #fff3cd !important;
}

.hf-status-badge.bg-danger {
  color: #b91c1c !important;
  background: #fee2e2 !important;
}

.hf-status-badge.bg-success {
  color: #047857 !important;
  background: #d1fae5 !important;
}

.hf-status-badge.bg-secondary {
  color: #475569 !important;
  background: #e2e8f0 !important;
}

.hf-delete-btn,
.hf-edit-btn {
  width: 34px;
  height: 34px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: .65rem;
  font-weight: 700;
}

.hf-delete-btn {
  background: #fff5f5;
  border-color: #fecaca;
  color: #dc2626;
}

.hf-delete-btn:hover {
  color: #fff;
  background: #dc2626;
  border-color: #dc2626;
}

.hf-edit-btn {
  background: rgba(var(--bs-primary-rgb), .04);
  border-color: rgba(var(--bs-primary-rgb), .34);
}

.hf-edit-btn:hover {
  color: #fff;
  background: var(--bs-primary);
  border-color: var(--bs-primary);
}

.hf-lanc-mobile-list .row {
  margin-left: 0;
  margin-right: 0;
}

.hf-lanc-mobile-card {
  border-top: 0;
  border-right: 0;
  border-bottom: 0;
  transition: transform .16s ease, box-shadow .16s ease;
}

.hf-lanc-mobile-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 18px 36px rgba(15, 23, 42, .10) !important;
}

.hf-lanc-mobile-value {
  margin-bottom: .45rem;
  font-size: 1.28rem;
  font-weight: 900;
  line-height: 1.15;
}

.hf-lanc-mobile-desc {
  color: #0f172a;
  font-weight: 700;
  margin-bottom: .7rem;
}

.hf-mobile-info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: .65rem;
  padding-top: .7rem;
  border-top: 1px solid rgba(226, 232, 240, .9);
}

.hf-mobile-info-grid span {
  display: block;
  color: #64748b;
  font-size: .74rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
}

.hf-mobile-info-grid strong {
  display: block;
  color: #334155;
  font-size: .92rem;
  margin-top: .12rem;
}

@media (max-width: 767.98px) {
  .hf-lanc-page {
    padding-left: .25rem;
    padding-right: .25rem;
  }

  .hf-lanc-hero {
    align-items: flex-start !important;
  }

  .hf-btn-new-lanc {
    padding: .44rem .62rem;
  }

  .hf-lanc-tabs {
    display: flex;
    width: 100%;
    overflow-x: auto;
  }

  .hf-lanc-tabs .nav-item {
    flex: 1 0 auto;
  }

  .hf-lanc-tabs .nav-link {
    text-align: center;
    white-space: nowrap;
  }
}

[data-bs-theme="dark"] .hf-lanc-page {
  background:
    radial-gradient(circle at 18% 0%, rgba(var(--bs-primary-rgb), .16), transparent 28rem),
    linear-gradient(180deg, #111827 0%, #0f172a 100%);
}

[data-bs-theme="dark"] .hf-lanc-tabs,
[data-bs-theme="dark"] .hf-lanc-table-card,
[data-bs-theme="dark"] .hf-lanc-mobile-card,
[data-bs-theme="dark"] .hf-empty-state {
  background: rgba(17, 24, 39, .9);
  border-color: rgba(148, 163, 184, .18);
}

[data-bs-theme="dark"] .hf-lanc-table thead th {
  background: rgba(30, 41, 59, .95);
  color: #cbd5e1;
}

[data-bs-theme="dark"] .hf-lanc-table tbody td,
[data-bs-theme="dark"] .hf-date-main,
[data-bs-theme="dark"] .hf-mobile-info-grid strong {
  color: #cbd5e1;
  border-color: rgba(51, 65, 85, .9);
}

[data-bs-theme="dark"] .hf-lanc-desc,
[data-bs-theme="dark"] .hf-lanc-mobile-desc {
  color: #e5e7eb;
}
</style>

<?php
// Final do layout
if (file_exists(__DIR__.'/layout_end.php')) {
    require __DIR__.'/layout_end.php';
} else {
    require __DIR__.'/_layout_end.php';
}
