<?php
// os_form.php — Formulário de OS com fotos (upload normal + async), galeria em modal e itens mobile-friendly

// === DEBUG (remova em produção) ===
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === LAYOUT / AUTH ===
require_once __DIR__.'/_layout_start.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/auth.php';

$pdo = db();
$tid = tenantId();
if (!$tid) die('Tenant inválido.');

$id = (int)($_GET['id'] ?? 0);

// ---------- CLIENTES (com fallback p/ coluna default_garantia_dias) ----------
try {
  $st = $pdo->prepare("
    SELECT id, nome, default_garantia_dias
    FROM hf_clientes
    WHERE tenant_id=:t AND deleted_at IS NULL
    ORDER BY nome
  ");
  $st->execute([':t'=>$tid]);
  $clientes = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $st = $pdo->prepare("
    SELECT id, nome, 0 AS default_garantia_dias
    FROM hf_clientes
    WHERE tenant_id=:t AND deleted_at IS NULL
    ORDER BY nome
  ");
  $st->execute([':t'=>$tid]);
  $clientes = $st->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- PRODUTOS ----------
try {
  $st = $pdo->prepare("
    SELECT id, nome, preco
    FROM hf_produtos
    WHERE tenant_id=:t AND deleted_at IS NULL AND status=1
    ORDER BY nome
  ");
  $st->execute([':t'=>$tid]);
  $produtos = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $produtos = []; }

// ---------- SERVIÇOS ----------
try {
  $st = $pdo->prepare("
    SELECT id, nome, preco
    FROM hf_servicos
    WHERE tenant_id=:t AND deleted_at IS NULL AND status=1
    ORDER BY nome
  ");
  $st->execute([':t'=>$tid]);
  $servicos = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $servicos = []; }

// ---------- OS existente + itens ----------
$os = null; $itens = [];
if ($id>0){
  $st = $pdo->prepare("SELECT * FROM hf_os WHERE id=:id AND tenant_id=:t AND deleted_at IS NULL");
  $st->execute([':id'=>$id, ':t'=>$tid]);
  $os = $st->fetch(PDO::FETCH_ASSOC);

  $st = $pdo->prepare("SELECT * FROM hf_os_itens WHERE os_id=:id ORDER BY id");
  $st->execute([':id'=>$id]);
  $itens = $st->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- FOTOS EXISTENTES ----------
$fotos = [];
if ($id>0){
  try{
    $sqlFotos = "SELECT id, caminho, thumb, original_nome, created_at
                 FROM hf_os_fotos
                 WHERE os_id = :id";
    $params = [':id'=>$id];
    if ($tid){
      $sqlFotos .= " AND (tenant_id=:t OR tenant_id IS NULL)";
      $params[':t'] = $tid;
    }
    $sqlFotos .= " ORDER BY id DESC";
    $st = $pdo->prepare($sqlFotos);
    $st->execute($params);
    $fotos = $st->fetchAll(PDO::FETCH_ASSOC);
  }catch(Throwable $e){ $fotos = []; }
}
$fotosQtde = is_array($fotos) ? count($fotos) : 0;

// ---------- Garantia dias (derivado de data_abertura x garantia_ate) ----------
$garantiaDiasEdit = '';
if ($os && !empty($os['garantia_ate'])) {
  $ini = strtotime($os['data_abertura'] ?? $os['created_at'] ?? 'now');
  $end = strtotime($os['garantia_ate']);
  if ($ini && $end && $end >= $ini) $garantiaDiasEdit = (string)ceil(($end - $ini) / 86400);
}
?>
<?php include __DIR__.'/_sidebar.php'; ?>
<main class="hf-content">
  <div class="d-flex align-items-center mb-3">
    <h4 class="mb-0">
      <?= $id>0 ? 'Editar OS' : 'Nova OS' ?>
      <?php if ($id>0 && !empty($os['numero'])): ?>
        <small class="text-muted">#<?= (int)$os['numero']; ?></small>
      <?php endif; ?>
    </h4>

    <div class="ms-auto d-flex gap-2">
      <?php if ($id>0): ?>
        <!-- Botão para abrir o documento da OS -->
        <a href="/os_documento.php?id=<?= (int)$id ?>"
           target="_blank"
           class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-file-earmark-text"></i> Documento
        </a>
      <?php endif; ?>

      <a class="btn btn-outline-secondary btn-sm" href="/os_list.php">
        <i class="bi bi-arrow-left"></i> Voltar
      </a>
    </div>
  </div>

  <!-- IMPORTANTE: enctype para upload -->
  <form class="hf-card p-3" method="post" action="/os_save.php" id="osForm" enctype="multipart/form-data">
    <input type="hidden" name="id" value="<?= (int)$id ?>">

    <!-- Cliente -->
    <div class="card mb-3">
      <div class="card-header">
        <strong>Cliente</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Cliente*</label>
            <div class="input-group">
              <select class="form-select" name="cliente_id" id="cliSelect" required>
                <option value="">Selecione...</option>
                <?php foreach($clientes as $c): ?>
                  <option value="<?= (int)$c['id'] ?>"
                          data-garantia="<?= (int)($c['default_garantia_dias'] ?? 0) ?>"
                          <?= $os && (int)$os['cliente_id']===(int)$c['id'] ? 'selected':'' ?>>
                    <?= htmlspecialchars($c['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#modalCliente" title="Cadastrar cliente">
                <i class="bi bi-person-plus"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Execução -->
    <div class="card mb-3">
      <div class="card-header">
        <strong>Execução</strong>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
              <?php foreach(['aberta','em_andamento','concluida','cancelada'] as $s): ?>
                <option value="<?= $s ?>" <?= $os && $os['status']===$s ? 'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-3">
            <label class="form-label">Prioridade</label>
            <select class="form-select" name="prioridade">
              <?php foreach(['baixa','media','alta'] as $p): ?>
                <option value="<?= $p ?>" <?= $os && $os['prioridade']===$p ? 'selected':'' ?>><?= ucfirst($p) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Técnico</label>
            <input class="form-control" name="tecnico" maxlength="120" value="<?= htmlspecialchars($os['tecnico'] ?? '') ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Defeito reclamado</label>
            <textarea class="form-control" name="defeito" rows="2"><?= htmlspecialchars($os['defeito'] ?? '') ?></textarea>
          </div>

          <div class="col-12">
            <label class="form-label">Laudo / Observações</label>
            <textarea class="form-control" name="laudo" rows="2"><?= htmlspecialchars($os['laudo'] ?? '') ?></textarea>
          </div>

          <div class="col-md-5 col-lg-4">
            <label class="form-label">Garantia (dias)</label>
            <div class="input-group">
              <input type="number" min="0" step="1" class="form-control" name="garantia_dias" id="garantiaDias"
                     value="<?= htmlspecialchars($garantiaDiasEdit) ?>">
              <button class="btn btn-outline-secondary btn-sm" type="button" data-gd="15">15</button>
              <button class="btn btn-outline-secondary btn-sm" type="button" data-gd="30">30</button>
              <button class="btn btn-outline-secondary btn-sm" type="button" data-gd="60">60</button>
              <button class="btn btn-outline-secondary btn-sm" type="button" data-gd="90">90</button>
            </div>
            <small class="text-muted">A data final será calculada automaticamente.</small>
          </div>

          <input type="hidden" name="valor_mao_obra" value="0,00">
        </div>
      </div>
    </div>

    <!-- Itens -->
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <strong>Itens</strong>
        <div class="ms-auto d-flex gap-2">
          <button type="button" class="btn btn-primary btn-sm" id="addProd">
            <i class="bi bi-plus-lg"></i> Produto
          </button>
          <button type="button" class="btn btn-success btn-sm" id="addServ">
            <i class="bi bi-plus-lg"></i> Serviço
          </button>
        </div>
      </div>
      <div class="card-body">
        <div class="d-flex align-items-center mb-2" id="itensActions">
          <h6 class="mb-0">Produtos e serviços da OS</h6>
        </div>

        <div class="table-responsive itens-responsive">
          <table class="table table-sm align-middle" id="itensTable">
            <thead class="table-light">
              <tr>
                <th style="width:110px">Tipo</th>
                <th>Ref.</th>
                <th>Descrição</th>
                <th style="width:110px" class="text-end">Qtd</th>
                <th style="width:140px" class="text-end">Vlr Unit (R$)</th>
                <th style="width:140px" class="text-end">Total (R$)</th>
                <th style="width:70px"></th>
              </tr>
            </thead>
            <tbody id="itensBody">
              <?php if (empty($itens)): ?>
                <tr id="emptyRow">
                  <td colspan="7" class="text-center text-muted py-3">
                    Nenhum item. Use <b>+ Produto</b> ou <b>+ Serviço</b>.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <!-- Financeiro -->
    <div class="card mb-3">
      <div class="card-header">
        <strong>Financeiro</strong>
      </div>
      <div class="card-body">
        <!-- Totais -->
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Desconto (R$)</label>
            <input class="form-control" name="desconto"
                   value="<?= number_format((float)($os['desconto'] ?? 0),2,',','.') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Acréscimo (R$)</label>
            <input class="form-control" name="acrescimo"
                   value="<?= number_format((float)($os['acrescimo'] ?? 0),2,',','.') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Total (R$)</label>
            <input class="form-control" name="total" id="totalGeral" readonly
                   value="<?= number_format((float)($os['total'] ?? 0),2,',','.') ?>">
          </div>
        </div>

        <hr class="my-3">

        <!-- Pagamento -->
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Status financeiro</label>
            <select class="form-select" name="status_financeiro">
              <?php
                $sf = $os['status_financeiro'] ?? 'pendente';
                foreach (['pendente'=>'Pendente','parcial'=>'Parcial','pago'=>'Pago'] as $k=>$label):
              ?>
                <option value="<?= $k ?>" <?= $sf === $k ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Forma de pagamento</label>
            <select class="form-select" name="forma_pagto">
              <?php
                $fp = $os['forma_pagto'] ?? '';
                $opts = [
                  ''          => 'Selecione...',
                  'dinheiro'  => 'Dinheiro',
                  'cartao'    => 'Cartão',
                  'pix'       => 'Pix',
                  'boleto'    => 'Boleto',
                  'transferencia' => 'Transferência',
                ];
                foreach ($opts as $k=>$label):
              ?>
                <option value="<?= $k ?>" <?= $fp === $k ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label">Valor pago (R$)</label>
            <input class="form-control" name="valor_pago"
                   value="<?= number_format((float)($os['valor_pago'] ?? 0),2,',','.') ?>">
          </div>

          <div class="col-md-2">
            <label class="form-label">Data pagamento</label>
            <input type="date" class="form-control" name="data_pagto"
                   value="<?= !empty($os['data_pagto']) ? htmlspecialchars(substr($os['data_pagto'],0,10)) : '' ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- Fotos do produto -->
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center">
        <strong>Fotos</strong>
        <button class="btn btn-outline-secondary btn-sm ms-auto"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#fotosProdutoCollapse"
                aria-expanded="false"
                aria-controls="fotosProdutoCollapse">
          <i class="bi bi-camera"></i> Adicionar fotos do produto
        </button>
      </div>

      <div class="collapse" id="fotosProdutoCollapse">
        <div class="card-body">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <span class="text-muted small">Fotos do produto (estado de chegada)</span>
            <?php if ($id>0): ?>
              <small class="text-muted">Upload instantâneo — limite 2 MB por foto.</small>
            <?php else: ?>
              <small class="text-muted">Salve a OS primeiro para habilitar consulta das fotos.</small>
            <?php endif; ?>
          </div>

          <?php if ($id>0): ?>
            <!-- Uploader assíncrono -->
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
              <label class="btn btn-primary btn-sm mb-0" for="fotoAsync">
                <i class="bi bi-camera"></i> Tirar foto / Anexar
              </label>
              <input type="file" id="fotoAsync" accept="image/*" capture="environment" multiple style="display:none"
                     data-os-id="<?= (int)$id ?>">
              <button type="button"
                      id="btnVerFotos"
                      class="btn btn-outline-secondary btn-sm"
                      data-bs-toggle="modal"
                      data-bs-target="#modalFotos">
                <i class="bi bi-images"></i>
                Ver fotos (<span id="fotoCount"><?= $fotosQtde ?></span>)
              </button>
              <small class="text-muted d-block d-md-inline">
                Máx 10 imagens por envio, até 2 MB cada (JPG/PNG/GIF).
              </small>
            </div>
            <div id="uploadMsg" class="small text-muted"></div>
          <?php else: ?>
            <!-- Fallback para OS nova (sem id ainda) -->
            <p class="text-muted mb-2">
              No celular, você pode tirar foto direto da câmera. As imagens serão salvas ao clicar em <b>Salvar</b>.
            </p>
            <div class="d-flex align-items-center gap-2 mb-2">
              <label class="btn btn-primary btn-sm mb-0" for="fotos">
                <i class="bi bi-camera"></i> Tirar foto / Anexar
              </label>
              <input type="file" id="fotos" name="fotos[]" accept="image/*" capture="environment" multiple style="display:none">
              <small class="text-muted">Máx 10 imagens, até 2 MB cada. JPG/PNG/GIF.</small>
            </div>
            <div id="preview" class="d-flex flex-wrap" style="gap:10px"></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
      <a class="btn btn-outline-secondary" href="/os_list.php">Cancelar</a>
      <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Salvar</button>
    </div>
  </form>
</main>

<!-- Modal: Novo Cliente (cadastro rápido) -->
<div class="modal fade" id="modalCliente" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="formQuickCliente">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-plus"></i> Novo Cliente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Nome*</label>
            <input name="nome" class="form-control" required maxlength="150">
          </div>
          <div class="col-md-6">
            <label class="form-label">Documento (CPF/CNPJ)</label>
            <input name="documento" class="form-control" maxlength="20">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" maxlength="120">
          </div>
          <div class="col-md-6">
            <label class="form-label">Celular</label>
            <input name="celular" class="form-control" maxlength="30" placeholder="(11) 9 9999-9999">
          </div>
        </div>
        <div id="quickCliMsg" class="text-danger small mt-2" style="display:none"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-check-lg"></i> Salvar e usar
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Galeria de fotos -->
<?php if ($id>0): ?>
<div class="modal fade" id="modalFotos" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <i class="bi bi-images"></i>
          Fotos da OS #<?= htmlspecialchars($os['numero'] ?? $id) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div id="galeria" class="hf-foto-grid">
          <?php if (!empty($fotos)): foreach($fotos as $f):
            $thumb = $f['thumb'] ?: $f['caminho']; ?>
            <div class="hf-foto-card" data-id="<?= (int)$f['id'] ?>">
              <a href="/<?= htmlspecialchars($f['caminho']) ?>" target="_blank" rel="noopener">
                <img src="/<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($f['original_nome'] ?? 'foto') ?>">
              </a>
              <button type="button" class="btn btn-sm btn-outline-danger hf-foto-del" data-id="<?= (int)$f['id'] ?>">
                <i class="bi bi-trash"></i>
              </button>
            </div>
          <?php endforeach; else: ?>
            <div class="text-muted" id="noFotos">Nenhuma foto anexada ainda.</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__.'/_layout_end.php'; ?>

<!-- ====== CSS: Itens + Galeria ====== -->
<style>
#itensActions{
  position:sticky;
  top:0;
  z-index:5;
  background:#fff;
  padding:.5rem;
  border:1px solid #e9ecef;
  border-radius:.5rem;
  margin-bottom:.75rem
}
@media (min-width:768px){
  .itens-responsive table{table-layout:fixed}
  .itens-responsive .form-control-sm,
  .itens-responsive .form-select-sm{min-height:34px}
}
@media (max-width:767.98px){
  .itens-responsive table,
  .itens-responsive thead,
  .itens-responsive tbody,
  .itens-responsive th,
  .itens-responsive td,
  .itens-responsive tr{
    display:block;
    width:100%
  }
  .itens-responsive thead{display:none}
  .itens-responsive tbody tr{
    border:1px solid #e9ecef;
    border-radius:.75rem;
    padding:.75rem;
    margin-bottom:.75rem;
    background:#fff;
    box-shadow:0 1px 2px rgba(0,0,0,.03)
  }
  .itens-responsive td{
    display:grid;
    grid-template-columns:minmax(88px,42%) 1fr;
    align-items:center;
    gap:.5rem;
    padding:.35rem 0;
    border:0!important
  }
  .itens-responsive td[data-label="Ações"]{
    display:flex;
    justify-content:flex-end;
    gap:.5rem;
    margin-top:.25rem
  }
  .itens-responsive td::before{
    content:attr(data-label);
    font-size:.84rem;
    color:#6c757d
  }
  .itens-responsive .form-control-sm,
  .itens-responsive .form-select-sm{
    font-size:1rem;
    padding:.5rem .65rem;
    min-height:42px
  }
  .itens-responsive .total{
    font-weight:600;
    font-size:1.05rem;
    color:#0d6efd;
    text-align:right
  }
}

/* ===== Galeria de fotos ===== */
.hf-foto-grid{
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(110px, 1fr));
  gap:.75rem;
}
.hf-foto-card{
  position:relative;
  border:1px solid #e9ecef;
  border-radius:.5rem;
  overflow:hidden;
  background:#fff;
}
.hf-foto-card img{
  width:100%;
  height:110px;
  object-fit:cover;
  display:block;
}
.hf-foto-card .hf-foto-del{
  position:absolute;
  top:.35rem;
  right:.35rem;
  padding:.2rem .4rem;
  border-radius:.35rem;
  backdrop-filter:blur(2px);
}
@media (max-width:575.98px){
  .hf-foto-grid{
    grid-template-columns:repeat(3,1fr);
  }
}
</style>
<script>
// Catálogos p/ front
const PRODUTOS = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE) ?>;
const SERVICOS = <?= json_encode($servicos, JSON_UNESCAPED_UNICODE) ?>;
const MAX_FILE_MB = 2;
const MAX_FILE_BYTES = MAX_FILE_MB * 1024 * 1024;

// utils: BR <-> número
function toNumberBR(v){
  v=(v||'').toString().trim(); if(!v) return 0;
  v=v.replace(/\./g,'').replace(',','.');
  return parseFloat(v)||0;
}
function toBR(n){
  return (Number(n)||0).toFixed(2).replace('.',',');
}

const tbody = document.getElementById('itensBody');

function updateEmptyHint(){
  const rows = Array.from(tbody.querySelectorAll('tr')).filter(tr=>tr.id!=='emptyRow');
  let hint = tbody.querySelector('#emptyRow');
  if (rows.length===0 && !hint){
    const tr = document.createElement('tr');
    tr.id='emptyRow';
    tr.innerHTML = `<td colspan="7" class="text-center text-muted py-3">
      Nenhum item. Use <b>+ Produto</b> ou <b>+ Serviço</b>.
    </td>`;
    tbody.appendChild(tr);
  }
  if (rows.length>0 && hint) hint.remove();
}

// linha com data-labels p/ mobile
function rowTemplate(item){
  const tipo = item?.tipo || 'P';
  const desc = (item?.descricao || '').replace(/"/g,'&quot;');
  const qtd  = item?.qtd || 1;
  const vu   = item?.valor_unit || 0;
  const tot  = (qtd*vu)||0;

  return `<tr>
    <td data-label="Tipo">
      <select name="item_tipo[]" class="form-select form-select-sm tipo">
        <option value="P" ${tipo==='P'?'selected':''}>Produto</option>
        <option value="S" ${tipo==='S'?'selected':''}>Serviço</option>
      </select>
    </td>
    <td data-label="Ref.">
      <select name="item_ref[]" class="form-select form-select-sm ref"></select>
    </td>
    <td data-label="Descrição">
      <input name="item_desc[]" class="form-control form-control-sm desc" value="${desc}">
    </td>
    <td data-label="Qtd">
      <input name="item_qtd[]" class="form-control form-control-sm text-end qtd" value="${toBR(qtd)}">
    </td>
    <td data-label="Vlr Unit (R$)">
      <input name="item_vu[]"  class="form-control form-control-sm text-end vu"  value="${toBR(vu)}">
    </td>
    <td data-label="Total" class="text-end total">${toBR(tot)}</td>
    <td data-label="Ações" class="text-end">
      <button type="button" class="btn btn-sm btn-outline-danger del"><i class="bi bi-trash"></i></button>
    </td>
  </tr>`;
}

function recalc(){
  let soma = 0;
  tbody.querySelectorAll('tr').forEach(tr=>{
    if (tr.id==='emptyRow') return;
    const qtd = toNumberBR(tr.querySelector('.qtd').value);
    const vu  = toNumberBR(tr.querySelector('.vu').value);
    const tot = (qtd*vu)||0;
    tr.querySelector('.total').textContent = toBR(tot);
    soma += tot;
  });
  const mao = toNumberBR(document.querySelector('[name="valor_mao_obra"]').value);
  const desc= toNumberBR(document.querySelector('[name="desconto"]').value);
  const acr = toNumberBR(document.querySelector('[name="acrescimo"]').value);
  document.getElementById('totalGeral').value = toBR(soma + mao - desc + acr);
}

function bindRow(tr){
  const tipo = tr.querySelector('.tipo');
  const ref  = tr.querySelector('.ref');
  const desc = tr.querySelector('.desc');
  const qtd  = tr.querySelector('.qtd');
  const vu   = tr.querySelector('.vu');

  function loadRefOptions(){
    const isP = (tipo.value==='P');
    const list = isP ? PRODUTOS : SERVICOS;
    ref.innerHTML = list.map(i=>`<option value="${i.id}" data-preco="${i.preco}">${i.nome}</option>`).join('');
    const cur = ref.getAttribute('data-selected'); if (cur) ref.value = cur;

    const sel = list.find(i=>String(i.id)===String(ref.value));
    if (!desc.value && sel) desc.value = sel.nome;
    if (!toNumberBR(vu.value) && sel) vu.value = toBR(sel.preco||0);
    recalc();
  }

  tipo.addEventListener('change', ()=>{
    ref.setAttribute('data-selected','');
    loadRefOptions();
  });
  ref.addEventListener('change', ()=>{
    const opt = ref.options[ref.selectedIndex];
    const preco = parseFloat(opt?.dataset?.preco||'0')||0;
    if (!desc.value) desc.value = opt.textContent.trim();
    vu.value = toBR(preco);
    recalc();
  });

  [qtd,vu].forEach(el=>el.addEventListener('input', recalc));

  tr.querySelector('.del').addEventListener('click', ()=>{
    tr.remove();
    updateEmptyHint();
    recalc();
  });

  loadRefOptions();
}

function addItem(tipo='P', payload=null){
  const hint = tbody.querySelector('#emptyRow'); if (hint) hint.remove();
  const temp = document.createElement('tbody');
  temp.innerHTML = rowTemplate({tipo, ...(payload||{})});
  const tr = temp.firstElementChild;
  tbody.appendChild(tr);
  if (payload?.ref_id) tr.querySelector('.ref').setAttribute('data-selected', String(payload.ref_id));
  bindRow(tr);
  updateEmptyHint();
}

document.getElementById('addProd').addEventListener('click', ()=>addItem('P'));
document.getElementById('addServ').addEventListener('click', ()=>addItem('S'));

// Preload itens (edição)
<?php if ($itens && count($itens)): ?>
(function preload(){
  const items = <?= json_encode($itens, JSON_UNESCAPED_UNICODE) ?>;
  items.forEach(i=>{
    addItem(i.tipo, {
      ref_id: i.ref_id,
      descricao: i.descricao,
      qtd: parseFloat(i.qtd),
      valor_unit: parseFloat(i.valor_unit)
    });
  });
  updateEmptyHint();
  recalc();
})();
<?php else: ?>
updateEmptyHint();
recalc();
<?php endif; ?>
</script>

<!-- Preview local das fotos (somente OS nova, id=0) + limite 2 MB -->
<script>
(function(){
  var input = document.getElementById('fotos');
  var preview = document.getElementById('preview');
  if(!input || !preview) return;

  input.addEventListener('change', function(){
    preview.innerHTML = '';
    var files = Array.prototype.slice.call(this.files || []);
    if(files.length > 10){
      alert('Limite de 10 imagens por vez.');
      this.value = '';
      return;
    }
    files.forEach(function(f){
      if(!/^image\//i.test(f.type)) return;
      if(f.size > MAX_FILE_BYTES){
        alert('Arquivo acima de ' + MAX_FILE_MB + ' MB ignorado: ' + f.name);
        return;
      }
      var reader = new FileReader();
      reader.onload = function(e){
        var img = document.createElement('img');
        img.src = e.target.result;
        img.className = 'img-thumbnail';
        img.style.width = '120px';
        img.style.height = '120px';
        img.style.objectFit = 'cover';
        preview.appendChild(img);
      };
      reader.readAsDataURL(f);
    });
  });
})();
</script>

<!-- Upload assíncrono de fotos (OS já existente) + excluir + limite 2 MB -->
<script>
(function(){
  const up = document.getElementById('fotoAsync');
  const gal = document.getElementById('galeria');
  const countEl = document.getElementById('fotoCount');
  const msgEl = document.getElementById('uploadMsg');
  if(!up) return;

  function setMsg(txt){
    if(!msgEl) return;
    msgEl.textContent = txt || '';
  }

  async function uploadFiles(files){
    if(!files || !files.length) return;
    const osId = up.getAttribute('data-os-id');
    const fd = new FormData();
    fd.append('os_id', osId);

    let validFiles = 0;
    for (let i=0;i<files.length;i++){
      const f = files[i];
      if (!/^image\//i.test(f.type)) continue;
      if (f.size > MAX_FILE_BYTES){
        alert('Arquivo acima de ' + MAX_FILE_MB + ' MB ignorado: ' + f.name);
        continue;
      }
      fd.append('fotos[]', f);
      validFiles++;
    }
    if (!validFiles){
      up.value = '';
      return;
    }

    const btnLabel = up.previousElementSibling;
    if (btnLabel) btnLabel.classList.add('disabled');
    setMsg('Enviando fotos...');

    try{
      const r = await fetch('/os_foto_upload.php', { method:'POST', body: fd });
      const j = await r.json();
      if(!j.ok){
        alert(j.msg || 'Falha ao enviar');
        setMsg('');
        return;
      }

      setMsg('Upload concluído.');
      const noFotos = document.getElementById('noFotos');
      if (noFotos) noFotos.remove();

      if (gal){
        (j.fotos || []).forEach(f=>{
          const card = document.createElement('div');
          card.className = 'hf-foto-card';
          card.setAttribute('data-id', f.id);
          card.innerHTML = `
            <a href="/${f.caminho}" target="_blank" rel="noopener">
              <img src="/${(f.thumb||f.caminho)}" alt="${(f.original_nome||'foto')}">
            </a>
            <button type="button" class="btn btn-sm btn-outline-danger hf-foto-del" data-id="${f.id}">
              <i class="bi bi-trash"></i>
            </button>`;
          gal.prepend(card);
        });
      }
      if (countEl){
        const atual = parseInt(countEl.textContent || '0',10) || 0;
        countEl.textContent = atual + (j.fotos ? j.fotos.length : 0);
      }
    }catch(e){
      alert('Erro de comunicação no upload.');
      setMsg('');
    }finally{
      if (btnLabel) btnLabel.classList.remove('disabled');
      up.value = '';
      setTimeout(()=>setMsg(''), 3000);
    }
  }

  up.addEventListener('change', ()=> uploadFiles(up.files));

  if (gal){
    // Excluir foto (AJAX)
    gal.addEventListener('click', async (ev)=>{
      const btn = ev.target.closest('.hf-foto-del');
      if(!btn) return;
      if(!confirm('Excluir esta foto?')) return;

      const id = btn.getAttribute('data-id');
      const fd = new FormData();
      fd.append('id', id);

      try{
        const r = await fetch('/os_foto_delete.php', { method:'POST', body: fd });
        const j = await r.json();
        if(!j.ok){
          alert(j.msg || 'Falha ao excluir');
          return;
        }

        const card = btn.closest('.hf-foto-card');
        if (card) card.remove();

        if (!document.querySelector('.hf-foto-card')){
          const p = document.createElement('div');
          p.id = 'noFotos';
          p.className = 'text-muted';
          p.textContent = 'Nenhuma foto anexada ainda.';
          gal.appendChild(p);
        }

        if (countEl){
          const atual = parseInt(countEl.textContent || '0',10) || 0;
          countEl.textContent = Math.max(0, atual-1);
        }
      }catch(e){
        alert('Erro de comunicação ao excluir.');
      }
    });
  }
})();
</script>

<!-- Garantia: presets + auto pela seleção do cliente -->
<script>
document.querySelectorAll('[data-gd]').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const v = btn.getAttribute('data-gd');
    const inp = document.getElementById('garantiaDias');
    if (inp){
      inp.value = v;
      inp.dispatchEvent(new Event('input'));
    }
  });
});

(function(){
  const sel = document.getElementById('cliSelect');
  const inp = document.getElementById('garantiaDias');
  if (!sel || !inp) return;
  sel.addEventListener('change', ()=>{
    const opt = sel.options[sel.selectedIndex];
    const pad = parseInt(opt?.dataset?.garantia || '0', 10);
    if (!inp.value && pad>0) {
      inp.value = pad;
      inp.dispatchEvent(new Event('input'));
    }
  });
})();
</script>

<!-- Modal cliente: salvar rápido via AJAX -->
<script>
(function(){
  const form = document.getElementById('formQuickCliente');
  const msg  = document.getElementById('quickCliMsg');
  const sel  = document.getElementById('cliSelect');
  const modalEl = document.getElementById('modalCliente');
  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  if(!form) return;

  form.addEventListener('submit', async function(e){
    e.preventDefault();
    msg.style.display='none'; msg.textContent='';

    const fd = new FormData(form);
    try{
      const r = await fetch('/cliente_quick_save.php', { method:'POST', body: fd });
      const j = await r.json();
      if(!j.ok){
        msg.textContent = j.msg || 'Erro ao salvar';
        msg.style.display='block';
        return;
      }

      const op = document.createElement('option');
      op.value = j.id;
      op.textContent = j.nome;
      sel.appendChild(op);
      sel.value = String(j.id);

      modal.hide();
      form.reset();
    }catch(err){
      msg.textContent = 'Falha na comunicação';
      msg.style.display = 'block';
    }
  });

  modalEl.addEventListener('shown.bs.modal', ()=>{
    form.querySelector('[name="nome"]').focus();
  });
})();
</script>

<!-- Força abertura do modal de fotos via JS (desktop + mobile) -->
<script>
(function(){
  const btn = document.getElementById('btnVerFotos');
  const modalEl = document.getElementById('modalFotos');
  if(!btn || !modalEl) return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

  btn.addEventListener('click', function(e){
    e.preventDefault();
    modal.show();
  });
})();
</script>
